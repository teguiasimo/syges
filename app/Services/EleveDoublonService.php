<?php

namespace App\Services;

use App\Models\ArchiveAdministrative;
use App\Models\Assiduite;
use App\Models\CompteFinancierEleve;
use App\Models\DeliberationConseil;
use App\Models\DisciplineBulletin;
use App\Models\DispenseEleve;
use App\Models\DispenseFinanciere;
use App\Models\DossierConseilDiscipline;
use App\Models\Eleve;
use App\Models\EleveBourseRemise;
use App\Models\EleveDoublonIgnore;
use App\Models\EleveFusionAudit;
use App\Models\HeureConsigne;
use App\Models\HistoriqueSms;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\PenaliteEleve;
use App\Models\SanctionDisciplinaire;
use App\Models\SouscriptionFraisOptionnel;
use App\Models\User;
use App\Models\VersementCaisse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service métier centralisé pour la prévention, la détection et la fusion sécurisée
 * des doublons d'élèves dans SYGES.
 *
 * Responsabilités principales :
 * 1. Détection temps réel de profils similaires lors de la saisie d'un nouvel élève (sans bloquer les jumeaux).
 * 2. Scan rapide et groupé des paires suspectes sur une année scolaire / un établissement.
 * 3. Prévisualisation comparative côte à côte incluant la détection exhaustive des conflits de notes.
 * 4. Fusion transactionnelle atomique (`DB::transaction`) transférant paiements, reçus, bourses, dispenses,
 *    frais annexes, notes, discipline, délibérations et archives vers le dossier Maître, avec audit complet.
 */
class EleveDoublonService
{
    /**
     * Champs civils et de contact comparables / sélectionnables lors d'une fusion.
     *
     * @var list<string>
     */
    public const IDENTITY_FIELDS = [
        'nom',
        'prenom',
        'matricule_national',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'photo_url',
        'nom_pere',
        'nom_mere',
        'nom_tuteur',
        'telephone_parent',
        'email_parent',
        'adresse',
    ];

    /**
     * Normalise une chaîne de texte pour la comparaison floue (sans accents, majuscules, espaces propres).
     *
     * @param  string|null  $value  Chaîne brute à normaliser.
     * @return string Chaîne normalisée prête pour comparaison.
     */
    public function normaliserTexte(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $ascii = Str::ascii(trim($value));
        $upper = mb_strtoupper($ascii, 'UTF-8');
        $clean = preg_replace('/[^A-Z0-9\s]/', ' ', $upper) ?? $upper;

        return trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
    }

    /**
     * Normalise un numéro de téléphone (chiffres uniquement, indicatif Cameroun 237 retiré).
     *
     * @param  string|null  $phone  Numéro de téléphone saisi.
     * @return string Numéro normalisé.
     */
    public function normaliserTelephone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00237')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '237') && strlen($digits) > 9) {
            $digits = substr($digits, 3);
        }

        return $digits;
    }

    /**
     * Évalue le score de ressemblance (0 à 100) et les motifs explicites entre deux dossiers élèves.
     *
     * Règles métier adaptées au contexte camerounais :
     * - Un même téléphone parent seul avec des noms différents (fratrie) n'est jamais signalé.
     * - Un même nom de famille dans la même classe (même avec un prénom orthographié différemment) est détecté.
     * - Les inversions nom/prénom et fautes de frappe proches (Levenshtein ≤ 2) sont identifiées.
     *
     * @param  array<string, mixed>  $a  Données normalisées ou brutes du premier élève.
     * @param  array<string, mixed>  $b  Données normalisées ou brutes du second élève.
     * @return array{score: int, raisons: list<string>} Score sur 100 et liste des motifs.
     */
    public function evaluerRessemblance(array $a, array $b): array
    {
        $nomA = $this->normaliserTexte($a['nom'] ?? null);
        $nomB = $this->normaliserTexte($b['nom'] ?? null);
        $prenomA = $this->normaliserTexte($a['prenom'] ?? null);
        $prenomB = $this->normaliserTexte($b['prenom'] ?? null);

        $fullA = trim($nomA.' '.$prenomA);
        $fullB = trim($nomB.' '.$prenomB);

        if ($nomA === '' || $nomB === '') {
            return ['score' => 0, 'raisons' => []];
        }

        $score = 0;
        $raisons = [];

        $sameClass = ! empty($a['classe_id']) && ! empty($b['classe_id'])
            && (int) $a['classe_id'] === (int) $b['classe_id'];

        $dobA = ! empty($a['date_naissance']) ? substr((string) $a['date_naissance'], 0, 10) : '';
        $dobB = ! empty($b['date_naissance']) ? substr((string) $b['date_naissance'], 0, 10) : '';
        $sameDob = $dobA !== '' && $dobB !== '' && $dobA === $dobB;

        $telA = $this->normaliserTelephone($a['telephone_parent'] ?? null);
        $telB = $this->normaliserTelephone($b['telephone_parent'] ?? null);
        $sameTel = strlen($telA) >= 8 && $telA === $telB;

        // 1. Comparaison sur l'identité complète et les inversions Nom / Prénom
        if ($fullA !== '' && $fullA === $fullB) {
            $score = 92;
            $raisons[] = 'Nom et prénom identiques';
        } elseif ($prenomA !== '' && $prenomB !== '' && $nomA === $prenomB && $prenomA === $nomB) {
            $score = 88;
            $raisons[] = 'Nom et prénom inversés';
        } elseif ($nomA === $nomB) {
            // Même nom de famille exact
            if ($prenomA !== '' && $prenomB !== '') {
                $distPrenom = levenshtein($prenomA, $prenomB);
                similar_text($prenomA, $prenomB, $pctPrenom);
                $containsPrenom = str_contains($prenomA, $prenomB) || str_contains($prenomB, $prenomA);

                if ($distPrenom <= 2 || $pctPrenom >= 72.0 || $containsPrenom) {
                    $score = 84;
                    $raisons[] = 'Même nom et prénom très proche';
                } elseif ($sameClass) {
                    $score = 72;
                    $raisons[] = 'Même nom de famille dans la même classe';
                } elseif ($sameDob) {
                    $score = 78;
                    $raisons[] = 'Même nom de famille et même date de naissance';
                } else {
                    $score = 45;
                }
            } elseif ($sameClass) {
                $score = 75;
                $raisons[] = 'Même nom de famille dans la même classe (prénom incomplet)';
            } elseif ($sameDob) {
                $score = 80;
                $raisons[] = 'Même nom de famille et même date de naissance';
            } else {
                $score = 50;
            }
        } else {
            // Noms de famille légèrement différents (faute de frappe ou nom composé partiel)
            $distNom = levenshtein($nomA, $nomB);
            $nomPartage = (strlen($nomA) >= 4 && strlen($nomB) >= 4)
                && ($distNom <= 2 || str_contains($nomA, $nomB) || str_contains($nomB, $nomA));

            if ($nomPartage) {
                if ($prenomA !== '' && $prenomB !== '') {
                    $distPrenom = levenshtein($prenomA, $prenomB);
                    similar_text($prenomA, $prenomB, $pctPrenom);
                    if ($distPrenom <= 2 || $pctPrenom >= 75.0) {
                        $score = 80;
                        $raisons[] = 'Orthographe du nom très proche et même prénom';
                    } elseif ($sameClass) {
                        $score = 68;
                        $raisons[] = 'Nom très proche dans la même classe';
                    }
                } elseif ($sameClass) {
                    $score = 68;
                    $raisons[] = 'Nom très proche dans la même classe';
                }
            } else {
                // Vérification sur la chaîne complète (ex: nom composé coupé différemment entre Nom et Prénom)
                similar_text($fullA, $fullB, $pctFull);
                if ($pctFull >= 84.0 && ($sameClass || $sameDob || $sameTel)) {
                    $score = (int) round($pctFull);
                    $raisons[] = 'Identité complète très proche ('.round($pctFull).' %)';
                }
            }
        }

        // Si aucune correspondance de nom suffisante, ne pas signaler de simples camarades ou frères
        if ($score < 45) {
            return ['score' => 0, 'raisons' => []];
        }

        // 2. Bonus contextuels (classe, date de naissance, téléphone parent)
        if ($sameClass) {
            $score += 10;
            if (! in_array('Même nom de famille dans la même classe', $raisons, true)
                && ! in_array('Même nom de famille dans la même classe (prénom incomplet)', $raisons, true)
                && ! in_array('Nom très proche dans la même classe', $raisons, true)) {
                $raisons[] = 'Même classe';
            }
        }

        if ($sameDob) {
            $score += 15;
            if (! in_array('Même nom de famille et même date de naissance', $raisons, true)) {
                $raisons[] = 'Même date de naissance';
            }
        }

        if ($sameTel) {
            $score += 12;
            $raisons[] = 'Même téléphone parent';
        }

        return [
            'score' => min(100, $score),
            'raisons' => array_values(array_unique($raisons)),
        ];
    }

    /**
     * Détecte en temps réel les élèves existants ressemblant à la saisie en cours
     * lors de l'enregistrement d'un nouvel élève.
     *
     * @param  array<string, mixed>  $payload  Champs saisis (nom, prenom, classe_id, annee_scolaire_id, date_naissance, telephone_parent).
     * @param  int|null  $etablissementId  Identifiant de l'établissement actif (null si transversal).
     * @return list<array<string, mixed>> Top 5 des dossiers similaires triés par pertinence décroissante.
     */
    public function detecterSimilairesPourCreation(array $payload, ?int $etablissementId = null): array
    {
        $nomNorm = $this->normaliserTexte($payload['nom'] ?? null);
        if (strlen($nomNorm) < 2) {
            return [];
        }

        $anneeId = ! empty($payload['annee_scolaire_id']) ? (int) $payload['annee_scolaire_id'] : null;
        $firstToken = explode(' ', $nomNorm)[0];
        $prefix = substr($firstToken, 0, min(3, strlen($firstToken)));

        // Pré-filtrage SQL rapide pour éviter un scan complet sur les grandes bases
        $query = Inscription::withoutGlobalScopes()
            ->with([
                'eleve:id,matricule,nom,prenom,sexe,date_naissance,telephone_parent,photo_url',
                'classe:id,nom,etablissement_id',
                'etablissement:id,code,nom',
            ]);

        if ($anneeId) {
            $query->where('annee_scolaire_id', $anneeId);
        }

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }

        $telNorm = $this->normaliserTelephone($payload['telephone_parent'] ?? null);
        $classeId = ! empty($payload['classe_id']) ? (int) $payload['classe_id'] : null;

        $query->whereHas('eleve', function ($eq) use ($prefix, $telNorm, $classeId) {
            $eq->where('nom', 'like', '%'.$prefix.'%')
                ->orWhere('prenom', 'like', '%'.$prefix.'%');

            if (strlen($telNorm) >= 8) {
                $eq->orWhere('telephone_parent', 'like', '%'.substr($telNorm, -8).'%');
            }
            if ($classeId) {
                // Inclut les élèves de la même classe si une faute porte sur les 3 premières lettres du nom
                $eq->orWhereHas('inscriptions', fn ($iq) => $iq->where('classe_id', $classeId));
            }
        });

        $inscriptions = $query->limit(250)->get();

        $matches = [];
        $seenEleves = [];

        foreach ($inscriptions as $insc) {
            $el = $insc->eleve;
            if (! $el || isset($seenEleves[$el->id])) {
                continue;
            }
            $seenEleves[$el->id] = true;

            $eval = $this->evaluerRessemblance($payload, [
                'nom' => $el->nom,
                'prenom' => $el->prenom,
                'date_naissance' => $el->date_naissance?->format('Y-m-d'),
                'telephone_parent' => $el->telephone_parent,
                'classe_id' => $insc->classe_id,
                'etablissement_id' => $insc->etablissement_id,
            ]);

            if ($eval['score'] >= 65) {
                $matches[] = [
                    'eleve_id' => $el->id,
                    'matricule' => $el->matricule,
                    'nom' => $el->nom,
                    'prenom' => $el->prenom,
                    'nom_complet' => $el->nom_complet,
                    'sexe' => $el->sexe,
                    'date_naissance' => $el->date_naissance?->format('Y-m-d'),
                    'telephone_parent' => $el->telephone_parent,
                    'photo_url' => $el->photo_url,
                    'classe_id' => $insc->classe_id,
                    'classe_nom' => $insc->classe?->nom ?? 'Non assignée',
                    'etablissement_nom' => $insc->etablissement?->nom ?? '',
                    'score' => $eval['score'],
                    'raisons' => $eval['raisons'],
                ];
            }
        }

        usort($matches, fn ($x, $y) => $y['score'] <=> $x['score']);

        return array_slice($matches, 0, 5);
    }

    /**
     * Scanne les inscriptions d'une année scolaire (et éventuellement d'un campus)
     * pour détecter toutes les paires de doublons suspects en excluant les paires déjà ignorées.
     *
     * Utilise un regroupement par clé (première syllabe du nom + classe) pour rester très rapide (< 50 ms)
     * même sur des bases de plusieurs milliers d'élèves.
     *
     * @param  int|null  $etablissementId  ID de l'établissement ciblé (null = tous les campus accessibles).
     * @param  int  $anneeId  ID de l'année scolaire à analyser.
     * @param  list<int>  $accessibleEtabIds  Établissements autorisés si vue globale.
     * @return list<array<string, mixed>> Liste des paires suspectes triées par score décroissant.
     */
    public function scannerDoublons(?int $etablissementId, int $anneeId, array $accessibleEtabIds = []): array
    {
        $query = Inscription::withoutGlobalScopes()
            ->with([
                'eleve:id,matricule,nom,prenom,sexe,date_naissance,lieu_naissance,telephone_parent,photo_url',
                'classe:id,nom,etablissement_id',
                'etablissement:id,code,nom',
            ])
            ->where('annee_scolaire_id', $anneeId);

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        } elseif (! empty($accessibleEtabIds)) {
            $query->whereIn('etablissement_id', $accessibleEtabIds);
        }

        $inscriptions = $query->get();
        if ($inscriptions->count() < 2) {
            return [];
        }

        // Chargement en bloc des paires ignorées (jumeaux / vrais homonymes)
        $ignoredKeys = EleveDoublonIgnore::query()
            ->get(['eleve_min_id', 'eleve_max_id'])
            ->mapWithKeys(fn ($row) => ["{$row->eleve_min_id}:{$row->eleve_max_id}" => true])
            ->all();

        // Agrégats financiers & pédagogiques en requêtes groupées (0 N+1)
        $inscriptionIds = $inscriptions->pluck('id')->all();
        $comptesByInsc = CompteFinancierEleve::withoutGlobalScopes()
            ->whereIn('inscription_id', $inscriptionIds)
            ->get(['id', 'inscription_id', 'montant_net_exigible', 'total_paye', 'solde_restant'])
            ->keyBy('inscription_id');

        $compteIds = $comptesByInsc->pluck('id')->all();
        $versementsCountByCompte = ! empty($compteIds)
            ? VersementCaisse::withoutGlobalScopes()
                ->whereIn('compte_financier_id', $compteIds)
                ->where('statut', 'valide')
                ->selectRaw('compte_financier_id, COUNT(*) as cnt')
                ->groupBy('compte_financier_id')
                ->pluck('cnt', 'compte_financier_id')
                ->all()
            : [];

        $notesCountByInsc = Note::query()
            ->whereIn('inscription_id', $inscriptionIds)
            ->selectRaw('inscription_id, COUNT(*) as cnt')
            ->groupBy('inscription_id')
            ->pluck('cnt', 'inscription_id')
            ->all();

        // Construction des enregistrements normalisés et seaux de comparaison (buckets)
        $items = [];
        $buckets = [];

        foreach ($inscriptions as $insc) {
            $el = $insc->eleve;
            if (! $el || isset($items[$el->id])) {
                continue;
            }

            $compte = $comptesByInsc->get($insc->id);
            $nomNorm = $this->normaliserTexte($el->nom);
            $prenomNorm = $this->normaliserTexte($el->prenom);
            $firstWord = explode(' ', $nomNorm)[0] ?? '';

            $items[$el->id] = [
                'eleve_id' => $el->id,
                'inscription_id' => $insc->id,
                'matricule' => $el->matricule,
                'nom' => $el->nom,
                'prenom' => $el->prenom,
                'nom_complet' => $el->nom_complet,
                'sexe' => $el->sexe,
                'date_naissance' => $el->date_naissance?->format('Y-m-d'),
                'lieu_naissance' => $el->lieu_naissance,
                'telephone_parent' => $el->telephone_parent,
                'photo_url' => $el->photo_url,
                'classe_id' => $insc->classe_id,
                'classe_nom' => $insc->classe?->nom ?? 'Non assignée',
                'etablissement_id' => $insc->etablissement_id,
                'etablissement_nom' => $insc->etablissement?->nom ?? '',
                'etablissement_code' => $insc->etablissement?->code ?? '',
                'statut' => $insc->statut,
                'total_paye' => (float) ($compte?->total_paye ?? 0),
                'solde_restant' => (float) ($compte?->solde_restant ?? 0),
                'nb_versements' => (int) ($compte ? ($versementsCountByCompte[$compte->id] ?? 0) : 0),
                'nb_notes' => (int) ($notesCountByInsc[$insc->id] ?? 0),
            ];

            // Clé 1 : Même classe (capte les fautes de frappe sur le début du nom dans une même classe)
            if ($insc->classe_id) {
                $buckets['cls:'.$insc->classe_id][] = $el->id;
            }
            // Clé 2 : 3 premières lettres du nom (capte les doublons inter-classes dans le même établissement)
            if (strlen($firstWord) >= 3) {
                $buckets['nom:'.substr($firstWord, 0, 3)][] = $el->id;
            }
            // Clé 3 : 3 premières lettres du prénom (capte les inversions Nom ↔ Prénom)
            $firstPrenom = explode(' ', $prenomNorm)[0] ?? '';
            if (strlen($firstPrenom) >= 3) {
                $buckets['nom:'.substr($firstPrenom, 0, 3)][] = $el->id;
            }
        }

        // Comparaison dédupliquée des paires candidates
        $evaluatedPairs = [];
        $suspects = [];

        foreach ($buckets as $bucketIds) {
            $count = count($bucketIds);
            if ($count < 2 || $count > 120) {
                // Pour les classes ou préfixes très nombreux, on compare jusqu'à 120 éléments max par bucket
                $count = min($count, 120);
            }

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $id1 = $bucketIds[$i];
                    $id2 = $bucketIds[$j];
                    if ($id1 === $id2) {
                        continue;
                    }

                    $minId = min($id1, $id2);
                    $maxId = max($id1, $id2);
                    $pairKey = "{$minId}:{$maxId}";

                    if (isset($evaluatedPairs[$pairKey]) || isset($ignoredKeys[$pairKey])) {
                        continue;
                    }
                    $evaluatedPairs[$pairKey] = true;

                    $a = $items[$minId];
                    $b = $items[$maxId];
                    $eval = $this->evaluerRessemblance($a, $b);

                    if ($eval['score'] >= 65) {
                        // Suggère comme Maître par défaut la fiche la plus riche (paiements + notes + ancienneté)
                        $poidsA = ($a['nb_versements'] * 10) + ($a['nb_notes'] * 3) + ($a['total_paye'] > 0 ? 5 : 0);
                        $poidsB = ($b['nb_versements'] * 10) + ($b['nb_notes'] * 3) + ($b['total_paye'] > 0 ? 5 : 0);
                        $master = $poidsB > $poidsA ? $b : $a;
                        $duplicate = $poidsB > $poidsA ? $a : $b;

                        $suspects[] = [
                            'pair_key' => $pairKey,
                            'score' => $eval['score'],
                            'niveau_confiance' => $eval['score'] >= 85 ? 'eleve' : 'moyen',
                            'raisons' => $eval['raisons'],
                            'master' => $master,
                            'duplicate' => $duplicate,
                        ];
                    }
                }
            }
        }

        usort($suspects, fn ($x, $y) => $y['score'] <=> $x['score']);

        return $suspects;
    }

    /**
     * Prépare la prévisualisation comparative détaillée entre un dossier Maître et un dossier Doublon,
     * incluant la détection exhaustive de tous les conflits de notes à arbitrer par l'utilisateur.
     *
     * @param  Eleve  $master  Dossier élève conservé (Maître).
     * @param  Eleve  $duplicate  Dossier élève à absorber (Doublon).
     * @return array<string, mixed> Données comparatives, résumé des transferts et liste des conflits de notes.
     */
    public function previsualiserFusion(Eleve $master, Eleve $duplicate): array
    {
        if ($master->id === $duplicate->id) {
            throw new InvalidArgumentException('Impossible de fusionner un élève avec lui-même.');
        }

        $loadRelations = [
            'inscriptions' => fn ($q) => $q->withoutGlobalScopes()->with([
                'classe.niveau',
                'etablissement:id,code,nom',
                'anneeScolaire:id,code,nom',
                'compteFinancier' => fn ($cq) => $cq->withoutGlobalScopes()->with([
                    'versements' => fn ($vq) => $vq->withoutGlobalScopes()->where('statut', 'valide'),
                    'souscriptionsOptionnelles' => fn ($sq) => $sq->where('statut', 'actif')->with('fraisScolaire.typeFrais'),
                    'boursesRemises.bourseRemise',
                    'dispensesFinancieres',
                    'penalites' => fn ($pq) => $pq->where('statut', '!=', 'annule'),
                ]),
            ]),
        ];

        $master->load($loadRelations);
        $duplicate->load($loadRelations);

        $masterStats = $this->extraireResumeEleve($master);
        $dupStats = $this->extraireResumeEleve($duplicate);

        // Détection des conflits de notes et comptage des notes sans conflit sur les inscriptions de même année/campus
        $masterInscMap = [];
        foreach ($master->inscriptions as $mi) {
            $key = "{$mi->annee_scolaire_id}:{$mi->etablissement_id}";
            $masterInscMap[$key] = $mi;
        }

        $conflitsNotes = [];
        $notesSansConflit = 0;
        $inscriptionsAutresAnnees = 0;

        foreach ($duplicate->inscriptions as $dupInsc) {
            $key = "{$dupInsc->annee_scolaire_id}:{$dupInsc->etablissement_id}";
            $masterInsc = $masterInscMap[$key] ?? null;

            if (! $masterInsc) {
                $inscriptionsAutresAnnees++;

                continue;
            }

            $masterNotes = Note::with(['attribution.matiere:id,nom,code', 'evaluation.periode:id,nom'])
                ->where('inscription_id', $masterInsc->id)
                ->get()
                ->keyBy(fn (Note $n) => "{$n->attribution_id}:{$n->evaluation_id}");

            $dupNotes = Note::with(['attribution.matiere:id,nom,code', 'evaluation.periode:id,nom'])
                ->where('inscription_id', $dupInsc->id)
                ->get();

            foreach ($dupNotes as $dupNote) {
                $noteKey = "{$dupNote->attribution_id}:{$dupNote->evaluation_id}";
                $masterNote = $masterNotes->get($noteKey);

                if (! $masterNote) {
                    $notesSansConflit++;

                    continue;
                }

                // Conflit réel si la note chiffrée ou le statut de présence diffèrent
                $valMaster = $masterNote->valeur_note !== null ? (float) $masterNote->valeur_note : null;
                $valDup = $dupNote->valeur_note !== null ? (float) $dupNote->valeur_note : null;

                if ($valMaster !== $valDup || $masterNote->statut_presence !== $dupNote->statut_presence) {
                    $conflitsNotes[] = [
                        'duplicate_note_id' => $dupNote->id,
                        'master_note_id' => $masterNote->id,
                        'annee_code' => $dupInsc->anneeScolaire?->code ?? '',
                        'matiere_nom' => $dupNote->attribution?->matiere?->nom ?? 'Matière',
                        'matiere_code' => $dupNote->attribution?->matiere?->code ?? '',
                        'periode_nom' => $dupNote->evaluation?->periode?->nom ?? '',
                        'evaluation_nom' => $dupNote->evaluation?->nom ?? 'Évaluation',
                        'master_note' => [
                            'valeur_note' => $valMaster,
                            'statut_presence' => $masterNote->statut_presence,
                            'appreciation' => $masterNote->appreciation,
                            'cote' => $masterNote->cote,
                        ],
                        'duplicate_note' => [
                            'valeur_note' => $valDup,
                            'statut_presence' => $dupNote->statut_presence,
                            'appreciation' => $dupNote->appreciation,
                            'cote' => $dupNote->cote,
                        ],
                    ];
                }
            }
        }

        // Suggestions par défaut des champs d'identité (Maître prioritaire, complétion auto si vide)
        $suggestedIdentity = [];
        foreach (self::IDENTITY_FIELDS as $field) {
            $mVal = $this->formatIdentityValue($master, $field);
            $dVal = $this->formatIdentityValue($duplicate, $field);
            $suggestedIdentity[$field] = ($mVal !== null && $mVal !== '') ? 'master' : (($dVal !== null && $dVal !== '') ? 'duplicate' : 'master');
        }

        return [
            'master' => $masterStats,
            'duplicate' => $dupStats,
            'suggested_identity' => $suggestedIdentity,
            'conflits_notes' => $conflitsNotes,
            'resume_transfert' => [
                'nb_versements' => $dupStats['nb_versements'],
                'montant_versements' => $dupStats['total_paye'],
                'nb_notes_sans_conflit' => $notesSansConflit,
                'nb_conflits_notes' => count($conflitsNotes),
                'nb_assiduites' => $dupStats['nb_assiduites'],
                'nb_sanctions' => $dupStats['nb_sanctions'],
                'nb_inscriptions_autres_annees' => $inscriptionsAutresAnnees,
            ],
        ];
    }

    /**
     * Exécute la fusion atomique et sécurisée du dossier Doublon dans le dossier Maître.
     *
     * Toutes les opérations financières, pédagogiques, disciplinaires et d'audit s'exécutent
     * dans une unique transaction SQL (`DB::transaction`) garantissant le tout-ou-rien.
     *
     * @param  Eleve  $master  Dossier conservé (Maître).
     * @param  Eleve  $duplicate  Dossier absorbé puis supprimé (Doublon).
     * @param  User  $actor  Administrateur validant la fusion.
     * @param  array<string, mixed>  $options  Choix d'identité (`identity_choices`) et arbitrages de conflits de notes (`note_resolutions`).
     * @return EleveFusionAudit Enregistrement d'audit créé.
     */
    public function fusionner(Eleve $master, Eleve $duplicate, User $actor, array $options = []): EleveFusionAudit
    {
        if ($master->id === $duplicate->id) {
            throw new InvalidArgumentException('Impossible de fusionner un élève avec lui-même.');
        }

        $identityChoices = is_array($options['identity_choices'] ?? null) ? $options['identity_choices'] : [];
        $noteResolutions = is_array($options['note_resolutions'] ?? null) ? $options['note_resolutions'] : [];

        $photoToDeleteAfterCommit = null;

        $audit = DB::transaction(function () use (
            $master,
            $duplicate,
            $actor,
            $identityChoices,
            $noteResolutions,
            &$photoToDeleteAfterCommit
        ): EleveFusionAudit {
            // Rechargement frais sous verrou pour garantir la cohérence concurrente
            $master = Eleve::lockForUpdate()->findOrFail($master->id);
            $duplicate = Eleve::lockForUpdate()->findOrFail($duplicate->id);

            $absorbedMatricule = $duplicate->matricule;
            $absorbedNomComplet = $duplicate->nom_complet;
            $absorbedEleveId = $duplicate->id;
            $oldDupPhoto = $duplicate->photo_url;
            $oldMasterPhoto = $master->photo_url;

            // 1. Mise à jour des champs civils et de contact sur la fiche Maître selon les choix de l'utilisateur
            $identityUpdates = [];
            foreach (self::IDENTITY_FIELDS as $field) {
                $choice = $identityChoices[$field] ?? null;
                $masterVal = $master->getAttribute($field);
                $dupVal = $duplicate->getAttribute($field);

                if ($choice === 'duplicate' && $dupVal !== null && $dupVal !== '') {
                    $identityUpdates[$field] = $dupVal;
                } elseif ($choice === null && ($masterVal === null || $masterVal === '') && $dupVal !== null && $dupVal !== '') {
                    // Auto-complétion bienveillante des champs vides du Maître
                    $identityUpdates[$field] = $dupVal;
                }
            }

            if (isset($identityUpdates['nom'])) {
                $identityUpdates['nom'] = mb_strtoupper(trim((string) $identityUpdates['nom']), 'UTF-8');
            }

            if (! empty($identityUpdates)) {
                $master->update($identityUpdates);
            }

            // Détermine si une photo physique devenue orpheline pourra être nettoyée après commit
            $finalMasterPhoto = $master->photo_url;
            if ($oldDupPhoto && $oldDupPhoto !== $finalMasterPhoto) {
                $photoToDeleteAfterCommit = $oldDupPhoto;
            } elseif ($oldMasterPhoto && $oldMasterPhoto !== $finalMasterPhoto) {
                $photoToDeleteAfterCommit = $oldMasterPhoto;
            }

            // 2. Fusion ou rattachement de chaque inscription du doublon
            $masterInscriptions = Inscription::withoutGlobalScopes()
                ->where('eleve_id', $master->id)
                ->get()
                ->keyBy(fn (Inscription $i) => "{$i->annee_scolaire_id}:{$i->etablissement_id}");

            $dupInscriptions = Inscription::withoutGlobalScopes()
                ->where('eleve_id', $duplicate->id)
                ->get();

            $nbVersementsTransferes = 0;
            $montantVersementsTransferes = 0.0;
            $nbNotesTransferees = 0;
            $nbConflitsNotesResolus = 0;
            $nbAssiduitesTransferees = 0;
            $nbSanctionsTransferees = 0;
            $nbInscriptionsRattachees = 0;
            $etabPrincipalId = $masterInscriptions->first()?->etablissement_id ?? $dupInscriptions->first()?->etablissement_id;

            foreach ($dupInscriptions as $dupInsc) {
                $pairKey = "{$dupInsc->annee_scolaire_id}:{$dupInsc->etablissement_id}";
                $masterInsc = $masterInscriptions->get($pairKey);

                if (! $masterInsc) {
                    // Année scolaire ou campus distinct : rattachement direct de toute l'inscription et de son compte financier
                    $dupInsc->update(['eleve_id' => $master->id]);
                    CompteFinancierEleve::withoutGlobalScopes()
                        ->where('inscription_id', $dupInsc->id)
                        ->update(['eleve_id' => $master->id]);

                    $nbInscriptionsRattachees++;

                    continue;
                }

                // 2A. Fusion financière (Versements, Bourses, Dispenses, Frais annexes, Pénalités, Arriérés)
                $dupCompte = CompteFinancierEleve::withoutGlobalScopes()
                    ->where('inscription_id', $dupInsc->id)
                    ->first();

                if ($dupCompte) {
                    $masterCompte = CompteFinancierEleve::withoutGlobalScopes()
                        ->where('inscription_id', $masterInsc->id)
                        ->first();

                    if (! $masterCompte) {
                        // Le dossier Maître n'avait pas encore de compte financier : on rattache celui du doublon
                        $dupCompte->update([
                            'eleve_id' => $master->id,
                            'inscription_id' => $masterInsc->id,
                        ]);
                        VersementCaisse::withoutGlobalScopes()
                            ->where('compte_financier_id', $dupCompte->id)
                            ->update(['inscription_id' => $masterInsc->id]);
                        $dupCompte->actualiserSolde();
                    } else {
                        // Comptage préalable des versements valides transférés pour le journal d'audit
                        $versementsValidesDup = VersementCaisse::withoutGlobalScopes()
                            ->where('compte_financier_id', $dupCompte->id)
                            ->where('statut', 'valide')
                            ->get(['id', 'montant_total']);

                        $nbVersementsTransferes += $versementsValidesDup->count();
                        $montantVersementsTransferes += (float) $versementsValidesDup->sum('montant_total');

                        // Transfert de TOUS les versements (valides et annulés) en conservant 100 % des numéros de reçus
                        VersementCaisse::withoutGlobalScopes()
                            ->where('compte_financier_id', $dupCompte->id)
                            ->update([
                                'compte_financier_id' => $masterCompte->id,
                                'inscription_id' => $masterInsc->id,
                            ]);

                        // Transfert dédupliqué des bourses / remises (contrainte unique compte_financier_id + bourse_remise_id)
                        $masterBourseIds = EleveBourseRemise::where('compte_financier_id', $masterCompte->id)
                            ->pluck('bourse_remise_id')
                            ->all();
                        foreach (EleveBourseRemise::where('compte_financier_id', $dupCompte->id)->get() as $ebr) {
                            if (in_array($ebr->bourse_remise_id, $masterBourseIds, true)) {
                                $ebr->delete();
                            } else {
                                $ebr->update(['compte_financier_id' => $masterCompte->id]);
                                $masterBourseIds[] = $ebr->bourse_remise_id;
                            }
                        }

                        // Transfert dédupliqué des souscriptions aux frais annexes (unique compte_financier_id + frais_scolaire_id)
                        $masterOptions = SouscriptionFraisOptionnel::where('compte_financier_id', $masterCompte->id)
                            ->get()
                            ->keyBy('frais_scolaire_id');
                        foreach (SouscriptionFraisOptionnel::where('compte_financier_id', $dupCompte->id)->get() as $opt) {
                            $existingMasterOpt = $masterOptions->get($opt->frais_scolaire_id);
                            if ($existingMasterOpt) {
                                // Si le doublon avait une option active alors que celle du Maître était résiliée, on la réactive
                                if ($existingMasterOpt->statut !== 'actif' && $opt->statut === 'actif') {
                                    $existingMasterOpt->update([
                                        'statut' => 'actif',
                                        'montant' => $opt->montant,
                                    ]);
                                }
                                $opt->delete();
                            } else {
                                $opt->update(['compte_financier_id' => $masterCompte->id]);
                                $masterOptions->put($opt->frais_scolaire_id, $opt);
                            }
                        }

                        // Transfert dédupliqué des dispenses financières (unique compte_financier_id + frais_scolaire_id)
                        $masterDispenses = DispenseFinanciere::withoutGlobalScopes()
                            ->where('compte_financier_id', $masterCompte->id)
                            ->get()
                            ->keyBy('frais_scolaire_id');
                        foreach (DispenseFinanciere::withoutGlobalScopes()->where('compte_financier_id', $dupCompte->id)->get() as $disp) {
                            $existingDisp = $masterDispenses->get($disp->frais_scolaire_id);
                            if ($existingDisp) {
                                if ((float) $disp->montant_dispense > (float) $existingDisp->montant_dispense) {
                                    $existingDisp->update(['montant_dispense' => $disp->montant_dispense]);
                                }
                                $disp->delete();
                            } else {
                                $disp->update(['compte_financier_id' => $masterCompte->id]);
                                $masterDispenses->put($disp->frais_scolaire_id, $disp);
                            }
                        }

                        // Transfert des pénalités et cumul des arriérés éventuels
                        PenaliteEleve::where('compte_financier_id', $dupCompte->id)
                            ->update(['compte_financier_id' => $masterCompte->id]);

                        if ((float) ($dupCompte->arrieres_anterieurs ?? 0) > 0) {
                            $masterCompte->arrieres_anterieurs = (float) ($masterCompte->arrieres_anterieurs ?? 0)
                                + (float) $dupCompte->arrieres_anterieurs;
                            $masterCompte->save();
                        }

                        // Suppression du compte financier du doublon puis recalcul exact du solde Maître
                        $dupCompte->delete();
                        $masterCompte->refresh();
                        $masterCompte->actualiserSolde();
                    }
                }

                // 2B. Fusion pédagogique des Notes avec résolution explicite des conflits
                $masterNotes = Note::where('inscription_id', $masterInsc->id)
                    ->get()
                    ->keyBy(fn (Note $n) => "{$n->attribution_id}:{$n->evaluation_id}");

                $dupNotes = Note::where('inscription_id', $dupInsc->id)->get();
                foreach ($dupNotes as $dupNote) {
                    $noteKey = "{$dupNote->attribution_id}:{$dupNote->evaluation_id}";
                    $masterNote = $masterNotes->get($noteKey);

                    if (! $masterNote) {
                        $dupNote->update(['inscription_id' => $masterInsc->id]);
                        $masterNotes->put($noteKey, $dupNote);
                        $nbNotesTransferees++;
                    } else {
                        // Arbitrage choisi par l'utilisateur ('master' par défaut, ou 'duplicate')
                        $resolution = $noteResolutions[(string) $dupNote->id] ?? $noteResolutions[$dupNote->id] ?? 'master';
                        if ($resolution === 'duplicate') {
                            $masterNote->update([
                                'valeur_note' => $dupNote->valeur_note,
                                'statut_presence' => $dupNote->statut_presence,
                                'appreciation' => $dupNote->appreciation,
                                'competence_evaluee' => $dupNote->competence_evaluee,
                                'cote' => $dupNote->cote,
                                'modifie_par_user_id' => $actor->id,
                            ]);
                        }
                        $dupNote->delete();
                        $nbConflitsNotesResolus++;
                    }
                }

                // 2C. Fusion des synthèses disciplinaires de bulletin (unique inscription_id + periode_id)
                $masterDiscBulletins = DisciplineBulletin::where('inscription_id', $masterInsc->id)
                    ->get()
                    ->keyBy('periode_id');
                foreach (DisciplineBulletin::where('inscription_id', $dupInsc->id)->get() as $dbRow) {
                    $existingDb = $masterDiscBulletins->get($dbRow->periode_id);
                    if ($existingDb) {
                        $existingDb->update([
                            'absences_justifiees' => (int) $existingDb->absences_justifiees + (int) $dbRow->absences_justifiees,
                            'absences_non_justifiees' => (int) $existingDb->absences_non_justifiees + (int) $dbRow->absences_non_justifiees,
                            'retards' => (int) $existingDb->retards + (int) $dbRow->retards,
                            'consignes' => (int) $existingDb->consignes + (int) $dbRow->consignes,
                            'avertissements_conduite' => (int) $existingDb->avertissements_conduite + (int) $dbRow->avertissements_conduite,
                            'blames_conduite' => (int) $existingDb->blames_conduite + (int) $dbRow->blames_conduite,
                            'exclusions_jours' => (int) $existingDb->exclusions_jours + (int) $dbRow->exclusions_jours,
                            'decision_conseil' => $existingDb->decision_conseil ?: $dbRow->decision_conseil,
                        ]);
                        $dbRow->delete();
                    } else {
                        $dbRow->update(['inscription_id' => $masterInsc->id]);
                        $masterDiscBulletins->put($dbRow->periode_id, $dbRow);
                    }
                }

                // 2D. Fusion des délibérations de conseil (unique inscription_id + type_deliberation + periode_id)
                $masterDelibs = DeliberationConseil::where('inscription_id', $masterInsc->id)
                    ->get()
                    ->keyBy(fn ($d) => "{$d->type_deliberation}:".($d->periode_id ?? 'null'));
                foreach (DeliberationConseil::where('inscription_id', $dupInsc->id)->get() as $delib) {
                    $dKey = "{$delib->type_deliberation}:".($delib->periode_id ?? 'null');
                    if ($masterDelibs->has($dKey)) {
                        $delib->delete();
                    } else {
                        $delib->update(['inscription_id' => $masterInsc->id]);
                        $masterDelibs->put($dKey, $delib);
                    }
                }

                // 2E. Réattachement des enregistrements sans contrainte d'unicité sur inscription_id
                $nbAssiduitesTransferees += Assiduite::where('inscription_id', $dupInsc->id)
                    ->update(['inscription_id' => $masterInsc->id]);
                $nbSanctionsTransferees += SanctionDisciplinaire::where('inscription_id', $dupInsc->id)
                    ->update(['inscription_id' => $masterInsc->id]);
                HeureConsigne::withoutGlobalScopes()
                    ->where('inscription_id', $dupInsc->id)
                    ->update(['inscription_id' => $masterInsc->id, 'eleve_id' => $master->id]);
                DispenseEleve::withoutGlobalScopes()
                    ->where('inscription_id', $dupInsc->id)
                    ->update(['inscription_id' => $masterInsc->id, 'eleve_id' => $master->id]);
                DossierConseilDiscipline::where('inscription_id', $dupInsc->id)
                    ->update(['inscription_id' => $masterInsc->id, 'eleve_id' => $master->id]);
                HistoriqueSms::where('inscription_id', $dupInsc->id)
                    ->update(['inscription_id' => $masterInsc->id]);

                // Suppression de l'inscription en double désormais entièrement vidée
                $dupInsc->delete();
            }

            // 3. Réattachement des tables rattachées directement à eleve_id (même sans inscription correspondante)
            HeureConsigne::withoutGlobalScopes()
                ->where('eleve_id', $duplicate->id)
                ->update(['eleve_id' => $master->id]);
            DispenseEleve::withoutGlobalScopes()
                ->where('eleve_id', $duplicate->id)
                ->update(['eleve_id' => $master->id]);
            DossierConseilDiscipline::where('eleve_id', $duplicate->id)
                ->update(['eleve_id' => $master->id]);
            ArchiveAdministrative::where('eleve_id', $duplicate->id)
                ->update(['eleve_id' => $master->id]);

            // Réattachement d'éventuels audits de fusions antérieures dont ce doublon aurait été Maître
            EleveFusionAudit::where('master_eleve_id', $duplicate->id)
                ->update(['master_eleve_id' => $master->id]);

            // Nettoyage des entrées d'ignorance impliquant le doublon
            EleveDoublonIgnore::where('eleve_min_id', $duplicate->id)
                ->orWhere('eleve_max_id', $duplicate->id)
                ->delete();

            // 4. Mention lisible dans les observations de l'inscription la plus récente du dossier Maître
            $latestMasterInsc = Inscription::withoutGlobalScopes()
                ->where('eleve_id', $master->id)
                ->orderByDesc('id')
                ->first();
            if ($latestMasterInsc) {
                $noteAudit = sprintf(
                    '[Fusion %s] Doublon absorbé : %s (%s) par %s — %d versement(s) (%s FCFA), %d note(s).',
                    Carbon::now()->format('d-m-Y'),
                    $absorbedMatricule,
                    $absorbedNomComplet,
                    $actor->name ?? $actor->email,
                    $nbVersementsTransferes,
                    number_format($montantVersementsTransferes, 0, ',', ' '),
                    $nbNotesTransferees
                );
                $latestMasterInsc->update([
                    'observations' => trim(($latestMasterInsc->observations ? $latestMasterInsc->observations."\n" : '').$noteAudit),
                ]);
            }

            // 5. Enregistrement pérenne dans le journal d'audit des fusions
            $auditRecord = EleveFusionAudit::create([
                'etablissement_id' => $etabPrincipalId,
                'master_eleve_id' => $master->id,
                'master_matricule' => $master->matricule,
                'absorbed_eleve_id' => $absorbedEleveId,
                'absorbed_matricule' => $absorbedMatricule,
                'absorbed_nom_complet' => $absorbedNomComplet,
                'merged_by_user_id' => $actor->id,
                'montant_versements_transferes' => $montantVersementsTransferes,
                'nb_versements_transferes' => $nbVersementsTransferes,
                'nb_notes_transferees' => $nbNotesTransferees,
                'nb_conflits_notes_resolus' => $nbConflitsNotesResolus,
                'resume_json' => [
                    'assiduites_transferees' => $nbAssiduitesTransferees,
                    'sanctions_transferees' => $nbSanctionsTransferees,
                    'inscriptions_autres_annees' => $nbInscriptionsRattachees,
                    'identity_updates' => array_keys($identityUpdates),
                ],
            ]);

            // 6. Suppression propre de la fiche Doublon une fois tout son historique transféré
            $duplicate->delete();

            return $auditRecord;
        });

        // Nettoyage post-commit de l'éventuelle photo physique devenue orpheline
        if ($photoToDeleteAfterCommit && str_starts_with($photoToDeleteAfterCommit, '/storage/photos_eleves/')) {
            $relative = ltrim(str_replace('/storage/', '', $photoToDeleteAfterCommit), '/');
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        }

        return $audit;
    }

    /**
     * Formate un champ d'identité élève pour la prévisualisation comparative.
     *
     * @param  Eleve  $eleve  Instance de l'élève.
     * @param  string  $field  Nom du champ.
     * @return string|null Valeur formatée ou null.
     */
    private function formatIdentityValue(Eleve $eleve, string $field): ?string
    {
        if ($field === 'date_naissance') {
            return $eleve->date_naissance?->format('Y-m-d');
        }

        $val = $eleve->getAttribute($field);

        return $val !== null ? (string) $val : null;
    }

    /**
     * Construit le résumé financier, civil et pédagogique complet d'un élève pour la comparaison côte à côte.
     *
     * @param  Eleve  $eleve  Élève chargé avec ses inscriptions et comptes financiers.
     * @return array<string, mixed> Résumé structuré pour l'interface React.
     */
    private function extraireResumeEleve(Eleve $eleve): array
    {
        $inscriptionIds = $eleve->inscriptions->pluck('id')->all();

        $totalPaye = 0.0;
        $soldeRestant = 0.0;
        $nbVersements = 0;
        $optionsActives = [];

        foreach ($eleve->inscriptions as $insc) {
            $compte = $insc->compteFinancier;
            if (! $compte) {
                continue;
            }
            $totalPaye += (float) $compte->total_paye;
            $soldeRestant += (float) $compte->solde_restant;
            $nbVersements += $compte->versements->count();

            foreach ($compte->souscriptionsOptionnelles as $opt) {
                $optionsActives[] = $opt->fraisScolaire?->libelle_tranche
                    ?: ($opt->fraisScolaire?->typeFrais?->libelle ?? 'Frais annexe');
            }
        }

        $nbNotes = ! empty($inscriptionIds) ? Note::whereIn('inscription_id', $inscriptionIds)->count() : 0;
        $nbAssiduites = ! empty($inscriptionIds) ? Assiduite::whereIn('inscription_id', $inscriptionIds)->count() : 0;
        $nbSanctions = ! empty($inscriptionIds) ? SanctionDisciplinaire::whereIn('inscription_id', $inscriptionIds)->count() : 0;

        $latestInsc = $eleve->inscriptions->sortByDesc('id')->first();

        return [
            'id' => $eleve->id,
            'matricule' => $eleve->matricule,
            'matricule_national' => $eleve->matricule_national,
            'nom' => $eleve->nom,
            'prenom' => $eleve->prenom,
            'nom_complet' => $eleve->nom_complet,
            'sexe' => $eleve->sexe,
            'date_naissance' => $eleve->date_naissance?->format('Y-m-d'),
            'lieu_naissance' => $eleve->lieu_naissance,
            'nationalite' => $eleve->nationalite,
            'photo_url' => $eleve->photo_url,
            'nom_pere' => $eleve->nom_pere,
            'nom_mere' => $eleve->nom_mere,
            'nom_tuteur' => $eleve->nom_tuteur,
            'telephone_parent' => $eleve->telephone_parent,
            'email_parent' => $eleve->email_parent,
            'adresse' => $eleve->adresse,
            'classe_nom' => $latestInsc?->classe?->nom ?? 'Non assignée',
            'etablissement_nom' => $latestInsc?->etablissement?->nom ?? '',
            'nb_inscriptions' => $eleve->inscriptions->count(),
            'total_paye' => $totalPaye,
            'solde_restant' => $soldeRestant,
            'nb_versements' => $nbVersements,
            'options_actives' => array_values(array_unique($optionsActives)),
            'nb_notes' => $nbNotes,
            'nb_assiduites' => $nbAssiduites,
            'nb_sanctions' => $nbSanctions,
        ];
    }
}

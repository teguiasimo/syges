<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\DeliberationConseil;
use App\Models\DisciplineBulletin;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service Métier pour le Module 15 : Tableau de Bord, Statistiques & Conseil de Classe (Gesco V1.1).
 *
 * Prend en charge :
 * - 15.1 : Statistiques Démographiques & Effectifs (Rapports de rentrée, parité filles/garçons, densité des classes, listes d'examen).
 * - 15.2 : Délibérations du Conseil de Classe, attribution/blocage des mentions (12.4 & 4.3), et moteur de promotion humaine (2.6).
 * - 15.3 : Statistiques Pédagogiques (taux de réussite, détection précoce du décrochage) & Suivi Financier de Direction.
 */
class StatistiqueConseilService
{
    /**
     * Service de calcul des résultats pédagogiques et bulletins.
     */
    protected BulletinCalculationService $bulletinService;

    /**
     * Constructeur injectant le calculateur de bulletins.
     *
     * @param BulletinCalculationService $bulletinService
     */
    public function __construct(BulletinCalculationService $bulletinService)
    {
        $this->bulletinService = $bulletinService;
    }

    /**
     * Calcule l'ensemble des KPIs démographiques et d'effectifs pour un établissement (Gesco 15.1).
     *
     * @param int $etablissementId
     * @param int|null $anneeId
     * @return array<string, mixed>
     */
    public function getStatistiquesDemographiques(int $etablissementId, ?int $anneeId = null): array
    {
        $anneeActive = $anneeId ? AnneeScolaire::find($anneeId) : (AnneeScolaire::current() ?? AnneeScolaire::latest()->first());
        $anneeId = $anneeActive?->id;

        // Requête de base pour les inscriptions validées
        $queryInscriptions = Inscription::query()
            ->join('classes', 'inscriptions.classe_id', '=', 'classes.id')
            ->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
            ->join('cycles', 'niveaux.cycle_id', '=', 'cycles.id')
            ->join('sections', 'cycles.section_id', '=', 'sections.id')
            ->join('eleves', 'inscriptions.eleve_id', '=', 'eleves.id')
            ->where('sections.etablissement_id', $etablissementId)
            ->where('inscriptions.statut', 'validee');

        if ($anneeId) {
            $queryInscriptions->where('inscriptions.annee_scolaire_id', $anneeId);
        }

        $totalEffectif = (clone $queryInscriptions)->count();

        // Parité Garçons / Filles
        $effectifGarcons = (clone $queryInscriptions)->where('eleves.sexe', 'M')->count();
        $effectifFilles = (clone $queryInscriptions)->where('eleves.sexe', 'F')->count();

        $tauxGarcons = $totalEffectif > 0 ? round(($effectifGarcons / $totalEffectif) * 100, 1) : 0.0;
        $tauxFilles = $totalEffectif > 0 ? round(($effectifFilles / $totalEffectif) * 100, 1) : 0.0;

        // Répartition Nouveaux vs Redoublants
        $effectifRedoublants = (clone $queryInscriptions)->where('inscriptions.is_redoublant', true)->count();
        $effectifNouveaux = $totalEffectif - $effectifRedoublants;
        $tauxRedoublants = $totalEffectif > 0 ? round(($effectifRedoublants / $totalEffectif) * 100, 1) : 0.0;

        // Densité moyenne des classes et surcharge
        $classes = Classe::whereHas('niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->withCount(['inscriptions' => function ($q) use ($anneeId) {
                $q->where('statut', 'validee');
                if ($anneeId) {
                    $q->where('annee_scolaire_id', $anneeId);
                }
            }])
            ->orderBy('nom')
            ->get();

        $totalClasses = $classes->count();
        $moyenneDensite = $totalClasses > 0 ? round($totalEffectif / $totalClasses, 1) : 0.0;

        $classesDetails = $classes->map(function ($classe) {
            $effectif = $classe->inscriptions_count;
            $capacite = $classe->capacite ?: 50;
            $isSurchargee = $effectif > $capacite;
            $tauxOccupation = $capacite > 0 ? round(($effectif / $capacite) * 100, 1) : 0.0;

            return [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'effectif' => $effectif,
                'capacite' => $capacite,
                'taux_occupation' => $tauxOccupation,
                'is_surchargee' => $isSurchargee,
            ];
        });

        $nombreClassesSurchargees = $classesDetails->where('is_surchargee', true)->count();

        return [
            'annee_scolaire' => $anneeActive?->nom ?? 'N/A',
            'effectif_total' => $totalEffectif,
            'parite' => [
                'garcons' => $effectifGarcons,
                'filles' => $effectifFilles,
                'taux_garcons' => $tauxGarcons,
                'taux_filles' => $tauxFilles,
                'ratio_texte' => "{$tauxFilles}% Filles / {$tauxGarcons}% Garçons",
            ],
            'repartition_statut' => [
                'nouveaux' => $effectifNouveaux,
                'redoublants' => $effectifRedoublants,
                'taux_redoublants' => $tauxRedoublants,
            ],
            'densite' => [
                'nombre_classes' => $totalClasses,
                'moyenne_par_classe' => $moyenneDensite,
                'classes_surchargees' => $nombreClassesSurchargees,
                'details' => $classesDetails,
            ],
        ];
    }

    /**
     * Génère la liste officielle alphabétique des élèves avec matricule national pour examens (Gesco 15.1).
     *
     * @param int $etablissementId
     * @param int|null $classeId
     * @param int|null $anneeId
     * @return array<int, array<string, mixed>>
     */
    public function getListesOfficiellesExamens(int $etablissementId, ?int $classeId = null, ?int $anneeId = null): array
    {
        $anneeActive = $anneeId ? AnneeScolaire::find($anneeId) : (AnneeScolaire::current() ?? AnneeScolaire::latest()->first());
        $anneeId = $anneeActive?->id;

        $query = Inscription::with(['eleve', 'classe.niveau'])
            ->whereHas('classe.niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->where('statut', 'validee');

        if ($anneeId) {
            $query->where('annee_scolaire_id', $anneeId);
        }

        if ($classeId) {
            $query->where('classe_id', $classeId);
        }

        $inscriptions = $query->get()
            ->sortBy(fn ($i) => ($i->eleve?->nom ?? '') . ' ' . ($i->eleve?->prenom ?? ''));

        return $inscriptions->map(function ($ins, $index) {
            return [
                'rang_alphabetique' => $index + 1,
                'inscription_id' => $ins->id,
                'matricule' => $ins->eleve?->matricule ?? 'N/A',
                'matricule_ministere' => $ins->eleve?->matricule_national ?? 'NON_ATTRIBUE',
                'nom' => $ins->eleve?->nom ?? '',
                'prenom' => $ins->eleve?->prenom ?? '',
                'nom_complet' => trim(($ins->eleve?->nom ?? '') . ' ' . ($ins->eleve?->prenom ?? '')),
                'sexe' => $ins->eleve?->sexe ?? 'M',
                'date_naissance' => $ins->eleve?->date_naissance?->format('d/m/Y') ?? 'N/A',
                'lieu_naissance' => $ins->eleve?->lieu_naissance ?? 'N/A',
                'classe_nom' => $ins->classe?->nom ?? 'N/A',
                'is_redoublant' => (bool) $ins->is_redoublant,
                'statut_candidat' => $ins->is_redoublant ? 'Redoublant' : 'Régulier',
            ];
        })->values()->all();
    }

    /**
     * Récupère les indicateurs académiques de réussite et la détection précoce du décrochage (Gesco 15.3).
     *
     * @param int $etablissementId
     * @param int|null $anneeId
     * @param int|null $periodeId
     * @return array<string, mixed>
     */
    public function getStatistiquesPedagogiques(int $etablissementId, ?int $anneeId = null, ?int $periodeId = null): array
    {
        $anneeActive = $anneeId ? AnneeScolaire::find($anneeId) : (AnneeScolaire::current() ?? AnneeScolaire::latest()->first());
        $anneeId = $anneeActive?->id;

        // Classes de l'établissement
        $classes = Classe::whereHas('niveau.cycle.section', fn ($q) => $q->where('etablissement_id', $etablissementId))
            ->where('is_active', true)
            ->get();

        $periode = $periodeId ? Periode::find($periodeId) : Periode::where('annee_scolaire_id', $anneeId)->orderBy('ordre')->first();

        $totalElevesEvalues = 0;
        $totalAdmis = 0;
        $elevesDecrochage = [];

        foreach ($classes as $classe) {
            $donnees = null;
            if ($periode) {
                $donnees = $this->bulletinService->calculerTrimestre($classe, $periode);
            } elseif ($anneeActive) {
                $donnees = $this->bulletinService->calculerAnnuel($classe, $anneeActive);
            }

            if (! $donnees || empty($donnees['eleves'])) {
                continue;
            }

            foreach ($donnees['eleves'] as $el) {
                $totalElevesEvalues++;
                $mg = (float) ($el['moyenne_generale'] ?? 0);
                if ($mg >= 10.0) {
                    $totalAdmis++;
                }

                // Détection précoce du risque de décrochage : Moyenne < 8.5 OU Absences NJ >= 15h
                $absencesNJ = (int) ($el['discipline']['absences_non_justifiees'] ?? 0);
                $isRisqueMoyenne = $mg < 8.5;
                $isRisqueAbsences = $absencesNJ >= 15;

                if ($isRisqueMoyenne || $isRisqueAbsences) {
                    $elevesDecrochage[] = [
                        'inscription_id' => $el['inscription_id'],
                        'nom_complet' => $el['nom_complet'],
                        'matricule' => $el['matricule'],
                        'classe_nom' => $classe->nom,
                        'moyenne_generale' => $mg,
                        'absences_non_justifiees' => $absencesNJ,
                        'gravite' => ($isRisqueMoyenne && $isRisqueAbsences) ? 'critique' : 'alerte',
                        'motif' => ($isRisqueMoyenne && $isRisqueAbsences)
                            ? 'Moyenne très basse (<8.5) & Absences massives (≥15h)'
                            : ($isRisqueMoyenne ? 'Moyenne insuffisante (<8.5)' : 'Cumul absences non justifiées (≥15h)'),
                    ];
                }
            }
        }

        $tauxReussiteGlobal = $totalElevesEvalues > 0 ? round(($totalAdmis / $totalElevesEvalues) * 100, 1) : 0.0;

        return [
            'total_evalues' => $totalElevesEvalues,
            'total_admis' => $totalAdmis,
            'taux_reussite_global' => $tauxReussiteGlobal,
            'total_decrochage' => count($elevesDecrochage),
            'eleves_decrochage' => $elevesDecrochage,
            'periode_nom' => $periode?->nom ?? 'Annuel',
        ];
    }

    /**
     * Récupère la synthèse financière consolidée pour la Direction de l'établissement (Gesco 15.3).
     *
     * @param int $etablissementId
     * @param int|null $anneeId
     * @return array<string, mixed>
     */
    public function getStatistiquesFinancieres(int $etablissementId, ?int $anneeId = null): array
    {
        $anneeActive = $anneeId ? AnneeScolaire::find($anneeId) : (AnneeScolaire::current() ?? AnneeScolaire::latest()->first());
        $anneeId = $anneeActive?->id;

        $comptesQuery = CompteFinancierEleve::where('etablissement_id', $etablissementId);
        if ($anneeId) {
            $comptesQuery->where('annee_scolaire_id', $anneeId);
        }

        $totalFacture = (float) (clone $comptesQuery)->sum('montant_net_exigible');
        $totalRecouvre = (float) (clone $comptesQuery)->sum('total_paye');
        $totalCreances = (float) (clone $comptesQuery)->sum('solde_restant');

        $tauxRecouvrement = $totalFacture > 0 ? round(($totalRecouvre / $totalFacture) * 100, 1) : 0.0;

        // Effectifs par statut financier
        $totalComptes = (clone $comptesQuery)->count();
        $nombreSoldes = (clone $comptesQuery)->where('statut_financier', 'solde')->count();
        $nombreInsolvables = (clone $comptesQuery)->where('solde_restant', '>', 0)->count();

        return [
            'total_facture' => $totalFacture,
            'total_recouvre' => $totalRecouvre,
            'total_creances' => $totalCreances,
            'taux_recouvrement' => $tauxRecouvrement,
            'total_comptes' => $totalComptes,
            'nombre_soldes' => $nombreSoldes,
            'nombre_debiteurs' => $nombreInsolvables,
        ];
    }

    /**
     * Prépare les données de délibération d'une classe pour un trimestre ou pour le bilan annuel (Gesco 15.2).
     *
     * Applique :
     * - Le calcul automatique des distinctions / mentions.
     * - La déchéance automatique des mentions si absences non justifiées >= 15h (Gesco 12.4) ou note éliminatoire (Gesco 4.3).
     * - La proposition de passage de fin d'année (Promu / Redouble / Exclu) (Gesco 2.6 Étape A).
     * - La restitution des statuts déjà enregistrés ou validés.
     *
     * @param Classe $classe
     * @param Periode|null $periode
     * @param AnneeScolaire|null $annee
     * @return array<string, mixed>
     */
    public function preparerDeliberation(Classe $classe, ?Periode $periode = null, ?AnneeScolaire $annee = null): array
    {
        $etablissementId = $classe->niveau?->cycle?->section?->etablissement_id ?? 0;
        $typeDeliberation = $periode ? 'trimestrielle' : 'annuelle';
        $anneeScolaire = $annee ?? ($periode ? $periode->anneeScolaire : AnneeScolaire::current());

        if (! $anneeScolaire) {
            return [
                'classe' => [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'professeur_principal' => $classe->professeurPrincipal?->nom_complet ?? 'Non assigné',
                ],
                'type_deliberation' => $typeDeliberation,
                'periode' => $periode ? ['id' => $periode->id, 'nom' => $periode->nom] : null,
                'annee_scolaire' => ['id' => 0, 'nom' => 'N/A'],
                'eleves' => [],
                'statistiques_classe' => [],
                'toutes_validees' => false,
            ];
        }

        // 1. Calcul des résultats pédagogiques
        $resultats = $periode
            ? $this->bulletinService->calculerTrimestre($classe, $periode)
            : $this->bulletinService->calculerAnnuel($classe, $anneeScolaire);

        if (empty($resultats['eleves'])) {
            return [
                'classe' => [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'professeur_principal' => $classe->professeurPrincipal?->nom_complet ?? 'Non assigné',
                ],
                'type_deliberation' => $typeDeliberation,
                'periode' => $periode ? ['id' => $periode->id, 'nom' => $periode->nom] : null,
                'annee_scolaire' => ['id' => $anneeScolaire->id, 'nom' => $anneeScolaire->nom],
                'eleves' => [],
                'statistiques_classe' => $resultats['statistiques_classe'] ?? [],
                'toutes_validees' => false,
            ];
        }

        // 2. Récupération des délibérations déjà enregistrées en base
        $deliberationsExistantes = DeliberationConseil::where('classe_id', $classe->id)
            ->where('type_deliberation', $typeDeliberation)
            ->where(function ($q) use ($periode) {
                if ($periode) {
                    $q->where('periode_id', $periode->id);
                } else {
                    $q->whereNull('periode_id');
                }
            })
            ->get()
            ->keyBy('inscription_id');

        $lignesDeliberations = [];

        foreach ($resultats['eleves'] as $el) {
            $inscriptionId = $el['inscription_id'];
            $moyenne = (float) ($el['moyenne_generale'] ?? 0.0);
            $rang = $el['rang_general'] ?? null;
            $aNoteEliminatoire = (bool) ($el['a_note_eliminatoire'] ?? false);
            $absencesNJ = (int) ($el['discipline']['absences_non_justifiees'] ?? 0);

            // Calcul de la distinction théorique
            $mentionTheorique = 'aucune';
            if ($moyenne >= 16.0) {
                $mentionTheorique = 'felicitations';
            } elseif ($moyenne >= 14.0) {
                $mentionTheorique = 'encouragements';
            } elseif ($moyenne >= 12.0) {
                $mentionTheorique = 'tableau_honneur';
            }

            // Contrôle des blocages de mentions (Gesco 12.4 & 4.3)
            $mentionBloquee = false;
            $motifBlocage = null;

            if ($mentionTheorique !== 'aucune') {
                if ($absencesNJ >= 15) {
                    $mentionBloquee = true;
                    $motifBlocage = 'absences_excessives';
                } elseif ($aNoteEliminatoire) {
                    $mentionBloquee = true;
                    $motifBlocage = 'note_eliminatoire';
                }
            }

            $mentionEffective = $mentionBloquee ? 'aucune' : $mentionTheorique;

            // Proposition automatique de fin d'année (Gesco 2.6 Étape A)
            $statutPropose = 'en_attente';
            if ($typeDeliberation === 'annuelle') {
                if ($moyenne >= 10.0) {
                    $statutPropose = 'promu';
                } elseif ($moyenne >= 8.5) {
                    $statutPropose = 'redouble';
                } else {
                    $statutPropose = 'exclu';
                }
            }

            // Délibération existante ou pré-remplie
            $delib = $deliberationsExistantes->get($inscriptionId);

            $statutRetenu = $delib?->statut_retenu ?? $statutPropose;
            $appreciation = $delib?->appreciation_globale ?? ($el['appreciation_generale'] ?? self::determinerAppreciationConseil($moyenne));
            $decisionTexte = $delib?->decision_conseil ?? ($el['decision_finale'] ?? self::determinerDecisionTexte($statutRetenu));
            $estValide = $delib?->est_valide ?? false;

            $lignesDeliberations[] = [
                'deliberation_id' => $delib?->id,
                'inscription_id' => $inscriptionId,
                'matricule' => $el['matricule'],
                'matricule_ministere' => $el['matricule_ministere'] ?? 'N/A',
                'nom_complet' => $el['nom_complet'],
                'sexe' => $el['genre'] ?? 'M',
                'is_redoublant' => (bool) ($el['is_redoublant'] ?? false),
                'moyenne_obtenue' => $moyenne,
                'rang' => $rang,
                'absences_non_justifiees' => $absencesNJ,
                'a_note_eliminatoire' => $aNoteEliminatoire,
                'mention_theorique' => $mentionTheorique,
                'mention_obtenue' => $delib?->mention_obtenue ?? $mentionEffective,
                'mention_bloquee' => $mentionBloquee,
                'motif_blocage_mention' => $motifBlocage,
                'statut_propose' => $statutPropose,
                'statut_retenu' => $statutRetenu,
                'appreciation_globale' => $appreciation,
                'decision_conseil' => $decisionTexte,
                'observations' => $delib?->observations ?? '',
                'est_valide' => $estValide,
                'valide_at' => $delib?->valide_at?->format('d/m/Y H:i'),
                'valide_par' => $delib?->valideParUser?->name,
            ];
        }

        return [
            'classe' => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'professeur_principal' => $classe->professeurPrincipal?->nom_complet ?? 'Non assigné',
            ],
            'type_deliberation' => $typeDeliberation,
            'periode' => $periode ? ['id' => $periode->id, 'nom' => $periode->nom] : null,
            'annee_scolaire' => ['id' => $anneeScolaire->id, 'nom' => $anneeScolaire->nom],
            'eleves' => $lignesDeliberations,
            'statistiques_classe' => $resultats['statistiques_classe'] ?? [],
            'toutes_validees' => count($lignesDeliberations) > 0 && collect($lignesDeliberations)->every('est_valide', true),
        ];
    }

    /**
     * Sauvegarde les décisions et appréciations du conseil de classe (Brouillon / Vote).
     *
     * @param Classe $classe
     * @param Periode|null $periode
     * @param AnneeScolaire $annee
     * @param array<int, array<string, mixed>> $lignes
     * @param User $user
     * @return int Nombre de délibérations enregistrées
     */
    public function sauvegarderDeliberations(
        Classe $classe,
        ?Periode $periode,
        AnneeScolaire $annee,
        array $lignes,
        User $user
    ): int {
        $etablissementId = $classe->niveau->cycle->section->etablissement_id;
        $typeDeliberation = $periode ? 'trimestrielle' : 'annuelle';
        $periodeId = $periode?->id;

        return DB::transaction(function () use (
            $etablissementId,
            $classe,
            $annee,
            $periodeId,
            $typeDeliberation,
            $lignes
        ) {
            $count = 0;
            foreach ($lignes as $ligne) {
                $inscriptionId = (int) $ligne['inscription_id'];

                DeliberationConseil::updateOrCreate(
                    [
                        'inscription_id' => $inscriptionId,
                        'type_deliberation' => $typeDeliberation,
                        'periode_id' => $periodeId,
                    ],
                    [
                        'etablissement_id' => $etablissementId,
                        'classe_id' => $classe->id,
                        'annee_scolaire_id' => $annee->id,
                        'moyenne_obtenue' => $ligne['moyenne_obtenue'] ?? 0.0,
                        'rang' => ! empty($ligne['rang']) ? (int) preg_replace('/[^0-9]/', '', (string) $ligne['rang']) : null,
                        'mention_obtenue' => $ligne['mention_obtenue'] ?? 'aucune',
                        'mention_bloquee' => (bool) ($ligne['mention_bloquee'] ?? false),
                        'motif_blocage_mention' => $ligne['motif_blocage_mention'] ?? null,
                        'statut_propose' => $ligne['statut_propose'] ?? 'en_attente',
                        'statut_retenu' => $ligne['statut_retenu'] ?? ($ligne['statut_propose'] ?? 'en_attente'),
                        'appreciation_globale' => $ligne['appreciation_globale'] ?? null,
                        'decision_conseil' => $ligne['decision_conseil'] ?? self::determinerDecisionTexte($ligne['statut_retenu'] ?? 'en_attente'),
                        'observations' => $ligne['observations'] ?? null,
                    ]
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * Valide définitivement la promotion des élèves d'une classe (Gesco 2.6 Étape B - validation humaine obligatoire).
     *
     * Aucune bascule ni statut officiel définitif n'est appliqué sans ce clic explicite du Censeur ou Chef d'Établissement.
     *
     * @param Classe $classe
     * @param AnneeScolaire $annee
     * @param array<int, array<string, mixed>> $decisions
     * @param User $user
     * @return int Nombre d'élèves validés
     */
    public function validerPromotions(
        Classe $classe,
        AnneeScolaire $annee,
        array $decisions,
        User $user
    ): int {
        $etablissementId = $classe->niveau->cycle->section->etablissement_id;

        return DB::transaction(function () use (
            $classe,
            $annee,
            $decisions,
            $user
        ) {
            $count = 0;
            foreach ($decisions as $dec) {
                $inscriptionId = (int) $dec['inscription_id'];
                $statutRetenu = $dec['statut_retenu'] ?? 'promu';

                $delib = DeliberationConseil::where('inscription_id', $inscriptionId)
                    ->where('type_deliberation', 'annuelle')
                    ->first();

                if ($delib) {
                    $delib->update([
                        'statut_retenu' => $statutRetenu,
                        'decision_conseil' => self::determinerDecisionTexte($statutRetenu),
                        'appreciation_globale' => $dec['appreciation_globale'] ?? $delib->appreciation_globale,
                        'est_valide' => true,
                        'valide_par_user_id' => $user->id,
                        'valide_at' => now(),
                    ]);
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * Génère l'appréciation textuelle par défaut du conseil selon la moyenne.
     *
     * @param float $moyenne
     * @return string
     */
    protected static function determinerAppreciationConseil(float $moyenne): string
    {
        if ($moyenne >= 16.0) {
            return 'Excellent travail, félicitations du conseil de classe.';
        }
        if ($moyenne >= 14.0) {
            return 'Très bon trimestre, travail sérieux et régulier.';
        }
        if ($moyenne >= 12.0) {
            return 'Bon ensemble, des résultats satisfaisants.';
        }
        if ($moyenne >= 10.0) {
            return 'Trimestre moyen, persévérez dans vos efforts.';
        }
        if ($moyenne >= 8.5) {
            return 'Résultats insuffisants, redoublez d\'efforts et de rigueur.';
        }

        return 'Travail très insuffisant, risque sérieux d\'échec scolaire.';
    }

    /**
     * Formate la décision officielle du conseil.
     *
     * @param string $statut
     * @return string
     */
    protected static function determinerDecisionTexte(string $statut): string
    {
        return match ($statut) {
            'promu' => 'Admis(e) en classe supérieure',
            'redouble' => 'Autorisé(e) à redoubler',
            'exclu' => 'Exclu(e) pour insuffisance de travail',
            default => 'En attente de délibération',
        };
    }
}

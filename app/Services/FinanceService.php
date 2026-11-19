<?php

namespace App\Services;

use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\CategorieFrais;
use App\Models\Classe;
use App\Models\ClotureCaisse;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\DispenseFinanciere;
use App\Models\Eleve;
use App\Models\EleveBourseRemise;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\ModeleEcheance;
use App\Models\Niveau;
use App\Models\PenaliteConfiguration;
use App\Models\PenaliteEleve;
use App\Models\Personnel;
use App\Models\ReglementDivers;
use App\Models\Section;
use App\Models\SouscriptionFraisOptionnel;
use App\Models\TypeFrais;
use App\Models\User;
use App\Models\VentilationVersement;
use App\Models\VersementCaisse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service Métier pour le Module 11 : Finance & Comptabilité Scolaire (Gesco V1.1).
 *
 * Gère l'ensemble de la logique financière :
 * - Initialisation et mise à jour des comptes courants élèves selon la grille tarifaire.
 * - Enregistrement des encaissements au guichet avec numérotation infalsifiable et ventilation fine.
 * - Procédure d'annulation administrative par émission d'avoir.
 * - Clôture journalière de la caisse avec verrouillage des écritures.
 * - Vérification du verrou de solvabilité pour le téléchargement des bulletins scolaires.
 */
class FinanceService
{
    /**
     * Récupère la caisse principale de l'établissement ou la crée automatiquement si elle n'existe pas.
     *
     * Permet au module financier de fonctionner de manière totalement transparente avec le rôle
     * Économe / Intendant sans obliger l'administrateur à paramétrer des caisses et guichets.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @return Caisse Instance de la caisse principale active
     */
    public function obtenirOuCreerCaissePrincipale(int $etablissementId): Caisse
    {
        $caisse = Caisse::where('etablissement_id', $etablissementId)
            ->where('is_principale', true)
            ->first()
            ?? Caisse::where('etablissement_id', $etablissementId)
                ->where('is_active', true)
                ->first();

        if ($caisse) {
            return $caisse;
        }

        return Caisse::create([
            'etablissement_id' => $etablissementId,
            'code' => 'CAISSE-PRIN',
            'nom' => 'Caisse Principale',
            'solde_initial' => 0,
            'is_principale' => true,
            'is_active' => true,
            'description' => "Caisse centrale d'encaissement de l'établissement",
        ]);
    }

    /**
     * Déduit automatiquement un numéro de tranche cohérent (1 à 9) à partir du libellé d'un frais du catalogue.
     *
     * Par exemple : « Tranche 2 SIL - CM2 » ou « 2ème Tranche » retourne 2, tandis que « Inscription » ou « CEPE » retourne 1.
     *
     * @param  string  $libelle  Libellé du frais configuré
     * @return int Numéro de tranche détecté (par défaut 1)
     */
    public function infererNumeroTrancheDepuisLibelle(string $libelle): int
    {
        if (preg_match('/tranche\s*([1-9])/iu', $libelle, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/([1-9])\s*(?:ère|ere|ème|eme)\s*tranche/iu', $libelle, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    /**
     * Retourne la collection ordonnée des frais scolaires obligatoires applicables à un niveau et une série.
     *
     * Règle d'héritage par rubrique et tranche :
     * - Si un tarif spécifique à la série de la classe existe pour un couple (type_frais_id, tranche_numero), il prime.
     * - Sinon, le tarif général du niveau (serie = null / vide, « Toutes séries ») s'applique automatiquement.
     *
     * @param  int  $anneeId  Identifiant de l'année scolaire
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  int|null  $niveauId  Identifiant du niveau pédagogique
     * @param  string|null  $serie  Série éventuelle de la classe (ex: C, D, A4, GENERAL, ALLEMAND)
     * @return Collection<int, FraisScolaire>
     */
    public function obtenirTarifsObligatoiresApplicables(
        int $anneeId,
        int $etablissementId,
        ?int $niveauId,
        ?string $serie = null
    ): Collection {
        if (! $niveauId) {
            return collect();
        }

        $tousTarifs = FraisScolaire::with('typeFrais.categorieFrais')
            ->where('annee_scolaire_id', $anneeId)
            ->where('etablissement_id', $etablissementId)
            ->where('niveau_id', $niveauId)
            ->where(function ($q) {
                $q->where('frais_scolaires.obligatoire', true)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('frais_scolaires.obligatoire')
                            ->whereHas('typeFrais', fn ($q3) => $q3->where('obligatoire', true));
                    });
            })
            ->orderBy('tranche_numero')
            ->orderBy('id')
            ->get();

        $serieNormalisee = $serie ? mb_strtoupper(trim($serie)) : null;

        // Filtrer les tarifs compatibles (toutes séries OU série exacte de la classe)
        $compatibles = $tousTarifs->filter(function (FraisScolaire $frais) use ($serieNormalisee) {
            $fraisSerie = $frais->serie ? mb_strtoupper(trim($frais->serie)) : null;
            if (! $fraisSerie) {
                return true;
            }

            return $serieNormalisee !== null && $fraisSerie === $serieNormalisee;
        });

        // Résolution de priorité : tarif série spécifique > tarif toutes séries par (type_frais_id, tranche_numero)
        // Puis tri selon l'ordre de priorité de l'établissement :
        // 1. Surcharge éventuelle `frais_scolaires.ordre_priorite`, sinon `types_frais.ordre_priorite` (ex: 1=APEE, 2=Inscription, 3=Scolarité)
        // 2. Numéro de tranche croissant (Tranche 1 -> Tranche 2 -> Tranche 3)
        // 3. Identifiant technique
        return $compatibles
            ->groupBy(fn (FraisScolaire $f) => $f->type_frais_id.'_'.$f->tranche_numero)
            ->map(function ($groupe) use ($serieNormalisee) {
                if ($serieNormalisee) {
                    $specifique = $groupe->first(fn (FraisScolaire $f) => ! empty($f->serie) && mb_strtoupper(trim($f->serie)) === $serieNormalisee);
                    if ($specifique) {
                        return $specifique;
                    }
                }

                return $groupe->first();
            })
            ->values()
            ->sortBy([
                fn (FraisScolaire $a, FraisScolaire $b) => (int) ($a->ordre_priorite ?? ($a->typeFrais?->ordre_priorite ?? 10))
                    <=> (int) ($b->ordre_priorite ?? ($b->typeFrais?->ordre_priorite ?? 10)),
                ['tranche_numero', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * Retourne la collection ordonnée des frais scolaires optionnels configurés pour un niveau donné.
     *
     * Un frais est considéré comme optionnel pour un niveau lorsque `frais_scolaires.obligatoire === false`
     * (ou lorsque `frais_scolaires.obligatoire` est null et `types_frais.obligatoire === false`).
     * Permet de proposer à l'économe/caissier les prestations ou frais spécifiques (ex: Inscription P CDE,
     * Inscription P TI, Transport, Cantine) à cocher sur le dossier de l'élève avant l'encaissement.
     *
     * @param  int  $anneeId  Identifiant de l'année scolaire
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  int|null  $niveauId  Identifiant du niveau pédagogique
     * @param  string|null  $serie  Série éventuelle de la classe
     * @return Collection<int, FraisScolaire>
     */
    public function obtenirTarifsOptionnelsApplicables(
        int $anneeId,
        int $etablissementId,
        ?int $niveauId,
        ?string $serie = null
    ): Collection {
        if (! $niveauId) {
            return collect();
        }

        $tarifsOptionnels = FraisScolaire::with('typeFrais.categorieFrais')
            ->where('annee_scolaire_id', $anneeId)
            ->where('etablissement_id', $etablissementId)
            ->where('niveau_id', $niveauId)
            ->where(function ($q) {
                $q->where('frais_scolaires.obligatoire', false)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('frais_scolaires.obligatoire')
                            ->whereHas('typeFrais', fn ($q3) => $q3->where('obligatoire', false));
                    });
            })
            ->orderBy('tranche_numero')
            ->orderBy('id')
            ->get();

        $serieNormalisee = $serie ? mb_strtoupper(trim($serie)) : null;

        return $tarifsOptionnels
            ->filter(function (FraisScolaire $frais) use ($serieNormalisee) {
                $fraisSerie = $frais->serie ? mb_strtoupper(trim($frais->serie)) : null;
                if (! $fraisSerie || $fraisSerie === 'GENERAL') {
                    return true;
                }

                return $serieNormalisee === null || $serieNormalisee === 'GENERAL' || $fraisSerie === $serieNormalisee;
            })
            ->sortBy([
                fn (FraisScolaire $a, FraisScolaire $b) => (int) ($a->ordre_priorite ?? ($a->typeFrais?->ordre_priorite ?? 10))
                    <=> (int) ($b->ordre_priorite ?? ($b->typeFrais?->ordre_priorite ?? 10)),
                ['tranche_numero', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * Synchronise automatiquement les comptes financiers de tous les élèves inscrits d'un établissement.
     *
     * Garantit qu'aucun élève inscrit (y compris par import CSV ou avant configuration des tarifs)
     * ne soit absent du guichet d'encaissement ou de la liste des comptes élèves.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  int|null  $anneeId  Identifiant de l'année scolaire active
     */
    public function synchroniserComptesEtablissement(int $etablissementId, ?int $anneeId): void
    {
        if (! $etablissementId || ! $anneeId) {
            return;
        }

        // Préchargement complet pour recalculer efficacement débits initiaux et statuts basés sur les dates d'échéance.
        $inscriptions = Inscription::withoutGlobalScopes()
            ->with([
                'classe.niveau',
                'compteFinancier.inscription.classe.niveau',
                'compteFinancier.boursesRemises.bourseRemise',
                'compteFinancier.dispensesFinancieres.fraisScolaire',
                'compteFinancier.souscriptionsOptionnelles.fraisScolaire.typeFrais.categorieFrais',
                'compteFinancier.penalites',
                'compteFinancier.versements.ventilations',
            ])
            ->where('annee_scolaire_id', $anneeId)
            ->where(function ($q) use ($etablissementId) {
                $q->where('etablissement_id', $etablissementId)
                    ->orWhereHas('classe', fn ($cq) => $cq->where('etablissement_id', $etablissementId));
            })
            ->whereNotIn('statut', ['annulee', 'radie'])
            ->get();

        // Caches locaux par couple (niveau_id, serie) pour éviter les requêtes N+1
        $tarifsParNiveauSerie = [];
        $debitParNiveauSerie = [];
        $aujourdhui = Carbon::today();

        foreach ($inscriptions as $inscription) {
            $compte = $inscription->compteFinancier;

            if (! $compte) {
                $this->initialiserCompteEleve($inscription);

                continue;
            }

            $nivId = $inscription->classe?->niveau_id;
            $ser = $inscription->classe?->serie ? mb_strtoupper(trim($inscription->classe->serie)) : '';
            $cleCache = ($nivId ?? 0).'_'.$ser;

            if (! array_key_exists($cleCache, $tarifsParNiveauSerie)) {
                $tarifs = $this->obtenirTarifsObligatoiresApplicables(
                    $anneeId,
                    $etablissementId,
                    $nivId,
                    $inscription->classe?->serie
                )->values();
                $tarifsParNiveauSerie[$cleCache] = $tarifs;
                $debitParNiveauSerie[$cleCache] = (float) $tarifs->sum('montant');
            }

            // Si le compte avait été créé avec un débit nul avant le paramétrage de la grille tarifaire, on l'actualise
            if ((float) $compte->debit_initial <= 0) {
                $nouveauDebit = $debitParNiveauSerie[$cleCache];
                if ($nouveauDebit > 0) {
                    $compte->debit_initial = $nouveauDebit;
                    $compte->actualiserSolde();

                    continue;
                }
            }

            // Rafraîchit le statut stocké en base dès qu'une date limite d'échéance change l'éligibilité.
            $statutAttendu = $this->determinerStatutFinancierCompte(
                $compte,
                $aujourdhui,
                $tarifsParNiveauSerie[$cleCache]
            );

            if ($compte->statut_financier !== $statutAttendu) {
                $compte->statut_financier = $statutAttendu;
                $compte->saveQuietly();
            }
        }
    }

    /**
     * Recalcule immédiatement les comptes financiers de tous les élèves d'un niveau donné
     * suite à l'ajout, la modification ou la suppression d'un tarif dans la matrice scolaire.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  int  $anneeId  Identifiant de l'année scolaire
     * @param  int  $niveauId  Identifiant du niveau modifié
     */
    public function recalculerComptesNiveau(int $etablissementId, int $anneeId, int $niveauId): void
    {
        $inscriptions = Inscription::withoutGlobalScopes()
            ->with(['classe', 'compteFinancier'])
            ->where('annee_scolaire_id', $anneeId)
            ->whereHas('classe', fn ($q) => $q->where('niveau_id', $niveauId))
            ->whereNotIn('statut', ['annulee', 'radie'])
            ->get();

        foreach ($inscriptions as $inscription) {
            $this->initialiserCompteEleve($inscription);
        }
    }

    /**
     * Initialise ou recalcule le compte financier d'un élève lors de son inscription.
     *
     * Applique la matrice tarifaire selon l'algorithme d'héritage (Niveau + Série spécifique,
     * ou Niveau + Toutes les séries par défaut).
     *
     * @param  Inscription  $inscription  Inscription validée ou en cours
     * @param  float  $remiseBourse  Remise ou bourse éventuelle accordée
     * @param  float  $arrieresAnterieurs  Dette ou reliquat d'une année précédente reporté manuellement
     */
    public function initialiserCompteEleve(
        Inscription $inscription,
        float $remiseBourse = 0,
        float $arrieresAnterieurs = 0
    ): CompteFinancierEleve {
        $classe = $inscription->classe()->with('niveau.cycle.section.etablissement')->first();
        $niveauId = $classe?->niveau_id;
        $serie = $classe?->serie;
        $etablissementId = $inscription->etablissement_id
            ?? $classe?->etablissement_id
            ?? $classe?->niveau?->cycle?->section?->etablissement_id
            ?? Etablissement::first()?->id;
        $anneeId = (int) $inscription->annee_scolaire_id;

        // 1. Recherche des tarifs obligatoires applicables selon la règle de priorité Série > Défaut
        $tarifsApplicables = $this->obtenirTarifsObligatoiresApplicables(
            $anneeId,
            (int) $etablissementId,
            $niveauId,
            $serie
        );

        $debitInitial = (float) $tarifsApplicables->sum('montant');

        // 2. Recherche ou création du compte financier
        $compte = CompteFinancierEleve::withoutGlobalScopes()->firstOrNew([
            'inscription_id' => $inscription->id,
        ]);

        $compte->eleve_id = $inscription->eleve_id;
        $compte->etablissement_id = $etablissementId;
        $compte->annee_scolaire_id = $anneeId;
        $compte->debit_initial = $debitInitial;
        if ($arrieresAnterieurs > 0) {
            $compte->arrieres_anterieurs = $arrieresAnterieurs;
        }
        if ($remiseBourse > 0) {
            $compte->remise_bourse = $remiseBourse;
        }
        $compte->save();

        // 3. Actualisation des soldes et des statuts
        $compte->actualiserSolde();

        return $compte;
    }

    /**
     * Génère un numéro de reçu séquentiel unique pour l'année en cours (ex: REC-EPSJ-2026-000001).
     * Intègre le code officiel de l'établissement pour éliminer toute collision multi-tenant.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @return string Numéro officiel de reçu
     */
    public function genererReferenceRecu(int $etablissementId): string
    {
        $annee = Carbon::now()->year;
        $etab = Etablissement::find($etablissementId);
        $code = $etab?->code ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $etab->code)) : "E{$etablissementId}";
        $prefix = "REC-{$code}-{$annee}-";

        $dernierVersement = VersementCaisse::withoutGlobalScopes()
            ->where('etablissement_id', $etablissementId)
            ->where('reference_recu', 'LIKE', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($dernierVersement) {
            $numero = (int) substr($dernierVersement->reference_recu, strlen($prefix)) + 1;
        } else {
            $numero = 1;
        }

        return $prefix.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Enregistre un encaissement physique de caisse avec sa ventilation et actualise l'ardoise élève.
     *
     * @param array{
     *     compte_financier_id: int,
     *     montant_total: float,
     *     mode_paiement: string,
     *     reference_paiement?: string|null,
     *     payeur_nom?: string|null,
     *     payeur_telephone?: string|null,
     *     observations?: string|null,
     *     ventilations: array<int, array{type_frais_id?: int|null, frais_scolaire_id?: int|null, libelle: string, montant: float}>
     * } $data Données de paiement
     * @param  Personnel|null  $caissier  Personnel ayant encaissé
     * @param  User|null  $user  Utilisateur connecté
     *
     * @throws InvalidArgumentException En cas de caisse clôturée ou d'incohérence de ventilation
     */
    /**
     * Valide et résout la date effective d'un versement (aujourd'hui par défaut, ou date passée raisonnable).
     * Empêche les dates futures (ex: 2028) ainsi que les saisies manifestement aberrantes antérieures à l'exercice.
     *
     * @param  string|null  $dateSaisie  Date transmise depuis le formulaire (optionnelle)
     * @param  CompteFinancierEleve  $compte  Compte financier élève concerné
     *
     * @throws InvalidArgumentException Si la date est invalide, future ou hors borne raisonnable
     */
    private function resoudreDateVersement(?string $dateSaisie, CompteFinancierEleve $compte): Carbon
    {
        if (empty($dateSaisie)) {
            return Carbon::now();
        }

        $parsed = Carbon::parse($dateSaisie);
        $todayEnd = Carbon::today()->endOfDay();
        if ($parsed->gt($todayEnd)) {
            throw new InvalidArgumentException(
                'La date de versement ne peut pas être dans le futur (maximum : '.Carbon::today()->format('d-m-Y').').'
            );
        }

        $minDate = $this->obtenirDateMinimaleVersement($compte);
        if ($parsed->lt($minDate)) {
            throw new InvalidArgumentException(
                "La date de versement ne peut pas être antérieure au {$minDate->format('d-m-Y')}."
            );
        }

        // Conserve l'heure courante pour aujourd'hui, sinon fixe midi sur la date passée pour éviter tout décalage horaire
        if ($parsed->toDateString() === Carbon::today()->toDateString()) {
            return $parsed->format('H:i:s') === '00:00:00' ? Carbon::now() : $parsed;
        }

        return $parsed->format('H:i:s') === '00:00:00'
            ? $parsed->copy()->setTime(12, 0, 0)
            : $parsed;
    }

    /**
     * Calcule la date minimale autorisée pour la saisie rétroactive d'un versement sur un compte.
     *
     * @param  CompteFinancierEleve  $compte  Compte financier élève
     */
    private function obtenirDateMinimaleVersement(CompteFinancierEleve $compte): Carbon
    {
        $plancherAbsolu = Carbon::create(2020, 1, 1)->startOfDay();
        $dateDebutAnnee = $compte->anneeScolaire?->date_debut;

        if (! $dateDebutAnnee) {
            return $plancherAbsolu;
        }

        $minAnnee = Carbon::parse($dateDebutAnnee)->subMonths(6)->startOfDay();

        return $minAnnee->gt($plancherAbsolu) ? $minAnnee : $plancherAbsolu;
    }

    /**
     * Détermine la date limite d'éligibilité au paiement anticipé avant rentrée scolaire.
     *
     * @param  CompteFinancierEleve  $compte  Compte financier élève
     * @param  Collection<int, FraisScolaire>|null  $fraisObligatoires  Tranches obligatoires du niveau si déjà chargées
     */
    private function obtenirDateLimiteRentree(CompteFinancierEleve $compte, ?Collection $fraisObligatoires = null): ?string
    {
        if ($compte->anneeScolaire?->date_debut) {
            return Carbon::parse($compte->anneeScolaire->date_debut)->toDateString();
        }

        $tarifs = $fraisObligatoires ?? $this->obtenirTarifsObligatoiresApplicables(
            (int) $compte->annee_scolaire_id,
            (int) $compte->etablissement_id,
            $compte->inscription?->classe?->niveau_id,
            $compte->inscription?->classe?->serie
        );

        $premiereLimite = $tarifs
            ->pluck('date_limite')
            ->filter()
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->sort()
            ->first();

        return $premiereLimite ?: null;
    }

    /**
     * Recherche le modèle actif de remise anticipée avant rentrée (ex: REM-ANTICIPE-4) pour un établissement.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  int|null  $modeleId  Identifiant explicite du modèle si transmis par l'interface
     */
    private function trouverModeleRemiseAnticipee(int $etablissementId, ?int $modeleId = null): ?BourseRemise
    {
        if ($modeleId) {
            return BourseRemise::where('etablissement_id', $etablissementId)
                ->where('id', $modeleId)
                ->where('is_active', true)
                ->first();
        }

        return BourseRemise::where('etablissement_id', $etablissementId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'REM-ANTICIPE-4')
                    ->orWhere(function ($sub) {
                        $sub->where('type', 'remise')
                            ->where('nom', 'like', '%anticip%');
                    });
            })
            ->orderByRaw("CASE WHEN code = 'REM-ANTICIPE-4' THEN 0 ELSE 1 END")
            ->first();
    }

    public function enregistrerVersement(array $data, ?Personnel $caissier, ?User $user): VersementCaisse
    {
        $compte = CompteFinancierEleve::with(['inscription.classe', 'anneeScolaire'])->findOrFail($data['compte_financier_id']);
        $etablissementId = $compte->etablissement_id;
        $dateVersement = $this->resoudreDateVersement($data['date_versement'] ?? null, $compte);
        $dateCible = $dateVersement->toDateString();

        // 1. Vérification anti-fraude : la journée ciblée ne doit pas être déjà clôturée pour ce caissier
        $clotureExistante = ClotureCaisse::where('etablissement_id', $etablissementId)
            ->whereDate('date_cloture', $dateCible)
            ->where('personnel_id', $caissier?->id)
            ->where('statut', 'cloturee')
            ->first();

        if ($clotureExistante) {
            $suffixDate = $dateVersement->isToday() ? '' : " (journée du {$dateVersement->format('d-m-Y')})";
            throw new InvalidArgumentException("Impossible d'encaisser : la caisse de cette journée a déjà été clôturée.{$suffixDate}");
        }

        // 2. Vérification de la concordance de la ventilation
        $sommeVentilation = array_reduce($data['ventilations'], fn ($carry, $item) => $carry + (float) $item['montant'], 0.0);
        if (abs($sommeVentilation - (float) $data['montant_total']) > 0.01) {
            throw new InvalidArgumentException('Le montant total ('.number_format((float) $data['montant_total'], 0).' FCFA) ne correspond pas à la somme des ventilations ('.number_format($sommeVentilation, 0).' FCFA).');
        }

        $appliquerRemiseAnticipee = ! empty($data['appliquer_remise_anticipee']);
        $modeleAnticipeId = ! empty($data['bourse_remise_anticipee_id']) ? (int) $data['bourse_remise_anticipee_id'] : null;

        return DB::transaction(function () use ($data, $compte, $etablissementId, $caissier, $user, $dateVersement, $appliquerRemiseAnticipee, $modeleAnticipeId) {
            // 2b. Application atomique optionnelle de la remise anticipée avant rentrée (ex: 4 %) avant le contrôle du solde
            if ($appliquerRemiseAnticipee) {
                $modeleAnticipe = $this->trouverModeleRemiseAnticipee($etablissementId, $modeleAnticipeId);
                if (! $modeleAnticipe) {
                    throw new InvalidArgumentException("Aucun modèle actif de remise anticipée n'est configuré pour cet établissement.");
                }

                $dateLimiteRentree = $this->obtenirDateLimiteRentree($compte);
                if ($dateLimiteRentree && $dateVersement->toDateString() > $dateLimiteRentree) {
                    throw new InvalidArgumentException(
                        'La remise anticipée ne peut être accordée que pour un versement daté au plus tard le '.Carbon::parse($dateLimiteRentree)->format('d-m-Y').'.'
                    );
                }

                $dejaAttribuee = $compte->boursesRemises()
                    ->where('bourse_remise_id', $modeleAnticipe->id)
                    ->exists();

                if (! $dejaAttribuee) {
                    $this->attribuerBourseRemise(
                        $compte,
                        $modeleAnticipe,
                        "Paiement anticipé avant la rentrée ({$dateVersement->format('d-m-Y')})",
                        $user
                    );
                }
            }

            // 3. Règle comptable stricte : le montant versé ne peut jamais excéder le solde restant à payer
            $compte->actualiserSolde();
            $montantTotalSaisi = round((float) $data['montant_total'], 2);
            $soldeRestantActuel = round((float) $compte->solde_restant, 2);

            if ($soldeRestantActuel <= 0) {
                throw new InvalidArgumentException(
                    "Impossible d'encaisser : ce compte élève est déjà intégralement soldé (Reste à payer : 0 FCFA). Si un frais optionnel concerne cet élève, veuillez d'abord l'activer sur son dossier."
                );
            }

            if ($montantTotalSaisi > $soldeRestantActuel + 0.01) {
                throw new InvalidArgumentException(
                    'Le montant du versement ('.number_format($montantTotalSaisi, 0, ',', ' ').' FCFA) ne peut pas dépasser le reste à payer de l\'élève ('.number_format($soldeRestantActuel, 0, ',', ' ').' FCFA).'
                );
            }

            // Résolution transparente de la caisse cible (caisse spécifiée ou Caisse Principale auto-créée)
            $caisseId = ! empty($data['caisse_id'])
                ? (int) $data['caisse_id']
                : $this->obtenirOuCreerCaissePrincipale($etablissementId)->id;

            // Génération du numéro officiel séquentiel de reçu
            $referenceRecu = $this->genererReferenceRecu($etablissementId);

            // Création du versement avec sa date réelle (courante ou rétroactive papier)
            $versement = VersementCaisse::create([
                'reference_recu' => $referenceRecu,
                'compte_financier_id' => $compte->id,
                'inscription_id' => $compte->inscription_id,
                'etablissement_id' => $etablissementId,
                'caisse_id' => $caisseId,
                'personnel_id' => $caissier?->id,
                'date_versement' => $dateVersement,
                'montant_total' => $data['montant_total'],
                'mode_paiement' => $data['mode_paiement'],
                'reference_paiement' => $data['reference_paiement'] ?? null,
                'recu_par' => $caissier ? ($caissier->nom.' '.$caissier->prenom) : ($user?->name ?? 'Service Intendance'),
                'payeur_nom' => $data['payeur_nom'] ?? null,
                'payeur_telephone' => $data['payeur_telephone'] ?? null,
                'statut' => 'valide',
                'observations' => $data['observations'] ?? null,
            ]);

            // Enregistrement des rubriques ventilées
            foreach ($data['ventilations'] as $vent) {
                VentilationVersement::create([
                    'versement_id' => $versement->id,
                    'type_frais_id' => $vent['type_frais_id'] ?? null,
                    'frais_scolaire_id' => $vent['frais_scolaire_id'] ?? null,
                    'libelle' => $vent['libelle'],
                    'montant' => $vent['montant'],
                ]);
            }

            // Actualisation automatique du solde élève
            $compte->actualiserSolde();

            return $versement->load('ventilations', 'compteFinancier.eleve', 'inscription.classe');
        });
    }

    /**
     * Modifie un versement valide existant (montant, date, mode de paiement, payeur, référence)
     * et recalcule atomiquement sa ventilation par tranches ainsi que le solde financier de l'élève.
     *
     * Conserve le numéro officiel du reçu (`reference_recu`), vérifie l'absence de clôture de caisse
     * sur la date d'origine et la nouvelle date, contrôle que le nouveau montant ne dépasse pas le plafond
     * exigible restant de l'élève (hors ancien montant du reçu modifié) et met immédiatement à jour
     * le compte élève et l'ensemble des états financiers.
     *
     * @param  VersementCaisse  $versement  Versement valide à modifier
     * @param  array<string, mixed>  $data  Nouvelles données du versement (montant_total, date_versement, mode_paiement, etc.)
     * @param  User|null  $user  Utilisateur effectuant la modification
     * @return VersementCaisse Versement mis à jour avec ses nouvelles ventilations
     *
     * @throws InvalidArgumentException Si le reçu est annulé, si la caisse est clôturée ou si le montant dépasse le reste dû
     */
    public function modifierVersement(
        VersementCaisse $versement,
        array $data,
        ?User $user = null
    ): VersementCaisse {
        if ($versement->statut !== 'valide') {
            throw new InvalidArgumentException('Impossible de modifier un versement qui a déjà été annulé.');
        }

        if ($versement->cloture_caisse_id !== null) {
            throw new InvalidArgumentException('Ce versement appartient à une journée de caisse déjà clôturée et verrouillée.');
        }

        return DB::transaction(function () use ($versement, $data) {
            /** @var CompteFinancierEleve $compte */
            $compte = CompteFinancierEleve::with([
                'eleve',
                'inscription.classe.niveau',
                'anneeScolaire',
                'etablissement',
            ])->findOrFail($versement->compte_financier_id);

            $dateOrigineIso = Carbon::parse($versement->date_versement)->toDateString();

            // Vérification anti-fraude : la journée d'origine ne doit pas être clôturée
            $clotureOrigine = ClotureCaisse::where('etablissement_id', $versement->etablissement_id)
                ->whereDate('date_cloture', $dateOrigineIso)
                ->where('statut', 'cloturee')
                ->when($versement->personnel_id, fn ($q) => $q->where('personnel_id', $versement->personnel_id))
                ->exists();

            if ($clotureOrigine) {
                throw new InvalidArgumentException(
                    'La caisse du '.Carbon::parse($dateOrigineIso)->format('d-m-Y').' est déjà clôturée. Impossible de modifier une écriture figée.'
                );
            }

            // Résolution et validation de la nouvelle date de versement
            $dateVersement = ! empty($data['date_versement'])
                ? $this->resoudreDateVersement((string) $data['date_versement'], $compte)
                : Carbon::parse($versement->date_versement);

            $nouvelleDateIso = $dateVersement->toDateString();
            if ($nouvelleDateIso !== $dateOrigineIso) {
                $clotureCible = ClotureCaisse::where('etablissement_id', $versement->etablissement_id)
                    ->whereDate('date_cloture', $nouvelleDateIso)
                    ->where('statut', 'cloturee')
                    ->when($versement->personnel_id, fn ($q) => $q->where('personnel_id', $versement->personnel_id))
                    ->exists();

                if ($clotureCible) {
                    throw new InvalidArgumentException(
                        'La caisse du '.$dateVersement->format('d-m-Y').' est déjà clôturée. Veuillez sélectionner une date ouverte.'
                    );
                }
            }

            // Détermination du nouveau montant total
            $ventilationsFournies = ! empty($data['ventilations']) && is_array($data['ventilations'])
                ? $data['ventilations']
                : [];
            $sommeVentilationsFournies = round((float) collect($ventilationsFournies)->sum('montant'), 2);

            $nouveauMontant = isset($data['montant_total']) && $data['montant_total'] !== ''
                ? round((float) $data['montant_total'], 2)
                : $sommeVentilationsFournies;

            if ($nouveauMontant <= 0) {
                throw new InvalidArgumentException('Le montant du versement doit être strictement supérieur à 0 FCFA.');
            }

            // Neutralisation temporaire de l'ancien versement dans la transaction afin d'évaluer
            // le reste exact à payer et l'échéancier de l'élève avant l'application du nouveau montant
            $versement->ventilations()->delete();
            $versement->update(['montant_total' => 0]);

            $compte->actualiserSolde();
            $compte->refresh();

            $plafondAutorise = round((float) $compte->solde_restant, 2);
            if ($nouveauMontant > $plafondAutorise + 0.01) {
                $montantFormate = number_format($nouveauMontant, 0, ',', ' ');
                $plafondFormate = number_format($plafondAutorise, 0, ',', ' ');

                throw new InvalidArgumentException(
                    "Le montant modifié ({$montantFormate} FCFA) dépasse le reste à payer autorisé sur ce dossier ({$plafondFormate} FCFA)."
                );
            }

            // Calcul des nouvelles ventilations : ciblage manuel explicite ou cascade automatique par ordre de priorité
            $modeAllocation = (string) ($data['mode_allocation'] ?? 'auto');
            $nouvellesVentilations = [];

            if ($modeAllocation === 'manuel' && ! empty($ventilationsFournies) && abs($sommeVentilationsFournies - $nouveauMontant) <= 0.01) {
                foreach ($ventilationsFournies as $vent) {
                    $nouvellesVentilations[] = [
                        'type_frais_id' => ! empty($vent['type_frais_id']) ? (int) $vent['type_frais_id'] : null,
                        'frais_scolaire_id' => ! empty($vent['frais_scolaire_id']) ? (int) $vent['frais_scolaire_id'] : null,
                        'libelle' => (string) $vent['libelle'],
                        'montant' => round((float) $vent['montant'], 2),
                    ];
                }
            } else {
                /** @var CompteFinancierEleve $compteFrais */
                $compteFrais = CompteFinancierEleve::with([
                    'eleve',
                    'inscription.classe.niveau',
                    'boursesRemises.bourseRemise',
                    'dispensesFinancieres.fraisScolaire.typeFrais',
                    'souscriptionsOptionnelles.fraisScolaire.typeFrais.categorieFrais',
                    'penalites',
                    'versements.ventilations',
                ])->findOrFail($compte->id);

                $structure = $this->construireLignesEcheancier($compteFrais);
                $reliquat = $nouveauMontant;

                foreach ($structure['lignes'] as $ligne) {
                    if ($reliquat <= 0.009) {
                        break;
                    }
                    $restantLigne = round((float) ($ligne['montant_restant'] ?? 0), 2);
                    if ($restantLigne <= 0.009) {
                        continue;
                    }
                    $affecte = round(min($reliquat, $restantLigne), 2);
                    $nouvellesVentilations[] = [
                        'type_frais_id' => ! empty($ligne['type_frais_id']) ? (int) $ligne['type_frais_id'] : null,
                        'frais_scolaire_id' => in_array($ligne['type'] ?? '', ['obligatoire', 'optionnel'], true) && ! empty($ligne['id'])
                            ? (int) $ligne['id']
                            : null,
                        'libelle' => (string) $ligne['libelle'],
                        'montant' => $affecte,
                    ];
                    $reliquat = round($reliquat - $affecte, 2);
                }

                if ($reliquat > 0.009) {
                    $nouvellesVentilations[] = [
                        'type_frais_id' => null,
                        'frais_scolaire_id' => null,
                        'libelle' => 'Scolarité / Acompte',
                        'montant' => $reliquat,
                    ];
                }
            }

            // Mise à jour de l'écriture de versement (tout en préservant le numéro de reçu d'origine)
            $versement->update([
                'date_versement' => $dateVersement,
                'montant_total' => $nouveauMontant,
                'mode_paiement' => $data['mode_paiement'] ?? $versement->mode_paiement,
                'reference_paiement' => array_key_exists('reference_paiement', $data)
                    ? ($data['reference_paiement'] ?: null)
                    : $versement->reference_paiement,
                'payeur_nom' => array_key_exists('payeur_nom', $data)
                    ? ($data['payeur_nom'] ?: null)
                    : $versement->payeur_nom,
                'payeur_telephone' => array_key_exists('payeur_telephone', $data)
                    ? ($data['payeur_telephone'] ?: null)
                    : $versement->payeur_telephone,
                'observations' => array_key_exists('observations', $data)
                    ? ($data['observations'] ?: null)
                    : $versement->observations,
            ]);

            foreach ($nouvellesVentilations as $vent) {
                VentilationVersement::create([
                    'versement_id' => $versement->id,
                    'type_frais_id' => $vent['type_frais_id'],
                    'frais_scolaire_id' => $vent['frais_scolaire_id'],
                    'libelle' => $vent['libelle'],
                    'montant' => $vent['montant'],
                ]);
            }

            // Recalcul complet du dossier financier de l'élève (total payé, solde restant, statut, remises)
            $compte->actualiserSolde();

            return $versement->fresh(['ventilations', 'compteFinancier.eleve', 'inscription.classe']);
        });
    }

    /**
     * Annule administrativement un versement avec motif obligatoire et réajustement du solde.
     *
     * @param  VersementCaisse  $versement  Versement à annuler
     * @param  string  $motif  Justification de l'annulation
     * @param  User  $annulateur  Administrateur ayant validé l'annulation
     *
     * @throws InvalidArgumentException
     */
    public function annulerVersement(VersementCaisse $versement, string $motif, User $annulateur): VersementCaisse
    {
        if ($versement->statut === 'annule') {
            throw new InvalidArgumentException('Ce versement a déjà été annulé.');
        }

        return DB::transaction(function () use ($versement, $motif, $annulateur) {
            $versement->update([
                'statut' => 'annule',
                'motif_annulation' => $motif,
                'annule_par' => $annulateur->id,
                'annule_le' => Carbon::now(),
            ]);

            // Recalcul du solde de l'élève
            $versement->compteFinancier->actualiserSolde();

            return $versement;
        });
    }

    /**
     * Annule en une seule opération atomique un lot de versements sélectionnés (ex: 8 opérations d'un même élève)
     * avec un motif d'audit commun et recalcule une seule fois chaque compte financier élève impacté.
     *
     * @param  array<int, int>  $versementIds  Identifiants des versements à annuler
     * @param  string  $motif  Justification obligatoire de l'annulation groupée
     * @param  User  $annulateur  Utilisateur autorisé ayant validé l'annulation
     * @param  int|null  $etablissementId  Identifiant d'établissement pour cloisonner le périmètre (null si super-admin global)
     * @return array{nb_annules: int, montant_total_annule: float, nb_comptes_impactes: int}
     *
     * @throws InvalidArgumentException
     */
    public function annulerVersementsEnLot(
        array $versementIds,
        string $motif,
        User $annulateur,
        ?int $etablissementId = null
    ): array {
        $ids = array_values(array_unique(array_map('intval', $versementIds)));
        if (empty($ids)) {
            throw new InvalidArgumentException('Veuillez sélectionner au moins un versement à annuler.');
        }

        return DB::transaction(function () use ($ids, $motif, $annulateur, $etablissementId) {
            $query = VersementCaisse::with('compteFinancier')
                ->whereIn('id', $ids)
                ->where('statut', 'valide');

            if ($etablissementId !== null) {
                $query->where('etablissement_id', $etablissementId);
            }

            $versements = $query->get();

            if ($versements->isEmpty()) {
                throw new InvalidArgumentException('Aucun versement valide à annuler parmi la sélection.');
            }

            $maintenant = Carbon::now();
            $montantTotalAnnule = 0.0;
            $comptesImpactes = [];

            foreach ($versements as $versement) {
                $versement->update([
                    'statut' => 'annule',
                    'motif_annulation' => $motif,
                    'annule_par' => $annulateur->id,
                    'annule_le' => $maintenant,
                ]);

                $montantTotalAnnule += (float) $versement->montant_total;
                if ($versement->compteFinancier) {
                    $comptesImpactes[$versement->compte_financier_id] = $versement->compteFinancier;
                }
            }

            foreach ($comptesImpactes as $compte) {
                $compte->actualiserSolde();
            }

            return [
                'nb_annules' => $versements->count(),
                'montant_total_annule' => $montantTotalAnnule,
                'nb_comptes_impactes' => count($comptesImpactes),
            ];
        });
    }

    /**
     * Clôture la caisse d'une journée donnée, fige les écritures et génère le journal récapitulatif.
     *
     * @param  int  $etablissementId  Identifiant établissement
     * @param  int|null  $personnelId  Caissier / économe concerné
     * @param  string  $date  Date au format Y-m-d
     * @param  string|null  $observations  Remarques éventuelles
     * @param  User  $user  Utilisateur effectuant l'action
     */
    public function cloturerCaisse(
        int $etablissementId,
        ?int $personnelId,
        string $date,
        ?string $observations,
        User $user
    ): ClotureCaisse {
        return DB::transaction(function () use ($etablissementId, $personnelId, $date, $observations, $user) {
            $debut = Carbon::parse($date)->startOfDay();
            $fin = Carbon::parse($date)->endOfDay();

            $query = VersementCaisse::where('etablissement_id', $etablissementId)
                ->whereBetween('date_versement', [$debut, $fin])
                ->where('statut', 'valide');

            if ($personnelId) {
                $query->where('personnel_id', $personnelId);
            }

            $versements = $query->get();

            $totalEspeces = (float) $versements->where('mode_paiement', 'especes')->sum('montant_total');
            $totalCheques = (float) $versements->where('mode_paiement', 'cheque')->sum('montant_total');
            $totalVirements = (float) $versements->where('mode_paiement', 'virement')->sum('montant_total');
            $totalMobileMoney = (float) $versements->where('mode_paiement', 'mobile_money_manuel')->sum('montant_total');
            $totalGeneral = (float) $versements->sum('montant_total');
            $nbTransactions = $versements->count();

            $cloture = ClotureCaisse::updateOrCreate(
                [
                    'etablissement_id' => $etablissementId,
                    'personnel_id' => $personnelId,
                    'date_cloture' => $date,
                ],
                [
                    'total_especes' => $totalEspeces,
                    'total_cheques' => $totalCheques,
                    'total_virements' => $totalVirements,
                    'total_mobile_money' => $totalMobileMoney,
                    'total_general' => $totalGeneral,
                    'nombre_transactions' => $nbTransactions,
                    'statut' => 'cloturee',
                    'observations' => $observations,
                    'cloture_a' => Carbon::now(),
                    'verifie_par' => $user->id,
                ]
            );

            // Rattachement des versements à cette clôture
            VersementCaisse::whereIn('id', $versements->pluck('id'))
                ->update(['cloture_caisse_id' => $cloture->id]);

            return $cloture;
        });
    }

    /**
     * Vérifie si le verrou de solvabilité de l'établissement bloque l'accès aux bulletins scolaires.
     *
     * @return array{bloque: bool, solde_restant: float, message: string|null}
     */
    public function verifierSolvabiliteBulletin(Inscription $inscription): array
    {
        $etablissement = $inscription->eleve?->etablissement ?? $inscription->classe?->etablissement;

        // Si le verrou n'est pas activé au niveau établissement, le bulletin est libre d'accès
        if (! $etablissement || ! $etablissement->verrou_solvabilite_actif) {
            return [
                'bloque' => false,
                'solde_restant' => 0.0,
                'message' => null,
            ];
        }

        $compte = $inscription->compteFinancier()->first();
        if (! $compte) {
            $compte = $this->initialiserCompteEleve($inscription);
        }

        $soldeRestant = (float) $compte->solde_restant;

        if ($soldeRestant > 0) {
            $nomEleve = trim($inscription->eleve->nom.' '.($inscription->eleve->prenom ?? ''));

            return [
                'bloque' => true,
                'solde_restant' => $soldeRestant,
                'message' => "Accès restreint. Veuillez régulariser le solde financier de {$nomEleve} auprès de l'intendance pour débloquer le bulletin.",
            ];
        }

        return [
            'bloque' => false,
            'solde_restant' => 0.0,
            'message' => null,
        ];
    }

    /**
     * Génère une référence unique pour un règlement divers (ex: REC-DIV-EPSJ-2026-000001).
     * Intègre le code officiel de l'établissement pour éliminer toute collision multi-tenant.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @return string Référence unique
     */
    public function genererReferenceReglementDivers(int $etablissementId): string
    {
        $annee = Carbon::now()->year;
        $etab = Etablissement::find($etablissementId);
        $code = $etab?->code ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $etab->code)) : "E{$etablissementId}";
        $prefix = "REC-DIV-{$code}-{$annee}-";

        $dernierReglement = ReglementDivers::withoutGlobalScopes()
            ->where('etablissement_id', $etablissementId)
            ->where('reference_recu', 'LIKE', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $numero = $dernierReglement
            ? ((int) substr($dernierReglement->reference_recu, strlen($prefix)) + 1)
            : 1;

        return $prefix.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Enregistre un encaissement libre hors scolarité (Aurore §4.2).
     *
     * Résout automatiquement la Caisse Principale de l'établissement si aucune caisse
     * spécifique n'est transmise, afin de simplifier l'utilisation quotidienne.
     *
     * @param array{
     *     caisse_id?: int|null,
     *     etablissement_id?: int|null,
     *     payeur_nom: string,
     *     payeur_telephone?: string|null,
     *     categorie: string,
     *     libelle: string,
     *     montant: float,
     *     mode_paiement: string,
     *     reference_paiement?: string|null,
     *     observations?: string|null
     * } $data Données de la recette
     * @param  Personnel|null  $caissier  Personnel ayant encaissé
     * @param  User|null  $user  Utilisateur connecté
     */
    public function enregistrerReglementDivers(array $data, ?Personnel $caissier, ?User $user): ReglementDivers
    {
        if (! empty($data['caisse_id'])) {
            $caisse = Caisse::findOrFail($data['caisse_id']);
        } elseif (! empty($data['etablissement_id'])) {
            $caisse = $this->obtenirOuCreerCaissePrincipale((int) $data['etablissement_id']);
        } else {
            throw new InvalidArgumentException("Impossible de déterminer l'établissement d'encaissement.");
        }

        $etablissementId = $caisse->etablissement_id;
        $today = Carbon::today()->toDateString();

        // Contrôle anti-fraude caisse clôturée
        $clotureExistante = ClotureCaisse::where('etablissement_id', $etablissementId)
            ->where('caisse_id', $caisse->id)
            ->whereDate('date_cloture', $today)
            ->where('statut', 'cloturee')
            ->first();

        if ($clotureExistante) {
            throw new InvalidArgumentException("Impossible d'encaisser : cette caisse a déjà été clôturée pour aujourd'hui.");
        }

        return DB::transaction(function () use ($data, $caisse, $etablissementId, $caissier, $user) {
            $referenceRecu = $this->genererReferenceReglementDivers($etablissementId);

            $dateReglement = ! empty($data['date_paiement'])
                ? Carbon::parse($data['date_paiement'])->setTime(Carbon::now()->hour, Carbon::now()->minute, Carbon::now()->second)
                : (! empty($data['date_reglement']) ? Carbon::parse($data['date_reglement']) : Carbon::now());

            return ReglementDivers::create([
                'etablissement_id' => $etablissementId,
                'caisse_id' => $caisse->id,
                'reference_recu' => $referenceRecu,
                'date_reglement' => $dateReglement,
                'payeur_nom' => $data['payeur_nom'],
                'payeur_telephone' => $data['payeur_telephone'] ?? null,
                'categorie' => $data['categorie'] ?? 'autre',
                'libelle' => $data['libelle'],
                'montant' => $data['montant'],
                'mode_paiement' => $data['mode_paiement'] ?? 'especes',
                'reference_paiement' => $data['reference_paiement'] ?? null,
                'caissier_id' => $caissier?->id,
                'user_id' => $user?->id,
                'statut' => 'valide',
                'observations' => $data['observations'] ?? null,
            ]);
        });
    }

    /**
     * Annule administrativement un règlement divers.
     *
     * @param  string  $motif  Justificatif obligatoire
     * @param  User  $annulateur  Administrateur ayant validé l'annulation
     */
    public function annulerReglementDivers(ReglementDivers $reglement, string $motif, User $annulateur): ReglementDivers
    {
        if ($reglement->statut === 'annule') {
            throw new InvalidArgumentException('Ce règlement a déjà été annulé.');
        }

        $reglement->update([
            'statut' => 'annule',
            'motif_annulation' => $motif,
        ]);

        return $reglement;
    }

    /**
     * Attribue une bourse d'études ou une remise paramétrée au compte scolaire d'un élève (Aurore §4.1.6),
     * avec choix flexible de la tranche cible d'imputation.
     *
     * @param  CompteFinancierEleve  $compte  Compte financier élève
     * @param  BourseRemise  $bourseRemise  Modèle de bourse/remise à appliquer
     * @param  string|null  $motif  Justificatif éventuel
     * @param  User|null  $user  Utilisateur validateur
     * @param  string|null  $cibleImputation  Surcharge optionnelle ('derniere_tranche', 'tranche_1', 'tranche_2', 'tranche_3', 'proportionnelle')
     * @param  int|null  $fraisScolaireId  Tranche / rubrique précise ciblée pour cet élève (optionnel)
     */
    /**
     * Détermine l'assiette de calcul (en FCFA) d'une bourse ou remise pour un élève donné.
     *
     * Par défaut (`total_scolarite`), un pourcentage est calculé sur la scolarité totale nette
     * de dispenses (`debit_initial - total_dispense`), quelle que soit la tranche cible choisie
     * pour déduire la remise. Si le modèle est configuré en `tranche_ciblee`, l'assiette est
     * restreinte au montant net (après dispense) de la tranche ou ligne ciblée.
     *
     * @param  CompteFinancierEleve  $compte  Compte financier de l'élève
     * @param  BourseRemise  $bourseRemise  Modèle de bourse ou remise
     * @param  string|null  $cibleImputation  Cible d'imputation éventuelle (surcharge du modèle)
     * @param  int|null  $fraisScolaireId  Identifiant de la ligne tarifaire précisément ciblée
     * @param  Collection<int, FraisScolaire>|null  $fraisObligatoires  Tarifs obligatoires préchargés si disponibles
     * @param  Collection<int, DispenseFinanciere>|null  $dispenses  Dispenses préchargées si disponibles
     * @return float Assiette de calcul nette (>= 0)
     */
    private function resoudreBaseCalculRemise(
        CompteFinancierEleve $compte,
        BourseRemise $bourseRemise,
        ?string $cibleImputation = null,
        ?int $fraisScolaireId = null,
        ?Collection $fraisObligatoires = null,
        ?Collection $dispenses = null
    ): float {
        $baseApresDispense = max(0.0, (float) $compte->debit_initial - (float) ($compte->total_dispense ?? 0));
        $baseMode = $bourseRemise->base_calcul ?: 'total_scolarite';

        if ($baseMode !== 'tranche_ciblee') {
            return $baseApresDispense;
        }

        // En mode 'tranche_ciblee', on identifie la ligne tarifaire concernée (ligne explicite ou tranche du modèle)
        $fraisCible = null;
        if ($fraisScolaireId) {
            $fraisCible = FraisScolaire::find($fraisScolaireId);
        } else {
            $cibleEffective = $cibleImputation ?: ($bourseRemise->cible_imputation ?: 'derniere_tranche');
            if ($cibleEffective !== 'proportionnelle') {
                if ($fraisObligatoires === null) {
                    $compte->loadMissing('inscription.classe');
                    $classe = $compte->inscription?->classe;
                    $fraisObligatoires = $this->obtenirTarifsObligatoiresApplicables(
                        (int) $compte->annee_scolaire_id,
                        (int) $compte->etablissement_id,
                        $classe?->niveau_id,
                        $classe?->serie
                    );
                }

                $indices = $this->ordonnerIndicesCiblesRemise($fraisObligatoires, $cibleEffective, null);
                if (! empty($indices)) {
                    $fraisCible = $fraisObligatoires[$indices[0]] ?? null;
                }
            }
        }

        if (! $fraisCible) {
            return $baseApresDispense;
        }

        $dispenseSurFrais = $dispenses !== null
            ? (float) ($dispenses->firstWhere('frais_scolaire_id', $fraisCible->id)?->montant_dispense ?? 0)
            : (float) ($compte->dispensesFinancieres()
                ->where('frais_scolaire_id', $fraisCible->id)
                ->value('montant_dispense') ?? 0);

        return max(0.0, (float) $fraisCible->montant - $dispenseSurFrais);
    }

    /**
     * Calcule le montant déductible (en FCFA) d'une bourse ou remise en respectant son mode et sa base de calcul.
     *
     * @param  CompteFinancierEleve  $compte  Compte financier de l'élève
     * @param  BourseRemise  $bourseRemise  Modèle de bourse ou remise
     * @param  string|null  $cibleImputation  Cible d'imputation éventuelle (surcharge du modèle)
     * @param  int|null  $fraisScolaireId  Identifiant de la ligne tarifaire précisément ciblée
     * @param  Collection<int, FraisScolaire>|null  $fraisObligatoires  Tarifs obligatoires préchargés si disponibles
     * @param  Collection<int, DispenseFinanciere>|null  $dispenses  Dispenses préchargées si disponibles
     * @return float Montant calculé plafonné au solde net initial de l'élève
     */
    public function calculerMontantBourseRemise(
        CompteFinancierEleve $compte,
        BourseRemise $bourseRemise,
        ?string $cibleImputation = null,
        ?int $fraisScolaireId = null,
        ?Collection $fraisObligatoires = null,
        ?Collection $dispenses = null
    ): float {
        $baseApresDispense = max(0.0, (float) $compte->debit_initial - (float) ($compte->total_dispense ?? 0));

        if ($bourseRemise->mode_calcul === 'pourcentage') {
            $baseCalcul = $this->resoudreBaseCalculRemise(
                $compte,
                $bourseRemise,
                $cibleImputation,
                $fraisScolaireId,
                $fraisObligatoires,
                $dispenses
            );

            return min($baseApresDispense, round(($baseCalcul * (float) $bourseRemise->valeur) / 100));
        }

        return min($baseApresDispense, (float) $bourseRemise->valeur);
    }

    public function attribuerBourseRemise(
        CompteFinancierEleve $compte,
        BourseRemise $bourseRemise,
        ?string $motif = null,
        ?User $user = null,
        ?string $cibleImputation = null,
        ?int $fraisScolaireId = null
    ): EleveBourseRemise {
        return DB::transaction(function () use ($compte, $bourseRemise, $motif, $user, $cibleImputation, $fraisScolaireId) {
            $montant = $this->calculerMontantBourseRemise(
                $compte,
                $bourseRemise,
                $cibleImputation,
                $fraisScolaireId
            );

            // Enregistrement de l'attribution avec cible d'imputation éventuelle
            $attribution = EleveBourseRemise::updateOrCreate(
                [
                    'compte_financier_id' => $compte->id,
                    'bourse_remise_id' => $bourseRemise->id,
                ],
                [
                    'montant_calcule' => $montant,
                    'cible_imputation' => $cibleImputation ?: null,
                    'frais_scolaire_id' => $fraisScolaireId ?: null,
                    'date_attribution' => Carbon::today(),
                    'motif' => $motif,
                    'attribue_par' => $user?->id,
                ]
            );

            // Actualisation automatique du solde restant et du statut financier
            $compte->actualiserSolde();

            return $attribution->fresh();
        });
    }

    /**
     * Accorde ou met à jour une dispense financière totale (100 %) ou partielle sur une tranche obligatoire
     * d'un élève (ex: élève inscrit au 2ème ou 3ème trimestre, exonération APEE fratrie...).
     *
     * @param  CompteFinancierEleve  $compte  Compte financier de l'élève
     * @param  FraisScolaire  $frais  Ligne de tarif obligatoire à dispenser
     * @param  string  $typeDispense  'totale' ou 'partielle'
     * @param  float|null  $montantDispense  Montant exonéré (requis si 'partielle')
     * @param  string  $motif  Justification obligatoire
     * @param  User|null  $user  Utilisateur ayant validé la dispense
     *
     * @throws InvalidArgumentException Si la ligne n'est pas obligatoire ou si le montant est invalide
     */
    public function accorderDispenseFinanciere(
        CompteFinancierEleve $compte,
        FraisScolaire $frais,
        string $typeDispense,
        ?float $montantDispense,
        string $motif,
        ?User $user = null
    ): DispenseFinanciere {
        $frais->loadMissing('typeFrais');

        $estObligatoire = $frais->obligatoire !== null
            ? (bool) $frais->obligatoire
            : (bool) $frais->typeFrais?->obligatoire;

        if (! $estObligatoire) {
            throw new InvalidArgumentException('Seuls les frais obligatoires peuvent faire l’objet d’une dispense (les options se résilient directement).');
        }

        $montantBrut = (float) $frais->montant;
        if ($montantBrut <= 0) {
            throw new InvalidArgumentException('Le montant initial de cette tranche est nul.');
        }

        if ($typeDispense === 'totale') {
            $montantFinal = $montantBrut;
        } else {
            if ($montantDispense === null || $montantDispense <= 0) {
                throw new InvalidArgumentException('Veuillez indiquer un montant de dispense strictement positif.');
            }
            if ($montantDispense > $montantBrut) {
                throw new InvalidArgumentException('Le montant dispensé ne peut pas dépasser le montant brut de la tranche ('.number_format($montantBrut, 0, ',', ' ').' FCFA).');
            }
            $montantFinal = $montantDispense;
            if (abs($montantFinal - $montantBrut) < 0.01) {
                $typeDispense = 'totale';
                $montantFinal = $montantBrut;
            }
        }

        return DB::transaction(function () use ($compte, $frais, $typeDispense, $montantFinal, $motif, $user) {
            $dispense = DispenseFinanciere::updateOrCreate(
                [
                    'compte_financier_id' => $compte->id,
                    'frais_scolaire_id' => $frais->id,
                ],
                [
                    'type_dispense' => $typeDispense,
                    'montant_dispense' => $montantFinal,
                    'motif' => trim($motif),
                    'date_dispense' => Carbon::today(),
                    'accorde_par' => $user?->id,
                ]
            );

            $compte->actualiserSolde();

            return $dispense;
        });
    }

    /**
     * Retire une dispense financière précédemment accordée sur une tranche et réajuste le compte de l'élève.
     */
    public function retirerDispenseFinanciere(DispenseFinanciere $dispense): void
    {
        DB::transaction(function () use ($dispense) {
            $compte = $dispense->compteFinancier;
            $dispense->delete();
            if ($compte) {
                $compte->actualiserSolde();
            }
        });
    }

    /**
     * Retire une bourse ou une remise accordée à un compte élève.
     */
    public function retirerBourseRemise(EleveBourseRemise $attribution): void
    {
        DB::transaction(function () use ($attribution) {
            $compte = $attribution->compteFinancier;
            $attribution->delete();

            // Si aucune autre bourse n'est enregistrée, remise_bourse retombe à 0
            if ($compte->boursesRemises()->count() === 0) {
                $compte->remise_bourse = 0;
            }

            $compte->actualiserSolde();
        });
    }

    /**
     * Applique une pénalité financière de retard sur le compte d'un élève (Aurore §3.17).
     *
     * @param  CompteFinancierEleve  $compte  Compte financier élève
     * @param  PenaliteConfiguration|null  $config  Configuration éventuelle
     * @param  string  $libelle  Intitulé explicite
     * @param  float  $montant  Montant de la pénalité
     * @param  string|null  $motif  Justificatif
     * @param  User|null  $user  Utilisateur ayant appliqué la pénalité
     */
    public function appliquerPenalite(
        CompteFinancierEleve $compte,
        ?PenaliteConfiguration $config,
        string $libelle,
        float $montant,
        ?string $motif = null,
        ?User $user = null
    ): PenaliteEleve {
        return DB::transaction(function () use ($compte, $config, $libelle, $montant, $motif, $user) {
            $penalite = PenaliteEleve::create([
                'compte_financier_id' => $compte->id,
                'penalite_configuration_id' => $config?->id,
                'libelle' => $libelle,
                'montant' => $montant,
                'date_application' => Carbon::today(),
                'statut' => 'a_payer',
                'motif' => $motif,
                'applique_par' => $user?->id,
            ]);

            $compte->actualiserSolde();

            return $penalite;
        });
    }

    /**
     * Annule une pénalité appliquée sur le compte d'un élève.
     */
    public function annulerPenalite(PenaliteEleve $penalite): void
    {
        DB::transaction(function () use ($penalite) {
            $penalite->update(['statut' => 'annule']);
            $penalite->compteFinancier->actualiserSolde();
        });
    }

    /**
     * Détecte les retards selon les dates limites de la Grille Tarifaire (ou du modèle d'échéance si présent)
     * et applique les pénalités configurées.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  int  $anneeId  Identifiant de l'année scolaire
     * @param  User|null  $user  Utilisateur déclencheur
     * @return array{penalites_appliquees: int, total_montant: float}
     */
    public function appliquerPenalitesAutomatiquesEcheances(
        int $etablissementId,
        int $anneeId,
        ?User $user = null
    ): array {
        $configPenalite = PenaliteConfiguration::where('etablissement_id', $etablissementId)
            ->where('is_active', true)
            ->first();

        if (! $configPenalite) {
            return ['penalites_appliquees' => 0, 'total_montant' => 0.0];
        }

        $echeancier = ModeleEcheance::with('tranches')
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->where('is_default', true)
            ->first();

        $today = Carbon::today();
        $comptes = CompteFinancierEleve::with('inscription.classe')
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->where('solde_restant', '>', 0)
            ->get();

        $nbAppliquees = 0;
        $totalMontant = 0.0;

        // Mode 1 : Modèle d'échéance explicite (rétrocompatibilité)
        if ($echeancier) {
            foreach ($echeancier->tranches as $tranche) {
                $dateExigibilite = Carbon::parse($tranche->date_limite)->addDays($configPenalite->jours_grace);

                if ($today->greaterThan($dateExigibilite)) {
                    foreach ($comptes as $compte) {
                        $montantTheoriqueExigible = ((float) $compte->montant_net_exigible * (float) $tranche->pourcentage_exigible) / 100;

                        if ($compte->total_paye < $montantTheoriqueExigible) {
                            $libelle = "Pénalité de retard - {$tranche->libelle}";

                            $existe = $compte->penalites()
                                ->where('libelle', $libelle)
                                ->where('statut', '!=', 'annule')
                                ->exists();

                            if (! $existe) {
                                $montantPenalite = $configPenalite->mode_calcul === 'pourcentage'
                                    ? round((($montantTheoriqueExigible - $compte->total_paye) * $configPenalite->valeur) / 100)
                                    : $configPenalite->valeur;

                                $this->appliquerPenalite(
                                    $compte,
                                    $configPenalite,
                                    $libelle,
                                    $montantPenalite,
                                    'Échéance du '.$tranche->date_limite->format('d/m/Y').' non respectée',
                                    $user
                                );

                                $nbAppliquees++;
                                $totalMontant += $montantPenalite;
                            }
                        }
                    }
                }
            }

            return [
                'penalites_appliquees' => $nbAppliquees,
                'total_montant' => $totalMontant,
            ];
        }

        // Mode 2 (Standard simplifié) : Évaluation directe à partir des dates limites de la Grille Tarifaire
        // en respectant les dispenses par tranche, les cibles de remises et les priorités de paiement
        foreach ($comptes as $compte) {
            $calcul = $this->construireLignesEcheancier($compte, $today);

            foreach ($calcul['lignes'] as $ligne) {
                if ($ligne['type'] !== 'obligatoire' || empty($ligne['date_limite_iso']) || $ligne['montant'] <= 0) {
                    continue;
                }

                $dateExigibilite = Carbon::parse($ligne['date_limite_iso'])->addDays($configPenalite->jours_grace);
                if ($today->greaterThan($dateExigibilite) && $ligne['montant_restant'] > 0) {
                    $libelle = "Pénalité de retard - {$ligne['libelle']}";

                    $existe = $compte->penalites()
                        ->where('libelle', $libelle)
                        ->where('statut', '!=', 'annule')
                        ->exists();

                    if (! $existe) {
                        $manquant = (float) $ligne['montant_restant'];
                        $montantPenalite = $configPenalite->mode_calcul === 'pourcentage'
                            ? round(($manquant * $configPenalite->valeur) / 100)
                            : (float) $configPenalite->valeur;

                        $this->appliquerPenalite(
                            $compte,
                            $configPenalite,
                            $libelle,
                            $montantPenalite,
                            'Date limite du '.($ligne['date_limite'] ?? '').' dépassée',
                            $user
                        );

                        $nbAppliquees++;
                        $totalMontant += $montantPenalite;
                    }
                }
            }
        }

        return [
            'penalites_appliquees' => $nbAppliquees,
            'total_montant' => $totalMontant,
        ];
    }

    /**
     * Enregistre une souscription à un frais optionnel (bus, cantine, internat) pour un élève.
     * Recalcule instantanément l'ardoise financière de l'élève.
     *
     * @param  int  $compteId  Identifiant du compte financier élève
     * @param  int  $fraisScolaireId  Identifiant du frais optionnel dans la grille tarifaire
     * @param  float|null  $montant  Montant personnalisé éventuel (prend le montant du frais si null)
     * @param  string|null  $observations  Observations éventuelles
     *
     * @throws InvalidArgumentException Si le frais n'est pas optionnel ou si l'élève est déjà souscrit
     */
    public function souscrireFraisOptionnel(
        int $compteId,
        int $fraisScolaireId,
        ?float $montant = null,
        ?string $observations = null
    ): SouscriptionFraisOptionnel {
        $compte = CompteFinancierEleve::findOrFail($compteId);
        $frais = FraisScolaire::with('typeFrais')->findOrFail($fraisScolaireId);

        $estObligatoire = $frais->obligatoire !== null
            ? (bool) $frais->obligatoire
            : (bool) $frais->typeFrais?->obligatoire;

        if ($estObligatoire) {
            throw new InvalidArgumentException('Ce frais est déjà obligatoire dans la grille tarifaire de la classe.');
        }

        $existant = SouscriptionFraisOptionnel::where('compte_financier_id', $compteId)
            ->where('frais_scolaire_id', $fraisScolaireId)
            ->first();

        if ($existant && $existant->statut === 'actif') {
            throw new InvalidArgumentException("Cet élève bénéficie déjà d'une souscription active à ce service optionnel.");
        }

        $montantFinal = $montant !== null && $montant > 0 ? $montant : (float) $frais->montant;

        if ($existant) {
            $existant->update([
                'montant' => $montantFinal,
                'statut' => 'actif',
                'observations' => $observations,
                'date_souscription' => Carbon::today(),
            ]);
            $souscription = $existant;
        } else {
            $souscription = SouscriptionFraisOptionnel::create([
                'compte_financier_id' => $compteId,
                'frais_scolaire_id' => $fraisScolaireId,
                'montant' => $montantFinal,
                'date_souscription' => Carbon::today(),
                'statut' => 'actif',
                'observations' => $observations,
            ]);
        }

        $compte->actualiserSolde();

        return $souscription;
    }

    /**
     * Annule une souscription active à un frais optionnel et réajuste immédiatement le compte élève.
     *
     * Empêche la résiliation si un versement valide couvre déjà ce frais optionnel (ou ferait passer
     * le montant net exigible en dessous du total déjà payé par l'élève).
     *
     * @param  SouscriptionFraisOptionnel  $souscription  Souscription à résilier
     * @return bool Succès de l'opération
     *
     * @throws InvalidArgumentException Si la souscription est déjà couverte par un paiement valide
     */
    public function annulerSouscriptionOptionnelle(SouscriptionFraisOptionnel $souscription): bool
    {
        $compte = $souscription->compteFinancier;

        if ($compte) {
            $ventilationValideExiste = VentilationVersement::where('frais_scolaire_id', $souscription->frais_scolaire_id)
                ->whereHas('versement', function ($q) use ($compte) {
                    $q->where('compte_financier_id', $compte->id)
                        ->where('statut', 'valide');
                })
                ->exists();

            if ($ventilationValideExiste) {
                throw new InvalidArgumentException(
                    "Impossible de retirer ce frais optionnel car un versement valide y est déjà imputé. Veuillez d'abord annuler le versement correspondant."
                );
            }

            $nouveauNetExigible = max(0.0, (float) $compte->montant_net_exigible - (float) $souscription->montant);
            if ((float) $compte->total_paye > $nouveauNetExigible + 0.01) {
                throw new InvalidArgumentException(
                    "Impossible de retirer ce frais optionnel : l'élève a déjà réglé un montant supérieur au solde qui resterait exigible."
                );
            }
        }

        $souscription->update(['statut' => 'annule']);
        if ($compte) {
            $compte->actualiserSolde();
        }

        return true;
    }

    /**
     * Applique en lot un frais optionnel (ex: Inscription Première CDE ou Première TI)
     * à l'ensemble des élèves inscrits d'une classe donnée et recalcule immédiatement leur montant total à verser.
     *
     * @param  int  $classeId  Identifiant de la classe cible
     * @param  int  $fraisScolaireId  Identifiant du frais optionnel de la grille
     * @param  int  $anneeId  Identifiant de l'année scolaire
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @return int Nombre d'élèves mis à jour
     *
     * @throws InvalidArgumentException Si le frais n'est pas optionnel ou n'appartient pas au niveau de la classe
     */
    public function souscrireFraisOptionnelPourClasse(
        int $classeId,
        int $fraisScolaireId,
        int $anneeId,
        int $etablissementId
    ): int {
        $classe = Classe::where('etablissement_id', $etablissementId)->findOrFail($classeId);
        $frais = FraisScolaire::with('typeFrais')
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->findOrFail($fraisScolaireId);

        $estObligatoire = $frais->obligatoire !== null
            ? (bool) $frais->obligatoire
            : (bool) $frais->typeFrais?->obligatoire;

        if ($estObligatoire) {
            throw new InvalidArgumentException('Ce frais est déjà obligatoire pour tout le niveau.');
        }

        if ($frais->niveau_id && (int) $frais->niveau_id !== (int) $classe->niveau_id) {
            throw new InvalidArgumentException("Ce frais optionnel n'appartient pas au niveau de la classe sélectionnée.");
        }

        $comptes = CompteFinancierEleve::where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->whereHas('inscription', function ($q) use ($classeId) {
                $q->where('classe_id', $classeId)
                    ->whereNotIn('statut', ['annulee', 'radie']);
            })
            ->get();

        $nbAppliques = 0;

        DB::transaction(function () use ($comptes, $frais, &$nbAppliques) {
            foreach ($comptes as $compte) {
                $existant = SouscriptionFraisOptionnel::where('compte_financier_id', $compte->id)
                    ->where('frais_scolaire_id', $frais->id)
                    ->first();

                if ($existant && $existant->statut === 'actif') {
                    continue;
                }

                if ($existant) {
                    $existant->update([
                        'montant' => (float) $frais->montant,
                        'statut' => 'actif',
                        'date_souscription' => Carbon::today(),
                    ]);
                } else {
                    SouscriptionFraisOptionnel::create([
                        'compte_financier_id' => $compte->id,
                        'frais_scolaire_id' => $frais->id,
                        'montant' => (float) $frais->montant,
                        'date_souscription' => Carbon::today(),
                        'statut' => 'actif',
                    ]);
                }

                $compte->actualiserSolde();
                $nbAppliques++;
            }
        });

        return $nbAppliques;
    }

    /**
     * Détermine l'ordre exact des indices de tranches obligatoires sur lesquels imputer une remise ou bourse.
     *
     * @param  Collection<int, FraisScolaire>  $fraisObligatoires  Tranches obligatoires du niveau
     * @param  string  $cible  Cible choisie ('derniere_tranche', 'tranche_1', 'tranche_2', 'tranche_3', 'tranche_4')
     * @param  int|null  $fraisScolaireIdCible  Identifiant d'une ligne de tarif spécifiquement ciblée
     * @return array<int, int> Liste ordonnée d'indices dans $fraisObligatoires
     */
    private function ordonnerIndicesCiblesRemise(
        Collection $fraisObligatoires,
        string $cible,
        ?int $fraisScolaireIdCible = null
    ): array {
        $count = $fraisObligatoires->count();
        if ($count === 0) {
            return [];
        }

        $indices = [];

        // 1. Si une ligne précise est ciblée pour l'élève, elle passe en toute première position
        if ($fraisScolaireIdCible !== null) {
            foreach ($fraisObligatoires as $idx => $frais) {
                if ((int) $frais->id === $fraisScolaireIdCible) {
                    $indices[] = $idx;
                    break;
                }
            }
        }

        // 2. Selon la philosophie d'imputation ('derniere_tranche' ou 'tranche_N')
        if (str_starts_with($cible, 'tranche_')) {
            $numCible = max(1, (int) substr($cible, 8));
            $scolariteMemeTranche = [];
            $autresMemeTranche = [];
            $tranchesSuivantes = [];
            $tranchesPrecedentes = [];

            foreach ($fraisObligatoires as $idx => $frais) {
                $num = (int) $frais->tranche_numero;
                $codeType = mb_strtoupper(
                    ($frais->typeFrais?->code ?? '').' '.
                    ($frais->typeFrais?->libelle ?? '').' '.
                    ($frais->typeFrais?->categorieFrais?->nom ?? '')
                );
                $estScolarite = (str_contains($codeType, 'SCOL') || str_contains($codeType, 'PENS') || str_contains($codeType, 'TRANCHE'))
                    && ! str_contains($codeType, 'INSCR');

                if ($num === $numCible) {
                    if ($estScolarite) {
                        $scolariteMemeTranche[] = $idx;
                    } else {
                        $autresMemeTranche[] = $idx;
                    }
                } elseif ($num > $numCible) {
                    $tranchesSuivantes[] = $idx;
                } else {
                    $tranchesPrecedentes[] = $idx;
                }
            }

            // S'il n'y a pas de type explicitement nommé Scolarité, trier la même tranche par montant décroissant
            if (empty($scolariteMemeTranche) && count($autresMemeTranche) > 1) {
                usort($autresMemeTranche, fn (int $a, int $b) => (float) $fraisObligatoires[$b]->montant <=> (float) $fraisObligatoires[$a]->montant);
            }

            $tranchesPrecedentes = array_reverse($tranchesPrecedentes);

            $suite = array_merge(
                $scolariteMemeTranche,
                $autresMemeTranche,
                $tranchesSuivantes,
                $tranchesPrecedentes
            );
        } else {
            // Par défaut ('derniere_tranche') : on part de la dernière tranche obligatoire et on remonte
            $suite = range($count - 1, 0);
        }

        foreach ($suite as $idx) {
            if (! in_array($idx, $indices, true)) {
                $indices[] = $idx;
            }
        }

        return $indices;
    }

    /**
     * Répartit les bourses et remises attribuées à un élève sur ses tranches obligatoires (après dispenses),
     * en respectant pour chaque avantage sa cible d'imputation ('derniere_tranche', 'tranche_1', 'tranche_2',
     * 'tranche_3', 'proportionnelle' ou un frais_scolaire_id spécifique).
     *
     * @param  Collection<int, FraisScolaire>  $fraisObligatoires  Tranches obligatoires triées par priorité
     * @param  array<int, float>  $montantsApresDispense  Montant restant par index après dispense
     * @param  CompteFinancierEleve  $compte  Compte financier de l'élève
     * @return array<int, float> Montant de remise imputé sur chaque index de $fraisObligatoires
     */
    private function imputerRemisesSurFraisObligatoires(
        Collection $fraisObligatoires,
        array $montantsApresDispense,
        CompteFinancierEleve $compte
    ): array {
        $count = $fraisObligatoires->count();
        if ($count === 0) {
            return [];
        }

        $remisesParIndex = array_fill(0, $count, 0.0);
        $capacites = $montantsApresDispense;
        $sommeAttributionsImputees = 0.0;

        foreach ($compte->boursesRemises as $attribution) {
            $aDeduire = (float) $attribution->montant_calcule;
            if ($aDeduire <= 0) {
                continue;
            }

            $cible = $attribution->cible_imputation
                ?: ($attribution->bourseRemise?->cible_imputation ?: 'derniere_tranche');

            if ($cible === 'proportionnelle' && ! $attribution->frais_scolaire_id) {
                $totalCap = array_sum($capacites);
                if ($totalCap > 0) {
                    $montantEffectif = min($aDeduire, $totalCap);
                    $resteProRata = $montantEffectif;
                    $dernierIdxPositif = null;
                    for ($i = 0; $i < $count; $i++) {
                        if ($capacites[$i] > 0) {
                            $dernierIdxPositif = $i;
                        }
                    }
                    for ($i = 0; $i < $count; $i++) {
                        if ($capacites[$i] <= 0) {
                            continue;
                        }
                        $part = ($i === $dernierIdxPositif)
                            ? min($capacites[$i], $resteProRata)
                            : min($capacites[$i], round(($montantEffectif * $capacites[$i]) / $totalCap));
                        $remisesParIndex[$i] += $part;
                        $capacites[$i] = max(0.0, $capacites[$i] - $part);
                        $resteProRata = max(0.0, $resteProRata - $part);
                        $sommeAttributionsImputees += $part;
                    }
                }

                continue;
            }

            $indicesOrdre = $this->ordonnerIndicesCiblesRemise(
                $fraisObligatoires,
                $cible,
                $attribution->frais_scolaire_id ? (int) $attribution->frais_scolaire_id : null
            );

            $reste = $aDeduire;
            foreach ($indicesOrdre as $idx) {
                if ($reste <= 0) {
                    break;
                }
                $deduction = min($reste, $capacites[$idx]);
                if ($deduction > 0) {
                    $remisesParIndex[$idx] += $deduction;
                    $capacites[$idx] = max(0.0, $capacites[$idx] - $deduction);
                    $reste = max(0.0, $reste - $deduction);
                    $sommeAttributionsImputees += $deduction;
                }
            }
        }

        // Prise en charge des éventuelles remises saisies manuellement sur `compte->remise_bourse`
        $remiseManuelleRestante = max(0.0, (float) $compte->remise_bourse - $sommeAttributionsImputees);
        if ($remiseManuelleRestante > 0) {
            for ($i = $count - 1; $i >= 0; $i--) {
                if ($remiseManuelleRestante <= 0) {
                    break;
                }
                $deduction = min($remiseManuelleRestante, $capacites[$i]);
                $remisesParIndex[$i] += $deduction;
                $capacites[$i] = max(0.0, $capacites[$i] - $deduction);
                $remiseManuelleRestante = max(0.0, $remiseManuelleRestante - $deduction);
            }
        }

        return $remisesParIndex;
    }

    /**
     * Construit les lignes détaillées d'échéancier d'un élève à une date de référence donnée :
     * 1. Applique les dispenses par tranche (totales ou partielles).
     * 2. Applique les bourses et remises selon leur tranche cible configurée.
     * 3. Répartit les paiements en 2 passes :
     *    - Passe 1 : ventilations ciblant explicitement un `frais_scolaire_id` (ex: Bus payé au guichet).
     *    - Passe 2 : cascade automatique du reliquat selon l'ordre de priorité de l'établissement.
     *
     * @param  CompteFinancierEleve  $compte  Compte financier de l'élève
     * @param  Carbon|null  $dateRef  Date d'évaluation des échéances (aujourd'hui par défaut)
     * @return array{
     *     lignes: array<int, array<string, mixed>>,
     *     frais_obligatoires: Collection<int, FraisScolaire>,
     *     souscriptions_actives: array<int, array<string, mixed>>
     * }
     */
    private function construireLignesEcheancier(
        CompteFinancierEleve $compte,
        ?Carbon $dateRef = null,
        ?\Illuminate\Support\Collection $fraisObligatoiresPrecharges = null
    ): array {
        $compte->loadMissing([
            'inscription.classe.niveau',
            'boursesRemises.bourseRemise',
            'dispensesFinancieres.fraisScolaire',
            'souscriptionsOptionnelles.fraisScolaire.typeFrais.categorieFrais',
            'penalites',
            'versements.ventilations',
        ]);

        $reference = ($dateRef ?? Carbon::today())->copy()->endOfDay();
        $classe = $compte->inscription?->classe;
        $niveauId = $classe?->niveau_id;
        $serie = $classe?->serie;

        $fraisObligatoires = $fraisObligatoiresPrecharges !== null
            ? $fraisObligatoiresPrecharges->values()
            : $this->obtenirTarifsObligatoiresApplicables(
                (int) $compte->annee_scolaire_id,
                (int) $compte->etablissement_id,
                $niveauId,
                $serie
            )->values();

        // Map des dispenses par frais_scolaire_id
        $dispensesParFraisId = $compte->dispensesFinancieres->keyBy('frais_scolaire_id');

        // Montants après dispense pour chaque tranche obligatoire
        $montantsApresDispense = [];
        foreach ($fraisObligatoires as $idx => $frais) {
            $brut = (float) $frais->montant;
            $dispense = $dispensesParFraisId->get($frais->id);
            $montantDispense = $dispense ? min($brut, (float) $dispense->montant_dispense) : 0.0;
            $montantsApresDispense[$idx] = max(0.0, $brut - $montantDispense);
        }

        // Imputation flexible des bourses et remises selon leur cible respective
        $remisesParIndex = $this->imputerRemisesSurFraisObligatoires(
            $fraisObligatoires,
            $montantsApresDispense,
            $compte
        );

        $lignes = [];

        // 0. Arriérés antérieurs éventuels (toujours en tête de priorité)
        $arrieres = (float) $compte->arrieres_anterieurs;
        if ($arrieres > 0) {
            $lignes[] = [
                'type' => 'arrieres',
                'id' => null,
                'type_frais_id' => null,
                'ordre_priorite' => 0,
                'tranche_numero' => 0,
                'libelle' => 'Arriérés des années antérieures',
                'categorie' => 'Arriérés N-1',
                'montant_brut' => $arrieres,
                'dispense_appliquee' => 0.0,
                'dispense_id' => null,
                'dispense_motif' => null,
                'remise_appliquee' => 0.0,
                'montant' => $arrieres,
                'date_limite' => null,
                'date_limite_iso' => null,
                'est_echu' => true,
                'montant_paye' => 0.0,
                'montant_restant' => $arrieres,
                'statut' => 'en_retard',
            ];
        }

        // 1. Tranches obligatoires dans l'ordre exact de priorité de l'établissement
        foreach ($fraisObligatoires as $index => $frais) {
            $estEchu = $frais->date_limite
                ? Carbon::parse($frais->date_limite)->startOfDay()->lte($reference)
                : false;
            $montantBrut = (float) $frais->montant;
            /** @var DispenseFinanciere|null $dispense */
            $dispense = $dispensesParFraisId->get($frais->id);
            $dispenseAppliquee = $dispense ? min($montantBrut, (float) $dispense->montant_dispense) : 0.0;
            $remiseAppliquee = $remisesParIndex[$index] ?? 0.0;
            $montantLigne = max(0.0, $montantBrut - $dispenseAppliquee - $remiseAppliquee);
            $prioriteEffective = (int) ($frais->ordre_priorite ?? ($frais->typeFrais?->ordre_priorite ?? 10));

            $lignes[] = [
                'type' => 'obligatoire',
                'id' => $frais->id,
                'type_frais_id' => $frais->type_frais_id,
                'categorie_frais_id' => $frais->typeFrais?->categorie_frais_id,
                'ordre_priorite' => $prioriteEffective,
                'tranche_numero' => (int) $frais->tranche_numero,
                'libelle' => $frais->libelle_tranche ?: ($frais->typeFrais?->libelle ?: ('Tranche '.$frais->tranche_numero)),
                'categorie' => $frais->typeFrais?->categorieFrais?->nom ?? ($frais->typeFrais?->libelle ?? 'Scolarité'),
                'montant_brut' => $montantBrut,
                'dispense_appliquee' => $dispenseAppliquee,
                'dispense_id' => $dispense?->id,
                'dispense_motif' => $dispense?->motif,
                'remise_appliquee' => $remiseAppliquee,
                'montant' => $montantLigne,
                'date_limite' => $frais->date_limite ? Carbon::parse($frais->date_limite)->format('d-m-Y') : null,
                'date_limite_iso' => $frais->date_limite ? Carbon::parse($frais->date_limite)->toDateString() : null,
                'est_echu' => $montantLigne > 0 ? $estEchu : false,
                'montant_paye' => 0.0,
                'montant_restant' => $montantLigne,
                'statut' => ($montantLigne <= 0 && $dispenseAppliquee > 0) ? 'dispense' : 'a_choir',
            ];
        }

        // 2. Frais optionnels souscrits actifs (Bus, Cantine, etc.)
        $souscriptionsActives = [];
        foreach ($compte->souscriptionsOptionnelles as $souscription) {
            if ($souscription->statut !== 'actif') {
                continue;
            }
            $fraisOpt = $souscription->fraisScolaire;
            $estEchu = $fraisOpt?->date_limite
                ? Carbon::parse($fraisOpt->date_limite)->startOfDay()->lte($reference)
                : false;
            $montantLigne = (float) $souscription->montant;

            $libelleTypeOpt = $fraisOpt?->typeFrais?->libelle ?? 'Option';
            $precisionTrancheOpt = ($fraisOpt?->libelle_tranche && mb_strtolower(trim($fraisOpt->libelle_tranche)) !== mb_strtolower(trim($libelleTypeOpt)))
                ? ' - '.$fraisOpt->libelle_tranche
                : '';
            $intituleOption = $libelleTypeOpt
                .$precisionTrancheOpt
                .($souscription->observations ? " ({$souscription->observations})" : '');

            $lignes[] = [
                'type' => 'optionnel',
                'id' => $fraisOpt?->id,
                'type_frais_id' => $fraisOpt?->type_frais_id,
                'categorie_frais_id' => $fraisOpt?->typeFrais?->categorie_frais_id,
                'souscription_id' => $souscription->id,
                'ordre_priorite' => 90,
                'tranche_numero' => (int) ($fraisOpt?->tranche_numero ?? 1),
                'libelle' => $intituleOption,
                'categorie' => $fraisOpt?->typeFrais?->categorieFrais?->nom ?? 'Option / Annexe',
                'montant_brut' => $montantLigne,
                'dispense_appliquee' => 0.0,
                'dispense_id' => null,
                'dispense_motif' => null,
                'remise_appliquee' => 0.0,
                'montant' => $montantLigne,
                'date_limite' => $fraisOpt?->date_limite ? Carbon::parse($fraisOpt->date_limite)->format('d-m-Y') : null,
                'date_limite_iso' => $fraisOpt?->date_limite ? Carbon::parse($fraisOpt->date_limite)->toDateString() : null,
                'est_echu' => $estEchu,
                'montant_paye' => 0.0,
                'montant_restant' => $montantLigne,
                'statut' => 'a_choir',
            ];

            $souscriptionsActives[] = [
                'id' => $souscription->id,
                'frais_scolaire_id' => $souscription->frais_scolaire_id,
                'libelle' => $intituleOption,
                'montant' => $montantLigne,
                'date_souscription' => $souscription->date_souscription ? Carbon::parse($souscription->date_souscription)->format('d-m-Y') : null,
            ];
        }

        // 3. Pénalités actives éventuelles
        foreach ($compte->penalites as $penalite) {
            if ($penalite->statut !== 'a_payer') {
                continue;
            }
            $montantLigne = (float) $penalite->montant;
            $lignes[] = [
                'type' => 'penalite',
                'id' => null,
                'type_frais_id' => null,
                'penalite_id' => $penalite->id,
                'ordre_priorite' => 99,
                'tranche_numero' => 99,
                'libelle' => $penalite->libelle,
                'categorie' => 'Pénalité',
                'montant_brut' => $montantLigne,
                'dispense_appliquee' => 0.0,
                'dispense_id' => null,
                'dispense_motif' => null,
                'remise_appliquee' => 0.0,
                'montant' => $montantLigne,
                'date_limite' => $penalite->date_application ? Carbon::parse($penalite->date_application)->format('d-m-Y') : null,
                'date_limite_iso' => $penalite->date_application ? Carbon::parse($penalite->date_application)->toDateString() : null,
                'est_echu' => true,
                'montant_paye' => 0.0,
                'montant_restant' => $montantLigne,
                'statut' => 'en_retard',
            ];
        }

        // 4. Allocation des paiements en 2 passes :
        //    - Passe 1 : ventilations ciblant explicitement un `frais_scolaire_id` (ex: parent payant le Bus ou une tranche précise)
        //    - Passe 2 : cascade automatique du reliquat selon l'ordre de priorité de l'établissement
        $versementsValides = $compte->versements->where('statut', 'valide');
        $sommeVersementsDB = 0.0;
        $reliquatLibre = 0.0;

        foreach ($versementsValides as $versement) {
            $montantVers = (float) $versement->montant_total;
            $sommeVersementsDB += $montantVers;

            if ($versement->ventilations->isEmpty()) {
                $reliquatLibre += $montantVers;

                continue;
            }

            $sommeVentilee = 0.0;
            foreach ($versement->ventilations as $vent) {
                $montantVent = (float) $vent->montant;
                $sommeVentilee += $montantVent;

                if ($vent->frais_scolaire_id) {
                    $cibleTrouvee = false;
                    foreach ($lignes as $idx => $ligne) {
                        if (! empty($ligne['id']) && (int) $ligne['id'] === (int) $vent->frais_scolaire_id && $lignes[$idx]['montant_restant'] > 0) {
                            $affecte = min($montantVent, $lignes[$idx]['montant_restant']);
                            $lignes[$idx]['montant_paye'] += $affecte;
                            $lignes[$idx]['montant_restant'] = max(0.0, $lignes[$idx]['montant_restant'] - $affecte);
                            $reliquatLibre += max(0.0, $montantVent - $affecte);
                            $cibleTrouvee = true;
                            break;
                        }
                    }
                    if (! $cibleTrouvee) {
                        $reliquatLibre += $montantVent;
                    }
                } else {
                    $reliquatLibre += $montantVent;
                }
            }

            if ($montantVers > $sommeVentilee + 0.01) {
                $reliquatLibre += ($montantVers - $sommeVentilee);
            }
        }

        // Compatibilité si `total_paye` a été renseigné directement sans enregistrement `VersementCaisse`
        if ((float) $compte->total_paye > $sommeVersementsDB + 0.01) {
            $reliquatLibre += ((float) $compte->total_paye - $sommeVersementsDB);
        }

        // Passe 2 : Cascade automatique dans l'ordre exact de priorité des lignes
        foreach ($lignes as $idx => $ligne) {
            if ($reliquatLibre <= 0) {
                break;
            }
            if ($lignes[$idx]['montant_restant'] <= 0) {
                continue;
            }
            $affecte = min($reliquatLibre, $lignes[$idx]['montant_restant']);
            $lignes[$idx]['montant_paye'] += $affecte;
            $lignes[$idx]['montant_restant'] = max(0.0, $lignes[$idx]['montant_restant'] - $affecte);
            $reliquatLibre = max(0.0, $reliquatLibre - $affecte);
        }

        // Finalisation des statuts par ligne
        foreach ($lignes as $idx => $ligne) {
            if ($ligne['montant'] <= 0 && $ligne['dispense_appliquee'] > 0) {
                $lignes[$idx]['statut'] = 'dispense';
            } elseif ($ligne['montant_restant'] <= 0.009) {
                $lignes[$idx]['montant_restant'] = 0.0;
                $lignes[$idx]['statut'] = 'solde';
            } elseif ($ligne['est_echu']) {
                $lignes[$idx]['statut'] = 'en_retard';
            } else {
                $lignes[$idx]['statut'] = 'a_choir';
            }
        }

        return [
            'lignes' => $lignes,
            'frais_obligatoires' => $fraisObligatoires,
            'souscriptions_actives' => $souscriptionsActives,
        ];
    }

    /**
     * Détermine le statut financier réel d'un compte élève en croisant les paiements,
     * les dates limites d'échéance échues et l'avancement séquentiel des tranches obligatoires.
     *
     * Règles appliquées :
     * - `'solde'` : solde restant <= 0 avec débit ou montant net exigible > 0.
     * - `'insolvable'` : au moins une échéance passée (`date_limite <= dateReference`) reste impayée,
     *   même si l'élève a versé un acompte partiel ou soldé l'inscription avant d'être en retard sur la T1.
     * - `'en_attente'` : aucun paiement encore effectué, mais aucune date limite dépassée.
     * - `'acompte'` : versement partiel sur la première rubrique due, sans retard d'échéance.
     * - `'inscrit_a_jour'` : inscription soldée et aucune échéance en retard, T1 pas encore soldée.
     * - `'en_regle_tranche_1'` : 1ère tranche de scolarité soldée et aucune échéance en retard.
     * - `'en_regle_tranche_2'` : 2ème tranche (ou >= 60% sur grille mono-ligne) soldée et aucune échéance en retard.
     *
     * @param  CompteFinancierEleve  $compte  Compte financier à évaluer
     * @param  Carbon|null  $dateReference  Date d'évaluation (aujourd'hui par défaut)
     * @param  \Illuminate\Support\Collection|null  $fraisObligatoiresPrecharges  Tarifs déjà chargés en lot (mode sync)
     * @return string Statut normalisé stocké dans `statut_financier`
     */
    public function determinerStatutFinancierCompte(
        CompteFinancierEleve $compte,
        ?Carbon $dateReference = null,
        ?\Illuminate\Support\Collection $fraisObligatoiresPrecharges = null
    ): string {
        $soldeRestant = (float) $compte->solde_restant;
        $netExigible = (float) $compte->montant_net_exigible;
        $debitInitial = (float) $compte->debit_initial;
        $totalPaye = (float) $compte->total_paye;

        if ($soldeRestant <= 0.009 && ($netExigible > 0 || $debitInitial > 0)) {
            return 'solde';
        }

        // En appel unitaire (ex: après versement/annulation), on force le rechargement des relations de paiement.
        if ($fraisObligatoiresPrecharges === null) {
            $compte->load([
                'inscription.classe.niveau',
                'boursesRemises.bourseRemise',
                'dispensesFinancieres.fraisScolaire',
                'souscriptionsOptionnelles.fraisScolaire.typeFrais.categorieFrais',
                'penalites',
                'versements.ventilations',
            ]);
        } else {
            $compte->loadMissing('inscription.classe.niveau');
        }

        // Repli si l'élève n'a pas encore de classe/niveau associé ou aucune ligne tarifaire.
        if (! $compte->inscription?->classe?->niveau_id) {
            if ($totalPaye <= 0.009) {
                return 'insolvable';
            }

            $taux = $netExigible > 0 ? ($totalPaye / $netExigible) : 1.0;

            return $taux >= 0.6 ? 'en_regle_tranche_2' : 'en_regle_tranche_1';
        }

        $calcul = $this->construireLignesEcheancier(
            $compte,
            $dateReference ?? Carbon::today(),
            $fraisObligatoiresPrecharges
        );
        $lignes = $calcul['lignes'] ?? [];

        if (empty($lignes)) {
            if ($totalPaye <= 0.009) {
                return 'insolvable';
            }

            $taux = $netExigible > 0 ? ($totalPaye / $netExigible) : 1.0;

            return $taux >= 0.6 ? 'en_regle_tranche_2' : 'en_regle_tranche_1';
        }

        // 1. Toute dette échue non couverte (même après versement d'un acompte partiel) classe le compte « insolvable ».
        foreach ($lignes as $ligne) {
            if (! empty($ligne['est_echu']) && (float) ($ligne['montant_restant'] ?? 0) > 0.009) {
                return 'insolvable';
            }
        }

        // 2. Aucun versement mais aucune date limite encore dépassée : en attente avant échéance.
        if ($totalPaye <= 0.009) {
            return 'en_attente';
        }

        // 3. Analyse séquentielle des frais obligatoires (hors arriérés déjà vérifiés ci-dessus)
        $lignesObligatoires = array_values(array_filter(
            $lignes,
            fn (array $ligne): bool => ($ligne['type'] ?? '') === 'obligatoire'
        ));

        if (empty($lignesObligatoires)) {
            $taux = $netExigible > 0 ? ($totalPaye / $netExigible) : 0.0;

            return $taux >= 0.6 ? 'en_regle_tranche_2' : 'acompte';
        }

        $estLigneSoldee = static fn (array $ligne): bool => in_array($ligne['statut'] ?? '', ['solde', 'dispense'], true)
            || (float) ($ligne['montant_restant'] ?? 0) <= 0.009;

        $estLigneInscription = static fn (array $ligne): bool => str_contains(
            mb_strtolower((string) ($ligne['libelle'] ?? '')),
            'inscription'
        );

        $ligneInscription = null;
        $lignesScolarite = [];
        foreach ($lignesObligatoires as $ligne) {
            if ($ligneInscription === null && $estLigneInscription($ligne)) {
                $ligneInscription = $ligne;
            } else {
                $lignesScolarite[] = $ligne;
            }
        }

        // Si une rubrique Inscription dédiée existe, elle constitue le premier palier obligatoire.
        if ($ligneInscription !== null) {
            if (! $estLigneSoldee($ligneInscription)) {
                return 'acompte';
            }

            $tranchesSoldees = 0;
            foreach ($lignesScolarite as $ligneScol) {
                if (! $estLigneSoldee($ligneScol)) {
                    break;
                }
                $tranchesSoldees++;
            }

            if ($tranchesSoldees === 0) {
                return 'inscrit_a_jour';
            }

            return $tranchesSoldees >= 2 ? 'en_regle_tranche_2' : 'en_regle_tranche_1';
        }

        // Aucune rubrique nommée Inscription : évaluation directe des tranches obligatoires
        if (count($lignesScolarite) === 1) {
            $taux = $netExigible > 0 ? ($totalPaye / $netExigible) : 0.0;

            return $taux >= 0.6 ? 'en_regle_tranche_2' : 'acompte';
        }

        $tranchesSoldees = 0;
        foreach ($lignesScolarite as $ligneScol) {
            if (! $estLigneSoldee($ligneScol)) {
                break;
            }
            $tranchesSoldees++;
        }

        if ($tranchesSoldees === 0) {
            return 'acompte';
        }

        return $tranchesSoldees >= 2 ? 'en_regle_tranche_2' : 'en_regle_tranche_1';
    }

    /**
     * Calcule avec exactitude la dette échue en retard d'un élève à une date donnée,
     * en tenant compte des dispenses de tranches, des cibles de bourses/remises et des paiements ciblés ou prioritaires.
     *
     * @param  CompteFinancierEleve  $compte  Compte financier de l'élève
     * @param  Carbon|null  $dateReference  Date d'évaluation (aujourd'hui par défaut)
     * @return float Montant en FCFA des échéances dépassées non encore couvertes
     */
    public function calculerMontantEchuEnRetard(CompteFinancierEleve $compte, ?Carbon $dateReference = null): float
    {
        if (! $compte->inscription?->classe?->niveau_id) {
            return 0.0;
        }

        $calcul = $this->construireLignesEcheancier($compte, $dateReference ?? Carbon::today());

        $totalRetard = 0.0;
        foreach ($calcul['lignes'] as $ligne) {
            if ($ligne['est_echu'] && $ligne['montant_restant'] > 0) {
                $totalRetard += (float) $ligne['montant_restant'];
            }
        }

        return round($totalRetard, 2);
    }

    /**
     * Construit le dossier financier et l'échéancier complet d'un élève
     * (arriérés + frais obligatoires par ordre de priorité + dispenses + bourses flexibles + options + pénalités + reçus).
     *
     * @param  CompteFinancierEleve  $compte  Compte financier de l'élève
     * @return array<string, mixed>
     */
    public function obtenirEcheancierEleve(CompteFinancierEleve $compte): array
    {
        $compte->loadMissing([
            'anneeScolaire',
            'inscription.classe.niveau',
            'eleve',
            'boursesRemises.bourseRemise',
            'boursesRemises.fraisScolaire.typeFrais',
            'dispensesFinancieres.fraisScolaire.typeFrais',
            'souscriptionsOptionnelles.fraisScolaire.typeFrais',
            'penalites',
            'versements.ventilations',
        ]);

        $classe = $compte->inscription?->classe;
        $calcul = $this->construireLignesEcheancier($compte, Carbon::today());
        $lignes = $calcul['lignes'];
        $fraisObligatoires = $calcul['frais_obligatoires'];
        $souscriptionsActives = $calcul['souscriptions_actives'];

        // 4. Bourses & Remises attribuées à cet élève (avec indication de la tranche cible)
        $boursesAttribuees = $compte->boursesRemises->map(function (EleveBourseRemise $ebr) {
            $cibleEffective = $ebr->cible_imputation ?: ($ebr->bourseRemise?->cible_imputation ?: 'derniere_tranche');
            $libelleFraisCible = null;
            if ($ebr->fraisScolaire) {
                $libelleFraisCible = $ebr->fraisScolaire->libelle_tranche
                    ?: ($ebr->fraisScolaire->typeFrais?->libelle.' - Tranche '.$ebr->fraisScolaire->tranche_numero);
            }

            return [
                'id' => $ebr->id,
                'bourse_remise_id' => $ebr->bourse_remise_id,
                'nom' => $ebr->bourseRemise?->nom ?? 'Remise individuelle',
                'categorie' => $ebr->bourseRemise?->categorie ?? 'autre',
                'type' => $ebr->bourseRemise?->type ?? 'remise',
                'mode_calcul' => $ebr->bourseRemise?->mode_calcul ?? 'montant_fixe',
                'valeur' => $ebr->bourseRemise ? (float) $ebr->bourseRemise->valeur : (float) $ebr->montant_calcule,
                'base_calcul' => $ebr->bourseRemise?->base_calcul ?: 'total_scolarite',
                'montant_calcule' => (float) $ebr->montant_calcule,
                'cible_imputation' => $cibleEffective,
                'frais_scolaire_id' => $ebr->frais_scolaire_id,
                'frais_cible_libelle' => $libelleFraisCible,
                'motif' => $ebr->motif,
                'date_attribution' => $ebr->date_attribution ? Carbon::parse($ebr->date_attribution)->format('d-m-Y') : null,
            ];
        })->values()->all();

        // 4b. Dispenses financières accordées à cet élève (ex: arrivée au 2ème trimestre)
        $dispensesAttribuees = $compte->dispensesFinancieres->map(function (DispenseFinanciere $disp) {
            $frais = $disp->fraisScolaire;
            $libelleTranche = $frais
                ? ($frais->libelle_tranche ?: ($frais->typeFrais?->libelle.' - Tranche '.$frais->tranche_numero))
                : 'Tranche scolaire';

            return [
                'id' => $disp->id,
                'frais_scolaire_id' => $disp->frais_scolaire_id,
                'libelle_tranche' => $libelleTranche,
                'type_dispense' => $disp->type_dispense,
                'montant_brut_tranche' => (float) ($frais?->montant ?? $disp->montant_dispense),
                'montant_dispense' => (float) $disp->montant_dispense,
                'motif' => $disp->motif,
                'date_dispense' => $disp->date_dispense ? Carbon::parse($disp->date_dispense)->format('d-m-Y') : null,
            ];
        })->values()->all();

        // 5. Historique des versements de l'élève (du plus récent au plus ancien)
        $versements = $compte->versements
            ->sortByDesc('date_versement')
            ->values()
            ->map(fn (VersementCaisse $v) => [
                'id' => $v->id,
                'reference_recu' => $v->reference_recu,
                'date_versement' => $v->date_versement ? Carbon::parse($v->date_versement)->toISOString() : null,
                'montant_total' => (float) $v->montant_total,
                'mode_paiement' => $v->mode_paiement,
                'reference_paiement' => $v->reference_paiement,
                'payeur_nom' => $v->payeur_nom,
                'payeur_telephone' => $v->payeur_telephone,
                'observations' => $v->observations,
                'recu_par' => $v->recu_par,
                'statut' => $v->statut,
                'motif_annulation' => $v->motif_annulation,
                'ventilations' => $v->ventilations->map(fn (VentilationVersement $vent) => [
                    'id' => $vent->id,
                    'type_frais_id' => $vent->type_frais_id,
                    'frais_scolaire_id' => $vent->frais_scolaire_id,
                    'libelle' => $vent->libelle,
                    'montant' => (float) $vent->montant,
                ])->values()->all(),
            ])->all();

        // 5b. Frais optionnels configurés pour le niveau de l'élève (avec état de souscription pour cet élève)
        $tarifsOptionnelsNiveau = $this->obtenirTarifsOptionnelsApplicables(
            (int) $compte->annee_scolaire_id,
            (int) $compte->etablissement_id,
            $classe?->niveau_id,
            $classe?->serie
        );

        $souscriptionsActivesParFraisId = $compte->souscriptionsOptionnelles
            ->where('statut', 'actif')
            ->keyBy('frais_scolaire_id');

        $fraisOptionnelsNiveau = $tarifsOptionnelsNiveau->map(function (FraisScolaire $fraisOpt) use ($souscriptionsActivesParFraisId, $lignes) {
            /** @var SouscriptionFraisOptionnel|null $subActive */
            $subActive = $souscriptionsActivesParFraisId->get($fraisOpt->id);
            $ligneOption = collect($lignes)->first(fn (array $l) => $l['type'] === 'optionnel' && (int) ($l['id'] ?? 0) === (int) $fraisOpt->id);

            return [
                'id' => $fraisOpt->id,
                'frais_scolaire_id' => $fraisOpt->id,
                'type_frais_id' => $fraisOpt->type_frais_id,
                'libelle' => $fraisOpt->libelle_tranche ?: ($fraisOpt->typeFrais?->libelle ?? 'Frais optionnel'),
                'categorie' => $fraisOpt->typeFrais?->categorieFrais?->nom ?? 'Option / Annexe',
                'montant' => (float) $fraisOpt->montant,
                'montant_standard' => (float) $fraisOpt->montant,
                'date_limite' => $fraisOpt->date_limite ? Carbon::parse($fraisOpt->date_limite)->format('d-m-Y') : null,
                'serie' => $fraisOpt->serie,
                'est_souscrit' => $subActive !== null,
                'souscription_id' => $subActive?->id,
                'montant_souscrit' => $subActive ? (float) $subActive->montant : (float) $fraisOpt->montant,
                'montant_paye' => (float) ($ligneOption['montant_paye'] ?? 0),
            ];
        })->values()->all();

        // Métadonnées pour l'encaissement rétroactif et l'éligibilité à la remise anticipée avant rentrée
        $dateMinVersement = $this->obtenirDateMinimaleVersement($compte)->toDateString();
        $dateLimiteRentree = $this->obtenirDateLimiteRentree($compte, $fraisObligatoires);
        $modeleAnticipe = $this->trouverModeleRemiseAnticipee((int) $compte->etablissement_id);
        $remiseAnticipeePayload = null;
        if ($modeleAnticipe) {
            $montantEstimeAnticipe = $this->calculerMontantBourseRemise(
                $compte,
                $modeleAnticipe,
                null,
                null,
                $fraisObligatoires
            );
            $dejaAttribuee = $compte->boursesRemises->contains('bourse_remise_id', $modeleAnticipe->id);

            $remiseAnticipeePayload = [
                'id' => $modeleAnticipe->id,
                'nom' => $modeleAnticipe->nom,
                'valeur' => (float) $modeleAnticipe->valeur,
                'mode_calcul' => $modeleAnticipe->mode_calcul,
                'base_calcul' => $modeleAnticipe->base_calcul ?: 'total_scolarite',
                'cible_imputation' => $modeleAnticipe->cible_imputation ?: 'derniere_tranche',
                'montant_estime' => $montantEstimeAnticipe,
                'deja_attribuee' => $dejaAttribuee,
            ];
        }

        return [
            'compte' => $compte,
            'eleve' => $compte->eleve,
            'classe' => $classe,
            'niveau_nom' => $classe?->niveau?->nom,
            'nb_tarifs_niveau' => $fraisObligatoires->count(),
            'lignes' => $lignes,
            'bourses_attribuees' => $boursesAttribuees,
            'dispenses_attribuees' => $dispensesAttribuees,
            'souscriptions_actives' => $souscriptionsActives,
            'frais_optionnels_niveau' => $fraisOptionnelsNiveau,
            'versements' => $versements,
            'total_exigible' => (float) $compte->montant_net_exigible,
            'total_dispense' => (float) ($compte->total_dispense ?? 0),
            'total_paye' => (float) $compte->total_paye,
            'solde_restant' => (float) $compte->solde_restant,
            'remise_bourse' => (float) $compte->remise_bourse,
            'arrieres_anterieurs' => (float) $compte->arrieres_anterieurs,
            'montant_echu_en_retard' => $this->calculerMontantEchuEnRetard($compte, Carbon::today()),
            'statut_financier' => $compte->statut_financier,
            'date_min_versement' => $dateMinVersement,
            'date_limite_rentree' => $dateLimiteRentree,
            'remise_anticipee_modele' => $remiseAnticipeePayload,
        ];
    }

    /**
     * Construit le contexte de périmètre hiérarchique (Section, Cycle, Niveau, Classe) sécurisé
     * sur l'établissement cible et génère un libellé lisible pour les en-têtes PDF.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement actif
     * @param  int|null  $sectionId  Filtre optionnel par section linguistique
     * @param  int|null  $cycleId  Filtre optionnel par cycle d'enseignement
     * @param  int|null  $niveauId  Filtre optionnel par niveau d'études
     * @param  int|null  $classeId  Filtre optionnel par classe précise
     * @return array{
     *     section: ?Section,
     *     cycle: ?Cycle,
     *     niveau: ?Niveau,
     *     classe: ?Classe,
     *     perimetre_label: string,
     *     a_filtre_pedagogique: bool
     * }
     */
    private function construireContextePerimetre(
        ?int $etablissementId = null,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): array {
        $section = $sectionId
            ? ($etablissementId ? Section::withoutGlobalScopes()->where('etablissement_id', $etablissementId)->find($sectionId) : Section::withoutGlobalScopes()->find($sectionId))
            : null;

        $cycle = $cycleId
            ? ($etablissementId
                ? Cycle::withoutGlobalScopes()->whereHas('section', fn ($q) => $q->withoutGlobalScopes()->where('etablissement_id', $etablissementId))->with('section')->find($cycleId)
                : Cycle::withoutGlobalScopes()->with('section')->find($cycleId))
            : null;

        $niveau = $niveauId
            ? ($etablissementId ? Niveau::withoutGlobalScopes()->where('etablissement_id', $etablissementId)->with('cycle.section')->find($niveauId) : Niveau::withoutGlobalScopes()->with('cycle.section')->find($niveauId))
            : null;

        $classe = $classeId
            ? ($etablissementId ? Classe::withoutGlobalScopes()->where('etablissement_id', $etablissementId)->with('niveau.cycle.section')->find($classeId) : Classe::withoutGlobalScopes()->with('niveau.cycle.section')->find($classeId))
            : null;

        $segments = [];
        $sectionNom = $section?->nom ?? $cycle?->section?->nom ?? $niveau?->cycle?->section?->nom ?? $classe?->niveau?->cycle?->section?->nom;
        $cycleNom = $cycle?->nom ?? $niveau?->cycle?->nom ?? $classe?->niveau?->cycle?->nom;
        $niveauNom = $niveau?->nom ?? $classe?->niveau?->nom;

        if ($sectionNom && ($section || $cycle || $niveau || $classe)) {
            $segments[] = 'Section : '.$sectionNom;
        }
        if ($cycleNom && ($cycle || $niveau || $classe)) {
            $segments[] = 'Cycle : '.$cycleNom;
        }
        if ($niveauNom && ($niveau || $classe)) {
            $segments[] = 'Niveau : '.$niveauNom;
        }
        if ($classe) {
            $segments[] = 'Classe : '.$classe->nom;
        }

        $defaultLabel = empty($etablissementId)
            ? 'Complexe Scolaire Global • Tous les établissements'
            : 'Toutes les sections, cycles, niveaux et classes';

        return [
            'section' => $section,
            'cycle' => $cycle,
            'niveau' => $niveau,
            'classe' => $classe,
            'perimetre_label' => ! empty($segments)
                ? implode(' • ', $segments)
                : $defaultLabel,
            'a_filtre_pedagogique' => (bool) ($sectionId || $cycleId || $niveauId || $classeId),
        ];
    }

    /**
     * Applique en cascade les filtres pédagogiques (Section → Cycle → Niveau → Classe)
     * sur une requête Eloquent portant une relation `inscription`.
     *
     * @param  Builder<VersementCaisse|CompteFinancierEleve>  $query  Requête Eloquent à restreindre
     * @param  int|null  $sectionId  Identifiant de la section
     * @param  int|null  $cycleId  Identifiant du cycle
     * @param  int|null  $niveauId  Identifiant du niveau
     * @param  int|null  $classeId  Identifiant de la classe
     */
    private function appliquerFiltreHierarchieInscription(
        Builder $query,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): void {
        if ($classeId) {
            $query->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        }

        if ($niveauId) {
            $query->whereHas('inscription.classe', fn ($q) => $q->where('niveau_id', $niveauId));
        }

        if ($cycleId) {
            $query->whereHas('inscription.classe.niveau', fn ($q) => $q->where('cycle_id', $cycleId));
        }

        if ($sectionId) {
            $query->whereHas('inscription.classe.niveau.cycle', fn ($q) => $q->where('section_id', $sectionId));
        }
    }

    /**
     * Normalise et ordonne un intervalle de dates `[debut, fin]` pour les états financiers.
     *
     * @param  string|null  $dateDebut  Date de début (YYYY-MM-DD), par défaut début du mois en cours
     * @param  string|null  $dateFin  Date de fin (YYYY-MM-DD), par défaut fin de la journée en cours
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resoudreIntervalleDates(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $debut = $dateDebut ? Carbon::parse($dateDebut)->startOfDay() : Carbon::today()->startOfMonth();
        $fin = $dateFin ? Carbon::parse($dateFin)->endOfDay() : Carbon::today()->endOfDay();

        if ($debut->gt($fin)) {
            $tmp = $debut->copy()->endOfDay();
            $debut = $fin->copy()->startOfDay();
            $fin = $tmp;
        }

        return [$debut, $fin];
    }

    /**
     * Récupère les données pour le Journal Détaillé des Encaissements filtré par période et hiérarchie scolaire.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  string|null  $dateDebut  Date de début (YYYY-MM-DD)
     * @param  string|null  $dateFin  Date de fin (YYYY-MM-DD)
     * @param  int|null  $caisseId  Filtre optionnel par caisse
     * @param  string|null  $modePaiement  Filtre optionnel par mode de paiement
     * @param  int|null  $sectionId  Filtre optionnel par section
     * @param  int|null  $cycleId  Filtre optionnel par cycle
     * @param  int|null  $niveauId  Filtre optionnel par niveau
     * @param  int|null  $classeId  Filtre optionnel par classe
     * @return array<string, mixed>
     */
    public function obtenirJournalDetaille(
        ?int $etablissementId = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?int $caisseId = null,
        ?string $modePaiement = null,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null,
        ?int $categorieFraisId = null,
        ?int $typeFraisId = null
    ): array {
        [$debut, $fin] = $this->resoudreIntervalleDates($dateDebut, $dateFin);
        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $query = VersementCaisse::with([
            'compteFinancier.eleve',
            'inscription.classe.niveau.cycle.section',
            'caisse',
            'caissier',
            'ventilations',
        ])
            ->where('statut', 'valide')
            ->whereBetween('date_versement', [$debut, $fin])
            ->orderBy('date_versement', 'asc');

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }

        if ($caisseId) {
            $query->where('caisse_id', $caisseId);
        }

        if ($modePaiement) {
            $query->where('mode_paiement', $modePaiement);
        }

        if ($categorieFraisId || $typeFraisId) {
            $query->whereHas('ventilations', function (Builder $vq) use ($etablissementId, $categorieFraisId, $typeFraisId) {
                $this->appliquerFiltreVentilationsParCibles($vq, $etablissementId, $categorieFraisId, $typeFraisId);
            });
        }

        $this->appliquerFiltreHierarchieInscription($query, $sectionId, $cycleId, $niveauId, $classeId);

        $versements = $query->get();

        $totauxParMode = [
            'especes' => 0.0,
            'cheque' => 0.0,
            'virement' => 0.0,
            'mobile_money_manuel' => 0.0,
        ];

        $totalGeneral = 0.0;
        foreach ($versements as $v) {
            $m = (float) $v->montant_total;
            $totalGeneral += $m;
            $mode = $v->mode_paiement;
            if (isset($totauxParMode[$mode])) {
                $totauxParMode[$mode] += $m;
            }
        }

        return [
            'date_debut' => $debut->format('d-m-Y'),
            'date_fin' => $fin->format('d-m-Y'),
            'perimetre_label' => $perimetre['perimetre_label'],
            'section' => $perimetre['section'],
            'cycle' => $perimetre['cycle'],
            'niveau' => $perimetre['niveau'],
            'classe' => $perimetre['classe'],
            'mode_paiement_filtre' => $modePaiement,
            'versements' => $versements,
            'total_general' => $totalGeneral,
            'totaux_par_mode' => $totauxParMode,
            'nombre_versements' => $versements->count(),
        ];
    }

    /**
     * Récupère les données pour le Journal Synthétique de Caisse (Totaux par jour, niveau/classe, caisse et rubrique).
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  string|null  $dateDebut  Date de début (YYYY-MM-DD)
     * @param  string|null  $dateFin  Date de fin (YYYY-MM-DD)
     * @param  int|null  $caisseId  Filtre optionnel par caisse
     * @param  string|null  $modePaiement  Filtre optionnel par mode de paiement
     * @param  int|null  $sectionId  Filtre optionnel par section
     * @param  int|null  $cycleId  Filtre optionnel par cycle
     * @param  int|null  $niveauId  Filtre optionnel par niveau
     * @param  int|null  $classeId  Filtre optionnel par classe
     * @return array<string, mixed>
     */
    public function obtenirJournalSynthetique(
        ?int $etablissementId = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?int $caisseId = null,
        ?string $modePaiement = null,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): array {
        [$debut, $fin] = $this->resoudreIntervalleDates($dateDebut, $dateFin);
        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $versementsQuery = VersementCaisse::with(['inscription.classe.niveau.cycle.section'])
            ->where('statut', 'valide')
            ->whereBetween('date_versement', [$debut, $fin])
            ->orderBy('date_versement', 'asc');

        if ($etablissementId) {
            $versementsQuery->where('etablissement_id', $etablissementId);
        }

        if ($caisseId) {
            $versementsQuery->where('caisse_id', $caisseId);
        }

        if ($modePaiement) {
            $versementsQuery->where('mode_paiement', $modePaiement);
        }

        $this->appliquerFiltreHierarchieInscription($versementsQuery, $sectionId, $cycleId, $niveauId, $classeId);

        $versements = $versementsQuery->get();

        // 1. Synthèse par Jour
        $parJour = [];
        // 2. Synthèse par Niveau & Classe (pilotage pédagogique/financier)
        $parClasse = [];

        foreach ($versements as $v) {
            $jour = Carbon::parse($v->date_versement)->format('d-m-Y');
            if (! isset($parJour[$jour])) {
                $parJour[$jour] = [
                    'date' => $jour,
                    'nombre' => 0,
                    'especes' => 0.0,
                    'momo' => 0.0,
                    'cheque' => 0.0,
                    'virement' => 0.0,
                    'total' => 0.0,
                ];
            }
            $m = (float) $v->montant_total;
            $parJour[$jour]['nombre']++;
            $parJour[$jour]['total'] += $m;
            if ($v->mode_paiement === 'especes') {
                $parJour[$jour]['especes'] += $m;
            } elseif ($v->mode_paiement === 'mobile_money_manuel') {
                $parJour[$jour]['momo'] += $m;
            } elseif ($v->mode_paiement === 'cheque') {
                $parJour[$jour]['cheque'] += $m;
            } elseif ($v->mode_paiement === 'virement') {
                $parJour[$jour]['virement'] += $m;
            }

            $classeObj = $v->inscription?->classe;
            $niveauObj = $classeObj?->niveau;
            $cleClasse = ($niveauObj?->id ?? 0).'-'.($classeObj?->id ?? 0);

            if (! isset($parClasse[$cleClasse])) {
                $parClasse[$cleClasse] = [
                    'niveau_ordre' => (int) ($niveauObj?->ordre ?? 999),
                    'niveau' => $niveauObj?->nom ?? 'Non affecté',
                    'classe' => $classeObj?->nom ?? 'N/A',
                    'nombre' => 0,
                    'total' => 0.0,
                ];
            }
            $parClasse[$cleClasse]['nombre']++;
            $parClasse[$cleClasse]['total'] += $m;
        }

        usort($parClasse, function (array $a, array $b): int {
            if ($a['niveau_ordre'] !== $b['niveau_ordre']) {
                return $a['niveau_ordre'] <=> $b['niveau_ordre'];
            }

            return strcmp($a['classe'], $b['classe']);
        });

        // 3. Synthèse par Caisse
        $caisses = Caisse::where('etablissement_id', $etablissementId)->get();
        $parCaisse = [];
        foreach ($caisses as $c) {
            $totCaisse = (float) $versements->where('caisse_id', $c->id)->sum('montant_total');
            $nbCaisse = $versements->where('caisse_id', $c->id)->count();
            $parCaisse[] = [
                'caisse' => $c->nom,
                'code' => $c->code,
                'nombre' => $nbCaisse,
                'total' => $totCaisse,
            ];
        }

        // 4. Synthèse par Rubrique (Ventilations)
        $ventilations = $versements->isNotEmpty()
            ? VentilationVersement::whereIn('versement_id', $versements->pluck('id'))
                ->select('libelle', DB::raw('SUM(montant) as total_montant'), DB::raw('COUNT(*) as total_occurrences'))
                ->groupBy('libelle')
                ->orderByDesc('total_montant')
                ->get()
            : collect();

        return [
            'date_debut' => $debut->format('d-m-Y'),
            'date_fin' => $fin->format('d-m-Y'),
            'perimetre_label' => $perimetre['perimetre_label'],
            'section' => $perimetre['section'],
            'cycle' => $perimetre['cycle'],
            'niveau' => $perimetre['niveau'],
            'classe' => $perimetre['classe'],
            'mode_paiement_filtre' => $modePaiement,
            'synthese_jours' => array_values($parJour),
            'synthese_niveaux' => array_values($parClasse),
            'synthese_caisses' => $parCaisse,
            'synthese_rubriques' => $ventilations,
            'total_general' => (float) $versements->sum('montant_total'),
            'nombre_total' => $versements->count(),
        ];
    }

    /**
     * Récupère la liste des élèves insolvables (dette échue > 0) filtrée par hiérarchie scolaire
     * (Section → Cycle → Niveau → Classe) et par période `[dateDebut, dateFin]`.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  int  $anneeId  Identifiant de l'année scolaire
     * @param  int|null  $classeId  Filtre optionnel par classe
     * @param  int|null  $niveauId  Filtre optionnel par niveau
     * @param  Carbon|null  $dateRef  Date de référence explicite (rétrocompatibilité)
     * @param  int|null  $sectionId  Filtre optionnel par section
     * @param  int|null  $cycleId  Filtre optionnel par cycle
     * @param  string|null  $dateDebut  Début de la fenêtre de suivi des encaissements (YYYY-MM-DD)
     * @param  string|null  $dateFin  Date d'arrêté des échéances échues (YYYY-MM-DD)
     * @return array<string, mixed>
     */
    public function obtenirListeInsolvables(
        ?int $etablissementId = null,
        int $anneeId = 0,
        ?int $classeId = null,
        ?int $niveauId = null,
        ?Carbon $dateRef = null,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?string $dateDebut = null,
        ?string $dateFin = null
    ): array {
        $reference = $dateFin
            ? Carbon::parse($dateFin)->endOfDay()
            : ($dateRef ? $dateRef->copy()->endOfDay() : Carbon::today()->endOfDay());
        $debut = $dateDebut ? Carbon::parse($dateDebut)->startOfDay() : null;

        if ($debut && $debut->gt($reference)) {
            $tmp = $debut->copy()->endOfDay();
            $debut = $reference->copy()->startOfDay();
            $reference = $tmp;
        }

        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $query = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe.niveau.cycle.section',
            'versements' => fn ($q) => $q->where('statut', 'valide'),
        ]);

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }

        if ($anneeId > 0) {
            $query->where('annee_scolaire_id', $anneeId);
        }

        $this->appliquerFiltreHierarchieInscription($query, $sectionId, $cycleId, $niveauId, $classeId);

        $comptes = $query->get();
        $insolvables = [];
        $totalDetteEchue = 0.0;
        $totalSoldeRestant = 0.0;
        $totalVersePeriode = 0.0;

        foreach ($comptes as $compte) {
            // L'insolvabilité est évaluée uniquement sur les échéances dont la date limite est échue à la date de référence
            $detteEchue = $compte->montantEchuEnRetard($reference);
            if ($detteEchue > 0) {
                $retard = $detteEchue;

                $versePeriode = (float) $compte->versements
                    ->filter(function (VersementCaisse $v) use ($debut, $reference): bool {
                        $dv = Carbon::parse($v->date_versement);
                        if ($debut && $dv->lt($debut)) {
                            return false;
                        }

                        return $dv->lte($reference);
                    })
                    ->sum('montant_total');

                $insolvables[] = [
                    'compte_id' => $compte->id,
                    'matricule' => $compte->eleve?->matricule,
                    'nom_complet' => $compte->eleve?->nom.' '.($compte->eleve?->prenom ?? ''),
                    'sexe' => $compte->eleve?->sexe,
                    'redoublant' => (bool) ($compte->inscription?->is_redoublant ?? false) ? 'R' : 'NR',
                    'date_naissance' => $compte->eleve?->date_naissance ? Carbon::parse($compte->eleve->date_naissance)->format('d-m-Y') : null,
                    'lieu_naissance' => $compte->eleve?->lieu_naissance,
                    'parent_tuteur' => $compte->eleve?->nom_parent ?? $compte->eleve?->nom_tuteur ?? $compte->eleve?->nom_pere ?? $compte->eleve?->nom_mere ?? 'N/A',
                    'niveau' => $compte->inscription?->classe?->niveau?->nom ?? 'N/A',
                    'classe' => $compte->inscription?->classe?->nom ?? 'N/A',
                    'contact_parent' => $compte->eleve?->telephone_parent ?? $compte->eleve?->telephone_tuteur ?? $compte->eleve?->telephone ?? 'N/A',
                    'montant_net_exigible' => (float) $compte->montant_net_exigible,
                    'total_paye' => (float) $compte->total_paye,
                    'verse_periode' => $versePeriode,
                    'montant_echu_en_retard' => $retard,
                    'solde_restant' => (float) $compte->solde_restant,
                ];
                $totalDetteEchue += $retard;
                $totalSoldeRestant += (float) $compte->solde_restant;
                $totalVersePeriode += $versePeriode;
            }
        }

        // Tri par dette échue décroissante
        usort($insolvables, fn ($a, $b) => $b['montant_echu_en_retard'] <=> $a['montant_echu_en_retard']);

        return [
            'date_debut' => $debut?->format('d-m-Y'),
            'date_fin' => $reference->format('d-m-Y'),
            'date_reference' => $reference->format('d-m-Y'),
            'perimetre_label' => $perimetre['perimetre_label'],
            'section' => $perimetre['section'],
            'cycle' => $perimetre['cycle'],
            'niveau' => $perimetre['niveau'],
            'classe' => $perimetre['classe'],
            'eleves' => $insolvables,
            'nombre_insolvables' => count($insolvables),
            'total_dette_echue' => $totalDetteEchue,
            'total_solde_restant' => $totalSoldeRestant,
            'total_verse_periode' => $totalVersePeriode,
        ];
    }

    /**
     * Récupère la liste des élèves en règle (aucune dette échue à la date de fin ou scolarité 100 % soldée),
     * filtrée par hiérarchie scolaire (Section → Cycle → Niveau → Classe) et par période `[dateDebut, dateFin]`.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  int  $anneeId  Identifiant de l'année scolaire
     * @param  int|null  $classeId  Filtre optionnel par classe
     * @param  int|null  $niveauId  Filtre optionnel par niveau
     * @param  int|null  $sectionId  Filtre optionnel par section
     * @param  int|null  $cycleId  Filtre optionnel par cycle
     * @param  string|null  $dateDebut  Début de la fenêtre de suivi des encaissements (YYYY-MM-DD)
     * @param  string|null  $dateFin  Date d'arrêté des échéances échues (YYYY-MM-DD)
     * @return array<string, mixed>
     */
    public function obtenirListeEnRegle(
        ?int $etablissementId = null,
        int $anneeId = 0,
        ?int $classeId = null,
        ?int $niveauId = null,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?string $dateDebut = null,
        ?string $dateFin = null
    ): array {
        $reference = $dateFin ? Carbon::parse($dateFin)->endOfDay() : Carbon::today()->endOfDay();
        $debut = $dateDebut ? Carbon::parse($dateDebut)->startOfDay() : null;

        if ($debut && $debut->gt($reference)) {
            $tmp = $debut->copy()->endOfDay();
            $debut = $reference->copy()->startOfDay();
            $reference = $tmp;
        }

        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $query = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe.niveau.cycle.section',
            'versements' => fn ($q) => $q->where('statut', 'valide'),
        ]);

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }

        if ($anneeId > 0) {
            $query->where('annee_scolaire_id', $anneeId);
        }

        $this->appliquerFiltreHierarchieInscription($query, $sectionId, $cycleId, $niveauId, $classeId);

        $comptes = $query->get();
        $enRegle = [];
        $totalPaye = 0.0;
        $totalVersePeriode = 0.0;

        foreach ($comptes as $compte) {
            // Un élève sans aucun frais scolaire paramétré (exigible = 0 et rien versé) ne doit pas être considéré comme soldé/en règle
            $aDesFraisConfigures = ((float) $compte->montant_net_exigible > 0) || ((float) $compte->debit_initial > 0) || ((float) $compte->total_paye > 0);
            if (! $aDesFraisConfigures) {
                continue;
            }

            $detteEchue = $compte->montantEchuEnRetard($reference);
            if ($detteEchue <= 0 && ($compte->total_paye > 0 || $compte->solde_restant <= 0)) {
                $estSolde = $compte->solde_restant <= 0;
                $versePeriode = (float) $compte->versements
                    ->filter(function (VersementCaisse $v) use ($debut, $reference): bool {
                        $dv = Carbon::parse($v->date_versement);
                        if ($debut && $dv->lt($debut)) {
                            return false;
                        }

                        return $dv->lte($reference);
                    })
                    ->sum('montant_total');

                $enRegle[] = [
                    'compte_id' => $compte->id,
                    'matricule' => $compte->eleve?->matricule,
                    'nom_complet' => $compte->eleve?->nom.' '.($compte->eleve?->prenom ?? ''),
                    'sexe' => $compte->eleve?->sexe,
                    'niveau' => $compte->inscription?->classe?->niveau?->nom ?? 'N/A',
                    'classe' => $compte->inscription?->classe?->nom ?? 'N/A',
                    'montant_net_exigible' => (float) $compte->montant_net_exigible,
                    'total_paye' => (float) $compte->total_paye,
                    'verse_periode' => $versePeriode,
                    'solde_restant' => (float) $compte->solde_restant,
                    'statut' => $estSolde ? 'Soldé (100%)' : 'À jour de l\'échéance',
                ];
                $totalPaye += (float) $compte->total_paye;
                $totalVersePeriode += $versePeriode;
            }
        }

        return [
            'date_debut' => $debut?->format('d-m-Y'),
            'date_fin' => $reference->format('d-m-Y'),
            'date_reference' => $reference->format('d-m-Y'),
            'perimetre_label' => $perimetre['perimetre_label'],
            'section' => $perimetre['section'],
            'cycle' => $perimetre['cycle'],
            'niveau' => $perimetre['niveau'],
            'classe' => $perimetre['classe'],
            'eleves' => $enRegle,
            'nombre_en_regle' => count($enRegle),
            'total_paye' => $totalPaye,
            'total_verse_periode' => $totalVersePeriode,
        ];
    }

    /**
     * Génère l'état consolidé des Recettes Diverses et encaissements libres hors scolarité.
     *
     * Permet le filtrage par période (date_debut / date_fin), caisse, mode de règlement et catégorie.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @param  string|null  $dateDebut  Date de début (YYYY-MM-DD ou JJ-MM-AAAA)
     * @param  string|null  $dateFin  Date de fin (YYYY-MM-DD ou JJ-MM-AAAA)
     * @param  int|null  $caisseId  Identifiant de la caisse
     * @param  string|null  $modePaiement  Mode de règlement
     * @param  string|null  $categorie  Catégorie personnalisée de recette
     * @return array<string, mixed> Données consolidées pour l'édition PDF et le tableau
     */
    public function obtenirEtatRecettesDiverses(
        ?int $etablissementId = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?int $caisseId = null,
        ?string $modePaiement = null,
        ?string $categorie = null
    ): array {
        [$debut, $fin] = $this->resoudreIntervalleDates($dateDebut, $dateFin);

        $query = ReglementDivers::with(['caisse', 'caissier', 'user'])
            ->where('statut', 'valide')
            ->whereBetween('date_reglement', [$debut, $fin])
            ->orderBy('date_reglement', 'asc');

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }

        if ($caisseId) {
            $query->where('caisse_id', $caisseId);
        }

        if ($modePaiement && $modePaiement !== 'all') {
            $query->where('mode_paiement', $modePaiement);
        }

        if ($categorie && $categorie !== 'all') {
            $query->where('categorie', $categorie);
        }

        $recettes = $query->get();

        $totalGeneral = (float) $recettes->sum('montant');
        $totalEspeces = (float) $recettes->where('mode_paiement', 'especes')->sum('montant');
        $totalCheques = (float) $recettes->where('mode_paiement', 'cheque')->sum('montant');
        $totalVirements = (float) $recettes->where('mode_paiement', 'virement')->sum('montant');
        $totalMobileMoney = (float) $recettes->where('mode_paiement', 'mobile_money_manuel')->sum('montant');

        // Récapitulatif ventilé par catégorie
        $parCategorie = $recettes->groupBy('categorie')->map(function ($groupe, $nomCat) {
            return [
                'categorie' => $nomCat,
                'nombre' => $groupe->count(),
                'montant' => (float) $groupe->sum('montant'),
            ];
        })->values();

        $caisseNom = $caisseId ? (Caisse::find($caisseId)?->nom ?? 'Toutes les caisses') : 'Toutes les caisses';

        return [
            'recettes' => $recettes,
            'date_debut' => $debut->format('d-m-Y'),
            'date_fin' => $fin->format('d-m-Y'),
            'caisse_nom' => $caisseNom,
            'mode_paiement_filtre' => $modePaiement ?: 'Tous les modes',
            'categorie_filtre' => $categorie ?: 'Toutes les catégories',
            'total_general' => $totalGeneral,
            'total_especes' => $totalEspeces,
            'total_cheques' => $totalCheques,
            'total_virements' => $totalVirements,
            'total_mobile_money' => $totalMobileMoney,
            'nombre_operations' => $recettes->count(),
            'par_categorie' => $parCategorie,
        ];
    }

    /**
     * Génère les données du Journal Résumé des Versements (agrégation par Section -> Niveau -> Classe).
     *
     * Conforme au modèle officiel « Journal résumé des versements » du concurrent.
     *
     * @param  int|null  $etablissementId  Identifiant de l'établissement ou null (vision globale du complexe)
     * @param  string|null  $dateDebut  Date de début
     * @param  string|null  $dateFin  Date de fin
     * @param  int|null  $sectionId  Filtre section
     * @param  int|null  $cycleId  Filtre cycle
     * @param  int|null  $niveauId  Filtre niveau
     * @param  int|null  $classeId  Filtre classe
     * @return array<string, mixed>
     */
    public function obtenirJournalResume(
        ?int $etablissementId = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): array {
        [$debut, $fin] = $this->resoudreIntervalleDates($dateDebut, $dateFin);
        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $query = VersementCaisse::with(['inscription.classe.niveau.cycle.section'])
            ->where('statut', 'valide')
            ->whereBetween('date_versement', [$debut, $fin]);

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }

        $this->appliquerFiltreHierarchieInscription($query, $sectionId, $cycleId, $niveauId, $classeId);

        $versements = $query->get();

        $structure = [];
        $totalGeneralVerse = 0.0;
        $totalElevesAyantVerse = $versements->pluck('inscription.eleve_id')->filter()->unique()->count();

        foreach ($versements as $v) {
            $classe = $v->inscription?->classe;
            if (! $classe) {
                continue;
            }
            $niveau = $classe->niveau;
            $cycle = $niveau?->cycle;
            $section = $cycle?->section;

            $secKey = $section?->id ?? 0;
            $secNom = $section?->nom ?? 'Section Standard';

            $nivKey = $niveau?->id ?? 0;
            $nivNom = $niveau?->nom ?? 'Niveau Standard';
            $nivOrdre = (int) ($niveau?->ordre ?? 999);

            $claKey = $classe->id;
            $claNom = $classe->nom;

            if (! isset($structure[$secKey])) {
                $structure[$secKey] = [
                    'id' => $secKey,
                    'nom' => $secNom,
                    'total_verse' => 0.0,
                    'effectif' => 0,
                    'niveaux' => [],
                ];
            }

            if (! isset($structure[$secKey]['niveaux'][$nivKey])) {
                $structure[$secKey]['niveaux'][$nivKey] = [
                    'id' => $nivKey,
                    'nom' => $nivNom,
                    'ordre' => $nivOrdre,
                    'total_verse' => 0.0,
                    'effectif' => 0,
                    'classes' => [],
                ];
            }

            if (! isset($structure[$secKey]['niveaux'][$nivKey]['classes'][$claKey])) {
                $structure[$secKey]['niveaux'][$nivKey]['classes'][$claKey] = [
                    'id' => $claKey,
                    'nom' => $claNom,
                    'total_verse' => 0.0,
                    'nombre_recus' => 0,
                    'eleves_ids' => [],
                ];
            }

            $m = (float) $v->montant_total;
            $totalGeneralVerse += $m;
            $structure[$secKey]['total_verse'] += $m;
            $structure[$secKey]['niveaux'][$nivKey]['total_verse'] += $m;
            $structure[$secKey]['niveaux'][$nivKey]['classes'][$claKey]['total_verse'] += $m;
            $structure[$secKey]['niveaux'][$nivKey]['classes'][$claKey]['nombre_recus']++;

            if ($v->inscription?->eleve_id) {
                $structure[$secKey]['niveaux'][$nivKey]['classes'][$claKey]['eleves_ids'][] = $v->inscription->eleve_id;
            }
        }

        foreach ($structure as $secKey => &$secData) {
            $secEleves = [];
            foreach ($secData['niveaux'] as $nivKey => &$nivData) {
                $nivEleves = [];
                foreach ($nivData['classes'] as $claKey => &$claData) {
                    $uniqueEleves = array_unique($claData['eleves_ids']);
                    $claData['effectif'] = count($uniqueEleves);
                    unset($claData['eleves_ids']);
                    $nivEleves = array_merge($nivEleves, $uniqueEleves);
                }
                unset($claData);
                $uniqueNivEleves = array_unique($nivEleves);
                $nivData['effectif'] = count($uniqueNivEleves);
                $secEleves = array_merge($secEleves, $uniqueNivEleves);
                usort($nivData['classes'], fn ($a, $b) => strcmp($a['nom'], $b['nom']));
            }
            unset($nivData);
            $secData['effectif'] = count(array_unique($secEleves));
            usort($secData['niveaux'], fn ($a, $b) => $a['ordre'] <=> $b['ordre']);
        }
        unset($secData);

        return [
            'date_debut' => $debut->format('d-m-Y'),
            'date_fin' => $fin->format('d-m-Y'),
            'perimetre_label' => $perimetre['perimetre_label'],
            'sections' => array_values($structure),
            'total_general_verse' => $totalGeneralVerse,
            'total_general_lettres' => $this->convertirMontantEnLettres($totalGeneralVerse),
            'total_eleves_ayants_verse' => $totalElevesAyantVerse,
            'nombre_versements' => $versements->count(),
        ];
    }

    /**
     * Génère les données du Journal des Versements ventilé par Catégorie de Frais.
     *
     * Conforme au modèle officiel « Liste des Versements - Regroupement par catégorie » du concurrent.
     */
    public function obtenirJournalParCategories(
        ?int $etablissementId = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?int $caisseId = null,
        ?string $modePaiement = null,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null,
        ?int $categorieFraisId = null,
        ?int $typeFraisId = null
    ): array {
        [$debut, $fin] = $this->resoudreIntervalleDates($dateDebut, $dateFin);
        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $query = VentilationVersement::with([
            'versement.compteFinancier.eleve',
            'versement.inscription.classe.niveau.cycle.section',
            'versement.caisse',
            'versement.caissier',
            'fraisScolaire.typeFrais.categorieFrais',
        ])
            ->whereHas('versement', function ($q) use ($debut, $fin, $etablissementId, $caisseId, $modePaiement) {
                $q->where('statut', 'valide')
                    ->whereBetween('date_versement', [$debut, $fin]);
                if ($etablissementId) {
                    $q->where('etablissement_id', $etablissementId);
                }
                if ($caisseId) {
                    $q->where('caisse_id', $caisseId);
                }
                if ($modePaiement) {
                    $q->where('mode_paiement', $modePaiement);
                }
            });

        if ($categorieFraisId || $typeFraisId) {
            $this->appliquerFiltreVentilationsParCibles($query, $etablissementId, $categorieFraisId, $typeFraisId);
        }

        if ($classeId || $niveauId || $cycleId || $sectionId) {
            $query->whereHas('versement', function ($q) use ($classeId, $niveauId, $cycleId, $sectionId) {
                $this->appliquerFiltreHierarchieInscription($q, $sectionId, $cycleId, $niveauId, $classeId);
            });
        }

        $ventilations = $query->get();

        $groupes = [];
        $totalGeneral = 0.0;
        $formatter = class_exists(\NumberFormatter::class) ? new \NumberFormatter('fr', \NumberFormatter::SPELLOUT) : null;

        foreach ($ventilations as $vent) {
            $v = $vent->versement;
            if (! $v) {
                continue;
            }

            $catNom = $vent->fraisScolaire?->typeFrais?->categorieFrais?->nom
                ?? $vent->fraisScolaire?->typeFrais?->libelle
                ?? $vent->libelle;

            if (! isset($groupes[$catNom])) {
                $groupes[$catNom] = [
                    'categorie' => $catNom,
                    'lignes' => [],
                    'total_categorie' => 0.0,
                    'total_en_lettres' => '',
                ];
            }

            $m = (float) $vent->montant;
            $totalGeneral += $m;
            $groupes[$catNom]['total_categorie'] += $m;

            $eleve = $v->compteFinancier?->eleve;
            $classe = $v->inscription?->classe?->nom ?? 'N/A';

            $groupes[$catNom]['lignes'][] = [
                'date_heure' => Carbon::parse($v->date_versement)->format('d-m-Y H:i'),
                'reference_recu' => $v->reference_recu,
                'matricule' => $eleve?->matricule ?? 'N/A',
                'eleve_nom' => $eleve ? ($eleve->nom.' '.($eleve->prenom ?? '')) : 'N/A',
                'classe' => $classe,
                'payeur' => $v->payeur_nom ?: 'Élève / Parent',
                'mode_paiement' => $v->mode_paiement,
                'caissier' => $v->caissier?->nom ?? 'Économe',
                'rubrique_libelle' => $vent->libelle,
                'montant' => $m,
            ];
        }

        foreach ($groupes as &$grp) {
            if ($formatter) {
                $grp['total_en_lettres'] = mb_strtoupper($formatter->format((int) $grp['total_categorie'])).' FRANCS CFA';
            }
        }
        unset($grp);

        $totalGeneralLettres = $formatter ? mb_strtoupper($formatter->format((int) $totalGeneral)).' FRANCS CFA' : '';

        return [
            'date_debut' => $debut->format('d-m-Y'),
            'date_fin' => $fin->format('d-m-Y'),
            'perimetre_label' => $perimetre['perimetre_label'],
            'categories' => array_values($groupes),
            'total_general' => $totalGeneral,
            'total_general_lettres' => $totalGeneralLettres,
            'nombre_lignes' => $ventilations->count(),
            'nombre_versements' => $ventilations->pluck('versement_id')->unique()->count(),
        ];
    }

    /**
     * Génère l'état nominatif de suivi des élèves pour une Catégorie de Frais ou un Frais précis,
     * avec filtrage par condition / palier atteint (Tous, Soldé, Uniquement jusqu'à ce palier, Partiel, Non payé).
     *
     * @param  int|null  $etablissementId  Identifiant de l'établissement (null en mode multi-campus)
     * @param  int  $anneeId  Identifiant de l'année scolaire
     * @param  int|null  $categorieFraisId  Filtre optionnel par catégorie de frais
     * @param  int|null  $typeFraisId  Filtre optionnel par rubrique de frais précise (ex: Inscription, 1ère Tranche)
     * @param  string  $modeFiltre  Condition : tous | solde | uniquement_palier | partiel | non_paye
     * @param  int|null  $sectionId  Filtre optionnel par section
     * @param  int|null  $cycleId  Filtre optionnel par cycle
     * @param  int|null  $niveauId  Filtre optionnel par niveau
     * @param  int|null  $classeId  Filtre optionnel par classe
     * @return array<string, mixed>
     */
    public function obtenirSuiviParFraisOuCategorie(
        ?int $etablissementId = null,
        int $anneeId = 0,
        ?int $categorieFraisId = null,
        ?int $typeFraisId = null,
        string $modeFiltre = 'tous',
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): array {
        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $query = CompteFinancierEleve::withoutGlobalScopes()
            ->with([
                'eleve',
                'inscription.classe.niveau.cycle.section',
                'boursesRemises.bourseRemise',
                'dispensesFinancieres.fraisScolaire',
                'souscriptionsOptionnelles.fraisScolaire.typeFrais.categorieFrais',
                'penalites',
                'versements.ventilations',
            ])
            ->whereHas('inscription', fn (Builder $iq) => $iq->whereNotIn('statut', ['annulee', 'radie']));

        if ($anneeId > 0) {
            $query->where('annee_scolaire_id', $anneeId);
        }
        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }
        if ($classeId || $niveauId || $cycleId || $sectionId) {
            $this->appliquerFiltreHierarchieInscription($query, $sectionId, $cycleId, $niveauId, $classeId);
        }

        $comptes = $query->get();
        $cibles = $this->resoudreIdsCiblesFrais($etablissementId, $categorieFraisId, $typeFraisId);
        $categorieIdsCibles = $cibles['categorie_ids'];
        $typeIdsCibles = $cibles['type_ids'];
        $aujourdHui = Carbon::today();
        $tarifsCache = [];

        $elevesLignes = [];
        $totalAttendu = 0.0;
        $totalPaye = 0.0;
        $totalRestant = 0.0;

        foreach ($comptes as $compte) {
            $classe = $compte->inscription?->classe;
            $niveauIdCompte = $classe?->niveau_id;
            $serieCompte = $classe?->serie;

            if (! $niveauIdCompte) {
                continue;
            }

            $cleCache = $compte->etablissement_id.'_'.$compte->annee_scolaire_id.'_'.$niveauIdCompte.'_'.($serieCompte ? mb_strtoupper(trim($serieCompte)) : '');
            if (! array_key_exists($cleCache, $tarifsCache)) {
                $tarifsCache[$cleCache] = $this->obtenirTarifsObligatoiresApplicables(
                    (int) $compte->annee_scolaire_id,
                    (int) $compte->etablissement_id,
                    (int) $niveauIdCompte,
                    $serieCompte
                )->values();
            }

            $calcul = $this->construireLignesEcheancier($compte, $aujourdHui, $tarifsCache[$cleCache]);
            $lignes = $calcul['lignes'] ?? [];

            // Sélection des lignes correspondant au frais ou à la catégorie ciblée (y compris homonymes multi-campus).
            $lignesCibles = array_values(array_filter($lignes, function (array $l) use ($typeFraisId, $categorieFraisId, $typeIdsCibles, $categorieIdsCibles): bool {
                $typeLigne = $l['type'] ?? '';
                if (! in_array($typeLigne, ['obligatoire', 'optionnel'], true)) {
                    return false;
                }
                if ($typeFraisId !== null && ! in_array((int) ($l['type_frais_id'] ?? 0), $typeIdsCibles, true)) {
                    return false;
                }
                if ($categorieFraisId !== null && ! in_array((int) ($l['categorie_frais_id'] ?? 0), $categorieIdsCibles, true)) {
                    return false;
                }

                return true;
            }));

            if (empty($lignesCibles)) {
                continue;
            }

            $attendu = round(array_sum(array_map(fn (array $l): float => (float) ($l['montant'] ?? 0), $lignesCibles)), 2);
            $paye = round(array_sum(array_map(fn (array $l): float => (float) ($l['montant_paye'] ?? 0), $lignesCibles)), 2);
            $restant = round(array_sum(array_map(fn (array $l): float => (float) ($l['montant_restant'] ?? 0), $lignesCibles)), 2);
            $cibleSoldee = $restant <= 0.009;

            // Application de la condition / palier demandé.
            $estRetenu = match ($modeFiltre) {
                'solde' => $cibleSoldee,
                'partiel' => $paye > 0.009 && $restant > 0.009,
                'non_paye' => $attendu > 0.009 && $paye <= 0.009,
                'uniquement_palier' => $this->correspondAuPalierUniquement(
                    $lignes,
                    $cibleSoldee,
                    $typeFraisId !== null ? $typeIdsCibles : [],
                    $categorieFraisId !== null ? $categorieIdsCibles : []
                ),
                default => true,
            };

            if (! $estRetenu) {
                continue;
            }

            $statutCible = $cibleSoldee
                ? 'Soldé'
                : ($paye > 0.009 ? 'Partiel' : 'Non payé');

            $rubriquesLabel = implode(', ', array_unique(array_map(
                fn (array $l): string => (string) ($l['libelle'] ?? ''),
                $lignesCibles
            )));

            $eleve = $compte->eleve;
            $elevesLignes[] = [
                'matricule' => $eleve?->matricule ?? 'N/A',
                'nom_complet' => trim(($eleve?->nom ?? '').' '.($eleve?->prenom ?? '')) ?: 'N/A',
                'classe' => $classe?->nom ?? 'N/A',
                'rubriques_label' => $rubriquesLabel,
                'attendu' => $attendu,
                'paye' => $paye,
                'restant' => $restant,
                'statut_cible' => $statutCible,
            ];

            $totalAttendu += $attendu;
            $totalPaye += $paye;
            $totalRestant += $restant;
        }

        usort($elevesLignes, function (array $a, array $b): int {
            $cmp = strcmp($a['classe'], $b['classe']);

            return $cmp !== 0 ? $cmp : strcmp($a['nom_complet'], $b['nom_complet']);
        });

        $typeFrais = $typeFraisId ? TypeFrais::withoutGlobalScopes()->find($typeFraisId) : null;
        $categorieFrais = $categorieFraisId ? CategorieFrais::withoutGlobalScopes()->find($categorieFraisId) : null;

        $cibleLabel = 'Tous les frais';
        if ($typeFrais && $categorieFrais) {
            $cibleLabel = $categorieFrais->nom.' — '.$typeFrais->libelle;
        } elseif ($typeFrais) {
            $cibleLabel = $typeFrais->libelle;
        } elseif ($categorieFrais) {
            $cibleLabel = 'Catégorie : '.$categorieFrais->nom;
        }

        $modeLabel = match ($modeFiltre) {
            'solde' => 'Ayant soldé le frais / catégorie',
            'uniquement_palier' => 'Ayant payé uniquement jusqu\'à ce palier (aucun palier suivant soldé)',
            'partiel' => 'Paiement partiel (acompte en cours)',
            'non_paye' => 'Aucun versement sur ce frais',
            default => 'Tous les élèves concernés',
        };

        $taux = $totalAttendu > 0 ? round(($totalPaye / $totalAttendu) * 100, 1) : 0.0;
        $formatter = class_exists(\NumberFormatter::class) ? new \NumberFormatter('fr', \NumberFormatter::SPELLOUT) : null;
        $totalPayeLettres = $formatter ? mb_strtoupper($formatter->format((int) $totalPaye)).' FRANCS CFA' : '';

        return [
            'perimetre_label' => $perimetre['perimetre_label'],
            'cible_label' => $cibleLabel,
            'mode_label' => $modeLabel,
            'eleves' => $elevesLignes,
            'nombre_eleves' => count($elevesLignes),
            'total_attendu' => $totalAttendu,
            'total_paye' => $totalPaye,
            'total_restant' => $totalRestant,
            'taux_recouvrement' => $taux,
            'total_paye_lettres' => $totalPayeLettres,
        ];
    }

    /**
     * Résout les identifiants réels de catégories et de types de frais à cibler dans les états PDF.
     *
     * Pour un établissement précis ($etablissementId non nul), conserve l'identifiant sélectionné.
     * En vision globale multi-campus ($etablissementId === null), l'interface dédoublonne les libellés
     * homonymes (« Scolarité », « Inscription », etc.) : étend alors la sélection à tous les campus
     * partageant le même libellé normalisé.
     *
     * @param  int|null  $etablissementId  Identifiant de l'établissement (null en mode multi-campus)
     * @param  int|null  $categorieFraisId  Identifiant de catégorie sélectionné
     * @param  int|null  $typeFraisId  Identifiant de type de frais sélectionné
     * @return array{categorie_ids: array<int, int>, type_ids: array<int, int>}
     */
    private function resoudreIdsCiblesFrais(
        ?int $etablissementId,
        ?int $categorieFraisId,
        ?int $typeFraisId
    ): array {
        $categorieIds = [];
        if ($categorieFraisId !== null) {
            if ($etablissementId !== null && $etablissementId > 0) {
                $categorieIds = [$categorieFraisId];
            } else {
                $catCible = CategorieFrais::withoutGlobalScopes()->find($categorieFraisId);
                if ($catCible) {
                    $nomNorm = mb_strtolower(trim($catCible->nom));
                    $categorieIds = CategorieFrais::withoutGlobalScopes()
                        ->where('is_active', true)
                        ->get(['id', 'nom'])
                        ->filter(fn (CategorieFrais $c): bool => mb_strtolower(trim($c->nom)) === $nomNorm)
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();
                }
                if (empty($categorieIds)) {
                    $categorieIds = [$categorieFraisId];
                }
            }
        }

        $typeIds = [];
        if ($typeFraisId !== null) {
            if ($etablissementId !== null && $etablissementId > 0) {
                $typeIds = [$typeFraisId];
            } else {
                $tfCible = TypeFrais::withoutGlobalScopes()->find($typeFraisId);
                if ($tfCible) {
                    $libelleNorm = mb_strtolower(trim($tfCible->libelle));
                    $typesQuery = TypeFrais::withoutGlobalScopes()->where('is_active', true);
                    if (! empty($categorieIds)) {
                        $typesQuery->whereIn('categorie_frais_id', $categorieIds);
                    }
                    $typeIds = $typesQuery->get(['id', 'libelle'])
                        ->filter(fn (TypeFrais $tf): bool => mb_strtolower(trim($tf->libelle)) === $libelleNorm)
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();
                }
                if (empty($typeIds)) {
                    $typeIds = [$typeFraisId];
                }
            }
        }

        return [
            'categorie_ids' => $categorieIds,
            'type_ids' => $typeIds,
        ];
    }

    /**
     * Applique sur une requête de ventilations (ou sous-requête whereHas) le filtrage par type de frais
     * et/ou catégorie de frais, y compris les homonymes multi-campus en vision globale.
     *
     * @param  Builder  $vq  Requête Eloquent portant sur les ventilations
     * @param  int|null  $etablissementId  Établissement ciblé (null = multi-campus)
     * @param  int|null  $categorieFraisId  Identifiant de catégorie optionnel
     * @param  int|null  $typeFraisId  Identifiant de type de frais optionnel
     */
    private function appliquerFiltreVentilationsParCibles(
        Builder $vq,
        ?int $etablissementId,
        ?int $categorieFraisId,
        ?int $typeFraisId
    ): void {
        if ($categorieFraisId === null && $typeFraisId === null) {
            return;
        }

        $cibles = $this->resoudreIdsCiblesFrais($etablissementId, $categorieFraisId, $typeFraisId);
        $typeIds = $cibles['type_ids'];
        $categorieIds = $cibles['categorie_ids'];

        if ($typeFraisId !== null) {
            if (empty($typeIds)) {
                $vq->whereRaw('1 = 0');
            } else {
                $vq->where(function (Builder $sub) use ($typeIds) {
                    $sub->whereIn('type_frais_id', $typeIds)
                        ->orWhereHas('fraisScolaire', fn (Builder $fq) => $fq->whereIn('type_frais_id', $typeIds));
                });
            }
        }

        if ($categorieFraisId !== null) {
            $catTypeIds = empty($categorieIds)
                ? []
                : TypeFrais::withoutGlobalScopes()
                    ->whereIn('categorie_frais_id', $categorieIds)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

            if (empty($catTypeIds)) {
                $vq->whereRaw('1 = 0');
            } else {
                $vq->where(function (Builder $sub) use ($catTypeIds) {
                    $sub->whereIn('type_frais_id', $catTypeIds)
                        ->orWhereHas('fraisScolaire', fn (Builder $fq) => $fq->whereIn('type_frais_id', $catTypeIds));
                });
            }
        }
    }

    /**
     * Vérifie si un élève a atteint exactement le palier demandé sans avoir soldé de rubrique obligatoire ultérieure.
     *
     * @param  array<int, array<string, mixed>>  $lignes  Ensemble des lignes de l'échéancier
     * @param  bool  $cibleSoldee  Indique si la ou les lignes ciblées sont soldées
     * @param  array<int, int>  $typeFraisIds  Identifiants du type de frais ciblé (étendus en multi-campus)
     * @param  array<int, int>  $categorieFraisIds  Identifiants de la catégorie ciblée (étendus en multi-campus)
     * @return bool Vrai si le palier ciblé est soldé et qu'aucun palier ultérieur obligatoire n'est soldé
     */
    private function correspondAuPalierUniquement(
        array $lignes,
        bool $cibleSoldee,
        array $typeFraisIds,
        array $categorieFraisIds
    ): bool {
        if (! $cibleSoldee) {
            return false;
        }

        $lignesOblig = array_values(array_filter($lignes, fn (array $l): bool => ($l['type'] ?? '') === 'obligatoire'));
        $dernierIndexCible = null;

        foreach ($lignesOblig as $idx => $ligne) {
            $matchType = ! empty($typeFraisIds) && in_array((int) ($ligne['type_frais_id'] ?? 0), $typeFraisIds, true);
            $matchCat = empty($typeFraisIds) && ! empty($categorieFraisIds) && in_array((int) ($ligne['categorie_frais_id'] ?? 0), $categorieFraisIds, true);
            if ($matchType || $matchCat) {
                $dernierIndexCible = $idx;
            }
        }

        // Si le frais ciblé n'est pas une tranche obligatoire (ex: optionnel), on valide simplement son solde.
        if ($dernierIndexCible === null) {
            return true;
        }

        for ($i = $dernierIndexCible + 1, $total = count($lignesOblig); $i < $total; $i++) {
            $ligneSuivante = $lignesOblig[$i];
            if ((float) ($ligneSuivante['montant_restant'] ?? 0) <= 0.009) {
                return false;
            }
        }

        return true;
    }

    /**
     * Génère les données du Bilan Résumé des Recouvrements (Synthèse comparée par Section/Niveau/Classe).
     *
     * Colonnes conformes au concurrent : Classe, À verser, Réduction, Versé, Reste, % Recouvrement, Effectif.
     */
    public function obtenirBilanResumeRecouvrement(
        ?int $etablissementId = null,
        int $anneeId = 0,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): array {
        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $classesQuery = Classe::with(['niveau.cycle.section', 'inscriptions.compteFinancier' => function ($q) use ($anneeId) {
            if ($anneeId > 0) {
                $q->where('annee_scolaire_id', $anneeId);
            }
        }])
            ->where('is_active', true);

        if ($etablissementId) {
            $classesQuery->where('etablissement_id', $etablissementId);
        }

        if ($classeId) {
            $classesQuery->where('id', $classeId);
        } elseif ($niveauId) {
            $classesQuery->where('niveau_id', $niveauId);
        } elseif ($cycleId) {
            $classesQuery->whereHas('niveau', fn ($q) => $q->where('cycle_id', $cycleId));
        } elseif ($sectionId) {
            $classesQuery->whereHas('niveau.cycle', fn ($q) => $q->where('section_id', $sectionId));
        }

        $classes = $classesQuery->get();

        $structure = [];
        $grandTotalAVerser = 0.0;
        $grandTotalReduction = 0.0;
        $grandTotalVerse = 0.0;
        $grandTotalReste = 0.0;
        $grandTotalEffectif = 0;

        foreach ($classes as $classe) {
            $niveau = $classe->niveau;
            $cycle = $niveau?->cycle;
            $section = $cycle?->section;

            $secKey = $section?->id ?? 0;
            $secNom = $section?->nom ?? 'Section Standard';

            $nivKey = $niveau?->id ?? 0;
            $nivNom = $niveau?->nom ?? 'Niveau Standard';
            $nivOrdre = (int) ($niveau?->ordre ?? 999);

            $aVerser = 0.0;
            $reduction = 0.0;
            $verse = 0.0;
            $reste = 0.0;
            $effectif = 0;

            foreach ($classe->inscriptions as $insc) {
                $compte = $insc->compteFinancier;
                if (! $compte) {
                    continue;
                }
                $effectif++;
                $aVerser += (float) $compte->debit_initial;
                $red = (float) ($compte->total_dispense ?? 0) + (float) ($compte->remise_bourse ?? 0);
                $reduction += $red;
                $verse += (float) $compte->total_paye;
                $reste += (float) $compte->solde_restant;
            }

            $netExigible = max(0.0, $aVerser - $reduction);
            $tauxRecouvrement = $netExigible > 0 ? round(($verse / $netExigible) * 100, 2) : 0.0;

            if (! isset($structure[$secKey])) {
                $structure[$secKey] = [
                    'id' => $secKey,
                    'nom' => $secNom,
                    'a_verser' => 0.0,
                    'reduction' => 0.0,
                    'verse' => 0.0,
                    'reste' => 0.0,
                    'effectif' => 0,
                    'niveaux' => [],
                ];
            }

            if (! isset($structure[$secKey]['niveaux'][$nivKey])) {
                $structure[$secKey]['niveaux'][$nivKey] = [
                    'id' => $nivKey,
                    'nom' => $nivNom,
                    'ordre' => $nivOrdre,
                    'a_verser' => 0.0,
                    'reduction' => 0.0,
                    'verse' => 0.0,
                    'reste' => 0.0,
                    'effectif' => 0,
                    'classes' => [],
                ];
            }

            $structure[$secKey]['a_verser'] += $aVerser;
            $structure[$secKey]['reduction'] += $reduction;
            $structure[$secKey]['verse'] += $verse;
            $structure[$secKey]['reste'] += $reste;
            $structure[$secKey]['effectif'] += $effectif;

            $structure[$secKey]['niveaux'][$nivKey]['a_verser'] += $aVerser;
            $structure[$secKey]['niveaux'][$nivKey]['reduction'] += $reduction;
            $structure[$secKey]['niveaux'][$nivKey]['verse'] += $verse;
            $structure[$secKey]['niveaux'][$nivKey]['reste'] += $reste;
            $structure[$secKey]['niveaux'][$nivKey]['effectif'] += $effectif;

            $structure[$secKey]['niveaux'][$nivKey]['classes'][] = [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'a_verser' => $aVerser,
                'reduction' => $reduction,
                'verse' => $verse,
                'reste' => $reste,
                'taux_recouvrement' => $tauxRecouvrement,
                'effectif' => $effectif,
            ];

            $grandTotalAVerser += $aVerser;
            $grandTotalReduction += $reduction;
            $grandTotalVerse += $verse;
            $grandTotalReste += $reste;
            $grandTotalEffectif += $effectif;
        }

        foreach ($structure as &$sec) {
            $secNet = max(0.0, $sec['a_verser'] - $sec['reduction']);
            $sec['taux_recouvrement'] = $secNet > 0 ? round(($sec['verse'] / $secNet) * 100, 2) : 0.0;

            foreach ($sec['niveaux'] as &$niv) {
                $nivNet = max(0.0, $niv['a_verser'] - $niv['reduction']);
                $niv['taux_recouvrement'] = $nivNet > 0 ? round(($niv['verse'] / $nivNet) * 100, 2) : 0.0;
                usort($niv['classes'], fn ($a, $b) => strcmp($a['nom'], $b['nom']));
            }
            unset($niv);
            usort($sec['niveaux'], fn ($a, $b) => $a['ordre'] <=> $b['ordre']);
        }
        unset($sec);

        $grandNet = max(0.0, $grandTotalAVerser - $grandTotalReduction);
        $grandTaux = $grandNet > 0 ? round(($grandTotalVerse / $grandNet) * 100, 2) : 0.0;

        return [
            'perimetre_label' => $perimetre['perimetre_label'],
            'sections' => array_values($structure),
            'total_a_verser' => $grandTotalAVerser,
            'total_reduction' => $grandTotalReduction,
            'total_verse' => $grandTotalVerse,
            'total_verse_lettres' => $this->convertirMontantEnLettres($grandTotalVerse),
            'total_reste' => $grandTotalReste,
            'total_effectif' => $grandTotalEffectif,
            'taux_recouvrement_global' => $grandTaux,
            'taux_global' => $grandTaux,
        ];
    }

    /**
     * Génère les données du Bilan Détaillé des Versements (nominatif pour tous les élèves).
     *
     * Conforme au modèle officiel « Bilan détaillé des Versements » du concurrent.
     */
    public function obtenirBilanDetailleVersements(
        ?int $etablissementId = null,
        int $anneeId = 0,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): array {
        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $query = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe.niveau.cycle.section',
        ]);

        if ($anneeId > 0) {
            $query->where('annee_scolaire_id', $anneeId);
        }

        if ($etablissementId) {
            $query->where('etablissement_id', $etablissementId);
        }

        $this->appliquerFiltreHierarchieInscription($query, $sectionId, $cycleId, $niveauId, $classeId);

        $comptes = $query->get();

        $eleves = [];
        $totalExigible = 0.0;
        $totalReduction = 0.0;
        $totalVerse = 0.0;
        $totalReste = 0.0;

        foreach ($comptes as $compte) {
            $e = $compte->eleve;
            $cl = $compte->inscription?->classe?->nom ?? 'N/A';
            $aVerser = (float) $compte->debit_initial;
            $red = (float) ($compte->total_dispense ?? 0) + (float) ($compte->remise_bourse ?? 0);
            $verse = (float) $compte->total_paye;
            $reste = (float) $compte->solde_restant;

            $totalExigible += $aVerser;
            $totalReduction += $red;
            $totalVerse += $verse;
            $totalReste += $reste;

            $eleves[] = [
                'matricule' => $e?->matricule ?? 'N/A',
                'nom_complet' => $e ? ($e->nom.' '.($e->prenom ?? '')) : 'N/A',
                'sexe' => $e?->sexe ?? '-',
                'classe' => $cl,
                'a_verser' => $aVerser,
                'reduction' => $red,
                'verse' => $verse,
                'reste' => $reste,
            ];
        }

        usort($eleves, function ($a, $b) {
            $cmp = strcmp($a['classe'], $b['classe']);

            return $cmp !== 0 ? $cmp : strcmp($a['nom_complet'], $b['nom_complet']);
        });

        $groupesClasses = [];
        foreach ($eleves as $el) {
            $clNom = $el['classe'];
            if (! isset($groupesClasses[$clNom])) {
                $groupesClasses[$clNom] = [
                    'classe' => $clNom,
                    'eleves' => [],
                    'total_verse' => 0.0,
                    'total_reste' => 0.0,
                    'total_a_verser' => 0.0,
                ];
            }
            $groupesClasses[$clNom]['eleves'][] = $el;
            $groupesClasses[$clNom]['total_verse'] += $el['verse'];
            $groupesClasses[$clNom]['total_reste'] += $el['reste'];
            $groupesClasses[$clNom]['total_a_verser'] += $el['a_verser'];
        }
        ksort($groupesClasses);

        return [
            'perimetre_label' => $perimetre['perimetre_label'],
            'eleves' => $eleves,
            'classes' => array_values($groupesClasses),
            'total_exigible' => $totalExigible,
            'total_a_verser' => $totalExigible,
            'total_reduction' => $totalReduction,
            'total_verse' => $totalVerse,
            'total_verse_lettres' => $this->convertirMontantEnLettres($totalVerse),
            'total_reste' => $totalReste,
            'nombre_eleves' => count($eleves),
            'total_eleves' => count($eleves),
        ];
    }

    /**
     * Génère les données du Bilan des Réductions, Bourses & Bonus d'anticipation.
     *
     * Conforme au modèle officiel « Bilan des réductions » du concurrent.
     */
    public function obtenirBilanReductionsBourses(
        ?int $etablissementId = null,
        int $anneeId = 0,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): array {
        $perimetre = $this->construireContextePerimetre($etablissementId, $sectionId, $cycleId, $niveauId, $classeId);

        $queryBourses = EleveBourseRemise::with([
            'compteFinancier.eleve',
            'compteFinancier.inscription.classe.niveau.cycle.section',
            'bourseRemise',
            'fraisScolaire.typeFrais',
        ]);

        if ($anneeId > 0) {
            $queryBourses->whereHas('compteFinancier', fn ($q) => $q->where('annee_scolaire_id', $anneeId));
        }
        if ($etablissementId) {
            $queryBourses->whereHas('compteFinancier', fn ($q) => $q->where('etablissement_id', $etablissementId));
        }
        if ($classeId || $niveauId || $cycleId || $sectionId) {
            $queryBourses->whereHas('compteFinancier', function ($q) use ($classeId, $niveauId, $cycleId, $sectionId) {
                $this->appliquerFiltreHierarchieInscription($q, $sectionId, $cycleId, $niveauId, $classeId);
            });
        }

        $bourses = $queryBourses->get();

        $lignes = [];
        $totalReduction = 0.0;
        $sommeAttributionsParCompte = [];

        foreach ($bourses as $b) {
            $compte = $b->compteFinancier;
            $eleve = $compte?->eleve;
            $classe = $compte?->inscription?->classe?->nom ?? 'N/A';
            $fraisCible = $b->fraisScolaire?->libelle_tranche
                ?: ($b->fraisScolaire?->typeFrais?->libelle ?? 'Scolarité (Dernière tranche)');
            $motif = $b->motif ?: ($b->bourseRemise?->nom ?? strtoupper($b->bourseRemise?->type ?? 'BONUS'));
            // Lecture du champ réel `montant_calcule` (anciennement `montant_deduit` inexistant qui renvoyait 0).
            $montant = (float) ($b->montant_calcule ?? 0);

            if ($compte) {
                $sommeAttributionsParCompte[$compte->id] = ($sommeAttributionsParCompte[$compte->id] ?? 0.0) + $montant;
            }

            $totalReduction += $montant;

            $lignes[] = [
                'matricule' => $eleve?->matricule ?? 'N/A',
                'nom_complet' => $eleve ? ($eleve->nom.' '.($eleve->prenom ?? '')) : 'N/A',
                'classe' => $classe,
                'type' => $b->bourseRemise?->type ?? 'remise',
                'frais_concerne' => $fraisCible,
                'montant' => $montant,
                'motif' => $motif,
            ];
        }

        // Complément pour les réductions saisies manuellement sur `remise_bourse` (sans ligne `EleveBourseRemise` dédiée).
        $queryComptesRemise = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe.niveau.cycle.section',
        ])->where('remise_bourse', '>', 0);

        if ($anneeId > 0) {
            $queryComptesRemise->where('annee_scolaire_id', $anneeId);
        }
        if ($etablissementId) {
            $queryComptesRemise->where('etablissement_id', $etablissementId);
        }
        if ($classeId || $niveauId || $cycleId || $sectionId) {
            $this->appliquerFiltreHierarchieInscription($queryComptesRemise, $sectionId, $cycleId, $niveauId, $classeId);
        }

        foreach ($queryComptesRemise->get() as $compteRemise) {
            $dejaVentile = $sommeAttributionsParCompte[$compteRemise->id] ?? 0.0;
            $ecartManuel = round((float) $compteRemise->remise_bourse - $dejaVentile, 2);

            if ($ecartManuel > 0.01) {
                $eleve = $compteRemise->eleve;
                $totalReduction += $ecartManuel;

                $lignes[] = [
                    'matricule' => $eleve?->matricule ?? 'N/A',
                    'nom_complet' => $eleve ? ($eleve->nom.' '.($eleve->prenom ?? '')) : 'N/A',
                    'classe' => $compteRemise->inscription?->classe?->nom ?? 'N/A',
                    'type' => 'remise',
                    'frais_concerne' => 'Ajustement manuel compte',
                    'montant' => $ecartManuel,
                    'motif' => $compteRemise->observations ?: 'Réduction directe sur dossier',
                ];
            }
        }

        usort($lignes, function ($a, $b) {
            $cmp = strcmp($a['classe'], $b['classe']);

            return $cmp !== 0 ? $cmp : strcmp($a['nom_complet'], $b['nom_complet']);
        });

        $formatter = class_exists(\NumberFormatter::class) ? new \NumberFormatter('fr', \NumberFormatter::SPELLOUT) : null;
        $totalEnLettres = $formatter ? mb_strtoupper($formatter->format((int) $totalReduction)).' FRANCS CFA' : '';

        return [
            'perimetre_label' => $perimetre['perimetre_label'],
            'reductions' => $lignes,
            'beneficiaires' => $lignes,
            'total_reduction' => $totalReduction,
            'total_montant' => $totalReduction,
            'total_reduction_lettres' => $totalEnLettres,
            'total_lettres' => $totalEnLettres,
            'nombre_beneficiaires' => count($lignes),
            'total_beneficiaires' => count($lignes),
        ];
    }

    /**
     * Génère les données du Relevé Individuel des Versements de l'Élève.
     *
     * Conforme au modèle officiel « Liste des versements effectués par l'élève » du concurrent.
     */
    public function obtenirReleveIndividuelEleve(int $eleveId, int $anneeId): array
    {
        $eleve = Eleve::with(['inscriptions.classe.niveau'])->findOrFail($eleveId);
        $compte = CompteFinancierEleve::with([
            'versements' => fn ($query) => $query
                ->where('statut', 'valide')
                ->orderBy('date_versement', 'asc')
                ->with([
                    'ventilations.fraisScolaire.typeFrais',
                    'ventilations.typeFrais',
                    'caissier',
                ]),
            'boursesRemises.bourseRemise',
            'etablissement.institution',
            'inscription.classe.niveau.cycle.section',
        ])
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeId)
            ->first();

        $versements = $compte?->versements ?? collect();

        $lignes = [];
        $totalVerse = 0.0;

        foreach ($versements as $v) {
            $m = (float) $v->montant_total;
            $totalVerse += $m;

            // Résout le libellé de chaque ligne ventilée : libellé saisi -> libellé de tranche -> libellé du type de frais
            $rubriques = $v->ventilations
                ->map(function (VentilationVersement $ven): string {
                    return $ven->libelle
                        ?: ($ven->fraisScolaire?->libelle_tranche
                            ?: ($ven->typeFrais?->libelle
                                ?: ($ven->fraisScolaire?->typeFrais?->libelle ?? 'Frais')));
                })
                ->filter()
                ->unique()
                ->implode(', ');

            $lignes[] = [
                'id' => $v->id,
                'date' => Carbon::parse($v->date_versement)->format('d-m-Y H:i'),
                'reference_recu' => $v->reference_recu,
                'rubriques' => $rubriques ?: 'Versement scolarité',
                'caissier' => $v->caissier?->nom ?? 'Économe',
                'mode_paiement' => $v->mode_paiement,
                'montant' => $m,
            ];
        }

        $aVerser = (float) ($compte?->debit_initial ?? 0);
        // Cumule les dispenses financières et les bourses/remises accordées sur le dossier
        $reduction = (float) ($compte?->total_dispense ?? 0) + (float) ($compte?->remise_bourse ?? 0);
        $netExigible = (float) ($compte?->montant_net_exigible ?? max(0, $aVerser - $reduction));
        $soldeRestant = (float) ($compte?->solde_restant ?? max(0, $netExigible - $totalVerse));

        return [
            'eleve' => $eleve,
            'classe' => $compte?->inscription?->classe?->nom ?? $eleve->inscriptions->first()?->classe?->nom ?? 'N/A',
            'compte' => $compte,
            'etablissement' => $compte?->etablissement,
            'versements' => $lignes,
            'total_verse' => $totalVerse,
            'total_verse_lettres' => $this->convertirMontantEnLettres($totalVerse),
            'a_verser' => $aVerser,
            'reduction' => $reduction,
            'net_exigible' => $netExigible,
            'solde_restant' => $soldeRestant,
        ];
    }

    /**
     * Convertit un montant monétaire en toutes lettres (format officiel français/Cameroun).
     *
     * @param  float  $montant  Montant en FCFA
     * @return string Montant exprimé en toutes lettres
     */
    public function convertirMontantEnLettres(float $montant): string
    {
        $arrondi = (int) round($montant);
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::SPELLOUT);
            $texte = $formatter->format($arrondi);
            if ($texte) {
                return mb_strtoupper($texte).' FRANCS CFA';
            }
        }

        return number_format($montant, 0, ',', ' ').' FRANCS CFA';
    }
}

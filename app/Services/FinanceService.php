<?php

namespace App\Services;

use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\Classe;
use App\Models\ClotureCaisse;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\DispenseFinanciere;
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
            ->whereHas('typeFrais', fn ($q) => $q->where('obligatoire', true))
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

        $inscriptions = Inscription::withoutGlobalScopes()
            ->with(['classe.niveau', 'compteFinancier'])
            ->where('annee_scolaire_id', $anneeId)
            ->where(function ($q) use ($etablissementId) {
                $q->where('etablissement_id', $etablissementId)
                    ->orWhereHas('classe', fn ($cq) => $cq->where('etablissement_id', $etablissementId));
            })
            ->whereNotIn('statut', ['annulee', 'radie'])
            ->get();

        // Cache local des montants obligatoires par couple (niveau_id, serie) pour éviter les requêtes N+1
        $debitParNiveauSerie = [];

        foreach ($inscriptions as $inscription) {
            $compte = $inscription->compteFinancier;

            if (! $compte) {
                $this->initialiserCompteEleve($inscription);

                continue;
            }

            // Si le compte avait été créé avec un débit nul avant le paramétrage de la grille tarifaire, on l'actualise
            if ((float) $compte->debit_initial <= 0) {
                $nivId = $inscription->classe?->niveau_id;
                $ser = $inscription->classe?->serie ? mb_strtoupper(trim($inscription->classe->serie)) : '';
                $cleCache = ($nivId ?? 0).'_'.$ser;

                if (! array_key_exists($cleCache, $debitParNiveauSerie)) {
                    $tarifs = $this->obtenirTarifsObligatoiresApplicables(
                        $anneeId,
                        $etablissementId,
                        $nivId,
                        $inscription->classe?->serie
                    );
                    $debitParNiveauSerie[$cleCache] = (float) $tarifs->sum('montant');
                }

                $nouveauDebit = $debitParNiveauSerie[$cleCache];
                if ($nouveauDebit > 0) {
                    $compte->debit_initial = $nouveauDebit;
                    $compte->actualiserSolde();
                }
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
    public function enregistrerVersement(array $data, ?Personnel $caissier, ?User $user): VersementCaisse
    {
        $compte = CompteFinancierEleve::with('inscription')->findOrFail($data['compte_financier_id']);
        $etablissementId = $compte->etablissement_id;
        $today = Carbon::today()->toDateString();

        // 1. Vérification anti-fraude : la journée ne doit pas être déjà clôturée pour ce caissier
        $clotureExistante = ClotureCaisse::where('etablissement_id', $etablissementId)
            ->whereDate('date_cloture', $today)
            ->where('personnel_id', $caissier?->id)
            ->where('statut', 'cloturee')
            ->first();

        if ($clotureExistante) {
            throw new InvalidArgumentException("Impossible d'encaisser : la caisse de cette journée a déjà été clôturée.");
        }

        // 2. Vérification de la concordance de la ventilation
        $sommeVentilation = array_reduce($data['ventilations'], fn ($carry, $item) => $carry + (float) $item['montant'], 0.0);
        if (abs($sommeVentilation - (float) $data['montant_total']) > 0.01) {
            throw new InvalidArgumentException('Le montant total ('.number_format((float) $data['montant_total'], 0).' FCFA) ne correspond pas à la somme des ventilations ('.number_format($sommeVentilation, 0).' FCFA).');
        }

        return DB::transaction(function () use ($data, $compte, $etablissementId, $caissier, $user) {
            // Résolution transparente de la caisse cible (caisse spécifiée ou Caisse Principale auto-créée)
            $caisseId = ! empty($data['caisse_id'])
                ? (int) $data['caisse_id']
                : $this->obtenirOuCreerCaissePrincipale($etablissementId)->id;

            // Génération du numéro officiel séquentiel de reçu
            $referenceRecu = $this->genererReferenceRecu($etablissementId);

            // Création du versement
            $versement = VersementCaisse::create([
                'reference_recu' => $referenceRecu,
                'compte_financier_id' => $compte->id,
                'inscription_id' => $compte->inscription_id,
                'etablissement_id' => $etablissementId,
                'caisse_id' => $caisseId,
                'personnel_id' => $caissier?->id,
                'date_versement' => Carbon::now(),
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

            return ReglementDivers::create([
                'etablissement_id' => $etablissementId,
                'caisse_id' => $caisse->id,
                'reference_recu' => $referenceRecu,
                'date_reglement' => Carbon::now(),
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
    public function attribuerBourseRemise(
        CompteFinancierEleve $compte,
        BourseRemise $bourseRemise,
        ?string $motif = null,
        ?User $user = null,
        ?string $cibleImputation = null,
        ?int $fraisScolaireId = null
    ): EleveBourseRemise {
        return DB::transaction(function () use ($compte, $bourseRemise, $motif, $user, $cibleImputation, $fraisScolaireId) {
            $baseApresDispense = max(0.0, (float) $compte->debit_initial - (float) ($compte->total_dispense ?? 0));
            $baseCalcul = $baseApresDispense;

            if ($fraisScolaireId) {
                $fraisCible = FraisScolaire::find($fraisScolaireId);
                if ($fraisCible) {
                    $dispenseSurFrais = (float) ($compte->dispensesFinancieres()
                        ->where('frais_scolaire_id', $fraisCible->id)
                        ->value('montant_dispense') ?? 0);
                    $baseCalcul = max(0.0, (float) $fraisCible->montant - $dispenseSurFrais);
                }
            }

            // Calcul du montant déductible
            if ($bourseRemise->mode_calcul === 'pourcentage') {
                $montant = round(($baseCalcul * (float) $bourseRemise->valeur) / 100);
            } else {
                $montant = min($baseApresDispense, (float) $bourseRemise->valeur);
            }

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

        if (! $frais->typeFrais?->obligatoire) {
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

        if ($frais->typeFrais?->obligatoire) {
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
     */
    public function annulerSouscriptionOptionnelle(SouscriptionFraisOptionnel $souscription): bool
    {
        $souscription->update(['statut' => 'annule']);
        $compte = $souscription->compteFinancier;
        if ($compte) {
            $compte->actualiserSolde();
        }

        return true;
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
    private function construireLignesEcheancier(CompteFinancierEleve $compte, ?Carbon $dateRef = null): array
    {
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

        $fraisObligatoires = $this->obtenirTarifsObligatoiresApplicables(
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
                'recu_par' => $v->recu_par,
                'statut' => $v->statut,
                'motif_annulation' => $v->motif_annulation,
                'ventilations' => $v->ventilations->map(fn (VentilationVersement $vent) => [
                    'id' => $vent->id,
                    'frais_scolaire_id' => $vent->frais_scolaire_id,
                    'libelle' => $vent->libelle,
                    'montant' => (float) $vent->montant,
                ])->values()->all(),
            ])->all();

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
            'versements' => $versements,
            'total_exigible' => (float) $compte->montant_net_exigible,
            'total_dispense' => (float) ($compte->total_dispense ?? 0),
            'total_paye' => (float) $compte->total_paye,
            'solde_restant' => (float) $compte->solde_restant,
            'remise_bourse' => (float) $compte->remise_bourse,
            'arrieres_anterieurs' => (float) $compte->arrieres_anterieurs,
            'montant_echu_en_retard' => $this->calculerMontantEchuEnRetard($compte, Carbon::today()),
            'statut_financier' => $compte->statut_financier,
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
        int $etablissementId,
        ?int $sectionId = null,
        ?int $cycleId = null,
        ?int $niveauId = null,
        ?int $classeId = null
    ): array {
        $section = $sectionId
            ? Section::withoutGlobalScopes()->where('etablissement_id', $etablissementId)->find($sectionId)
            : null;

        $cycle = $cycleId
            ? Cycle::withoutGlobalScopes()
                ->whereHas('section', fn ($q) => $q->withoutGlobalScopes()->where('etablissement_id', $etablissementId))
                ->with('section')
                ->find($cycleId)
            : null;

        $niveau = $niveauId
            ? Niveau::withoutGlobalScopes()
                ->where('etablissement_id', $etablissementId)
                ->with('cycle.section')
                ->find($niveauId)
            : null;

        $classe = $classeId
            ? Classe::withoutGlobalScopes()
                ->where('etablissement_id', $etablissementId)
                ->with('niveau.cycle.section')
                ->find($classeId)
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

        return [
            'section' => $section,
            'cycle' => $cycle,
            'niveau' => $niveau,
            'classe' => $classe,
            'perimetre_label' => ! empty($segments)
                ? implode(' • ', $segments)
                : 'Toutes les sections, cycles, niveaux et classes',
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
        int $etablissementId,
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

        $query = VersementCaisse::with([
            'compteFinancier.eleve',
            'inscription.classe.niveau.cycle.section',
            'caisse',
            'caissier',
            'ventilations',
        ])
            ->where('etablissement_id', $etablissementId)
            ->where('statut', 'valide')
            ->whereBetween('date_versement', [$debut, $fin])
            ->orderBy('date_versement', 'asc');

        if ($caisseId) {
            $query->where('caisse_id', $caisseId);
        }

        if ($modePaiement) {
            $query->where('mode_paiement', $modePaiement);
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
        int $etablissementId,
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
            ->where('etablissement_id', $etablissementId)
            ->where('statut', 'valide')
            ->whereBetween('date_versement', [$debut, $fin])
            ->orderBy('date_versement', 'asc');

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
        int $etablissementId,
        int $anneeId,
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
        ])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId);

        $this->appliquerFiltreHierarchieInscription($query, $sectionId, $cycleId, $niveauId, $classeId);

        $comptes = $query->get();
        $insolvables = [];
        $totalDetteEchue = 0.0;
        $totalSoldeRestant = 0.0;
        $totalVersePeriode = 0.0;

        foreach ($comptes as $compte) {
            $detteEchue = $compte->montantEchuEnRetard($reference);
            if ($detteEchue > 0 || ($compte->solde_restant > 0 && $compte->total_paye == 0 && ! $dateFin)) {
                $retard = $detteEchue > 0 ? $detteEchue : (float) $compte->montant_net_exigible;

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
        int $etablissementId,
        int $anneeId,
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
        ])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId);

        $this->appliquerFiltreHierarchieInscription($query, $sectionId, $cycleId, $niveauId, $classeId);

        $comptes = $query->get();
        $enRegle = [];
        $totalPaye = 0.0;
        $totalVersePeriode = 0.0;

        foreach ($comptes as $compte) {
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
}

<?php

namespace App\Services;

use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\ClotureCaisse;
use App\Models\CompteFinancierEleve;
use App\Models\EcheanceTranche;
use App\Models\EleveBourseRemise;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\ModeleEcheance;
use App\Models\PenaliteConfiguration;
use App\Models\PenaliteEleve;
use App\Models\Personnel;
use App\Models\ReglementDivers;
use App\Models\SouscriptionFraisOptionnel;
use App\Models\User;
use App\Models\VentilationVersement;
use App\Models\VersementCaisse;
use Carbon\Carbon;
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
     * Initialise ou recalcule le compte financier d'un élève lors de son inscription.
     *
     * Applique la matrice tarifaire selon l'algorithme d'héritage (Niveau + Série spécifique,
     * ou Niveau + Toutes les séries par défaut).
     *
     * @param Inscription $inscription Inscription validée ou en cours
     * @param float $remiseBourse Remise ou bourse éventuelle accordée
     * @param float $arrieresAnterieurs Dette ou reliquat d'une année précédente reporté manuellement
     * @return CompteFinancierEleve
     */
    public function initialiserCompteEleve(
        Inscription $inscription,
        float $remiseBourse = 0,
        float $arrieresAnterieurs = 0
    ): CompteFinancierEleve {
        $classe = $inscription->classe()->with('niveau.cycle.section.etablissement')->first();
        $niveauId = $classe?->niveau_id;
        $serie = $classe?->serie;
        $etablissementId = $classe?->niveau?->cycle?->section?->etablissement_id
            ?? \App\Models\Etablissement::first()?->id;
        $anneeId = $inscription->annee_scolaire_id;

        // 1. Recherche des tarifs obligatoires applicables selon la règle de priorité Série > Défaut
        $tarifsSpecifiques = FraisScolaire::where('annee_scolaire_id', $anneeId)
            ->where('etablissement_id', $etablissementId)
            ->where('niveau_id', $niveauId)
            ->where('serie', $serie)
            ->whereHas('typeFrais', fn ($q) => $q->where('obligatoire', true))
            ->get();

        if ($tarifsSpecifiques->isNotEmpty()) {
            $tarifsApplicables = $tarifsSpecifiques;
        } else {
            // Repli sur les frais configurés pour "Toutes séries" (serie = null)
            $tarifsApplicables = FraisScolaire::where('annee_scolaire_id', $anneeId)
                ->where('etablissement_id', $etablissementId)
                ->where('niveau_id', $niveauId)
                ->whereNull('serie')
                ->whereHas('typeFrais', fn ($q) => $q->where('obligatoire', true))
                ->get();
        }

        $debitInitial = (float) $tarifsApplicables->sum('montant');

        // 2. Recherche ou création du compte financier
        $compte = CompteFinancierEleve::firstOrNew([
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
     * @param int $etablissementId Identifiant de l'établissement
     * @return string Numéro officiel de reçu
     */
    public function genererReferenceRecu(int $etablissementId): string
    {
        $annee = Carbon::now()->year;
        $etab = \App\Models\Etablissement::find($etablissementId);
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

        return $prefix . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
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
     * @param Personnel|null $caissier Personnel ayant encaissé
     * @param User|null $user Utilisateur connecté
     * @return VersementCaisse
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
            throw new InvalidArgumentException("Le montant total (" . number_format((float) $data['montant_total'], 0) . " FCFA) ne correspond pas à la somme des ventilations (" . number_format($sommeVentilation, 0) . " FCFA).");
        }

        return DB::transaction(function () use ($data, $compte, $etablissementId, $caissier, $user) {
            // Résolution de la caisse cible (caisse spécifiée ou caisse principale par défaut)
            $caisseId = $data['caisse_id'] ?? Caisse::where('etablissement_id', $etablissementId)
                ->where('is_principale', true)
                ->value('id');

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
                'recu_par' => $caissier ? ($caissier->nom . ' ' . $caissier->prenom) : ($user?->name ?? 'Service Intendance'),
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
     * @param VersementCaisse $versement Versement à annuler
     * @param string $motif Justification de l'annulation
     * @param User $annulateur Administrateur ayant validé l'annulation
     * @return VersementCaisse
     *
     * @throws InvalidArgumentException
     */
    public function annulerVersement(VersementCaisse $versement, string $motif, User $annulateur): VersementCaisse
    {
        if ($versement->statut === 'annule') {
            throw new InvalidArgumentException("Ce versement a déjà été annulé.");
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
     * Clôture la caisse d'une journée donnée, fige les écritures et génère le journal récapitulatif.
     *
     * @param int $etablissementId Identifiant établissement
     * @param int|null $personnelId Caissier / économe concerné
     * @param string $date Date au format Y-m-d
     * @param string|null $observations Remarques éventuelles
     * @param User $user Utilisateur effectuant l'action
     * @return ClotureCaisse
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
     * @param Inscription $inscription
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
            $nomEleve = trim($inscription->eleve->nom . ' ' . ($inscription->eleve->prenom ?? ''));

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
     * @param int $etablissementId Identifiant de l'établissement
     * @return string Référence unique
     */
    public function genererReferenceReglementDivers(int $etablissementId): string
    {
        $annee = Carbon::now()->year;
        $etab = \App\Models\Etablissement::find($etablissementId);
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

        return $prefix . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Enregistre un encaissement libre hors scolarité (Aurore §4.2).
     *
     * @param array{
     *     caisse_id: int,
     *     payeur_nom: string,
     *     payeur_telephone?: string|null,
     *     categorie: string,
     *     libelle: string,
     *     montant: float,
     *     mode_paiement: string,
     *     reference_paiement?: string|null,
     *     observations?: string|null
     * } $data Données de la recette
     * @param Personnel|null $caissier Personnel ayant encaissé
     * @param User|null $user Utilisateur connecté
     * @return ReglementDivers
     */
    public function enregistrerReglementDivers(array $data, ?Personnel $caissier, ?User $user): ReglementDivers
    {
        $caisse = Caisse::findOrFail($data['caisse_id']);
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
     * @param ReglementDivers $reglement
     * @param string $motif Justificatif obligatoire
     * @param User $annulateur Administrateur ayant validé l'annulation
     * @return ReglementDivers
     */
    public function annulerReglementDivers(ReglementDivers $reglement, string $motif, User $annulateur): ReglementDivers
    {
        if ($reglement->statut === 'annule') {
            throw new InvalidArgumentException("Ce règlement a déjà été annulé.");
        }

        $reglement->update([
            'statut' => 'annule',
            'motif_annulation' => $motif,
        ]);

        return $reglement;
    }

    /**
     * Attribue une bourse d'études ou une remise paramétrée au compte scolaire d'un élève (Aurore §4.1.6).
     *
     * @param CompteFinancierEleve $compte Compte financier élève
     * @param BourseRemise $bourseRemise Modèle de bourse/remise à appliquer
     * @param string|null $motif Justificatif éventuel
     * @param User|null $user Utilisateur validateur
     * @return EleveBourseRemise
     */
    public function attribuerBourseRemise(
        CompteFinancierEleve $compte,
        BourseRemise $bourseRemise,
        ?string $motif = null,
        ?User $user = null
    ): EleveBourseRemise {
        return DB::transaction(function () use ($compte, $bourseRemise, $motif, $user) {
            // Calcul du montant déductible
            if ($bourseRemise->mode_calcul === 'pourcentage') {
                $montant = round(((float) $compte->debit_initial * (float) $bourseRemise->valeur) / 100);
            } else {
                $montant = min((float) $compte->debit_initial, (float) $bourseRemise->valeur);
            }

            // Enregistrement de l'attribution
            $attribution = EleveBourseRemise::updateOrCreate(
                [
                    'compte_financier_id' => $compte->id,
                    'bourse_remise_id' => $bourseRemise->id,
                ],
                [
                    'montant_calcule' => $montant,
                    'date_attribution' => Carbon::today(),
                    'motif' => $motif,
                    'attribue_par' => $user?->id,
                ]
            );

            // Actualisation automatique du solde restant et du statut financier
            $compte->actualiserSolde();

            return $attribution;
        });
    }

    /**
     * Retire une bourse ou une remise accordée à un compte élève.
     *
     * @param EleveBourseRemise $attribution
     * @return void
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
     * @param CompteFinancierEleve $compte Compte financier élève
     * @param PenaliteConfiguration|null $config Configuration éventuelle
     * @param string $libelle Intitulé explicite
     * @param float $montant Montant de la pénalité
     * @param string|null $motif Justificatif
     * @param User|null $user Utilisateur ayant appliqué la pénalité
     * @return PenaliteEleve
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
     *
     * @param PenaliteEleve $penalite
     * @return void
     */
    public function annulerPenalite(PenaliteEleve $penalite): void
    {
        DB::transaction(function () use ($penalite) {
            $penalite->update(['statut' => 'annule']);
            $penalite->compteFinancier->actualiserSolde();
        });
    }

    /**
     * Détecte les retards selon l'échéancier par défaut et applique les pénalités configurées.
     *
     * @param int $etablissementId Identifiant de l'établissement
     * @param int $anneeId Identifiant de l'année scolaire
     * @param User|null $user Utilisateur déclencheur
     * @return array{penalites_appliquees: int, total_montant: float}
     */
    public function appliquerPenalitesAutomatiquesEcheances(
        int $etablissementId,
        int $anneeId,
        ?User $user = null
    ): array {
        $echeancier = ModeleEcheance::with('tranches')
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->where('is_default', true)
            ->first();

        if (! $echeancier) {
            return ['penalites_appliquees' => 0, 'total_montant' => 0.0];
        }

        $configPenalite = PenaliteConfiguration::where('etablissement_id', $etablissementId)
            ->where('is_active', true)
            ->first();

        if (! $configPenalite) {
            return ['penalites_appliquees' => 0, 'total_montant' => 0.0];
        }

        $today = Carbon::today();
        $comptes = CompteFinancierEleve::where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId)
            ->where('solde_restant', '>', 0)
            ->get();

        $nbAppliquees = 0;
        $totalMontant = 0.0;

        foreach ($echeancier->tranches as $tranche) {
            $dateExigibilite = Carbon::parse($tranche->date_limite)->addDays($configPenalite->jours_grace);

            if ($today->greaterThan($dateExigibilite)) {
                foreach ($comptes as $compte) {
                    // Calcul du montant exigible à cette tranche selon le pourcentage
                    $montantTheoriqueExigible = ((float) $compte->montant_net_exigible * (float) $tranche->pourcentage_exigible) / 100;

                    if ($compte->total_paye < $montantTheoriqueExigible) {
                        $libelle = "Pénalité de retard - {$tranche->libelle}";

                        // Vérifier si cette pénalité n'a pas déjà été appliquée pour cette tranche
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
                                "Échéance du " . $tranche->date_limite->format('d/m/Y') . " non respectée",
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

    /**
     * Enregistre une souscription à un frais optionnel (bus, cantine, internat) pour un élève.
     * Recalcule instantanément l'ardoise financière de l'élève.
     *
     * @param int $compteId Identifiant du compte financier élève
     * @param int $fraisScolaireId Identifiant du frais optionnel dans la grille tarifaire
     * @param float|null $montant Montant personnalisé éventuel (prend le montant du frais si null)
     * @param string|null $observations Observations éventuelles
     * @return SouscriptionFraisOptionnel
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
            throw new InvalidArgumentException("Ce frais est déjà obligatoire dans la grille tarifaire de la classe.");
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
     * @param SouscriptionFraisOptionnel $souscription
     * @return bool
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
     * Construit l'échéancier prévisionnel complet d'un élève (frais obligatoires + options actives).
     *
     * @param CompteFinancierEleve $compte
     * @return array<string, mixed>
     */
    public function obtenirEcheancierEleve(CompteFinancierEleve $compte): array
    {
        $compte->loadMissing(['inscription.classe.niveau', 'eleve', 'boursesRemises.bourseRemise', 'souscriptionsOptionnelles.fraisScolaire.typeFrais', 'versements.ventilations']);
        $classe = $compte->inscription?->classe;
        $niveauId = $classe?->niveau_id;
        $serie = $classe?->serie;
        $today = Carbon::today();

        // 1. Frais obligatoires du niveau
        $fraisObligatoires = FraisScolaire::with('typeFrais')
            ->where('annee_scolaire_id', $compte->annee_scolaire_id)
            ->where('etablissement_id', $compte->etablissement_id)
            ->where('niveau_id', $niveauId)
            ->where(function ($q) use ($serie) {
                if ($serie) {
                    $q->where('serie', $serie)->orWhereNull('serie');
                } else {
                    $q->whereNull('serie');
                }
            })
            ->whereHas('typeFrais', fn ($q) => $q->where('obligatoire', true))
            ->orderBy('tranche_numero')
            ->get();

        $lignes = [];
        $totalVerse = (float) $compte->total_paye;
        $reliquatPaiement = $totalVerse;

        foreach ($fraisObligatoires as $frais) {
            $estEchu = $frais->date_limite ? Carbon::parse($frais->date_limite)->lte($today) : false;
            $montantLigne = (float) $frais->montant;

            $payeSurLigne = min($reliquatPaiement, $montantLigne);
            $reliquatPaiement = max(0, $reliquatPaiement - $payeSurLigne);
            $resteSurLigne = max(0, $montantLigne - $payeSurLigne);

            $lignes[] = [
                'type' => 'obligatoire',
                'id' => $frais->id,
                'libelle' => $frais->libelle_tranche ?? ($frais->typeFrais?->libelle . ' - Tranche ' . $frais->tranche_numero),
                'categorie' => $frais->typeFrais?->libelle ?? 'Scolarité',
                'montant' => $montantLigne,
                'date_limite' => $frais->date_limite ? Carbon::parse($frais->date_limite)->format('d-m-Y') : null,
                'est_echu' => $estEchu,
                'montant_paye' => $payeSurLigne,
                'montant_restant' => $resteSurLigne,
                'statut' => $resteSurLigne == 0 ? 'solde' : ($estEchu ? 'en_retard' : 'a_choir'),
            ];
        }

        // 2. Frais optionnels souscrits
        foreach ($compte->souscriptionsOptionnelles as $souscription) {
            if ($souscription->statut !== 'actif') {
                continue;
            }
            $fraisOpt = $souscription->fraisScolaire;
            $estEchu = $fraisOpt?->date_limite ? Carbon::parse($fraisOpt->date_limite)->lte($today) : false;
            $montantLigne = (float) $souscription->montant;

            $payeSurLigne = min($reliquatPaiement, $montantLigne);
            $reliquatPaiement = max(0, $reliquatPaiement - $payeSurLigne);
            $resteSurLigne = max(0, $montantLigne - $payeSurLigne);

            $lignes[] = [
                'type' => 'optionnel',
                'souscription_id' => $souscription->id,
                'libelle' => ($fraisOpt?->typeFrais?->libelle ?? 'Option') . ($souscription->observations ? " ({$souscription->observations})" : ''),
                'categorie' => 'Option / Annexe',
                'montant' => $montantLigne,
                'date_limite' => $fraisOpt?->date_limite ? Carbon::parse($fraisOpt->date_limite)->format('d-m-Y') : null,
                'est_echu' => $estEchu,
                'montant_paye' => $payeSurLigne,
                'montant_restant' => $resteSurLigne,
                'statut' => $resteSurLigne == 0 ? 'solde' : ($estEchu ? 'en_retard' : 'a_choir'),
            ];
        }

        return [
            'compte' => $compte,
            'eleve' => $compte->eleve,
            'classe' => $classe,
            'lignes' => $lignes,
            'total_exigible' => (float) $compte->montant_net_exigible,
            'total_paye' => (float) $compte->total_paye,
            'solde_restant' => (float) $compte->solde_restant,
            'montant_echu_en_retard' => $compte->montantEchuEnRetard(),
            'statut_financier' => $compte->statut_financier,
        ];
    }

    /**
     * Récupère les données pour le Journal Détaillé des Encaissements.
     *
     * @param int $etablissementId
     * @param string|null $dateDebut
     * @param string|null $dateFin
     * @param int|null $caisseId
     * @param string|null $modePaiement
     * @return array<string, mixed>
     */
    public function obtenirJournalDetaille(
        int $etablissementId,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?int $caisseId = null,
        ?string $modePaiement = null
    ): array {
        $debut = $dateDebut ? Carbon::parse($dateDebut)->startOfDay() : Carbon::today()->startOfMonth();
        $fin = $dateFin ? Carbon::parse($dateFin)->endOfDay() : Carbon::today()->endOfDay();

        $query = VersementCaisse::with([
            'compteFinancier.eleve',
            'inscription.classe',
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
            'versements' => $versements,
            'total_general' => $totalGeneral,
            'totaux_par_mode' => $totauxParMode,
            'nombre_versements' => $versements->count(),
        ];
    }

    /**
     * Récupère les données pour le Journal Synthétique de Caisse (Totaux par jour, par caisse, par mode).
     *
     * @param int $etablissementId
     * @param string|null $dateDebut
     * @param string|null $dateFin
     * @param int|null $caisseId
     * @return array<string, mixed>
     */
    public function obtenirJournalSynthetique(
        int $etablissementId,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?int $caisseId = null
    ): array {
        $debut = $dateDebut ? Carbon::parse($dateDebut)->startOfDay() : Carbon::today()->startOfMonth();
        $fin = $dateFin ? Carbon::parse($dateFin)->endOfDay() : Carbon::today()->endOfDay();

        $versementsQuery = VersementCaisse::where('etablissement_id', $etablissementId)
            ->where('statut', 'valide')
            ->whereBetween('date_versement', [$debut, $fin]);

        if ($caisseId) {
            $versementsQuery->where('caisse_id', $caisseId);
        }

        $versements = $versementsQuery->get();

        // 1. Synthèse par Jour
        $parJour = [];
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
            if ($v->mode_paiement === 'especes') $parJour[$jour]['especes'] += $m;
            elseif ($v->mode_paiement === 'mobile_money_manuel') $parJour[$jour]['momo'] += $m;
            elseif ($v->mode_paiement === 'cheque') $parJour[$jour]['cheque'] += $m;
            elseif ($v->mode_paiement === 'virement') $parJour[$jour]['virement'] += $m;
        }

        // 2. Synthèse par Caisse
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

        // 3. Synthèse par Rubrique (Ventilations)
        $ventilations = VentilationVersement::whereIn('versement_id', $versements->pluck('id'))
            ->select('libelle', DB::raw('SUM(montant) as total_montant'), DB::raw('COUNT(*) as total_occurrences'))
            ->groupBy('libelle')
            ->orderByDesc('total_montant')
            ->get();

        return [
            'date_debut' => $debut->format('d-m-Y'),
            'date_fin' => $fin->format('d-m-Y'),
            'synthese_jours' => array_values($parJour),
            'synthese_caisses' => $parCaisse,
            'synthese_rubriques' => $ventilations,
            'total_general' => (float) $versements->sum('montant_total'),
            'nombre_total' => $versements->count(),
        ];
    }

    /**
     * Récupère la liste des élèves insolvables (dette échue > 0) par classe ou niveau.
     *
     * @param int $etablissementId
     * @param int $anneeId
     * @param int|null $classeId
     * @param int|null $niveauId
     * @param Carbon|null $dateRef
     * @return array<string, mixed>
     */
    public function obtenirListeInsolvables(
        int $etablissementId,
        int $anneeId,
        ?int $classeId = null,
        ?int $niveauId = null,
        ?Carbon $dateRef = null
    ): array {
        $reference = $dateRef ?? Carbon::today();

        $query = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe.niveau',
        ])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId);

        if ($classeId) {
            $query->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        } elseif ($niveauId) {
            $query->whereHas('inscription.classe', fn ($q) => $q->where('niveau_id', $niveauId));
        }

        $comptes = $query->get();
        $insolvables = [];
        $totalDetteEchue = 0.0;
        $totalSoldeRestant = 0.0;

        foreach ($comptes as $compte) {
            $detteEchue = $compte->montantEchuEnRetard($reference);
            if ($detteEchue > 0 || ($compte->solde_restant > 0 && $compte->total_paye == 0)) {
                $retard = $detteEchue > 0 ? $detteEchue : (float) $compte->montant_net_exigible;
                $insolvables[] = [
                    'compte_id' => $compte->id,
                    'matricule' => $compte->eleve?->matricule,
                    'nom_complet' => $compte->eleve?->nom . ' ' . ($compte->eleve?->prenom ?? ''),
                    'sexe' => $compte->eleve?->sexe,
                    'classe' => $compte->inscription?->classe?->nom ?? 'N/A',
                    'contact_parent' => $compte->eleve?->telephone_tuteur ?? $compte->eleve?->telephone ?? 'N/A',
                    'montant_net_exigible' => (float) $compte->montant_net_exigible,
                    'total_paye' => (float) $compte->total_paye,
                    'montant_echu_en_retard' => $retard,
                    'solde_restant' => (float) $compte->solde_restant,
                ];
                $totalDetteEchue += $retard;
                $totalSoldeRestant += (float) $compte->solde_restant;
            }
        }

        // Tri par dette échue décroissante
        usort($insolvables, fn ($a, $b) => $b['montant_echu_en_retard'] <=> $a['montant_echu_en_retard']);

        return [
            'date_reference' => $reference->format('d-m-Y'),
            'eleves' => $insolvables,
            'nombre_insolvables' => count($insolvables),
            'total_dette_echue' => $totalDetteEchue,
            'total_solde_restant' => $totalSoldeRestant,
        ];
    }

    /**
     * Récupère la liste des élèves en règle (aucune dette échue ou scolarité 100% soldée).
     *
     * @param int $etablissementId
     * @param int $anneeId
     * @param int|null $classeId
     * @param int|null $niveauId
     * @return array<string, mixed>
     */
    public function obtenirListeEnRegle(
        int $etablissementId,
        int $anneeId,
        ?int $classeId = null,
        ?int $niveauId = null
    ): array {
        $reference = Carbon::today();

        $query = CompteFinancierEleve::with([
            'eleve',
            'inscription.classe.niveau',
        ])
            ->where('etablissement_id', $etablissementId)
            ->where('annee_scolaire_id', $anneeId);

        if ($classeId) {
            $query->whereHas('inscription', fn ($q) => $q->where('classe_id', $classeId));
        } elseif ($niveauId) {
            $query->whereHas('inscription.classe', fn ($q) => $q->where('niveau_id', $niveauId));
        }

        $comptes = $query->get();
        $enRegle = [];
        $totalPaye = 0.0;

        foreach ($comptes as $compte) {
            $detteEchue = $compte->montantEchuEnRetard($reference);
            if ($detteEchue <= 0 && $compte->total_paye > 0) {
                $estSolde = $compte->solde_restant <= 0;
                $enRegle[] = [
                    'compte_id' => $compte->id,
                    'matricule' => $compte->eleve?->matricule,
                    'nom_complet' => $compte->eleve?->nom . ' ' . ($compte->eleve?->prenom ?? ''),
                    'sexe' => $compte->eleve?->sexe,
                    'classe' => $compte->inscription?->classe?->nom ?? 'N/A',
                    'montant_net_exigible' => (float) $compte->montant_net_exigible,
                    'total_paye' => (float) $compte->total_paye,
                    'solde_restant' => (float) $compte->solde_restant,
                    'statut' => $estSolde ? 'Soldé (100%)' : 'À jour de l\'échéance',
                ];
                $totalPaye += (float) $compte->total_paye;
            }
        }

        return [
            'date_reference' => $reference->format('d-m-Y'),
            'eleves' => $enRegle,
            'nombre_en_regle' => count($enRegle),
            'total_paye' => $totalPaye,
        ];
    }
}

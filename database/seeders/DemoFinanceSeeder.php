<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\TypeFrais;
use App\Models\User;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeder de démonstration pour le Module 11 : Gestion de la Caisse & Paiements (Gesco V1.1).
 *
 * Crée le référentiel des frais scolaires (Scolarité, APEE, Inscription, Cantine),
 * la grille tarifaire par tranches, initialise les comptes élèves et enregistre
 * plusieurs encaissements types (Espèces, Mobile Money, Virement).
 */
class DemoFinanceSeeder extends Seeder
{
    /**
     * Exécute le peuplement des données financières de démonstration.
     *
     * @param FinanceService $financeService
     * @return void
     */
    public function run(FinanceService $financeService): void
    {
        $etablissement = Etablissement::where('code', 'CSJB')->first() ?? Etablissement::first();
        $annee = AnneeScolaire::where('is_current', true)->first() ?? AnneeScolaire::latest()->first();

        if (! $etablissement || ! $annee) {
            return;
        }

        // 1. Activation du verrou de solvabilité sur l'établissement pour la démonstration
        $etablissement->update([
            'verrou_solvabilite_actif' => true,
            'bonus_paiement_anticipe_actif' => true,
            'montant_bonus_anticipe' => 5000,
            'date_limite_bonus' => '2026-09-30',
        ]);

        // 2. Types de frais
        $fraisData = [
            [
                'code' => 'INSCRIPTION',
                'libelle' => 'Droits d\'Inscription',
                'description' => 'Frais obligatoires d\'ouverture et de validation du dossier d\'inscription.',
                'obligatoire' => true,
                'periodicite' => 'annuel',
                'montant_defaut' => 15000,
            ],
            [
                'code' => 'SCOLARITE',
                'libelle' => 'Frais de Scolarité',
                'description' => 'Contribution aux enseignements et encadrement pédagogique.',
                'obligatoire' => true,
                'periodicite' => 'annuel',
                'montant_defaut' => 120000,
            ],
            [
                'code' => 'APEE',
                'libelle' => 'Contribution APEE',
                'description' => 'Association des Parents d\'Élèves et Enseignants.',
                'obligatoire' => true,
                'periodicite' => 'annuel',
                'montant_defaut' => 25000,
            ],
            [
                'code' => 'CANTINE',
                'libelle' => 'Demi-Pension & Cantine',
                'description' => 'Restauration scolaire pour les élèves souscripteurs.',
                'obligatoire' => false,
                'periodicite' => 'trimestriel',
                'montant_defaut' => 35000,
            ],
        ];

        $typesFraisMap = [];
        foreach ($fraisData as $item) {
            $tf = TypeFrais::firstOrCreate(
                ['etablissement_id' => $etablissement->id, 'code' => $item['code']],
                array_merge($item, ['etablissement_id' => $etablissement->id])
            );
            $typesFraisMap[$item['code']] = $tf;
        }

        // 3. Matrice Tarifaire (Pour tous les niveaux de l'établissement)
        $niveaux = Niveau::whereHas('cycle.section', fn ($q) => $q->where('etablissement_id', $etablissement->id))->get();

        foreach ($niveaux as $niveau) {
            // Tranche 1 : Droits d'inscription + APEE
            FraisScolaire::firstOrCreate([
                'annee_scolaire_id' => $annee->id,
                'etablissement_id' => $etablissement->id,
                'niveau_id' => $niveau->id,
                'serie' => null,
                'type_frais_id' => $typesFraisMap['INSCRIPTION']->id,
                'tranche_numero' => 1,
            ], [
                'libelle_tranche' => 'Tranche 1 - Inscription',
                'montant' => 15000,
                'date_limite' => '2026-09-30',
            ]);

            FraisScolaire::firstOrCreate([
                'annee_scolaire_id' => $annee->id,
                'etablissement_id' => $etablissement->id,
                'niveau_id' => $niveau->id,
                'serie' => null,
                'type_frais_id' => $typesFraisMap['APEE']->id,
                'tranche_numero' => 1,
            ], [
                'libelle_tranche' => 'Tranche 1 - Frais APEE',
                'montant' => 25000,
                'date_limite' => '2026-09-30',
            ]);

            // Tranche 2 : Scolarité Part 1
            FraisScolaire::firstOrCreate([
                'annee_scolaire_id' => $annee->id,
                'etablissement_id' => $etablissement->id,
                'niveau_id' => $niveau->id,
                'serie' => null,
                'type_frais_id' => $typesFraisMap['SCOLARITE']->id,
                'tranche_numero' => 2,
            ], [
                'libelle_tranche' => 'Tranche 2 - Scolarité (50%)',
                'montant' => 60000,
                'date_limite' => '2026-11-30',
            ]);

            // Tranche 3 : Scolarité Part 2 (Solde)
            FraisScolaire::firstOrCreate([
                'annee_scolaire_id' => $annee->id,
                'etablissement_id' => $etablissement->id,
                'niveau_id' => $niveau->id,
                'serie' => null,
                'type_frais_id' => $typesFraisMap['SCOLARITE']->id,
                'tranche_numero' => 3,
            ], [
                'libelle_tranche' => 'Tranche 3 - Solde Scolarité',
                'montant' => 60000,
                'date_limite' => '2027-01-31',
            ]);
        }

        // 4. Initialisation des comptes élèves pour les inscriptions de l'année
        $inscriptions = Inscription::with(['eleve', 'classe.niveau'])->where('annee_scolaire_id', $annee->id)->get();
        $comptes = [];

        foreach ($inscriptions as $inscription) {
            $compte = $financeService->initialiserCompteEleve($inscription);
            $comptes[] = $compte;
        }

        // 5. Enregistrement d'encaissements de démonstration
        $caissier = Personnel::where('etablissement_id', $etablissement->id)->first();
        $adminUser = User::where('email', 'admin@gmail.com')->first();

        if (count($comptes) >= 4) {
            // Élève 1 : Soldé (Totalité payée en espèces)
            $c1 = $comptes[0];
            $financeService->enregistrerVersement([
                'compte_financier_id' => $c1->id,
                'montant_total' => 160000,
                'mode_paiement' => 'especes',
                'reference_paiement' => null,
                'payeur_nom' => $c1->eleve->nom_parent ?? 'Parent Tene',
                'payeur_telephone' => '+237 699 11 22 33',
                'observations' => 'Règlement intégral scolarité + APEE en espèces au guichet.',
                'ventilations' => [
                    ['libelle' => 'Tranche 1 - Inscription', 'montant' => 15000, 'type_frais_id' => $typesFraisMap['INSCRIPTION']->id],
                    ['libelle' => 'Tranche 1 - Frais APEE', 'montant' => 25000, 'type_frais_id' => $typesFraisMap['APEE']->id],
                    ['libelle' => 'Tranche 2 - Scolarité (50%)', 'montant' => 60000, 'type_frais_id' => $typesFraisMap['SCOLARITE']->id],
                    ['libelle' => 'Tranche 3 - Solde Scolarité', 'montant' => 60000, 'type_frais_id' => $typesFraisMap['SCOLARITE']->id],
                ],
            ], $caissier, $adminUser);

            // Élève 2 : En règle Tranche 1 (Payé par Mobile Money)
            $c2 = $comptes[1];
            $financeService->enregistrerVersement([
                'compte_financier_id' => $c2->id,
                'montant_total' => 40000,
                'mode_paiement' => 'mobile_money_manuel',
                'reference_paiement' => 'OM-TXID-998241',
                'payeur_nom' => $c2->eleve->nom_parent ?? 'Parent Ebale',
                'payeur_telephone' => '+237 677 44 55 66',
                'observations' => 'Paiement Tranche 1 via Orange Money reçu.',
                'ventilations' => [
                    ['libelle' => 'Tranche 1 - Inscription', 'montant' => 15000, 'type_frais_id' => $typesFraisMap['INSCRIPTION']->id],
                    ['libelle' => 'Tranche 1 - Frais APEE', 'montant' => 25000, 'type_frais_id' => $typesFraisMap['APEE']->id],
                ],
            ], $caissier, $adminUser);

            // Élève 3 : En règle Tranche 2 (Payé par Virement Bancaire avec bourse partielle de 20 000 FCFA)
            $c3 = $comptes[2];
            $c3->remise_bourse = 20000;
            $c3->observations = 'Bourse d\'encouragement d\'excellence académique.';
            $c3->actualiserSolde();

            $financeService->enregistrerVersement([
                'compte_financier_id' => $c3->id,
                'montant_total' => 100000,
                'mode_paiement' => 'virement',
                'reference_paiement' => 'VIR-AFRILAND-2026-0871',
                'payeur_nom' => $c3->eleve->nom_parent ?? 'Parent Manga',
                'payeur_telephone' => '+237 690 77 88 99',
                'observations' => 'Virement bancaire Tranches 1 & 2 après déduction bourse.',
                'ventilations' => [
                    ['libelle' => 'Tranche 1 - Inscription & APEE', 'montant' => 40000, 'type_frais_id' => $typesFraisMap['INSCRIPTION']->id],
                    ['libelle' => 'Tranche 2 - Scolarité (50%)', 'montant' => 60000, 'type_frais_id' => $typesFraisMap['SCOLARITE']->id],
                ],
            ], $caissier, $adminUser);

            // Élève 4 : Non payé (Insolvable avec solde 160 000 FCFA pour tester le verrou)
        }
    }
}

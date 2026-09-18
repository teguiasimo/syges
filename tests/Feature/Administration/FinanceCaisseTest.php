<?php

namespace Tests\Feature\Administration;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\TypeFrais;
use App\Models\User;
use App\Models\VersementCaisse;
use App\Services\FinanceService;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de fonctionnalités pour le Module 11 : Gestion de la Caisse & Paiements (Gesco V1.1).
 *
 * Vérifie :
 * - Le contrôle d'accès selon les permissions RBAC (finances.view, finances.manage, finances.cloture, finances.annuler).
 * - L'affichage du tableau de bord de caisse avec les props Inertia.
 * - L'initialisation automatique du compte financier élève.
 * - L'enregistrement d'un versement guichet multi-modes avec ventilation fine et mise à jour du solde.
 * - L'émission du reçu officiel PDF avec QR Code infalsifiable.
 * - L'annulation d'un versement avec motif obligatoire et restauration du solde.
 * - La clôture journalière de caisse et le verrouillage des écritures.
 * - Le fonctionnement du verrou de solvabilité sur les bulletins.
 */
class FinanceCaisseTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public ?User $unauthorizedUser = null;
    public ?Inscription $inscription = null;
    public ?TypeFrais $typeFrais = null;
    public ?FraisScolaire $fraisScolaire = null;
    public ?Personnel $caissier = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions financières
        foreach (['finances.view', 'finances.manage', 'finances.cloture', 'finances.annuler'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole($adminRole);

        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);

        // 2. Structure scolaire
        $institution = Institution::firstOrCreate(
            ['code' => 'TEST-INST'],
            ['nom' => 'Institution Test Finance', 'sigle' => 'ITF']
        );

        $this->etablissement = Etablissement::firstOrCreate(
            ['code' => 'TEST-ETAB-FIN'],
            [
                'institution_id' => $institution->id,
                'nom' => 'Collège Test Finance',
                'statut_juridique' => 'Prive Laique',
                'ordre_enseignement' => 'Secondaire General',
                'verrou_solvabilite_actif' => true,
            ]
        );

        $section = Section::firstOrCreate(
            ['code' => 'SEC-FR-FIN', 'etablissement_id' => $this->etablissement->id],
            ['nom' => 'Section Francophone']
        );

        $cycle = Cycle::firstOrCreate(
            ['code' => 'CYC-1-FIN', 'section_id' => $section->id],
            ['nom' => '1er Cycle Test Fin', 'ordre' => 1]
        );

        $niveau = Niveau::firstOrCreate(
            ['code' => 'NIV-6-FIN', 'cycle_id' => $cycle->id],
            ['nom' => '6ème Test Fin', 'ordre' => 1]
        );

        $this->annee = AnneeScolaire::firstOrCreate(
            ['code' => '2026-2027'],
            [
                'nom' => 'Année Scolaire 2026-2027',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-06-30',
                'statut' => 'en_cours',
                'is_current' => true,
            ]
        );

        $this->classe = Classe::firstOrCreate(
            ['code' => '6M1-FIN', 'niveau_id' => $niveau->id],
            ['nom' => '6ème M1 Fin', 'capacite_max' => 60, 'is_active' => true]
        );

        // Personnel caissier
        $this->caissier = Personnel::firstOrCreate(
            ['matricule' => 'CAIS-001', 'etablissement_id' => $this->etablissement->id],
            [
                'nom' => 'NDONGO',
                'prenom' => 'Jeanne',
                'sexe' => 'F',
                'telephone' => '+237 699 11 22 33',
                'fonction_principale' => 'Intendant',
                'statut' => 'Actif',
            ]
        );

        // Élève et Inscription
        $eleve = Eleve::firstOrCreate(
            ['matricule' => 'ELEVE-FIN-01'],
            [
                'nom' => 'KAMDEM',
                'prenom' => 'Arthur',
                'sexe' => 'M',
                'date_naissance' => '2014-05-12',
                'nom_parent' => 'KAMDEM Paul',
                'telephone_parent' => '+237 699 00 11 22',
            ]
        );

        $this->inscription = Inscription::firstOrCreate(
            ['eleve_id' => $eleve->id, 'annee_scolaire_id' => $this->annee->id],
            [
                'classe_id' => $this->classe->id,
                'statut' => 'validee',
                'type_inscription' => 'nouveau',
            ]
        );

        // Type de frais et grille tarifaire
        $this->typeFrais = TypeFrais::firstOrCreate(
            ['etablissement_id' => $this->etablissement->id, 'code' => 'SCOL-TEST'],
            [
                'libelle' => 'Scolarité Annuelle Test',
                'obligatoire' => true,
                'periodicite' => 'annuel',
                'montant_defaut' => 100000,
            ]
        );

        $this->fraisScolaire = FraisScolaire::firstOrCreate(
            [
                'annee_scolaire_id' => $this->annee->id,
                'etablissement_id' => $this->etablissement->id,
                'niveau_id' => $niveau->id,
                'type_frais_id' => $this->typeFrais->id,
                'tranche_numero' => 1,
            ],
            [
                'libelle_tranche' => 'Tranche 1 - Scolarité',
                'montant' => 100000,
                'date_limite' => '2026-10-31',
            ]
        );
    }

    /**
     * Vérifie que l'accès à la page des finances est protégé par permission.
     */
    public function test_unauthorized_user_cannot_access_finances(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('administration.finances.index'));
        $response->assertStatus(403);
    }

    /**
     * Vérifie qu'un utilisateur autorisé peut consulter la page de caisse avec toutes les props Inertia.
     */
    public function test_authorized_user_can_access_finances_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('administration.finances.index', [
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->annee->id,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('administration/finances/index')
            ->has('versements')
            ->has('comptes')
            ->has('typesFrais')
            ->has('tarifs')
            ->has('journalDuJour')
            ->has('historiqueClotures')
        );
    }

    /**
     * Vérifie l'initialisation et le recalcul automatique du compte financier élève.
     */
    public function test_initialisation_compte_financier_eleve(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription, 10000); // 10 000 FCFA bourse

        $this->assertInstanceOf(CompteFinancierEleve::class, $compte);
        $this->assertEquals(100000, $compte->debit_initial);
        $this->assertEquals(10000, $compte->remise_bourse);
        $this->assertEquals(90000, $compte->montant_net_exigible);
        $this->assertEquals(0, $compte->total_paye);
        $this->assertEquals(90000, $compte->solde_restant);
        $this->assertEquals('insolvable', $compte->statut_financier);
    }

    /**
     * Vérifie l'initialisation avec arriérés antérieurs et l'ajustement depuis le contrôleur finance.
     */
    public function test_initialisation_avec_arrieres_et_ajustement_compte_financier(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription, 5000, 25000); // 5000 bourse, 25000 arriérés

        $this->assertEquals(100000, $compte->debit_initial);
        $this->assertEquals(25000, $compte->arrieres_anterieurs);
        $this->assertEquals(5000, $compte->remise_bourse);
        // Net exigible = 100 000 + 25 000 - 5 000 = 120 000
        $this->assertEquals(120000, $compte->montant_net_exigible);
        $this->assertEquals(120000, $compte->solde_restant);

        // Test de l'ajustement via route HTTP
        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.finances.comptes.ajuster', $compte), [
                'remise_bourse' => 10000,
                'arrieres_anterieurs' => 30000,
                'observations' => 'Mise à jour manuelle de la dette',
            ]);

        $response->assertRedirect();
        $compte->refresh();
        $this->assertEquals(30000, $compte->arrieres_anterieurs);
        $this->assertEquals(10000, $compte->remise_bourse);
        // Net exigible = 100 000 + 30 000 - 10 000 = 120 000
        $this->assertEquals(120000, $compte->montant_net_exigible);
    }

    /**
     * Vérifie l'enregistrement d'un versement guichet avec ventilation et actualisation instantanée du solde.
     */
    public function test_enregistrement_versement_guichet_avec_ventilation(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $payload = [
            'compte_financier_id' => $compte->id,
            'montant_total' => 60000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'KAMDEM Paul',
            'payeur_telephone' => '+237 699 00 11 22',
            'personnel_id' => $this->caissier->id,
            'ventilations' => [
                [
                    'type_frais_id' => $this->typeFrais->id,
                    'frais_scolaire_id' => $this->fraisScolaire->id,
                    'libelle' => 'Tranche 1 - Scolarité Partielle',
                    'montant' => 60000,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->post(route('administration.finances.versements.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Vérification en base de données
        $this->assertDatabaseHas('versements_caisse', [
            'compte_financier_id' => $compte->id,
            'montant_total' => 60000,
            'mode_paiement' => 'especes',
            'statut' => 'valide',
        ]);

        $this->assertDatabaseHas('ventilation_versements', [
            'libelle' => 'Tranche 1 - Scolarité Partielle',
            'montant' => 60000,
        ]);

        // Le compte élève doit être actualisé
        $compte->refresh();
        $this->assertEquals(60000, $compte->total_paye);
        $this->assertEquals(40000, $compte->solde_restant);
        $this->assertEquals('en_regle_tranche_2', $compte->statut_financier);
    }

    /**
     * Vérifie la génération et l'impression du reçu officiel PDF avec QR Code.
     */
    public function test_impression_recu_officiel_pdf(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $versement = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 100000,
            'mode_paiement' => 'mobile_money_manuel',
            'reference_paiement' => 'MOMO-REF-TEST-9988',
            'payeur_nom' => 'KAMDEM Paul',
            'ventilations' => [
                ['libelle' => 'Règlement Intégral Scolarité', 'montant' => 100000],
            ],
        ], $this->caissier, $this->adminUser);

        $response = $this->actingAs($this->adminUser)->get(route('administration.finances.versements.recu', $versement));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("Recu_{$versement->reference_recu}.pdf", $response->headers->get('Content-Disposition') ?? '');
    }

    /**
     * Vérifie l'annulation administrative d'un versement et le réajustement du compte élève.
     */
    public function test_annulation_versement_avec_motif_obligatoire(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $versement = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 50000,
            'mode_paiement' => 'cheque',
            'reference_paiement' => 'CHQ-889911',
            'ventilations' => [
                ['libelle' => 'Acompte Scolarité Chèque', 'montant' => 50000],
            ],
        ], $this->caissier, $this->adminUser);

        $compte->refresh();
        $this->assertEquals(50000, $compte->total_paye);

        // Annulation via requête POST
        $response = $this->actingAs($this->adminUser)->post(route('administration.finances.versements.annuler', $versement), [
            'motif_annulation' => 'Chèque sans provision retourné par la banque.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $versement->refresh();
        $this->assertEquals('annule', $versement->statut);
        $this->assertEquals('Chèque sans provision retourné par la banque.', $versement->motif_annulation);
        $this->assertEquals($this->adminUser->id, $versement->annule_par);

        // Le solde élève doit avoir été restauré à 0 payé
        $compte->refresh();
        $this->assertEquals(0, $compte->total_paye);
        $this->assertEquals(100000, $compte->solde_restant);
        $this->assertEquals('insolvable', $compte->statut_financier);
    }

    /**
     * Vérifie la clôture journalière de caisse et le verrouillage des nouvelles écritures.
     */
    public function test_cloture_journaliere_caisse_et_verrouillage(): void
    {
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($this->adminUser)->post(route('administration.finances.cloturer-journee'), [
            'etablissement_id' => $this->etablissement->id,
            'personnel_id' => $this->caissier->id,
            'date_cloture' => $today,
            'observations' => 'Clôture de test sans anomalie.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clotures_caisse', [
            'etablissement_id' => $this->etablissement->id,
            'personnel_id' => $this->caissier->id,
            'statut' => 'cloturee',
        ]);

        $this->assertTrue(
            \App\Models\ClotureCaisse::where('etablissement_id', $this->etablissement->id)
                ->where('personnel_id', $this->caissier->id)
                ->whereDate('date_cloture', $today)
                ->where('statut', 'cloturee')
                ->exists()
        );

        // Toute nouvelle tentative d'encaissement pour ce caissier aujourd'hui doit être rejetée
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Impossible d'encaisser : la caisse de cette journée a déjà été clôturée.");

        $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 10000,
            'mode_paiement' => 'especes',
            'ventilations' => [
                ['libelle' => 'Test Clôture Rejet', 'montant' => 10000],
            ],
        ], $this->caissier, $this->adminUser);
    }

    /**
     * Vérifie le verrou de solvabilité sur les bulletins scolaires (Gesco V1.1 - Module 11.5).
     */
    public function test_verrou_solvabilite_bloque_eleve_insolvable(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        // L'élève a un solde restant de 100 000 FCFA et le verrou est actif sur l'établissement
        $resultat = $financeService->verifierSolvabiliteBulletin($this->inscription);

        $this->assertTrue($resultat['bloque']);
        $this->assertEquals(100000, $resultat['solde_restant']);
        $this->assertStringContainsString('Accès restreint. Veuillez régulariser le solde financier', $resultat['message'] ?? '');

        // Si on solde le compte
        $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 100000,
            'mode_paiement' => 'especes',
            'ventilations' => [
                ['libelle' => 'Scolarité Solde', 'montant' => 100000],
            ],
        ], null, $this->adminUser);

        $resultatApresPaiement = $financeService->verifierSolvabiliteBulletin($this->inscription);
        $this->assertFalse($resultatApresPaiement['bloque']);
        $this->assertEquals(0, $resultatApresPaiement['solde_restant']);
    }

    /**
     * Vérifie l'isolation financière stricte entre deux établissements (Gesco V1.1 multi-campus).
     * Les versements, journaux de caisse et tarifs d'un campus ne doivent pas contaminer un autre campus.
     */
    public function test_finances_etablissements_isolation(): void
    {
        // Création d'un second établissement
        $secondEtab = Etablissement::create([
            'institution_id' => $this->etablissement->institution_id,
            'nom' => 'Lycée Secondaire Campus B',
            'code' => 'ETAB-FIN-B',
            'statut_juridique' => 'Prive Laique',
            'ordre_enseignement' => 'Secondaire General',
            'verrou_solvabilite_actif' => false,
        ]);

        // Consultation de la caisse pour le Campus A
        $responseA = $this->actingAs($this->adminUser)->get(route('administration.finances.index', [
            'etablissement_id' => $this->etablissement->id,
        ]));
        $responseA->assertOk();
        $responseA->assertInertia(fn ($page) => $page
            ->component('administration/finances/index')
            ->where('currentEtablissement.id', $this->etablissement->id)
            ->where('currentEtablissement.verrou_solvabilite_actif', true)
        );

        // Consultation de la caisse pour le Campus B
        $responseB = $this->actingAs($this->adminUser)->get(route('administration.finances.index', [
            'etablissement_id' => $secondEtab->id,
        ]));
        $responseB->assertOk();
        $responseB->assertInertia(fn ($page) => $page
            ->component('administration/finances/index')
            ->where('currentEtablissement.id', $secondEtab->id)
            ->where('currentEtablissement.verrou_solvabilite_actif', false)
        );
    }
}


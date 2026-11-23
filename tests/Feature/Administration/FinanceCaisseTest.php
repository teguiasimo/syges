<?php

namespace Tests\Feature\Administration;

use App\Models\AnneeScolaire;
use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\CategorieFrais;
use App\Models\Classe;
use App\Models\ClotureCaisse;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\EleveBourseRemise;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\PenaliteConfiguration;
use App\Models\PenaliteEleve;
use App\Models\Personnel;
use App\Models\ReglementDivers;
use App\Models\Section;
use App\Models\SouscriptionFraisOptionnel;
use App\Models\TypeFrais;
use App\Models\User;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

    public ?User $unauthorizedUser = null;

    public ?Inscription $inscription = null;

    public ?TypeFrais $typeFrais = null;

    public ?FraisScolaire $fraisScolaire = null;

    public ?Personnel $caissier = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions financières
        foreach (['finances.view', 'finances.manage', 'finances.cloture', 'finances.annuler', 'versements.create', 'versements.edit', 'versements.cancel'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
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

        $this->adminUser->update(['etablissement_id' => $this->etablissement->id]);

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
                'date_limite' => Carbon::today()->addMonths(2)->toDateString(),
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
        // Avant la date limite d'échéance, un compte sans versement est « en_attente » (et non faussement insolvable).
        $this->assertEquals('en_attente', $compte->statut_financier);
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
        $this->assertEquals('en_attente', $compte->statut_financier);
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
            ClotureCaisse::where('etablissement_id', $this->etablissement->id)
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

    /**
     * Vérifie que le statut financier respecte strictement les dates limites d'échéance et les paliers.
     *
     * Un acompte de 5 000 F quand l'inscription est échue, ou le solde de la seule inscription quand
     * la 1ère tranche est échue, ne doit jamais être affiché « en_regle_tranche_1 » mais « insolvable ».
     */
    public function test_statut_financier_respecte_echeances_et_paliers(): void
    {
        $cycle = Cycle::where('code', 'CYC-1-FIN')->firstOrFail();
        $niveauPalier = Niveau::firstOrCreate(
            ['code' => 'NIV-PAL-FIN', 'cycle_id' => $cycle->id],
            ['nom' => '5ème Test Palier', 'ordre' => 2]
        );
        $classePalier = Classe::firstOrCreate(
            ['code' => '5M1-PAL', 'niveau_id' => $niveauPalier->id],
            ['nom' => '5ème M1 Palier', 'capacite_max' => 60, 'is_active' => true]
        );

        $catScol = CategorieFrais::firstOrCreate(
            ['etablissement_id' => $this->etablissement->id, 'nom' => 'Scolarité Palier Test'],
            ['description' => 'Catégorie test paliers', 'is_active' => true]
        );

        $tfInsc = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catScol->id,
            'code' => 'INSC-PAL',
            'libelle' => 'Inscription',
            'obligatoire' => true,
            'ordre_priorite' => 1,
            'periodicite' => 'annuel',
            'montant_defaut' => 20000,
        ]);
        $tfT1 = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catScol->id,
            'code' => 'T1-PAL',
            'libelle' => '1ère Tranche',
            'obligatoire' => true,
            'ordre_priorite' => 2,
            'periodicite' => 'trimestriel',
            'montant_defaut' => 50000,
        ]);
        $tfT2 = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catScol->id,
            'code' => 'T2-PAL',
            'libelle' => '2ème Tranche',
            'obligatoire' => true,
            'ordre_priorite' => 3,
            'periodicite' => 'trimestriel',
            'montant_defaut' => 30000,
        ]);

        // Grille 1 : Inscription et 1ère Tranche déjà échues
        $fInsc = FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauPalier->id,
            'type_frais_id' => $tfInsc->id,
            'tranche_numero' => 1,
            'ordre_priorite' => 1,
            'libelle_tranche' => 'Inscription',
            'montant' => 20000,
            'date_limite' => Carbon::today()->subDays(20)->toDateString(),
        ]);
        $fT1 = FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauPalier->id,
            'type_frais_id' => $tfT1->id,
            'tranche_numero' => 2,
            'ordre_priorite' => 2,
            'libelle_tranche' => '1ère Tranche',
            'montant' => 50000,
            'date_limite' => Carbon::today()->subDays(5)->toDateString(),
        ]);
        FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauPalier->id,
            'type_frais_id' => $tfT2->id,
            'tranche_numero' => 3,
            'ordre_priorite' => 3,
            'libelle_tranche' => '2ème Tranche',
            'montant' => 30000,
            'date_limite' => Carbon::today()->addMonths(2)->toDateString(),
        ]);

        $eleve = Eleve::create([
            'matricule' => 'ELV-PAL-01',
            'nom' => 'NGONO',
            'prenom' => 'Claire',
            'sexe' => 'F',
            'date_naissance' => '2013-03-10',
        ]);
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id,
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $classePalier->id,
            'etablissement_id' => $this->etablissement->id,
            'statut' => 'validee',
            'type_inscription' => 'nouveau',
        ]);

        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($inscription);

        // Cas 1 : Avance 5 000 F avec Inscription échue -> insolvable
        $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 5000,
            'mode_paiement' => 'especes',
            'ventilations' => [['libelle' => 'Acompte 5000', 'montant' => 5000]],
        ], null, $this->adminUser);
        $compte->refresh();
        $this->assertEquals('insolvable', $compte->statut_financier);

        // Cas 2 : Complète uniquement l'Inscription (20 000 F total) alors que T1 est échue -> insolvable
        $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 15000,
            'mode_paiement' => 'especes',
            'ventilations' => [['libelle' => 'Solde Inscription', 'montant' => 15000]],
        ], null, $this->adminUser);
        $compte->refresh();
        $this->assertEquals('insolvable', $compte->statut_financier);

        // Cas 3 : Si T1 est encore future -> l'élève avec uniquement l'Inscription payée est « inscrit_a_jour »
        $fT1->update(['date_limite' => Carbon::today()->addMonth()->toDateString()]);
        $compte->actualiserSolde();
        $this->assertEquals('inscrit_a_jour', $compte->statut_financier);

        // Cas 4 : Si Inscription ET T1 sont futures et élève à 5 000 F sur un autre compte -> « acompte » ; à 0 -> « en_attente »
        $fInsc->update(['date_limite' => Carbon::today()->addDays(15)->toDateString()]);
        $eleve2 = Eleve::create([
            'matricule' => 'ELV-PAL-02',
            'nom' => 'MBARGA',
            'prenom' => 'Paul',
            'sexe' => 'M',
            'date_naissance' => '2013-07-01',
        ]);
        $insc2 = Inscription::create([
            'eleve_id' => $eleve2->id,
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $classePalier->id,
            'etablissement_id' => $this->etablissement->id,
            'statut' => 'validee',
            'type_inscription' => 'nouveau',
        ]);
        $compte2 = $financeService->initialiserCompteEleve($insc2);
        $this->assertEquals('en_attente', $compte2->statut_financier);

        $financeService->enregistrerVersement([
            'compte_financier_id' => $compte2->id,
            'montant_total' => 5000,
            'mode_paiement' => 'especes',
            'ventilations' => [['libelle' => 'Acompte avant date', 'montant' => 5000]],
        ], null, $this->adminUser);
        $compte2->refresh();
        $this->assertEquals('acompte', $compte2->statut_financier);

        // Cas 5 : Sur compte 1, règlement T1 (50 000) alors que T2 est future -> « en_regle_tranche_1 »
        $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 50000,
            'mode_paiement' => 'especes',
            'ventilations' => [['libelle' => '1ère Tranche', 'montant' => 50000]],
        ], null, $this->adminUser);
        $compte->refresh();
        $this->assertEquals('en_regle_tranche_1', $compte->statut_financier);
    }

    /**
     * Vérifie que le Bilan des bourses et réductions lit correctement `montant_calcule` (non plus 0)
     * et inclut également les réductions manuelles enregistrées sur `remise_bourse`.
     */
    public function test_bilan_reductions_affiche_montants_reels_et_non_zero(): void
    {
        Permission::firstOrCreate(['name' => 'finances.etats', 'guard_name' => 'web']);

        $financeService = app(FinanceService::class);
        $compte1 = $financeService->initialiserCompteEleve($this->inscription);

        $modeleBourse = BourseRemise::create([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->annee->id,
            'code' => 'BRS-EXC-15K',
            'nom' => 'Bourse Excellence Test',
            'type' => 'remise',
            'categorie' => 'excellence',
            'mode_calcul' => 'montant_fixe',
            'valeur' => 15000,
            'cible_imputation' => 'tranche_1',
            'is_active' => true,
        ]);

        EleveBourseRemise::create([
            'compte_financier_id' => $compte1->id,
            'bourse_remise_id' => $modeleBourse->id,
            'montant_calcule' => 15000,
            'cible_imputation' => 'tranche_1',
            'frais_scolaire_id' => $this->fraisScolaire->id,
            'date_attribution' => Carbon::today(),
            'motif' => 'Bonus paiement anticipé',
            'attribue_par' => $this->adminUser->id,
        ]);
        $compte1->update(['remise_bourse' => 15000]);
        $compte1->actualiserSolde();

        // Second élève avec réduction manuelle directe sur `remise_bourse`
        $eleve2 = Eleve::create([
            'matricule' => 'ELV-RED-02',
            'nom' => 'FOTSO',
            'prenom' => 'Mireille',
            'sexe' => 'F',
            'date_naissance' => '2014-01-20',
        ]);
        $insc2 = Inscription::create([
            'eleve_id' => $eleve2->id,
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $this->classe->id,
            'etablissement_id' => $this->etablissement->id,
            'statut' => 'validee',
            'type_inscription' => 'nouveau',
        ]);
        $compte2 = $financeService->initialiserCompteEleve($insc2);
        $compte2->update(['remise_bourse' => 10000, 'observations' => 'Remise fratrie manuelle']);
        $compte2->actualiserSolde();

        $bilan = $financeService->obtenirBilanReductionsBourses($this->etablissement->id, $this->annee->id);

        $this->assertEquals(25000.0, $bilan['total_reduction']);
        $this->assertEquals(2, $bilan['nombre_beneficiaires']);
        $this->assertEquals(15000.0, $bilan['reductions'][0]['montant'] == 15000.0 ? 15000.0 : $bilan['reductions'][1]['montant']);

        $response = $this->actingAs($this->adminUser)->get(route('administration.finances.etats.bilan-reductions-pdf', [
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->annee->id,
        ]));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Vérifie le filtrage analytique par Catégorie / Frais précis / Palier (« uniquement l'inscription » vs « T1 »)
     * ainsi que les éditions PDF associées (#03 Catégories et #09 Suivi par Frais & Palier).
     */
    public function test_suivi_frais_categorie_et_palier_uniquement(): void
    {
        Permission::firstOrCreate(['name' => 'finances.etats', 'guard_name' => 'web']);

        $cycle = Cycle::where('code', 'CYC-1-FIN')->firstOrFail();
        $niv = Niveau::firstOrCreate(
            ['code' => 'NIV-CAT-FIN', 'cycle_id' => $cycle->id],
            ['nom' => '4ème Test Catégories', 'ordre' => 3]
        );
        $classe = Classe::firstOrCreate(
            ['code' => '4M1-CAT', 'niveau_id' => $niv->id],
            ['nom' => '4ème M1 Cat', 'capacite_max' => 60, 'is_active' => true]
        );

        $catScol = CategorieFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Scolarité Cat',
            'is_active' => true,
        ]);
        $catExam = CategorieFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => "Frais d'Examen Cat",
            'is_active' => true,
        ]);

        $tfInsc = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catScol->id,
            'code' => 'INSC-CAT',
            'libelle' => 'Inscription Cat',
            'obligatoire' => true,
            'ordre_priorite' => 1,
            'periodicite' => 'annuel',
            'montant_defaut' => 15000,
        ]);
        $tfT1 = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catScol->id,
            'code' => 'T1-CAT',
            'libelle' => '1ère Tranche Cat',
            'obligatoire' => true,
            'ordre_priorite' => 2,
            'periodicite' => 'trimestriel',
            'montant_defaut' => 40000,
        ]);
        $tfExam = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catExam->id,
            'code' => 'EXAM-CAT',
            'libelle' => 'Dossier BEPC',
            'obligatoire' => true,
            'ordre_priorite' => 3,
            'periodicite' => 'ponctuel',
            'montant_defaut' => 10000,
        ]);

        $fInsc = FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niv->id,
            'type_frais_id' => $tfInsc->id,
            'tranche_numero' => 1,
            'ordre_priorite' => 1,
            'libelle_tranche' => 'Inscription Cat',
            'montant' => 15000,
            'date_limite' => Carbon::today()->addMonth()->toDateString(),
        ]);
        $fT1 = FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niv->id,
            'type_frais_id' => $tfT1->id,
            'tranche_numero' => 2,
            'ordre_priorite' => 2,
            'libelle_tranche' => '1ère Tranche Cat',
            'montant' => 40000,
            'date_limite' => Carbon::today()->addMonths(2)->toDateString(),
        ]);
        FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niv->id,
            'type_frais_id' => $tfExam->id,
            'tranche_numero' => 3,
            'ordre_priorite' => 3,
            'libelle_tranche' => 'Dossier BEPC',
            'montant' => 10000,
            'date_limite' => Carbon::today()->addMonths(3)->toDateString(),
        ]);

        $financeService = app(FinanceService::class);

        // Élève A : a payé UNIQUEMENT l'Inscription (15 000 F)
        $eA = Eleve::create(['matricule' => 'ELV-CAT-A', 'nom' => 'ATANGANA', 'prenom' => 'Luc', 'sexe' => 'M', 'date_naissance' => '2012-02-02']);
        $iA = Inscription::create(['eleve_id' => $eA->id, 'annee_scolaire_id' => $this->annee->id, 'classe_id' => $classe->id, 'etablissement_id' => $this->etablissement->id, 'statut' => 'validee', 'type_inscription' => 'nouveau']);
        $cA = $financeService->initialiserCompteEleve($iA);
        $financeService->enregistrerVersement([
            'compte_financier_id' => $cA->id,
            'montant_total' => 15000,
            'mode_paiement' => 'especes',
            'ventilations' => [['frais_scolaire_id' => $fInsc->id, 'type_frais_id' => $tfInsc->id, 'libelle' => 'Inscription Cat', 'montant' => 15000]],
        ], null, $this->adminUser);

        // Élève B : a payé Inscription + 1ère Tranche (55 000 F)
        $eB = Eleve::create(['matricule' => 'ELV-CAT-B', 'nom' => 'BELLA', 'prenom' => 'Diane', 'sexe' => 'F', 'date_naissance' => '2012-06-15']);
        $iB = Inscription::create(['eleve_id' => $eB->id, 'annee_scolaire_id' => $this->annee->id, 'classe_id' => $classe->id, 'etablissement_id' => $this->etablissement->id, 'statut' => 'validee', 'type_inscription' => 'nouveau']);
        $cB = $financeService->initialiserCompteEleve($iB);
        $financeService->enregistrerVersement([
            'compte_financier_id' => $cB->id,
            'montant_total' => 55000,
            'mode_paiement' => 'especes',
            'ventilations' => [
                ['frais_scolaire_id' => $fInsc->id, 'type_frais_id' => $tfInsc->id, 'libelle' => 'Inscription Cat', 'montant' => 15000],
                ['frais_scolaire_id' => $fT1->id, 'type_frais_id' => $tfT1->id, 'libelle' => '1ère Tranche Cat', 'montant' => 40000],
            ],
        ], null, $this->adminUser);

        // Filtre « uniquement l'Inscription » -> ne doit retenir que Élève A
        $suiviInscSeule = $financeService->obtenirSuiviParFraisOuCategorie(
            $this->etablissement->id,
            $this->annee->id,
            null,
            $tfInsc->id,
            'uniquement_palier',
            null,
            null,
            null,
            $classe->id
        );
        $this->assertEquals(1, $suiviInscSeule['nombre_eleves']);
        $this->assertEquals('ELV-CAT-A', $suiviInscSeule['eleves'][0]['matricule']);

        // Filtre « uniquement jusqu'à la 1ère Tranche » -> ne doit retenir que Élève B
        $suiviT1Seule = $financeService->obtenirSuiviParFraisOuCategorie(
            $this->etablissement->id,
            $this->annee->id,
            null,
            $tfT1->id,
            'uniquement_palier',
            null,
            null,
            null,
            $classe->id
        );
        $this->assertEquals(1, $suiviT1Seule['nombre_eleves']);
        $this->assertEquals('ELV-CAT-B', $suiviT1Seule['eleves'][0]['matricule']);

        // Filtre sur catégorie Examen : 2 élèves concernés, 20 000 F attendus, 0 F payé
        $suiviCatExam = $financeService->obtenirSuiviParFraisOuCategorie(
            $this->etablissement->id,
            $this->annee->id,
            $catExam->id,
            null,
            'tous',
            null,
            null,
            null,
            $classe->id
        );
        $this->assertEquals(2, $suiviCatExam['nombre_eleves']);
        $this->assertEquals(20000.0, $suiviCatExam['total_attendu']);
        $this->assertEquals(0.0, $suiviCatExam['total_paye']);

        // Vérification HTTP 200 des deux éditions PDF (#09 et #03 filtré par catégorie)
        $pdfSuivi = $this->actingAs($this->adminUser)->get(route('administration.finances.etats.suivi-frais-pdf', [
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->annee->id,
            'type_frais_id' => $tfInsc->id,
            'mode_filtre_frais' => 'uniquement_palier',
        ]));
        $pdfSuivi->assertOk();
        $this->assertEquals('application/pdf', $pdfSuivi->headers->get('Content-Type'));

        $pdfCat = $this->actingAs($this->adminUser)->get(route('administration.finances.etats.journal-categories-pdf', [
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->annee->id,
            'categorie_frais_id' => $catScol->id,
        ]));
        $pdfCat->assertOk();
        $this->assertEquals('application/pdf', $pdfCat->headers->get('Content-Type'));
    }

    /**
     * Vérifie la génération du reçu au format Ticket de caisse thermique 80mm (`?format=ticket`).
     */
    public function test_impression_ticket_caisse_80mm(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $versement = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 25000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'Parent Ticket',
            'ventilations' => [
                ['libelle' => 'Acompte Ticket 80mm', 'montant' => 25000],
            ],
        ], $this->caissier, $this->adminUser);

        $response = $this->actingAs($this->adminUser)->get(
            route('administration.finances.versements.recu', $versement).'?format=ticket'
        );

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            "Ticket_{$versement->reference_recu}.pdf",
            $response->headers->get('Content-Disposition') ?? ''
        );
    }

    /**
     * Vérifie qu'en mode multi-campus global (`etablissement_id=all`), les catégories et types de frais
     * homonymes entre deux établissements sont dédoublonnés dans l'interface des états et consolidés
     * sur tous les campus lors de la génération des rapports filtrés par catégorie ou frais.
     */
    public function test_etats_dedoublonne_categories_et_types_frais_en_mode_global_multi_campus(): void
    {
        Permission::firstOrCreate(['name' => 'finances.etats', 'guard_name' => 'web']);

        $etab2 = Etablissement::create([
            'institution_id' => $this->etablissement->institution_id,
            'code' => 'TEST-ETAB-FIN-2',
            'nom' => 'Campus Annexe Test',
            'statut_juridique' => 'Prive Laique',
            'ordre_enseignement' => 'Secondaire General',
            'verrou_solvabilite_actif' => true,
        ]);

        $section2 = Section::create(['code' => 'SEC-EN-FIN', 'etablissement_id' => $etab2->id, 'nom' => 'Section Anglophone']);
        $cycle2 = Cycle::create(['code' => 'CYC-1-FIN2', 'section_id' => $section2->id, 'nom' => 'First Cycle', 'ordre' => 1]);
        $niv2 = Niveau::create(['code' => 'NIV-F1-FIN2', 'cycle_id' => $cycle2->id, 'nom' => 'Form 1', 'ordre' => 1]);
        $classe2 = Classe::create(['code' => 'F1A-FIN2', 'niveau_id' => $niv2->id, 'nom' => 'Form 1 A', 'capacite_max' => 60, 'is_active' => true]);

        // Deux catégories homonymes « Scolarité » (1 par établissement) et deux types « Inscription »
        $cat1 = CategorieFrais::create(['etablissement_id' => $this->etablissement->id, 'nom' => 'Scolarité', 'is_active' => true]);
        $cat2 = CategorieFrais::create(['etablissement_id' => $etab2->id, 'nom' => 'Scolarité', 'is_active' => true]);

        $tf1 = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $cat1->id,
            'code' => 'INSC-E1',
            'libelle' => 'Inscription',
            'obligatoire' => true,
            'ordre_priorite' => 1,
            'periodicite' => 'annuel',
            'montant_defaut' => 15000,
        ]);
        $tf2 = TypeFrais::create([
            'etablissement_id' => $etab2->id,
            'categorie_frais_id' => $cat2->id,
            'code' => 'INSC-E2',
            'libelle' => 'Inscription',
            'obligatoire' => true,
            'ordre_priorite' => 1,
            'periodicite' => 'annuel',
            'montant_defaut' => 20000,
        ]);

        // 1. Vérifier que la page /administration/finances/etats en mode global dédoublonne « Scolarité » et « Inscription »
        $response = $this->actingAs($this->adminUser)->get('/administration/finances/etats?etablissement_id=all');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('isGlobal', true)
            ->where('categoriesFrais', fn ($cats) => count($cats) === 1 && $cats[0]['nom'] === 'Scolarité')
            ->where('typesFrais', fn ($tfs) => collect($tfs)->where('libelle', 'Inscription')->count() === 1)
        );

        // 2. Vérifier que FinanceService consolidé (etablissementId=null) retrouve les 2 campus avec l'ID représentatif $cat1
        $niv1 = $this->classe->niveau_id;
        $f1 = FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niv1,
            'type_frais_id' => $tf1->id,
            'tranche_numero' => 1,
            'ordre_priorite' => 1,
            'libelle_tranche' => 'Inscription',
            'montant' => 15000,
            'date_limite' => Carbon::today()->addMonth()->toDateString(),
        ]);
        $f2 = FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $etab2->id,
            'niveau_id' => $niv2->id,
            'type_frais_id' => $tf2->id,
            'tranche_numero' => 1,
            'ordre_priorite' => 1,
            'libelle_tranche' => 'Inscription',
            'montant' => 20000,
            'date_limite' => Carbon::today()->addMonth()->toDateString(),
        ]);

        $financeService = app(FinanceService::class);
        $e1 = Eleve::create(['matricule' => 'ELV-MC-1', 'nom' => 'FOSSO', 'prenom' => 'Paul', 'sexe' => 'M', 'date_naissance' => '2013-01-10']);
        $i1 = Inscription::create(['eleve_id' => $e1->id, 'annee_scolaire_id' => $this->annee->id, 'classe_id' => $this->classe->id, 'etablissement_id' => $this->etablissement->id, 'statut' => 'validee', 'type_inscription' => 'nouveau']);
        $c1 = $financeService->initialiserCompteEleve($i1);
        $financeService->enregistrerVersement([
            'compte_financier_id' => $c1->id,
            'montant_total' => 15000,
            'mode_paiement' => 'especes',
            'ventilations' => [['frais_scolaire_id' => $f1->id, 'type_frais_id' => $tf1->id, 'libelle' => 'Inscription', 'montant' => 15000]],
        ], null, $this->adminUser);

        $e2 = Eleve::create(['matricule' => 'ELV-MC-2', 'nom' => 'JOHNSON', 'prenom' => 'Mary', 'sexe' => 'F', 'date_naissance' => '2013-03-22']);
        $i2 = Inscription::create(['eleve_id' => $e2->id, 'annee_scolaire_id' => $this->annee->id, 'classe_id' => $classe2->id, 'etablissement_id' => $etab2->id, 'statut' => 'validee', 'type_inscription' => 'nouveau']);
        $c2 = $financeService->initialiserCompteEleve($i2);
        $financeService->enregistrerVersement([
            'compte_financier_id' => $c2->id,
            'montant_total' => 20000,
            'mode_paiement' => 'especes',
            'ventilations' => [['frais_scolaire_id' => $f2->id, 'type_frais_id' => $tf2->id, 'libelle' => 'Inscription', 'montant' => 20000]],
        ], null, $this->adminUser);

        // En mode global (null) avec l'ID représentatif du campus 1, les 2 élèves des 2 campus doivent ressortir
        $suiviGlobal = $financeService->obtenirSuiviParFraisOuCategorie(
            null,
            $this->annee->id,
            $cat1->id,
            $tf1->id,
            'tous'
        );
        $this->assertEquals(2, $suiviGlobal['nombre_eleves']);
        $this->assertEquals(35000.0, $suiviGlobal['total_paye']);

        $journalGlobal = $financeService->obtenirJournalParCategories(
            null,
            Carbon::today()->toDateString(),
            Carbon::today()->toDateString(),
            null,
            null,
            null,
            null,
            null,
            null,
            $cat1->id,
            $tf1->id
        );
        $this->assertEquals(35000.0, $journalGlobal['total_general']);
    }

    /**
     * Vérifie que l'impression PDF du Bilan Détaillé des Versements transmet les filtres
     * hiérarchiques (section, cycle, niveau, classe) dans l'ordre attendu par FinanceService
     * et isole correctement la classe ciblée.
     */
    public function test_bilan_detaille_pdf_transmet_filtres_hierarchiques_dans_bon_ordre(): void
    {
        Permission::firstOrCreate(['name' => 'finances.etats', 'guard_name' => 'web']);

        $financeService = app(FinanceService::class);
        $financeService->initialiserCompteEleve($this->inscription);

        // Second périmètre hiérarchique distinct dans le même établissement pour vérifier l'isolation
        $secAlt = Section::create(['code' => 'SEC-BD-ALT', 'etablissement_id' => $this->etablissement->id, 'nom' => 'Section Test Bilan']);
        $cycAlt = Cycle::create(['code' => 'CYC-BD-ALT', 'section_id' => $secAlt->id, 'nom' => 'Cycle Test Bilan', 'ordre' => 2]);
        $nivAlt = Niveau::create(['code' => 'NIV-BD-ALT', 'cycle_id' => $cycAlt->id, 'nom' => 'Niveau Test Bilan', 'ordre' => 2]);
        $classeAlt = Classe::create(['code' => 'CLS-BD-ALT', 'niveau_id' => $nivAlt->id, 'nom' => 'Classe Test Bilan', 'capacite_max' => 50, 'is_active' => true]);

        $eleveAlt = Eleve::create([
            'matricule' => 'ELV-BD-ALT',
            'nom' => 'MBAPPE',
            'prenom' => 'Clara',
            'sexe' => 'F',
            'date_naissance' => '2012-05-14',
        ]);
        $inscAlt = Inscription::create([
            'eleve_id' => $eleveAlt->id,
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $classeAlt->id,
            'etablissement_id' => $this->etablissement->id,
            'statut' => 'validee',
            'type_inscription' => 'nouveau',
        ]);
        $financeService->initialiserCompteEleve($inscAlt);

        $niv1 = Niveau::findOrFail($this->classe->niveau_id);
        $cyc1 = Cycle::findOrFail($niv1->cycle_id);

        /** @var array<string, mixed>|null $captured */
        $captured = null;
        view()->composer('finances.bilan_detaille', function ($view) use (&$captured): void {
            $captured = $view->getData();
        });

        $response = $this->actingAs($this->adminUser)->get(route('administration.finances.etats.bilan-detaille-pdf', [
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->annee->id,
            'section_id' => $cyc1->section_id,
            'cycle_id' => $cyc1->id,
            'niveau_id' => $niv1->id,
            'classe_id' => $this->classe->id,
            'date_debut' => Carbon::today()->toDateString(),
            'date_fin' => Carbon::today()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertIsArray($captured);
        $this->assertEquals(1, $captured['nombre_eleves']);
        $this->assertEquals($this->classe->nom, $captured['eleves'][0]['classe']);
        $this->assertNotEquals('ELV-BD-ALT', $captured['eleves'][0]['matricule']);
    }

    /**
     * Vérifie que le reçu de caisse s'imprime par défaut en A4 Portrait avec 2 exemplaires
     * (Parent/Élève + Souche Administration) sur une seule page pour <= 4 lignes de ventilation,
     * et bascule automatiquement en 1 exemplaire pleine page A4 au-delà de 4 lignes.
     */
    public function test_impression_recu_a4_deux_exemplaires_et_bascule_automatique(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        // Cas 1 : 3 lignes de ventilation (<= 4) -> 2 exemplaires sur 1 page A4 Portrait
        $versementCompact = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 60000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'KAMDEM Paul',
            'ventilations' => [
                ['libelle' => 'Inscription annuelle', 'montant' => 15000],
                ['libelle' => '1ère Tranche Scolarité', 'montant' => 35000],
                ['libelle' => 'Frais APEE & Assurance', 'montant' => 10000],
            ],
        ], $this->caissier, $this->adminUser);

        $dataCompact = null;
        view()->composer('finances.recu_caisse', function ($view) use (&$dataCompact): void {
            $dataCompact = $view->getData();
        });

        $respCompact = $this->actingAs($this->adminUser)
            ->get(route('administration.finances.versements.recu', $versementCompact));

        $respCompact->assertOk();
        $this->assertEquals('application/pdf', $respCompact->headers->get('Content-Type'));
        $this->assertIsArray($dataCompact);
        $htmlCompact = view('finances.recu_caisse', $dataCompact)->render();
        $this->assertStringContainsString('Exemplaire Parent / Élève', $htmlCompact);
        $this->assertStringContainsString('Souche Administration / Caisse', $htmlCompact);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+595\.280*\s+841\.890*\s*\]/', $respCompact->getContent());
        $this->assertMatchesRegularExpression('/\/Count\s+1\b/', $respCompact->getContent());

        // Cas 2 : 6 lignes de ventilation (> 4) -> bascule automatique sur 1 exemplaire pleine page A4
        $versementLong = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 30000,
            'mode_paiement' => 'virement',
            'payeur_nom' => 'KAMDEM Paul',
            'ventilations' => [
                ['libelle' => 'Frais ligne 1', 'montant' => 5000],
                ['libelle' => 'Frais ligne 2', 'montant' => 5000],
                ['libelle' => 'Frais ligne 3', 'montant' => 5000],
                ['libelle' => 'Frais ligne 4', 'montant' => 5000],
                ['libelle' => 'Frais ligne 5', 'montant' => 5000],
                ['libelle' => 'Frais ligne 6', 'montant' => 5000],
            ],
        ], $this->caissier, $this->adminUser);

        $dataLong = null;
        view()->composer('finances.recu_caisse', function ($view) use (&$dataLong): void {
            $dataLong = $view->getData();
        });

        $respLong = $this->actingAs($this->adminUser)
            ->get(route('administration.finances.versements.recu', $versementLong));

        $respLong->assertOk();
        $this->assertIsArray($dataLong);
        $htmlLong = view('finances.recu_caisse', $dataLong)->render();
        $this->assertStringContainsString('Exemplaire Officiel — Format A4', $htmlLong);
        $this->assertStringNotContainsString('Souche Administration / Caisse', $htmlLong);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+595\.280*\s+841\.890*\s*\]/', $respLong->getContent());
        $this->assertMatchesRegularExpression('/\/Count\s+1\b/', $respLong->getContent());
    }

    /**
     * Vérifie que le reçu d'encaissement divers s'imprime en A4 Portrait avec 2 exemplaires
     * (Versant/Client + Souche Administration) sur 1 page par défaut, et bascule en 1 exemplaire
     * lorsque les observations dépassent 180 caractères.
     */
    public function test_impression_recu_divers_a4_deux_exemplaires_et_bascule_automatique(): void
    {
        $caisse = Caisse::firstOrCreate(
            ['etablissement_id' => $this->etablissement->id, 'code' => 'CAISSE-FIN-A4'],
            [
                'nom' => 'Caisse Test A4',
                'solde_initial' => 0,
                'is_principale' => true,
                'is_active' => true,
            ]
        );

        $reglementCourt = ReglementDivers::create([
            'etablissement_id' => $this->etablissement->id,
            'caisse_id' => $caisse->id,
            'reference_recu' => 'REC-DIV-A4-001',
            'date_reglement' => Carbon::today(),
            'payeur_nom' => 'M. NKOA Serge',
            'payeur_telephone' => '677001122',
            'categorie' => 'uniformes',
            'libelle' => 'Tenue EPS + Badge élève',
            'montant' => 12500,
            'mode_paiement' => 'especes',
            'observations' => 'Retrait complet effectué au secrétariat.',
            'statut' => 'valide',
        ]);

        $dataCourt = null;
        view()->composer('finances.recu_divers', function ($view) use (&$dataCourt): void {
            $dataCourt = $view->getData();
        });

        $respCourt = $this->actingAs($this->adminUser)
            ->get(route('administration.finances.reglements-divers.recu', $reglementCourt));

        $respCourt->assertOk();
        $this->assertEquals('application/pdf', $respCourt->headers->get('Content-Type'));
        $this->assertIsArray($dataCourt);
        $htmlCourt = view('finances.recu_divers', $dataCourt)->render();
        $this->assertStringContainsString('Exemplaire Versant / Client', $htmlCourt);
        $this->assertStringContainsString('Souche Administration / Caisse', $htmlCourt);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+595\.280*\s+841\.890*\s*\]/', $respCourt->getContent());
        $this->assertMatchesRegularExpression('/\/Count\s+1\b/', $respCourt->getContent());

        $reglementLong = ReglementDivers::create([
            'etablissement_id' => $this->etablissement->id,
            'caisse_id' => $caisse->id,
            'reference_recu' => 'REC-DIV-A4-002',
            'date_reglement' => Carbon::today(),
            'payeur_nom' => 'Mme TCHOUA Clarisse',
            'categorie' => 'documents',
            'libelle' => 'Lot de manuels scolaires',
            'montant' => 35000,
            'mode_paiement' => 'mobile_money',
            'observations' => str_repeat('Détail complémentaire de livraison du lot scolaire. ', 6),
            'statut' => 'valide',
        ]);

        $dataLong = null;
        view()->composer('finances.recu_divers', function ($view) use (&$dataLong): void {
            $dataLong = $view->getData();
        });

        $respLong = $this->actingAs($this->adminUser)
            ->get(route('administration.finances.reglements-divers.recu', $reglementLong));

        $respLong->assertOk();
        $this->assertIsArray($dataLong);
        $htmlLong = view('finances.recu_divers', $dataLong)->render();
        $this->assertStringContainsString('Exemplaire Officiel — Format A4', $htmlLong);
        $this->assertStringNotContainsString('Souche Administration / Caisse', $htmlLong);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+595\.280*\s+841\.890*\s*\]/', $respLong->getContent());
        $this->assertMatchesRegularExpression('/\/Count\s+1\b/', $respLong->getContent());
    }

    /**
     * Vérifie que le Relevé Individuel des Versements d'un Élève (`/administration/finances/etats/releve-eleve-pdf`)
     * se génère sans erreur `RelationNotFoundException` et affiche les rubriques ventilées exactes.
     */
    public function test_impression_releve_individuel_eleve_pdf(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 25000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'Parent Relevé',
            'ventilations' => [
                [
                    'frais_scolaire_id' => $this->fraisScolaire->id,
                    'type_frais_id' => $this->typeFrais->id,
                    'libelle' => '1ère Tranche Scolarité',
                    'montant' => 25000,
                ],
            ],
        ], $this->caissier, $this->adminUser);

        $releveData = $financeService->obtenirReleveIndividuelEleve(
            $this->inscription->eleve_id,
            $this->annee->id
        );

        $this->assertCount(1, $releveData['versements']);
        $this->assertSame('1ère Tranche Scolarité', $releveData['versements'][0]['rubriques']);
        $this->assertSame(25000.0, $releveData['total_verse']);

        $response = $this->actingAs($this->adminUser)->get(
            route('administration.finances.etats.releve-eleve-pdf', [
                'eleve_id' => $this->inscription->eleve_id,
                'annee_scolaire_id' => $this->annee->id,
                'classe_id' => $this->inscription->classe_id,
            ])
        );

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Vérifie que la route « Situation Financière » appelée depuis la liste des élèves
     * (`/administration/documents/situation-financiere/{inscription}`) fonctionne avec des versements
     * et pénalités existants sans lever `RelationNotFoundException` (`modePaiement` / `penaliteConfiguration`).
     */
    public function test_situation_financiere_eleve_depuis_module_pedagogique_avec_versements(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $versement = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 25000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'Parent Élève',
            'ventilations' => [
                [
                    'frais_scolaire_id' => $this->fraisScolaire->id,
                    'type_frais_id' => $this->typeFrais->id,
                    'libelle' => '1ère Tranche Scolarité',
                    'montant' => 25000,
                ],
            ],
        ], $this->caissier, $this->adminUser);

        $penaliteConfig = PenaliteConfiguration::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Majoration Retard',
            'type_penalite' => 'montant_fixe',
            'valeur' => 2000,
            'is_active' => true,
        ]);

        PenaliteEleve::create([
            'compte_financier_id' => $compte->id,
            'penalite_configuration_id' => $penaliteConfig->id,
            'libelle' => 'Pénalité Retard T1',
            'montant' => 2000,
            'date_application' => Carbon::today(),
            'statut' => 'a_payer',
        ]);

        $capturedViewData = null;
        view()->composer('finances.fiche_compte_eleve', function ($view) use (&$capturedViewData): void {
            $capturedViewData = $view->getData();
        });

        $response = $this->actingAs($this->adminUser)->get(
            route('administration.documents.situation-financiere', $this->inscription)
        );

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertIsArray($capturedViewData);

        $html = view('finances.fiche_compte_eleve', $capturedViewData)->render();
        $this->assertStringContainsString($versement->reference_recu, $html);
        $this->assertStringContainsString('25 000 FCFA', $html);
        $this->assertStringNotContainsString('TARIFICATION NON CONFIGURÉE', $html);
    }

    /**
     * Vérifie que lorsqu'un établissement/niveau (ex. Maternelle ou nouveau cycle) gère uniquement
     * le volet pédagogique sans grille tarifaire ni compte financier initialisé, le clic sur
     * « Situation Financière » génère un PDF propre mentionnant l'attente de configuration.
     */
    public function test_situation_financiere_eleve_sans_tarification_configuree_affiche_etat_attente_sans_erreur(): void
    {
        // Nouveau campus uniquement pédagogique (aucun FraisScolaire ni CompteFinancierEleve configuré)
        $etabPedagogique = Etablissement::create([
            'institution_id' => $this->etablissement->institution_id,
            'code' => 'ESP-MAT-TEST',
            'nom' => 'École Maternelle Test Sans Tarif',
            'statut_juridique' => 'Prive Laique',
            'ordre_enseignement' => 'Maternelle',
            'verrou_solvabilite_actif' => false,
        ]);

        $sectionMat = Section::create([
            'code' => 'SEC-MAT-TEST',
            'etablissement_id' => $etabPedagogique->id,
            'nom' => 'Section Maternelle',
        ]);
        $cycleMat = Cycle::create([
            'code' => 'CYC-MAT-TEST',
            'section_id' => $sectionMat->id,
            'nom' => 'Cycle Maternel',
            'ordre' => 1,
        ]);
        $niveauMat = Niveau::create([
            'code' => 'N1-MAT',
            'cycle_id' => $cycleMat->id,
            'nom' => 'Nursery One',
            'ordre' => 1,
        ]);
        $classeMat = Classe::create([
            'etablissement_id' => $etabPedagogique->id,
            'code' => 'N1A-MAT',
            'niveau_id' => $niveauMat->id,
            'nom' => 'Nursery One A',
            'capacite_max' => 45,
            'is_active' => true,
        ]);

        $eleveMat = Eleve::create([
            'matricule' => 'ELV-MAT-001',
            'nom' => 'KAMGA',
            'prenom' => 'Chloé',
            'sexe' => 'F',
            'date_naissance' => '2021-05-10',
        ]);

        $inscriptionMat = Inscription::create([
            'eleve_id' => $eleveMat->id,
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $classeMat->id,
            'etablissement_id' => $etabPedagogique->id,
            'statut' => 'validee',
            'type_inscription' => 'nouveau',
        ]);

        // Vérification préalable : aucun compte financier n'existe avant l'appel
        $this->assertDatabaseMissing('comptes_financiers_eleves', [
            'inscription_id' => $inscriptionMat->id,
        ]);

        $capturedViewData = null;
        view()->composer('finances.fiche_compte_eleve', function ($view) use (&$capturedViewData): void {
            $capturedViewData = $view->getData();
        });

        $response = $this->actingAs($this->adminUser)->get(
            route('administration.documents.situation-financiere', $inscriptionMat)
        );

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertIsArray($capturedViewData);

        $html = view('finances.fiche_compte_eleve', $capturedViewData)->render();
        $this->assertStringContainsString('Grille tarifaire non encore configurée pour ce niveau', $html);
        $this->assertStringContainsString('Nursery One', $html);
        $this->assertStringContainsString('TARIFICATION NON CONFIGURÉE', $html);
        $this->assertStringNotContainsString('TOTALEMENT SOLDÉ (100%)', $html);
    }

    /**
     * Vérifie que la modification d'un versement existant (ex: 15 000 FCFA corrigé en 25 000 FCFA)
     * conserve le numéro de reçu, recalcule automatiquement la ventilation par tranches,
     * met à jour le compte financier de l'élève et répercute le nouveau montant dans les états financiers.
     */
    public function test_modification_versement_recalcule_automatiquement_ventilations_dossier_eleve_et_etats_financiers(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        // Versement initial saisi par erreur à 15 000 FCFA au lieu de 25 000 FCFA
        $versement = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 15000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'KAMDEM Paul',
            'ventilations' => [
                [
                    'frais_scolaire_id' => $this->fraisScolaire->id,
                    'type_frais_id' => $this->typeFrais->id,
                    'libelle' => 'Tranche 1 - Scolarité',
                    'montant' => 15000,
                ],
            ],
        ], $this->caissier, $this->adminUser);

        $referenceInitiale = $versement->reference_recu;
        $compte->refresh();
        $this->assertEquals(15000.0, (float) $compte->total_paye);
        $this->assertEquals(85000.0, (float) $compte->solde_restant);

        // Modification du versement à 25 000 FCFA via l'endpoint dédié
        $response = $this->actingAs($this->adminUser)->put(
            route('administration.finances.versements.update', $versement),
            [
                'montant_total' => 25000,
                'date_versement' => Carbon::today()->toDateString(),
                'mode_paiement' => 'mobile_money_manuel',
                'reference_paiement' => 'OM-2026-9988',
                'payeur_nom' => 'KAMDEM Paul (Corrigé)',
                'mode_allocation' => 'auto',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $versement->refresh();
        $compte->refresh();

        // Le numéro de reçu reste intact, le montant et les ventilations sont mis à jour
        $this->assertSame($referenceInitiale, $versement->reference_recu);
        $this->assertEquals(25000.0, (float) $versement->montant_total);
        $this->assertSame('mobile_money_manuel', $versement->mode_paiement);
        $this->assertSame('OM-2026-9988', $versement->reference_paiement);
        $this->assertSame('KAMDEM Paul (Corrigé)', $versement->payeur_nom);
        $this->assertEquals(25000.0, (float) $versement->ventilations()->sum('montant'));

        // Le compte financier de l'élève est immédiatement mis à jour
        $this->assertEquals(25000.0, (float) $compte->total_paye);
        $this->assertEquals(75000.0, (float) $compte->solde_restant);

        // Vérification de la mise à jour dans le dossier élève JSON et les états financiers
        $dossierJson = $this->actingAs($this->adminUser)
            ->getJson(route('administration.finances.comptes.dossier', $compte))
            ->assertOk()
            ->json();

        $this->assertEquals(25000.0, (float) $dossierJson['total_paye']);
        $this->assertEquals(75000.0, (float) $dossierJson['solde_restant']);
        $this->assertTrue((bool) $dossierJson['can_edit_versements']);

        $releve = $financeService->obtenirReleveIndividuelEleve((int) $compte->eleve_id, (int) $compte->annee_scolaire_id);
        $this->assertEquals(25000.0, (float) $releve['total_verse']);
        $this->assertEquals(75000.0, (float) $releve['solde_restant']);
    }

    /**
     * Vérifie que seul un utilisateur disposant du droit `versements.edit` (ou `finances.manage`)
     * peut modifier un versement existant.
     */
    public function test_modification_versement_exige_la_permission_versements_edit(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $versement = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 15000,
            'mode_paiement' => 'especes',
            'ventilations' => [
                [
                    'frais_scolaire_id' => $this->fraisScolaire->id,
                    'type_frais_id' => $this->typeFrais->id,
                    'libelle' => 'Tranche 1 - Scolarité',
                    'montant' => 15000,
                ],
            ],
        ], $this->caissier, $this->adminUser);

        // Secrétaire / Caissier ayant uniquement le droit d'encaisser (sans versements.edit)
        $secretaire = User::factory()->create([
            'is_active' => true,
            'etablissement_id' => $this->etablissement->id,
        ]);
        $secretaire->givePermissionTo(['finances.view', 'versements.create']);

        $this->actingAs($secretaire)
            ->put(route('administration.finances.versements.update', $versement), [
                'montant_total' => 25000,
                'mode_paiement' => 'especes',
            ])
            ->assertStatus(403);

        $this->assertEquals(15000.0, (float) $versement->fresh()->montant_total);

        // Activation du droit `versements.edit` pour cet utilisateur
        $secretaire->givePermissionTo('versements.edit');

        $this->actingAs($secretaire)
            ->put(route('administration.finances.versements.update', $versement), [
                'montant_total' => 25000,
                'mode_paiement' => 'especes',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(25000.0, (float) $versement->fresh()->montant_total);
        $this->assertEquals(25000.0, (float) $compte->fresh()->total_paye);
    }

    /**
     * Vérifie que la modification d'un versement est rejetée si le nouveau montant dépasse le plafond
     * exigible de l'élève, sans altérer le versement initial (atomicité transactionnelle).
     */
    public function test_modification_versement_refuse_montant_superieur_au_reste_du(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        $versement = $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 15000,
            'mode_paiement' => 'especes',
            'ventilations' => [
                [
                    'frais_scolaire_id' => $this->fraisScolaire->id,
                    'type_frais_id' => $this->typeFrais->id,
                    'libelle' => 'Tranche 1 - Scolarité',
                    'montant' => 15000,
                ],
            ],
        ], $this->caissier, $this->adminUser);

        $response = $this->actingAs($this->adminUser)->put(
            route('administration.finances.versements.update', $versement),
            [
                'montant_total' => 150000, // Supérieur aux 100 000 FCFA exigibles
                'mode_paiement' => 'especes',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');

        // Le versement initial de 15 000 FCFA et ses ventilations sont intégralement préservés
        $versement->refresh();
        $compte->refresh();
        $this->assertEquals(15000.0, (float) $versement->montant_total);
        $this->assertEquals(15000.0, (float) $versement->ventilations()->sum('montant'));
        $this->assertEquals(15000.0, (float) $compte->total_paye);
        $this->assertEquals(85000.0, (float) $compte->solde_restant);
    }

    /**
     * Vérifie que les bilans (détaillé et résumé) intègrent et détaillent les frais annexes/optionnels
     * dans le cumul exigible afin que (Exigible - Réduction - Versé = Solde Dû) soit toujours exact.
     */
    public function test_bilans_incluent_et_detaillent_les_frais_annexes_optionnels_dans_lexigible(): void
    {
        $financeService = app(FinanceService::class);
        $compte = $financeService->initialiserCompteEleve($this->inscription);

        // Ajout d'un frais optionnel de 85 000 FCFA (ex: Frais de Bus)
        $typeBus = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'BUS-OPT-TEST',
            'libelle' => 'FRAIS BUS',
            'obligatoire' => false,
            'ordre_priorite' => 5,
            'periodicite' => 'annuel',
            'montant_defaut' => 85000,
        ]);

        $fraisBus = FraisScolaire::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $typeBus->id,
            'tranche_numero' => 1,
            'ordre_priorite' => 5,
            'libelle_tranche' => 'FRAIS BUS',
            'montant' => 85000,
            'obligatoire' => false,
            'date_limite' => Carbon::today()->addMonth()->toDateString(),
        ]);

        SouscriptionFraisOptionnel::create([
            'compte_financier_id' => $compte->id,
            'frais_scolaire_id' => $fraisBus->id,
            'montant' => 85000,
            'date_souscription' => Carbon::today()->toDateString(),
            'statut' => 'actif',
        ]);
        $compte->actualiserSolde();

        // Versement partiel de 10 000 FCFA
        $financeService->enregistrerVersement([
            'compte_financier_id' => $compte->id,
            'montant_total' => 10000,
            'mode_paiement' => 'especes',
            'ventilations' => [
                [
                    'frais_scolaire_id' => $this->fraisScolaire->id,
                    'type_frais_id' => $this->typeFrais->id,
                    'libelle' => 'Tranche 1 - Scolarité',
                    'montant' => 10000,
                ],
            ],
        ], $this->caissier, $this->adminUser);

        $bilanDetaille = $financeService->obtenirBilanDetailleVersements($this->etablissement->id, $this->annee->id);
        $ligne = $bilanDetaille['eleves'][0];

        $this->assertEquals(100000.0, $ligne['scolarite_base']);
        $this->assertEquals(85000.0, $ligne['frais_annexes']);
        $this->assertEquals(185000.0, $ligne['a_verser']);
        $this->assertEquals(10000.0, $ligne['verse']);
        $this->assertEquals(175000.0, $ligne['reste']);
        $this->assertStringContainsString('FRAIS BUS', $ligne['details_annexes']);
        $this->assertEquals(
            $bilanDetaille['total_a_verser'] - $bilanDetaille['total_reduction'] - $bilanDetaille['total_verse'],
            $bilanDetaille['total_reste']
        );

        $bilanResume = $financeService->obtenirBilanResumeRecouvrement($this->etablissement->id, $this->annee->id);
        $this->assertEquals(185000.0, $bilanResume['total_a_verser']);
        $this->assertEquals(175000.0, $bilanResume['total_reste']);

        // Vérifie le rendu Blade conditionnel : colonne « Frais Annexes » présente avec options, masquée sans options
        $htmlAvecAnnexes = view('finances.bilan_detaille', array_merge($bilanDetaille, [
            'annee' => $this->annee,
            'etablissement' => $this->etablissement,
        ]))->render();
        $this->assertStringContainsString('Frais Annexes', $htmlAvecAnnexes);
        $this->assertStringContainsString('FRAIS BUS', $htmlAvecAnnexes);

        // Sans frais annexes dans la classe -> la colonne Frais Annexes n'est pas affichée
        $bilanSansAnnexes = $bilanDetaille;
        $bilanSansAnnexes['total_frais_annexes'] = 0.0;
        $bilanSansAnnexes['classes'][0]['total_frais_annexes'] = 0.0;
        $bilanSansAnnexes['classes'][0]['eleves'][0]['frais_annexes'] = 0.0;
        $bilanSansAnnexes['classes'][0]['eleves'][0]['details_annexes'] = '';

        $htmlSansAnnexes = view('finances.bilan_detaille', array_merge($bilanSansAnnexes, [
            'annee' => $this->annee,
            'etablissement' => $this->etablissement,
        ]))->render();
        $this->assertStringNotContainsString('Frais Annexes', $htmlSansAnnexes);
        $this->assertStringContainsString('Montant Exigible', $htmlSansAnnexes);
    }
}

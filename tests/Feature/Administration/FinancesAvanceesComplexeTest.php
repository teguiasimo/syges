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
use App\Models\DispenseFinanciere;
use App\Models\Eleve;
use App\Models\EleveBourseRemise;
use App\Models\Etablissement;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\SouscriptionFraisOptionnel;
use App\Models\TypeFrais;
use App\Models\User;
use App\Models\VersementCaisse;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests fonctionnels pour les extensions financières destinées aux complexes scolaires :
 * - Gestion des Frais Optionnels & Souscriptions (Bus, Cantine, Internat)
 * - Dossier Financier & Échéancier dynamique de l'élève
 * - Règle de solvabilité temporelle par date d'échéance échue
 * - Éditions & Rapports PDF Officiels (Journaux, Insolvables, En Règle, Fiche parent)
 */
class FinancesAvanceesComplexeTest extends TestCase
{
    use RefreshDatabase;

    public ?User $adminUser = null;

    public ?Etablissement $etablissement = null;

    public ?AnneeScolaire $anneeScolaire = null;

    public ?Classe $classe = null;

    public ?Eleve $eleve = null;

    public ?Inscription $inscription = null;

    public ?CompteFinancierEleve $compte = null;

    public ?FraisScolaire $fraisObligatoire = null;

    public ?FraisScolaire $fraisOptionnelBus = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions RBAC
        Permission::firstOrCreate(['name' => 'bourses.manage', 'guard_name' => 'web']);
        foreach (['finances.view', 'finances.manage', 'finances.cloture', 'finances.annuler', 'versements.create', 'versements.cancel'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $institution = Institution::create([
            'nom' => 'Groupe Scolaire Test',
            'code' => 'GST',
        ]);

        $this->etablissement = Etablissement::create([
            'institution_id' => $institution->id,
            'nom' => 'Collège & Lycée d\'Excellence',
            'code' => 'CLE',
            'type' => 'secondaire_general',
        ]);

        $section = Section::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Section Francophone',
            'code' => 'FR',
        ]);

        $cycle = Cycle::create([
            'section_id' => $section->id,
            'nom' => '1er Cycle',
            'code' => 'C1',
            'ordre' => 1,
        ]);

        $niveau = Niveau::create([
            'cycle_id' => $cycle->id,
            'etablissement_id' => $this->etablissement->id,
            'nom' => '6ème',
            'code' => '6EME',
            'ordre' => 1,
        ]);

        $this->classe = Classe::create([
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveau->id,
            'nom' => '6ème A',
            'code' => '6A',
        ]);

        $this->anneeScolaire = AnneeScolaire::create([
            'code' => '2026-2027',
            'nom' => 'Année 2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'is_current' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'etablissement_id' => $this->etablissement->id,
        ]);
        $this->adminUser->assignRole($superAdminRole);

        // Frais obligatoire (Scolarité Tranche 1)
        $typeObligatoire = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'SCOL-T1',
            'libelle' => 'Scolarité Tranche 1',
            'obligatoire' => true,
            'periodicite' => 'annuel',
            'montant_defaut' => 50000,
        ]);

        $this->fraisObligatoire = FraisScolaire::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveau->id,
            'type_frais_id' => $typeObligatoire->id,
            'tranche_numero' => 1,
            'libelle_tranche' => 'Tranche 1 - Rentrée',
            'montant' => 50000,
            'date_limite' => Carbon::now()->subDays(10)->toDateString(), // Échue
        ]);

        // Frais optionnel (Transport Bus)
        $typeOptionnel = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'BUS-L1',
            'libelle' => 'Transport Scolaire Ligne 1',
            'obligatoire' => false,
            'periodicite' => 'mensuel',
            'montant_defaut' => 20000,
        ]);

        $this->fraisOptionnelBus = FraisScolaire::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveau->id,
            'type_frais_id' => $typeOptionnel->id,
            'tranche_numero' => 1,
            'libelle_tranche' => 'Bus Mensuel',
            'montant' => 20000,
            'date_limite' => Carbon::now()->subDays(5)->toDateString(),
        ]);

        // Élève et inscription
        $this->eleve = Eleve::create([
            'etablissement_id' => $this->etablissement->id,
            'matricule' => 'ELEVE-TEST-001',
            'nom' => 'KOUAM',
            'prenom' => 'Michel',
            'sexe' => 'M',
            'date_naissance' => '2014-05-12',
            'telephone_parent' => '699112233',
        ]);

        $this->inscription = Inscription::create([
            'eleve_id' => $this->eleve->id,
            'classe_id' => $this->classe->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => Carbon::now()->subDays(20),
            'statut' => 'validee',
        ]);

        $this->compte = CompteFinancierEleve::create([
            'inscription_id' => $this->inscription->id,
            'eleve_id' => $this->eleve->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 50000,
            'montant_net_exigible' => 50000,
            'total_paye' => 0,
            'solde_restant' => 50000,
            'statut_financier' => 'insolvable',
        ]);
    }

    /**
     * Teste que l'index finance charge les frais optionnels dans les props.
     */
    public function test_index_finances_inclut_les_frais_optionnels(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/administration/finances');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('administration/finances/index')
            ->has('fraisOptionnels')
        );
    }

    /**
     * Teste la souscription à un frais optionnel et le recalcul automatique du compte élève.
     */
    public function test_souscription_frais_optionnel_actualise_solde_eleve(): void
    {
        $this->assertEquals(50000, $this->compte->montant_net_exigible);

        // Souscription au bus (20 000 FCFA)
        $response = $this->actingAs($this->adminUser)->post('/administration/finances/souscriptions-optionnelles', [
            'compte_financier_id' => $this->compte->id,
            'frais_scolaire_id' => $this->fraisOptionnelBus->id,
            'montant' => 20000,
            'observations' => 'Arrêt Carrefour Ange Raphaël',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->compte->refresh();

        // Le montant net exigible doit être passé à 50 000 + 20 000 = 70 000 FCFA
        $this->assertEquals(70000, $this->compte->montant_net_exigible);
        $this->assertEquals(70000, $this->compte->solde_restant);

        $souscription = SouscriptionFraisOptionnel::where('compte_financier_id', $this->compte->id)->first();
        $this->assertNotNull($souscription);
        $this->assertEquals('actif', $souscription->statut);

        // Résiliation de la souscription
        $deleteResponse = $this->actingAs($this->adminUser)->delete("/administration/finances/souscriptions-optionnelles/{$souscription->id}");
        $deleteResponse->assertRedirect();

        $this->compte->refresh();
        $this->assertEquals(50000, $this->compte->montant_net_exigible);
        $this->assertEquals(50000, $this->compte->solde_restant);
    }

    /**
     * Teste l'API JSON du dossier financier et de l'échéancier prévisionnel de l'élève.
     */
    public function test_api_dossier_financier_eleve_retourne_echeancier(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson("/administration/finances/comptes/{$this->compte->id}/dossier");

        $response->assertOk();
        $response->assertJsonStructure([
            'lignes',
            'total_exigible',
            'total_paye',
            'solde_restant',
            'montant_echu_en_retard',
            'statut_financier',
        ]);

        $this->assertEquals(50000, $response->json('total_exigible'));
        $this->assertEquals(50000, $response->json('montant_echu_en_retard'));
    }

    /**
     * Teste la génération de la Fiche Individuelle de Situation Financière (PDF Parent).
     */
    public function test_telechargement_fiche_compte_eleve_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)->get("/administration/finances/comptes/{$this->compte->id}/fiche-pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }

    /**
     * Teste la génération du Grand Journal Détaillé des Encaissements en PDF.
     */
    public function test_telechargement_journal_detaille_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/administration/finances/etats/journal-detaille-pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }

    /**
     * Teste la génération du Journal Synthétique de Caisse en PDF.
     */
    public function test_telechargement_journal_synthetique_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/administration/finances/etats/journal-synthetique-pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }

    /**
     * Teste la génération de l'État Nominatif des Élèves Insolvables en PDF.
     */
    public function test_telechargement_liste_insolvables_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/administration/finances/etats/insolvables-pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }

    /**
     * Teste la génération de l'État des Élèves en Règle en PDF.
     */
    public function test_telechargement_liste_en_regle_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/administration/finances/etats/en-regle-pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }

    /**
     * Teste la suite officielle des 8 états financiers inspirés des standards scolaires camerounais.
     */
    public function test_telechargement_suite_officielle_8_etats_financiers_pdf(): void
    {
        // 01. Journal des versements
        $res1 = $this->actingAs($this->adminUser)->get('/administration/finances/etats/journal-versements-pdf');
        $res1->assertOk();
        $this->assertStringContainsString('application/pdf', $res1->headers->get('content-type') ?? '');

        // 02. Journal résumé des versements
        $res2 = $this->actingAs($this->adminUser)->get('/administration/finances/etats/journal-resume-pdf');
        $res2->assertOk();
        $this->assertStringContainsString('application/pdf', $res2->headers->get('content-type') ?? '');

        // 03. Journal des versements par catégories
        $res3 = $this->actingAs($this->adminUser)->get('/administration/finances/etats/journal-categories-pdf');
        $res3->assertOk();
        $this->assertStringContainsString('application/pdf', $res3->headers->get('content-type') ?? '');

        // 04. Bilan résumé des recouvrements
        $res4 = $this->actingAs($this->adminUser)->get('/administration/finances/etats/bilan-resume-pdf');
        $res4->assertOk();
        $this->assertStringContainsString('application/pdf', $res4->headers->get('content-type') ?? '');

        // 05. Bilan détaillé des versements
        $res5 = $this->actingAs($this->adminUser)->get('/administration/finances/etats/bilan-detaille-pdf');
        $res5->assertOk();
        $this->assertStringContainsString('application/pdf', $res5->headers->get('content-type') ?? '');

        // 06. Bilan des réductions et bourses
        $res6 = $this->actingAs($this->adminUser)->get('/administration/finances/etats/bilan-reductions-pdf');
        $res6->assertOk();
        $this->assertStringContainsString('application/pdf', $res6->headers->get('content-type') ?? '');

        // 07. Liste des insolvables (enrichie)
        $res7 = $this->actingAs($this->adminUser)->get('/administration/finances/etats/insolvables-pdf');
        $res7->assertOk();
        $this->assertStringContainsString('application/pdf', $res7->headers->get('content-type') ?? '');

        // 08. Relevé individuel des versements de l'élève
        $res8 = $this->actingAs($this->adminUser)->get("/administration/finances/etats/releve-eleve-pdf?eleve_id={$this->eleve->id}");
        $res8->assertOk();
        $this->assertStringContainsString('application/pdf', $res8->headers->get('content-type') ?? '');
    }

    /**
     * Teste la vision consolidée multi-campus « Complexe Scolaire Global » (etablissement_id = all).
     */
    public function test_etats_financiers_mode_complexe_scolaire_global(): void
    {
        // En tant que SuperAdmin, la vision 'all' consolide tous les établissements du complexe
        $resPage = $this->actingAs($this->adminUser)->get('/administration/finances/etats?etablissement_id=all');
        $resPage->assertOk();

        // Édition consolidée du Bilan Résumé pour l'ensemble du complexe scolaire
        $resPdf = $this->actingAs($this->adminUser)->get('/administration/finances/etats/bilan-resume-pdf?etablissement_id=all');
        $resPdf->assertOk();
        $this->assertStringContainsString('application/pdf', $resPdf->headers->get('content-type') ?? '');
    }

    /**
     * Teste que la page dédiée « Guichet Scolarité & Dossiers Élèves » synchronise automatiquement
     * tous les élèves inscrits qui n'avaient pas encore de compte financier initialisé.
     */
    public function test_page_dediee_scolarites_synchronise_automatiquement_les_inscriptions_sans_compte(): void
    {
        $nouvelEleve = Eleve::create([
            'etablissement_id' => $this->etablissement->id,
            'matricule' => 'ELEVE-SYNC-002',
            'nom' => 'NDJOCK',
            'prenom' => 'Clarisse',
            'sexe' => 'F',
            'telephone_parent' => '677001122',
        ]);

        // Inscription créée sans initialiser manuellement le CompteFinancierEleve (cas d'import CSV historique)
        $nouvelleInscription = Inscription::create([
            'eleve_id' => $nouvelEleve->id,
            'classe_id' => $this->classe->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => Carbon::now()->toDateString(),
            'statut' => 'validee',
        ]);

        $this->assertDatabaseMissing('comptes_financiers_eleves', [
            'inscription_id' => $nouvelleInscription->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/administration/finances/scolarites');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('administration/finances/scolarites')
            ->has('comptes', 2)
        );

        $this->assertDatabaseHas('comptes_financiers_eleves', [
            'inscription_id' => $nouvelleInscription->id,
            'debit_initial' => 50000,
            'solde_restant' => 50000,
        ]);
    }

    /**
     * Teste la création d'une catégorie personnalisée de recette diverse et l'encaissement
     * sans sélectionner de caisse (résolution automatique de la Caisse Principale).
     */
    public function test_categories_personnalisees_recettes_diverses_et_encaissement_sans_caisse(): void
    {
        $catResponse = $this->actingAs($this->adminUser)->post('/administration/finances/categories-recettes', [
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Vente de cahiers de liaison',
        ]);
        $catResponse->assertRedirect();

        $this->assertDatabaseHas('categories_recettes_diverses', [
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Vente de cahiers de liaison',
        ]);

        // Encaissement sans fournir caisse_id
        $regResponse = $this->actingAs($this->adminUser)->post('/administration/finances/reglements-divers', [
            'etablissement_id' => $this->etablissement->id,
            'payeur_nom' => 'Mme MBARGA',
            'categorie' => 'Vente de cahiers de liaison',
            'libelle' => '2 cahiers de liaison',
            'montant' => 2500,
            'mode_paiement' => 'especes',
        ]);

        $regResponse->assertRedirect();
        $regResponse->assertSessionHas('success');

        $this->assertDatabaseHas('reglements_divers', [
            'etablissement_id' => $this->etablissement->id,
            'categorie' => 'Vente de cahiers de liaison',
            'montant' => 2500,
        ]);

        // Vérifie que la Caisse Principale a été créée automatiquement en arrière-plan
        $this->assertDatabaseHas('caisses', [
            'etablissement_id' => $this->etablissement->id,
            'is_principale' => true,
        ]);
    }

    /**
     * Teste qu'une remise individuelle (ex: Paiement en avance -3 000 FCFA) créée sans code manuel
     * s'impute automatiquement sur la dernière tranche de l'élève.
     */
    public function test_remise_paiement_en_avance_s_impute_sur_la_derniere_tranche(): void
    {
        // Ajout d'une 2ème tranche obligatoire de 20 000 FCFA
        $this->actingAs($this->adminUser)->post('/administration/finances/tarifs', [
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $this->fraisObligatoire->type_frais_id,
            'tranche_numero' => 2,
            'libelle_tranche' => 'Tranche 2 - Finale',
            'montant' => 20000,
            'date_limite' => Carbon::now()->addMonths(2)->toDateString(),
        ])->assertRedirect();

        $this->compte->refresh();
        $this->assertEquals(70000, (float) $this->compte->montant_net_exigible);

        // Création d'un modèle de remise épuré (sans code, sans niveau_id, catégorie personnalisable)
        $this->actingAs($this->adminUser)->post('/administration/finances/bourses-remises', [
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'nom' => 'Bonus paiement avant la rentrée',
            'type' => 'remise',
            'categorie' => 'Paiement en avance (avant rentrée)',
            'mode_calcul' => 'montant_fixe',
            'valeur' => 3000,
        ])->assertRedirect();

        $bourse = BourseRemise::where('nom', 'Bonus paiement avant la rentrée')->firstOrFail();

        // Attribution individuelle à cet élève
        $this->actingAs($this->adminUser)->post('/administration/finances/bourses-remises/attribuer', [
            'compte_financier_id' => $this->compte->id,
            'bourse_remise_id' => $bourse->id,
        ])->assertRedirect();

        $dossier = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk()
            ->json();

        $this->assertEquals(67000, $dossier['total_exigible']);
        $this->assertCount(2, $dossier['lignes']);
        // Tranche 1 reste à 50 000 FCFA
        $this->assertEquals(50000, $dossier['lignes'][0]['montant']);
        $this->assertEquals(0, $dossier['lignes'][0]['remise_appliquee']);
        // Tranche 2 (dernière tranche) passe de 20 000 à 17 000 FCFA (-3 000 FCFA déduits)
        $this->assertEquals(17000, $dossier['lignes'][1]['montant']);
        $this->assertEquals(3000, $dossier['lignes'][1]['remise_appliquee']);
    }

    /**
     * Teste que la modification d'une ligne de la Grille Tarifaire recalcule automatiquement
     * les comptes financiers des élèves du niveau concerné.
     */
    public function test_modification_tarif_recalcule_automatiquement_les_comptes_du_niveau(): void
    {
        $response = $this->actingAs($this->adminUser)->put("/administration/finances/tarifs/{$this->fraisObligatoire->id}", [
            'niveau_id' => $this->fraisObligatoire->niveau_id,
            'type_frais_id' => $this->fraisObligatoire->type_frais_id,
            'tranche_numero' => 1,
            'libelle_tranche' => 'Tranche 1 Révisée',
            'montant' => 65000,
            'date_limite' => Carbon::now()->addDays(15)->toDateString(),
        ]);

        $response->assertRedirect();
        $this->compte->refresh();

        $this->assertEquals(65000, (float) $this->compte->debit_initial);
        $this->assertEquals(65000, (float) $this->compte->montant_net_exigible);
        $this->assertEquals(65000, (float) $this->compte->solde_restant);
    }

    /**
     * Teste que l'ordre de priorité configurable par établissement (ex: APEE #1 -> Inscription #2 -> Scolarité #3)
     * gouverne automatiquement la cascade d'affectation d'un versement partiel.
     */
    public function test_ordre_de_priorite_configurable_coupe_apee_puis_inscription_puis_scolarite(): void
    {
        // Le frais Scolarité existant passe en priorité #3
        $this->fraisObligatoire->typeFrais->update(['ordre_priorite' => 3]);

        // Création de l'APEE en priorité #1 (5 000 FCFA) et de l'Inscription en priorité #2 (15 000 FCFA)
        $typeApee = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'APEE',
            'libelle' => 'Cotisation APEE',
            'obligatoire' => true,
            'periodicite' => 'annuel',
            'ordre_priorite' => 1,
            'montant_defaut' => 5000,
        ]);

        $typeInsc = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'INSC',
            'libelle' => 'Frais d\'Inscription',
            'obligatoire' => true,
            'periodicite' => 'annuel',
            'ordre_priorite' => 2,
            'montant_defaut' => 15000,
        ]);

        $this->actingAs($this->adminUser)->post('/administration/finances/tarifs', [
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $typeInsc->id,
            'tranche_numero' => 1,
            'libelle_tranche' => 'Inscription annuelle',
            'montant' => 15000,
        ])->assertRedirect();

        $this->actingAs($this->adminUser)->post('/administration/finances/tarifs', [
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $typeApee->id,
            'tranche_numero' => 1,
            'libelle_tranche' => 'APEE annuelle',
            'montant' => 5000,
        ])->assertRedirect();

        // Le parent verse 30 000 FCFA sans ciblage spécifique (cascade automatique par priorité)
        $this->actingAs($this->adminUser)->post('/administration/finances/versements', [
            'compte_financier_id' => $this->compte->id,
            'montant_total' => 30000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'M. KOUAM',
            'ventilations' => [
                [
                    'libelle' => 'Versement rentrée',
                    'montant' => 30000,
                    'type_frais_id' => null,
                    'frais_scolaire_id' => null,
                ],
            ],
        ])->assertRedirect();

        $dossier = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk()
            ->json();

        // Ordre strict attendu : 0 = APEE (5 000 soldé), 1 = Inscription (15 000 soldé), 2 = Scolarité T1 (10 000 payé / 40 000 restant)
        $this->assertCount(3, $dossier['lignes']);
        $this->assertEquals('APEE annuelle', $dossier['lignes'][0]['libelle']);
        $this->assertEquals(5000, $dossier['lignes'][0]['montant_paye']);
        $this->assertEquals('solde', $dossier['lignes'][0]['statut']);

        $this->assertEquals('Inscription annuelle', $dossier['lignes'][1]['libelle']);
        $this->assertEquals(15000, $dossier['lignes'][1]['montant_paye']);
        $this->assertEquals('solde', $dossier['lignes'][1]['statut']);

        $this->assertEquals('Tranche 1 - Rentrée', $dossier['lignes'][2]['libelle']);
        $this->assertEquals(10000, $dossier['lignes'][2]['montant_paye']);
        $this->assertEquals(40000, $dossier['lignes'][2]['montant_restant']);
    }

    /**
     * Teste le mode « Cibler manuellement » au guichet : un parent venant régler uniquement le Bus
     * solde la ligne Bus sans entamer la Tranche 1 obligatoire impayée.
     */
    public function test_encaissement_cible_manuellement_regle_le_bus_sans_entamer_la_tranche_obligatoire(): void
    {
        // Souscription de l'élève au Bus (20 000 FCFA)
        $this->actingAs($this->adminUser)->post('/administration/finances/souscriptions-optionnelles', [
            'compte_financier_id' => $this->compte->id,
            'frais_scolaire_id' => $this->fraisOptionnelBus->id,
            'montant' => 20000,
        ])->assertRedirect();

        // Paiement ciblé explicitement sur le frais_scolaire_id du Bus
        $this->actingAs($this->adminUser)->post('/administration/finances/versements', [
            'compte_financier_id' => $this->compte->id,
            'montant_total' => 20000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'Mme KOUAM',
            'ventilations' => [
                [
                    'libelle' => 'Transport Scolaire Ligne 1',
                    'montant' => 20000,
                    'type_frais_id' => $this->fraisOptionnelBus->type_frais_id,
                    'frais_scolaire_id' => $this->fraisOptionnelBus->id,
                ],
            ],
        ])->assertRedirect();

        $dossier = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk()
            ->json();

        // Ligne 0 (Tranche 1 obligatoire) reste intégralement due (50 000 FCFA)
        $this->assertEquals(0, $dossier['lignes'][0]['montant_paye']);
        $this->assertEquals(50000, $dossier['lignes'][0]['montant_restant']);

        // Ligne 1 (Bus optionnel) est soldée à 100 %
        $this->assertEquals('optionnel', $dossier['lignes'][1]['type']);
        $this->assertEquals(20000, $dossier['lignes'][1]['montant_paye']);
        $this->assertEquals(0, $dossier['lignes'][1]['montant_restant']);
        $this->assertEquals('solde', $dossier['lignes'][1]['statut']);
    }

    /**
     * Teste qu'une remise peut être configurée pour s'imputer sur la 1ère ou la 2ème tranche
     * (au lieu de la dernière tranche), tant sur le modèle que lors de l'attribution individuelle.
     */
    public function test_cible_imputation_configurable_deduit_la_remise_sur_premiere_ou_deuxieme_tranche(): void
    {
        // Ajout des Tranches 2 (30 000) et 3 (20 000)
        $this->actingAs($this->adminUser)->post('/administration/finances/tarifs', [
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $this->fraisObligatoire->type_frais_id,
            'tranche_numero' => 2,
            'libelle_tranche' => 'Tranche 2',
            'montant' => 30000,
        ])->assertRedirect();

        $this->actingAs($this->adminUser)->post('/administration/finances/tarifs', [
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $this->fraisObligatoire->type_frais_id,
            'tranche_numero' => 3,
            'libelle_tranche' => 'Tranche 3',
            'montant' => 20000,
        ])->assertRedirect();

        // Modèle configuré par défaut sur `tranche_1` (-5 000 FCFA)
        $this->actingAs($this->adminUser)->post('/administration/finances/bourses-remises', [
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'nom' => 'Remise Inscription Rapide T1',
            'type' => 'remise',
            'categorie' => 'Paiement en avance',
            'mode_calcul' => 'montant_fixe',
            'valeur' => 5000,
            'cible_imputation' => 'tranche_1',
        ])->assertRedirect();

        $bourse = BourseRemise::where('nom', 'Remise Inscription Rapide T1')->firstOrFail();
        $this->assertEquals('tranche_1', $bourse->cible_imputation);

        // 1) Attribution selon le modèle (`tranche_1`) -> Tranche 1 passe de 50 000 à 45 000
        $this->actingAs($this->adminUser)->post('/administration/finances/bourses-remises/attribuer', [
            'compte_financier_id' => $this->compte->id,
            'bourse_remise_id' => $bourse->id,
        ])->assertRedirect();

        $dossierT1 = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk()
            ->json();

        $this->assertEquals(45000, $dossierT1['lignes'][0]['montant']);
        $this->assertEquals(5000, $dossierT1['lignes'][0]['remise_appliquee']);
        $this->assertEquals(30000, $dossierT1['lignes'][1]['montant']);
        $this->assertEquals(20000, $dossierT1['lignes'][2]['montant']);

        // 2) Surcharge individuelle sur `tranche_2` pour cet enfant -> Tranche 2 passe de 30 000 à 25 000
        $this->actingAs($this->adminUser)->post('/administration/finances/bourses-remises/attribuer', [
            'compte_financier_id' => $this->compte->id,
            'bourse_remise_id' => $bourse->id,
            'cible_imputation' => 'tranche_2',
        ])->assertRedirect();

        $dossierT2 = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk()
            ->json();

        $this->assertEquals(50000, $dossierT2['lignes'][0]['montant']);
        $this->assertEquals(25000, $dossierT2['lignes'][1]['montant']);
        $this->assertEquals(5000, $dossierT2['lignes'][1]['remise_appliquee']);
        $this->assertEquals(20000, $dossierT2['lignes'][2]['montant']);
    }

    /**
     * Teste la dispense financière (totale 100 % puis partielle) pour un élève arrivé au 2ème trimestre :
     * la Tranche 1 échue passe en statut `dispense`, le retard échu tombe à 0 FCFA, et `remise_bourse` reste distinct.
     */
    public function test_dispense_financiere_totale_et_partielle_eleve_inscrit_deuxieme_trimestre(): void
    {
        // Ajout de la Tranche 2 (non échue : 30 000 FCFA)
        $this->actingAs($this->adminUser)->post('/administration/finances/tarifs', [
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $this->fraisObligatoire->type_frais_id,
            'tranche_numero' => 2,
            'libelle_tranche' => 'Tranche 2 - 2ème Trimestre',
            'montant' => 30000,
            'date_limite' => Carbon::now()->addMonth()->toDateString(),
        ])->assertRedirect();

        // Avant dispense : Tranche 1 (50 000) est échue en retard, total exigible = 80 000
        $avant = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk()
            ->json();
        $this->assertEquals(80000, $avant['total_exigible']);
        $this->assertEquals(50000, $avant['montant_echu_en_retard']);

        // 1) Octroi d'une dispense TOTALE (100 %) sur la Tranche 1 (élève arrivé au 2ème trimestre)
        $this->actingAs($this->adminUser)->post('/administration/finances/dispenses', [
            'compte_financier_id' => $this->compte->id,
            'frais_scolaire_id' => $this->fraisObligatoire->id,
            'type_dispense' => 'totale',
            'motif' => 'Arrivée au 2ème trimestre',
        ])->assertRedirect();

        $this->compte->refresh();
        $this->assertEquals(50000, (float) $this->compte->total_dispense);
        $this->assertEquals(0, (float) $this->compte->remise_bourse);
        $this->assertEquals(30000, (float) $this->compte->montant_net_exigible);
        $this->assertEquals(30000, (float) $this->compte->solde_restant);

        $apresTotale = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk()
            ->json();

        $this->assertEquals('dispense', $apresTotale['lignes'][0]['statut']);
        $this->assertEquals(0, $apresTotale['lignes'][0]['montant']);
        $this->assertEquals(50000, $apresTotale['lignes'][0]['dispense_appliquee']);
        $this->assertEquals(0, $apresTotale['montant_echu_en_retard']);

        // 2) Modification en dispense PARTIELLE (20 000 FCFA exonérés sur les 50 000 FCFA)
        $this->actingAs($this->adminUser)->post('/administration/finances/dispenses', [
            'compte_financier_id' => $this->compte->id,
            'frais_scolaire_id' => $this->fraisObligatoire->id,
            'type_dispense' => 'partielle',
            'montant_dispense' => 20000,
            'motif' => 'Arrivée mi-1er trimestre',
        ])->assertRedirect();

        $this->compte->refresh();
        $this->assertEquals(20000, (float) $this->compte->total_dispense);
        $this->assertEquals(60000, (float) $this->compte->montant_net_exigible);

        // 3) Levée complète de la dispense
        $dispense = DispenseFinanciere::where('compte_financier_id', $this->compte->id)->firstOrFail();
        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/dispenses/{$dispense->id}")
            ->assertRedirect();

        $this->compte->refresh();
        $this->assertEquals(0, (float) $this->compte->total_dispense);
        $this->assertEquals(80000, (float) $this->compte->montant_net_exigible);
    }

    /**
     * Teste de non-régression : l'accès à `GET /administration/finances` et au Journal Détaillé PDF
     * charge sans erreur la relation `caisse` lorsqu'au moins un versement est déjà enregistré.
     */
    public function test_index_finances_charge_les_versements_avec_leur_relation_caisse(): void
    {
        $this->actingAs($this->adminUser)->post('/administration/finances/versements', [
            'compte_financier_id' => $this->compte->id,
            'montant_total' => 15000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'M. KOUAM',
            'ventilations' => [
                [
                    'libelle' => 'Acompte Tranche 1',
                    'montant' => 15000,
                    'type_frais_id' => $this->fraisObligatoire->type_frais_id,
                    'frais_scolaire_id' => $this->fraisObligatoire->id,
                ],
            ],
        ])->assertRedirect();

        $this->actingAs($this->adminUser)
            ->get('/administration/finances')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('administration/finances/index')
                ->has('versements', 1)
            );

        $this->actingAs($this->adminUser)
            ->get('/administration/finances/etats/journal-detaille-pdf')
            ->assertOk();
    }

    /**
     * Vérifie le flux complet sans répétition :
     * 1. Création de Catégories de Frais (Libellé + Description),
     * 2. Configuration unique de frais au Catalogue (sans code ni périodicité imposés),
     * 3. Affectation groupée en 1 requête à plusieurs niveaux (SIL, CP, CM2) et propagation dans les dossiers élèves,
     * 4. Synchronisation automatique des niveaux et des comptes élèves lors de la modification d'un frais du Catalogue.
     */
    public function test_catalogue_categories_frais_affectation_multi_niveaux_et_synchronisation(): void
    {
        $section = Section::where('etablissement_id', $this->etablissement->id)->firstOrFail();
        $cyclePrimaire = Cycle::create([
            'section_id' => $section->id,
            'nom' => 'Primaire',
            'code' => 'PRIM',
            'ordre' => 0,
        ]);

        $niveauSil = Niveau::create([
            'cycle_id' => $cyclePrimaire->id,
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'SIL',
            'code' => 'SIL',
            'ordre' => 1,
        ]);
        $niveauCp = Niveau::create([
            'cycle_id' => $cyclePrimaire->id,
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'CP',
            'code' => 'CP',
            'ordre' => 2,
        ]);
        $niveauCm2 = Niveau::create([
            'cycle_id' => $cyclePrimaire->id,
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'CM2',
            'code' => 'CM2',
            'ordre' => 6,
        ]);

        $classeSil = Classe::create([
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauSil->id,
            'nom' => 'SIL A',
            'code' => 'SIL-A',
        ]);
        $classeCm2 = Classe::create([
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauCm2->id,
            'nom' => 'CM2 A',
            'code' => 'CM2-A',
        ]);

        $eleveSil = Eleve::create([
            'etablissement_id' => $this->etablissement->id,
            'matricule' => 'EL-SIL-01',
            'nom' => 'NDJOCK',
            'prenom' => 'Awa',
            'sexe' => 'F',
            'date_naissance' => '2020-03-10',
        ]);
        $inscSil = Inscription::create([
            'eleve_id' => $eleveSil->id,
            'classe_id' => $classeSil->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => Carbon::today(),
            'statut' => 'validee',
        ]);
        $compteSil = CompteFinancierEleve::create([
            'inscription_id' => $inscSil->id,
            'eleve_id' => $eleveSil->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 0,
            'montant_net_exigible' => 0,
            'total_paye' => 0,
            'solde_restant' => 0,
            'statut_financier' => 'insolvable',
        ]);

        $eleveCm2 = Eleve::create([
            'etablissement_id' => $this->etablissement->id,
            'matricule' => 'EL-CM2-01',
            'nom' => 'MBARGA',
            'prenom' => 'Junior',
            'sexe' => 'M',
            'date_naissance' => '2015-07-22',
        ]);
        $inscCm2 = Inscription::create([
            'eleve_id' => $eleveCm2->id,
            'classe_id' => $classeCm2->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => Carbon::today(),
            'statut' => 'validee',
        ]);
        $compteCm2 = CompteFinancierEleve::create([
            'inscription_id' => $inscCm2->id,
            'eleve_id' => $eleveCm2->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 0,
            'montant_net_exigible' => 0,
            'total_paye' => 0,
            'solde_restant' => 0,
            'statut_financier' => 'insolvable',
        ]);

        // 1) Création des catégories légères (Libellé + Description)
        $this->actingAs($this->adminUser)->post('/administration/finances/categories-frais', [
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Scolarité Primaire',
            'description' => 'Inscription et tranches du cycle primaire',
        ])->assertRedirect();

        $this->actingAs($this->adminUser)->post('/administration/finances/categories-frais', [
            'etablissement_id' => $this->etablissement->id,
            'nom' => "Frais d'Examen Officiel",
            'description' => 'Dossiers CEPE et concours',
        ])->assertRedirect();

        $catScolarite = CategorieFrais::withoutGlobalScopes()
            ->where('etablissement_id', $this->etablissement->id)
            ->where('nom', 'Scolarité Primaire')
            ->firstOrFail();

        $catExamen = CategorieFrais::withoutGlobalScopes()
            ->where('etablissement_id', $this->etablissement->id)
            ->where('nom', "Frais d'Examen Officiel")
            ->firstOrFail();

        // 2) Configuration unique des frais dans le Catalogue (sans code ni périodicité requis)
        $this->actingAs($this->adminUser)->post('/administration/finances/types-frais', [
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catScolarite->id,
            'libelle' => 'Inscription SIL - CM2',
            'obligatoire' => true,
            'ordre_priorite' => 1,
            'montant_defaut' => 15000,
            'date_limite_defaut' => '2026-09-15',
            'description' => 'Payable dès la rentrée',
        ])->assertRedirect();

        $this->actingAs($this->adminUser)->post('/administration/finances/types-frais', [
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catScolarite->id,
            'libelle' => 'Tranche 1 SIL - CM2',
            'obligatoire' => true,
            'ordre_priorite' => 2,
            'montant_defaut' => 35000,
            'date_limite_defaut' => '2026-11-15',
        ])->assertRedirect();

        $this->actingAs($this->adminUser)->post('/administration/finances/types-frais', [
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catScolarite->id,
            'libelle' => 'Tranche 2 SIL - CM2',
            'obligatoire' => true,
            'ordre_priorite' => 3,
            'montant_defaut' => 25000,
            'date_limite_defaut' => '2027-02-15',
        ])->assertRedirect();

        $this->actingAs($this->adminUser)->post('/administration/finances/types-frais', [
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $catExamen->id,
            'libelle' => 'Examen CEPE',
            'obligatoire' => true,
            'ordre_priorite' => 4,
            'montant_defaut' => 10000,
            'date_limite_defaut' => '2027-01-20',
        ])->assertRedirect();

        $fraisInscr = TypeFrais::where('libelle', 'Inscription SIL - CM2')->firstOrFail();
        $fraisT1 = TypeFrais::where('libelle', 'Tranche 1 SIL - CM2')->firstOrFail();
        $fraisT2 = TypeFrais::where('libelle', 'Tranche 2 SIL - CM2')->firstOrFail();
        $fraisCepe = TypeFrais::where('libelle', 'Examen CEPE')->firstOrFail();

        // 3) Rattachement groupé en 1 seule requête des 3 frais Scolarité aux niveaux SIL, CP et CM2
        $this->actingAs($this->adminUser)->post('/administration/finances/tarifs/affecter-niveaux', [
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'type_frais_ids' => [$fraisInscr->id, $fraisT1->id, $fraisT2->id],
            'niveau_ids' => [$niveauSil->id, $niveauCp->id, $niveauCm2->id],
        ])->assertRedirect();

        // Et rattachement du CEPE uniquement au CM2
        $this->actingAs($this->adminUser)->post('/administration/finances/tarifs/affecter-niveaux', [
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'type_frais_ids' => [$fraisCepe->id],
            'niveau_ids' => [$niveauCm2->id],
        ])->assertRedirect();

        // Vérification immédiate des soldes élèves : SIL = 75 000 FCFA, CM2 = 85 000 FCFA
        $compteSil->refresh();
        $compteCm2->refresh();
        $this->assertEquals(75000.0, (float) $compteSil->montant_net_exigible);
        $this->assertEquals(85000.0, (float) $compteCm2->montant_net_exigible);

        // Vérification de la propagation du nom de Catégorie dans l'échéancier du dossier élève CM2
        $dossierJson = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$compteCm2->id}/dossier")
            ->assertOk()
            ->json();

        $categoriesEcheancier = collect($dossierJson['lignes'])->pluck('categorie')->unique()->values()->all();
        $this->assertContains('Scolarité Primaire', $categoriesEcheancier);
        $this->assertContains("Frais d'Examen Officiel", $categoriesEcheancier);

        // 4) Modification d'un frais dans le Catalogue -> Synchronisation automatique de tous les niveaux rattachés
        $this->actingAs($this->adminUser)->put("/administration/finances/types-frais/{$fraisT2->id}", [
            'categorie_frais_id' => $catScolarite->id,
            'libelle' => 'Tranche 2 SIL - CM2',
            'obligatoire' => true,
            'ordre_priorite' => 3,
            'montant_defaut' => 30000, // +5 000 FCFA
            'date_limite_defaut' => '2027-03-01',
        ])->assertRedirect();

        $compteSil->refresh();
        $compteCm2->refresh();
        $this->assertEquals(80000.0, (float) $compteSil->montant_net_exigible);
        $this->assertEquals(90000.0, (float) $compteCm2->montant_net_exigible);

        // Vérification du rendu Inertia de Guichet & Scolarités avec categoriesFrais
        $this->actingAs($this->adminUser)
            ->get('/administration/finances/scolarites')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('administration/finances/scolarites')
                ->has('categoriesFrais')
            );
    }

    /**
     * Vérifie qu'aucun élément financier déjà lié (catégorie utilisée, frais rattaché à un niveau,
     * tarif ayant enregistré un paiement, bourse attribuée) ne peut être supprimé.
     */
    public function test_interdit_la_suppression_des_elements_financiers_deja_lies_ou_utilises(): void
    {
        // 1. Catégorie de frais utilisée par un frais du catalogue -> suppression bloquée
        $categorie = CategorieFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Groupe Scolarité Verrouillé',
            'description' => 'Utilisée par au moins un frais',
            'is_active' => true,
        ]);

        $typeFraisLie = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $categorie->id,
            'code' => 'TF-LOCK-01',
            'libelle' => 'Inscription Verrouillée',
            'obligatoire' => true,
            'periodicite' => 'annuel',
            'ordre_priorite' => 1,
            'montant_defaut' => 15000,
        ]);

        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/categories-frais/{$categorie->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('categories_frais', ['id' => $categorie->id]);

        // Si le frais est supprimé du catalogue (mis à la corbeille / soft-deleted),
        // la catégorie n'a plus de frais actif (compteur = 0) et doit pouvoir être supprimée.
        $typeFraisLie->delete();
        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/categories-frais/{$categorie->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('categories_frais', ['id' => $categorie->id]);
        $this->assertNull($typeFraisLie->fresh()->categorie_frais_id);

        // On recrée une catégorie et un frais rattaché pour la suite du test
        $categorie = CategorieFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Catégorie Test Verrouillage 2',
        ]);
        $typeFraisLie = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'categorie_frais_id' => $categorie->id,
            'code' => 'INSC-VERR-2',
            'libelle' => 'Inscription Verrouillée 2',
            'obligatoire' => true,
            'periodicite' => 'annuel',
            'ordre_priorite' => 1,
            'montant_defaut' => 15000,
        ]);

        // 2. Frais du catalogue rattaché à un niveau -> suppression du TypeFrais bloquée
        $tarifSansPaiement = FraisScolaire::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $typeFraisLie->id,
            'tranche_numero' => 1,
            'libelle_tranche' => 'Inscription Verrouillée',
            'montant' => 15000,
        ]);

        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/types-frais/{$typeFraisLie->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('types_frais', ['id' => $typeFraisLie->id]);

        // 3. Tarif de niveau ayant déjà reçu un paiement -> suppression du FraisScolaire bloquée
        $this->actingAs($this->adminUser)->post('/administration/finances/versements', [
            'compte_financier_id' => $this->compte->id,
            'montant_total' => 10000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'Parent Test',
            'ventilations' => [
                [
                    'libelle' => 'Acompte Scolarité T1',
                    'montant' => 10000,
                    'type_frais_id' => $this->fraisObligatoire->type_frais_id,
                    'frais_scolaire_id' => $this->fraisObligatoire->id,
                ],
            ],
        ])->assertRedirect();

        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/tarifs/{$this->fraisObligatoire->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('frais_scolaires', ['id' => $this->fraisObligatoire->id]);

        // En revanche, un tarif de niveau vierge (aucun paiement, aucune dispense) peut être détaché
        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/tarifs/{$tarifSansPaiement->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('frais_scolaires', ['id' => $tarifSansPaiement->id]);

        // 4. Modèle de bourse/remise déjà attribué à un élève -> suppression bloquée
        $bourse = BourseRemise::create([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'code' => 'REM-LOCK',
            'nom' => 'Remise Paiement Anticipé',
            'type' => 'remise',
            'categorie' => 'Paiement en avance',
            'mode_calcul' => 'montant_fixe',
            'valeur' => 3000,
            'cible_imputation' => 'derniere_tranche',
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser)->post('/administration/finances/bourses-remises/attribuer', [
            'compte_financier_id' => $this->compte->id,
            'bourse_remise_id' => $bourse->id,
        ])->assertRedirect();

        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/bourses-remises/{$bourse->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('bourses_remises', ['id' => $bourse->id]);
    }

    /**
     * Teste le cas concret d'erreur d'imputation (versé sur Élève X en 8 opérations au lieu d'Élève Y) :
     * annulation groupée des 8 opérations en une seule requête avec un seul motif,
     * retour immédiat du solde de X à l'état initial, puis encaissement propre sur Y.
     */
    public function test_annulation_en_lot_de_huit_versements_et_rectification_erreur_eleve_x_vers_y(): void
    {
        // Création d'un second élève Y dans la même classe
        $eleveY = Eleve::create([
            'etablissement_id' => $this->etablissement->id,
            'matricule' => 'ELEVE-TEST-Y02',
            'nom' => 'NGONO',
            'prenom' => 'Clarisse',
            'sexe' => 'F',
            'date_naissance' => '2014-09-10',
        ]);
        $inscY = Inscription::create([
            'eleve_id' => $eleveY->id,
            'classe_id' => $this->classe->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => Carbon::today(),
            'statut' => 'validee',
        ]);
        $compteY = CompteFinancierEleve::create([
            'inscription_id' => $inscY->id,
            'eleve_id' => $eleveY->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 50000,
            'montant_net_exigible' => 50000,
            'total_paye' => 0,
            'solde_restant' => 50000,
            'statut_financier' => 'insolvable',
        ]);

        // L'économe saisit par erreur 8 versements successifs de 5 000 FCFA (= 40 000 FCFA) sur l'élève X ($this->compte)
        for ($i = 1; $i <= 8; $i++) {
            $this->actingAs($this->adminUser)->post('/administration/finances/versements', [
                'compte_financier_id' => $this->compte->id,
                'montant_total' => 5000,
                'mode_paiement' => 'especes',
                'payeur_nom' => 'Parent Homonyme',
                'ventilations' => [
                    [
                        'libelle' => "Versement partiel n°{$i}",
                        'montant' => 5000,
                        'type_frais_id' => null,
                        'frais_scolaire_id' => null,
                    ],
                ],
            ])->assertRedirect();
        }

        $this->compte->refresh();
        $this->assertEquals(40000.0, (float) $this->compte->total_paye);
        $this->assertEquals(10000.0, (float) $this->compte->solde_restant);

        $huitVersementIds = VersementCaisse::withoutGlobalScopes()
            ->where('compte_financier_id', $this->compte->id)
            ->where('statut', 'valide')
            ->pluck('id')
            ->all();

        $this->assertCount(8, $huitVersementIds);

        // Annulation groupée des 8 opérations en UNE SEULE requête avec un seul motif d'audit
        $motif = 'Erreur homonymie guichet : versements destinés à NGONO Clarisse (ELEVE-TEST-Y02)';
        $this->actingAs($this->adminUser)
            ->post('/administration/finances/versements/annuler-lot', [
                'versement_ids' => $huitVersementIds,
                'motif_annulation' => $motif,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // Les 8 écritures sont toutes passées à `annule` (numéros de reçus préservés pour la traçabilité)
        $nbAnnules = VersementCaisse::withoutGlobalScopes()
            ->whereIn('id', $huitVersementIds)
            ->where('statut', 'annule')
            ->where('motif_annulation', $motif)
            ->count();
        $this->assertEquals(8, $nbAnnules);

        // Le compte de l'élève X est intégralement revenu à son état initial (0 payé, 50 000 restant)
        $this->compte->refresh();
        $this->assertEquals(0.0, (float) $this->compte->total_paye);
        $this->assertEquals(50000.0, (float) $this->compte->solde_restant);

        // Encaissement rectificatif propre des 40 000 FCFA sur le bon élève Y
        $this->actingAs($this->adminUser)->post('/administration/finances/versements', [
            'compte_financier_id' => $compteY->id,
            'montant_total' => 40000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'Parent NGONO',
            'ventilations' => [
                [
                    'libelle' => 'Règlement scolarité rectifié',
                    'montant' => 40000,
                    'type_frais_id' => null,
                    'frais_scolaire_id' => null,
                ],
            ],
        ])->assertRedirect();

        $compteY->refresh();
        $this->assertEquals(40000.0, (float) $compteY->total_paye);
        $this->assertEquals(10000.0, (float) $compteY->solde_restant);
    }

    /**
     * Teste le filtrage multicritère des états financiers :
     * - Filtrage par intervalle de dates (`date_debut` / `date_fin` : état mensuel, journalier, etc.),
     * - Filtrage par hiérarchie scolaire complète (`section_id`, `cycle_id`, `niveau_id`, `classe_id`),
     * - Présence de la synthèse analytique par Niveau & Classe dans le Journal Synthétique,
     * - Cloisonnement multi-établissement strict (`403` si un utilisateur non Super-Admin cible un autre établissement).
     */
    public function test_etats_financiers_filtres_par_periode_et_hierarchie_scolaire_complete(): void
    {
        // Création d'une seconde branche pédagogique dans le même établissement : Section Anglophone > Primary > Class 6 > Class 6 A
        $sectionAng = Section::create([
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Section Anglophone',
            'code' => 'ANG',
        ]);
        $cycleAng = Cycle::create([
            'section_id' => $sectionAng->id,
            'nom' => 'Primary Cycle',
            'code' => 'PRIM-ANG',
            'ordre' => 2,
        ]);
        $niveauClass6 = Niveau::create([
            'cycle_id' => $cycleAng->id,
            'etablissement_id' => $this->etablissement->id,
            'nom' => 'Class 6',
            'code' => 'CL6',
            'ordre' => 6,
        ]);
        $classeClass6A = Classe::create([
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauClass6->id,
            'nom' => 'Class 6 A',
            'code' => 'CL6-A',
        ]);

        // Tarif obligatoire pour Class 6 (40 000 FCFA échu au 15/10/2026)
        $fraisClass6 = FraisScolaire::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauClass6->id,
            'type_frais_id' => $this->fraisObligatoire->type_frais_id,
            'tranche_numero' => 1,
            'libelle_tranche' => 'First Term Tuition',
            'montant' => 40000,
            'ordre_priorite' => 1,
            'date_limite' => '2026-10-15',
        ]);

        $eleveAng = Eleve::create([
            'etablissement_id' => $this->etablissement->id,
            'matricule' => 'EL-ANG-001',
            'nom' => 'TABI',
            'prenom' => 'Grace',
            'sexe' => 'F',
            'date_naissance' => '2015-04-12',
        ]);
        $inscAng = Inscription::create([
            'eleve_id' => $eleveAng->id,
            'classe_id' => $classeClass6A->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_inscription' => '2026-09-05',
            'statut' => 'validee',
        ]);
        $compteAng = CompteFinancierEleve::create([
            'inscription_id' => $inscAng->id,
            'eleve_id' => $eleveAng->id,
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'debit_initial' => 40000,
            'montant_net_exigible' => 40000,
            'total_paye' => 0,
            'solde_restant' => 40000,
            'statut_financier' => 'insolvable',
        ]);

        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);

        // Versement 1 : 25 000 FCFA sur 6ème A (Section Francophone) daté du 10 Octobre 2026
        $vOctobre = $financeService->enregistrerVersement([
            'compte_financier_id' => $this->compte->id,
            'montant_total' => 25000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'Parent 6ème A',
            'ventilations' => [
                [
                    'libelle' => 'Acompte Octobre',
                    'montant' => 25000,
                    'type_frais_id' => $this->fraisObligatoire->type_frais_id,
                    'frais_scolaire_id' => $this->fraisObligatoire->id,
                ],
            ],
        ], null, $this->adminUser);
        VersementCaisse::withoutGlobalScopes()->whereKey($vOctobre->id)->update([
            'date_versement' => Carbon::parse('2026-10-10 10:15:00'),
        ]);

        // Versement 2 : 40 000 FCFA (solde total) sur Class 6 A (Section Anglophone) daté du 15 Novembre 2026
        $vNovembre = $financeService->enregistrerVersement([
            'compte_financier_id' => $compteAng->id,
            'montant_total' => 40000,
            'mode_paiement' => 'mobile_money_manuel',
            'payeur_nom' => 'Parent Class 6',
            'ventilations' => [
                [
                    'libelle' => 'Règlement intégral Class 6',
                    'montant' => 40000,
                    'type_frais_id' => $fraisClass6->type_frais_id,
                    'frais_scolaire_id' => $fraisClass6->id,
                ],
            ],
        ], null, $this->adminUser);
        VersementCaisse::withoutGlobalScopes()->whereKey($vNovembre->id)->update([
            'date_versement' => Carbon::parse('2026-11-15 14:30:00'),
        ]);

        // A. Filtrage mensuel Octobre 2026 (01/10/2026 -> 31/10/2026) : seul le versement d'Octobre (25 000) ressort
        $journalOctobre = $financeService->obtenirJournalDetaille(
            $this->etablissement->id,
            '2026-10-01',
            '2026-10-31'
        );
        $this->assertEquals('01-10-2026', $journalOctobre['date_debut']);
        $this->assertEquals('31-10-2026', $journalOctobre['date_fin']);
        $this->assertEquals(25000.0, (float) $journalOctobre['total_general']);
        $this->assertCount(1, $journalOctobre['versements']);

        // B. Filtrage sur un Niveau particulier (`Class 6`) sur toute la période Sept-Déc 2026 :
        //    seul Class 6 A (40 000 FCFA) est comptabilisé dans le Journal Synthétique et `synthese_niveaux`
        $syntheseClass6 = $financeService->obtenirJournalSynthetique(
            $this->etablissement->id,
            '2026-09-01',
            '2026-12-31',
            null,
            null,
            $sectionAng->id,
            $cycleAng->id,
            $niveauClass6->id,
            null
        );
        $this->assertEquals(40000.0, (float) $syntheseClass6['total_general']);
        $this->assertCount(1, $syntheseClass6['synthese_niveaux']);
        $this->assertEquals('Class 6', $syntheseClass6['synthese_niveaux'][0]['niveau']);
        $this->assertEquals('Class 6 A', $syntheseClass6['synthese_niveaux'][0]['classe']);
        $this->assertStringContainsString('Niveau : Class 6', $syntheseClass6['perimetre_label']);

        // C. Liste des Élèves en Règle filtrée sur `niveau_id = Class 6` : contient uniquement TABI Grace (40 000 versés sur la période Nov)
        $enRegleClass6 = $financeService->obtenirListeEnRegle(
            $this->etablissement->id,
            $this->anneeScolaire->id,
            null,
            $niveauClass6->id,
            null,
            null,
            '2026-11-01',
            '2026-11-30'
        );
        $this->assertEquals(1, $enRegleClass6['nombre_en_regle']);
        $this->assertEquals('EL-ANG-001', $enRegleClass6['eleves'][0]['matricule']);
        $this->assertEquals(40000.0, (float) $enRegleClass6['eleves'][0]['verse_periode']);

        // D. Génération HTTP 200 OK des 5 états PDF avec filtres date_debut / date_fin + hiérarchie
        $query = http_build_query([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'date_debut' => '2026-10-01',
            'date_fin' => '2026-11-30',
            'section_id' => $sectionAng->id,
            'cycle_id' => $cycleAng->id,
            'niveau_id' => $niveauClass6->id,
            'classe_id' => $classeClass6A->id,
        ]);

        foreach ([
            "/administration/finances/etats/journal-detaille-pdf?{$query}",
            "/administration/finances/etats/journal-synthetique-pdf?{$query}",
            "/administration/finances/etats/insolvables-pdf?{$query}",
            "/administration/finances/etats/en-regle-pdf?{$query}",
            "/administration/finances/bilan-pdf?etablissement_id={$this->etablissement->id}&annee_scolaire_id={$this->anneeScolaire->id}&date_debut=2026-10-01&date_fin=2026-11-30",
        ] as $pdfEndpoint) {
            $this->actingAs($this->adminUser)
                ->get($pdfEndpoint)
                ->assertOk();
        }

        // E. Cloisonnement Multi-Tenant : un utilisateur local (non Super-Admin) ne peut PAS tirer l'état d'un autre établissement (HTTP 403)
        $autreEtab = Etablissement::create([
            'institution_id' => $this->etablissement->institution_id,
            'nom' => 'Campus Primaire Nord',
            'code' => 'CPN',
            'type' => 'primaire',
        ]);

        Permission::firstOrCreate(['name' => 'finances.etats', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'depenses.bilan', 'guard_name' => 'web']);
        $directeurLocal = User::factory()->create([
            'etablissement_id' => $this->etablissement->id,
        ]);
        $directeurLocal->givePermissionTo(['finances.view', 'finances.etats', 'depenses.bilan']);

        $this->actingAs($directeurLocal)
            ->get("/administration/finances/etats/journal-synthetique-pdf?etablissement_id={$autreEtab->id}&date_debut=2026-10-01&date_fin=2026-10-31")
            ->assertForbidden();

        $this->actingAs($directeurLocal)
            ->get("/administration/finances/bilan-pdf?etablissement_id={$autreEtab->id}&date_debut=2026-10-01&date_fin=2026-10-31")
            ->assertForbidden();
    }

    /**
     * Vérifie le cadrage strict par Rôles & Permissions du module financier (rôle `caissier`) :
     * - Le caissier PEUT : consulter le module et le guichet, encaisser la scolarité, encaisser une recette diverse,
     *   activer une option (Bus/Cantine) et générer les états PDF filtrés.
     * - Le caissier NE PEUT PAS (`403 Forbidden`) : configurer le catalogue ou les tarifs par niveau,
     *   annuler un reçu (unitaire ou en lot), créer ou attribuer une remise/bourse, accorder une dispense,
     *   clôturer la journée ou modifier le verrou de solvabilité des bulletins.
     */
    public function test_permissions_granulaires_et_role_caissier_cadrage_strict(): void
    {
        $allFinancePerms = [
            'finances.view',
            'finances.manage',
            'finances.etats',
            'frais.configure',
            'versements.create',
            'recettes_diverses.create',
            'versements.cancel',
            'caisses.view',
            'caisses.manage',
            'caisses.cloture',
            'bourses.manage',
            'bourses.attribuer',
            'dispenses_financieres.manage',
            'options_financieres.manage',
            'finances.verrou_solvabilite',
        ];
        foreach ($allFinancePerms as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        $caissierRole = Role::firstOrCreate(['name' => 'caissier', 'guard_name' => 'web']);
        $caissierRole->syncPermissions([
            'finances.view',
            'finances.etats',
            'versements.create',
            'recettes_diverses.create',
            'options_financieres.manage',
            'caisses.view',
        ]);

        $caissierUser = User::factory()->create([
            'etablissement_id' => $this->etablissement->id,
        ]);
        $caissierUser->assignRole($caissierRole);

        // 1. Vérification des drapeaux Inertia `can` exposés au frontend sur `/administration/finances/scolarites`
        $this->actingAs($caissierUser)
            ->get('/administration/finances/scolarites')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('administration/finances/scolarites')
                ->where('can.view', true)
                ->where('can.etats', true)
                ->where('can.versements_create', true)
                ->where('can.recettes_diverses_create', true)
                ->where('can.options_manage', true)
                ->where('can.manage', false)
                ->where('can.frais_configure', false)
                ->where('can.versements_cancel', false)
                ->where('can.bourses_manage', false)
                ->where('can.bourses_attribuer', false)
                ->where('can.dispenses_manage', false)
                ->where('can.caisses_cloture', false)
                ->where('can.verrou_solvabilite', false)
            );

        // 2. Actions AUTORISÉES au rôle `caissier` : encaissement scolarité, recette diverse, option Bus et état PDF filtré
        $this->actingAs($caissierUser)->post('/administration/finances/versements', [
            'compte_financier_id' => $this->compte->id,
            'montant_total' => 15000,
            'mode_paiement' => 'especes',
            'payeur_nom' => 'Parent Guichet',
            'ventilations' => [
                [
                    'libelle' => 'Versement guichet caissier',
                    'montant' => 15000,
                    'type_frais_id' => null,
                    'frais_scolaire_id' => null,
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $versementCree = VersementCaisse::withoutGlobalScopes()
            ->where('compte_financier_id', $this->compte->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($caissierUser)->post('/administration/finances/reglements-divers', [
            'etablissement_id' => $this->etablissement->id,
            'payeur_nom' => 'Parent Tenue',
            'categorie' => 'Tenue scolaire',
            'libelle' => 'Polo sport',
            'montant' => 4500,
            'mode_paiement' => 'especes',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($caissierUser)->post('/administration/finances/souscriptions-optionnelles', [
            'compte_financier_id' => $this->compte->id,
            'frais_scolaire_id' => $this->fraisOptionnelBus->id,
            'montant' => 20000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($caissierUser)
            ->get("/administration/finances/etats/journal-synthetique-pdf?date_debut=2026-09-01&date_fin=2027-06-30&niveau_id={$this->classe->niveau_id}")
            ->assertOk();

        // 3. Actions SENSIBLES INTERDITES (`403 Forbidden`) au rôle `caissier`
        $this->actingAs($caissierUser)
            ->post("/administration/finances/versements/{$versementCree->id}/annuler", [
                'motif_annulation' => 'Tentative non autorisée par caissier',
            ])
            ->assertForbidden();

        $this->actingAs($caissierUser)
            ->post('/administration/finances/versements/annuler-lot', [
                'versement_ids' => [$versementCree->id],
                'motif_annulation' => 'Tentative lot non autorisée',
            ])
            ->assertForbidden();

        $this->actingAs($caissierUser)
            ->post('/administration/finances/types-frais', [
                'etablissement_id' => $this->etablissement->id,
                'libelle' => 'Frais pirate',
                'montant_defaut' => 10000,
                'obligatoire' => true,
            ])
            ->assertForbidden();

        $this->actingAs($caissierUser)
            ->post('/administration/finances/tarifs/affecter-niveaux', [
                'etablissement_id' => $this->etablissement->id,
                'annee_scolaire_id' => $this->anneeScolaire->id,
                'type_frais_id' => $this->fraisObligatoire->type_frais_id,
                'niveau_ids' => [$this->classe->niveau_id],
            ])
            ->assertForbidden();

        $bourseModele = BourseRemise::create([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'code' => 'REM-DIR-01',
            'nom' => 'Remise Direction',
            'type' => 'remise',
            'categorie' => 'Paiement en avance',
            'mode_calcul' => 'montant_fixe',
            'valeur' => 3000,
            'cible_imputation' => 'derniere_tranche',
            'is_active' => true,
        ]);

        $this->actingAs($caissierUser)
            ->post('/administration/finances/bourses-remises', [
                'etablissement_id' => $this->etablissement->id,
                'nom' => 'Remise non autorisée',
                'type' => 'remise',
                'categorie' => 'Autre',
                'mode_calcul' => 'montant_fixe',
                'valeur' => 5000,
            ])
            ->assertForbidden();

        $this->actingAs($caissierUser)
            ->post('/administration/finances/bourses-remises/attribuer', [
                'compte_financier_id' => $this->compte->id,
                'bourse_remise_id' => $bourseModele->id,
            ])
            ->assertForbidden();

        $this->actingAs($caissierUser)
            ->post('/administration/finances/dispenses', [
                'compte_financier_id' => $this->compte->id,
                'frais_scolaire_id' => $this->fraisObligatoire->id,
                'type_dispense' => 'totale',
                'motif' => 'Tentative dispense non autorisée',
            ])
            ->assertForbidden();

        $this->actingAs($caissierUser)
            ->post('/administration/finances/cloturer-journee', [
                'etablissement_id' => $this->etablissement->id,
                'date_cloture' => Carbon::today()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($caissierUser)
            ->post("/administration/finances/etablissements/{$this->etablissement->id}/verrou-solvabilite", [
                'verrou_solvabilite_actif' => false,
            ])
            ->assertForbidden();
    }

    /**
     * Teste que la page dédiée du Centre des États & Rapports Financiers (/administration/finances/etats)
     * est accessible pour les profils autorisés (Intendant, Caissier, Super Admin) et protégée en multi-tenant.
     */
    public function test_page_dediee_etats_financiers_accessible_et_cloisonnee(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/administration/finances/etats')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('administration/finances/etats')
                ->has('etablissements')
                ->has('currentEtablissement')
                ->has('caisses')
                ->has('anneesScolaires')
                ->has('can')
            );

        // Profil sans permission financière -> Rejet 403
        $simpleUser = User::factory()->create([
            'etablissement_id' => $this->etablissement->id,
        ]);

        $this->actingAs($simpleUser)
            ->get('/administration/finances/etats')
            ->assertForbidden();

        // Utilisateur local avec permission finances.etats mais rattaché à son propre établissement
        Permission::firstOrCreate(['name' => 'finances.etats', 'guard_name' => 'web']);
        $localIntendant = User::factory()->create([
            'etablissement_id' => $this->etablissement->id,
        ]);
        $localIntendant->givePermissionTo('finances.etats');

        // Accès valide sur son campus
        $this->actingAs($localIntendant)
            ->get('/administration/finances/etats')
            ->assertOk();

        // Cloisonnement multi-tenant : tentative d'accès à un autre établissement sans être super-admin -> Rejet 403
        $autreEtab = Etablissement::create([
            'institution_id' => $this->etablissement->institution_id,
            'nom' => 'Autre Campus Privé',
            'code' => 'ACP',
            'type' => 'primaire',
        ]);

        $this->actingAs($localIntendant)
            ->get("/administration/finances/etats?etablissement_id={$autreEtab->id}")
            ->assertForbidden();
    }

    /**
     * Teste que le montant d'un versement est strictement rejeté s'il dépasse le reste à payer
     * ou si le compte de l'élève est déjà soldé (reste à payer = 0 FCFA).
     */
    public function test_versement_rejete_si_montant_superieur_au_reste_a_payer_ou_compte_solde(): void
    {
        // Le compte initial a un exigible et un reste à payer de 50 000 FCFA.
        // Tentative de verser 100 000 FCFA -> doit être rejetée avec erreur de validation
        $responseTrop = $this->actingAs($this->adminUser)
            ->post('/administration/finances/versements', [
                'compte_financier_id' => $this->compte->id,
                'montant_total' => 100000,
                'mode_paiement' => 'especes',
                'ventilations' => [
                    [
                        'frais_scolaire_id' => $this->fraisObligatoire->id,
                        'libelle' => 'Scolarité Tranche 1',
                        'montant' => 100000,
                    ],
                ],
            ]);

        $responseTrop->assertSessionHasErrors('error');
        $this->compte->refresh();
        $this->assertEquals(0, (float) $this->compte->total_paye);
        $this->assertEquals(50000, (float) $this->compte->solde_restant);

        // Versement exact de 50 000 FCFA -> autorisé et solde le compte
        $responseExact = $this->actingAs($this->adminUser)
            ->post('/administration/finances/versements', [
                'compte_financier_id' => $this->compte->id,
                'montant_total' => 50000,
                'mode_paiement' => 'especes',
                'ventilations' => [
                    [
                        'frais_scolaire_id' => $this->fraisObligatoire->id,
                        'libelle' => 'Scolarité Tranche 1',
                        'montant' => 50000,
                    ],
                ],
            ]);

        $responseExact->assertSessionHasNoErrors();
        $this->compte->refresh();
        $this->assertEquals(50000, (float) $this->compte->total_paye);
        $this->assertEquals(0, (float) $this->compte->solde_restant);

        // Tentative d'encaisser à nouveau 10 000 FCFA sur un compte déjà soldé (reste = 0) -> rejetée
        $responseSolde = $this->actingAs($this->adminUser)
            ->post('/administration/finances/versements', [
                'compte_financier_id' => $this->compte->id,
                'montant_total' => 10000,
                'mode_paiement' => 'especes',
                'ventilations' => [
                    [
                        'frais_scolaire_id' => $this->fraisObligatoire->id,
                        'libelle' => 'Scolarité Tranche 1',
                        'montant' => 10000,
                    ],
                ],
            ]);

        $responseSolde->assertSessionHasErrors('error');
        $this->compte->refresh();
        $this->assertEquals(50000, (float) $this->compte->total_paye);
    }

    /**
     * Teste qu'un frais configuré au Catalogue (où types_frais.obligatoire = true par défaut)
     * mais rattaché au niveau en mode Optionnel (frais_scolaires.obligatoire = false) :
     * - remonte bien dans `frais_optionnels_niveau` du dossier élève,
     * - peut être coché/appliqué individuellement ou à toute une classe (ex: Inscription P CDE vs P TI),
     * - augmente immédiatement le montant net exigible et le reste à payer avant encaissement.
     */
    public function test_frais_optionnel_defini_au_niveau_et_souscription_individuelle_ou_par_classe(): void
    {
        // Catalogue : le frais a obligatoire = true par défaut dans types_frais
        $typeInscriptionCde = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'INSC-P-CDE',
            'libelle' => 'Inscription Première CDE',
            'obligatoire' => true,
            'ordre_priorite' => 2,
        ]);

        // Rattachement au niveau : configuré en OPTIONNEL (frais_scolaires.obligatoire = false)
        $fraisOptionnelNiveau = FraisScolaire::create([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'niveau_id' => $this->classe->niveau_id,
            'type_frais_id' => $typeInscriptionCde->id,
            'libelle_tranche' => 'Inscription Première CDE',
            'montant' => 25000,
            'obligatoire' => false,
        ]);

        // 1. Vérifier que le dossier JSON de l'élève propose bien ce frais dans `frais_optionnels_niveau`
        $dossierJson = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk();

        $optionsNiveau = collect($dossierJson->json('frais_optionnels_niveau'));
        $optCde = $optionsNiveau->firstWhere('frais_scolaire_id', $fraisOptionnelNiveau->id);
        $this->assertNotNull($optCde);
        $this->assertFalse($optCde['est_souscrit']);
        $this->assertEquals(25000, (float) $optCde['montant_standard']);

        // 2. Appliquer ce frais optionnel à toute la classe en 1 clic
        $this->actingAs($this->adminUser)
            ->post('/administration/finances/souscriptions-optionnelles/classe', [
                'classe_id' => $this->classe->id,
                'frais_scolaire_id' => $fraisOptionnelNiveau->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->compte->refresh();
        // Montant total à verser passe de 50 000 à 75 000 FCFA (50 000 obligatoire + 25 000 optionnel)
        $this->assertEquals(75000, (float) $this->compte->montant_net_exigible);
        $this->assertEquals(75000, (float) $this->compte->solde_restant);

        // 3. L'élève peut désormais verser 75 000 FCFA (sans dépasser le reste à payer)
        $this->actingAs($this->adminUser)
            ->post('/administration/finances/versements', [
                'compte_financier_id' => $this->compte->id,
                'montant_total' => 75000,
                'mode_paiement' => 'especes',
                'ventilations' => [
                    [
                        'frais_scolaire_id' => $this->fraisObligatoire->id,
                        'libelle' => 'Scolarité Tranche 1',
                        'montant' => 50000,
                    ],
                    [
                        'frais_scolaire_id' => $fraisOptionnelNiveau->id,
                        'libelle' => 'Inscription Première CDE',
                        'montant' => 25000,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->compte->refresh();
        $this->assertEquals(75000, (float) $this->compte->total_paye);
        $this->assertEquals(0, (float) $this->compte->solde_restant);
        $this->assertEquals('solde', $this->compte->statut_financier);
    }

    /**
     * Vérifie que l'insolvabilité dans les états repose strictement sur les échéances échues (`date_limite`),
     * même lorsque `date_fin` n'est pas transmise, évitant ainsi d'afficher des élèves insolvables avant la rentrée.
     */
    public function test_insolvabilite_basee_uniquement_sur_echeances_echues_avant_et_apres_rentree(): void
    {
        // Fixe une échéance future (07-09-2026) sur le tarif obligatoire de test
        $this->fraisObligatoire->update(['date_limite' => '2026-09-07']);

        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);

        // 1. Avant la rentrée (11 juillet 2026) avec date_fin explicite : 0 élève insolvable
        $avantRentreeAvecDate = $financeService->obtenirListeInsolvables(
            $this->etablissement->id,
            $this->anneeScolaire->id,
            null,
            null,
            null,
            null,
            null,
            '2026-07-01',
            '2026-07-11'
        );
        $this->assertEquals(0, $avantRentreeAvecDate['nombre_insolvables']);

        // 2. Sans date_fin (horloge serveur le 11 juillet 2026) : également 0 élève insolvable
        Carbon::setTestNow(Carbon::parse('2026-07-11 10:00:00'));
        try {
            $avantRentreeSansDateFin = $financeService->obtenirListeInsolvables(
                $this->etablissement->id,
                $this->anneeScolaire->id
            );
            $this->assertEquals(0, $avantRentreeSansDateFin['nombre_insolvables']);
        } finally {
            Carbon::setTestNow();
        }

        // 3. Après dépassement de la date limite (10 septembre 2026) : l'élève non payé devient insolvable sur 50 000 F
        $apresEcheance = $financeService->obtenirListeInsolvables(
            $this->etablissement->id,
            $this->anneeScolaire->id,
            null,
            null,
            null,
            null,
            null,
            null,
            '2026-09-10'
        );
        $this->assertEquals(1, $apresEcheance['nombre_insolvables']);
        $this->assertEquals(50000.0, (float) $apresEcheance['eleves'][0]['montant_echu_en_retard']);
    }

    /**
     * Vérifie la saisie rétroactive d'un versement papier (`date_versement`), le blocage des dates futures
     * ou aberrantes, le contrôle de clôture sur la date saisie et l'application atomique de la remise 4 %.
     */
    public function test_encaissement_retroactif_date_versement_bornes_cloture_et_remise_anticipee(): void
    {
        $this->anneeScolaire->update(['date_debut' => '2026-09-07']);

        $modeleAnticipe = BourseRemise::create([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'code' => 'REM-ANTICIPE-4',
            'nom' => 'Réduction 4% Paiement Anticipé (avant rentrée)',
            'type' => 'remise',
            'categorie' => 'autre',
            'mode_calcul' => 'pourcentage',
            'valeur' => 4.0,
            'cible_imputation' => 'derniere_tranche',
            'is_active' => true,
        ]);

        // 1. Le dossier JSON expose les bornes calendaires et le modèle de remise anticipée
        $dossierJson = $this->actingAs($this->adminUser)
            ->getJson("/administration/finances/comptes/{$this->compte->id}/dossier")
            ->assertOk();

        $this->assertEquals('2026-09-07', $dossierJson->json('date_limite_rentree'));
        $this->assertEquals($modeleAnticipe->id, $dossierJson->json('remise_anticipee_modele.id'));
        $this->assertEquals(2000.0, (float) $dossierJson->json('remise_anticipee_modele.montant_estime'));

        // 2. Date future (ex: 2028) ou trop ancienne (1995) strictement refusées
        $this->actingAs($this->adminUser)
            ->post('/administration/finances/versements', [
                'compte_financier_id' => $this->compte->id,
                'montant_total' => 10000,
                'date_versement' => '2028-05-10',
                'mode_paiement' => 'especes',
                'ventilations' => [
                    [
                        'frais_scolaire_id' => $this->fraisObligatoire->id,
                        'libelle' => 'Scolarité Tranche 1',
                        'montant' => 10000,
                    ],
                ],
            ])
            ->assertSessionHasErrors('date_versement');

        $this->actingAs($this->adminUser)
            ->post('/administration/finances/versements', [
                'compte_financier_id' => $this->compte->id,
                'montant_total' => 10000,
                'date_versement' => '1995-01-01',
                'mode_paiement' => 'especes',
                'ventilations' => [
                    [
                        'frais_scolaire_id' => $this->fraisObligatoire->id,
                        'libelle' => 'Scolarité Tranche 1',
                        'montant' => 10000,
                    ],
                ],
            ])
            ->assertSessionHasErrors('date_versement');

        // 3. Même si la caisse d'aujourd'hui est clôturée, une saisie rétroactive datée du 25-08-2026 reste autorisée
        $caissePrincipale = Caisse::firstOrCreate(
            ['etablissement_id' => $this->etablissement->id, 'code' => 'CAISSE-PRIN'],
            ['nom' => 'Caisse Principale', 'solde_initial' => 0, 'is_principale' => true, 'is_active' => true]
        );

        ClotureCaisse::create([
            'etablissement_id' => $this->etablissement->id,
            'caisse_id' => $caissePrincipale->id,
            'date_cloture' => Carbon::today()->toDateString(),
            'solde_theorique' => 0,
            'solde_physique_declare' => 0,
            'ecart' => 0,
            'statut' => 'cloturee',
        ]);

        // Versement anticipé du 25-08-2026 avec remise 4 % (50 000 - 2 000 = 48 000 FCFA net exigible)
        $this->actingAs($this->adminUser)
            ->post('/administration/finances/versements', [
                'compte_financier_id' => $this->compte->id,
                'montant_total' => 48000,
                'date_versement' => '2026-08-25',
                'appliquer_remise_anticipee' => true,
                'bourse_remise_anticipee_id' => $modeleAnticipe->id,
                'mode_paiement' => 'especes',
                'ventilations' => [
                    [
                        'frais_scolaire_id' => $this->fraisObligatoire->id,
                        'libelle' => 'Scolarité Tranche 1',
                        'montant' => 48000,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->compte->refresh();
        $this->assertEquals(2000.0, (float) $this->compte->remise_bourse);
        $this->assertEquals(48000.0, (float) $this->compte->montant_net_exigible);
        $this->assertEquals(48000.0, (float) $this->compte->total_paye);
        $this->assertEquals(0.0, (float) $this->compte->solde_restant);
        $this->assertEquals('solde', $this->compte->statut_financier);

        $versement = VersementCaisse::where('compte_financier_id', $this->compte->id)->firstOrFail();
        $this->assertEquals('2026-08-25', Carbon::parse($versement->date_versement)->toDateString());
    }

    /**
     * Vérifie que l'en-tête officiel des états financiers place l'année scolaire de part
     * et d'autre (FR à gauche, EN à droite), libère la colonne centrale pour un logo plus grand
     * et affiche la devise officielle du complexe scolaire.
     */
    public function test_entete_etats_financiers_annee_bilaterale_devise_et_logo_agrandi(): void
    {
        $institution = $this->etablissement->institution;
        $institution->update([
            'devise' => 'L’ÉDUCATION DES ENFANTS, L’ESPOIR DE DEMAIN',
            'devise_en' => 'THE EDUCATION OF CHILDREN, THE HOPE OF TOMORROW',
            'logo' => '/storage/logos/espoir_logo.jpg',
        ]);

        // Rendu direct du partial d'en-tête avec l'établissement rafraîchi
        $etab = $this->etablissement->fresh(['institution']);
        $html = view('finances.partials.header', [
            'annee' => $this->anneeScolaire,
            'etablissement' => $etab,
            'serviceTitle' => 'Direction Financière & Comptabilité',
        ])->render();

        // Extraction des trois cellules principales (Gauche FR, Centre, Droite EN)
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $html, $matches);
        $this->assertCount(3, $matches[1]);

        [$leftCol, $centerCol, $rightCol] = $matches[1];

        $anneeAttendue = preg_replace('/^\s*Ann[ée]e\s+scolaire\s*[:\-]?\s*/ui', '', $this->anneeScolaire->code ?? $this->anneeScolaire->nom);

        // 1. L'année scolaire figure bien de part et d'autre (FR à gauche, EN à droite) et absente du centre
        $this->assertStringContainsString("ANNÉE SCOLAIRE : {$anneeAttendue}", $leftCol);
        $this->assertStringContainsString("ACADEMIC YEAR : {$anneeAttendue}", $rightCol);
        $this->assertStringNotContainsString('ANNÉE SCOLAIRE', $centerCol);
        $this->assertStringNotContainsString('ACADEMIC YEAR', $centerCol);

        // 2. La devise officielle du complexe est présente au centre
        $this->assertStringContainsString('« L’ÉDUCATION DES ENFANTS, L’ESPOIR DE DEMAIN »', $centerCol);

        // 3. Le logo dispose d'un dimensionnement élargi (68px x 110px) si l'image existe sur disque
        if (is_file(public_path('storage/logos/espoir_logo.jpg'))) {
            $this->assertStringContainsString('max-height: 68px; max-width: 110px;', $centerCol);
        }
    }

    /**
     * Vérifie que la création et la mise à jour d'un modèle de bourse/remise enregistrent
     * correctement le libellé personnalisé, la base de calcul et la cible d'imputation par défaut.
     */
    public function test_creation_et_mise_a_jour_modele_bourse_avec_libelle_base_calcul_et_cible(): void
    {
        $this->actingAs($this->adminUser)
            ->post('/administration/finances/bourses-remises', [
                'etablissement_id' => $this->etablissement->id,
                'annee_scolaire_id' => $this->anneeScolaire->id,
                'nom' => 'Remise 4% avant rentrée (Primaire)',
                'type' => 'remise',
                'categorie' => 'REMISE',
                'mode_calcul' => 'pourcentage',
                'valeur' => 4,
                'base_calcul' => 'total_scolarite',
                'cible_imputation' => 'tranche_1',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $modele = BourseRemise::where('nom', 'Remise 4% avant rentrée (Primaire)')->firstOrFail();
        $this->assertSame('total_scolarite', $modele->base_calcul);
        $this->assertSame('tranche_1', $modele->cible_imputation);
        $this->assertEquals(4.0, (float) $modele->valeur);

        $this->actingAs($this->adminUser)
            ->put("/administration/finances/bourses-remises/{$modele->id}", [
                'nom' => 'Remise 4% sur 1ère tranche uniquement',
                'type' => 'remise',
                'categorie' => 'REMISE',
                'mode_calcul' => 'pourcentage',
                'valeur' => 4,
                'base_calcul' => 'tranche_ciblee',
                'cible_imputation' => 'derniere_tranche',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $modele->refresh();
        $this->assertSame('Remise 4% sur 1ère tranche uniquement', $modele->nom);
        $this->assertSame('tranche_ciblee', $modele->base_calcul);
        $this->assertSame('derniere_tranche', $modele->cible_imputation);

        // Vérification qu'un modèle à montant fixe personnalisé reste 100 % fonctionnel
        $this->actingAs($this->adminUser)
            ->post('/administration/finances/bourses-remises', [
                'etablissement_id' => $this->etablissement->id,
                'annee_scolaire_id' => $this->anneeScolaire->id,
                'nom' => 'Remise Primaire -3 000 FCFA',
                'type' => 'remise',
                'categorie' => 'REMISE',
                'mode_calcul' => 'montant_fixe',
                'valeur' => 3000,
                'cible_imputation' => 'tranche_1',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $modeleFixe = BourseRemise::where('nom', 'Remise Primaire -3 000 FCFA')->firstOrFail();
        $this->assertSame('montant_fixe', $modeleFixe->mode_calcul);
        $this->assertEquals(3000.0, (float) $modeleFixe->valeur);
        $this->assertSame('tranche_1', $modeleFixe->cible_imputation);
    }

    /**
     * Scénario réel École ESPOIR (scolarité 75 000 FCFA : T1 35 000 + T2 30 000 + Inscription 10 000) :
     * une remise de 4 % en mode `total_scolarite` déduite sur la 1ère Tranche doit calculer 3 000 FCFA
     * (et non 4 % × 35 000 = 1 400 FCFA), puis imputer ces 3 000 FCFA sur la 1ère Tranche.
     */
    public function test_remise_pourcentage_total_scolarite_ciblee_sur_premiere_tranche_calcule_exactement(): void
    {
        $niveauId = $this->classe->niveau_id;

        // Ajustement de T1 à 35 000 FCFA + ajout de T2 (30 000) et T3 Inscription (10 000) => 75 000 FCFA
        $this->fraisObligatoire->update([
            'montant' => 35000,
            'libelle_tranche' => '1ère Tranche',
            'tranche_numero' => 1,
            'obligatoire' => true,
        ]);

        $typeT2 = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'SCOL-T2-ESPOIR',
            'libelle' => '2ème Tranche',
            'obligatoire' => true,
            'periodicite' => 'annuel',
            'ordre_priorite' => 2,
            'montant_defaut' => 30000,
        ]);
        FraisScolaire::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauId,
            'type_frais_id' => $typeT2->id,
            'tranche_numero' => 2,
            'libelle_tranche' => '2ème Tranche',
            'obligatoire' => true,
            'montant' => 30000,
            'date_limite' => Carbon::now()->addMonths(2)->toDateString(),
        ]);

        $typeT3 = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'INSCR-ESPOIR',
            'libelle' => 'Inscription',
            'obligatoire' => true,
            'periodicite' => 'annuel',
            'ordre_priorite' => 3,
            'montant_defaut' => 10000,
        ]);
        FraisScolaire::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauId,
            'type_frais_id' => $typeT3->id,
            'tranche_numero' => 3,
            'libelle_tranche' => 'Inscription',
            'obligatoire' => true,
            'montant' => 10000,
            'date_limite' => Carbon::now()->subDays(15)->toDateString(),
        ]);

        $this->compte->update(['debit_initial' => 75000]);
        $this->compte->actualiserSolde();

        $modeleTotal = BourseRemise::create([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'code' => 'REM-ESPOIR-4-TOT',
            'nom' => 'Remise 4% Scolarité Totale',
            'type' => 'remise',
            'categorie' => 'REMISE',
            'mode_calcul' => 'pourcentage',
            'valeur' => 4,
            'base_calcul' => 'total_scolarite',
            'cible_imputation' => 'derniere_tranche',
            'is_active' => true,
        ]);

        // Attribution dans le dossier de l'élève en choisissant précisément la 1ère Tranche comme tranche à déduire
        $this->actingAs($this->adminUser)
            ->post('/administration/finances/bourses-remises/attribuer', [
                'compte_financier_id' => $this->compte->id,
                'bourse_remise_id' => $modeleTotal->id,
                'frais_scolaire_id' => $this->fraisObligatoire->id,
                'motif' => 'Paiement avant rentrée Primaire',
            ])
            ->assertSessionHasNoErrors();

        $attribution = EleveBourseRemise::where('compte_financier_id', $this->compte->id)
            ->where('bourse_remise_id', $modeleTotal->id)
            ->firstOrFail();

        // 4 % de 75 000 = 3 000 FCFA (préservé même après actualiserSolde())
        $this->assertEquals(3000.0, (float) $attribution->montant_calcule);
        $this->compte->refresh();
        $this->compte->actualiserSolde();
        $this->assertEquals(3000.0, (float) $attribution->fresh()->montant_calcule);
        $this->assertEquals(3000.0, (float) $this->compte->remise_bourse);
        $this->assertEquals(72000.0, (float) $this->compte->montant_net_exigible);
        $this->assertEquals(72000.0, (float) $this->compte->solde_restant);

        // Vérification de l'échéancier : -3 000 FCFA imputés uniquement sur la 1ère Tranche (35 000 -> 32 000)
        $dossier = app(FinanceService::class)->obtenirEcheancierEleve($this->compte->fresh());
        $ligneT1 = collect($dossier['lignes'])->firstWhere('id', $this->fraisObligatoire->id);
        $this->assertNotNull($ligneT1);
        $this->assertEquals(3000.0, (float) $ligneT1['remise_appliquee']);
        $this->assertEquals(32000.0, (float) $ligneT1['montant']);
        $this->assertSame('total_scolarite', $dossier['bourses_attribuees'][0]['base_calcul']);
    }

    /**
     * Vérifie que le mode optionnel `tranche_ciblee` calcule le pourcentage strictement sur le montant
     * de la tranche ou ligne sélectionnée (ex : 4 % de 35 000 FCFA = 1 400 FCFA).
     */
    public function test_remise_pourcentage_mode_tranche_ciblee_calcule_sur_la_tranche_uniquement(): void
    {
        $niveauId = $this->classe->niveau_id;

        $this->fraisObligatoire->update([
            'montant' => 35000,
            'libelle_tranche' => '1ère Tranche',
            'tranche_numero' => 1,
            'obligatoire' => true,
        ]);

        $typeT2 = TypeFrais::create([
            'etablissement_id' => $this->etablissement->id,
            'code' => 'SCOL-T2-CIBLE',
            'libelle' => '2ème Tranche',
            'obligatoire' => true,
            'periodicite' => 'annuel',
            'ordre_priorite' => 2,
            'montant_defaut' => 40000,
        ]);
        FraisScolaire::create([
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'etablissement_id' => $this->etablissement->id,
            'niveau_id' => $niveauId,
            'type_frais_id' => $typeT2->id,
            'tranche_numero' => 2,
            'libelle_tranche' => '2ème Tranche',
            'obligatoire' => true,
            'montant' => 40000,
            'date_limite' => Carbon::now()->addMonths(2)->toDateString(),
        ]);

        $this->compte->update(['debit_initial' => 75000]);
        $this->compte->actualiserSolde();

        $modeleTranche = BourseRemise::create([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'code' => 'REM-ESPOIR-4-T1',
            'nom' => 'Remise 4% sur Tranche Ciblée',
            'type' => 'remise',
            'categorie' => 'REMISE',
            'mode_calcul' => 'pourcentage',
            'valeur' => 4,
            'base_calcul' => 'tranche_ciblee',
            'cible_imputation' => 'tranche_1',
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->post('/administration/finances/bourses-remises/attribuer', [
                'compte_financier_id' => $this->compte->id,
                'bourse_remise_id' => $modeleTranche->id,
                'frais_scolaire_id' => $this->fraisObligatoire->id,
            ])
            ->assertSessionHasNoErrors();

        $attribution = EleveBourseRemise::where('compte_financier_id', $this->compte->id)
            ->where('bourse_remise_id', $modeleTranche->id)
            ->firstOrFail();

        // 4 % de 35 000 = 1 400 FCFA
        $this->assertEquals(1400.0, (float) $attribution->montant_calcule);
        $this->compte->refresh();
        $this->assertEquals(1400.0, (float) $this->compte->remise_bourse);
        $this->assertEquals(73600.0, (float) $this->compte->solde_restant);

        $dossier = app(FinanceService::class)->obtenirEcheancierEleve($this->compte);
        $ligneT1 = collect($dossier['lignes'])->firstWhere('id', $this->fraisObligatoire->id);
        $this->assertNotNull($ligneT1);
        $this->assertEquals(1400.0, (float) $ligneT1['remise_appliquee']);
        $this->assertEquals(33600.0, (float) $ligneT1['montant']);
    }

    /**
     * Vérifie que les retraits répétés (double-clic) d'une remise, d'une dispense financière
     * ou d'une souscription optionnelle répondent proprement sans provoquer d'erreur 404,
     * tout en bloquant (403) tout retrait depuis un autre établissement.
     */
    public function test_retrait_idempotent_remise_dispense_option_evite_404_double_clic(): void
    {
        Permission::firstOrCreate(['name' => 'bourses.attribuer', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'dispenses_financieres.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'options_financieres.manage', 'guard_name' => 'web']);

        // Création d'un autre campus et d'un utilisateur local cloisonné
        $autreEtab = Etablissement::create([
            'institution_id' => $this->etablissement->institution_id,
            'nom' => 'Campus Annexe',
            'code' => 'ANX-02',
            'type' => 'secondaire_general',
        ]);
        $autreUser = User::factory()->create(['etablissement_id' => $autreEtab->id]);
        $autreUser->givePermissionTo(['bourses.attribuer', 'dispenses_financieres.manage', 'options_financieres.manage']);

        /** @var FinanceService $financeService */
        $financeService = app(FinanceService::class);

        $modele = BourseRemise::create([
            'etablissement_id' => $this->etablissement->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'code' => 'REM-DBL-5000',
            'nom' => 'Remise Fratrie',
            'type' => 'remise',
            'categorie' => 'REMISE',
            'mode_calcul' => 'montant_fixe',
            'valeur' => 5000,
            'is_active' => true,
        ]);

        $attribution = $financeService->attribuerBourseRemise($this->compte, $modele, 'Test double clic', $this->adminUser);
        $dispense = $financeService->accorderDispenseFinanciere($this->compte, $this->fraisObligatoire, 'partielle', 2000, 'Motif test', $this->adminUser);
        $souscription = $financeService->souscrireFraisOptionnel($this->compte->id, $this->fraisOptionnelBus->id);

        // Sécurité multi-campus : un utilisateur d'un autre campus ne doit pas pouvoir retirer la remise (403)
        $this->actingAs($autreUser)
            ->delete("/administration/finances/bourses-remises/attributions/{$attribution->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('eleve_bourses_remises', ['id' => $attribution->id]);

        // 1er clic puis 2ème clic sur « Retirer » remise : aucune erreur 404
        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/bourses-remises/attributions/{$attribution->id}")
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('eleve_bourses_remises', ['id' => $attribution->id]);

        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/bourses-remises/attributions/{$attribution->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        // 1er clic puis 2ème clic sur « Retirer » dispense : aucune erreur 404
        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/dispenses/{$dispense->id}")
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/dispenses/{$dispense->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        // 1er clic puis 2ème clic sur « Résilier » option : aucune erreur 404
        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/souscriptions-optionnelles/{$souscription->id}")
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->actingAs($this->adminUser)
            ->delete("/administration/finances/souscriptions-optionnelles/{$souscription->id}")
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}

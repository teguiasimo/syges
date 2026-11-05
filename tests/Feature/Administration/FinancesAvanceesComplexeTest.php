<?php

namespace Tests\Feature\Administration;

use App\Models\AnneeScolaire;
use App\Models\Caisse;
use App\Models\Classe;
use App\Models\CompteFinancierEleve;
use App\Models\Cycle;
use App\Models\Eleve;
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
}

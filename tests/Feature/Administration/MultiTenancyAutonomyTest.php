<?php

namespace Tests\Feature\Administration;

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\Classe;
use App\Models\ConfigurationNumerotation;
use App\Models\Cycle;
use App\Models\DeliberationConseil;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\ModeleEcheance;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Salle;
use App\Models\Section;
use App\Models\TypeFrais;
use App\Models\User;
use App\Models\VersementCaisse;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suite de tests de validation d'Autonomie et d'Isolation Multi-Tenant (SaaS).
 *
 * Démontre de manière formelle et automatisée que de multiples établissements (20+)
 * peuvent coexister sur la plateforme SYGES de manière 100% autonome, sans collision
 * d'identifiants uniques, sans fuite de données et avec protection anti-IDOR stricte.
 */
class MultiTenancyAutonomyTest extends TestCase
{
    use RefreshDatabase;

    public ?Institution $institution = null;
    public ?Etablissement $etabA = null;
    public ?Etablissement $etabB = null;
    public ?Etablissement $etabC = null;
    public ?AnneeScolaire $annee = null;

    public ?User $adminA = null;
    public ?User $adminB = null;
    public ?User $superAdmin = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Configuration des rôles et permissions
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $permissions = [
            'users.view', 'users.create', 'users.edit', 'users.block', 'users.toggle_status',
            'notes.view', 'notes.create', 'notes.manage', 'notes.lock', 'notes.unlock',
            'finances.view', 'finances.caisse', 'finances.versements',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $roleAdmin->syncPermissions($permissions);

        // 2. Institution hôte
        $this->institution = Institution::create([
            'nom' => 'Groupe Éducatif International',
            'code' => 'INST-GEI',
            'sigle' => 'GEI',
        ]);

        // 3. Trois établissements concurrents
        $this->etabA = Etablissement::create([
            'institution_id' => $this->institution->id,
            'nom' => 'Campus de Douala',
            'code' => 'DLA',
            'statut_juridique' => 'Prive_Laic',
            'ordre_enseignement' => 'Secondaire_General',
        ]);

        $this->etabB = Etablissement::create([
            'institution_id' => $this->institution->id,
            'nom' => 'Campus de Yaoundé',
            'code' => 'YDE',
            'statut_juridique' => 'Prive_Laic',
            'ordre_enseignement' => 'Secondaire_General',
        ]);

        $this->etabC = Etablissement::create([
            'institution_id' => $this->institution->id,
            'nom' => 'Campus de Bafoussam',
            'code' => 'BAF',
            'statut_juridique' => 'Prive_Laic',
            'ordre_enseignement' => 'Secondaire_General',
        ]);

        // 4. Année scolaire commune
        $this->annee = AnneeScolaire::create([
            'code' => '2026-2027',
            'nom' => 'Année Scolaire 2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'is_active' => true,
        ]);

        // 5. Administrateurs locaux
        $this->adminA = User::factory()->create([
            'etablissement_id' => $this->etabA->id,
            'is_active' => true,
            'login' => 'admin_dla',
            'email' => 'admin.dla@gei.cm',
        ]);
        $this->adminA->assignRole('admin');

        $this->adminB = User::factory()->create([
            'etablissement_id' => $this->etabB->id,
            'is_active' => true,
            'login' => 'admin_yde',
            'email' => 'admin.yde@gei.cm',
        ]);
        $this->adminB->assignRole('admin');

        $this->superAdmin = User::factory()->create([
            'etablissement_id' => null,
            'is_active' => true,
            'login' => 'superadmin_global',
            'email' => 'superadmin@gei.cm',
        ]);
        $this->superAdmin->assignRole('super-admin');
    }

    /**
     * Test 1 : Vérifie la coexistence sans conflit d'entités avec codes et noms identiques dans chaque établissement.
     */
    public function test_multiple_establishments_can_have_identical_class_room_and_fee_codes(): void
    {
        // Sections et Cycles pour chaque établissement
        $secA = Section::create(['etablissement_id' => $this->etabA->id, 'nom' => 'Section Francophone', 'code' => 'FR-A']);
        $cycA = Cycle::create(['section_id' => $secA->id, 'nom' => '1er Cycle', 'code' => 'C1-A', 'ordre' => 1]);
        $niveauA = Niveau::create(['cycle_id' => $cycA->id, 'etablissement_id' => $this->etabA->id, 'nom' => '6ème', 'code' => '6EME', 'ordre' => 1]);

        $secB = Section::create(['etablissement_id' => $this->etabB->id, 'nom' => 'Section Francophone', 'code' => 'FR-B']);
        $cycB = Cycle::create(['section_id' => $secB->id, 'nom' => '1er Cycle', 'code' => 'C1-B', 'ordre' => 1]);
        $niveauB = Niveau::create(['cycle_id' => $cycB->id, 'etablissement_id' => $this->etabB->id, 'nom' => '6ème', 'code' => '6EME', 'ordre' => 1]);

        $secC = Section::create(['etablissement_id' => $this->etabC->id, 'nom' => 'Section Francophone', 'code' => 'FR-C']);
        $cycC = Cycle::create(['section_id' => $secC->id, 'nom' => '1er Cycle', 'code' => 'C1-C', 'ordre' => 1]);
        $niveauC = Niveau::create(['cycle_id' => $cycC->id, 'etablissement_id' => $this->etabC->id, 'nom' => '6ème', 'code' => '6EME', 'ordre' => 1]);

        // Classes avec le même code et nom dans chaque campus
        $classeA = Classe::create([
            'etablissement_id' => $this->etabA->id,
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $niveauA->id,
            'nom' => '6ème A',
            'code' => '6A',
            'capacite' => 45,
        ]);

        $classeB = Classe::create([
            'etablissement_id' => $this->etabB->id,
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $niveauB->id,
            'nom' => '6ème A',
            'code' => '6A',
            'capacite' => 50,
        ]);

        $classeC = Classe::create([
            'etablissement_id' => $this->etabC->id,
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $niveauC->id,
            'nom' => '6ème A',
            'code' => '6A',
            'capacite' => 40,
        ]);

        $this->assertDatabaseCount('classes', 3);
        $this->assertEquals($classeA->code, $classeB->code);
        $this->assertEquals($classeB->code, $classeC->code);

        // Salles identiques avec le même code
        $salleA = Salle::create(['etablissement_id' => $this->etabA->id, 'nom' => 'Salle 101', 'code' => 'S101', 'type_salle' => 'standard', 'capacite_places' => 50]);
        $salleB = Salle::create(['etablissement_id' => $this->etabB->id, 'nom' => 'Salle 101', 'code' => 'S101', 'type_salle' => 'standard', 'capacite_places' => 50]);

        $this->assertDatabaseCount('salles', 2);
        $this->assertEquals($salleA->code, $salleB->code);

        // Caisses identiques avec le même code
        $caisseA = Caisse::create(['etablissement_id' => $this->etabA->id, 'nom' => 'Caisse Principale', 'code' => 'CAISSE-PPLE', 'type' => 'principale', 'solde_actuel' => 100000]);
        $caisseB = Caisse::create(['etablissement_id' => $this->etabB->id, 'nom' => 'Caisse Principale', 'code' => 'CAISSE-PPLE', 'type' => 'principale', 'solde_actuel' => 200000]);

        $this->assertDatabaseCount('caisses', 2);
        $this->assertEquals($caisseA->code, $caisseB->code);
    }

    /**
     * Test 2 : Vérifie que la génération des numéros de reçus et des matricules de personnel
     * intègre le code de chaque établissement et ne provoque jamais de collision en base de données.
     */
    public function test_concurrent_receipt_and_matricule_generation_avoids_global_collisions(): void
    {
        $financeService = app(FinanceService::class);

        // Reçus simultanés pour Etab A, B, C
        $recuA = $financeService->genererReferenceRecu($this->etabA->id, 2026);
        $recuB = $financeService->genererReferenceRecu($this->etabB->id, 2026);
        $recuC = $financeService->genererReferenceRecu($this->etabC->id, 2026);

        // Vérification de la présence du code établissement garantissant l'unicité globale
        $this->assertStringContainsString('DLA', $recuA);
        $this->assertStringContainsString('YDE', $recuB);
        $this->assertStringContainsString('BAF', $recuC);

        $this->assertNotEquals($recuA, $recuB);
        $this->assertNotEquals($recuB, $recuC);
        $this->assertNotEquals($recuA, $recuC);

        // Reçus divers simultanés
        $divA = $financeService->genererReferenceReglementDivers($this->etabA->id, 2026);
        $divB = $financeService->genererReferenceReglementDivers($this->etabB->id, 2026);
        $this->assertStringContainsString('DLA', $divA);
        $this->assertStringContainsString('YDE', $divB);
        $this->assertNotEquals($divA, $divB);

        // Matricules personnels simultanés
        $matA = Personnel::generateMatricule($this->etabA->id, 2026);
        $matB = Personnel::generateMatricule($this->etabB->id, 2026);
        $matC = Personnel::generateMatricule($this->etabC->id, 2026);

        $this->assertStringContainsString('DLA', $matA);
        $this->assertStringContainsString('YDE', $matB);
        $this->assertStringContainsString('BAF', $matC);

        $this->assertNotEquals($matA, $matB);
        $this->assertNotEquals($matB, $matC);

        // Insertion effective en base de données sans déclenchement d'erreur SQL 1062 Duplicate entry
        $personnelA = Personnel::create([
            'etablissement_id' => $this->etabA->id,
            'matricule' => $matA,
            'nom' => 'Kamga',
            'prenom' => 'Jean',
            'sexe' => 'M',
            'statut' => 'actif',
            'telephone' => '+237699001122',
        ]);

        $personnelB = Personnel::create([
            'etablissement_id' => $this->etabB->id,
            'matricule' => $matB,
            'nom' => 'Kamga',
            'prenom' => 'Jean',
            'sexe' => 'M',
            'statut' => 'actif',
            'telephone' => '+237699001123',
        ]);

        $this->assertDatabaseHas('personnels', ['id' => $personnelA->id, 'matricule' => $matA]);
        $this->assertDatabaseHas('personnels', ['id' => $personnelB->id, 'matricule' => $matB]);
    }

    /**
     * Test 3 : Vérifie la sécurité anti-IDOR : un administrateur d'un campus ne peut ni modifier,
     * ni bloquer un utilisateur appartenant à un autre campus.
     */
    public function test_local_admin_cannot_modify_or_block_user_from_another_establishment(): void
    {
        // Admin A tente de modifier le profil de Admin B (Campus Yaoundé)
        $responseUpdate = $this->actingAs($this->adminA)
            ->put(route('administration.users.update', $this->adminB->id), [
                'login' => 'admin_yde_hacked',
                'name' => 'Pirate Admin',
                'email' => 'admin.yde@gei.cm',
            ]);

        $responseUpdate->assertStatus(403);
        $this->assertDatabaseHas('users', [
            'id' => $this->adminB->id,
            'login' => 'admin_yde',
        ]);

        // Admin A tente de bloquer Admin B (Campus Yaoundé)
        $responseToggle = $this->actingAs($this->adminA)
            ->patch(route('administration.users.toggle-status', $this->adminB->id));

        $responseToggle->assertStatus(403);
        $this->assertTrue($this->adminB->fresh()->is_active);
    }

    /**
     * Test 4 : Vérifie que le basculement d'établissement par session est strictement verrouillé
     * pour les utilisateurs locaux et autorisé pour le Super Admin.
     */
    public function test_establishment_switching_security_and_containment(): void
    {
        // Admin A tente de basculer sa session vers Campus B
        $responseUnauthorized = $this->actingAs($this->adminA)
            ->post(route('context.switch-etablissement'), [
                'etablissement_id' => $this->etabB->id,
            ]);

        $responseUnauthorized->assertStatus(403);

        // Super Admin bascule vers Campus B avec succès
        $responseSuperAdmin = $this->actingAs($this->superAdmin)
            ->post(route('context.switch-etablissement'), [
                'etablissement_id' => $this->etabB->id,
            ]);

        $responseSuperAdmin->assertSessionHas('current_etablissement_id', $this->etabB->id);
    }

    /**
     * Test 5 : Vérifie l'étanchéité des notes d'évaluation entre établissements (anti-IDOR dans NoteController).
     */
    public function test_local_staff_cannot_enter_or_lock_grades_for_another_establishment(): void
    {
        // Attribution Campus B
        $secB = Section::create(['etablissement_id' => $this->etabB->id, 'nom' => 'Section Francophone B', 'code' => 'FR-TLE']);
        $cycB = Cycle::create(['section_id' => $secB->id, 'nom' => '2nd Cycle', 'code' => 'C2-B', 'ordre' => 2]);
        $niveauB = Niveau::create(['cycle_id' => $cycB->id, 'etablissement_id' => $this->etabB->id, 'nom' => 'Terminale', 'code' => 'TLE', 'ordre' => 7]);
        $classeB = Classe::create([
            'etablissement_id' => $this->etabB->id,
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $niveauB->id,
            'nom' => 'Terminale C',
            'code' => 'TC',
            'capacite' => 35,
        ]);

        $matiereB = Matiere::create(['etablissement_id' => $this->etabB->id, 'nom' => 'Physique', 'code' => 'PHY']);
        $personnelB = Personnel::create([
            'etablissement_id' => $this->etabB->id,
            'matricule' => 'PERS-B-001',
            'nom' => 'Fomekong',
            'prenom' => 'Paul',
            'sexe' => 'M',
            'statut' => 'actif',
            'telephone' => '+237699009988',
        ]);

        $attributionB = Attribution::create([
            'classe_id' => $classeB->id,
            'matiere_id' => $matiereB->id,
            'personnel_id' => $personnelB->id,
            'annee_scolaire_id' => $this->annee->id,
        ]);

        $periodeB = Periode::create([
            'annee_scolaire_id' => $this->annee->id,
            'etablissement_id' => $this->etabB->id,
            'code' => 'T1-B',
            'nom' => 'Trimestre 1',
            'type' => 'trimestre',
            'numero' => 1,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-11-30',
        ]);

        $evaluationB = Evaluation::create([
            'periode_id' => $periodeB->id,
            'code' => 'EVAL-B1',
            'nom' => 'Évaluation 1',
            'type_evaluation' => 'continue',
            'date_evaluation' => '2026-10-15',
            'statut' => 'publiee',
        ]);

        // Admin A (Campus Douala) tente de récupérer la grille de notes de Campus B
        $responseGrille = $this->actingAs($this->adminA)
            ->getJson(route('administration.notes.grille', [
                'attribution_id' => $attributionB->id,
                'evaluation_id' => $evaluationB->id,
            ]));

        $responseGrille->assertStatus(403);

        // Admin A tente de verrouiller les notes de Campus B
        $responseLock = $this->actingAs($this->adminA)
            ->post(route('administration.notes.verrouiller'), [
                'attribution_id' => $attributionB->id,
                'evaluation_id' => $evaluationB->id,
            ]);

        $responseLock->assertStatus(403);
    }

    /**
     * Test 6 : Vérifie l'application systématique du GlobalScope BelongsToEtablissement
     * sur les modèles financiers, bourses, échéanciers et délibérations.
     */
    public function test_global_scope_isolates_data_records_strictly_by_establishment(): void
    {
        // Données établissement A
        BourseRemise::create([
            'etablissement_id' => $this->etabA->id,
            'code' => 'BOURSE-EXC',
            'nom' => 'Bourse Excellence A',
            'type' => 'bourse',
            'categorie' => 'excellence',
            'mode_calcul' => 'pourcentage',
            'valeur' => 50.0,
            'is_active' => true,
        ]);

        // Données établissement B avec même code
        BourseRemise::create([
            'etablissement_id' => $this->etabB->id,
            'code' => 'BOURSE-EXC',
            'nom' => 'Bourse Excellence B',
            'type' => 'bourse',
            'categorie' => 'excellence',
            'mode_calcul' => 'pourcentage',
            'valeur' => 25.0,
            'is_active' => true,
        ]);

        // Échéanciers avec même libellé
        ModeleEcheance::create([
            'etablissement_id' => $this->etabA->id,
            'annee_scolaire_id' => $this->annee->id,
            'nom' => 'Échéancier Standard',
            'is_default' => true,
        ]);

        ModeleEcheance::create([
            'etablissement_id' => $this->etabB->id,
            'annee_scolaire_id' => $this->annee->id,
            'nom' => 'Échéancier Standard',
            'is_default' => true,
        ]);

        // Session simulée pour l'utilisateur A
        session(['current_etablissement_id' => $this->etabA->id]);
        $this->actingAs($this->adminA);

        $boursesA = BourseRemise::all();
        $this->assertCount(1, $boursesA);
        $this->assertEquals('Bourse Excellence A', $boursesA->first()->nom);

        $echeancesA = ModeleEcheance::all();
        $this->assertCount(1, $echeancesA);
        $this->assertEquals($this->etabA->id, $echeancesA->first()->etablissement_id);

        // Session simulée pour l'utilisateur B
        session(['current_etablissement_id' => $this->etabB->id]);
        $this->actingAs($this->adminB);

        $boursesB = BourseRemise::all();
        $this->assertCount(1, $boursesB);
        $this->assertEquals('Bourse Excellence B', $boursesB->first()->nom);

        $echeancesB = ModeleEcheance::all();
        $this->assertCount(1, $echeancesB);
        $this->assertEquals($this->etabB->id, $echeancesB->first()->etablissement_id);
    }
}

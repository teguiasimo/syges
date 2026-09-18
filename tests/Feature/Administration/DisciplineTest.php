<?php

namespace Tests\Feature\Administration;

use App\Models\Assiduite;
use App\Models\DisciplineBulletin;
use App\Models\SanctionDisciplinaire;
use App\Models\User;
use App\Services\DisciplineService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests fonctionnels pour le Module 12 : Absences, Retards & Discipline (Gesco V1.1).
 *
 * Valide :
 * - Le contrôle d'accès RBAC (discipline.view, discipline.manage, discipline.sanction).
 * - La consultation de l'espace d'assiduité avec ses composantes Inertia.
 * - Le signalement d'une absence et d'un retard avec synchronisation sur le bulletin scolaire.
 * - La justification a posteriori d'une absence et mise à jour de la balance (J vs NJ).
 * - La suppression d'un événement d'assiduité et recalcul dynamique du bulletin.
 * - Le prononcé d'une sanction disciplinaire et sa répercussion dans le registre et le bulletin.
 * - La règle 12.4 de déchéance des mentions honorifiques en cas d'absentéisme non justifié >= 15h.
 */
class DisciplineTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public ?User $unauthorizedUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les permissions de discipline
        foreach (['discipline.view', 'discipline.manage', 'discipline.sanction'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole($adminRole);

        $this->unauthorizedUser = User::factory()->create([
            'is_active' => true,
        ]);

        // Structure académique
        $this->institution = \App\Models\Institution::firstOrCreate(
            ['code' => 'INST-DISC'],
            ['nom' => 'Institution Discipline', 'sigle' => 'IND']
        );

        $this->etablissement = \App\Models\Etablissement::firstOrCreate(
            ['code' => 'ETAB-DISC'],
            [
                'institution_id' => $this->institution->id,
                'nom' => 'Lycée Discipline',
                'statut_juridique' => 'Public',
                'ordre_enseignement' => 'Secondaire General',
                'seuil_absences_decheance_mentions' => 15,
            ]
        );

        $this->section = \App\Models\Section::firstOrCreate(
            ['code' => 'SEC-FR-DISC', 'etablissement_id' => $this->etablissement->id],
            ['nom' => 'Section Francophone']
        );

        $this->cycle = \App\Models\Cycle::firstOrCreate(
            ['code' => 'CYC-1-DISC', 'section_id' => $this->section->id],
            ['nom' => 'Premier Cycle', 'ordre' => 1]
        );

        $this->niveau = \App\Models\Niveau::firstOrCreate(
            ['code' => 'NIV-4-DISC', 'cycle_id' => $this->cycle->id],
            ['nom' => '4ème Discipline', 'ordre' => 1]
        );

        $this->annee = \App\Models\AnneeScolaire::firstOrCreate(
            ['code' => '2026-2027'],
            [
                'nom' => 'Année 2026-2027',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-06-30',
                'statut' => 'en_cours',
                'is_current' => true,
            ]
        );

        $this->classe = \App\Models\Classe::firstOrCreate(
            ['code' => '4E-DISC', 'niveau_id' => $this->niveau->id],
            ['nom' => '4ème A Discipline', 'capacite_max' => 50, 'is_active' => true]
        );

        $this->periode = \App\Models\Periode::firstOrCreate(
            ['code' => 'TRIM-1-DISC', 'annee_scolaire_id' => $this->annee->id],
            ['nom' => '1er Trimestre', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-11-30']
        );

        $this->matiere = \App\Models\Matiere::firstOrCreate(
            ['code' => 'MATH-DISC'],
            ['nom' => 'Mathématiques', 'code_matiere' => 'MATH']
        );

        $this->eleve1 = \App\Models\Eleve::firstOrCreate(
            ['matricule' => 'DISC-EL-001'],
            [
                'nom' => 'KAMGA',
                'prenom' => 'Alain',
                'date_naissance' => '2010-05-15',
                'sexe' => 'M',
            ]
        );

        $this->inscription1 = \App\Models\Inscription::firstOrCreate(
            [
                'eleve_id' => $this->eleve1->id,
                'annee_scolaire_id' => $this->annee->id,
                'classe_id' => $this->classe->id,
            ],
            [
                'code' => 'INS-DISC-001',
                'date_inscription' => '2026-09-05',
                'statut' => 'validee',
            ]
        );
    }

    /**
     * Vérifie que l'accès sans permission est refusé (403).
     */
    public function test_acces_refuse_sans_permission_discipline(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('administration.discipline.index'));

        $response->assertStatus(403);
    }

    /**
     * Vérifie l'affichage de l'index de discipline pour un administrateur.
     */
    public function test_administrateur_peut_acceder_espace_discipline(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('administration.discipline.index', [
                'etablissement_id' => $this->etablissement->id,
                'periode_id' => $this->periode->id,
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('administration/discipline/index')
            ->has('assiduites')
            ->has('sanctions')
            ->has('syntheses')
            ->has('periodes')
        );
    }

    /**
     * Vérifie l'enregistrement d'une absence non justifiée et la mise à jour immédiate du bulletin.
     */
    public function test_enregistrement_absence_actualise_synthese_bulletin(): void
    {
        $postData = [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'type_evenement' => 'absence',
            'date_evenement' => now()->toDateString(),
            'nombre_heures' => 4,
            'matiere_id' => $this->matiere->id,
            'statut_justification' => 'non_justifiee',
            'motif_justification' => null,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.discipline.assiduites.store'), $postData);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('assiduites', [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'type_evenement' => 'absence',
            'nombre_heures' => 4,
            'statut_justification' => 'non_justifiee',
        ]);

        // Vérifier que le bulletin a été mis à jour
        $this->assertDatabaseHas('disciplines_bulletins', [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'absences_non_justifiees' => 4,
            'absences_justifiees' => 0,
        ]);
    }

    /**
     * Vérifie l'enregistrement d'un retard.
     */
    public function test_enregistrement_retard_actualise_compteur_bulletin(): void
    {
        $postData = [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'type_evenement' => 'retard',
            'date_evenement' => now()->toDateString(),
            'nombre_heures' => 1,
            'matiere_id' => null,
            'statut_justification' => 'non_justifiee',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.discipline.assiduites.store'), $postData);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('disciplines_bulletins', [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'retards' => 1,
        ]);
    }

    /**
     * Vérifie la justification a posteriori d'une absence.
     */
    public function test_justification_a_posteriori_met_a_jour_les_cumuls(): void
    {
        $assiduite = Assiduite::create([
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'type_evenement' => 'absence',
            'date_evenement' => now()->toDateString(),
            'nombre_heures' => 6,
            'statut_justification' => 'non_justifiee',
        ]);

        app(DisciplineService::class)->actualiserSyntheseBulletin($this->inscription1->id, $this->periode->id);

        $this->assertDatabaseHas('disciplines_bulletins', [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'absences_non_justifiees' => 6,
            'absences_justifiees' => 0,
        ]);

        // Justification
        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.discipline.assiduites.justifier', $assiduite), [
                'motif_justification' => 'Certificat médical présenté et validé.',
            ]);

        $response->assertSessionHasNoErrors();

        $assiduite->refresh();
        $this->assertEquals('justifiee', $assiduite->statut_justification);
        $this->assertStringContainsString('Certificat médical', $assiduite->motif_justification);

        // La synthèse doit maintenant refléter 6h justifiées et 0h non justifiées
        $this->assertDatabaseHas('disciplines_bulletins', [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'absences_non_justifiees' => 0,
            'absences_justifiees' => 6,
        ]);
    }

    /**
     * Vérifie la suppression d'une absence et le recalcul de la synthèse.
     */
    public function test_suppression_assiduite_recalcule_bulletin(): void
    {
        $assiduite = Assiduite::create([
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'type_evenement' => 'absence',
            'date_evenement' => now()->toDateString(),
            'nombre_heures' => 8,
            'statut_justification' => 'non_justifiee',
        ]);

        app(DisciplineService::class)->actualiserSyntheseBulletin($this->inscription1->id, $this->periode->id);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('administration.discipline.assiduites.destroy', $assiduite));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('assiduites', ['id' => $assiduite->id]);

        $this->assertDatabaseHas('disciplines_bulletins', [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'absences_non_justifiees' => 0,
        ]);
    }

    /**
     * Vérifie le prononcé d'une sanction disciplinaire et sa présence dans le bulletin.
     */
    public function test_prononce_sanction_disciplinaire_enregistre_dans_bulletin(): void
    {
        $postData = [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'date_decision' => now()->toDateString(),
            'type_sanction' => 'exclusion_temporaire',
            'motif' => 'Indiscipline caractérisée et perturbation de cours répétée.',
            'nombre_jours_ou_heures' => 3,
            'decide_par' => 'Conseil de Discipline',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('administration.discipline.sanctions.store'), $postData);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sanctions_disciplinaires', [
            'inscription_id' => $this->inscription1->id,
            'type_sanction' => 'exclusion_temporaire',
            'nombre_jours_ou_heures' => 3,
        ]);

        $this->assertDatabaseHas('disciplines_bulletins', [
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'exclusions_jours' => 3,
        ]);
    }

    /**
     * Vérifie la règle 12.4 de déchéance des mentions au tableau d'honneur (absences non justifiées >= 15h).
     */
    public function test_regle_decheance_tableau_honneur_absences_non_justifiees_15h(): void
    {
        $service = app(DisciplineService::class);

        // 1. Initialement : 10h non justifiées -> pas de déchéance
        Assiduite::create([
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'type_evenement' => 'absence',
            'date_evenement' => now()->toDateString(),
            'nombre_heures' => 10,
            'statut_justification' => 'non_justifiee',
        ]);
        $service->actualiserSyntheseBulletin($this->inscription1->id, $this->periode->id);

        $res1 = $service->evaluerEligibiliteMentions($this->inscription1, $this->periode);
        $this->assertTrue($res1['eligible']);

        // 2. Ajout de 6h supplémentaires non justifiées -> total 16h >= 15h -> Déchéance active !
        Assiduite::create([
            'inscription_id' => $this->inscription1->id,
            'periode_id' => $this->periode->id,
            'type_evenement' => 'absence',
            'date_evenement' => now()->toDateString(),
            'nombre_heures' => 6,
            'statut_justification' => 'non_justifiee',
        ]);
        $service->actualiserSyntheseBulletin($this->inscription1->id, $this->periode->id);

        $res2 = $service->evaluerEligibiliteMentions($this->inscription1, $this->periode);
        $this->assertFalse($res2['eligible']);
    }
}

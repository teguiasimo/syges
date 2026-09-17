<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Evaluation;
use App\Models\Periode;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tests de fonctionnalités pour le Module 3 : Gestion des Périodes & Paramètres d'Évaluation (Gesco).
 *
 * Vérifie :
 * - Le contrôle d'accès selon les permissions (periodes.view, periodes.manage, periodes.lock).
 * - La consultation de l'année active, des trimestres et des séquences avec TanStack Table.
 * - La création d'une année scolaire avec auto-génération de la structure officielle (3 trimestres, 6 séquences).
 * - La bascule de l'année scolaire active.
 * - Le paramétrage du mode de calcul de la moyenne trimestrielle (Option A : Arithmétique vs Option B : Pondérée 100%).
 * - Le verrouillage / déverrouillage de la saisie des notes par séquence.
 * - L'intégrité référentielle lors de la suppression de périodes.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $permissions = [
        'periodes.view',
        'periodes.manage',
        'periodes.lock',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    // Année de test
    $this->annee = AnneeScolaire::firstOrCreate(
        ['code' => '2026-2027'],
        [
            'nom' => 'Année Scolaire 2026-2027',
            'nom_en' => 'Academic Year 2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'en_cours',
            'mode_calcul_moyenne' => 'arithmetique_simple',
            'is_current' => true,
        ]
    );
});

test('guests are redirected to login when visiting periodes page', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('administration.periodes.index'));
    $response->assertRedirect(route('login'));
});

test('unauthorized users without periodes.view permission cannot access periodes page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('administration.periodes.index'));
    $response->assertForbidden();
});

test('authorized users can view periodes page with current academic year and trimestres', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->get(route('administration.periodes.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/periodes/index')
        ->has('annees')
        ->has('selectedAnnee')
        ->has('periodes')
        ->has('can')
        ->where('can.periodes_manage', true)
        ->where('can.periodes_lock', true)
    );
});

test('can create a new academic year with auto-generated standard 3 trimestres and 6 sequences', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.periodes.annees.store'), [
        'code' => '2027-2028',
        'nom' => 'Année Scolaire 2027-2028',
        'nom_en' => 'Academic Year 2027-2028',
        'date_debut' => '2027-09-01',
        'date_fin' => '2028-06-30',
        'statut' => 'en_preparation',
        'mode_calcul_moyenne' => 'arithmetique_simple',
        'is_current' => false,
        'auto_generate_structure' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('annees_scolaires', [
        'code' => '2027-2028',
        'nom' => 'Année Scolaire 2027-2028',
    ]);

    $newAnnee = AnneeScolaire::where('code', '2027-2028')->first();
    expect($newAnnee)->not->toBeNull();
    // Doit avoir 3 trimestres auto-générés
    expect($newAnnee->periodes()->count())->toBe(3);

    // Chaque trimestre doit avoir 2 séquences (total 6)
    $totalSequences = Evaluation::whereIn('periode_id', $newAnnee->periodes()->pluck('id'))->count();
    expect($totalSequences)->toBe(6);
});

test('can set an academic year as the current active year', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $otherAnnee = AnneeScolaire::create([
        'code' => '2028-2029',
        'nom' => 'Année Scolaire 2028-2029',
        'date_debut' => '2028-09-01',
        'date_fin' => '2029-06-30',
        'statut' => 'en_preparation',
        'mode_calcul_moyenne' => 'arithmetique_simple',
        'is_current' => false,
    ]);

    $response = $this->patch(route('administration.periodes.annees.set-current', $otherAnnee));
    $response->assertRedirect();

    expect($otherAnnee->fresh()->is_current)->toBeTrue();
    expect($this->annee->fresh()->is_current)->toBeFalse();
});

test('can update calculation mode between arithmetic simple and weighted percentage', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->patch(route('administration.periodes.annees.mode-calcul', $this->annee), [
        'mode_calcul_moyenne' => 'ponderee_pourcentage',
    ]);

    $response->assertRedirect();
    expect($this->annee->fresh()->mode_calcul_moyenne)->toBe('ponderee_pourcentage');

    // Revenir en arithmétique simple
    $response2 = $this->patch(route('administration.periodes.annees.mode-calcul', $this->annee), [
        'mode_calcul_moyenne' => 'arithmetique_simple',
    ]);

    $response2->assertRedirect();
    expect($this->annee->fresh()->mode_calcul_moyenne)->toBe('arithmetique_simple');
});

test('can create, update and delete a custom trimestre', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.periodes.trimestres.store'), [
        'annee_scolaire_id' => $this->annee->id,
        'code' => 'SEM-1',
        'nom' => 'Semestre 1',
        'nom_en' => 'Semester 1',
        'ordre' => 10,
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-01-31',
        'statut' => 'a_venir',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('periodes', [
        'code' => 'SEM-1',
        'nom' => 'Semestre 1',
    ]);

    $trimestre = Periode::where('code', 'SEM-1')->where('annee_scolaire_id', $this->annee->id)->first();

    // Mise à jour
    $updResponse = $this->put(route('administration.periodes.trimestres.update', $trimestre), [
        'code' => 'SEM-1-MAJ',
        'nom' => 'Semestre 1 Modifié',
        'nom_en' => 'Updated Semester 1',
        'ordre' => 10,
        'statut' => 'en_cours',
    ]);
    $updResponse->assertRedirect();
    expect($trimestre->fresh()->nom)->toBe('Semestre 1 Modifié');

    // Suppression
    $delResponse = $this->delete(route('administration.periodes.trimestres.destroy', $trimestre));
    $delResponse->assertRedirect();
    $this->assertDatabaseMissing('periodes', ['id' => $trimestre->id]);
});

test('can toggle lock status on an evaluation with periodes.lock permission', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $trimestre = Periode::create([
        'annee_scolaire_id' => $this->annee->id,
        'code' => 'TRIM-TEST',
        'nom' => 'Trimestre Test',
        'ordre' => 1,
        'statut' => 'en_cours',
    ]);

    $evaluation = Evaluation::create([
        'periode_id' => $trimestre->id,
        'code' => 'SEQ-TEST',
        'nom' => 'Séquence Test',
        'ordre' => 1,
        'ponderation' => 50,
        'is_locked' => false,
        'statut' => 'ouverte',
    ]);

    // Verrouillage
    $lockResponse = $this->patch(route('administration.periodes.evaluations.toggle-lock', $evaluation));
    $lockResponse->assertRedirect();
    expect($evaluation->fresh()->is_locked)->toBeTrue();
    expect($evaluation->fresh()->statut)->toBe('verrouillee');

    // Déverrouillage
    $unlockResponse = $this->patch(route('administration.periodes.evaluations.toggle-lock', $evaluation));
    $unlockResponse->assertRedirect();
    expect($evaluation->fresh()->is_locked)->toBeFalse();
    expect($evaluation->fresh()->statut)->toBe('ouverte');
});

test('cannot delete a trimestre if it still contains evaluations', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $trimestre = Periode::create([
        'annee_scolaire_id' => $this->annee->id,
        'code' => 'TRIM-BLOC',
        'nom' => 'Trimestre Non Vide',
        'ordre' => 1,
        'statut' => 'en_cours',
    ]);

    Evaluation::create([
        'periode_id' => $trimestre->id,
        'code' => 'SEQ-CHILD',
        'nom' => 'Séquence Enfant',
        'ordre' => 1,
        'ponderation' => 100,
        'is_locked' => false,
        'statut' => 'a_venir',
    ]);

    // Tentative de suppression du trimestre
    $response = $this->delete(route('administration.periodes.trimestres.destroy', $trimestre));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('periodes', ['id' => $trimestre->id]);
});

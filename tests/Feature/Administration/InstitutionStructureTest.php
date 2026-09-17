<?php

/** @var \Tests\TestCase $this */

use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tests de fonctionnalités pour le module Institution & Structure Pédagogique dans SYGES.
 *
 * Ce fichier teste rigoureusement :
 * - L'accès sécurisé à l'interface d'administration selon les permissions accordées.
 * - Le mode Drill-down (exploration dynamique avec breadcrumbs réactifs) à chaque niveau hiérarchique :
 *     Institution -> Établissements -> Sections -> Cycles -> Niveaux -> Classes
 * - Les opérations CRUD pour les Établissements, Sections, Cycles, Niveaux et Classes.
 * - Les validations de sécurité et de dépendance (interdiction de suppression en cas de dépendances enfants).
 * - La prise en charge bilingue (Français / Anglais).
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $permissions = [
        'institutions.view',
        'institutions.edit',
        'etablissements.manage',
        'structures.manage',
        'classes.manage',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');

    // Assurer qu'une institution de base existe
    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-TEST'],
        [
            'sigle' => 'TEST-INST',
            'nom' => 'Groupe Scolaire de Test',
            'nom_en' => 'Test School Group',
            'devise' => 'Excellence et Rigueur',
            'devise_en' => 'Excellence and Discipline',
            'email' => 'contact@test-inst.org',
            'telephone_1' => '+237 600 000 000',
        ]
    );
});

test('guests are redirected to login when visiting institution page', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('administration.institution.index'));
    $response->assertRedirect(route('login'));
});

test('unauthorized users without institutions.view permission cannot access the module', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('administration.institution.index'));
    $response->assertForbidden();
});

test('authorized users can view root institution card and etablissements list', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->get(route('administration.institution.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/institution/index')
        ->has('institution')
        ->has('etablissements')
        ->has('breadcrumbs')
        ->has('can')
        ->where('can.institutions_edit', true)
        ->where('can.etablissements_manage', true)
        ->where('can.structures_manage', true)
        ->where('can.classes_manage', true)
    );
});

test('unauthorized users without institutions.edit permission cannot access institution edit page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('administration.institution.edit'));
    $response->assertForbidden();
});

test('authorized users can view dedicated institution edit page with data', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->get(route('administration.institution.edit'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/institution/edit')
        ->has('institution')
        ->where('institution.code', $this->institution->code)
    );
});

test('can update institution information in french and english', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->put(route('administration.institution.update'), [
        'code' => $this->institution->code,
        'nom' => 'Groupe Scolaire Mis à Jour',
        'nom_en' => 'Updated School Group',
        'sigle' => 'GSMJ',
        'devise' => 'Travail - Progrès',
        'devise_en' => 'Work - Progress',
        'email' => 'maj@test.org',
        'telephone_1' => '+237 699 111 222',
        'delegation_regionale' => 'Délégation du Centre',
        'delegation_regionale_en' => 'Center Regional Delegation',
        'delegation_departementale' => 'Délégation du Mfoundi',
        'delegation_departementale_en' => 'Mfoundi Division Delegation',
    ]);

    $response->assertRedirect(route('administration.institution.index'));
    $this->institution->refresh();
    expect($this->institution->nom)->toBe('Groupe Scolaire Mis à Jour');
    expect($this->institution->nom_en)->toBe('Updated School Group');
    expect($this->institution->sigle)->toBe('GSMJ');
});

test('can create and update an etablissement with bilingual fields', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.etablissements.store'), [
        'code' => 'CBT',
        'nom' => 'Collège Bilingue de Test',
        'nom_en' => 'Test Bilingual College',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
        'email' => 'cbt@test.org',
        'telephone_1' => '+237 677 888 999',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('etablissements', [
        'code' => 'CBT',
        'nom' => 'Collège Bilingue de Test',
    ]);

    $etablissement = Etablissement::where('code', 'CBT')->first();

    $updateResponse = $this->put(route('administration.etablissements.update', $etablissement), [
        'code' => 'CBI',
        'nom' => 'Collège Bilingue International',
        'nom_en' => 'International Bilingual College',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_Technique',
        'email' => 'cbi@test.org',
    ]);

    $updateResponse->assertRedirect();
    expect($etablissement->fresh()->code)->toBe('CBI');
});

test('drill down into an etablissement displays its sections', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $etablissement = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Lycée Test Drill',
        'code' => 'LTD',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $response = $this->get(route('administration.institution.index', ['etablissement_id' => $etablissement->id]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('selectedEtablissement.id', $etablissement->id)
        ->has('sections')
        ->has('breadcrumbs', 2)
    );
});

test('can manage sections, cycles, niveaux and classes hierarchically', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    // 1. Établissement
    $etab = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Complexe Pédagogique',
        'code' => 'CPED',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    // 2. Section
    $secResponse = $this->post(route('administration.sections.store'), [
        'etablissement_id' => $etab->id,
        'nom' => 'Section Francophone',
        'nom_en' => 'Francophone Sub-system',
        'code' => 'FR',
        'langue' => 'fr',
    ]);
    $secResponse->assertRedirect();
    $section = Section::where('code', 'FR')->where('etablissement_id', $etab->id)->first();
    expect($section)->not->toBeNull();

    // 3. Cycle
    $cycleResponse = $this->post(route('administration.cycles.store'), [
        'section_id' => $section->id,
        'nom' => 'Premier Cycle',
        'nom_en' => 'First Cycle',
        'code' => 'CYCLE-1',
        'ordre' => 1,
        'diplome_fin_cycle' => 'BEPC',
    ]);
    $cycleResponse->assertRedirect();
    $cycle = Cycle::where('code', 'CYCLE-1')->where('section_id', $section->id)->first();
    expect($cycle)->not->toBeNull();

    // 4. Niveau
    $nivResponse = $this->post(route('administration.niveaux.store'), [
        'cycle_id' => $cycle->id,
        'nom' => 'Sixième',
        'nom_en' => 'Form 1',
        'code' => '6EME',
        'ordre' => 1,
    ]);
    $nivResponse->assertRedirect();
    $niveau = Niveau::where('code', '6EME')->where('cycle_id', $cycle->id)->first();
    expect($niveau)->not->toBeNull();

    // 5. Classe
    $classeResponse = $this->post(route('administration.classes.store'), [
        'niveau_id' => $niveau->id,
        'nom' => '6ème M1',
        'code' => '6M1',
        'capacite_max' => 45,
    ]);
    $classeResponse->assertRedirect();
    $classe = Classe::where('code', '6M1')->where('niveau_id', $niveau->id)->first();
    expect($classe)->not->toBeNull();
    expect($classe->capacite_max)->toBe(45);
});

test('cannot delete an entity if it contains child dependencies', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $etab = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Etablissement Parent',
        'code' => 'EPAR',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $section = Section::create([
        'etablissement_id' => $etab->id,
        'nom' => 'Section Enfant',
        'code' => 'SENF',
        'langue' => 'en',
    ]);

    // Tentative de suppression de l'établissement alors qu'une section existe
    $response = $this->delete(route('administration.etablissements.destroy', $etab));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('etablissements', ['id' => $etab->id]);

    // Suppression de la section d'abord
    $secDelResponse = $this->delete(route('administration.sections.destroy', $section));
    $secDelResponse->assertSessionHas('success');
    $this->assertDatabaseMissing('sections', ['id' => $section->id]);

    // Maintenant l'établissement peut être supprimé
    $etabDelResponse = $this->delete(route('administration.etablissements.destroy', $etab));
    $etabDelResponse->assertSessionHas('success');
    $this->assertDatabaseMissing('etablissements', ['id' => $etab->id]);
});

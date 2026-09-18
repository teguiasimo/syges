<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Personnel;
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

/**
 * Vérifie que seuls les utilisateurs disposant de la permission 'etablissements.manage'
 * peuvent modifier ou supprimer un établissement, avec un rejet HTTP 403 strict dans le cas contraire.
 */
test('unauthorized users cannot update or delete an establishment', function () {
    /** @var \Tests\TestCase $this */
    // Création d'un utilisateur standard sans aucune permission de gestion
    $standardUser = User::factory()->create();
    $this->actingAs($standardUser);

    $etab = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Campus Sécurisé',
        'code' => 'CSEC',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    // Tentative non autorisée de mise à jour -> Rejet 403 Forbidden
    $updateResponse = $this->put(route('administration.etablissements.update', $etab), [
        'nom' => 'Nom Modifié Non Autorisé',
        'code' => 'HACK',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_Technique',
    ]);
    $updateResponse->assertForbidden();

    // Vérification que les données en base n'ont pas bougé
    $etab->refresh();
    expect($etab->nom)->toBe('Campus Sécurisé');
    expect($etab->code)->toBe('CSEC');

    // Tentative non autorisée de suppression -> Rejet 403 Forbidden
    $deleteResponse = $this->delete(route('administration.etablissements.destroy', $etab));
    $deleteResponse->assertForbidden();
    $this->assertDatabaseHas('etablissements', ['id' => $etab->id]);
});

/**
 * Vérifie que la page dédiée des paramètres et règles de l'établissement (Option B)
 * est accessible et permet d'ajuster le format de bulletin, le mode de calcul,
 * le seuil de discipline et les verrous de solvabilité financière.
 */
test('authorized users can view and update etablissement settings with sans_competences default', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    // 1. Création d'un établissement rapide
    $createResponse = $this->post(route('administration.etablissements.store'), [
        'code' => 'CAMP-SET',
        'nom' => 'Campus Paramétrable',
        'statut_juridique' => 'Prive_Confessionnel',
        'ordre_enseignement' => 'Secondaire_General',
    ]);
    $createResponse->assertRedirect();

    $etablissement = Etablissement::where('code', 'CAMP-SET')->firstOrFail();

    // Vérification que le format par défaut est bien 'sans_competences'
    expect($etablissement->mode_bulletin_defaut)->toBe('sans_competences');
    expect($etablissement->type_calcul_trimestre)->toBe('arithmetique');
    expect($etablissement->seuil_absences_decheance_mentions)->toBe(40);
    expect($etablissement->verrou_solvabilite_actif)->toBeFalse();

    // 2. Accès à la page dédiée de configuration
    $viewResponse = $this->get(route('administration.etablissements.settings', $etablissement));
    $viewResponse->assertOk();
    $viewResponse->assertInertia(fn ($page) => $page
        ->component('administration/institution/etablissement-settings')
        ->has('etablissement')
        ->where('etablissement.id', $etablissement->id)
        ->where('etablissement.mode_bulletin_defaut', 'sans_competences')
    );

    // 3. Mise à jour des options avancées
    $updateResponse = $this->put(route('administration.etablissements.settings.update', $etablissement), [
        'code' => 'CAMP-SET',
        'nom' => 'Campus Paramétrable Modifié',
        'nom_en' => 'Configurable Campus Updated',
        'statut_juridique' => 'Prive_Confessionnel',
        'ordre_enseignement' => 'Secondaire_General',
        'mode_bulletin_defaut' => 'avec_competences',
        'type_calcul_trimestre' => 'pondere',
        'seuil_absences_decheance_mentions' => 20,
        'verrou_solvabilite_actif' => true,
        'bonus_paiement_anticipe_actif' => true,
        'montant_bonus_anticipe' => 5000,
        'date_limite_bonus' => '2026-10-31',
    ]);

    $updateResponse->assertSessionHas('success');
    $etablissement->refresh();

    expect($etablissement->nom)->toBe('Campus Paramétrable Modifié');
    expect($etablissement->mode_bulletin_defaut)->toBe('avec_competences');
    expect($etablissement->type_calcul_trimestre)->toBe('pondere');
    expect($etablissement->seuil_absences_decheance_mentions)->toBe(20);
    expect($etablissement->verrou_solvabilite_actif)->toBeTrue();
    expect($etablissement->bonus_paiement_anticipe_actif)->toBeTrue();
    expect($etablissement->montant_bonus_anticipe)->toBe(5000.0);
});

/**
 * Vérifie que les utilisateurs non autorisés ne peuvent ni consulter ni modifier les paramètres d'établissement.
 */
test('unauthorized users cannot access or update etablissement settings', function () {
    /** @var \Tests\TestCase $this */
    $standardUser = User::factory()->create();
    $this->actingAs($standardUser);

    $etab = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Campus Protégé',
        'code' => 'CPROT',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    // Tentative d'accès à la page -> 403
    $this->get(route('administration.etablissements.settings', $etab))->assertForbidden();

    // Tentative de modification des règles -> 403
    $this->put(route('administration.etablissements.settings.update', $etab), [
        'code' => 'CPROT',
        'nom' => 'Piratage',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
        'mode_bulletin_defaut' => 'sans_competences',
        'type_calcul_trimestre' => 'arithmetique',
        'seuil_absences_decheance_mentions' => 40,
        'verrou_solvabilite_actif' => false,
        'bonus_paiement_anticipe_actif' => false,
    ])->assertForbidden();
});

/**
 * Vérifie que le nom d'une section est obligatoirement unique au sein d'un même établissement.
 */
test('section name must be unique per etablissement', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $etab1 = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Campus Alpha',
        'code' => 'CALPHA',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $etab2 = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Campus Beta',
        'code' => 'CBETA',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Primaire',
    ]);

    // 1. Création réussie d'une première section dans le campus Alpha
    $res1 = $this->post(route('administration.sections.store'), [
        'etablissement_id' => $etab1->id,
        'nom' => 'Section Francophone',
        'langue' => 'fr',
    ]);
    $res1->assertSessionHasNoErrors();
    $res1->assertRedirect();

    // 2. Tentative de créer un doublon avec exactement le même nom dans le même campus -> Erreur
    $resDuplicate = $this->post(route('administration.sections.store'), [
        'etablissement_id' => $etab1->id,
        'nom' => 'Section Francophone',
        'langue' => 'fr',
    ]);
    $resDuplicate->assertSessionHasErrors('nom');

    // 3. Tentative de doublon insensible à la casse ("section francophone") -> Erreur
    $resDuplicateCase = $this->post(route('administration.sections.store'), [
        'etablissement_id' => $etab1->id,
        'nom' => 'section francophone',
        'langue' => 'fr',
    ]);
    $resDuplicateCase->assertSessionHasErrors('nom');

    // 4. Même nom dans un établissement différent (Campus Beta) -> Autorisé
    $resEtab2 = $this->post(route('administration.sections.store'), [
        'etablissement_id' => $etab2->id,
        'nom' => 'Section Francophone',
        'langue' => 'fr',
    ]);
    $resEtab2->assertSessionHasNoErrors();
    $resEtab2->assertRedirect();
});

/**
 * Vérifie que le nom du cycle est strictement unique par établissement scolaire (insensible à la casse),
 * que le code technique est généré automatiquement s'il est omis,
 * et qu'un même nom de cycle peut être utilisé dans des établissements différents.
 */
test('cycle name must be unique per etablissement and auto-generates code', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $etab1 = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Lycée Polyvalent Alpha',
        'code' => 'LPALPHA',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $etab2 = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Collège Privé Beta',
        'code' => 'CPBETA',
        'statut_juridique' => 'Prive_Laic',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $section1A = Section::create([
        'etablissement_id' => $etab1->id,
        'nom' => 'Section Francophone',
        'code' => 'FR-ALP',
        'langue' => 'fr',
    ]);

    $section1B = Section::create([
        'etablissement_id' => $etab1->id,
        'nom' => 'Section Anglophone',
        'code' => 'EN-ALP',
        'langue' => 'en',
    ]);

    $section2 = Section::create([
        'etablissement_id' => $etab2->id,
        'nom' => 'Section Francophone',
        'code' => 'FR-BET',
        'langue' => 'fr',
    ]);

    // 1. Création réussie du 1er cycle dans section1A sans code explicite (auto-génération)
    $res1 = $this->post(route('administration.cycles.store'), [
        'section_id' => $section1A->id,
        'nom' => 'Premier Cycle',
        'diplome_fin_cycle' => 'BEPC',
        'ordre' => 1,
    ]);
    $res1->assertSessionHasNoErrors();
    $res1->assertRedirect();

    $cycle1 = Cycle::where('section_id', $section1A->id)->where('nom', 'Premier Cycle')->first();
    expect($cycle1)->not->toBeNull();
    expect($cycle1->etablissement_id)->toBe($etab1->id);
    expect($cycle1->code)->not->toBeEmpty();
    expect($cycle1->diplome_fin_cycle)->toBe('BEPC');

    // 2. Tentative de créer un doublon avec exactement le même nom dans la même section -> Erreur
    $resDuplicateSameSection = $this->post(route('administration.cycles.store'), [
        'section_id' => $section1A->id,
        'nom' => 'Premier Cycle',
        'ordre' => 2,
    ]);
    $resDuplicateSameSection->assertSessionHasErrors('nom');

    // 3. Tentative de créer un cycle avec le même nom dans une autre section du même établissement -> Erreur
    $resDuplicateSameEtab = $this->post(route('administration.cycles.store'), [
        'section_id' => $section1B->id,
        'nom' => 'Premier Cycle',
        'ordre' => 1,
    ]);
    $resDuplicateSameEtab->assertSessionHasErrors('nom');

    // 4. Tentative de doublon insensible à la casse ("premier cycle") -> Erreur
    $resDuplicateCase = $this->post(route('administration.cycles.store'), [
        'section_id' => $section1A->id,
        'nom' => 'premier cycle',
        'ordre' => 2,
    ]);
    $resDuplicateCase->assertSessionHasErrors('nom');

    // 5. Même nom de cycle dans un établissement distinct (etab2) -> Autorisé
    $resEtab2 = $this->post(route('administration.cycles.store'), [
        'section_id' => $section2->id,
        'nom' => 'Premier Cycle',
        'diplome_fin_cycle' => 'BEPC',
        'ordre' => 1,
    ]);
    $resEtab2->assertSessionHasNoErrors();
    $resEtab2->assertRedirect();

    $cycleEtab2 = Cycle::where('section_id', $section2->id)->where('nom', 'Premier Cycle')->first();
    expect($cycleEtab2)->not->toBeNull();
    expect($cycleEtab2->etablissement_id)->toBe($etab2->id);
});

/**
 * Vérifie que le nom du niveau est strictement unique par établissement scolaire (insensible à la casse),
 * que le code technique est généré automatiquement s'il est omis,
 * et qu'un même nom de niveau peut être utilisé dans des établissements différents.
 */
test('niveau name must be unique per etablissement and auto-generates code', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $etab1 = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Lycée Municipal 1',
        'code' => 'LMUN1',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $etab2 = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Lycée Municipal 2',
        'code' => 'LMUN2',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $section1 = Section::create([
        'etablissement_id' => $etab1->id,
        'nom' => 'Section Francophone',
        'code' => 'FR-M1',
        'langue' => 'fr',
    ]);

    $cycle1 = Cycle::create([
        'etablissement_id' => $etab1->id,
        'section_id' => $section1->id,
        'nom' => 'Premier Cycle',
        'code' => 'CYC1-M1',
        'ordre' => 1,
    ]);

    $cycle2 = Cycle::create([
        'etablissement_id' => $etab1->id,
        'section_id' => $section1->id,
        'nom' => 'Second Cycle',
        'code' => 'CYC2-M1',
        'ordre' => 2,
    ]);

    $section2 = Section::create([
        'etablissement_id' => $etab2->id,
        'nom' => 'Section Francophone',
        'code' => 'FR-M2',
        'langue' => 'fr',
    ]);

    $cycleEtab2 = Cycle::create([
        'etablissement_id' => $etab2->id,
        'section_id' => $section2->id,
        'nom' => 'Premier Cycle',
        'code' => 'CYC1-M2',
        'ordre' => 1,
    ]);

    // 1. Création réussie du niveau Sixième sans spécifier de code (auto-génération en 6EME)
    $res1 = $this->post(route('administration.niveaux.store'), [
        'cycle_id' => $cycle1->id,
        'nom' => 'Sixième',
        'ordre' => 1,
    ]);
    $res1->assertSessionHasNoErrors();
    $res1->assertRedirect();

    $niv1 = Niveau::where('cycle_id', $cycle1->id)->where('nom', 'Sixième')->first();
    expect($niv1)->not->toBeNull();
    expect($niv1->etablissement_id)->toBe($etab1->id);
    expect($niv1->code)->toBe('6EME');

    // 2. Tentative de créer un doublon avec exactement le même nom dans le même cycle -> Erreur
    $resDuplicateSameCycle = $this->post(route('administration.niveaux.store'), [
        'cycle_id' => $cycle1->id,
        'nom' => 'Sixième',
        'ordre' => 2,
    ]);
    $resDuplicateSameCycle->assertSessionHasErrors('nom');

    // 3. Tentative de créer un niveau du même nom dans un autre cycle du même établissement -> Erreur
    $resDuplicateSameEtab = $this->post(route('administration.niveaux.store'), [
        'cycle_id' => $cycle2->id,
        'nom' => 'Sixième',
        'ordre' => 1,
    ]);
    $resDuplicateSameEtab->assertSessionHasErrors('nom');

    // 4. Tentative de doublon insensible à la casse ("sixième") -> Erreur
    $resDuplicateCase = $this->post(route('administration.niveaux.store'), [
        'cycle_id' => $cycle1->id,
        'nom' => 'sixième',
        'ordre' => 2,
    ]);
    $resDuplicateCase->assertSessionHasErrors('nom');

    // 5. Même nom dans un établissement différent (etab2) -> Autorisé
    $resEtab2 = $this->post(route('administration.niveaux.store'), [
        'cycle_id' => $cycleEtab2->id,
        'nom' => 'Sixième',
        'ordre' => 1,
    ]);
    $resEtab2->assertSessionHasNoErrors();
    $resEtab2->assertRedirect();

    $nivEtab2 = Niveau::where('cycle_id', $cycleEtab2->id)->where('nom', 'Sixième')->first();
    expect($nivEtab2)->not->toBeNull();
    expect($nivEtab2->etablissement_id)->toBe($etab2->id);
});

/**
 * Vérifie qu'une classe ne peut pas être supprimée si elle contient des données pédagogiques actives
 * (attributions de matières/enseignants ou inscriptions d'élèves), préservant ainsi l'intégrité du cursus,
 * mais qu'une classe vierge peut être supprimée sans encombre.
 */
test('classe cannot be deleted if it has attributions or inscriptions but can be deleted if empty', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $etab = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Lycée Pilote Delta',
        'code' => 'LPDELTA',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    $section = Section::create([
        'etablissement_id' => $etab->id,
        'nom' => 'Section Francophone',
        'code' => 'FR-DELTA',
        'langue' => 'fr',
    ]);

    $cycle = Cycle::create([
        'section_id' => $section->id,
        'etablissement_id' => $etab->id,
        'nom' => 'Premier Cycle',
        'code' => 'CYC1-D',
        'ordre' => 1,
    ]);

    $niveau = Niveau::create([
        'cycle_id' => $cycle->id,
        'etablissement_id' => $etab->id,
        'nom' => 'Troisième',
        'code' => '3EME',
        'ordre' => 1,
    ]);

    $classe = Classe::create([
        'niveau_id' => $niveau->id,
        'nom' => '3ème A',
        'code' => '3A',
        'capacite_max' => 40,
    ]);

    $annee = AnneeScolaire::firstOrCreate(
        ['code' => '2026-2027'],
        ['nom' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'statut' => 'en_cours', 'is_current' => true]
    );

    $matiere = Matiere::firstOrCreate(
        ['code' => 'MATH-CL'],
        ['nom' => 'Mathématiques', 'is_active' => true]
    );

    $prof = Personnel::firstOrCreate(
        ['matricule' => 'ENS-CL-01'],
        [
            'etablissement_id' => $etab->id,
            'nom' => 'Fomekong',
            'prenom' => 'Alain',
            'sexe' => 'M',
            'telephone' => '677000001',
            'fonction_principale' => 'Enseignant',
            'statut' => 'Actif',
            'is_enseignant' => true,
        ]
    );

    // 1. Ajouter une attribution de cours (Matière + Enseignant)
    $attribution = Attribution::create([
        'annee_scolaire_id' => $annee->id,
        'classe_id' => $classe->id,
        'matiere_id' => $matiere->id,
        'personnel_id' => $prof->id,
        'heures_hebdo' => 4,
    ]);

    // Tentative de suppression de la classe -> Doit échouer avec message d'erreur et redirection
    $deleteWithAttribution = $this->delete(route('administration.classes.destroy', $classe));
    $deleteWithAttribution->assertSessionHas('error');
    $this->assertDatabaseHas('classes', ['id' => $classe->id]);
    $this->assertDatabaseHas('attributions', ['id' => $attribution->id]);

    // 2. Retirer l'attribution et ajouter une inscription d'élève
    $attribution->delete();

    $eleve = Eleve::firstOrCreate(
        ['matricule' => 'MAT-CL-001'],
        ['nom' => 'Kamga', 'prenom' => 'Paul', 'sexe' => 'M', 'date_naissance' => '2012-05-15', 'statut' => 'actif']
    );

    $inscription = Inscription::create([
        'annee_scolaire_id' => $annee->id,
        'classe_id' => $classe->id,
        'eleve_id' => $eleve->id,
        'statut' => 'validee',
        'type_inscription' => 'nouveau',
        'date_inscription' => now(),
    ]);

    // Tentative de suppression de la classe avec élève -> Doit échouer avec message d'erreur
    $deleteWithStudent = $this->delete(route('administration.classes.destroy', $classe));
    $deleteWithStudent->assertSessionHas('error');
    $this->assertDatabaseHas('classes', ['id' => $classe->id]);
    $this->assertDatabaseHas('inscriptions', ['id' => $inscription->id]);

    // 3. Retirer l'inscription (la classe redevient complètement vierge)
    $inscription->delete();

    // Tentative de suppression de la classe vierge -> Doit réussir
    $deleteEmpty = $this->delete(route('administration.classes.destroy', $classe));
    $deleteEmpty->assertSessionHas('success');
    $this->assertDatabaseMissing('classes', ['id' => $classe->id]);
});

test('user can create, update, and delete custom series for an establishment', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $etab = Etablissement::create([
        'institution_id' => $this->institution->id,
        'nom' => 'Lycée Classique de Test',
        'code' => 'LYC-SERIES',
        'statut_juridique' => 'Public',
        'ordre_enseignement' => 'Secondaire_General',
    ]);

    // 1. Vérification du pré-remplissage des séries par défaut (GENERAL, C, D, E, TI, ALLEMAND, ESPAGNOL, A4 ALL, A4 ESP)
    \App\Models\Serie::seedDefaultsForEtablissement($etab->id);
    $this->assertDatabaseHas('series', ['etablissement_id' => $etab->id, 'nom' => 'GENERAL']);
    $this->assertDatabaseHas('series', ['etablissement_id' => $etab->id, 'nom' => 'A4 ALL']);
    $this->assertDatabaseHas('series', ['etablissement_id' => $etab->id, 'nom' => 'C']);

    // 2. Création d'une série personnalisée supplémentaire (ex: SES)
    $createResponse = $this->post(route('administration.series.store'), [
        'etablissement_id' => $etab->id,
        'nom' => 'SES',
    ]);
    $createResponse->assertSessionHas('success');
    $this->assertDatabaseHas('series', [
        'etablissement_id' => $etab->id,
        'nom' => 'SES',
    ]);

    $serie = \App\Models\Serie::where('etablissement_id', $etab->id)->where('nom', 'SES')->firstOrFail();

    // 3. Vérification de l'unicité du nom au sein du même établissement
    $duplicateResponse = $this->post(route('administration.series.store'), [
        'etablissement_id' => $etab->id,
        'nom' => 'ses',
    ]);
    $duplicateResponse->assertSessionHasErrors(['nom']);

    // 4. Mise à jour de la série
    $updateResponse = $this->put(route('administration.series.update', $serie), [
        'nom' => 'SES ECO',
    ]);
    $updateResponse->assertSessionHas('success');
    $this->assertDatabaseHas('series', [
        'id' => $serie->id,
        'nom' => 'SES ECO',
    ]);

    // 5. Création d'une classe avec série par défaut (si vide -> GENERAL) et avec série personnalisée
    $section = Section::create([
        'etablissement_id' => $etab->id,
        'nom' => 'Section Francophone',
        'code' => 'FR-SERIES',
        'langue' => 'fr',
    ]);
    $cycle = Cycle::create([
        'section_id' => $section->id,
        'nom' => 'Second Cycle',
        'code' => 'CYC2-SERIES',
        'ordre' => 2,
    ]);
    $niveau = Niveau::create([
        'cycle_id' => $cycle->id,
        'nom' => 'Terminale',
        'code' => 'TLE-SERIES',
        'ordre' => 7,
    ]);

    // Classe sans série renseignée -> doit prendre GENERAL par défaut
    $classeDefaultResponse = $this->post(route('administration.classes.store'), [
        'niveau_id' => $niveau->id,
        'code' => 'TLE-GEN',
        'nom' => 'Terminale Générale',
        'capacite_max' => 50,
    ]);
    $classeDefaultResponse->assertSessionHas('success');
    $this->assertDatabaseHas('classes', [
        'code' => 'TLE-GEN',
        'serie' => 'GENERAL',
    ]);

    // Classe avec série personnalisée (SES ECO)
    $classeCustomResponse = $this->post(route('administration.classes.store'), [
        'niveau_id' => $niveau->id,
        'code' => 'TLE-SES',
        'nom' => 'Terminale SES',
        'serie' => 'SES ECO',
        'capacite_max' => 45,
    ]);
    $classeCustomResponse->assertSessionHas('success');
    $this->assertDatabaseHas('classes', [
        'code' => 'TLE-SES',
        'serie' => 'SES ECO',
    ]);

    $classeCustom = Classe::where('code', 'TLE-SES')->firstOrFail();

    // 6. Tentative de suppression de la série alors qu'elle est utilisée -> Doit échouer
    $deleteSerieBlocked = $this->delete(route('administration.series.destroy', $serie));
    $deleteSerieBlocked->assertSessionHas('error');
    $this->assertDatabaseHas('series', ['id' => $serie->id]);

    // 7. Supprimer la classe puis supprimer la série -> Doit réussir
    $this->delete(route('administration.classes.destroy', $classeCustom))->assertSessionHas('success');
    $deleteSerieSuccess = $this->delete(route('administration.series.destroy', $serie));
    $deleteSerieSuccess->assertSessionHas('success');
    $this->assertDatabaseMissing('series', ['id' => $serie->id]);
});





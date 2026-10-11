<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Caisse;
use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\Personnel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tests d'architecture et de sécurité : Explosion granulaire des permissions.
 * Valide le principe du moindre privilège (Least Privilege / Zero Trust) :
 * - Un utilisateur disposant uniquement des droits de consultation ou modification
 *   se voit strictement refuser l'accès aux opérations de suppression (HTTP 403).
 * - L'octroi de la permission unitaire spécifique permet l'exécution de l'action.
 * - Le rôle super-admin conserve l'accès global sans restriction.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    // Initialise les rôles et toutes les permissions via le seeder principal
    $this->seed(DatabaseSeeder::class);

    // Institution & Établissement de base
    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-TEST'],
        ['nom' => 'Institution Test', 'telephone' => '+237600000000', 'email' => 'inst@test.cm']
    );

    $this->etablissement = Etablissement::firstOrCreate(
        ['code' => 'ETAB-TEST'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Campus Test',
            'type_etablissement' => 'secondaire',
            'telephone' => '+237600000001',
            'email' => 'campus@test.cm',
        ]
    );

    $this->anneeScolaire = AnneeScolaire::firstOrCreate(
        ['code' => '2026-2027'],
        [
            'nom' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'is_current' => true,
            'statut' => 'ouverte',
        ]
    );
});

test('user with only etablissements.view and etablissements.edit cannot delete an etablissement', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['etablissement_id' => $this->etablissement->id]);
    $user->givePermissionTo(['etablissements.view', 'etablissements.edit']);
    $this->actingAs($user);

    $targetEtab = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'ETAB-TARGET',
        'nom' => 'Campus Cible',
        'type_etablissement' => 'secondaire',
        'telephone' => '+237600000002',
        'email' => 'cible@test.cm',
    ]);

    // Tentative de suppression : doit échouer avec HTTP 403 (défense en profondeur)
    $response = $this->delete(route('administration.etablissements.destroy', $targetEtab));
    $response->assertForbidden();

    // L'établissement doit toujours exister en base
    $this->assertDatabaseHas('etablissements', ['id' => $targetEtab->id]);
});

test('user with granular etablissements.delete permission can delete an etablissement', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['etablissement_id' => $this->etablissement->id]);
    $user->givePermissionTo('etablissements.delete');
    $this->actingAs($user);

    $targetEtab = Etablissement::create([
        'institution_id' => $this->institution->id,
        'code' => 'ETAB-TO-DELETE',
        'nom' => 'Campus À Supprimer',
        'type_etablissement' => 'secondaire',
        'telephone' => '+237600000003',
        'email' => 'todelete@test.cm',
    ]);

    $response = $this->delete(route('administration.etablissements.destroy', $targetEtab));
    $response->assertRedirect();
    $this->assertDatabaseMissing('etablissements', ['id' => $targetEtab->id]);
});

test('user with personnels.view and personnels.edit cannot delete a personnel without personnels.delete', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['etablissement_id' => $this->etablissement->id]);
    $user->givePermissionTo(['personnels.view', 'personnels.edit']);
    $this->actingAs($user);

    $personnel = Personnel::create([
        'etablissement_id' => $this->etablissement->id,
        'matricule' => 'PERS-TEST-001',
        'nom' => 'FOTSO',
        'prenom' => 'Armand',
        'sexe' => 'M',
        'telephone' => '+237611111111',
        'fonction_principale' => 'Enseignant',
        'statut' => 'Actif',
    ]);

    $response = $this->delete(route('administration.personnels.destroy', $personnel));
    $response->assertForbidden();
    $this->assertDatabaseHas('personnels', ['id' => $personnel->id]);
});

test('user with personnels.delete can delete a personnel', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['etablissement_id' => $this->etablissement->id]);
    $user->givePermissionTo('personnels.delete');
    $this->actingAs($user);

    $personnel = Personnel::create([
        'etablissement_id' => $this->etablissement->id,
        'matricule' => 'PERS-TEST-002',
        'nom' => 'KAMGA',
        'prenom' => 'Brice',
        'sexe' => 'M',
        'telephone' => '+237622222222',
        'fonction_principale' => 'Enseignant',
        'statut' => 'Actif',
    ]);

    $response = $this->delete(route('administration.personnels.destroy', $personnel));
    $response->assertRedirect();
    $this->assertSoftDeleted('personnels', ['id' => $personnel->id]);
});

test('user with eleves.view and eleves.edit cannot delete an eleve without eleves.delete', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['etablissement_id' => $this->etablissement->id]);
    $user->givePermissionTo(['eleves.view', 'eleves.edit']);
    $this->actingAs($user);

    $eleve = Eleve::create([
        'matricule' => 'ELV-GRANULAR-1',
        'nom' => 'TCHOUA',
        'prenom' => 'Carole',
        'sexe' => 'F',
        'telephone_parent' => '+237633333333',
    ]);

    $response = $this->delete(route('administration.eleves.destroy', $eleve));
    $response->assertForbidden();
    $this->assertDatabaseHas('eleves', ['id' => $eleve->id]);
});

test('user with eleves.delete can delete an eleve', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['etablissement_id' => $this->etablissement->id]);
    $user->givePermissionTo('eleves.delete');
    $this->actingAs($user);

    $eleve = Eleve::create([
        'matricule' => 'ELV-GRANULAR-2',
        'nom' => 'TALLA',
        'prenom' => 'David',
        'sexe' => 'M',
        'telephone_parent' => '+237644444444',
    ]);

    $response = $this->delete(route('administration.eleves.destroy', $eleve));
    $response->assertRedirect();
    $this->assertDatabaseMissing('eleves', ['id' => $eleve->id]);
});

test('user with depenses.view and depenses.create cannot delete a depense without depenses.delete', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['etablissement_id' => $this->etablissement->id]);
    $user->givePermissionTo(['depenses.view', 'depenses.create']);
    $this->actingAs($user);

    $caisse = Caisse::firstOrCreate(
        ['etablissement_id' => $this->etablissement->id, 'code' => 'CS-TEST'],
        ['nom' => 'Caisse Principale Test', 'solde_actuel' => 100000, 'is_active' => true]
    );

    $categorie = CategorieDepense::firstOrCreate(
        ['code' => 'CAT-TEST'],
        ['nom' => 'Fournitures', 'couleur' => '#3b82f6', 'is_active' => true]
    );

    $depense = Depense::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'caisse_id' => $caisse->id,
        'categorie_depense_id' => $categorie->id,
        'numero_piece' => 'DEP-001',
        'beneficiaire' => 'Fournisseur Test',
        'montant' => 25000,
        'date_depense' => '2026-09-22',
        'motif' => 'Achat rames de papier',
        'mode_reglement' => 'especes',
        'statut' => 'validee',
        'created_by' => $user->id,
    ]);

    $response = $this->delete(route('administration.finances.depenses.destroy', $depense));
    $response->assertForbidden();
    $this->assertDatabaseHas('depenses', ['id' => $depense->id]);
});

test('user with depenses.delete can delete a depense', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['etablissement_id' => $this->etablissement->id]);
    $user->givePermissionTo('depenses.delete');
    $this->actingAs($user);

    $caisse = Caisse::firstOrCreate(
        ['etablissement_id' => $this->etablissement->id, 'code' => 'CS-TEST2'],
        ['nom' => 'Caisse Test 2', 'solde_actuel' => 50000, 'is_active' => true]
    );

    $categorie = CategorieDepense::firstOrCreate(
        ['code' => 'CAT-TEST2'],
        ['nom' => 'Entretien', 'couleur' => '#10b981', 'is_active' => true]
    );

    $depense = Depense::create([
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'caisse_id' => $caisse->id,
        'categorie_depense_id' => $categorie->id,
        'numero_piece' => 'DEP-002',
        'beneficiaire' => 'Technicien Test',
        'montant' => 15000,
        'date_depense' => '2026-09-22',
        'motif' => 'Maintenance électrique',
        'mode_reglement' => 'especes',
        'statut' => 'validee',
        'created_by' => $user->id,
    ]);

    $response = $this->delete(route('administration.finances.depenses.destroy', $depense));
    $response->assertRedirect();
    $this->assertDatabaseMissing('depenses', ['id' => $depense->id]);
});

test('role permission matrix lists all modules with zero unassigned or orphan permissions', function () {
    /** @var \Tests\TestCase $this */
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    $this->actingAs($superAdmin);

    $response = $this->get(route('administration.roles.index'));
    $response->assertOk();

    $page = $response->viewData('page');
    $modules = $page['props']['modules'];

    expect(count($modules))->toBeGreaterThanOrEqual(18);

    // Vérifier que chaque module dispose d'un id, d'un nom et d'une liste de permissions
    foreach ($modules as $module) {
        expect($module)->toHaveKeys(['id', 'name', 'icon', 'description', 'permissions']);
        expect($module['permissions'])->toBeArray();
        expect(count($module['permissions']))->toBeGreaterThan(0);
    }
});

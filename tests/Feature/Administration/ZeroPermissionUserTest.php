<?php

/** @var \Tests\TestCase $this */

use App\Models\AnneeScolaire;
use App\Models\Etablissement;
use App\Models\Institution;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Role;

/**
 * Tests d'architecture de sécurité : Isolation Zero-Trust pour utilisateur sans droit.
 *
 * Vérifie le comportement du système lorsqu'un utilisateur se connecte :
 * 1. Sans aucun rôle assigné (roles = []).
 * 2. Avec un rôle assigné qui ne dispose d'absolument aucune permission (permissions = []).
 *
 * Contrôle l'étanchéité totale :
 * - Aucune permission injectée dans les props partagées Inertia (auth.permissions = []).
 * - Absence totale de privilèges administratifs (auth.user.is_super_admin = false).
 * - Rejet strict par HTTP 403 Forbidden sur l'ensemble des modules d'administration.
 * - Rejet strict par HTTP 403 Forbidden sur toutes les tentatives d'écriture (POST, PUT, DELETE).
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    // Initialisation des permissions et rôles de base via le Seeder principal
    $this->seed(DatabaseSeeder::class);

    // Création d'une structure institutionnelle minimale pour contextualiser les requêtes
    $this->institution = Institution::firstOrCreate(
        ['code' => 'INST-ZERO'],
        [
            'nom' => 'Institution Zero Trust',
            'telephone' => '+237600000000',
            'email' => 'zerotrust@syges.cm',
        ]
    );

    $this->etablissement = Etablissement::firstOrCreate(
        ['code' => 'CAMPUS-ZERO'],
        [
            'institution_id' => $this->institution->id,
            'nom' => 'Campus Unique Zero Trust',
            'type_etablissement' => 'secondaire',
            'telephone' => '+237600000001',
            'email' => 'campus-zero@syges.cm',
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

/**
 * Test 1 : Un utilisateur authentifié sans aucun rôle ne reçoit aucune permission dans Inertia.
 */
test('authenticated user without any role has empty permissions and is not super admin in inertia props', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'norole@test.cm',
        'etablissement_id' => $this->etablissement->id,
    ]);

    $this->actingAs($user);

    // Accès à la page d'administration globale
    $response = $this->get(route('administration'));
    $response->assertOk();

    // Vérification que les props d'authentification partagées n'accordent aucun privilège
    $response->assertInertia(fn ($page) => $page
        ->component('administration')
        ->where('auth.user.is_super_admin', false)
        ->where('auth.user.roles', [])
        ->where('auth.user.permissions', [])
    );
});

/**
 * Test 2 : Un utilisateur sans aucun rôle ne peut accéder à aucun des modules d'administration (HTTP 403).
 */
test('authenticated user without any role is forbidden from all administration modules', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'norole-blocked@test.cm',
        'etablissement_id' => $this->etablissement->id,
    ]);

    $this->actingAs($user);

    // Liste exhaustive de tous les endpoints de consultation des modules administratifs de SYGES
    $protectedGetRoutes = [
        'administration.institution.index',
        'administration.users.index',
        'administration.roles.index',
        'administration.periodes.index',
        'administration.matieres.index',
        'administration.eleves.index',
        'administration.personnels.index',
        'administration.attributions.index',
        'administration.notes.index',
        'administration.bulletins.index',
        'administration.finances.index',
        'administration.discipline.index',
        'administration.emplois-du-temps.index',
        'administration.communication.index',
        'administration.statistiques.index',
        'administration.deliberations.index',
        'administration.archivage.index',
        'administration.pedagogie.competences.index',
        'administration.pedagogie.dispenses.index',
        'administration.discipline.conseil.index',
        'administration.progression.index',
        'administration.eleves.cartes-scolaires',
    ];

    foreach ($protectedGetRoutes as $routeName) {
        $response = $this->get(route($routeName));
        $response->assertForbidden();
    }
});

/**
 * Test 3 : Un utilisateur sans rôle ne peut exécuter aucune opération d'écriture (POST/PUT/DELETE).
 */
test('authenticated user without any role cannot perform write operations in administration', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'norole-writer@test.cm',
        'etablissement_id' => $this->etablissement->id,
    ]);

    $this->actingAs($user);

    // Tentative de création d'un établissement
    $resEtab = $this->post(route('administration.etablissements.store'), [
        'nom' => 'Hacker Campus',
        'code' => 'HACK',
    ]);
    $resEtab->assertForbidden();

    // Tentative de création d'un utilisateur
    $resUser = $this->post(route('administration.users.store'), [
        'name' => 'Hacker User',
        'email' => 'hacker@test.cm',
    ]);
    $resUser->assertForbidden();

    // Tentative de création d'un rôle
    $resRole = $this->post(route('administration.roles.store'), [
        'name' => 'hacker-role',
        'permissions' => ['users.view'],
    ]);
    $resRole->assertForbidden();

    // Tentative de sauvegarde d'une note
    $resNote = $this->post(route('administration.notes.sauvegarder'), [
        'classe_id' => 1,
        'matiere_id' => 1,
        'evaluation_id' => 1,
        'notes' => [],
    ]);
    $resNote->assertForbidden();
});

/**
 * Test 4 : Un utilisateur possédant un Rôle mais à 0 permission n'a accès à rien.
 */
test('authenticated user with a role having zero permissions cannot see or execute anything', function () {
    /** @var \Tests\TestCase $this */
    // Création d'un rôle sans aucune permission rattachée
    $emptyRole = Role::firstOrCreate(['name' => 'role-vide-sans-droit', 'guard_name' => 'web']);
    $emptyRole->syncPermissions([]); // Garantit 0 permission

    $user = User::factory()->create([
        'email' => 'zero-permission@test.cm',
        'etablissement_id' => $this->etablissement->id,
    ]);

    $user->assignRole($emptyRole);

    $this->actingAs($user);

    // 1. Vérification des permissions effectives de l'utilisateur
    expect($user->getAllPermissions())->toBeEmpty();
    expect($user->hasRole('role-vide-sans-droit'))->toBeTrue();

    // 2. Vérification des données transmises à l'interface Inertia
    $response = $this->get(route('administration'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration')
        ->where('auth.user.is_super_admin', false)
        ->where('auth.user.roles', ['role-vide-sans-droit'])
        ->where('auth.user.permissions', []) // Liste strictement vide de permissions
    );

    // 3. Vérification du rejet 403 Forbidden sur les modules clés
    $this->get(route('administration.institution.index'))->assertForbidden();
    $this->get(route('administration.users.index'))->assertForbidden();
    $this->get(route('administration.roles.index'))->assertForbidden();
    $this->get(route('administration.matieres.index'))->assertForbidden();
    $this->get(route('administration.notes.index'))->assertForbidden();
    $this->get(route('administration.bulletins.index'))->assertForbidden();
    $this->get(route('administration.finances.index'))->assertForbidden();
    $this->get(route('administration.statistiques.index'))->assertForbidden();

    // 4. Vérification que le tableau de bord masque complètement les données stratégiques
    $dashResponse = $this->get(route('dashboard'));
    $dashResponse->assertOk();
    $dashResponse->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('hasAccess', false)
        ->where('demographie.effectif_total', 0)
        ->where('pedagogie.taux_reussite_global', 0)
        ->where('finances.total_facture', 0)
    );
});

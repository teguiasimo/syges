<?php

/** @var \Tests\TestCase $this */

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tests de fonctionnalités pour la gestion des Utilisateurs dans SYGES.
 *
 * Vérifie :
 * - L'accès sécurisé à l'interface d'administration des utilisateurs.
 * - La création d'un utilisateur avec login unique, email, mot de passe et rôles.
 * - Les validations de formulaire (unicité du login, mot de passe confirmé).
 * - La mise à jour des informations et l'assignation de rôles.
 * - Le basculement de statut (Actif / Bloqué).
 * - Les protections de sécurité (impossible de bloquer ou supprimer le Super Admin ou son propre compte).
 * - La connexion via email ou login, et le blocage effectif des comptes suspendus.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    foreach (['users.view', 'users.create', 'users.edit', 'users.block'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');
});

test('guests are redirected to login when visiting users page', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('administration.users.index'));
    $response->assertRedirect(route('login'));
});

test('unauthorized users without users.view permission cannot access users page', function () {
    /** @var \Tests\TestCase $this */
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $response = $this->get(route('administration.users.index'));
    $response->assertForbidden();
});

test('unauthorized users without users.create permission cannot create users', function () {
    /** @var \Tests\TestCase $this */
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $response = $this->post(route('administration.users.store'), [
        'name' => 'Utilisateur Non Autorisé',
        'login' => 'non.autorise',
        'email' => 'non.autorise@syges.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);
    $response->assertForbidden();
});

test('unauthorized users without users.edit permission cannot update users', function () {
    /** @var \Tests\TestCase $this */
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $targetUser = User::factory()->create();

    $response = $this->put(route('administration.users.update', $targetUser), [
        'name' => 'Modification Interdite',
        'login' => 'target.user',
        'email' => 'target@syges.com',
    ]);
    $response->assertForbidden();
});

test('unauthorized users without users.block permission cannot toggle user status', function () {
    /** @var \Tests\TestCase $this */
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $targetUser = User::factory()->create(['is_active' => true]);

    $response = $this->patch(route('administration.users.toggle-status', $targetUser));
    $response->assertForbidden();
});

test('authenticated admin can access the users management page with tanstack data', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->get(route('administration.users.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/users/index')
        ->has('users')
        ->has('availableRoles')
        ->has('can')
        ->where('can.create', true)
        ->where('can.edit', true)
        ->where('can.block', true)
        ->where('availableRoles', fn ($roles) => ! collect($roles)->pluck('name')->contains('super-admin'))
    );
});

test('cannot assign super-admin role when creating a new user', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->post(route('administration.users.store'), [
        'name' => 'Faux Super Admin',
        'login' => 'fake.admin',
        'email' => 'fake@syges.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'roles' => ['super-admin'],
    ]);

    $response->assertSessionHasErrors(['roles.0']);
});

test('can create a new user with login, email, password and roles', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $role = Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);

    $response = $this->post(route('administration.users.store'), [
        'name' => 'Professeur Kouamé',
        'login' => 'kouame.prof',
        'email' => 'kouame@syges.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'roles' => ['enseignant'],
        'is_active' => true,
    ]);

    $response->assertRedirect(route('administration.users.index'));
    $this->assertDatabaseHas('users', [
        'login' => 'kouame.prof',
        'email' => 'kouame@syges.com',
        'is_active' => true,
    ]);

    $createdUser = User::where('login', 'kouame.prof')->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->hasRole('enseignant'))->toBeTrue();
    expect(Hash::check('secret123', $createdUser->password))->toBeTrue();
});

test('cannot create a user with a duplicate login or email', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    User::create([
        'name' => 'Utilisateur Existant',
        'login' => 'dup.login',
        'email' => 'dup@syges.com',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);

    // Tentative avec le même login
    $responseLogin = $this->post(route('administration.users.store'), [
        'name' => 'Autre Nom',
        'login' => 'dup.login',
        'email' => 'other@syges.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);
    $responseLogin->assertSessionHasErrors(['login']);

    // Tentative avec le même email
    $responseEmail = $this->post(route('administration.users.store'), [
        'name' => 'Autre Nom',
        'login' => 'other.login',
        'email' => 'dup@syges.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);
    $responseEmail->assertSessionHasErrors(['email']);
});

test('can update an existing user information and roles', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $role1 = Role::firstOrCreate(['name' => 'directeur-etudes', 'guard_name' => 'web']);
    $role2 = Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Directeur Initial',
        'login' => 'directeur.init',
        'email' => 'directeur@syges.com',
        'password' => Hash::make('oldpassword'),
        'is_active' => true,
    ]);
    $user->assignRole('directeur-etudes');

    // Mise à jour avec nouveau nom et nouveau rôle sans changer le mot de passe
    $response = $this->put(route('administration.users.update', $user), [
        'name' => 'Directeur Modifié',
        'login' => 'directeur.mod',
        'email' => 'directeur.mod@syges.com',
        'password' => '',
        'password_confirmation' => '',
        'roles' => ['enseignant'],
    ]);

    $response->assertRedirect(route('administration.users.index'));

    $user->refresh();
    expect($user->name)->toBe('Directeur Modifié');
    expect($user->login)->toBe('directeur.mod');
    expect($user->hasRole('enseignant'))->toBeTrue();
    expect($user->hasRole('directeur-etudes'))->toBeFalse();
    // Le mot de passe n'a pas été écrasé
    expect(Hash::check('oldpassword', $user->password))->toBeTrue();
    // Le statut d'activation reste intact
    expect($user->is_active)->toBeTrue();
});

test('updating user profile does not alter active or blocked status', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $blockedUser = User::create([
        'name' => 'Utilisateur Bloqué',
        'login' => 'bloque.edit',
        'email' => 'bloque.edit@syges.com',
        'password' => Hash::make('password123'),
        'is_active' => false,
    ]);

    $response = $this->put(route('administration.users.update', $blockedUser), [
        'name' => 'Utilisateur Bloqué Modifié',
        'login' => 'bloque.edit',
        'email' => 'bloque.edit@syges.com',
        'password' => '',
        'password_confirmation' => '',
        'roles' => [],
    ]);

    $response->assertRedirect(route('administration.users.index'));
    expect($blockedUser->fresh()->is_active)->toBeFalse();
    expect($blockedUser->fresh()->name)->toBe('Utilisateur Bloqué Modifié');
});

test('can toggle user status between active and blocked', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $user = User::create([
        'name' => 'Employé Temporaire',
        'login' => 'emp.temp',
        'email' => 'emp.temp@syges.com',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);

    // Blocage
    $response1 = $this->patch(route('administration.users.toggle-status', $user));
    $response1->assertRedirect(route('administration.users.index'));
    expect($user->fresh()->is_active)->toBeFalse();

    // Déblocage
    $response2 = $this->patch(route('administration.users.toggle-status', $user));
    $response2->assertRedirect(route('administration.users.index'));
    expect($user->fresh()->is_active)->toBeTrue();
});

test('cannot block the primary super admin', function () {
    /** @var \Tests\TestCase $this */
    $superAdmin = User::firstOrCreate(
        ['email' => 'admin@gmail.com'],
        [
            'name' => 'Super Administrateur',
            'login' => 'admin',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]
    );

    $otherAdmin = User::create([
        'name' => 'Autre Admin',
        'login' => 'autre.admin',
        'email' => 'autre.admin@syges.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $otherAdmin->givePermissionTo('users.block');

    $this->actingAs($otherAdmin);

    // Tentative de blocage
    $responseToggle = $this->patch(route('administration.users.toggle-status', $superAdmin));
    $responseToggle->assertSessionHas('error');
    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

test('cannot block oneself', function () {
    /** @var \Tests\TestCase $this */
    $user = User::create([
        'name' => 'Moi-même',
        'login' => 'self.test',
        'email' => 'self@syges.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $user->givePermissionTo('users.block');

    $this->actingAs($user);

    // Tentative d'auto-blocage
    $responseToggle = $this->patch(route('administration.users.toggle-status', $user));
    $responseToggle->assertSessionHas('error');
    expect($user->fresh()->is_active)->toBeTrue();
});

test('user deletion is disabled to preserve audit and operation traces', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $user = User::create([
        'name' => 'Compte Permanent',
        'login' => 'permanent.user',
        'email' => 'permanent@syges.com',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);

    // La route de suppression n'existe pas pour garantir l'intégrité des données
    $response = $this->delete("/administration/users/{$user->id}");
    $response->assertStatus(405);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('active users can authenticate using either login or email', function () {
    /** @var \Tests\TestCase $this */
    $user = User::create([
        'name' => 'Dual Login Test',
        'login' => 'monlogin',
        'email' => 'monlogin@syges.com',
        'password' => Hash::make('mypassword123'),
        'is_active' => true,
    ]);

    // Connexion via email
    $responseEmail = $this->post(route('login.store'), [
        'email' => 'monlogin@syges.com',
        'password' => 'mypassword123',
    ]);
    $responseEmail->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    Auth::logout();

    // Connexion via login
    $responseLogin = $this->post(route('login.store'), [
        'email' => 'monlogin',
        'password' => 'mypassword123',
    ]);
    $responseLogin->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('blocked users cannot authenticate even with correct credentials', function () {
    /** @var \Tests\TestCase $this */
    $user = User::create([
        'name' => 'Utilisateur Suspendu',
        'login' => 'bloque.user',
        'email' => 'bloque@syges.com',
        'password' => Hash::make('mypassword123'),
        'is_active' => false,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'bloque.user',
        'password' => 'mypassword123',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
});

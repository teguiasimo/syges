<?php

/** @var \Tests\TestCase $this */

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tests de fonctionnalités pour la gestion des Rôles et Permissions.
 * Couvre les contrôles d'habilitation (droits/permissions), la création, la modification,
 * la suppression sécurisée et les protections des rôles système.
 */

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('super-admin');
});

test('guests are redirected to login when visiting roles page', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('administration.roles.index'));
    $response->assertRedirect(route('login'));
});

test('unauthorized users without roles.view permission cannot access roles page', function () {
    /** @var \Tests\TestCase $this */
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $response = $this->get(route('administration.roles.index'));
    $response->assertForbidden();
});

test('authorized users can access the roles management page', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $response = $this->get(route('administration.roles.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/roles/index')
        ->has('roles')
        ->has('modules')
        ->has('totalPermissions')
        ->has('can')
        ->where('can.create', true)
        ->where('can.edit', true)
        ->where('can.delete', true)
    );
});

test('unauthorized users without roles.create permission cannot create roles', function () {
    /** @var \Tests\TestCase $this */
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $response = $this->post(route('administration.roles.store'), [
        'name' => 'Role Non Autorisé',
        'permissions' => [],
    ]);
    $response->assertForbidden();
});

test('can create a new custom role with selected permissions', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $permission = Permission::firstOrCreate(['name' => 'roles.view', 'guard_name' => 'web']);

    $response = $this->post(route('administration.roles.store'), [
        'name' => 'Surveillant Test',
        'permissions' => ['roles.view'],
    ]);

    $response->assertRedirect(route('administration.roles.index'));
    $this->assertDatabaseHas('roles', [
        'name' => 'surveillant-test',
    ]);

    $role = Role::findByName('surveillant-test', 'web');
    expect($role->hasPermissionTo('roles.view'))->toBeTrue();
});

test('cannot create a role with a duplicate name', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    Role::firstOrCreate(['name' => 'comptable', 'guard_name' => 'web']);

    $response = $this->post(route('administration.roles.store'), [
        'name' => 'comptable',
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('unauthorized users without roles.edit permission cannot update roles', function () {
    /** @var \Tests\TestCase $this */
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $role = Role::firstOrCreate(['name' => 'test-role', 'guard_name' => 'web']);

    $response = $this->put(route('administration.roles.update', $role), [
        'name' => 'Nom Modifié',
        'permissions' => [],
    ]);
    $response->assertForbidden();
});

test('can update an existing custom role and its permissions', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $role = Role::firstOrCreate(['name' => 'bibliothecaire', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users.create', 'guard_name' => 'web']);

    $response = $this->put(route('administration.roles.update', $role), [
        'name' => 'Responsable Utilisateurs',
        'permissions' => ['users.create'],
    ]);

    $response->assertRedirect(route('administration.roles.index'));
    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'responsable-utilisateurs',
    ]);

    $role->refresh();
    expect($role->hasPermissionTo('users.create'))->toBeTrue();
});

test('cannot rename the super-admin role', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $response = $this->put(route('administration.roles.update', $superAdminRole), [
        'name' => 'Nouveau Nom',
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors(['name']);
    expect($superAdminRole->refresh()->name)->toBe('super-admin');
});

test('unauthorized users without roles.delete permission cannot delete roles', function () {
    /** @var \Tests\TestCase $this */
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $role = Role::firstOrCreate(['name' => 'a-supprimer', 'guard_name' => 'web']);

    $response = $this->delete(route('administration.roles.destroy', $role));
    $response->assertForbidden();
});

test('cannot delete protected system roles', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $response1 = $this->delete(route('administration.roles.destroy', $superAdminRole));
    $response1->assertSessionHas('error');
    $this->assertDatabaseHas('roles', ['name' => 'super-admin']);

    $response2 = $this->delete(route('administration.roles.destroy', $adminRole));
    $response2->assertSessionHas('error');
    $this->assertDatabaseHas('roles', ['name' => 'admin']);
});

test('cannot delete a role assigned to users', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $role = Role::firstOrCreate(['name' => 'censeur', 'guard_name' => 'web']);
    $assignedUser = User::factory()->create();
    $assignedUser->assignRole($role);

    $response = $this->delete(route('administration.roles.destroy', $role));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('roles', ['name' => 'censeur']);
});

test('can delete an unassigned custom role', function () {
    /** @var \Tests\TestCase $this */
    $this->actingAs($this->adminUser);

    $role = Role::firstOrCreate(['name' => 'temporaire', 'guard_name' => 'web']);

    $response = $this->delete(route('administration.roles.destroy', $role));
    $response->assertRedirect(route('administration.roles.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('roles', ['name' => 'temporaire']);
});

<?php

/** @var \Tests\TestCase $this */

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tests de fonctionnalités pour la gestion des Rôles et Permissions.
 */

test('guests are redirected to login when visiting roles page', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('administration.roles.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can access the roles management page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('administration.roles.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('administration/roles/index')
        ->has('roles')
        ->has('modules')
        ->has('totalPermissions')
    );
});

test('can create a new custom role with selected permissions', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $permission = Permission::firstOrCreate(['name' => 'grades.view', 'guard_name' => 'web']);

    $response = $this->post(route('administration.roles.store'), [
        'name' => 'Surveillant Test',
        'permissions' => ['grades.view'],
    ]);

    $response->assertRedirect(route('administration.roles.index'));
    $this->assertDatabaseHas('roles', [
        'name' => 'surveillant-test',
    ]);

    $role = Role::findByName('surveillant-test', 'web');
    expect($role->hasPermissionTo('grades.view'))->toBeTrue();
});

test('cannot create a role with a duplicate name', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    Role::firstOrCreate(['name' => 'comptable', 'guard_name' => 'web']);

    $response = $this->post(route('administration.roles.store'), [
        'name' => 'comptable',
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('can update an existing custom role and its permissions', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $role = Role::firstOrCreate(['name' => 'bibliothecaire', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'classes.view', 'guard_name' => 'web']);

    $response = $this->put(route('administration.roles.update', $role), [
        'name' => 'Responsable CDI',
        'permissions' => ['classes.view'],
    ]);

    $response->assertRedirect(route('administration.roles.index'));
    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'responsable-cdi',
    ]);

    $role->refresh();
    expect($role->hasPermissionTo('classes.view'))->toBeTrue();
});

test('cannot rename the super-admin role', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $response = $this->put(route('administration.roles.update', $superAdminRole), [
        'name' => 'Nouveau Nom',
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors(['name']);
    expect($superAdminRole->refresh()->name)->toBe('super-admin');
});

test('cannot delete protected system roles', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

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
    $user = User::factory()->create();
    $this->actingAs($user);

    $role = Role::firstOrCreate(['name' => 'censeur', 'guard_name' => 'web']);
    $assignedUser = User::factory()->create();
    $assignedUser->assignRole($role);

    $response = $this->delete(route('administration.roles.destroy', $role));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('roles', ['name' => 'censeur']);
});

test('can delete an unassigned custom role', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $role = Role::firstOrCreate(['name' => 'temporaire', 'guard_name' => 'web']);

    $response = $this->delete(route('administration.roles.destroy', $role));
    $response->assertRedirect(route('administration.roles.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('roles', ['name' => 'temporaire']);
});

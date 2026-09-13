<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder principal de l'application SYGES.
 * Initialise l'ensemble des permissions scolaires par catégorie,
 * configure les rôles par défaut et attribue les droits au compte Super Administrateur.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Exécute le peuplement de la base de données.
     * Enregistre les permissions, crée les rôles standard et le compte Super Admin.
     *
     * @return void
     */
    public function run(): void
    {
        // Réinitialiser le cache des permissions et des rôles Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Définition des permissions scolaires organisées par domaine
        $permissions = [
            // Module Utilisateurs & Profils
            'users.view' => 'web',
            'users.create' => 'web',
            'users.edit' => 'web',
            'users.delete' => 'web',
            'users.roles' => 'web',

            // Module Rôles & Permissions
            'roles.view' => 'web',
            'roles.create' => 'web',
            'roles.edit' => 'web',
            'roles.delete' => 'web',

            // Module Pédagogie & Classes
            'classes.view' => 'web',
            'classes.manage' => 'web',
            'subjects.manage' => 'web',

            // Module Évaluations & Notes
            'grades.view' => 'web',
            'grades.edit' => 'web',
            'report-cards.generate' => 'web',

            // Module Présences & Discipline
            'attendance.view' => 'web',
            'attendance.record' => 'web',

            // Module Configuration Système
            'settings.view' => 'web',
            'settings.edit' => 'web',
            'backups.manage' => 'web',
        ];

        // Création ou persistance des permissions
        foreach ($permissions as $name => $guardName) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guardName]);
        }

        // 2. Création des rôles standard pour SYGES
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $directorRole = Role::firstOrCreate(['name' => 'directeur-etudes', 'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);
        $secretaryRole = Role::firstOrCreate(['name' => 'secretaire', 'guard_name' => 'web']);

        // Attribution des permissions par rôle
        // Le super-admin reçoit la totalité des permissions
        $superAdminRole->syncPermissions(Permission::all());

        // L'admin gère tout sauf la gestion destructive du super-admin
        $adminRole->syncPermissions([
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.roles',
            'roles.view', 'roles.create', 'roles.edit',
            'classes.view', 'classes.manage', 'subjects.manage',
            'grades.view', 'grades.edit', 'report-cards.generate',
            'attendance.view', 'attendance.record',
            'settings.view', 'settings.edit',
        ]);

        // Le directeur des études pilote la pédagogie et les évaluations
        $directorRole->syncPermissions([
            'classes.view', 'classes.manage', 'subjects.manage',
            'grades.view', 'grades.edit', 'report-cards.generate',
            'attendance.view',
        ]);

        // L'enseignant saisit les notes et gère les présences
        $teacherRole->syncPermissions([
            'classes.view',
            'grades.view', 'grades.edit',
            'attendance.view', 'attendance.record',
        ]);

        // Le secrétariat gère les inscriptions d'élèves et les présences
        $secretaryRole->syncPermissions([
            'users.view', 'users.create', 'users.edit',
            'classes.view',
            'attendance.view', 'attendance.record',
            'report-cards.generate',
        ]);

        // 3. Création ou mise à jour du Super Administrateur principal
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin@'),
                'email_verified_at' => now(),
            ]
        );

        // Attribution du rôle super-admin
        if (! $superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole($superAdminRole);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder principal de l'application SYGES.
 * Initialise les rôles du système et crée le compte Super Administrateur par défaut.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Exécute les graines de la base de données.
     * Réinitialise le cache des permissions Spatie, crée le rôle 'super-admin'
     * et initialise le compte administrateur principal.
     *
     * @return void
     */
    public function run(): void
    {
        // Réinitialiser le cache des permissions et des rôles Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Création du rôle 'super-admin' avec le guard 'web'
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // Création ou mise à jour du Super Administrateur
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin@'),
                'email_verified_at' => now(),
            ]
        );

        // Attribution du rôle super-admin à l'utilisateur
        if (! $superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole($superAdminRole);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration de synchronisation idempotente des nouvelles permissions financières et du rôle `caissier`.
 *
 * Garantit que toute instance mise à jour depuis v1.1.x (qui exécute uniquement `migrate --force`
 * sans ré-exécuter `DatabaseSeeder`) dispose en base des permissions créées pour la refonte financière
 * ainsi que de leur attribution par défaut aux rôles existants, sans jamais écraser les délégations
 * personnalisées (telles que `system.update` accordée au rôle `admin`).
 */
return new class extends Migration
{
    /**
     * Crée les permissions et le rôle manquants puis met à jour les attributions minimales attendues.
     */
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Création idempotente des nouvelles permissions introduites lors de la refonte financière
        $newFinancialPermissions = [
            'finances.etats',
            'frais.configure',
            'versements.edit',
            'recettes_diverses.create',
            'bourses.attribuer',
            'dispenses_financieres.manage',
            'options_financieres.manage',
            'finances.verrou_solvabilite',
        ];

        foreach ($newFinancialPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 2. Création du nouveau rôle `caissier` avec ses permissions de guichet s'il n'existe pas encore
        $caissierAbsent = Role::where('name', 'caissier')->where('guard_name', 'web')->doesntExist();
        if ($caissierAbsent) {
            $caissierRole = Role::create(['name' => 'caissier', 'guard_name' => 'web']);
            $caissierWanted = [
                'institutions.view', 'etablissements.view',
                'sections.view', 'cycles.view', 'niveaux.view', 'classes.view',
                'eleves.view',
                'finances.view', 'finances.etats',
                'versements.create', 'recettes_diverses.create',
                'options_financieres.manage',
                'caisses.view',
            ];
            $existingForCaissier = Permission::where('guard_name', 'web')
                ->whereIn('name', $caissierWanted)
                ->pluck('name')
                ->all();

            if (! empty($existingForCaissier)) {
                $caissierRole->syncPermissions($existingForCaissier);
            }
        }

        // 3. Super-Admin : garantir la détention intégrale de toutes les permissions (dont les nouvelles)
        $superAdminRole = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions(Permission::where('guard_name', 'web')->get());
        }

        // 4. Intendant / Comptable : accorder toutes les nouvelles permissions financières
        $intendantRole = Role::where('name', 'intendant')->where('guard_name', 'web')->first();
        if ($intendantRole) {
            $intendantRole->givePermissionTo($newFinancialPermissions);
        }

        // 5. Administrateur local : ajouter uniquement les nouvelles permissions financières non restreintes
        //    (préserve intactes les délégations personnalisées existantes telles que `system.update`)
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminSafeNewFinances = [
                'finances.etats',
                'versements.edit',
                'recettes_diverses.create',
                'bourses.attribuer',
                'dispenses_financieres.manage',
                'options_financieres.manage',
                'finances.verrou_solvabilite',
            ];
            $adminRole->givePermissionTo($adminSafeNewFinances);
        }

        // 6. Directeur des Études & Secrétaire : accorder la consultation des états financiers (`finances.etats`)
        foreach (['directeur-etudes', 'secretaire'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo('finances.etats');
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Annulation volontairement sans suppression destructive des permissions en production.
     */
    public function down(): void
    {
        // Aucun retrait destructeur pour préserver les habilitations en place sur le serveur client.
    }
};

<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration d'ajout de l'indicateur explicite d'Accès Global Multi-Campus (`has_global_access`) sur les comptes utilisateurs.
 *
 * Permet à un compte non Super-Admin (notamment le rôle `admin`) de bénéficier d'une vue transversale
 * multi-campus explicite et sécurisée, tout en conservant le cloisonnement strict (`1 = 0`) pour tout compte
 * local mal configuré (`etablissement_id = null` et `has_global_access = false`).
 * Synchronise également les restrictions souhaitées sur le rôle `admin` en base existante.
 */
return new class extends Migration
{
    /**
     * Exécute la migration pour activer `has_global_access` et ajuster la matrice `admin`.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'has_global_access')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('has_global_access')
                    ->default(false)
                    ->after('etablissement_id')
                    ->comment('Indique si un utilisateur non Super-Admin dispose d un accès transversal multi-campus.');
            });
        }

        // Rétrocompatibilité sécurisée : activer explicitement `has_global_access` pour les secrétaires déjà en accès global
        // (etablissement_id nul et aucune fiche personnel rattachée à un campus précis).
        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $globalSecretaryIds = DB::table('users')
                ->join('model_has_roles', function ($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.model_type', '=', User::class);
                })
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->whereIn('roles.name', ['secretaire', 'secretaire-general'])
                ->whereNull('users.etablissement_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('personnels')
                        ->whereColumn('personnels.user_id', 'users.id')
                        ->whereNotNull('personnels.etablissement_id');
                })
                ->pluck('users.id')
                ->all();

            if (! empty($globalSecretaryIds)) {
                DB::table('users')
                    ->whereIn('id', $globalSecretaryIds)
                    ->update(['has_global_access' => true]);
            }

            // Mise à jour en base existante des restrictions du rôle `admin` (gestion utilisateurs, frais et annulations réservées au super-admin)
            $adminRole = Role::where('name', 'admin')->first();
            if ($adminRole) {
                $restrictedForAdmin = [
                    'users.view',
                    'users.create',
                    'users.edit',
                    'users.block',
                    'frais.configure',
                    'versements.cancel',
                    'finances.annuler',
                    'finances.manage',
                ];

                foreach ($restrictedForAdmin as $permName) {
                    // Vérifier l'existence effective de la permission en base (évite PermissionDoesNotExist lors d'une migration incrémentale depuis v1.1.4)
                    if (Permission::where('name', $permName)->where('guard_name', 'web')->exists()
                        && $adminRole->hasPermissionTo($permName)) {
                        $adminRole->revokePermissionTo($permName);
                    }
                }

                app()[PermissionRegistrar::class]->forgetCachedPermissions();
            }
        }
    }

    /**
     * Annule l'ajout de la colonne `has_global_access`.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'has_global_access')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('has_global_access');
            });
        }
    }
};

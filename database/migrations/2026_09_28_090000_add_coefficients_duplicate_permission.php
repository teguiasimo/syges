<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Exécute les migrations pour insérer la permission de duplication de grille de coefficients.
     *
     * @return void
     */
    public function up(): void
    {
        // 1. Création de la permission de duplication des coefficients
        $perm = Permission::firstOrCreate([
            'name' => 'coefficients.duplicate',
            'guard_name' => 'web',
        ]);

        // 2. Attribution automatique aux rôles administratifs et pédagogiques habilités
        $rolesToAssign = ['super-admin', 'admin', 'directeur-etudes', 'censeur'];
        foreach ($rolesToAssign as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && ! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }
    }

    /**
     * Annule les migrations en retirant la permission.
     *
     * @return void
     */
    public function down(): void
    {
        $perm = Permission::where('name', 'coefficients.duplicate')->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }
    }
};

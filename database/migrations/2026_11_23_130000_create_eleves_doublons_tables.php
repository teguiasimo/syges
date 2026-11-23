<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration ajoutant la gouvernance complète des doublons élèves dans SYGES :
 * - Table `eleves_fusions_audit` : journalisation traçable des fusions et recherche par ancien matricule.
 * - Table `eleves_doublons_ignores` : mémorisation des faux positifs (jumeaux, vrais homonymes).
 * - Création automatique de la permission dédiée `eleves.merge` attribuée à `super-admin` et `admin`.
 */
return new class extends Migration
{
    /**
     * Exécute la migration.
     */
    public function up(): void
    {
        Schema::create('eleves_fusions_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->nullOnDelete();
            $table->foreignId('master_eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('master_matricule', 50)->index();
            $table->unsignedBigInteger('absorbed_eleve_id');
            $table->string('absorbed_matricule', 50)->index();
            $table->string('absorbed_nom_complet', 255);
            $table->foreignId('merged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('montant_versements_transferes', 14, 2)->default(0);
            $table->unsignedInteger('nb_versements_transferes')->default(0);
            $table->unsignedInteger('nb_notes_transferees')->default(0);
            $table->unsignedInteger('nb_conflits_notes_resolus')->default(0);
            $table->json('resume_json')->nullable();
            $table->timestamps();
        });

        Schema::create('eleves_doublons_ignores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_min_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('eleve_max_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('motif', 150)->nullable();
            $table->foreignId('ignored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['eleve_min_id', 'eleve_max_id'], 'uniq_eleve_doublon_ignore');
        });

        // Création idempotente de la permission dédiée `eleves.merge` si les tables Spatie existent déjà
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $permission = Permission::firstOrCreate([
                'name' => 'eleves.merge',
                'guard_name' => 'web',
            ]);

            foreach (['super-admin', 'admin'] as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Annule la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('eleves_doublons_ignores');
        Schema::dropIfExists('eleves_fusions_audit');
    }
};

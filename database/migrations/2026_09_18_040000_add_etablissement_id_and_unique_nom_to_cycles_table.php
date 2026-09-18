<?php

use App\Models\Cycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant etablissement_id et la contrainte d'unicité sur le nom du cycle par établissement.
 */
return new class extends Migration
{
    /**
     * Applique la migration.
     */
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->foreignId('etablissement_id')
                ->nullable()
                ->after('section_id')
                ->constrained('etablissements')
                ->cascadeOnDelete();
        });

        // Remplir etablissement_id pour les cycles existants depuis leur section parente
        foreach (Cycle::with('section')->get() as $cycle) {
            if ($cycle->section) {
                $cycle->etablissement_id = $cycle->section->etablissement_id;
                $cycle->saveQuietly();
            }
        }

        // Appliquer la contrainte d'unicité (etablissement_id, nom)
        Schema::table('cycles', function (Blueprint $table) {
            $table->unique(['etablissement_id', 'nom'], 'cycles_etablissement_id_nom_unique');
        });
    }

    /**
     * Annule la migration.
     */
    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropUnique('cycles_etablissement_id_nom_unique');
            $table->dropForeign(['etablissement_id']);
            $table->dropColumn('etablissement_id');
        });
    }
};

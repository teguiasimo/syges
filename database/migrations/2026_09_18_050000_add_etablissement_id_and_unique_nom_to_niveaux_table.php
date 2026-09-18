<?php

use App\Models\Niveau;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant etablissement_id et la contrainte d'unicité sur le nom du niveau par établissement.
 */
return new class extends Migration
{
    /**
     * Applique la migration.
     */
    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->foreignId('etablissement_id')
                ->nullable()
                ->after('cycle_id')
                ->constrained('etablissements')
                ->cascadeOnDelete();
        });

        // Remplir etablissement_id pour les niveaux existants depuis leur cycle parent
        foreach (Niveau::with('cycle')->get() as $niveau) {
            if ($niveau->cycle) {
                $niveau->etablissement_id = $niveau->cycle->etablissement_id;
                $niveau->saveQuietly();
            }
        }

        // Appliquer la contrainte d'unicité (etablissement_id, nom)
        Schema::table('niveaux', function (Blueprint $table) {
            $table->unique(['etablissement_id', 'nom'], 'niveaux_etablissement_id_nom_unique');
        });
    }

    /**
     * Annule la migration.
     */
    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropUnique('niveaux_etablissement_id_nom_unique');
            $table->dropForeign(['etablissement_id']);
            $table->dropColumn('etablissement_id');
        });
    }
};

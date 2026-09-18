<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations en ajoutant etablissement_id à la table classes,
     * en rétro-remplissant les données existantes et en ajoutant les contraintes d'isolation.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('etablissement_id')
                ->nullable()
                ->after('id')
                ->constrained('etablissements')
                ->cascadeOnDelete();
        });

        // Rétro-remplissage des classes existantes
        $classes = DB::table('classes')->get();
        foreach ($classes as $classe) {
            $niveau = DB::table('niveaux')->where('id', $classe->niveau_id)->first();
            $etabId = $niveau?->etablissement_id;

            if (! $etabId && $niveau?->cycle_id) {
                $cycle = DB::table('cycles')->where('id', $niveau->cycle_id)->first();
                $etabId = $cycle?->etablissement_id;
                if (! $etabId && $cycle?->section_id) {
                    $section = DB::table('sections')->where('id', $cycle->section_id)->first();
                    $etabId = $section?->etablissement_id;
                }
            }

            if (! $etabId) {
                $etabId = DB::table('etablissements')->value('id');
            }

            if ($etabId) {
                DB::table('classes')->where('id', $classe->id)->update(['etablissement_id' => $etabId]);
            }
        }

        // Ajout d'index pour l'isolation et la performance des requêtes
        Schema::table('classes', function (Blueprint $table) {
            $table->index(['etablissement_id', 'is_active']);
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['etablissement_id']);
            $table->dropIndex(['etablissement_id', 'is_active']);
            $table->dropColumn('etablissement_id');
        });
    }
};

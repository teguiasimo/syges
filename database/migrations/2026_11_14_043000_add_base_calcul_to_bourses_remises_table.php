<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne `base_calcul` sur la table `bourses_remises`.
 *
 * Permet de dissocier l'assiette de calcul d'une remise en pourcentage (`total_scolarite`
 * par défaut sur le net initial de l'élève, ou `tranche_ciblee` sur la seule tranche ciblée)
 * de la tranche où la réduction est imputée (`cible_imputation` / `frais_scolaire_id`).
 */
return new class extends Migration
{
    /**
     * Exécute la migration pour ajouter `base_calcul` à `bourses_remises`.
     */
    public function up(): void
    {
        if (Schema::hasTable('bourses_remises') && ! Schema::hasColumn('bourses_remises', 'base_calcul')) {
            Schema::table('bourses_remises', function (Blueprint $table) {
                $table->string('base_calcul', 30)
                    ->default('total_scolarite')
                    ->after('mode_calcul');
            });

            DB::table('bourses_remises')
                ->whereNull('base_calcul')
                ->update(['base_calcul' => 'total_scolarite']);
        }
    }

    /**
     * Annule la migration en supprimant la colonne `base_calcul`.
     */
    public function down(): void
    {
        if (Schema::hasTable('bourses_remises') && Schema::hasColumn('bourses_remises', 'base_calcul')) {
            Schema::table('bourses_remises', function (Blueprint $table) {
                $table->dropColumn('base_calcul');
            });
        }
    }
};

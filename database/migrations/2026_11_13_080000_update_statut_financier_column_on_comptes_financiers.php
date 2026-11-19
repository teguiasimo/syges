<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration élargissant la colonne `statut_financier` sur `comptes_financiers_eleves`.
 *
 * Passe d'un ENUM restrictif (`insolvable`, `en_regle_tranche_1`, `en_regle_tranche_2`, `solde`)
 * à un VARCHAR(40) afin de supporter les nouveaux statuts précis basés sur les échéances réelles
 * (`en_attente`, `acompte`, `inscrit_a_jour`, `en_regle_tranche_1`, `en_regle_tranche_2`, `insolvable`, `solde`).
 */
return new class extends Migration
{
    /**
     * Exécute la migration pour autoriser tous les nouveaux statuts financiers.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('comptes_financiers_eleves', function (Blueprint $table) {
            // Remplace l'ENUM initial (et la contrainte CHECK sous SQLite) par une chaîne de 40 caractères.
            $table->string('statut_financier', 40)
                ->default('insolvable')
                ->change();
        });
    }

    /**
     * Annule la migration en ramenant la colonne au format ENUM d'origine.
     *
     * @return void
     */
    public function down(): void
    {
        // Normalisation préalable des éventuels nouveaux statuts pour éviter une erreur SQL au rollback.
        DB::table('comptes_financiers_eleves')
            ->whereIn('statut_financier', ['en_attente', 'acompte'])
            ->update(['statut_financier' => 'insolvable']);

        DB::table('comptes_financiers_eleves')
            ->where('statut_financier', 'inscrit_a_jour')
            ->update(['statut_financier' => 'en_regle_tranche_1']);

        Schema::table('comptes_financiers_eleves', function (Blueprint $table) {
            $table->enum('statut_financier', ['insolvable', 'en_regle_tranche_1', 'en_regle_tranche_2', 'solde'])
                ->default('insolvable')
                ->change();
        });
    }
};


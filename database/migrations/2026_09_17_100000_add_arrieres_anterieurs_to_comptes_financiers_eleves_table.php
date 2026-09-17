<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant la colonne `arrieres_anterieurs` dans la table `comptes_financiers_eleves`.
 *
 * Permet au comptable ou à la secrétaire de saisir ou reporter manuellement
 * les dettes et reliquats impayés d'une année scolaire antérieure lors de la réinscription (V1).
 */
return new class extends Migration
{
    /**
     * Exécute les modifications de schéma.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('comptes_financiers_eleves', function (Blueprint $table) {
            $table->decimal('arrieres_anterieurs', 12, 2)
                ->default(0)
                ->after('debit_initial')
                ->comment('Dette ou arriérés de scolarité reportés manuellement d\'une année antérieure');
        });
    }

    /**
     * Annule les modifications de schéma.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('comptes_financiers_eleves', function (Blueprint $table) {
            $table->dropColumn('arrieres_anterieurs');
        });
    }
};

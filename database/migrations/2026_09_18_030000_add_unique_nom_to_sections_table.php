<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant une contrainte d'unicité sur le nom de la section par établissement.
 */
return new class extends Migration
{
    /**
     * Applique la contrainte d'unicité (etablissement_id, nom).
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->unique(['etablissement_id', 'nom'], 'sections_etablissement_id_nom_unique');
        });
    }

    /**
     * Annule la contrainte d'unicité.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique('sections_etablissement_id_nom_unique');
        });
    }
};

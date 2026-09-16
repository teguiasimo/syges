<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant la colonne 'sigle' à la table institutions.
 * Permet de distinguer le code administratif officiel de l'acronyme usuel (ex: GSBSJ).
 */
return new class extends Migration
{
    /**
     * Exécute les modifications sur la table.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('sigle', 50)->nullable()->after('code')->comment('Sigle ou acronyme usuel (ex: GSJF, GSB-STP)');
        });
    }

    /**
     * Annule les modifications sur la table.
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('sigle');
        });
    }
};

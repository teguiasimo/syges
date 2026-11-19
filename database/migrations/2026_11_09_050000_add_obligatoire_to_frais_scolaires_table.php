<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour ajouter le caractère d'exigibilité obligatoire/optionnel
 * directement sur la grille des frais par niveau (`frais_scolaires`).
 *
 * Permet de définir si un frais rattaché à un niveau est dû obligatoirement par tous
 * les élèves de ce niveau (ex: scolarité, inscription, examen) ou optionnel (sur souscription :
 * bus, cantine, activités).
 */
return new class extends Migration
{
    /**
     * Exécute la migration.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('frais_scolaires', 'obligatoire')) {
            Schema::table('frais_scolaires', function (Blueprint $table) {
                $table->boolean('obligatoire')->nullable()->default(null)->after('montant');
            });

            // Initialise la valeur selon le type de frais lié (compatible SQLite & MySQL)
            DB::statement('UPDATE frais_scolaires SET obligatoire = (SELECT obligatoire FROM types_frais WHERE types_frais.id = frais_scolaires.type_frais_id) WHERE type_frais_id IS NOT NULL');
        }
    }

    /**
     * Annule la migration.
     */
    public function down(): void
    {
        if (Schema::hasColumn('frais_scolaires', 'obligatoire')) {
            Schema::table('frais_scolaires', function (Blueprint $table) {
                $table->dropColumn('obligatoire');
            });
        }
    }
};

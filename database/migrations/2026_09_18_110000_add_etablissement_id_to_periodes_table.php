<?php

use App\Models\Etablissement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant etablissement_id à la table periodes et ajustant l'index d'unicité.
 *
 * Permet à chaque établissement (campus) d'avoir son propre calendrier de trimestres,
 * ses propres dates limites de saisie et son propre verrouillage d'évaluations (Option A).
 */
return new class extends Migration
{
    /**
     * Applique la migration.
     */
    public function up(): void
    {
        // 1. Ajouter un index explicite sur annee_scolaire_id pour maintenir la contrainte de clé étrangère
        Schema::table('periodes', function (Blueprint $table) {
            $table->index('annee_scolaire_id', 'periodes_annee_scolaire_id_index');
        });

        // 2. Ajouter la colonne etablissement_id
        Schema::table('periodes', function (Blueprint $table) {
            $table->foreignId('etablissement_id')
                ->nullable()
                ->after('id')
                ->constrained('etablissements')
                ->cascadeOnDelete();
        });

        // 3. Remplir etablissement_id pour les périodes existantes avec le premier établissement trouvé
        $defaultEtabId = Etablissement::first()?->id;
        if ($defaultEtabId) {
            DB::table('periodes')->whereNull('etablissement_id')->update(['etablissement_id' => $defaultEtabId]);
        }

        // 4. Supprimer l'ancienne contrainte d'unicité et ajouter la nouvelle composite
        Schema::table('periodes', function (Blueprint $table) {
            $table->dropUnique(['annee_scolaire_id', 'code']);
            $table->unique(['etablissement_id', 'annee_scolaire_id', 'code'], 'periodes_etablissement_annee_code_unique');
        });
    }

    /**
     * Annule la migration.
     */
    public function down(): void
    {
        Schema::table('periodes', function (Blueprint $table) {
            $table->dropUnique('periodes_etablissement_annee_code_unique');
            $table->unique(['annee_scolaire_id', 'code']);
            $table->dropForeign(['etablissement_id']);
            $table->dropColumn('etablissement_id');
            $table->dropIndex('periodes_annee_scolaire_id_index');
        });
    }
};

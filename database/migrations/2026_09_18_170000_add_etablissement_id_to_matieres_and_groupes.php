<?php

use App\Models\Etablissement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration d'architecture SaaS multi-établissements pour le Module Matières & Coefficients.
 *
 * Ajoute la colonne etablissement_id :
 * 1. Sur groupes_matieres : permet à chaque établissement d'avoir sa propre arborescence
 *    de blocs pédagogiques officiels (Francophone, Anglophone, Primaire, Technique).
 * 2. Sur matieres : permet un modèle hybride combinant un tronc commun institutionnel
 *    (etablissement_id = null) et des matières spécifiques locales par établissement.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations pour ajouter etablissement_id et ajuster l'unicité des codes.
     */
    public function up(): void
    {
        // 1. Table groupes_matieres (Blocs Pédagogiques)
        Schema::table('groupes_matieres', function (Blueprint $table) {
            $table->foreignId('etablissement_id')
                ->nullable()
                ->after('id')
                ->constrained('etablissements')
                ->cascadeOnDelete()
                ->comment('Établissement scolaire propriétaire du bloc pédagogique (null = bloc standard)');

            // Suppression de l'ancienne contrainte d'unicité globale sur le code
            $table->dropUnique('groupes_matieres_code_unique');

            // Nouvelle contrainte d'unicité : un code est unique par établissement
            $table->unique(['etablissement_id', 'code'], 'groupes_matieres_etab_code_unique');
        });

        // Migration des blocs pédagogiques existants vers le premier établissement disponible
        $firstEtablissementId = DB::table('etablissements')->value('id');
        if ($firstEtablissementId) {
            DB::table('groupes_matieres')
                ->whereNull('etablissement_id')
                ->update(['etablissement_id' => $firstEtablissementId]);
        }

        // 2. Table matieres (Catalogue Général)
        Schema::table('matieres', function (Blueprint $table) {
            $table->foreignId('etablissement_id')
                ->nullable()
                ->after('id')
                ->constrained('etablissements')
                ->cascadeOnDelete()
                ->comment('Établissement propriétaire de la matière (null = tronc commun institutionnel)');

            // Suppression de l'ancienne contrainte d'unicité globale sur le code
            $table->dropUnique('matieres_code_unique');

            // Nouvelle contrainte d'unicité par portée d'établissement
            $table->unique(['etablissement_id', 'code'], 'matieres_etab_code_unique');
        });
    }

    /**
     * Annule les modifications apportées aux tables.
     */
    public function down(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->dropUnique('matieres_etab_code_unique');
            $table->dropConstrainedForeignId('etablissement_id');
            $table->unique('code', 'matieres_code_unique');
        });

        Schema::table('groupes_matieres', function (Blueprint $table) {
            $table->dropUnique('groupes_matieres_etab_code_unique');
            $table->dropConstrainedForeignId('etablissement_id');
            $table->unique('code', 'groupes_matieres_code_unique');
        });
    }
};

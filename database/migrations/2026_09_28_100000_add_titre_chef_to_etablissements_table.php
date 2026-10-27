<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant les colonnes titre_chef et titre_chef_en à la table etablissements.
 *
 * Permet aux administrateurs de personnaliser librement le titre officiel du chef d'établissement
 * (ex: 'Le Directeur', 'Le Proviseur', 'Le Principal', 'The Director', 'The Principal')
 * pour l'ensemble des états et documents officiels (bulletins, certificats de scolarité, fiches, etc.).
 */
return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->string('titre_chef', 100)->nullable()->after('nom_chef_etablissement')
                ->comment('Titre officiel personnalisé du chef d\'établissement en français (ex: Le Directeur, Le Proviseur, Le Principal)');
            $table->string('titre_chef_en', 100)->nullable()->after('titre_chef')
                ->comment('Titre officiel personnalisé en anglais (ex: The Director, The Principal, The Headmaster)');
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn(['titre_chef', 'titre_chef_en']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 9 : Attribution des Classes & Matières (Cahier des charges Gesco V1.1).
 * 
 * Crée :
 * 1. La table `attributions` (Passerelle opérationnelle enseignant <-> classe <-> matière).
 * 2. La colonne `professeur_principal_id` sur la table `classes` (Gesco Section 2.7.5).
 */
return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        // 1. Table des Attributions des Enseignants aux Classes et Matières
        Schema::create('attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->decimal('volume_horaire_reel', 4, 2)->default(0.00)->comment('Volume horaire effectif hebdomadaire dispensé (ex: 4.00h)');
            $table->boolean('is_titulaire')->default(true)->comment('Vrai s\'il s\'agit de l\'enseignant titulaire officiel du cours');
            $table->boolean('is_active')->default(true)->comment('Attribution active pour la période');
            $table->timestamps();

            // Unicité : pour une année scolaire donnée, une matière dans une classe a un enseignant unique
            $table->unique(['annee_scolaire_id', 'classe_id', 'matiere_id']);
        });

        // 2. Ajout de la liaison Professeur Principal sur la table des classes (Gesco 2.7.5)
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('professeur_principal_id')
                ->nullable()
                ->after('capacite_max')
                ->constrained('personnels')
                ->nullOnDelete()
                ->comment('Enseignant désigné Professeur Principal de la classe');
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['professeur_principal_id']);
            $table->dropColumn('professeur_principal_id');
        });

        Schema::dropIfExists('attributions');
    }
};

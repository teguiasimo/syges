<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 3 : Gestion des Périodes & Paramètres d'Évaluation (Cahier des charges Gesco).
 *
 * Crée les tables :
 * 1. annees_scolaires (Cadre temporel global, statut, mode de calcul des moyennes).
 * 2. periodes (Trimestres ou semestres d'évaluation).
 * 3. evaluations (Séquences ou examens, pondérations, dates limites et verrouillage).
 */
return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        // 1. Table des Années Scolaires
        Schema::create('annees_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Code de l\'année (ex: 2026-2027)');
            $table->string('nom', 120)->comment('Nom en français (ex: Année Scolaire 2026-2027)');
            $table->string('nom_en', 120)->nullable()->comment('Nom en anglais (ex: Academic Year 2026-2027)');
            $table->date('date_debut')->comment('Date d\'ouverture officielle');
            $table->date('date_fin')->comment('Date de clôture officielle');
            $table->string('statut', 30)->default('en_cours')->comment('en_preparation, en_cours, cloturee');
            $table->string('mode_calcul_moyenne', 40)->default('arithmetique_simple')->comment('arithmetique_simple ou ponderee_pourcentage');
            $table->boolean('is_current')->default(false)->comment('Indicateur de l\'année scolaire active du système');
            $table->timestamps();
        });

        // 2. Table des Périodes (Trimestres / Semestres)
        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->string('code', 30)->comment('Code de la période (ex: TRIM-1, TERM-1)');
            $table->string('nom', 100)->comment('Nom en français (ex: 1er Trimestre)');
            $table->string('nom_en', 100)->nullable()->comment('Nom en anglais (ex: 1st Term)');
            $table->unsignedSmallInteger('ordre')->default(1)->comment('Ordre chronologique (1, 2, 3...)');
            $table->date('date_debut')->nullable()->comment('Date de début de la période');
            $table->date('date_fin')->nullable()->comment('Date de fin de la période');
            $table->string('statut', 30)->default('a_venir')->comment('a_venir, en_cours, cloturee');
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'code']);
        });

        // 3. Table des Évaluations (Séquences / Contrôles)
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            $table->string('code', 30)->comment('Code de l\'évaluation (ex: SEQ-1, SEQ-2, EXAM)');
            $table->string('nom', 100)->comment('Nom en français (ex: Séquence 1)');
            $table->string('nom_en', 100)->nullable()->comment('Nom en anglais (ex: Sequence 1)');
            $table->unsignedSmallInteger('ordre')->default(1)->comment('Ordre chronologique dans le trimestre (1, 2...)');
            $table->decimal('ponderation', 5, 2)->default(50.00)->comment('Poids en % pour le calcul pondéré de la moyenne');
            $table->date('date_debut')->nullable()->comment('Date de début de la session d\'évaluation');
            $table->date('date_fin')->nullable()->comment('Date de fin de la session d\'évaluation');
            $table->date('date_limite_saisie')->nullable()->comment('Date limite de saisie des notes pour les enseignants');
            $table->boolean('is_locked')->default(false)->comment('Verrouillage manuel ou automatique de la saisie');
            $table->string('statut', 30)->default('a_venir')->comment('a_venir, ouverte, verrouillee, cloturee');
            $table->timestamps();

            $table->unique(['periode_id', 'code']);
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('periodes');
        Schema::dropIfExists('annees_scolaires');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 4 : Gestion des Matières & Coefficients (Cahier des charges Gesco).
 *
 * Crée les tables :
 * 1. groupes_matieres (Blocs pédagogiques ordonnés avec etablissement_id pour personnalisation locale).
 * 2. matieres (Catalogue des disciplines avec etablissement_id nullable pour matières nationales ou locales).
 * 3. matiere_niveaux (Grille des coefficients par Niveau + Série/Filière).
 */
return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        // 1. Table des Groupes de Matières (Blocs Pédagogiques)
        Schema::create('groupes_matieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->string('code', 30)->comment('Code court du groupe (ex: BLOC-1, BLOC-SCI)');
            $table->string('nom', 120)->comment('Nom du groupe en français (ex: Enseignements Scientifiques)');
            $table->string('nom_en', 120)->nullable()->comment('Nom en anglais (ex: Scientific Subjects)');
            $table->string('couleur', 30)->nullable()->default('indigo')->comment('Couleur d\'identification visuelle');
            $table->unsignedSmallInteger('ordre')->default(1)->comment('Ordre d\'apparition sur le bulletin PDF de haut en bas');
            $table->timestamps();

            $table->unique(['etablissement_id', 'code'], 'groupes_matieres_etab_code_unique');
        });

        // 2. Table du Catalogue Général des Matières
        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->string('code', 30)->comment('Code officiel de la matière (ex: MATH, PCT, FRAN, ANG, SVT)');
            $table->string('nom', 120)->comment('Nom complet en français (ex: Mathématiques)');
            $table->string('nom_en', 120)->nullable()->comment('Nom en anglais (ex: Mathematics)');
            $table->string('libelle_bulletin', 100)->nullable()->comment('Libellé personnalisé pour impression sur le bulletin');
            $table->string('libelle_bulletin_en', 100)->nullable()->comment('Libellé d\'impression en anglais');
            $table->boolean('is_active')->default(true)->comment('Statut d\'activation de la discipline');
            $table->timestamps();

            $table->unique(['etablissement_id', 'code'], 'matieres_etab_code_unique');
        });

        // 3. Table des Coefficients par Niveau & Série (Moteur d'Évaluation)
        Schema::create('matiere_niveaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('groupe_matiere_id')->constrained('groupes_matieres')->cascadeOnDelete();
            $table->string('serie', 50)->nullable()->default('Toutes')->comment('Série / Filière spécifique (ex: C, D, A4, TI, ou Toutes)');
            $table->decimal('coefficient', 4, 2)->default(1.00)->comment('Poids académique (ex: 4.00, 3.00, 1.50)');
            $table->decimal('note_max', 5, 2)->default(20.00)->comment('Note maximale d\'évaluation (défaut 20.00)');
            $table->decimal('note_eliminatoire', 5, 2)->nullable()->comment('Note éliminatoire non bloquante, signalée et disqualifiante pour les mentions (V1.1)');
            $table->boolean('is_facultative')->default(false)->comment('Matière optionnelle : seuls les points > 10 comptent en bonus');
            $table->boolean('is_comptabilisee')->default(true)->comment('Comptabiliser dans la moyenne générale (Oui/Non)');
            $table->timestamps();

            // Unicité d'une matière pour un niveau et une série donnés
            $table->unique(['niveau_id', 'matiere_id', 'serie']);
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matiere_niveaux');
        Schema::dropIfExists('matieres');
        Schema::dropIfExists('groupes_matieres');
    }
};

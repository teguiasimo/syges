<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 10 : Saisie des Notes & Calcul des Bulletins (Gesco V1.1 Section 10).
 *
 * Crée :
 * 1. La table `notes` : Enregistrement des notes par élève, attribution (matière/classe/enseignant) et évaluation.
 * 2. La table `disciplines_bulletins` : Synthèse disciplinaire trimestrielle pour l'impression du bulletin.
 * 3. Ajoute sur la table `etablissements` : Le mode de bulletin par défaut (avec_competences / sans_competences)
 *    et le mode de calcul trimestriel par défaut (arithmétique simple ou pondéré).
 */
return new class extends Migration
{
    /**
     * Exécute les migrations du Module 10.
     *
     * @return void
     */
    public function up(): void
    {
        // 1. Table des Notes d'Évaluation
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('attribution_id')->constrained('attributions')->cascadeOnDelete();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();

            // Note chiffrée (0.00 à 20.00) - nullable en cas d'absence
            $table->decimal('valeur_note', 4, 2)->nullable()->comment('Note chiffrée sur 20.00');

            // Statut d'évaluation (Présent, Absent Justifié, Absent Non Justifié, Non Évalué)
            $table->enum('statut_presence', [
                'present',
                'absent_justifie',
                'absent_non_justifie',
                'non_evalue'
            ])->default('present')->comment('Statut de présence de l\'élève lors de l\'épreuve');

            // Appréciation qualitative de l'enseignant (ex: "Très bon travail", "Des efforts")
            $table->string('appreciation', 255)->nullable()->comment('Appréciation qualitative de l\'enseignant');

            // Approche Par Compétences (APC - Nouvelles normes MINESEC Cameroun)
            $table->text('competence_evaluee')->nullable()->comment('Énoncé de la compétence ciblée pour l\'évaluation');
            $table->enum('cote', [
                'CTBA', // Compétence Très Bien Acquise (16-20)
                'CBA',  // Compétence Bien Acquise (14-15.99)
                'CMA',  // Compétence Moyennement Acquise (10-13.99)
                'CNA'   // Compétence Non Acquise (<10)
            ])->nullable()->comment('Cote de niveau d\'acquisition de la compétence (APC)');

            // Sécurité et verrouillage
            $table->boolean('is_verrouillee')->default(false)->comment('Verrouillage empêchant les modifications ultérieures');
            $table->foreignId('saisie_par_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Utilisateur ayant saisi la note');
            $table->foreignId('modifie_par_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Dernier utilisateur ayant modifié la note');

            $table->timestamps();

            // Unicité : un élève n'a qu'une seule note pour une attribution et une évaluation données
            $table->unique(['inscription_id', 'attribution_id', 'evaluation_id'], 'unique_note_eleve_evaluation');
            $table->index(['attribution_id', 'evaluation_id']);
        });

        // 2. Table de Synthèse Disciplinaire Trimestrielle pour les Bulletins
        Schema::create('disciplines_bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();

            $table->unsignedSmallInteger('absences_justifiees')->default(0)->comment('Heures d\'absences justifiées');
            $table->unsignedSmallInteger('absences_non_justifiees')->default(0)->comment('Heures d\'absences non justifiées');
            $table->unsignedSmallInteger('retards')->default(0)->comment('Nombre de retards');
            $table->unsignedSmallInteger('consignes')->default(0)->comment('Heures de consignes / retenues');
            $table->unsignedSmallInteger('avertissements_conduite')->default(0)->comment('Nombre d\'avertissements de conduite');
            $table->unsignedSmallInteger('blames_conduite')->default(0)->comment('Nombre de blâmes de conduite');
            $table->unsignedSmallInteger('exclusions_jours')->default(0)->comment('Nombre de jours d\'exclusion');

            $table->text('decision_conseil')->nullable()->comment('Décision ou avis motivé du conseil de classe');
            $table->timestamps();

            $table->unique(['inscription_id', 'periode_id']);
        });

        // 3. Paramétrage du format de bulletin et mode de calcul sur les établissements
        Schema::table('etablissements', function (Blueprint $table) {
            $table->enum('mode_bulletin_defaut', ['avec_competences', 'sans_competences'])
                ->default('sans_competences')
                ->after('statut_juridique')
                ->comment('Format de bulletin par défaut : avec_competences (APC) ou sans_competences (Standard)');

            $table->enum('type_calcul_trimestre', ['arithmetique', 'pondere'])
                ->default('arithmetique')
                ->after('mode_bulletin_defaut')
                ->comment('Règle de calcul des moyennes de trimestre : arithmétique simple ou pondéré par %');
        });
    }

    /**
     * Annule les migrations du Module 10.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn(['mode_bulletin_defaut', 'type_calcul_trimestre']);
        });

        Schema::dropIfExists('disciplines_bulletins');
        Schema::dropIfExists('notes');
    }
};

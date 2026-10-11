<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module de Suivi Pédagogique : Cahier de Texte & Fiches de Progression.
 * Conforme aux exigences du Manuel Aurore (Section 5.1.8 - Fiches de progression par classe et matière)
 * et au Cahier des Charges Gesco V1.1.
 *
 * Permet aux enseignants de consigner leurs séances de cours (chapitres, leçons, heures prévues/effectuées, devoirs)
 * et aux censeurs / directeurs des études de viser et valider l'avancement effectif du programme.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        Schema::create('fiches_progression', function (Blueprint $table) {
            $table->id();

            // Cloisonnement institutionnel et temporel
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            
            // Attribution pédagogique (Enseignant <-> Classe <-> Matière)
            $table->foreignId('attribution_id')->constrained('attributions')->cascadeOnDelete();
            
            // Période scolaire (Trimestre / Semestre - optionnel)
            $table->foreignId('periode_id')->nullable()->constrained('periodes')->nullOnDelete();

            // Informations de la séance de cours
            $table->date('date_seance')->comment('Date de dispensation de la séance de cours');
            $table->unsignedSmallInteger('semaine_numero')->nullable()->comment('Numéro de semaine pédagogique (ex: 1 à 36)');
            $table->string('chapitre')->comment('Titre ou numéro du chapitre / module didactique');
            $table->string('titre_lecon')->comment('Intitulé précis de la leçon ou unité d\'apprentissage');
            $table->text('resume_activite')->nullable()->comment('Résumé des notions enseignées, travaux dirigés ou activités menées');
            $table->text('travail_a_faire')->nullable()->comment('Devoirs de maison, lectures recommandées ou exercices assignés');

            // Métriques horaires
            $table->decimal('heures_prevues', 4, 2)->default(2.00)->comment('Volume horaire théorique planifié (ex: 2.00h)');
            $table->decimal('heures_effectuees', 4, 2)->default(2.00)->comment('Volume horaire effectivement dispensé en classe');

            // Statut de réalisation
            $table->enum('statut', ['fait', 'en_cours', 'reporte'])->default('fait')->comment('État de la leçon : achevée, en cours ou reportée');
            $table->string('motif_report')->nullable()->comment('Motif d\'ajournement ou de report le cas échéant');

            // Contrôle & Visa hiérarchique (Censeur / Directeur des Études / Inspecteur)
            $table->boolean('visa_censeur')->default(false)->comment('Indique si la séance a été vérifiée et visée par le censeur');
            $table->timestamp('date_visa')->nullable()->comment('Date et heure de l\'apposition du visa');
            $table->foreignId('vise_par_id')->nullable()->constrained('users')->nullOnDelete()->comment('Utilisateur ayant visé la séance');
            $table->text('observations_censeur')->nullable()->comment('Remarques, appréciations ou directives pédagogiques du censeur');

            $table->timestamps();

            // Index pour l'optimisation des requêtes et filtres
            $table->index(['etablissement_id', 'attribution_id'], 'idx_progression_etab_attr');
            $table->index(['annee_scolaire_id', 'date_seance'], 'idx_progression_annee_date');
            $table->index(['attribution_id', 'periode_id'], 'idx_progression_attr_periode');
            $table->index(['visa_censeur'], 'idx_progression_visa');
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiches_progression');
    }
};

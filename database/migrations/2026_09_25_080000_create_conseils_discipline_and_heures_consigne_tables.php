<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module de Conseil de Discipline & Heures de Consigne (Phase 5).
 *
 * Conforme à la législation scolaire MINESEC (Cameroun) :
 * - Gestion formelle des sessions du Conseil de Discipline (convocations officielles, délibérations, PV).
 * - Comparutions et sanctions majeures (exclusions temporaires, définitives, blâmes).
 * - Registre d'assiduité des heures de retenue et consignes du samedi.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('heures_consigne');
        Schema::dropIfExists('dossiers_conseil_discipline');
        Schema::dropIfExists('conseils_discipline');

        // 1. Table des sessions formelles de Conseil de Discipline
        Schema::create('conseils_discipline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->string('numero_session', 50)->comment('Ex: CD-2026/S1');
            $table->dateTime('date_conseil')->comment('Date et heure de tenue de la session');
            $table->string('lieu', 100)->default('Salle des Actes / Bureau du Chef d’Établissement');
            $table->string('president_seance', 150)->comment('Président de séance (Proviseur/Principal)');
            $table->string('rapporteur', 150)->comment('Rapporteur (Censeur / SG)');
            $table->text('membres_presents')->nullable()->comment('Membres présents (enseignants, APEE, délégués)');
            $table->enum('statut', ['planifie', 'en_cours', 'tenu', 'annule'])->default('planifie');
            $table->text('observations_generales')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['etablissement_id', 'annee_scolaire_id', 'statut'], 'cd_etab_annee_statut_idx');
        });

        // 2. Table des dossiers d'élèves traduits en Conseil de Discipline
        Schema::create('dossiers_conseil_discipline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conseil_discipline_id')->constrained('conseils_discipline')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->text('motifs_renvoi')->comment('Faits et fautes graves reprochés');
            $table->text('plaidoirie_eleve')->nullable()->comment('Explications et défense de l’élève / parent');
            $table->enum('decision_finale', [
                'en_attente',
                'acquittement',
                'avertissement',
                'blame',
                'consigne',
                'exclusion_temporaire',
                'exclusion_definitive',
            ])->default('en_attente');
            $table->integer('nombre_jours_exclusion')->nullable();
            $table->integer('nombre_heures_consigne')->nullable();
            $table->date('date_convocation')->nullable()->comment('Date d’émission de la convocation');
            $table->boolean('parent_present')->default(false);
            $table->boolean('is_convocation_envoyee')->default(false);
            $table->timestamps();

            $table->index(['conseil_discipline_id', 'decision_finale'], 'dcd_cd_decision_idx');
            $table->index('inscription_id', 'dcd_insc_idx');
        });

        // 3. Table des Heures de Consigne / Retenue du Samedi
        Schema::create('heures_consigne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->date('date_consigne')->comment('Date de la séance de retenue (ex: samedi matin)');
            $table->time('heure_debut')->nullable()->default('08:00:00');
            $table->time('heure_fin')->nullable()->default('12:00:00');
            $table->integer('nombre_heures')->default(2)->comment('Volume horaire de consigne');
            $table->string('motif', 500)->comment('Motif de la sanction de retenue');
            $table->text('travail_assigne')->nullable()->comment('Devoirs à rendre ou travaux d’intérêt général');
            $table->string('surveillant_responsable', 150)->nullable()->comment('Surveillant Général / Éducateur');
            $table->enum('statut', ['programmee', 'effectuee', 'non_effectuee', 'reportee'])->default('programmee');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['etablissement_id', 'annee_scolaire_id', 'statut'], 'hc_etab_annee_statut_idx');
            $table->index(['inscription_id', 'date_consigne'], 'hc_insc_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heures_consigne');
        Schema::dropIfExists('dossiers_conseil_discipline');
        Schema::dropIfExists('conseils_discipline');
    }
};

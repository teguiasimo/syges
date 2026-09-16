<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 15 : Tableau de Bord, Statistiques & Conseil de Classe (Gesco V1.1 Section 15 & Section 2.6).
 *
 * Crée :
 * - La table `deliberations_conseils` pour enregistrer les délibérations trimestrielles et annuelles :
 *   * Saisie des appréciations globales du conseil
 *   * Attribution des distinctions et déchéance automatique (absences >= 15h ou note éliminatoire - Module 12.4 & 4.3)
 *   * Moteur de proposition de passage de fin d'année (Promu / Redouble / Exclu - Module 2.6 Étape A)
 *   * Validation humaine obligatoire par le Censeur ou Chef d'Établissement (Module 2.6 Étape B)
 */
return new class extends Migration
{
    /**
     * Exécute les migrations du Module 15.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('deliberations_conseils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('periodes')->cascadeOnDelete();

            // Type de délibération : 'trimestrielle' ou 'annuelle'
            $table->enum('type_deliberation', ['trimestrielle', 'annuelle'])
                ->default('trimestrielle')
                ->comment('Portée de la délibération : bilan de trimestre ou bilan de fin d\'année');

            // Résultats chiffrés consolidés
            $table->decimal('moyenne_obtenue', 4, 2)->default(0.00)->comment('M.G.T. ou M.A.G. calculée');
            $table->unsignedSmallInteger('rang')->nullable()->comment('Rang obtenu dans la classe');

            // Distinctions et mentions honorifiques (Gesco 15.2 & 12.4)
            $table->enum('mention_obtenue', [
                'aucune',
                'tableau_honneur',
                'encouragements',
                'felicitations',
            ])->default('aucune')->comment('Distinction académique attribuée');

            $table->boolean('mention_bloquee')->default(false)
                ->comment('Indique si la mention a été annulée d\'office pour indiscipline ou note éliminatoire');
            $table->string('motif_blocage_mention', 150)->nullable()
                ->comment('Raison de la déchéance : absences_excessives, note_eliminatoire, sanction');

            // Décision de fin d'année (Gesco 2.6 Moteur de promotion)
            $table->enum('statut_propose', [
                'promu',
                'redouble',
                'exclu',
                'en_attente',
            ])->default('en_attente')->comment('Proposition automatique de passage calculée d\'après la M.A.G. (Étape A)');

            $table->enum('statut_retenu', [
                'promu',
                'redouble',
                'exclu',
                'en_attente',
            ])->default('en_attente')->comment('Décision finale votée et retenue par le conseil de classe');

            // Appréciations et observations qualitatives
            $table->text('appreciation_globale')->nullable()->comment('Appréciation générale du conseil de classe');
            $table->string('decision_conseil', 150)->nullable()->comment('Formulation textuelle de la décision');
            $table->text('observations')->nullable()->comment('Remarques confidentielles de la commission');

            // Validation humaine obligatoire (Gesco 2.6 Étape B)
            $table->boolean('est_valide')->default(false)->comment('Validation définitive par la direction');
            $table->foreignId('valide_par_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Censeur ou Proviseur ayant validé');
            $table->timestamp('valide_at')->nullable()->comment('Date et heure de validation officielle');

            $table->timestamps();

            // Index d'unicité et de performance
            $table->unique(['inscription_id', 'type_deliberation', 'periode_id'], 'unique_deliberation_eleve_periode');
            $table->index(['classe_id', 'type_deliberation', 'periode_id'], 'idx_deliberation_classe_session');
        });
    }

    /**
     * Annule les migrations du Module 15.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('deliberations_conseils');
    }
};

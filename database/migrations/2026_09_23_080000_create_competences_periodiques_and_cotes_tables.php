<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations pour la Pédagogie APC (Phase 3).
     *
     * Crée :
     * 1. competences_periodiques : Banque de compétences par matière, classe/niveau et séquence/trimestre.
     * 2. cotes_configurations : Grille optionnelle de cotes d'acquisition personnalisées (A/B/C ou CTBA/CBA).
     */
    public function up(): void
    {
        // 1. Table des Compétences Périodiques du Programme
        Schema::create('competences_periodiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('periodes')->cascadeOnDelete();
            $table->foreignId('evaluation_id')->nullable()->constrained('evaluations')->cascadeOnDelete();
            
            $table->text('libelle_competence')->comment('Intitulé officiel de la compétence évaluée');
            $table->text('description')->nullable()->comment('Objectifs pédagogiques détaillés ou éléments clés');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['etablissement_id', 'classe_id', 'matiere_id'], 'idx_comp_etab_classe_mat');
            $table->index(['classe_id', 'periode_id'], 'idx_comp_classe_periode');
            $table->index(['classe_id', 'evaluation_id'], 'idx_comp_classe_eval');
        });

        // 2. Table des Cotes d'Acquisition Personnalisées (Optionnelles)
        Schema::create('cotes_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('code_cote', 10)->comment('CTBA, CBA, CMA, CNA ou A, B, C, D');
            $table->string('libelle', 100)->comment('Ex: Compétence Très Bien Acquise ou Excellent');
            $table->string('libelle_en', 100)->nullable()->comment('Ex: Expert ou Distinction');
            $table->decimal('borne_minimale', 5, 2)->default(0.00);
            $table->decimal('borne_maximale', 5, 2)->default(20.00);
            $table->string('couleur', 25)->default('#15803d')->comment('Code hexadécimal couleur du badge');
            $table->integer('ordre')->default(1);
            $table->timestamps();

            $table->index(['etablissement_id', 'borne_minimale', 'borne_maximale'], 'idx_cotes_bornes');
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotes_configurations');
        Schema::dropIfExists('competences_periodiques');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour la gestion des Dérogations & Dispenses Officielles (Phase 4).
 *
 * Conforme aux directives du MINESEC / MINEDUB camerounais :
 * - Gestion des dispenses d'Éducation Physique et Sportive (EPS), langues vivantes et travaux pratiques.
 * - Suivi des certificats médicaux d'inaptitude physique et dérogations ministérielles.
 * - Neutralisation automatique du coefficient dans le moteur de calcul du bulletin.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dispenses_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('periodes')->nullOnDelete()
                ->comment('Période ou trimestre concerné. Null si la dispense couvre toute l’année scolaire');

            $table->string('type_dispense', 50)->default('medicale')
                ->comment('Type : medicale (EPS/inaptitude), administrative, handicap, derogation_special');
            $table->text('motif')->comment('Motif circonstancié de la dispense ou diagnostic autorisé');
            $table->string('reference_certificat', 100)->nullable()
                ->comment('Numéro ou référence du certificat médical / acte ministériel');
            $table->string('medecin_ou_autorite', 150)->nullable()
                ->comment('Nom du médecin, hôpital, centre médical ou autorité signataire');
            $table->date('date_debut')->nullable()->comment('Date de prise d’effet de la dispense');
            $table->date('date_fin')->nullable()->comment('Date de fin de validité de la dispense');
            $table->boolean('neutraliser_coefficient')->default(true)
                ->comment('Si vrai, le coefficient de la matière est neutralisé (0) sur le bulletin');
            $table->enum('statut', ['validee', 'en_attente', 'rejetee', 'expiree'])->default('validee')
                ->comment('Statut d’approbation de la dispense');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index de performance pour le moteur de calcul de bulletin
            $table->index(['inscription_id', 'matiere_id', 'statut']);
            $table->index(['etablissement_id', 'annee_scolaire_id', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispenses_eleves');
    }
};

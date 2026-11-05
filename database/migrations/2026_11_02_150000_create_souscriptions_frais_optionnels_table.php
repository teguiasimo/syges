<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour la gestion des souscriptions aux Frais Scolaires Optionnels.
 *
 * Permet aux élèves d'un établissement/complexe scolaire de souscrire sélectivement
 * à des prestations et services annexes paramétrés par niveau (ex: Bus scolaire / Transport,
 * Cantine, Internat, Activités parascolaires, etc.).
 *
 * Règles métier :
 * - Un élève ne peut souscrire qu'une seule fois activement à un même frais optionnel.
 * - L'activation ou l'annulation de la souscription recalcule automatiquement le débit exigible
 *   et le solde restant du compte financier de l'élève.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('souscriptions_frais_optionnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_financier_id')
                ->constrained('comptes_financiers_eleves')
                ->cascadeOnDelete()
                ->comment('Compte financier annuel de l\'élève bénéficiaire');
            $table->foreignId('frais_scolaire_id')
                ->constrained('frais_scolaires')
                ->cascadeOnDelete()
                ->comment('Référence au frais optionnel paramétré dans la grille tarifaire');
            $table->decimal('montant', 12, 2)
                ->comment('Montant facturé (valeur par défaut du tarif ou montant négocié)');
            $table->date('date_souscription')
                ->comment('Date de prise d\'effet du service optionnel');
            $table->enum('statut', ['actif', 'annule'])
                ->default('actif')
                ->comment('État de la souscription (actif = imputé au compte, annule = exclu)');
            $table->text('observations')
                ->nullable()
                ->comment('Précisions éventuelles (ex: arrêt de bus, régime alimentaire)');
            $table->timestamps();

            // Unicité : un élève n'a qu'une seule souscription par frais scolaire donné
            $table->unique(['compte_financier_id', 'frais_scolaire_id'], 'souscription_compte_frais_unique');
            $table->index(['compte_financier_id', 'statut'], 'idx_souscription_compte_statut');
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('souscriptions_frais_optionnels');
    }
};


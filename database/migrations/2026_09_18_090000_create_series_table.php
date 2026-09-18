<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de création de la table des séries scolaires.
 *
 * Conserve uniquement l'intitulé/nom unique de la série par établissement
 * (ex: GENERAL, C, D, E, TI, ALLEMAND, ESPAGNOL, A4 ALL, A4 ESP).
 */
return new class extends Migration
{
    /**
     * Exécute les migrations pour créer la table series.
     */
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('nom', 50)->comment('Nom unique de la série (ex: GENERAL, C, D, TI, A4 ALL...)');
            $table->timestamps();

            // Unicité du nom de la série par établissement
            $table->unique(['etablissement_id', 'nom']);
        });
    }

    /**
     * Annule la migration en supprimant la table series.
     */
    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};

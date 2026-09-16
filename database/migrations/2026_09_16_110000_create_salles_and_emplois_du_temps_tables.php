<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 13 : Emplois du Temps & Gestion des Salles (Gesco V1.1).
 *
 * Crée :
 * - `salles` : Référentiel des locaux pédagogiques, laboratoires, salles informatiques avec capacité d'accueil.
 * - `seances_emploi_temps` : Grille hebdomadaire structurée (Lundi à Samedi) avec détection anti-conflits (enseignant, salle, classe), gestion des remplacements et annulations.
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
        // 1. Table des Salles Physiques (Gesco 13.1)
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('code', 50);
            $table->enum('type_salle', ['standard', 'laboratoire', 'informatique', 'sport', 'polyvalente'])
                ->default('standard');
            $table->unsignedInteger('capacite_places')->default(50);
            $table->enum('statut', ['disponible', 'indisponible'])->default('disponible');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['etablissement_id', 'code'], 'salles_etab_code_unique');
        });

        // 2. Grille Hebdomadaire des Emplois du Temps (Gesco 13.2, 13.3, 13.4)
        Schema::create('seances_emploi_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('attribution_id')->constrained('attributions')->cascadeOnDelete();
            $table->foreignId('salle_id')->nullable()->constrained('salles')->nullOnDelete();
            $table->enum('jour_semaine', ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']);
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->enum('statut', ['actif', 'annule', 'remplace'])->default('actif');
            $table->foreignId('remplace_par_personnel_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->string('motif_annulation', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index composite optimisé pour les vérifications anti-conflits
            $table->index(['annee_scolaire_id', 'jour_semaine', 'heure_debut', 'heure_fin'], 'seance_creneau_idx');
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('seances_emploi_temps');
        Schema::dropIfExists('salles');
    }
};

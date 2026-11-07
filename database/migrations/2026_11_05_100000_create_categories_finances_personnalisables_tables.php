<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour la personnalisation libre des catégories financières (Recettes Diverses & Bourses/Remises).
 *
 * Permet à chaque établissement de créer ses propres catégories :
 * - Catégories de recettes diverses (ex: Uniformes, Macarons, Location, Kermesse, Duplicata...)
 * - Catégories de bourses et remises (ex: Paiement en avance, Fratrie, Personnel, Excellence, Cas social...)
 */
return new class extends Migration
{
    /**
     * Exécute les migrations de création des référentiels de catégories personnalisables.
     */
    public function up(): void
    {
        // 1. Table des catégories personnalisables pour les Recettes Diverses
        Schema::create('categories_recettes_diverses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['etablissement_id', 'nom'], 'cat_recettes_etab_nom_unique');
        });

        // 2. Table des catégories personnalisables pour les Bourses et Remises
        Schema::create('categories_bourses_remises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['etablissement_id', 'nom'], 'cat_bourses_etab_nom_unique');
        });

        // 3. Extension de la longueur de la colonne categorie sur reglements_divers et bourses_remises
        Schema::table('reglements_divers', function (Blueprint $table) {
            $table->string('categorie', 100)->default('autre')->change();
        });

        Schema::table('bourses_remises', function (Blueprint $table) {
            $table->string('categorie', 100)->default('autre')->change();
        });

        // 4. Initialisation des catégories standards globales (etablissement_id = null) utilisables par tous
        $now = now();

        $defaultRecettes = [
            'Vente d\'uniformes & tenues',
            'Macarons & livrets scolaires',
            'Cantine libre / Tickets repas',
            'Location de salle / espace',
            'Dons & subventions',
            'Duplicata & documents officiels',
        ];

        foreach ($defaultRecettes as $nom) {
            DB::table('categories_recettes_diverses')->insert([
                'etablissement_id' => null,
                'nom' => $nom,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $defaultBourses = [
            'Paiement en avance (avant rentrée)',
            'Fratrie / Famille nombreuse',
            'Enfant du personnel',
            'Excellence scolaire',
            'Sociale / Orphelin',
        ];

        foreach ($defaultBourses as $nom) {
            DB::table('categories_bourses_remises')->insert([
                'etablissement_id' => null,
                'nom' => $nom,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories_bourses_remises');
        Schema::dropIfExists('categories_recettes_diverses');
    }
};

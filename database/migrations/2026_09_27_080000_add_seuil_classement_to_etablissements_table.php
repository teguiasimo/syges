<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant la configuration du seuil minimal d'éligibilité au classement par coefficients (MINESEC / Gesco).
 *
 * Permet d'éviter qu'un élève ayant composé dans un nombre marginal de matières (ex: 4 matières sur 12)
 * ne soit classé 1er de la classe avec une moyenne biaisée.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->boolean('seuil_classement_actif')->default(true)
                ->comment('Active ou désactive le filtre d\'éligibilité au classement par quota de coefficients');

            $table->string('mode_seuil_classement', 20)->default('pourcentage')
                ->comment('Mode de calcul du seuil : pourcentage ou valeur_fixe');

            $table->decimal('seuil_classement_valeur', 5, 2)->default(60.00)
                ->comment('Valeur du seuil (ex: 60.00 pour 60% du total des coefficients de la classe, ou 15.00 coeffs fixes)');
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn([
                'seuil_classement_actif',
                'mode_seuil_classement',
                'seuil_classement_valeur',
            ]);
        });
    }
};

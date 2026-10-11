<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour la Gestion Complète des Finances : Dépenses & Charges (Phase 1).
 *
 * Implémente le suivi des sorties de caisse et de trésorerie (Aurore & SAGEES) :
 * - Catégorisation budgétaire des dépenses (fournitures, charges, salaires, maintenance).
 * - Fiche de décharge et bon de décaissement officiel pour chaque sortie de caisse.
 * - Suivi du solde net (Recettes vs Dépenses) par caisse et par établissement.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('depenses');
        Schema::dropIfExists('categories_depenses');

        // 1. Table des catégories de dépenses / charges
        Schema::create('categories_depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete()
                ->comment('Null si catégorie standard globale');
            $table->string('code', 50);
            $table->string('nom', 150);
            $table->string('description', 500)->nullable();
            $table->string('couleur', 25)->default('#64748b');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['etablissement_id', 'is_active'], 'cat_dep_etab_act_idx');
        });

        // 2. Table des dépenses / décaissements de caisse
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('caisse_id')->constrained('caisses')->cascadeOnDelete();
            $table->foreignId('categorie_depense_id')->constrained('categories_depenses')->cascadeOnDelete();

            $table->string('numero_piece', 50)->comment('Ex: DEP-2026/0001');
            $table->string('beneficiaire', 150)->comment('Nom du prestataire, employé ou bénéficiaire');
            $table->decimal('montant', 12, 2)->comment('Montant décaissé en FCFA');
            $table->date('date_depense');
            $table->text('motif')->comment('Objet détaillé de la dépense');
            $table->enum('mode_reglement', ['especes', 'virement', 'cheque', 'orange_money', 'mtn_momo'])->default('especes');
            $table->string('reference_paiement', 100)->nullable()->comment('N° chèque, réf transaction');
            $table->string('justificatif_path', 255)->nullable()->comment('Facture scannée');
            $table->enum('statut', ['validee', 'en_attente', 'annulee'])->default('validee');

            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['etablissement_id', 'annee_scolaire_id', 'statut'], 'dep_etab_annee_stat_idx');
            $table->index(['caisse_id', 'date_depense'], 'dep_caisse_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depenses');
        Schema::dropIfExists('categories_depenses');
    }
};

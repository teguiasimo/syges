<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration d'isolation multi-établissements (Multi-Tenant SaaS) pour la table inscriptions.
 *
 * Cette migration ajoute la colonne `etablissement_id` sur la table `inscriptions`
 * afin de garantir un partitionnement direct, strict et étanche des effectifs scolaires annuels
 * par campus (Maternelle, Primaire, Collège Bilingue, Lycée Général, etc.).
 *
 * Avantages majeurs :
 * 1. Élimination des jointures récursives lentes (whereHas('classe.niveau.cycle.section')).
 * 2. Isolation totale des listes de classe, certificats de scolarité, bulletins et dettes par campus.
 * 3. Indexation composite optimisée pour les requêtes de scolarité annuelle.
 */
return new class extends Migration
{
    /**
     * Exécute les modifications de schéma pour la table inscriptions.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            // Clé étrangère directe vers l'établissement scolaire
            $table->foreignId('etablissement_id')
                ->nullable()
                ->after('classe_id')
                ->constrained('etablissements')
                ->cascadeOnDelete();

            // Index composite de haute performance pour le filtrage annuel des effectifs
            $table->index(['etablissement_id', 'annee_scolaire_id', 'statut'], 'idx_inscriptions_etab_annee_statut');
        });

        // Rétro-remplissage (backfill) automatique pour toute inscription existante via sa classe rattachée
        DB::statement("
            UPDATE inscriptions
            SET etablissement_id = (
                SELECT etablissement_id FROM classes WHERE classes.id = inscriptions.classe_id
            )
            WHERE etablissement_id IS NULL AND classe_id IS NOT NULL
        ");
    }

    /**
     * Annule les modifications de schéma de la table inscriptions.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_inscriptions_etab_annee_statut');
            $table->dropForeign(['etablissement_id']);
            $table->dropColumn('etablissement_id');
        });
    }
};

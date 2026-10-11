<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 11 : Gestion de la Caisse & Paiements (Finance & Comptabilité Scolaire).
 * 
 * Crée les tables nécessaires à la gestion financière conformément au cahier des charges Gesco V1.1 :
 * - types_frais : Référentiel des types de frais (Scolarité, APEE, Cantine, etc.)
 * - frais_scolaires : Grille tarifaire par niveau, série et tranche d'échéance
 * - comptes_financiers_eleves : Compte courant de chaque élève pour l'année scolaire
 * - clotures_caisse : Journal et verrouillage journalier des écritures de caisse
 * - versements_caisse : Encaissements physiques (Espèces, Chèque, Virement, MoMo manuel)
 * - ventilation_versements : Décomposition d'un encaissement par tranche ou nature de frais
 * - Ajout des options de verrou de solvabilité et d'escompte sur la table etablissements
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
        // 1. Référentiel des types de frais
        Schema::create('types_frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('code', 50); // ex: 'INSCRIPTION', 'SCOLARITE', 'APEE', 'CANTINE', 'TRANSPORT'
            $table->string('libelle', 150);
            $table->text('description')->nullable();
            $table->boolean('obligatoire')->default(true);
            $table->enum('periodicite', ['annuel', 'trimestriel', 'mensuel', 'ponctuel'])->default('annuel');
            $table->decimal('montant_defaut', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['etablissement_id', 'code']);
        });

        // 2. Grille tarifaire (Matrice Niveau / Série / Tranches)
        Schema::create('frais_scolaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->string('serie', 50)->nullable(); // Null = applicable à toutes les séries
            $table->foreignId('type_frais_id')->constrained('types_frais')->cascadeOnDelete();
            $table->unsignedTinyInteger('tranche_numero')->default(1);
            $table->string('libelle_tranche', 150)->nullable(); // ex: 'Tranche 1 - Inscription'
            $table->decimal('montant', 12, 2);
            $table->date('date_limite')->nullable(); // Échéance exigible
            $table->timestamps();

            $table->index(['annee_scolaire_id', 'etablissement_id', 'niveau_id'], 'frais_scolaires_query_idx');
        });

        // 3. Comptes financiers élèves (Compte courant scolaire)
        Schema::create('comptes_financiers_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->unique()->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->decimal('debit_initial', 12, 2)->default(0);
            $table->decimal('arrieres_anterieurs', 12, 2)->default(0)->comment('Dette ou arriérés de scolarité reportés manuellement d\'une année antérieure');
            $table->decimal('remise_bourse', 12, 2)->default(0);
            $table->decimal('montant_net_exigible', 12, 2)->default(0);
            $table->decimal('total_paye', 12, 2)->default(0);
            $table->decimal('solde_restant', 12, 2)->default(0);
            $table->enum('statut_financier', ['insolvable', 'en_regle_tranche_1', 'en_regle_tranche_2', 'solde'])->default('insolvable');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['etablissement_id', 'annee_scolaire_id', 'statut_financier'], 'comptes_fin_status_idx');
        });

        // 4. Clôtures de caisse journalières
        Schema::create('clotures_caisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->date('date_cloture');
            $table->decimal('total_especes', 12, 2)->default(0);
            $table->decimal('total_cheques', 12, 2)->default(0);
            $table->decimal('total_virements', 12, 2)->default(0);
            $table->decimal('total_mobile_money', 12, 2)->default(0);
            $table->decimal('total_general', 12, 2)->default(0);
            $table->unsignedInteger('nombre_transactions')->default(0);
            $table->enum('statut', ['ouverte', 'cloturee'])->default('cloturee');
            $table->text('observations')->nullable();
            $table->timestamp('cloture_a')->nullable();
            $table->foreignId('verifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['etablissement_id', 'date_cloture', 'personnel_id'], 'clotures_caisse_unique_idx');
        });

        // 5. Versements de caisse (Encaissements au guichet)
        Schema::create('versements_caisse', function (Blueprint $table) {
            $table->id();
            $table->string('reference_recu', 50)->unique(); // Ex: REC-2026-000001
            $table->foreignId('compte_financier_id')->constrained('comptes_financiers_eleves')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->foreignId('cloture_caisse_id')->nullable()->constrained('clotures_caisse')->nullOnDelete();
            $table->dateTime('date_versement');
            $table->decimal('montant_total', 12, 2);
            $table->enum('mode_paiement', ['especes', 'cheque', 'virement', 'mobile_money_manuel'])->default('especes');
            $table->string('reference_paiement', 100)->nullable(); // Numéro chèque, bordereau ou ID MoMo
            $table->string('recu_par', 150)->nullable(); // Nom de l'agent qui encaisse
            $table->string('payeur_nom', 150)->nullable();
            $table->string('payeur_telephone', 50)->nullable();
            $table->enum('statut', ['valide', 'annule'])->default('valide');
            $table->text('motif_annulation')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annule_le')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['etablissement_id', 'date_versement', 'statut'], 'versements_query_idx');
        });

        // 6. Ventilation détaillée des versements
        Schema::create('ventilation_versements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('versement_id')->constrained('versements_caisse')->cascadeOnDelete();
            $table->foreignId('type_frais_id')->nullable()->constrained('types_frais')->nullOnDelete();
            $table->foreignId('frais_scolaire_id')->nullable()->constrained('frais_scolaires')->nullOnDelete();
            $table->string('libelle', 150); // ex: 'Scolarité Tranche 1'
            $table->decimal('montant', 12, 2);
            $table->timestamps();
        });

        // 7. Paramètres d'établissement pour la solvabilité et le bonus
        Schema::table('etablissements', function (Blueprint $table) {
            $table->boolean('verrou_solvabilite_actif')->default(false)->after('ordre_enseignement');
            $table->boolean('bonus_paiement_anticipe_actif')->default(false)->after('verrou_solvabilite_actif');
            $table->decimal('montant_bonus_anticipe', 12, 2)->default(0)->after('bonus_paiement_anticipe_actif');
            $table->date('date_limite_bonus')->nullable()->after('montant_bonus_anticipe');
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn([
                'verrou_solvabilite_actif',
                'bonus_paiement_anticipe_actif',
                'montant_bonus_anticipe',
                'date_limite_bonus',
            ]);
        });

        Schema::dropIfExists('ventilation_versements');
        Schema::dropIfExists('versements_caisse');
        Schema::dropIfExists('clotures_caisse');
        Schema::dropIfExists('comptes_financiers_eleves');
        Schema::dropIfExists('frais_scolaires');
        Schema::dropIfExists('types_frais');
    }
};

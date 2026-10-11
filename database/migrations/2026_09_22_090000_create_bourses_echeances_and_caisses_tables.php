<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour les fonctionnalités financières avancées inspirées du standard Aurore (Phase 1).
 *
 * Crée les structures suivantes :
 * - caisses : Gestion des caisses multiples (Caisse Principale, Cantine, Activités, etc.)
 * - reglements_divers : Encaissements libres et recettes diverses non liées aux élèves
 * - bourses_remises : Référentiel des bourses d'études et modèles de remises (par niveau ou profil)
 * - eleve_bourses_remises : Attribution des bourses et remises aux comptes scolaires des élèves
 * - modeles_echeances & echeances_tranches : Échéanciers calendaires stricts de paiement
 * - penalites_configurations & penalites_eleves : Paramétrage et calcul des pénalités de retard
 * - Mise à jour des tables versements_caisse et clotures_caisse pour rattachement à une caisse spécifique
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
        // 1. Table des caisses multiples
        Schema::create('caisses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('code', 50); // ex: 'CAISSE-PRIN', 'CAISSE-CANT', 'CAISSE-ANNEXE'
            $table->string('nom', 150);
            $table->decimal('solde_initial', 12, 2)->default(0);
            $table->foreignId('responsable_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->boolean('is_principale')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['etablissement_id', 'code']);
        });

        // 2. Création d'une caisse principale par défaut pour les établissements existants
        $etablissements = DB::table('etablissements')->get();
        foreach ($etablissements as $etab) {
            DB::table('caisses')->insert([
                'etablissement_id' => $etab->id,
                'code' => 'CAISSE-PRIN',
                'nom' => 'Caisse Principale',
                'solde_initial' => 0,
                'is_principale' => true,
                'is_active' => true,
                'description' => 'Caisse centrale d\'encaissement de l\'établissement',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Liaison de versements_caisse et clotures_caisse avec la caisse
        Schema::table('versements_caisse', function (Blueprint $table) {
            $table->foreignId('caisse_id')->nullable()->after('etablissement_id')->constrained('caisses')->nullOnDelete();
        });

        Schema::table('clotures_caisse', function (Blueprint $table) {
            $table->foreignId('caisse_id')->nullable()->after('etablissement_id')->constrained('caisses')->nullOnDelete();
        });

        // Mise à jour des versements existants vers la caisse principale de leur établissement
        $caissesPrincipales = DB::table('caisses')->where('is_principale', true)->get();
        foreach ($caissesPrincipales as $cp) {
            DB::table('versements_caisse')
                ->where('etablissement_id', $cp->etablissement_id)
                ->whereNull('caisse_id')
                ->update(['caisse_id' => $cp->id]);

            DB::table('clotures_caisse')
                ->where('etablissement_id', $cp->etablissement_id)
                ->whereNull('caisse_id')
                ->update(['caisse_id' => $cp->id]);
        }

        // 4. Table des règlements divers (recettes hors scolarité élève)
        Schema::create('reglements_divers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('caisse_id')->constrained('caisses')->cascadeOnDelete();
            $table->string('reference_recu', 50)->unique();
            $table->dateTime('date_reglement');
            $table->string('payeur_nom', 150);
            $table->string('payeur_telephone', 50)->nullable();
            $table->string('categorie', 50)->default('autre'); // 'uniformes', 'cantine_libre', 'macaron', 'location', 'don', 'autre'
            $table->string('libelle', 255);
            $table->decimal('montant', 12, 2);
            $table->enum('mode_paiement', ['especes', 'cheque', 'virement', 'mobile_money_manuel'])->default('especes');
            $table->string('reference_paiement', 100)->nullable();
            $table->foreignId('caissier_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('statut', ['valide', 'annule'])->default('valide');
            $table->text('motif_annulation')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['etablissement_id', 'date_reglement', 'statut'], 'reglements_divers_idx');
        });

        // 5. Référentiel des bourses et remises
        Schema::create('bourses_remises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annees_scolaires')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('nom', 150);
            $table->enum('type', ['bourse', 'remise'])->default('remise');
            $table->string('categorie', 50)->default('autre'); // 'excellence', 'sociale', 'fratrie', 'personnel', 'autre'
            $table->enum('mode_calcul', ['pourcentage', 'montant_fixe'])->default('pourcentage');
            $table->decimal('valeur', 12, 2); // ex: 20 (% de déduction) ou 50000 (FCFA déduits)
            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->nullOnDelete()->comment('Si applicable uniquement à un niveau scolaire');
            $table->foreignId('type_frais_id')->nullable()->constrained('types_frais')->nullOnDelete()->comment('Si applicable uniquement à un type de frais précis');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['etablissement_id', 'code']);
        });

        // 6. Attribution des bourses et remises aux élèves
        Schema::create('eleve_bourses_remises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_financier_id')->constrained('comptes_financiers_eleves')->cascadeOnDelete();
            $table->foreignId('bourse_remise_id')->constrained('bourses_remises')->cascadeOnDelete();
            $table->decimal('montant_calcule', 12, 2)->default(0);
            $table->date('date_attribution');
            $table->string('motif', 255)->nullable();
            $table->foreignId('attribue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['compte_financier_id', 'bourse_remise_id'], 'eleve_bourse_remise_unique');
        });

        // 7. Modèles d'échéances calendaires
        Schema::create('modeles_echeances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('echeances_tranches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modele_echeance_id')->constrained('modeles_echeances')->cascadeOnDelete();
            $table->unsignedTinyInteger('tranche_numero')->default(1);
            $table->string('libelle', 150);
            $table->date('date_limite');
            $table->decimal('pourcentage_exigible', 5, 2)->default(0)->comment('Ex: 40% pour Tranche 1, 70% pour Tranche 2, 100% pour Tranche 3');
            $table->timestamps();
        });

        // 8. Pénalités de retard
        Schema::create('penalites_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('nom', 150);
            $table->enum('mode_calcul', ['montant_fixe', 'pourcentage'])->default('montant_fixe');
            $table->decimal('valeur', 12, 2)->default(2000); // 2000 FCFA ou 5%
            $table->unsignedInteger('jours_grace')->default(5)->comment('Nombre de jours après l\'échéance avant application');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('penalites_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_financier_id')->constrained('comptes_financiers_eleves')->cascadeOnDelete();
            $table->foreignId('penalite_configuration_id')->nullable()->constrained('penalites_configurations')->nullOnDelete();
            $table->string('libelle', 150);
            $table->decimal('montant', 12, 2);
            $table->date('date_application');
            $table->enum('statut', ['a_payer', 'paye', 'annule'])->default('a_payer');
            $table->text('motif')->nullable();
            $table->foreignId('applique_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('penalites_eleves');
        Schema::dropIfExists('penalites_configurations');
        Schema::dropIfExists('echeances_tranches');
        Schema::dropIfExists('modeles_echeances');
        Schema::dropIfExists('eleve_bourses_remises');
        Schema::dropIfExists('bourses_remises');
        Schema::dropIfExists('reglements_divers');

        Schema::table('versements_caisse', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caisse_id');
        });

        Schema::table('clotures_caisse', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caisse_id');
        });

        Schema::dropIfExists('caisses');
    }
};

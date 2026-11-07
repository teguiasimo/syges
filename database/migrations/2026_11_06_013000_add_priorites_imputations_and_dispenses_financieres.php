<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant :
 * 1. L'ordre de priorité configurable des paiements sur `types_frais` et `frais_scolaires`.
 * 2. La cible d'imputation configurable des bourses et remises (`derniere_tranche`, `tranche_1`, `tranche_2`, etc.)
 *    sur `bourses_remises` et par surcharge individuelle sur `eleve_bourses_remises`.
 * 3. Le suivi comptable séparé des dispenses financières (`dispenses_financieres` + `comptes_financiers_eleves.total_dispense`)
 *    pour gérer proprement les élèves arrivant en cours d'année (2ème/3ème trimestre) ou exonérés d'une rubrique.
 */
return new class extends Migration
{
    /**
     * Exécute les modifications de schéma et initialise les priorités standards sur l'existant.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('types_frais', 'ordre_priorite')) {
            Schema::table('types_frais', function (Blueprint $table) {
                $table->unsignedSmallInteger('ordre_priorite')
                    ->default(10)
                    ->after('periodicite');
            });

            // Pré-initialisation intelligente des priorités sur les types de frais existants
            DB::table('types_frais')
                ->where(function ($q) {
                    $q->where('code', 'LIKE', '%APE%')
                        ->orWhere('libelle', 'LIKE', '%APE%');
                })
                ->update(['ordre_priorite' => 1]);

            DB::table('types_frais')
                ->where(function ($q) {
                    $q->where('code', 'LIKE', '%INSCR%')
                        ->orWhere('libelle', 'LIKE', '%Inscr%');
                })
                ->update(['ordre_priorite' => 2]);

            DB::table('types_frais')
                ->where(function ($q) {
                    $q->where('code', 'LIKE', '%SCOL%')
                        ->orWhere('code', 'LIKE', '%PENS%')
                        ->orWhere('libelle', 'LIKE', '%Scolarit%')
                        ->orWhere('libelle', 'LIKE', '%Pension%');
                })
                ->update(['ordre_priorite' => 3]);
        }

        if (! Schema::hasColumn('frais_scolaires', 'ordre_priorite')) {
            Schema::table('frais_scolaires', function (Blueprint $table) {
                $table->unsignedSmallInteger('ordre_priorite')
                    ->nullable()
                    ->after('tranche_numero');
            });
        }

        if (! Schema::hasColumn('bourses_remises', 'cible_imputation')) {
            Schema::table('bourses_remises', function (Blueprint $table) {
                $table->string('cible_imputation', 40)
                    ->default('derniere_tranche')
                    ->after('valeur');
            });
        }

        if (! Schema::hasColumn('eleve_bourses_remises', 'cible_imputation')) {
            Schema::table('eleve_bourses_remises', function (Blueprint $table) {
                $table->string('cible_imputation', 40)
                    ->nullable()
                    ->after('montant_calcule');
                $table->foreignId('frais_scolaire_id')
                    ->nullable()
                    ->after('cible_imputation')
                    ->constrained('frais_scolaires')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('comptes_financiers_eleves', 'total_dispense')) {
            Schema::table('comptes_financiers_eleves', function (Blueprint $table) {
                $table->decimal('total_dispense', 12, 2)
                    ->default(0)
                    ->after('debit_initial');
            });
        }

        if (! Schema::hasTable('dispenses_financieres')) {
            Schema::create('dispenses_financieres', function (Blueprint $table) {
                $table->id();
                $table->foreignId('compte_financier_id')
                    ->constrained('comptes_financiers_eleves')
                    ->cascadeOnDelete();
                $table->foreignId('frais_scolaire_id')
                    ->constrained('frais_scolaires')
                    ->cascadeOnDelete();
                $table->string('type_dispense', 20)->default('totale');
                $table->decimal('montant_dispense', 12, 2);
                $table->string('motif', 255);
                $table->date('date_dispense');
                $table->foreignId('accorde_par')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(['compte_financier_id', 'frais_scolaire_id'], 'uniq_dispense_compte_frais');
            });
        }
    }

    /**
     * Annule les modifications de schéma.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispenses_financieres');

        if (Schema::hasColumn('comptes_financiers_eleves', 'total_dispense')) {
            Schema::table('comptes_financiers_eleves', function (Blueprint $table) {
                $table->dropColumn('total_dispense');
            });
        }

        if (Schema::hasColumn('eleve_bourses_remises', 'frais_scolaire_id')) {
            Schema::table('eleve_bourses_remises', function (Blueprint $table) {
                $table->dropConstrainedForeignId('frais_scolaire_id');
                $table->dropColumn('cible_imputation');
            });
        }

        if (Schema::hasColumn('bourses_remises', 'cible_imputation')) {
            Schema::table('bourses_remises', function (Blueprint $table) {
                $table->dropColumn('cible_imputation');
            });
        }

        if (Schema::hasColumn('frais_scolaires', 'ordre_priorite')) {
            Schema::table('frais_scolaires', function (Blueprint $table) {
                $table->dropColumn('ordre_priorite');
            });
        }

        if (Schema::hasColumn('types_frais', 'ordre_priorite')) {
            Schema::table('types_frais', function (Blueprint $table) {
                $table->dropColumn('ordre_priorite');
            });
        }
    }
};

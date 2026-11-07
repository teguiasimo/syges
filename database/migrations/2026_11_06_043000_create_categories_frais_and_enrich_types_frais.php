<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de création des Catégories de Frais (`categories_frais`) et d'enrichissement
 * du Catalogue des Frais (`types_frais`).
 *
 * Permet de regrouper les frais configurés (ex: Inscription SIL-CM2, Tranche 1 SIL-CM2, CEPE, Fête)
 * sous des catégories légères (Libellé + Description) et d'y porter directement la date butoir
 * afin de rattacher un même frais à plusieurs niveaux sans répétition de saisie.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations de schéma et initialise les catégories par défaut des établissements existants.
     */
    public function up(): void
    {
        if (! Schema::hasTable('categories_frais')) {
            Schema::create('categories_frais', function (Blueprint $table) {
                $table->id();
                $table->foreignId('etablissement_id')
                    ->constrained('etablissements')
                    ->cascadeOnDelete();
                $table->string('nom', 120);
                $table->string('description', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['etablissement_id', 'nom']);
            });
        }

        Schema::table('types_frais', function (Blueprint $table) {
            if (! Schema::hasColumn('types_frais', 'categorie_frais_id')) {
                $table->foreignId('categorie_frais_id')
                    ->nullable()
                    ->after('etablissement_id')
                    ->constrained('categories_frais')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('types_frais', 'date_limite_defaut')) {
                $table->date('date_limite_defaut')
                    ->nullable()
                    ->after('montant_defaut');
            }
        });

        // Initialisation des catégories standards pour les établissements déjà présents en base
        $now = now();
        $etablissementIds = DB::table('etablissements')->pluck('id');

        $categoriesStandards = [
            [
                'nom' => 'Scolarité',
                'description' => "Droits d'inscription, pension et tranches de scolarité.",
            ],
            [
                'nom' => "Frais d'Examen",
                'description' => 'Dossiers et droits des examens officiels (CEP/CEPE, BEPC, Probatoire, Baccalauréat).',
            ],
            [
                'nom' => 'Fête & Vie Scolaire',
                'description' => "Cotisations APEE, fête de fin d'année, activités culturelles et sportives.",
            ],
            [
                'nom' => 'Transport & Services Annexes',
                'description' => 'Prestations à la carte : bus scolaire, cantine, études surveillées, tenues.',
            ],
        ];

        foreach ($etablissementIds as $etabId) {
            $mapIds = [];
            foreach ($categoriesStandards as $cat) {
                $existingId = DB::table('categories_frais')
                    ->where('etablissement_id', $etabId)
                    ->where('nom', $cat['nom'])
                    ->value('id');

                if (! $existingId) {
                    $existingId = DB::table('categories_frais')->insertGetId([
                        'etablissement_id' => $etabId,
                        'nom' => $cat['nom'],
                        'description' => $cat['description'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $mapIds[$cat['nom']] = $existingId;
            }

            // Rattachement intelligent des types de frais déjà créés dans l'établissement
            $typesExistants = DB::table('types_frais')
                ->where('etablissement_id', $etabId)
                ->whereNull('categorie_frais_id')
                ->get(['id', 'code', 'libelle', 'obligatoire']);

            foreach ($typesExistants as $tf) {
                $cle = mb_strtoupper(($tf->code ?? '').' '.($tf->libelle ?? ''));

                if (! $tf->obligatoire || str_contains($cle, 'BUS') || str_contains($cle, 'TRANSP') || str_contains($cle, 'CANTIN') || str_contains($cle, 'INTERN')) {
                    $cible = $mapIds['Transport & Services Annexes'];
                } elseif (str_contains($cle, 'EXAM') || str_contains($cle, 'CEPE') || str_contains($cle, 'CEP') || str_contains($cle, 'BEPC') || str_contains($cle, 'PROB') || str_contains($cle, 'BAC')) {
                    $cible = $mapIds["Frais d'Examen"];
                } elseif (str_contains($cle, 'FETE') || str_contains($cle, 'FÊTE') || str_contains($cle, 'APE') || str_contains($cle, 'SPORT')) {
                    $cible = $mapIds['Fête & Vie Scolaire'];
                } else {
                    $cible = $mapIds['Scolarité'];
                }

                DB::table('types_frais')
                    ->where('id', $tf->id)
                    ->update(['categorie_frais_id' => $cible]);
            }
        }
    }

    /**
     * Annule les modifications de schéma.
     */
    public function down(): void
    {
        Schema::table('types_frais', function (Blueprint $table) {
            if (Schema::hasColumn('types_frais', 'categorie_frais_id')) {
                $table->dropConstrainedForeignId('categorie_frais_id');
            }
            if (Schema::hasColumn('types_frais', 'date_limite_defaut')) {
                $table->dropColumn('date_limite_defaut');
            }
        });

        Schema::dropIfExists('categories_frais');
    }
};

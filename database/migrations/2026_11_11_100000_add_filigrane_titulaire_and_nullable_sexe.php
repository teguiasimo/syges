<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ajoutant la prise en charge générique :
 * - du logo filigrane officiel (`logo_filigrane`) sur les institutions et établissements,
 * - du titre paramétrable du responsable pédagogique de classe (`titre_responsable_classe` / EN)
 *   pour supporter aussi bien les Maîtres/Maîtresses (MINEDUB) que les Professeurs Principaux (MINESEC),
 * - de l'état neutre nullable sur `eleves.sexe` pour l'enrôlement progressif sur le terrain
 *   sans fausser les statistiques Filles/Garçons.
 */
return new class extends Migration
{
    /**
     * Applique les évolutions de schéma en base de données.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            if (! Schema::hasColumn('institutions', 'logo_filigrane')) {
                $table->string('logo_filigrane', 255)
                    ->nullable()
                    ->after('logo')
                    ->comment('Chemin du logo dédié aux filigranes des documents officiels');
            }
        });

        Schema::table('etablissements', function (Blueprint $table) {
            if (! Schema::hasColumn('etablissements', 'logo_filigrane')) {
                $table->string('logo_filigrane', 255)
                    ->nullable()
                    ->after('logo')
                    ->comment('Chemin du logo filigrane propre à l\'établissement');
            }
            if (! Schema::hasColumn('etablissements', 'titre_responsable_classe')) {
                $table->string('titre_responsable_classe', 100)
                    ->nullable()
                    ->after('titre_chef_en')
                    ->comment('Libellé FR du titulaire de classe (ex: Le Maître / La Maîtresse, Le Professeur Principal)');
            }
            if (! Schema::hasColumn('etablissements', 'titre_responsable_classe_en')) {
                $table->string('titre_responsable_classe_en', 100)
                    ->nullable()
                    ->after('titre_responsable_classe')
                    ->comment('Libellé EN du titulaire de classe (ex: Class Teacher, Class Master)');
            }
        });

        Schema::table('eleves', function (Blueprint $table) {
            // Rend le sexe optionnel (nullable) pour éviter les valeurs fictives lors d'enrôlements partiels
            $table->string('sexe', 10)->nullable()->default(null)->change();
        });
    }

    /**
     * Annule les évolutions de schéma si un rollback est exécuté.
     *
     * @return void
     */
    public function down(): void
    {
        // Assainit les valeurs nulles avant de restaurer la contrainte non-nulle d'origine
        DB::table('eleves')->whereNull('sexe')->update(['sexe' => 'M']);

        Schema::table('eleves', function (Blueprint $table) {
            $table->enum('sexe', ['M', 'F'])->default('M')->change();
        });

        Schema::table('etablissements', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('etablissements', 'logo_filigrane') ? 'logo_filigrane' : null,
                Schema::hasColumn('etablissements', 'titre_responsable_classe') ? 'titre_responsable_classe' : null,
                Schema::hasColumn('etablissements', 'titre_responsable_classe_en') ? 'titre_responsable_classe_en' : null,
            ]));

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('institutions', function (Blueprint $table) {
            if (Schema::hasColumn('institutions', 'logo_filigrane')) {
                $table->dropColumn('logo_filigrane');
            }
        });
    }
};


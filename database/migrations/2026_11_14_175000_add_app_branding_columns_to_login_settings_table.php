<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute à la table `login_settings` les options d'identité visuelle partagée
 * après connexion (logo, titre et sous-titre de la barre latérale + synchronisation du favicon).
 */
return new class extends Migration
{
    /**
     * Exécute l'ajout des colonnes d'identité de l'application connectée.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('login_settings')) {
            return;
        }

        Schema::table('login_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('login_settings', 'apply_branding_to_app')) {
                $table->boolean('apply_branding_to_app')->default(false)->after('support_email');
            }
            if (! Schema::hasColumn('login_settings', 'app_brand_name')) {
                $table->string('app_brand_name', 80)->nullable()->after('apply_branding_to_app');
            }
            if (! Schema::hasColumn('login_settings', 'app_brand_subtitle')) {
                $table->string('app_brand_subtitle', 120)->nullable()->after('app_brand_name');
            }
            if (! Schema::hasColumn('login_settings', 'app_logo_path')) {
                $table->string('app_logo_path', 255)->nullable()->after('app_brand_subtitle');
            }
            if (! Schema::hasColumn('login_settings', 'sync_favicon')) {
                $table->boolean('sync_favicon')->default(true)->after('app_logo_path');
            }
        });
    }

    /**
     * Supprime les colonnes d'identité de l'application connectée en cas de rollback.
     *
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('login_settings')) {
            return;
        }

        Schema::table('login_settings', function (Blueprint $table) {
            foreach (['sync_favicon', 'app_logo_path', 'app_brand_subtitle', 'app_brand_name', 'apply_branding_to_app'] as $column) {
                if (Schema::hasColumn('login_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

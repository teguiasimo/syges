<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration créant les tables de configuration dynamique de la page de connexion :
 * - `login_settings` : paramétrage global (mode personnalisé/défaut, vitesse de défilement, identité visuelle et contacts d'aide).
 * - `login_slides` : diapositives personnalisées du carrousel (photo, titre, message, ordre et état d'activation).
 */
return new class extends Migration
{
    /**
     * Exécute la création des tables d'apparence et de diaporama de connexion.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('login_settings')) {
            Schema::create('login_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('use_custom_slides')->default(false);
                $table->unsignedSmallInteger('rotation_interval_seconds')->default(4);
                $table->string('brand_name', 120)->nullable();
                $table->string('brand_subtitle', 160)->nullable();
                $table->string('logo_path', 255)->nullable();
                $table->string('footer_left', 160)->nullable();
                $table->string('footer_right', 80)->nullable();
                $table->string('support_label', 80)->nullable();
                $table->string('support_phone', 40)->nullable();
                $table->string('support_email', 120)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('login_slides')) {
            Schema::create('login_slides', function (Blueprint $table) {
                $table->id();
                $table->string('titre', 120);
                $table->string('message', 255)->nullable();
                $table->string('image_path', 255);
                $table->unsignedInteger('ordre')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'ordre'], 'idx_login_slides_active_ordre');
            });
        }
    }

    /**
     * Annule la création des tables d'apparence et de diaporama de connexion.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('login_slides');
        Schema::dropIfExists('login_settings');
    }
};

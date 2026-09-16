<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 14 : Communication & Notifications SMS (Gesco V1.1).
 *
 * Crée :
 * - `sms_configurations` : Paramétrage des passerelles SMS (Orange, MTN, Africa's Talking) avec Sender ID (max 11 car.) et gestion du solde.
 * - `sms_campagnes` : Campagnes d'envoi groupé par établissement, cycle, niveau, classe ou ciblage des parents insolvables.
 * - `historique_sms` : Registre horodaté et traçabilité exhaustive de tous les SMS émis (automatiques ou manuels) avec décompte des crédits et statut de distribution.
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
        // 1. Passerelle & Configuration SMS (Gesco 14.1)
        Schema::create('sms_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('fournisseur', 50)->default('africas_talking');
            $table->string('api_url', 255)->nullable();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->string('sender_id', 11)->default('SYGES');
            $table->unsignedInteger('solde_credits')->default(100);
            $table->unsignedInteger('seuil_alerte_solde')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('etablissement_id', 'sms_config_etab_unique');
        });

        // 2. Campagnes SMS de Masse (Gesco 14.2.B)
        Schema::create('sms_campagnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('titre', 150);
            $table->text('message_brut');
            $table->text('message_sanitise'); // Conforme norme GSM 7-bit (sans accents)
            $table->enum('cible_type', ['tous', 'section', 'niveau', 'classe', 'insolvables'])->default('tous');
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->unsignedInteger('nombre_destinataires')->default(0);
            $table->unsignedInteger('total_credits')->default(0);
            $table->enum('statut', ['en_attente', 'en_cours', 'termine', 'echec'])->default('termine');
            $table->foreignId('lance_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Traçabilité & Historique Exhaustif des SMS (Gesco 14.3)
        Schema::create('historique_sms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('campagne_id')->nullable()->constrained('sms_campagnes')->cascadeOnDelete();
            $table->foreignId('inscription_id')->nullable()->constrained('inscriptions')->nullOnDelete();
            $table->enum('type_notification', [
                'alerte_absence',
                'alerte_sanction',
                'rappel_paiement',
                'confirmation_recu',
                'campagne_masse',
                'personnalise',
            ])->default('campagne_masse');
            $table->string('destinataire_nom', 150)->nullable();
            $table->string('destinataire_telephone', 30);
            $table->text('message');
            $table->enum('statut_distribution', ['en_attente', 'envoye', 'echec'])->default('envoye');
            $table->unsignedInteger('credits_consommes')->default(1);
            $table->string('reponse_fournisseur', 255)->nullable();
            $table->timestamps();

            $table->index(['etablissement_id', 'type_notification'], 'idx_hist_etab_type');
            $table->index(['destinataire_telephone'], 'idx_hist_tel');
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_sms');
        Schema::dropIfExists('sms_campagnes');
        Schema::dropIfExists('sms_configurations');
    }
};

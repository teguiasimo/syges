<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Module 12 : Absences, Retards & Discipline (Gesco V1.1).
 *
 * Crée les tables nécessaires à la gestion granulaire de la discipline :
 * - assiduites : Enregistrement quotidien des absences et retards horodatés par matière.
 * - sanctions_disciplinaires : Registre officiel des sanctions prononcées (Avertissements, Blâmes, Retenues, Exclusions).
 * - Ajout du seuil d'heures d'absences non justifiées pour déchéance des mentions sur la table etablissements.
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
        // 1. Table des événements d'assiduité (Absences et Retards)
        Schema::create('assiduites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            $table->enum('type_evenement', ['absence', 'retard'])->default('absence');
            $table->date('date_evenement');
            $table->decimal('nombre_heures', 4, 1)->default(1.0);
            $table->foreignId('matiere_id')->nullable()->constrained('matieres')->nullOnDelete();
            $table->enum('statut_justification', ['justifiee', 'non_justifiee'])->default('non_justifiee');
            $table->text('motif_justification')->nullable();
            $table->string('justificatif_path')->nullable();
            $table->foreignId('signale_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inscription_id', 'periode_id', 'type_evenement'], 'assiduites_inscr_per_type_idx');
            $table->index(['date_evenement', 'statut_justification'], 'assiduites_date_statut_idx');
        });

        // 2. Table des sanctions disciplinaires
        Schema::create('sanctions_disciplinaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            $table->date('date_decision');
            $table->enum('type_sanction', [
                'avertissement',
                'blame',
                'retenue',
                'exclusion_temporaire',
                'exclusion_definitive',
            ])->default('avertissement');
            $table->text('motif');
            $table->unsignedInteger('nombre_jours_ou_heures')->default(1);
            $table->string('decide_par', 100)->default('Censeur');
            $table->string('document_notification_path')->nullable();
            $table->boolean('is_notified_parent')->default(false);
            $table->timestamps();

            $table->index(['inscription_id', 'periode_id', 'type_sanction'], 'sanctions_inscr_per_type_idx');
        });

        // 3. Paramètre seuil de déchéance des mentions au niveau établissement (défaut 40h MINESEC Cameroun)
        Schema::table('etablissements', function (Blueprint $table) {
            $table->unsignedTinyInteger('seuil_absences_decheance_mentions')->default(40)->after('type_calcul_trimestre');
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
            $table->dropColumn('seuil_absences_decheance_mentions');
        });

        Schema::dropIfExists('sanctions_disciplinaires');
        Schema::dropIfExists('assiduites');
    }
};

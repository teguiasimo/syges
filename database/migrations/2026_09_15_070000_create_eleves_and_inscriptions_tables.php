<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour la création des tables du Module 5 : Élèves & Inscriptions.
 * Conforme au Cahier des Charges Gesco V1.1 (Base Élève Globale, Transferts)
 * et à la documentation SAGEES Schooling (format matricule, filiation, urgences).
 *
 * Inclut directement etablissement_id sur inscriptions pour le cloisonnement multi-campus hermétique.
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
        // 1. Table eleves : Base Élève Globale et Pérenne
        Schema::create('eleves', function (Blueprint $table) {
            $table->id();

            // Identifiants
            $table->string('matricule', 50)->unique()->comment('Matricule interne unique généré ou attribué');
            $table->string('matricule_national', 50)->nullable()->index()->comment('Identifiant national MINESEC/MINEDUB');

            // État Civil
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->enum('sexe', ['M', 'F'])->default('M');
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('nationalite', 80)->default('CAMEROUNAISE');
            $table->string('photo_url')->nullable()->comment('Chemin vers la photo d\'identité');

            // Filiation et Parents / Tuteurs (Gesco 5.1 & 16.1)
            $table->string('nom_pere')->nullable()->comment('Nom complet du père');
            $table->string('nom_mere')->nullable()->comment('Nom complet de la mère');
            $table->string('nom_tuteur')->nullable()->comment('Nom complet du tuteur légal');
            $table->string('telephone_parent', 50)->nullable()->index()->comment('Téléphone principal tuteur (identifiant SMS & portail)');
            $table->string('email_parent')->nullable()->comment('Email du parent ou tuteur');
            $table->string('adresse')->nullable()->comment('Adresse physique ou quartier de résidence');

            // Statut global de la fiche
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // 2. Table inscriptions : Liaison annuelle Élève <-> Classe <-> Année Scolaire avec etablissement_id direct
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();

            // Clés étrangères
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();

            // Régime et Type d'inscription
            $table->enum('type_inscription', ['nouveau', 'reinscription'])->default('nouveau');
            $table->boolean('is_redoublant')->default(false);

            // Statuts de l'inscription (Gesco 5.3)
            $table->enum('statut', [
                'en_attente',   // Dossier initié non validé
                'validee',      // Inscription officielle active
                'suspendue',    // Suspendue administrativement ou scolairement
                'exclu',        // Exclu en cours d'année
                'transfere',    // Transféré vers un autre établissement / classe
            ])->default('validee');

            $table->date('date_inscription')->default(now()->toDateString());

            // Informations de mouvement et transfert (Gesco V1.1 cadrage)
            $table->string('motif_sortie')->nullable()->comment('Motif de départ ou détails du transfert');
            $table->string('etablissement_provenance')->nullable()->comment('Établissement d\'origine si transfert externe entrant');
            $table->text('observations')->nullable();

            $table->timestamps();

            // Index de performance et recherche
            $table->index(['etablissement_id', 'annee_scolaire_id', 'statut'], 'idx_inscriptions_etab_annee_statut');
            $table->index(['eleve_id', 'annee_scolaire_id']);
            $table->index(['classe_id', 'annee_scolaire_id']);
            $table->index(['statut']);
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
        Schema::dropIfExists('eleves');
    }
};

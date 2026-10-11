<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour le Centre d'Archivage Administratif et les Masques de Numérotation.
 * Conforme aux sections 3.15, 3.27.8 et 3.28.3 du Manuel Aurore et aux exigences Gesco V1.1.
 *
 * 1. Table `archives_administratives` : Traçabilité et historique complet des élèves et personnels sortants
 *    avec préservation absolue des données historiques (notes, bulletins, finances).
 * 2. Table `configurations_numerotation` : Personnalisation des souches, radicaux et masques séquentiels
 *    pour matricules, reçus et certificats administratifs.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        // 1. Table des Archives Administratives (Élèves & Personnels sortants)
        Schema::create('archives_administratives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annees_scolaires')->nullOnDelete();

            // Type d'entité archivée
            $table->enum('type_entite', ['eleve', 'personnel'])->comment('Catégorie d\'archive : élève ou personnel');
            $table->foreignId('eleve_id')->nullable()->constrained('eleves')->nullOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();

            // Identité figée pour conservation historique pérenne
            $table->string('matricule', 60);
            $table->string('nom_complet', 200);
            $table->string('derniere_classe_ou_fonction', 150)->nullable()->comment('Ex: Terminale C ou Censeur');

            // Motif et date de départ
            $table->enum('motif', [
                'diplome',              // Fin de scolarité / Diplômé
                'transfert_radiation',  // Changement d'établissement / Radiation
                'exclusion_definitive', // Exclusion disciplinaire
                'abandon',              // Abandon de scolarité
                'demission',            // Démission volontaire
                'retraite',             // Départ à la retraite
                'fin_contrat',          // Fin de contrat ou vacation
                'licenciement',         // Licenciement
                'mutation',             // Mutation vers un autre établissement public/privé
                'deces',                // Décès
                'autre',                // Autre motif
            ])->comment('Motif officiel de la mise en archive');

            $table->date('date_archivage')->comment('Date effective de sortie de l\'établissement');
            $table->string('destination')->nullable()->comment('Nouvel établissement d\'accueil ou nouvelle entreprise');
            $table->text('observations')->nullable()->comment('Circonstances ou références du procès-verbal');

            // Statut de l'archive
            $table->enum('statut', ['archive', 'restaure'])->default('archive');
            $table->foreignId('archive_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_restauration')->nullable();
            $table->foreignId('restaure_par_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Index
            $table->index(['etablissement_id', 'type_entite', 'statut'], 'idx_archives_etab_type_statut');
            $table->index(['matricule']);
            $table->index(['date_archivage']);
        });

        // 2. Table des Masques et Souches de Numérotation (Aurore §3.15)
        Schema::create('configurations_numerotation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            
            // Type de document numéroté
            $table->enum('type_document', [
                'matricule_eleve',
                'matricule_personnel',
                'recu_scolarite',
                'recu_divers',
                'certificat_scolarite',
                'certificat_radiation',
            ]);

            $table->string('prefixe', 20)->default('')->comment('Préfixe court (ex: GS, MAT, PERS, REC)');
            $table->string('format_masque', 80)->default('{PREFIX}-{ANNEE}-{NUM:4}')->comment('Ex: {PREFIX}-{ANNEE}-{NUM:4} ou {PREFIX}/{ETAB}/{NUM:5}');
            $table->unsignedInteger('compteur_actuel')->default(1)->comment('Dernier index numérique généré');
            $table->string('annee_reference', 10)->nullable()->comment('Dernière année calendaire ou scolaire de réinitialisation');
            $table->boolean('reinitialiser_annuellement')->default(true)->comment('Réinitialise le compteur à 1 chaque nouvelle année');

            $table->timestamps();

            $table->unique(['etablissement_id', 'type_document'], 'unique_etab_type_numerotation');
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configurations_numerotation');
        Schema::dropIfExists('archives_administratives');
    }
};

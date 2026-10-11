<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour la création des tables du Module 8 : Personnel & Ressources Humaines (RH).
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 8.1, 8.2, 8.3).
 * 
 * Crée :
 * 1. La table `departements` : Structuration des enseignants par famille de disciplines.
 * 2. La table `personnels` : Registre unique et centralisé des employés avec liaison optionnelle vers `users`.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations pour créer les tables départements et personnels.
     *
     * @return void
     */
    public function up(): void
    {
        // 1. Table des départements pédagogiques (Gesco 8.2)
        Schema::create('departements', function (Blueprint $table) {
            $table->id();

            // Rattachement à l'institution globale
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();

            // Identité du département
            $table->string('nom')->comment('Nom du département, ex: Mathématiques');
            $table->string('code', 20)->nullable()->comment('Code court du département, ex: MATH');

            // Animateur pédagogique / Chef de département (clé étrangère nullable ajoutée ultérieurement)
            $table->unsignedBigInteger('chef_departement_id')->nullable()->comment('Enseignant désigné chef de département');

            // Statut d'activité
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // 2. Table du personnel & fiches administratives (Gesco 8.1 & 8.3)
        Schema::create('personnels', function (Blueprint $table) {
            $table->id();

            // Identifiants administratifs & Liaison système
            $table->string('matricule', 50)->unique()->comment('Matricule interne généré séquentiellement, ex: PERS-2026-0001');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Compte d\'accès utilisateur lié (optionnel)');
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete()->comment('Établissement / campus d\'affectation principale');
            $table->foreignId('departement_id')->nullable()->constrained('departements')->nullOnDelete()->comment('Département pédagogique de rattachement');

            // Informations Civiles (Gesco 8.1.4)
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->enum('sexe', ['M', 'F'])->default('M');
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('telephone', 50)->comment('Téléphone principal');
            $table->string('telephone_secondaire', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->string('photo_url')->nullable();

            // Statut Professionnel & RH (Gesco 8.1.5)
            $table->string('fonction_principale', 80)->default('Enseignant')->comment('Enseignant, Censeur, Intendant, Économe, Secrétaire, Surveillant Général, Gardien, etc.');
            $table->string('type_contrat', 60)->default('Permanent')->comment('Permanent, Affecté de l\'État, CDD, Vacataire, Stagiaire');
            $table->date('date_embauche')->default(now()->toDateString());
            $table->string('statut', 40)->default('Actif')->comment('Actif, En congé, Démissionnaire, Retraité, Suspendu, Licencié');

            // Données Légales & RH (Gesco 8.1.6)
            $table->string('numero_cnps', 60)->nullable()->comment('Numéro de sécurité sociale / CNPS');
            $table->string('diplome_plus_eleve', 120)->nullable()->comment('Dernier diplôme académique ou professionnel obtenu');
            $table->text('observations')->nullable();

            // Profil Pédagogique Spécifique Enseignant (Gesco 8.3)
            $table->boolean('is_enseignant')->default(true)->comment('Vrai si le membre du personnel dispense des cours');
            $table->string('specialite', 120)->nullable()->comment('Discipline d\'origine ou domaine d\'expertise');
            $table->decimal('volume_horaire_hebdo', 5, 2)->default(18.00)->comment('Quota contractuel hebdomadaire en heures (ex: 18h)');
            $table->decimal('taux_horaire_vacation', 10, 2)->nullable()->comment('Taux horaire si personnel vacataire');

            $table->softDeletes();
            $table->timestamps();

            // Index pour l'optimisation des recherches et jointures
            $table->index(['etablissement_id', 'fonction_principale']);
            $table->index(['statut']);
            $table->index(['is_enseignant']);
            $table->index(['departement_id']);
        });

        // 3. Ajout de la contrainte de clé étrangère chef_departement_id sur la table departements
        Schema::table('departements', function (Blueprint $table) {
            $table->foreign('chef_departement_id')
                ->references('id')
                ->on('personnels')
                ->nullOnDelete();
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->dropForeign(['chef_departement_id']);
        });

        Schema::dropIfExists('personnels');
        Schema::dropIfExists('departements');
    }
};

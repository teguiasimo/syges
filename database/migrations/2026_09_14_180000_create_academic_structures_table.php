<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de création de l'arborescence pédagogique et institutionnelle de SYGES.
 *
 * Crée les tables hiérarchiques :
 * 1. institutions (organisme faîtier unique avec sigle)
 * 2. etablissements (campus et écoles physiques rattachés)
 * 3. sections (sous-systèmes linguistiques et filières : FR, EN, Technique avec unicité par campus)
 * 4. cycles (grandes étapes académiques avec etablissement_id direct et unicité par campus)
 * 5. niveaux (paliers d'études ordonnés avec etablissement_id direct et unicité par campus)
 * 6. classes (groupes et salles réelles d'élèves avec etablissement_id direct)
 * 7. Clé étrangère etablissement_id sur la table users.
 */
return new class extends Migration
{
    /**
     * Exécute les migrations pour créer les tables hiérarchiques.
     */
    public function up(): void
    {
        // 1. Table des Institutions (Organisme faîtier)
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Code court ou acronyme unique (ex: GSB-STP)');
            $table->string('nom', 150)->comment('Nom officiel de l\'institution en français');
            $table->string('sigle', 50)->nullable()->comment('Sigle institutionnel officiel (ex: GSJF, MINESEC)');
            $table->string('nom_en', 150)->nullable()->comment('Nom officiel en anglais');
            $table->string('devise', 150)->nullable()->comment('Devise officielle en français');
            $table->string('devise_en', 150)->nullable()->comment('Devise officielle en anglais');
            $table->string('logo', 255)->nullable()->comment('Chemin relatif du logo institutionnel');
            $table->string('email', 100)->nullable()->comment('Adresse email de contact');
            $table->string('telephone_1', 30)->nullable()->comment('Numéro de téléphone principal');
            $table->string('telephone_2', 30)->nullable()->comment('Numéro de téléphone secondaire');
            $table->string('boite_postale', 50)->nullable()->comment('Boîte postale (BP / P.O. Box)');
            $table->string('site_web', 150)->nullable()->comment('Site web officiel');
            $table->string('region', 100)->nullable()->comment('Région administrative');
            $table->string('region_en', 100)->nullable()->comment('Région en anglais');
            $table->string('departement', 100)->nullable()->comment('Département administratif');
            $table->string('departement_en', 100)->nullable()->comment('Département en anglais');
            $table->string('arrondissement', 100)->nullable()->comment('Arrondissement');
            $table->string('arrondissement_en', 100)->nullable()->comment('Arrondissement en anglais');
            $table->string('ville', 100)->nullable()->comment('Ville du siège');
            $table->string('delegation_regionale', 150)->nullable()->comment('Délégation régionale de tutelle');
            $table->string('delegation_regionale_en', 150)->nullable()->comment('Délégation régionale en anglais');
            $table->string('delegation_departementale', 150)->nullable()->comment('Délégation départementale de tutelle');
            $table->string('delegation_departementale_en', 150)->nullable()->comment('Délégation départementale en anglais');
            $table->timestamps();
        });

        // 2. Table des Établissements (Campus scolaires)
        Schema::create('etablissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('code', 30)->unique()->comment('Code officiel MINESEC/MINEDUB (ex: EPSJ, LYC-002)');
            $table->string('nom', 150)->comment('Nom de l\'établissement en français');
            $table->string('nom_en', 150)->nullable()->comment('Nom de l\'établissement en anglais');
            $table->string('statut_juridique', 50)->default('Prive_Laic')->comment('Public, Prive_Laic, Prive_Confessionnel');
            $table->string('ordre_enseignement', 50)->default('Secondaire_General')->comment('Maternelle, Primaire, Secondaire_General, Secondaire_Technique, Polyvalent');
            $table->string('decret_creation', 150)->nullable()->comment('Arrêté d\'ouverture ou décret ministériel');
            $table->string('nom_chef_etablissement', 120)->nullable()->comment('Nom du Proviseur, Directeur ou Principal');
            $table->string('email', 100)->nullable();
            $table->string('telephone_1', 30)->nullable();
            $table->string('telephone_2', 30)->nullable();
            $table->string('adresse', 150)->nullable()->comment('Adresse physique ou quartier');
            $table->string('logo', 255)->nullable()->comment('Logo spécifique au campus si différent');
            $table->timestamps();
        });

        // Contrainte de clé étrangère sur la table users (après création de etablissements)
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('etablissement_id')
                ->references('id')
                ->on('etablissements')
                ->nullOnDelete();
        });

        // 3. Table des Sections (Sous-systèmes éducatifs)
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('code', 20)->comment('Code court (FR, EN, TECH)');
            $table->string('nom', 100)->comment('Libellé en français (ex: Section Francophone)');
            $table->string('nom_en', 100)->nullable()->comment('Libellé en anglais (ex: Francophone Section)');
            $table->string('langue', 10)->default('fr')->comment('Langue principale d\'enseignement : fr ou en');
            $table->timestamps();

            $table->unique(['etablissement_id', 'code']);
            $table->unique(['etablissement_id', 'nom']);
        });

        // 4. Table des Cycles (Grandes étapes pédagogiques)
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->string('code', 20)->comment('Code court (CYC1, CYC2, LOW-SEC, UPP-SEC)');
            $table->string('nom', 100)->comment('Libellé en français (ex: Premier Cycle, Second Cycle)');
            $table->string('nom_en', 100)->nullable()->comment('Libellé en anglais');
            $table->string('diplome_fin_cycle', 50)->nullable()->comment('Diplôme officiel préparé : BEPC, BACC, GCE O-Level...');
            $table->integer('ordre')->default(1)->comment('Ordre chronologique d\'affichage');
            $table->timestamps();

            $table->unique(['section_id', 'code']);
            $table->unique(['etablissement_id', 'nom']);
        });

        // 5. Table des Niveaux (Paliers d'apprentissage)
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('cycles')->cascadeOnDelete();
            $table->string('code', 20)->comment('Code court (6EME, 3EME, TLE, F1, F5)');
            $table->string('nom', 100)->comment('Libellé en français (ex: Sixième, Troisième)');
            $table->string('nom_en', 100)->nullable()->comment('Libellé en anglais (ex: Form 1, Upper Sixth)');
            $table->integer('ordre')->default(1)->comment('Ordre numérique de progression pour le moteur de passage');
            $table->timestamps();

            $table->unique(['cycle_id', 'code']);
            $table->unique(['etablissement_id', 'nom']);
        });

        // 6. Table des Classes (Salles / Groupes réels d'élèves)
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->string('code', 30)->comment('Code d\'identification (ex: 6M1, TLE-C1, F3-SCI)');
            $table->string('nom', 100)->comment('Libellé complet (ex: 6ème Moderne 1, Terminale C1)');
            $table->string('serie', 50)->nullable()->comment('Série ou filière (A4, C, D, TI, F4, Arts, Science...)');
            $table->integer('capacite_max')->default(50)->comment('Nombre maximal d\'élèves autorisés');
            $table->boolean('is_active')->default(true)->comment('Classe active pour l\'année');
            $table->timestamps();

            $table->unique(['niveau_id', 'code']);
            $table->index(['etablissement_id', 'is_active']);
        });
    }

    /**
     * Annule les migrations en supprimant les tables dans l'ordre inverse.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['etablissement_id']);
        });

        Schema::dropIfExists('classes');
        Schema::dropIfExists('niveaux');
        Schema::dropIfExists('cycles');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('etablissements');
        Schema::dropIfExists('institutions');
    }
};

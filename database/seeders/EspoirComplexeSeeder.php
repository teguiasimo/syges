<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\BourseRemise;
use App\Models\Caisse;
use App\Models\CategorieFrais;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\FraisScolaire;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\TypeFrais;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Seeder autonome d'intégration du client « Groupe Scolaire Bilingue Privé Laïc ESPOIR ».
 *
 * Isolé du code métier générique de SYGES, ce seeder déploie l'ensemble des données
 * initiales de l'établissement ESPOIR (Bafoussam / Tougang Village) :
 * - Identité de l'Institution et logo/filigrane officiels
 * - 2 campus distincts : Maternelle (`ESP-MAT`) et Primaire (`ESP-PRI`)
 * - Architecture pédagogique complète (5 sections dont Section Spéciale Bilingue,
 *   5 cycles, 13 niveaux dont 6 au Primaire, 20 classes réelles)
 * - Année scolaire `2026-2027`, 3 trimestres et 6 séquences par campus
 * - Comptes direction locale (`Directrice` Maternelle, `Directeur` Primaire) et
 *   compte global `Secrétaire` (`etablissement_id = null`, vision multi-établissements)
 * - 12 Maîtres/Maîtresses réels issus des fiches terrain + 8 fiches provisoires pour
 *   les classes non encore pourvues
 * - 503 élèves et inscriptions validées avec comptes financiers pré-initialisés,
 *   grilles tarifaires (85 000 FCFA Maternelle / 75 000 FCFA Primaire) et modèle de
 *   réduction anticipée de 4 % imputée sur la dernière tranche.
 */
class EspoirComplexeSeeder extends Seeder
{
    /**
     * Exécute le peuplement complet et idempotent du Groupe Scolaire ESPOIR.
     *
     * @return void
     *
     * @throws RuntimeException Si le fichier JSON des effectifs est introuvable ou invalide.
     */
    public function run(): void
    {
        // Garantir la présence préalable des rôles, permissions et du catalogue national des matières
        if (Role::where('name', 'directeur-etudes')->doesntExist()) {
            $this->call(DatabaseSeeder::class);
        }

        $jsonPath = database_path('seeders/data/espoir_data.json');
        if (! is_file($jsonPath)) {
            throw new RuntimeException("Fichier de données ESPOIR introuvable : {$jsonPath}");
        }

        /** @var array<string, array<int, array<string, mixed>>> $elevesParClasse */
        $elevesParClasse = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($elevesParClasse)) {
            throw new RuntimeException("Impossible de décoder le fichier JSON ESPOIR : {$jsonPath}");
        }

        DB::transaction(function () use ($elevesParClasse): void {
            // 1. Institution mère (Entité faîtière ESPOIR)
            $institution = $this->seedInstitution();

            // 2. Deux campus distincts : Maternelle et Primaire
            [$matEtab, $priEtab] = $this->seedEtablissements($institution);

            // 3. Année scolaire courante 2026-2027 et découpage en trimestres/séquences par campus
            $annee = $this->seedAnneeScolaireEtPeriodes([$matEtab, $priEtab]);

            // 4. Comptes utilisateurs direction locale et Secrétaire globale multi-campus
            $this->seedStaffAccounts($matEtab, $priEtab);

            // 5. Structure pédagogique (Sections, Cycles, Niveaux, Classes)
            /** @var array<string, Classe> $classesByJsonKey */
            /** @var array<int, array<int, Niveau>> $niveauxByEtab */
            [$classesByJsonKey, $niveauxByEtab] = $this->seedAcademicStructure($matEtab, $priEtab);

            // 6. Équipe enseignante (12 réels + 8 provisoires) et affectation comme titulaires de classe
            $this->seedEnseignantsEtTitulaires($matEtab, $priEtab, $classesByJsonKey);

            // 7. Configuration financière complète par campus (grilles tarifaires, caisse, remise 4 %)
            $this->seedFinancesConfiguration($matEtab, $priEtab, $annee, $niveauxByEtab);

            // 8. Importation des 503 élèves, inscriptions validées et initialisation des comptes financiers
            $this->seedElevesEtInscriptions($matEtab, $priEtab, $annee, $classesByJsonKey, $elevesParClasse);
        });
    }

    /**
     * Crée ou met à jour l'Institution mère « Groupe Scolaire Bilingue Privé Laïc ESPOIR ».
     *
     * @return Institution
     */
    private function seedInstitution(): Institution
    {
        return Institution::updateOrCreate(
            ['code' => 'GSBL-ESPOIR'],
            [
                'sigle' => 'GSBL ESPOIR',
                'nom' => 'Groupe Scolaire Bilingue Privé Laïc « ESPOIR »',
                'nom_en' => 'Secular Bilingual School Group "HOPE"',
                // Devise officielle relevée dans le pied de page des 18 listes officielles du complexe ESPOIR
                'devise' => 'L’ÉDUCATION DES ENFANTS, L’ESPOIR DE DEMAIN',
                'devise_en' => 'THE EDUCATION OF CHILDREN, THE HOPE OF TOMORROW',
                'logo' => '/storage/logos/espoir_logo.jpg',
                'logo_filigrane' => '/storage/logos/espoir_filigrane.png',
                'email' => 'contact@espoir-bafoussam.cm',
                'telephone_1' => '677 72 06 88',
                'telephone_2' => '699 96 68 30',
                'boite_postale' => 'Bafoussam / Tougang Village',
                'region' => 'Ouest',
                'region_en' => 'West',
                'departement' => 'Mifi',
                'departement_en' => 'Mifi',
                'arrondissement' => 'Bafoussam 1er',
                'arrondissement_en' => 'Bafoussam I',
                'ville' => 'Bafoussam',
                'delegation_regionale' => 'Délégation Régionale de l\'Éducation de Base de l\'Ouest',
                'delegation_regionale_en' => 'West Regional Delegation of Basic Education',
                'delegation_departementale' => 'Délégation Départementale de l\'Éducation de Base de la Mifi',
                'delegation_departementale_en' => 'Mifi Divisional Delegation of Basic Education',
            ]
        );
    }

    /**
     * Crée ou met à jour les deux établissements physiques (Maternelle et Primaire)
     * avec leurs titres de direction et de titulaires adaptés à l'Éducation de Base.
     *
     * @param  Institution  $institution  Institution mère ESPOIR
     * @return array{0: Etablissement, 1: Etablissement} Tuple `[Maternelle, Primaire]`
     */
    private function seedEtablissements(Institution $institution): array
    {
        $matEtab = Etablissement::updateOrCreate(
            ['code' => 'ESP-MAT'],
            [
                'institution_id' => $institution->id,
                'nom' => 'École Maternelle Bilingue Privée Laïque « ESPOIR »',
                'nom_en' => 'Bilingual Secular Private Nursery School "HOPE"',
                'statut_juridique' => 'Prive_Laic',
                'ordre_enseignement' => 'Maternelle',
                'nom_chef_etablissement' => 'Mme La Directrice (À compléter)',
                'titre_chef' => 'La Directrice',
                'titre_chef_en' => 'The Headmistress',
                'titre_responsable_classe' => 'Le Maître / La Maîtresse',
                'titre_responsable_classe_en' => 'Class Teacher',
                'email' => 'maternelle@espoir-bafoussam.cm',
                'telephone_1' => '677 72 06 88',
                'telephone_2' => '699 96 68 30',
                'adresse' => 'Tougang Village — En face du stade, Bafoussam',
                'logo' => '/storage/logos/espoir_logo.jpg',
                'logo_filigrane' => '/storage/logos/espoir_filigrane.png',
                'verrou_solvabilite_actif' => true,
                'bonus_paiement_anticipe_actif' => true,
            ]
        );

        $priEtab = Etablissement::updateOrCreate(
            ['code' => 'ESP-PRI'],
            [
                'institution_id' => $institution->id,
                'nom' => 'École Primaire Bilingue Privée Laïque « ESPOIR »',
                'nom_en' => 'Bilingual Secular Private Primary School "HOPE"',
                'statut_juridique' => 'Prive_Laic',
                'ordre_enseignement' => 'Primaire',
                'nom_chef_etablissement' => 'M. Le Directeur (À compléter)',
                'titre_chef' => 'Le Directeur',
                'titre_chef_en' => 'The Director',
                'titre_responsable_classe' => 'Le Maître / La Maîtresse',
                'titre_responsable_classe_en' => 'Class Teacher',
                'email' => 'primaire@espoir-bafoussam.cm',
                'telephone_1' => '677 72 06 88',
                'telephone_2' => '699 96 68 30',
                'adresse' => 'Tougang Village — En face du stade, Bafoussam',
                'logo' => '/storage/logos/espoir_logo.jpg',
                'logo_filigrane' => '/storage/logos/espoir_filigrane.png',
                'verrou_solvabilite_actif' => true,
                'bonus_paiement_anticipe_actif' => true,
            ]
        );

        return [$matEtab, $priEtab];
    }

    /**
     * Crée l'année scolaire active `2026-2027` et initialise les 3 trimestres + 6 séquences
     * propres à chaque campus.
     *
     * @param  array<int, Etablissement>  $etablissements  Liste des établissements du groupe
     * @return AnneeScolaire Année scolaire active
     */
    private function seedAnneeScolaireEtPeriodes(array $etablissements): AnneeScolaire
    {
        AnneeScolaire::where('code', '!=', '2026-2027')->update(['is_current' => false]);

        $annee = AnneeScolaire::updateOrCreate(
            ['code' => '2026-2027'],
            [
                'nom' => 'Année Scolaire 2026-2027',
                'nom_en' => 'Academic Year 2026-2027',
                'date_debut' => '2026-09-07',
                'date_fin' => '2027-06-30',
                'statut' => 'en_cours',
                'mode_calcul_moyenne' => 'arithmetique_simple',
                'is_current' => true,
            ]
        );

        $trimesters = [
            [
                'code' => 'TRIM-1',
                'nom' => '1er Trimestre',
                'nom_en' => '1st Term',
                'ordre' => 1,
                'date_debut' => '2026-09-07',
                'date_fin' => '2026-12-18',
                'statut' => 'en_cours',
                'sequences' => [
                    ['code' => 'SEQ-1', 'nom' => 'Séquence 1', 'nom_en' => 'Sequence 1', 'ordre' => 1, 'date_debut' => '2026-10-05', 'date_fin' => '2026-10-16', 'date_limite_saisie' => '2026-10-24', 'statut' => 'ouverte'],
                    ['code' => 'SEQ-2', 'nom' => 'Séquence 2', 'nom_en' => 'Sequence 2', 'ordre' => 2, 'date_debut' => '2026-11-23', 'date_fin' => '2026-12-04', 'date_limite_saisie' => '2026-12-12', 'statut' => 'a_venir'],
                ],
            ],
            [
                'code' => 'TRIM-2',
                'nom' => '2ème Trimestre',
                'nom_en' => '2nd Term',
                'ordre' => 2,
                'date_debut' => '2027-01-04',
                'date_fin' => '2027-03-26',
                'statut' => 'a_venir',
                'sequences' => [
                    ['code' => 'SEQ-3', 'nom' => 'Séquence 3', 'nom_en' => 'Sequence 3', 'ordre' => 1, 'date_debut' => '2027-02-01', 'date_fin' => '2027-02-12', 'date_limite_saisie' => '2027-02-20', 'statut' => 'a_venir'],
                    ['code' => 'SEQ-4', 'nom' => 'Séquence 4', 'nom_en' => 'Sequence 4', 'ordre' => 2, 'date_debut' => '2027-03-15', 'date_fin' => '2027-03-24', 'date_limite_saisie' => '2027-03-31', 'statut' => 'a_venir'],
                ],
            ],
            [
                'code' => 'TRIM-3',
                'nom' => '3ème Trimestre',
                'nom_en' => '3rd Term',
                'ordre' => 3,
                'date_debut' => '2027-04-12',
                'date_fin' => '2027-06-30',
                'statut' => 'a_venir',
                'sequences' => [
                    ['code' => 'SEQ-5', 'nom' => 'Séquence 5', 'nom_en' => 'Sequence 5', 'ordre' => 1, 'date_debut' => '2027-05-03', 'date_fin' => '2027-05-14', 'date_limite_saisie' => '2027-05-22', 'statut' => 'a_venir'],
                    ['code' => 'SEQ-6', 'nom' => 'Séquence 6', 'nom_en' => 'Sequence 6', 'ordre' => 2, 'date_debut' => '2027-06-07', 'date_fin' => '2027-06-18', 'date_limite_saisie' => '2027-06-25', 'statut' => 'a_venir'],
                ],
            ],
        ];

        foreach ($etablissements as $etab) {
            foreach ($trimesters as $trim) {
                $periode = Periode::withoutGlobalScopes()->updateOrCreate(
                    [
                        'etablissement_id' => $etab->id,
                        'annee_scolaire_id' => $annee->id,
                        'code' => $trim['code'],
                    ],
                    [
                        'nom' => $trim['nom'],
                        'nom_en' => $trim['nom_en'],
                        'ordre' => $trim['ordre'],
                        'date_debut' => $trim['date_debut'],
                        'date_fin' => $trim['date_fin'],
                        'statut' => $trim['statut'],
                    ]
                );

                foreach ($trim['sequences'] as $seq) {
                    Evaluation::updateOrCreate(
                        [
                            'periode_id' => $periode->id,
                            'code' => $seq['code'],
                        ],
                        [
                            'nom' => $seq['nom'],
                            'nom_en' => $seq['nom_en'],
                            'ordre' => $seq['ordre'],
                            'ponderation' => 50.00,
                            'date_debut' => $seq['date_debut'],
                            'date_fin' => $seq['date_fin'],
                            'date_limite_saisie' => $seq['date_limite_saisie'],
                            'is_locked' => false,
                            'statut' => $seq['statut'],
                        ]
                    );
                }
            }
        }

        return $annee;
    }

    /**
     * Crée les comptes de direction locaux (cloisonnés par campus) ainsi que le compte
     * Secrétaire générale multi-campus (`etablissement_id = null`, sans fiche Personnel locale).
     *
     * @param  Etablissement  $matEtab  Campus Maternelle
     * @param  Etablissement  $priEtab  Campus Primaire
     * @return void
     */
    private function seedStaffAccounts(Etablissement $matEtab, Etablissement $priEtab): void
    {
        $directorRole = Role::where('name', 'directeur-etudes')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $defaultPassword = Hash::make('Espoir@2026');

        // 1. Directrice Maternelle (cloisonnée sur ESP-MAT)
        $directriceUser = User::updateOrCreate(
            ['email' => 'directrice.maternelle@espoir.cm'],
            [
                'name' => 'Directrice Maternelle (À compléter)',
                'login' => 'directrice.maternelle',
                'password' => $defaultPassword,
                'etablissement_id' => $matEtab->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $directriceUser->syncRoles([$directorRole]);

        Personnel::withoutGlobalScopes()->updateOrCreate(
            ['matricule' => 'ESP-MAT-DIR-01'],
            [
                'user_id' => $directriceUser->id,
                'etablissement_id' => $matEtab->id,
                'nom' => 'DIRECTRICE MATERNELLE',
                'prenom' => 'À compléter',
                'sexe' => 'F',
                'telephone' => '677 72 06 88',
                'email' => 'directrice.maternelle@espoir.cm',
                'fonction_principale' => 'Directrice',
                'type_contrat' => 'Permanent',
                'date_embauche' => '2026-09-01',
                'statut' => 'Actif',
                'is_enseignant' => false,
                'observations' => 'Compte provisoire de la Directrice de l\'École Maternelle — modifiable sur le terrain.',
            ]
        );

        // 2. Directeur Primaire (cloisonné sur ESP-PRI)
        $directeurUser = User::updateOrCreate(
            ['email' => 'directeur.primaire@espoir.cm'],
            [
                'name' => 'Directeur Primaire (À compléter)',
                'login' => 'directeur.primaire',
                'password' => $defaultPassword,
                'etablissement_id' => $priEtab->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $directeurUser->syncRoles([$directorRole]);

        Personnel::withoutGlobalScopes()->updateOrCreate(
            ['matricule' => 'ESP-PRI-DIR-01'],
            [
                'user_id' => $directeurUser->id,
                'etablissement_id' => $priEtab->id,
                'nom' => 'DIRECTEUR PRIMAIRE',
                'prenom' => 'À compléter',
                'sexe' => 'M',
                'telephone' => '699 96 68 30',
                'email' => 'directeur.primaire@espoir.cm',
                'fonction_principale' => 'Directeur',
                'type_contrat' => 'Permanent',
                'date_embauche' => '2026-09-01',
                'statut' => 'Actif',
                'is_enseignant' => false,
                'observations' => 'Compte provisoire du Directeur de l\'École Primaire — modifiable sur le terrain.',
            ]
        );

        // 3. Secrétaire / Administratrice Multi-Campus (profil `admin` + accès global explicite sans Personnel local)
        $secretaireUser = User::updateOrCreate(
            ['email' => 'secretaire@espoir.cm'],
            [
                'name' => 'Secrétaire Générale ESPOIR',
                'login' => 'secretaire.espoir',
                'password' => $defaultPassword,
                'etablissement_id' => null,
                'has_global_access' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $secretaireUser->syncRoles([$adminRole]);
    }

    /**
     * Déploie l'arborescence Sections → Cycles → Niveaux → Classes sur les deux établissements.
     *
     * Veille à garantir des noms de cycles et de niveaux uniques par `etablissement_id`
     * afin de respecter les contraintes d'unicité du schéma SYGES.
     *
     * @param  Etablissement  $matEtab  Campus Maternelle
     * @param  Etablissement  $priEtab  Campus Primaire
     * @return array{0: array<string, Classe>, 1: array<int, array<int, Niveau>>}
     */
    private function seedAcademicStructure(Etablissement $matEtab, Etablissement $priEtab): array
    {
        $blueprint = [
            $matEtab->id => [
                'sections' => [
                    [
                        'code' => 'FR',
                        'nom' => 'Section Francophone',
                        'nom_en' => 'Francophone Section',
                        'langue' => 'fr',
                        'cycle' => ['code' => 'CYC-MAT-FR', 'nom' => 'Cycle Maternelle Francophone', 'nom_en' => 'Francophone Nursery Cycle', 'ordre' => 1],
                        'niveaux' => [
                            ['code' => 'PS-FR', 'nom' => 'Petite Section', 'nom_en' => 'Small Section', 'ordre' => 2, 'classes' => [
                                ['json_key' => 'PS', 'code' => 'PS-FR', 'nom' => 'PETITE SECTION'],
                            ]],
                            ['code' => 'GS-FR', 'nom' => 'Grande Section', 'nom_en' => 'Great Section', 'ordre' => 3, 'classes' => [
                                ['json_key' => 'GS', 'code' => 'GS-FR', 'nom' => 'GRANDE SECTION'],
                            ]],
                        ],
                    ],
                    [
                        'code' => 'EN',
                        'nom' => 'Section Anglophone',
                        'nom_en' => 'Anglophone Section',
                        'langue' => 'en',
                        'cycle' => ['code' => 'CYC-MAT-EN', 'nom' => 'Nursery Cycle (Anglophone)', 'nom_en' => 'Anglophone Nursery Cycle', 'ordre' => 2],
                        'niveaux' => [
                            ['code' => 'NUR-1', 'nom' => 'Nursery One', 'nom_en' => 'Nursery One', 'ordre' => 2, 'classes' => [
                                ['json_key' => 'NUR1A', 'code' => 'NUR-1A', 'nom' => 'NURSERY ONE A'],
                                ['json_key' => 'NUR1B', 'code' => 'NUR-1B', 'nom' => 'NURSERY ONE B'],
                            ]],
                            ['code' => 'NUR-2', 'nom' => 'Nursery Two', 'nom_en' => 'Nursery Two', 'ordre' => 3, 'classes' => [
                                ['json_key' => 'NUR2', 'code' => 'NUR-2', 'nom' => 'NURSERY TWO'],
                            ]],
                        ],
                    ],
                    [
                        'code' => 'SPE-BIL',
                        'nom' => 'Section Spéciale Bilingue',
                        'nom_en' => 'Special Bilingual Section',
                        'langue' => 'fr',
                        'cycle' => ['code' => 'CYC-MAT-BIL', 'nom' => 'Cycle Maternelle Spéciale Bilingue', 'nom_en' => 'Special Bilingual Nursery Cycle', 'ordre' => 3],
                        'niveaux' => [
                            ['code' => 'PREMAT-BIL', 'nom' => 'Prématernelle (Bilingue)', 'nom_en' => 'Pre-Nursery (Bilingual)', 'ordre' => 1, 'classes' => [
                                ['json_key' => 'PREMAT_BIL', 'code' => 'PREMAT-BIL', 'nom' => 'PREMATERNELLE SPÉCIALE BILINGUE'],
                            ]],
                            ['code' => 'PS-BIL', 'nom' => 'Petite Section (Bilingue)', 'nom_en' => 'Nursery One (Bilingual)', 'ordre' => 2, 'classes' => [
                                ['json_key' => 'PS_BIL', 'code' => 'PS-BIL', 'nom' => 'PETITE SECTION SPÉCIALE BILINGUE'],
                            ]],
                            ['code' => 'GS-BIL', 'nom' => 'Grande Section (Bilingue)', 'nom_en' => 'Nursery Two (Bilingual)', 'ordre' => 3, 'classes' => [
                                ['json_key' => 'GS_BIL', 'code' => 'GS-BIL', 'nom' => 'GRANDE SECTION SPÉCIALE BILINGUE'],
                            ]],
                        ],
                    ],
                ],
            ],
            $priEtab->id => [
                'sections' => [
                    [
                        'code' => 'FR',
                        'nom' => 'Section Francophone',
                        'nom_en' => 'Francophone Section',
                        'langue' => 'fr',
                        'cycle' => ['code' => 'CYC-PRI-FR', 'nom' => 'Cycle Primaire Francophone', 'nom_en' => 'Francophone Primary Cycle', 'ordre' => 1],
                        'niveaux' => [
                            [
                                'code' => 'NIV-1',
                                'nom' => 'Niveau 1',
                                'nom_en' => 'Level 1',
                                'ordre' => 1,
                                'classes' => [
                                    ['json_key' => 'SIL', 'code' => 'SIL', 'nom' => 'SIL'],
                                    ['json_key' => 'CP', 'code' => 'CP', 'nom' => 'CP'],
                                ],
                            ],
                            [
                                'code' => 'NIV-2',
                                'nom' => 'Niveau 2',
                                'nom_en' => 'Level 2',
                                'ordre' => 2,
                                'classes' => [
                                    ['json_key' => 'CE1', 'code' => 'CE1', 'nom' => 'CE1'],
                                    ['json_key' => 'CE2', 'code' => 'CE2', 'nom' => 'CE2'],
                                ],
                            ],
                            [
                                'code' => 'NIV-3',
                                'nom' => 'Niveau 3',
                                'nom_en' => 'Level 3',
                                'ordre' => 3,
                                'classes' => [
                                    ['json_key' => 'CM1', 'code' => 'CM1', 'nom' => 'CM1'],
                                    ['json_key' => 'CM2', 'code' => 'CM2', 'nom' => 'CM2'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'code' => 'EN',
                        'nom' => 'Section Anglophone',
                        'nom_en' => 'Anglophone Section',
                        'langue' => 'en',
                        'cycle' => ['code' => 'CYC-PRI-EN', 'nom' => 'Primary Cycle (Anglophone)', 'nom_en' => 'Anglophone Primary Cycle', 'ordre' => 2],
                        'niveaux' => [
                            [
                                'code' => 'LVL-1',
                                'nom' => 'Level 1',
                                'nom_en' => 'Level 1',
                                'ordre' => 1,
                                'classes' => [
                                    ['json_key' => 'CLASS 1', 'code' => 'CLASS-1', 'nom' => 'CLASS 1'],
                                    ['json_key' => 'CLASS 2', 'code' => 'CLASS-2', 'nom' => 'CLASS 2'],
                                ],
                            ],
                            [
                                'code' => 'LVL-2',
                                'nom' => 'Level 2',
                                'nom_en' => 'Level 2',
                                'ordre' => 2,
                                'classes' => [
                                    ['json_key' => 'CLASS 3', 'code' => 'CLASS-3', 'nom' => 'CLASS 3'],
                                    ['json_key' => 'CLASS 4', 'code' => 'CLASS-4', 'nom' => 'CLASS 4'],
                                ],
                            ],
                            [
                                'code' => 'LVL-3',
                                'nom' => 'Level 3',
                                'nom_en' => 'Level 3',
                                'ordre' => 3,
                                'classes' => [
                                    ['json_key' => 'CLASS 5', 'code' => 'CLASS-5', 'nom' => 'CLASS 5'],
                                    ['json_key' => 'CLASS 6', 'code' => 'CLASS-6', 'nom' => 'CLASS 6'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $classesByJsonKey = [];
        $niveauxByEtab = [
            $matEtab->id => [],
            $priEtab->id => [],
        ];

        foreach ($blueprint as $etabId => $data) {
            foreach ($data['sections'] as $secDef) {
                $section = Section::updateOrCreate(
                    ['etablissement_id' => $etabId, 'code' => $secDef['code']],
                    [
                        'nom' => $secDef['nom'],
                        'nom_en' => $secDef['nom_en'],
                        'langue' => $secDef['langue'],
                    ]
                );

                $cycDef = $secDef['cycle'];
                $cycle = Cycle::withoutGlobalScopes()->updateOrCreate(
                    ['section_id' => $section->id, 'code' => $cycDef['code']],
                    [
                        'etablissement_id' => $etabId,
                        'nom' => $cycDef['nom'],
                        'nom_en' => $cycDef['nom_en'],
                        'diplome_fin_cycle' => $etabId === $priEtab->id
                            ? ($secDef['code'] === 'FR' ? 'CEP' : 'FSLC')
                            : null,
                        'ordre' => $cycDef['ordre'],
                    ]
                );

                foreach ($secDef['niveaux'] as $nivDef) {
                    $niveau = Niveau::withoutGlobalScopes()->updateOrCreate(
                        ['cycle_id' => $cycle->id, 'code' => $nivDef['code']],
                        [
                            'etablissement_id' => $etabId,
                            'nom' => $nivDef['nom'],
                            'nom_en' => $nivDef['nom_en'],
                            'ordre' => $nivDef['ordre'],
                        ]
                    );

                    $niveauxByEtab[$etabId][] = $niveau;

                    foreach ($nivDef['classes'] as $clsDef) {
                        $classe = Classe::withoutGlobalScopes()->updateOrCreate(
                            ['niveau_id' => $niveau->id, 'code' => $clsDef['code']],
                            [
                                'etablissement_id' => $etabId,
                                'nom' => $clsDef['nom'],
                                'capacite_max' => 60,
                                'is_active' => true,
                            ]
                        );

                        $classesByJsonKey[$clsDef['json_key']] = $classe;
                    }
                }
            }
        }

        return [$classesByJsonKey, $niveauxByEtab];
    }

    /**
     * Crée les fiches des 12 Maîtres et Maîtresses réels identifiés dans les documents
     * ainsi que des fiches provisoires pour les classes sans titulaire nommé, puis
     * rattache chaque classe à son titulaire (`professeur_principal_id`).
     *
     * @param  Etablissement  $matEtab  Campus Maternelle
     * @param  Etablissement  $priEtab  Campus Primaire
     * @param  array<string, Classe>  $classesByJsonKey  Classes indexées par clé JSON
     * @return void
     */
    private function seedEnseignantsEtTitulaires(
        Etablissement $matEtab,
        Etablissement $priEtab,
        array $classesByJsonKey
    ): void {
        // 12 enseignants réels extraits des fiches de classe ESPOIR
        $realTeachers = [
            // Primaire Francophone
            ['matricule' => 'ESP-PRI-ENS-01', 'etab_id' => $priEtab->id, 'nom' => 'MATENE', 'prenom' => 'Phanie Viviane', 'sexe' => 'F', 'tel' => '676 47 32 51', 'fonction' => 'Maîtresse', 'classes' => ['SIL'], 'obs' => null],
            ['matricule' => 'ESP-PRI-ENS-02', 'etab_id' => $priEtab->id, 'nom' => 'YOGOM', 'prenom' => 'Diane', 'sexe' => 'F', 'tel' => '683 24 65 02', 'fonction' => 'Maîtresse', 'classes' => ['CP'], 'obs' => null],
            ['matricule' => 'ESP-PRI-ENS-03', 'etab_id' => $priEtab->id, 'nom' => 'MOCHE', 'prenom' => 'Juliette', 'sexe' => 'F', 'tel' => '695 05 39 84', 'fonction' => 'Maîtresse', 'classes' => ['CE1'], 'obs' => null],
            ['matricule' => 'ESP-PRI-ENS-04', 'etab_id' => $priEtab->id, 'nom' => 'TCHATCHOUANG', 'prenom' => 'Euphrasile', 'sexe' => 'F', 'tel' => '680 31 00 34', 'fonction' => 'Maîtresse', 'classes' => ['CE2'], 'obs' => null],
            ['matricule' => 'ESP-PRI-ENS-05', 'etab_id' => $priEtab->id, 'nom' => 'AYEMELE', 'prenom' => 'Alice', 'sexe' => 'F', 'tel' => '678 87 31 42', 'fonction' => 'Maîtresse', 'classes' => ['CM1'], 'obs' => null],
            ['matricule' => 'ESP-PRI-ENS-06', 'etab_id' => $priEtab->id, 'nom' => 'KAMGA', 'prenom' => 'Blondelle', 'sexe' => 'F', 'tel' => '679 88 56 13', 'fonction' => 'Maîtresse', 'classes' => ['CM2'], 'obs' => null],
            // Primaire Anglophone
            ['matricule' => 'ESP-PRI-ENS-07', 'etab_id' => $priEtab->id, 'nom' => 'SHIMENYI', 'prenom' => 'Ruth', 'sexe' => 'F', 'tel' => '679 57 17 39', 'fonction' => 'Maîtresse', 'classes' => ['CLASS 2'], 'obs' => null],
            ['matricule' => 'ESP-PRI-ENS-08', 'etab_id' => $priEtab->id, 'nom' => 'BERINYUY', 'prenom' => 'Julienne', 'sexe' => 'F', 'tel' => '672 72 47 26', 'fonction' => 'Maîtresse', 'classes' => ['CLASS 3', 'CLASS 4'], 'obs' => 'Titulaire des classes Class 3 et Class 4'],
            ['matricule' => 'ESP-PRI-ENS-09', 'etab_id' => $priEtab->id, 'nom' => 'HENRY', 'prenom' => 'Forbai', 'sexe' => 'M', 'tel' => '652 16 21 44', 'fonction' => 'Maître', 'classes' => ['CLASS 5'], 'obs' => null],
            // Maternelle Francophone
            ['matricule' => 'ESP-MAT-ENS-01', 'etab_id' => $matEtab->id, 'nom' => 'KAMDOM', 'prenom' => 'Daniela', 'sexe' => 'F', 'tel' => '676 48 31 12', 'fonction' => 'Maîtresse', 'classes' => ['PS'], 'obs' => 'Titulaire principale Petite Section Francophone (binôme avec Mme KENTSA Berenice)'],
            ['matricule' => 'ESP-MAT-ENS-02', 'etab_id' => $matEtab->id, 'nom' => 'KENTSA', 'prenom' => 'Berenice', 'sexe' => 'F', 'tel' => '659 33 82 06', 'fonction' => 'Maîtresse', 'classes' => [], 'obs' => 'Co-titulaire Petite Section Francophone'],
            ['matricule' => 'ESP-MAT-ENS-03', 'etab_id' => $matEtab->id, 'nom' => 'MPOUOMEDJIE', 'prenom' => 'Wato Alida', 'sexe' => 'F', 'tel' => '691 33 61 00', 'fonction' => 'Maîtresse', 'classes' => ['GS'], 'obs' => null],
        ];

        $assignedClassKeys = [];

        foreach ($realTeachers as $teacher) {
            $personnel = Personnel::withoutGlobalScopes()->updateOrCreate(
                ['matricule' => $teacher['matricule']],
                [
                    'etablissement_id' => $teacher['etab_id'],
                    'nom' => $teacher['nom'],
                    'prenom' => $teacher['prenom'],
                    'sexe' => $teacher['sexe'],
                    'telephone' => $teacher['tel'],
                    'fonction_principale' => $teacher['fonction'],
                    'type_contrat' => 'Permanent',
                    'date_embauche' => '2026-09-01',
                    'statut' => 'Actif',
                    'is_enseignant' => true,
                    'observations' => $teacher['obs'],
                ]
            );

            foreach ($teacher['classes'] as $classKey) {
                if (isset($classesByJsonKey[$classKey])) {
                    $classesByJsonKey[$classKey]->update(['professeur_principal_id' => $personnel->id]);
                    $assignedClassKeys[$classKey] = true;
                }
            }
        }

        // Fiches provisoires pour les classes sans enseignant identifié dans les listes Word
        $provisionalCounterMat = 1;
        $provisionalCounterPri = 1;

        foreach ($classesByJsonKey as $classKey => $classe) {
            if (isset($assignedClassKeys[$classKey])) {
                continue;
            }

            $isMat = (int) $classe->etablissement_id === (int) $matEtab->id;
            $prefix = $isMat ? 'ESP-MAT-PROV-' : 'ESP-PRI-PROV-';
            $seq = $isMat ? $provisionalCounterMat++ : $provisionalCounterPri++;
            $matricule = $prefix.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

            $provisionalTeacher = Personnel::withoutGlobalScopes()->updateOrCreate(
                ['matricule' => $matricule],
                [
                    'etablissement_id' => $classe->etablissement_id,
                    'nom' => 'TITULAIRE PROVISOIRE',
                    'prenom' => "{$classe->nom} (À compléter)",
                    'sexe' => 'F',
                    'telephone' => 'À compléter',
                    'fonction_principale' => 'Maîtresse',
                    'type_contrat' => 'Permanent',
                    'date_embauche' => '2026-09-01',
                    'statut' => 'Actif',
                    'is_enseignant' => true,
                    'observations' => "Fiche provisoire de {$classe->nom} créée en attente d'identification du titulaire sur le terrain.",
                ]
            );

            $classe->update(['professeur_principal_id' => $provisionalTeacher->id]);
        }
    }

    /**
     * Paramètre les catégories, types de frais, grilles tarifaires par niveau,
     * caisses principales et modèle de réduction de 4 % pour les deux établissements.
     *
     * @param  Etablissement  $matEtab  Campus Maternelle (85 000 FCFA)
     * @param  Etablissement  $priEtab  Campus Primaire (75 000 FCFA)
     * @param  AnneeScolaire  $annee  Année scolaire 2026-2027
     * @param  array<int, array<int, Niveau>>  $niveauxByEtab  Niveaux regroupés par ID d'établissement
     * @return void
     */
    private function seedFinancesConfiguration(
        Etablissement $matEtab,
        Etablissement $priEtab,
        AnneeScolaire $annee,
        array $niveauxByEtab
    ): void {
        $configs = [
            $matEtab->id => [
                'tranche1' => 45000.0,
                'tranche2' => 30000.0,
                'remise_exemple' => '85 000 -> 3 400 FCFA',
            ],
            $priEtab->id => [
                'tranche1' => 35000.0,
                'tranche2' => 30000.0,
                'remise_exemple' => '75 000 -> 3 000 FCFA',
            ],
        ];

        foreach ([$matEtab, $priEtab] as $etab) {
            $cfg = $configs[$etab->id];

            // Initialiser les catégories de frais standards pour cet établissement
            $categories = CategorieFrais::obtenirOuInitialiserPourEtablissement($etab->id);
            $catScolarite = $categories->firstWhere('nom', 'Scolarité');
            $catServices = $categories->firstWhere('nom', 'Transport & Services Annexes');

            // Types de frais : Inscription (10k), Tranche 1 (45k/35k), Tranche 2 (30k), Cantine (optionnelle)
            $typeInscription = TypeFrais::withoutGlobalScopes()->updateOrCreate(
                ['etablissement_id' => $etab->id, 'code' => 'INSCRIPTION'],
                [
                    'categorie_frais_id' => $catScolarite?->id,
                    'libelle' => 'Inscription',
                    'description' => 'Frais d\'inscription et ouverture de dossier annuel',
                    'obligatoire' => true,
                    'periodicite' => 'annuel',
                    'ordre_priorite' => 1,
                    'montant_defaut' => 10000.0,
                    'date_limite_defaut' => '2026-09-07',
                    'is_active' => true,
                ]
            );

            $typeTranche1 = TypeFrais::withoutGlobalScopes()->updateOrCreate(
                ['etablissement_id' => $etab->id, 'code' => 'TRANCHE-1'],
                [
                    'categorie_frais_id' => $catScolarite?->id,
                    'libelle' => '1ère Tranche (Scolarité)',
                    'description' => 'Première tranche de pension exigible avant/à la rentrée',
                    'obligatoire' => true,
                    'periodicite' => 'annuel',
                    'ordre_priorite' => 2,
                    'montant_defaut' => $cfg['tranche1'],
                    'date_limite_defaut' => '2026-10-15',
                    'is_active' => true,
                ]
            );

            $typeTranche2 = TypeFrais::withoutGlobalScopes()->updateOrCreate(
                ['etablissement_id' => $etab->id, 'code' => 'TRANCHE-2'],
                [
                    'categorie_frais_id' => $catScolarite?->id,
                    'libelle' => '2ème Tranche (Scolarité)',
                    'description' => 'Solde final de la scolarité (support d\'imputation de la remise anticipée de 4 %)',
                    'obligatoire' => true,
                    'periodicite' => 'annuel',
                    'ordre_priorite' => 3,
                    'montant_defaut' => $cfg['tranche2'],
                    'date_limite_defaut' => '2027-01-15',
                    'is_active' => true,
                ]
            );

            TypeFrais::withoutGlobalScopes()->updateOrCreate(
                ['etablissement_id' => $etab->id, 'code' => 'CANTINE'],
                [
                    'categorie_frais_id' => $catServices?->id ?? $catScolarite?->id,
                    'libelle' => 'Cantine scolaire',
                    'description' => 'Service facultatif de cantine scolaire (tarif à ajuster selon formule sur le terrain)',
                    'obligatoire' => false,
                    'periodicite' => 'mensuel',
                    'ordre_priorite' => 4,
                    'montant_defaut' => 0.0,
                    'date_limite_defaut' => null,
                    'is_active' => true,
                ]
            );

            // Grille tarifaire sur l'ensemble des niveaux du campus
            foreach ($niveauxByEtab[$etab->id] as $niveau) {
                $tranchesNiveau = [
                    ['type' => $typeInscription, 'num' => 1, 'prio' => 1, 'libelle' => 'Inscription', 'montant' => 10000.0, 'limite' => '2026-09-07'],
                    ['type' => $typeTranche1, 'num' => 2, 'prio' => 2, 'libelle' => '1ère Tranche', 'montant' => $cfg['tranche1'], 'limite' => '2026-10-15'],
                    ['type' => $typeTranche2, 'num' => 3, 'prio' => 3, 'libelle' => '2ème Tranche', 'montant' => $cfg['tranche2'], 'limite' => '2027-01-15'],
                ];

                foreach ($tranchesNiveau as $tr) {
                    FraisScolaire::withoutGlobalScopes()->updateOrCreate(
                        [
                            'annee_scolaire_id' => $annee->id,
                            'etablissement_id' => $etab->id,
                            'niveau_id' => $niveau->id,
                            'serie' => null,
                            'tranche_numero' => $tr['num'],
                        ],
                        [
                            'type_frais_id' => $tr['type']->id,
                            'ordre_priorite' => $tr['prio'],
                            'libelle_tranche' => $tr['libelle'],
                            'montant' => $tr['montant'],
                            'obligatoire' => true,
                            'date_limite' => $tr['limite'],
                        ]
                    );
                }
            }

            // Caisse Principale du campus
            Caisse::withoutGlobalScopes()->updateOrCreate(
                ['etablissement_id' => $etab->id, 'code' => 'CAISSE-PRIN'],
                [
                    'nom' => 'Caisse Principale',
                    'solde_initial' => 0.0,
                    'is_principale' => true,
                    'is_active' => true,
                    'description' => "Caisse centrale d'encaissement de {$etab->nom}",
                ]
            );

            // Modèle de réduction anticipée de 4 % imputable sur la dernière tranche
            BourseRemise::withoutGlobalScopes()->updateOrCreate(
                ['etablissement_id' => $etab->id, 'code' => 'REM-ANTICIPE-4'],
                [
                    'annee_scolaire_id' => $annee->id,
                    'nom' => 'Réduction 4% Paiement Anticipé (avant rentrée)',
                    'type' => 'remise',
                    'categorie' => 'autre',
                    'mode_calcul' => 'pourcentage',
                    'valeur' => 4.0,
                    'cible_imputation' => 'derniere_tranche',
                    'niveau_id' => null,
                    'type_frais_id' => null,
                    'description' => "Réduction de 4 % sur la totalité de la pension ({$cfg['remise_exemple']}) lorsque l'inscription et la 1ère tranche sont réglées avant la rentrée ; déduite automatiquement sur la dernière tranche.",
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Importe les 503 élèves depuis `espoir_data.json`, crée leurs inscriptions validées
     * pour 2026-2027 et initialise immédiatement leurs comptes financiers élèves.
     *
     * Les champs non fournis dans les listes Word (`sexe`, `telephone_parent`) restent
     * volontairement à `null` pour signaler « À compléter » sans fausser les statistiques.
     *
     * @param  Etablissement  $matEtab  Campus Maternelle
     * @param  Etablissement  $priEtab  Campus Primaire
     * @param  AnneeScolaire  $annee  Année scolaire 2026-2027
     * @param  array<string, Classe>  $classesByJsonKey  Classes indexées par clé JSON
     * @param  array<string, array<int, array<string, mixed>>>  $elevesParClasse  Données élèves extraites
     * @return void
     */
    private function seedElevesEtInscriptions(
        Etablissement $matEtab,
        Etablissement $priEtab,
        AnneeScolaire $annee,
        array $classesByJsonKey,
        array $elevesParClasse
    ): void {
        $financeService = app(FinanceService::class);
        $seqMat = 1;
        $seqPri = 1;

        // Ordre déterministe des classes garantissant une numérotation matricule stable en ré-exécution
        foreach ($classesByJsonKey as $jsonKey => $classe) {
            $students = $elevesParClasse[$jsonKey] ?? [];
            $isMat = (int) $classe->etablissement_id === (int) $matEtab->id;
            $etabCode = $isMat ? 'ESP-MAT' : 'ESP-PRI';

            foreach ($students as $student) {
                $seq = $isMat ? $seqMat++ : $seqPri++;
                $matricule = sprintf('%s-2026-%04d', $etabCode, $seq);

                $eleve = Eleve::updateOrCreate(
                    ['matricule' => $matricule],
                    [
                        'nom' => (string) ($student['nom'] ?? ''),
                        'prenom' => ! empty($student['prenom']) ? (string) $student['prenom'] : null,
                        'sexe' => null,
                        'date_naissance' => ! empty($student['date_naissance']) ? (string) $student['date_naissance'] : null,
                        'nationalite' => 'CAMEROUNAISE',
                        'telephone_parent' => null,
                        'is_active' => true,
                    ]
                );

                $inscription = Inscription::withoutGlobalScopes()->firstOrCreate(
                    [
                        'eleve_id' => $eleve->id,
                        'annee_scolaire_id' => $annee->id,
                    ],
                    [
                        'etablissement_id' => $classe->etablissement_id,
                        'classe_id' => $classe->id,
                        'type_inscription' => 'nouveau',
                        'is_redoublant' => false,
                        'statut' => 'validee',
                        'date_inscription' => '2026-09-07',
                    ]
                );

                // Harmoniser la classe si ré-exécution
                if ((int) $inscription->classe_id !== (int) $classe->id || (int) $inscription->etablissement_id !== (int) $classe->etablissement_id) {
                    $inscription->update([
                        'classe_id' => $classe->id,
                        'etablissement_id' => $classe->etablissement_id,
                    ]);
                }

                $financeService->initialiserCompteEleve($inscription);
            }
        }
    }
}


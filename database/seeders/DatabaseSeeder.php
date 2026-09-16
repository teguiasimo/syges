<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder principal de l'application SYGES.
 * Initialise l'ensemble des permissions scolaires par catégorie,
 * configure les rôles par défaut et attribue les droits au compte Super Administrateur.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Exécute le peuplement de la base de données.
     * Enregistre les permissions, crée les rôles standard et le compte Super Admin.
     *
     * @return void
     */
    public function run(): void
    {
        // Réinitialiser le cache des permissions et des rôles Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Définition des permissions des fonctionnalités actuellement implémentées
        $permissions = [
            // Module Utilisateurs & Profils
            'users.view' => 'web',
            'users.create' => 'web',
            'users.edit' => 'web',
            'users.block' => 'web',

            // Module Rôles & Permissions
            'roles.view' => 'web',
            'roles.create' => 'web',
            'roles.edit' => 'web',
            'roles.delete' => 'web',

            // Module Institution & Structure Pédagogique
            'institutions.view' => 'web',
            'institutions.edit' => 'web',
            'etablissements.manage' => 'web',
            'structures.manage' => 'web',
            'classes.manage' => 'web',

            // Module Périodes & Paramètres d'Évaluation (Gesco 3)
            'periodes.view' => 'web',
            'periodes.manage' => 'web',
            'periodes.lock' => 'web',

            // Module Matières & Coefficients (Gesco 4)
            'matieres.view' => 'web',
            'matieres.manage' => 'web',

            // Module Élèves & Inscriptions (Gesco 5)
            'eleves.view' => 'web',
            'eleves.create' => 'web',
            'eleves.edit' => 'web',
            'eleves.delete' => 'web',
            'inscriptions.manage' => 'web',
            'inscriptions.transfer' => 'web',
        ];

        // Création ou persistance des permissions actives
        foreach ($permissions as $name => $guardName) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guardName]);
        }

        // Nettoyage : suppression de toute permission non encore implémentée
        Permission::whereNotIn('name', array_keys($permissions))->delete();

        // 2. Création des rôles standard pour SYGES
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $directorRole = Role::firstOrCreate(['name' => 'directeur-etudes', 'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);
        $secretaryRole = Role::firstOrCreate(['name' => 'secretaire', 'guard_name' => 'web']);

        // Attribution des permissions par rôle
        // Le super-admin reçoit l'ensemble des permissions existantes
        $superAdminRole->syncPermissions(Permission::all());

        // L'administrateur dispose de toutes les permissions actives
        $adminRole->syncPermissions(Permission::all());

        // Les rôles métiers reçoivent leurs permissions respectives
        $directorRole->syncPermissions([
            'institutions.view', 'etablissements.manage', 'structures.manage', 'classes.manage',
            'periodes.view', 'periodes.manage', 'periodes.lock',
            'matieres.view', 'matieres.manage',
            'eleves.view', 'eleves.create', 'eleves.edit', 'inscriptions.manage', 'inscriptions.transfer',
        ]);
        $teacherRole->syncPermissions([
            'matieres.view',
            'eleves.view',
        ]);
        $secretaryRole->syncPermissions([
            'users.view', 'users.create', 'users.edit', 'institutions.view', 'periodes.view', 'matieres.view',
            'eleves.view', 'eleves.create', 'eleves.edit', 'inscriptions.manage',
        ]);

        // 3. Création ou mise à jour du Super Administrateur principal
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Super Admin',
                'login' => 'admin',
                'is_active' => true,
                'password' => Hash::make('admin@'),
                'email_verified_at' => now(),
            ]
        );

        if (empty($superAdmin->login) || ! isset($superAdmin->is_active)) {
            $superAdmin->login = 'admin';
            $superAdmin->is_active = true;
            $superAdmin->save();
        }

        // Attribution du rôle super-admin
        if (! $superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole($superAdminRole);
        }

        // 4. Peuplement de l'Institution faîtière unique et de son arborescence scolaire
        $institution = \App\Models\Institution::firstOrCreate(
            ['code' => 'GSJF'],
            [
                'nom' => 'GROUPE SCOLAIRE BILINGUE SAINT JOSEPH',
                'nom_en' => 'SAINT JOSEPH BILINGUAL SCHOOL GROUP',
                'sigle' => 'GSJF',
                'devise' => 'Discipline - Travail - Succès',
                'devise_en' => 'Discipline - Hard Work - Success',
                'email' => 'contact@saintjoseph-bilingual.org',
                'telephone_1' => '+237 699 00 11 22',
                'telephone_2' => '+237 677 33 44 55',
                'boite_postale' => 'B.P. 1045 Yaoundé',
                'site_web' => 'https://saintjoseph-bilingual.org',
                'ville' => 'Yaoundé',
                'region' => 'Centre',
                'region_en' => 'Center',
                'departement' => 'Mfoundi',
                'departement_en' => 'Mfoundi',
                'delegation_regionale' => 'Délégation Régionale des Enseignements Secondaires du Centre',
                'delegation_regionale_en' => 'Center Regional Delegation of Secondary Education',
                'delegation_departementale' => 'Délégation Départementale du Mfoundi',
                'delegation_departementale_en' => 'Mfoundi Divisional Delegation',
            ]
        );

        // Établissement 1 : Secondaire
        $college = \App\Models\Etablissement::firstOrCreate(
            ['code' => 'CSJB'],
            [
                'institution_id' => $institution->id,
                'nom' => 'Collège Saint Joseph Bilingue',
                'nom_en' => 'Saint Joseph Bilingual College',
                'statut_juridique' => 'Prive_Confessionnel',
                'ordre_enseignement' => 'Secondaire_General',
                'decret_creation' => 'Arrêté N° 045/MINESEC/CAB du 12 Juillet 2010',
                'nom_chef_etablissement' => 'Mme Ndongo Célestine (Principale)',
                'email' => 'college@saintjoseph-bilingual.org',
                'telephone_1' => '+237 699 00 11 23',
                'adresse' => 'Quartier Bastos, Yaoundé',
            ]
        );

        // Section Francophone du Collège
        $sectionFr = \App\Models\Section::firstOrCreate(
            ['etablissement_id' => $college->id, 'code' => 'FR'],
            [
                'nom' => 'Section Francophone',
                'nom_en' => 'Francophone Section',
                'langue' => 'fr',
            ]
        );

        // Cycles Francophones
        $cycle1 = \App\Models\Cycle::firstOrCreate(
            ['section_id' => $sectionFr->id, 'code' => 'CYC1-FR'],
            [
                'nom' => 'Premier Cycle',
                'nom_en' => 'First Cycle',
                'diplome_fin_cycle' => 'BEPC',
                'ordre' => 1,
            ]
        );

        $cycle2 = \App\Models\Cycle::firstOrCreate(
            ['section_id' => $sectionFr->id, 'code' => 'CYC2-FR'],
            [
                'nom' => 'Second Cycle',
                'nom_en' => 'Second Cycle',
                'diplome_fin_cycle' => 'BACCALAURÉAT',
                'ordre' => 2,
            ]
        );

        // Niveaux du 1er Cycle Francophone
        $niv6eme = \App\Models\Niveau::firstOrCreate(
            ['cycle_id' => $cycle1->id, 'code' => '6EME'],
            [
                'nom' => 'Sixième',
                'nom_en' => 'Form 1 (Equiv.)',
                'ordre' => 1,
            ]
        );

        $niv3eme = \App\Models\Niveau::firstOrCreate(
            ['cycle_id' => $cycle1->id, 'code' => '3EME'],
            [
                'nom' => 'Troisième',
                'nom_en' => 'Form 4 (Equiv.)',
                'ordre' => 4,
            ]
        );

        // Classes de 6ème et 3ème
        \App\Models\Classe::firstOrCreate(
            ['niveau_id' => $niv6eme->id, 'code' => '6M1'],
            [
                'nom' => '6ème Moderne 1',
                'serie' => 'Général',
                'capacite_max' => 50,
                'is_active' => true,
            ]
        );

        \App\Models\Classe::firstOrCreate(
            ['niveau_id' => $niv3eme->id, 'code' => '3ESP'],
            [
                'nom' => '3ème Espagnol',
                'serie' => 'Général',
                'capacite_max' => 45,
                'is_active' => true,
            ]
        );

        // 5. Peuplement de l'Année Scolaire Active, Trimestres et Séquences (Gesco 3)
        $annee = \App\Models\AnneeScolaire::firstOrCreate(
            ['code' => '2026-2027'],
            [
                'nom' => 'Année Scolaire 2026-2027',
                'nom_en' => 'Academic Year 2026-2027',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-06-30',
                'statut' => 'en_cours',
                'mode_calcul_moyenne' => 'arithmetique_simple',
                'is_current' => true,
            ]
        );

        // Trimestres standards
        $trimestresData = [
            [
                'code' => 'TRIM-1',
                'nom' => '1er Trimestre',
                'nom_en' => '1st Term',
                'ordre' => 1,
                'date_debut' => '2026-09-01',
                'date_fin' => '2026-11-30',
                'statut' => 'en_cours',
                'sequences' => [
                    ['code' => 'SEQ-1', 'nom' => 'Séquence 1', 'nom_en' => 'Sequence 1', 'ordre' => 1, 'ponderation' => 50.0, 'date_debut' => '2026-09-01', 'date_fin' => '2026-10-15', 'date_limite_saisie' => '2026-10-22'],
                    ['code' => 'SEQ-2', 'nom' => 'Séquence 2', 'nom_en' => 'Sequence 2', 'ordre' => 2, 'ponderation' => 50.0, 'date_debut' => '2026-10-16', 'date_fin' => '2026-11-30', 'date_limite_saisie' => '2026-12-05'],
                ],
            ],
            [
                'code' => 'TRIM-2',
                'nom' => '2ème Trimestre',
                'nom_en' => '2nd Term',
                'ordre' => 2,
                'date_debut' => '2026-12-01',
                'date_fin' => '2027-03-15',
                'statut' => 'a_venir',
                'sequences' => [
                    ['code' => 'SEQ-3', 'nom' => 'Séquence 3', 'nom_en' => 'Sequence 3', 'ordre' => 3, 'ponderation' => 50.0, 'date_debut' => '2026-12-01', 'date_fin' => '2027-01-31', 'date_limite_saisie' => '2027-02-07'],
                    ['code' => 'SEQ-4', 'nom' => 'Séquence 4', 'nom_en' => 'Sequence 4', 'ordre' => 4, 'ponderation' => 50.0, 'date_debut' => '2027-02-01', 'date_fin' => '2027-03-15', 'date_limite_saisie' => '2027-03-22'],
                ],
            ],
            [
                'code' => 'TRIM-3',
                'nom' => '3ème Trimestre',
                'nom_en' => '3rd Term',
                'ordre' => 3,
                'date_debut' => '2027-03-16',
                'date_fin' => '2027-06-30',
                'statut' => 'a_venir',
                'sequences' => [
                    ['code' => 'SEQ-5', 'nom' => 'Séquence 5', 'nom_en' => 'Sequence 5', 'ordre' => 5, 'ponderation' => 50.0, 'date_debut' => '2027-03-16', 'date_fin' => '2027-05-15', 'date_limite_saisie' => '2027-05-22'],
                    ['code' => 'SEQ-6', 'nom' => 'Séquence 6', 'nom_en' => 'Sequence 6', 'ordre' => 6, 'ponderation' => 50.0, 'date_debut' => '2027-05-16', 'date_fin' => '2027-06-30', 'date_limite_saisie' => '2027-07-05'],
                ],
            ],
        ];

        foreach ($trimestresData as $tData) {
            $sequences = $tData['sequences'];
            unset($tData['sequences']);

            $tData['annee_scolaire_id'] = $annee->id;
            $trimestre = \App\Models\Periode::firstOrCreate(
                ['annee_scolaire_id' => $annee->id, 'code' => $tData['code']],
                $tData
            );

            foreach ($sequences as $sData) {
                $sData['periode_id'] = $trimestre->id;
                \App\Models\Evaluation::firstOrCreate(
                    ['periode_id' => $trimestre->id, 'code' => $sData['code']],
                    $sData
                );
            }
        }

        // 6. Peuplement des Groupes de Matières (Blocs Pédagogiques MINESEC/MINEDUB - Gesco 4.1)
        $groupeScientifique = \App\Models\GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-SCI'],
            [
                'nom' => 'Enseignements Scientifiques & Technologiques',
                'nom_en' => 'Scientific & Technological Subjects',
                'couleur' => 'blue',
                'ordre' => 1,
            ]
        );

        $groupeLitteraire = \App\Models\GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-LIT'],
            [
                'nom' => 'Enseignements Littéraires & Sciences Humaines',
                'nom_en' => 'Literary & Human Sciences Subjects',
                'couleur' => 'emerald',
                'ordre' => 2,
            ]
        );

        $groupePratique = \App\Models\GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-DEV'],
            [
                'nom' => 'Développement Personnel, Arts & Éducation Physique',
                'nom_en' => 'Personal Development, Arts & Physical Education',
                'couleur' => 'amber',
                'ordre' => 3,
            ]
        );

        // 7. Peuplement du Catalogue Général des Matières (Gesco 4.2)
        $matieresData = [
            // Matières Scientifiques
            ['code' => 'MATH', 'nom' => 'Mathématiques', 'nom_en' => 'Mathematics', 'groupe_id' => $groupeScientifique->id],
            ['code' => 'PCT', 'nom' => 'Physique - Chimie - Technologie', 'nom_en' => 'Physics - Chemistry - Technology', 'groupe_id' => $groupeScientifique->id],
            ['code' => 'SVT', 'nom' => 'Sciences de la Vie et de la Terre', 'nom_en' => 'Life and Earth Sciences', 'groupe_id' => $groupeScientifique->id],
            ['code' => 'INFO', 'nom' => 'Informatique', 'nom_en' => 'Computer Science', 'groupe_id' => $groupeScientifique->id],

            // Matières Littéraires
            ['code' => 'FRAN', 'nom' => 'Langue Française', 'nom_en' => 'French Language', 'groupe_id' => $groupeLitteraire->id],
            ['code' => 'LIT', 'nom' => 'Littérature & Culture', 'nom_en' => 'Literature & Culture', 'groupe_id' => $groupeLitteraire->id],
            ['code' => 'ANG', 'nom' => 'Anglais', 'nom_en' => 'English Language', 'groupe_id' => $groupeLitteraire->id],
            ['code' => 'HIST-GEO', 'nom' => 'Histoire - Géographie - ECM', 'nom_en' => 'History - Geography - Civics', 'groupe_id' => $groupeLitteraire->id],

            // Matières Pratiques & Développement Personnel
            ['code' => 'EPS', 'nom' => 'Éducation Physique et Sportive', 'nom_en' => 'Physical & Sports Education', 'groupe_id' => $groupePratique->id],
            ['code' => 'ARTS', 'nom' => 'Éducation Artistique & Dessin', 'nom_en' => 'Artistic Education & Drawing', 'groupe_id' => $groupePratique->id],
            ['code' => 'TM', 'nom' => 'Travail Manuel & Économie Sociale', 'nom_en' => 'Manual Labor & Home Economics', 'groupe_id' => $groupePratique->id],
        ];

        $matieresMap = [];
        foreach ($matieresData as $mData) {
            $matiere = \App\Models\Matiere::firstOrCreate(
                ['code' => $mData['code']],
                [
                    'nom' => $mData['nom'],
                    'nom_en' => $mData['nom_en'],
                    'libelle_bulletin' => $mData['nom'],
                    'libelle_bulletin_en' => $mData['nom_en'],
                    'is_active' => true,
                ]
            );
            $matieresMap[$mData['code']] = [
                'model' => $matiere,
                'groupe_id' => $mData['groupe_id'],
            ];
        }

        // 8. Grille Standard des Coefficients pour la classe de 6ème (Gesco 4.3)
        $coefficients6eme = [
            'MATH' => ['coef' => 4.0, 'eliminatoire' => null, 'facultative' => false],
            'FRAN' => ['coef' => 5.0, 'eliminatoire' => null, 'facultative' => false],
            'ANG' => ['coef' => 3.0, 'eliminatoire' => null, 'facultative' => false],
            'SVT' => ['coef' => 2.0, 'eliminatoire' => null, 'facultative' => false],
            'HIST-GEO' => ['coef' => 2.0, 'eliminatoire' => null, 'facultative' => false],
            'INFO' => ['coef' => 2.0, 'eliminatoire' => null, 'facultative' => false],
            'EPS' => ['coef' => 2.0, 'eliminatoire' => null, 'facultative' => false],
            'ARTS' => ['coef' => 1.0, 'eliminatoire' => null, 'facultative' => true], // Facultatif bonus > 10
            'TM' => ['coef' => 1.0, 'eliminatoire' => null, 'facultative' => false],
        ];

        foreach ($coefficients6eme as $code => $config) {
            if (isset($matieresMap[$code])) {
                \App\Models\MatiereNiveau::firstOrCreate(
                    [
                        'niveau_id' => $niv6eme->id,
                        'matiere_id' => $matieresMap[$code]['model']->id,
                        'serie' => 'Toutes',
                    ],
                    [
                        'groupe_matiere_id' => $matieresMap[$code]['groupe_id'],
                        'coefficient' => $config['coef'],
                        'note_max' => 20.0,
                        'note_eliminatoire' => $config['eliminatoire'],
                        'is_facultative' => $config['facultative'],
                        'is_comptabilisee' => true,
                    ]
                );
            }
        }

        // 9. Grille Standard des Coefficients pour la classe de 3ème (Gesco 4.3)
        $coefficients3eme = [
            'MATH' => ['coef' => 4.0, 'eliminatoire' => 5.0, 'facultative' => false],
            'PCT' => ['coef' => 3.0, 'eliminatoire' => null, 'facultative' => false],
            'SVT' => ['coef' => 3.0, 'eliminatoire' => null, 'facultative' => false],
            'INFO' => ['coef' => 2.0, 'eliminatoire' => null, 'facultative' => false],
            'FRAN' => ['coef' => 5.0, 'eliminatoire' => 6.0, 'facultative' => false],
            'ANG' => ['coef' => 3.0, 'eliminatoire' => null, 'facultative' => false],
            'HIST-GEO' => ['coef' => 3.0, 'eliminatoire' => null, 'facultative' => false],
            'EPS' => ['coef' => 2.0, 'eliminatoire' => null, 'facultative' => false],
            'TM' => ['coef' => 1.0, 'eliminatoire' => null, 'facultative' => false],
        ];

        foreach ($coefficients3eme as $code => $config) {
            if (isset($matieresMap[$code])) {
                \App\Models\MatiereNiveau::firstOrCreate(
                    [
                        'niveau_id' => $niv3eme->id,
                        'matiere_id' => $matieresMap[$code]['model']->id,
                        'serie' => 'Toutes',
                    ],
                    [
                        'groupe_matiere_id' => $matieresMap[$code]['groupe_id'],
                        'coefficient' => $config['coef'],
                        'note_max' => 20.0,
                        'note_eliminatoire' => $config['eliminatoire'],
                        'is_facultative' => $config['facultative'],
                        'is_comptabilisee' => true,
                    ]
                );
            }
        }

        // 10. Peuplement d'élèves exemples et inscriptions (Module 5 Gesco / SAGEES)
        $classe6M1 = \App\Models\Classe::where('code', '6M1')->first();
        $classe3ESP = \App\Models\Classe::where('code', '3ESP')->first();

        $sampleEleves = [
            [
                'matricule' => 'GS26-0001',
                'matricule_national' => 'MIN-2026-67812',
                'nom' => 'KAMDEM',
                'prenom' => 'Joseph Fabrice',
                'sexe' => 'M',
                'date_naissance' => '2012-05-14',
                'lieu_naissance' => 'Douala',
                'nationalite' => 'CAMEROUNAISE',
                'telephone_parent' => '+237 699 11 22 33',
                'nom_pere' => 'KAMDEM Pierre',
                'nom_mere' => 'NGO NJOCK Marie',
                'adresse' => 'Bastos, Yaoundé',
                'groupe_sanguin' => 'O+',
                'classe_id' => $classe6M1?->id,
                'statut' => 'validee',
                'is_redoublant' => false,
            ],
            [
                'matricule' => 'GS26-0002',
                'matricule_national' => null,
                'nom' => 'FOTSO',
                'prenom' => 'Rachel Emilie',
                'sexe' => 'F',
                'date_naissance' => '2012-09-20',
                'lieu_naissance' => 'Bafoussam',
                'nationalite' => 'CAMEROUNAISE',
                'telephone_parent' => '+237 677 44 55 66',
                'nom_pere' => 'FOTSO André',
                'nom_mere' => 'MEKOU Brigitte',
                'adresse' => 'Omnisports, Yaoundé',
                'groupe_sanguin' => 'A+',
                'classe_id' => $classe6M1?->id,
                'statut' => 'validee',
                'is_redoublant' => false,
            ],
            [
                'matricule' => 'GS26-0003',
                'matricule_national' => null,
                'nom' => 'ABENA',
                'prenom' => 'Thierry Charles',
                'sexe' => 'M',
                'date_naissance' => '2011-11-03',
                'lieu_naissance' => 'Yaoundé',
                'nationalite' => 'CAMEROUNAISE',
                'telephone_parent' => '+237 691 23 45 67',
                'nom_pere' => 'ABENA François',
                'nom_mere' => 'ONANA Clarisse',
                'adresse' => 'Mvan, Yaoundé',
                'groupe_sanguin' => 'B+',
                'classe_id' => $classe6M1?->id,
                'statut' => 'en_attente',
                'is_redoublant' => true,
            ],
            [
                'matricule' => 'GS26-0004',
                'matricule_national' => 'MIN-2026-99014',
                'nom' => 'NDONGO',
                'prenom' => 'Ariane Sandra',
                'sexe' => 'F',
                'date_naissance' => '2009-08-15',
                'lieu_naissance' => 'Ebolowa',
                'nationalite' => 'CAMEROUNAISE',
                'telephone_parent' => '+237 655 88 99 00',
                'nom_pere' => 'NDONGO Marc',
                'nom_mere' => 'EBALE Jeanne',
                'adresse' => 'Biyem-Assi, Yaoundé',
                'groupe_sanguin' => 'O-',
                'classe_id' => $classe3ESP?->id,
                'statut' => 'validee',
                'is_redoublant' => false,
            ],
            [
                'matricule' => 'GS26-0005',
                'matricule_national' => 'MIN-2026-88123',
                'nom' => 'MOUKOKO',
                'prenom' => 'Samuel Junior',
                'sexe' => 'M',
                'date_naissance' => '2009-03-28',
                'lieu_naissance' => 'Douala',
                'nationalite' => 'CAMEROUNAISE',
                'telephone_parent' => '+237 680 77 11 44',
                'nom_pere' => 'MOUKOKO Henri',
                'nom_mere' => 'EPANYA Sophie',
                'adresse' => 'Mendong, Yaoundé',
                'groupe_sanguin' => 'AB+',
                'classe_id' => $classe3ESP?->id,
                'statut' => 'validee',
                'is_redoublant' => false,
            ],
        ];

        foreach ($sampleEleves as $data) {
            $classeId = $data['classe_id'];
            $statut = $data['statut'];
            $isRedoublant = $data['is_redoublant'];
            unset($data['classe_id'], $data['statut'], $data['is_redoublant']);

            $eleve = \App\Models\Eleve::firstOrCreate(
                ['matricule' => $data['matricule']],
                $data
            );

            if ($classeId && $annee) {
                \App\Models\Inscription::firstOrCreate(
                    [
                        'eleve_id' => $eleve->id,
                        'annee_scolaire_id' => $annee->id,
                    ],
                    [
                        'classe_id' => $classeId,
                        'type_inscription' => 'nouveau',
                        'is_redoublant' => $isRedoublant,
                        'statut' => $statut,
                        'date_inscription' => '2026-09-02',
                    ]
                );
            }
        }

        // 11. Exécution du Seeder de Démonstration Académique Avancée (Enseignants, Attributions, Notes APC, Bulletins)
        $this->call(DemoAcademicSeeder::class);
    }
}

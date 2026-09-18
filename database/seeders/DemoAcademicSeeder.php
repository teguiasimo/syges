<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Attribution;
use App\Models\Classe;
use App\Models\Cycle;
use App\Models\Departement;
use App\Models\DisciplineBulletin;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\GroupeMatiere;
use App\Models\Inscription;
use App\Models\Institution;
use App\Models\Matiere;
use App\Models\MatiereNiveau;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Personnel;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder de Démonstration Académique et Pédagogique Complète pour SYGES.
 *
 * Conforme au Cahier des Charges Gesco V1.1 et aux normes MINESEC Cameroun (APC) :
 * - Configure les rôles (super-admin, enseignant, directeur-etudes) et leurs permissions réelles.
 * - Initialise une structure éducative d'élite : Institution, Établissement (Collège Bilingue),
 *   Sections, Cycles, Niveaux et Classes (dont la 6ème Moderne 1 - 6M1).
 * - Crée 5 comptes enseignants réels avec logins et mots de passe connus pour la simulation.
 * - Répartit la grille d'attributions de cours complète pour la classe de 6M1 avec désignation du Professeur Principal.
 * - Inscrit 8 élèves représentatifs aux profils académiques variés (brillants, moyens, fragiles, redoublants).
 * - Enregistre l'ensemble des notes et compétences APC sur les 6 séquences (Séquence 1 à 6).
 * - Enregistre les synthèses disciplinaires trimestrielles et avis du conseil de classe.
 *
 * Permet de tester instantanément :
 * 1. La connexion en tant qu'enseignant (ex: M. TCHOUA) pour voir sa classe et saisir/consulter ses notes.
 * 2. La génération des Bulletins Séquentiels (SEQ-1, etc.).
 * 3. La génération des Bulletins Trimestriels (TRIM-1, TRIM-2, TRIM-3).
 * 4. La génération du Bulletin Annuel Récapitulatif avec MGA, rangs et décision finale du conseil de classe.
 */
class DemoAcademicSeeder extends Seeder
{
    /**
     * Exécute le peuplement académique complet pour la démonstration.
     *
     * @return void
     */
    public function run(): void
    {
        // 1. Réinitialisation et synchronisation des rôles et permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $directorRole = Role::firstOrCreate(['name' => 'directeur-etudes', 'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);

        $allPermissions = [
            'users.view', 'users.create', 'users.edit', 'users.block',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'institutions.view', 'institutions.edit', 'etablissements.manage', 'structures.manage', 'classes.manage',
            'periodes.view', 'periodes.manage', 'periodes.lock',
            'matieres.view', 'matieres.manage',
            'eleves.view', 'eleves.create', 'eleves.edit', 'eleves.delete', 'inscriptions.manage', 'inscriptions.transfer',
            'personnels.view', 'personnels.manage', 'personnels.delete', 'departements.manage',
            'attributions.view', 'attributions.manage', 'attributions.delete',
            'notes.view', 'notes.enter', 'notes.lock',
            'bulletins.view', 'bulletins.generate',
        ];

        foreach ($allPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        $superAdminRole->syncPermissions(Permission::all());
        $adminRole->syncPermissions(Permission::all());

        $directorRole->syncPermissions([
            'institutions.view', 'etablissements.manage', 'structures.manage', 'classes.manage',
            'periodes.view', 'periodes.manage', 'periodes.lock',
            'matieres.view', 'matieres.manage',
            'eleves.view', 'eleves.create', 'eleves.edit', 'inscriptions.manage', 'inscriptions.transfer',
            'personnels.view', 'departements.manage',
            'attributions.view', 'attributions.manage',
            'notes.view', 'notes.enter', 'notes.lock',
            'bulletins.view', 'bulletins.generate',
        ]);

        // Permissions précises pour les enseignants (accès aux notes, classes, attributions)
        $teacherRole->syncPermissions([
            'matieres.view',
            'eleves.view',
            'periodes.view',
            'attributions.view',
            'notes.view',
            'notes.enter',
        ]);

        // 2. Institution et Établissement de référence
        $institution = Institution::firstOrCreate(
            ['code' => 'GSJF'],
            [
                'nom' => 'GROUPE SCOLAIRE BILINGUE SAINT JOSEPH',
                'nom_en' => 'SAINT JOSEPH BILINGUAL SCHOOL GROUP',
                'sigle' => 'GSJF',
                'devise' => 'Discipline - Travail - Succès',
                'devise_en' => 'Discipline - Hard Work - Success',
                'email' => 'contact@saintjoseph-bilingual.org',
                'telephone_1' => '+237 699 00 11 22',
                'boite_postale' => 'B.P. 1045 Yaoundé',
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

        $college = Etablissement::firstOrCreate(
            ['code' => 'CSJB'],
            [
                'institution_id' => $institution->id,
                'nom' => 'Collège Saint Joseph Bilingue',
                'nom_en' => 'Saint Joseph Bilingual College',
                'statut_juridique' => 'Prive_Confessionnel',
                'mode_bulletin_defaut' => 'sans_competences',
                'type_calcul_trimestre' => 'arithmetique',
                'ordre_enseignement' => 'Secondaire_General',
                'nom_chef_etablissement' => 'Mme Ndongo Célestine (Principale)',
                'email' => 'college@saintjoseph-bilingual.org',
                'telephone_1' => '+237 699 00 11 23',
                'adresse' => 'Quartier Bastos, Yaoundé',
            ]
        );

        // Mise à jour de sécurité des options de bulletin
        $college->mode_bulletin_defaut = 'sans_competences';
        $college->type_calcul_trimestre = 'arithmetique';
        $college->save();

        // 3. Sections, Cycles, Niveaux et Classes
        $sectionFr = Section::firstOrCreate(
            ['etablissement_id' => $college->id, 'code' => 'FR'],
            ['nom' => 'Section Francophone', 'nom_en' => 'Francophone Section', 'langue' => 'fr']
        );

        $cycle1 = Cycle::where('section_id', $sectionFr->id)
            ->where('nom', 'Premier Cycle')
            ->first() ?? Cycle::create([
                'section_id' => $sectionFr->id,
                'etablissement_id' => $college->id,
                'code' => 'CYC1',
                'nom' => 'Premier Cycle',
                'nom_en' => 'First Cycle',
                'diplome_fin_cycle' => 'BEPC',
                'ordre' => 1,
            ]);

        $niv6eme = Niveau::firstOrCreate(
            ['code' => '6EME'],
            ['cycle_id' => $cycle1->id, 'nom' => 'Sixième', 'nom_en' => 'Form 1 (Equiv.)', 'ordre' => 1]
        );

        $classe6M1 = Classe::firstOrCreate(
            ['code' => '6M1'],
            ['niveau_id' => $niv6eme->id, 'nom' => '6ème Moderne 1', 'serie' => 'Général', 'capacite_max' => 50, 'is_active' => true]
        );

        // Consolidation : s'assurer qu'il n'existe qu'une seule classe 6M1 active
        $autresClasses6M1 = Classe::where('code', '6M1')->where('id', '!=', $classe6M1->id)->get();
        foreach ($autresClasses6M1 as $doublon) {
            Inscription::where('classe_id', $doublon->id)->update(['classe_id' => $classe6M1->id]);
            Attribution::where('classe_id', $doublon->id)->update(['classe_id' => $classe6M1->id]);
            $doublon->delete();
        }

        // 4. Année Scolaire 2026-2027, Trimestres et Séquences
        $annee = AnneeScolaire::firstOrCreate(
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
        $annee->is_current = true;
        $annee->save();

        $trimestresConfig = [
            [
                'code' => 'TRIM-1', 'nom' => '1er Trimestre', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-11-30', 'statut' => 'en_cours',
                'sequences' => [
                    ['code' => 'SEQ-1', 'nom' => 'Séquence 1', 'ordre' => 1, 'ponderation' => 50.0],
                    ['code' => 'SEQ-2', 'nom' => 'Séquence 2', 'ordre' => 2, 'ponderation' => 50.0],
                ],
            ],
            [
                'code' => 'TRIM-2', 'nom' => '2ème Trimestre', 'ordre' => 2, 'date_debut' => '2026-12-01', 'date_fin' => '2027-03-15', 'statut' => 'a_venir',
                'sequences' => [
                    ['code' => 'SEQ-3', 'nom' => 'Séquence 3', 'ordre' => 3, 'ponderation' => 50.0],
                    ['code' => 'SEQ-4', 'nom' => 'Séquence 4', 'ordre' => 4, 'ponderation' => 50.0],
                ],
            ],
            [
                'code' => 'TRIM-3', 'nom' => '3ème Trimestre', 'ordre' => 3, 'date_debut' => '2027-03-16', 'date_fin' => '2027-06-30', 'statut' => 'a_venir',
                'sequences' => [
                    ['code' => 'SEQ-5', 'nom' => 'Séquence 5', 'ordre' => 5, 'ponderation' => 50.0],
                    ['code' => 'SEQ-6', 'nom' => 'Séquence 6', 'ordre' => 6, 'ponderation' => 50.0],
                ],
            ],
        ];

        $evaluationsMap = [];
        foreach ($trimestresConfig as $tCfg) {
            $seqs = $tCfg['sequences'];
            unset($tCfg['sequences']);
            $tCfg['annee_scolaire_id'] = $annee->id;

            $trim = Periode::firstOrCreate(
                ['annee_scolaire_id' => $annee->id, 'code' => $tCfg['code']],
                $tCfg
            );

            foreach ($seqs as $sCfg) {
                $sCfg['periode_id'] = $trim->id;
                $eval = Evaluation::firstOrCreate(
                    ['periode_id' => $trim->id, 'code' => $sCfg['code']],
                    $sCfg
                );
                $evaluationsMap[$sCfg['code']] = $eval;
            }
        }

        // 5. Groupes de matières (Blocs pédagogiques MINESEC)
        $groupeSci = GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-SCI'],
            ['nom' => 'Enseignements Scientifiques & Technologiques', 'couleur' => 'blue', 'ordre' => 1]
        );
        $groupeLit = GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-LIT'],
            ['nom' => 'Enseignements Littéraires & Sciences Humaines', 'couleur' => 'emerald', 'ordre' => 2]
        );
        $groupeDev = GroupeMatiere::firstOrCreate(
            ['code' => 'BLOC-DEV'],
            ['nom' => 'Développement Personnel, Arts & EPS', 'couleur' => 'amber', 'ordre' => 3]
        );

        // 6. Catalogue de matières & Coefficients pour la 6ème
        $matieresConfigs = [
            'MATH' => ['nom' => 'Mathématiques', 'groupe' => $groupeSci, 'coef' => 4.0, 'facultatif' => false],
            'INFO' => ['nom' => 'Informatique', 'groupe' => $groupeSci, 'coef' => 2.0, 'facultatif' => false],
            'SVT' => ['nom' => 'Sciences de la Vie et de la Terre', 'groupe' => $groupeSci, 'coef' => 2.0, 'facultatif' => false],
            'FRAN' => ['nom' => 'Langue Française', 'groupe' => $groupeLit, 'coef' => 5.0, 'facultatif' => false],
            'ANG' => ['nom' => 'Anglais', 'groupe' => $groupeLit, 'coef' => 3.0, 'facultatif' => false],
            'HIST-GEO' => ['nom' => 'Histoire - Géographie - ECM', 'groupe' => $groupeLit, 'coef' => 2.0, 'facultatif' => false],
            'EPS' => ['nom' => 'Éducation Physique et Sportive', 'groupe' => $groupeDev, 'coef' => 2.0, 'facultatif' => false],
            'ARTS' => ['nom' => 'Éducation Artistique & Dessin', 'groupe' => $groupeDev, 'coef' => 1.0, 'facultatif' => true],
            'TM' => ['nom' => 'Travail Manuel & Économie Sociale', 'groupe' => $groupeDev, 'coef' => 1.0, 'facultatif' => false],
        ];

        $matieresInstances = [];
        foreach ($matieresConfigs as $code => $cfg) {
            $mat = Matiere::firstOrCreate(
                ['code' => $code],
                [
                    'nom' => $cfg['nom'],
                    'libelle_bulletin' => $cfg['nom'],
                    'is_active' => true,
                ]
            );
            $matieresInstances[$code] = $mat;

            MatiereNiveau::firstOrCreate(
                [
                    'niveau_id' => $niv6eme->id,
                    'matiere_id' => $mat->id,
                    'serie' => 'Toutes',
                ],
                [
                    'groupe_matiere_id' => $cfg['groupe']->id,
                    'coefficient' => $cfg['coef'],
                    'note_max' => 20.0,
                    'is_facultative' => $cfg['facultatif'],
                    'is_comptabilisee' => true,
                ]
            );
        }

        // 7. Départements pédagogiques
        $deptMath = Departement::firstOrCreate(
            ['institution_id' => $institution->id, 'code' => 'MATH-INFO'],
            ['nom' => 'Département de Mathématiques & Informatique', 'is_active' => true]
        );
        $deptLettres = Departement::firstOrCreate(
            ['institution_id' => $institution->id, 'code' => 'LETTRES'],
            ['nom' => 'Département de Lettres & Langues', 'is_active' => true]
        );
        $deptSciences = Departement::firstOrCreate(
            ['institution_id' => $institution->id, 'code' => 'SCIENCES'],
            ['nom' => 'Département de Sciences Naturelles & EPS', 'is_active' => true]
        );
        $deptHumaines = Departement::firstOrCreate(
            ['institution_id' => $institution->id, 'code' => 'SC-HUM'],
            ['nom' => 'Département des Sciences Humaines', 'is_active' => true]
        );

        // 8. Création des Enseignants réels avec comptes utilisateurs
        $enseignantsData = [
            [
                'login' => 'tchoua',
                'email' => 'tchoua@syges.cm',
                'name' => 'M. TCHOUA Jean-Paul',
                'nom' => 'TCHOUA',
                'prenom' => 'Jean-Paul',
                'sexe' => 'M',
                'telephone' => '+237 670 11 22 33',
                'specialite' => 'Mathématiques & Informatique',
                'departement_id' => $deptMath->id,
                'volume_hebdo' => 18.0,
            ],
            [
                'login' => 'essomba',
                'email' => 'essomba@syges.cm',
                'name' => 'Mme ESSOMBA Marie-Thérèse',
                'nom' => 'ESSOMBA',
                'prenom' => 'Marie-Thérèse',
                'sexe' => 'F',
                'telephone' => '+237 699 22 33 44',
                'specialite' => 'Lettres Modernes Françaises',
                'departement_id' => $deptLettres->id,
                'volume_hebdo' => 18.0,
            ],
            [
                'login' => 'asonganyi',
                'email' => 'asonganyi@syges.cm',
                'name' => 'Mr. ASONGANYI Michael',
                'nom' => 'ASONGANYI',
                'prenom' => 'Michael',
                'sexe' => 'M',
                'telephone' => '+237 677 33 44 55',
                'specialite' => 'English Modern Letters',
                'departement_id' => $deptLettres->id,
                'volume_hebdo' => 16.0,
            ],
            [
                'login' => 'biya',
                'email' => 'biya@syges.cm',
                'name' => 'M. BIYA Alain Patrick',
                'nom' => 'BIYA',
                'prenom' => 'Alain Patrick',
                'sexe' => 'M',
                'telephone' => '+237 690 44 55 66',
                'specialite' => 'Sciences Naturelles & EPS',
                'departement_id' => $deptSciences->id,
                'volume_hebdo' => 18.0,
            ],
            [
                'login' => 'ngoyomb',
                'email' => 'ngoyomb@syges.cm',
                'name' => 'Mme NGO YOMB Anne',
                'nom' => 'NGO YOMB',
                'prenom' => 'Anne',
                'sexe' => 'F',
                'telephone' => '+237 655 55 66 77',
                'specialite' => 'Histoire - Géographie - ECM',
                'departement_id' => $deptHumaines->id,
                'volume_hebdo' => 16.0,
            ],
        ];

        $personnelsMap = [];
        foreach ($enseignantsData as $idx => $eData) {
            // Compte utilisateur avec mot de passe universel de test "password"
            $u = User::firstOrCreate(
                ['email' => $eData['email']],
                [
                    'name' => $eData['name'],
                    'login' => $eData['login'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $u->login = $eData['login'];
            $u->is_active = true;
            $u->save();

            if (! $u->hasRole('enseignant')) {
                $u->assignRole($teacherRole);
            }

            // Fiche personnel RH
            $matricule = 'PERS-2026-' . str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);
            $p = Personnel::firstOrCreate(
                ['email' => $eData['email']],
                [
                    'matricule' => $matricule,
                    'user_id' => $u->id,
                    'etablissement_id' => $college->id,
                    'departement_id' => $eData['departement_id'],
                    'nom' => $eData['nom'],
                    'prenom' => $eData['prenom'],
                    'sexe' => $eData['sexe'],
                    'telephone' => $eData['telephone'],
                    'fonction_principale' => 'Enseignant',
                    'type_contrat' => 'CDI (Contrat à Durée Indéterminée)',
                    'date_embauche' => '2026-09-01',
                    'statut' => 'Actif',
                    'is_enseignant' => true,
                    'specialite' => $eData['specialite'],
                    'volume_horaire_hebdo' => $eData['volume_hebdo'],
                ]
            );
            $p->user_id = $u->id;
            $p->save();

            $personnelsMap[$eData['login']] = $p;
        }

        // Définir M. TCHOUA Jean-Paul comme Professeur Principal de la 6M1
        $classe6M1->professeur_principal_id = $personnelsMap['tchoua']->id;
        $classe6M1->save();

        // 9. Attributions de cours pour la classe 6M1
        $attributionsPlan = [
            'MATH' => ['prof' => 'tchoua', 'heures' => 4.0],
            'INFO' => ['prof' => 'tchoua', 'heures' => 2.0],
            'TM' => ['prof' => 'tchoua', 'heures' => 1.0],
            'FRAN' => ['prof' => 'essomba', 'heures' => 5.0],
            'ARTS' => ['prof' => 'essomba', 'heures' => 1.0],
            'ANG' => ['prof' => 'asonganyi', 'heures' => 3.0],
            'SVT' => ['prof' => 'biya', 'heures' => 2.0],
            'EPS' => ['prof' => 'biya', 'heures' => 2.0],
            'HIST-GEO' => ['prof' => 'ngoyomb', 'heures' => 2.0],
        ];

        $attributionsMap = [];
        foreach ($attributionsPlan as $matiereCode => $attrInfo) {
            $prof = $personnelsMap[$attrInfo['prof']];
            $mat = $matieresInstances[$matiereCode];

            $attr = Attribution::firstOrCreate(
                [
                    'annee_scolaire_id' => $annee->id,
                    'classe_id' => $classe6M1->id,
                    'matiere_id' => $mat->id,
                ],
                [
                    'personnel_id' => $prof->id,
                    'volume_horaire_reel' => $attrInfo['heures'],
                    'is_titulaire' => true,
                    'is_active' => true,
                ]
            );
            $attributionsMap[$matiereCode] = $attr;
        }

        // 10. Élèves de la classe 6M1 (8 profils académiques contrastés)
        $elevesRoster = [
            [
                'matricule' => 'GS26-0001',
                'matricule_national' => 'MIN-2026-67812',
                'nom' => 'KAMDEM',
                'prenom' => 'Joseph Fabrice',
                'sexe' => 'M',
                'date_naissance' => '2012-05-14',
                'lieu_naissance' => 'Douala',
                'is_redoublant' => false,
                'profil' => 'brillant', // ~17.5/20 (Félicitations, CTBA)
            ],
            [
                'matricule' => 'GS26-0002',
                'matricule_national' => 'MIN-2026-67813',
                'nom' => 'FOTSO',
                'prenom' => 'Rachel Emilie',
                'sexe' => 'F',
                'date_naissance' => '2012-09-20',
                'lieu_naissance' => 'Bafoussam',
                'is_redoublant' => false,
                'profil' => 'tres_bon', // ~15.5/20 (Félicitations, CBA/CTBA)
            ],
            [
                'matricule' => 'GS26-0003',
                'matricule_national' => 'MIN-2026-67814',
                'nom' => 'MANGA',
                'prenom' => 'Danielle Christiane',
                'sexe' => 'F',
                'date_naissance' => '2012-03-11',
                'lieu_naissance' => 'Yaoundé',
                'is_redoublant' => false,
                'profil' => 'bon', // ~14.2/20 (Encouragements, CBA)
            ],
            [
                'matricule' => 'GS26-0004',
                'matricule_national' => 'MIN-2026-67815',
                'nom' => 'ABENA',
                'prenom' => 'Thierry Charles',
                'sexe' => 'M',
                'date_naissance' => '2011-11-03',
                'lieu_naissance' => 'Yaoundé',
                'is_redoublant' => false,
                'profil' => 'moyen', // ~12.2/20 (Tableau d'honneur, CMA)
            ],
            [
                'matricule' => 'GS26-0005',
                'matricule_national' => 'MIN-2026-67816',
                'nom' => 'BIKOI',
                'prenom' => 'Jean-Pierre',
                'sexe' => 'M',
                'date_naissance' => '2012-07-25',
                'lieu_naissance' => 'Edéa',
                'is_redoublant' => false,
                'profil' => 'passable', // ~10.5/20 (Admis, CMA)
            ],
            [
                'matricule' => 'GS26-0006',
                'matricule_national' => 'MIN-2026-67817',
                'nom' => 'ETOGO',
                'prenom' => 'Henriette',
                'sexe' => 'F',
                'date_naissance' => '2012-01-19',
                'lieu_naissance' => 'Mbalmayo',
                'is_redoublant' => false,
                'profil' => 'difficulte', // ~9.1/20 (Proposée redoublement, CNA/CMA)
            ],
            [
                'matricule' => 'GS26-0007',
                'matricule_national' => 'MIN-2026-67818',
                'nom' => 'BELA',
                'prenom' => 'Rodrigue',
                'sexe' => 'M',
                'date_naissance' => '2011-06-30',
                'lieu_naissance' => 'Sa\'a',
                'is_redoublant' => true,
                'profil' => 'fragile', // ~7.8/20 (Proposé redoublement, CNA)
            ],
            [
                'matricule' => 'GS26-0008',
                'matricule_national' => 'MIN-2026-67819',
                'nom' => 'NKOLO',
                'prenom' => 'Christian',
                'sexe' => 'M',
                'date_naissance' => '2011-12-05',
                'lieu_naissance' => 'Obala',
                'is_redoublant' => true,
                'profil' => 'echec', // ~5.4/20 (Non admis / Exclu, CNA)
            ],
        ];

        $inscriptionsInstances = [];
        foreach ($elevesRoster as $eR) {
            $isRedoublant = $eR['is_redoublant'];
            $profil = $eR['profil'];
            unset($eR['is_redoublant'], $eR['profil']);

            $eR['nationalite'] = 'CAMEROUNAISE';
            $eleve = Eleve::firstOrCreate(
                ['matricule' => $eR['matricule']],
                $eR
            );

            $insc = Inscription::updateOrCreate(
                [
                    'eleve_id' => $eleve->id,
                    'annee_scolaire_id' => $annee->id,
                ],
                [
                    'classe_id' => $classe6M1->id,
                    'type_inscription' => $isRedoublant ? 'reinscription' : 'nouveau',
                    'is_redoublant' => $isRedoublant,
                    'statut' => 'validee',
                    'date_inscription' => '2026-09-02',
                ]
            );
            $inscriptionsInstances[] = [
                'inscription' => $insc,
                'profil' => $profil,
            ];
        }

        // 11. Compétences APC par Matière et par Séquence
        $competencesCatalogue = [
            'MATH' => [
                'SEQ-1' => 'Calcul des périmètres de polygones et opérations arithmétiques de base',
                'SEQ-2' => 'Résolution de situations-problèmes concrètes avec proportionnalité',
                'SEQ-3' => 'Propriétés des droites sécantes, parallèles et constructions géométriques',
                'SEQ-4' => 'Opérations fondamentales sur les fractions et nombres décimaux',
                'SEQ-5' => 'Calcul d\'aires et de volumes de solides usuels de l\'espace',
                'SEQ-6' => 'Organisation et interprétation de séries de données statistiques simples',
            ],
            'FRAN' => [
                'SEQ-1' => 'Production d\'un récit narratif ordonné avec repères temporels',
                'SEQ-2' => 'Maîtrise des accords sujet-verbe et enrichissement lexical descriptif',
                'SEQ-3' => 'Rédaction d\'une lettre familière et respect des conventions épistolaires',
                'SEQ-4' => 'Analyse des valeurs temporelles du passé composé et de l\'imparfait',
                'SEQ-5' => 'Élaboration d\'un dialogue argumenté cohérent et expressif',
                'SEQ-6' => 'Synthèse textuelle et reformulation correcte d\'idées principales',
            ],
            'ANG' => [
                'SEQ-1' => 'Introducing oneself, greeting peers and describing daily routines',
                'SEQ-2' => 'Asking for and giving directions using spatial prepositions',
                'SEQ-3' => 'Describing family members, professions and expressing preferences',
                'SEQ-4' => 'Using past simple tense to recount a past event or holiday',
                'SEQ-5' => 'Discussing sports, hobbies and using frequency adverbs correctly',
                'SEQ-6' => 'Writing a short postcard describing school and living environment',
            ],
            'INFO' => [
                'SEQ-1' => 'Identification des composants matériels de l\'unité centrale et périphériques',
                'SEQ-2' => 'Gestion de fichiers, arborescence de dossiers et respect de l\'ergonomie',
                'SEQ-3' => 'Saisie et mise en forme basique d\'un document textuel sous traitement de texte',
                'SEQ-4' => 'Insertion d\'images et mise en page simple sous traitement de texte',
                'SEQ-5' => 'Navigation sécurisée sur Internet et recherche documentaire ciblée',
                'SEQ-6' => 'Sensibilisation aux bonnes pratiques de cybersécurité et protection des données',
            ],
            'SVT' => [
                'SEQ-1' => 'Schématisation de l\'appareil digestif humain et rôle des nutriments',
                'SEQ-2' => 'Caractérisation des étapes de la reproduction sexuée chez les végétaux',
                'SEQ-3' => 'Identification des différentes composantes d\'un écosystème forestier',
                'SEQ-4' => 'Analyse des mécanismes de respiration chez les animaux aquatiques et terrestres',
                'SEQ-5' => 'Sensibilisation aux maladies hydriques et principes d\'hygiène alimentaire',
                'SEQ-6' => 'Préservation de la biodiversité locale et lutte contre la déforestation',
            ],
            'HIST-GEO' => [
                'SEQ-1' => 'Localisation des grands ensembles de relief et de climats au Cameroun',
                'SEQ-2' => 'Origines et organisation sociopolitique des royaumes traditionnels africains',
                'SEQ-3' => 'Analyse des facteurs hydrographiques et des bassins fluviaux nationaux',
                'SEQ-4' => 'Étude des migrations historiques des peuples en Afrique Centrale',
                'SEQ-5' => 'Civisme : symboles de la République et institutions démocratiques',
                'SEQ-6' => 'Gestion durable des ressources naturelles et protection de l\'environnement',
            ],
            'EPS' => [
                'SEQ-1' => 'Maîtrise de la course de vitesse sur 60 mètres et départ en starting-blocks',
                'SEQ-2' => 'Exécution technique du saut en longueur avec impulsion contrôlée',
                'SEQ-3' => 'Pratique des passes et déplacements coordonnés en sports collectifs',
                'SEQ-4' => 'Endurance aérobie et gestion du rythme cardiaque pendant l\'effort',
                'SEQ-5' => 'Gymnastique au sol : enchaînement de figures simples et équilibre',
                'SEQ-6' => 'Respect des règles de fair-play, de l\'arbitrage et de la sécurité collective',
            ],
            'ARTS' => [
                'SEQ-1' => 'Dessin d\'observation avec respect de la perspective et des ombres',
                'SEQ-2' => 'Mélange harmonieux des couleurs primaires et secondaires à la gouache',
                'SEQ-3' => 'Initiation aux motifs géométriques et symboliques de l\'art africain',
                'SEQ-4' => 'Modelage à l\'argile d\'une poterie ou d\'un masque rituel',
                'SEQ-5' => 'Chant choral et reproduction de rythmes musicaux traditionnels',
                'SEQ-6' => 'Création d\'une affiche illustrative sur un thème de santé publique',
            ],
            'TM' => [
                'SEQ-1' => 'Confection d\'un objet utilitaire à base de bambou ou rotin local',
                'SEQ-2' => 'Rapiéçage textile, couture de boutons et points d\'aiguille élémentaires',
                'SEQ-3' => 'Principes d\'entretien d\'un potager scolaire et arrosage écologique',
                'SEQ-4' => 'Transformation locale d\'aliments et techniques traditionnelles de séchage',
                'SEQ-5' => 'Sensibilisation au recyclage créatif des déchets plastiques ménagers',
                'SEQ-6' => 'Assemblage de petites maquettes en bois et respect des consignes d\'outillage',
            ],
        ];

        // 12. Grille de notes types selon le profil de l'élève
        $profilNotesMap = [
            'brillant' => [
                'MATH' => [18.0, 18.5, 17.5, 19.0, 18.0, 19.5],
                'INFO' => [19.0, 19.5, 18.5, 19.0, 19.5, 20.0],
                'TM' => [16.0, 16.5, 17.0, 16.5, 17.0, 17.5],
                'FRAN' => [16.5, 17.0, 16.0, 17.5, 17.0, 18.0],
                'ARTS' => [17.0, 17.5, 18.0, 17.5, 18.0, 18.5],
                'ANG' => [17.5, 18.0, 17.0, 18.5, 18.0, 19.0],
                'SVT' => [18.0, 17.5, 18.5, 18.0, 19.0, 18.5],
                'EPS' => [16.0, 16.5, 17.0, 16.0, 17.0, 16.5],
                'HIST-GEO' => [17.0, 17.5, 16.5, 18.0, 17.5, 18.5],
            ],
            'tres_bon' => [
                'MATH' => [15.5, 16.0, 15.0, 16.5, 15.5, 16.0],
                'INFO' => [16.0, 17.0, 16.5, 17.0, 16.5, 17.5],
                'TM' => [15.0, 15.5, 16.0, 15.0, 15.5, 16.0],
                'FRAN' => [15.0, 15.5, 14.5, 16.0, 15.0, 15.5],
                'ARTS' => [16.0, 16.5, 17.0, 16.5, 16.0, 17.0],
                'ANG' => [16.0, 16.5, 15.5, 17.0, 16.0, 16.5],
                'SVT' => [15.5, 16.0, 15.0, 16.5, 15.5, 16.0],
                'EPS' => [15.0, 15.5, 16.0, 15.5, 16.0, 15.5],
                'HIST-GEO' => [15.0, 15.5, 14.5, 16.0, 15.5, 16.0],
            ],
            'bon' => [
                'MATH' => [13.5, 14.0, 14.5, 14.0, 14.5, 15.0],
                'INFO' => [15.0, 15.5, 14.5, 15.0, 15.5, 16.0],
                'TM' => [14.0, 14.5, 14.0, 14.5, 15.0, 14.5],
                'FRAN' => [14.0, 14.5, 13.5, 14.0, 14.5, 15.0],
                'ARTS' => [15.0, 15.5, 16.0, 15.0, 15.5, 16.0],
                'ANG' => [14.5, 15.0, 14.0, 14.5, 15.0, 15.5],
                'SVT' => [14.0, 14.5, 13.5, 14.5, 14.0, 14.5],
                'EPS' => [14.5, 15.0, 15.5, 14.5, 15.0, 15.0],
                'HIST-GEO' => [13.5, 14.0, 14.5, 13.5, 14.0, 14.5],
            ],
            'moyen' => [
                'MATH' => [11.5, 12.0, 12.5, 11.5, 12.0, 12.5],
                'INFO' => [13.0, 13.5, 12.5, 13.0, 13.5, 14.0],
                'TM' => [12.0, 12.5, 13.0, 12.0, 12.5, 13.0],
                'FRAN' => [12.0, 12.5, 11.5, 12.0, 12.5, 13.0],
                'ARTS' => [14.0, 14.5, 14.0, 14.5, 15.0, 14.5],
                'ANG' => [11.5, 12.0, 12.5, 11.5, 12.0, 12.5],
                'SVT' => [12.0, 12.5, 11.5, 12.0, 12.5, 13.0],
                'EPS' => [13.0, 13.5, 14.0, 13.0, 13.5, 14.0],
                'HIST-GEO' => [11.5, 12.0, 12.5, 11.5, 12.0, 12.5],
            ],
            'passable' => [
                'MATH' => [10.0, 10.5, 10.0, 11.0, 10.5, 11.0],
                'INFO' => [11.5, 12.0, 11.0, 11.5, 12.0, 12.5],
                'TM' => [11.0, 11.5, 11.0, 11.5, 12.0, 11.5],
                'FRAN' => [10.5, 10.0, 10.5, 11.0, 10.5, 11.0],
                'ARTS' => [12.0, 12.5, 13.0, 12.0, 12.5, 13.0],
                'ANG' => [9.5, 10.0, 10.5, 10.0, 10.5, 11.0],
                'SVT' => [10.0, 10.5, 9.5, 10.5, 10.0, 10.5],
                'EPS' => [12.0, 12.5, 13.0, 12.0, 12.5, 13.0],
                'HIST-GEO' => [10.0, 10.5, 9.5, 10.5, 10.0, 10.5],
            ],
            'difficulte' => [
                'MATH' => [8.5, 9.0, 8.5, 9.5, 9.0, 9.5],
                'INFO' => [10.0, 10.5, 9.5, 10.0, 10.5, 11.0],
                'TM' => [10.0, 10.5, 10.0, 10.5, 11.0, 10.5],
                'FRAN' => [9.0, 8.5, 9.0, 9.5, 9.0, 9.5],
                'ARTS' => [11.0, 11.5, 12.0, 11.0, 11.5, 12.0],
                'ANG' => [8.5, 9.0, 8.0, 9.0, 8.5, 9.0],
                'SVT' => [9.0, 8.5, 9.0, 9.5, 9.0, 9.5],
                'EPS' => [11.0, 11.5, 12.0, 11.0, 11.5, 12.0],
                'HIST-GEO' => [8.5, 9.0, 8.5, 9.0, 9.5, 9.0],
            ],
            'fragile' => [
                'MATH' => [7.0, 7.5, 8.0, 7.5, 8.0, 8.5],
                'INFO' => [8.5, 9.0, 8.0, 8.5, 9.0, 9.5],
                'TM' => [9.0, 9.5, 9.0, 9.5, 10.0, 9.5],
                'FRAN' => [7.5, 8.0, 7.5, 8.0, 8.5, 8.0],
                'ARTS' => [10.0, 10.5, 11.0, 10.0, 10.5, 11.0],
                'ANG' => [7.0, 7.5, 7.0, 7.5, 8.0, 7.5],
                'SVT' => [7.5, 8.0, 7.5, 8.0, 8.5, 8.0],
                'EPS' => [10.5, 11.0, 11.5, 10.5, 11.0, 11.5],
                'HIST-GEO' => [7.0, 7.5, 7.5, 8.0, 8.0, 8.5],
            ],
            'echec' => [
                'MATH' => [4.5, 5.0, 4.0, 5.5, 5.0, 5.5],
                'INFO' => [6.0, 6.5, 5.5, 6.0, 6.5, 7.0],
                'TM' => [7.5, 8.0, 7.0, 7.5, 8.0, 7.5],
                'FRAN' => [5.0, 5.5, 4.5, 5.5, 5.0, 6.0],
                'ARTS' => [8.0, 8.5, 9.0, 8.0, 8.5, 9.0],
                'ANG' => [4.5, 5.0, 4.0, 5.0, 4.5, 5.0],
                'SVT' => [5.5, 5.0, 5.5, 6.0, 5.5, 6.0],
                'EPS' => [9.0, 9.5, 10.0, 9.0, 9.5, 9.5],
                'HIST-GEO' => [5.0, 5.5, 4.5, 5.0, 5.5, 6.0],
            ],
        ];

        $sequenceCodes = ['SEQ-1', 'SEQ-2', 'SEQ-3', 'SEQ-4', 'SEQ-5', 'SEQ-6'];

        // Enregistrement des notes pour tous les élèves, toutes les matières et toutes les séquences
        foreach ($inscriptionsInstances as $item) {
            $insc = $item['inscription'];
            $profil = $item['profil'];
            $grilleEleve = $profilNotesMap[$profil];

            foreach ($attributionsMap as $matiereCode => $attr) {
                $notesSequence = $grilleEleve[$matiereCode];

                foreach ($sequenceCodes as $seqIdx => $seqCode) {
                    $eval = $evaluationsMap[$seqCode];
                    $valeurNote = $notesSequence[$seqIdx];
                    $competence = $competencesCatalogue[$matiereCode][$seqCode] ?? 'Acquisition standard';

                    // Appréciation qualitative adaptée
                    $appreciation = match (true) {
                        $valeurNote >= 16.0 => 'Excellent travail, rigueur remarquable.',
                        $valeurNote >= 14.0 => 'Très bon ensemble, travail régulier et soutenu.',
                        $valeurNote >= 12.0 => 'Bon travail, continuez avec cette régularité.',
                        $valeurNote >= 10.0 => 'Travail convenable, consolider les notions clés.',
                        $valeurNote >= 8.0 => 'Résultats insuffisants, intensifier les révisions.',
                        default => 'Niveau très faible, lacunes importantes à combler.',
                    };

                    Note::updateOrCreate(
                        [
                            'inscription_id' => $insc->id,
                            'attribution_id' => $attr->id,
                            'evaluation_id' => $eval->id,
                        ],
                        [
                            'valeur_note' => $valeurNote,
                            'statut_presence' => 'present',
                            'appreciation' => $appreciation,
                            'competence_evaluee' => $competence,
                            'cote' => Note::determinerCoteAPC($valeurNote),
                            'is_verrouillee' => false,
                            'saisie_par_user_id' => $attr->personnel->user_id,
                        ]
                    );
                }
            }
        }

        // 13. Données disciplinaires pour les 3 trimestres
        $disciplineProfils = [
            'brillant' => [
                'TRIM-1' => ['abs_just' => 0, 'abs_non_just' => 0, 'retards' => 0, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Félicitations du Conseil pour une assiduité et des résultats remarquables.'],
                'TRIM-2' => ['abs_just' => 0, 'abs_non_just' => 0, 'retards' => 0, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Élève exemplaire, moteur pour la classe.'],
                'TRIM-3' => ['abs_just' => 0, 'abs_non_just' => 0, 'retards' => 0, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Année remarquable. Toutes nos félicitations.'],
            ],
            'tres_bon' => [
                'TRIM-1' => ['abs_just' => 2, 'abs_non_just' => 0, 'retards' => 0, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Très bonne conduite, élève studieuse et appliquée.'],
                'TRIM-2' => ['abs_just' => 0, 'abs_non_just' => 0, 'retards' => 1, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Très bon trimestre, poursuivez sur cette lancée.'],
                'TRIM-3' => ['abs_just' => 1, 'abs_non_just' => 0, 'retards' => 0, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Excellente année scolaire.'],
            ],
            'bon' => [
                'TRIM-1' => ['abs_just' => 0, 'abs_non_just' => 0, 'retards' => 1, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Bon trimestre, attitude positive et travailleur.'],
                'TRIM-2' => ['abs_just' => 2, 'abs_non_just' => 0, 'retards' => 0, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Travail sérieux et régulier.'],
                'TRIM-3' => ['abs_just' => 0, 'abs_non_just' => 0, 'retards' => 1, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Bilan annuel très positif.'],
            ],
            'moyen' => [
                'TRIM-1' => ['abs_just' => 2, 'abs_non_just' => 2, 'retards' => 2, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Ensemble convenable, participation active en classe.'],
                'TRIM-2' => ['abs_just' => 4, 'abs_non_just' => 1, 'retards' => 1, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Trimestre correct, veiller à la ponctualité.'],
                'TRIM-3' => ['abs_just' => 2, 'abs_non_just' => 0, 'retards' => 2, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Travail convenable sur l\'ensemble de l\'année.'],
            ],
            'passable' => [
                'TRIM-1' => ['abs_just' => 0, 'abs_non_just' => 4, 'retards' => 3, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Attention aux bavardages et aux retards répétés.'],
                'TRIM-2' => ['abs_just' => 1, 'abs_non_just' => 3, 'retards' => 2, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Passable, doit s\'investir davantage pour progresser.'],
                'TRIM-3' => ['abs_just' => 0, 'abs_non_just' => 2, 'retards' => 3, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Admis de justesse, redoubler d\'efforts en 5ème.'],
            ],
            'difficulte' => [
                'TRIM-1' => ['abs_just' => 2, 'abs_non_just' => 6, 'retards' => 4, 'avert' => 1, 'blames' => 0, 'excl' => 0, 'avis' => 'Avertissement de conduite : retards trop fréquents.'],
                'TRIM-2' => ['abs_just' => 4, 'abs_non_just' => 4, 'retards' => 3, 'avert' => 0, 'blames' => 0, 'excl' => 0, 'avis' => 'Légère amélioration de la conduite mais travail insuffisant.'],
                'TRIM-3' => ['abs_just' => 2, 'abs_non_just' => 6, 'retards' => 4, 'avert' => 1, 'blames' => 0, 'excl' => 0, 'avis' => 'Niveau global trop juste pour la classe supérieure.'],
            ],
            'fragile' => [
                'TRIM-1' => ['abs_just' => 0, 'abs_non_just' => 10, 'retards' => 6, 'avert' => 1, 'blames' => 1, 'excl' => 0, 'avis' => 'Blâme de conduite : absentéisme injustifié préoccupant.'],
                'TRIM-2' => ['abs_just' => 2, 'abs_non_just' => 8, 'retards' => 5, 'avert' => 1, 'blames' => 0, 'excl' => 0, 'avis' => 'Manque d\'implication flagrant dans les matières fondamentales.'],
                'TRIM-3' => ['abs_just' => 0, 'abs_non_just' => 12, 'retards' => 7, 'avert' => 1, 'blames' => 1, 'excl' => 0, 'avis' => 'Redoublement indispensable pour acquérir les prérequis.'],
            ],
            'echec' => [
                'TRIM-1' => ['abs_just' => 0, 'abs_non_just' => 20, 'retards' => 10, 'avert' => 2, 'blames' => 1, 'excl' => 2, 'avis' => 'Comportement indiscipliné, absentéisme grave. Exclusion temporaire 2j.'],
                'TRIM-2' => ['abs_just' => 2, 'abs_non_just' => 18, 'retards' => 8, 'avert' => 2, 'blames' => 1, 'excl' => 1, 'avis' => 'Décrochage total constaté par l\'ensemble de l\'équipe pédagogique.'],
                'TRIM-3' => ['abs_just' => 0, 'abs_non_just' => 24, 'retards' => 12, 'avert' => 2, 'blames' => 2, 'excl' => 3, 'avis' => 'Conseil de discipline : non réadmission pour indiscipline et résultats nuls.'],
            ],
        ];

        $trimestresInstances = Periode::where('annee_scolaire_id', $annee->id)->orderBy('ordre')->get();
        foreach ($inscriptionsInstances as $item) {
            $insc = $item['inscription'];
            $profil = $item['profil'];
            $discProfile = $disciplineProfils[$profil];

            foreach ($trimestresInstances as $trim) {
                $dData = $discProfile[$trim->code] ?? null;
                if ($dData) {
                    DisciplineBulletin::updateOrCreate(
                        [
                            'inscription_id' => $insc->id,
                            'periode_id' => $trim->id,
                        ],
                        [
                            'absences_justifiees' => $dData['abs_just'],
                            'absences_non_justifiees' => $dData['abs_non_just'],
                            'retards' => $dData['retards'],
                            'consignes' => 0,
                            'avertissements_conduite' => $dData['avert'],
                            'blames_conduite' => $dData['blames'],
                            'exclusions_jours' => $dData['excl'],
                            'decision_conseil' => $dData['avis'],
                        ]
                    );
                }
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\GroupeMatiere;
use App\Models\Matiere;
use Illuminate\Database\Seeder;

/**
 * Seeder officiel du Référentiel National des Disciplines & Blocs Pédagogiques.
 *
 * Initialise l'intégralité du Catalogue Général National des 145 matières
 * officielles du système éducatif camerounais (MINESEC / MINEDUB / Enseignement Catholique)
 * ainsi que les blocs pédagogiques standards en tronc commun (etablissement_id = null).
 *
 * Ce catalogue couvre :
 * - L'Enseignement Secondaire Général (Francophone & Anglophone)
 * - L'Enseignement Secondaire Technique & Professionnel (Commercial, Industriel, Tertiaire)
 * - L'Enseignement Primaire & Maternelle Bilingue
 *
 * Chaque campus peut ensuite associer ces disciplines à ses niveaux d'études avec
 * leurs coefficients et volumes horaires propres.
 */
class CatalogueMatiereSeeder extends Seeder
{
    /**
     * Exécute le peuplement idempotent du catalogue national des matières et blocs.
     *
     * @return void
     */
    public function run(): void
    {
        // ==========================================
        // 1. Blocs Pédagogiques Standards (Tronc Commun Universel)
        // ==========================================
        $groupes = [
            [
                'code' => 'BLOC-SCI',
                'nom' => 'Scientifiques',
                'nom_en' => 'Scientific Subjects',
                'couleur' => 'blue',
                'ordre' => 1,
            ],
            [
                'code' => 'BLOC-LIT',
                'nom' => 'Littéraires',
                'nom_en' => 'Literary Subjects',
                'couleur' => 'emerald',
                'ordre' => 2,
            ],
            [
                'code' => 'BLOC-COMP',
                'nom' => 'Complémentaires',
                'nom_en' => 'Complementary Subjects',
                'couleur' => 'amber',
                'ordre' => 3,
            ],
            [
                'code' => 'BLOC-TECH',
                'nom' => 'Enseignements Professionnels & Techniques',
                'nom_en' => 'Vocational & Technical Subjects',
                'couleur' => 'orange',
                'ordre' => 4,
            ],
            [
                'code' => 'BLOC-PRIM',
                'nom' => 'Activités Fondamentales & Éveil du Primaire',
                'nom_en' => 'Primary Core Curriculum & Development',
                'couleur' => 'indigo',
                'ordre' => 5,
            ],
            [
                'code' => 'BLOC-MAT',
                'nom' => "Activités d'Éveil & Socialisation Maternelle",
                'nom_en' => 'Nursery Developmental & Awareness Activities',
                'couleur' => 'purple',
                'ordre' => 6,
            ],
        ];

        foreach ($groupes as $g) {
            GroupeMatiere::updateOrCreate(
                [
                    'etablissement_id' => null,
                    'code' => $g['code'],
                ],
                [
                    'nom' => $g['nom'],
                    'nom_en' => $g['nom_en'],
                    'couleur' => $g['couleur'],
                    'ordre' => $g['ordre'],
                ]
            );
        }

        // ==========================================
        // 2. Catalogue Exhaustif des 145 Matières Nationales (MINESEC / MINEDUB)
        // ==========================================
        $matieres = [
            // #1 : FRAN - Langue Française
            [
                'code' => 'FRAN',
                'nom' => 'Langue Française',
                'nom_en' => 'French Language',
                'libelle_bulletin' => 'Français',
                'libelle_bulletin_en' => 'French',
                'is_active' => true,
            ],
            // #2 : FRAN-ORTHO - Orthographe
            [
                'code' => 'FRAN-ORTHO',
                'nom' => 'Orthographe',
                'nom_en' => 'Spelling',
                'libelle_bulletin' => 'Orthographe',
                'libelle_bulletin_en' => 'Spelling',
                'is_active' => true,
            ],
            // #3 : FRAN-TEXTE - Étude de texte
            [
                'code' => 'FRAN-TEXTE',
                'nom' => 'Étude de texte',
                'nom_en' => 'Text Study',
                'libelle_bulletin' => 'Étude de texte',
                'libelle_bulletin_en' => 'Text Study',
                'is_active' => true,
            ],
            // #4 : FRAN-ORAL - Expression orale
            [
                'code' => 'FRAN-ORAL',
                'nom' => 'Expression orale',
                'nom_en' => 'Oral Expression',
                'libelle_bulletin' => 'Expression orale',
                'libelle_bulletin_en' => 'Oral Expression',
                'is_active' => true,
            ],
            // #5 : FRAN-ECRIT - Expression écrite
            [
                'code' => 'FRAN-ECRIT',
                'nom' => 'Expression écrite',
                'nom_en' => 'Written Expression',
                'libelle_bulletin' => 'Expression écrite',
                'libelle_bulletin_en' => 'Written Expression',
                'is_active' => true,
            ],
            // #6 : LIT - Littérature
            [
                'code' => 'LIT',
                'nom' => 'Littérature',
                'nom_en' => 'Literature',
                'libelle_bulletin' => 'Littérature',
                'libelle_bulletin_en' => 'Literature',
                'is_active' => true,
            ],
            // #7 : ANG - Anglais
            [
                'code' => 'ANG',
                'nom' => 'Anglais',
                'nom_en' => 'English Language',
                'libelle_bulletin' => 'Anglais',
                'libelle_bulletin_en' => 'English',
                'is_active' => true,
            ],
            // #8 : INT-ENG - Intensive English
            [
                'code' => 'INT-ENG',
                'nom' => 'Intensive English',
                'nom_en' => 'Intensive English',
                'libelle_bulletin' => 'Intensive English',
                'libelle_bulletin_en' => 'Intensive English',
                'is_active' => true,
            ],
            // #9 : ALL - Langue Vivante II : Allemand
            [
                'code' => 'ALL',
                'nom' => 'Langue Vivante II : Allemand',
                'nom_en' => 'German',
                'libelle_bulletin' => 'Allemand',
                'libelle_bulletin_en' => 'German',
                'is_active' => true,
            ],
            // #10 : ESP - Langue Vivante II : Espagnol
            [
                'code' => 'ESP',
                'nom' => 'Langue Vivante II : Espagnol',
                'nom_en' => 'Spanish',
                'libelle_bulletin' => 'Espagnol',
                'libelle_bulletin_en' => 'Spanish',
                'is_active' => true,
            ],
            // #11 : ITAL - Langue Vivante II : Italien
            [
                'code' => 'ITAL',
                'nom' => 'Langue Vivante II : Italien',
                'nom_en' => 'Italian',
                'libelle_bulletin' => 'Italien',
                'libelle_bulletin_en' => 'Italian',
                'is_active' => true,
            ],
            // #12 : CHIN - Langue Vivante II : Chinois
            [
                'code' => 'CHIN',
                'nom' => 'Langue Vivante II : Chinois',
                'nom_en' => 'Chinese',
                'libelle_bulletin' => 'Chinois',
                'libelle_bulletin_en' => 'Chinese',
                'is_active' => true,
            ],
            // #13 : ARAB - Langue Vivante II : Arabe
            [
                'code' => 'ARAB',
                'nom' => 'Langue Vivante II : Arabe',
                'nom_en' => 'Arabic',
                'libelle_bulletin' => 'Arabe',
                'libelle_bulletin_en' => 'Arabic',
                'is_active' => true,
            ],
            // #14 : LV3 - Langue Vivante III
            [
                'code' => 'LV3',
                'nom' => 'Langue Vivante III',
                'nom_en' => 'Modern Language III',
                'libelle_bulletin' => 'Langue Vivante III',
                'libelle_bulletin_en' => 'Language III',
                'is_active' => true,
            ],
            // #15 : LAT - Langues Anciennes : Latin
            [
                'code' => 'LAT',
                'nom' => 'Langues Anciennes : Latin',
                'nom_en' => 'Latin',
                'libelle_bulletin' => 'Latin',
                'libelle_bulletin_en' => 'Latin',
                'is_active' => true,
            ],
            // #16 : GREC - Langues Anciennes : Grec
            [
                'code' => 'GREC',
                'nom' => 'Langues Anciennes : Grec',
                'nom_en' => 'Ancient Greek',
                'libelle_bulletin' => 'Grec',
                'libelle_bulletin_en' => 'Greek',
                'is_active' => true,
            ],
            // #17 : MATH - Mathématiques
            [
                'code' => 'MATH',
                'nom' => 'Mathématiques',
                'nom_en' => 'Mathematics',
                'libelle_bulletin' => 'Mathématiques',
                'libelle_bulletin_en' => 'Mathematics',
                'is_active' => true,
            ],
            // #18 : PHYS - Physique
            [
                'code' => 'PHYS',
                'nom' => 'Physique',
                'nom_en' => 'Physics',
                'libelle_bulletin' => 'Physique',
                'libelle_bulletin_en' => 'Physics',
                'is_active' => true,
            ],
            // #19 : CHIM - Chimie
            [
                'code' => 'CHIM',
                'nom' => 'Chimie',
                'nom_en' => 'Chemistry',
                'libelle_bulletin' => 'Chimie',
                'libelle_bulletin_en' => 'Chemistry',
                'is_active' => true,
            ],
            // #20 : PCT - Physique - Chimie - Technologie
            [
                'code' => 'PCT',
                'nom' => 'Physique - Chimie - Technologie',
                'nom_en' => 'Physics - Chemistry - Technology',
                'libelle_bulletin' => 'Physique - Chimie',
                'libelle_bulletin_en' => 'Physics - Chemistry',
                'is_active' => true,
            ],
            // #21 : SVTEEHB - Sciences de la Vie et de la Terre, Éducation à l'Environnement, Hygiène et Biotechnologie
            [
                'code' => 'SVTEEHB',
                'nom' => 'Sciences de la Vie et de la Terre, Éducation à l\'Environnement, Hygiène et Biotechnologie',
                'nom_en' => 'Life and Earth Sciences (SVTEEHB)',
                'libelle_bulletin' => 'SVTEEHB',
                'libelle_bulletin_en' => 'SVTEEHB',
                'is_active' => true,
            ],
            // #22 : SVT - Sciences de la Vie et de la Terre
            [
                'code' => 'SVT',
                'nom' => 'Sciences de la Vie et de la Terre',
                'nom_en' => 'Life and Earth Sciences',
                'libelle_bulletin' => 'SVT',
                'libelle_bulletin_en' => 'Biology / Geology',
                'is_active' => true,
            ],
            // #23 : SCI - Sciences
            [
                'code' => 'SCI',
                'nom' => 'Sciences',
                'nom_en' => 'General Science',
                'libelle_bulletin' => 'Sciences',
                'libelle_bulletin_en' => 'Science',
                'is_active' => true,
            ],
            // #24 : INFO - Informatique fondamentale et TIC
            [
                'code' => 'INFO',
                'nom' => 'Informatique fondamentale et TIC',
                'nom_en' => 'Computer Science & ICT',
                'libelle_bulletin' => 'Informatique',
                'libelle_bulletin_en' => 'Computer Science',
                'is_active' => true,
            ],
            // #25 : TI-ALGO - Algorithmique et Programmation
            [
                'code' => 'TI-ALGO',
                'nom' => 'Algorithmique et Programmation',
                'nom_en' => 'Algorithmics & Programming',
                'libelle_bulletin' => 'Algorithmique - Programmation',
                'libelle_bulletin_en' => 'Algorithmics',
                'is_active' => true,
            ],
            // #26 : TI-SI - Systèmes d'Information
            [
                'code' => 'TI-SI',
                'nom' => 'Systèmes d\'Information',
                'nom_en' => 'Information Systems',
                'libelle_bulletin' => 'Systèmes d\'Information',
                'libelle_bulletin_en' => 'Information Systems',
                'is_active' => true,
            ],
            // #27 : TI-MAINT - Maintenance et Multimédia
            [
                'code' => 'TI-MAINT',
                'nom' => 'Maintenance et Multimédia',
                'nom_en' => 'Maintenance & Multimedia',
                'libelle_bulletin' => 'Maintenance et Multimédia',
                'libelle_bulletin_en' => 'Maintenance & Multimedia',
                'is_active' => true,
            ],
            // #28 : TI-RESEAU - Réseau, Internet et Sécurité Informatique
            [
                'code' => 'TI-RESEAU',
                'nom' => 'Réseau, Internet et Sécurité Informatique',
                'nom_en' => 'Computer Network, Internet & Security',
                'libelle_bulletin' => 'Réseau & Sécurité Info',
                'libelle_bulletin_en' => 'Network & Security',
                'is_active' => true,
            ],
            // #29 : E-DTSM - Dessin et Technologie des Systèmes Mécaniques
            [
                'code' => 'E-DTSM',
                'nom' => 'Dessin et Technologie des Systèmes Mécaniques',
                'nom_en' => 'Drawing & Mechanical Systems Technology',
                'libelle_bulletin' => 'Dessin & Tech. Systèmes Méc.',
                'libelle_bulletin_en' => 'Technical Drawing',
                'is_active' => true,
            ],
            // #30 : E-FEM - Fabrication des Éléments Mécaniques
            [
                'code' => 'E-FEM',
                'nom' => 'Fabrication des Éléments Mécaniques',
                'nom_en' => 'Mechanical Elements Manufacturing',
                'libelle_bulletin' => 'Fabrication Éléments Méc.',
                'libelle_bulletin_en' => 'Mechanical Manufacturing',
                'is_active' => true,
            ],
            // #31 : PHILO - Philosophie
            [
                'code' => 'PHILO',
                'nom' => 'Philosophie',
                'nom_en' => 'Philosophy',
                'libelle_bulletin' => 'Philosophie',
                'libelle_bulletin_en' => 'Philosophy',
                'is_active' => true,
            ],
            // #32 : HIST - Histoire
            [
                'code' => 'HIST',
                'nom' => 'Histoire',
                'nom_en' => 'History',
                'libelle_bulletin' => 'Histoire',
                'libelle_bulletin_en' => 'History',
                'is_active' => true,
            ],
            // #33 : GEO - Géographie
            [
                'code' => 'GEO',
                'nom' => 'Géographie',
                'nom_en' => 'Geography',
                'libelle_bulletin' => 'Géographie',
                'libelle_bulletin_en' => 'Geography',
                'is_active' => true,
            ],
            // #34 : HIST-GEO - Histoire - Géographie
            [
                'code' => 'HIST-GEO',
                'nom' => 'Histoire - Géographie',
                'nom_en' => 'History & Geography',
                'libelle_bulletin' => 'Histoire - Géographie',
                'libelle_bulletin_en' => 'History - Geography',
                'is_active' => true,
            ],
            // #35 : ECM - Éducation à la Citoyenneté et à la Morale
            [
                'code' => 'ECM',
                'nom' => 'Éducation à la Citoyenneté et à la Morale',
                'nom_en' => 'Citizenship Education & Morals',
                'libelle_bulletin' => 'Éduc. Citoyenneté & Morale',
                'libelle_bulletin_en' => 'Citizenship Education',
                'is_active' => true,
            ],
            // #36 : EPS - Éducation Physique et Sportive
            [
                'code' => 'EPS',
                'nom' => 'Éducation Physique et Sportive',
                'nom_en' => 'Physical & Sports Education',
                'libelle_bulletin' => 'EPS',
                'libelle_bulletin_en' => 'Sport',
                'is_active' => true,
            ],
            // #37 : TM - Travail Manuel
            [
                'code' => 'TM',
                'nom' => 'Travail Manuel',
                'nom_en' => 'Manual Labor & Handicraft',
                'libelle_bulletin' => 'Travail Manuel',
                'libelle_bulletin_en' => 'Manual Labor',
                'is_active' => true,
            ],
            // #38 : TM-EM - Économie Ménagère
            [
                'code' => 'TM-EM',
                'nom' => 'Économie Ménagère',
                'nom_en' => 'Home Economics',
                'libelle_bulletin' => 'Économie Ménagère',
                'libelle_bulletin_en' => 'Home Economics',
                'is_active' => true,
            ],
            // #39 : EVA - Éducation à la Vie et à l'Amour
            [
                'code' => 'EVA',
                'nom' => 'Éducation à la Vie et à l\'Amour',
                'nom_en' => 'Education for Life and Love',
                'libelle_bulletin' => 'Éduc. Vie & Amour',
                'libelle_bulletin_en' => 'Life & Love Education',
                'is_active' => true,
            ],
            // #40 : RELIGION - Religion
            [
                'code' => 'RELIGION',
                'nom' => 'Religion',
                'nom_en' => 'Religious Studies',
                'libelle_bulletin' => 'Religion',
                'libelle_bulletin_en' => 'Religion',
                'is_active' => true,
            ],
            // #41 : EDUC-RELIG - Éducation religieuse
            [
                'code' => 'EDUC-RELIG',
                'nom' => 'Éducation religieuse',
                'nom_en' => 'Religious Education',
                'libelle_bulletin' => 'Éducation religieuse',
                'libelle_bulletin_en' => 'Religious Education',
                'is_active' => true,
            ],
            // #42 : OR.SCO - Orientation Scolaire
            [
                'code' => 'OR.SCO',
                'nom' => 'Orientation Scolaire',
                'nom_en' => 'School Guidance & Counseling',
                'libelle_bulletin' => 'Orientation Scolaire',
                'libelle_bulletin_en' => 'Guidance',
                'is_active' => true,
            ],
            // #43 : LN - Langues Nationales
            [
                'code' => 'LN',
                'nom' => 'Langues Nationales',
                'nom_en' => 'National Languages',
                'libelle_bulletin' => 'Langues Nationales',
                'libelle_bulletin_en' => 'National Languages',
                'is_active' => true,
            ],
            // #44 : CN - Cultures Nationales
            [
                'code' => 'CN',
                'nom' => 'Cultures Nationales',
                'nom_en' => 'National Cultures',
                'libelle_bulletin' => 'Cultures Nationales',
                'libelle_bulletin_en' => 'National Cultures',
                'is_active' => true,
            ],
            // #45 : LCN - Langue et Culture Nationale
            [
                'code' => 'LCN',
                'nom' => 'Langue et Culture Nationale',
                'nom_en' => 'National Language and Culture',
                'libelle_bulletin' => 'Langue & Culture Nat.',
                'libelle_bulletin_en' => 'LCN',
                'is_active' => true,
            ],
            // #46 : ARTS - Éducation Artistique
            [
                'code' => 'ARTS',
                'nom' => 'Éducation Artistique',
                'nom_en' => 'Artistic Education',
                'libelle_bulletin' => 'Éducation Artistique',
                'libelle_bulletin_en' => 'Arts',
                'is_active' => true,
            ],
            // #47 : MUSIQUE - Musique et Dessin
            [
                'code' => 'MUSIQUE',
                'nom' => 'Musique et Dessin',
                'nom_en' => 'Music and Drawing',
                'libelle_bulletin' => 'Musique / Dessin',
                'libelle_bulletin_en' => 'Music / Drawing',
                'is_active' => true,
            ],
            // #48 : AC-HIST - Histoire du Cinéma
            [
                'code' => 'AC-HIST',
                'nom' => 'Histoire du Cinéma',
                'nom_en' => 'Film History',
                'libelle_bulletin' => 'Histoire du Cinéma',
                'libelle_bulletin_en' => 'Cinema History',
                'is_active' => true,
            ],
            // #49 : AC-LANG - Éléments de Langage et de Grammaire Cinématographiques
            [
                'code' => 'AC-LANG',
                'nom' => 'Éléments de Langage et de Grammaire Cinématographiques',
                'nom_en' => 'Cinema Language & Grammar Elements',
                'libelle_bulletin' => 'Langage Cinématographique',
                'libelle_bulletin_en' => 'Cinema Language',
                'is_active' => true,
            ],
            // #50 : AC-OUTILS - Outils et Métiers du Cinéma
            [
                'code' => 'AC-OUTILS',
                'nom' => 'Outils et Métiers du Cinéma',
                'nom_en' => 'Cinema Tools & Professions',
                'libelle_bulletin' => 'Outils & Métiers du Cinéma',
                'libelle_bulletin_en' => 'Cinema Tools',
                'is_active' => true,
            ],
            // #51 : AC-GENRES - Genres Cinématographiques
            [
                'code' => 'AC-GENRES',
                'nom' => 'Genres Cinématographiques',
                'nom_en' => 'Film Genres',
                'libelle_bulletin' => 'Genres Cinématographiques',
                'libelle_bulletin_en' => 'Film Genres',
                'is_active' => true,
            ],
            // #52 : AC-ANAL - Analyse Filmique
            [
                'code' => 'AC-ANAL',
                'nom' => 'Analyse Filmique',
                'nom_en' => 'Film Analysis',
                'libelle_bulletin' => 'Analyse Filmique',
                'libelle_bulletin_en' => 'Film Analysis',
                'is_active' => true,
            ],
            // #53 : AC-ECO - Économie du Cinéma
            [
                'code' => 'AC-ECO',
                'nom' => 'Économie du Cinéma',
                'nom_en' => 'Film Industry Economics',
                'libelle_bulletin' => 'Économie du Cinéma',
                'libelle_bulletin_en' => 'Cinema Economics',
                'is_active' => true,
            ],
            // #54 : AC-REAL - Processus de Réalisation d'un Film
            [
                'code' => 'AC-REAL',
                'nom' => 'Processus de Réalisation d\'un Film',
                'nom_en' => 'Film Directing & Production Process',
                'libelle_bulletin' => 'Processus Réalisation Film',
                'libelle_bulletin_en' => 'Film Production',
                'is_active' => true,
            ],
            // #55 : AC-PROJ - Projet de Fin de Formation (Cinéma)
            [
                'code' => 'AC-PROJ',
                'nom' => 'Projet de Fin de Formation (Cinéma)',
                'nom_en' => 'Graduation Project (Cinema)',
                'libelle_bulletin' => 'Projet Fin Formation',
                'libelle_bulletin_en' => 'Graduation Project',
                'is_active' => true,
            ],
            // #56 : AC-SOCIO - Sociologie du Cinéma
            [
                'code' => 'AC-SOCIO',
                'nom' => 'Sociologie du Cinéma',
                'nom_en' => 'Cinema Sociology',
                'libelle_bulletin' => 'Sociologie du Cinéma',
                'libelle_bulletin_en' => 'Cinema Sociology',
                'is_active' => true,
            ],
            // #57 : ECO-GEN - Économie Générale
            [
                'code' => 'ECO-GEN',
                'nom' => 'Économie Générale',
                'nom_en' => 'General Economics',
                'libelle_bulletin' => 'Économie Générale',
                'libelle_bulletin_en' => 'General Economics',
                'is_active' => true,
            ],
            // #58 : ECO-ORG - Économie et Organisation des Entreprises
            [
                'code' => 'ECO-ORG',
                'nom' => 'Économie et Organisation des Entreprises',
                'nom_en' => 'Business Economics and Organization',
                'libelle_bulletin' => 'Économie & Organisation',
                'libelle_bulletin_en' => 'Business Organization',
                'is_active' => true,
            ],
            // #59 : DROIT - Droit
            [
                'code' => 'DROIT',
                'nom' => 'Droit',
                'nom_en' => 'Law',
                'libelle_bulletin' => 'Droit',
                'libelle_bulletin_en' => 'Law',
                'is_active' => true,
            ],
            // #60 : MATH-APPLI - Mathématiques Appliquées et Statistiques
            [
                'code' => 'MATH-APPLI',
                'nom' => 'Mathématiques Appliquées et Statistiques',
                'nom_en' => 'Applied Mathematics and Statistics',
                'libelle_bulletin' => 'Maths Appliquées',
                'libelle_bulletin_en' => 'Applied Maths',
                'is_active' => true,
            ],
            // #61 : MATH-COM - Mathématiques Commerciales
            [
                'code' => 'MATH-COM',
                'nom' => 'Mathématiques Commerciales',
                'nom_en' => 'Business Mathematics',
                'libelle_bulletin' => 'Maths Commerciales',
                'libelle_bulletin_en' => 'Business Maths',
                'is_active' => true,
            ],
            // #62 : LEGIS-TRAV - Législation du Travail
            [
                'code' => 'LEGIS-TRAV',
                'nom' => 'Législation du Travail',
                'nom_en' => 'Labor Law',
                'libelle_bulletin' => 'Législation du Travail',
                'libelle_bulletin_en' => 'Labor Law',
                'is_active' => true,
            ],
            // #63 : COMMERCE - Commerce
            [
                'code' => 'COMMERCE',
                'nom' => 'Commerce',
                'nom_en' => 'Commerce',
                'libelle_bulletin' => 'Commerce',
                'libelle_bulletin_en' => 'Commerce',
                'is_active' => true,
            ],
            // #64 : BUREAUTIQUE - Bureautique
            [
                'code' => 'BUREAUTIQUE',
                'nom' => 'Bureautique',
                'nom_en' => 'Office Automation',
                'libelle_bulletin' => 'Bureautique',
                'libelle_bulletin_en' => 'Office Automation',
                'is_active' => true,
            ],
            // #65 : MOB - Matériels et Outils Bureautiques
            [
                'code' => 'MOB',
                'nom' => 'Matériels et Outils Bureautiques',
                'nom_en' => 'Office Equipment and Tools',
                'libelle_bulletin' => 'Matériels Bureautiques',
                'libelle_bulletin_en' => 'Office Equipment',
                'is_active' => true,
            ],
            // #66 : OTA - Organisation du Travail Administratif
            [
                'code' => 'OTA',
                'nom' => 'Organisation du Travail Administratif',
                'nom_en' => 'Administrative Work Organization',
                'libelle_bulletin' => 'Organisation Travail Admin.',
                'libelle_bulletin_en' => 'Work Organization',
                'is_active' => true,
            ],
            // #67 : REDACT-PRO - Rédaction Commerciale et Professionnelle
            [
                'code' => 'REDACT-PRO',
                'nom' => 'Rédaction Commerciale et Professionnelle',
                'nom_en' => 'Business and Professional Writing',
                'libelle_bulletin' => 'Rédaction Professionnelle',
                'libelle_bulletin_en' => 'Business Writing',
                'is_active' => true,
            ],
            // #68 : PRISE-PAROLE - Prise Rapide de la Parole / Sténographie
            [
                'code' => 'PRISE-PAROLE',
                'nom' => 'Prise Rapide de la Parole / Sténographie',
                'nom_en' => 'Shorthand / Speech Recording',
                'libelle_bulletin' => 'Prise Rapide Parole',
                'libelle_bulletin_en' => 'Shorthand',
                'is_active' => true,
            ],
            // #69 : CGAO - Comptabilité et Gestion Assistée par Ordinateur
            [
                'code' => 'CGAO',
                'nom' => 'Comptabilité et Gestion Assistée par Ordinateur',
                'nom_en' => 'Computerized Accounting and Management',
                'libelle_bulletin' => 'Comptabilité Assistée Info',
                'libelle_bulletin_en' => 'Computer Accounting',
                'is_active' => true,
            ],
            // #70 : COMPTA-FIN - Comptabilité Financière
            [
                'code' => 'COMPTA-FIN',
                'nom' => 'Comptabilité Financière',
                'nom_en' => 'Financial Accounting',
                'libelle_bulletin' => 'Comptabilité Financière',
                'libelle_bulletin_en' => 'Financial Accounting',
                'is_active' => true,
            ],
            // #71 : COMPTA-ENTR - Comptabilité d'Entreprise
            [
                'code' => 'COMPTA-ENTR',
                'nom' => 'Comptabilité d\'Entreprise',
                'nom_en' => 'Corporate Accounting',
                'libelle_bulletin' => 'Comptabilité d\'Entreprise',
                'libelle_bulletin_en' => 'Corporate Accounting',
                'is_active' => true,
            ],
            // #72 : COMPTA-MGT - Comptabilité de Management
            [
                'code' => 'COMPTA-MGT',
                'nom' => 'Comptabilité de Management',
                'nom_en' => 'Management Accounting',
                'libelle_bulletin' => 'Comptabilité Management',
                'libelle_bulletin_en' => 'Management Accounting',
                'is_active' => true,
            ],
            // #73 : FIN-ENTR - Finance d'Entreprise
            [
                'code' => 'FIN-ENTR',
                'nom' => 'Finance d\'Entreprise',
                'nom_en' => 'Corporate Finance',
                'libelle_bulletin' => 'Finance d\'Entreprise',
                'libelle_bulletin_en' => 'Corporate Finance',
                'is_active' => true,
            ],
            // #74 : GEST-INFO-FIN - Gestion de l'Information Financière
            [
                'code' => 'GEST-INFO-FIN',
                'nom' => 'Gestion de l\'Information Financière',
                'nom_en' => 'Financial Information Management',
                'libelle_bulletin' => 'Gestion Info Financière',
                'libelle_bulletin_en' => 'Financial Info Mgt',
                'is_active' => true,
            ],
            // #75 : PRATIQUE-COMPTA - Pratique Comptable Quotidienne
            [
                'code' => 'PRATIQUE-COMPTA',
                'nom' => 'Pratique Comptable Quotidienne',
                'nom_en' => 'Daily Accounting Practice',
                'libelle_bulletin' => 'Pratique Comptable',
                'libelle_bulletin_en' => 'Daily Accounting',
                'is_active' => true,
            ],
            // #76 : TRAVAUX-COMPTA - Travaux Comptables de Fin d'Exercice
            [
                'code' => 'TRAVAUX-COMPTA',
                'nom' => 'Travaux Comptables de Fin d\'Exercice',
                'nom_en' => 'Year-End Accounting Operations',
                'libelle_bulletin' => 'Travaux Fin Exercice',
                'libelle_bulletin_en' => 'Year-End Accounting',
                'is_active' => true,
            ],
            // #77 : DOC-COM-COMPTA - Documents Commerciaux et Comptabilité
            [
                'code' => 'DOC-COM-COMPTA',
                'nom' => 'Documents Commerciaux et Comptabilité',
                'nom_en' => 'Commercial Documents and Accounting',
                'libelle_bulletin' => 'Documents Commerciaux',
                'libelle_bulletin_en' => 'Commercial Docs',
                'is_active' => true,
            ],
            // #78 : TRAV-APPLI - Travaux d'Application et Synthèse
            [
                'code' => 'TRAV-APPLI',
                'nom' => 'Travaux d\'Application et Synthèse',
                'nom_en' => 'Practical Application and Synthesis',
                'libelle_bulletin' => 'Travaux d\'Application',
                'libelle_bulletin_en' => 'Practical Application',
                'is_active' => true,
            ],
            // #79 : QHSE - Santé, Sécurité et Environnement
            [
                'code' => 'QHSE',
                'nom' => 'Santé, Sécurité et Environnement',
                'nom_en' => 'Health, Safety and Environment',
                'libelle_bulletin' => 'Santé, Sécurité, Environ.',
                'libelle_bulletin_en' => 'HSE',
                'is_active' => true,
            ],
            // #80 : ENTREPREN - Entrepreneuriat
            [
                'code' => 'ENTREPREN',
                'nom' => 'Entrepreneuriat',
                'nom_en' => 'Entrepreneurship',
                'libelle_bulletin' => 'Entrepreneuriat',
                'libelle_bulletin_en' => 'Entrepreneurship',
                'is_active' => true,
            ],
            // #81 : METIER-FORM - Métier et Formation
            [
                'code' => 'METIER-FORM',
                'nom' => 'Métier et Formation',
                'nom_en' => 'Trade and Training Orientation',
                'libelle_bulletin' => 'Métier et Formation',
                'libelle_bulletin_en' => 'Trade & Training',
                'is_active' => true,
            ],
            // #82 : TECH-TEXTILE - Technologie des Textiles
            [
                'code' => 'TECH-TEXTILE',
                'nom' => 'Technologie des Textiles',
                'nom_en' => 'Textile Technology',
                'libelle_bulletin' => 'Technologie des Textiles',
                'libelle_bulletin_en' => 'Textile Tech',
                'is_active' => true,
            ],
            // #83 : DESSIN-MODE - Dessin de Mode
            [
                'code' => 'DESSIN-MODE',
                'nom' => 'Dessin de Mode',
                'nom_en' => 'Fashion Drawing',
                'libelle_bulletin' => 'Dessin de Mode',
                'libelle_bulletin_en' => 'Fashion Drawing',
                'is_active' => true,
            ],
            // #84 : COUPE - Coupe Vêtement
            [
                'code' => 'COUPE',
                'nom' => 'Coupe Vêtement',
                'nom_en' => 'Pattern Cutting',
                'libelle_bulletin' => 'Coupe',
                'libelle_bulletin_en' => 'Cutting',
                'is_active' => true,
            ],
            // #85 : COUTURE - Couture et Assemblage
            [
                'code' => 'COUTURE',
                'nom' => 'Couture et Assemblage',
                'nom_en' => 'Sewing and Assembly',
                'libelle_bulletin' => 'Couture',
                'libelle_bulletin_en' => 'Sewing',
                'is_active' => true,
            ],
            // #86 : DTCM - Dessin et Technologie de Construction Mécanique
            [
                'code' => 'DTCM',
                'nom' => 'Dessin et Technologie de Construction Mécanique',
                'nom_en' => 'Mechanical Construction Drawing and Technology',
                'libelle_bulletin' => 'Dessin Tech Construction',
                'libelle_bulletin_en' => 'Mech Drawing',
                'is_active' => true,
            ],
            // #87 : TECH-MECAN - Technologie Mécanique
            [
                'code' => 'TECH-MECAN',
                'nom' => 'Technologie Mécanique',
                'nom_en' => 'Mechanical Technology',
                'libelle_bulletin' => 'Technologie Mécanique',
                'libelle_bulletin_en' => 'Mech Technology',
                'is_active' => true,
            ],
            // #88 : TECH-ELEC-MEC - Technologie Électrique et Mécanique
            [
                'code' => 'TECH-ELEC-MEC',
                'nom' => 'Technologie Électrique et Mécanique',
                'nom_en' => 'Electrical and Mechanical Technology',
                'libelle_bulletin' => 'Tech Électrique & Méc.',
                'libelle_bulletin_en' => 'Electro-Mech Tech',
                'is_active' => true,
            ],
            // #89 : TP-AJUSTAGE - Travaux Pratiques d'Ajustage
            [
                'code' => 'TP-AJUSTAGE',
                'nom' => 'Travaux Pratiques d\'Ajustage',
                'nom_en' => 'Fitting Practical Work',
                'libelle_bulletin' => 'TP Ajustage',
                'libelle_bulletin_en' => 'Fitting Practice',
                'is_active' => true,
            ],
            // #90 : RECEPT-VEHIC - Réception d'un Véhicule
            [
                'code' => 'RECEPT-VEHIC',
                'nom' => 'Réception d\'un Véhicule',
                'nom_en' => 'Vehicle Reception and Diagnostics',
                'libelle_bulletin' => 'Réception Véhicule',
                'libelle_bulletin_en' => 'Vehicle Reception',
                'is_active' => true,
            ],
            // #91 : MOTEURS-COMB - Moteurs à Combustion Interne
            [
                'code' => 'MOTEURS-COMB',
                'nom' => 'Moteurs à Combustion Interne',
                'nom_en' => 'Internal Combustion Engines',
                'libelle_bulletin' => 'Moteurs à Combustion',
                'libelle_bulletin_en' => 'Engines',
                'is_active' => true,
            ],
            // #92 : TRANSMISSIONS - Les Transmissions Automobiles
            [
                'code' => 'TRANSMISSIONS',
                'nom' => 'Les Transmissions Automobiles',
                'nom_en' => 'Automotive Transmissions',
                'libelle_bulletin' => 'Transmissions',
                'libelle_bulletin_en' => 'Transmissions',
                'is_active' => true,
            ],
            // #93 : FREINAGE-SECU - Tenue de Route, Freinage et Sécurité
            [
                'code' => 'FREINAGE-SECU',
                'nom' => 'Tenue de Route, Freinage et Sécurité',
                'nom_en' => 'Road Handling, Braking and Safety',
                'libelle_bulletin' => 'Freinage & Sécurité Auto',
                'libelle_bulletin_en' => 'Braking & Safety',
                'is_active' => true,
            ],
            // #94 : ELEC-AUTO - Électricité et Électronique Automobile
            [
                'code' => 'ELEC-AUTO',
                'nom' => 'Électricité et Électronique Automobile',
                'nom_en' => 'Automotive Electricity and Electronics',
                'libelle_bulletin' => 'Électricité & Électro Auto',
                'libelle_bulletin_en' => 'Auto Electricity',
                'is_active' => true,
            ],
            // #95 : DEVIS-ESTIM - Devis et Estimation
            [
                'code' => 'DEVIS-ESTIM',
                'nom' => 'Devis et Estimation',
                'nom_en' => 'Estimating and Costing',
                'libelle_bulletin' => 'Devis et Estimation',
                'libelle_bulletin_en' => 'Cost Estimation',
                'is_active' => true,
            ],
            // #96 : DESSIN-BAT - Dessin Bâtiment
            [
                'code' => 'DESSIN-BAT',
                'nom' => 'Dessin Bâtiment',
                'nom_en' => 'Building Drawing',
                'libelle_bulletin' => 'Dessin Bâtiment',
                'libelle_bulletin_en' => 'Building Drawing',
                'is_active' => true,
            ],
            // #97 : CONNAIS-METIER - Connaissance du Métier et des Matériaux
            [
                'code' => 'CONNAIS-METIER',
                'nom' => 'Connaissance du Métier et des Matériaux',
                'nom_en' => 'Trade and Building Materials Knowledge',
                'libelle_bulletin' => 'Métier & Matériaux BTP',
                'libelle_bulletin_en' => 'Materials Knowledge',
                'is_active' => true,
            ],
            // #98 : OUTILLAGE-BAT - Connaissance du Matériel et Outillage
            [
                'code' => 'OUTILLAGE-BAT',
                'nom' => 'Connaissance du Matériel et Outillage',
                'nom_en' => 'Tools and Equipment Knowledge',
                'libelle_bulletin' => 'Matériel & Outillage BTP',
                'libelle_bulletin_en' => 'Tools & Equipment',
                'is_active' => true,
            ],
            // #99 : PROCEDES-REAL - Procédés et Réalisation
            [
                'code' => 'PROCEDES-REAL',
                'nom' => 'Procédés et Réalisation',
                'nom_en' => 'Building Processes and Implementation',
                'libelle_bulletin' => 'Procédés & Réalisation',
                'libelle_bulletin_en' => 'Processes & Building',
                'is_active' => true,
            ],
            // #100 : MACONNERIE-ECHA - Exécution de Maçonnerie et Échafaudages
            [
                'code' => 'MACONNERIE-ECHA',
                'nom' => 'Exécution de Maçonnerie et Échafaudages',
                'nom_en' => 'Masonry and Scaffolding Execution',
                'libelle_bulletin' => 'Maçonnerie & Échafaudages',
                'libelle_bulletin_en' => 'Masonry & Scaffolding',
                'is_active' => true,
            ],
            // #101 : OUVRAGES-TERRE - Ouvrages en Terre
            [
                'code' => 'OUVRAGES-TERRE',
                'nom' => 'Ouvrages en Terre',
                'nom_en' => 'Earthworks',
                'libelle_bulletin' => 'Ouvrages en Terre',
                'libelle_bulletin_en' => 'Earthworks',
                'is_active' => true,
            ],
            // #102 : COFFRAGE - Exécution de Coffrage
            [
                'code' => 'COFFRAGE',
                'nom' => 'Exécution de Coffrage',
                'nom_en' => 'Formwork Execution',
                'libelle_bulletin' => 'Exécution de Coffrage',
                'libelle_bulletin_en' => 'Formwork',
                'is_active' => true,
            ],
            // #103 : FERRAILLAGE - Exécution de Ferraillage
            [
                'code' => 'FERRAILLAGE',
                'nom' => 'Exécution de Ferraillage',
                'nom_en' => 'Reinforcement Execution',
                'libelle_bulletin' => 'Exécution de Ferraillage',
                'libelle_bulletin_en' => 'Reinforcement',
                'is_active' => true,
            ],
            // #104 : BETON - Mise en Œuvre du Béton
            [
                'code' => 'BETON',
                'nom' => 'Mise en Œuvre du Béton',
                'nom_en' => 'Concrete Works',
                'libelle_bulletin' => 'Mise en Œuvre du Béton',
                'libelle_bulletin_en' => 'Concrete Works',
                'is_active' => true,
            ],
            // #105 : REVETEMENTS - Exécution des Revêtements
            [
                'code' => 'REVETEMENTS',
                'nom' => 'Exécution des Revêtements',
                'nom_en' => 'Finishing and Coating',
                'libelle_bulletin' => 'Exécution des Revêtements',
                'libelle_bulletin_en' => 'Coating & Finishing',
                'is_active' => true,
            ],
            // #106 : CIRCUITS-ELEC - Circuits Électriques
            [
                'code' => 'CIRCUITS-ELEC',
                'nom' => 'Circuits Électriques',
                'nom_en' => 'Electric Circuits',
                'libelle_bulletin' => 'Circuits Électriques',
                'libelle_bulletin_en' => 'Electric Circuits',
                'is_active' => true,
            ],
            // #107 : MACHINES-ELEC - Machines Électriques
            [
                'code' => 'MACHINES-ELEC',
                'nom' => 'Machines Électriques',
                'nom_en' => 'Electrical Machines',
                'libelle_bulletin' => 'Machines Électriques',
                'libelle_bulletin_en' => 'Electrical Machines',
                'is_active' => true,
            ],
            // #108 : TECH-EQUIP-ELEC - Technologie des Équipements et Installations Électriques
            [
                'code' => 'TECH-EQUIP-ELEC',
                'nom' => 'Technologie des Équipements et Installations Électriques',
                'nom_en' => 'Electrical Equipment and Installations Technology',
                'libelle_bulletin' => 'Techno Équipements Élec.',
                'libelle_bulletin_en' => 'Electrical Equipment',
                'is_active' => true,
            ],
            // #109 : SCHEMAS-ELEC - Schémas Électriques
            [
                'code' => 'SCHEMAS-ELEC',
                'nom' => 'Schémas Électriques',
                'nom_en' => 'Electrical Diagrams',
                'libelle_bulletin' => 'Schémas Électriques',
                'libelle_bulletin_en' => 'Electrical Diagrams',
                'is_active' => true,
            ],
            // #110 : PROD-TRANS-DIST - Production, Transport et Distribution de l'Énergie
            [
                'code' => 'PROD-TRANS-DIST',
                'nom' => 'Production, Transport et Distribution de l\'Énergie',
                'nom_en' => 'Energy Production, Transmission and Distribution',
                'libelle_bulletin' => 'Prod. Transport Distrib.',
                'libelle_bulletin_en' => 'Energy Transmission',
                'is_active' => true,
            ],
            // #111 : DESSIN-TECH - Dessin Technique Électrique
            [
                'code' => 'DESSIN-TECH',
                'nom' => 'Dessin Technique Électrique',
                'nom_en' => 'Electrical Technical Drawing',
                'libelle_bulletin' => 'Dessin Technique Élec.',
                'libelle_bulletin_en' => 'Technical Drawing',
                'is_active' => true,
            ],
            // #112 : TP-ELEC - Travaux Pratiques d'Électricité
            [
                'code' => 'TP-ELEC',
                'nom' => 'Travaux Pratiques d\'Électricité',
                'nom_en' => 'Electrical Practical Works',
                'libelle_bulletin' => 'TP Électricité',
                'libelle_bulletin_en' => 'Electrical Practice',
                'is_active' => true,
            ],
            // #113 : INSTAL-RESID-COM - Installations Électriques Résidentielles et Commerciales
            [
                'code' => 'INSTAL-RESID-COM',
                'nom' => 'Installations Électriques Résidentielles et Commerciales',
                'nom_en' => 'Residential and Commercial Electrical Installations',
                'libelle_bulletin' => 'Install. Élec. Résidentielles',
                'libelle_bulletin_en' => 'Residential Electrical',
                'is_active' => true,
            ],
            // #114 : INSTAL-INDUST - Installations Électriques Industrielles
            [
                'code' => 'INSTAL-INDUST',
                'nom' => 'Installations Électriques Industrielles',
                'nom_en' => 'Industrial Electrical Installations',
                'libelle_bulletin' => 'Install. Élec. Industrielles',
                'libelle_bulletin_en' => 'Industrial Electrical',
                'is_active' => true,
            ],
            // #115 : MESURES-ESSAIS - Mesures et Essais Électriques
            [
                'code' => 'MESURES-ESSAIS',
                'nom' => 'Mesures et Essais Électriques',
                'nom_en' => 'Electrical Measurements and Testing',
                'libelle_bulletin' => 'Mesures & Essais Élec.',
                'libelle_bulletin_en' => 'Electrical Testing',
                'is_active' => true,
            ],
            // #116 : TECH-MATERIAUX - Technologie des Matériaux Bois
            [
                'code' => 'TECH-MATERIAUX',
                'nom' => 'Technologie des Matériaux Bois',
                'nom_en' => 'Wood Materials Technology',
                'libelle_bulletin' => 'Techno Matériaux Bois',
                'libelle_bulletin_en' => 'Wood Technology',
                'is_active' => true,
            ],
            // #117 : TECH-MENUISERIE - Technologie Professionnelle Menuiserie
            [
                'code' => 'TECH-MENUISERIE',
                'nom' => 'Technologie Professionnelle Menuiserie',
                'nom_en' => 'Joinery Professional Technology',
                'libelle_bulletin' => 'Technologie Menuiserie',
                'libelle_bulletin_en' => 'Joinery Technology',
                'is_active' => true,
            ],
            // #118 : DESSIN-MENUIS - Dessin de Menuiserie
            [
                'code' => 'DESSIN-MENUIS',
                'nom' => 'Dessin de Menuiserie',
                'nom_en' => 'Joinery Drawing',
                'libelle_bulletin' => 'Dessin de Menuiserie',
                'libelle_bulletin_en' => 'Joinery Drawing',
                'is_active' => true,
            ],
            // #119 : EXEC-MENUISERIE - Exécution de Menuiserie et Échafaudage
            [
                'code' => 'EXEC-MENUISERIE',
                'nom' => 'Exécution de Menuiserie et Échafaudage',
                'nom_en' => 'Joinery and Scaffolding Execution',
                'libelle_bulletin' => 'Menuiserie & Échafaudage',
                'libelle_bulletin_en' => 'Joinery & Scaffolding',
                'is_active' => true,
            ],
            // #120 : FAB-OUVERTURES - Fabrication et Installation des Ouvertures et Revêtements
            [
                'code' => 'FAB-OUVERTURES',
                'nom' => 'Fabrication et Installation des Ouvertures et Revêtements',
                'nom_en' => 'Manufacture and Installation of Openings and Coatings',
                'libelle_bulletin' => 'Ouvertures & Revêtements',
                'libelle_bulletin_en' => 'Openings & Coatings',
                'is_active' => true,
            ],
            // #121 : FAB-MEUBLES - Fabrication et Installation des Meubles (TP)
            [
                'code' => 'FAB-MEUBLES',
                'nom' => 'Fabrication et Installation des Meubles (TP)',
                'nom_en' => 'Furniture Manufacturing and Installation',
                'libelle_bulletin' => 'Fabrication Meubles (TP)',
                'libelle_bulletin_en' => 'Furniture Making',
                'is_active' => true,
            ],
            // #122 : ENG-LANG - English Language
            [
                'code' => 'ENG-LANG',
                'nom' => 'English Language',
                'nom_en' => 'English Language',
                'libelle_bulletin' => 'English Language',
                'libelle_bulletin_en' => 'English Language',
                'is_active' => true,
            ],
            // #123 : ENG-LIT - Literature in English
            [
                'code' => 'ENG-LIT',
                'nom' => 'Literature in English',
                'nom_en' => 'Literature in English',
                'libelle_bulletin' => 'Literature in English',
                'libelle_bulletin_en' => 'Literature in English',
                'is_active' => true,
            ],
            // #124 : ENG-FREN - French (Anglophone Section)
            [
                'code' => 'ENG-FREN',
                'nom' => 'French (Anglophone Section)',
                'nom_en' => 'French',
                'libelle_bulletin' => 'French',
                'libelle_bulletin_en' => 'French',
                'is_active' => true,
            ],
            // #125 : ENG-MATH - Mathematics (Anglophone Section)
            [
                'code' => 'ENG-MATH',
                'nom' => 'Mathematics (Anglophone Section)',
                'nom_en' => 'Mathematics',
                'libelle_bulletin' => 'Mathematics',
                'libelle_bulletin_en' => 'Mathematics',
                'is_active' => true,
            ],
            // #126 : ENG-ADDMATH - Additional Mathematics
            [
                'code' => 'ENG-ADDMATH',
                'nom' => 'Additional Mathematics',
                'nom_en' => 'Additional Mathematics',
                'libelle_bulletin' => 'Additional Maths',
                'libelle_bulletin_en' => 'Additional Maths',
                'is_active' => true,
            ],
            // #127 : ENG-PHYS - Physics (Anglophone Section)
            [
                'code' => 'ENG-PHYS',
                'nom' => 'Physics (Anglophone Section)',
                'nom_en' => 'Physics',
                'libelle_bulletin' => 'Physics',
                'libelle_bulletin_en' => 'Physics',
                'is_active' => true,
            ],
            // #128 : ENG-CHEM - Chemistry (Anglophone Section)
            [
                'code' => 'ENG-CHEM',
                'nom' => 'Chemistry (Anglophone Section)',
                'nom_en' => 'Chemistry',
                'libelle_bulletin' => 'Chemistry',
                'libelle_bulletin_en' => 'Chemistry',
                'is_active' => true,
            ],
            // #129 : ENG-BIO - Biology
            [
                'code' => 'ENG-BIO',
                'nom' => 'Biology',
                'nom_en' => 'Biology',
                'libelle_bulletin' => 'Biology',
                'libelle_bulletin_en' => 'Biology',
                'is_active' => true,
            ],
            // #130 : ENG-HBIO - Human Biology
            [
                'code' => 'ENG-HBIO',
                'nom' => 'Human Biology',
                'nom_en' => 'Human Biology',
                'libelle_bulletin' => 'Human Biology',
                'libelle_bulletin_en' => 'Human Biology',
                'is_active' => true,
            ],
            // #131 : ENG-GEOL - Geology
            [
                'code' => 'ENG-GEOL',
                'nom' => 'Geology',
                'nom_en' => 'Geology',
                'libelle_bulletin' => 'Geology',
                'libelle_bulletin_en' => 'Geology',
                'is_active' => true,
            ],
            // #132 : ENG-HIST - History (Anglophone Section)
            [
                'code' => 'ENG-HIST',
                'nom' => 'History (Anglophone Section)',
                'nom_en' => 'History',
                'libelle_bulletin' => 'History',
                'libelle_bulletin_en' => 'History',
                'is_active' => true,
            ],
            // #133 : ENG-GEOG - Geography (Anglophone Section)
            [
                'code' => 'ENG-GEOG',
                'nom' => 'Geography (Anglophone Section)',
                'nom_en' => 'Geography',
                'libelle_bulletin' => 'Geography',
                'libelle_bulletin_en' => 'Geography',
                'is_active' => true,
            ],
            // #134 : ENG-CITIZEN - Citizenship Education (Anglophone Section)
            [
                'code' => 'ENG-CITIZEN',
                'nom' => 'Citizenship Education (Anglophone Section)',
                'nom_en' => 'Citizenship Education',
                'libelle_bulletin' => 'Citizenship Education',
                'libelle_bulletin_en' => 'Citizenship Education',
                'is_active' => true,
            ],
            // #135 : ENG-LOGIC - Logic
            [
                'code' => 'ENG-LOGIC',
                'nom' => 'Logic',
                'nom_en' => 'Logic',
                'libelle_bulletin' => 'Logic',
                'libelle_bulletin_en' => 'Logic',
                'is_active' => true,
            ],
            // #136 : ENG-PHILOS - Philosophy (Anglophone Section)
            [
                'code' => 'ENG-PHILOS',
                'nom' => 'Philosophy (Anglophone Section)',
                'nom_en' => 'Philosophy',
                'libelle_bulletin' => 'Philosophy',
                'libelle_bulletin_en' => 'Philosophy',
                'is_active' => true,
            ],
            // #137 : ENG-ECON - Economics
            [
                'code' => 'ENG-ECON',
                'nom' => 'Economics',
                'nom_en' => 'Economics',
                'libelle_bulletin' => 'Economics',
                'libelle_bulletin_en' => 'Economics',
                'is_active' => true,
            ],
            // #138 : ENG-COMM - Commerce (Anglophone Section)
            [
                'code' => 'ENG-COMM',
                'nom' => 'Commerce (Anglophone Section)',
                'nom_en' => 'Commerce',
                'libelle_bulletin' => 'Commerce',
                'libelle_bulletin_en' => 'Commerce',
                'is_active' => true,
            ],
            // #139 : ENG-CS-ICT - Computer Science / ICT (Anglophone Section)
            [
                'code' => 'ENG-CS-ICT',
                'nom' => 'Computer Science / ICT (Anglophone Section)',
                'nom_en' => 'Computer Science / ICT',
                'libelle_bulletin' => 'Computer Science / ICT',
                'libelle_bulletin_en' => 'Computer Science / ICT',
                'is_active' => true,
            ],
            // #140 : ENG-FOOD-NUT - Food and Nutrition / Food Science
            [
                'code' => 'ENG-FOOD-NUT',
                'nom' => 'Food and Nutrition / Food Science',
                'nom_en' => 'Food & Nutrition / Food Science',
                'libelle_bulletin' => 'Food & Nutrition',
                'libelle_bulletin_en' => 'Food & Nutrition',
                'is_active' => true,
            ],
            // #141 : ENG-PE - Physical Education (PE)
            [
                'code' => 'ENG-PE',
                'nom' => 'Physical Education (PE)',
                'nom_en' => 'Physical Education (PE)',
                'libelle_bulletin' => 'Physical Education (PE)',
                'libelle_bulletin_en' => 'Physical Education (PE)',
                'is_active' => true,
            ],
            // #142 : ENG-MAN-ARTS - Manual Labour / Arts
            [
                'code' => 'ENG-MAN-ARTS',
                'nom' => 'Manual Labour / Arts',
                'nom_en' => 'Manual Labour / Arts',
                'libelle_bulletin' => 'Manual Labour / Arts',
                'libelle_bulletin_en' => 'Manual Labour / Arts',
                'is_active' => true,
            ],
            // #143 : ENG-REL-KNOW - Religious Knowledge
            [
                'code' => 'ENG-REL-KNOW',
                'nom' => 'Religious Knowledge',
                'nom_en' => 'Religious Knowledge',
                'libelle_bulletin' => 'Religious Knowledge',
                'libelle_bulletin_en' => 'Religious Knowledge',
                'is_active' => true,
            ],
            // #144 : ENG-SBE-FREN - Special Bilingual Education French (SBE French)
            [
                'code' => 'ENG-SBE-FREN',
                'nom' => 'Special Bilingual Education French (SBE French)',
                'nom_en' => 'SBE French',
                'libelle_bulletin' => 'SBE French',
                'libelle_bulletin_en' => 'SBE French',
                'is_active' => true,
            ],
            // #145 : ESF/DESSIN - ESF et Dessin
            [
                'code' => 'ESF/DESSIN',
                'nom' => 'ESF et Dessin',
                'nom_en' => null,
                'libelle_bulletin' => 'ESF/Dessin',
                'libelle_bulletin_en' => null,
                'is_active' => true,
            ],

            // =========================================================================
            // 3. Curricula Officiels MINEDUB Cameroun — Maternelle & Primaire (FR & EN)
            // =========================================================================

            // --- A. Éducation Maternelle Francophone (MINEDUB Cameroun) ---
            [
                'code' => 'MAT-LANG-COMM',
                'nom' => 'Langage, Initiation à la Lecture & Graphisme',
                'nom_en' => 'Pre-reading, Language & Writing Readiness',
                'libelle_bulletin' => 'Langage & Graphisme',
                'libelle_bulletin_en' => 'Language & Pre-writing',
                'is_active' => true,
            ],
            [
                'code' => 'MAT-EVEIL-MATH',
                'nom' => 'Éveil Mathématique & Logique (Formes & Nombres)',
                'nom_en' => 'Mathematical & Logical Awakening',
                'libelle_bulletin' => 'Éveil Mathématique',
                'libelle_bulletin_en' => 'Early Maths',
                'is_active' => true,
            ],
            [
                'code' => 'MAT-EVEIL-SCI',
                'nom' => 'Éveil Scientifique, Technologique & Environnement',
                'nom_en' => 'Scientific, Environmental & Sensory Awakening',
                'libelle_bulletin' => 'Éveil Scientifique',
                'libelle_bulletin_en' => 'Early Science',
                'is_active' => true,
            ],
            [
                'code' => 'MAT-VIVRE-ENS',
                'nom' => 'Vivre-Ensemble, Politesse & Éducation Civique',
                'nom_en' => 'Social Living, Manners & Moral Awakening',
                'libelle_bulletin' => 'Vivre-Ensemble',
                'libelle_bulletin_en' => 'Social Living',
                'is_active' => true,
            ],
            [
                'code' => 'MAT-EDUC-ART',
                'nom' => 'Éducation Artistique (Dessin, Coloriage, Chant & Récitation)',
                'nom_en' => 'Artistic Education (Drawing, Songs & Rhymes)',
                'libelle_bulletin' => 'Arts & Chants',
                'libelle_bulletin_en' => 'Arts & Songs',
                'is_active' => true,
            ],
            [
                'code' => 'MAT-PSYCHOMOT',
                'nom' => 'Psychomotricité & Éducation Physique',
                'nom_en' => 'Psychomotor & Physical Development',
                'libelle_bulletin' => 'Psychomotricité',
                'libelle_bulletin_en' => 'Psychomotor Skills',
                'is_active' => true,
            ],
            [
                'code' => 'MAT-EV-TIC',
                'nom' => "Éveil aux Technologies de l'Information (TIC)",
                'nom_en' => 'Early ICT & Digital Discovery',
                'libelle_bulletin' => 'Éveil TIC',
                'libelle_bulletin_en' => 'Early ICT',
                'is_active' => true,
            ],
            [
                'code' => 'MAT-INIT-ANG',
                'nom' => "Initiation à l'Anglais (Maternelle Francophone)",
                'nom_en' => 'Early English Discovery',
                'libelle_bulletin' => 'Initiation Anglais',
                'libelle_bulletin_en' => 'English Discovery',
                'is_active' => true,
            ],

            // --- B. Nursery School Anglophone Subsystem (MINEDUB Cameroon) ---
            [
                'code' => 'NUR-LANG-READ',
                'nom' => 'Pre-reading, Storytelling & Language Skills',
                'nom_en' => 'Pre-reading, Storytelling & Language Skills',
                'libelle_bulletin' => 'Language & Pre-reading',
                'libelle_bulletin_en' => 'Language & Pre-reading',
                'is_active' => true,
            ],
            [
                'code' => 'NUR-WRITING',
                'nom' => 'Pre-writing & Graphomotor Skills',
                'nom_en' => 'Pre-writing & Graphomotor Skills',
                'libelle_bulletin' => 'Pre-writing Skills',
                'libelle_bulletin_en' => 'Pre-writing Skills',
                'is_active' => true,
            ],
            [
                'code' => 'NUR-MATHS',
                'nom' => 'Pre-mathematics & Numeracy',
                'nom_en' => 'Pre-mathematics & Numeracy',
                'libelle_bulletin' => 'Pre-mathematics',
                'libelle_bulletin_en' => 'Pre-mathematics',
                'is_active' => true,
            ],
            [
                'code' => 'NUR-SCI-HEALTH',
                'nom' => 'Environmental Science & Health Habits',
                'nom_en' => 'Environmental Science & Health Habits',
                'libelle_bulletin' => 'Science & Health',
                'libelle_bulletin_en' => 'Science & Health',
                'is_active' => true,
            ],
            [
                'code' => 'NUR-SOC-MORAL',
                'nom' => 'Citizenship & Moral Living (Good Manners)',
                'nom_en' => 'Citizenship & Moral Living',
                'libelle_bulletin' => 'Moral & Social Living',
                'libelle_bulletin_en' => 'Moral & Social Living',
                'is_active' => true,
            ],
            [
                'code' => 'NUR-ARTS-MUSIC',
                'nom' => 'Arts, Crafts, Rhymes & Singing',
                'nom_en' => 'Arts, Crafts, Rhymes & Singing',
                'libelle_bulletin' => 'Arts, Crafts & Rhymes',
                'libelle_bulletin_en' => 'Arts, Crafts & Rhymes',
                'is_active' => true,
            ],
            [
                'code' => 'NUR-MOTRICITY',
                'nom' => 'Motor Skills & Physical Education',
                'nom_en' => 'Motor Skills & Physical Education',
                'libelle_bulletin' => 'Motor Skills & PE',
                'libelle_bulletin_en' => 'Motor Skills & PE',
                'is_active' => true,
            ],
            [
                'code' => 'NUR-FREN-INIT',
                'nom' => 'French Oral Initiation (Nursery)',
                'nom_en' => 'French Oral Initiation',
                'libelle_bulletin' => 'French Oral',
                'libelle_bulletin_en' => 'French Oral',
                'is_active' => true,
            ],

            // --- C. Enseignement Primaire Francophone (MINEDUB Cameroun — SIL à CM2) ---
            [
                'code' => 'PRIM-LECTURE',
                'nom' => 'Lecture & Compréhension de Texte (Primaire)',
                'nom_en' => 'Reading & Text Comprehension (Primary FR)',
                'libelle_bulletin' => 'Lecture',
                'libelle_bulletin_en' => 'Reading',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-GRAMM',
                'nom' => 'Grammaire & Conjugaison (Primaire)',
                'nom_en' => 'French Grammar & Conjugation',
                'libelle_bulletin' => 'Grammaire-Conj.',
                'libelle_bulletin_en' => 'Grammar & Conj.',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-VOCAB',
                'nom' => 'Vocabulaire (Primaire)',
                'nom_en' => 'Vocabulary (Primary FR)',
                'libelle_bulletin' => 'Vocabulaire',
                'libelle_bulletin_en' => 'Vocabulary',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-DICTEE',
                'nom' => 'Dictée & Questions',
                'nom_en' => 'Dictation & Comprehension Questions',
                'libelle_bulletin' => 'Dictée & Questions',
                'libelle_bulletin_en' => 'Dictation',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-ECRITURE',
                'nom' => 'Écriture & Calligraphie',
                'nom_en' => 'Handwriting & Penmanship',
                'libelle_bulletin' => 'Écriture',
                'libelle_bulletin_en' => 'Handwriting',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-RECIT',
                'nom' => 'Récitation & Poésie',
                'nom_en' => 'Recitation & Poetry',
                'libelle_bulletin' => 'Récitation',
                'libelle_bulletin_en' => 'Recitation',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-PROBLEMES',
                'nom' => 'Résolution de Problèmes & Calcul Rapide',
                'nom_en' => 'Problem Solving & Mental Arithmetic',
                'libelle_bulletin' => 'Problèmes & Calcul',
                'libelle_bulletin_en' => 'Problem Solving',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-SCI-TECH',
                'nom' => "Sciences et Technologies (Éducation à l'Environnement)",
                'nom_en' => 'Science & Technology (Primary FR)',
                'libelle_bulletin' => 'Sciences & Techno.',
                'libelle_bulletin_en' => 'Science & Tech.',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-AGRO-PAST',
                'nom' => 'Activités Agropastorales & Bricolage',
                'nom_en' => 'Agro-pastoral & Practical Crafts',
                'libelle_bulletin' => 'Agropastorale',
                'libelle_bulletin_en' => 'Agro-pastoral Crafts',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-HYG-SANTE',
                'nom' => 'Hygiène Pratique & Éducation à la Santé',
                'nom_en' => 'Hygiene & Health Education',
                'libelle_bulletin' => 'Hygiène & Santé',
                'libelle_bulletin_en' => 'Hygiene & Health',
                'is_active' => true,
            ],
            [
                'code' => 'PRIM-TIC',
                'nom' => "Technologies de l'Information et de la Communication (Primaire)",
                'nom_en' => 'Information & Communication Technologies (Primary FR)',
                'libelle_bulletin' => 'TIC',
                'libelle_bulletin_en' => 'ICT',
                'is_active' => true,
            ],

            // --- D. Primary School Anglophone Subsystem (MINEDUB Cameroon — Class 1 to 6) ---
            [
                'code' => 'ENG-PRIM-PHON',
                'nom' => 'English Language (Reading, Phonics & Comprehension)',
                'nom_en' => 'English Language & Phonics',
                'libelle_bulletin' => 'English Phonics & Reading',
                'libelle_bulletin_en' => 'English Phonics & Reading',
                'is_active' => true,
            ],
            [
                'code' => 'ENG-PRIM-GRAM',
                'nom' => 'English Grammar, Vocabulary & Composition (Primary)',
                'nom_en' => 'Grammar, Vocabulary & Composition',
                'libelle_bulletin' => 'Grammar & Composition',
                'libelle_bulletin_en' => 'Grammar & Composition',
                'is_active' => true,
            ],
            [
                'code' => 'ENG-PRIM-HAND',
                'nom' => 'Handwriting, Spelling & Dictation (Primary)',
                'nom_en' => 'Handwriting, Spelling & Dictation',
                'libelle_bulletin' => 'Handwriting & Spelling',
                'libelle_bulletin_en' => 'Handwriting & Spelling',
                'is_active' => true,
            ],
            [
                'code' => 'ENG-PRIM-NUM',
                'nom' => 'Mathematics (Arithmetic & Problem Solving - Primary)',
                'nom_en' => 'Mathematics (Primary)',
                'libelle_bulletin' => 'Mathematics',
                'libelle_bulletin_en' => 'Mathematics',
                'is_active' => true,
            ],
            [
                'code' => 'ENG-PRIM-SCI',
                'nom' => 'Science and Technology (Primary)',
                'nom_en' => 'Science & Technology',
                'libelle_bulletin' => 'Science & Technology',
                'libelle_bulletin_en' => 'Science & Technology',
                'is_active' => true,
            ],
            [
                'code' => 'ENG-PRIM-SOC',
                'nom' => 'Social Studies (History, Geography & Citizenship)',
                'nom_en' => 'Social Studies',
                'libelle_bulletin' => 'Social Studies',
                'libelle_bulletin_en' => 'Social Studies',
                'is_active' => true,
            ],
            [
                'code' => 'ENG-PRIM-HEAL',
                'nom' => 'Health Education & Home Economics (Primary)',
                'nom_en' => 'Health Education & Home Economics',
                'libelle_bulletin' => 'Health & Home Econ.',
                'libelle_bulletin_en' => 'Health & Home Econ.',
                'is_active' => true,
            ],
            [
                'code' => 'ENG-PRIM-VOC',
                'nom' => 'Vocational Studies, Arts & Crafts (Primary)',
                'nom_en' => 'Vocational Studies & Crafts',
                'libelle_bulletin' => 'Vocational & Crafts',
                'libelle_bulletin_en' => 'Vocational & Crafts',
                'is_active' => true,
            ],
            [
                'code' => 'ENG-PRIM-NAT',
                'nom' => 'National Languages & Cultures (Primary EN)',
                'nom_en' => 'National Languages & Cultures',
                'libelle_bulletin' => 'National Languages',
                'libelle_bulletin_en' => 'National Languages',
                'is_active' => true,
            ],
        ];

        // Insertion et synchronisation idempotente de l'ensemble du catalogue national (181 disciplines)
        foreach ($matieres as $data) {
            Matiere::updateOrCreate(
                [
                    'etablissement_id' => null,
                    'code' => $data['code'],
                ],
                [
                    'nom' => $data['nom'],
                    'nom_en' => $data['nom_en'],
                    'libelle_bulletin' => $data['libelle_bulletin'],
                    'libelle_bulletin_en' => $data['libelle_bulletin_en'],
                    'is_active' => $data['is_active'],
                ]
            );
        }
    }
}

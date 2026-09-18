import { type SearchableOption } from '@/components/ui/searchable-select';

/**
 * Référentiel officiel ordonné des diplômes du système éducatif camerounais (MINESEC, MINEDUB, MINESUP).
 * Ordonnancement strict :
 * 1. Secondaire Général
 * 2. Secondaire Technique & Professionnel
 * 3. Enseignement Primaire
 * 4. Formation des Enseignants (Normal)
 * 5. Enseignement Supérieur
 * 6. Maternelle / Aucun
 */
export const DIPLOMES_CYCLE_CAMEROUN: SearchableOption[] = [
    // 1. Enseignement Secondaire Général
    {
        value: 'BEPC',
        label: 'BEPC - Brevet d’Études du Premier Cycle',
        group: 'Secondaire Général',
        description: 'Sanctionne la fin du premier cycle francophone (3ème)',
    },
    {
        value: 'GCE O-Level',
        label: 'GCE O-Level - General Certificate of Education Ordinary Level',
        group: 'Secondaire Général',
        description: 'Sanctionne la fin du premier cycle anglophone (Form 5)',
    },
    {
        value: 'Probatoire',
        label: 'Probatoire - Enseignement Secondaire Général',
        group: 'Secondaire Général',
        description: 'Examen officiel de classe de Première (Séries A, C, D, TI...)',
    },
    {
        value: 'BACCALAURÉAT',
        label: 'Baccalauréat - Enseignement Secondaire Général',
        group: 'Secondaire Général',
        description: 'Diplôme d’État de fin d’études secondaires (Terminale)',
    },
    {
        value: 'GCE A-Level',
        label: 'GCE A-Level - General Certificate of Education Advanced Level',
        group: 'Secondaire Général',
        description: 'Diplôme de fin d’études secondaires anglophones (Upper Sixth)',
    },

    // 2. Enseignement Secondaire Technique & Professionnel
    {
        value: 'CAP',
        label: 'CAP - Certificat d’Aptitude Professionnelle',
        group: 'Secondaire Technique & Professionnel',
        description: 'Sanctionne le premier cycle technique (Industriel, Commercial, Économie Sociale)',
    },
    {
        value: 'Probatoire Technique',
        label: 'Probatoire Technique / Brevet de Technicien (BT)',
        group: 'Secondaire Technique & Professionnel',
        description: 'Examen technique de classe de Première (F1 à F8, STT, ESF, HTR...)',
    },
    {
        value: 'Baccalauréat Technique',
        label: 'Baccalauréat Technique / Brevet de Technicien (BT)',
        group: 'Secondaire Technique & Professionnel',
        description: 'Diplôme de fin d’études secondaires techniques et technologiques',
    },
    {
        value: 'GCE Technical O-Level',
        label: 'GCE Technical O-Level',
        group: 'Secondaire Technique & Professionnel',
        description: 'Sanctionne le 1er cycle de l’enseignement technique anglophone',
    },
    {
        value: 'GCE Technical A-Level',
        label: 'GCE Technical A-Level',
        group: 'Secondaire Technique & Professionnel',
        description: 'Diplôme de fin d’études secondaires techniques anglophones',
    },

    // 3. Enseignement Primaire & Éducation de Base
    {
        value: 'CEP',
        label: 'CEP - Certificat d’Études Primaires',
        group: 'Enseignement Primaire',
        description: 'Sanctionne la fin du cycle primaire francophone (CM2)',
    },
    {
        value: 'FSLC',
        label: 'FSLC - First School Leaving Certificate',
        group: 'Enseignement Primaire',
        description: 'Sanctionne la fin du cycle primaire anglophone (Class 6)',
    },

    // 4. Formation des Enseignants (Écoles Normales d’Instituteurs)
    {
        value: 'CAPIEMP',
        label: 'CAPIEMP - Certificat d’Aptitude Pédagogique (ENIEG)',
        group: 'Formation des Enseignants',
        description: 'Instituteurs de l’enseignement maternel et primaire',
    },
    {
        value: 'CAPIET',
        label: 'CAPIET - Certificat d’Aptitude Pédagogique (ENIET)',
        group: 'Formation des Enseignants',
        description: 'Instituteurs de l’enseignement technique et professionnel',
    },

    // 5. Enseignement Supérieur & Post-Secondaire
    {
        value: 'BTS',
        label: 'BTS - Brevet de Technicien Supérieur',
        group: 'Enseignement Supérieur',
        description: 'Diplôme national d’études supérieures professionnelles (Bac+2)',
    },
    {
        value: 'HND',
        label: 'HND - Higher National Diploma',
        group: 'Enseignement Supérieur',
        description: 'Équivalent anglophone du BTS (Bac+2)',
    },
    {
        value: 'DSEP',
        label: 'DSEP - Diplôme Supérieur d’Études Professionnelles',
        group: 'Enseignement Supérieur',
        description: 'Enseignement supérieur technique et professionnel (Bac+2)',
    },
    {
        value: 'LICENCE',
        label: 'Licence / Bachelor’s Degree',
        group: 'Enseignement Supérieur',
        description: 'Grade universitaire de premier cycle (Bac+3)',
    },
    {
        value: 'MASTER',
        label: 'Master / Master’s Degree',
        group: 'Enseignement Supérieur',
        description: 'Grade universitaire de deuxième cycle (Bac+5)',
    },
    {
        value: 'DOCTORAT',
        label: 'Doctorat / Ph.D',
        group: 'Enseignement Supérieur',
        description: 'Grade universitaire de troisième cycle (Bac+8)',
    },

    // 6. Maternelle / Autre
    {
        value: 'AUCUN',
        label: 'Aucun (Cycle Maternel / Sans diplôme)',
        group: 'Maternelle / Autre',
        description: 'Pour les cycles pré-scolaires ou sections sans examen diplômant',
    },
];

/**
 * Référentiel des Séries officielles pour l'Enseignement Secondaire au Cameroun (MINESEC).
 * Couvre le Secondaire Général, les Sciences & Technologies du Tertiaire,
 * les Techniques Industrielles, ainsi que le sous-système anglophone.
 */
export const SERIES_ENSEIGNEMENT_CAMEROUN: SearchableOption[] = [
    // 1. Secondaire Général (Francophone)
    {
        value: 'A4',
        label: 'Série A4 - Lettres, Langues Vivantes II & Philosophie',
        group: 'Secondaire Général',
        description: 'Filière littéraire dominante (Allemand, Espagnol, Chinois, Italien...)',
    },
    {
        value: 'A1',
        label: 'Série A1 - Lettres, Latin & Grec',
        group: 'Secondaire Général',
        description: 'Humanités classiques',
    },
    {
        value: 'A2',
        label: 'Série A2 - Lettres, Latin & Langue Vivante II',
        group: 'Secondaire Général',
        description: 'Lettres classiques et langues vivantes',
    },
    {
        value: 'A3',
        label: 'Série A3 - Lettres & Latin',
        group: 'Secondaire Général',
        description: 'Lettres et langue latine',
    },
    {
        value: 'A5',
        label: 'Série A5 - Langues Vivantes II & III',
        group: 'Secondaire Général',
        description: 'Trilinguisme approfondi (spécifique Probatoire)',
    },
    {
        value: 'ABI',
        label: 'Série ABI - Lettres Bilingues',
        group: 'Secondaire Général',
        description: 'Bilinguisme français / anglais renforcé',
    },
    {
        value: 'C',
        label: 'Série C - Mathématiques & Sciences Physiques',
        group: 'Secondaire Général',
        description: 'Filière scientifique exacte et mathématiques approfondies',
    },
    {
        value: 'D',
        label: 'Série D - Sciences de la Vie et de la Terre, Maths & Chimie',
        group: 'Secondaire Général',
        description: 'Sciences naturelles, expérimentales et biologiques',
    },
    {
        value: 'E',
        label: 'Série E - Mathématiques & Techniques',
        group: 'Secondaire Général',
        description: 'Sciences et technologie de pointe',
    },
    {
        value: 'TI',
        label: 'Série TI - Technologies de l’Information',
        group: 'Secondaire Général',
        description: 'Informatique, algorithmique et sciences du numérique',
    },
    {
        value: 'SH',
        label: 'Série SH - Sciences Humaines',
        group: 'Secondaire Général',
        description: 'Histoire, Géographie, Économie et Sociologie',
    },
    {
        value: 'AC',
        label: 'Série AC - Arts Cinématographiques',
        group: 'Secondaire Général',
        description: 'Cinéma, audiovisuel et expression visuelle',
    },

    // 2. Sciences et Technologies du Tertiaire (STT)
    {
        value: 'CG',
        label: 'Série CG - Comptabilité et Gestion',
        group: 'Sciences & Technologies du Tertiaire (STT)',
        description: 'Gestion comptable, financière et fiscale',
    },
    {
        value: 'ACA',
        label: 'Série ACA - Action et Communication Administratives',
        group: 'Sciences & Technologies du Tertiaire (STT)',
        description: 'Secrétariat bureautique, gestion des flux et organisation',
    },
    {
        value: 'ACC',
        label: 'Série ACC - Action et Communication Commerciales',
        group: 'Sciences & Technologies du Tertiaire (STT)',
        description: 'Vente, marketing et négociation commerciale',
    },
    {
        value: 'FIG',
        label: 'Série FIG - Fiscalité et Informatique de Gestion',
        group: 'Sciences & Technologies du Tertiaire (STT)',
        description: 'Informatique appliquée à la gestion et droit fiscal',
    },
    {
        value: 'SES',
        label: 'Série SES - Sciences Économiques et Sociales',
        group: 'Sciences & Technologies du Tertiaire (STT)',
        description: 'Économie appliquée, sociologie et monde de l’entreprise',
    },
    {
        value: 'ESF',
        label: 'Série ESF - Économie Sociale et Familiale',
        group: 'Sciences & Technologies du Tertiaire (STT)',
        description: 'Santé communautaire, nutrition et gestion familiale',
    },

    // 3. Techniques Industrielles (Séries F & Brevets)
    {
        value: 'F1',
        label: 'Série F1 - Fabrication Mécanique',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Usinage, construction mécanique et commande numérique',
    },
    {
        value: 'F2',
        label: 'Série F2 - Électronique',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Circuits électroniques, télécoms et automatisme',
    },
    {
        value: 'F3',
        label: 'Série F3 - Électrotechnique',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Installations électriques industrielles et distribution d’énergie',
    },
    {
        value: 'F4-BA',
        label: 'Série F4-BA - Génie Civil / Bâtiment',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Gros œuvre, maçonnerie et dessin architectural',
    },
    {
        value: 'F4-TP',
        label: 'Série F4-TP - Travaux Publics',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Ponts, chaussées, topographie et voirie',
    },
    {
        value: 'F5',
        label: 'Série F5 - Froid et Climatisation',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Installations thermiques, frigorifiques et génie climatique',
    },
    {
        value: 'F6',
        label: 'Série F6 - Chimie Industrielle & Bio-procédés',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Génie chimique et transformations industrielles',
    },
    {
        value: 'F7',
        label: 'Série F7 - Sciences Biologiques & Médico-sociales',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Analyses biomédicales et laboratoire de santé',
    },
    {
        value: 'IH',
        label: 'Série IH - Industrie de l’Habillement',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Confection industrielle, coupe et stylisme',
    },
    {
        value: 'MEB',
        label: 'Série MEB - Métiers du Bois / Menuiserie',
        group: 'Techniques Industrielles (Séries F)',
        description: 'Menuiserie, agencement et ébénisterie',
    },

    // 4. Sous-système Anglophone (Grammar & Technical)
    {
        value: 'Arts',
        label: 'Arts Stream - General Arts (GCE A-Level)',
        group: 'Sous-système Anglophone',
        description: 'Literature, History, Geography, Religious Studies, Philosophy',
    },
    {
        value: 'Science',
        label: 'Science Stream - Pure Sciences (GCE A-Level)',
        group: 'Sous-système Anglophone',
        description: 'Pure Maths, Physics, Chemistry, Biology, Further Maths',
    },
    {
        value: 'Commercial',
        label: 'Commercial Stream - Economics & Business',
        group: 'Sous-système Anglophone',
        description: 'Economics, Accounting, Commerce, Business Management',
    },
    {
        value: 'Technical',
        label: 'Technical Engineering Stream',
        group: 'Sous-système Anglophone',
        description: 'Mechanical, Electrical, Building Construction, Carpentry',
    },

    // 5. Tronc Commun
    {
        value: 'Générale',
        label: 'Générale (Tronc commun sans série)',
        group: 'Tronc Commun',
        description: 'Pour le 1er cycle (6ème, 5ème, 4ème, 3ème, Form 1-4) ou classes indifférenciées',
    },
];

/**
 * Référentiel officiel des Spécialités métiers et filières professionnelles (MINESEC / ESTP).
 */
export const SPECIALITES_ENSEIGNEMENT_CAMEROUN: SearchableOption[] = [
    // 1. Génie Civil & Bâtiment
    {
        value: 'Bâtiment (BA)',
        label: 'Bâtiment (BA) - Construction & Gros Œuvre',
        group: 'Génie Civil & Bâtiment',
        description: 'Maçonnerie, béton armé et structures',
    },
    {
        value: 'Bureau d’Études (BE)',
        label: 'Bureau d’Études (BE) - Dessin & Conception',
        group: 'Génie Civil & Bâtiment',
        description: 'Dessin assisté par ordinateur (DAO), plans et métrés',
    },
    {
        value: 'Travaux Publics (TP)',
        label: 'Travaux Publics (TP) - Voirie & Ouvrages d’art',
        group: 'Génie Civil & Bâtiment',
        description: 'Routes, ponts et infrastructures',
    },
    {
        value: 'Installation Sanitaire (IS)',
        label: 'Installation Sanitaire & Plomberie (IS)',
        group: 'Génie Civil & Bâtiment',
        description: 'Réseaux hydrauliques, canalisations et sanitaires',
    },
    {
        value: 'Menuiserie & Charpenterie',
        label: 'Menuiserie & Charpenterie (MECA / MBO)',
        group: 'Génie Civil & Bâtiment',
        description: 'Ouvrages du bois, toitures et finitions',
    },
    {
        value: 'Peinture & Finition',
        label: 'Peinture, Vitrerie & Finition (PF)',
        group: 'Génie Civil & Bâtiment',
        description: 'Revêtements, décoration et traitement des surfaces',
    },

    // 2. Génie Mécanique & Métallique
    {
        value: 'Fabrication Mécanique (FM)',
        label: 'Fabrication Mécanique (FM)',
        group: 'Génie Mécanique',
        description: 'Tournage, fraisage et usinage numérique',
    },
    {
        value: 'Mécanique Automobile (MA)',
        label: 'Mécanique Automobile (MA)',
        group: 'Génie Mécanique',
        description: 'Diagnostic, motorisation et maintenance automobile',
    },
    {
        value: 'Chaudronnerie & Tuyauterie',
        label: 'Chaudronnerie & Tuyauterie Industrielle (CH / TI)',
        group: 'Génie Mécanique',
        description: 'Soudure industrielle, charpente métallique et tuyauterie',
    },
    {
        value: 'Maintenance Mécanique',
        label: 'Maintenance des Systèmes Mécaniques (MSM)',
        group: 'Génie Mécanique',
        description: 'Entretien des lignes de production industrielle',
    },

    // 3. Génie Électrique & Énergétique
    {
        value: 'Électrotechnique (ELNI)',
        label: 'Électrotechnique (ELNI)',
        group: 'Génie Électrique & Énergie',
        description: 'Installation et distribution électrique haute/basse tension',
    },
    {
        value: 'Froid et Climatisation (FC)',
        label: 'Froid et Climatisation (FC)',
        group: 'Génie Électrique & Énergie',
        description: 'Pompes à chaleur, chambres froides et climatisation',
    },
    {
        value: 'Électronique & Audiovisuel',
        label: 'Électronique & Maintenance Audiovisuelle (EMA)',
        group: 'Génie Électrique & Énergie',
        description: 'Maintenance des équipements informatiques et multimédias',
    },
    {
        value: 'Énergies Renouvelables',
        label: 'Énergies Renouvelables & Énergie Solaire (ERS)',
        group: 'Génie Électrique & Énergie',
        description: 'Photovoltaïque, éolien et transition énergétique',
    },

    // 4. Gestion, Tertiaire & Services
    {
        value: 'Comptabilité et Gestion (CG)',
        label: 'Comptabilité et Gestion (CG)',
        group: 'Gestion, Tertiaire & Services',
        description: 'Tenue des comptes, fiscalité et logiciels de gestion',
    },
    {
        value: 'Secrétariat Bureautique (ACA)',
        label: 'Secrétariat Bureautique (ACA)',
        group: 'Gestion, Tertiaire & Services',
        description: 'Assistanat de direction et communication numérique',
    },
    {
        value: 'Commerce & Vente (ACC)',
        label: 'Commerce, Vente & Négociation (ACC)',
        group: 'Gestion, Tertiaire & Services',
        description: 'Techniques de vente et relation client',
    },
    {
        value: 'Fiscalité & Gestion (FIG)',
        label: 'Fiscalité et Informatique de Gestion (FIG)',
        group: 'Gestion, Tertiaire & Services',
        description: 'Audit financier et systèmes d’information',
    },
    {
        value: 'Banque et Microfinance',
        label: 'Banque, Microfinance & Assurance (BMF)',
        group: 'Gestion, Tertiaire & Services',
        description: 'Opérations de guichet, crédit et micro-assurance',
    },

    // 5. Hôtellerie, Tourisme & Restauration (HTR)
    {
        value: 'Cuisine et Restauration',
        label: 'Cuisine & Arts de la Table',
        group: 'Hôtellerie, Tourisme & Restauration',
        description: 'Techniques culinaires, pâtisserie et service en salle',
    },
    {
        value: 'Hébergement & Accueil',
        label: 'Hébergement, Réception & Accueil Hôtelier',
        group: 'Hôtellerie, Tourisme & Restauration',
        description: 'Gouvernance, gestion des étages et réservation',
    },
    {
        value: 'Tourisme et Loisirs',
        label: 'Tourisme, Agence de Voyage & Guidage',
        group: 'Hôtellerie, Tourisme & Restauration',
        description: 'Circuits touristiques et patrimoine culturel',
    },

    // 6. Métiers de la Mode & du Social
    {
        value: 'Industrie de l’Habillement (IH)',
        label: 'Industrie de l’Habillement (IH) - Confection & Stylisme',
        group: 'Mode & Économie Familiale',
        description: 'Patronage, modélisme et confection de vêtements',
    },
    {
        value: 'Économie Sociale et Familiale (ESF)',
        label: 'Économie Sociale et Familiale (ESF)',
        group: 'Mode & Économie Familiale',
        description: 'Puériculture, diététique et développement communautaire',
    },

    // 7. Enseignement Normal
    {
        value: 'Enseignement Primaire (ENIEG)',
        label: 'Pédagogie Primaire & Maternelle (CAPIEMP)',
        group: 'Enseignement Normal',
        description: 'Formation pédagogique des instituteurs',
    },
    {
        value: 'Enseignement Technique (ENIET)',
        label: 'Pédagogie Technique & Professionnelle (CAPIET)',
        group: 'Enseignement Normal',
        description: 'Formation des instituteurs de l’enseignement technique',
    },

    // 8. Enseignement Général (Sans spécialité métier)
    {
        value: 'Enseignement Général',
        label: 'Enseignement Général (Sans spécialité métier)',
        group: 'Général',
        description: 'Pour les classes classiques de collège ou lycée d’enseignement général',
    },
];

/**
 * Référentiel des Options pédagogiques modulables (Langues vivantes, Arts, LCN, etc.).
 */
export const OPTIONS_PEDAGOGIQUES_CAMEROUN: SearchableOption[] = [
    // 1. Langues Vivantes II & III (LV2 / LV3)
    {
        value: 'Allemand',
        label: 'Allemand (Langue Vivante II ou III)',
        group: 'Langues Vivantes (LV2 / LV3)',
        description: 'Langue officielle germanique enseignée dès la 4ème ou en 2nde',
    },
    {
        value: 'Espagnol',
        label: 'Espagnol (Langue Vivante II ou III)',
        group: 'Langues Vivantes (LV2 / LV3)',
        description: 'Langue vivante hispanique couramment choisie en sous-système francophone',
    },
    {
        value: 'Chinois',
        label: 'Chinois Mandarin (Langue Vivante II ou III)',
        group: 'Langues Vivantes (LV2 / LV3)',
        description: 'Programme officiel MINESEC en partenariat avec l’Institut Confucius',
    },
    {
        value: 'Italien',
        label: 'Italien (Langue Vivante II ou III)',
        group: 'Langues Vivantes (LV2 / LV3)',
        description: 'Enseignement des langues romanes en lycée',
    },
    {
        value: 'Arabe',
        label: 'Arabe (Langue Vivante II ou III)',
        group: 'Langues Vivantes (LV2 / LV3)',
        description: 'Option linguistique officielle en régions septentrionales et lycées bilingues',
    },
    {
        value: 'Latin',
        label: 'Latin (Langue Ancienne)',
        group: 'Langues Vivantes (LV2 / LV3)',
        description: 'Humanités classiques, étymologie et grammaire latine',
    },
    {
        value: 'Grec Ancien',
        label: 'Grec Ancien (Langue Ancienne)',
        group: 'Langues Vivantes (LV2 / LV3)',
        description: 'Lettres classiques pour séries littéraires A1/A2',
    },

    // 2. Langues et Cultures Nationales (LCN)
    {
        value: 'Langues Nationales (LNC)',
        label: 'Langues Nationales Camerounaises (LNC)',
        group: 'Langues & Cultures Nationales (LCN)',
        description: 'Apprentissage d’une langue patrimoniale (Ewondo, Douala, Fulfulde, Fe’fe’...)',
    },
    {
        value: 'Cultures & Traditions',
        label: 'Cultures Nationales, Contes & Patrimoine',
        group: 'Langues & Cultures Nationales (LCN)',
        description: 'Étude des us et coutumes, proverbes et traditions camerounaises',
    },

    // 3. Éducation Artistique & Musicale
    {
        value: 'Arts Plastiques',
        label: 'Arts Plastiques (Dessin, Peinture & Sculpture)',
        group: 'Éducation Artistique',
        description: 'Expression visuelle, dessin technique et histoire de l’art',
    },
    {
        value: 'Arts Musicaux',
        label: 'Arts Musicaux (Musique, Solfège & Chorale)',
        group: 'Éducation Artistique',
        description: 'Pratique instrumentale, solfège et chant choral scolaire',
    },
    {
        value: 'Arts Scéniques',
        label: 'Arts Scéniques (Théâtre & Déclamation)',
        group: 'Éducation Artistique',
        description: 'Dramaturgie, expression scénique et théâtre scolaire',
    },
    {
        value: 'Arts Cinématographiques',
        label: 'Arts Cinématographiques & Audiovisuel',
        group: 'Éducation Artistique',
        description: 'Photographie, réalisation et critique de cinéma',
    },

    // 4. Sciences, Technologies & Pratiques
    {
        value: 'Informatique Facultative',
        label: 'Informatique & Programmation Facultative',
        group: 'Sciences & Technologies',
        description: 'Bureautique avancée, codage et culture du web',
    },
    {
        value: 'Robotique & IA',
        label: 'Robotique, Électronique Pratique & IA',
        group: 'Sciences & Technologies',
        description: 'Ateliers scientifiques de club robotique et innovation',
    },
    {
        value: 'Agronomie / TMP',
        label: 'Travaux Manuels Pratiques & Agro-pastoral (TMP)',
        group: 'Sciences & Technologies',
        description: 'Jardin scolaire, techniques agricoles et petit élevage',
    },

    // 5. Sans option
    {
        value: 'Aucune',
        label: 'Aucune option (Tronc commun standard)',
        group: 'Tronc Commun',
        description: 'Aucune matière optionnelle sélectionnée pour cette classe',
    },
];

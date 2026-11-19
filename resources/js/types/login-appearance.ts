/**
 * Définition d'un slide affiché dans le carrousel de l'écran de connexion (`/login`).
 */
export interface LoginSlideItem {
    id: string;
    image: string;
    title: string;
    subtitle: string;
}

/**
 * Enregistrement d'un slide personnalisé sauvegardé en base de données (`login_slides`).
 */
export interface LoginSlideRecord {
    id: number;
    titre: string;
    message: string | null;
    image_path: string;
    ordre: number;
    is_active: boolean;
}

/**
 * Identité visuelle et coordonnées d'assistance affichées sur la page de connexion.
 */
export interface LoginBrandingConfig {
    brand_name: string;
    brand_subtitle: string;
    logo_url: string;
    footer_left: string;
    footer_right: string;
    support_label: string;
    support_phone: string;
    support_email: string;
}

/**
 * Identité visuelle partagée de la barre latérale après connexion et du favicon navigateur.
 */
export interface AppBrandingPayload {
    use_custom_app_branding: boolean;
    brand_name: string;
    brand_subtitle: string;
    logo_url: string;
    sync_favicon: boolean;
    favicon_url: string | null;
}

/**
 * Enregistrement de configuration globale en base (`login_settings`).
 */
export interface LoginSettingRecord {
    id: number;
    use_custom_slides: boolean;
    rotation_interval_seconds: number;
    brand_name: string | null;
    brand_subtitle: string | null;
    logo_path: string | null;
    footer_left: string | null;
    footer_right: string | null;
    support_label: string | null;
    support_phone: string | null;
    support_email: string | null;
    apply_branding_to_app: boolean;
    app_brand_name: string | null;
    app_brand_subtitle: string | null;
    app_logo_path: string | null;
    sync_favicon: boolean;
}

/**
 * Données synthétiques de l'institution faîtière pour pré-remplir le branding de connexion.
 */
export interface InstitutionLoginBrief {
    nom: string;
    sigle: string | null;
    devise: string | null;
    logo: string | null;
    email: string | null;
    telephone_1: string | null;
}

/**
 * Payload public partagé par Inertia vers les vues d'authentification (`login`, etc.).
 */
export interface LoginAppearancePayload {
    slides: LoginSlideItem[];
    using_custom_slides: boolean;
    rotation_interval_ms: number;
    branding: LoginBrandingConfig;
}

/**
 * 10 photographies scolaires camerounaises par défaut affichées lorsque aucune galerie personnalisée n'est active.
 */
export const DEFAULT_LOGIN_SCENES: LoginSlideItem[] = [
    {
        id: 'classe-pedagogie',
        image: '/images/auth/real-classroom-cameroon.jpg',
        title: 'Apprentissage & Pédagogie Active',
        subtitle: 'Séance de cours et travail pratique en classe au Cameroun',
    },
    {
        id: 'classe-eleves',
        image: '/images/auth/real-students-class.jpg',
        title: 'Travail en Classe & Réflexion',
        subtitle: 'Élèves studieux, cahiers et transmission des savoirs fondamentaux',
    },
    {
        id: 'ecole-urbaine',
        image: '/images/auth/real-school-douala.jpg',
        title: 'Établissements Scolaires Urbains',
        subtitle: 'Élèves réunis au sein de l’École Publique de Bonadowito (Douala)',
    },
    {
        id: 'ecole-village',
        image: '/images/auth/village-school.jpg',
        title: 'L’École au Village',
        subtitle: 'Bâtiment et cour d’école en zone rurale (Bamena, Ouest Cameroun)',
    },
    {
        id: 'ecoliers-cameroun',
        image: '/images/auth/school-scene-1.jpg',
        title: 'Écoliers du Cameroun',
        subtitle: 'La jeunesse camerounaise et l’accès universel à l’éducation (Akok Bekoe)',
    },
    {
        id: 'defile-jeunesse',
        image: '/images/auth/school-scene-4.jpg',
        title: 'Fête de la Jeunesse & Civisme',
        subtitle: 'Défilé scolaire traditionnel du 11 février et cohésion nationale',
    },
    {
        id: 'batiment-rural',
        image: '/images/auth/rural-school-west.jpg',
        title: 'Infrastructures Scolaires de Proximité',
        subtitle: 'Établissements scolaires au cœur des terroirs locaux (Bapeng-Bameka)',
    },
    {
        id: 'rassemblement-nord',
        image: '/images/auth/school-scene-3.jpg',
        title: 'Rassemblement & Camaraderie',
        subtitle: 'Élèves réunies dans la cour (Maroua, Grand Nord Cameroun)',
    },
    {
        id: 'patrimoine-bankim',
        image: '/images/auth/real-schoolhouse-bankim.jpg',
        title: 'Patrimoine Éducatif Régional',
        subtitle: 'Bâtiment scolaire historique et cadre d’études (Bankim, Adamaoua)',
    },
    {
        id: 'cour-recreation',
        image: '/images/auth/school-scene-8.jpg',
        title: 'Cérémonie & Discipline Scolaire',
        subtitle: 'Cérémonie de levée des couleurs et rassemblement dans la cour',
    },
];

/**
 * Branding SYGES par défaut utilisé en l'absence de personnalisation.
 */
export const DEFAULT_LOGIN_BRANDING: LoginBrandingConfig = {
    brand_name: 'SYGES',
    brand_subtitle: 'Gestion des Établissements Scolaires',
    logo_url: '/images/syges-icon.png',
    footer_left: 'SYGES • Système Éducatif Camerounais',
    footer_right: '© 2026',
    support_label: "Besoin d'aide ?",
    support_phone: '(+237) 697 44 90 03',
    support_email: 'teguiasimo@gmail.com',
};

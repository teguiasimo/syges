/**
 * Types TypeScript pour le Module 8 : Personnel & Ressources Humaines (RH) de SYGES.
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 8.1, 8.2, 8.3).
 */

/**
 * Entité représentant un membre du personnel administratif, technique ou enseignant.
 */
export interface Personnel {
    /** Identifiant unique en base de données */
    id: number;
    /** Matricule séquentiel officiel (format PERS-YYYY-XXXX) */
    matricule: string;
    /** Identifiant du compte utilisateur applicatif rattaché (nullable) */
    user_id?: number | null;
    /** Identifiant de l'établissement d'affectation */
    etablissement_id: number;
    /** Identifiant du département pédagogique de rattachement (nullable) */
    departement_id?: number | null;
    /** Nom de famille */
    nom: string;
    /** Prénom(s) */
    prenom?: string | null;
    /** Nom complet concaténé (accesseur backend nom_complet) */
    nom_complet?: string;
    /** Genre (M = Masculin, F = Féminin) */
    sexe: 'M' | 'F';
    /** Date de naissance (YYYY-MM-DD) */
    date_naissance?: string | null;
    /** Lieu de naissance */
    lieu_naissance?: string | null;
    /** Numéro de téléphone principal */
    telephone: string;
    /** Numéro de téléphone secondaire */
    telephone_secondaire?: string | null;
    /** Adresse email professionnelle ou de contact */
    email?: string | null;
    /** Adresse géographique de résidence */
    adresse?: string | null;
    /** Fonction principale (ex: Enseignant, Censeur, Surveillant Général, Comptable) */
    fonction_principale: string;
    /** Type de contrat (ex: CDI, CDD, Vacation, Fonctionnaire détaché) */
    type_contrat: string;
    /** Date de prise de service / embauche (YYYY-MM-DD) */
    date_embauche: string;
    /** Statut administratif (Actif, En congé, Démissionnaire, Retraité, Suspendu, Licencié) */
    statut: 'Actif' | 'En congé' | 'Démissionnaire' | 'Retraité' | 'Suspendu' | 'Licencié' | string;
    /** Numéro de sécurité sociale / CNPS */
    numero_cnps?: string | null;
    /** Diplôme le plus élevé certifié */
    diplome_plus_eleve?: string | null;
    /** Observations ou mentions RH */
    observations?: string | null;
    /** Indique si le personnel exerce comme enseignant */
    is_enseignant: boolean;
    /** Spécialité ou discipline enseignée (ex: Mathématiques, Lettres modernes) */
    specialite?: string | null;
    /** Volume horaire d'enseignement hebdomadaire légal ou contractuel (défaut 18h) */
    volume_horaire_hebdo: number;
    /** Taux horaire de rémunération pour les heures de vacation (nullable) */
    taux_horaire_vacation?: number | null;
    /** Date de création de la fiche */
    created_at?: string;
    /** Date de dernière modification */
    updated_at?: string;

    /** Établissement d'affectation chargé */
    etablissement?: {
        id: number;
        nom: string;
        code: string;
    };
    /** Département pédagogique rattaché */
    departement?: {
        id: number;
        nom: string;
        code: string;
    };
    /** Compte utilisateur système rattaché (nullable) */
    user?: {
        id: number;
        name: string;
        email: string;
        login: string;
        is_active: boolean;
    } | null;
}

/**
 * Entité représentant un département pédagogique (ex: Mathématiques, Sciences Physiques, Langues).
 */
export interface Departement {
    /** Identifiant unique du département */
    id: number;
    /** Identifiant de l'institution */
    institution_id: number;
    /** Identifiant du personnel désigné chef de département (nullable) */
    chef_departement_id?: number | null;
    /** Nom du département (ex: Département des Sciences et Technologies) */
    nom: string;
    /** Code ou sigle court (ex: DPT-MATH) */
    code?: string | null;
    /** Description ou objectifs pédagogiques */
    description?: string | null;
    /** Indique si le département est actif */
    is_active: boolean;
    /** Nombre de membres du personnel rattachés (enseignants) */
    personnels_count?: number;
    /** Chef de département rattaché */
    chef_departement?: {
        id: number;
        nom: string;
        prenom?: string | null;
        matricule: string;
    } | null;
}

/**
 * Référence légère à un établissement pour les listes déroulantes.
 */
export interface EtablissementOption {
    id: number;
    nom: string;
    code: string;
}

/**
 * Référence légère à un compte utilisateur disponible pour liaison.
 */
export interface AvailableUserOption {
    id: number;
    name: string;
    email: string;
    login: string;
}

/**
 * Statistiques récapitulatives pour le tableau de bord du personnel RH.
 */
export interface PersonnelStats {
    /** Effectif total enregistré */
    total: number;
    /** Effectif actif */
    actifs: number;
    /** Nombre d'enseignants qualifiés */
    enseignants: number;
    /** Nombre de départements pédagogiques */
    departementsCount: number;
    /** Nombre de personnels disposant d'un compte utilisateur SYGES */
    avecCompte: number;
}

/**
 * Filtres d'affichage pour la liste du personnel.
 */
export interface PersonnelFilters {
    etablissement_id?: string;
    departement_id?: string;
    fonction?: string;
    contrat?: string;
    statut?: string;
    search?: string;
}

/**
 * Habilitations de sécurité de l'utilisateur connecté sur le module RH.
 */
export interface PersonnelPermissions {
    manage: boolean;
    delete: boolean;
    departements: boolean;
}

/**
 * Propriétés transmises par Inertia à la page index du personnel.
 */
export interface PersonnelPageProps {
    personnels: Personnel[];
    departements: Departement[];
    etablissements: EtablissementOption[];
    availableUsers: AvailableUserOption[];
    roles: string[];
    stats: PersonnelStats;
    can: PersonnelPermissions;
    filters: PersonnelFilters;
}

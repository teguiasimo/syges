/**
 * Types et interfaces pour le Module 10 : Génération et Calcul des Bulletins Scolaires (Gesco 10 & MINESEC).
 */

import { CoteAPC } from './note';

/**
 * Format d'affichage et d'impression du bulletin.
 * - 'avec_competences' : Nouvelles normes officielles camerounaises APC avec intitulé de compétence et cote.
 * - 'sans_competences' : Format traditionnel standard sans colonne de compétences.
 */
export type ModeBulletin = 'avec_competences' | 'sans_competences';

/**
 * Ligne d'une matière sur le bulletin pour un élève.
 */
export interface LigneMatiereBulletin {
    attribution_id: number;
    matiere_id: number;
    matiere_code: string;
    matiere_nom: string;
    enseignant_nom: string;
    groupe_id: number;
    groupe_nom: string;
    coefficient: number;
    note_eliminatoire?: number | null;
    est_eliminatoire: boolean;
    notes_evaluations: Record<number, {
        evaluation_id: number;
        code: string;
        nom: string;
        note: number | null;
        statut_presence: string;
        appreciation?: string | null;
        competence?: string | null;
    }>;
    moyenne: number | null;
    points: number;
    cote_apc: CoteAPC | null;
    competences: string;
    appreciation: string;
    rang_matiere?: string | null;
    min_classe?: number;
    max_classe?: number;
    moyenne_classe?: number;
}

/**
 * Groupe de matières (bloc pédagogique) sur le bulletin.
 */
export interface GroupeMatiereBulletin {
    groupe_id: number;
    groupe_nom: string;
    groupe_ordre: number;
    matieres: LigneMatiereBulletin[];
    total_points: number;
    total_coef: number;
    moyenne_groupe: number;
}

/**
 * Bilan disciplinaire d'un élève.
 */
export interface DisciplineEleve {
    absences_justifiees: number;
    absences_non_justifiees: number;
    retards: number;
    consignes: number;
    avertissements: number;
    blames: number;
    exclusions: number;
    decision_conseil: string;
}

/**
 * Données complètes d'un élève calculées pour le bulletin.
 */
export interface EleveBulletin {
    inscription_id: number;
    matricule: string;
    matricule_ministere?: string | null;
    nom_complet: string;
    date_naissance?: string | null;
    lieu_naissance?: string | null;
    genre: string;
    photo_url?: string | null;
    is_redoublant: boolean;
    lignes_matieres: Record<number, LigneMatiereBulletin> | any;
    groupes_matieres?: GroupeMatiereBulletin[];
    total_points: number;
    total_coefficients: number;
    moyenne_generale: number;
    cote_globale: CoteAPC;
    a_note_eliminatoire: boolean;
    rang_general: string | null;
    mention?: string | null;
    distinction?: string | null;
    discipline?: DisciplineEleve;
    decision_finale?: string;
}

/**
 * Statistiques globales de la classe pour les bulletins.
 * Supporte la compatibilité entre les formats trimestriels, séquentiels et annuels.
 */
export interface StatsClasseBulletin {
    /** Effectif total de la classe */
    effectif_total?: number;
    /** Alias effectif pour compatibilité séquentiel/annuel */
    effectif?: number;
    /** Effectif des élèves ayant composé */
    effectif_evalue?: number;
    /** Moyenne générale de la classe sur 20 */
    moyenne_generale?: number;
    /** Alias moyenne générale pour compatibilité */
    moyenne_classe?: number;
    /** Note / moyenne minimale obtenue */
    min_generale?: number;
    /** Alias note minimale */
    moyenne_min?: number;
    /** Note / moyenne maximale obtenue */
    max_generale?: number;
    /** Alias note maximale */
    moyenne_max?: number;
    /** Nombre d'élèves ayant la moyenne (>= 10) */
    nb_moyennes?: number;
    /** Alias nombre de moyennes */
    nombre_moyennes?: number;
    /** Taux de réussite en pourcentage (>= 10/20) */
    taux_reussite: number;
}

/**
 * Structure complète renvoyée par le moteur de calcul pour une classe (séquentiel, trimestriel ou annuel).
 */
export interface DonneesClasseBulletin {
    /** Type de bulletin : séquentiel, trimestriel ou annuel */
    type_bulletin?: 'sequentiel' | 'trimestriel' | 'annuel';
    classe: {
        id: number;
        nom: string;
        code: string;
        effectif: number;
        professeur_principal?: string | null;
        niveau: string;
        etablissement: {
            id?: number;
            nom: string;
            nom_en?: string | null;
            code?: string;
            sigle?: string | null;
            logo?: string | null;
            logo_url?: string | null;
            boite_postale?: string | null;
            telephone?: string | null;
            email?: string | null;
            region?: string | null;
            departement?: string | null;
            delegation_regionale?: string | null;
            delegation_departementale?: string | null;
            nom_chef?: string | null;
            ville?: string | null;
            devise?: string | null;
        };
    };
    evaluation?: {
        id: number;
        nom: string;
        code: string;
        date_debut?: string | null;
        date_fin?: string | null;
    };
    periode?: {
        id: number;
        nom: string;
        nom_en?: string | null;
        code: string;
        evaluations?: Array<{ id: number; code: string; nom: string }>;
    };
    annee_scolaire: {
        id: number;
        nom: string;
    };
    mode_bulletin: ModeBulletin;
    mode_calcul?: string;
    stats_classe: StatsClasseBulletin;
    eleves: EleveBulletin[];
}

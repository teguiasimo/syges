/**
 * Types et interfaces pour le Module 10 : Saisie des Notes (Gesco V1.1 & APC MINESEC).
 */

/**
 * Statut de présence d'un élève lors d'une session d'évaluation.
 */
export type StatutPresence = 'present' | 'absent_justifie' | 'absent_non_justifie' | 'non_evalue';

/**
 * Cote de niveau d'acquisition de la compétence (Approche Par Compétences - APC Cameroun).
 * - CTBA : Compétence Très Bien Acquise (16-20 / Expert)
 * - CBA  : Compétence Bien Acquise (14-15.99 / Acquis)
 * - CMA  : Compétence Moyennement Acquise (10-13.99 / En cours)
 * - CNA  : Compétence Non Acquise (< 10 / Non acquis)
 */
export type CoteAPC = 'CTBA' | 'CBA' | 'CMA' | 'CNA';

/**
 * Représente une ligne d'élève dans la grille de saisie des notes (TanStack Table).
 */
export interface NoteLigneGrille {
    inscription_id: number;
    matricule: string;
    nom_complet: string;
    photo_url?: string | null;
    valeur_note: number | null;
    statut_presence: StatutPresence;
    appreciation: string;
    competence_evaluee: string;
    cote: CoteAPC | null;
    is_verrouillee: boolean;
}

/**
 * Attribution d'un cours sélectionné pour la saisie.
 */
export interface AttributionNote {
    id: number;
    annee_scolaire_id: number;
    classe_id: number;
    matiere_id: number;
    personnel_id: number;
    volume_horaire_reel: string;
    is_titulaire: boolean;
    classe?: {
        id: number;
        nom: string;
        code: string;
        niveau?: {
            id: number;
            nom: string;
        };
    };
    matiere?: {
        id: number;
        code: string;
        nom: string;
        libelle_bulletin?: string | null;
    };
    personnel?: {
        id: number;
        nom_complet: string;
    };
}

/**
 * Évaluation / Séquence d'un trimestre.
 */
export interface EvaluationNote {
    id: number;
    periode_id: number;
    code: string;
    nom: string;
    ordre: number;
    ponderation: number;
    date_limite_saisie?: string | null;
    is_locked: boolean;
    statut: string;
    periode?: {
        id: number;
        nom: string;
        code: string;
    };
}

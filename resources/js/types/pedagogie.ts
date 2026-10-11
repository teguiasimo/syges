/**
 * Types TypeScript pour la Pédagogie par Compétences (APC) et Cotes d'Acquisition.
 */

export interface CompetencePeriodiqueItem {
    id: number;
    etablissement_id: number;
    classe_id: number;
    matiere_id: number;
    periode_id: number | null;
    evaluation_id: number | null;
    libelle_competence: string;
    description: string | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
    classe?: {
        id: number;
        nom: string;
        code?: string;
        niveau?: {
            id: number;
            nom: string;
        };
    };
    matiere?: {
        id: number;
        nom: string;
        code: string;
    };
    periode?: {
        id: number;
        nom: string;
        code: string;
    };
    evaluation?: {
        id: number;
        nom: string;
        code: string;
    };
}

export interface CoteConfigurationItem {
    id: number;
    etablissement_id: number;
    code_cote: string;
    libelle: string;
    libelle_en: string | null;
    borne_minimale: number;
    borne_maximale: number;
    couleur: string;
    ordre: number;
    created_at?: string;
    updated_at?: string;
}

export interface MatiereRef {
    id: number;
    nom: string;
    code: string;
    is_active: boolean;
}

export interface PeriodeRef {
    id: number;
    nom: string;
    code: string;
    ordre: number;
    evaluations?: Array<{
        id: number;
        nom: string;
        code: string;
        ordre: number;
    }>;
}

export interface ClasseItem {
    id: number;
    nom: string;
    code?: string;
    niveau_id: number;
    niveau?: {
        id: number;
        nom: string;
    };
}

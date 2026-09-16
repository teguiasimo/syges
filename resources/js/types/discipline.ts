/**
 * Définitions TypeScript pour le Module 12 : Absences, Retards & Discipline (Gesco V1.1).
 */

import { Eleve, Inscription } from './eleve';

export interface MatiereDiscipline {
    id: number;
    nom: string;
    code?: string;
    code_matiere?: string;
}

export type TypeEvenementAssiduite = 'absence' | 'retard';

export type StatutJustification = 'justifiee' | 'non_justifiee';

export type TypeSanction =
    | 'avertissement'
    | 'blame'
    | 'retenue'
    | 'exclusion_temporaire'
    | 'exclusion_definitive';

/**
 * Enregistrement d'assiduité (absence ou retard horodaté).
 */
export interface Assiduite {
    id: number;
    inscription_id: number;
    periode_id: number;
    type_evenement: TypeEvenementAssiduite;
    date_evenement: string;
    nombre_heures: number;
    matiere_id: number | null;
    statut_justification: StatutJustification;
    motif_justification: string | null;
    justificatif_path: string | null;
    signale_par: number | null;
    inscription?: Inscription & { eleve?: Eleve; classe?: { id: number; nom: string } };
    matiere?: MatiereDiscipline;
    signalant?: { id: number; name: string };
    created_at?: string;
    updated_at?: string;
}

/**
 * Sanction disciplinaire officielle.
 */
export interface SanctionDisciplinaire {
    id: number;
    inscription_id: number;
    periode_id: number;
    date_decision: string;
    type_sanction: TypeSanction;
    motif: string;
    nombre_jours_ou_heures: number;
    decide_par: string;
    document_notification_path: string | null;
    is_notified_parent: boolean;
    inscription?: Inscription & { eleve?: Eleve; classe?: { id: number; nom: string } };
    created_at?: string;
    updated_at?: string;
}

/**
 * Synthèse disciplinaire trimestrielle agrégée pour les bulletins.
 */
export interface SyntheseDisciplinaire {
    id: number;
    inscription_id: number;
    periode_id: number;
    absences_justifiees: number;
    absences_non_justifiees: number;
    retards: number;
    consignes: number;
    avertissements_conduite: number;
    blames_conduite: number;
    exclusions_jours: number;
    decision_conseil: string | null;
    inscription?: Inscription & { eleve?: Eleve; classe?: { id: number; nom: string } };
}

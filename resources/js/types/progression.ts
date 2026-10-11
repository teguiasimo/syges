/**
 * Types TypeScript pour le module Cahier de Texte & Progression Pédagogique (Aurore 5.1.8).
 */

export interface FicheProgression {
    id: number;
    etablissement_id: number;
    annee_scolaire_id: number;
    attribution_id: number;
    periode_id?: number | null;
    date_seance: string;
    semaine_numero?: number | null;
    chapitre: string;
    titre_lecon: string;
    resume_activite?: string | null;
    travail_a_faire?: string | null;
    heures_prevues: number | string;
    heures_effectuees: number | string;
    statut: 'fait' | 'en_cours' | 'reporte';
    motif_report?: string | null;
    visa_censeur: boolean;
    date_visa?: string | null;
    vise_par_id?: number | null;
    observations_censeur?: string | null;
    created_at?: string;
    updated_at?: string;

    attribution?: {
        id: number;
        volume_horaire_reel: number | string;
        classe?: {
            id: number;
            nom: string;
            code: string;
        };
        matiere?: {
            id: number;
            nom: string;
            code: string;
        };
        personnel?: {
            id: number;
            nom: string;
            prenom?: string;
            matricule: string;
        };
    };
    periode?: {
        id: number;
        nom: string;
        code: string;
    } | null;
    vise_par?: {
        id: number;
        name: string;
    } | null;
}

export interface ProgressionStats {
    total_seances: number;
    total_heures_prevues: number;
    total_heures_effectuees: number;
    total_visees: number;
    total_en_attente_visa: number;
    taux_realisation: number;
}

export interface ProgressionFilters {
    classe_id?: string | number | null;
    matiere_id?: string | number | null;
    personnel_id?: string | number | null;
    periode_id?: string | number | null;
    statut?: string | null;
    visa?: string | null;
    search?: string | null;
}

export interface ProgressionPageProps {
    fiches: {
        data: FicheProgression[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: ProgressionStats;
    filters: ProgressionFilters;
    classes: Array<{ id: number; nom: string; code: string }>;
    matieres: Array<{ id: number; nom: string; code: string }>;
    enseignants: Array<{ id: number; nom: string; prenom?: string; matricule: string }>;
    periodes: Array<{ id: number; nom: string; code: string }>;
    attributions: Array<{
        id: number;
        classe_id: number;
        matiere_id: number;
        personnel_id: number;
        volume_horaire_reel: number;
        classe?: { id: number; nom: string; code: string };
        matiere?: { id: number; nom: string; code: string };
        personnel?: { id: number; nom: string; prenom?: string };
    }>;
}

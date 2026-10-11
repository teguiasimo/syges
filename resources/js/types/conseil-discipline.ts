/**
 * Types TypeScript pour le Conseil de Discipline & Heures de Consigne (Phase 5).
 */

import type { Inscription, EtablissementRef, AnneeScolaireRef } from './eleve';

export type StatutSession = 'planifie' | 'en_cours' | 'tenu' | 'annule';
export type DecisionConseil =
    | 'en_attente'
    | 'acquittement'
    | 'avertissement'
    | 'blame'
    | 'consigne'
    | 'exclusion_temporaire'
    | 'exclusion_definitive';

export type StatutConsigne = 'programmee' | 'effectuee' | 'non_effectuee' | 'reportee';

export interface DossierConseilDisciplineItem {
    id: number;
    conseil_discipline_id: number;
    inscription_id: number;
    eleve_id: number;
    motifs_renvoi: string;
    plaidoirie_eleve: string | null;
    decision_finale: DecisionConseil;
    nombre_jours_exclusion: number | null;
    nombre_heures_consigne: number | null;
    date_convocation: string | null;
    parent_present: boolean;
    is_convocation_envoyee: boolean;
    created_at?: string;
    eleve: {
        id: number;
        matricule: string;
        nom: string;
        prenom: string;
        sexe?: string;
        photo_url?: string | null;
    };
    inscription: {
        id: number;
        classe_id: number;
        classe: {
            id: number;
            nom: string;
            code?: string;
        };
    };
}

export interface ConseilDisciplineSessionItem {
    id: number;
    etablissement_id: number;
    annee_scolaire_id: number;
    numero_session: string;
    date_conseil: string;
    lieu: string;
    president_seance: string;
    rapporteur: string;
    membres_presents: string | null;
    statut: StatutSession;
    observations_generales: string | null;
    created_at?: string;
    dossiers: DossierConseilDisciplineItem[];
}

export interface HeureConsigneItem {
    id: number;
    etablissement_id: number;
    annee_scolaire_id: number;
    inscription_id: number;
    eleve_id: number;
    date_consigne: string;
    heure_debut: string | null;
    heure_fin: string | null;
    nombre_heures: number;
    motif: string;
    travail_assigne: string | null;
    surveillant_responsable: string | null;
    statut: StatutConsigne;
    observations: string | null;
    created_at?: string;
    eleve: {
        id: number;
        matricule: string;
        nom: string;
        prenom: string;
    };
    inscription: {
        id: number;
        classe_id: number;
        classe: {
            id: number;
            nom: string;
        };
    };
}

export interface ConseilDisciplineStats {
    total_sessions: number;
    total_dossiers: number;
    total_exclusions: number;
    total_heures_consigne: number;
}

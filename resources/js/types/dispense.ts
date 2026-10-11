/**
 * Types TypeScript pour la gestion des Dérogations & Dispenses Officielles (Phase 4).
 */

import type { Eleve, Inscription, EtablissementRef, AnneeScolaireRef } from './eleve';
import type { MatiereRef, PeriodeRef } from './pedagogie';

export type TypeDispense = 'medicale' | 'administrative' | 'handicap' | 'derogation_special';
export type StatutDispense = 'validee' | 'en_attente' | 'rejetee' | 'expiree';

export interface DispenseItem {
    id: number;
    etablissement_id: number;
    annee_scolaire_id: number;
    inscription_id: number;
    eleve_id: number;
    matiere_id: number;
    periode_id: number | null;
    type_dispense: TypeDispense;
    motif: string;
    reference_certificat: string | null;
    medecin_ou_autorite: string | null;
    date_debut: string | null;
    date_fin: string | null;
    neutraliser_coefficient: boolean;
    statut: StatutDispense;
    created_by?: number | null;
    created_at?: string;
    updated_at?: string;
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
            niveau?: {
                id: number;
                nom: string;
            };
        };
    };
    matiere: MatiereRef;
    periode?: PeriodeRef | null;
    auteur?: {
        id: number;
        name: string;
    } | null;
}

export interface DispenseStats {
    total_dispenses: number;
    total_validees: number;
    total_eps: number;
    total_en_attente: number;
}

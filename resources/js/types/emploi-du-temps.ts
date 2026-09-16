/**
 * Définitions TypeScript pour le Module 13 : Emplois du Temps & Gestion des Salles (Gesco V1.1).
 */

import { ClasseRef } from './eleve';
import { Attribution } from './attribution';
import { Personnel } from './personnel';

export type JourSemaine = 'lundi' | 'mardi' | 'mercredi' | 'jeudi' | 'vendredi' | 'samedi';

export type TypeSalle = 'standard' | 'laboratoire' | 'informatique' | 'sport' | 'polyvalente';

export type StatutSalle = 'disponible' | 'indisponible';

export type StatutSeance = 'actif' | 'annule' | 'remplace';

/**
 * Salle physique ou local d'enseignement.
 */
export interface Salle {
    id: number;
    etablissement_id: number;
    nom: string;
    code: string;
    type_salle: TypeSalle;
    capacite_places: number;
    statut: StatutSalle;
    description?: string | null;
    seances_emploi_temps_count?: number;
    created_at?: string;
    updated_at?: string;
}

/**
 * Séance d'emploi du temps hebdomadaire.
 */
export interface SeanceEmploiTemps {
    id: number;
    annee_scolaire_id: number;
    classe_id: number;
    attribution_id: number;
    salle_id?: number | null;
    jour_semaine: JourSemaine;
    heure_debut: string;
    heure_fin: string;
    statut: StatutSeance;
    remplace_par_personnel_id?: number | null;
    motif_annulation?: string | null;
    duree_minutes?: number;
    duree_heures?: number;
    classe?: ClasseRef;
    attribution?: Attribution;
    salle?: Salle | null;
    remplacant?: Personnel | null;
    created_at?: string;
    updated_at?: string;
}

/**
 * Synthèse RH des heures effectives dispensées pour un enseignant.
 */
export interface DecompteRH {
    heures_regulieres: number;
    heures_remplacement: number;
    heures_annulees: number;
    total_heures_effectives: number;
}

/**
 * Types TypeScript pour le Module 16 : Portail Parents & Élèves (Gesco V1.1).
 */

export interface EnfantResume {
    id: number;
    matricule: string;
    matricule_national?: string | null;
    nom: string;
    prenom?: string | null;
    sexe: 'M' | 'F';
    date_naissance?: string | null;
    photo_url?: string | null;
    inscriptions?: Array<{
        id: number;
        statut: string;
        classe?: {
            id: number;
            nom: string;
            niveau?: {
                id: number;
                nom: string;
            };
        };
    }>;
}

export interface SeanceEmploiTempsPortail {
    id: number;
    jour_semaine: number;
    jour_libelle: string;
    heure_debut: string;
    heure_fin: string;
    plage_horaire: string;
    matiere_nom: string;
    matiere_code: string;
    enseignant_nom: string;
    salle_nom: string;
    statut: string;
    is_annule: boolean;
}

export interface LigneMatierePortail {
    attribution_id: number;
    matiere_id: number;
    matiere_code: string;
    matiere_nom: string;
    enseignant_nom: string;
    groupe_nom: string;
    coefficient: number;
    moyenne?: number | null;
    note?: number | null;
    cote_apc?: string | null;
    appreciation?: string;
    note_eliminatoire?: number | null;
    est_eliminatoire: boolean;
    rang_matiere?: number | string | null;
}

export interface AcademiquePortail {
    inscription_id: number;
    matricule: string;
    nom_complet: string;
    total_points: number;
    total_coefficients: number;
    moyenne_generale: number;
    rang_general?: number | string | null;
    mention?: string | null;
    lignes_matieres: Record<string, LigneMatierePortail> | LigneMatierePortail[];
    appreciation_generale?: string;
    decision_finale?: string;
}

export interface SanctionPortail {
    id: number;
    nature_sanction: string;
    motif: string;
    date_decision: string;
    degre_gravite: string;
}

export interface DisciplinePortail {
    absences_justifiees: number;
    absences_non_justifiees: number;
    consignes: number;
    sanctions: SanctionPortail[];
}

export interface VersementPortail {
    id: number;
    numero_recu: string;
    date_versement: string;
    montant: number;
    mode_paiement: string;
    motif: string;
}

export interface FinancesPortail {
    compte_id?: number;
    debit_initial: number;
    remise_bourse: number;
    montant_net_exigible: number;
    total_paye: number;
    solde_restant: number;
    statut_financier: string;
    versements: VersementPortail[];
}

export interface VerrouSolvabilite {
    est_bloque: boolean;
    verrou_actif: boolean;
    solde_restant: number;
    message?: string | null;
}

export interface ProfilCompletEnfant {
    eleve: {
        id: number;
        matricule: string;
        matricule_national?: string | null;
        nom: string;
        prenom?: string | null;
        nom_complet: string;
        sexe: 'M' | 'F';
        date_naissance?: string | null;
        lieu_naissance?: string | null;
        photo_url?: string | null;
    };
    inscription?: {
        id: number;
        statut: string;
        is_redoublant: boolean;
    } | null;
    classe?: {
        id: number;
        nom: string;
        professeur_principal: string;
    } | null;
    annee_scolaire: {
        id: number;
        nom: string;
    };
    periodes: Array<{
        id: number;
        nom: string;
        ordre: number;
    }>;
    active_periode_id?: number;
    academique?: AcademiquePortail | null;
    statistiques_classe?: {
        moyenne_classe?: number;
        moyenne_max?: number;
        moyenne_min?: number;
        taux_reussite?: number;
    };
    emploi_du_temps: SeanceEmploiTempsPortail[];
    discipline: DisciplinePortail;
    finances: FinancesPortail;
    verrou_solvabilite: VerrouSolvabilite;
}

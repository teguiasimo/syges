/**
 * Types TypeScript pour la Gestion des Dépenses, Charges & Fiches de Décharge (Phase 1).
 */

export type ModeReglement = 'especes' | 'virement' | 'cheque' | 'orange_money' | 'mtn_momo';
export type StatutDepense = 'validee' | 'en_attente' | 'annulee';

export interface CategorieDepenseItem {
    id: number;
    etablissement_id: number | null;
    code: string;
    nom: string;
    description: string | null;
    couleur: string;
    is_active: boolean;
}

export interface CaisseRef {
    id: number;
    code: string;
    nom: string;
    type?: string;
    solde_actuel?: number;
    is_active?: boolean;
}

export interface DepenseItem {
    id: number;
    etablissement_id: number;
    annee_scolaire_id: number;
    caisse_id: number;
    categorie_depense_id: number;
    numero_piece: string;
    beneficiaire: string;
    montant: number;
    date_depense: string;
    motif: string;
    mode_reglement: ModeReglement;
    reference_paiement: string | null;
    justificatif_path: string | null;
    statut: StatutDepense;
    created_at?: string;
    caisse: CaisseRef;
    categorieDepense: CategorieDepenseItem;
    auteur?: {
        id: number;
        name: string;
    } | null;
}

export interface FinanceDepenseStats {
    total_depenses: number;
    nb_depenses: number;
    total_recettes: number;
    total_versements: number;
    total_divers: number;
    solde_net: number;
}

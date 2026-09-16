/**
 * Définitions TypeScript pour le Module 11 : Gestion de la Caisse & Paiements (Finance & Comptabilité Scolaire).
 * Conforme au cahier des charges Gesco V1.1.
 */

import { Eleve, Inscription } from './eleve';
import { Personnel } from './personnel';

export type ModePaiement = 'especes' | 'cheque' | 'virement' | 'mobile_money_manuel';

export type StatutFinancier = 'insolvable' | 'en_regle_tranche_1' | 'en_regle_tranche_2' | 'solde';

export type PeriodiciteFrais = 'annuel' | 'trimestriel' | 'mensuel' | 'ponctuel';

/**
 * Type de frais (nomenclature).
 */
export interface TypeFrais {
    id: number;
    etablissement_id: number;
    code: string;
    libelle: string;
    description: string | null;
    obligatoire: boolean;
    periodicite: PeriodiciteFrais;
    montant_defaut: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

/**
 * Ligne de tarif paramétrée dans la grille tarifaire.
 */
export interface FraisScolaire {
    id: number;
    annee_scolaire_id: number;
    etablissement_id: number;
    niveau_id: number;
    serie: string | null;
    type_frais_id: number;
    tranche_numero: number;
    libelle_tranche: string | null;
    montant: number;
    date_limite: string | null;
    niveau?: {
        id: number;
        nom: string;
    };
    type_frais?: TypeFrais;
}

/**
 * Ligne de ventilation d'un versement.
 */
export interface VentilationVersement {
    id: number;
    versement_id: number;
    type_frais_id: number | null;
    frais_scolaire_id: number | null;
    libelle: string;
    montant: number;
}

/**
 * Versement physique encaissé au guichet.
 */
export interface VersementCaisse {
    id: number;
    reference_recu: string;
    compte_financier_id: number;
    inscription_id: number;
    etablissement_id: number;
    personnel_id: number | null;
    cloture_caisse_id: number | null;
    date_versement: string;
    montant_total: number;
    mode_paiement: ModePaiement;
    reference_paiement: string | null;
    recu_par: string | null;
    payeur_nom: string | null;
    payeur_telephone: string | null;
    statut: 'valide' | 'annule';
    motif_annulation: string | null;
    annule_par: number | null;
    annule_le: string | null;
    observations: string | null;
    ventilations?: VentilationVersement[];
    compte_financier?: CompteFinancierEleve;
    inscription?: Inscription;
    caissier?: Personnel;
}

/**
 * Compte courant financier de l'élève.
 */
export interface CompteFinancierEleve {
    id: number;
    inscription_id: number;
    eleve_id: number;
    etablissement_id: number;
    annee_scolaire_id: number;
    debit_initial: number;
    remise_bourse: number;
    montant_net_exigible: number;
    total_paye: number;
    solde_restant: number;
    statut_financier: StatutFinancier;
    observations: string | null;
    eleve?: Eleve;
    inscription?: Inscription;
}

/**
 * Clôture journalière de caisse.
 */
export interface ClotureCaisse {
    id: number;
    etablissement_id: number;
    personnel_id: number | null;
    date_cloture: string;
    total_especes: number;
    total_cheques: number;
    total_virements: number;
    total_mobile_money: number;
    total_general: number;
    nombre_transactions: number;
    statut: 'ouverte' | 'cloturee';
    observations: string | null;
    cloture_a: string | null;
    caissier?: Personnel;
    verificateur?: {
        id: number;
        name: string;
    };
}

/**
 * Synthèse journalière de la caisse pour le jour J.
 */
export interface JournalDuJour {
    date: string;
    total_especes: number;
    total_cheques: number;
    total_virements: number;
    total_mobile_money: number;
    total_general: number;
    nombre_transactions: number;
    est_cloture: boolean;
}

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
    arrieres_anterieurs?: number;
    remise_bourse: number;
    montant_net_exigible: number;
    total_paye: number;
    solde_restant: number;
    statut_financier: StatutFinancier;
    observations: string | null;
    eleve?: Eleve;
    inscription?: Inscription;
    bourses_remises?: EleveBourseRemise[];
    penalites?: PenaliteEleve[];
}

/**
 * Clôture journalière de caisse.
 */
export interface ClotureCaisse {
    id: number;
    etablissement_id: number;
    caisse_id?: number | null;
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

/**
 * Caisse physique ou logique de l'établissement (Module Aurore §3.20).
 */
export interface Caisse {
    id: number;
    etablissement_id: number;
    code: string;
    nom: string;
    solde_initial: number;
    responsable_id: number | null;
    is_principale: boolean;
    is_active: boolean;
    description: string | null;
    responsable?: Personnel;
    created_at?: string;
    updated_at?: string;
}

/**
 * Règlement divers / encaissement hors scolarité (Module Aurore §4.2).
 */
export interface ReglementDivers {
    id: number;
    etablissement_id: number;
    caisse_id: number;
    reference_recu: string;
    date_reglement: string;
    payeur_nom: string;
    payeur_telephone: string | null;
    categorie: string;
    libelle: string;
    montant: number;
    mode_paiement: ModePaiement;
    reference_paiement: string | null;
    caissier_id: number | null;
    user_id: number | null;
    statut: 'valide' | 'annule';
    motif_annulation: string | null;
    observations: string | null;
    caisse?: Caisse;
    caissier?: Personnel;
    created_at?: string;
    updated_at?: string;
}

/**
 * Référentiel de bourse ou de remise (Module Aurore §3.19, §3.21, §3.25).
 */
export interface BourseRemise {
    id: number;
    etablissement_id: number;
    annee_scolaire_id: number | null;
    code: string;
    nom: string;
    type: 'bourse' | 'remise';
    categorie: 'excellence' | 'sociale' | 'fratrie' | 'personnel' | 'autre';
    mode_calcul: 'pourcentage' | 'montant_fixe';
    valeur: number;
    niveau_id: number | null;
    type_frais_id: number | null;
    description: string | null;
    is_active: boolean;
    niveau?: { id: number; nom: string };
    type_frais?: TypeFrais;
    created_at?: string;
    updated_at?: string;
}

/**
 * Attribution d'une bourse ou d'une remise à un élève.
 */
export interface EleveBourseRemise {
    id: number;
    compte_financier_id: number;
    bourse_remise_id: number;
    montant_calcule: number;
    date_attribution: string;
    motif: string | null;
    attribue_par: number | null;
    bourse_remise?: BourseRemise;
    created_at?: string;
    updated_at?: string;
}

/**
 * Tranche calendaire d'un échéancier.
 */
export interface EcheanceTranche {
    id: number;
    modele_echeance_id: number;
    tranche_numero: number;
    libelle: string;
    date_limite: string;
    pourcentage_exigible: number;
}

/**
 * Modèle d'échéancier calendaire (Module Aurore §3.24).
 */
export interface ModeleEcheance {
    id: number;
    etablissement_id: number;
    annee_scolaire_id: number;
    nom: string;
    description: string | null;
    is_default: boolean;
    tranches?: EcheanceTranche[];
    created_at?: string;
    updated_at?: string;
}

/**
 * Règle de calcul des pénalités de retard (Module Aurore §3.17).
 */
export interface PenaliteConfiguration {
    id: number;
    etablissement_id: number;
    nom: string;
    mode_calcul: 'montant_fixe' | 'pourcentage';
    valeur: number;
    jours_grace: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

/**
 * Pénalité effective appliquée à un élève.
 */
export interface PenaliteEleve {
    id: number;
    compte_financier_id: number;
    penalite_configuration_id: number | null;
    libelle: string;
    montant: number;
    date_application: string;
    statut: 'a_payer' | 'paye' | 'annule';
    motif: string | null;
    applique_par: number | null;
    configuration?: PenaliteConfiguration;
    created_at?: string;
    updated_at?: string;
}

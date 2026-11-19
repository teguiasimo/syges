/**
 * Définitions TypeScript pour le Module 11 : Gestion de la Caisse & Paiements (Finance & Comptabilité Scolaire).
 * Conforme au cahier des charges Gesco V1.1.
 */

import { Eleve, Inscription } from './eleve';
import { Personnel } from './personnel';

export type ModePaiement = 'especes' | 'cheque' | 'virement' | 'mobile_money_manuel';

export type StatutFinancier =
    | 'insolvable'
    | 'en_attente'
    | 'acompte'
    | 'inscrit_a_jour'
    | 'en_regle_tranche_1'
    | 'en_regle_tranche_2'
    | 'solde';

/**
 * Formats d'impression officiels des reçus de caisse : A4 (2 exemplaires/page) ou Ticket thermique 80mm.
 */
export type FormatImpressionRecu = 'a4' | 'ticket';

const RECEIPT_FORMAT_STORAGE_KEY = 'syges_recu_format';

/**
 * Lit la préférence persistée du format d'impression de reçu (`a4` par défaut ou `ticket`).
 * Migre automatiquement toute ancienne préférence `a5` vers le format standard `a4`.
 *
 * @returns {FormatImpressionRecu} Format mémorisé pour le poste de caisse
 */
export function getPreferredReceiptFormat(): FormatImpressionRecu {
    if (typeof window === 'undefined') {
        return 'a4';
    }
    const stored = window.localStorage.getItem(RECEIPT_FORMAT_STORAGE_KEY);
    return stored === 'ticket' ? 'ticket' : 'a4';
}

/**
 * Mémorise la préférence du format d'impression de reçu dans le navigateur du guichet.
 *
 * @param {FormatImpressionRecu} format Format choisi (`a4` ou `ticket`)
 */
export function setPreferredReceiptFormat(format: FormatImpressionRecu): void {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(RECEIPT_FORMAT_STORAGE_KEY, format);
    }
}

/**
 * Construit l'URL officielle d'impression PDF d'un versement au format A4 ou Ticket 80mm.
 *
 * @param {number} versementId Identifiant du versement caisse
 * @param {FormatImpressionRecu} format Gabarit d'impression souhaité
 * @returns {string} URL du PDF à ouvrir
 */
export function buildReceiptPrintUrl(
    versementId: number,
    format: FormatImpressionRecu = 'a4'
): string {
    const base = `/administration/finances/versements/${versementId}/recu`;
    return format === 'ticket' ? `${base}?format=ticket` : base;
}

export type PeriodiciteFrais = 'annuel' | 'trimestriel' | 'mensuel' | 'ponctuel';

/**
 * Assiette de calcul d'une bourse ou d'une remise en pourcentage.
 * - `total_scolarite` : calculée sur toute la scolarité nette de l'élève (défaut)
 * - `tranche_ciblee` : calculée uniquement sur le montant net de la tranche ciblée
 */
export type BaseCalculRemise = 'total_scolarite' | 'tranche_ciblee';

/**
 * Retourne le libellé lisible en français d'une assiette de calcul de remise.
 *
 * @param {string | null | undefined} baseCalcul Code de l'assiette de calcul
 * @returns {string} Libellé explicite pour l'utilisateur
 */
export function formatBaseCalculRemiseLabel(baseCalcul?: string | null): string {
    return baseCalcul === 'tranche_ciblee'
        ? 'Tranche ciblée uniquement'
        : 'Toute la scolarité';
}

/**
 * Cible d'imputation d'une bourse ou d'une remise sur les tranches obligatoires.
 */
export type CibleImputationRemise =
    | 'derniere_tranche'
    | 'tranche_1'
    | 'tranche_2'
    | 'tranche_3'
    | 'tranche_4'
    | 'proportionnelle';

/**
 * Retourne le libellé lisible en français d'une cible d'imputation de remise.
 *
 * @param {string | null | undefined} cible Code de la cible d'imputation
 * @returns {string} Libellé explicite pour l'utilisateur
 */
export function formatCibleImputationLabel(cible?: string | null): string {
    switch (cible) {
        case 'tranche_1':
            return '1ère tranche';
        case 'tranche_2':
            return '2ème tranche';
        case 'tranche_3':
            return '3ème tranche';
        case 'tranche_4':
            return '4ème tranche';
        case 'proportionnelle':
            return 'Répartition proportionnelle';
        case 'derniere_tranche':
        default:
            return 'Dernière tranche';
    }
}

/**
 * Catégorie légère de regroupement des frais du Catalogue (ex: Scolarité, Frais d'Examen, Fête).
 * Sert à classer les frais sans alourdir la configuration et à calculer les montants globaux par catégorie.
 */
export interface CategorieFrais {
    id: number;
    etablissement_id: number;
    nom: string;
    description: string | null;
    is_active: boolean;
    types_frais_count?: number;
}

/**
 * Référence d'un niveau pédagogique enrichi de son cycle et de sa section pour l'affectation groupée.
 */
export interface NiveauFinanceRef {
    id: number;
    code?: string;
    nom: string;
    ordre?: number;
    cycle_id?: number;
    cycle?: {
        id: number;
        nom: string;
        section?: {
            id: number;
            nom: string;
        } | null;
    } | null;
}

/**
 * Frais configuré dans le Catalogue des Frais de l'établissement.
 */
export interface TypeFrais {
    id: number;
    etablissement_id: number;
    categorie_frais_id?: number | null;
    code: string;
    libelle: string;
    description: string | null;
    obligatoire: boolean;
    periodicite: PeriodiciteFrais;
    ordre_priorite?: number;
    montant_defaut: number;
    date_limite_defaut?: string | null;
    is_active: boolean;
    categorie_frais?: CategorieFrais | null;
    frais_scolaires?: FraisScolaire[];
    created_at?: string;
    updated_at?: string;
}

/**
 * Ligne d'affectation d'un frais du catalogue à un niveau dans la grille tarifaire.
 */
export interface FraisScolaire {
    id: number;
    annee_scolaire_id: number;
    etablissement_id: number;
    niveau_id: number;
    serie: string | null;
    type_frais_id: number;
    tranche_numero: number;
    ordre_priorite?: number | null;
    libelle_tranche: string | null;
    montant: number;
    obligatoire?: boolean;
    date_limite: string | null;
    niveau?: NiveauFinanceRef;
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
    total_dispense?: number;
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
    dispenses_financieres?: DispenseFinanciere[];
    souscriptions_optionnelles?: SouscriptionFraisOptionnel[];
    penalites?: PenaliteEleve[];
    versements?: VersementCaisse[];
}

/**
 * Souscription d'un élève à un frais optionnel (Bus, Cantine, Internat, etc.).
 */
export interface SouscriptionFraisOptionnel {
    id: number;
    compte_financier_id: number;
    frais_scolaire_id: number;
    montant: number;
    date_souscription: string;
    statut: 'actif' | 'annule';
    observations: string | null;
    frais_scolaire?: FraisScolaire;
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
 * Catégorie personnalisable (Recette diverse ou Bourse / Remise).
 */
export interface CategorieFinanciere {
    id: number;
    etablissement_id: number | null;
    nom: string;
    is_active: boolean;
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
    categorie: string;
    mode_calcul: 'pourcentage' | 'montant_fixe';
    valeur: number;
    base_calcul?: BaseCalculRemise;
    cible_imputation?: CibleImputationRemise;
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
    cible_imputation?: CibleImputationRemise | null;
    frais_scolaire_id?: number | null;
    date_attribution: string;
    motif: string | null;
    attribue_par: number | null;
    bourse_remise?: BourseRemise;
    created_at?: string;
    updated_at?: string;
}

/**
 * Dispense financière (totale ou partielle) accordée à un élève sur une tranche précise
 * (ex: élève arrivant au 2ème trimestre dispensé de la Tranche 1).
 */
export interface DispenseFinanciere {
    id: number;
    compte_financier_id: number;
    frais_scolaire_id: number;
    type_dispense: 'totale' | 'partielle';
    montant_dispense: number;
    motif: string;
    date_dispense: string;
    accorde_par?: number | null;
    frais_scolaire?: FraisScolaire;
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

/**
 * Matrice normalisée des permissions granulaires du module financier transmise par FinanceController.
 */
export interface CanFinancePermissions {
    view: boolean;
    manage: boolean;
    etats?: boolean;
    frais_configure?: boolean;
    versements_create: boolean;
    versements_edit?: boolean;
    recettes_diverses_create?: boolean;
    versements_cancel?: boolean;
    caisses_manage?: boolean;
    caisses_cloture?: boolean;
    bourses_manage: boolean;
    bourses_attribuer?: boolean;
    dispenses_manage?: boolean;
    options_manage?: boolean;
    verrou_solvabilite?: boolean;
    echeances_manage?: boolean;
    penalites_manage?: boolean;
}

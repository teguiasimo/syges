/**
 * Définitions TypeScript pour le Module 14 : Communication & Notifications SMS (Gesco V1.1).
 */

import { Inscription } from './eleve';

export type FournisseurSms = 'orange' | 'mtn' | 'africas_talking' | 'autre';

export type TypeCible = 'tous' | 'section' | 'niveau' | 'classe' | 'insolvables';

export type TypeNotification =
    | 'alerte_absence'
    | 'alerte_sanction'
    | 'rappel_paiement'
    | 'confirmation_recu'
    | 'campagne_masse'
    | 'personnalise';

export type StatutDistribution = 'en_attente' | 'envoye' | 'echec';

/**
 * Configuration de la passerelle SMS.
 */
export interface SmsConfiguration {
    id: number;
    etablissement_id: number;
    fournisseur: FournisseurSms;
    api_url: string | null;
    api_key?: string | null;
    sender_id: string;
    solde_credits: number;
    seuil_alerte_solde: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

/**
 * Campagne d'envoi groupé de SMS.
 */
export interface SmsCampagne {
    id: number;
    etablissement_id: number;
    titre: string;
    message_brut: string;
    message_sanitise: string;
    cible_type: TypeCible;
    cible_id: number | null;
    nombre_destinataires: number;
    total_credits: number;
    statut: string;
    lance_par_user_id: number | null;
    lanceur?: { id: number; name: string };
    created_at?: string;
    updated_at?: string;
}

/**
 * Entrée de traçabilité individuelle d'un SMS envoyé.
 */
export interface HistoriqueSms {
    id: number;
    etablissement_id: number;
    campagne_id: number | null;
    inscription_id: number | null;
    type_notification: TypeNotification;
    destinataire_nom: string | null;
    destinataire_telephone: string;
    message: string;
    statut_distribution: StatutDistribution;
    credits_consommes: number;
    reponse_fournisseur: string | null;
    inscription?: Inscription;
    campagne?: SmsCampagne;
    created_at?: string;
    updated_at?: string;
}

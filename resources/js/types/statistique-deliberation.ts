/**
 * Types TypeScript pour le Module 15 : Tableau de Bord, Statistiques & Conseil de Classe (Gesco V1.1).
 */

export interface PariteGenre {
    garcons: number;
    filles: number;
    taux_garcons: number;
    taux_filles: number;
    ratio_texte: string;
}

export interface RepartitionStatut {
    nouveaux: number;
    redoublants: number;
    taux_redoublants: number;
}

export interface ClasseDensiteDetail {
    id: number;
    nom: string;
    effectif: number;
    capacite: number;
    taux_occupation: number;
    is_surchargee: boolean;
}

export interface DensiteClasses {
    nombre_classes: number;
    moyenne_par_classe: number;
    classes_surchargees: number;
    details: ClasseDensiteDetail[];
}

export interface CycleRepartitionDetail {
    nom: string;
    cycle: string;
    section: string;
    total: number;
    pourcentage: number;
}

export interface StatistiqueDemographique {
    annee_scolaire: string;
    effectif_total: number;
    parite: PariteGenre;
    repartition_statut: RepartitionStatut;
    densite: DensiteClasses;
    repartition_cycles?: CycleRepartitionDetail[];
}

export interface EleveDecrochage {
    inscription_id: number;
    nom_complet: string;
    matricule: string;
    classe_nom: string;
    moyenne_generale: number;
    absences_non_justifiees: number;
    gravite: 'alerte' | 'critique';
    motif: string;
}

export interface StatistiquePedagogique {
    total_evalues: number;
    total_admis: number;
    taux_reussite_global: number;
    total_decrochage: number;
    eleves_decrochage: EleveDecrochage[];
    periode_nom: string;
}

export interface StatistiqueFinanciere {
    total_facture: number;
    total_recouvre: number;
    total_creances: number;
    taux_recouvrement: number;
    total_comptes: number;
    nombre_soldes: number;
    nombre_debiteurs: number;
}

export interface LigneBordereauOfficiel {
    rang_alphabetique: number;
    inscription_id: number;
    matricule: string;
    matricule_ministere: string;
    nom: string;
    prenom: string;
    nom_complet: string;
    sexe: 'M' | 'F';
    date_naissance: string;
    lieu_naissance: string;
    classe_nom: string;
    is_redoublant: boolean;
    statut_candidat: string;
}

export interface LigneDeliberation {
    deliberation_id?: number | null;
    inscription_id: number;
    matricule: string;
    matricule_ministere: string;
    nom_complet: string;
    sexe: 'M' | 'F';
    is_redoublant: boolean;
    moyenne_obtenue: number;
    rang?: number | null;
    absences_non_justifiees: number;
    a_note_eliminatoire: boolean;
    mention_theorique: 'aucune' | 'tableau_honneur' | 'encouragements' | 'felicitations';
    mention_obtenue: 'aucune' | 'tableau_honneur' | 'encouragements' | 'felicitations';
    mention_bloquee: boolean;
    motif_blocage_mention?: string | null;
    statut_propose: 'promu' | 'redouble' | 'exclu' | 'en_attente';
    statut_retenu: 'promu' | 'redouble' | 'exclu' | 'en_attente';
    appreciation_globale: string;
    decision_conseil: string;
    observations?: string;
    est_valide: boolean;
    valide_at?: string;
    valide_par?: string;
}

export interface DeliberationData {
    classe: {
        id: number;
        nom: string;
        professeur_principal: string;
    };
    type_deliberation: 'trimestrielle' | 'annuelle';
    periode?: {
        id: number;
        nom: string;
    } | null;
    annee_scolaire: {
        id: number;
        nom: string;
    };
    eleves: LigneDeliberation[];
    statistiques_classe: {
        moyenne_classe?: number;
        moyenne_max?: number;
        moyenne_min?: number;
        taux_reussite?: number;
        effectif_evalue?: number;
    };
    toutes_validees: boolean;
}

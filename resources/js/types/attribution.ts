/**
 * Types TypeScript pour le Module 9 : Attribution des Classes & Matières (SYGES).
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 9.1, 9.2 et 2.7.5).
 */

/**
 * Attribution d'un enseignant à une classe et une matière pour une année scolaire.
 */
export interface Attribution {
    id: number;
    annee_scolaire_id: number;
    personnel_id: number;
    classe_id: number;
    matiere_id: number;
    volume_horaire_reel: number;
    is_titulaire: boolean;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;

    /** Enseignant rattaché */
    personnel?: {
        id: number;
        nom: string;
        prenom?: string | null;
        matricule: string;
        telephone?: string;
        specialite?: string | null;
    };

    /** Classe bénéficiaire */
    classe?: {
        id: number;
        nom: string;
        code: string;
    };

    /** Matière enseignée */
    matiere?: {
        id: number;
        code: string;
        nom: string;
        libelle_bulletin?: string | null;
    };
}

/**
 * Fiche de classe enrichie avec son professeur principal et ses attributions de cours.
 */
export interface ClasseWithAttributions {
    id: number;
    etablissement_id?: number;
    niveau_id: number;
    code: string;
    nom: string;
    serie?: string | null;
    capacite_max: number;
    professeur_principal_id?: number | null;
    is_active: boolean;

    /** Professeur principal désigné */
    professeur_principal?: {
        id: number;
        nom: string;
        prenom?: string | null;
        matricule: string;
        telephone?: string;
    } | null;

    /** Niveau et établissement d'appartenance */
    niveau?: {
        id: number;
        code: string;
        nom: string;
        cycle?: {
            id: number;
            code?: string;
            nom?: string;
            section?: {
                id: number;
                code?: string;
                nom?: string;
                etablissement?: {
                    id: number;
                    nom: string;
                    code: string;
                };
            };
        };
    };

    /** Liste des attributions de cours configurées pour cette classe */
    attributions: Attribution[];
}

/**
 * Enseignant avec synthèse de charge horaire (Contractuel vs Réel vs Écart).
 */
export interface EnseignantWithWorkload {
    id: number;
    matricule: string;
    nom: string;
    prenom?: string | null;
    nom_complet?: string;
    telephone: string;
    specialite?: string | null;
    volume_horaire_hebdo: number;
    heures_attribuees: number;
    ecart: number;
    statut_charge: 'sous_charge' | 'equilibre' | 'surcharge';
    taux_vacation?: number | null;
    departement?: {
        id: number;
        nom: string;
        code?: string | null;
    } | null;
    etablissement?: {
        id: number;
        nom: string;
        code: string;
    };
    attributions: Attribution[];
}

/**
 * Ligne de la matrice des coefficients par niveau & série.
 */
export interface MatiereNiveauRef {
    id: number;
    niveau_id: number;
    matiere_id: number;
    groupe_matiere_id: number;
    serie?: string | null;
    coefficient: number;
    matiere?: {
        id: number;
        code: string;
        nom: string;
        libelle_bulletin?: string | null;
    };
    groupe_matiere?: {
        id: number;
        code: string;
        nom: string;
        couleur?: string | null;
        ordre: number;
    };
}

/**
 * Statistiques globales de couverture et de mobilisation enseignante.
 */
export interface AttributionStats {
    totalClasses: number;
    totalAttributions: number;
    enseignantsMobilises: number;
    totalEnseignants: number;
    enseignantsSurcharge: number;
    enseignantsSousCharge: number;
    heuresTotalesReelles: number;
    tauxCouverture: number;
}

/**
 * Propriétés transmises par Inertia à la page index des attributions.
 */
export interface AttributionsPageProps {
    anneesScolaires: {
        id: number;
        code: string;
        nom: string;
        is_current: boolean;
    }[];
    selectedAnneeId: number;
    etablissements: {
        id: number;
        nom: string;
        code: string;
    }[];
    classes: ClasseWithAttributions[];
    enseignants: EnseignantWithWorkload[];
    matieres: {
        id: number;
        code: string;
        nom: string;
    }[];
    matiereNiveaux: MatiereNiveauRef[];
    stats: AttributionStats;
    can: {
        manage: boolean;
        delete: boolean;
    };
    filters: {
        annee_scolaire_id: number;
        etablissement_id?: string;
        classe_id?: string;
    };
}

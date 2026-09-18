/**
 * Types TypeScript pour le Module 5 : Gestion des Élèves & Inscriptions (SYGES).
 * Conforme au Cahier des Charges Gesco V1.1 et SAGEES Schooling.
 */

export interface EtablissementRef {
    id: number;
    code: string;
    nom: string;
    nom_en?: string;
    sections?: SectionRef[];
}

export interface SectionRef {
    id: number;
    etablissement_id: number;
    code: string;
    nom: string;
    cycles?: CycleRef[];
}

export interface CycleRef {
    id: number;
    section_id: number;
    code: string;
    nom: string;
    niveaux?: NiveauRef[];
}

export interface NiveauRef {
    id: number;
    cycle_id: number;
    code: string;
    nom: string;
    classes?: ClasseRef[];
}

export interface ClasseRef {
    id: number;
    etablissement_id?: number;
    niveau_id: number;
    code: string;
    nom: string;
    serie?: string;
    capacite_max: number;
    is_active: boolean;
    niveau?: {
        id: number;
        nom: string;
        code: string;
        cycle?: {
            id: number;
            nom: string;
            section?: {
                id: number;
                nom: string;
                etablissement?: {
                    id: number;
                    nom: string;
                };
            };
        };
    };
}

export interface AnneeScolaireRef {
    id: number;
    code: string;
    nom: string;
    statut: string;
    is_current: boolean;
}

export interface Eleve {
    id: number;
    matricule: string;
    matricule_national?: string | null;
    nom: string;
    prenom?: string | null;
    nom_complet: string;
    sexe: 'M' | 'F';
    date_naissance?: string | null;
    lieu_naissance?: string | null;
    nationalite: string;
    photo_url?: string | null;
    nom_pere?: string | null;
    nom_mere?: string | null;
    nom_tuteur?: string | null;
    telephone_parent?: string | null;
    email_parent?: string | null;
    profession_parent?: string | null;
    adresse?: string | null;
    ville?: string | null;
    groupe_sanguin?: string | null;
    contact_urgence_nom?: string | null;
    contact_urgence_telephone?: string | null;
    allergies_conditions?: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Inscription {
    id: number;
    eleve_id: number;
    classe_id: number;
    etablissement_id?: number | null;
    annee_scolaire_id: number;
    type_inscription: 'nouveau' | 'reinscription';
    is_redoublant: boolean;
    statut: 'en_attente' | 'validee' | 'suspendue' | 'exclu' | 'transfere';
    date_inscription: string;
    motif_sortie?: string | null;
    etablissement_provenance?: string | null;
    observations?: string | null;
    created_at: string;
    updated_at: string;

    // Relations imbriquées
    eleve: Eleve;
    classe: ClasseRef;
    etablissement?: EtablissementRef | null;
}

export interface EleveStats {
    totalInscrits: number;
    totalFilles: number;
    totalGarcons: number;
    totalRedoublants: number;
    totalEnAttente: number;
    nonInscritsCount: number;
}

/**
 * Types TypeScript pour le Centre d'Archivage Administratif et Masques de Numérotation (Aurore §3.15, §3.16, §3.27.8, §3.28.3).
 */

export interface ArchiveAdministrative {
    id: number;
    etablissement_id: number;
    annee_scolaire_id?: number | null;
    type_entite: 'eleve' | 'personnel';
    eleve_id?: number | null;
    personnel_id?: number | null;
    matricule: string;
    nom_complet: string;
    derniere_classe_ou_fonction?: string | null;
    motif:
        | 'diplome'
        | 'transfert_radiation'
        | 'exclusion_definitive'
        | 'abandon'
        | 'demission'
        | 'retraite'
        | 'fin_contrat'
        | 'licenciement'
        | 'mutation'
        | 'deces'
        | 'autre';
    motif_libelle?: string;
    date_archivage: string;
    destination?: string | null;
    observations?: string | null;
    statut: 'archive' | 'restaure';
    archive_par_id?: number | null;
    date_restauration?: string | null;
    restaure_par_id?: number | null;
    created_at?: string;
    updated_at?: string;

    eleve?: {
        id: number;
        matricule: string;
        nom: string;
        prenom?: string;
    };
    personnel?: {
        id: number;
        matricule: string;
        nom: string;
        prenom?: string;
    };
    annee_scolaire?: {
        id: number;
        nom: string;
        code: string;
    };
    archive_par?: {
        id: number;
        name: string;
    };
    restaure_par?: {
        id: number;
        name: string;
    };
}

export interface ConfigurationNumerotation {
    id: number;
    etablissement_id: number;
    type_document:
        | 'matricule_eleve'
        | 'matricule_personnel'
        | 'recu_scolarite'
        | 'recu_divers'
        | 'certificat_scolarite'
        | 'certificat_radiation';
    prefixe: string;
    format_masque: string;
    compteur_actuel: number;
    annee_reference?: string | null;
    reinitialiser_annuellement: boolean;
}

export interface ArchiveStats {
    total_archives: number;
    total_eleves: number;
    total_personnels: number;
}

export interface ArchiveFilters {
    type_entite?: string | null;
    motif?: string | null;
    statut?: string | null;
    search?: string | null;
}

export interface ArchivagePageProps {
    archives: {
        data: ArchiveAdministrative[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: ArchiveStats;
    filters: ArchiveFilters;
    elevesActifs: Array<{ id: number; matricule: string; nom: string; prenom?: string }>;
    personnelsActifs: Array<{
        id: number;
        matricule: string;
        nom: string;
        prenom?: string;
        fonction_principale: string;
    }>;
    configurationsNumerotation: ConfigurationNumerotation[];
    annees: Array<{ id: number; nom: string; code: string; statut: string; is_current: boolean }>;
}

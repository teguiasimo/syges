import { usePage } from '@inertiajs/react';
import type { User } from '@/types/auth';

/**
 * Catalogue exhaustif de l'ensemble des permissions du système SYGES.
 * Offre l'autocomplétion TypeScript et la détection d'erreurs statiques dans les composants React.
 */
export type PermissionName =
    // Module Utilisateurs & Profils
    | 'users.view'
    | 'users.create'
    | 'users.edit'
    | 'users.block'
    // Module Rôles & Permissions
    | 'roles.view'
    | 'roles.create'
    | 'roles.edit'
    | 'roles.delete'
    // Module Institution & Structure Pédagogique
    | 'institutions.view'
    | 'institutions.edit'
    | 'etablissements.manage'
    | 'structures.manage'
    | 'classes.manage'
    // Module Périodes & Paramètres d'Évaluation (Gesco 3)
    | 'periodes.view'
    | 'periodes.manage'
    | 'periodes.lock'
    // Module Matières & Coefficients (Gesco 4)
    | 'matieres.view'
    | 'matieres.manage'
    // Module Élèves & Inscriptions (Gesco 5)
    | 'eleves.view'
    | 'eleves.create'
    | 'eleves.edit'
    | 'eleves.delete'
    | 'inscriptions.manage'
    | 'inscriptions.transfer'
    // Module Personnel & Ressources Humaines (Gesco 8)
    | 'personnels.view'
    | 'personnels.create'
    | 'personnels.edit'
    | 'personnels.delete'
    | 'departements.manage'
    // Module Attribution des Classes & Matières (Gesco 9)
    | 'attributions.view'
    | 'attributions.manage'
    | 'attributions.delete'
    // Module Saisie des Notes & Évaluations (Gesco 10)
    | 'notes.view'
    | 'notes.enter'
    | 'notes.manage'
    | 'notes.lock'
    // Module Bulletins Scolaires (Gesco 10)
    | 'bulletins.view'
    | 'bulletins.generate'
    | 'bulletins.lock'
    // Module Gestion de la Caisse & Paiements (Gesco 11)
    | 'finances.view'
    | 'finances.manage'
    | 'finances.cloture'
    | 'finances.annuler'
    // Module Absences, Retards & Discipline (Gesco 12)
    | 'discipline.view'
    | 'discipline.manage'
    | 'discipline.sanction'
    // Module Emplois du Temps & Salles (Gesco 13)
    | 'emplois_du_temps.view'
    | 'emplois_du_temps.manage'
    // Module Communication & Notifications SMS (Gesco 14)
    | 'communication.view'
    | 'communication.send'
    | 'communication.manage'
    // Module Tableau de Bord, Statistiques & Conseil de Classe (Gesco 15)
    | 'statistiques.view'
    | 'deliberations.view'
    | 'deliberations.manage'
    | 'deliberations.valider';

/**
 * Rôles administratifs et pédagogiques de base configurés dans la plateforme.
 */
export type RoleName =
    | 'super-admin'
    | 'admin'
    | 'directeur-etudes'
    | 'intendant'
    | 'enseignant'
    | 'secretaire';

/**
 * Interface de retour du hook centralisé usePermission.
 */
export interface UsePermissionReturn {
    /** L'utilisateur actuellement authentifié ou null s'il n'est pas connecté. */
    user: User | null;
    /** Indique si l'utilisateur est un Super Administrateur disposant de tous les droits. */
    isSuperAdmin: boolean;
    /** Liste brute de tous les noms de rôles assignés à l'utilisateur. */
    roles: string[];
    /** Liste brute de tous les noms de permissions assignées à l'utilisateur. */
    permissions: string[];
    /**
     * Vérifie si l'utilisateur possède une permission spécifique.
     * Renvoie toujours true si l'utilisateur est Super Administrateur.
     *
     * @param {PermissionName | string} permission - Nom de la permission à vérifier.
     * @returns {boolean}
     */
    can: (permission: PermissionName | string) => boolean;
    /**
     * Vérifie si l'utilisateur possède au moins une permission parmi la liste fournie.
     *
     * @param {(PermissionName | string)[]} permissionList - Liste des permissions à tester (OU logique).
     * @returns {boolean}
     */
    canAny: (permissionList: (PermissionName | string)[]) => boolean;
    /**
     * Vérifie si l'utilisateur possède la totalité des permissions spécifiées.
     *
     * @param {(PermissionName | string)[]} permissionList - Liste des permissions requises (ET logique).
     * @returns {boolean}
     */
    canAll: (permissionList: (PermissionName | string)[]) => boolean;
    /**
     * Vérifie si l'utilisateur possède un rôle spécifique.
     *
     * @param {RoleName | string} role - Nom du rôle à vérifier.
     * @returns {boolean}
     */
    hasRole: (role: RoleName | string) => boolean;
    /**
     * Vérifie si l'utilisateur possède au moins un rôle parmi ceux listés.
     *
     * @param {(RoleName | string)[]} roleList - Liste des rôles à tester (OU logique).
     * @returns {boolean}
     */
    hasAnyRole: (roleList: (RoleName | string)[]) => boolean;
}

/**
 * Hook centralisé React de gestion des rôles et permissions utilisateur (RBAC).
 *
 * Se base sur les données partagées globalement par le middleware Inertia (`auth.user`).
 * Fournit une API propre, déclarative, performante et typée pour conditionner les éléments d'interface.
 *
 * @example
 * ```tsx
 * const { can, hasRole, isSuperAdmin } = usePermission();
 *
 * if (can('etablissements.manage')) {
 *     return <Button>Ajouter un établissement</Button>;
 * }
 * ```
 */
export function usePermission(): UsePermissionReturn {
    const page = usePage<{
        auth?: {
            user?: User | null;
        };
    }>();

    const user = page.props.auth?.user ?? null;

    // Détermination du statut Super-Admin (par drapeau explicite, rôle ou email racine)
    const isSuperAdmin = Boolean(
        user?.is_super_admin ||
        user?.email === 'admin@gmail.com' ||
        user?.roles?.includes('super-admin')
    );

    const roles = user?.roles ?? [];
    const permissions = user?.permissions ?? [];

    /**
     * Vérifie si l'utilisateur a la permission requise.
     */
    const can = (permission: PermissionName | string): boolean => {
        if (!user) return false;
        if (isSuperAdmin) return true;
        return permissions.includes(permission);
    };

    /**
     * Vérifie si l'utilisateur a au moins une des permissions fournies.
     */
    const canAny = (permissionList: (PermissionName | string)[]): boolean => {
        if (!user) return false;
        if (isSuperAdmin) return true;
        return permissionList.some((perm) => permissions.includes(perm));
    };

    /**
     * Vérifie si l'utilisateur a toutes les permissions fournies.
     */
    const canAll = (permissionList: (PermissionName | string)[]): boolean => {
        if (!user) return false;
        if (isSuperAdmin) return true;
        return permissionList.every((perm) => permissions.includes(perm));
    };

    /**
     * Vérifie si l'utilisateur possède un rôle donné.
     */
    const hasRole = (role: RoleName | string): boolean => {
        if (!user) return false;
        return roles.includes(role);
    };

    /**
     * Vérifie si l'utilisateur possède au moins un rôle parmi la sélection.
     */
    const hasAnyRole = (roleList: (RoleName | string)[]): boolean => {
        if (!user) return false;
        return roleList.some((r) => roles.includes(r));
    };

    return {
        user,
        isSuperAdmin,
        roles,
        permissions,
        can,
        canAny,
        canAll,
        hasRole,
        hasAnyRole,
    };
}

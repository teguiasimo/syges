import { Link } from '@inertiajs/react';
import {
    Award,
    BarChart3,
    BookOpen,
    Briefcase,
    CalendarDays,
    CalendarRange,
    Edit3,
    FileText,
    GraduationCap,
    Layers,
    MessageSquare,
    School,
    ShieldAlert,
    ShieldCheck,
    Users,
    Wallet,
} from 'lucide-react';
import * as React from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';

/**
 * Définition d'un élément de navigation pour un module administratif.
 *
 * @property {string} title - Libellé affiché dans le menu.
 * @property {string} href - URL de redirection vers le module.
 * @property {React.ElementType} icon - Composant d'icône Lucide.
 * @property {string} [color] - Classe Tailwind pour colorer intelligemment l'icône.
 * @property {string} [permission] - Permission requise pour accéder au module.
 * @property {string} [tooltip] - Texte d'infobulle affiché lorsque la barre est repliée.
 */
export interface NavModuleItem {
    title: string;
    href: string;
    icon: React.ElementType;
    color?: string;
    permission?: string;
    tooltip?: string;
}

/**
 * Définition d'un groupe logique regroupant plusieurs modules associés.
 *
 * @property {string} label - En-tête du groupe affiché en majuscules discrètes.
 * @property {NavModuleItem[]} items - Liste des modules appartenant à ce groupe.
 */
export interface NavModuleGroup {
    label: string;
    items: NavModuleItem[];
}

/**
 * Configuration des modules d'administration classés par ordre structurel et chronologique (Gesco V1.1).
 * Chaque module bénéficie d'une icône colorée de manière sémantique et intelligente.
 *
 * Ordre structurel & chronologique de mise en place :
 * 1. FONDATIONS & STRUCTURE (Priorité 1 : Structure & Campus, Années & Périodes, Matières & Coeffs)
 * 2. PERSONNEL & ENSEIGNEMENT (Priorité 2 : Personnel & RH, Attribution des Cours, Emplois du Temps)
 * 3. VIE SCOLAIRE & ÉLÈVES (Priorité 3 : Inscriptions, Caisse & Scolarités, Discipline & Absences)
 * 4. PÉDAGOGIE & ÉVALUATIONS (Priorité 4 : Saisie des Notes, Bulletins Scolaires, Délibérations & Conseil)
 * 5. PILOTAGE & DIFFUSION (Priorité 5 : Statistiques ministérielles & Rapports, Diffusion SMS)
 * 6. SÉCURITÉ & SYSTÈME (Priorité 6 : Gestion des Utilisateurs, Rôles & Permissions RBAC)
 * 7. ACCÈS DISTANT (Priorité 7 : Portail Parents & Élèves placé tout au fond)
 */
const MODULE_GROUPS: NavModuleGroup[] = [
    {
        label: 'Fondations & Structure',
        items: [
            {
                title: 'Structure & Campus',
                href: '/administration/institution',
                icon: School,
                color: 'text-emerald-500 dark:text-emerald-400',
                permission: 'institutions.view',
                tooltip: 'Établissements, Cycles & Classes (Gesco 1)',
            },
            {
                title: 'Années & Périodes',
                href: '/administration/periodes',
                icon: CalendarRange,
                color: 'text-teal-500 dark:text-teal-400',
                permission: 'periodes.view',
                tooltip: 'Années & Trimestres (Gesco 3)',
            },
            {
                title: 'Matières & Coeffs',
                href: '/administration/matieres',
                icon: BookOpen,
                color: 'text-purple-500 dark:text-purple-400',
                permission: 'matieres.view',
                tooltip: 'Catalogue & Grille des Coefficients (Gesco 4)',
            },
        ],
    },
    {
        label: 'Personnel & Enseignement',
        items: [
            {
                title: 'Personnel & RH',
                href: '/administration/personnels',
                icon: Briefcase,
                color: 'text-cyan-600 dark:text-cyan-400',
                permission: 'personnels.view',
                tooltip: 'Employés, Enseignants & Contrats (Gesco 8)',
            },
            {
                title: 'Attribution des Cours',
                href: '/administration/attributions',
                icon: Layers,
                color: 'text-indigo-500 dark:text-indigo-400',
                permission: 'attributions.view',
                tooltip: 'Attribution des Cours & Classes (Gesco 9)',
            },
            {
                title: 'Emplois du Temps',
                href: '/administration/emplois-du-temps',
                icon: CalendarDays,
                color: 'text-violet-500 dark:text-violet-400',
                permission: 'emplois_du_temps.view',
                tooltip: 'Emplois du Temps & Salles (Gesco 13)',
            },
        ],
    },
    {
        label: 'Vie Scolaire & Élèves',
        items: [
            {
                title: 'Élèves & Inscriptions',
                href: '/administration/eleves',
                icon: GraduationCap,
                color: 'text-blue-500 dark:text-blue-400',
                permission: 'eleves.view',
                tooltip: 'Élèves & Inscriptions (Gesco 5)',
            },
            {
                title: 'Caisse & Scolarités',
                href: '/administration/finances',
                icon: Wallet,
                color: 'text-emerald-600 dark:text-emerald-400',
                permission: 'finances.view',
                tooltip: 'Encaissements, Reçus & Caisse (Gesco 11)',
            },
            {
                title: 'Discipline & Absences',
                href: '/administration/discipline',
                icon: ShieldAlert,
                color: 'text-red-500 dark:text-red-400',
                permission: 'discipline.view',
                tooltip: 'Assiduité, Absences & Sanctions (Gesco 12)',
            },
        ],
    },
    {
        label: 'Pédagogie & Évaluations',
        items: [
            {
                title: 'Saisie des Notes',
                href: '/administration/notes',
                icon: Edit3,
                color: 'text-rose-500 dark:text-rose-400',
                permission: 'notes.view',
                tooltip: 'Saisie des Notes Séquentielles (Gesco 10)',
            },
            {
                title: 'Bulletins Scolaires',
                href: '/administration/bulletins',
                icon: FileText,
                color: 'text-sky-500 dark:text-sky-400',
                permission: 'bulletins.view',
                tooltip: 'Calculs & Bulletins Officiels (Gesco 10)',
            },
            {
                title: 'Délibérations & Conseil',
                href: '/administration/deliberations',
                icon: Award,
                color: 'text-amber-500 dark:text-amber-400',
                permission: 'deliberations.view',
                tooltip: 'Conseil de Classe & Mentions (Gesco 15.2)',
            },
        ],
    },
    {
        label: 'Pilotage & Diffusion',
        items: [
            {
                title: 'Statistiques & Rapports',
                href: '/administration/statistiques',
                icon: BarChart3,
                color: 'text-blue-600 dark:text-blue-400',
                permission: 'statistiques.view',
                tooltip: 'KPIs, Parité & Bordereaux Ministériels (Gesco 15)',
            },
            {
                title: 'Communication SMS',
                href: '/administration/communication',
                icon: MessageSquare,
                color: 'text-green-500 dark:text-green-400',
                permission: 'communication.view',
                tooltip: 'Diffusion & Notifications SMS (Gesco 14)',
            },
        ],
    },
    {
        label: 'Sécurité & Système',
        items: [
            {
                title: 'Utilisateurs',
                href: '/administration/users',
                icon: Users,
                color: 'text-orange-500 dark:text-orange-400',
                permission: 'users.view',
                tooltip: 'Comptes Utilisateurs & Statuts',
            },
            {
                title: 'Rôles & Permissions',
                href: '/administration/roles',
                icon: ShieldCheck,
                color: 'text-amber-600 dark:text-amber-400',
                permission: 'roles.view',
                tooltip: 'Gestion des Rôles & Habilitations RBAC',
            },
        ],
    },
    {
        label: 'Accès Distant',
        items: [
            {
                title: 'Portail Parents & Élèves',
                href: '/portail',
                icon: Users,
                color: 'text-fuchsia-500 dark:text-fuchsia-400',
                permission: '',
                tooltip: 'Portail Parents & Élèves (Consultation distante future)',
            },
        ],
    },
];

/**
 * Propriétés attendues par le composant de navigation modulaire.
 */
interface NavModulesProps {
    /** Liste optionnelle des permissions accordées à l'utilisateur actuellement authentifié */
    permissions?: string[];
    /** Indique optionnellement si l'utilisateur possède le statut Super Administrateur */
    isSuperAdmin?: boolean;
}

/**
 * Composant de navigation latérale par modules pour la barre latérale SYGES.
 * Organise l'ensemble des modules fonctionnels sous forme de pôles thématiques
 * directement accessibles en dessous du menu principal d'administration.
 *
 * Règles appliquées :
 * - Style standard de la sidebar préservé intégralement.
 * - Coloration intelligente et distincte pour chaque icône de module.
 * - Le Portail Parents & Élèves apparaît à la dernière ligne ("loin au fond").
 * - Troncature propre sans défilement horizontal.
 *
 * @param {NavModulesProps} [props]
 * @returns {JSX.Element | null}
 */
export function NavModules({ permissions: propPermissions, isSuperAdmin: propIsSuperAdmin }: NavModulesProps = {}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { can, isSuperAdmin: hookIsSuperAdmin } = usePermission();

    const isSuperAdmin = propIsSuperAdmin ?? hookIsSuperAdmin;

    // Filtre les groupes pour n'afficher que ceux dont au moins un module est autorisé
    const authorizedGroups = React.useMemo(() => {
        return MODULE_GROUPS.map((group) => ({
            ...group,
            items: group.items.filter(
                (item) =>
                    !item.permission ||
                    isSuperAdmin ||
                    (propPermissions ? propPermissions.includes(item.permission) : can(item.permission))
            ),
        })).filter((group) => group.items.length > 0);
    }, [propPermissions, isSuperAdmin, can]);

    if (authorizedGroups.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1">
            {authorizedGroups.map((group) => (
                <SidebarGroup key={group.label} className="px-2 py-0.5">
                    <SidebarGroupLabel className="text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">
                        {group.label}
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => {
                            const IconComponent = item.icon;
                            const isActive = isCurrentOrParentUrl(item.href);

                            return (
                                <SidebarMenuItem key={item.href}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActive}
                                        tooltip={{ children: item.tooltip ?? item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            <IconComponent className={cn('size-4 shrink-0', item.color)} />
                                            <span className="truncate">{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </div>
    );
}

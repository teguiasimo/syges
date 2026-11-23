import { Link } from '@inertiajs/react';
import {
    Archive,
    Award,
    Banknote,
    BarChart3,
    BookOpen,
    Briefcase,
    CalendarDays,
    CalendarRange,
    CheckSquare,
    CreditCard,
    Edit3,
    FileCheck,
    FileSpreadsheet,
    FileText,
    Gavel,
    GraduationCap,
    Image as ImageIcon,
    Layers,
    MessageSquare,
    RefreshCw,
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
import { cn, toUrl } from '@/lib/utils';

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
    superAdminOnly?: boolean;
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
 * 3. VIE SCOLAIRE & ÉLÈVES (Priorité 3 : Inscriptions, Finances & Tarifs, Guichet & Scolarités, Discipline & Absences)
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
                color: 'text-emerald-500',
                permission: 'institutions.view',
                tooltip: 'Établissements, Cycles & Classes',
            },
            {
                title: 'Apparence & Identité',
                href: '/administration/institution/login-appearance',
                icon: ImageIcon,
                color: 'text-sky-500',
                permission: 'institutions.edit',
                tooltip: 'Carrousel login, logo du menu latéral, favicon & branding',
            },
            {
                title: 'Années & Périodes',
                href: '/administration/periodes',
                icon: CalendarRange,
                color: 'text-teal-500',
                permission: 'periodes.view',
                tooltip: 'Années & Trimestres',
            },
            {
                title: 'Matières & Coeffs',
                href: '/administration/matieres',
                icon: BookOpen,
                color: 'text-blue-600',
                permission: 'matieres.view',
                tooltip: 'Catalogue & Grille des Coefficients',
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
                color: 'text-cyan-600',
                permission: 'personnels.view',
                tooltip: 'Employés, Enseignants & Contrats',
            },
            {
                title: 'Attribution des Cours',
                href: '/administration/attributions',
                icon: Layers,
                color: 'text-blue-500',
                permission: 'attributions.view',
                tooltip: 'Attribution des Cours & Classes',
            },
            {
                title: 'Emplois du Temps',
                href: '/administration/emplois-du-temps',
                icon: CalendarDays,
                color: 'text-sky-600',
                permission: 'emplois_du_temps.view',
                tooltip: 'Emplois du Temps & Salles',
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
                color: 'text-blue-500',
                permission: 'eleves.view',
                tooltip: 'Élèves & Inscriptions',
            },

            {
                title: 'Finances & Tarifs',
                href: '/administration/finances',
                icon: Wallet,
                color: 'text-teal-600',
                permission: 'finances.view',
                tooltip: 'Types de frais, Grille tarifaire, Remises & Journal',
            },
            {
                title: 'Guichet & Scolarités',
                href: '/administration/finances/scolarites',
                icon: CreditCard,
                color: 'text-emerald-600',
                permission: 'finances.view',
                tooltip: 'Encaissement par classe & Dossiers Financiers Élèves',
            },
            {
                title: 'Dépenses & Charges',
                href: '/administration/finances/depenses',
                icon: Banknote,
                color: 'text-emerald-500',
                permission: 'finances.view',
                tooltip: 'Charges, Décharges & Bilan Entrées/Sorties',
            },
            {
                title: 'Discipline & Absences',
                href: '/administration/discipline',
                icon: ShieldAlert,
                color: 'text-red-500',
                permission: 'discipline.view',
                tooltip: 'Assiduité, Absences & Sanctions',
            },
            {
                title: 'Conseil de Discipline',
                href: '/administration/discipline/conseil',
                icon: Gavel,
                color: 'text-rose-600',
                permission: 'discipline.view',
                tooltip: 'Sessions CD, Convocations & Heures de Consigne',
            },
            {
                title: 'Centre d\'Archivage',
                href: '/administration/archivage',
                icon: Archive,
                color: 'text-amber-600',
                permission: 'eleves.view',
                tooltip: 'Archives administratives, masques & reconduction',
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
                color: 'text-rose-500',
                permission: 'notes.view',
                tooltip: 'Saisie des Notes Séquentielles',
            },
            {
                title: 'Relevés de Notes',
                href: '/administration/notes/releves',
                icon: FileSpreadsheet,
                color: 'text-amber-500',
                permission: 'releves_notes.view',
                tooltip: 'Fiches de report & relevés de notes imprimables',
            },
            {
                title: 'Compétences APC',
                href: '/administration/pedagogie/competences',
                icon: CheckSquare,
                color: 'text-blue-600',
                permission: 'competences_apc.view',
                tooltip: "Compétences Périodiques & Cotes d'Évaluation",
            },
            {
                title: 'Dispenses & Dérogations',
                href: '/administration/pedagogie/dispenses',
                icon: FileCheck,
                color: 'text-teal-600',
                permission: 'dispenses.view',
                tooltip: 'Dispenses EPS, Médicales & PV Officiel',
            },
            {
                title: 'Bulletins Scolaires',
                href: '/administration/bulletins',
                icon: FileText,
                color: 'text-sky-500',
                permission: 'bulletins.view',
                tooltip: 'Calculs & Bulletins Officiels',
            },
            {
                title: 'Cahier de Texte & Progression',
                href: '/administration/progression-pedagogique',
                icon: BookOpen,
                color: 'text-teal-600',
                permission: 'progression.view',
                tooltip: 'Cahier de texte, suivi horaire & visas',
            },
            {
                title: 'Délibérations & Conseil',
                href: '/administration/deliberations',
                icon: Award,
                color: 'text-amber-500',
                permission: 'deliberations.view',
                tooltip: 'Conseil de Classe & Mentions',
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
                color: 'text-blue-600',
                permission: 'statistiques.view',
                tooltip: 'KPIs, Parité & Bordereaux Ministériels',
            },
            {
                title: 'Communication SMS',
                href: '/administration/communication',
                icon: MessageSquare,
                color: 'text-green-500',
                permission: 'communication.view',
                tooltip: 'Diffusion & Notifications SMS',
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
                color: 'text-orange-500',
                permission: 'users.view',
                tooltip: 'Comptes Utilisateurs & Statuts',
            },
            {
                title: 'Rôles & Permissions',
                href: '/administration/roles',
                icon: ShieldCheck,
                color: 'text-amber-600',
                permission: 'roles.view',
                tooltip: 'Gestion des Rôles & Habilitations RBAC',
            },
            {
                title: 'Mise à jour & Versions',
                href: '/administration/system-update',
                icon: RefreshCw,
                color: 'text-sky-500',
                permission: 'system.update',
                tooltip: 'Gestionnaire de Versions SemVer & Mise à jour GitHub',
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
                color: 'text-fuchsia-500',
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
    const { currentUrl } = useCurrentUrl();
    const { can, isSuperAdmin: hookIsSuperAdmin } = usePermission();

    const isSuperAdmin = propIsSuperAdmin ?? hookIsSuperAdmin;

    // Filtre les groupes pour n'afficher que ceux dont au moins un module est autorisé
    const authorizedGroups = React.useMemo(() => {
        return MODULE_GROUPS.map((group) => ({
            ...group,
            items: group.items.filter((item) => {
                if (item.superAdminOnly) {
                    return Boolean(isSuperAdmin);
                }
                return (
                    !item.permission ||
                    isSuperAdmin ||
                    (propPermissions ? propPermissions.includes(item.permission) : can(item.permission))
                );
            }),
        })).filter((group) => group.items.length > 0);
    }, [propPermissions, isSuperAdmin, can]);

    /**
     * Résout l'item de menu actif de manière univoque (Exact Match puis Longest Prefix Match).
     * Empêche que des URLs sœurs partageant un préfixe commun (ex: /notes et /notes/releves,
     * ou /finances et /finances/depenses) ne soient actives simultanément dans la barre latérale.
     */
    const activeHref = React.useMemo(() => {
        // Normaliser l'URL courante en retirant les barres obliques finales et les paramètres de requête
        const cleanCurrentUrl = (currentUrl || '').split('?')[0].replace(/\/+$/, '') || '/';
        let bestMatch = '';
        let longestLength = 0;

        // Passe 1 : Priorité absolue aux correspondances exactes
        for (const group of authorizedGroups) {
            for (const item of group.items) {
                const itemPath = toUrl(item.href).split('?')[0].replace(/\/+$/, '') || '/';
                if (cleanCurrentUrl === itemPath) {
                    return item.href;
                }
            }
        }

        // Passe 2 : Si aucune correspondance exacte, retenir le préfixe parent le plus long et spécifique
        for (const group of authorizedGroups) {
            for (const item of group.items) {
                const itemPath = toUrl(item.href).split('?')[0].replace(/\/+$/, '') || '/';
                if (cleanCurrentUrl.startsWith(itemPath + '/') && itemPath.length > longestLength) {
                    longestLength = itemPath.length;
                    bestMatch = item.href;
                }
            }
        }

        return bestMatch;
    }, [authorizedGroups, currentUrl]);

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
                            const isActive = item.href === activeHref;

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

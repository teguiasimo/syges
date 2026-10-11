import { Link } from '@inertiajs/react';
import { LayoutGrid, ShieldCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavModules } from '@/components/nav-modules';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/contexts/language-context';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * Composant principal de la barre latérale (Sidebar) de l'application SYGES.
 * Filtre dynamiquement les éléments de navigation : l'accès à l'Administration
 * et à ses modules n'apparaît que si l'utilisateur possède au moins une permission administrative ou est Super Admin.
 *
 * @returns {JSX.Element} La barre latérale de navigation.
 */
export function AppSidebar() {
    const { t } = useTranslation();
    const { permissions, isSuperAdmin } = usePermission();

    // Détermine si l'utilisateur a le droit d'accéder au volet administration
    const hasAdminAccess =
        isSuperAdmin ||
        permissions.some(
            (p) =>
                p.startsWith('users.') ||
                p.startsWith('roles.') ||
                p.startsWith('personnels.') ||
                p.startsWith('departements.') ||
                p.startsWith('attributions.') ||
                p.startsWith('institutions.') ||
                p.startsWith('etablissements.') ||
                p.startsWith('structures.') ||
                p.startsWith('classes.') ||
                p.startsWith('periodes.') ||
                p.startsWith('matieres.') ||
                p.startsWith('eleves.') ||
                p.startsWith('inscriptions.') ||
                p.startsWith('notes.') ||
                p.startsWith('releves_notes.') ||
                p.startsWith('progression.') ||
                p.startsWith('bulletins.') ||
                p.startsWith('settings.') ||
                p.startsWith('grades.') ||
                p.startsWith('attendance.') ||
                p.startsWith('backups.') ||
                p.startsWith('finances.') ||
                p.startsWith('discipline.') ||
                p.startsWith('emplois_du_temps.') ||
                p.startsWith('communication.') ||
                p.startsWith('statistiques.') ||
                p.startsWith('deliberations.')
        );

    // Liste des éléments du menu principal filtrée et traduite avec icônes colorées
    const navItems: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
            color: 'text-sky-500 dark:text-sky-400',
        },
        ...(hasAdminAccess
            ? [
                  {
                      title: t('nav.administration'),
                      href: '/administration',
                      icon: ShieldCheck,
                      color: 'text-indigo-500 dark:text-indigo-400',
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            {/* En-tête de la barre latérale avec Logo SYGES */}
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            {/* Corps de navigation principale et modules d'administration */}
            <SidebarContent>
                <NavMain items={navItems} />

                {/* Modules d'administration regroupés par pôles thématiques en dessous d'Administration */}
                {hasAdminAccess && (
                    <>
                        <SidebarSeparator className="my-1.5 opacity-50" />
                        <NavModules permissions={permissions} isSuperAdmin={isSuperAdmin} />
                    </>
                )}
            </SidebarContent>

            {/* Pied de page de la barre latérale : profil utilisateur uniquement */}
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

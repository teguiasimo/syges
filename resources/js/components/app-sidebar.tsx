import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, ShieldCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/contexts/language-context';
import { dashboard } from '@/routes';
import type { NavItem, User } from '@/types';

/**
 * Composant principal de la barre latérale (Sidebar) de l'application SYGES.
 * Filtre dynamiquement les éléments de navigation : l'accès à l'Administration
 * n'apparaît que si l'utilisateur possède au moins une permission administrative ou est Super Admin.
 *
 * @returns {JSX.Element} La barre latérale de navigation.
 */
export function AppSidebar() {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth?: { user?: User } }>().props;
    const permissions = auth?.user?.permissions ?? [];
    const isSuperAdmin = auth?.user?.is_super_admin ?? false;

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
                p.startsWith('bulletins.') ||
                p.startsWith('settings.') ||
                p.startsWith('grades.') ||
                p.startsWith('attendance.') ||
                p.startsWith('backups.')
        );

    // Liste des éléments du menu principal filtrée et traduite
    const navItems: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(hasAdminAccess
            ? [
                  {
                      title: t('nav.administration'),
                      href: '/administration',
                      icon: ShieldCheck,
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

            {/* Corps de navigation principale */}
            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            {/* Pied de page de la barre latérale : profil utilisateur uniquement */}
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

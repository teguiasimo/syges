import { Link } from '@inertiajs/react';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * Éléments du menu de navigation principal de la barre latérale.
 * Comprend le Tableau de bord et l'accès à l'Administration.
 */
const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Administration',
        href: '/administration',
        icon: ShieldCheck,
    },
];

/**
 * Composant principal de la barre latérale (Sidebar) de l'application SYGES.
 * Organise la navigation par groupe et intègre le profil utilisateur dans le pied de page.
 * Les liens externes (Repository, Documentation) ont été retirés pour un rendu épuré.
 *
 * @returns {JSX.Element} La barre latérale de navigation.
 */
export function AppSidebar() {
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            {/* Pied de page de la barre latérale : profil utilisateur uniquement */}
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

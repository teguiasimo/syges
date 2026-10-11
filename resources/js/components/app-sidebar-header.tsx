import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { LanguageSwitcher } from '@/components/language-switcher';
import { EtablissementSwitcher } from '@/components/etablissement-switcher';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * En-tête de la mise en page avec barre latérale (AppSidebarHeader).
 * Affiche le déclencheur de barre latérale et le fil d'Ariane à gauche,
 * et à droite le sélecteur d'établissement actif (Multi-Tenant SaaS),
 * le sélecteur de langue (icône de traduction) et le bloc profil utilisateur.
 *
 * @param {{ breadcrumbs?: BreadcrumbItemType[] }} props - Les éléments du fil d'Ariane.
 * @returns {JSX.Element} L'en-tête supérieur de l'application.
 */
export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth } = usePage().props;

    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center justify-between border-b px-3 sm:px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            {/* Section gauche : Déclencheur sidebar + Fil d'Ariane adaptatif */}
            <div className="flex items-center gap-2 min-w-0">
                <SidebarTrigger className="-ml-1 shrink-0" />
                <div className="hidden md:block truncate">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>

            {/* Section droite : Sélecteur de campus, Sélecteur de langue et bloc utilisateur */}
            {auth.user && (
                <div className="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                    {/* Sélecteur Maître de Campus (Multi-Établissements SaaS) */}
                    <EtablissementSwitcher />

                    {/* Icône de traduction (i18n) immédiatement à côté du nom de l'utilisateur */}
                    <LanguageSwitcher />

                    {/* Bloc profil utilisateur avec menu déroulant */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="flex items-center gap-1.5 sm:gap-2 rounded-lg p-1 sm:p-1.5 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
                            >
                                <UserInfo user={auth.user} showEmail={true} hideTextOnMobile={true} />
                                <ChevronsUpDown className="hidden sm:block size-4 text-muted-foreground shrink-0" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-56 rounded-lg" align="end" side="bottom">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            )}
        </header>
    );
}

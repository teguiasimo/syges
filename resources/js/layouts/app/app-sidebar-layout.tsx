import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import {
    NavigationProgressIndicator,
    useInertiaNavigationLoading,
} from '@/components/navigation-progress-indicator';
import type { AppLayoutProps } from '@/types';

/**
 * Gabarit principal connecté avec barre latérale persistante et retour visuel non intrusif
 * lors des transitions Inertia prolongées (> 200 ms).
 *
 * @param {AppLayoutProps} props Contenu de page et fil d'Ariane.
 * @returns {JSX.Element} Structure complète avec barre latérale et indicateur de navigation.
 */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const isNavigating = useInertiaNavigationLoading(300);

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                aria-busy={isNavigating}
                className="min-w-0 overflow-x-clip"
            >
                <NavigationProgressIndicator />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div
                    className={
                        isNavigating
                            ? 'flex flex-1 flex-col transition-opacity duration-200 opacity-70'
                            : 'flex flex-1 flex-col transition-opacity duration-150 opacity-100'
                    }
                >
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}

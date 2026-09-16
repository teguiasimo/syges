import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { LanguageProvider } from '@/contexts/language-context';
import type { BreadcrumbItem } from '@/types';

/**
 * Layout principal de l'application connecté (dashboard, modules d'administration, etc.).
 * Intègre le LanguageProvider au sein du contexte de page Inertia, garantissant que
 * l'en-tête, la barre latérale et les composants enfants ont un accès réactif aux traductions.
 *
 * @param {{ breadcrumbs?: BreadcrumbItem[]; children: React.ReactNode }} props
 * @returns {JSX.Element}
 */
export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <LanguageProvider>
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
            </AppLayoutTemplate>
        </LanguageProvider>
    );
}

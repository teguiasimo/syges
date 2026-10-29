import React from 'react';
import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';
import { LanguageProvider } from '@/contexts/language-context';

/**
 * Propriétés attendues par le composant englobant d'authentification.
 *
 * @property {string} [title] - Titre principal affiché sur la page d'authentification.
 * @property {string} [description] - Description ou sous-titre explicatif sous le titre principal.
 * @property {React.ReactNode} children - Contenu spécifique de la page (ex: formulaire de connexion).
 */
type AuthLayoutProps = {
    title?: string;
    description?: string;
    children: React.ReactNode;
};

/**
 * Layout principal pour les pages d'authentification.
 * Utilise la variante "split" enveloppée dans le LanguageProvider.
 * Extrait automatiquement les métadonnées de titre et description
 * définies statiquement sur le composant page (ex: Login.layout).
 *
 * @param {AuthLayoutProps} props - Les propriétés passées au layout.
 * @returns {JSX.Element} Le template d'authentification split rendu.
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: AuthLayoutProps) {
    // Extraction intelligente de la configuration de layout depuis le composant de page Inertia
    const layoutConfig =
        React.isValidElement(children) && typeof children.type === 'function'
            ? (children.type as any).layout
            : null;

    const finalTitle = title || layoutConfig?.title || '';
    const finalDescription = description || layoutConfig?.description || '';

    return (
        <LanguageProvider>
            <AuthLayoutTemplate title={finalTitle} description={finalDescription}>
                {children}
            </AuthLayoutTemplate>
        </LanguageProvider>
    );
}

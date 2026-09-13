import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';

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
 * Utilise la variante "split" (disposition en 2 sections : marque/visuel à gauche, formulaire à droite).
 *
 * @param {AuthLayoutProps} props - Les propriétés passées au layout.
 * @returns {JSX.Element} Le template d'authentification split rendu.
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: AuthLayoutProps) {
    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}

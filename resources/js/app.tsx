import { createInertiaApp, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { syncBrowserFavicon } from '@/lib/branding';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { AppBrandingPayload } from '@/types';

// Nom de l'application utilisé pour le titre des pages (fallback sur 'SYGES')
const defaultAppName = import.meta.env.VITE_APP_NAME || 'SYGES';
let currentBrandTitle = defaultAppName;

/**
 * Synchronise le titre applicatif (onglet navigateur) et le favicon depuis `appBranding`.
 *
 * @param {unknown} rawBranding Données `appBranding` partagées dans les props Inertia.
 */
function syncRuntimeBranding(rawBranding: unknown): void {
    const branding = (rawBranding && typeof rawBranding === 'object')
        ? (rawBranding as Partial<AppBrandingPayload>)
        : null;

    currentBrandTitle =
        branding?.use_custom_app_branding && branding.brand_name
            ? branding.brand_name
            : defaultAppName;

    syncBrowserFavicon(branding?.favicon_url);
}

/**
 * Synchronise la balise `<meta name="csrf-token">` avec le jeton partagé par Inertia
 * afin que les appels `fetch` ou formulaires natifs utilisent toujours un jeton valide
 * après une connexion, déconnexion ou rotation de session en SPA.
 *
 * @param {unknown} token - Jeton CSRF extrait des props de page Inertia.
 */
function syncCsrfMetaTag(token: unknown): void {
    if (typeof document === 'undefined' || typeof token !== 'string' || !token) {
        return;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute('content') !== token) {
        meta.setAttribute('content', token);
    }
}

/**
 * Enregistre les garde-fous globaux Inertia pour la cohérence des jetons CSRF
 * et la récupération fluide d'éventuelles erreurs HTTP 419 (Session expirée).
 */
function registerInertiaCsrfGuards(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialise immédiatement le nom applicatif/favicon depuis le payload SSR initial
    try {
        const rawPage = document.getElementById('app')?.dataset?.page;
        if (rawPage) {
            const parsed = JSON.parse(rawPage) as { props?: { appBranding?: unknown } };
            if (parsed?.props?.appBranding) {
                syncRuntimeBranding(parsed.props.appBranding);
            }
        }
    } catch {
        // Ignore tout parsing invalide en environnement hors-DOM ou test
    }

    router.on('navigate', (event) => {
        const pageProps = (event as CustomEvent).detail?.page?.props;
        syncCsrfMetaTag(pageProps?.csrf_token);
        syncRuntimeBranding(pageProps?.appBranding);
    });

    router.on('success', (event) => {
        const pageProps = (event as CustomEvent).detail?.page?.props;
        syncCsrfMetaTag(pageProps?.csrf_token);
        syncRuntimeBranding(pageProps?.appBranding);
    });

    // Filet de sécurité client : intercepte tout statut 419 résiduel avant la modale HTML brute d'Inertia
    router.on('httpException', (event) => {
        const customEvent = event as CustomEvent;
        const status = customEvent.detail?.response?.status;

        if (status === 419) {
            customEvent.preventDefault();
            toast.error('Votre session a expiré. Actualisation de la page...', {
                id: 'csrf-expired-toast',
                position: 'top-right',
            });
            window.setTimeout(() => {
                window.location.reload();
            }, 350);
            return false;
        }
    });
}

registerInertiaCsrfGuards();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${currentBrandTitle}` : currentBrandTitle),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

/**
 * Verrouille définitivement l'application en mode clair et nettoie d'éventuelles
 * traces héritées d'un ancien réglage de thème sombre (classe `.dark`, localStorage, cookie).
 */
function enforceLightTheme(): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';

    try {
        window.localStorage.removeItem('appearance');
    } catch {
        // Ignore si l'accès au stockage local est restreint
    }

    document.cookie = 'appearance=;path=/;max-age=0;SameSite=Lax';
}

enforceLightTheme();

/**
 * Helpers de synchronisation de l'identité visuelle du navigateur (favicon)
 * suite aux mises à jour SPA Inertia de l'apparence personnalisée.
 */

/**
 * Icônes par défaut de la plateforme SYGES à restaurer en cas de réinitialisation sans rechargement.
 */
const DEFAULT_FAVICON_URL = '/favicon.svg?v=syges3';
const DEFAULT_SHORTCUT_URL = '/favicon.ico?v=syges3';
const DEFAULT_APPLE_URL = '/apple-touch-icon.png?v=syges3';

/**
 * Déduit le type MIME approprié d'une icône navigateur selon l'extension du fichier image.
 *
 * @param {string} url URL publique du fichier icône
 * @returns {string | null} Type MIME explicite ou `null` pour laisser le navigateur détecter
 */
function resolveIconMimeType(url: string): string | null {
    const clean = url.split('?')[0].toLowerCase();
    if (clean.endsWith('.svg')) {
        return 'image/svg+xml';
    }
    if (clean.endsWith('.png')) {
        return 'image/png';
    }
    if (clean.endsWith('.webp')) {
        return 'image/webp';
    }
    if (clean.endsWith('.jpg') || clean.endsWith('.jpeg')) {
        return 'image/jpeg';
    }

    return null;
}

/**
 * Crée ou met à jour une balise `<link>` d'icône dans `<head>`.
 *
 * @param {string} rel Valeur de l'attribut `rel` (`icon`, `shortcut icon`, `apple-touch-icon`)
 * @param {string} href URL cible de l'icône
 * @param {string} mode Marqueur d'état (`custom` ou `default`)
 */
function upsertIconLink(rel: string, href: string, mode: 'custom' | 'default'): void {
    const link = document.createElement('link');
    link.rel = rel;
    link.href = href;
    link.setAttribute('data-syges-favicon', mode);

    const mimeType = resolveIconMimeType(href);
    if (mimeType) {
        link.type = mimeType;
    }

    document.head.appendChild(link);
}

/**
 * Met à jour dynamiquement l'icône de l'onglet navigateur (favicon) lorsque l'identité
 * personnalisée est modifiée ou réinitialisée en navigation SPA Inertia.
 *
 * @param {string | null | undefined} faviconUrl URL du logo personnalisé actif, ou `null` pour revenir aux favicons SYGES
 */
export function syncBrowserFavicon(faviconUrl: string | null | undefined): void {
    if (typeof document === 'undefined' || !document.head) {
        return;
    }

    const existingCustomMarkers = Array.from(
        document.head.querySelectorAll('link[rel*="icon"]'),
    );

    const normalized = typeof faviconUrl === 'string' ? faviconUrl.trim() : '';

    // Évite toute écriture DOM inutile si le favicon cible est déjà appliqué
    const currentPrimary = existingCustomMarkers[0]?.getAttribute('href') ?? '';
    const targetPrimary = normalized || DEFAULT_FAVICON_URL;
    if (currentPrimary === targetPrimary && existingCustomMarkers.length > 0) {
        return;
    }

    existingCustomMarkers.forEach((el) => el.remove());

    if (normalized) {
        upsertIconLink('icon', normalized, 'custom');
        upsertIconLink('shortcut icon', normalized, 'custom');
        upsertIconLink('apple-touch-icon', normalized, 'custom');
        return;
    }

    upsertIconLink('icon', DEFAULT_FAVICON_URL, 'default');
    upsertIconLink('shortcut icon', DEFAULT_SHORTCUT_URL, 'default');
    upsertIconLink('apple-touch-icon', DEFAULT_APPLE_URL, 'default');
}

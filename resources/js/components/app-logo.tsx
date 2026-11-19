import { usePage } from '@inertiajs/react';
import type { AppBrandingPayload } from '@/types';

/**
 * Propriétés globales minimales consommées par le composant logo de la barre latérale.
 */
interface SidebarBrandingProps {
    name?: string;
    appBranding?: AppBrandingPayload;
    [key: string]: unknown;
}

/**
 * URL du logo officiel SYGES utilisée par défaut ou en secours si l'image personnalisée échoue.
 */
const DEFAULT_SYGES_LOGO_URL = '/images/syges-icon.png';

/**
 * Composant Logo affiché en haut à gauche de l'application (Barre latérale connectée).
 *
 * Conserve par défaut le logo, le nom et le sous-titre officiels SYGES (`Gestion Scolaire`),
 * et bascule automatiquement vers l'identité personnalisée (logo, nom court et sous-titre)
 * dès que l'administrateur active l'option depuis *Apparence & Identité*.
 *
 * @returns {JSX.Element} Le logo et libellé de la barre latérale.
 */
export default function AppLogo() {
    const { name, appBranding } = usePage<SidebarBrandingProps>().props;

    const useCustom = Boolean(appBranding?.use_custom_app_branding);
    const logoSrc = useCustom && appBranding?.logo_url ? appBranding.logo_url : DEFAULT_SYGES_LOGO_URL;
    const brandTitle = useCustom && appBranding?.brand_name ? appBranding.brand_name : (name ?? 'SYGES');
    const brandSubtitle =
        useCustom && appBranding?.brand_subtitle ? appBranding.brand_subtitle : 'Gestion Scolaire';

    return (
        <>
            <div className="flex aspect-square size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-xs">
                <img
                    src={logoSrc}
                    alt={`Logo ${brandTitle}`}
                    className="size-9 object-contain"
                    onError={(event) => {
                        // Repli silencieux sur l'icône SYGES officielle si un média personnalisé venait à manquer
                        if (!event.currentTarget.src.endsWith(DEFAULT_SYGES_LOGO_URL)) {
                            event.currentTarget.src = DEFAULT_SYGES_LOGO_URL;
                        }
                    }}
                />
            </div>
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-bold tracking-tight text-foreground text-base">
                    {brandTitle}
                </span>
                <span className="truncate text-xs font-normal text-muted-foreground">
                    {brandSubtitle}
                </span>
            </div>
        </>
    );
}

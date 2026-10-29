import type { SVGAttributes } from 'react';

/**
 * Icône officielle de l'application SYGES (favicon / marqueur visuel).
 * Remplace définitivement le logo cube SVG par défaut de Laravel.
 *
 * @param {SVGAttributes<SVGElement>} props Propriétés SVG standard
 * @returns {JSX.Element} L'icône officielle de l'application SYGES
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 208 208" xmlns="http://www.w3.org/2000/svg">
            <image href="/images/syges-icon.png" width="208" height="208" />
        </svg>
    );
}

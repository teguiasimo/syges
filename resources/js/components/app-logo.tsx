import { usePage } from '@inertiajs/react';

/**
 * Composant Logo officiel SYGES affiché en haut à gauche de l'application (Barre latérale).
 * Présente le logo officiel de la plateforme SYGES et le sous-titre officiel.
 *
 * @returns {JSX.Element} Le logo de l'application dans la barre latérale.
 */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-xs">
                <img
                    src="/images/syges-icon.png"
                    alt="Logo SYGES"
                    className="size-9 object-contain"
                />
            </div>
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-bold tracking-tight text-foreground text-base">
                    {name ?? 'SYGES'}
                </span>
                <span className="truncate text-xs font-normal text-muted-foreground">
                    Gestion Scolaire
                </span>
            </div>
        </>
    );
}

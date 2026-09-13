import { usePage } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';

/**
 * Composant Logo affiché en haut de la barre latérale.
 * Présente le logo de l'établissement, le nom SYGES et le sous-titre 'Gestion Scolaire'.
 *
 * @returns {JSX.Element} Le logo de l'application dans la barre latérale.
 */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-xs">
                <GraduationCap className="size-5" />
            </div>
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-bold text-foreground">
                    {name ?? 'SYGES'}
                </span>
                <span className="truncate text-xs font-normal text-muted-foreground">
                    Gestion Scolaire
                </span>
            </div>
        </>
    );
}

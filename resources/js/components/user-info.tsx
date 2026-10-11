import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

/**
 * Composant UserInfo affichant l'avatar et l'identification textuelle de l'utilisateur.
 * Supporte le masquage adaptatif du texte sur mobile pour préserver l'espace de la barre d'en-tête.
 *
 * @param {{ user: User; showEmail?: boolean; hideTextOnMobile?: boolean }} props
 * @returns {JSX.Element}
 */
export function UserInfo({
    user,
    showEmail = false,
    hideTextOnMobile = false,
}: {
    user: User;
    showEmail?: boolean;
    hideTextOnMobile?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-8 w-8 shrink-0 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className={`flex-1 text-left text-sm leading-tight min-w-0 ${hideTextOnMobile ? 'hidden sm:grid' : 'grid'}`}>
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="text-muted-foreground truncate text-xs">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}

import React, { useEffect, useState } from 'react';
import { User } from 'lucide-react';
import { cn, resolveMediaUrl } from '@/lib/utils';

/**
 * Propriétés du composant d'affichage de la photo d'identité d'un élève.
 */
interface EleveAvatarProps {
    /** URL ou chemin stocké en base de données (`eleve.photo_url`). */
    photoUrl?: string | null;
    /** Texte alternatif accessible (nom complet de l'élève). */
    alt: string;
    /** Classes CSS appliquées à la balise `<img>`. */
    imgClassName?: string;
    /** Classes CSS appliquées à l'icône utilisateur par défaut lorsque la photo est absente ou illisible. */
    iconClassName?: string;
    /** Élément de substitution personnalisé affiché en l'absence de photo ou en cas d'erreur de chargement. */
    fallback?: React.ReactNode;
}

/**
 * Affiche la photo d'identité d'un élève avec normalisation d'URL et repli automatique
 * si la photo est absente ou introuvable sur le serveur.
 *
 * @param {EleveAvatarProps} props Propriétés du composant.
 * @returns {React.JSX.Element} Image de l'élève ou visuel de substitution.
 */
export function EleveAvatar({
    photoUrl,
    alt,
    imgClassName = 'size-full object-cover',
    iconClassName = 'size-5 text-primary/70',
    fallback,
}: EleveAvatarProps) {
    const resolvedSrc = resolveMediaUrl(photoUrl);
    const [hasLoadError, setHasLoadError] = useState(false);

    // Réinitialiser l'état d'erreur dès que l'URL de la photo est mise à jour
    useEffect(() => {
        setHasLoadError(false);
    }, [resolvedSrc]);

    if (resolvedSrc && !hasLoadError) {
        return (
            <img
                key={resolvedSrc}
                src={resolvedSrc}
                alt={alt}
                className={imgClassName}
                onError={() => setHasLoadError(true)}
            />
        );
    }

    if (fallback !== undefined) {
        return <>{fallback}</>;
    }

    return <User className={cn(iconClassName)} />;
}

import * as React from 'react';
import { cn } from '@/lib/utils';

/**
 * Composant Textarea standard shadcn/ui stylisé pour SYGES.
 *
 * @param {React.ComponentProps<'textarea'>} props - Propriétés natives de la balise textarea.
 * @returns {JSX.Element} Élément textarea stylisé.
 */
function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                className
            )}
            {...props}
        />
    );
}

export { Textarea };

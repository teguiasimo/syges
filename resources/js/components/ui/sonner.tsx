import { useFlashToast } from '@/hooks/use-flash-toast';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/**
 * Conteneur global des notifications Toast (verrouillé en thème clair, position haut droite).
 *
 * @param {ToasterProps} props Propriétés optionnelles Sonner.
 * @returns {JSX.Element} Composant Toaster configuré pour SYGES.
 */
function Toaster({ ...props }: ToasterProps) {
    useFlashToast();

    return (
        <Sonner
            theme="light"
            className="toaster group"
            position="top-right"
            richColors
            toastOptions={{
                classNames: {
                    success: '!bg-emerald-50 !text-emerald-950 !border-emerald-300',
                    error: '!bg-rose-50 !text-rose-950 !border-rose-300',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };

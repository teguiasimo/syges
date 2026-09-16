import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="top-right"
            richColors
            toastOptions={{
                classNames: {
                    success: '!bg-emerald-50 !text-emerald-950 !border-emerald-300 dark:!bg-emerald-950/90 dark:!text-emerald-100 dark:!border-emerald-800',
                    error: '!bg-rose-50 !text-rose-950 !border-rose-300 dark:!bg-rose-950/90 dark:!text-rose-100 dark:!border-rose-800',
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

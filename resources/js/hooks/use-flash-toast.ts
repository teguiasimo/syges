import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message, {
                id: 'global-flash-toast',
                position: 'top-right',
                className: data.type === 'success' ? 'bg-emerald-600 text-white border-emerald-700' : undefined,
            });
        });
    }, []);
}

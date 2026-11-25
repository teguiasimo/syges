import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Délai de grâce (en millisecondes) avant d'afficher l'indicateur de chargement de navigation.
 * Évite tout clignotement visuel lors des transitions rapides (< 200 ms).
 */
const NAVIGATION_SPINNER_DELAY_MS = 200;

/**
 * Hook React observant les transitions de pages complètes Inertia (`router.on('start' | 'finish')`).
 *
 * Ignore volontairement les préchargements (`prefetch`) et rechargements partiels ciblés (`only` / props différées)
 * afin de ne signaler que les changements d'écran réellement initiés par l'utilisateur.
 *
 * @param {number} [delayMs=200] Délai minimal avant activation de l'état de chargement visible.
 * @returns {boolean} `true` lorsqu'une navigation de page est en cours depuis plus de `delayMs`.
 */
export function useInertiaNavigationLoading(delayMs: number = NAVIGATION_SPINNER_DELAY_MS): boolean {
    const [isLoading, setIsLoading] = useState(false);
    const timerRef = useRef<number | null>(null);
    const activeVisitsRef = useRef<number>(0);

    useEffect(() => {
        const clearPendingTimer = () => {
            if (timerRef.current !== null) {
                window.clearTimeout(timerRef.current);
                timerRef.current = null;
            }
        };

        const isIgnoredVisit = (event: unknown): boolean => {
            const visit = (event as CustomEvent)?.detail?.visit as
                | { prefetch?: boolean; only?: string[]; method?: string; async?: boolean }
                | undefined;

            // Ignore les préchargements silencieux, requêtes asynchrones en arrière-plan et props différées
            return Boolean(
                visit?.prefetch ||
                    visit?.async ||
                    (Array.isArray(visit?.only) && visit.only.length > 0),
            );
        };

        const stopLoading = (event?: unknown) => {
            if (event && isIgnoredVisit(event)) {
                return;
            }
            activeVisitsRef.current = Math.max(0, activeVisitsRef.current - 1);
            if (activeVisitsRef.current === 0) {
                clearPendingTimer();
                setIsLoading(false);
            }
        };

        const forceResetLoading = () => {
            activeVisitsRef.current = 0;
            clearPendingTimer();
            setIsLoading(false);
        };

        const removeStart = router.on('start', (event) => {
            if (isIgnoredVisit(event)) {
                return;
            }

            activeVisitsRef.current += 1;
            if (timerRef.current === null) {
                timerRef.current = window.setTimeout(() => {
                    timerRef.current = null;
                    if (activeVisitsRef.current > 0) {
                        setIsLoading(true);
                    }
                }, delayMs);
            }
        });

        const removeFinish = router.on('finish', (event) => stopLoading(event));
        const removeCancel = router.on('cancel', forceResetLoading);
        const removeHttpException = router.on('httpException', forceResetLoading);
        const removeNetworkError = router.on('networkError', forceResetLoading);

        return () => {
            clearPendingTimer();
            removeStart();
            removeFinish();
            removeCancel();
            removeHttpException();
            removeNetworkError();
        };
    }, [delayMs]);

    return isLoading;
}

/**
 * Indicateur visuel discret et non bloquant de chargement de page pour les navigations Inertia.
 *
 * Positionné en haut à droite (cohérent avec les notifications toast SYGES), il apparaît uniquement
 * lorsqu'une transition dépasse 200 ms afin de confirmer immédiatement à l'utilisateur que sa demande
 * est en cours de traitement sans jamais figer l'interface ni clignoter sur les pages instantanées.
 *
 * @returns {JSX.Element | null} Pilule flottante animée avec spinner ou `null` hors chargement.
 */
export function NavigationProgressIndicator() {
    const isLoading = useInertiaNavigationLoading(NAVIGATION_SPINNER_DELAY_MS);

    if (!isLoading) {
        return null;
    }

    return (
        <div
            role="status"
            aria-live="polite"
            aria-label="Chargement de la page en cours"
            className="pointer-events-none fixed top-3 right-4 z-50 flex items-center gap-2 rounded-full border border-emerald-200 bg-white/95 px-3.5 py-1.5 text-xs font-semibold text-emerald-800 shadow-lg backdrop-blur-sm animate-in fade-in slide-in-from-top-1 duration-150"
        >
            <Loader2 className="size-3.5 animate-spin text-emerald-600 shrink-0" />
            <span>Chargement en cours...</span>
        </div>
    );
}

import React, { useState, useCallback } from 'react';
import { AlertTriangle, Trash2, Loader2, Sparkles, HelpCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Propriétés du composant modal universel de confirmation (suppression et actions critiques).
 */
export interface ConfirmDeleteDialogProps {
    /** Indique si la boîte de dialogue est ouverte */
    open: boolean;
    /** Callback de changement d'état d'ouverture */
    onOpenChange: (open: boolean) => void;
    /** Action exécutée lorsque l'utilisateur confirme */
    onConfirm: () => void;
    /** Titre de la modale (par défaut : 'Confirmer la suppression') */
    title?: string;
    /** Type ou catégorie de l'entité (ex: 'l\'établissement', 'la classe') */
    itemType?: string;
    /** Nom ou libellé de l'élément cible mis en exergue */
    itemName?: string;
    /** Question ou texte principal personnalisé (optionnel) */
    promptText?: React.ReactNode;
    /** Description ou avertissement détaillé */
    description?: React.ReactNode;
    /** Libellé du bouton de confirmation (par défaut : 'Supprimer définitivement') */
    confirmLabel?: string;
    /** Libellé du bouton d'annulation (par défaut : 'Annuler') */
    cancelLabel?: string;
    /** État de chargement désactivant les interactions */
    isLoading?: boolean;
    /** Variante visuelle de la modale ('destructive' | 'primary' | 'warning') */
    variant?: 'destructive' | 'primary' | 'warning';
}

/**
 * Options passées à la méthode `confirmDelete` du hook `useConfirmDelete`.
 */
export interface ConfirmDeleteOptions {
    title?: string;
    itemType?: string;
    itemName?: string;
    promptText?: React.ReactNode;
    description?: React.ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'destructive' | 'primary' | 'warning';
    onConfirm: () => void;
}

/**
 * Composant Modal Universel de Confirmation de Suppression & Actions Critiques pour SYGES.
 *
 * Remplace avantageusement les popups natives du navigateur (window.confirm)
 * par une interface esthétique, moderne, accessible et cohérente :
 * - Avertissement visuel avec icône stylisée selon la variante (destructive, primary, warning)
 * - Mise en valeur du nom de l'entité ciblée
 * - Bouton d'action rouge destructif ou primaire avec état de chargement
 * - Adaptabilité totale au thème sombre / clair
 *
 * @param {ConfirmDeleteDialogProps} props
 * @returns {JSX.Element}
 */
export function ConfirmDeleteDialog({
    open,
    onOpenChange,
    onConfirm,
    title = 'Confirmer la suppression',
    itemType,
    itemName,
    promptText,
    description,
    confirmLabel,
    cancelLabel = 'Annuler',
    isLoading = false,
    variant = 'destructive',
}: ConfirmDeleteDialogProps) {
    const handleConfirm = () => {
        onConfirm();
    };

    const isDestructive = variant === 'destructive';
    const isPrimary = variant === 'primary';
    const isWarning = variant === 'warning';

    const defaultConfirmLabel = isDestructive
        ? 'Supprimer définitivement'
        : isPrimary
        ? 'Confirmer'
        : 'Continuer';

    const effectiveConfirmLabel = confirmLabel || defaultConfirmLabel;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md p-0 sm:p-0 overflow-hidden border-border/80 shadow-2xl">
                {/* Bandeau d'en-tête avec icône d'alerte stylisée */}
                <div className="p-6 pb-4 space-y-4">
                    <div className="flex items-start gap-4">
                        <div
                            className={`size-11 rounded-full flex items-center justify-center shrink-0 shadow-xs ring-4 ${
                                isDestructive
                                    ? 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 ring-rose-50 dark:ring-rose-950/30'
                                    : isPrimary
                                    ? 'bg-blue-100 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 ring-blue-50 dark:ring-blue-950/30'
                                    : 'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 ring-amber-50 dark:ring-amber-950/30'
                            }`}
                        >
                            {isDestructive ? (
                                <Trash2 className="size-5" />
                            ) : isPrimary ? (
                                <Sparkles className="size-5" />
                            ) : (
                                <AlertTriangle className="size-5" />
                            )}
                        </div>

                        <div className="space-y-1 pt-0.5 flex-1">
                            <DialogTitle className="text-lg font-bold text-foreground">
                                {title}
                            </DialogTitle>
                            <DialogDescription className="text-xs text-muted-foreground">
                                Cette action requiert votre confirmation formelle.
                            </DialogDescription>
                        </div>
                    </div>

                    {/* Bloc descriptif avec nom de l'élément mis en exergue */}
                    <div className="space-y-3 pt-1">
                        {promptText ? (
                            <p className="text-sm text-foreground/90 leading-relaxed font-medium">
                                {promptText}
                            </p>
                        ) : (
                            <p className="text-sm text-foreground/90 leading-relaxed">
                                Êtes-vous sûr de vouloir supprimer{' '}
                                {itemType ? `${itemType} ` : ''}
                                {itemName && (
                                    <strong className="font-semibold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-1.5 py-0.5 rounded border border-rose-200/80 dark:border-rose-900/60 break-all">
                                        « {itemName} »
                                    </strong>
                                )}{' '}
                                ?
                            </p>
                        )}

                        {/* Avertissement / Détail */}
                        {description && (
                            <div
                                className={`rounded-lg p-3 text-xs flex items-start gap-2.5 border ${
                                    isDestructive
                                        ? 'bg-rose-50/80 border-rose-200/90 text-rose-900 dark:bg-rose-950/40 dark:border-rose-900/60 dark:text-rose-200'
                                        : isPrimary
                                        ? 'bg-blue-50/80 border-blue-200/90 text-blue-900 dark:bg-blue-950/40 dark:border-blue-900/60 dark:text-blue-200'
                                        : 'bg-amber-50/80 border-amber-200/90 text-amber-900 dark:bg-amber-950/40 dark:border-amber-900/60 dark:text-amber-200'
                                }`}
                            >
                                {isDestructive ? (
                                    <AlertTriangle className="size-4 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                                ) : isPrimary ? (
                                    <Sparkles className="size-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                                ) : (
                                    <AlertTriangle className="size-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                                )}
                                <div className="space-y-0.5">
                                    {isDestructive && (
                                        <p className="font-bold text-rose-700 dark:text-rose-300">
                                            Attention : cette opération est irréversible.
                                        </p>
                                    )}
                                    <p className="leading-relaxed">
                                        {description}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Barre d'action inférieure avec boutons Annuler et Confirmer */}
                <DialogFooter className="bg-muted/40 px-6 py-3.5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 border-t border-border/60">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isLoading}
                        className="cursor-pointer text-xs h-9"
                    >
                        {cancelLabel}
                    </Button>

                    <Button
                        type="button"
                        onClick={handleConfirm}
                        disabled={isLoading}
                        className={`gap-2 cursor-pointer text-xs h-9 text-white shadow-sm font-semibold ${
                            isDestructive
                                ? 'bg-rose-600 hover:bg-rose-700 border-rose-700'
                                : isPrimary
                                ? 'bg-blue-600 hover:bg-blue-700 border-blue-700'
                                : 'bg-amber-600 hover:bg-amber-700 border-amber-700'
                        }`}
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="size-3.5 animate-spin" />
                                <span>Traitement en cours...</span>
                            </>
                        ) : (
                            <>
                                {isDestructive && <Trash2 className="size-3.5" />}
                                {isPrimary && <Sparkles className="size-3.5" />}
                                {isWarning && <AlertTriangle className="size-3.5" />}
                                <span>{effectiveConfirmLabel}</span>
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Hook React personnalisé facilitant l'intégration de la modale de confirmation dans n'importe quelle page.
 *
 * Utilisation typique :
 * ```tsx
 * const { confirmDelete, ConfirmDialog } = useConfirmDelete();
 *
 * // Dans un bouton ou une action :
 * confirmDelete({
 *     title: "Supprimer l'établissement",
 *     itemType: "l'établissement",
 *     itemName: item.nom,
 *     onConfirm: () => router.delete(`/administration/etablissements/${item.id}`),
 * });
 *
 * // Dans le JSX :
 * return (
 *     <>
 *         ...
 *         <ConfirmDialog />
 *     </>
 * );
 * ```
 */
export function useConfirmDelete() {
    const [state, setState] = useState<{
        open: boolean;
        title?: string;
        itemType?: string;
        itemName?: string;
        promptText?: React.ReactNode;
        description?: React.ReactNode;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'destructive' | 'primary' | 'warning';
        onConfirm: () => void;
        isLoading?: boolean;
    }>({
        open: false,
        onConfirm: () => {},
    });

    /**
     * Déclenche l'affichage de la boîte de dialogue de confirmation personnalisée.
     */
    const confirmDelete = useCallback((options: ConfirmDeleteOptions) => {
        setState({
            open: true,
            title: options.title,
            itemType: options.itemType,
            itemName: options.itemName,
            promptText: options.promptText,
            description: options.description,
            confirmLabel: options.confirmLabel,
            cancelLabel: options.cancelLabel,
            variant: options.variant,
            onConfirm: () => {
                options.onConfirm();
                setState((prev) => ({ ...prev, open: false }));
            },
            isLoading: false,
        });
    }, []);

    /**
     * Ferme la modale de confirmation sans exécuter l'action.
     */
    const closeConfirm = useCallback(() => {
        setState((prev) => ({ ...prev, open: false }));
    }, []);

    /**
     * Composant React pré-câblé à insérer dans le template JSX.
     */
    const ConfirmDialog = useCallback(() => {
        return (
            <ConfirmDeleteDialog
                open={state.open}
                onOpenChange={(open) => {
                    if (!open) closeConfirm();
                }}
                onConfirm={state.onConfirm}
                title={state.title}
                itemType={state.itemType}
                itemName={state.itemName}
                promptText={state.promptText}
                description={state.description}
                confirmLabel={state.confirmLabel}
                cancelLabel={state.cancelLabel}
                isLoading={state.isLoading}
                variant={state.variant}
            />
        );
    }, [state, closeConfirm]);

    return {
        confirmDelete,
        closeConfirm,
        ConfirmDialog,
        isOpen: state.open,
    };
}

export default ConfirmDeleteDialog;

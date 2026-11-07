import React, { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { VersementCaisse } from '@/types/finance';

/**
 * Propriétés de la modale d'annulation unitaire ou groupée de versements de caisse.
 */
interface AnnulerVersementModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    versement?: VersementCaisse | null;
    versementsLot?: VersementCaisse[];
    onCancelledSuccess?: () => void;
}

/**
 * Modale d'annulation administrative d'un versement unitaire ou d'un lot de versements
 * (ex: annuler en une seule fois les 8 opérations d'un même élève suite à une erreur ou un retrait).
 *
 * Exige un motif écrit unique, conserve la trace complète dans le journal d'audit
 * et déclenche le recalcul automatique du solde des comptes élèves impactés.
 *
 * @param {AnnulerVersementModalProps} props Propriétés de la modale
 * @returns {JSX.Element} Modale de confirmation d'annulation
 */
export default function AnnulerVersementModal({
    open,
    onOpenChange,
    versement = null,
    versementsLot = [],
    onCancelledSuccess,
}: AnnulerVersementModalProps) {
    const [motifAnnulation, setMotifAnnulation] = useState<string>('');
    const [processing, setProcessing] = useState<boolean>(false);
    const [fieldError, setFieldError] = useState<string | null>(null);

    /**
     * Liste effective des versements ciblés (mode lot prioritaire si plusieurs éléments fournis, sinon unitaire).
     */
    const cibles = useMemo<VersementCaisse[]>(() => {
        if (versementsLot.length > 0) return versementsLot;
        return versement ? [versement] : [];
    }, [versement, versementsLot]);

    const estModeLot = cibles.length > 1;
    const montantCumule = useMemo(
        () => cibles.reduce((acc, v) => acc + Number(v.montant_total || 0), 0),
        [cibles]
    );

    /**
     * Soumet l'annulation (unitaire ou en lot) au serveur.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (cibles.length === 0) return;

        const motifNettoye = motifAnnulation.trim();
        if (motifNettoye.length < 5) {
            setFieldError('Veuillez saisir un motif explicite (minimum 5 caractères).');
            return;
        }

        setProcessing(true);
        setFieldError(null);

        if (cibles.length === 1 && versementsLot.length === 0) {
            router.post(
                `/administration/finances/versements/${cibles[0].id}/annuler`,
                { motif_annulation: motifNettoye },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success(
                            'Le versement a été annulé avec succès. Le compte élève a été réajusté.',
                            {
                                position: 'top-right',
                                className: 'bg-emerald-600 text-white border-none',
                            }
                        );
                        setMotifAnnulation('');
                        onCancelledSuccess?.();
                        onOpenChange(false);
                    },
                    onError: (err) => {
                        const msg =
                            (err as Record<string, string>)?.motif_annulation ||
                            (err as Record<string, string>)?.error ||
                            Object.values(err)[0] ||
                            "Erreur lors de l'annulation du versement.";
                        setFieldError(String(msg));
                        toast.error(String(msg), { position: 'top-right' });
                    },
                    onFinish: () => setProcessing(false),
                }
            );
            return;
        }

        router.post(
            '/administration/finances/versements/annuler-lot',
            {
                versement_ids: cibles.map((v) => v.id),
                motif_annulation: motifNettoye,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        `${cibles.length} versement(s) annulé(s) en une seule opération. Les soldes ont été recalculés.`,
                        {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        }
                    );
                    setMotifAnnulation('');
                    onCancelledSuccess?.();
                    onOpenChange(false);
                },
                onError: (err) => {
                    const msg =
                        (err as Record<string, string>)?.motif_annulation ||
                        (err as Record<string, string>)?.error ||
                        Object.values(err)[0] ||
                        "Erreur lors de l'annulation groupée.";
                    setFieldError(String(msg));
                    toast.error(String(msg), { position: 'top-right' });
                },
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    setMotifAnnulation('');
                    setFieldError(null);
                }
                onOpenChange(nextOpen);
            }}
        >
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-red-600">
                        <AlertTriangle className="w-5 h-5 shrink-0" />
                        {estModeLot
                            ? `Annuler ${cibles.length} Versements Sélectionnés`
                            : 'Annuler un Versement de Caisse'}
                    </DialogTitle>
                    <DialogDescription className="wrap-break-word text-xs">
                        {estModeLot ? (
                            <>
                                Vous allez annuler en une seule fois{' '}
                                <strong>{cibles.length} reçus</strong> pour un montant cumulé de{' '}
                                <strong className="tabular-nums text-slate-900 dark:text-slate-100">
                                    {montantCumule.toLocaleString('fr-FR')} FCFA
                                </strong>
                                .
                            </>
                        ) : (
                            <>
                                Reçu N° <strong>{cibles[0]?.reference_recu}</strong> pour un montant de{' '}
                                <strong className="tabular-nums text-slate-900 dark:text-slate-100">
                                    {montantCumule.toLocaleString('fr-FR')} FCFA
                                </strong>
                                .
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {estModeLot && (
                        <div className="max-h-32 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60 p-2.5 space-y-1">
                            {cibles.map((v) => (
                                <div
                                    key={v.id}
                                    className="flex items-center justify-between gap-2 text-[11px]"
                                >
                                    <span className="font-mono font-semibold text-slate-700 dark:text-slate-300 truncate">
                                        {v.reference_recu} — {v.compte_financier?.eleve?.nom}{' '}
                                        {v.compte_financier?.eleve?.prenom}
                                    </span>
                                    <span className="font-bold text-slate-900 dark:text-slate-100 tabular-nums shrink-0">
                                        {Number(v.montant_total).toLocaleString('fr-FR')} FCFA
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="p-3 bg-red-50 dark:bg-red-950/40 rounded-lg border border-red-200 dark:border-red-900 text-xs text-red-700 dark:text-red-300">
                        Attention : cette opération déduira immédiatement{' '}
                        {estModeLot ? 'les montants versés' : 'le montant versé'} du dossier concerné et
                        conservera la trace d'audit dans le journal comptable.
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="motif_annulation" className="text-xs font-semibold">
                            Motif de l'annulation (Obligatoire) <span className="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="motif_annulation"
                            rows={3}
                            placeholder="Ex : Erreur d'imputation (versé sur l'élève X au lieu de l'élève Y), retrait de dossier..."
                            value={motifAnnulation}
                            onChange={(e) => setMotifAnnulation(e.target.value)}
                            className="text-xs"
                            required
                        />
                        {fieldError && <p className="text-xs text-red-500">{fieldError}</p>}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full sm:w-auto text-xs"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Conserver
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || motifAnnulation.trim().length < 5}
                            variant="destructive"
                            className="w-full sm:w-auto text-xs font-semibold"
                        >
                            {processing
                                ? 'Annulation en cours...'
                                : estModeLot
                                  ? `Confirmer l'annulation des ${cibles.length} reçus`
                                  : "Confirmer l'Annulation"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

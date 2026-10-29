import React from 'react';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { VersementCaisse } from '@/types/finance';
import { AlertTriangle } from 'lucide-react';

interface AnnulerVersementModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    versement: VersementCaisse | null;
}

/**
 * Modale d'annulation administrative d'un versement (Gesco V1.1 - Module 11.4).
 * Exige un motif écrit et conserve la trace dans le journal d'audit.
 *
 * @param {AnnulerVersementModalProps} props
 * @returns {JSX.Element}
 */
export default function AnnulerVersementModal({ open, onOpenChange, versement }: AnnulerVersementModalProps) {
    const { data, setData, post, processing, reset, errors } = useForm({
        motif_annulation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!versement) return;

        post(`/administration/finances/versements/${versement.id}/annuler`, {
            onSuccess: () => {
                toast.success('Le versement a été annulé avec succès. Le compte a été réajusté.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.error || 'Erreur lors de l’annulation.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-red-600">
                        <AlertTriangle className="w-5 h-5 shrink-0" />
                        Annuler un Versement de Caisse
                    </DialogTitle>
                    <DialogDescription className="wrap-break-word">
                        Reçu N° <strong>{versement?.reference_recu}</strong> pour un montant de{' '}
                        <strong className="tabular-nums">{Number(versement?.montant_total || 0).toLocaleString('fr-FR')} FCFA</strong>.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="p-3 bg-red-50 dark:bg-red-950/40 rounded border border-red-200 dark:border-red-900 text-xs text-red-700 dark:text-red-300">
                        Attention : cette opération déduira le montant versé de l'ardoise de l'élève et émettra une écriture d'annulation irréversible dans le journal comptable.
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="motif_annulation">Motif de l'annulation (Obligatoire) <span className="text-red-500">*</span></Label>
                        <Textarea
                            id="motif_annulation"
                            rows={3}
                            placeholder="Indiquez la raison détaillée (erreur de saisie, chèque rejeté...)"
                            value={data.motif_annulation}
                            onChange={(e) => setData('motif_annulation', e.target.value)}
                            required
                        />
                        {errors.motif_annulation && <p className="text-xs text-red-500">{errors.motif_annulation}</p>}
                    </div>

                    {/* Pied de modale empilé sur mobile, aligné à droite sur desktop */}
                    <DialogFooter>
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={() => onOpenChange(false)}>
                            Conserver le versement
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || data.motif_annulation.trim().length < 5}
                            variant="destructive"
                            className="w-full sm:w-auto"
                        >
                            {processing ? 'Annulation...' : 'Confirmer l’Annulation'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

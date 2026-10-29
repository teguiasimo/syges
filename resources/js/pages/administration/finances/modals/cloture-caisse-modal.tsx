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
import { JournalDuJour } from '@/types/finance';
import { formatDate } from '@/lib/utils';
import { Lock, ShieldCheck } from 'lucide-react';

interface ClotureCaisseModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    journal: JournalDuJour;
    etablissementId: number;
}

/**
 * Modale de clôture journalière de caisse (Gesco V1.1 - Module 11.4).
 * Affiche la synthèse des encaissements par mode de règlement et verrouille les écritures.
 *
 * @param {ClotureCaisseModalProps} props
 * @returns {JSX.Element}
 */
export default function ClotureCaisseModal({
    open,
    onOpenChange,
    journal,
    etablissementId,
}: ClotureCaisseModalProps) {
    const { data, setData, post, processing, reset } = useForm({
        etablissement_id: etablissementId,
        date_cloture: journal.date,
        observations: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/administration/finances/cloturer-journee', {
            onSuccess: () => {
                toast.success('La caisse de la journée a été clôturée avec succès.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.error || 'Erreur lors de la clôture de caisse.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
                        <Lock className="w-5 h-5 text-amber-600 shrink-0" />
                        Clôture Journalière de Caisse
                    </DialogTitle>
                    <DialogDescription>
                        Date de clôture : <strong>{formatDate(journal.date)}</strong> ({journal.nombre_transactions} transactions)
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Synthèse par mode de paiement adaptable aux petits écrans */}
                    <div className="p-3 bg-slate-50 dark:bg-slate-900 rounded border divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                        <div className="flex flex-wrap justify-between gap-2 py-1">
                            <span>Espèces physiques :</span>
                            <span className="font-semibold tabular-nums">{Number(journal.total_especes).toLocaleString('fr-FR')} FCFA</span>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 py-1">
                            <span>Mobile Money (Manuel) :</span>
                            <span className="font-semibold tabular-nums">{Number(journal.total_mobile_money).toLocaleString('fr-FR')} FCFA</span>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 py-1">
                            <span>Virements Bancaires :</span>
                            <span className="font-semibold tabular-nums">{Number(journal.total_virements).toLocaleString('fr-FR')} FCFA</span>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 py-1">
                            <span>Chèques :</span>
                            <span className="font-semibold tabular-nums">{Number(journal.total_cheques).toLocaleString('fr-FR')} FCFA</span>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 pt-2 font-bold text-sm text-emerald-600">
                            <span>TOTAL GÉNÉRAL DU JOUR :</span>
                            <span className="tabular-nums">{Number(journal.total_general).toLocaleString('fr-FR')} FCFA</span>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="cloture_observations">Observations / Remarques de caisse</Label>
                        <Textarea
                            id="cloture_observations"
                            rows={3}
                            placeholder="Écarts éventuels, billets abîmés, observations..."
                            value={data.observations}
                            onChange={(e) => setData('observations', e.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white flex items-center justify-center gap-1"
                        >
                            <ShieldCheck className="w-4 h-4 shrink-0" />
                            {processing ? 'Verrouillage...' : 'Valider la Clôture'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

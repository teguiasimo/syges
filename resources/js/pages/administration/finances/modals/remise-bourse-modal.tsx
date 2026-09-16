import React, { useEffect } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CompteFinancierEleve } from '@/types/finance';
import { Award, CheckCircle2 } from 'lucide-react';

interface RemiseBourseModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    compte: CompteFinancierEleve | null;
}

/**
 * Modale d'ajustement de bourse ou remise financière accordée à un élève (Gesco V1.1 - Module 11.1).
 *
 * @param {RemiseBourseModalProps} props
 * @returns {JSX.Element}
 */
export default function RemiseBourseModal({ open, onOpenChange, compte }: RemiseBourseModalProps) {
    const { data, setData, post, processing, reset } = useForm({
        remise_bourse: '0',
        observations: '',
    });

    useEffect(() => {
        if (compte) {
            setData({
                remise_bourse: String(compte.remise_bourse || 0),
                observations: compte.observations || '',
            });
        }
    }, [compte]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte) return;

        post(`/administration/finances/comptes/${compte.id}/ajuster`, {
            onSuccess: () => {
                toast.success('Bourse / Remise appliquée avec succès.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.error || 'Erreur lors de l’application de la remise.', {
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
                        <Award className="w-5 h-5 text-indigo-600" />
                        Accorder une Remise ou Bourse d'études
                    </DialogTitle>
                    <DialogDescription>
                        Élève : <strong>{compte?.eleve?.nom} {compte?.eleve?.prenom}</strong> ({compte?.eleve?.matricule})
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="p-3 bg-slate-50 dark:bg-slate-900 rounded border text-xs space-y-1">
                        <div className="flex justify-between">
                            <span className="text-slate-500">Débit Initial Brut :</span>
                            <span className="font-semibold">{Number(compte?.debit_initial || 0).toLocaleString('fr-FR')} FCFA</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-slate-500">Total Déjà Versé :</span>
                            <span className="font-semibold text-emerald-600">{Number(compte?.total_paye || 0).toLocaleString('fr-FR')} FCFA</span>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="remise_bourse">Montant de la Remise / Bourse (FCFA) <span className="text-red-500">*</span></Label>
                        <Input
                            id="remise_bourse"
                            type="number"
                            min={0}
                            value={data.remise_bourse}
                            onChange={(e) => setData('remise_bourse', e.target.value)}
                            required
                            className="text-right font-semibold"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="observations_bourse">Motif / Justification de la bourse</Label>
                        <Textarea
                            id="observations_bourse"
                            rows={3}
                            placeholder="Ex: Bourse d'excellence académique, tarif fratrie..."
                            value={data.observations}
                            onChange={(e) => setData('observations', e.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1"
                        >
                            <CheckCircle2 className="w-4 h-4" />
                            {processing ? 'Enregistrement...' : 'Valider la Remise'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

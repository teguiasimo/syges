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
        arrieres_anterieurs: '0',
        observations: '',
    });

    useEffect(() => {
        if (compte) {
            setData({
                remise_bourse: String(compte.remise_bourse || 0),
                arrieres_anterieurs: String(compte.arrieres_anterieurs || 0),
                observations: compte.observations || '',
            });
        }
    }, [compte]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte) return;

        post(`/administration/finances/comptes/${compte.id}/ajuster`, {
            onSuccess: () => {
                toast.success('Compte financier ajusté avec succès.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.error || 'Erreur lors de l’ajustement du compte.', {
                    position: 'top-right',
                });
            },
        });
    };

    const debitInitial = Number(compte?.debit_initial || 0);
    const arrieres = Number(data.arrieres_anterieurs || 0);
    const remise = Number(data.remise_bourse || 0);
    const netPrevu = Math.max(0, debitInitial + arrieres - remise);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
                        <Award className="w-5 h-5 text-teal-600 shrink-0" />
                        Ajustement Financier de l'Élève
                    </DialogTitle>
                    <DialogDescription className="wrap-break-word">
                        Élève : <strong>{compte?.eleve?.nom} {compte?.eleve?.prenom}</strong> ({compte?.eleve?.matricule})
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 min-w-0">
                    {/* Synthèse chiffrée adaptable aux petits écrans */}
                    <div className="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border text-xs space-y-1.5">
                        <div className="flex flex-wrap justify-between gap-2">
                            <span className="text-slate-500">Frais Annuels Bruts :</span>
                            <span className="font-semibold tabular-nums">{debitInitial.toLocaleString('fr-FR')} FCFA</span>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2">
                            <span className="text-slate-500">Total Déjà Versé :</span>
                            <span className="font-semibold text-emerald-600 tabular-nums">{Number(compte?.total_paye || 0).toLocaleString('fr-FR')} FCFA</span>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 pt-1 border-t border-slate-200 dark:border-slate-800 font-bold">
                            <span className="text-slate-700 dark:text-slate-300">Net Exigible Calculé :</span>
                            <span className="text-teal-600 dark:text-teal-400 tabular-nums">{netPrevu.toLocaleString('fr-FR')} FCFA</span>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1.5 min-w-0">
                            <Label htmlFor="arrieres_anterieurs">Arriérés Antérieurs (FCFA)</Label>
                            <Input
                                id="arrieres_anterieurs"
                                type="number"
                                min={0}
                                value={data.arrieres_anterieurs}
                                onChange={(e) => setData('arrieres_anterieurs', e.target.value)}
                                className="text-right font-semibold tabular-nums"
                            />
                        </div>

                        <div className="space-y-1.5 min-w-0">
                            <Label htmlFor="remise_bourse">Remise / Bourse (FCFA)</Label>
                            <Input
                                id="remise_bourse"
                                type="number"
                                min={0}
                                value={data.remise_bourse}
                                onChange={(e) => setData('remise_bourse', e.target.value)}
                                className="text-right font-semibold tabular-nums"
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="observations_bourse">Motif / Observations</Label>
                        <Textarea
                            id="observations_bourse"
                            rows={3}
                            placeholder="Ex: Report dette 2024-2025, Bourse d'excellence académique..."
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
                            className="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white flex items-center justify-center gap-1"
                        >
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            {processing ? 'Enregistrement...' : 'Valider la Remise'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

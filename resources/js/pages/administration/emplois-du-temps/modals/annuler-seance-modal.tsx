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
import { SeanceEmploiTemps } from '@/types/emploi-du-temps';
import { CalendarX } from 'lucide-react';

interface AnnulerSeanceModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    seance: SeanceEmploiTemps | null;
}

/**
 * Modale d'annulation motivée d'un créneau d'emploi du temps (Module 13.4).
 *
 * @param {AnnulerSeanceModalProps} props
 * @returns {JSX.Element}
 */
export default function AnnulerSeanceModal({
    open,
    onOpenChange,
    seance,
}: AnnulerSeanceModalProps) {
    const { data, setData, post, processing, reset, errors } = useForm({
        motif_annulation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!seance) return;

        post(`/administration/emplois-du-temps/seances/${seance.id}/annuler`, {
            onSuccess: () => {
                toast.success('Séance marquée comme annulée. Emplois du temps mis à jour.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.motif_annulation || 'Erreur lors de l’annulation de la séance.', {
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
                        <CalendarX className="w-5 h-5 text-rose-600" />
                        Annuler ce Cours
                    </DialogTitle>
                    <DialogDescription>
                        {seance && (
                            <span>
                                Cours de <strong>{seance.attribution?.matiere?.nom}</strong> ({seance.classe?.nom}) le{' '}
                                <strong className="capitalize">{seance.jour_semaine}</strong> de {seance.heure_debut} à {seance.heure_fin}.
                            </span>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="motif_annulation" className="text-xs font-semibold">
                            Motif de l'annulation <span className="text-rose-500">*</span>
                        </Label>
                        <Textarea
                            id="motif_annulation"
                            rows={3}
                            placeholder="Ex: Absence maladie justifiée de l'enseignant, fermeture exceptionnelle..."
                            value={data.motif_annulation}
                            onChange={(e) => setData('motif_annulation', e.target.value)}
                            required
                        />
                        {errors.motif_annulation && (
                            <p className="text-xs text-rose-500">{errors.motif_annulation}</p>
                        )}
                    </div>

                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                reset();
                                onOpenChange(false);
                            }}
                            disabled={processing}
                        >
                            Retour
                        </Button>
                        <Button
                            type="submit"
                            className="bg-rose-600 hover:bg-rose-700 text-white font-semibold"
                            disabled={processing}
                        >
                            {processing ? 'Enregistrement...' : 'Confirmer l\'Annulation'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

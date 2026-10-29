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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { SeanceEmploiTemps } from '@/types/emploi-du-temps';
import { UserCheck } from 'lucide-react';

interface RemplacementModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    seance: SeanceEmploiTemps | null;
    enseignants: any[];
}

/**
 * Modale d'affectation d'un enseignant remplaçant (Module 13.4).
 *
 * @param {RemplacementModalProps} props
 * @returns {JSX.Element}
 */
export default function RemplacementModal({
    open,
    onOpenChange,
    seance,
    enseignants,
}: RemplacementModalProps) {
    const { data, setData, post, processing, reset, errors } = useForm({
        remplace_par_personnel_id: '',
        motif_annulation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!seance) return;

        post(`/administration/emplois-du-temps/seances/${seance.id}/remplacer`, {
            onSuccess: () => {
                toast.success('Remplaçant affecté avec succès. Agenda mis à jour.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.remplace_par_personnel_id || 'Conflit d’horaire pour ce remplaçant.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100">
                        <UserCheck className="w-5 h-5 text-teal-600 shrink-0" />
                        <span>Affecter un Enseignant Remplaçant</span>
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
                        <Label htmlFor="remplace_par_personnel_id" className="text-xs font-semibold">
                            Enseignant remplaçant disponible <span className="text-rose-500">*</span>
                        </Label>
                        <Select
                            value={data.remplace_par_personnel_id}
                            onValueChange={(val) => setData('remplace_par_personnel_id', val)}
                        >
                            <SelectTrigger id="remplace_par_personnel_id" className="text-xs h-9">
                                <SelectValue placeholder="Sélectionner un collègue..." />
                            </SelectTrigger>
                            <SelectContent className="max-h-56">
                                {enseignants
                                    .filter((ens) => ens.id !== seance?.attribution?.personnel_id)
                                    .map((ens) => (
                                        <SelectItem key={ens.id} value={String(ens.id)} className="text-xs">
                                            Prof. {ens.nom} {ens.prenom} ({ens.matricule})
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        {errors.remplace_par_personnel_id && (
                            <p className="text-xs text-rose-500">{errors.remplace_par_personnel_id}</p>
                        )}
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="motif_annulation" className="text-xs font-semibold">
                            Motif ou circonstances du remplacement
                        </Label>
                        <Textarea
                            id="motif_annulation"
                            rows={3}
                            placeholder="Ex: Remplacement ponctuel suite à congé maladie du titulaire..."
                            value={data.motif_annulation}
                            onChange={(e) => setData('motif_annulation', e.target.value)}
                        />
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
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            className="bg-teal-600 hover:bg-teal-700 text-white font-semibold"
                            disabled={processing}
                        >
                            {processing ? 'Vérification...' : 'Confirmer le Remplacement'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

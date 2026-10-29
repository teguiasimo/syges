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
import { Assiduite } from '@/types/discipline';
import { ShieldCheck } from 'lucide-react';
import { formatDate } from '@/lib/utils';

interface JustifierModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assiduite: Assiduite | null;
}

/**
 * Modale de justification a posteriori d'une absence ou d'un retard (Module 12.2).
 *
 * @param {JustifierModalProps} props
 * @returns {JSX.Element}
 */
export default function JustifierModal({
    open,
    onOpenChange,
    assiduite,
}: JustifierModalProps) {
    const { data, setData, post, processing, reset, errors } = useForm({
        motif_justification: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!assiduite) return;

        post(`/administration/discipline/assiduites/${assiduite.id}/justifier`, {
            onSuccess: () => {
                toast.success("L'événement d'assiduité a été justifié avec succès.", {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.motif_justification || "Erreur lors de la justification.", {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-md max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100">
                        <ShieldCheck className="w-5 h-5 text-emerald-600 shrink-0" />
                        <span>Justifier une Absence / Retard</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        {assiduite && (
                            <span>
                                Élève : <strong>{assiduite.inscription?.eleve?.nom} {assiduite.inscription?.eleve?.prenom}</strong> ({assiduite.nombre_heures}h le {formatDate(assiduite.date_evenement)})
                            </span>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="motif_justification" className="text-sm font-medium">
                            Motif ou document justificatif <span className="text-rose-500">*</span>
                        </Label>
                        <Textarea
                            id="motif_justification"
                            rows={4}
                            placeholder="Ex: Certificat médical du Dr. Martin présenté le 16/09..."
                            value={data.motif_justification}
                            onChange={(e) => setData('motif_justification', e.target.value)}
                            required
                        />
                        {errors.motif_justification && (
                            <p className="text-xs text-rose-500">{errors.motif_justification}</p>
                        )}
                    </div>

                    <DialogFooter className="flex flex-col-reverse sm:flex-row gap-2 pt-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                reset();
                                onOpenChange(false);
                            }}
                            disabled={processing}
                            className="w-full sm:w-auto"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold w-full sm:w-auto"
                            disabled={processing}
                        >
                            {processing ? 'Enregistrement...' : 'Valider la Justification'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { TypeSanction } from '@/types/discipline';
import { AlertOctagon } from 'lucide-react';
import { formatDateForInput } from '@/lib/utils';

interface NouvelleSanctionModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    inscriptions: any[];
    periodeId: number;
}

/**
 * Modale d'enregistrement d'une sanction disciplinaire officielle (Module 12.3).
 *
 * @param {NouvelleSanctionModalProps} props
 * @returns {JSX.Element}
 */
export default function NouvelleSanctionModal({
    open,
    onOpenChange,
    inscriptions,
    periodeId,
}: NouvelleSanctionModalProps) {
    const today = formatDateForInput(new Date().toISOString());

    const { data, setData, post, processing, reset, errors, transform } = useForm({
        inscription_id: '',
        periode_id: periodeId,
        date_decision: today,
        type_sanction: 'avertissement' as TypeSanction,
        motif: '',
        nombre_jours_ou_heures: '1',
        decide_par: 'Conseil de Discipline',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            periode_id: periodeId,
            nombre_jours_ou_heures: Number(formValues.nombre_jours_ou_heures),
        }));

        post('/administration/discipline/sanctions', {
            onSuccess: () => {
                toast.success('Sanction disciplinaire enregistrée avec succès.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-emerald-700',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.motif || 'Erreur lors de l’enregistrement de la sanction.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
                        <AlertOctagon className="w-5 h-5 text-rose-600 shrink-0" />
                        <span>Prononcer une Sanction Disciplinaire</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Enregistrement officiel dans le dossier disciplinaire et report sur le bulletin.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="inscription_id" className="text-xs sm:text-sm font-medium">
                            Élève concerné <span className="text-rose-500">*</span>
                        </Label>
                        <Select
                            value={data.inscription_id}
                            onValueChange={(val) => setData('inscription_id', val)}
                        >
                            <SelectTrigger id="inscription_id" className="w-full">
                                <SelectValue placeholder="Sélectionner un élève..." />
                            </SelectTrigger>
                            <SelectContent className="max-h-60">
                                {inscriptions.map((ins) => (
                                    <SelectItem key={ins.id} value={String(ins.id)}>
                                        {ins.eleve?.nom} {ins.eleve?.prenom} — {ins.classe?.nom} ({ins.eleve?.matricule})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.inscription_id && (
                            <p className="text-xs text-rose-500">{errors.inscription_id}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 min-[420px]:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="type_sanction" className="text-xs sm:text-sm font-medium">
                                Nature de la sanction <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.type_sanction}
                                onValueChange={(val: TypeSanction) => setData('type_sanction', val)}
                            >
                                <SelectTrigger id="type_sanction" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="avertissement">Avertissement conduite</SelectItem>
                                    <SelectItem value="blame">Blâme conduite</SelectItem>
                                    <SelectItem value="retenue">Retenue / Consigne (heures)</SelectItem>
                                    <SelectItem value="exclusion_temporaire">Exclusion Temporaire (jours)</SelectItem>
                                    <SelectItem value="exclusion_definitive">Exclusion Définitive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="nombre_jours_ou_heures" className="text-xs sm:text-sm font-medium">
                                {data.type_sanction === 'retenue' ? 'Nombre d\'heures' : 'Durée / Impact (jours)'} <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="nombre_jours_ou_heures"
                                type="number"
                                min={1}
                                max={30}
                                value={data.nombre_jours_ou_heures}
                                onChange={(e) => setData('nombre_jours_ou_heures', e.target.value)}
                                required
                                className="w-full"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 min-[420px]:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="date_decision" className="text-xs sm:text-sm font-medium">
                                Date de notification <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="date_decision"
                                type="date"
                                value={data.date_decision}
                                onChange={(e) => setData('date_decision', e.target.value)}
                                required
                                className="w-full"
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="decide_par" className="text-xs sm:text-sm font-medium">
                                Autorité décisionnaire <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.decide_par}
                                onValueChange={(val) => setData('decide_par', val)}
                            >
                                <SelectTrigger id="decide_par" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Conseil de Discipline">Conseil de Discipline</SelectItem>
                                    <SelectItem value="Chef d'Établissement">Chef d'Établissement</SelectItem>
                                    <SelectItem value="Censeur des Études">Censeur des Études</SelectItem>
                                    <SelectItem value="Surveillant Général">Surveillant Général</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="motif" className="text-xs sm:text-sm font-medium">
                            Motif circonstancié de la sanction <span className="text-rose-500">*</span>
                        </Label>
                        <Textarea
                            id="motif"
                            rows={3}
                            placeholder="Description précise des faits répréhensibles constatés..."
                            value={data.motif}
                            onChange={(e) => setData('motif', e.target.value)}
                            required
                            className="w-full text-xs sm:text-sm"
                        />
                        {errors.motif && <p className="text-xs text-rose-500">{errors.motif}</p>}
                    </div>

                    <DialogFooter className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-3">
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
                            className="w-full sm:w-auto bg-rose-600 hover:bg-rose-700 text-white font-semibold"
                            disabled={processing}
                        >
                            {processing ? 'Enregistrement...' : 'Prononcer la Sanction'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
import { TypeEvenementAssiduite, StatutJustification } from '@/types/discipline';
import { UserX, CheckCircle2 } from 'lucide-react';
import { formatDateForInput } from '@/lib/utils';

interface NouvelleAssiduiteModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    inscriptions: any[];
    matieres: { id: number; nom: string }[];
    periodeId: number;
}

/**
 * Modale de signalement d'une absence ou d'un retard horodaté (Gesco V1.1 - Module 12.1).
 *
 * @param {NouvelleAssiduiteModalProps} props
 * @returns {JSX.Element}
 */
export default function NouvelleAssiduiteModal({
    open,
    onOpenChange,
    inscriptions,
    matieres,
    periodeId,
}: NouvelleAssiduiteModalProps) {
    const today = formatDateForInput(new Date().toISOString());

    const { data, setData, post, processing, reset, errors, transform } = useForm({
        inscription_id: '',
        periode_id: periodeId,
        type_evenement: 'absence' as TypeEvenementAssiduite,
        date_evenement: today,
        nombre_heures: '1',
        matiere_id: '',
        statut_justification: 'non_justifiee' as StatutJustification,
        motif_justification: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            periode_id: periodeId,
            nombre_heures: Number(formValues.nombre_heures),
            matiere_id: formValues.matiere_id ? Number(formValues.matiere_id) : null,
            motif_justification: formValues.motif_justification || null,
        }));

        post('/administration/discipline/assiduites', {
            onSuccess: () => {
                toast.success('Événement d’assiduité enregistré. Synthèse du bulletin actualisée.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-emerald-700',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.error || 'Erreur lors de l’enregistrement de l’assiduité.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-md max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
                        <UserX className="w-5 h-5 text-rose-600 shrink-0" />
                        <span>Signaler une Absence ou un Retard</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Enregistrement quotidien répercuté automatiquement sur le bulletin scolaire.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Élève */}
                    <div className="space-y-1">
                        <Label htmlFor="inscr_select" className="text-xs sm:text-sm">Élève concerné <span className="text-red-500">*</span></Label>
                        <Select
                            value={data.inscription_id}
                            onValueChange={(val) => setData('inscription_id', val)}
                        >
                            <SelectTrigger id="inscr_select" className="w-full">
                                <SelectValue placeholder="Choisir un élève..." />
                            </SelectTrigger>
                            <SelectContent className="max-h-56">
                                {inscriptions.map((i) => (
                                    <SelectItem key={i.id} value={String(i.id)}>
                                        {i.eleve?.matricule} - {i.eleve?.nom} {i.eleve?.prenom} ({i.classe?.nom})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.inscription_id && <p className="text-xs text-red-500">{errors.inscription_id}</p>}
                    </div>

                    {/* Type & Date */}
                    <div className="grid grid-cols-1 min-[420px]:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="type_evenement" className="text-xs sm:text-sm">Nature de l'événement</Label>
                            <Select
                                value={data.type_evenement}
                                onValueChange={(val: TypeEvenementAssiduite) => setData('type_evenement', val)}
                            >
                                <SelectTrigger id="type_evenement" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="absence">Absence</SelectItem>
                                    <SelectItem value="retard">Retard</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="date_evenement" className="text-xs sm:text-sm">Date de l'événement <span className="text-red-500">*</span></Label>
                            <Input
                                id="date_evenement"
                                type="date"
                                value={data.date_evenement}
                                onChange={(e) => setData('date_evenement', e.target.value)}
                                required
                                className="w-full"
                            />
                        </div>
                    </div>

                    {/* Nombre d'heures & Matière */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div className="space-y-1 sm:col-span-1">
                            <Label htmlFor="nombre_heures" className="text-xs sm:text-sm">Heures (h)</Label>
                            <Input
                                id="nombre_heures"
                                type="number"
                                step="0.5"
                                min="0.5"
                                max="12"
                                value={data.nombre_heures}
                                onChange={(e) => setData('nombre_heures', e.target.value)}
                                required
                                className="w-full"
                            />
                        </div>

                        <div className="space-y-1 sm:col-span-2">
                            <Label htmlFor="matiere_select" className="text-xs sm:text-sm">Matière concernée (optionnel)</Label>
                            <Select
                                value={data.matiere_id}
                                onValueChange={(val) => setData('matiere_id', val)}
                            >
                                <SelectTrigger id="matiere_select" className="w-full">
                                    <SelectValue placeholder="Toutes ou hors cours" />
                                </SelectTrigger>
                                <SelectContent className="max-h-56">
                                    {matieres.map((m) => (
                                        <SelectItem key={m.id} value={String(m.id)}>
                                            {m.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Statut de justification */}
                    <div className="space-y-1">
                        <Label htmlFor="statut_justif" className="text-xs sm:text-sm">Statut initial</Label>
                        <Select
                            value={data.statut_justification}
                            onValueChange={(val: StatutJustification) => setData('statut_justification', val)}
                        >
                            <SelectTrigger id="statut_justif" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="non_justifiee">Non justifiée (Compte pour déchéance)</SelectItem>
                                <SelectItem value="justifiee">Justifiée (Certificat médical / Mot)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Motif éventuel */}
                    <div className="space-y-1">
                        <Label htmlFor="motif_justif" className="text-xs sm:text-sm">Motif / Justification</Label>
                        <Textarea
                            id="motif_justif"
                            rows={2}
                            placeholder="Motif fourni ou circonstances de l'absence..."
                            value={data.motif_justification}
                            onChange={(e) => setData('motif_justification', e.target.value)}
                            className="w-full text-xs sm:text-sm"
                        />
                    </div>

                    <DialogFooter className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="w-full sm:w-auto"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !data.inscription_id}
                            className="w-full sm:w-auto bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center gap-1"
                        >
                            <CheckCircle2 className="w-4 h-4" />
                            {processing ? 'Enregistrement...' : 'Valider le Signalement'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

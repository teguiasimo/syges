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
import { TypeSalle, StatutSalle } from '@/types/emploi-du-temps';
import { DoorOpen } from 'lucide-react';

interface NouvelleSalleModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissementId: number;
}

/**
 * Modale d'ajout d'une salle physique (Gesco 13.1).
 *
 * @param {NouvelleSalleModalProps} props
 * @returns {JSX.Element}
 */
export default function NouvelleSalleModal({
    open,
    onOpenChange,
    etablissementId,
}: NouvelleSalleModalProps) {
    const { data, setData, post, processing, reset, errors, transform } = useForm({
        etablissement_id: etablissementId,
        nom: '',
        code: '',
        type_salle: 'standard' as TypeSalle,
        capacite_places: '50',
        statut: 'disponible' as StatutSalle,
        description: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            etablissement_id: etablissementId,
            capacite_places: Number(formValues.capacite_places),
        }));

        post('/administration/emplois-du-temps/salles', {
            onSuccess: () => {
                toast.success('Salle physique enregistrée avec succès.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.code || err.nom || 'Erreur lors de l’enregistrement de la salle.', {
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
                        <DoorOpen className="w-5 h-5 text-indigo-600" />
                        Ajouter une Salle Pédagogique
                    </DialogTitle>
                    <DialogDescription>
                        Répertoire des locaux pour l'attribution des créneaux et le contrôle des effectifs.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3.5 py-2">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="nom" className="text-xs font-semibold">
                                Nom de la salle <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="nom"
                                placeholder="Ex: Salle 101, Labo SVT"
                                value={data.nom}
                                onChange={(e) => setData('nom', e.target.value)}
                                className="text-xs h-9"
                                required
                            />
                            {errors.nom && <p className="text-xs text-rose-500">{errors.nom}</p>}
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="code" className="text-xs font-semibold">
                                Code unique <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="code"
                                placeholder="Ex: S101, LAB-01"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                className="text-xs h-9"
                                required
                            />
                            {errors.code && <p className="text-xs text-rose-500">{errors.code}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="type_salle" className="text-xs font-semibold">
                                Type de local <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.type_salle}
                                onValueChange={(val: TypeSalle) => setData('type_salle', val)}
                            >
                                <SelectTrigger id="type_salle" className="text-xs h-9">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="standard">Salle Standard</SelectItem>
                                    <SelectItem value="laboratoire">Laboratoire Scientifique</SelectItem>
                                    <SelectItem value="informatique">Salle Informatique</SelectItem>
                                    <SelectItem value="sport">Terrain / EPS</SelectItem>
                                    <SelectItem value="polyvalente">Salle Polyvalente</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="capacite_places" className="text-xs font-semibold">
                                Capacité (places) <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="capacite_places"
                                type="number"
                                min={5}
                                max={500}
                                value={data.capacite_places}
                                onChange={(e) => setData('capacite_places', e.target.value)}
                                className="text-xs h-9"
                                required
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="description" className="text-xs font-semibold">
                            Équipements & Description
                        </Label>
                        <Textarea
                            id="description"
                            rows={2}
                            placeholder="Ex: Projecteur interactif, 30 ordinateurs, climatisation..."
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
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
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
                            disabled={processing}
                        >
                            {processing ? 'Enregistrement...' : 'Créer la Salle'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

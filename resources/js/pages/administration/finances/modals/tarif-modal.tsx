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
import { TypeFrais } from '@/types/finance';
import { formatDateForInput } from '@/lib/utils';
import { Layers, CheckCircle2 } from 'lucide-react';

interface TarifModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    niveaux: { id: number; nom: string }[];
    typesFrais: TypeFrais[];
    anneeId: number;
    etablissementId: number;
}

/**
 * Modale de paramétrage d'un tarif dans la Matrice Tarifaire (Gesco V1.1 - Module 5.2 / 11.1).
 *
 * @param {TarifModalProps} props
 * @returns {JSX.Element}
 */
export default function TarifModal({
    open,
    onOpenChange,
    niveaux,
    typesFrais,
    anneeId,
    etablissementId,
}: TarifModalProps) {
    const { data, setData, post, processing, reset, errors, transform } = useForm({
        annee_scolaire_id: anneeId,
        etablissement_id: etablissementId,
        niveau_id: '',
        serie: '',
        type_frais_id: '',
        tranche_numero: 1,
        libelle_tranche: '',
        montant: '',
        date_limite: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            annee_scolaire_id: anneeId,
            etablissement_id: etablissementId,
            serie: formValues.serie ? formValues.serie : null,
            date_limite: formValues.date_limite ? formValues.date_limite : null,
        }));

        post('/administration/finances/tarifs', {
            onSuccess: () => {
                toast.success('Ligne de tarif enregistrée avec succès dans la matrice.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.error || 'Erreur lors de l’enregistrement du tarif.', {
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
                        <Layers className="w-5 h-5 text-emerald-600 shrink-0" />
                        Ajouter un Tarif à la Matrice Scolaire
                    </DialogTitle>
                    <DialogDescription>
                        Configurez le montant et l'échéance par Niveau / Série / Tranche.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3 min-w-0">
                    {/* Niveau */}
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="tarif_niveau">Niveau Pédagogique <span className="text-red-500">*</span></Label>
                        <Select
                            value={data.niveau_id}
                            onValueChange={(val) => setData('niveau_id', val)}
                        >
                            <SelectTrigger id="tarif_niveau">
                                <SelectValue placeholder="Choisir un niveau..." />
                            </SelectTrigger>
                            <SelectContent>
                                {niveaux.map((n) => (
                                    <SelectItem key={n.id} value={String(n.id)}>
                                        {n.nom}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.niveau_id && <p className="text-xs text-red-500">{errors.niveau_id}</p>}
                    </div>

                    {/* Série (optionnelle) */}
                    <div className="space-y-1">
                        <Label htmlFor="tarif_serie">Série / Filière (optionnelle)</Label>
                        <Input
                            id="tarif_serie"
                            placeholder="Ex: C, D, A4, TI (Laisser vide pour Toutes séries)"
                            value={data.serie}
                            onChange={(e) => setData('serie', e.target.value)}
                        />
                    </div>

                    {/* Type de Frais */}
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="tarif_type">Type de Frais <span className="text-red-500">*</span></Label>
                        <Select
                            value={data.type_frais_id}
                            onValueChange={(val) => {
                                setData('type_frais_id', val);
                                const selected = typesFrais.find((tf) => String(tf.id) === val);
                                if (selected && !data.libelle_tranche) {
                                    setData((prev) => ({
                                        ...prev,
                                        type_frais_id: val,
                                        libelle_tranche: selected.libelle,
                                        montant: selected.montant_defaut > 0 ? String(selected.montant_defaut) : prev.montant,
                                    }));
                                }
                            }}
                        >
                            <SelectTrigger id="tarif_type">
                                <SelectValue placeholder="Choisir le type de frais..." />
                            </SelectTrigger>
                            <SelectContent>
                                {typesFrais.map((tf) => (
                                    <SelectItem key={tf.id} value={String(tf.id)}>
                                        {tf.libelle} ({tf.obligatoire ? 'Obligatoire' : 'Optionnel'})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.type_frais_id && <p className="text-xs text-red-500">{errors.type_frais_id}</p>}
                    </div>

                    {/* Tranche & Libellé : empilé sur mobile, 1/3 + 2/3 dès sm */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="tranche_num">Tranche N°</Label>
                            <Input
                                id="tranche_num"
                                type="number"
                                min={1}
                                max={10}
                                value={data.tranche_numero}
                                onChange={(e) => setData('tranche_numero', Number(e.target.value))}
                                required
                            />
                        </div>
                        <div className="sm:col-span-2 space-y-1 min-w-0">
                            <Label htmlFor="libelle_tranche">Libellé d'échéance</Label>
                            <Input
                                id="libelle_tranche"
                                placeholder="ex: Tranche 1 - Inscription"
                                value={data.libelle_tranche}
                                onChange={(e) => setData('libelle_tranche', e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Montant & Date limite : empilé sur mobile, 2 colonnes dès sm */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="tarif_montant">Montant FCFA <span className="text-red-500">*</span></Label>
                            <Input
                                id="tarif_montant"
                                type="number"
                                min={0}
                                placeholder="0"
                                value={data.montant}
                                onChange={(e) => setData('montant', e.target.value)}
                                required
                                className="text-right font-semibold tabular-nums"
                            />
                            {errors.montant && <p className="text-xs text-red-500">{errors.montant}</p>}
                        </div>
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="tarif_echeance">Date Limite</Label>
                            <Input
                                id="tarif_echeance"
                                type="date"
                                value={formatDateForInput(data.date_limite)}
                                onChange={(e) => setData('date_limite', e.target.value)}
                            />
                        </div>
                    </div>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !data.niveau_id || !data.type_frais_id || Number(data.montant) <= 0}
                            className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center gap-1"
                        >
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            {processing ? 'Enregistrement...' : 'Ajouter le Tarif'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

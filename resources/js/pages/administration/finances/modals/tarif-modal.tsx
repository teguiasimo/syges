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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { FraisScolaire, TypeFrais } from '@/types/finance';
import { formatDateForInput } from '@/lib/utils';
import { Layers, CheckCircle2 } from 'lucide-react';

/**
 * Propriétés de la modale de création / édition d'une ligne de la Grille Tarifaire.
 */
interface TarifModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    niveaux: { id: number; nom: string }[];
    typesFrais: TypeFrais[];
    anneeId: number;
    etablissementId: number;
    tarifToEdit?: FraisScolaire | null;
    onOpenCreateTypeFrais?: () => void;
}

/**
 * Modale de paramétrage (création ou modification) d'un tarif dans la Grille Tarifaire Scolaire.
 *
 * Centralise au même endroit le montant exigible, le numéro de tranche et sa date limite d'exigibilité,
 * éliminant toute double saisie d'échéancier séparé.
 *
 * @param {TarifModalProps} props Propriétés du composant
 * @returns {JSX.Element} Modale de gestion de tarif
 */
export default function TarifModal({
    open,
    onOpenChange,
    niveaux,
    typesFrais,
    anneeId,
    etablissementId,
    tarifToEdit = null,
    onOpenCreateTypeFrais,
}: TarifModalProps) {
    const isEditing = Boolean(tarifToEdit);

    const { data, setData, post, put, processing, reset, errors, transform } = useForm({
        annee_scolaire_id: anneeId,
        etablissement_id: etablissementId,
        niveau_id: '',
        serie: '',
        type_frais_id: '',
        tranche_numero: 1,
        ordre_priorite: '',
        libelle_tranche: '',
        montant: '',
        date_limite: '',
    });

    /**
     * Synchronise les champs du formulaire à l'ouverture en mode création ou édition.
     */
    useEffect(() => {
        if (!open) return;

        if (tarifToEdit) {
            setData({
                annee_scolaire_id: tarifToEdit.annee_scolaire_id || anneeId,
                etablissement_id: tarifToEdit.etablissement_id || etablissementId,
                niveau_id: String(tarifToEdit.niveau_id),
                serie: tarifToEdit.serie ?? '',
                type_frais_id: String(tarifToEdit.type_frais_id),
                tranche_numero: Number(tarifToEdit.tranche_numero || 1),
                ordre_priorite:
                    tarifToEdit.ordre_priorite !== null && tarifToEdit.ordre_priorite !== undefined
                        ? String(tarifToEdit.ordre_priorite)
                        : '',
                libelle_tranche: tarifToEdit.libelle_tranche ?? '',
                montant: String(Number(tarifToEdit.montant)),
                date_limite: formatDateForInput(tarifToEdit.date_limite),
            });
        } else {
            setData({
                annee_scolaire_id: anneeId,
                etablissement_id: etablissementId,
                niveau_id: '',
                serie: '',
                type_frais_id: '',
                tranche_numero: 1,
                ordre_priorite: '',
                libelle_tranche: '',
                montant: '',
                date_limite: '',
            });
        }
    }, [open, tarifToEdit, anneeId, etablissementId]);

    /**
     * Soumet le tarif au serveur (création ou mise à jour) et recalcule automatiquement les soldes élèves du niveau.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            annee_scolaire_id: anneeId,
            etablissement_id: etablissementId,
            serie: formValues.serie?.trim() ? formValues.serie.trim() : null,
            ordre_priorite: String(formValues.ordre_priorite ?? '').trim() !== ''
                ? parseInt(String(formValues.ordre_priorite), 10)
                : null,
            date_limite: formValues.date_limite ? formValues.date_limite : null,
        }));

        if (isEditing && tarifToEdit) {
            put(`/administration/finances/tarifs/${tarifToEdit.id}`, {
                onSuccess: () => {
                    toast.success('Tarif et échéance mis à jour avec succès.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    reset();
                    onOpenChange(false);
                },
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    toast.error(first || 'Erreur lors de la mise à jour du tarif.', {
                        position: 'top-right',
                    });
                },
            });
            return;
        }

        post('/administration/finances/tarifs', {
            onSuccess: () => {
                toast.success('Ligne de tarif enregistrée avec succès dans la grille.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                const first = Object.values(err)[0] as string;
                toast.error(first || 'Erreur lors de l’enregistrement du tarif.', {
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
                        {isEditing ? 'Modifier le Tarif Scolaire' : 'Ajouter un Tarif à la Grille Scolaire'}
                    </DialogTitle>
                    <DialogDescription>
                        Définissez le montant par niveau, la tranche et sa date limite d'exigibilité.
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

                    {/* Type de Frais */}
                    <div className="space-y-1 min-w-0">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="tarif_type">Type de Frais <span className="text-red-500">*</span></Label>
                            {onOpenCreateTypeFrais && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        onOpenChange(false);
                                        onOpenCreateTypeFrais();
                                    }}
                                    className="text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-semibold cursor-pointer"
                                >
                                    + Nouveau Type
                                </button>
                            )}
                        </div>

                        {typesFrais.length === 0 ? (
                            <div className="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 rounded-lg text-xs text-amber-800 dark:text-amber-200 space-y-2">
                                <p>Aucun type de frais configuré (ex: Scolarité, Inscription, APEE).</p>
                                {onOpenCreateTypeFrais && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-7 text-xs border-amber-300 text-amber-900 dark:text-amber-100 hover:bg-amber-100"
                                        onClick={() => {
                                            onOpenChange(false);
                                            onOpenCreateTypeFrais();
                                        }}
                                    >
                                        Créer un Type de Frais
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <Select
                                value={data.type_frais_id}
                                onValueChange={(val) => {
                                    setData('type_frais_id', val);
                                    const selected = typesFrais.find((tf) => String(tf.id) === val);
                                    if (selected && !isEditing && !data.libelle_tranche) {
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
                        )}
                        {errors.type_frais_id && <p className="text-xs text-red-500">{errors.type_frais_id}</p>}
                    </div>

                    {/* Tranche & Libellé */}
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
                            <Label htmlFor="libelle_tranche">Intitulé de la tranche</Label>
                            <Input
                                id="libelle_tranche"
                                placeholder="ex: Inscription ou 1ère Tranche"
                                value={data.libelle_tranche}
                                onChange={(e) => setData('libelle_tranche', e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Montant & Date limite */}
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
                            <Label htmlFor="tarif_echeance">Date Limite d'Échéance</Label>
                            <Input
                                id="tarif_echeance"
                                type="date"
                                value={formatDateForInput(data.date_limite)}
                                onChange={(e) => setData('date_limite', e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Série & Priorité optionnelles */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="tarif_priorite" className="text-xs text-slate-600 dark:text-slate-400">
                                Priorité sur cette ligne (optionnel)
                            </Label>
                            <Input
                                id="tarif_priorite"
                                type="number"
                                min={1}
                                max={99}
                                placeholder={
                                    (() => {
                                        const sel = typesFrais.find((tf) => String(tf.id) === data.type_frais_id);
                                        return sel?.ordre_priorite
                                            ? `Auto : Prio #${sel.ordre_priorite}`
                                            : 'Héritée du Type';
                                    })()
                                }
                                value={data.ordre_priorite}
                                onChange={(e) => setData('ordre_priorite', e.target.value)}
                                className="h-8 text-xs tabular-nums"
                            />
                        </div>
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="tarif_serie" className="text-xs text-slate-600 dark:text-slate-400">
                                Série spécifique (optionnel)
                            </Label>
                            <Input
                                id="tarif_serie"
                                placeholder="Toutes séries (ex: C, D, A4)"
                                value={data.serie}
                                onChange={(e) => setData('serie', e.target.value)}
                                className="h-8 text-xs"
                            />
                        </div>
                    </div>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !data.niveau_id || !data.type_frais_id || Number(data.montant) < 0}
                            className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center gap-1"
                        >
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            {processing ? 'Enregistrement...' : isEditing ? 'Enregistrer les modifications' : 'Ajouter le Tarif'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { TypeFrais, PeriodiciteFrais } from '@/types/finance';
import { Tag, CheckCircle2 } from 'lucide-react';

interface TypeFraisModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissementId: number;
    typeFraisToEdit?: TypeFrais | null;
}

/**
 * Modale de création et de configuration d'un Type de Frais (Nomenclature financière).
 *
 * Permet de définir les catégories de frais de l'établissement :
 * - Frais obligatoires (ex: Inscription, Scolarité, APEE)
 * - Frais optionnels (ex: Transport scolaire/Bus, Cantine/Demi-pension, Uniformes)
 *
 * @param {TypeFraisModalProps} props - Propriétés du composant modale
 * @returns {JSX.Element}
 */
export default function TypeFraisModal({
    open,
    onOpenChange,
    etablissementId,
    typeFraisToEdit,
}: TypeFraisModalProps) {
    const { data, setData, post, processing, reset, errors, transform } = useForm({
        etablissement_id: etablissementId,
        code: '',
        libelle: '',
        description: '',
        obligatoire: true,
        periodicite: 'annuel' as PeriodiciteFrais,
        montant_defaut: '',
    });

    // Synchronisation lors de l'édition ou de la réinitialisation
    useEffect(() => {
        if (typeFraisToEdit) {
            setData({
                etablissement_id: typeFraisToEdit.etablissement_id,
                code: typeFraisToEdit.code,
                libelle: typeFraisToEdit.libelle,
                description: typeFraisToEdit.description || '',
                obligatoire: Boolean(typeFraisToEdit.obligatoire),
                periodicite: typeFraisToEdit.periodicite,
                montant_defaut: typeFraisToEdit.montant_defaut ? String(typeFraisToEdit.montant_defaut) : '',
            });
        } else {
            setData({
                etablissement_id: etablissementId,
                code: '',
                libelle: '',
                description: '',
                obligatoire: true,
                periodicite: 'annuel',
                montant_defaut: '',
            });
        }
    }, [typeFraisToEdit, etablissementId, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            etablissement_id: etablissementId,
            code: formValues.code.trim().toUpperCase(),
            montant_defaut: formValues.montant_defaut ? parseFloat(String(formValues.montant_defaut)) : 0,
        }));

        post('/administration/finances/types-frais', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Type de frais enregistré avec succès dans la nomenclature.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.error || 'Erreur lors de l’enregistrement du type de frais.', {
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
                        <Tag className="w-5 h-5 text-emerald-600 shrink-0" />
                        {typeFraisToEdit ? 'Modifier le Type de Frais' : 'Nouveau Type de Frais'}
                    </DialogTitle>
                    <DialogDescription>
                        Configurez la nature du frais (scolarité, inscription, cantine, transport, etc.).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 min-w-0">
                    {/* Code technique */}
                    <div className="space-y-1">
                        <Label htmlFor="type_code">Code de Référence <span className="text-red-500">*</span></Label>
                        <Input
                            id="type_code"
                            placeholder="Ex: SCOLARITE, INSCRIPTION, BUS, CANTINE"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.toUpperCase())}
                            disabled={Boolean(typeFraisToEdit)}
                            className="font-mono uppercase text-xs"
                            required
                        />
                        <p className="text-[11px] text-slate-500">Identifiant unique en lettres majuscules sans espace.</p>
                        {errors.code && <p className="text-xs text-red-500">{errors.code}</p>}
                    </div>

                    {/* Libellé complet */}
                    <div className="space-y-1">
                        <Label htmlFor="type_libelle">Libellé d'Affichage <span className="text-red-500">*</span></Label>
                        <Input
                            id="type_libelle"
                            placeholder="Ex: Frais de Scolarité / Pension"
                            value={data.libelle}
                            onChange={(e) => setData('libelle', e.target.value)}
                            required
                        />
                        {errors.libelle && <p className="text-xs text-red-500">{errors.libelle}</p>}
                    </div>

                    {/* Obligatoire ou Optionnel */}
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="type_obligatoire">Caractère d'Exigibilité <span className="text-red-500">*</span></Label>
                        <Select
                            value={data.obligatoire ? 'true' : 'false'}
                            onValueChange={(val) => setData('obligatoire', val === 'true')}
                        >
                            <SelectTrigger id="type_obligatoire">
                                <SelectValue placeholder="Choisir le caractère..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="true">Obligatoire (Appliqué d'office à tous les élèves)</SelectItem>
                                <SelectItem value="false">Optionnel (Frais à la carte : Bus, Cantine, Activités...)</SelectItem>
                            </SelectContent>
                        </Select>
                        <p className="text-[11px] text-slate-500">
                            {data.obligatoire
                                ? 'Les élèves inscrits dans le niveau hériteront automatiquement de ce frais.'
                                : 'Souscription volontaire au guichet via le dossier financier de l’élève.'}
                        </p>
                    </div>

                    {/* Périodicité */}
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="type_periodicite">Périodicité d'Exigibilité <span className="text-red-500">*</span></Label>
                        <Select
                            value={data.periodicite}
                            onValueChange={(val: PeriodiciteFrais) => setData('periodicite', val)}
                        >
                            <SelectTrigger id="type_periodicite">
                                <SelectValue placeholder="Choisir la périodicité..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="annuel">Annuel (ex: Scolarité, Inscription, APEE)</SelectItem>
                                <SelectItem value="trimestriel">Trimestriel (ex: Examens blancs, Activités)</SelectItem>
                                <SelectItem value="mensuel">Mensuel (ex: Transport bus, Cantine)</SelectItem>
                                <SelectItem value="ponctuel">Ponctuel (ex: Tenue de sport, Carnet de correspondance)</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.periodicite && <p className="text-xs text-red-500">{errors.periodicite}</p>}
                    </div>

                    {/* Montant indicatif par défaut */}
                    <div className="space-y-1">
                        <Label htmlFor="type_montant">Montant Indicatif / Défaut (FCFA)</Label>
                        <Input
                            id="type_montant"
                            type="number"
                            min="0"
                            step="100"
                            placeholder="Ex: 50000"
                            value={data.montant_defaut}
                            onChange={(e) => setData('montant_defaut', e.target.value)}
                        />
                        <p className="text-[11px] text-slate-500">Sera proposé automatiquement lors du paramétrage par niveau.</p>
                        {errors.montant_defaut && <p className="text-xs text-red-500">{errors.montant_defaut}</p>}
                    </div>

                    {/* Description optionnelle */}
                    <div className="space-y-1">
                        <Label htmlFor="type_description">Description / Remarques (Optionnel)</Label>
                        <Textarea
                            id="type_description"
                            placeholder="Précisions sur les modalités ou conditions..."
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={2}
                        />
                    </div>

                    <DialogFooter className="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold flex items-center gap-1.5"
                        >
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            {processing ? 'Enregistrement...' : 'Enregistrer le Type de Frais'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

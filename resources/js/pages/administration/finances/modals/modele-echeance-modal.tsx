import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { formatDateForInput } from '@/lib/utils';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ModeleEcheanceModalProps {
    isOpen: boolean;
    onClose: () => void;
    etablissementId: number;
    anneeId: number;
}

interface TrancheDraft {
    tranche_numero: number;
    libelle: string;
    date_limite: string;
    pourcentage_exigible: number;
}

/**
 * Modal de création d'un modèle d'échéancier calendaire de paiement (Aurore §3.24).
 * Permet de définir les tranches et dates butoirs de versement.
 *
 * @param {ModeleEcheanceModalProps} props
 * @returns {JSX.Element}
 */
export default function ModeleEcheanceModal({
    isOpen,
    onClose,
    etablissementId,
    anneeId,
}: ModeleEcheanceModalProps) {
    const [nom, setNom] = useState('Échéancier Standard Trimestriel');
    const [description, setDescription] = useState('Calendrier officiel des 3 tranches de scolarité');
    const [isDefault, setIsDefault] = useState(true);

    const [tranches, setTranches] = useState<TrancheDraft[]>([
        { tranche_numero: 1, libelle: 'Tranche 1 (Rentrée)', date_limite: formatDateForInput('2026-10-15'), pourcentage_exigible: 40 },
        { tranche_numero: 2, libelle: 'Tranche 2 (Mi-année)', date_limite: formatDateForInput('2027-01-15'), pourcentage_exigible: 75 },
        { tranche_numero: 3, libelle: 'Tranche 3 (Solde final)', date_limite: formatDateForInput('2027-04-15'), pourcentage_exigible: 100 },
    ]);

    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleAddTranche = () => {
        const nextNum = tranches.length + 1;
        setTranches([
            ...tranches,
            {
                tranche_numero: nextNum,
                libelle: `Tranche ${nextNum}`,
                date_limite: formatDateForInput(new Date()),
                pourcentage_exigible: 100,
            },
        ]);
    };

    const handleRemoveTranche = (index: number) => {
        if (tranches.length <= 1) {
            toast.error('Un échéancier doit comporter au moins une tranche.');
            return;
        }
        setTranches(tranches.filter((_, idx) => idx !== index));
    };

    const handleTrancheChange = (index: number, field: keyof TrancheDraft, value: any) => {
        const updated = [...tranches];
        updated[index] = { ...updated[index], [field]: value };
        setTranches(updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!nom.trim()) {
            toast.error("Veuillez renseigner l'intitulé de l'échéancier.");
            return;
        }

        setIsSubmitting(true);

        router.post(
            '/administration/finances/modeles-echeances',
            {
                etablissement_id: etablissementId,
                annee_scolaire_id: anneeId,
                nom: nom.trim(),
                description: description.trim() || null,
                is_default: isDefault,
                tranches: tranches as any,
            },
            {
                onSuccess: () => {
                    toast.success("Modèle d'échéancier enregistré avec succès.");
                    onClose();
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Erreur lors de la création de l'échéancier.");
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Créer un Modèle d'Échéancier Calendaire</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="nom">Nom du modèle d'échéancier *</Label>
                        <Input
                            id="nom"
                            value={nom}
                            onChange={e => setNom(e.target.value)}
                            placeholder="ex: Échéancier Standard Secondaire 2026-2027"
                            required
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                            placeholder="Remarques sur le calendrier des paiements..."
                        />
                    </div>

                    <div className="border rounded-lg p-3 bg-slate-50 space-y-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold text-slate-800">Tranches d'échéances et dates butoirs</span>
                            <Button type="button" size="sm" variant="outline" onClick={handleAddTranche} className="h-8 gap-1">
                                <Plus className="w-3.5 h-3.5" />
                                Ajouter une tranche
                            </Button>
                        </div>

                        <div className="space-y-2">
                            {tranches.map((t, idx) => (
                                <div key={idx} className="grid grid-cols-12 gap-2 items-center bg-white p-2 rounded border border-slate-200">
                                    <div className="col-span-1 text-xs font-bold text-center text-slate-600">
                                        T{t.tranche_numero}
                                    </div>
                                    <div className="col-span-4">
                                        <Input
                                            value={t.libelle}
                                            onChange={e => handleTrancheChange(idx, 'libelle', e.target.value)}
                                            placeholder="Libellé tranche"
                                            className="h-8 text-xs"
                                            required
                                        />
                                    </div>
                                    <div className="col-span-4">
                                        <Input
                                            type="date"
                                            value={formatDateForInput(t.date_limite)}
                                            onChange={e => handleTrancheChange(idx, 'date_limite', e.target.value)}
                                            className="h-8 text-xs"
                                            required
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <div className="flex items-center gap-1">
                                            <Input
                                                type="number"
                                                min="1"
                                                max="100"
                                                value={t.pourcentage_exigible}
                                                onChange={e => handleTrancheChange(idx, 'pourcentage_exigible', parseFloat(e.target.value) || 0)}
                                                className="h-8 text-xs"
                                                required
                                            />
                                            <span className="text-xs text-slate-500">%</span>
                                        </div>
                                    </div>
                                    <div className="col-span-1 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleRemoveTranche(idx)}
                                            className="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                        >
                                            <Trash2 className="w-3.5 h-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <label className="flex items-center space-x-2 text-sm cursor-pointer pt-1">
                        <input
                            type="checkbox"
                            checked={isDefault}
                            onChange={e => setIsDefault(e.target.checked)}
                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span className="font-medium">Définir comme échéancier par défaut pour cet établissement</span>
                    </label>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isSubmitting} className="bg-blue-600 hover:bg-blue-700 text-white">
                            {isSubmitting ? 'Enregistrement...' : "Enregistrer l'échéancier"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { BourseRemise, CompteFinancierEleve } from '@/types/finance';

interface AttribuerBourseModalProps {
    isOpen: boolean;
    onClose: () => void;
    compte: CompteFinancierEleve | null;
    boursesRemises: BourseRemise[];
}

/**
 * Modal d'attribution directe d'une bourse ou d'une remise au compte scolaire d'un élève (Aurore §4.1.6).
 *
 * @param {AttribuerBourseModalProps} props
 * @returns {JSX.Element}
 */
export default function AttribuerBourseModal({
    isOpen,
    onClose,
    compte,
    boursesRemises,
}: AttribuerBourseModalProps) {
    const [bourseRemiseId, setBourseRemiseId] = useState<string>('');
    const [motif, setMotif] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const selectedModel = boursesRemises.find(b => b.id.toString() === bourseRemiseId);

    // Calcul de l'estimation de la déduction
    const montantEstime = React.useMemo(() => {
        if (!selectedModel || !compte) return 0;
        if (selectedModel.mode_calcul === 'pourcentage') {
            return Math.round(((compte.debit_initial || 0) * selectedModel.valeur) / 100);
        }
        return Math.min(compte.debit_initial || 0, selectedModel.valeur);
    }, [selectedModel, compte]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!compte || !bourseRemiseId) {
            toast.error('Veuillez sélectionner un modèle de bourse ou de remise.');
            return;
        }

        setIsSubmitting(true);

        router.post(
            '/administration/finances/bourses-remises/attribuer',
            {
                compte_financier_id: compte.id,
                bourse_remise_id: parseInt(bourseRemiseId, 10),
                motif: motif.trim() || null,
            },
            {
                onSuccess: () => {
                    toast.success('Bourse/Remise accordée avec succès.');
                    onClose();
                    setBourseRemiseId('');
                    setMotif('');
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Erreur lors de l'attribution.");
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    if (!compte) return null;

    return (
        <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Accorder une Bourse ou une Remise</DialogTitle>
                </DialogHeader>

                {/* Résumé du compte financier avec retour à la ligne adaptatif sur mobile */}
                <div className="bg-slate-50 dark:bg-slate-900/60 p-3 rounded-lg border border-slate-200 dark:border-slate-800 text-sm space-y-1 my-1 min-w-0">
                    <div className="wrap-break-word">Élève : <strong className="text-slate-900 dark:text-slate-100">{compte.eleve?.nom} {compte.eleve?.prenom}</strong></div>
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs sm:text-sm">
                        <span>Matricule : <span className="font-mono">{compte.eleve?.matricule}</span></span>
                        <span className="hidden sm:inline text-slate-400">|</span>
                        <span>Classe : <strong>{compte.inscription?.classe?.nom}</strong></span>
                    </div>
                    <div>Frais initiaux bruts : <strong className="text-blue-700 dark:text-blue-400 tabular-nums">{new Intl.NumberFormat('fr-FR').format(compte.debit_initial)} FCFA</strong></div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 py-2 min-w-0">
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="bourseRemise">Sélectionner le modèle *</Label>
                        <Select value={bourseRemiseId} onValueChange={val => setBourseRemiseId(val)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Choisir une bourse ou remise..." />
                            </SelectTrigger>
                            <SelectContent>
                                {boursesRemises
                                    .filter(b => b.is_active)
                                    .map(b => (
                                        <SelectItem key={b.id} value={b.id.toString()}>
                                            {b.type === 'bourse' ? '🎓 [Bourse]' : '🏷️ [Remise]'} {b.nom} ({b.mode_calcul === 'pourcentage' ? `${b.valeur}%` : `${new Intl.NumberFormat('fr-FR').format(b.valeur)} FCFA`})
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {selectedModel && (
                        <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-lg text-sm text-emerald-900 dark:text-emerald-200">
                            <div className="font-semibold flex flex-wrap items-center justify-between gap-2">
                                <span>Déduction calculée :</span>
                                <span className="text-base text-emerald-700 dark:text-emerald-400 font-bold tabular-nums">
                                    - {new Intl.NumberFormat('fr-FR').format(montantEstime)} FCFA
                                </span>
                            </div>
                            <div className="text-xs text-emerald-800 dark:text-emerald-300 mt-1 tabular-nums">
                                Nouveau net exigible : {new Intl.NumberFormat('fr-FR').format(Math.max(0, (compte.debit_initial || 0) + (compte.arrieres_anterieurs || 0) - montantEstime))} FCFA
                            </div>
                        </div>
                    )}

                    <div className="space-y-1">
                        <Label htmlFor="motif">Motif / Justification</Label>
                        <Input
                            id="motif"
                            placeholder="ex: 3e enfant scolarisé dans l'établissement (Dossier vérifié)"
                            value={motif}
                            onChange={e => setMotif(e.target.value)}
                        />
                    </div>

                    {/* Boutons pleine largeur sur mobile avec retour à la ligne autorisé sur petits écrans (320px) */}
                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={isSubmitting || !bourseRemiseId}
                            className="w-full sm:w-auto whitespace-normal h-auto py-2 bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            {isSubmitting ? 'Validation...' : 'Valider & Recalculer le compte'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

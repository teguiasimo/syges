import { useForm } from '@inertiajs/react';
import { AlertCircle, ArrowRight, Check, Copy, Loader2, Sparkles } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface DupliquerAnneeModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    annees: Array<{ id: number; nom: string; code: string; statut: string; is_current: boolean }>;
}

/**
 * Assistant de Reconduction & Duplication Annuelle en 1 clic (Aurore §3.16).
 */
export function DupliquerAnneeModal({ open, onOpenChange, annees }: DupliquerAnneeModalProps) {
    const currentYear = annees.find((a) => a.is_current) || annees[0];

    const { data, setData, post, processing, errors, reset } = useForm({
        source_annee_id: currentYear ? String(currentYear.id) : '',
        cible_annee_id: '',
        options: {
            periodes: true,
            frais_scolaires: true,
            modeles_echeances: true,
            bourses_remises: true,
            attributions: false,
        },
    });

    const handleOptionToggle = (key: keyof typeof data.options) => {
        setData('options', {
            ...data.options,
            [key]: !data.options[key],
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.cible_annee_id) {
            toast.error('Veuillez sélectionner l\'année scolaire cible', {
                position: 'top-right',
            });
            return;
        }

        if (data.source_annee_id === data.cible_annee_id) {
            toast.error('L\'année source et l\'année cible doivent être différentes', {
                position: 'top-right',
            });
            return;
        }

        post('/administration/archivage/dupliquer-annee', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Configuration annuelle reconduite avec succès !', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white font-medium border-emerald-700 shadow-lg',
                });
                reset();
                onOpenChange(false);
            },
            onError: () => {
                toast.error('Une erreur est survenue lors de la duplication', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-teal-600 dark:text-teal-400">
                        <Sparkles className="w-5 h-5 shrink-0" />
                        <DialogTitle className="text-base sm:text-lg">Assistant de Reconduction Annuelle (1-Clic)</DialogTitle>
                    </div>
                    <DialogDescription className="text-xs sm:text-sm">
                        Clonez automatiquement les paramètres d'une année précédente vers une nouvelle année pour préparer la rentrée scolaire sans ressaisie.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Année source -> Année cible */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center bg-slate-50 dark:bg-slate-900/50 p-3 rounded-lg border">
                        <div className="space-y-1.5">
                            <Label htmlFor="source" className="text-xs font-semibold">
                                Année d'origine (Source)
                            </Label>
                            <Select
                                value={data.source_annee_id}
                                onValueChange={(val) => setData('source_annee_id', val)}
                            >
                                <SelectTrigger id="source" className="w-full">
                                    <SelectValue placeholder="Année source..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {annees.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.nom} {a.is_current ? '(En cours)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.source_annee_id && (
                                <p className="text-xs text-rose-500">{errors.source_annee_id}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="cible" className="text-xs font-semibold text-teal-600 dark:text-teal-400">
                                Nouvelle Année (Cible)
                            </Label>
                            <Select
                                value={data.cible_annee_id}
                                onValueChange={(val) => setData('cible_annee_id', val)}
                            >
                                <SelectTrigger id="cible" className="w-full border-teal-300">
                                    <SelectValue placeholder="Choisir la nouvelle année..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {annees
                                        .filter((a) => String(a.id) !== data.source_annee_id)
                                        .map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>
                                                {a.nom} ({a.code})
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {errors.cible_annee_id && (
                                <p className="text-xs text-rose-500">{errors.cible_annee_id}</p>
                            )}
                        </div>
                    </div>

                    {/* Options de duplication */}
                    <div className="space-y-3">
                        <Label className="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Sélectionnez les éléments à dupliquer
                        </Label>

                        <div className="space-y-2.5 rounded-md border p-3 bg-white dark:bg-slate-950">
                            <label className="flex items-start gap-3 cursor-pointer">
                                <Checkbox
                                    checked={data.options.periodes}
                                    onCheckedChange={() => handleOptionToggle('periodes')}
                                    className="mt-0.5"
                                />
                                <div>
                                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                        Périodes scolaires & Séquences
                                    </div>
                                    <p className="text-[11px] text-slate-500">
                                        Trimestres / semestres et découpage séquentiel d'évaluation avec leurs coefficients.
                                    </p>
                                </div>
                            </label>

                            <label className="flex items-start gap-3 cursor-pointer">
                                <Checkbox
                                    checked={data.options.frais_scolaires}
                                    onCheckedChange={() => handleOptionToggle('frais_scolaires')}
                                    className="mt-0.5"
                                />
                                <div>
                                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                        Grille tarifaire des frais de scolarité
                                    </div>
                                    <p className="text-[11px] text-slate-500">
                                        Montants exigibles par niveau, classe, série et tranches de paiement.
                                    </p>
                                </div>
                            </label>

                            <label className="flex items-start gap-3 cursor-pointer">
                                <Checkbox
                                    checked={data.options.modeles_echeances}
                                    onCheckedChange={() => handleOptionToggle('modeles_echeances')}
                                    className="mt-0.5"
                                />
                                <div>
                                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                        Modèles d'échéances et tranches de règlement
                                    </div>
                                    <p className="text-[11px] text-slate-500">
                                        Calendrier des échéances et pourcentages d'exigibilité.
                                    </p>
                                </div>
                            </label>

                            <label className="flex items-start gap-3 cursor-pointer">
                                <Checkbox
                                    checked={data.options.bourses_remises}
                                    onCheckedChange={() => handleOptionToggle('bourses_remises')}
                                    className="mt-0.5"
                                />
                                <div>
                                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                        Modèles de bourses et remises
                                    </div>
                                    <p className="text-[11px] text-slate-500">
                                        Bourses d'excellence, remises fratrie, réductions enfants du personnel.
                                    </p>
                                </div>
                            </label>

                            <label className="flex items-start gap-3 cursor-pointer">
                                <Checkbox
                                    checked={data.options.attributions}
                                    onCheckedChange={() => handleOptionToggle('attributions')}
                                    className="mt-0.5"
                                />
                                <div>
                                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                        Attributions pédagogiques des enseignants
                                    </div>
                                    <p className="text-[11px] text-slate-500">
                                        Reconduit les affectations des mêmes professeurs aux mêmes classes et cours.
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div className="rounded-md bg-blue-50 dark:bg-blue-950/40 p-2.5 border border-blue-200 dark:border-blue-900 flex items-center gap-2 text-xs text-blue-700 dark:text-blue-300">
                        <AlertCircle className="w-4 h-4 shrink-0" />
                        <span>Les inscriptions et les notes ne sont pas dupliquées. Seules les structures administratives sont clonées.</span>
                    </div>

                    <DialogFooter className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                            className="w-full sm:w-auto"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            className="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white gap-1.5"
                            disabled={processing}
                        >
                            {processing ? (
                                <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                                <Copy className="w-4 h-4" />
                            )}
                            Exécuter la duplication
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

import { useForm } from '@inertiajs/react';
import { Binary, Loader2, Save, Sliders } from 'lucide-react';
import React, { useEffect } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ConfigurationNumerotation } from '@/types/archivage';

interface ConfigurationNumerotationModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    configuration: ConfigurationNumerotation | null;
}

/**
 * Modal d'édition des masques et souches de numérotation (Aurore §3.15).
 */
export function ConfigurationNumerotationModal({
    open,
    onOpenChange,
    configuration,
}: ConfigurationNumerotationModalProps) {
    if (!configuration) return null;

    const { data, setData, put, processing, errors } = useForm({
        prefixe: configuration.prefixe,
        format_masque: configuration.format_masque,
        compteur_actuel: configuration.compteur_actuel,
        reinitialiser_annuellement: configuration.reinitialiser_annuellement,
    });

    useEffect(() => {
        if (configuration) {
            setData({
                prefixe: configuration.prefixe,
                format_masque: configuration.format_masque,
                compteur_actuel: configuration.compteur_actuel,
                reinitialiser_annuellement: configuration.reinitialiser_annuellement,
            });
        }
    }, [configuration, open]);

    const previewNumber = () => {
        let result = data.format_masque.replace('{PREFIX}', data.prefixe);
        result = result.replace('{ANNEE}', String(new Date().getFullYear()));
        if (/\{NUM:(\d+)\}/.test(result)) {
            result = result.replace(/\{NUM:(\d+)\}/, (match, p1) => {
                return String(data.compteur_actuel).padStart(parseInt(p1, 10), '0');
            });
        } else {
            result = result.replace('{NUM}', String(data.compteur_actuel));
        }
        return result;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        put(`/administration/archivage/numerotation/${configuration.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Masque de numérotation mis à jour avec succès', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white font-medium border-emerald-700 shadow-lg',
                });
                onOpenChange(false);
            },
            onError: () => {
                toast.error('Erreur lors de la mise à jour de la configuration', {
                    position: 'top-right',
                });
            },
        });
    };

    const getTypeLabel = (type: string) => {
        switch (type) {
            case 'matricule_eleve':
                return 'Matricules Élèves';
            case 'matricule_personnel':
                return 'Matricules Enseignants & Personnel';
            case 'recu_scolarite':
                return 'Reçus de Paiement Scolarité';
            case 'recu_divers':
                return 'Reçus de Recettes Diverses';
            case 'certificat_scolarite':
                return 'Certificats de Scolarité Officiels';
            case 'certificat_radiation':
                return 'Certificats de Radiation / Sortie';
            default:
                return type;
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-teal-600 dark:text-teal-400">
                        <Binary className="w-5 h-5 shrink-0" />
                        <DialogTitle className="text-base sm:text-lg">Configurer la numérotation : {getTypeLabel(configuration.type_document)}</DialogTitle>
                    </div>
                    <DialogDescription className="text-xs sm:text-sm">
                        Personnalisez le préfixe radical, la souche et le format séquentiel automatique.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Prévisualisation dynamique */}
                    <div className="rounded-lg border bg-teal-50/60 dark:bg-teal-950/40 p-3 text-center border-teal-200 dark:border-teal-900">
                        <span className="text-[11px] font-bold text-teal-700 dark:text-teal-300 uppercase tracking-wider block">
                            Aperçu du prochain numéro généré
                        </span>
                        <div className="text-lg sm:text-xl font-black font-mono tracking-wider text-teal-900 dark:text-teal-100 mt-1">
                            {previewNumber()}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="prefixe" className="text-xs font-semibold">
                                Préfixe / Radical (ex: GS, REC)
                            </Label>
                            <Input
                                id="prefixe"
                                value={data.prefixe}
                                onChange={(e) => setData('prefixe', e.target.value.toUpperCase())}
                                required
                            />
                            {errors.prefixe && (
                                <p className="text-xs text-rose-500">{errors.prefixe}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="compteur_actuel" className="text-xs font-semibold">
                                Prochain index séquentiel
                            </Label>
                            <Input
                                id="compteur_actuel"
                                type="number"
                                min="1"
                                value={data.compteur_actuel}
                                onChange={(e) => setData('compteur_actuel', parseInt(e.target.value, 10) || 1)}
                                required
                            />
                            {errors.compteur_actuel && (
                                <p className="text-xs text-rose-500">{errors.compteur_actuel}</p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="format_masque" className="text-xs font-semibold">
                            Format du masque séquentiel
                        </Label>
                        <Input
                            id="format_masque"
                            value={data.format_masque}
                            onChange={(e) => setData('format_masque', e.target.value)}
                            required
                        />
                        <p className="text-[11px] text-slate-500">
                            Variables disponibles : <code className="text-teal-600 font-mono">{"{PREFIX}"}</code>, <code className="text-teal-600 font-mono">{"{ANNEE}"}</code>, <code className="text-teal-600 font-mono">{"{NUM:4}"}</code> (0001) ou <code className="text-teal-600 font-mono">{"{NUM:5}"}</code> (00001).
                        </p>
                        {errors.format_masque && (
                            <p className="text-xs text-rose-500">{errors.format_masque}</p>
                        )}
                    </div>

                    <div className="flex items-center justify-between border rounded-lg p-3">
                        <div className="space-y-0.5">
                            <Label htmlFor="reinit" className="text-xs font-semibold cursor-pointer">
                                Réinitialiser le compteur chaque nouvelle année
                            </Label>
                            <p className="text-[11px] text-slate-500">
                                Recommence à 1 au changement de millésime (ex: 2026 vers 2027)
                            </p>
                        </div>
                        {/* Case à cocher pour la réinitialisation annuelle du compteur */}
                        <Checkbox
                            id="reinit"
                            checked={data.reinitialiser_annuellement}
                            onCheckedChange={(checked) => setData('reinitialiser_annuellement', Boolean(checked))}
                        />
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
                                <Save className="w-4 h-4" />
                            )}
                            Enregistrer le masque
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

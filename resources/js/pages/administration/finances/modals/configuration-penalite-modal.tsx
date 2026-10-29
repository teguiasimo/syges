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
import { PenaliteConfiguration } from '@/types/finance';

interface ConfigurationPenaliteModalProps {
    isOpen: boolean;
    onClose: () => void;
    etablissementId: number;
    config?: PenaliteConfiguration | null;
}

/**
 * Modal de paramétrage des pénalités financières de retard (Aurore §3.17).
 *
 * @param {ConfigurationPenaliteModalProps} props
 * @returns {JSX.Element}
 */
export default function ConfigurationPenaliteModal({
    isOpen,
    onClose,
    etablissementId,
    config,
}: ConfigurationPenaliteModalProps) {
    const [nom, setNom] = useState(config?.nom || 'Pénalité standard pour retard de tranche');
    const [modeCalcul, setModeCalcul] = useState<'montant_fixe' | 'pourcentage'>(
        config?.mode_calcul || 'montant_fixe'
    );
    const [valeur, setValeur] = useState(config?.valeur?.toString() || '2000');
    const [joursGrace, setJoursGrace] = useState(config?.jours_grace?.toString() || '5');
    const [isActive, setIsActive] = useState(config?.is_active ?? true);
    const [isSubmitting, setIsSubmitting] = useState(false);

    React.useEffect(() => {
        if (config) {
            setNom(config.nom);
            setModeCalcul(config.mode_calcul);
            setValeur(config.valeur.toString());
            setJoursGrace(config.jours_grace.toString());
            setIsActive(config.is_active);
        }
    }, [config, isOpen]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const valNum = parseFloat(valeur);
        const joursNum = parseInt(joursGrace, 10);

        if (!nom.trim() || isNaN(valNum) || valNum <= 0 || isNaN(joursNum) || joursNum < 0) {
            toast.error('Veuillez renseigner des paramètres de calcul valides.');
            return;
        }

        setIsSubmitting(true);

        router.post(
            '/administration/finances/penalites-configurations',
            {
                etablissement_id: etablissementId,
                nom: nom.trim(),
                mode_calcul: modeCalcul,
                valeur: valNum,
                jours_grace: joursNum,
                is_active: isActive,
            },
            {
                onSuccess: () => {
                    toast.success('Règle de pénalité de retard enregistrée avec succès.');
                    onClose();
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Erreur lors de l'enregistrement de la pénalité.");
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Paramétrage des Pénalités de Retard</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="nom">Intitulé de la règle *</Label>
                        <Input
                            id="nom"
                            value={nom}
                            onChange={e => setNom(e.target.value)}
                            placeholder="ex: Majoration pour retard de paiement"
                            required
                        />
                    </div>

                    {/* Grille responsive : 1 colonne sur mobile, 2 colonnes dès sm */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="modeCalcul">Mode d'application *</Label>
                            <Select value={modeCalcul} onValueChange={(val: 'montant_fixe' | 'pourcentage') => setModeCalcul(val)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="montant_fixe">Montant forfaitaire (FCFA)</SelectItem>
                                    <SelectItem value="pourcentage">Pourcentage du reste dû (%)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="valeur">
                                Valeur {modeCalcul === 'pourcentage' ? '(%)' : '(FCFA)'} *
                            </Label>
                            <Input
                                id="valeur"
                                type="number"
                                min="1"
                                value={valeur}
                                onChange={e => setValeur(e.target.value)}
                                required
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="joursGrace">Jours de grâce (Tolérance après la date limite) *</Label>
                        <Input
                            id="joursGrace"
                            type="number"
                            min="0"
                            placeholder="ex: 5 jours de grâce"
                            value={joursGrace}
                            onChange={e => setJoursGrace(e.target.value)}
                            required
                        />
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Nombre de jours après l'échéance calendaire avant que la pénalité ne devienne exigible.
                        </p>
                    </div>

                    <label className="flex items-start gap-2.5 text-sm cursor-pointer pt-1">
                        <input
                            type="checkbox"
                            checked={isActive}
                            onChange={e => setIsActive(e.target.checked)}
                            className="mt-0.5 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span className="font-medium leading-snug">Activer le calcul automatique des pénalités de retard</span>
                    </label>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isSubmitting} className="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white">
                            {isSubmitting ? 'Enregistrement...' : 'Enregistrer la configuration'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

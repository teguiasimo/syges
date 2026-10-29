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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Coins, Zap } from 'lucide-react';

interface RechargerSoldeModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissementId: number;
    soldeActuel: number;
}

/**
 * Modale de recharge du solde de crédits SMS (Gesco 14.1).
 *
 * @param {RechargerSoldeModalProps} props
 * @returns {JSX.Element}
 */
export default function RechargerSoldeModal({
    open,
    onOpenChange,
    etablissementId,
    soldeActuel,
}: RechargerSoldeModalProps) {
    const { data, setData, post, processing } = useForm({
        etablissement_id: etablissementId,
        credits: '500',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/administration/communication/recharger', {
            onSuccess: () => {
                toast.success('Solde de crédits SMS rechargé avec succès.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                onOpenChange(false);
            },
            onError: () => {
                toast.error('Erreur lors du rechargement des crédits.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100">
                        <Coins className="w-5 h-5 text-amber-500 shrink-0" />
                        <span>Recharger le Solde SMS</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Solde actuel : <strong>{soldeActuel} crédits SMS</strong>.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="credits" className="text-xs font-semibold">
                            Pack de crédits à ajouter
                        </Label>
                        <Select
                            value={data.credits}
                            onValueChange={(val) => setData('credits', val)}
                        >
                            <SelectTrigger id="credits" className="text-xs h-9 w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="100">+100 SMS (Pack Découverte)</SelectItem>
                                <SelectItem value="500">+500 SMS (Pack Établissement Standard)</SelectItem>
                                <SelectItem value="1000">+1 000 SMS (Pack Grand Campus)</SelectItem>
                                <SelectItem value="5000">+5 000 SMS (Pack Illimité Rentrée)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
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
                            className="bg-amber-500 hover:bg-amber-600 text-white font-semibold w-full sm:w-auto"
                            disabled={processing}
                        >
                            {processing ? 'Crédit en cours...' : 'Recharger le Solde'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

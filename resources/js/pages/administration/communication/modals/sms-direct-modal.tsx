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
import { Textarea } from '@/components/ui/textarea';
import { MessageSquare, Sparkles } from 'lucide-react';

interface SmsDirectModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissementId: number;
}

/**
 * Modale d'envoi d'un SMS direct unitaire (Gesco 14.2).
 *
 * @param {SmsDirectModalProps} props
 * @returns {JSX.Element}
 */
export default function SmsDirectModal({
    open,
    onOpenChange,
    etablissementId,
}: SmsDirectModalProps) {
    const { data, setData, post, processing, reset, errors, transform } = useForm({
        etablissement_id: etablissementId,
        telephone: '',
        destinataire_nom: '',
        message: '',
    });

    const charCount = data.message.length;
    const estimatedSegments = charCount === 0 ? 1 : charCount <= 160 ? 1 : Math.ceil(charCount / 153);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            etablissement_id: etablissementId,
        }));

        post('/administration/communication/sms-direct', {
            onSuccess: () => {
                toast.success('SMS envoyé avec succès.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.telephone || err.message || err.solde || 'Erreur lors de l’envoi du SMS.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100">
                        <MessageSquare className="w-5 h-5 text-teal-600 shrink-0" />
                        <span>Envoyer un SMS Direct</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Message unitaire avec normalisation internationale automatique (+237...).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3.5 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="telephone" className="text-xs font-semibold">
                            Numéro de téléphone destinataire <span className="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="telephone"
                            placeholder="Ex: 699112233 ou +237699112233"
                            value={data.telephone}
                            onChange={(e) => setData('telephone', e.target.value)}
                            className="text-xs h-9"
                            required
                        />
                        {errors.telephone && <p className="text-xs text-rose-500">{errors.telephone}</p>}
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="destinataire_nom" className="text-xs font-semibold">
                            Nom du destinataire (Optionnel)
                        </Label>
                        <Input
                            id="destinataire_nom"
                            placeholder="Ex: M. Jean Talla (Parent d'élève)"
                            value={data.destinataire_nom}
                            onChange={(e) => setData('destinataire_nom', e.target.value)}
                            className="text-xs h-9"
                        />
                    </div>

                    <div className="space-y-1">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="message" className="text-xs font-semibold">
                                Contenu du message <span className="text-rose-500">*</span>
                            </Label>
                            <span className="text-[11px] font-mono text-slate-500">
                                {charCount} car. ({estimatedSegments} segment)
                            </span>
                        </div>
                        <Textarea
                            id="message"
                            rows={3}
                            placeholder="Saisissez votre message..."
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            required
                        />
                        {errors.message && <p className="text-xs text-rose-500">{errors.message}</p>}
                    </div>

                    <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                reset();
                                onOpenChange(false);
                            }}
                            disabled={processing}
                            className="w-full sm:w-auto"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            className="bg-teal-600 hover:bg-teal-700 text-white font-semibold w-full sm:w-auto"
                            disabled={processing}
                        >
                            {processing ? 'Envoi...' : 'Expédier le SMS'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

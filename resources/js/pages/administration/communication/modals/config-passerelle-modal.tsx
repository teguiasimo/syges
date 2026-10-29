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
import { FournisseurSms, SmsConfiguration } from '@/types/communication';
import { Settings, Shield } from 'lucide-react';

interface ConfigPasserelleModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    config: SmsConfiguration;
}

/**
 * Modale de configuration de la passerelle SMS & Sender ID (Gesco 14.1).
 *
 * @param {ConfigPasserelleModalProps} props
 * @returns {JSX.Element}
 */
export default function ConfigPasserelleModal({
    open,
    onOpenChange,
    config,
}: ConfigPasserelleModalProps) {
    const { data, setData, put, processing, reset, errors } = useForm({
        etablissement_id: config.etablissement_id,
        fournisseur: config.fournisseur as FournisseurSms,
        sender_id: config.sender_id || 'SYGES',
        api_url: config.api_url || '',
        api_key: config.api_key || '',
        api_secret: '',
        seuil_alerte_solde: String(config.seuil_alerte_solde || 20),
        is_active: config.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        put('/administration/communication/config', {
            onSuccess: () => {
                toast.success('Configuration de la passerelle SMS mise à jour.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.sender_id || err.fournisseur || 'Erreur lors de la mise à jour de la passerelle.', {
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
                        <Settings className="w-5 h-5 text-teal-600 shrink-0" />
                        <span>Passerelle SMS & Sender ID</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Paramétrage des identifiants d'opérateur télécom et du nom d'expéditeur GSM officiel.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3.5 py-2">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="fournisseur" className="text-xs font-semibold">
                                Agrégateur télécom <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.fournisseur}
                                onValueChange={(val: FournisseurSms) => setData('fournisseur', val)}
                            >
                                <SelectTrigger id="fournisseur" className="text-xs h-9 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="africas_talking">Africa's Talking</SelectItem>
                                    <SelectItem value="orange">Orange SMS API</SelectItem>
                                    <SelectItem value="mtn">MTN Mobile SMS</SelectItem>
                                    <SelectItem value="autre">Autre passerelle HTTP</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="sender_id" className="text-xs font-semibold">
                                Sender ID (Max 11 car.) <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="sender_id"
                                maxLength={11}
                                placeholder="Ex: SYGES, COLLEGE"
                                value={data.sender_id}
                                onChange={(e) => setData('sender_id', e.target.value.toUpperCase().replace(/[^A-Z0-9_-]/g, ''))}
                                className="text-xs h-9 font-mono uppercase"
                                required
                            />
                            {errors.sender_id && <p className="text-xs text-rose-500">{errors.sender_id}</p>}
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="api_url" className="text-xs font-semibold">
                            URL de l'API Endpoint
                        </Label>
                        <Input
                            id="api_url"
                            placeholder="https://api.africastalking.com/version1/messaging"
                            value={data.api_url}
                            onChange={(e) => setData('api_url', e.target.value)}
                            className="text-xs h-9 font-mono"
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="api_key" className="text-xs font-semibold">
                            Clé d'API / Token d'accès
                        </Label>
                        <Input
                            id="api_key"
                            type="password"
                            placeholder="••••••••••••••••••••••••••••••••"
                            value={data.api_key}
                            onChange={(e) => setData('api_key', e.target.value)}
                            className="text-xs h-9 font-mono"
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="seuil_alerte_solde" className="text-xs font-semibold">
                                Seuil d'alerte solde bas
                            </Label>
                            <Input
                                id="seuil_alerte_solde"
                                type="number"
                                min={5}
                                max={500}
                                value={data.seuil_alerte_solde}
                                onChange={(e) => setData('seuil_alerte_solde', e.target.value)}
                                className="text-xs h-9"
                                required
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="is_active" className="text-xs font-semibold">
                                Statut de la passerelle
                            </Label>
                            <Select
                                value={data.is_active ? '1' : '0'}
                                onValueChange={(val) => setData('is_active', val === '1')}
                            >
                                <SelectTrigger id="is_active" className="text-xs h-9 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active / Opérationnelle</SelectItem>
                                    <SelectItem value="0">Désactivée</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
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
                            className="bg-teal-600 hover:bg-teal-700 text-white font-semibold w-full sm:w-auto"
                            disabled={processing}
                        >
                            {processing ? 'Enregistrement...' : 'Sauvegarder les Paramètres'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

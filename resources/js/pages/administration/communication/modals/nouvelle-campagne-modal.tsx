import React, { useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { TypeCible } from '@/types/communication';
import { Send, AlertTriangle, Sparkles } from 'lucide-react';

interface NouvelleCampagneModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissementId: number;
    classes: { id: number; nom: string }[];
    niveaux: { id: number; nom: string }[];
    sections: { id: number; nom: string }[];
    soldeCredits: number;
}

/**
 * Modale d'émission d'une campagne de SMS groupés (Gesco 14.2.B).
 *
 * @param {NouvelleCampagneModalProps} props
 * @returns {JSX.Element}
 */
export default function NouvelleCampagneModal({
    open,
    onOpenChange,
    etablissementId,
    classes,
    niveaux,
    sections,
    soldeCredits,
}: NouvelleCampagneModalProps) {
    const { data, setData, post, processing, reset, errors, transform } = useForm({
        etablissement_id: etablissementId,
        titre: '',
        cible_type: 'tous' as TypeCible,
        cible_id: '',
        message: '',
    });

    // Estimation temps-réel GSM 7-bit
    const charCount = data.message.length;
    const estimatedSegments = charCount === 0 ? 1 : charCount <= 160 ? 1 : Math.ceil(charCount / 153);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            etablissement_id: etablissementId,
            cible_id: formValues.cible_id ? Number(formValues.cible_id) : null,
        }));

        post('/administration/communication/campagnes', {
            onSuccess: () => {
                toast.success('Campagne SMS diffusée avec succès.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.solde || err.cible_type || err.message || 'Erreur lors de l’envoi de la campagne.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100">
                        <Send className="w-5 h-5 text-teal-600 shrink-0" />
                        <span>Diffuser une Campagne SMS Groupée</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Envoi ciblé aux parents avec conversion automatique GSM 7-bit (sans surcoût d'accent).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3.5 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="titre" className="text-xs font-semibold">
                            Objet / Titre de la campagne <span className="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="titre"
                            placeholder="Ex: Assemblée Générale des Parents d'Élèves"
                            value={data.titre}
                            onChange={(e) => setData('titre', e.target.value)}
                            className="text-xs h-9"
                            required
                        />
                        {errors.titre && <p className="text-xs text-rose-500">{errors.titre}</p>}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="cible_type" className="text-xs font-semibold">
                                Cible de diffusion <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.cible_type}
                                onValueChange={(val: TypeCible) => {
                                    setData('cible_type', val);
                                    setData('cible_id', '');
                                }}
                            >
                                <SelectTrigger id="cible_type" className="text-xs h-9 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="tous">Tout l'établissement</SelectItem>
                                    <SelectItem value="section">Par Section linguistique</SelectItem>
                                    <SelectItem value="niveau">Par Niveau pédagogique</SelectItem>
                                    <SelectItem value="classe">Par Classe spécifique</SelectItem>
                                    <SelectItem value="insolvables">Parents Insolvables uniquement</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {data.cible_type === 'classe' && (
                            <div className="space-y-1">
                                <Label htmlFor="cible_id" className="text-xs font-semibold">
                                    Classe sélectionnée <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.cible_id}
                                    onValueChange={(val) => setData('cible_id', val)}
                                >
                                    <SelectTrigger id="cible_id" className="text-xs h-9 w-full">
                                        <SelectValue placeholder="Choisir une classe..." />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-56">
                                        {classes.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)} className="text-xs">
                                                {c.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {data.cible_type === 'niveau' && (
                            <div className="space-y-1">
                                <Label htmlFor="cible_id" className="text-xs font-semibold">
                                    Niveau sélectionné <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.cible_id}
                                    onValueChange={(val) => setData('cible_id', val)}
                                >
                                    <SelectTrigger id="cible_id" className="text-xs h-9 w-full">
                                        <SelectValue placeholder="Choisir un niveau..." />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-56">
                                        {niveaux.map((n) => (
                                            <SelectItem key={n.id} value={String(n.id)} className="text-xs">
                                                {n.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {data.cible_type === 'section' && (
                            <div className="space-y-1">
                                <Label htmlFor="cible_id" className="text-xs font-semibold">
                                    Section sélectionnée <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.cible_id}
                                    onValueChange={(val) => setData('cible_id', val)}
                                >
                                    <SelectTrigger id="cible_id" className="text-xs h-9 w-full">
                                        <SelectValue placeholder="Choisir une section..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {sections.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)} className="text-xs">
                                                {s.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>

                    <div className="space-y-1">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="message" className="text-xs font-semibold">
                                Message SMS <span className="text-rose-500">*</span>
                            </Label>
                            <span className="text-[11px] font-mono text-slate-500">
                                {charCount} car. | <strong>{estimatedSegments} segment(s)</strong> / destinataire
                            </span>
                        </div>
                        <Textarea
                            id="message"
                            rows={4}
                            placeholder="Saisissez le texte de votre annonce..."
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            required
                        />
                        {errors.message && <p className="text-xs text-rose-500">{errors.message}</p>}
                        <div className="flex items-center gap-1.5 text-[10px] text-teal-700 dark:text-teal-300 bg-teal-50/80 dark:bg-teal-950/40 p-2 rounded">
                            <Sparkles className="w-3.5 h-3.5 shrink-0 text-teal-600" />
                            <span>
                                <strong>Sanitisation GSM 7-bit active</strong> : les accents (é, è, à, ç) seront nettoyés avant émission pour garantir 160 car./SMS au lieu de 70.
                            </span>
                        </div>
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
                            {processing ? 'Diffusion en cours...' : 'Diffuser la Campagne'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

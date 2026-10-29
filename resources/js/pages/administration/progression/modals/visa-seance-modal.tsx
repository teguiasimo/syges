import { router, useForm } from '@inertiajs/react';
import { CheckCircle2, Loader2, ShieldCheck, XCircle } from 'lucide-react';
import React, { useEffect, useState } from 'react';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/utils';
import type { FicheProgression } from '@/types/progression';

interface VisaSeanceModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    seance: FicheProgression | null;
}

/**
 * Modal d'apposition du Visa Hiérarchique du Censeur / Dir. Études (Aurore 5.1.8).
 */
export function VisaSeanceModal({ open, onOpenChange, seance }: VisaSeanceModalProps) {
    if (!seance) return null;

    const isAlreadyVise = seance.visa_censeur;
    const [submitting, setSubmitting] = useState<boolean>(false);

    const { data, setData } = useForm({
        visa: !isAlreadyVise,
        observations_censeur: seance.observations_censeur || '',
    });

    useEffect(() => {
        if (seance) {
            setData({
                visa: !seance.visa_censeur,
                observations_censeur: seance.observations_censeur || '',
            });
        }
    }, [seance, open]);

    const handleToggleVisa = (approuver: boolean) => {
        setSubmitting(true);
        router.post(
            `/administration/progression-pedagogique/${seance.id}/viser`,
            {
                visa: approuver,
                observations_censeur: data.observations_censeur,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        approuver ? 'Séance validée et visée par le censeur' : 'Visa de la séance révoqué',
                        {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white font-medium border-none shadow-lg',
                        }
                    );
                    onOpenChange(false);
                },
                onError: () => {
                    toast.error('Une erreur est survenue lors de l\'enregistrement du visa', {
                        position: 'top-right',
                    });
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            }
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-teal-600 dark:text-teal-400">
                        <ShieldCheck className="w-5 h-5 shrink-0" />
                        <DialogTitle>Visa Pédagogique du Censeur</DialogTitle>
                    </div>
                    <DialogDescription>
                        Contrôle de conformité de la séance dispensée en classe par l'enseignant.
                    </DialogDescription>
                </DialogHeader>

                {/* Récapitulatif de la séance */}
                <div className="rounded-md border bg-slate-50 dark:bg-slate-900/50 p-3 space-y-2 text-xs">
                    <div className="flex flex-col min-[400px]:flex-row min-[400px]:justify-between gap-0.5">
                        <span className="text-slate-500">Date & Semaine :</span>
                        <span className="font-semibold text-slate-800 dark:text-slate-200">
                            {formatDate(seance.date_seance)} {seance.semaine_numero ? `(Semaine ${seance.semaine_numero})` : ''}
                        </span>
                    </div>
                    <div className="flex flex-col min-[400px]:flex-row min-[400px]:justify-between gap-0.5">
                        <span className="text-slate-500">Classe & Matière :</span>
                        <span className="font-semibold text-slate-800 dark:text-slate-200">
                            {seance.attribution?.classe?.nom} - {seance.attribution?.matiere?.nom}
                        </span>
                    </div>
                    <div className="flex flex-col min-[400px]:flex-row min-[400px]:justify-between gap-0.5">
                        <span className="text-slate-500">Enseignant :</span>
                        <span className="font-semibold text-slate-800 dark:text-slate-200">
                            {seance.attribution?.personnel?.nom} {seance.attribution?.personnel?.prenom}
                        </span>
                    </div>
                    <div className="border-t pt-2 mt-2">
                        <div className="font-medium text-slate-700 dark:text-slate-300">
                            <strong>{seance.chapitre} :</strong> {seance.titre_lecon}
                        </div>
                        {seance.resume_activite && (
                            <p className="text-slate-500 mt-1 italic line-clamp-2">
                                {seance.resume_activite}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-col min-[400px]:flex-row min-[400px]:justify-between gap-0.5 border-t pt-2">
                        <span className="text-slate-500">Volume horaire réalisé :</span>
                        <span className="font-bold text-teal-600 dark:text-teal-400">
                            {seance.heures_effectuees} h / {seance.heures_prevues} h prévues
                        </span>
                    </div>
                </div>

                {/* Directives / Observations du Censeur */}
                <div className="space-y-1.5">
                    <Label htmlFor="observations_censeur" className="text-xs font-semibold">
                        Directives ou remarques du Censeur / Dir. des Études
                    </Label>
                    <Textarea
                        id="observations_censeur"
                        rows={3}
                        placeholder="Ex: Rythme conforme au découpage ministériel. Veiller à consolider les applications pratiques..."
                        value={data.observations_censeur}
                        onChange={(e) => setData('observations_censeur', e.target.value)}
                    />
                </div>

                <DialogFooter className="flex flex-col sm:flex-row gap-2 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={submitting}
                    >
                        Annuler
                    </Button>

                    {isAlreadyVise ? (
                        <Button
                            type="button"
                            variant="destructive"
                            className="gap-1.5"
                            onClick={() => handleToggleVisa(false)}
                            disabled={submitting}
                        >
                            {submitting ? (
                                <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                                <XCircle className="w-4 h-4" />
                            )}
                            Révoquer le Visa
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                            onClick={() => handleToggleVisa(true)}
                            disabled={submitting}
                        >
                            {submitting ? (
                                <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                                <CheckCircle2 className="w-4 h-4" />
                            )}
                            Apposer le Visa Officiel
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

import { router } from '@inertiajs/react';
import { Award, Check, School, UserCheck, Users, X } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ClasseWithAttributions, EnseignantWithWorkload } from '@/types/attribution';

/**
 * Propriétés attendues pour la désignation du professeur principal.
 */
interface ProfesseurPrincipalModalProps {
    classe: ClasseWithAttributions | null;
    isOpen: boolean;
    onClose: () => void;
    enseignants: EnseignantWithWorkload[];
}

/**
 * Modale de désignation du Professeur Principal d'une classe (Gesco Section 2.7.5).
 */
export function ProfesseurPrincipalModal({
    classe,
    isOpen,
    onClose,
    enseignants,
}: ProfesseurPrincipalModalProps) {
    const [selectedPersonnelId, setSelectedPersonnelId] = useState<string>('none');
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (classe) {
            setSelectedPersonnelId(
                classe.professeur_principal_id ? String(classe.professeur_principal_id) : 'none'
            );
        }
    }, [classe, isOpen]);

    /**
     * Enregistre le choix du Professeur Principal.
     */
    const handleSave = () => {
        if (!classe) return;
        setIsSubmitting(true);

        const profId = selectedPersonnelId === 'none' ? null : Number(selectedPersonnelId);

        router.patch(
            `/administration/attributions/classes/${classe.id}/professeur-principal`,
            { professeur_principal_id: profId },
            {
                onSuccess: () => {
                    toast.success('Professeur Principal mis à jour !', {
                        description: `Le Professeur Principal de la classe ${classe.nom} a été enregistré.`,
                    });
                    setIsSubmitting(false);
                    onClose();
                },
                onError: () => {
                    toast.error('Erreur lors de la désignation');
                    setIsSubmitting(false);
                },
            }
        );
    };

    if (!classe) return null;

    // Enseignants qui interviennent déjà dans cette classe
    const teacherIdsInClass = classe.attributions.map((a) => a.personnel_id);
    const teachersOfClass = enseignants.filter((e) => teacherIdsInClass.includes(e.id));
    const otherTeachers = enseignants.filter((e) => !teacherIdsInClass.includes(e.id));

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md p-6">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-lg font-bold flex items-center gap-2 text-foreground">
                        <div className="p-2 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-600 border border-amber-200 dark:border-amber-900/50">
                            <Award className="size-5" />
                        </div>
                        Professeur Principal : {classe.nom}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground mt-1">
                        Le Professeur Principal coordonne l'équipe pédagogique de la classe, préside les délibérations et rédige les bilans trimestriels.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-3">
                    <div>
                        <Label htmlFor="pp_select" className="text-xs font-semibold">
                            Sélectionner l'enseignant responsable
                        </Label>
                        <Select
                            value={selectedPersonnelId}
                            onValueChange={setSelectedPersonnelId}
                        >
                            <SelectTrigger id="pp_select" className="mt-1 h-9 text-sm">
                                <SelectValue placeholder="Choisir le professeur principal" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    <em className="text-muted-foreground">Aucun professeur principal</em>
                                </SelectItem>

                                {teachersOfClass.length > 0 && (
                                    <>
                                        <div className="px-2 py-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider bg-muted/40">
                                            Enseignants de la classe ({teachersOfClass.length})
                                        </div>
                                        {teachersOfClass.map((ens) => (
                                            <SelectItem key={ens.id} value={String(ens.id)}>
                                                ★ {ens.nom} {ens.prenom} ({ens.specialite || 'Général'})
                                            </SelectItem>
                                        ))}
                                    </>
                                )}

                                {otherTeachers.length > 0 && (
                                    <>
                                        <div className="px-2 py-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider bg-muted/40">
                                            Autres enseignants disponibles
                                        </div>
                                        {otherTeachers.map((ens) => (
                                            <SelectItem key={ens.id} value={String(ens.id)}>
                                                {ens.nom} {ens.prenom} ({ens.specialite || 'Général'})
                                            </SelectItem>
                                        ))}
                                    </>
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DialogFooter className="gap-2 border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        className="cursor-pointer"
                    >
                        Annuler
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSave}
                        disabled={isSubmitting}
                        className="bg-amber-600 hover:bg-amber-700 text-white cursor-pointer font-semibold"
                    >
                        <Check className="size-4 mr-1.5" />
                        {isSubmitting ? 'Enregistrement...' : 'Valider le Professeur Principal'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

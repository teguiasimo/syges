import { router } from '@inertiajs/react';
import { Award, Check } from 'lucide-react';
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
import { SearchableSelect } from '@/components/ui/searchable-select';
import { getClasseTitulaireLabel, type ClasseWithAttributions, type EnseignantWithWorkload } from '@/types/attribution';

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
     * Enregistre le choix du titulaire de classe (Professeur Principal / Maître / Maîtresse).
     */
    const handleSave = () => {
        if (!classe) return;
        setIsSubmitting(true);

        const profId = selectedPersonnelId === 'none' ? null : Number(selectedPersonnelId);
        const titulaireLabel = getClasseTitulaireLabel(classe);

        router.patch(
            `/administration/attributions/classes/${classe.id}/professeur-principal`,
            { professeur_principal_id: profId },
            {
                onSuccess: () => {
                    toast.success(`${titulaireLabel} mis à jour !`, {
                        description: `Le ${titulaireLabel.toLowerCase()} de la classe ${classe.nom} a été enregistré.`,
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

    const titulaireLabel = getClasseTitulaireLabel(classe);

    // Enseignants qui interviennent déjà dans cette classe
    const teacherIdsInClass = classe.attributions.map((a) => a.personnel_id);
    const teachersOfClass = enseignants.filter((e) => teacherIdsInClass.includes(e.id));
    const otherTeachers = enseignants.filter((e) => !teacherIdsInClass.includes(e.id));

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-base sm:text-lg font-bold flex items-center gap-2 text-foreground">
                        <div className="p-2 rounded-lg bg-amber-50 text-amber-600 border border-amber-200 shrink-0">
                            <Award className="size-5" />
                        </div>
                        <span>{titulaireLabel} : {classe.nom}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground mt-1">
                        Le {titulaireLabel.toLowerCase()} coordonne l'encadrement pédagogique de la classe, assure le suivi individuel des élèves et préside les bilans périodiques.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-3">
                    <div className="space-y-1.5 min-w-0">
                        <Label htmlFor="pp_select" className="text-xs font-semibold">
                            Sélectionner l'enseignant responsable
                        </Label>
                        <SearchableSelect
                            id="pp_select"
                            options={[
                                {
                                    value: 'none',
                                    label: 'Aucun responsable désigné',
                                    group: 'Titulaire / Aucun',
                                },
                                ...teachersOfClass.map((ens) => ({
                                    value: String(ens.id),
                                    label: `★ ${[ens.nom, ens.prenom].filter(Boolean).join(' ')}`,
                                    description: `${ens.specialite || 'Général'} (${ens.matricule})`,
                                    group: `Enseignants de la classe (${teachersOfClass.length})`,
                                })),
                                ...otherTeachers.map((ens) => ({
                                    value: String(ens.id),
                                    label: [ens.nom, ens.prenom].filter(Boolean).join(' '),
                                    description: `${ens.specialite || 'Général'} (${ens.matricule})`,
                                    group: 'Autres enseignants disponibles',
                                })),
                            ]}
                            value={selectedPersonnelId}
                            onValueChange={(val) => setSelectedPersonnelId(val || 'none')}
                            placeholder={`Choisir (${titulaireLabel.toLowerCase()})`}
                            searchPlaceholder="Rechercher un enseignant..."
                            emptyMessage="Aucun enseignant trouvé."
                            className="h-9 text-sm font-normal"
                        />
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
                        {isSubmitting ? 'Enregistrement...' : `Valider (${titulaireLabel})`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

import { useForm } from '@inertiajs/react';
import { BookOpen, Check, Clock, Layers, Plus, School, UserCheck, Users } from 'lucide-react';
import React, { useMemo } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ClasseWithAttributions,
    EnseignantWithWorkload,
    MatiereNiveauRef,
} from '@/types/attribution';

/**
 * Propriétés de la modale d'affectation individuelle d'un cours.
 */
interface CreateAttributionModalProps {
    isOpen: boolean;
    onClose: () => void;
    anneeScolaireId: number;
    classes: ClasseWithAttributions[];
    enseignants: EnseignantWithWorkload[];
    matieres: { id: number; code: string; nom: string }[];
    matiereNiveaux: MatiereNiveauRef[];
    initialClasseId?: number | null;
}

/**
 * Modale d'attribution d'un cours (Enseignant <-> Classe <-> Matière).
 * Permet au Censeur d'affecter un enseignant titulaire et son volume horaire hebdomadaire.
 */
export function CreateAttributionModal({
    isOpen,
    onClose,
    anneeScolaireId,
    classes,
    enseignants,
    matieres,
    matiereNiveaux,
    initialClasseId,
}: CreateAttributionModalProps) {
    const defaultClasseId = initialClasseId ?? (classes.length > 0 ? classes[0].id : '');

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        annee_scolaire_id: anneeScolaireId,
        classe_id: defaultClasseId as string | number,
        matiere_id: '' as string | number,
        personnel_id: '' as string | number,
        volume_horaire_reel: 3.0,
        is_titulaire: true,
    });

    // Synchronisation de l'année scolaire ou de la classe initiale
    React.useEffect(() => {
        setData((prev) => ({
            ...prev,
            annee_scolaire_id: anneeScolaireId,
            classe_id: initialClasseId ?? prev.classe_id,
        }));
    }, [anneeScolaireId, initialClasseId]);

    // Classe actuellement sélectionnée
    const selectedClasse = useMemo(() => {
        return classes.find((c) => String(c.id) === String(data.classe_id));
    }, [classes, data.classe_id]);

    // Matières au programme pour le niveau et la série de la classe sélectionnée
    const availableMatieres = useMemo(() => {
        if (!selectedClasse) return matieres;

        const niveauId = selectedClasse.niveau_id;
        const serie = selectedClasse.serie;

        const relevantMatiereIds = matiereNiveaux
            .filter((mn) => {
                return (
                    mn.niveau_id === niveauId &&
                    (mn.serie === 'Toutes' || empty(mn.serie) || mn.serie === serie)
                );
            })
            .map((mn) => mn.matiere_id);

        if (relevantMatiereIds.length === 0) {
            return matieres;
        }

        return matieres.filter((m) => relevantMatiereIds.includes(m.id));
    }, [selectedClasse, matieres, matiereNiveaux]);

    /**
     * Soumission du formulaire d'affectation.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/administration/attributions', {
            onSuccess: () => {
                toast.success('Attribution validée avec succès !', {
                    description: 'L\'enseignant a été affecté au cours et ses droits de saisie sont activés.',
                });
                reset();
                clearErrors();
                onClose();
            },
            onError: () => {
                toast.error('Erreur lors de l\'attribution', {
                    description: 'Veuillez vérifier les champs du formulaire.',
                });
            },
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-lg p-6">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-lg font-bold flex items-center gap-2 text-foreground">
                        <div className="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 border border-indigo-200 dark:border-indigo-900/50">
                            <Layers className="size-5" />
                        </div>
                        Affecter un Enseignant à un Cours
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground mt-1">
                        Sélectionnez la classe, la discipline et l'enseignant responsable avec son volume horaire effectif hebdomadaire.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Choix de la Classe */}
                    <div>
                        <Label htmlFor="attr_classe_id" className="text-xs font-semibold">
                            Classe d'affectation <span className="text-rose-500">*</span>
                        </Label>
                        <Select
                            value={data.classe_id ? String(data.classe_id) : ''}
                            onValueChange={(val) => {
                                setData((prev) => ({
                                    ...prev,
                                    classe_id: Number(val),
                                    matiere_id: '', // Réinitialise la matière au changement de classe
                                }));
                            }}
                        >
                            <SelectTrigger id="attr_classe_id" className="mt-1 h-9 text-sm">
                                <SelectValue placeholder="Sélectionner une classe" />
                            </SelectTrigger>
                            <SelectContent>
                                {classes.map((cls) => (
                                    <SelectItem key={cls.id} value={String(cls.id)}>
                                        {cls.nom} {cls.serie ? `(${cls.serie})` : ''} — {cls.niveau?.nom}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.classe_id && <p className="text-rose-500 text-xs mt-1">{errors.classe_id}</p>}
                    </div>

                    {/* Choix de la Matière */}
                    <div>
                        <Label htmlFor="attr_matiere_id" className="text-xs font-semibold">
                            Matière / Discipline <span className="text-rose-500">*</span>
                        </Label>
                        <Select
                            value={data.matiere_id ? String(data.matiere_id) : ''}
                            onValueChange={(val) => setData('matiere_id', Number(val))}
                        >
                            <SelectTrigger id="attr_matiere_id" className="mt-1 h-9 text-sm">
                                <SelectValue placeholder="Sélectionner la discipline" />
                            </SelectTrigger>
                            <SelectContent>
                                {availableMatieres.map((mat) => (
                                    <SelectItem key={mat.id} value={String(mat.id)}>
                                        {mat.nom} ({mat.code})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.matiere_id && <p className="text-rose-500 text-xs mt-1">{errors.matiere_id}</p>}
                    </div>

                    {/* Choix de l'Enseignant */}
                    <div>
                        <Label htmlFor="attr_personnel_id" className="text-xs font-semibold">
                            Enseignant responsable <span className="text-rose-500">*</span>
                        </Label>
                        <Select
                            value={data.personnel_id ? String(data.personnel_id) : ''}
                            onValueChange={(val) => setData('personnel_id', Number(val))}
                        >
                            <SelectTrigger id="attr_personnel_id" className="mt-1 h-9 text-sm">
                                <SelectValue placeholder="Sélectionner un enseignant" />
                            </SelectTrigger>
                            <SelectContent>
                                {enseignants.map((ens) => (
                                    <SelectItem key={ens.id} value={String(ens.id)}>
                                        {ens.nom} {ens.prenom} ({ens.matricule}) — {ens.specialite || 'Général'} [{ens.heures_attribuees}h / {ens.volume_horaire_hebdo}h]
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.personnel_id && <p className="text-rose-500 text-xs mt-1">{errors.personnel_id}</p>}
                    </div>

                    {/* Volume horaire réel hebdomadaire */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="attr_volume" className="text-xs font-semibold">
                                Heures hebdo réelles <span className="text-rose-500">*</span>
                            </Label>
                            <div className="relative mt-1">
                                <Clock className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                <Input
                                    id="attr_volume"
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    max="30"
                                    required
                                    value={data.volume_horaire_reel}
                                    onChange={(e) => setData('volume_horaire_reel', parseFloat(e.target.value) || 0)}
                                    className="pl-8 h-9 text-sm"
                                />
                            </div>
                        </div>

                        <div className="flex items-center pt-5">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="attr_is_titulaire"
                                    checked={data.is_titulaire}
                                    onCheckedChange={(checked) => setData('is_titulaire', !!checked)}
                                />
                                <Label htmlFor="attr_is_titulaire" className="text-xs font-medium cursor-pointer">
                                    Enseignant titulaire officiel
                                </Label>
                            </div>
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
                            type="submit"
                            disabled={processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer font-semibold"
                        >
                            <Plus className="size-4 mr-1.5" />
                            {processing ? 'Enregistrement...' : 'Affecter le cours'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function empty(val: any): boolean {
    return val === null || val === undefined || val === '';
}

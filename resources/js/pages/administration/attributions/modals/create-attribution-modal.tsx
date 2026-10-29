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
import { SearchableSelect, type SearchableOption } from '@/components/ui/searchable-select';
import { truncateText } from '@/lib/utils';
import type {
    ClasseWithAttributions,
    EnseignantWithWorkload,
    MatiereNiveauRef,
} from '@/types/attribution';

/**
 * Propriétés attendues pour la modale d'affectation individuelle d'un cours.
 */
interface CreateAttributionModalProps {
    /** Indicateur d'ouverture du dialogue */
    isOpen: boolean;
    /** Callback de fermeture du dialogue */
    onClose: () => void;
    /** Identifiant de l'année scolaire active */
    anneeScolaireId: number;
    /** Liste des classes disponibles */
    classes: ClasseWithAttributions[];
    /** Liste des enseignants avec charge horaire */
    enseignants: EnseignantWithWorkload[];
    /** Catalogue des matières actives */
    matieres: { id: number; code: string; nom: string }[];
    /** Matrice des matières par niveau */
    matiereNiveaux: MatiereNiveauRef[];
    /** Identifiant de classe pré-sélectionnée optionnel */
    initialClasseId?: number | null;
}

/**
 * Modale d'attribution d'un cours (Enseignant <-> Classe <-> Matière).
 * Conforme au Cahier des Charges Gesco V1.1 (Module 9 - Répartition pédagogique).
 *
 * Fonctionnalités intégrées :
 * - Sélection de classe avec recherche temps réel et regroupement par niveau scolaire.
 * - Sélection de matière avec filtre de recherche et gestion des libellés longs.
 * - Sélection de l'enseignant responsable sans débordement de fenêtre, avec charge horaire en temps réel.
 * - Volume horaire hebdomadaire par défaut fixé à 2.0 h avec compacité visuelle optimisée.
 * - Option titulaire officiel décochée par défaut.
 *
 * @param {CreateAttributionModalProps} props
 * @returns {JSX.Element}
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
    const defaultClasseId = initialClasseId ? Number(initialClasseId) : '';

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        annee_scolaire_id: anneeScolaireId,
        classe_id: defaultClasseId as string | number,
        matiere_id: '' as string | number,
        personnel_id: '' as string | number,
        volume_horaire_reel: 2.0,
        is_titulaire: false,
    });

    // Synchronisation de l'état lors de l'ouverture ou du changement d'initialClasseId / anneeScolaireId
    React.useEffect(() => {
        if (isOpen) {
            setData((prev) => ({
                ...prev,
                annee_scolaire_id: anneeScolaireId,
                classe_id: initialClasseId ? Number(initialClasseId) : '',
                matiere_id: '',
                personnel_id: '',
            }));
            clearErrors();
        }
    }, [isOpen, anneeScolaireId, initialClasseId]);

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
     * Options formatées pour SearchableSelect : Classes d'affectation regroupées par niveau scolaire.
     */
    const classeOptions: SearchableOption[] = useMemo(() => {
        return classes.map((cls) => ({
            value: String(cls.id),
            label: `${cls.nom}${cls.serie ? ` (${cls.serie})` : ''}`,
            group: cls.niveau?.nom || 'Classes',
        }));
    }, [classes]);

    /**
     * Options formatées pour SearchableSelect : Matières & disciplines avec recherche et code officiel.
     */
    const matiereOptions: SearchableOption[] = useMemo(() => {
        return availableMatieres.map((mat) => {
            // Élimine toute redondance si le nom se termine déjà par le code entre parenthèses
            const cleanNom = mat.nom.replace(new RegExp(`\\s*\\(${mat.code}\\)$`, 'i'), '').trim();
            return {
                value: String(mat.id),
                label: `${cleanNom} (${mat.code})`,
                group: 'Disciplines au programme',
            };
        });
    }, [availableMatieres]);

    /**
     * Options formatées pour SearchableSelect : Enseignants responsables avec spécialité et charge horaire.
     */
    const enseignantOptions: SearchableOption[] = useMemo(() => {
        return enseignants.map((ens) => ({
            value: String(ens.id),
            label: `${ens.nom} ${ens.prenom} (${ens.matricule})`,
            description: `${ens.specialite || 'Général'} • [${ens.heures_attribuees}h / ${ens.volume_horaire_hebdo}h]`,
            group: ens.departement?.nom || 'Corps professoral',
        }));
    }, [enseignants]);

    /**
     * Soumission du formulaire d'affectation de cours.
     * Supporte le mode enchaînement (continueAdding) pour affecter plusieurs cours sans fermer la modale.
     *
     * @param {React.FormEvent} e Événement de soumission
     * @param {boolean} continueAdding Si vrai, conserve la classe et prépare l'affectation suivante
     */
    const handleSubmit = (e: React.FormEvent, continueAdding: boolean = false) => {
        e.preventDefault();

        post('/administration/attributions', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Attribution validée avec succès !', {
                    description: 'L\'enseignant a été affecté au cours et ses droits de saisie sont activés.',
                });

                if (continueAdding) {
                    // Conserve la classe d'affectation sélectionnée et réinitialise uniquement la matière et l'enseignant
                    setData((prev) => ({
                        ...prev,
                        matiere_id: '',
                        personnel_id: '',
                        volume_horaire_reel: 2.0,
                        is_titulaire: false,
                    }));
                    clearErrors();
                } else {
                    reset();
                    clearErrors();
                    onClose();
                }
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
            <DialogContent className="sm:max-w-lg">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-base sm:text-lg font-bold flex items-center gap-2 text-foreground">
                        <div className="p-2 rounded-lg bg-teal-50 dark:bg-teal-950/50 text-teal-600 border border-teal-200 dark:border-teal-900/50 shrink-0">
                            <Layers className="size-5" />
                        </div>
                        <span>Affecter un Enseignant à un Cours</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground mt-1">
                        Sélectionnez la classe, la discipline et l'enseignant responsable avec son volume horaire hebdomadaire effectif.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Choix de la Classe (avec zone de recherche et regroupement par niveau) */}
                    <div className="space-y-1">
                        <Label htmlFor="attr_classe_id" className="text-xs font-semibold text-foreground">
                            Classe d'affectation <span className="text-rose-500">*</span>
                        </Label>
                        <SearchableSelect
                            id="attr_classe_id"
                            options={classeOptions}
                            value={String(data.classe_id || '')}
                            onChange={(val) => {
                                setData((prev) => ({
                                    ...prev,
                                    classe_id: val ? Number(val) : '',
                                    matiere_id: '', // Réinitialise la discipline au changement de classe
                                }));
                            }}
                            placeholder="Sélectionner une classe..."
                            searchPlaceholder="Rechercher une classe, un niveau..."
                            emptyMessage="Aucune classe trouvée pour"
                            className="h-9 text-xs"
                        />
                        {errors.classe_id && <p className="text-rose-500 text-xs mt-1">{errors.classe_id}</p>}
                    </div>

                    {/* Choix de la Matière (avec zone de recherche et gestion des noms longs) */}
                    <div className="space-y-1">
                        <Label htmlFor="attr_matiere_id" className="text-xs font-semibold text-foreground">
                            Matière / Discipline <span className="text-rose-500">*</span>
                        </Label>
                        <SearchableSelect
                            id="attr_matiere_id"
                            options={matiereOptions}
                            value={String(data.matiere_id || '')}
                            onChange={(val) => setData('matiere_id', val ? Number(val) : '')}
                            placeholder="Sélectionner la discipline..."
                            searchPlaceholder="Rechercher une matière ou un code..."
                            emptyMessage="Aucune discipline trouvée pour"
                            className="h-9 text-xs"
                        />
                        {errors.matiere_id && <p className="text-rose-500 text-xs mt-1">{errors.matiere_id}</p>}
                    </div>

                    {/* Choix de l'Enseignant (avec zone de recherche et protection contre le débordement) */}
                    <div className="space-y-1">
                        <Label htmlFor="attr_personnel_id" className="text-xs font-semibold text-foreground">
                            Enseignant responsable <span className="text-rose-500">*</span>
                        </Label>
                        <SearchableSelect
                            id="attr_personnel_id"
                            options={enseignantOptions}
                            value={String(data.personnel_id || '')}
                            onChange={(val) => setData('personnel_id', val ? Number(val) : '')}
                            placeholder="Sélectionner un enseignant..."
                            searchPlaceholder="Rechercher par nom, prénom, matricule..."
                            emptyMessage="Aucun enseignant trouvé pour"
                            className="h-9 text-xs"
                        />
                        {errors.personnel_id && <p className="text-rose-500 text-xs mt-1">{errors.personnel_id}</p>}
                    </div>

                    {/* Réorganisation compacte : Volume horaire hebdo & Titulaire officiel */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-3 bg-muted/40 rounded-lg border">
                        <div className="flex items-center gap-3">
                            <Label htmlFor="attr_volume" className="text-xs font-semibold shrink-0 text-foreground">
                                Horaire hebdo <span className="text-rose-500">*</span>
                            </Label>
                            <div className="relative w-24">
                                <Clock className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
                                <Input
                                    id="attr_volume"
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    max="30"
                                    required
                                    value={data.volume_horaire_reel}
                                    onChange={(e) => setData('volume_horaire_reel', parseFloat(e.target.value) || 0)}
                                    className="pl-8 h-8 text-xs font-bold text-center"
                                />
                            </div>
                            <span className="text-[11px] text-muted-foreground shrink-0">heures/sem</span>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="attr_is_titulaire"
                                checked={data.is_titulaire}
                                onCheckedChange={(checked) => setData('is_titulaire', !!checked)}
                            />
                            <Label htmlFor="attr_is_titulaire" className="text-xs font-medium cursor-pointer select-none">
                                Enseignant titulaire officiel
                            </Label>
                        </div>
                    </div>
                    {errors.volume_horaire_reel && (
                        <p className="text-rose-500 text-xs mt-0.5">{errors.volume_horaire_reel}</p>
                    )}

                    {/* Pied de modale adaptatif empilant les 3 actions sur mobile sans débordement */}
                    <DialogFooter className="gap-2 border-t pt-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between w-full">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            className="cursor-pointer text-xs w-full sm:w-auto"
                        >
                            Annuler
                        </Button>

                        <div className="flex flex-wrap items-center justify-end gap-2 w-full sm:w-auto">
                            {/* Bouton pour enchaîner les attributions sans fermer la modale */}
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={(e) => handleSubmit(e, true)}
                                className="cursor-pointer text-xs border-teal-600/40 text-teal-700 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-950/50 font-medium flex-1 sm:flex-initial"
                            >
                                <Plus className="size-3.5 mr-1 text-teal-600" />
                                Affecter & Continuer
                            </Button>

                            {/* Bouton principal pour affecter et fermer la modale */}
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-teal-600 hover:bg-teal-700 text-white cursor-pointer font-semibold text-xs shadow-xs flex-1 sm:flex-initial"
                            >
                                <Check className="size-4 mr-1.5" />
                                {processing ? 'Enregistrement...' : 'Affecter le cours'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Fonction utilitaire de vérification de vacuité.
 *
 * @param {*} val Valeur à tester
 * @returns {boolean}
 */
function empty(val: any): boolean {
    return val === null || val === undefined || val === '';
}

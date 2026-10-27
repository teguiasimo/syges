import { useForm } from '@inertiajs/react';
import { BookOpen, Check, Plus, PlusCircle } from 'lucide-react';
import React, { useEffect } from 'react';
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
import { SearchableSelect } from '@/components/ui/searchable-select';
import type { Departement, Personnel } from '@/types/personnel';

/**
 * Propriétés attendues par la modale de création / modification d'un département pédagogique.
 */
interface DepartementModalProps {
    /** Département en cours d'édition (null si création) */
    departement: Departement | null;
    /** Indique si la modale est ouverte */
    isOpen: boolean;
    /** Callback de fermeture */
    onClose: () => void;
    /** Liste des membres du personnel pouvant être désignés chef de département */
    personnels: Personnel[];
}

/**
 * Modale de gestion des départements pédagogiques (Sections Gesco 8.1, 8.2).
 * Permet la création et l'édition rapide d'une discipline/département sans champ description superflu.
 * Intègre un bouton "+ Nouveau" pour enchaîner les créations de départements sans fermer la modale,
 * et utilise SearchableSelect pour éliminer tout débordement causé par les noms longs des chefs.
 */
export function DepartementModal({
    departement,
    isOpen,
    onClose,
    personnels,
}: DepartementModalProps) {
    const isEditing = !!departement;

    /**
     * Formulaire Inertia pour la gestion du département.
     */
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        nom: '',
        code: '',
        chef_departement_id: '' as string | number,
        is_active: true,
    });

    /**
     * Synchronise le formulaire lors de l'ouverture ou du changement de département sélectionné.
     */
    useEffect(() => {
        if (departement) {
            setData({
                nom: departement.nom,
                code: departement.code || '',
                chef_departement_id: departement.chef_departement_id || '',
                is_active: departement.is_active,
            });
            clearErrors();
        } else {
            reset();
            clearErrors();
        }
    }, [departement, isOpen]);

    /**
     * Traitement de l'enregistrement avec option de conservation de la modale ouverte.
     *
     * @param {boolean} keepOpen Indique s'il faut réinitialiser le formulaire pour ajouter le département suivant sans fermer la modale.
     */
    const handleSave = (keepOpen: boolean = false) => {
        if (isEditing) {
            put(`/administration/personnels/departements/${departement.id}`, {
                onSuccess: () => {
                    toast.success('Département mis à jour !', {
                        description: `Le département ${data.nom} a été modifié avec succès.`,
                    });
                    onClose();
                },
                onError: () => {
                    toast.error('Erreur lors de la modification', {
                        description: 'Veuillez vérifier les informations du formulaire.',
                    });
                },
            });
        } else {
            post('/administration/personnels/departements', {
                onSuccess: () => {
                    toast.success('Département créé avec succès !', {
                        description: `Le département ${data.nom} a été ajouté.`,
                    });
                    reset();
                    clearErrors();
                    if (!keepOpen) {
                        onClose();
                    }
                },
                onError: () => {
                    toast.error('Erreur lors de la création', {
                        description: 'Veuillez vérifier les champs renseignés.',
                    });
                },
            });
        }
    };

    /**
     * Soumission classique du formulaire.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        handleSave(false);
    };

    // Filtrer les enseignants pour suggérer les chefs de département
    const eligibleChefs = personnels.filter((p) => p.is_enseignant || p.fonction_principale === 'Enseignant');

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-lg p-6">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-lg font-bold flex items-center gap-2 text-foreground">
                        <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50">
                            <BookOpen className="size-4" />
                        </div>
                        {isEditing ? `Modifier : ${departement.nom}` : 'Nouveau Département Pédagogique'}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground mt-1">
                        Les départements regroupent les enseignants par discipline (Mathématiques, Lettres, Sciences, etc.).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Nom du département */}
                    <div>
                        <Label htmlFor="dep_nom" className="text-xs font-semibold">
                            Nom du département <span className="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="dep_nom"
                            required
                            value={data.nom}
                            onChange={(e) => setData('nom', e.target.value)}
                            placeholder="ex: Mathématiques"
                            className="mt-1 h-9 text-sm"
                        />
                        {errors.nom && <p className="text-rose-500 text-xs mt-1">{errors.nom}</p>}
                    </div>

                    {/* Code et Statut actif regroupés harmonieusement */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                        <div>
                            <Label htmlFor="dep_code" className="text-xs font-semibold">
                                Code / Sigle court
                            </Label>
                            <Input
                                id="dep_code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                placeholder="ex: DPT-MATH"
                                className="mt-1 h-9 text-sm font-mono"
                            />
                            {errors.code && <p className="text-rose-500 text-xs mt-1">{errors.code}</p>}
                        </div>

                        <div className="flex items-center gap-2 pt-4 sm:pt-5">
                            <Checkbox
                                id="dep_is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) => setData('is_active', !!checked)}
                            />
                            <Label htmlFor="dep_is_active" className="text-xs font-medium cursor-pointer">
                                Département actif
                            </Label>
                        </div>
                    </div>

                    {/* Chef de Département avec champ de recherche et protection contre les débordements */}
                    <div className="w-full min-w-0">
                        <Label htmlFor="dep_chef" className="text-xs font-semibold">
                            Chef de Département (Optionnel)
                        </Label>
                        <div className="mt-1 w-full min-w-0">
                            <SearchableSelect
                                id="dep_chef"
                                options={[
                                    { value: '', label: 'Aucun chef désigné', group: 'Général' },
                                    ...eligibleChefs.map((p) => ({
                                        value: String(p.id),
                                        label: `${p.nom} ${p.prenom || ''} (${p.matricule})`,
                                        group: 'Enseignants éligibles',
                                    })),
                                ]}
                                value={data.chef_departement_id ? String(data.chef_departement_id) : ''}
                                onValueChange={(val) => setData('chef_departement_id', val ? Number(val) : '')}
                                placeholder="Aucun chef désigné"
                                searchPlaceholder="Rechercher un enseignant ou matricule..."
                                className="h-9 text-xs w-full"
                            />
                        </div>
                        {errors.chef_departement_id && (
                            <p className="text-rose-500 text-xs mt-1">{errors.chef_departement_id}</p>
                        )}
                    </div>

                    <DialogFooter className="gap-2 border-t pt-4 flex flex-wrap justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            className="cursor-pointer text-xs"
                        >
                            Annuler
                        </Button>

                        {!isEditing && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => handleSave(true)}
                                className="cursor-pointer text-xs border-blue-600/40 text-blue-700 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/50 font-medium"
                            >
                                <PlusCircle className="size-4 mr-1.5 text-blue-600 dark:text-blue-400" />
                                + Nouveau
                            </Button>
                        )}

                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-blue-600 hover:bg-blue-700 text-white cursor-pointer font-semibold text-xs"
                        >
                            {isEditing ? <Check className="size-4 mr-1.5" /> : <Plus className="size-4 mr-1.5" />}
                            {processing ? 'Enregistrement...' : isEditing ? 'Mettre à jour' : 'Créer le département'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

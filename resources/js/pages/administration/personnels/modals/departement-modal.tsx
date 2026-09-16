import { useForm } from '@inertiajs/react';
import { BookOpen, Check, Plus, Trash2, Users } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
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
 */
export function DepartementModal({
    departement,
    isOpen,
    onClose,
    personnels,
}: DepartementModalProps) {
    const isEditing = !!departement;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        nom: '',
        code: '',
        description: '',
        chef_departement_id: '' as string | number,
        is_active: true,
    });

    useEffect(() => {
        if (departement) {
            setData({
                nom: departement.nom,
                code: departement.code || '',
                description: departement.description || '',
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
     * Traitement de la soumission de création ou de modification.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

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
                    onClose();
                },
                onError: () => {
                    toast.error('Erreur lors de la création', {
                        description: 'Veuillez vérifier les champs renseignés.',
                    });
                },
            });
        }
    };

    // Filtrer les enseignants pour suggérer les chefs de département
    const eligibleChefs = personnels.filter((p) => p.is_enseignant || p.fonction_principale === 'Enseignant');

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-lg p-6">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-lg font-bold flex items-center gap-2 text-foreground">
                        <div className="p-2 rounded-lg bg-teal-50 dark:bg-teal-950/50 text-teal-600 border border-teal-200 dark:border-teal-900/50">
                            <BookOpen className="size-4" />
                        </div>
                        {isEditing ? `Modifier : ${departement.nom}` : 'Nouveau Département Pédagogique'}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground mt-1">
                        Les départements regroupent les enseignants par discipline (Mathématiques, Lettres, Sciences, etc.).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    <div>
                        <Label htmlFor="dep_nom" className="text-xs font-semibold">
                            Nom du département <span className="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="dep_nom"
                            required
                            value={data.nom}
                            onChange={(e) => setData('nom', e.target.value)}
                            placeholder="ex: Département des Mathématiques"
                            className="mt-1 h-9 text-sm"
                        />
                        {errors.nom && <p className="text-rose-500 text-xs mt-1">{errors.nom}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
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

                        <div>
                            <Label htmlFor="dep_chef" className="text-xs font-semibold">
                                Chef de Département
                            </Label>
                            <Select
                                value={data.chef_departement_id ? String(data.chef_departement_id) : 'none'}
                                onValueChange={(val) => setData('chef_departement_id', val === 'none' ? '' : Number(val))}
                            >
                                <SelectTrigger id="dep_chef" className="mt-1 h-9 text-sm">
                                    <SelectValue placeholder="Aucun chef désigné" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Aucun chef désigné</SelectItem>
                                    {eligibleChefs.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nom} {p.prenom} ({p.matricule})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="dep_desc" className="text-xs font-semibold">
                            Description ou objectifs pédagogiques
                        </Label>
                        <Textarea
                            id="dep_desc"
                            rows={3}
                            value={data.description}
                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('description', e.target.value)}
                            placeholder="ex: Coordination des programmes de mathématiques, organisation des devoirs communs..."
                            className="mt-1 text-xs"
                        />
                    </div>

                    <div className="flex items-center gap-2 pt-1">
                        <Checkbox
                            id="dep_is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) => setData('is_active', !!checked)}
                        />
                        <Label htmlFor="dep_is_active" className="text-xs font-medium cursor-pointer">
                            Département actif dans l'institution
                        </Label>
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
                            className="bg-teal-600 hover:bg-teal-700 text-white cursor-pointer font-semibold"
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

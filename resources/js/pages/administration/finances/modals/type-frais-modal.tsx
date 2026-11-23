import React, { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Calendar, CheckCircle2, FolderPlus, Layers, Plus, Tag, X } from 'lucide-react';
import { toast } from 'sonner';
import { formatDateForInput } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { CategorieFrais, TypeFrais } from '@/types/finance';

/**
 * Propriétés de la modale de configuration d'un Frais du Catalogue.
 */
interface TypeFraisModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissementId: number;
    categoriesFrais?: CategorieFrais[];
    typeFraisToEdit?: TypeFrais | null;
}

/**
 * Modale de création et de modification d'un Frais dans le Catalogue des Frais (`01. Catalogue des Frais`).
 *
 * Permet de configurer un frais une seule fois (catégorie, libellé, montant, ordre de priorité et date butoir)
 * de manière épurée et sans répétition. Inclut la création rapide d'une Catégorie de Frais ainsi qu'un bouton
 * « Enregistrer & Nouveau » pour enchaîner les saisies rapidement.
 *
 * @param {TypeFraisModalProps} props Propriétés du composant
 * @returns {JSX.Element} Modale du Catalogue des Frais
 */
export default function TypeFraisModal({
    open,
    onOpenChange,
    etablissementId,
    categoriesFrais = [],
    typeFraisToEdit = null,
}: TypeFraisModalProps) {
    const isEditing = Boolean(typeFraisToEdit);

    // Mini-formulaire intégré de création rapide d'une catégorie (Libellé + Description)
    const [showNewCategorieForm, setShowNewCategorieForm] = useState(false);
    const [newCatNom, setNewCatNom] = useState('');
    const [newCatDescription, setNewCatDescription] = useState('');
    const [creatingCat, setCreatingCat] = useState(false);
    const [pendingSelectCatName, setPendingSelectCatName] = useState<string | null>(null);
    const [savingAndNew, setSavingAndNew] = useState(false);

    const { data, setData, post, put, processing, reset, errors, transform } = useForm({
        etablissement_id: etablissementId,
        categorie_frais_id: '',
        libelle: '',
        description: '',
        obligatoire: true,
        ordre_priorite: '2',
        montant_defaut: '',
        date_limite_defaut: '',
    });

    /**
     * Sélectionne automatiquement la catégorie nouvellement créée dès que Inertia rafraîchit `categoriesFrais`.
     */
    useEffect(() => {
        if (!pendingSelectCatName) return;
        const found = categoriesFrais.find(
            (c) => c.nom.trim().toLowerCase() === pendingSelectCatName.trim().toLowerCase()
        );
        if (found) {
            setData('categorie_frais_id', String(found.id));
            setPendingSelectCatName(null);
        }
    }, [categoriesFrais, pendingSelectCatName]);

    /**
     * Synchronise les champs du formulaire à l'ouverture en mode création ou édition.
     */
    useEffect(() => {
        if (!open) {
            setShowNewCategorieForm(false);
            return;
        }

        if (typeFraisToEdit) {
            setData({
                etablissement_id: typeFraisToEdit.etablissement_id || etablissementId,
                categorie_frais_id: typeFraisToEdit.categorie_frais_id
                    ? String(typeFraisToEdit.categorie_frais_id)
                    : '',
                libelle: typeFraisToEdit.libelle,
                description: typeFraisToEdit.description || '',
                obligatoire: Boolean(typeFraisToEdit.obligatoire),
                ordre_priorite: String(typeFraisToEdit.ordre_priorite ?? 2),
                montant_defaut:
                    typeFraisToEdit.montant_defaut !== undefined && typeFraisToEdit.montant_defaut !== null
                        ? String(Number(typeFraisToEdit.montant_defaut))
                        : '',
                date_limite_defaut: formatDateForInput(typeFraisToEdit.date_limite_defaut),
            });
        } else {
            const defaultCat = categoriesFrais[0];
            setData({
                etablissement_id: etablissementId,
                categorie_frais_id: defaultCat ? String(defaultCat.id) : '',
                libelle: '',
                description: '',
                obligatoire: true,
                ordre_priorite: '2',
                montant_defaut: '',
                date_limite_defaut: '',
            });
        }
    }, [typeFraisToEdit, etablissementId, open]);

    /**
     * Enregistre à la volée une nouvelle Catégorie de Frais (Libellé + Description uniquement).
     */
    const handleCreateCategorieInline = () => {
        const nom = newCatNom.trim();
        if (!nom) {
            toast.error('Veuillez saisir le libellé de la catégorie.', { position: 'top-right' });
            return;
        }

        setCreatingCat(true);
        router.post(
            '/administration/finances/categories-frais',
            {
                etablissement_id: etablissementId,
                nom,
                description: newCatDescription.trim() || null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success(`Catégorie « ${nom} » créée avec succès.`, {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    setPendingSelectCatName(nom);
                    setNewCatNom('');
                    setNewCatDescription('');
                    setShowNewCategorieForm(false);
                },
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    toast.error(first || 'Impossible de créer cette catégorie.', {
                        position: 'top-right',
                    });
                },
                onFinish: () => setCreatingCat(false),
            }
        );
    };

    /**
     * Enregistre le frais courant et réinitialise les champs pour permettre
     * une saisie rapide à la chaîne sans fermer la modale, en conservant la catégorie active.
     */
    const handleSaveAndNew = () => {
        if (!data.libelle.trim() || data.montant_defaut === '') {
            toast.error('Veuillez renseigner au minimum le libellé et le montant du frais.', {
                position: 'top-right',
            });
            return;
        }

        setSavingAndNew(true);
        router.post(
            '/administration/finances/types-frais',
            {
                etablissement_id: etablissementId,
                categorie_frais_id: data.categorie_frais_id
                    ? parseInt(String(data.categorie_frais_id), 10)
                    : null,
                libelle: data.libelle.trim(),
                description: null,
                obligatoire: true,
                ordre_priorite: data.ordre_priorite
                    ? parseInt(String(data.ordre_priorite), 10)
                    : 10,
                montant_defaut: data.montant_defaut
                    ? parseFloat(String(data.montant_defaut))
                    : 0,
                date_limite_defaut: data.date_limite_defaut ? data.date_limite_defaut : null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success(`Frais « ${data.libelle.trim()} » ajouté avec succès.`, {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    // Conserve la catégorie et la priorité sélectionnées, réinitialise libellé, montant et date butoir
                    setData((prev) => ({
                        ...prev,
                        libelle: '',
                        montant_defaut: '',
                        date_limite_defaut: '',
                    }));
                },
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    toast.error(first || 'Erreur lors de l’enregistrement du frais.', {
                        position: 'top-right',
                    });
                },
                onFinish: () => setSavingAndNew(false),
            }
        );
    };

    /**
     * Soumet la création ou la mise à jour du frais au serveur.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            etablissement_id: etablissementId,
            categorie_frais_id: formValues.categorie_frais_id
                ? parseInt(String(formValues.categorie_frais_id), 10)
                : null,
            ordre_priorite: formValues.ordre_priorite
                ? parseInt(String(formValues.ordre_priorite), 10)
                : 10,
            montant_defaut: formValues.montant_defaut
                ? parseFloat(String(formValues.montant_defaut))
                : 0,
            date_limite_defaut: formValues.date_limite_defaut ? formValues.date_limite_defaut : null,
        }));

        if (isEditing && typeFraisToEdit) {
            put(`/administration/finances/types-frais/${typeFraisToEdit.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Frais mis à jour et synchronisé sur les niveaux rattachés.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    reset();
                    onOpenChange(false);
                },
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    toast.error(first || 'Erreur lors de la mise à jour du frais.', {
                        position: 'top-right',
                    });
                },
            });
            return;
        }

        post('/administration/finances/types-frais', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Frais ajouté avec succès dans le catalogue.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                const first = Object.values(err)[0] as string;
                toast.error(first || 'Erreur lors de l’enregistrement du frais.', {
                    position: 'top-right',
                });
            },
        });
    };

    const nbNiveauxRattaches = typeFraisToEdit?.frais_scolaires?.length ?? 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-slate-800">
                        <Tag className="w-5 h-5 text-emerald-600 shrink-0" />
                        {isEditing ? 'Modifier le Frais du Catalogue' : 'Configurer un Nouveau Frais'}
                    </DialogTitle>
                    <DialogDescription className="text-xs">
                        Définissez les paramètres de base du frais (catégorie, libellé, montant, priorité et date butoir).
                    </DialogDescription>
                </DialogHeader>

                {isEditing && nbNiveauxRattaches > 0 && (
                    <div className="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2">
                        <Layers className="w-4 h-4 text-emerald-600 shrink-0" />
                        <span>
                            Ce frais est actuellement rattaché à{' '}
                            <strong>{nbNiveauxRattaches} niveau(x)</strong> : toute modification mettra automatiquement à jour ces niveaux et les dossiers élèves.
                        </span>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-3.5 min-w-0">
                    {/* 1. Catégorie de Frais + Création rapide intégrée */}
                    <div className="space-y-1.5 min-w-0">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor="frais_categorie" className="text-xs font-semibold">
                                Catégorie de Frais <span className="text-red-500">*</span>
                            </Label>
                            <button
                                type="button"
                                onClick={() => setShowNewCategorieForm((prev) => !prev)}
                                className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1 cursor-pointer"
                            >
                                {showNewCategorieForm ? (
                                    <>
                                        <X className="w-3.5 h-3.5" /> Fermer
                                    </>
                                ) : (
                                    <>
                                        <Plus className="w-3.5 h-3.5" /> Nouvelle catégorie
                                    </>
                                )}
                            </button>
                        </div>

                        {showNewCategorieForm ? (
                            <div className="p-3 rounded-lg border border-emerald-200 bg-emerald-50/50 space-y-2.5 min-w-0">
                                <div className="flex items-center gap-1.5 text-xs font-bold text-emerald-800">
                                    <FolderPlus className="w-3.5 h-3.5" />
                                    <span>Nouvelle Catégorie de Frais</span>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="new_cat_nom" className="text-[11px]">
                                        Libellé de la catégorie <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="new_cat_nom"
                                        placeholder="Ex: Scolarité, Frais d'Examen, Fête de fin d'année..."
                                        value={newCatNom}
                                        onChange={(e) => setNewCatNom(e.target.value)}
                                        className="h-8 text-xs bg-white"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="new_cat_desc" className="text-[11px]">
                                        Description (optionnel)
                                    </Label>
                                    <Input
                                        id="new_cat_desc"
                                        placeholder="Ex: Regroupe CEPE, BEPC et examens blancs"
                                        value={newCatDescription}
                                        onChange={(e) => setNewCatDescription(e.target.value)}
                                        className="h-8 text-xs bg-white"
                                    />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 text-xs"
                                        onClick={() => setShowNewCategorieForm(false)}
                                    >
                                        Annuler
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={creatingCat || !newCatNom.trim()}
                                        onClick={handleCreateCategorieInline}
                                        className="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                                    >
                                        {creatingCat ? 'Création...' : 'Ajouter la catégorie'}
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="min-w-0">
                                <SearchableSelect
                                    id="frais_categorie"
                                    options={categoriesFrais.map((cat) => ({
                                        value: String(cat.id),
                                        label: cat.nom,
                                    }))}
                                    value={data.categorie_frais_id}
                                    onValueChange={(val) => setData('categorie_frais_id', val)}
                                    placeholder="Sélectionner une catégorie (Scolarité, Examen, Fête...)"
                                    searchPlaceholder="Rechercher une catégorie..."
                                    emptyMessage="Aucune catégorie trouvée."
                                    className="h-9 text-xs font-normal"
                                />
                            </div>
                        )}
                        {errors.categorie_frais_id && (
                            <p className="text-xs text-red-500">{errors.categorie_frais_id}</p>
                        )}
                    </div>

                    {/* 2. Libellé explicite du frais */}
                    <div className="space-y-1">
                        <Label htmlFor="type_libelle" className="text-xs font-semibold">
                            Libellé du Frais <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            id="type_libelle"
                            placeholder="Ex: Inscription 6EME-4EME"
                            value={data.libelle}
                            onChange={(e) => setData('libelle', e.target.value)}
                            className="h-9 text-xs font-medium"
                            required
                        />
                        {errors.libelle && <p className="text-xs text-red-500">{errors.libelle}</p>}
                    </div>

                    {/* 3. Montant & Ordre de priorité */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="type_montant" className="text-xs font-semibold">
                                Montant du Frais (FCFA) <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="type_montant"
                                type="number"
                                min="0"
                                step="100"
                                placeholder="Ex: 25000"
                                value={data.montant_defaut}
                                onChange={(e) => setData('montant_defaut', e.target.value)}
                                className="h-9 text-xs font-bold tabular-nums text-right"
                                required
                            />
                            {errors.montant_defaut && (
                                <p className="text-xs text-red-500">{errors.montant_defaut}</p>
                            )}
                        </div>

                        <div className="space-y-1 min-w-0">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="type_priorite" className="text-xs font-semibold">
                                    Ordre de Priorité <span className="text-red-500">*</span>
                                </Label>
                                <Badge variant="outline" className="text-[10px] py-0 px-1.5">
                                    1 = coupé en 1er
                                </Badge>
                            </div>
                            <Input
                                id="type_priorite"
                                type="number"
                                min={1}
                                max={99}
                                value={data.ordre_priorite}
                                onChange={(e) => setData('ordre_priorite', e.target.value)}
                                className="h-9 text-xs font-bold tabular-nums"
                                required
                            />
                        </div>
                    </div>

                    {/* 4. Date butoir / Date limite */}
                    <div className="space-y-1">
                        <Label htmlFor="type_date_limite" className="text-xs font-semibold flex items-center gap-1.5">
                            <Calendar className="w-3.5 h-3.5 text-emerald-600" />
                            <span>Date Butoir / Échéance</span>
                            <span className="text-slate-400 font-normal">(optionnel — vide = dès l'inscription)</span>
                        </Label>
                        <Input
                            id="type_date_limite"
                            type="date"
                            value={formatDateForInput(data.date_limite_defaut)}
                            onChange={(e) => setData('date_limite_defaut', e.target.value)}
                            className="h-9 text-xs"
                        />
                        {errors.date_limite_defaut && (
                            <p className="text-xs text-red-500">{errors.date_limite_defaut}</p>
                        )}
                    </div>

                    <DialogFooter className="pt-2 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing || savingAndNew}
                            className="text-xs h-9"
                        >
                            Annuler
                        </Button>
                        {!isEditing && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing || savingAndNew || !data.libelle.trim() || data.montant_defaut === ''}
                                onClick={handleSaveAndNew}
                                className="text-xs h-9 font-semibold border-emerald-300 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 flex items-center gap-1.5"
                            >
                                <Plus className="w-3.5 h-3.5" />
                                {savingAndNew ? 'Enregistrement...' : 'Enregistrer & Nouveau'}
                            </Button>
                        )}
                        <Button
                            type="submit"
                            disabled={processing || savingAndNew || !data.libelle.trim() || data.montant_defaut === ''}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs h-9 flex items-center gap-1.5"
                        >
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            {processing
                                ? 'Enregistrement...'
                                : isEditing
                                  ? 'Enregistrer & Synchroniser'
                                  : 'Enregistrer dans le Catalogue'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

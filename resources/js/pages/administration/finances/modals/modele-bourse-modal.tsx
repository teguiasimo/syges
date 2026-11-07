import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
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
import { BourseRemise, CategorieFinanciere, CibleImputationRemise, formatCibleImputationLabel } from '@/types/finance';
import { Award, Check, Plus, Sparkles, X } from 'lucide-react';

/**
 * Propriétés de la modale minimaliste de création / édition d'un Modèle de Bourse ou Remise.
 */
interface ModeleBourseModalProps {
    isOpen: boolean;
    onClose: () => void;
    etablissementId: number;
    anneeId?: number;
    categories?: CategorieFinanciere[];
    modelToEdit?: BourseRemise | null;
}

/**
 * Catégories standards proposées par défaut (incluant en tête le Paiement en avance avant rentrée).
 */
const DEFAULT_CATEGORIES = [
    'Paiement en avance (avant rentrée)',
    'Fratrie / Famille nombreuse',
    'Enfant du personnel',
    'Excellence scolaire',
    'Sociale / Orphelin',
];

/**
 * Modale épurée de création et modification d'un modèle de Bourse, Bonus ou Remise.
 *
 * Se concentre strictement sur l'essentiel : Type, Catégorie personnalisable, Libellé et Montant/Pourcentage.
 * Toute remise créée ici s'applique individuellement à un élève (depuis son dossier) et s'impute
 * automatiquement en commençant par sa dernière tranche de scolarité.
 *
 * @param {ModeleBourseModalProps} props Propriétés du composant
 * @returns {JSX.Element} Modale de modèle de bourse/remise
 */
export default function ModeleBourseModal({
    isOpen,
    onClose,
    etablissementId,
    anneeId,
    categories = [],
    modelToEdit,
}: ModeleBourseModalProps) {
    const isEditing = Boolean(modelToEdit);

    const categoryNames = categories.length > 0
        ? categories.map((c) => c.nom)
        : DEFAULT_CATEGORIES;

    const [type, setType] = useState<'bourse' | 'remise'>('remise');
    const [categorie, setCategorie] = useState<string>(categoryNames[0] || DEFAULT_CATEGORIES[0]);
    const [isAddingCategory, setIsAddingCategory] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');
    const [isSavingCategory, setIsSavingCategory] = useState(false);

    const [nom, setNom] = useState('');
    const [modeCalcul, setModeCalcul] = useState<'pourcentage' | 'montant_fixe'>('montant_fixe');
    const [valeur, setValeur] = useState('');
    const [cibleImputation, setCibleImputation] = useState<CibleImputationRemise>('derniere_tranche');
    const [isActive, setIsActive] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);

    /**
     * Initialise le formulaire en mode création ou édition à chaque ouverture de la modale.
     */
    useEffect(() => {
        if (!isOpen) return;

        setIsAddingCategory(false);
        setNewCategoryName('');

        if (modelToEdit) {
            setType(modelToEdit.type);
            setCategorie(modelToEdit.categorie || categoryNames[0] || DEFAULT_CATEGORIES[0]);
            setNom(modelToEdit.nom);
            setModeCalcul(modelToEdit.mode_calcul);
            setValeur(String(Number(modelToEdit.valeur)));
            setCibleImputation(modelToEdit.cible_imputation || 'derniere_tranche');
            setIsActive(modelToEdit.is_active);
        } else {
            setType('remise');
            setCategorie(categoryNames[0] || DEFAULT_CATEGORIES[0]);
            setNom('');
            setModeCalcul('montant_fixe');
            setValeur('');
            setCibleImputation('derniere_tranche');
            setIsActive(true);
        }
    }, [modelToEdit, isOpen]);

    /**
     * Crée à la volée une nouvelle catégorie personnalisée de bourse/remise et la sélectionne.
     */
    const handleCreateCategory = () => {
        const nomTrim = newCategoryName.trim();
        if (!nomTrim) return;

        setIsSavingCategory(true);
        router.post(
            '/administration/finances/categories-bourses',
            {
                etablissement_id: etablissementId,
                nom: nomTrim,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setCategorie(nomTrim);
                    setNewCategoryName('');
                    setIsAddingCategory(false);
                    toast.success(`Catégorie « ${nomTrim} » créée et sélectionnée.`, {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                },
                onError: () => {
                    toast.error('Impossible de créer cette catégorie.', { position: 'top-right' });
                },
                onFinish: () => setIsSavingCategory(false),
            }
        );
    };

    /**
     * Soumet le modèle de bourse ou remise au backend.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const valNum = parseFloat(valeur);
        if (!nom.trim() || !categorie.trim() || isNaN(valNum) || valNum <= 0) {
            toast.error('Veuillez renseigner le libellé et une valeur positive.', {
                position: 'top-right',
            });
            return;
        }

        setIsSubmitting(true);

        const payload = {
            etablissement_id: etablissementId,
            annee_scolaire_id: anneeId || null,
            nom: nom.trim(),
            type,
            categorie: categorie.trim(),
            mode_calcul: modeCalcul,
            valeur: valNum,
            cible_imputation: cibleImputation,
            is_active: isActive,
        };

        if (isEditing && modelToEdit) {
            router.put(
                `/administration/finances/bourses-remises/${modelToEdit.id}`,
                payload,
                {
                    onSuccess: () => {
                        toast.success('Modèle mis à jour avec succès.', {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        });
                        onClose();
                    },
                    onError: (errors) => {
                        const first = Object.values(errors)[0] as string;
                        toast.error(first || 'Erreur lors de la mise à jour.', { position: 'top-right' });
                    },
                    onFinish: () => setIsSubmitting(false),
                }
            );
        } else {
            router.post(
                '/administration/finances/bourses-remises',
                payload,
                {
                    onSuccess: () => {
                        toast.success(`Modèle « ${nom.trim()} » créé avec succès.`, {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        });
                        onClose();
                    },
                    onError: (errors) => {
                        const first = Object.values(errors)[0] as string;
                        toast.error(first || 'Erreur lors de la création du modèle.', { position: 'top-right' });
                    },
                    onFinish: () => setIsSubmitting(false),
                }
            );
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-slate-900 dark:text-slate-100">
                        <Award className="w-5 h-5 text-emerald-600 shrink-0" />
                        {isEditing ? 'Modifier le Modèle' : 'Nouveau Modèle de Bourse ou Remise'}
                    </DialogTitle>
                    <DialogDescription>
                        Créez une réduction réutilisable à attribuer individuellement dans le dossier d'un élève.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3.5 py-1 min-w-0">
                    {/* Type */}
                    <div className="space-y-1">
                        <Label htmlFor="bourse_type">Nature de l'avantage <span className="text-red-500">*</span></Label>
                        <Select value={type} onValueChange={(val: 'bourse' | 'remise') => setType(val)}>
                            <SelectTrigger id="bourse_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="remise">Remise / Bonus (ex: Paiement en avance, Fratrie)</SelectItem>
                                <SelectItem value="bourse">Bourse d'études / Prise en charge</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Catégorie personnalisable */}
                    <div className="space-y-1.5 min-w-0">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="bourse_categorie">Catégorie <span className="text-red-500">*</span></Label>
                            <button
                                type="button"
                                onClick={() => setIsAddingCategory((prev) => !prev)}
                                className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1 cursor-pointer"
                            >
                                {isAddingCategory ? (
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

                        {isAddingCategory ? (
                            <div className="flex items-center gap-1.5 p-2 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                                <Input
                                    placeholder="Ex: Partenariat entreprise, Parrainage..."
                                    value={newCategoryName}
                                    onChange={(e) => setNewCategoryName(e.target.value)}
                                    className="h-8 text-xs bg-white dark:bg-slate-900"
                                    autoFocus
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            handleCreateCategory();
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={isSavingCategory || !newCategoryName.trim()}
                                    onClick={handleCreateCategory}
                                    className="h-8 px-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs shrink-0"
                                >
                                    <Check className="w-3.5 h-3.5 mr-1" /> Créer
                                </Button>
                            </div>
                        ) : (
                            <Select value={categorie} onValueChange={setCategorie}>
                                <SelectTrigger id="bourse_categorie">
                                    <SelectValue placeholder="Sélectionner une catégorie" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.from(new Set([...categoryNames, ...(categorie ? [categorie] : [])])).map((cat) => (
                                        <SelectItem key={cat} value={cat}>
                                            {cat}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </div>

                    {/* Intitulé */}
                    <div className="space-y-1">
                        <Label htmlFor="bourse_nom">Libellé du modèle <span className="text-red-500">*</span></Label>
                        <Input
                            id="bourse_nom"
                            placeholder="ex: Bonus paiement avant la rentrée (-3 000 FCFA)"
                            value={nom}
                            onChange={(e) => setNom(e.target.value)}
                            required
                        />
                    </div>

                    {/* Mode de calcul & Valeur */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="modeCalcul">Mode de calcul <span className="text-red-500">*</span></Label>
                            <Select value={modeCalcul} onValueChange={(val: 'pourcentage' | 'montant_fixe') => setModeCalcul(val)}>
                                <SelectTrigger id="modeCalcul">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="montant_fixe">Montant fixe (FCFA)</SelectItem>
                                    <SelectItem value="pourcentage">Pourcentage (%)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="valeur">
                                {modeCalcul === 'pourcentage' ? 'Taux (%)' : 'Montant (FCFA)'} <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="valeur"
                                type="number"
                                min="0.01"
                                step="any"
                                placeholder={modeCalcul === 'pourcentage' ? 'ex: 15' : 'ex: 3000'}
                                value={valeur}
                                onChange={(e) => setValeur(e.target.value)}
                                className="font-bold tabular-nums"
                                required
                            />
                        </div>
                    </div>

                    {/* Tranche cible d'imputation */}
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="cibleImputation">Sur quelle tranche déduire par défaut ? <span className="text-red-500">*</span></Label>
                        <Select
                            value={cibleImputation}
                            onValueChange={(val: CibleImputationRemise) => setCibleImputation(val)}
                        >
                            <SelectTrigger id="cibleImputation">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="derniere_tranche">Dernière tranche (recommandé pour bonus anticipation)</SelectItem>
                                <SelectItem value="tranche_1">1ère tranche de scolarité</SelectItem>
                                <SelectItem value="tranche_2">2ème tranche de scolarité</SelectItem>
                                <SelectItem value="tranche_3">3ème tranche de scolarité</SelectItem>
                                <SelectItem value="proportionnelle">Répartition proportionnelle sur toutes les tranches</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Rappel métier clair */}
                    <div className="p-2.5 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200/70 dark:border-emerald-800/50 text-[11px] text-emerald-900 dark:text-emerald-200 flex items-start gap-2">
                        <Sparkles className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                        <span>
                            S'applique <strong>individuellement par élève</strong> sur <strong>{formatCibleImputationLabel(cibleImputation).toLowerCase()}</strong> (modifiable dans le dossier de l'enfant si besoin).
                        </span>
                    </div>

                    {isEditing && (
                        <label className="flex items-start gap-2.5 text-xs cursor-pointer pt-0.5">
                            <input
                                type="checkbox"
                                checked={isActive}
                                onChange={(e) => setIsActive(e.target.checked)}
                                className="mt-0.5 shrink-0 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <span className="leading-snug text-slate-700 dark:text-slate-300">Modèle actif et disponible dans les dossiers élèves</span>
                        </label>
                    )}

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isSubmitting} className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white">
                            {isSubmitting ? 'Enregistrement...' : isEditing ? 'Mettre à jour' : 'Enregistrer le modèle'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

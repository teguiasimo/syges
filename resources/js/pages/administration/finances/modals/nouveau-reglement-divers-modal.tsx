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
import { formatDateForInput } from '@/lib/utils';
import { CategorieFinanciere, ReglementDivers } from '@/types/finance';
import { Check, Plus, Receipt, X } from 'lucide-react';

/**
 * Propriétés de la modale d'enregistrement et de modification d'une Recette Diverse hors scolarité.
 */
interface NouveauReglementDiversModalProps {
    isOpen: boolean;
    onClose: () => void;
    etablissementId: number;
    categories?: CategorieFinanciere[];
    reglementToEdit?: ReglementDivers | null;
}

/**
 * Catégories par défaut : uniquement Cantine selon les consignes du projet.
 */
const DEFAULT_CATEGORIES = [
    'Cantine',
];

/**
 * Modale simplifiée d'enregistrement et de modification d'une Recette Diverse hors scolarité.
 *
 * Organisation ergonomique du formulaire :
 * - Ligne 1 : Catégorie de recette & Date de paiement (par défaut date du jour).
 * - Ligne 2 : Nom du payeur & Montant perçu (FCFA).
 * - Ligne 3 : Commentaire facultatif (anciennement libellé/motif).
 * - Ligne 4 : Mode de paiement & Téléphone du payeur.
 *
 * @param {NouveauReglementDiversModalProps} props Propriétés du composant
 * @returns {JSX.Element} Modale de recette diverse
 */
export default function NouveauReglementDiversModal({
    isOpen,
    onClose,
    etablissementId,
    categories = [],
    reglementToEdit,
}: NouveauReglementDiversModalProps) {
    const isEditing = Boolean(reglementToEdit);

    const categoryNames = categories.length > 0
        ? categories.map((c) => c.nom)
        : DEFAULT_CATEGORIES;

    const [categorie, setCategorie] = useState<string>('Cantine');
    const [isAddingCategory, setIsAddingCategory] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');
    const [isSavingCategory, setIsSavingCategory] = useState(false);

    const [datePaiement, setDatePaiement] = useState<string>(() => formatDateForInput(new Date()));
    const [payeurNom, setPayeurNom] = useState('');
    const [montant, setMontant] = useState('');
    const [commentaire, setCommentaire] = useState('');
    const [payeurTelephone, setPayeurTelephone] = useState('');
    const [modePaiement, setModePaiement] = useState<'especes' | 'cheque' | 'virement' | 'mobile_money_manuel'>('especes');
    const [referencePaiement, setReferencePaiement] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    /**
     * Garantit qu'une catégorie et les valeurs sont correctement initialisées à l'ouverture.
     */
    useEffect(() => {
        if (!isOpen) return;

        setIsAddingCategory(false);
        setNewCategoryName('');

        if (reglementToEdit) {
            setCategorie(reglementToEdit.categorie || 'Cantine');
            setDatePaiement(formatDateForInput(reglementToEdit.date_reglement));
            setPayeurNom(reglementToEdit.payeur_nom || '');
            setMontant(String(Number(reglementToEdit.montant)));
            setCommentaire(reglementToEdit.libelle !== reglementToEdit.categorie ? reglementToEdit.libelle : '');
            setPayeurTelephone(reglementToEdit.payeur_telephone || '');
            setModePaiement((reglementToEdit.mode_paiement as any) || 'especes');
            setReferencePaiement(reglementToEdit.reference_paiement || '');
        } else {
            setCategorie(categoryNames.includes('Cantine') ? 'Cantine' : (categoryNames[0] || 'Cantine'));
            setDatePaiement(formatDateForInput(new Date()));
            setPayeurNom('');
            setMontant('');
            setCommentaire('');
            setPayeurTelephone('');
            setModePaiement('especes');
            setReferencePaiement('');
        }
    }, [isOpen, reglementToEdit]);

    /**
     * Crée à la volée une nouvelle catégorie personnalisée de recette diverse et la sélectionne.
     */
    const handleCreateCategory = () => {
        const nomTrim = newCategoryName.trim();
        if (!nomTrim) return;

        setIsSavingCategory(true);
        router.post(
            '/administration/finances/categories-recettes',
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
     * Valide et enregistre ou met à jour la recette diverse.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const montantNum = parseFloat(montant);
        if (!payeurNom.trim() || !categorie.trim() || isNaN(montantNum) || montantNum <= 0) {
            toast.error('Veuillez renseigner le nom du payeur, la catégorie et un montant valide.', {
                position: 'top-right',
            });
            return;
        }

        setIsSubmitting(true);

        const payload = {
            etablissement_id: etablissementId,
            payeur_nom: payeurNom.trim(),
            payeur_telephone: payeurTelephone.trim() || null,
            categorie: categorie.trim(),
            libelle: commentaire.trim() || categorie.trim(),
            montant: montantNum,
            date_paiement: datePaiement,
            mode_paiement: modePaiement,
            reference_paiement: modePaiement !== 'especes' ? (referencePaiement.trim() || null) : null,
        };

        if (isEditing && reglementToEdit) {
            router.put(
                `/administration/finances/reglements-divers/${reglementToEdit.id}`,
                payload,
                {
                    onSuccess: () => {
                        toast.success('Recette diverse modifiée avec succès.', {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        });
                        onClose();
                    },
                    onError: (errors) => {
                        const firstError = Object.values(errors)[0] as string;
                        toast.error(firstError || "Erreur lors de la modification de la recette.", {
                            position: 'top-right',
                        });
                    },
                    onFinish: () => setIsSubmitting(false),
                }
            );
        } else {
            router.post(
                '/administration/finances/reglements-divers',
                payload,
                {
                    onSuccess: () => {
                        toast.success('Recette diverse encaissée avec succès.', {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        });
                        onClose();
                    },
                    onError: (errors) => {
                        const firstError = Object.values(errors)[0] as string;
                        toast.error(firstError || "Erreur lors de l'enregistrement de la recette.", {
                            position: 'top-right',
                        });
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
                        <Receipt className="w-5 h-5 text-emerald-600 shrink-0" />
                        {isEditing ? 'Modifier la Recette Diverse' : 'Nouvelle Recette Diverse'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Mettez à jour les informations de cet encaissement hors scolarité.'
                            : 'Encaissement rapide hors scolarité (cantine, etc.).'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3.5 py-1 min-w-0">
                    {/* LIGNE 1 : Catégorie de recette & Date de paiement */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="categorie_recette">
                                    Catégorie <span className="text-red-500">*</span>
                                </Label>
                                <button
                                    type="button"
                                    onClick={() => setIsAddingCategory((prev) => !prev)}
                                    className="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-0.5 cursor-pointer"
                                >
                                    {isAddingCategory ? (
                                        <>
                                            <X className="w-3 h-3" /> Fermer
                                        </>
                                    ) : (
                                        <>
                                            <Plus className="w-3 h-3" /> Nouvelle
                                        </>
                                    )}
                                </button>
                            </div>

                            {isAddingCategory ? (
                                <div className="flex items-center gap-1.5 p-1.5 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                                    <Input
                                        placeholder="Ex: Cantine..."
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
                                        className="h-8 px-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs shrink-0"
                                    >
                                        <Check className="w-3.5 h-3.5" />
                                    </Button>
                                </div>
                            ) : (
                                <Select value={categorie} onValueChange={setCategorie}>
                                    <SelectTrigger id="categorie_recette" className="h-9 text-xs">
                                        <SelectValue placeholder="Choisir une catégorie" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Array.from(new Set([...categoryNames, ...(categorie ? [categorie] : [])])).map((cat) => (
                                            <SelectItem key={cat} value={cat} className="text-xs">
                                                {cat}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>

                        {/* Date de paiement (par défaut date du jour) */}
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="datePaiement">
                                Date de paiement <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="datePaiement"
                                type="date"
                                value={datePaiement}
                                onChange={(e) => setDatePaiement(e.target.value)}
                                className="h-9 text-xs"
                                required
                            />
                        </div>
                    </div>

                    {/* LIGNE 2 : Nom du payeur & Montant perçu */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="payeurNom">
                                Nom du payeur <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="payeurNom"
                                placeholder="Nom et prénom"
                                value={payeurNom}
                                onChange={(e) => setPayeurNom(e.target.value)}
                                className="h-9 text-xs"
                                required
                            />
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="montant">
                                Montant (FCFA) <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="montant"
                                type="number"
                                min="1"
                                placeholder="ex: 15000"
                                value={montant}
                                onChange={(e) => setMontant(e.target.value)}
                                className="h-9 font-bold tabular-nums text-xs"
                                required
                            />
                        </div>
                    </div>

                    {/* LIGNE 3 : Commentaire (non obligatoire) */}
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="commentaire">
                            Commentaire <span className="text-slate-400 font-normal text-xs">(optionnel)</span>
                        </Label>
                        <Input
                            id="commentaire"
                            placeholder="Détails, motif ou précisions éventuelles..."
                            value={commentaire}
                            onChange={(e) => setCommentaire(e.target.value)}
                            className="h-9 text-xs"
                        />
                    </div>

                    {/* LIGNE 4 : Mode de paiement & Téléphone payeur */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="modePaiement">
                                Mode de paiement <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={modePaiement}
                                onValueChange={(val: 'especes' | 'cheque' | 'virement' | 'mobile_money_manuel') => setModePaiement(val)}
                            >
                                <SelectTrigger id="modePaiement" className="h-9 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="especes" className="text-xs">Espèces</SelectItem>
                                    <SelectItem value="mobile_money_manuel" className="text-xs">Orange / MTN Money</SelectItem>
                                    <SelectItem value="virement" className="text-xs">Virement bancaire</SelectItem>
                                    <SelectItem value="cheque" className="text-xs">Chèque</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="payeurTelephone">
                                Téléphone payeur <span className="text-slate-400 font-normal text-xs">(optionnel)</span>
                            </Label>
                            <Input
                                id="payeurTelephone"
                                placeholder="6XX XXX XXX"
                                value={payeurTelephone}
                                onChange={(e) => setPayeurTelephone(e.target.value)}
                                className="h-9 text-xs"
                            />
                        </div>
                    </div>

                    {modePaiement !== 'especes' && (
                        <div className="space-y-1">
                            <Label htmlFor="referencePaiement">Référence transaction / N° Chèque</Label>
                            <Input
                                id="referencePaiement"
                                placeholder="Numéro de transaction ou référence"
                                value={referencePaiement}
                                onChange={(e) => setReferencePaiement(e.target.value)}
                                className="h-9 text-xs"
                            />
                        </div>
                    )}

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto text-xs" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isSubmitting} className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white text-xs">
                            {isSubmitting ? 'Enregistrement...' : isEditing ? 'Mettre à jour' : 'Encaisser & Émettre le reçu'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

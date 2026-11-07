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
import { CategorieFinanciere } from '@/types/finance';
import { Check, Plus, Receipt, X } from 'lucide-react';

/**
 * Propriétés de la modale d'enregistrement d'une Recette Diverse hors scolarité.
 */
interface NouveauReglementDiversModalProps {
    isOpen: boolean;
    onClose: () => void;
    etablissementId: number;
    categories?: CategorieFinanciere[];
}

/**
 * Catégories de secours affichées si aucune catégorie n'a encore été chargée.
 */
const DEFAULT_CATEGORIES = [
    "Vente d'uniformes & tenues",
    'Macarons & livrets scolaires',
    'Cantine libre / Tickets repas',
    'Location de salle / espace',
    'Dons & subventions',
    'Duplicata & documents officiels',
];

/**
 * Modale simplifiée d'enregistrement d'une Recette Diverse hors scolarité.
 *
 * Permet à l'utilisateur de choisir ou de créer librement ses propres catégories de recettes,
 * sans imposer de sélection de caisse ou d'agent encaisseur (résolus automatiquement via le compte connecté).
 *
 * @param {NouveauReglementDiversModalProps} props Propriétés du composant
 * @returns {JSX.Element} Modale de recette diverse
 */
export default function NouveauReglementDiversModal({
    isOpen,
    onClose,
    etablissementId,
    categories = [],
}: NouveauReglementDiversModalProps) {
    const categoryNames = categories.length > 0
        ? categories.map((c) => c.nom)
        : DEFAULT_CATEGORIES;

    const [categorie, setCategorie] = useState<string>(categoryNames[0] || DEFAULT_CATEGORIES[0]);
    const [isAddingCategory, setIsAddingCategory] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');
    const [isSavingCategory, setIsSavingCategory] = useState(false);

    const [payeurNom, setPayeurNom] = useState('');
    const [payeurTelephone, setPayeurTelephone] = useState('');
    const [libelle, setLibelle] = useState('');
    const [montant, setMontant] = useState('');
    const [modePaiement, setModePaiement] = useState<'especes' | 'cheque' | 'virement' | 'mobile_money_manuel'>('especes');
    const [referencePaiement, setReferencePaiement] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    /**
     * Garantit qu'une catégorie valide est sélectionnée à l'ouverture.
     */
    useEffect(() => {
        if (isOpen && (!categorie || !categoryNames.includes(categorie))) {
            setCategorie(categoryNames[0] || DEFAULT_CATEGORIES[0]);
        }
    }, [isOpen, categories.length]);

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
     * Valide et enregistre l'encaissement de la recette diverse.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const montantNum = parseFloat(montant);
        if (!payeurNom.trim() || !libelle.trim() || !categorie.trim() || isNaN(montantNum) || montantNum <= 0) {
            toast.error('Veuillez renseigner tous les champs essentiels avec un montant valide.', {
                position: 'top-right',
            });
            return;
        }

        setIsSubmitting(true);

        router.post(
            '/administration/finances/reglements-divers',
            {
                etablissement_id: etablissementId,
                payeur_nom: payeurNom.trim(),
                payeur_telephone: payeurTelephone.trim() || null,
                categorie: categorie.trim(),
                libelle: libelle.trim(),
                montant: montantNum,
                mode_paiement: modePaiement,
                reference_paiement: modePaiement !== 'especes' ? (referencePaiement.trim() || null) : null,
            },
            {
                onSuccess: () => {
                    toast.success('Recette diverse encaissée avec succès.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    onClose();
                    setPayeurNom('');
                    setPayeurTelephone('');
                    setLibelle('');
                    setMontant('');
                    setReferencePaiement('');
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
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-slate-900 dark:text-slate-100">
                        <Receipt className="w-5 h-5 text-emerald-600 shrink-0" />
                        Nouvelle Recette Diverse
                    </DialogTitle>
                    <DialogDescription>
                        Encaissement rapide hors scolarité (tenues, macarons, cantine libre, duplicatas, etc.).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3.5 py-1 min-w-0">
                    {/* Catégorie personnalisable */}
                    <div className="space-y-1.5 min-w-0">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="categorie_recette">Catégorie de recette <span className="text-red-500">*</span></Label>
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
                                    placeholder="Ex: Vente de cahiers d'exercices..."
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
                                <SelectTrigger id="categorie_recette">
                                    <SelectValue placeholder="Choisir une catégorie" />
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

                    {/* Désignation */}
                    <div className="space-y-1">
                        <Label htmlFor="libelle">Motif / Désignation <span className="text-red-500">*</span></Label>
                        <Input
                            id="libelle"
                            placeholder="ex: 2 Uniformes complets taille M + 1 Macaron"
                            value={libelle}
                            onChange={(e) => setLibelle(e.target.value)}
                            required
                        />
                    </div>

                    {/* Payeur & Téléphone */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="payeurNom">Nom du payeur <span className="text-red-500">*</span></Label>
                            <Input
                                id="payeurNom"
                                placeholder="Nom et prénom"
                                value={payeurNom}
                                onChange={(e) => setPayeurNom(e.target.value)}
                                required
                            />
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="payeurTelephone">Téléphone (optionnel)</Label>
                            <Input
                                id="payeurTelephone"
                                placeholder="6XX XXX XXX"
                                value={payeurTelephone}
                                onChange={(e) => setPayeurTelephone(e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Montant & Mode de règlement */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="montant">Montant perçu (FCFA) <span className="text-red-500">*</span></Label>
                            <Input
                                id="montant"
                                type="number"
                                min="1"
                                placeholder="ex: 15000"
                                value={montant}
                                onChange={(e) => setMontant(e.target.value)}
                                className="font-bold tabular-nums"
                                required
                            />
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="modePaiement">Mode de paiement <span className="text-red-500">*</span></Label>
                            <Select
                                value={modePaiement}
                                onValueChange={(val: 'especes' | 'cheque' | 'virement' | 'mobile_money_manuel') => setModePaiement(val)}
                            >
                                <SelectTrigger id="modePaiement">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="especes">Espèces</SelectItem>
                                    <SelectItem value="mobile_money_manuel">Orange / MTN Money</SelectItem>
                                    <SelectItem value="virement">Virement bancaire</SelectItem>
                                    <SelectItem value="cheque">Chèque</SelectItem>
                                </SelectContent>
                            </Select>
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
                            />
                        </div>
                    )}

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isSubmitting} className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white">
                            {isSubmitting ? 'Encaissement...' : 'Encaisser & Émettre le reçu'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

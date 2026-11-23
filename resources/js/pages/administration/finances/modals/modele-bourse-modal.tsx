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
import {
    BaseCalculRemise,
    BourseRemise,
    CibleImputationRemise,
} from '@/types/finance';
import { Award } from 'lucide-react';

/**
 * Propriétés de la modale de création / édition d'un Modèle de Bourse ou Remise.
 */
interface ModeleBourseModalProps {
    isOpen: boolean;
    onClose: () => void;
    etablissementId: number;
    anneeId?: number;
    categories?: unknown[];
    modelToEdit?: BourseRemise | null;
}

/**
 * Génère un libellé standardisé par défaut lorsqu'aucun nom personnalisé n'est saisi.
 *
 * @param {'REMISE' | 'BOURSE'} categorie Catégorie du modèle
 * @param {'pourcentage' | 'montant_fixe'} modeCalcul Mode de calcul
 * @param {string} valeurRaw Valeur brute saisie dans le formulaire
 * @returns {string} Libellé suggéré ou chaîne vide si aucune valeur positive n'est saisie
 */
function genererLibelleSuggestion(
    categorie: 'REMISE' | 'BOURSE',
    modeCalcul: 'pourcentage' | 'montant_fixe',
    valeurRaw: string
): string {
    const valNum = parseFloat(valeurRaw);
    if (isNaN(valNum) || valNum <= 0) return '';

    const prefixe = categorie === 'BOURSE' ? 'Bourse' : 'Remise';
    return modeCalcul === 'pourcentage'
        ? `${prefixe} ${valNum}%`
        : `${prefixe} ${valNum.toLocaleString('fr-FR')} FCFA`;
}

/**
 * Modale de création et modification d'un modèle de Bourse ou Remise.
 *
 * Permet de configurer :
 * - Le libellé explicite de la remise / bourse (ou génération automatique si laissé vide)
 * - Le mode de calcul (Montant fixe en FCFA ou Pourcentage)
 * - L'assiette de calcul en mode pourcentage (`total_scolarite` par défaut ou `tranche_ciblee`)
 * - La tranche d'imputation par défaut (modifiable lors de l'attribution à un élève)
 *
 * @param {ModeleBourseModalProps} props Propriétés du composant
 * @returns {JSX.Element} Modale de modèle de bourse/remise
 */
export default function ModeleBourseModal({
    isOpen,
    onClose,
    etablissementId,
    anneeId,
    modelToEdit,
}: ModeleBourseModalProps) {
    const isEditing = Boolean(modelToEdit);

    const [categorie, setCategorie] = useState<'REMISE' | 'BOURSE'>('REMISE');
    const [nom, setNom] = useState('');
    const [modeCalcul, setModeCalcul] = useState<'pourcentage' | 'montant_fixe'>('montant_fixe');
    const [valeur, setValeur] = useState('');
    const [baseCalcul, setBaseCalcul] = useState<BaseCalculRemise>('total_scolarite');
    const [cibleImputation, setCibleImputation] = useState<CibleImputationRemise>('derniere_tranche');
    const [isActive, setIsActive] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const libelleSuggestion = genererLibelleSuggestion(categorie, modeCalcul, valeur);

    /**
     * Initialise le formulaire en mode création ou édition à chaque ouverture de la modale.
     */
    useEffect(() => {
        if (!isOpen) return;

        if (modelToEdit) {
            const catNormalisee = (modelToEdit.categorie?.toUpperCase() === 'BOURSE' || modelToEdit.type === 'bourse')
                ? 'BOURSE'
                : 'REMISE';
            setCategorie(catNormalisee);
            setNom(modelToEdit.nom || '');
            setModeCalcul(modelToEdit.mode_calcul);
            setValeur(String(Number(modelToEdit.valeur)));
            setBaseCalcul(modelToEdit.base_calcul === 'tranche_ciblee' ? 'tranche_ciblee' : 'total_scolarite');
            setCibleImputation(modelToEdit.cible_imputation || 'derniere_tranche');
            setIsActive(modelToEdit.is_active);
        } else {
            setCategorie('REMISE');
            setNom('');
            setModeCalcul('montant_fixe');
            setValeur('');
            setBaseCalcul('total_scolarite');
            setCibleImputation('derniere_tranche');
            setIsActive(true);
        }
    }, [modelToEdit, isOpen]);

    /**
     * Soumet le modèle de bourse ou remise au backend.
     *
     * @param {React.FormEvent} e Événement de soumission du formulaire
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const valNum = parseFloat(valeur);
        if (isNaN(valNum) || valNum <= 0) {
            toast.error('Veuillez renseigner un montant ou un taux positif.', {
                position: 'top-right',
            });
            return;
        }

        const libelleFinal = nom.trim() || libelleSuggestion;
        if (!libelleFinal) {
            toast.error('Veuillez renseigner le libellé de la remise ou bourse.', {
                position: 'top-right',
            });
            return;
        }

        setIsSubmitting(true);

        const typeLower = categorie === 'BOURSE' ? 'bourse' : 'remise';

        const payload = {
            etablissement_id: etablissementId,
            annee_scolaire_id: anneeId || null,
            nom: libelleFinal,
            type: typeLower,
            categorie,
            mode_calcul: modeCalcul,
            valeur: valNum,
            base_calcul: modeCalcul === 'pourcentage' ? baseCalcul : 'total_scolarite',
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
                        toast.success(`Modèle « ${libelleFinal} » créé avec succès.`, {
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
            <DialogContent className="sm:max-w-lg max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-slate-900">
                        <Award className="w-5 h-5 text-emerald-600 shrink-0" />
                        {isEditing ? 'Modifier le Modèle' : 'Nouveau Modèle de Bourse ou Remise'}
                    </DialogTitle>
                    <DialogDescription>
                        Définissez une bourse ou une remise réutilisable avec son libellé, son mode de calcul
                        et sa tranche d'imputation par défaut.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2 min-w-0">
                    {/* Catégorie & Libellé explicite */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div className="space-y-1.5 min-w-0">
                            <Label htmlFor="bourse_categorie">
                                Catégorie <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={categorie}
                                onValueChange={(val: 'REMISE' | 'BOURSE') => setCategorie(val)}
                            >
                                <SelectTrigger id="bourse_categorie" className="h-9">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="REMISE">REMISE</SelectItem>
                                    <SelectItem value="BOURSE">BOURSE</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="sm:col-span-2 space-y-1.5 min-w-0">
                            <Label htmlFor="bourse_nom">
                                Libellé de la Remise / Bourse <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="bourse_nom"
                                type="text"
                                maxLength={150}
                                placeholder={
                                    libelleSuggestion
                                        ? `Suggestion : ${libelleSuggestion}`
                                        : 'ex: Remise Primaire (-3 000 FCFA)'
                                }
                                value={nom}
                                onChange={(e) => setNom(e.target.value)}
                                className="h-9"
                            />
                            {!nom.trim() && libelleSuggestion && (
                                <p className="text-[11px] text-slate-500">
                                    Sera enregistré sous « {libelleSuggestion} » si laissé vide.
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Mode de calcul & Valeur */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1.5 min-w-0">
                            <Label htmlFor="modeCalcul">
                                Mode de calcul <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={modeCalcul}
                                onValueChange={(val: 'pourcentage' | 'montant_fixe') => setModeCalcul(val)}
                            >
                                <SelectTrigger id="modeCalcul" className="h-9">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="montant_fixe">Montant fixe (FCFA)</SelectItem>
                                    <SelectItem value="pourcentage">Pourcentage (%)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5 min-w-0">
                            <Label htmlFor="valeur">
                                {modeCalcul === 'pourcentage' ? 'Taux (%)' : 'Montant (FCFA)'} <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="valeur"
                                type="number"
                                min="0.01"
                                step="any"
                                placeholder={modeCalcul === 'pourcentage' ? 'ex: 4' : 'ex: 3000'}
                                value={valeur}
                                onChange={(e) => setValeur(e.target.value)}
                                className="h-9 font-bold tabular-nums"
                                required
                            />
                        </div>
                    </div>

                    {/* Base de calcul (pour les pourcentages) */}
                    {modeCalcul === 'pourcentage' && (
                        <div className="space-y-1.5 min-w-0 rounded-lg border border-emerald-200 bg-emerald-50/50 p-3">
                            <Label htmlFor="baseCalcul" className="text-xs font-semibold text-emerald-900">
                                Base de calcul du pourcentage <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={baseCalcul}
                                onValueChange={(val: BaseCalculRemise) => setBaseCalcul(val)}
                            >
                                <SelectTrigger id="baseCalcul" className="h-9 bg-white">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="total_scolarite">
                                        Toute la scolarité nette (Recommandé)
                                    </SelectItem>
                                    <SelectItem value="tranche_ciblee">
                                        Uniquement la tranche ciblée
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-[11px] text-slate-600 leading-snug">
                                {baseCalcul === 'total_scolarite'
                                    ? 'Le pourcentage est calculé sur la scolarité totale de l’élève (ex : 4 % de 75 000 = 3 000 FCFA), puis déduit de la tranche choisie.'
                                    : 'Le pourcentage est calculé uniquement sur le montant de la tranche où la remise est imputée.'}
                            </p>
                        </div>
                    )}

                    {/* Tranche d'imputation par défaut */}
                    <div className="space-y-1.5 min-w-0">
                        <Label htmlFor="cibleImputation">Tranche d'imputation par défaut</Label>
                        <Select
                            value={cibleImputation}
                            onValueChange={(val: CibleImputationRemise) => setCibleImputation(val)}
                        >
                            <SelectTrigger id="cibleImputation" className="h-9">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="derniere_tranche">Dernière tranche obligatoire</SelectItem>
                                <SelectItem value="tranche_1">1ère tranche</SelectItem>
                                <SelectItem value="tranche_2">2ème tranche</SelectItem>
                                <SelectItem value="tranche_3">3ème tranche</SelectItem>
                                <SelectItem value="tranche_4">4ème tranche</SelectItem>
                                <SelectItem value="proportionnelle">Répartition proportionnelle</SelectItem>
                            </SelectContent>
                        </Select>
                        <p className="text-[11px] text-slate-500">
                            Tranche sur laquelle la réduction est déduite par défaut (ajustable au cas par cas dans le dossier de l'élève).
                        </p>
                    </div>

                    {isEditing && (
                        <label className="flex items-start gap-2.5 text-xs cursor-pointer pt-1">
                            <input
                                type="checkbox"
                                checked={isActive}
                                onChange={(e) => setIsActive(e.target.checked)}
                                className="mt-0.5 shrink-0 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <span className="leading-snug text-slate-700">
                                Modèle actif et disponible dans les dossiers élèves
                            </span>
                        </label>
                    )}

                    <DialogFooter className="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full sm:w-auto"
                            onClick={onClose}
                            disabled={isSubmitting}
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={isSubmitting}
                            className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            {isSubmitting ? 'Enregistrement...' : isEditing ? 'Mettre à jour' : 'Enregistrer le modèle'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

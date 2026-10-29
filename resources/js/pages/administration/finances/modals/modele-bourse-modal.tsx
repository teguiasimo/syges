import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
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
import { BourseRemise, TypeFrais } from '@/types/finance';

interface ModeleBourseModalProps {
    isOpen: boolean;
    onClose: () => void;
    etablissementId: number;
    anneeId?: number;
    niveaux: { id: number; nom: string }[];
    typesFrais: TypeFrais[];
    modelToEdit?: BourseRemise | null;
}

/**
 * Modal de création et édition d'un modèle de bourse ou de remise (Aurore §3.19, §3.21, §3.25).
 *
 * @param {ModeleBourseModalProps} props
 * @returns {JSX.Element}
 */
export default function ModeleBourseModal({
    isOpen,
    onClose,
    etablissementId,
    anneeId,
    niveaux,
    typesFrais,
    modelToEdit,
}: ModeleBourseModalProps) {
    const isEditing = !!modelToEdit;

    const [type, setType] = useState<'bourse' | 'remise'>(modelToEdit?.type || 'remise');
    const [categorie, setCategorie] = useState<'excellence' | 'sociale' | 'fratrie' | 'personnel' | 'autre'>(
        modelToEdit?.categorie || 'fratrie'
    );
    const [code, setCode] = useState(modelToEdit?.code || '');
    const [nom, setNom] = useState(modelToEdit?.nom || '');
    const [modeCalcul, setModeCalcul] = useState<'pourcentage' | 'montant_fixe'>(
        modelToEdit?.mode_calcul || 'pourcentage'
    );
    const [valeur, setValeur] = useState(modelToEdit?.valeur?.toString() || '');
    const [niveauId, setNiveauId] = useState<string>(modelToEdit?.niveau_id?.toString() || '');
    const [typeFraisId, setTypeFraisId] = useState<string>(modelToEdit?.type_frais_id?.toString() || '');
    const [description, setDescription] = useState(modelToEdit?.description || '');
    const [isActive, setIsActive] = useState(modelToEdit?.is_active ?? true);
    const [isSubmitting, setIsSubmitting] = useState(false);

    React.useEffect(() => {
        if (modelToEdit) {
            setType(modelToEdit.type);
            setCategorie(modelToEdit.categorie);
            setCode(modelToEdit.code);
            setNom(modelToEdit.nom);
            setModeCalcul(modelToEdit.mode_calcul);
            setValeur(modelToEdit.valeur.toString());
            setNiveauId(modelToEdit.niveau_id ? modelToEdit.niveau_id.toString() : '');
            setTypeFraisId(modelToEdit.type_frais_id ? modelToEdit.type_frais_id.toString() : '');
            setDescription(modelToEdit.description || '');
            setIsActive(modelToEdit.is_active);
        } else {
            setType('remise');
            setCategorie('fratrie');
            setCode('');
            setNom('');
            setModeCalcul('pourcentage');
            setValeur('');
            setNiveauId('');
            setTypeFraisId('');
            setDescription('');
            setIsActive(true);
        }
    }, [modelToEdit, isOpen]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const valNum = parseFloat(valeur);
        if (!code.trim() || !nom.trim() || isNaN(valNum) || valNum <= 0) {
            toast.error('Veuillez remplir les champs obligatoires avec une valeur positive.');
            return;
        }

        setIsSubmitting(true);

        const payload = {
            etablissement_id: etablissementId,
            annee_scolaire_id: anneeId || null,
            code: code.trim().toUpperCase(),
            nom: nom.trim(),
            type,
            categorie,
            mode_calcul: modeCalcul,
            valeur: valNum,
            niveau_id: niveauId ? parseInt(niveauId, 10) : null,
            type_frais_id: typeFraisId ? parseInt(typeFraisId, 10) : null,
            description: description.trim() || null,
            is_active: isActive,
        };

        if (isEditing && modelToEdit) {
            router.put(
                `/administration/finances/bourses-remises/${modelToEdit.id}`,
                payload,
                {
                    onSuccess: () => {
                        toast.success('Modèle mis à jour avec succès.');
                        onClose();
                    },
                    onError: (errors) => {
                        const first = Object.values(errors)[0] as string;
                        toast.error(first || 'Erreur lors de la mise à jour.');
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
                        toast.success(`Modèle de ${type} créé avec succès.`);
                        onClose();
                    },
                    onError: (errors) => {
                        const first = Object.values(errors)[0] as string;
                        toast.error(first || 'Erreur lors de la création du modèle.');
                    },
                    onFinish: () => setIsSubmitting(false),
                }
            );
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Modifier le modèle' : 'Nouveau Modèle de Bourse ou Remise'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2 min-w-0">
                    {/* Paires de champs empilées sur mobile et alignées sur 2 colonnes dès sm */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="type">Type *</Label>
                            <Select value={type} onValueChange={(val: 'bourse' | 'remise') => setType(val)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="remise">Remise / Réduction</SelectItem>
                                    <SelectItem value="bourse">Bourse d'études</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="categorie">Catégorie *</Label>
                            <Select value={categorie} onValueChange={(val: 'excellence' | 'sociale' | 'fratrie' | 'personnel' | 'autre') => setCategorie(val)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fratrie">Fratrie / Famille</SelectItem>
                                    <SelectItem value="excellence">Excellence scolaire</SelectItem>
                                    <SelectItem value="sociale">Sociale / Cas social</SelectItem>
                                    <SelectItem value="personnel">Enfant du personnel</SelectItem>
                                    <SelectItem value="autre">Autre accord</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="code">Code abrégé *</Label>
                            <Input
                                id="code"
                                placeholder="ex: REM-FRAT-10"
                                value={code}
                                onChange={e => setCode(e.target.value.toUpperCase())}
                                required
                            />
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="modeCalcul">Mode de calcul *</Label>
                            <Select value={modeCalcul} onValueChange={(val: 'pourcentage' | 'montant_fixe') => setModeCalcul(val)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pourcentage">Pourcentage (%)</SelectItem>
                                    <SelectItem value="montant_fixe">Montant fixe (FCFA)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="nom">Libellé du modèle *</Label>
                        <Input
                            id="nom"
                            placeholder="ex: Remise 3ème enfant (15% sur la scolarité)"
                            value={nom}
                            onChange={e => setNom(e.target.value)}
                            required
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="valeur">
                            Valeur {modeCalcul === 'pourcentage' ? '(en %)' : '(en FCFA)'} *
                        </Label>
                        <Input
                            id="valeur"
                            type="number"
                            min="0.01"
                            step="any"
                            placeholder={modeCalcul === 'pourcentage' ? 'ex: 15' : 'ex: 50000'}
                            value={valeur}
                            onChange={e => setValeur(e.target.value)}
                            required
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="niveau">Niveau spécifique</Label>
                            <Select value={niveauId} onValueChange={val => setNiveauId(val)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Tous niveaux" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Tous niveaux</SelectItem>
                                    {niveaux.map(n => (
                                        <SelectItem key={n.id} value={n.id.toString()}>
                                            {n.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="typeFrais">Frais spécifique</Label>
                            <Select value={typeFraisId} onValueChange={val => setTypeFraisId(val)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Scolarité totale" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Global</SelectItem>
                                    {typesFrais.map(tf => (
                                        <SelectItem key={tf.id} value={tf.id.toString()}>
                                            {tf.libelle}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="description">Conditions ou descriptif</Label>
                        <Input
                            id="description"
                            placeholder="Critères d'attribution..."
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                        />
                    </div>

                    <label className="flex items-start gap-2.5 text-sm cursor-pointer pt-1">
                        <input
                            type="checkbox"
                            checked={isActive}
                            onChange={e => setIsActive(e.target.checked)}
                            className="mt-0.5 shrink-0 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
                        />
                        <span className="leading-snug">Modèle actif et disponible pour attribution</span>
                    </label>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isSubmitting} className="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white">
                            {isSubmitting ? 'Enregistrement...' : isEditing ? 'Mettre à jour' : 'Enregistrer le modèle'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

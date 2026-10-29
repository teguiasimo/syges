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
import { Caisse } from '@/types/finance';

interface NouvelleCaisseModalProps {
    isOpen: boolean;
    onClose: () => void;
    etablissementId: number;
    caissiers: { id: number; nom: string; prenom: string }[];
    caisseToEdit?: Caisse | null;
}

/**
 * Modal de création et modification d'une Caisse d'établissement (Aurore §3.20).
 *
 * @param {NouvelleCaisseModalProps} props
 * @returns {JSX.Element}
 */
export default function NouvelleCaisseModal({
    isOpen,
    onClose,
    etablissementId,
    caissiers,
    caisseToEdit,
}: NouvelleCaisseModalProps) {
    const isEditing = !!caisseToEdit;

    const [code, setCode] = useState(caisseToEdit?.code || '');
    const [nom, setNom] = useState(caisseToEdit?.nom || '');
    const [soldeInitial, setSoldeInitial] = useState(caisseToEdit?.solde_initial?.toString() || '0');
    const [responsableId, setResponsableId] = useState<string>(
        caisseToEdit?.responsable_id?.toString() || ''
    );
    const [isPrincipale, setIsPrincipale] = useState(caisseToEdit?.is_principale || false);
    const [isActive, setIsActive] = useState(caisseToEdit?.is_active ?? true);
    const [description, setDescription] = useState(caisseToEdit?.description || '');
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Synchronisation lors de l'ouverture pour édition
    React.useEffect(() => {
        if (caisseToEdit) {
            setCode(caisseToEdit.code);
            setNom(caisseToEdit.nom);
            setSoldeInitial(caisseToEdit.solde_initial.toString());
            setResponsableId(caisseToEdit.responsable_id ? caisseToEdit.responsable_id.toString() : '');
            setIsPrincipale(caisseToEdit.is_principale);
            setIsActive(caisseToEdit.is_active);
            setDescription(caisseToEdit.description || '');
        } else {
            setCode('');
            setNom('');
            setSoldeInitial('0');
            setResponsableId('');
            setIsPrincipale(false);
            setIsActive(true);
            setDescription('');
        }
    }, [caisseToEdit, isOpen]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!code.trim() || !nom.trim()) {
            toast.error('Veuillez renseigner le code et le libellé de la caisse.');
            return;
        }

        setIsSubmitting(true);

        const payload = {
            etablissement_id: etablissementId,
            code: code.trim().toUpperCase(),
            nom: nom.trim(),
            solde_initial: parseFloat(soldeInitial) || 0,
            responsable_id: responsableId ? parseInt(responsableId, 10) : null,
            is_principale: isPrincipale,
            is_active: isActive,
            description: description.trim() || null,
        };

        if (isEditing && caisseToEdit) {
            router.put(
                `/administration/finances/caisses/${caisseToEdit.id}`,
                payload,
                {
                    onSuccess: () => {
                        toast.success('Caisse mise à jour avec succès.');
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
                '/administration/finances/caisses',
                payload,
                {
                    onSuccess: () => {
                        toast.success('Caisse créée avec succès.');
                        onClose();
                    },
                    onError: (errors) => {
                        const first = Object.values(errors)[0] as string;
                        toast.error(first || 'Erreur lors de la création de la caisse.');
                    },
                    onFinish: () => setIsSubmitting(false),
                }
            );
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Modifier la caisse' : 'Ajouter une nouvelle caisse'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2 min-w-0">
                    {/* Champs Code et Solde empilés sur mobile, 2 colonnes dès sm */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="code">Code Caisse *</Label>
                            <Input
                                id="code"
                                placeholder="ex: CAISSE-CANT"
                                value={code}
                                onChange={e => setCode(e.target.value.toUpperCase())}
                                required
                            />
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="soldeInitial">Solde d'ouverture (FCFA)</Label>
                            <Input
                                id="soldeInitial"
                                type="number"
                                min="0"
                                value={soldeInitial}
                                onChange={e => setSoldeInitial(e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="nom">Nom de la caisse *</Label>
                        <Input
                            id="nom"
                            placeholder="ex: Caisse Cantine & Activités"
                            value={nom}
                            onChange={e => setNom(e.target.value)}
                            required
                        />
                    </div>

                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="responsable">Caissier / Responsable</Label>
                        <Select
                            value={responsableId}
                            onValueChange={val => setResponsableId(val)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Non assigné" />
                            </SelectTrigger>
                            <SelectContent>
                                {caissiers.map(c => (
                                    <SelectItem key={c.id} value={c.id.toString()}>
                                        {c.nom} {c.prenom}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="description">Description / Affectation</Label>
                        <Input
                            id="description"
                            placeholder="Usage prévu pour cette caisse..."
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                        />
                    </div>

                    <div className="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                        <label className="flex items-start gap-2 text-sm cursor-pointer">
                            <input
                                type="checkbox"
                                checked={isPrincipale}
                                onChange={e => setIsPrincipale(e.target.checked)}
                                className="mt-0.5 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="font-medium leading-snug">Caisse principale de l'établissement</span>
                        </label>

                        <label className="flex items-center gap-2 text-sm cursor-pointer">
                            <input
                                type="checkbox"
                                checked={isActive}
                                onChange={e => setIsActive(e.target.checked)}
                                className="shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span>Active</span>
                        </label>
                    </div>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isSubmitting} className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white">
                            {isSubmitting ? 'Enregistrement...' : isEditing ? 'Mettre à jour' : 'Créer la caisse'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

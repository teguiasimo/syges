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
import { Textarea } from '@/components/ui/textarea';
import { Caisse } from '@/types/finance';

interface NouveauReglementDiversModalProps {
    isOpen: boolean;
    onClose: () => void;
    caisses: Caisse[];
    caissiers: { id: number; nom: string; prenom: string }[];
}

/**
 * Modal d'enregistrement d'un Règlement Divers hors scolarité (Aurore §4.2).
 * Permet d'encaisser des recettes annexes (uniformes, macarons, tickets cantine libre, location de salle, don).
 *
 * @param {NouveauReglementDiversModalProps} props
 * @returns {JSX.Element}
 */
export default function NouveauReglementDiversModal({
    isOpen,
    onClose,
    caisses,
    caissiers,
}: NouveauReglementDiversModalProps) {
    const defaultCaisseId = caisses.find(c => c.is_principale)?.id || caisses[0]?.id;

    const [caisseId, setCaisseId] = useState<number>(defaultCaisseId || 0);
    const [payeurNom, setPayeurNom] = useState('');
    const [payeurTelephone, setPayeurTelephone] = useState('');
    const [categorie, setCategorie] = useState<'uniformes' | 'cantine_libre' | 'macaron' | 'location' | 'don' | 'autre'>('uniformes');
    const [libelle, setLibelle] = useState('');
    const [montant, setMontant] = useState('');
    const [modePaiement, setModePaiement] = useState<'especes' | 'cheque' | 'virement' | 'mobile_money_manuel'>('especes');
    const [referencePaiement, setReferencePaiement] = useState('');
    const [personnelId, setPersonnelId] = useState<string>('');
    const [observations, setObservations] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const montantNum = parseFloat(montant);
        if (!payeurNom.trim() || !libelle.trim() || isNaN(montantNum) || montantNum <= 0) {
            toast.error('Veuillez remplir tous les champs obligatoires avec un montant valide.');
            return;
        }

        setIsSubmitting(true);

        router.post(
            '/administration/finances/reglements-divers',
            {
                caisse_id: caisseId,
                payeur_nom: payeurNom.trim(),
                payeur_telephone: payeurTelephone.trim() || null,
                categorie,
                libelle: libelle.trim(),
                montant: montantNum,
                mode_paiement: modePaiement,
                reference_paiement: referencePaiement.trim() || null,
                personnel_id: personnelId ? parseInt(personnelId, 10) : null,
                observations: observations.trim() || null,
            },
            {
                onSuccess: () => {
                    toast.success('Règlement divers enregistré avec succès.');
                    onClose();
                    // Reset form
                    setPayeurNom('');
                    setPayeurTelephone('');
                    setLibelle('');
                    setMontant('');
                    setReferencePaiement('');
                    setObservations('');
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0] as string;
                    toast.error(firstError || "Erreur lors de l'enregistrement du règlement.");
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={open => !open && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nouveau Règlement Divers (Recette libre)</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="caisse">Caisse d'encaissement *</Label>
                            <Select
                                value={caisseId ? caisseId.toString() : ''}
                                onValueChange={val => setCaisseId(parseInt(val, 10))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner la caisse" />
                                </SelectTrigger>
                                <SelectContent>
                                    {caisses.map(c => (
                                        <SelectItem key={c.id} value={c.id.toString()}>
                                            {c.nom} {c.is_principale ? '(Principale)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="categorie">Catégorie de recette *</Label>
                            <Select
                                value={categorie}
                                onValueChange={(val: any) => setCategorie(val)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Catégorie" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="uniformes">Vente d'uniformes / écussons</SelectItem>
                                    <SelectItem value="cantine_libre">Cantine libre / Repas visiteur</SelectItem>
                                    <SelectItem value="macaron">Macaron & Livrets</SelectItem>
                                    <SelectItem value="location">Location d'espaces / salles</SelectItem>
                                    <SelectItem value="don">Don & Subvention</SelectItem>
                                    <SelectItem value="autre">Autre prestation diverse</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="libelle">Désignation de la prestation *</Label>
                        <Input
                            id="libelle"
                            placeholder="ex: 2 Uniformes complets taille M + 1 Macaron"
                            value={libelle}
                            onChange={e => setLibelle(e.target.value)}
                            required
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="payeurNom">Nom du payeur / Client *</Label>
                            <Input
                                id="payeurNom"
                                placeholder="Nom et prénom"
                                value={payeurNom}
                                onChange={e => setPayeurNom(e.target.value)}
                                required
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="payeurTelephone">Téléphone</Label>
                            <Input
                                id="payeurTelephone"
                                placeholder="6XX XXX XXX"
                                value={payeurTelephone}
                                onChange={e => setPayeurTelephone(e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="montant">Montant perçu (FCFA) *</Label>
                            <Input
                                id="montant"
                                type="number"
                                min="1"
                                placeholder="ex: 15000"
                                value={montant}
                                onChange={e => setMontant(e.target.value)}
                                required
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="modePaiement">Mode de règlement *</Label>
                            <Select
                                value={modePaiement}
                                onValueChange={(val: any) => setModePaiement(val)}
                            >
                                <SelectTrigger>
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
                            <Label htmlFor="referencePaiement">Réf. transaction / N° Chèque</Label>
                            <Input
                                id="referencePaiement"
                                placeholder="Numéro de reçu ou référence"
                                value={referencePaiement}
                                onChange={e => setReferencePaiement(e.target.value)}
                            />
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="caissier">Agent encaisseur</Label>
                            <Select
                                value={personnelId}
                                onValueChange={val => setPersonnelId(val)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Intendance / Caisse" />
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
                            <Label htmlFor="observations">Remarques éventuelles</Label>
                            <Input
                                id="observations"
                                placeholder="Note interne..."
                                value={observations}
                                onChange={e => setObservations(e.target.value)}
                            />
                        </div>
                    </div>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isSubmitting} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                            {isSubmitting ? 'Enregistrement...' : 'Encaisser & Émettre le reçu'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

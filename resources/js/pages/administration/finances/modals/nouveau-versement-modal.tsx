import React, { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
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
import { Textarea } from '@/components/ui/textarea';
import { CompteFinancierEleve, ModePaiement, TypeFrais, FraisScolaire } from '@/types/finance';
import { Plus, Trash2, CheckCircle2, DollarSign } from 'lucide-react';

interface NouveauVersementModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    comptes: CompteFinancierEleve[];
    typesFrais: TypeFrais[];
    tarifs: FraisScolaire[];
    preselectedCompteId?: number | null;
}

interface VentilationItem {
    type_frais_id: number | null;
    frais_scolaire_id: number | null;
    libelle: string;
    montant: number;
}

/**
 * Modale de saisie d'un nouvel encaissement physique au guichet de caisse (Module 11.2).
 * Permet la sélection de l'élève, la saisie du mode de règlement et la ventilation fine des frais.
 *
 * @param {NouveauVersementModalProps} props
 * @returns {JSX.Element}
 */
export default function NouveauVersementModal({
    open,
    onOpenChange,
    comptes,
    typesFrais,
    tarifs,
    preselectedCompteId,
}: NouveauVersementModalProps) {
    const [selectedCompteId, setSelectedCompteId] = useState<string>(preselectedCompteId ? String(preselectedCompteId) : '');
    const [ventilations, setVentilations] = useState<VentilationItem[]>([
        { type_frais_id: null, frais_scolaire_id: null, libelle: 'Scolarité Tranche 1', montant: 0 },
    ]);

    const { data, setData, post, processing, errors, reset, transform } = useForm({
        compte_financier_id: '',
        montant_total: '',
        mode_paiement: 'especes' as ModePaiement,
        reference_paiement: '',
        payeur_nom: '',
        payeur_telephone: '',
        personnel_id: '',
        observations: '',
        ventilations: [] as VentilationItem[],
    });

    // Synchronisation lors de l'ouverture avec un compte présélectionné
    useEffect(() => {
        if (preselectedCompteId) {
            setSelectedCompteId(String(preselectedCompteId));
            setData('compte_financier_id', String(preselectedCompteId));
        }
    }, [preselectedCompteId]);

    const activeCompte = comptes.find((c) => String(c.id) === selectedCompteId);

    // Mettre à jour le montant d'une ligne de ventilation
    const updateVentilation = (index: number, field: keyof VentilationItem, value: any) => {
        const updated = [...ventilations];
        updated[index] = { ...updated[index], [field]: value };
        setVentilations(updated);

        // Recalcul de la somme globale
        const total = updated.reduce((sum, item) => sum + (Number(item.montant) || 0), 0);
        setData('montant_total', total > 0 ? String(total) : '');
    };

    // Ajouter une ligne de ventilation
    const addVentilation = () => {
        setVentilations([
            ...ventilations,
            { type_frais_id: null, frais_scolaire_id: null, libelle: 'Frais APEE / Divers', montant: 0 },
        ]);
    };

    // Supprimer une ligne de ventilation
    const removeVentilation = (index: number) => {
        if (ventilations.length <= 1) return;
        const updated = ventilations.filter((_, i) => i !== index);
        setVentilations(updated);
        const total = updated.reduce((sum, item) => sum + (Number(item.montant) || 0), 0);
        setData('montant_total', total > 0 ? String(total) : '');
    };

    // Soumission du versement
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const payloadVentilations = ventilations.map((v) => ({
            ...v,
            montant: Number(v.montant),
        }));

        transform((formValues) => ({
            ...formValues,
            compte_financier_id: selectedCompteId,
            ventilations: payloadVentilations,
        }));

        post('/administration/finances/versements', {
            onSuccess: (page: any) => {
                toast.success('Versement enregistré avec succès ! Reçu officiel généré.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                const dernierRecuId = page?.props?.flash?.dernier_recu_id;
                if (dernierRecuId) {
                    window.open(`/administration/finances/versements/${dernierRecuId}/recu`, '_blank');
                }
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                toast.error(err.error || 'Erreur lors de l’enregistrement du versement.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg sm:text-xl font-bold text-slate-800 dark:text-slate-100">
                        <DollarSign className="w-6 h-6 text-emerald-600 shrink-0" />
                        Guichet Caisse : Enregistrer un Versement
                    </DialogTitle>
                    <DialogDescription>
                        Émettez un reçu officiel infalsifiable avec QR Code et ventilation par rubrique.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 min-w-0">
                    {/* Sélection de l'élève */}
                    <div className="space-y-1.5 min-w-0">
                        <Label htmlFor="compte_select">Élève bénéficiaire <span className="text-red-500">*</span></Label>
                        <Select
                            value={selectedCompteId}
                            onValueChange={(val) => {
                                setSelectedCompteId(val);
                                setData('compte_financier_id', val);
                            }}
                        >
                            <SelectTrigger id="compte_select" className="w-full">
                                <SelectValue placeholder="Rechercher par nom ou matricule..." />
                            </SelectTrigger>
                            <SelectContent className="max-h-60">
                                {comptes.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.eleve?.matricule} - {c.eleve?.nom} {c.eleve?.prenom} ({c.inscription?.classe?.nom ?? 'Classe N/A'}) - Reste: {Number(c.solde_restant).toLocaleString('fr-FR')} FCFA
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.compte_financier_id && <p className="text-xs text-red-500">{errors.compte_financier_id}</p>}
                    </div>

                    {/* Aperçu du compte élève sélectionné : 1 colonne sur mobile, 3 colonnes dès sm */}
                    {activeCompte && (
                        <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                            <div className="flex sm:block justify-between items-center">
                                <span className="text-slate-500 block">Total Exigible</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
                                    {Number(activeCompte.montant_net_exigible).toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                            <div className="flex sm:block justify-between items-center">
                                <span className="text-slate-500 block">Déjà Payé</span>
                                <span className="font-semibold text-emerald-600 tabular-nums">
                                    {Number(activeCompte.total_paye).toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                            <div className="flex sm:block justify-between items-center pt-1.5 sm:pt-0 border-t sm:border-t-0 border-slate-200 dark:border-slate-800">
                                <span className="text-slate-500 block">Solde Restant Dû</span>
                                <span className="font-bold text-red-600 text-sm tabular-nums">
                                    {Number(activeCompte.solde_restant).toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                        </div>
                    )}

                    {/* Lignes de ventilation adaptées aux petits écrans */}
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label className="text-sm font-semibold">Ventilation des frais à encaisser</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addVentilation}
                                className="h-7 text-xs flex items-center gap-1"
                            >
                                <Plus className="w-3.5 h-3.5 shrink-0" /> Ajouter une rubrique
                            </Button>
                        </div>

                        <div className="space-y-2">
                            {ventilations.map((vent, idx) => (
                                <div key={idx} className="flex flex-col sm:flex-row sm:items-center gap-2 p-2 sm:p-0 rounded-md border sm:border-0 border-slate-200 dark:border-slate-800">
                                    <Input
                                        placeholder="Rubrique (ex: Scolarité Tranche 1)"
                                        value={vent.libelle}
                                        onChange={(e) => updateVentilation(idx, 'libelle', e.target.value)}
                                        className="flex-1 min-w-0"
                                        required
                                    />
                                    <div className="flex items-center gap-2">
                                        <Input
                                            type="number"
                                            placeholder="Montant FCFA"
                                            value={vent.montant || ''}
                                            onChange={(e) => updateVentilation(idx, 'montant', e.target.value)}
                                            className="flex-1 sm:w-36 sm:flex-none text-right font-medium tabular-nums"
                                            required
                                            min={1}
                                        />
                                        {ventilations.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeVentilation(idx)}
                                                className="text-red-500 hover:text-red-700 h-9 w-9 shrink-0"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="flex flex-wrap justify-end items-center gap-2 pt-1">
                            <span className="text-xs text-slate-500 uppercase font-semibold">Montant Total Encaissé :</span>
                            <span className="text-base font-bold text-emerald-600 tabular-nums">
                                {Number(data.montant_total || 0).toLocaleString('fr-FR')} FCFA
                            </span>
                        </div>
                    </div>

                    {/* Mode de règlement et référence */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="mode_paiement">Mode de Paiement <span className="text-red-500">*</span></Label>
                            <Select
                                value={data.mode_paiement}
                                onValueChange={(val: ModePaiement) => setData('mode_paiement', val)}
                            >
                                <SelectTrigger id="mode_paiement">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="especes">Espèces (Guichet)</SelectItem>
                                    <SelectItem value="mobile_money_manuel">Mobile Money (Manuel / TxID)</SelectItem>
                                    <SelectItem value="virement">Virement Bancaire</SelectItem>
                                    <SelectItem value="cheque">Chèque Bancaire</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="reference_paiement">Réf. Transaction / N° Chèque</Label>
                            <Input
                                id="reference_paiement"
                                placeholder="TxID MoMo, N° bordereau..."
                                value={data.reference_paiement}
                                onChange={(e) => setData('reference_paiement', e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Informations du payeur */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="payeur_nom">Nom du Payeur / Parent</Label>
                            <Input
                                id="payeur_nom"
                                placeholder="Nom et prénom..."
                                value={data.payeur_nom}
                                onChange={(e) => setData('payeur_nom', e.target.value)}
                            />
                        </div>
                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="payeur_telephone">Téléphone du Payeur</Label>
                            <Input
                                id="payeur_telephone"
                                placeholder="+237 6..."
                                value={data.payeur_telephone}
                                onChange={(e) => setData('payeur_telephone', e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Remarques */}
                    <div className="space-y-1">
                        <Label htmlFor="observations">Observations / Remarques</Label>
                        <Textarea
                            id="observations"
                            rows={2}
                            placeholder="Observations comptables..."
                            value={data.observations}
                            onChange={(e) => setData('observations', e.target.value)}
                        />
                    </div>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !selectedCompteId || Number(data.montant_total) <= 0}
                            className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center gap-1.5"
                        >
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            {processing ? 'Encaissement en cours...' : 'Valider & Émettre le Reçu'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

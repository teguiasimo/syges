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
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CompteFinancierEleve, FraisScolaire, SouscriptionFraisOptionnel } from '@/types/finance';
import { Bus, Trash2, PlusCircle, CheckCircle2 } from 'lucide-react';
import { formatDate } from '@/lib/utils';

interface SouscriptionOptionModalProps {
    isOpen: boolean;
    onClose: () => void;
    compte: CompteFinancierEleve | null;
    fraisOptionnels: FraisScolaire[];
}

/**
 * Modal de gestion des souscriptions aux Frais Optionnels (Bus, Cantine, Internat, etc.).
 *
 * Permet d'associer un élève à une prestation optionnelle de son établissement
 * et d'ajuster automatiquement son solde exigible.
 *
 * @param {SouscriptionOptionModalProps} props
 * @returns {JSX.Element}
 */
export default function SouscriptionOptionModal({
    isOpen,
    onClose,
    compte,
    fraisOptionnels,
}: SouscriptionOptionModalProps) {
    const [selectedFraisId, setSelectedFraisId] = useState<string>('');
    const [montant, setMontant] = useState<string>('');
    const [observations, setObservations] = useState<string>('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    // Conservation du dernier compte pour laisser Radix Dialog exécuter sa fermeture complète
    const [displayCompte, setDisplayCompte] = useState<CompteFinancierEleve | null>(compte);
    const activeCompte = compte ?? displayCompte;

    React.useEffect(() => {
        if (compte) {
            setDisplayCompte(compte);
        }
    }, [compte]);

    // Filtrer les options adaptées au niveau de l'élève (ou globales)
    const niveauId = activeCompte?.inscription?.classe?.niveau_id;
    const optionsApplicables = React.useMemo(() => {
        return fraisOptionnels.filter(f => !f.niveau_id || f.niveau_id === niveauId);
    }, [fraisOptionnels, niveauId]);

    // Pré-remplir le montant par défaut lorsqu'un frais est sélectionné
    const handleFraisChange = (id: string) => {
        setSelectedFraisId(id);
        const frais = optionsApplicables.find(f => f.id.toString() === id);
        if (frais) {
            setMontant(frais.montant.toString());
        }
    };

    // Soumission de la souscription
    const handleSubscribe = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte || !selectedFraisId) {
            toast.error('Veuillez sélectionner un service optionnel.');
            return;
        }

        setIsSubmitting(true);
        router.post(
            '/administration/finances/souscriptions-optionnelles',
            {
                compte_financier_id: compte.id,
                frais_scolaire_id: parseInt(selectedFraisId, 10),
                montant: montant ? parseFloat(montant) : null,
                observations: observations.trim() || null,
            },
            {
                onSuccess: () => {
                    toast.success('Souscription enregistrée avec succès. Le compte élève a été réactualisé.');
                    setSelectedFraisId('');
                    setMontant('');
                    setObservations('');
                    setIsSubmitting(false);
                    onClose();
                },
                onError: (errors) => {
                    setIsSubmitting(false);
                    toast.error(errors.error || 'Erreur lors de l\'enregistrement de la souscription.');
                },
            }
        );
    };

    // Annulation d'une souscription active
    const handleCancelSouscription = (souscription: SouscriptionFraisOptionnel) => {
        if (!confirm('Êtes-vous sûr de vouloir résilier cette souscription ? Le montant sera retiré du compte.')) {
            return;
        }

        router.delete(`/administration/finances/souscriptions-optionnelles/${souscription.id}`, {
            onSuccess: () => {
                toast.success('Souscription résiliée avec succès.');
                onClose();
            },
            onError: () => {
                toast.error('Erreur lors de la résiliation de la souscription.');
            },
        });
    };

    const souscriptionsActives = (activeCompte?.souscriptions_optionnelles || []).filter(s => s.statut === 'actif');

    return (
        <Dialog open={Boolean(isOpen && compte)} onOpenChange={open => !open && onClose()}>
            {activeCompte && (
            <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-slate-800 dark:text-slate-100">
                        <Bus className="w-5 h-5 text-indigo-600" />
                        Services & Prestations Optionnels (Bus, Cantine...)
                    </DialogTitle>
                </DialogHeader>

                {/* Bannière élève */}
                <div className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3 rounded-lg flex items-center justify-between text-xs">
                    <div>
                        <span className="font-bold text-slate-900 dark:text-slate-100">
                            {activeCompte.eleve?.nom} {activeCompte.eleve?.prenom}
                        </span>
                        <div className="text-slate-500">
                            Matricule : <span className="font-mono font-semibold">{activeCompte.eleve?.matricule}</span> | Classe : {activeCompte.inscription?.classe?.nom ?? 'N/A'}
                        </div>
                    </div>
                    <Badge variant="outline" className="font-mono">
                        Net dû : {Number(activeCompte.montant_net_exigible).toLocaleString('fr-FR')} FCFA
                    </Badge>
                </div>

                {/* Liste des souscriptions actives */}
                <div className="mt-2">
                    <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Souscriptions Actives ({souscriptionsActives.length})
                    </Label>
                    <div className="mt-1.5 space-y-2">
                        {souscriptionsActives.length === 0 ? (
                            <p className="text-xs text-slate-500 italic py-2">
                                Aucune prestation optionnelle souscrite pour cet élève actuellement.
                            </p>
                        ) : (
                            souscriptionsActives.map(sub => (
                                <div
                                    key={sub.id}
                                    className="flex items-center justify-between p-2.5 bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 rounded-md text-xs"
                                >
                                    <div>
                                        <div className="font-semibold text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                                            {sub.frais_scolaire?.type_frais?.libelle ?? 'Service Optionnel'}
                                            {sub.observations && (
                                                <span className="text-[11px] font-normal text-slate-600 dark:text-slate-400">
                                                    ({sub.observations})
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-[10px] text-slate-500 mt-0.5">
                                            Souscrit le {formatDate(sub.date_souscription)}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-bold text-indigo-700 dark:text-indigo-300">
                                            {Number(sub.montant).toLocaleString('fr-FR')} FCFA
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 w-7 p-0 text-red-500 hover:text-red-700 hover:bg-red-50"
                                            onClick={() => handleCancelSouscription(sub)}
                                            title="Résilier cette option"
                                        >
                                            <Trash2 className="w-3.5 h-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* Formulaire de nouvelle souscription */}
                <form onSubmit={handleSubscribe} className="mt-4 border-t border-slate-200 dark:border-slate-800 pt-3 space-y-3">
                    <div className="flex items-center gap-1 text-xs font-semibold text-slate-800 dark:text-slate-200">
                        <PlusCircle className="w-4 h-4 text-emerald-600" />
                        Ajouter une prestation optionnelle
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="frais-optionnel" className="text-xs">
                            Prestation / Option disponible *
                        </Label>
                        <Select value={selectedFraisId} onValueChange={handleFraisChange}>
                            <SelectTrigger id="frais-optionnel" className="h-9 text-xs">
                                <SelectValue placeholder="Sélectionner une option (Bus, Cantine...)" />
                            </SelectTrigger>
                            <SelectContent>
                                {optionsApplicables.map(f => (
                                    <SelectItem key={f.id} value={f.id.toString()} className="text-xs">
                                        {f.type_frais?.libelle} {f.libelle_tranche ? `(${f.libelle_tranche})` : ''} — {Number(f.montant).toLocaleString('fr-FR')} FCFA
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {optionsApplicables.length === 0 && (
                            <p className="text-[11px] text-amber-600">
                                Aucun frais marqué comme optionnel n'a été paramétré dans la grille tarifaire pour ce niveau.
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="montant-option" className="text-xs">
                                Montant facturé (FCFA) *
                            </Label>
                            <Input
                                id="montant-option"
                                type="number"
                                min="0"
                                className="h-9 text-xs"
                                placeholder="Montant en FCFA"
                                value={montant}
                                onChange={e => setMontant(e.target.value)}
                                required
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="obs-option" className="text-xs">
                                Précisions / Arrêt / Formule
                            </Label>
                            <Input
                                id="obs-option"
                                type="text"
                                className="h-9 text-xs"
                                placeholder="Ex: Ligne 3 - Bonamoussadi"
                                value={observations}
                                onChange={e => setObservations(e.target.value)}
                            />
                        </div>
                    </div>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isSubmitting}>
                            Fermer
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            className="bg-emerald-600 hover:bg-emerald-700 text-white"
                            disabled={isSubmitting || !selectedFraisId}
                        >
                            {isSubmitting ? 'Enregistrement...' : 'Valider la souscription'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
            )}
        </Dialog>
    );
}


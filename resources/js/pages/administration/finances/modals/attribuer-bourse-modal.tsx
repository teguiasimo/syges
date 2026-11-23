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
import { SearchableSelect } from '@/components/ui/searchable-select';
import {
    BourseRemise,
    CompteFinancierEleve,
    formatBaseCalculRemiseLabel,
    formatCibleImputationLabel,
} from '@/types/finance';

interface AttribuerBourseModalProps {
    isOpen: boolean;
    onClose: () => void;
    compte: CompteFinancierEleve | null;
    boursesRemises: BourseRemise[];
}

/**
 * Modal d'attribution directe d'une bourse ou d'une remise au compte scolaire d'un élève (Aurore §4.1.6).
 *
 * @param {AttribuerBourseModalProps} props Propriétés du composant
 * @returns {JSX.Element} Modale d'attribution de bourse ou remise
 */
export default function AttribuerBourseModal({
    isOpen,
    onClose,
    compte,
    boursesRemises,
}: AttribuerBourseModalProps) {
    const [bourseRemiseId, setBourseRemiseId] = useState<string>('');
    const [motif, setMotif] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    // Conservation du dernier compte afin que Radix Dialog complète proprement sa fermeture sans démontage anticipé
    const [displayCompte, setDisplayCompte] = useState<CompteFinancierEleve | null>(compte);
    const activeCompte = compte ?? displayCompte;

    React.useEffect(() => {
        if (compte) {
            setDisplayCompte(compte);
        }
    }, [compte]);

    const selectedModel = boursesRemises.find(b => b.id.toString() === bourseRemiseId);

    const baseNetteScolarite = React.useMemo(() => {
        if (!activeCompte) return 0;
        return Math.max(0, (activeCompte.debit_initial || 0) - (activeCompte.total_dispense || 0));
    }, [activeCompte]);

    const estPourcentageSurTrancheCiblee = Boolean(
        selectedModel?.mode_calcul === 'pourcentage' && selectedModel?.base_calcul === 'tranche_ciblee'
    );

    // Calcul de l'estimation de la déduction (exacte pour montant fixe et pourcentage sur scolarité totale)
    const montantEstime = React.useMemo(() => {
        if (!selectedModel || !activeCompte) return 0;
        if (selectedModel.mode_calcul === 'pourcentage') {
            if (selectedModel.base_calcul === 'tranche_ciblee') {
                return 0;
            }
            return Math.min(baseNetteScolarite, Math.round((baseNetteScolarite * selectedModel.valeur) / 100));
        }
        return Math.min(baseNetteScolarite, selectedModel.valeur);
    }, [selectedModel, activeCompte, baseNetteScolarite]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!compte || !bourseRemiseId) {
            toast.error('Veuillez sélectionner un modèle de bourse ou de remise.');
            return;
        }

        setIsSubmitting(true);

        router.post(
            '/administration/finances/bourses-remises/attribuer',
            {
                compte_financier_id: compte.id,
                bourse_remise_id: parseInt(bourseRemiseId, 10),
                motif: motif.trim() || null,
            },
            {
                onSuccess: () => {
                    toast.success('Bourse/Remise accordée avec succès.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    onClose();
                    setBourseRemiseId('');
                    setMotif('');
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Erreur lors de l'attribution.", { position: 'top-right' });
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    return (
        <Dialog open={Boolean(isOpen && compte)} onOpenChange={open => !open && onClose()}>
            {activeCompte && (
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Accorder une Bourse ou une Remise</DialogTitle>
                </DialogHeader>

                {/* Résumé du compte financier avec retour à la ligne adaptatif sur mobile */}
                <div className="bg-slate-50 p-3 rounded-lg border border-slate-200 text-sm space-y-1 my-1 min-w-0">
                    <div className="wrap-break-word">Élève : <strong className="text-slate-900">{activeCompte.eleve?.nom} {activeCompte.eleve?.prenom}</strong></div>
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs sm:text-sm">
                        <span>Matricule : <span className="font-mono">{activeCompte.eleve?.matricule}</span></span>
                        <span className="hidden sm:inline text-slate-400">|</span>
                        <span>Classe : <strong>{activeCompte.inscription?.classe?.nom}</strong></span>
                    </div>
                    <div>Frais initiaux bruts : <strong className="text-blue-700 tabular-nums">{new Intl.NumberFormat('fr-FR').format(activeCompte.debit_initial)} FCFA</strong></div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 py-2 min-w-0">
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="bourseRemise">Sélectionner le modèle *</Label>
                        <SearchableSelect
                            id="bourseRemise"
                            options={boursesRemises
                                .filter((b) => b.is_active)
                                .map((b) => ({
                                    value: b.id.toString(),
                                    label: b.nom,
                                    description: b.mode_calcul === 'pourcentage'
                                        ? `${b.valeur}%`
                                        : `${new Intl.NumberFormat('fr-FR').format(b.valeur)} FCFA`,
                                    group: b.type === 'bourse' ? 'Bourses' : 'Remises',
                                }))}
                            value={bourseRemiseId}
                            onValueChange={(val) => setBourseRemiseId(val)}
                            placeholder="Choisir une bourse ou remise..."
                            searchPlaceholder="Rechercher un modèle..."
                            emptyMessage="Aucun modèle trouvé."
                            className="h-9 text-xs font-normal"
                        />
                    </div>

                    {selectedModel && (
                        <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-900 space-y-1">
                            <div className="font-semibold flex flex-wrap items-center justify-between gap-2">
                                <span>Déduction estimée :</span>
                                <span className="text-base text-emerald-700 font-bold tabular-nums">
                                    {estPourcentageSurTrancheCiblee
                                        ? `${selectedModel.valeur}% sur tranche ciblée`
                                        : `- ${new Intl.NumberFormat('fr-FR').format(montantEstime)} FCFA`}
                                </span>
                            </div>
                            <div className="text-xs text-emerald-800 flex flex-wrap gap-x-2 gap-y-0.5">
                                <span>
                                    Imputation : <strong>{formatCibleImputationLabel(selectedModel.cible_imputation)}</strong>
                                </span>
                                {selectedModel.mode_calcul === 'pourcentage' && (
                                    <span>
                                        • Base : <strong>{formatBaseCalculRemiseLabel(selectedModel.base_calcul)}</strong>
                                    </span>
                                )}
                            </div>
                            {!estPourcentageSurTrancheCiblee && (
                                <div className="text-xs text-emerald-800 tabular-nums">
                                    Nouveau net exigible estimé : {new Intl.NumberFormat('fr-FR').format(Math.max(0, (activeCompte.debit_initial || 0) - (activeCompte.total_dispense || 0) + (activeCompte.arrieres_anterieurs || 0) - montantEstime))} FCFA
                                </div>
                            )}
                        </div>
                    )}

                    <div className="space-y-1">
                        <Label htmlFor="motif">Motif / Justification</Label>
                        <Input
                            id="motif"
                            placeholder="ex: 3e enfant scolarisé dans l'établissement (Dossier vérifié)"
                            value={motif}
                            onChange={e => setMotif(e.target.value)}
                        />
                    </div>

                    {/* Boutons pleine largeur sur mobile avec retour à la ligne autorisé sur petits écrans (320px) */}
                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={onClose} disabled={isSubmitting}>
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={isSubmitting || !bourseRemiseId}
                            className="w-full sm:w-auto whitespace-normal h-auto py-2 bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            {isSubmitting ? 'Validation...' : 'Valider & Recalculer le compte'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
            )}
        </Dialog>
    );
}

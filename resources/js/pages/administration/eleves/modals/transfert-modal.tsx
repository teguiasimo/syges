import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { ArrowRightLeft, AlertTriangle } from 'lucide-react';
import type { Inscription, EtablissementRef, ClasseRef } from '@/types/eleve';

/**
 * Propriétés du composant TransfertModal.
 */
interface TransfertModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    inscriptions: Inscription[];
    etablissements: EtablissementRef[];
}

/**
 * Modal de transfert d'élèves (unitaire ou groupé) vers une autre classe.
 * Conforme au cadrage V1.1 de Gesco :
 * - Intra-établissement : Réaffectation directe avec note d'historique.
 * - Intra-groupe (autre établissement du groupe) : Clôture de l'inscription précédente (statut 'transfere')
 *   et création de la nouvelle inscription dans l'établissement cible sans ressaisie de l'identité civile.
 *
 * @param {TransfertModalProps} props
 * @returns {JSX.Element}
 */
export function TransfertModal({
    open,
    onOpenChange,
    inscriptions,
    etablissements,
}: TransfertModalProps) {
    const [loading, setLoading] = useState(false);
    const [targetClasseId, setTargetClasseId] = useState('');
    const [motifTransfert, setMotifTransfert] = useState('');

    // Liste aplatie de toutes les classes disponibles
    const availableClasses: ClasseRef[] = React.useMemo(() => {
        const list: ClasseRef[] = [];
        etablissements.forEach((etab) => {
            etab.sections?.forEach((sec) => {
                sec.cycles?.forEach((cyc) => {
                    cyc.niveaux?.forEach((niv) => {
                        niv.classes?.forEach((cls) => {
                            list.push({
                                ...cls,
                                niveau: {
                                    id: niv.id,
                                    nom: niv.nom,
                                    code: niv.code,
                                    cycle: {
                                        id: cyc.id,
                                        nom: cyc.nom,
                                        section: {
                                            id: sec.id,
                                            nom: sec.nom,
                                            etablissement: {
                                                id: etab.id,
                                                nom: etab.nom,
                                            },
                                        },
                                    },
                                },
                            });
                        });
                    });
                });
            });
        });
        return list;
    }, [etablissements]);

    // Classe cible sélectionnée pour information contextuelle
    const targetClasse = availableClasses.find((c) => String(c.id) === targetClasseId);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!targetClasseId) {
            toast.error('Veuillez sélectionner la classe de destination.', { position: 'top-right' });
            return;
        }

        if (inscriptions.length === 0) {
            toast.error('Aucun élève sélectionné.', { position: 'top-right' });
            return;
        }

        setLoading(true);

        router.post('/administration/inscriptions/transferer', {
            inscription_ids: inscriptions.map((i) => i.id),
            target_classe_id: targetClasseId,
            motif_transfert: motifTransfert,
        }, {
            onSuccess: () => {
                setLoading(false);
                toast.success('Transfert effectué avec succès !', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-emerald-700',
                });
                onOpenChange(false);
                setTargetClasseId('');
                setMotifTransfert('');
            },
            onError: () => {
                setLoading(false);
                toast.error('Erreur lors du transfert.', { position: 'top-right' });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg sm:text-xl">
                        <ArrowRightLeft className="size-5 text-teal-600 shrink-0" />
                        <span>Transférer {inscriptions.length} élève(s)</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Déplacez un ou plusieurs élèves vers une autre classe de l'établissement ou vers un autre campus du Groupe Scolaire (Cadrage Gesco V1.1).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Liste résumé des élèves sélectionnés */}
                    <div className="rounded-xl border border-border bg-muted/20 p-3 max-h-36 overflow-y-auto space-y-1.5">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider block mb-1">
                            Élèves concernés ({inscriptions.length}) :
                        </span>
                        {inscriptions.map((insc) => (
                            <div key={insc.id} className="flex items-center justify-between text-xs py-0.5">
                                <span className="font-semibold text-foreground">{insc.eleve.nom_complet}</span>
                                <div className="flex items-center gap-2">
                                    <Badge variant="outline" className="font-mono text-[10px]">
                                        {insc.eleve.matricule}
                                    </Badge>
                                    <span className="text-muted-foreground">({insc.classe.nom})</span>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Sélection de la classe de destination */}
                    <div>
                        <Label htmlFor="dest_classe">Classe de destination *</Label>
                        <select
                            id="dest_classe"
                            value={targetClasseId}
                            onChange={(e) => setTargetClasseId(e.target.value)}
                            required
                            className="w-full mt-1.5 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">-- Sélectionnez la classe de destination --</option>
                            {availableClasses.map((cls) => (
                                <option key={cls.id} value={cls.id}>
                                    {cls.nom} ({cls.niveau?.nom} — {cls.niveau?.cycle?.section?.etablissement?.nom})
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Alerte si transfert vers un autre établissement (Intra-groupe V1.1) */}
                    {targetClasse && (
                        <div className="p-3 rounded-xl border border-blue-200 dark:border-blue-900 bg-blue-50/70 dark:bg-blue-950/30 text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2.5">
                            <AlertTriangle className="size-4 shrink-0 mt-0.5" />
                            <div>
                                <p className="font-semibold">Type de transfert détecté :</p>
                                <p className="mt-0.5">
                                    Destination : <strong>{targetClasse.nom}</strong> ({targetClasse.niveau?.cycle?.section?.etablissement?.nom}).
                                    L'historique scolaire et les dossiers financiers seront préservés.
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Motif du transfert */}
                    <div>
                        <Label htmlFor="motif_transf">Motif du transfert (optionnel)</Label>
                        <Input
                            id="motif_transf"
                            value={motifTransfert}
                            onChange={(e) => setMotifTransfert(e.target.value)}
                            placeholder="Ex: Rééquilibrage d'effectifs, changement d'option..."
                            className="mt-1.5"
                        />
                    </div>

                    {/* Pied de modal responsive */}
                    <DialogFooter className="pt-3 border-t border-border flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} className="w-full sm:w-auto">
                            Annuler
                        </Button>
                        <Button type="submit" disabled={loading || !targetClasseId} className="gap-2 w-full sm:w-auto">
                            {loading ? 'Transfert en cours...' : 'Exécuter le Transfert'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

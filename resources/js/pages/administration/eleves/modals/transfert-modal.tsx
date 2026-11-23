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
import { ArrowRightLeft, AlertTriangle, Info } from 'lucide-react';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { buildClasseSearchOptions, flattenClassesFromEtablissements } from '@/lib/utils';
import type { Inscription, EtablissementRef } from '@/types/eleve';

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
 * - Changement de classe (même établissement) : réaffectation directe avec conservation de l'historique scolaire
 *   et recalcul automatique de la pension attendue et du solde selon le tarif cible.
 * - Transfert inter-campus (autre établissement) : archivage de l'inscription d'origine et création
 *   d'une nouvelle inscription dotée d'un compte financier séparé dans le campus d'accueil.
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

    // Liste aplatie de toutes les classes disponibles et options formatées pour SearchableSelect
    const availableClasses = React.useMemo(
        () => flattenClassesFromEtablissements(etablissements),
        [etablissements]
    );

    const classeOptions = React.useMemo(
        () => buildClasseSearchOptions(availableClasses),
        [availableClasses]
    );

    // Classe cible sélectionnée pour information contextuelle
    const targetClasse = availableClasses.find((c) => String(c.id) === targetClasseId);

    /**
     * Détermine si le transfert s'effectue au sein du même établissement, vers un autre campus
     * ou de manière mixte selon les élèves sélectionnés, afin d'afficher l'impact financier exact.
     */
    const transferScope = React.useMemo(() => {
        if (!targetClasse) return null;

        const targetEtabId =
            targetClasse.niveau?.cycle?.section?.etablissement?.id ??
            targetClasse.etablissement_id ??
            null;

        let hasSameEtab = false;
        let hasInterCampus = false;

        inscriptions.forEach((insc) => {
            const sourceEtabId =
                insc.etablissement_id ??
                insc.etablissement?.id ??
                insc.classe?.niveau?.cycle?.section?.etablissement?.id ??
                null;

            if (targetEtabId !== null && sourceEtabId !== null && Number(sourceEtabId) === Number(targetEtabId)) {
                hasSameEtab = true;
            } else {
                hasInterCampus = true;
            }
        });

        const kind: 'same_etab' | 'inter_campus' | 'mixed' =
            hasSameEtab && hasInterCampus
                ? 'mixed'
                : hasInterCampus
                  ? 'inter_campus'
                  : 'same_etab';

        return {
            kind,
            targetEtabName: targetClasse.niveau?.cycle?.section?.etablissement?.nom ?? 'Établissement cible',
        };
    }, [targetClasse, inscriptions]);

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
                        Déplacez un ou plusieurs élèves vers une autre classe de l'établissement ou vers un autre campus du Groupe Scolaire.
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

                    {/* Sélection de la classe de destination avec recherche et nom d'établissement complet en description */}
                    <div className="space-y-1.5 min-w-0">
                        <Label htmlFor="dest_classe">Classe de destination *</Label>
                        <SearchableSelect
                            id="dest_classe"
                            options={classeOptions}
                            value={targetClasseId}
                            onValueChange={(val) => setTargetClasseId(val)}
                            placeholder="-- Sélectionnez la classe de destination --"
                            searchPlaceholder="Rechercher par classe, niveau ou établissement..."
                            emptyMessage="Aucune classe trouvée pour"
                        />
                    </div>

                    {/* Bandeau contextuel distinguant changement de classe interne et transfert inter-campus */}
                    {targetClasse && transferScope && (
                        <div
                            className={`p-3 rounded-xl border text-xs flex items-start gap-2.5 ${
                                transferScope.kind === 'same_etab'
                                    ? 'border-blue-200 bg-blue-50/70 text-blue-800'
                                    : 'border-amber-200 bg-amber-50/80 text-amber-900'
                            }`}
                        >
                            {transferScope.kind === 'same_etab' ? (
                                <Info className="size-4 shrink-0 mt-0.5" />
                            ) : (
                                <AlertTriangle className="size-4 shrink-0 mt-0.5" />
                            )}
                            <div className="space-y-1">
                                <p className="font-semibold">
                                    {transferScope.kind === 'same_etab'
                                        ? 'Changement de classe (même établissement)'
                                        : transferScope.kind === 'inter_campus'
                                          ? 'Transfert inter-campus (autre établissement)'
                                          : 'Transfert mixte (interne et inter-campus)'}
                                </p>
                                <p>
                                    Destination : <strong>{targetClasse.nom}</strong> ({transferScope.targetEtabName}).{' '}
                                    {transferScope.kind === 'same_etab' ? (
                                        <>
                                            L&apos;historique scolaire (notes, assiduité, discipline) et les paiements ou remises déjà enregistrés sont conservés. Si la classe cible applique un tarif différent, la pension attendue et le solde seront recalculés automatiquement.
                                        </>
                                    ) : transferScope.kind === 'inter_campus' ? (
                                        <>
                                            L&apos;inscription d&apos;origine et ses paiements restent archivés sur le campus source. Une nouvelle inscription et un compte financier séparé (tarif du campus d&apos;accueil) seront créés immédiatement.
                                        </>
                                    ) : (
                                        <>
                                            Pour les élèves du même établissement, l&apos;historique est conservé et la pension recalculée selon la classe cible ; pour ceux venant d&apos;un autre campus, une nouvelle inscription et un compte financier séparé seront créés.
                                        </>
                                    )}
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

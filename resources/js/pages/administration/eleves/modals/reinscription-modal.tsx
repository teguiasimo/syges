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
import {
    UserCheck,
    Search,
    CheckCircle2,
    School,
    Coins,
    AlertCircle,
    ArrowRight,
    Sparkles,
} from 'lucide-react';
import type { EtablissementRef, AnneeScolaireRef, ClasseRef } from '@/types/eleve';

/**
 * Interface décrivant les détails de scolarité passée d'un ancien élève.
 */
interface DerniereClasseInfo {
    classe_id?: number;
    classe_nom: string;
    niveau_nom: string;
    annee_nom: string;
    decision_conseil?: string | null;
    statut_retenu?: 'promu' | 'redouble' | 'exclu' | null;
    is_redoublant_preconise?: boolean;
    solde_impaye_precedent?: number;
}

/**
 * Interface pour les données retournées lors de la recherche d'anciens élèves.
 */
interface AncienEleveResult {
    id: number;
    matricule: string;
    matricule_national?: string | null;
    nom: string;
    prenom?: string | null;
    nom_complet: string;
    sexe: string;
    date_naissance?: string | null;
    telephone_parent?: string | null;
    derniere_classe?: DerniereClasseInfo | null;
}

/**
 * Propriétés du composant ReinscriptionModal.
 */
interface ReinscriptionModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissements: EtablissementRef[];
    anneesScolaires: AnneeScolaireRef[];
    selectedAnneeId: number;
}

/**
 * Modale de réinscription d'un ancien élève de la Base Globale pour la nouvelle année scolaire.
 *
 * Affiche de manière ergonomique la dernière classe fréquentée et la décision du conseil
 * (Promu / Redouble) pour guider le choix de la secrétaire, et propose le report manuel
 * des arriérés financiers antérieurs sur le nouveau compte.
 *
 * @param {ReinscriptionModalProps} props
 * @returns {JSX.Element}
 */
export function ReinscriptionModal({
    open,
    onOpenChange,
    etablissements,
    selectedAnneeId,
}: ReinscriptionModalProps) {
    const [loading, setLoading] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const [searchResults, setSearchResults] = useState<AncienEleveResult[]>([]);
    const [selectedEleve, setSelectedEleve] = useState<AncienEleveResult | null>(null);

    const [targetClasseId, setTargetClasseId] = useState('');
    const [isRedoublant, setIsRedoublant] = useState('0');
    const [statut, setStatut] = useState('validee');
    const [arrieresAnterieurs, setArrieresAnterieurs] = useState('');
    const [observations, setObservations] = useState('');

    // Aplatissement de la liste des classes disponibles
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

    // Recherche d'anciens élèves via endpoint dédié
    const handleSearch = async () => {
        if (!searchQuery.trim()) return;
        setSearching(true);
        try {
            const res = await fetch(`/administration/eleves/search-anciens?q=${encodeURIComponent(searchQuery)}&annee_id=${selectedAnneeId}`);
            if (res.ok) {
                const data = await res.json();
                setSearchResults(data);
            }
        } catch {
            toast.error('Erreur lors de la recherche des anciens élèves.', {
                position: 'top-right',
            });
        } finally {
            setSearching(false);
        }
    };

    // Sélection d'un élève dans les résultats
    const handleSelectEleve = (el: AncienEleveResult) => {
        setSelectedEleve(el);
        // Pré-sélection intelligente du régime selon l'avis du conseil
        if (el.derniere_classe?.is_redoublant_preconise) {
            setIsRedoublant('1');
        } else {
            setIsRedoublant('0');
        }

        // Si l'élève avait un impayé, on peut pré-remplir ou proposer le montant
        if (el.derniere_classe?.solde_impaye_precedent && el.derniere_classe.solde_impaye_precedent > 0) {
            setArrieresAnterieurs(String(el.derniere_classe.solde_impaye_precedent));
        } else {
            setArrieresAnterieurs('');
        }
    };

    // Soumission du formulaire de réinscription
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedEleve) {
            toast.error('Veuillez sélectionner un élève à réinscrire.', {
                position: 'top-right',
            });
            return;
        }

        if (!targetClasseId) {
            toast.error('Veuillez choisir la classe de réinscription.', {
                position: 'top-right',
            });
            return;
        }

        setLoading(true);

        router.post('/administration/eleves/reinscrire', {
            eleve_id: selectedEleve.id,
            classe_id: targetClasseId,
            annee_scolaire_id: selectedAnneeId,
            is_redoublant: isRedoublant === '1',
            statut,
            arrieres_anterieurs: Number(arrieresAnterieurs) || 0,
            observations,
        }, {
            onSuccess: () => {
                setLoading(false);
                toast.success(`L'élève ${selectedEleve.nom_complet} a été réinscrit avec succès !`, {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                onOpenChange(false);
                setSelectedEleve(null);
                setSearchQuery('');
                setSearchResults([]);
                setTargetClasseId('');
                setArrieresAnterieurs('');
                setObservations('');
            },
            onError: () => {
                setLoading(false);
                toast.error('Erreur lors de la réinscription de l\'élève.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                        <UserCheck className="size-5 text-emerald-600" />
                        Réinscrire un Ancien Élève (Rentrée Scolaire)
                    </DialogTitle>
                    <DialogDescription>
                        Recherchez un élève déjà inscrit les années passées. SYGES affiche sa dernière classe et la décision du conseil pour vous guider dans son affectation.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Étape 1 : Recherche de l'élève */}
                    <div className="space-y-2">
                        <Label className="font-semibold text-sm">1. Rechercher l'élève (par Nom, Prénom ou Matricule)</Label>
                        <div className="flex gap-2">
                            <Input
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), handleSearch())}
                                placeholder="Ex: Talla, Kamga, GS24-0012..."
                                className="flex-1"
                            />
                            <Button type="button" variant="secondary" onClick={handleSearch} disabled={searching} className="gap-2">
                                <Search className="size-4" />
                                {searching ? 'Recherche...' : 'Chercher'}
                            </Button>
                        </div>
                    </div>

                    {/* Liste des résultats de recherche */}
                    {searchResults.length > 0 && !selectedEleve && (
                        <div className="border border-border rounded-xl max-h-56 overflow-y-auto divide-y divide-border shadow-inner">
                            {searchResults.map((el) => {
                                const dc = el.derniere_classe;
                                return (
                                    <div
                                        key={el.id}
                                        onClick={() => handleSelectEleve(el)}
                                        className="p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 cursor-pointer transition-colors hover:bg-muted/50"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <p className="text-sm font-bold text-foreground">{el.nom_complet}</p>
                                                <Badge variant="outline" className="font-mono text-xs text-muted-foreground">
                                                    {el.matricule}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {el.telephone_parent ? `• Tél: ${el.telephone_parent}` : ''}
                                                </span>
                                            </div>

                                            {/* Affichage de la dernière classe et avis du conseil */}
                                            {dc ? (
                                                <div className="flex items-center gap-1.5 flex-wrap text-xs pt-0.5">
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 font-medium">
                                                        <School className="size-3" />
                                                        Dernière classe : <strong>{dc.classe_nom}</strong> ({dc.annee_nom})
                                                    </span>

                                                    {dc.decision_conseil && (
                                                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-medium ${
                                                            dc.statut_retenu === 'redouble'
                                                                ? 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300'
                                                                : 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300'
                                                        }`}>
                                                            {dc.decision_conseil}
                                                        </span>
                                                    )}

                                                    {dc.solde_impaye_precedent && dc.solde_impaye_precedent > 0 ? (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 font-semibold">
                                                            <AlertCircle className="size-3" />
                                                            Impayé an passé : {dc.solde_impaye_precedent.toLocaleString('fr-FR')} FCFA
                                                        </span>
                                                    ) : null}
                                                </div>
                                            ) : (
                                                <p className="text-xs text-muted-foreground italic">
                                                    Nouvel enregistrement sans historique de scolarité
                                                </p>
                                            )}
                                        </div>

                                        <Button type="button" size="sm" variant="ghost" className="gap-1 shrink-0 text-primary self-end sm:self-center">
                                            Choisir
                                            <ArrowRight className="size-3.5" />
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* Fiche récapitulative de l'élève sélectionné */}
                    {selectedEleve && (
                        <div className="p-4 bg-slate-50 dark:bg-slate-900 border border-border rounded-xl space-y-3">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1">
                                            <CheckCircle2 className="size-3.5" />
                                            Élève prêt pour la réinscription
                                        </span>
                                    </div>
                                    <p className="text-base font-bold text-foreground mt-0.5">{selectedEleve.nom_complet}</p>
                                    <p className="text-xs text-muted-foreground font-mono">
                                        Matricule : {selectedEleve.matricule} • {selectedEleve.sexe === 'M' ? 'Garçon' : 'Fille'}
                                        {selectedEleve.telephone_parent ? ` • Parent : ${selectedEleve.telephone_parent}` : ''}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setSelectedEleve(null);
                                        setArrieresAnterieurs('');
                                    }}
                                >
                                    Changer
                                </Button>
                            </div>

                            {/* Guidage ergonomique pour la secrétaire */}
                            {selectedEleve.derniere_classe && (
                                <div className="pt-2 border-t border-border flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-muted-foreground">Historique :</span>
                                        <span className="font-semibold text-foreground">
                                            {selectedEleve.derniere_classe.classe_nom} ({selectedEleve.derniere_classe.annee_nom})
                                        </span>
                                        {selectedEleve.derniere_classe.decision_conseil && (
                                            <Badge
                                                variant="outline"
                                                className={`text-[11px] font-semibold ${
                                                    selectedEleve.derniere_classe.statut_retenu === 'redouble'
                                                        ? 'border-amber-400 text-amber-700 bg-amber-50 dark:bg-amber-950/40'
                                                        : 'border-emerald-400 text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40'
                                                }`}
                                            >
                                                {selectedEleve.derniere_classe.decision_conseil}
                                            </Badge>
                                        )}
                                    </div>

                                    {/* Action rapide pour reporter l'impayé précédent */}
                                    {selectedEleve.derniere_classe.solde_impaye_precedent && selectedEleve.derniere_classe.solde_impaye_precedent > 0 ? (
                                        <div className="flex items-center gap-2">
                                            <span className="text-rose-600 font-semibold">
                                                Dette précédente : {selectedEleve.derniere_classe.solde_impaye_precedent.toLocaleString('fr-FR')} FCFA
                                            </span>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="secondary"
                                                className="h-6 text-[11px] px-2 bg-rose-100 hover:bg-rose-200 text-rose-800 dark:bg-rose-950 dark:text-rose-200 gap-1"
                                                onClick={() => setArrieresAnterieurs(String(selectedEleve.derniere_classe?.solde_impaye_precedent))}
                                            >
                                                <Sparkles className="size-3" />
                                                Reporter
                                            </Button>
                                        </div>
                                    ) : null}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Étape 2 : Choix de la classe et options */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-border">
                        <div className="sm:col-span-2">
                            <Label htmlFor="target_classe" className="font-semibold text-sm">
                                2. Nouvelle Classe d'Affectation *
                            </Label>
                            <select
                                id="target_classe"
                                value={targetClasseId}
                                onChange={(e) => setTargetClasseId(e.target.value)}
                                required
                                className="w-full mt-1.5 h-10 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="">-- Sélectionnez la nouvelle classe --</option>
                                {availableClasses.map((cls) => (
                                    <option key={cls.id} value={cls.id}>
                                        {cls.nom} ({cls.niveau?.nom ?? 'Niveau'} — {cls.niveau?.cycle?.section?.etablissement?.nom ?? ''})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <Label htmlFor="reinsc_redoublant" className="font-semibold text-sm">
                                Régime de l'Élève
                            </Label>
                            <select
                                id="reinsc_redoublant"
                                value={isRedoublant}
                                onChange={(e) => setIsRedoublant(e.target.value)}
                                className="w-full mt-1.5 h-10 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="0">Promu (Niveau supérieur)</option>
                                <option value="1">Redoublant (Répète la classe)</option>
                            </select>
                        </div>

                        <div>
                            <Label htmlFor="reinsc_statut" className="font-semibold text-sm">
                                Statut de la Réinscription
                            </Label>
                            <select
                                id="reinsc_statut"
                                value={statut}
                                onChange={(e) => setStatut(e.target.value)}
                                className="w-full mt-1.5 h-10 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="validee">Validée (Officielle - Effectif confirmé)</option>
                                <option value="en_attente">En attente (Pré-inscription)</option>
                            </select>
                        </div>

                        {/* Champ Arriérés Antérieurs reportés */}
                        <div className="sm:col-span-2 p-3 bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/50 rounded-xl space-y-1.5">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="reinsc_arrieres" className="font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5 text-xs">
                                    <Coins className="size-4 text-amber-600 shrink-0" />
                                    Arriérés de l'année précédente à reporter (FCFA) — Optionnel
                                </Label>
                                {arrieresAnterieurs && Number(arrieresAnterieurs) > 0 && (
                                    <span className="text-xs font-bold text-amber-700 dark:text-amber-300">
                                        + {Number(arrieresAnterieurs).toLocaleString('fr-FR')} FCFA au solde initial
                                    </span>
                                )}
                            </div>
                            <Input
                                id="reinsc_arrieres"
                                type="number"
                                min={0}
                                step={500}
                                value={arrieresAnterieurs}
                                onChange={(e) => setArrieresAnterieurs(e.target.value)}
                                placeholder="0 (Laissez vide ou 0 si l'élève n'a pas de dette de l'an dernier)"
                                className="bg-background mt-1 font-semibold"
                            />
                            <p className="text-[11px] text-muted-foreground">
                                Ce montant sera reporté comme arriéré sur le compte financier de l'élève pour la nouvelle année et sera exigible à la caisse.
                            </p>
                        </div>

                        <div className="sm:col-span-2">
                            <Label htmlFor="reinsc_obs" className="font-semibold text-sm">
                                Observations Administratives
                            </Label>
                            <Input
                                id="reinsc_obs"
                                value={observations}
                                onChange={(e) => setObservations(e.target.value)}
                                placeholder="Remarques particulières (ex: dispense, transfert interne...)"
                                className="mt-1.5"
                            />
                        </div>
                    </div>

                    <DialogFooter className="pt-4 border-t border-border">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={loading || !selectedEleve || !targetClasseId}
                            className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            <CheckCircle2 className="size-4" />
                            {loading ? 'Enregistrement...' : 'Valider la Réinscription'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

import React, { useState, useMemo } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
    PlusCircle,
    Check,
    Users,
    User,
    RefreshCw,
    X,
} from 'lucide-react';
import { SearchableSelect, type SearchableOption } from '@/components/ui/searchable-select';
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
 * Interface pour un élève chargé dans le cadre de la réinscription en lot.
 */
interface BatchEleveItem {
    eleve_id: number;
    matricule: string;
    matricule_national?: string | null;
    nom: string;
    prenom?: string | null;
    nom_complet: string;
    sexe: string;
    date_naissance?: string | null;
    telephone_parent?: string | null;
    deja_inscrit: boolean;
    classe_actuelle_nom?: string | null;
    decision_conseil?: string | null;
    statut_retenu?: 'promu' | 'redouble' | 'exclu' | null;
    is_redoublant_preconise?: boolean;
    solde_impaye_precedent: number;
    // États locaux d'édition dans le tableau
    is_selected: boolean;
    is_redoublant: boolean;
    arrieres_anterieurs: number;
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
 * Modale de réinscription des anciens élèves de la Base Globale pour la rentrée scolaire.
 * Propose 2 modes ergonomiques :
 * 1. Réinscription individuelle (avec recherche d'élève, avis de fin d'année, et bouton "+ Réinscrire & Nouveau")
 * 2. Réinscription collective en lot (par classe, pour promouvoir toute une promotion en 1 clic)
 *
 * @param {ReinscriptionModalProps} props Propriétés du composant
 * @returns {JSX.Element}
 */
export function ReinscriptionModal({
    open,
    onOpenChange,
    etablissements,
    selectedAnneeId,
}: ReinscriptionModalProps) {
    // Mode de travail : 'individuelle' ou 'lot'
    const [mode, setMode] = useState<'individuelle' | 'lot'>('individuelle');
    const [loading, setLoading] = useState(false);

    // ─────────────────────────────────────────────────────────────
    // ÉTATS POUR LE MODE INDIVIDUEL
    // ─────────────────────────────────────────────────────────────
    const [searchQuery, setSearchQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const [searchResults, setSearchResults] = useState<AncienEleveResult[]>([]);
    const [selectedEleve, setSelectedEleve] = useState<AncienEleveResult | null>(null);

    const [targetClasseId, setTargetClasseId] = useState('');
    const [isRedoublant, setIsRedoublant] = useState('0');
    const [statut, setStatut] = useState('validee');
    const [arrieresAnterieurs, setArrieresAnterieurs] = useState('');
    const [observations, setObservations] = useState('');

    // ─────────────────────────────────────────────────────────────
    // ÉTATS POUR LE MODE RÉINSCRIPTION EN LOT (PAR CLASSE)
    // ─────────────────────────────────────────────────────────────
    const [batchSourceClasseId, setBatchSourceClasseId] = useState('');
    const [batchTargetClasseId, setBatchTargetClasseId] = useState('');
    const [batchLoading, setBatchLoading] = useState(false);
    const [batchEleves, setBatchEleves] = useState<BatchEleveItem[]>([]);

    // Aplatissement de la liste de toutes les classes disponibles
    const availableClasses: ClasseRef[] = useMemo(() => {
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

    // Options formatées pour SearchableSelect
    const classeOptions: SearchableOption[] = useMemo(() => {
        return availableClasses.map((cls) => {
            const etabNom = cls.niveau?.cycle?.section?.etablissement?.nom || '';
            const etabAbrege = etabNom.length > 25 ? `${etabNom.substring(0, 25)}…` : etabNom;
            return {
                value: String(cls.id),
                label: `${cls.nom} (${cls.niveau?.nom || 'Niveau'}${etabAbrege ? ` — ${etabAbrege}` : ''})`,
                group: cls.niveau?.nom || 'Classes',
            };
        });
    }, [availableClasses]);

    // ─────────────────────────────────────────────────────────────
    // LOGIQUE MODE INDIVIDUEL
    // ─────────────────────────────────────────────────────────────

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

        // Si l'élève avait un impayé, pré-remplir le montant
        if (el.derniere_classe?.solde_impaye_precedent && el.derniere_classe.solde_impaye_precedent > 0) {
            setArrieresAnterieurs(String(el.derniere_classe.solde_impaye_precedent));
        } else {
            setArrieresAnterieurs('');
        }
    };

    /**
     * Soumission de la réinscription individuelle.
     * Supporte l'action 'save_and_new' pour enchaîner immédiatement sur le prochain élève.
     *
     * @param {'save_and_close' | 'save_and_new'} action Type d'action après enregistrement
     */
    const handleIndividualSubmit = (action: 'save_and_close' | 'save_and_new') => {
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
                    style: {
                        backgroundColor: '#10b981',
                        color: '#ffffff',
                        border: '1px solid #059669',
                    },
                });

                if (action === 'save_and_new') {
                    // Réinitialisation de l'élève actuel tout en conservant la classe sélectionnée
                    setSelectedEleve(null);
                    setSearchQuery('');
                    setSearchResults([]);
                    setArrieresAnterieurs('');
                    setObservations('');
                } else {
                    // Fermeture de la modale
                    onOpenChange(false);
                    setSelectedEleve(null);
                    setSearchQuery('');
                    setSearchResults([]);
                    setTargetClasseId('');
                    setArrieresAnterieurs('');
                    setObservations('');
                }
            },
            onError: () => {
                setLoading(false);
                toast.error('Erreur lors de la réinscription de l\'élève.', {
                    position: 'top-right',
                });
            },
        });
    };

    // ─────────────────────────────────────────────────────────────
    // LOGIQUE MODE RÉINSCRIPTION EN LOT (PAR CLASSE)
    // ─────────────────────────────────────────────────────────────

    /**
     * Charge les élèves de la classe source sélectionnée pour préparer la réinscription collective.
     *
     * @param {string} sourceId Identifiant de la classe source
     */
    const loadBatchEleves = async (sourceId: string) => {
        setBatchSourceClasseId(sourceId);
        if (!sourceId) {
            setBatchEleves([]);
            return;
        }

        setBatchLoading(true);
        try {
            const res = await fetch(`/administration/eleves/classe-anciens?classe_id=${sourceId}&annee_destination_id=${selectedAnneeId}`);
            if (res.ok) {
                const data: any[] = await res.json();
                const formatted: BatchEleveItem[] = data.map((d) => ({
                    eleve_id: d.eleve_id,
                    matricule: d.matricule,
                    matricule_national: d.matricule_national,
                    nom: d.nom,
                    prenom: d.prenom,
                    nom_complet: d.nom_complet,
                    sexe: d.sexe,
                    date_naissance: d.date_naissance,
                    telephone_parent: d.telephone_parent,
                    deja_inscrit: Boolean(d.deja_inscrit),
                    classe_actuelle_nom: d.classe_actuelle_nom,
                    decision_conseil: d.decision_conseil,
                    statut_retenu: d.statut_retenu,
                    is_redoublant_preconise: Boolean(d.is_redoublant_preconise),
                    solde_impaye_precedent: Number(d.solde_impaye_precedent) || 0,
                    // Par défaut, cocher uniquement les élèves qui ne sont pas déjà inscrits
                    is_selected: !d.deja_inscrit,
                    is_redoublant: Boolean(d.is_redoublant_preconise),
                    arrieres_anterieurs: Number(d.solde_impaye_precedent) || 0,
                }));
                setBatchEleves(formatted);
            }
        } catch {
            toast.error('Impossible de charger les élèves de la classe sélectionnée.', {
                position: 'top-right',
            });
        } finally {
            setBatchLoading(false);
        }
    };

    // Nombre d'élèves éligibles (non déjà inscrits)
    const eligibleBatchCount = useMemo(() => {
        return batchEleves.filter((e) => !e.deja_inscrit).length;
    }, [batchEleves]);

    // Nombre d'élèves actuellement cochés
    const selectedBatchCount = useMemo(() => {
        return batchEleves.filter((e) => e.is_selected && !e.deja_inscrit).length;
    }, [batchEleves]);

    // Bascule globale de sélection
    const toggleAllBatch = (checked: boolean) => {
        setBatchEleves((prev) =>
            prev.map((e) => (e.deja_inscrit ? e : { ...e, is_selected: checked }))
        );
    };

    // Bascule de sélection d'une ligne
    const toggleBatchItem = (eleveId: number, checked: boolean) => {
        setBatchEleves((prev) =>
            prev.map((e) => (e.eleve_id === eleveId ? { ...e, is_selected: checked } : e))
        );
    };

    // Modification du régime (Promu vs Redoublant) d'une ligne
    const toggleBatchRedoublant = (eleveId: number, isRedoublantVal: boolean) => {
        setBatchEleves((prev) =>
            prev.map((e) => (e.eleve_id === eleveId ? { ...e, is_redoublant: isRedoublantVal } : e))
        );
    };

    /**
     * Soumission de la réinscription collective en lot.
     */
    const handleBatchSubmit = () => {
        if (!batchTargetClasseId) {
            toast.error('Veuillez choisir la classe de destination.', {
                position: 'top-right',
            });
            return;
        }

        const toRegister = batchEleves.filter((e) => e.is_selected && !e.deja_inscrit);
        if (toRegister.length === 0) {
            toast.error('Veuillez sélectionner au moins un élève à réinscrire.', {
                position: 'top-right',
            });
            return;
        }

        setLoading(true);

        router.post('/administration/eleves/reinscrire-lot', {
            annee_scolaire_id: selectedAnneeId,
            target_classe_id: batchTargetClasseId,
            eleves: toRegister.map((e) => ({
                eleve_id: e.eleve_id,
                is_redoublant: e.is_redoublant,
                arrieres_anterieurs: e.arrieres_anterieurs,
            })),
            statut: 'validee',
        }, {
            onSuccess: () => {
                setLoading(false);
                toast.success(`${toRegister.length} élève(s) réinscrit(s) avec succès en lot !`, {
                    position: 'top-right',
                    style: {
                        backgroundColor: '#10b981',
                        color: '#ffffff',
                        border: '1px solid #059669',
                    },
                });
                onOpenChange(false);
                setBatchEleves([]);
                setBatchSourceClasseId('');
                setBatchTargetClasseId('');
            },
            onError: () => {
                setLoading(false);
                toast.error('Erreur lors de la réinscription collective en lot.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                        <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                            <UserCheck className="size-5" />
                        </div>
                        Réinscription des Anciens Élèves (Rentrée Scolaire)
                    </DialogTitle>
                    <DialogDescription>
                        Affectez les anciens élèves dans leurs nouvelles classes pour l'année scolaire en cours.
                    </DialogDescription>
                </DialogHeader>

                {/* Commutateur de mode : Individuel vs En Lot */}
                <div className="flex items-center p-1 bg-muted/50 rounded-xl border border-border w-fit gap-1">
                    <button
                        type="button"
                        onClick={() => setMode('individuelle')}
                        className={`flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                            mode === 'individuelle'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <User className="size-3.5" />
                        Réinscription Individuelle
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('lot')}
                        className={`flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                            mode === 'lot'
                                ? 'bg-background text-emerald-700 dark:text-emerald-400 shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Users className="size-3.5 text-emerald-600" />
                        Réinscription Collective (En Lot)
                        <span className="text-[10px] px-1.5 py-0.2 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold">
                            Par Classe
                        </span>
                    </button>
                </div>

                {/* ═══════════════════════════════════════════════════════════
                     MODE 1 : RÉINSCRIPTION INDIVIDUELLE
                     ═══════════════════════════════════════════════════════════ */}
                {mode === 'individuelle' && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleIndividualSubmit('save_and_close');
                        }}
                        className="space-y-4 pt-1"
                    >
                        {/* Étape 1 : Recherche de l'élève */}
                        <div className="space-y-2">
                            <Label className="font-semibold text-xs text-foreground">
                                1. Rechercher l'élève (par Nom, Prénom ou Matricule)
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), handleSearch())}
                                    placeholder="Ex: Talla, Kamga, 25-0012..."
                                    className="flex-1 h-9 text-sm"
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={handleSearch}
                                    disabled={searching}
                                    className="gap-2 h-9 text-xs"
                                >
                                    <Search className="size-3.5" />
                                    {searching ? 'Recherche...' : 'Chercher'}
                                </Button>
                            </div>
                        </div>

                        {/* Résultats de recherche */}
                        {searchResults.length > 0 && !selectedEleve && (
                            <div className="border border-border rounded-xl max-h-52 overflow-y-auto divide-y divide-border shadow-inner">
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

                                                {/* Dernière classe et avis du conseil */}
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
                                                                Impayé : {dc.solde_impaye_precedent.toLocaleString('fr-FR')} FCFA
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                ) : (
                                                    <p className="text-xs text-muted-foreground italic">
                                                        Élève sans antécédent d'inscription enregistré
                                                    </p>
                                                )}
                                            </div>

                                            <Button type="button" size="sm" variant="ghost" className="gap-1 shrink-0 text-primary self-end sm:self-center h-8 text-xs">
                                                Choisir
                                                <ArrowRight className="size-3.5" />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* Fiche de l'élève sélectionné */}
                        {selectedEleve && (
                            <div className="p-3.5 bg-slate-50 dark:bg-slate-900 border border-border rounded-xl space-y-2.5">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <span className="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1">
                                            <CheckCircle2 className="size-3.5" />
                                            Élève prêt pour la réinscription
                                        </span>
                                        <p className="text-base font-bold text-foreground mt-0.5">{selectedEleve.nom_complet}</p>
                                        <p className="text-xs text-muted-foreground font-mono">
                                            Matricule : {selectedEleve.matricule} • {selectedEleve.sexe === 'M' ? 'Garçon' : 'Fille'}
                                            {selectedEleve.telephone_parent ? ` • Tél: ${selectedEleve.telephone_parent}` : ''}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-8 text-xs"
                                        onClick={() => {
                                            setSelectedEleve(null);
                                            setArrieresAnterieurs('');
                                        }}
                                    >
                                        Changer
                                    </Button>
                                </div>

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

                                        {selectedEleve.derniere_classe.solde_impaye_precedent && selectedEleve.derniere_classe.solde_impaye_precedent > 0 ? (
                                            <div className="flex items-center gap-2">
                                                <span className="text-rose-600 font-semibold text-xs">
                                                    Dette : {selectedEleve.derniere_classe.solde_impaye_precedent.toLocaleString('fr-FR')} FCFA
                                                </span>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="secondary"
                                                    className="h-6 text-[10px] px-2 bg-rose-100 hover:bg-rose-200 text-rose-800 dark:bg-rose-950 dark:text-rose-200 gap-1"
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
                                <Label htmlFor="target_classe" className="font-semibold text-xs">
                                    2. Nouvelle Classe d'Affectation *
                                </Label>
                                <div className="mt-1">
                                    <SearchableSelect
                                        options={classeOptions}
                                        value={targetClasseId}
                                        onValueChange={setTargetClasseId}
                                        placeholder="Sélectionner ou chercher la classe..."
                                        searchPlaceholder="Taper le nom de la classe..."
                                        emptyMessage="Aucune classe trouvée."
                                    />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="reinsc_redoublant" className="font-semibold text-xs">
                                    Régime de l'Élève
                                </Label>
                                <select
                                    id="reinsc_redoublant"
                                    value={isRedoublant}
                                    onChange={(e) => setIsRedoublant(e.target.value)}
                                    className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="0">Promu (Niveau supérieur)</option>
                                    <option value="1">Redoublant (Répète la classe)</option>
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="reinsc_statut" className="font-semibold text-xs">
                                    Statut de la Réinscription
                                </Label>
                                <select
                                    id="reinsc_statut"
                                    value={statut}
                                    onChange={(e) => setStatut(e.target.value)}
                                    className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="validee">Validée (Officielle - Effectif confirmé)</option>
                                    <option value="en_attente">En attente (Pré-inscription)</option>
                                </select>
                            </div>

                            {/* Report d'arriérés */}
                            <div className="sm:col-span-2 p-3 bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/50 rounded-xl space-y-1">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="reinsc_arrieres" className="font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5 text-xs">
                                        <Coins className="size-3.5 text-amber-600 shrink-0" />
                                        Arriérés de l'année précédente à reporter (FCFA)
                                    </Label>
                                    {arrieresAnterieurs && Number(arrieresAnterieurs) > 0 && (
                                        <span className="text-xs font-bold text-amber-700 dark:text-amber-300">
                                            + {Number(arrieresAnterieurs).toLocaleString('fr-FR')} FCFA
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
                                    placeholder="0 (Laissez vide si pas d'arriérés)"
                                    className="bg-background mt-1 h-8 text-sm font-semibold"
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="reinsc_obs" className="font-semibold text-xs">
                                    Observations Administratives
                                </Label>
                                <Input
                                    id="reinsc_obs"
                                    value={observations}
                                    onChange={(e) => setObservations(e.target.value)}
                                    placeholder="Remarques particulières (ex: dispense, réorientation...)"
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>
                        </div>

                        {/* Footer avec hiérarchie : Annuler | + Réinscrire & Nouveau | Valider */}
                        <DialogFooter className="pt-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => onOpenChange(false)}
                                className="text-xs h-9"
                            >
                                Annuler
                            </Button>

                            <div className="flex items-center gap-2 w-full sm:w-auto">
                                {/* Bouton demandé : Réinscrire et Nouveau (garde la modale ouverte pour le prochain cas) */}
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={loading || !selectedEleve || !targetClasseId}
                                                onClick={() => handleIndividualSubmit('save_and_new')}
                                                className="flex-1 sm:flex-initial text-xs h-9 border-indigo-400/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 gap-1.5 font-medium"
                                            >
                                                <PlusCircle className="size-3.5 text-indigo-600" />
                                                Réinscrire et Nouveau
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" className="text-xs">
                                            Valide cette réinscription et réinitialise l'élève pour réinscrire le suivant sans fermer la boîte.
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <Button
                                    type="submit"
                                    disabled={loading || !selectedEleve || !targetClasseId}
                                    className="flex-1 sm:flex-initial gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9"
                                >
                                    <Check className="size-3.5" />
                                    {loading ? 'Enregistrement...' : 'Valider la Réinscription'}
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                )}

                {/* ═══════════════════════════════════════════════════════════
                     MODE 2 : RÉINSCRIPTION COLLECTIVE EN LOT (PAR CLASSE)
                     ═══════════════════════════════════════════════════════════ */}
                {mode === 'lot' && (
                    <div className="space-y-4 pt-1">
                        {/* Sélecteurs des deux classes : Source et Cible */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-border">
                            <div>
                                <Label className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <School className="size-3.5 text-blue-600" />
                                    1. Classe d'origine (Année précédente) *
                                </Label>
                                <div className="mt-1">
                                    <SearchableSelect
                                        options={classeOptions}
                                        value={batchSourceClasseId}
                                        onValueChange={(val) => loadBatchEleves(val)}
                                        placeholder="Sélectionner la classe source..."
                                        searchPlaceholder="Chercher la classe d'origine..."
                                        emptyMessage="Aucune classe trouvée."
                                    />
                                </div>
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    Sélectionnez la classe dont vous souhaitez réinscrire les élèves.
                                </p>
                            </div>

                            <div>
                                <Label className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <ArrowRight className="size-3.5 text-emerald-600" />
                                    2. Nouvelle Classe de destination *
                                </Label>
                                <div className="mt-1">
                                    <SearchableSelect
                                        options={classeOptions}
                                        value={batchTargetClasseId}
                                        onValueChange={setBatchTargetClasseId}
                                        placeholder="Sélectionner la classe cible..."
                                        searchPlaceholder="Chercher la nouvelle classe..."
                                        emptyMessage="Aucune classe trouvée."
                                    />
                                </div>
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    Classe d'accueil pour la nouvelle année scolaire.
                                </p>
                            </div>
                        </div>

                        {/* État de chargement des élèves */}
                        {batchLoading && (
                            <div className="py-8 flex flex-col items-center justify-center gap-2 text-muted-foreground">
                                <RefreshCw className="size-5 animate-spin text-primary" />
                                <span className="text-xs">Chargement des élèves de la classe...</span>
                            </div>
                        )}

                        {/* Liste des élèves de la classe avec cases à cocher */}
                        {!batchLoading && batchEleves.length > 0 && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between px-1">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="select_all_batch"
                                            checked={eligibleBatchCount > 0 && selectedBatchCount === eligibleBatchCount}
                                            onCheckedChange={(checked) => toggleAllBatch(Boolean(checked))}
                                        />
                                        <Label
                                            htmlFor="select_all_batch"
                                            className="text-xs font-bold cursor-pointer select-none"
                                        >
                                            Tout sélectionner ({selectedBatchCount}/{eligibleBatchCount} éligibles)
                                        </Label>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        Total cohorte : {batchEleves.length} élève(s)
                                    </span>
                                </div>

                                <div className="border border-border rounded-xl max-h-64 overflow-y-auto divide-y divide-border">
                                    {batchEleves.map((el) => {
                                        return (
                                            <div
                                                key={el.eleve_id}
                                                className={`p-2.5 flex items-center justify-between gap-3 text-xs transition-colors ${
                                                    el.deja_inscrit
                                                        ? 'bg-muted/40 opacity-70 cursor-not-allowed'
                                                        : el.is_selected
                                                        ? 'bg-emerald-50/50 dark:bg-emerald-950/20'
                                                        : 'hover:bg-muted/30'
                                                }`}
                                            >
                                                <div className="flex items-center gap-3 min-w-0">
                                                    <Checkbox
                                                        checked={el.is_selected && !el.deja_inscrit}
                                                        disabled={el.deja_inscrit}
                                                        onCheckedChange={(chk) => toggleBatchItem(el.eleve_id, Boolean(chk))}
                                                    />

                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <span className="font-bold text-foreground truncate">
                                                                {el.nom_complet}
                                                            </span>
                                                            <span className="font-mono text-[11px] text-muted-foreground">
                                                                [{el.matricule}]
                                                            </span>
                                                            <span className="text-[11px] text-muted-foreground">
                                                                {el.sexe === 'M' ? 'G' : 'F'}
                                                            </span>
                                                        </div>

                                                        <div className="flex items-center gap-2 pt-0.5 flex-wrap">
                                                            {el.decision_conseil && (
                                                                <span className={`text-[10px] px-1.5 py-0.2 rounded font-semibold ${
                                                                    el.statut_retenu === 'redouble'
                                                                        ? 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-200'
                                                                        : 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-200'
                                                                }`}>
                                                                    Avis : {el.decision_conseil}
                                                                </span>
                                                            )}

                                                            {el.solde_impaye_precedent > 0 && (
                                                                <span className="text-[10px] text-rose-600 font-semibold">
                                                                    Dette : {el.solde_impaye_precedent.toLocaleString('fr-FR')} FCFA
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Actions d'affectation par ligne */}
                                                <div className="flex items-center gap-2 shrink-0">
                                                    {el.deja_inscrit ? (
                                                        <Badge variant="secondary" className="text-[10px] bg-slate-200 dark:bg-slate-800">
                                                            Déjà inscrit {el.classe_actuelle_nom ? `(${el.classe_actuelle_nom})` : ''}
                                                        </Badge>
                                                    ) : (
                                                        <select
                                                            value={el.is_redoublant ? '1' : '0'}
                                                            onChange={(e) => toggleBatchRedoublant(el.eleve_id, e.target.value === '1')}
                                                            className="h-7 text-[11px] rounded border border-input bg-background px-2 focus:ring-1 focus:ring-ring"
                                                        >
                                                            <option value="0">Promu</option>
                                                            <option value="1">Redoublant</option>
                                                        </select>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {!batchLoading && batchSourceClasseId && batchEleves.length === 0 && (
                            <div className="py-8 text-center text-xs text-muted-foreground border border-dashed rounded-xl">
                                Aucun élève trouvé dans cette classe d'origine.
                            </div>
                        )}

                        {/* Footer pour le mode lot */}
                        <DialogFooter className="pt-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => onOpenChange(false)}
                                className="text-xs h-9"
                            >
                                Annuler
                            </Button>

                            <Button
                                type="button"
                                onClick={handleBatchSubmit}
                                disabled={loading || selectedBatchCount === 0 || !batchTargetClasseId}
                                className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9"
                            >
                                <CheckCircle2 className="size-4" />
                                {loading
                                    ? 'Réinscription en cours...'
                                    : `Réinscrire les ${selectedBatchCount} élève(s) sélectionné(s)`}
                            </Button>
                        </DialogFooter>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

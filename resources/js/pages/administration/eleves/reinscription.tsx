import React, { useState, useMemo, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    UserCheck,
    Users,
    User,
    Search,
    CheckCircle2,
    School,
    Coins,
    AlertCircle,
    PlusCircle,
    Check,
    RefreshCw,
    X,
    Filter,
    Calendar,
    Award,
    HelpCircle,
    CheckSquare,
    Square,
    Layers,
    Clock,
    Sparkles,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SearchableSelect, type SearchableOption } from '@/components/ui/searchable-select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { toast } from 'sonner';
import { formatDate } from '@/lib/utils';
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
 * Interface pour un élève chargé dans le cadre de la promotion collective.
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
    // États locaux réactifs
    is_selected: boolean;
    is_redoublant: boolean;
    arrieres_anterieurs: number;
}

/**
 * Propriétés reçues du contrôleur Inertia pour la page dédiée de réinscription.
 */
interface ReinscriptionPageProps {
    /** Liste des campus et de leur structure pédagogique */
    etablissements: EtablissementRef[];
    /** Liste de l'ensemble des années scolaires enregistrées */
    anneesScolaires: AnneeScolaireRef[];
    /** Identifiant de l'année scolaire de destination (nouvelle année active) */
    selectedAnneeId: number;
    /** Identifiant de l'année scolaire source (année passée de référence) */
    sourceAnneeId: number;
    /** Matrice des permissions de l'utilisateur connecté */
    can: {
        inscriptions_reinscrire: boolean;
        eleves_view: boolean;
        eleves_manage: boolean;
    };
}

/**
 * Page dédiée à la Réinscription et Promotion des Anciens Élèves.
 *
 * Fournit un espace de travail complet et spacieux pour :
 * 1. La promotion collective de cohorte par classe (passage en lot d'une année à l'autre avec sélection personnalisée d'élèves).
 * 2. La réinscription individuelle d'élèves de promotions antérieures.
 *
 * @param {ReinscriptionPageProps} props Propriétés du composant
 * @returns {JSX.Element} Vue complète de réinscription
 */
export default function ReinscriptionPage({
    etablissements,
    anneesScolaires,
    selectedAnneeId,
    sourceAnneeId,
    can,
}: ReinscriptionPageProps) {
    // Lecture de l'onglet actif depuis les query params ou 'lot' par défaut
    const initialTab = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab') === 'individuel'
        ? 'individuel'
        : 'lot';
    const [activeTab, setActiveTab] = useState<'lot' | 'individuel'>(initialTab);

    // Année scolaire de destination (active) et année source (antérieure)
    const [destAnneeId, setDestAnneeId] = useState<string>(String(selectedAnneeId || ''));
    const [srcAnneeId, setSrcAnneeId] = useState<string>(String(sourceAnneeId || ''));

    const [submitting, setSubmitting] = useState(false);

    // ─────────────────────────────────────────────────────────────
    // EXTRACTION DE LA LISTE DE TOUTES LES CLASSES DU SYSTÈME
    // ─────────────────────────────────────────────────────────────
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

    // Options formatées pour SearchableSelect (avec regroupement par niveau et campus)
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
    // 1. ÉTATS & LOGIQUE POUR LA PROMOTION COLLECTIVE (EN LOT)
    // ─────────────────────────────────────────────────────────────
    const [batchSourceClasseId, setBatchSourceClasseId] = useState<string>('');
    const [batchTargetClasseId, setBatchTargetClasseId] = useState<string>('');
    const [batchLoading, setBatchLoading] = useState(false);
    const [batchEleves, setBatchEleves] = useState<BatchEleveItem[]>([]);
    const [batchSearchFilter, setBatchSearchFilter] = useState('');
    const [batchTargetStatut, setBatchTargetStatut] = useState<'validee' | 'en_attente'>('validee');

    /**
     * Charge les élèves d'une classe source pour une année scolaire d'origine donnée.
     *
     * @param {string} sourceId Identifiant de la classe source
     * @param {string} sourceAnnee Identifiant de l'année source
     * @param {string} destAnnee Identifiant de l'année cible
     */
    const loadBatchEleves = async (sourceId: string, sourceAnnee: string, destAnnee: string) => {
        if (!sourceId) {
            setBatchEleves([]);
            return;
        }

        setBatchLoading(true);
        try {
            const url = `/administration/eleves/classe-anciens?classe_id=${sourceId}&annee_source_id=${sourceAnnee}&annee_destination_id=${destAnnee}`;
            const res = await fetch(url);
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
                    // Par défaut, cocher uniquement les élèves non encore inscrits
                    is_selected: !d.deja_inscrit,
                    is_redoublant: Boolean(d.is_redoublant_preconise),
                    arrieres_anterieurs: Number(d.solde_impaye_precedent) || 0,
                }));
                setBatchEleves(formatted);
            } else {
                toast.error('Impossible de charger les élèves de la classe sélectionnée.', {
                    position: 'top-right',
                });
            }
        } catch {
            toast.error('Erreur réseau lors de la récupération des élèves.', {
                position: 'top-right',
            });
        } finally {
            setBatchLoading(false);
        }
    };

    // Recharger la liste dès que la classe source ou l'une des années change
    useEffect(() => {
        if (batchSourceClasseId) {
            loadBatchEleves(batchSourceClasseId, srcAnneeId, destAnneeId);
        }
    }, [batchSourceClasseId, srcAnneeId, destAnneeId]);

    // Filtrage textuel rapide des élèves dans le tableau de lot
    const filteredBatchEleves = useMemo(() => {
        if (!batchSearchFilter.trim()) return batchEleves;
        const q = batchSearchFilter.toLowerCase();
        return batchEleves.filter(
            (e) =>
                e.nom_complet.toLowerCase().includes(q) ||
                e.matricule.toLowerCase().includes(q) ||
                (e.telephone_parent && e.telephone_parent.includes(q))
        );
    }, [batchEleves, batchSearchFilter]);

    // Métriques et compteurs pour la promotion collective
    const totalBatchCount = batchEleves.length;
    const eligibleBatchCount = useMemo(() => batchEleves.filter((e) => !e.deja_inscrit).length, [batchEleves]);
    const selectedBatchCount = useMemo(() => batchEleves.filter((e) => e.is_selected && !e.deja_inscrit).length, [batchEleves]);
    const dejaInscritsCount = useMemo(() => batchEleves.filter((e) => e.deja_inscrit).length, [batchEleves]);
    const totalArrieresSelected = useMemo(
        () => batchEleves.filter((e) => e.is_selected && !e.deja_inscrit).reduce((acc, curr) => acc + (curr.arrieres_anterieurs || 0), 0),
        [batchEleves]
    );

    // Actions rapides de sélection
    const handleSelectAllEligible = () => {
        setBatchEleves((prev) => prev.map((e) => (e.deja_inscrit ? e : { ...e, is_selected: true })));
    };

    const handleSelectPromusOnly = () => {
        setBatchEleves((prev) =>
            prev.map((e) => {
                if (e.deja_inscrit) return e;
                // Est considéré promu si son statut retenu est 'promu' ou si non préconisé redoublant
                const isPromu = Boolean(e.statut_retenu === 'promu' || (!e.is_redoublant_preconise && e.statut_retenu !== 'redouble'));
                return { ...e, is_selected: isPromu };
            })
        );
    };

    const handleSelectRedoublantsOnly = () => {
        setBatchEleves((prev) =>
            prev.map((e) => {
                if (e.deja_inscrit) return e;
                const isRedoublant = Boolean(e.statut_retenu === 'redouble' || e.is_redoublant_preconise);
                return { ...e, is_selected: isRedoublant };
            })
        );
    };

    const handleDeselectAll = () => {
        setBatchEleves((prev) => prev.map((e) => ({ ...e, is_selected: false })));
    };

    const toggleBatchItem = (eleveId: number, checked: boolean) => {
        setBatchEleves((prev) =>
            prev.map((e) => (e.eleve_id === eleveId ? { ...e, is_selected: checked } : e))
        );
    };

    const toggleBatchRedoublant = (eleveId: number, isRedoublantVal: boolean) => {
        setBatchEleves((prev) =>
            prev.map((e) => (e.eleve_id === eleveId ? { ...e, is_redoublant: isRedoublantVal } : e))
        );
    };

    const updateBatchArrieres = (eleveId: number, montant: number) => {
        setBatchEleves((prev) =>
            prev.map((e) => (e.eleve_id === eleveId ? { ...e, arrieres_anterieurs: Math.max(0, montant) } : e))
        );
    };

    /**
     * Soumission de la réinscription collective.
     *
     * @param {'stay' | 'index'} afterAction Destination après soumission
     */
    const submitBatchPromotion = (afterAction: 'stay' | 'index' = 'index') => {
        if (!batchTargetClasseId) {
            toast.error('Veuillez sélectionner la classe d\'accueil / destination.', {
                position: 'top-right',
            });
            return;
        }

        const toRegister = batchEleves.filter((e) => e.is_selected && !e.deja_inscrit);
        if (toRegister.length === 0) {
            toast.error('Veuillez cocher au moins un élève à réinscrire.', {
                position: 'top-right',
            });
            return;
        }

        setSubmitting(true);

        router.post(
            '/administration/eleves/reinscrire-lot',
            {
                annee_scolaire_id: Number(destAnneeId),
                target_classe_id: Number(batchTargetClasseId),
                statut: batchTargetStatut,
                _action: afterAction,
                eleves: toRegister.map((e) => ({
                    eleve_id: e.eleve_id,
                    is_redoublant: e.is_redoublant,
                    arrieres_anterieurs: e.arrieres_anterieurs,
                })),
            },
            {
                onSuccess: () => {
                    setSubmitting(false);
                    toast.success(
                        `${toRegister.length} élève(s) réinscrit(s) avec succès pour la nouvelle année scolaire !`,
                        {
                            position: 'top-right',
                            style: {
                                backgroundColor: '#10b981',
                                color: '#ffffff',
                                border: '1px solid #059669',
                            },
                        }
                    );
                    if (afterAction === 'stay') {
                        // Recharger la cohorte source
                        loadBatchEleves(batchSourceClasseId, srcAnneeId, destAnneeId);
                    }
                },
                onError: (err) => {
                    setSubmitting(false);
                    const firstErr = Object.values(err)[0] || 'Erreur lors de la réinscription en lot.';
                    toast.error(String(firstErr), {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    // ─────────────────────────────────────────────────────────────
    // 2. ÉTATS & LOGIQUE POUR LA RÉINSCRIPTION INDIVIDUELLE
    // ─────────────────────────────────────────────────────────────
    const [indivSearchQuery, setIndivSearchQuery] = useState('');
    const [indivSearching, setIndivSearching] = useState(false);
    const [indivSearchResults, setIndivSearchResults] = useState<AncienEleveResult[]>([]);
    const [indivSelectedEleve, setIndivSelectedEleve] = useState<AncienEleveResult | null>(null);

    const [indivTargetClasseId, setIndivTargetClasseId] = useState('');
    const [indivIsRedoublant, setIndivIsRedoublant] = useState<'0' | '1'>('0');
    const [indivStatut, setIndivStatut] = useState<'validee' | 'en_attente'>('validee');
    const [indivArrieres, setIndivArrieres] = useState('');
    const [indivObservations, setIndivObservations] = useState('');

    /**
     * Recherche d'anciens élèves par nom, prénom ou matricule.
     */
    const handleIndivSearch = async () => {
        if (!indivSearchQuery.trim()) return;
        setIndivSearching(true);
        try {
            const res = await fetch(
                `/administration/eleves/search-anciens?q=${encodeURIComponent(indivSearchQuery)}&annee_id=${destAnneeId}`
            );
            if (res.ok) {
                const data = await res.json();
                setIndivSearchResults(data);
            }
        } catch {
            toast.error('Erreur lors de la recherche des anciens élèves.', {
                position: 'top-right',
            });
        } finally {
            setIndivSearching(false);
        }
    };

    /**
     * Sélectionne un élève trouvé et pré-remplit les critères selon son historique scolaire.
     */
    const handleSelectIndivEleve = (el: AncienEleveResult) => {
        setIndivSelectedEleve(el);
        if (el.derniere_classe?.is_redoublant_preconise) {
            setIndivIsRedoublant('1');
        } else {
            setIndivIsRedoublant('0');
        }

        if (el.derniere_classe?.solde_impaye_precedent && el.derniere_classe.solde_impaye_precedent > 0) {
            setIndivArrieres(String(el.derniere_classe.solde_impaye_precedent));
        } else {
            setIndivArrieres('');
        }
    };

    /**
     * Soumission de la réinscription unitaire d'un élève.
     */
    const submitIndivReinscription = (action: 'save_and_new' | 'save_and_return') => {
        if (!indivSelectedEleve) {
            toast.error('Veuillez d\'abord rechercher et sélectionner un élève.', {
                position: 'top-right',
            });
            return;
        }

        if (!indivTargetClasseId) {
            toast.error('Veuillez sélectionner la classe d\'affectation.', {
                position: 'top-right',
            });
            return;
        }

        setSubmitting(true);

        router.post(
            '/administration/eleves/reinscrire',
            {
                eleve_id: indivSelectedEleve.id,
                classe_id: Number(indivTargetClasseId),
                annee_scolaire_id: Number(destAnneeId),
                is_redoublant: indivIsRedoublant === '1',
                statut: indivStatut,
                arrieres_anterieurs: Number(indivArrieres) || 0,
                observations: indivObservations,
                _action: action,
            },
            {
                onSuccess: () => {
                    setSubmitting(false);
                    toast.success(`L'élève ${indivSelectedEleve.nom_complet} a été réinscrit avec succès !`, {
                        position: 'top-right',
                        style: {
                            backgroundColor: '#10b981',
                            color: '#ffffff',
                            border: '1px solid #059669',
                        },
                    });

                    if (action === 'save_and_new') {
                        setIndivSelectedEleve(null);
                        setIndivSearchQuery('');
                        setIndivSearchResults([]);
                        setIndivArrieres('');
                        setIndivObservations('');
                    }
                },
                onError: (err) => {
                    setSubmitting(false);
                    const firstErr = Object.values(err)[0] || 'Erreur lors de la réinscription de l\'élève.';
                    toast.error(String(firstErr), {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    // Classe cible sélectionnée pour affichage d'informations de synthèse
    const currentTargetClasse = availableClasses.find((c) => String(c.id) === batchTargetClasseId);
    const currentSourceClasse = availableClasses.find((c) => String(c.id) === batchSourceClasseId);

    return (
        <>
            <Head title="Réinscription & Promotion des Anciens Élèves | SYGES" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* ───────────────────────────────────────────────────────────── */}
                {/* EN-TÊTE PRINCIPAL & NAVIGATION */}
                {/* ───────────────────────────────────────────────────────────── */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-border">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                            <Link
                                href="/administration/eleves"
                                className="inline-flex items-center gap-1 hover:text-foreground transition-colors font-medium cursor-pointer"
                            >
                                <ArrowLeft className="size-3.5" />
                                <span>Retour au registre des élèves</span>
                            </Link>
                        </div>
                        <h1 className="text-xl sm:text-2xl lg:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5 sm:gap-3">
                            <div className="p-2 rounded-xl sm:p-2.5 sm:rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shrink-0">
                                <UserCheck className="size-6 sm:size-7" />
                            </div>
                            <span>Réinscription & Promotion des Anciens Élèves</span>
                        </h1>
                        <p className="text-xs sm:text-sm text-muted-foreground max-w-3xl">
                            Espace dédié à la rentrée scolaire. Effectuez la promotion collective d'une cohorte entière
                            avec sélection personnalisée ou réinscrivez un élève individuel en conservant son historique académique et financier.
                        </p>
                    </div>

                    {/* Cadre de référence temporel : Année Destination & Année Source */}
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 p-3 rounded-2xl bg-muted/40 border border-border shadow-2xs w-full lg:w-auto">
                        <div className="space-y-1">
                            <Label className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1">
                                <Calendar className="size-3 text-emerald-600" />
                                Année Cible (Rentrée)
                            </Label>
                            <Select value={destAnneeId} onValueChange={setDestAnneeId}>
                                <SelectTrigger className="h-9 min-w-37.5 bg-background font-semibold text-xs border-emerald-300 dark:border-emerald-800">
                                    <SelectValue placeholder="Sélectionner..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {anneesScolaires.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)} className="text-xs">
                                            {a.nom} {a.is_current ? '(En cours)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="hidden sm:block text-muted-foreground/40 self-center mt-3">
                            <ArrowRight className="size-4 rotate-180" />
                        </div>

                        <div className="space-y-1">
                            <Label className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1">
                                <Clock className="size-3 text-teal-600" />
                                Année Source (Origine)
                            </Label>
                            <Select value={srcAnneeId} onValueChange={setSrcAnneeId}>
                                <SelectTrigger className="h-9 min-w-37.5 bg-background text-xs">
                                    <SelectValue placeholder="Sélectionner..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {anneesScolaires.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)} className="text-xs">
                                            {a.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                {/* ───────────────────────────────────────────────────────────── */}
                {/* SÉLECTEUR DE MODE (ONGLETS MAJEURS) */}
                {/* ───────────────────────────────────────────────────────────── */}
                <div className="flex border-b border-border gap-2 overflow-x-auto pb-1 -mb-1 w-full shrink-0">
                    <button
                        type="button"
                        onClick={() => setActiveTab('lot')}
                        className={`flex items-center gap-2.5 px-4 sm:px-5 py-3 text-xs sm:text-sm font-semibold border-b-2 transition-all cursor-pointer whitespace-nowrap shrink-0 ${
                            activeTab === 'lot'
                                ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-t-xl'
                                : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/30 rounded-t-xl'
                        }`}
                    >
                        <Users className="size-4 shrink-0" />
                        <span>1. Promotion Collective par Classe (En lot)</span>
                        <Badge variant="secondary" className="text-[10px] px-1.5 py-0 bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                            Recommandé Rentrée
                        </Badge>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('individuel')}
                        className={`flex items-center gap-2.5 px-4 sm:px-5 py-3 text-xs sm:text-sm font-semibold border-b-2 transition-all cursor-pointer whitespace-nowrap shrink-0 ${
                            activeTab === 'individuel'
                                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20 rounded-t-xl'
                                : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/30 rounded-t-xl'
                        }`}
                    >
                        <User className="size-4 shrink-0" />
                        <span>2. Réinscription Individuelle</span>
                    </button>
                </div>

                {/* ───────────────────────────────────────────────────────────── */}
                {/* MODE 1 : PROMOTION COLLECTIVE PAR CLASSE (EN LOT) */}
                {/* ───────────────────────────────────────────────────────────── */}
                {activeTab === 'lot' && (
                    <div className="space-y-6">
                        {/* Barre de sélection de la classe source et de la classe cible */}
                        <div className="rounded-2xl border-2 border-slate-300 dark:border-slate-700 bg-card p-6 shadow-xs space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                                {/* Classe d'origine (Année Source) */}
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                        <School className="size-4 text-indigo-600" />
                                        Classe d'Origine ({anneesScolaires.find((a) => String(a.id) === srcAnneeId)?.nom ?? 'Année Source'})
                                    </Label>
                                    <SearchableSelect
                                        options={classeOptions}
                                        value={batchSourceClasseId}
                                        onValueChange={(val) => setBatchSourceClasseId(val)}
                                        placeholder="Sélectionnez la classe source à promouvoir..."
                                        searchPlaceholder="Tapez le nom de la classe (ex: 6ème A)..."
                                    />
                                    <p className="text-[11px] text-muted-foreground">
                                        Sélectionnez la classe dont vous souhaitez promouvoir les élèves.
                                    </p>
                                </div>

                                {/* Classe de destination (Nouvelle Année Cible) */}
                                <div className="space-y-2">
                                    <Label className="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                                        <School className="size-4 text-emerald-600" />
                                        Classe d'Accueil Cible ({anneesScolaires.find((a) => String(a.id) === destAnneeId)?.nom ?? 'Année Cible'})
                                    </Label>
                                    <SearchableSelect
                                        options={classeOptions}
                                        value={batchTargetClasseId}
                                        onValueChange={(val) => setBatchTargetClasseId(val)}
                                        placeholder="Sélectionnez la classe d'accueil (ex: 5ème A)..."
                                        searchPlaceholder="Tapez le nom de la classe cible..."
                                    />
                                    <p className="text-[11px] text-muted-foreground">
                                        Les élèves cochés ci-dessous seront inscrits dans cette classe.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Si aucune classe source sélectionnée : État d'incitation */}
                        {!batchSourceClasseId ? (
                            <div className="rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-800 p-12 text-center bg-muted/20">
                                <div className="size-14 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 flex items-center justify-center mb-4">
                                    <Users className="size-7" />
                                </div>
                                <h3 className="text-base font-bold text-foreground">
                                    Sélectionnez une classe d'origine pour charger la cohorte
                                </h3>
                                <p className="text-xs text-muted-foreground max-w-md mx-auto mt-1">
                                    SYGES récupérera automatiquement la liste des élèves de cette classe,
                                    leurs avis de conseil de fin d'année et leurs soldes d'arriérés pour vous permettre
                                    de choisir exactement qui promouvoir.
                                </p>
                            </div>
                        ) : batchLoading ? (
                            <div className="rounded-2xl border border-border p-12 text-center bg-card">
                                <RefreshCw className="size-8 mx-auto animate-spin text-emerald-600 mb-3" />
                                <p className="text-sm font-semibold text-foreground">Chargement des élèves de la cohorte...</p>
                                <p className="text-xs text-muted-foreground mt-1">Récupération des avis de passage et des dettes scolaires antérieures</p>
                            </div>
                        ) : batchEleves.length === 0 ? (
                            <div className="rounded-2xl border border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20 p-8 text-center">
                                <AlertCircle className="size-8 mx-auto text-amber-600 mb-2" />
                                <h3 className="text-sm font-bold text-amber-900 dark:text-amber-200">
                                    Aucun élève trouvé dans cette classe pour l'année source sélectionnée
                                </h3>
                                <p className="text-xs text-amber-700 dark:text-amber-400 mt-1 max-w-md mx-auto">
                                    Vérifiez que la classe d'origine {currentSourceClasse?.nom} contenait bien des inscriptions
                                    au cours de l'année scolaire d'origine ou modifiez l'année source en haut à droite.
                                </p>
                            </div>
                        ) : (
                            /* TABLEAU DE SÉLECTION GROUPÉE AVEC ACTIONS */
                            <div className="space-y-4">
                                {/* Barre d'outils et de métriques */}
                                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 p-4 rounded-2xl bg-card border border-border shadow-xs">
                                    {/* Compteurs */}
                                    <div className="flex flex-wrap items-center gap-3">
                                        <div className="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                                            <span>Total cohorte :</span>
                                            <strong className="text-sm">{totalBatchCount}</strong>
                                        </div>

                                        <div className="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            <CheckSquare className="size-3.5 text-emerald-600" />
                                            <span>Sélectionnés :</span>
                                            <strong className="text-sm">{selectedBatchCount} sur {eligibleBatchCount} éligibles</strong>
                                        </div>

                                        {dejaInscritsCount > 0 && (
                                            <div className="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                                <span>Déjà réinscrits :</span>
                                                <strong>{dejaInscritsCount}</strong>
                                            </div>
                                        )}

                                        {totalArrieresSelected > 0 && (
                                            <div className="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                                <Coins className="size-3.5" />
                                                <span>Arriérés reportés :</span>
                                                <strong>{totalArrieresSelected.toLocaleString('fr-FR')} FCFA</strong>
                                            </div>
                                        )}
                                    </div>

                                    {/* Actions rapides de sélection */}
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleSelectAllEligible}
                                            className="h-8 text-xs cursor-pointer gap-1"
                                        >
                                            <CheckSquare className="size-3.5 text-emerald-600" />
                                            Tout cocher ({eligibleBatchCount})
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleSelectPromusOnly}
                                            className="h-8 text-xs cursor-pointer gap-1 text-emerald-700 border-emerald-300 dark:text-emerald-400 dark:border-emerald-800"
                                        >
                                            <Award className="size-3.5 text-emerald-600" />
                                            Promus uniquement
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleSelectRedoublantsOnly}
                                            className="h-8 text-xs cursor-pointer gap-1 text-amber-700 border-amber-300 dark:text-amber-400 dark:border-amber-800"
                                        >
                                            Redoublants uniquement
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={handleDeselectAll}
                                            className="h-8 text-xs cursor-pointer text-muted-foreground hover:text-foreground"
                                        >
                                            Tout décocher
                                        </Button>
                                    </div>
                                </div>

                                {/* Filtre textuel dans le tableau */}
                                <div className="relative max-w-sm">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Filtrer dans la cohorte par nom ou matricule..."
                                        value={batchSearchFilter}
                                        onChange={(e) => setBatchSearchFilter(e.target.value)}
                                        className="pl-9 h-9 text-xs bg-card"
                                    />
                                </div>

                                {/* TABLEAU DES ÉLÈVES DE LA CLASSE AVEC DÉBORDEMENT MAÎTRISÉ */}
                                <div className="rounded-2xl border border-border bg-card overflow-hidden shadow-xs min-w-0">
                                    <div className="overflow-x-auto min-w-0">
                                        <table className="w-full text-left border-collapse text-xs min-w-[750px]">
                                            <thead>
                                                <tr className="border-b border-border bg-muted/50 font-semibold text-muted-foreground uppercase text-[11px] tracking-wider">
                                                    <th className="p-3 w-12 text-center">
                                                        <Checkbox
                                                            checked={
                                                                eligibleBatchCount > 0 &&
                                                                selectedBatchCount === eligibleBatchCount
                                                            }
                                                            onCheckedChange={(checked) =>
                                                                checked ? handleSelectAllEligible() : handleDeselectAll()
                                                            }
                                                            aria-label="Sélectionner tous les élèves éligibles"
                                                        />
                                                    </th>
                                                    <th className="p-3">Élève & Identité</th>
                                                    <th className="p-3">Sexe & Âge</th>
                                                    <th className="p-3">Avis / Décision du Conseil</th>
                                                    <th className="p-3">Régime Prévu (Cible)</th>
                                                    <th className="p-3">Arriérés Antérieurs</th>
                                                    <th className="p-3 text-right">Statut Nouvelle Année</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {filteredBatchEleves.map((el) => {
                                                    const age = el.date_naissance
                                                        ? Math.floor(
                                                              (new Date().getTime() - new Date(el.date_naissance).getTime()) /
                                                                  (365.25 * 24 * 60 * 60 * 1000)
                                                          )
                                                        : null;

                                                    return (
                                                        <tr
                                                            key={el.eleve_id}
                                                            className={`transition-colors ${
                                                                el.deja_inscrit
                                                                    ? 'bg-muted/20 opacity-70'
                                                                    : el.is_selected
                                                                    ? 'bg-emerald-500/5 hover:bg-emerald-500/10'
                                                                    : 'hover:bg-muted/30'
                                                            }`}
                                                        >
                                                            {/* Checkbox individuel */}
                                                            <td className="p-3 text-center align-middle">
                                                                <Checkbox
                                                                    checked={el.is_selected && !el.deja_inscrit}
                                                                    disabled={el.deja_inscrit}
                                                                    onCheckedChange={(checked) =>
                                                                        toggleBatchItem(el.eleve_id, Boolean(checked))
                                                                    }
                                                                    aria-label={`Sélectionner ${el.nom_complet}`}
                                                                />
                                                            </td>

                                                            {/* Nom complet & Matricule */}
                                                            <td className="p-3 align-middle">
                                                                <div className="space-y-0.5">
                                                                    <span className="font-bold text-foreground block text-sm">
                                                                        {el.nom_complet}
                                                                    </span>
                                                                    <div className="flex items-center gap-1.5">
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="font-mono text-[10px] px-1.5 py-0 bg-muted/40"
                                                                        >
                                                                            {el.matricule}
                                                                        </Badge>
                                                                        {el.telephone_parent && (
                                                                            <span className="text-[11px] text-muted-foreground font-mono">
                                                                                Tél: {el.telephone_parent}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            {/* Sexe & Âge */}
                                                            <td className="p-3 align-middle">
                                                                <div className="flex items-center gap-1.5">
                                                                    <Badge
                                                                        variant="secondary"
                                                                        className={`text-[10px] px-1.5 py-0 font-semibold ${
                                                                            el.sexe === 'F'
                                                                                ? 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300'
                                                                                : 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300'
                                                                        }`}
                                                                    >
                                                                        {el.sexe === 'F' ? 'Fille' : 'Garçon'}
                                                                    </Badge>
                                                                    {age !== null && (
                                                                        <span className="text-muted-foreground">
                                                                            {age} ans
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                {el.date_naissance && (
                                                                    <span className="text-[10px] text-muted-foreground block mt-0.5">
                                                                        Né(e) le {formatDate(el.date_naissance)}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* Décision du conseil annuel */}
                                                            <td className="p-3 align-middle">
                                                                {el.decision_conseil ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className={`text-[11px] font-semibold px-2 py-0.5 ${
                                                                            el.statut_retenu === 'promu'
                                                                                ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                                                : el.statut_retenu === 'redouble'
                                                                                ? 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/50 dark:text-amber-300'
                                                                                : 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300'
                                                                        }`}
                                                                    >
                                                                        {el.decision_conseil}
                                                                    </Badge>
                                                                ) : (
                                                                    <span className="text-muted-foreground italic text-[11px]">
                                                                        Non délibéré
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* Régime Prévu (Promu ou Redoublant) basculable */}
                                                            <td className="p-3 align-middle">
                                                                {el.deja_inscrit ? (
                                                                    <span className="text-muted-foreground text-[11px]">
                                                                        Déjà statué
                                                                    </span>
                                                                ) : (
                                                                    <div className="flex items-center gap-1.5">
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant={!el.is_redoublant ? 'default' : 'outline'}
                                                                            onClick={() => toggleBatchRedoublant(el.eleve_id, false)}
                                                                            className={`h-7 px-2 text-[10px] font-semibold cursor-pointer ${
                                                                                !el.is_redoublant
                                                                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                                                                    : 'text-muted-foreground'
                                                                            }`}
                                                                        >
                                                                            Promu
                                                                        </Button>
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant={el.is_redoublant ? 'default' : 'outline'}
                                                                            onClick={() => toggleBatchRedoublant(el.eleve_id, true)}
                                                                            className={`h-7 px-2 text-[10px] font-semibold cursor-pointer ${
                                                                                el.is_redoublant
                                                                                    ? 'bg-amber-600 hover:bg-amber-700 text-white'
                                                                                    : 'text-muted-foreground'
                                                                            }`}
                                                                        >
                                                                            Redoublant
                                                                        </Button>
                                                                    </div>
                                                                )}
                                                            </td>

                                                            {/* Arriérés antérieurs reportés */}
                                                            <td className="p-3 align-middle">
                                                                {el.deja_inscrit ? (
                                                                    <span className="text-muted-foreground text-[11px]">
                                                                        Reporté
                                                                    </span>
                                                                ) : (
                                                                    <div className="flex items-center gap-1 max-w-35">
                                                                        <Input
                                                                            type="number"
                                                                            min="0"
                                                                            step="500"
                                                                            value={el.arrieres_anterieurs || ''}
                                                                            onChange={(e) =>
                                                                                updateBatchArrieres(
                                                                                    el.eleve_id,
                                                                                    Number(e.target.value) || 0
                                                                                )
                                                                            }
                                                                            className="h-7 text-xs font-mono"
                                                                            placeholder="0"
                                                                        />
                                                                        <span className="text-[10px] text-muted-foreground shrink-0">
                                                                            FCFA
                                                                        </span>
                                                                    </div>
                                                                )}
                                                            </td>

                                                            {/* Statut actuel pour la nouvelle année */}
                                                            <td className="p-3 text-right align-middle">
                                                                {el.deja_inscrit ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 font-bold"
                                                                    >
                                                                        Inscrit en {el.classe_actuelle_nom || 'Classe'}
                                                                    </Badge>
                                                                ) : el.is_selected ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 font-medium"
                                                                    >
                                                                        À inscrire
                                                                    </Badge>
                                                                ) : (
                                                                    <span className="text-muted-foreground text-[11px]">
                                                                        Non sélectionné
                                                                    </span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* BANDEAU DE VALIDATION EN BAS */}
                                <div className="rounded-2xl border-2 border-emerald-300 dark:border-emerald-800 bg-emerald-500/5 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <Sparkles className="size-5 text-emerald-600" />
                                            <h4 className="text-base font-bold text-foreground">
                                                Valider la promotion collective ({selectedBatchCount} élèves)
                                            </h4>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {selectedBatchCount > 0 ? (
                                                <span>
                                                    Prêt à inscrire <strong>{selectedBatchCount} élève(s)</strong> dans la classe{' '}
                                                    <strong className="text-emerald-700 dark:text-emerald-400">
                                                        {currentTargetClasse?.nom || '[Classe non choisie]'}
                                                    </strong>{' '}
                                                    pour l'année scolaire{' '}
                                                    <strong>
                                                        {anneesScolaires.find((a) => String(a.id) === destAnneeId)?.nom}
                                                    </strong>
                                                    . Leurs comptes financiers seront automatiquement créés avec report de leurs arriérés.
                                                </span>
                                            ) : (
                                                <span>Cochez les élèves que vous souhaitez inscrire dans le tableau ci-dessus.</span>
                                            )}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3 shrink-0">
                                        {/* Choix du statut d'inscription */}
                                        <div className="flex items-center gap-2">
                                            <Label className="text-xs font-semibold text-muted-foreground">
                                                Statut :
                                            </Label>
                                            <Select
                                                value={batchTargetStatut}
                                                onValueChange={(v: 'validee' | 'en_attente') => setBatchTargetStatut(v)}
                                            >
                                                <SelectTrigger className="h-9 w-32 bg-background text-xs font-semibold">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="validee" className="text-xs font-semibold text-emerald-600">
                                                        Validée
                                                    </SelectItem>
                                                    <SelectItem value="en_attente" className="text-xs font-semibold text-amber-600">
                                                        En attente
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {/* Valider et continuer sur la page pour une autre classe */}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={submitting || selectedBatchCount === 0 || !batchTargetClasseId}
                                            onClick={() => submitBatchPromotion('stay')}
                                            className="h-10 text-xs font-semibold cursor-pointer gap-1.5"
                                        >
                                            <PlusCircle className="size-4" />
                                            Valider et enchaîner
                                        </Button>

                                        {/* Valider et aller au registre */}
                                        <Button
                                            type="button"
                                            disabled={submitting || selectedBatchCount === 0 || !batchTargetClasseId}
                                            onClick={() => submitBatchPromotion('index')}
                                            className="h-10 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer gap-2 shadow-xs px-5"
                                        >
                                            <Check className="size-4" />
                                            {submitting ? 'Traitement...' : 'Valider & Voir le Registre'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* ───────────────────────────────────────────────────────────── */}
                {/* MODE 2 : RÉINSCRIPTION INDIVIDUELLE */}
                {/* ───────────────────────────────────────────────────────────── */}
                {activeTab === 'individuel' && (
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        {/* COLONNE GAUCHE : RECHERCHE ET LISTE D'ÉLÈVES (5 COLONNES) */}
                        <div className="lg:col-span-5 space-y-4">
                            <div className="rounded-2xl border-2 border-slate-300 dark:border-slate-700 bg-card p-5 shadow-xs space-y-4">
                                <div>
                                    <h3 className="text-sm font-bold text-foreground flex items-center gap-2">
                                        <Search className="size-4 text-indigo-600" />
                                        Rechercher un ancien élève
                                    </h3>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        Par matricule, nom, prénom ou numéro de téléphone du tuteur.
                                    </p>
                                </div>

                                <div className="flex gap-2">
                                    <Input
                                        placeholder="Ex: TCHOUPO, MAT-2024..."
                                        value={indivSearchQuery}
                                        onChange={(e) => setIndivSearchQuery(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleIndivSearch()}
                                        className="h-10 text-xs"
                                    />
                                    <Button
                                        type="button"
                                        onClick={handleIndivSearch}
                                        disabled={indivSearching || !indivSearchQuery.trim()}
                                        className="h-10 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer px-4 gap-1.5"
                                    >
                                        <Search className="size-3.5" />
                                        {indivSearching ? '...' : 'Chercher'}
                                    </Button>
                                </div>

                                {/* Liste des résultats de recherche */}
                                <div className="space-y-2 max-h-115 overflow-y-auto pr-1">
                                    {indivSearching ? (
                                        <div className="p-8 text-center text-xs text-muted-foreground">
                                            <RefreshCw className="size-5 animate-spin mx-auto text-indigo-600 mb-2" />
                                            Recherche dans la base des anciens élèves...
                                        </div>
                                    ) : indivSearchResults.length > 0 ? (
                                        indivSearchResults.map((el) => {
                                            const isSelected = indivSelectedEleve?.id === el.id;
                                            return (
                                                <div
                                                    key={el.id}
                                                    onClick={() => handleSelectIndivEleve(el)}
                                                    className={`p-3.5 rounded-xl border text-xs cursor-pointer transition-all ${
                                                        isSelected
                                                            ? 'border-indigo-600 bg-indigo-50/60 dark:bg-indigo-950/40 shadow-xs'
                                                            : 'border-border bg-card hover:bg-muted/40'
                                                    }`}
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-bold text-foreground text-sm">
                                                            {el.nom_complet}
                                                        </span>
                                                        <Badge
                                                            variant="outline"
                                                            className="font-mono text-[10px] px-1.5 py-0"
                                                        >
                                                            {el.matricule}
                                                        </Badge>
                                                    </div>

                                                    <div className="flex items-center gap-2 mt-1.5 text-muted-foreground text-[11px]">
                                                        <span>{el.sexe === 'F' ? 'Fille' : 'Garçon'}</span>
                                                        {el.derniere_classe && (
                                                            <>
                                                                <span>•</span>
                                                                <span>
                                                                    Dernière classe :{' '}
                                                                    <strong>{el.derniere_classe.classe_nom}</strong> (
                                                                    {el.derniere_classe.annee_nom})
                                                                </span>
                                                            </>
                                                        )}
                                                    </div>

                                                    {/* Avis et arriérés éventuels */}
                                                    <div className="flex flex-wrap items-center gap-1.5 mt-2">
                                                        {el.derniere_classe?.decision_conseil && (
                                                            <Badge
                                                                variant="outline"
                                                                className={`text-[10px] px-1.5 py-0 font-medium ${
                                                                    el.derniere_classe.statut_retenu === 'promu'
                                                                        ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                                                        : 'bg-amber-50 text-amber-800 border-amber-300'
                                                                }`}
                                                            >
                                                                {el.derniere_classe.decision_conseil}
                                                            </Badge>
                                                        )}

                                                        {Boolean(el.derniere_classe?.solde_impaye_precedent && el.derniere_classe.solde_impaye_precedent > 0) && (
                                                            <Badge
                                                                variant="outline"
                                                                className="text-[10px] px-1.5 py-0 font-semibold bg-rose-50 text-rose-700 border-rose-300"
                                                            >
                                                                Arriéré : {Number(el.derniere_classe?.solde_impaye_precedent).toLocaleString('fr-FR')} F
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : indivSearchQuery.trim() ? (
                                        <div className="p-8 text-center text-xs text-muted-foreground border border-dashed rounded-xl">
                                            Aucun ancien élève non encore inscrit ne correspond à votre recherche.
                                        </div>
                                    ) : (
                                        <div className="p-8 text-center text-xs text-muted-foreground border border-dashed rounded-xl">
                                            Tapez un nom ou un matricule pour démarrer la recherche.
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* COLONNE DROITE : FICHE DÉTAILLÉE & FORMULAIRE D'AFFECTATION (7 COLONNES) */}
                        <div className="lg:col-span-7 space-y-6">
                            {!indivSelectedEleve ? (
                                <div className="rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-800 p-12 text-center bg-card">
                                    <div className="size-14 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 flex items-center justify-center mb-4">
                                        <UserCheck className="size-7" />
                                    </div>
                                    <h3 className="text-base font-bold text-foreground">
                                        Sélectionnez un élève dans les résultats de recherche
                                    </h3>
                                    <p className="text-xs text-muted-foreground max-w-md mx-auto mt-1">
                                        Sa fiche d'historique s'affichera ici avec l'avis du conseil de fin d'année
                                        et le formulaire de réaffectation pour l'année scolaire active.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-6">
                                    {/* CARTE D'IDENTITÉ DE L'ÉLÈVE SÉLECTIONNÉ */}
                                    <div className="rounded-2xl border border-indigo-200 dark:border-indigo-900 bg-indigo-50/40 dark:bg-indigo-950/20 p-5 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="size-11 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-sm shadow-xs">
                                                    {indivSelectedEleve.nom.charAt(0)}
                                                    {indivSelectedEleve.prenom?.charAt(0) || ''}
                                                </div>
                                                <div>
                                                    <h3 className="text-base font-bold text-foreground">
                                                        {indivSelectedEleve.nom_complet}
                                                    </h3>
                                                    <div className="flex items-center gap-2 mt-0.5">
                                                        <Badge variant="outline" className="font-mono text-[10px] px-1.5 py-0 bg-background">
                                                            Mat: {indivSelectedEleve.matricule}
                                                        </Badge>
                                                        {indivSelectedEleve.telephone_parent && (
                                                            <span className="text-xs text-muted-foreground font-mono">
                                                                Tél: {indivSelectedEleve.telephone_parent}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setIndivSelectedEleve(null)}
                                                className="h-8 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </div>

                                        {/* Résumé de scolarité passée */}
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-indigo-200/60 dark:border-indigo-900/60 text-xs">
                                            <div>
                                                <span className="text-[10px] uppercase font-bold text-muted-foreground block">
                                                    Dernière Classe
                                                </span>
                                                <span className="font-semibold text-foreground">
                                                    {indivSelectedEleve.derniere_classe?.classe_nom || 'Non renseignée'}
                                                </span>
                                            </div>

                                            <div>
                                                <span className="text-[10px] uppercase font-bold text-muted-foreground block">
                                                    Avis de Fin d'Année
                                                </span>
                                                <span className="font-semibold text-foreground">
                                                    {indivSelectedEleve.derniere_classe?.decision_conseil || 'Aucun avis saisi'}
                                                </span>
                                            </div>

                                            <div>
                                                <span className="text-[10px] uppercase font-bold text-muted-foreground block">
                                                    Dette Antérieure
                                                </span>
                                                <span className={`font-semibold ${Number(indivSelectedEleve.derniere_classe?.solde_impaye_precedent) > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                                                    {Number(indivSelectedEleve.derniere_classe?.solde_impaye_precedent || 0).toLocaleString('fr-FR')} FCFA
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* FORMULAIRE D'AFFECTATION POUR LA NOUVELLE ANNÉE */}
                                    <div className="rounded-2xl border-2 border-slate-300 dark:border-slate-700 bg-card p-6 shadow-xs space-y-6">
                                        <h4 className="text-sm font-bold text-foreground flex items-center gap-2 border-b border-border pb-3">
                                            <CheckCircle2 className="size-4 text-emerald-600" />
                                            Affectation dans la nouvelle classe ({anneesScolaires.find((a) => String(a.id) === destAnneeId)?.nom ?? 'Nouvelle Année'})
                                        </h4>

                                        <div className="space-y-4">
                                            {/* Classe cible */}
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-foreground">
                                                    Classe d'affectation cible <span className="text-destructive">*</span>
                                                </Label>
                                                <SearchableSelect
                                                    options={classeOptions}
                                                    value={indivTargetClasseId}
                                                    onValueChange={setIndivTargetClasseId}
                                                    placeholder="Choisir la classe d'accueil..."
                                                    searchPlaceholder="Tapez le nom de la classe..."
                                                />
                                            </div>

                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                {/* Régime : Nouveau / Promu ou Redoublant */}
                                                <div className="space-y-2">
                                                    <Label className="text-xs font-bold uppercase tracking-wider text-foreground">
                                                        Régime scolaire
                                                    </Label>
                                                    <Select
                                                        value={indivIsRedoublant}
                                                        onValueChange={(v: '0' | '1') => setIndivIsRedoublant(v)}
                                                    >
                                                        <SelectTrigger className="h-10 text-xs font-semibold bg-background">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="0" className="text-xs font-semibold text-emerald-700">
                                                                Promu / Nouveau dans la classe
                                                            </SelectItem>
                                                            <SelectItem value="1" className="text-xs font-semibold text-amber-700">
                                                                Redoublant
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                {/* Statut de l'inscription */}
                                                <div className="space-y-2">
                                                    <Label className="text-xs font-bold uppercase tracking-wider text-foreground">
                                                        Statut de l'inscription
                                                    </Label>
                                                    <Select
                                                        value={indivStatut}
                                                        onValueChange={(v: 'validee' | 'en_attente') => setIndivStatut(v)}
                                                    >
                                                        <SelectTrigger className="h-10 text-xs font-semibold bg-background">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="validee" className="text-xs font-semibold text-emerald-700">
                                                                Validée immédiatement
                                                            </SelectItem>
                                                            <SelectItem value="en_attente" className="text-xs font-semibold text-amber-700">
                                                                En attente
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            {/* Report des arriérés antérieurs */}
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-foreground flex items-center justify-between">
                                                    <span>Report des arriérés scolaires antérieurs (FCFA)</span>
                                                    <span className="text-[10px] text-muted-foreground font-normal">
                                                        Sera imputé sur son nouveau compte financier
                                                    </span>
                                                </Label>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="500"
                                                    placeholder="0"
                                                    value={indivArrieres}
                                                    onChange={(e) => setIndivArrieres(e.target.value)}
                                                    className="h-10 text-xs font-mono"
                                                />
                                            </div>

                                            {/* Observations */}
                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-foreground">
                                                    Observations pédagogiques / administratives
                                                </Label>
                                                <Input
                                                    placeholder="Motif de réinscription, accord de dérogation..."
                                                    value={indivObservations}
                                                    onChange={(e) => setIndivObservations(e.target.value)}
                                                    className="h-10 text-xs"
                                                />
                                            </div>
                                        </div>

                                        {/* Actions de soumission */}
                                        <div className="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-border">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={submitting}
                                                onClick={() => submitIndivReinscription('save_and_new')}
                                                className="h-10 text-xs font-semibold cursor-pointer gap-1.5"
                                            >
                                                <PlusCircle className="size-4 text-indigo-600" />
                                                + Réinscrire & Suivant
                                            </Button>

                                            <Button
                                                type="button"
                                                disabled={submitting}
                                                onClick={() => submitIndivReinscription('save_and_return')}
                                                className="h-10 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer px-5 gap-2 shadow-xs"
                                            >
                                                <Check className="size-4" />
                                                {submitting ? 'Enregistrement...' : 'Valider la Réinscription'}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Configuration du fil d'Ariane pour la page dédiée de Réinscription.
 */
ReinscriptionPage.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Élèves & Inscriptions',
            href: '/administration/eleves',
        },
        {
            title: 'Réinscription & Promotion',
            href: '/administration/eleves/reinscription',
        },
    ],
};

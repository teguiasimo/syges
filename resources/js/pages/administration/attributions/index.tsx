import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    AlertTriangle,
    Award,
    BookOpen,
    Check,
    CheckCircle2,
    Clock,
    Edit2,
    Eye,
    FileSpreadsheet,
    Filter,
    Layers,
    Plus,
    RefreshCw,
    School,
    SlidersHorizontal,
    Trash2,
    TrendingDown,
    TrendingUp,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/contexts/language-context';
import type {
    Attribution,
    AttributionsPageProps,
    ClasseWithAttributions,
    EnseignantWithWorkload,
} from '@/types/attribution';
import { ClasseMatrixModal } from './modals/classe-matrix-modal';
import { CreateAttributionModal } from './modals/create-attribution-modal';
import { ProfesseurPrincipalModal } from './modals/professeur-principal-modal';

/**
 * Page principale du Module 9 : Attribution des Classes & Matières (Répartition pédagogique).
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 9.1, 9.2 et 2.7.5).
 *
 * Utilise la bibliothèque TanStack Table (@tanstack/react-table) pour l'affichage
 * universel de la grille des cours et du suivi de la charge horaire des enseignants.
 */
export default function AttributionsIndex({
    anneesScolaires,
    selectedAnneeId,
    etablissements,
    classes,
    enseignants,
    matieres,
    matiereNiveaux,
    stats,
    can,
    filters,
}: AttributionsPageProps) {
    const { t } = useTranslation();

    // Onglet d'affichage : 'classes' | 'enseignants'
    const [activeTab, setActiveTab] = useState<'classes' | 'enseignants'>('classes');

    // États des modales
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [targetClasseForCreate, setTargetClasseForCreate] = useState<number | null>(null);

    const [isMatrixOpen, setIsMatrixOpen] = useState(false);
    const [selectedClasseForMatrix, setSelectedClasseForMatrix] = useState<ClasseWithAttributions | null>(null);

    const [isPPOpen, setIsPPOpen] = useState(false);
    const [selectedClasseForPP, setSelectedClasseForPP] = useState<ClasseWithAttributions | null>(null);

    // Attribution à révoquer
    const [attributionToDelete, setAttributionToDelete] = useState<Attribution | null>(null);

    // Filtres locaux
    const [filterEtablissement, setFilterEtablissement] = useState<string>(String(filters?.etablissement_id || 'all'));
    const [filterCharge, setFilterCharge] = useState<string>('all');

    /**
     * Changement d'établissement scolaire via rechargement Inertia.
     */
    const handleEtablissementChange = (newEtab: string) => {
        setFilterEtablissement(newEtab);
        router.get(
            '/administration/attributions',
            {
                annee_scolaire_id: selectedAnneeId,
                etablissement_id: newEtab !== 'all' ? newEtab : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    /**
     * Changement d'année scolaire via rechargement Inertia.
     */
    const handleAnneeChange = (newAnneeId: string) => {
        router.get(
            '/administration/attributions',
            {
                annee_scolaire_id: newAnneeId,
                etablissement_id: filterEtablissement !== 'all' ? filterEtablissement : undefined,
            },
            { preserveState: true }
        );
    };

    /**
     * Filtrage des classes.
     */
    const filteredClasses = useMemo(() => {
        return classes.filter((c) => {
            if (filterEtablissement !== 'all') {
                const etabId = c.etablissement_id ?? c.niveau?.cycle?.section?.etablissement?.id;
                if (String(etabId) !== String(filterEtablissement)) return false;
            }
            return true;
        });
    }, [classes, filterEtablissement]);

    /**
     * Filtrage des enseignants selon la charge de travail et l'établissement.
     */
    const filteredEnseignants = useMemo(() => {
        return enseignants.filter((e) => {
            if (filterEtablissement !== 'all') {
                const etabId = e.etablissement?.id;
                // Si l'enseignant est rattaché à un campus différent, on filtre
                if (etabId && String(etabId) !== String(filterEtablissement)) {
                    return false;
                }
            }
            if (filterCharge !== 'all' && e.statut_charge !== filterCharge) {
                return false;
            }
            return true;
        });
    }, [enseignants, filterEtablissement, filterCharge]);

    /**
     * Révocation confirmée d'une attribution.
     */
    const handleConfirmDeleteAttribution = () => {
        if (!attributionToDelete) return;

        router.delete(`/administration/attributions/${attributionToDelete.id}`, {
            onSuccess: () => {
                toast.success('Attribution révoquée avec succès !', {
                    description: 'L\'enseignant a été dissocié de ce cours.',
                });
                setAttributionToDelete(null);
            },
            onError: () => {
                toast.error('Erreur lors de la révocation de l\'attribution');
            },
        });
    };

    // ==========================================
    // COLONNES TANSTACK TABLE - VUE PAR CLASSE
    // ==========================================
    const classeColumns = useMemo<ColumnDef<ClasseWithAttributions>[]>(() => {
        return [
            {
                accessorKey: 'nom',
                header: () => <span className="font-semibold text-xs">Classe & Niveau</span>,
                cell: ({ row }) => {
                    const c = row.original;
                    return (
                        <div>
                            <span className="font-bold text-sm text-foreground block">
                                {c.nom}
                            </span>
                            <div className="flex items-center gap-2 mt-0.5 text-xs text-muted-foreground">
                                <span className="font-mono bg-muted px-1.5 py-0.5 rounded border text-[11px]">
                                    {c.code}
                                </span>
                                <span>•</span>
                                <span>{c.niveau?.nom}</span>
                                {c.serie && (
                                    <>
                                        <span>•</span>
                                        <span className="font-medium text-indigo-600 dark:text-indigo-400">
                                            Série {c.serie}
                                        </span>
                                    </>
                                )}
                            </div>
                        </div>
                    );
                },
            },
            {
                id: 'professeur_principal',
                header: () => <span className="font-semibold text-xs">Professeur Principal</span>,
                cell: ({ row }) => {
                    const c = row.original;
                    if (c.professeur_principal) {
                        return (
                            <button
                                type="button"
                                onClick={() => {
                                    if (can.manage) {
                                        setSelectedClasseForPP(c);
                                        setIsPPOpen(true);
                                    }
                                }}
                                className={`flex items-center gap-2 text-left group p-1.5 rounded-lg transition-colors ${
                                    can.manage ? 'hover:bg-amber-50 dark:hover:bg-amber-950/40 cursor-pointer' : ''
                                }`}
                                title={can.manage ? 'Cliquer pour modifier le professeur principal de cette classe' : undefined}
                            >
                                <Award className="size-4 text-amber-600 shrink-0 group-hover:scale-110 transition-transform" />
                                <div className="text-xs">
                                    <span className="font-semibold text-foreground block group-hover:text-amber-700 dark:group-hover:text-amber-400">
                                        {c.professeur_principal.nom} {c.professeur_principal.prenom}
                                    </span>
                                    <span className="text-[11px] text-muted-foreground font-mono">
                                        {c.professeur_principal.matricule}
                                    </span>
                                </div>
                            </button>
                        );
                    }
                    return (
                        <button
                            type="button"
                            onClick={() => {
                                setSelectedClasseForPP(c);
                                setIsPPOpen(true);
                            }}
                            className="text-xs text-amber-600 hover:underline inline-flex items-center gap-1 font-medium cursor-pointer"
                        >
                            <Plus className="size-3" /> Désigner un PP
                        </button>
                    );
                },
            },
            {
                id: 'couverture_cours',
                header: () => <span className="font-semibold text-xs">Couverture des Cours</span>,
                cell: ({ row }) => {
                    const c = row.original;
                    const niveauId = c.niveau_id;
                    const serie = c.serie;

                    // Matières requises d'après la grille des coefficients
                    const requiredMatieres = matiereNiveaux.filter((mn) => {
                        return (
                            mn.niveau_id === niveauId &&
                            (mn.serie === 'Toutes' || empty(mn.serie) || mn.serie === serie)
                        );
                    });

                    const totalRequired = requiredMatieres.length;
                    const assignedSubjectIds = c.attributions.map((a) => a.matiere_id);
                    const assignedCount = requiredMatieres.filter((mn) =>
                        assignedSubjectIds.includes(mn.matiere_id)
                    ).length;

                    const percent = totalRequired > 0 ? Math.round((assignedCount / totalRequired) * 100) : 100;

                    return (
                        <div className="space-y-1.5 w-40">
                            <div className="flex items-center justify-between text-xs">
                                <span className="font-medium text-foreground">
                                    {assignedCount} / {totalRequired} matières
                                </span>
                                <span className={`font-bold text-xs ${percent === 100 ? 'text-emerald-600' : 'text-amber-600'}`}>
                                    {percent}%
                                </span>
                            </div>
                            <div className="w-full h-1.5 bg-muted rounded-full overflow-hidden">
                                <div
                                    className={`h-full transition-all ${percent === 100 ? 'bg-emerald-500' : 'bg-amber-500'}`}
                                    style={{ width: `${percent}%` }}
                                />
                            </div>
                        </div>
                    );
                },
            },
            {
                id: 'volume_total',
                header: () => <span className="font-semibold text-xs">Total Heures</span>,
                cell: ({ row }) => {
                    const totalHours = row.original.attributions.reduce(
                        (sum, a) => sum + Number(a.volume_horaire_reel),
                        0
                    );
                    return (
                        <Badge variant="outline" className="text-xs font-semibold">
                            <Clock className="size-3 mr-1 text-muted-foreground" />
                            {totalHours.toFixed(1)} h / sem.
                        </Badge>
                    );
                },
            },
            {
                id: 'actions',
                header: () => <div className="text-right pr-2 font-semibold text-xs">Actions</div>,
                cell: ({ row }) => {
                    const c = row.original;
                    return (
                        <div className="flex items-center justify-end gap-1.5">
                            {/* Bouton vers la page dédiée de la grille de classe */}
                            <Link
                                href={`/administration/attributions/grille?classe_id=${c.id}&annee_scolaire_id=${selectedAnneeId}`}
                                className="inline-flex items-center justify-center h-8 px-2.5 rounded-md border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-xs font-semibold cursor-pointer transition-colors shadow-2xs"
                            >
                                <Layers className="size-3.5 mr-1 text-indigo-600" />
                                Grille Classe
                            </Link>

                            {/* Bouton Affecter un cours rapide */}
                            {can.manage && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => {
                                        setTargetClasseForCreate(c.id);
                                        setIsCreateOpen(true);
                                    }}
                                    title="Ajouter un cours dans cette classe"
                                    className="size-8 rounded-lg text-muted-foreground hover:text-foreground cursor-pointer"
                                >
                                    <Plus className="size-4" />
                                </Button>
                            )}
                        </div>
                    );
                },
            },
        ];
    }, [matiereNiveaux, can]);

    // ==========================================
    // COLONNES TANSTACK TABLE - VUE PAR ENSEIGNANT
    // ==========================================
    const enseignantColumns = useMemo<ColumnDef<EnseignantWithWorkload>[]>(() => {
        return [
            {
                accessorKey: 'nom_complet',
                header: () => <span className="font-semibold text-xs">Enseignant</span>,
                cell: ({ row }) => {
                    const e = row.original;
                    return (
                        <div className="flex items-center gap-2.5">
                            <div className="size-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-bold text-xs flex items-center justify-center border border-indigo-200 dark:border-indigo-800">
                                {e.nom.slice(0, 1)}
                                {e.prenom ? e.prenom.slice(0, 1) : ''}
                            </div>
                            <div>
                                <span className="font-semibold text-xs text-foreground block">
                                    {e.nom} {e.prenom}
                                </span>
                                <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span className="font-mono bg-muted px-1 rounded border">
                                        {e.matricule}
                                    </span>
                                    {e.specialite && (
                                        <>
                                            <span>•</span>
                                            <span>{e.specialite}</span>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'departement.nom',
                header: () => <span className="font-semibold text-xs">Département & Campus</span>,
                cell: ({ row }) => {
                    const e = row.original;
                    return (
                        <div className="text-xs space-y-0.5">
                            <span className="font-medium text-foreground block truncate max-w-37.5">
                                {e.departement ? e.departement.nom : <em className="text-muted-foreground">Aucun département</em>}
                            </span>
                            <span className="text-[11px] text-muted-foreground block truncate max-w-37.5">
                                {e.etablissement ? e.etablissement.nom : '-'}
                            </span>
                        </div>
                    );
                },
            },
            {
                id: 'comparatif_charge',
                header: () => <span className="font-semibold text-xs">Charge Horaire (H. Réelles / Quota)</span>,
                cell: ({ row }) => {
                    const e = row.original;
                    return (
                        <div className="space-y-1">
                            <div className="flex items-baseline gap-1 text-xs">
                                <strong className="text-sm font-bold text-foreground">
                                    {e.heures_attribuees.toFixed(1)} h
                                </strong>
                                <span className="text-muted-foreground text-xs">
                                    / {e.volume_horaire_hebdo} h légal
                                </span>
                            </div>

                            {/* Badge d'écart */}
                            {e.ecart !== 0 ? (
                                <span className={`text-[11px] font-semibold inline-flex items-center gap-0.5 ${
                                    e.ecart > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400'
                                }`}>
                                    {e.ecart > 0 ? (
                                        <>
                                            <TrendingUp className="size-3" /> +{e.ecart.toFixed(1)} h (Surcharge)
                                        </>
                                    ) : (
                                        <>
                                            <TrendingDown className="size-3" /> {e.ecart.toFixed(1)} h (Sous-charge)
                                        </>
                                    )}
                                </span>
                            ) : (
                                <span className="text-[11px] font-semibold text-emerald-600 inline-flex items-center gap-0.5">
                                    <CheckCircle2 className="size-3" /> Équilibré (100%)
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'statut_charge',
                header: () => <span className="font-semibold text-xs">Statut</span>,
                cell: ({ row }) => {
                    const status = row.original.statut_charge;
                    if (status === 'surcharge') {
                        return (
                            <Badge className="bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800 text-[11px] font-medium">
                                Surcharge (Vacations)
                            </Badge>
                        );
                    }
                    if (status === 'sous_charge') {
                        return (
                            <Badge className="bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-800 text-[11px] font-medium">
                                Sous-charge
                            </Badge>
                        );
                    }
                    return (
                        <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800 text-[11px] font-medium">
                            Équilibré
                        </Badge>
                    );
                },
            },
            {
                id: 'cours_dispenses',
                header: () => <span className="font-semibold text-xs">Classes & Disciplines</span>,
                cell: ({ row }) => {
                    const attributions = row.original.attributions;
                    if (attributions.length === 0) {
                        return <span className="text-xs text-muted-foreground/60 italic">Aucun cours affecté</span>;
                    }
                    return (
                        <div className="flex flex-wrap gap-1 max-w-70">
                            {attributions.map((a) => (
                                <div
                                    key={a.id}
                                    className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-muted text-[11px] font-medium text-foreground border"
                                >
                                    <span className="font-bold text-indigo-600 dark:text-indigo-400">{a.classe?.code || 'CL'}</span>
                                    <span>:</span>
                                    <span className="truncate max-w-17.5">{a.matiere?.code || 'DISC'}</span>
                                    <span className="text-muted-foreground">({Number(a.volume_horaire_reel)}h)</span>
                                    {can.delete && (
                                        <button
                                            type="button"
                                            onClick={() => setAttributionToDelete(a)}
                                            title="Révoquer ce cours"
                                            className="text-muted-foreground hover:text-rose-600 ml-0.5 p-0.5 rounded cursor-pointer"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    );
                },
            },
        ];
    }, [can]);

    return (
        <>
            <Head title="Attribution des Classes & Matières - Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full">
                {/* EN-TÊTE PRINCIPAL */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-900/50">
                                <Layers className="size-7" />
                            </div>
                            <div>
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                                    Attribution des Classes & Matières
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground mt-0.5">
                                    Répartition pédagogique des cours, gestion des charges horaires et professeurs principaux.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* SÉLECTEUR D'ANNÉE SCOLAIRE ET BOUTON D'AFFECTATION */}
                    <div className="flex items-center gap-2.5 flex-wrap">
                        <select
                            value={selectedAnneeId}
                            onChange={(e) => handleAnneeChange(e.target.value)}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-ring cursor-pointer"
                        >
                            {anneesScolaires.map((a) => (
                                <option key={a.id} value={a.id}>
                                    Année {a.nom} {a.is_current ? '★' : ''}
                                </option>
                            ))}
                        </select>

                        {/* Bouton vers la page dédiée de la grille de classe */}
                        <Link
                            href={`/administration/attributions/grille?annee_scolaire_id=${selectedAnneeId}`}
                            className="inline-flex items-center justify-center h-9 px-3 rounded-md border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-950/40 hover:bg-indigo-100/60 font-semibold text-xs transition-colors shadow-2xs"
                        >
                            <FileSpreadsheet className="size-4 mr-1.5 text-indigo-600" />
                            Grille par classe
                        </Link>

                        {can.manage && (
                            <Button
                                type="button"
                                onClick={() => {
                                    setTargetClasseForCreate(null);
                                    setIsCreateOpen(true);
                                }}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer font-semibold shadow-sm text-xs sm:text-sm"
                            >
                                <Plus className="size-4 mr-1.5" />
                                Affecter un cours
                            </Button>
                        )}
                    </div>
                </div>

                {/* KPI SYNTHÈSE STATISTIQUE */}
                <div className="grid grid-cols-2 sm:grid-cols-6 gap-3">
                    {/* Taux de couverture */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Couverture Cours
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className={`text-2xl font-bold ${stats.tauxCouverture === 100 ? 'text-emerald-600' : 'text-amber-600'}`}>
                                {stats.tauxCouverture}%
                            </span>
                            <CheckCircle2 className="size-4 text-emerald-600" />
                        </div>
                    </div>

                    {/* Total attributions */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Cours Attribués
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-foreground">{stats.totalAttributions}</span>
                            <Layers className="size-4 text-muted-foreground" />
                        </div>
                    </div>

                    {/* Enseignants mobilisés */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Mobilisés
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-indigo-600">
                                {stats.enseignantsMobilises} <span className="text-xs text-muted-foreground">/ {stats.totalEnseignants}</span>
                            </span>
                            <Users className="size-4 text-indigo-600" />
                        </div>
                    </div>

                    {/* Heures réelles totales */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Heures Totales
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-foreground">{Number(stats.heuresTotalesReelles).toFixed(0)} h</span>
                            <Clock className="size-4 text-muted-foreground" />
                        </div>
                    </div>

                    {/* Surcharge / Heures sup */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            En Surcharge
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-amber-600">{stats.enseignantsSurcharge}</span>
                            <TrendingUp className="size-4 text-amber-600" />
                        </div>
                    </div>

                    {/* Sous-charge */}
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            En Sous-charge
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-rose-600">{stats.enseignantsSousCharge}</span>
                            <TrendingDown className="size-4 text-rose-600" />
                        </div>
                    </div>
                </div>

                {/* ONGLETS DE NAVIGATION */}
                <div className="flex items-center gap-2 border-b">
                    <button
                        type="button"
                        onClick={() => setActiveTab('classes')}
                        className={`pb-3 px-4 font-semibold text-sm transition-colors border-b-2 cursor-pointer flex items-center gap-2 ${
                            activeTab === 'classes'
                                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <School className="size-4" />
                        <span>Vue par Classe ({classes.length})</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('enseignants')}
                        className={`pb-3 px-4 font-semibold text-sm transition-colors border-b-2 cursor-pointer flex items-center gap-2 ${
                            activeTab === 'enseignants'
                                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Users className="size-4" />
                        <span>Vue par Enseignant (Charges horaires) ({enseignants.length})</span>
                    </button>
                </div>

                {/* CONTENU ONGLET 1 : VUE PAR CLASSE */}
                {activeTab === 'classes' && (
                    <div className="space-y-4">
                        <DataTable
                            columns={classeColumns}
                            data={filteredClasses}
                            searchPlaceholder="Rechercher une classe, un niveau..."
                            initialPageSize={20}
                            filterSlot={
                                <div className="flex items-center gap-2">
                                    <Select
                                        value={filterEtablissement}
                                        onValueChange={handleEtablissementChange}
                                    >
                                        <SelectTrigger className="h-9 w-48 text-xs bg-card">
                                            <SelectValue placeholder="Établissement" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les campus</SelectItem>
                                            {etablissements.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)}>
                                                    {e.nom} ({e.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            }
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 2 : VUE PAR ENSEIGNANT */}
                {activeTab === 'enseignants' && (
                    <div className="space-y-4">
                        <DataTable
                            columns={enseignantColumns}
                            data={filteredEnseignants}
                            searchPlaceholder="Rechercher un enseignant, matricule..."
                            initialPageSize={20}
                            filterSlot={
                                <div className="flex items-center gap-2">
                                    <Select
                                        value={filterEtablissement}
                                        onValueChange={handleEtablissementChange}
                                    >
                                        <SelectTrigger className="h-9 w-48 text-xs bg-card">
                                            <SelectValue placeholder="Établissement" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les campus</SelectItem>
                                            {etablissements.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)}>
                                                    {e.nom} ({e.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <Select
                                        value={filterCharge}
                                        onValueChange={setFilterCharge}
                                    >
                                        <SelectTrigger className="h-9 w-40 text-xs bg-card">
                                            <SelectValue placeholder="Charge horaire" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Toutes charges</SelectItem>
                                            <SelectItem value="surcharge">En surcharge</SelectItem>
                                            <SelectItem value="equilibre">Équilibré</SelectItem>
                                            <SelectItem value="sous_charge">En sous-charge</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            }
                        />
                    </div>
                )}
            </div>

            {/* MODALE D'AFFECTATION INDIVIDUELLE */}
            <CreateAttributionModal
                isOpen={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                anneeScolaireId={selectedAnneeId}
                classes={classes}
                enseignants={enseignants}
                matieres={matieres}
                matiereNiveaux={matiereNiveaux}
                initialClasseId={targetClasseForCreate}
            />

            {/* MODALE MATRICE DE CLASSE */}
            <ClasseMatrixModal
                classe={selectedClasseForMatrix}
                isOpen={isMatrixOpen}
                onClose={() => {
                    setIsMatrixOpen(false);
                    setSelectedClasseForMatrix(null);
                }}
                anneeScolaireId={selectedAnneeId}
                enseignants={enseignants}
                matieres={matieres}
                matiereNiveaux={matiereNiveaux}
            />

            {/* MODALE PROFESSEUR PRINCIPAL */}
            <ProfesseurPrincipalModal
                classe={selectedClasseForPP}
                isOpen={isPPOpen}
                onClose={() => {
                    setIsPPOpen(false);
                    setSelectedClasseForPP(null);
                }}
                enseignants={enseignants}
            />

            {/* CONFIRMATION DE RÉVOCATION D'ATTRIBUTION */}
            <Dialog
                open={!!attributionToDelete}
                onOpenChange={(open) => !open && setAttributionToDelete(null)}
            >
                <DialogContent className="max-w-md p-6">
                    <DialogHeader>
                        <div className="size-10 rounded-full bg-rose-100 dark:bg-rose-950/50 text-rose-600 flex items-center justify-center mb-2">
                            <AlertTriangle className="size-5" />
                        </div>
                        <DialogTitle className="text-lg font-bold text-foreground">
                            Révoquer cette attribution de cours ?
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground mt-1">
                            Voulez-vous retirer{' '}
                            <strong className="text-foreground">
                                {attributionToDelete?.personnel?.nom} {attributionToDelete?.personnel?.prenom}
                            </strong>{' '}
                            de la matière{' '}
                            <strong className="text-foreground">
                                {attributionToDelete?.matiere?.nom}
                            </strong>{' '}
                            dans la classe{' '}
                            <strong className="text-foreground">
                                {attributionToDelete?.classe?.nom}
                            </strong>{' '}
                            ?
                            <br />
                            Cette action révoquera l'attribution. Les notes déjà saisies resteront enregistrées.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2 mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAttributionToDelete(null)}
                            className="cursor-pointer"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleConfirmDeleteAttribution}
                            className="cursor-pointer font-semibold"
                        >
                            Confirmer la révocation
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Fil d'Ariane (Breadcrumbs) pour la page d'attribution des cours.
 */
AttributionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Attributions de Cours',
            href: '/administration/attributions',
        },
    ],
};

function empty(val: any): boolean {
    return val === null || val === undefined || val === '';
}

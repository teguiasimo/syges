import { Head, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    CalendarRange,
    Plus,
    Edit2,
    Trash2,
    CheckCircle2,
    Clock,
    Lock,
    Unlock,
    Calendar,
    Settings2,
    Calculator,
    AlertCircle,
    Check,
    ArrowUpDown,
    Shield,
    Building2,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { usePermission } from '@/hooks/use-permission';
import { useConfirmDelete } from '@/components/confirm-delete-dialog';
import { formatDate, formatDateForInput } from '@/lib/utils';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * Interface pour un Établissement (Campus).
 */
interface EtablissementItem {
    id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
}

/**
 * Interface pour une Évaluation / Séquence.
 */
interface EvaluationItem {
    id: number;
    periode_id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    ordre: number;
    ponderation: number;
    date_debut?: string | null;
    date_fin?: string | null;
    date_limite_saisie?: string | null;
    is_locked: boolean;
    statut: 'a_venir' | 'ouverte' | 'verrouillee' | 'cloturee';
}

/**
 * Interface pour un Trimestre / Période.
 */
interface PeriodeItem {
    id: number;
    etablissement_id?: number | null;
    annee_scolaire_id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    ordre: number;
    date_debut?: string | null;
    date_fin?: string | null;
    statut: 'a_venir' | 'en_cours' | 'cloturee';
    evaluations: EvaluationItem[];
}

/**
 * Interface pour une Année Scolaire.
 */
interface AnneeScolaireItem {
    id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    date_debut: string;
    date_fin: string;
    statut: 'en_preparation' | 'en_cours' | 'cloturee';
    mode_calcul_moyenne: 'arithmetique_simple' | 'ponderee_pourcentage';
    is_current: boolean;
}

/**
 * Props attendues par la page Index.
 */
interface PeriodeIndexProps {
    etablissements: EtablissementItem[];
    selectedEtablissement?: EtablissementItem | null;
    annees: AnneeScolaireItem[];
    selectedAnnee?: AnneeScolaireItem | null;
    periodes: PeriodeItem[];
    can: {
        periodes_manage: boolean;
        periodes_lock: boolean;
    };
    flash?: {
        success?: string;
        error?: string;
    };
}

/**
 * Page de gestion des Périodes, Trimestres, Séquences et Paramètres d'Évaluation.
 * Implémente le Module 3 du cahier des charges Gesco avec découpage par établissement (Option A).
 */
export default function PeriodesIndex({
    etablissements = [],
    selectedEtablissement,
    annees,
    selectedAnnee,
    periodes,
    can: propCan,
    flash,
}: PeriodeIndexProps) {
    const { can: checkPerm } = usePermission();
    const can = {
        periodes_manage: propCan?.periodes_manage ?? checkPerm('periodes.manage'),
        periodes_lock: propCan?.periodes_lock ?? checkPerm('periodes.lock'),
    };

    // Hook universel de confirmation de suppression
    const { confirmDelete, ConfirmDialog } = useConfirmDelete();

    // Gestion des toasts de notification en haut à droite avec style vert pour le succès
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, {
                style: {
                    backgroundColor: '#10b981',
                    color: '#ffffff',
                    border: '1px solid #059669',
                },
            });
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    // États des modales
    const [isAnneeModalOpen, setIsAnneeModalOpen] = useState(false);
    const [editingAnnee, setEditingAnnee] = useState<AnneeScolaireItem | null>(null);

    const [isTrimestreModalOpen, setIsTrimestreModalOpen] = useState(false);
    const [editingTrimestre, setEditingTrimestre] = useState<PeriodeItem | null>(null);

    const [isEvaluationModalOpen, setIsEvaluationModalOpen] = useState(false);
    const [editingEvaluation, setEditingEvaluation] = useState<EvaluationItem | null>(null);
    const [targetPeriodeId, setTargetPeriodeId] = useState<number | null>(null);

    // Formulaires Inertia
    const anneeForm = useForm({
        code: '',
        nom: '',
        nom_en: '',
        date_debut: '',
        date_fin: '',
        statut: 'en_preparation',
        mode_calcul_moyenne: 'arithmetique_simple',
        is_current: false,
        auto_generate_structure: true,
    });

    const trimestreForm = useForm({
        etablissement_id: selectedEtablissement?.id || 0,
        annee_scolaire_id: selectedAnnee?.id || 0,
        code: '',
        nom: '',
        nom_en: '',
        ordre: 1,
        date_debut: '',
        date_fin: '',
        statut: 'a_venir',
    });

    const evaluationForm = useForm({
        periode_id: 0,
        code: '',
        nom: '',
        nom_en: '',
        ordre: 1,
        ponderation: 50,
        date_debut: '',
        date_fin: '',
        date_limite_saisie: '',
        statut: 'a_venir',
    });

    /**
     * Changement de l'année scolaire affichée (préserve l'établissement actif).
     */
    const handleSelectAnnee = (anneeId: number) => {
        router.get(
            '/administration/periodes',
            {
                annee_id: anneeId,
                etablissement_id: selectedEtablissement?.id,
            },
            { preserveScroll: true, preserveState: true }
        );
    };

    /**
     * Changement de l'établissement actif (préserve l'année scolaire affichée).
     */
    const handleSelectEtablissement = (etabId: number) => {
        router.get(
            '/administration/periodes',
            {
                etablissement_id: etabId,
                annee_id: selectedAnnee?.id,
            },
            { preserveScroll: true, preserveState: true }
        );
    };

    /**
     * Définir l'année comme active pour l'ensemble du système.
     */
    const handleSetCurrentAnnee = (annee: AnneeScolaireItem) => {
        router.patch(`/administration/periodes/annees/${annee.id}/set-current`, {}, {
            preserveScroll: true,
        });
    };

    /**
     * Bascule du verrouillage de la saisie des notes pour une évaluation de l'établissement.
     */
    const handleToggleLock = (evaluation: EvaluationItem) => {
        if (!can.periodes_lock) return;
        router.patch(`/administration/periodes/evaluations/${evaluation.id}/toggle-lock`, {}, {
            preserveScroll: true,
        });
    };

    /**
     * Génération de la structure officielle camerounaise (3 trimestres, 6 séquences) pour l'établissement sélectionné.
     */
    const handleGenerateStandardStructure = () => {
        if (!selectedAnnee || !selectedEtablissement || !can.periodes_manage) return;
        confirmDelete({
            title: 'Génération de la structure officielle',
            variant: 'primary',
            confirmLabel: 'Générer la structure',
            promptText: `Voulez-vous générer automatiquement la structure officielle (3 Trimestres et 6 Séquences) pour « ${selectedEtablissement.nom} » (${selectedAnnee.nom}) ?`,
            description: 'Cette opération va initialiser les 3 trimestres et 6 séquences standards conformes au calendrier officiel camerounais pour cet établissement.',
            onConfirm: () => {
                router.post(`/administration/periodes/annees/${selectedAnnee.id}/generate-standard`, {
                    etablissement_id: selectedEtablissement.id,
                }, {
                    preserveScroll: true,
                });
            },
        });
    };

    // Soumission du formulaire Année
    const submitAnnee = (e: React.FormEvent) => {
        e.preventDefault();
        const autoNom = anneeForm.data.nom || `Année Scolaire ${anneeForm.data.code}`;

        anneeForm.transform((data) => ({
            ...data,
            nom: autoNom,
            nom_en: anneeForm.data.nom_en || null,
        }));

        if (editingAnnee) {
            anneeForm.put(`/administration/periodes/annees/${editingAnnee.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAnneeModalOpen(false);
                    setEditingAnnee(null);
                },
            });
        } else {
            anneeForm.post('/administration/periodes/annees', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAnneeModalOpen(false);
                    anneeForm.reset();
                },
            });
        }
    };

    // Soumission du formulaire Trimestre
    const submitTrimestre = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingTrimestre) {
            trimestreForm.put(`/administration/periodes/trimestres/${editingTrimestre.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsTrimestreModalOpen(false);
                    setEditingTrimestre(null);
                    trimestreForm.reset();
                },
            });
        } else {
            trimestreForm.transform((data) => ({
                ...data,
                etablissement_id: selectedEtablissement?.id || 0,
                annee_scolaire_id: selectedAnnee?.id || 0,
            }));
            trimestreForm.post('/administration/periodes/trimestres', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsTrimestreModalOpen(false);
                    trimestreForm.reset();
                },
            });
        }
    };

    // Soumission du formulaire Évaluation
    const submitEvaluation = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingEvaluation) {
            evaluationForm.put(`/administration/periodes/evaluations/${editingEvaluation.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEvaluationModalOpen(false);
                    setEditingEvaluation(null);
                },
            });
        } else {
            evaluationForm.post('/administration/periodes/evaluations', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEvaluationModalOpen(false);
                    evaluationForm.reset();
                },
            });
        }
    };

    /**
     * Définition des colonnes TanStack Table pour les Évaluations / Séquences d'un trimestre.
     */
    const makeEvaluationColumns = (periode: PeriodeItem): ColumnDef<EvaluationItem>[] => [
        {
            accessorKey: 'code',
            header: () => (
                <span className="font-bold text-[11px] uppercase tracking-wider text-slate-700 dark:text-slate-200">
                    Code
                </span>
            ),
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/50 px-2 py-0.5 rounded-md border border-sky-200/60 dark:border-sky-800/60">
                    {row.original.code}
                </span>
            ),
        },
        {
            accessorKey: 'nom',
            header: () => (
                <span className="font-bold text-[11px] uppercase tracking-wider text-slate-700 dark:text-slate-200">
                    Libellé
                </span>
            ),
            cell: ({ row }) => (
                <div className="font-semibold text-sm text-foreground">{row.original.nom}</div>
            ),
        },
        ...(selectedAnnee?.mode_calcul_moyenne === 'ponderee_pourcentage'
            ? [
                  {
                      accessorKey: 'ponderation',
                      header: () => (
                          <span className="font-bold text-[11px] uppercase tracking-wider text-slate-700 dark:text-slate-200">
                              Poids (%)
                          </span>
                      ),
                      cell: ({ row }: { row: { original: EvaluationItem } }) => (
                          <div className="flex items-center gap-1.5 font-medium">
                              <span className="text-xs px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-foreground font-semibold">
                                  {row.original.ponderation}%
                              </span>
                          </div>
                      ),
                  } as ColumnDef<EvaluationItem>,
              ]
            : []),
        {
            accessorKey: 'date_debut',
            header: () => (
                <span className="font-bold text-[11px] uppercase tracking-wider text-slate-700 dark:text-slate-200">
                    Période & Dates
                </span>
            ),
            cell: ({ row }) => (
                <div className="text-xs text-muted-foreground">
                    {row.original.date_debut && row.original.date_fin ? (
                        <span>Du {formatDate(row.original.date_debut)} au {formatDate(row.original.date_fin)}</span>
                    ) : (
                        <span className="italic">Dates non définies</span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'date_limite_saisie',
            header: () => (
                <span className="font-bold text-[11px] uppercase tracking-wider text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                    <Clock className="size-3 text-slate-500 dark:text-slate-400" />
                    Date Butoir Saisie
                </span>
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-1 text-xs">
                    <Clock className="size-3 text-muted-foreground" />
                    {row.original.date_limite_saisie ? (
                        <span className="font-medium text-foreground">{formatDate(row.original.date_limite_saisie)}</span>
                    ) : (
                        <span className="text-muted-foreground italic">Illimitée</span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'is_locked',
            header: () => (
                <span className="font-bold text-[11px] uppercase tracking-wider text-slate-700 dark:text-slate-200">
                    Verrou Saisie
                </span>
            ),
            cell: ({ row }) => {
                const isLocked = row.original.is_locked;
                return (
                    <div className="flex items-center gap-2">
                        {isLocked ? (
                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300">
                                <Lock className="size-3 text-rose-600" />
                                Verrouillée
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                                <Unlock className="size-3 text-emerald-600" />
                                Ouverte
                            </span>
                        )}

                        {can.periodes_lock && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => handleToggleLock(row.original)}
                                title={isLocked ? 'Déverrouiller la saisie' : 'Verrouiller la saisie'}
                                className="h-7 px-2 text-[11px] cursor-pointer hover:bg-muted"
                            >
                                {isLocked ? (
                                    <span className="text-emerald-600 dark:text-emerald-400">Déverrouiller</span>
                                ) : (
                                    <span className="text-rose-600 dark:text-rose-400">Verrouiller</span>
                                )}
                            </Button>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: () => (
                <div className="text-right pr-2 font-bold text-[11px] uppercase tracking-wider text-slate-700 dark:text-slate-200">
                    Actions
                </div>
            ),
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        {can.periodes_manage && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier la séquence"
                                    onClick={() => {
                                        setEditingEvaluation(item);
                                        evaluationForm.setData({
                                            periode_id: item.periode_id,
                                            code: item.code,
                                            nom: item.nom,
                                            nom_en: item.nom_en || '',
                                            ordre: item.ordre,
                                            ponderation: item.ponderation,
                                            date_debut: formatDateForInput(item.date_debut),
                                            date_fin: formatDateForInput(item.date_fin),
                                            date_limite_saisie: formatDateForInput(item.date_limite_saisie),
                                            statut: item.statut,
                                        });
                                        setIsEvaluationModalOpen(true);
                                    }}
                                    className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-400 cursor-pointer shadow-xs"
                                >
                                    <Edit2 className="size-3.5" />
                                    <span className="sr-only">Modifier</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Supprimer la séquence"
                                    onClick={() => {
                                        confirmDelete({
                                            title: "Supprimer la séquence",
                                            itemType: "la séquence",
                                            itemName: item.nom,
                                            description: "Toutes les notes et évaluations saisies pour cette séquence seront définitivement supprimées.",
                                            onConfirm: () => router.delete(`/administration/periodes/evaluations/${item.id}`),
                                        });
                                    }}
                                    className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                                >
                                    <Trash2 className="size-3.5" />
                                    <span className="sr-only">Supprimer</span>
                                </Button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title="Années & Périodes d'Évaluation - Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full">
                {/* 1. EN-TÊTE DU MODULE */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <CalendarRange className="size-7 text-sky-600 dark:text-sky-400" />
                            Périodes & Paramètres d'Évaluation
                        </h1>
                        <p className="text-muted-foreground text-xs sm:text-sm mt-1">
                            Cadre temporel de l'établissement, trimestres, séquences d'évaluation et règles de calcul des moyennes.
                        </p>
                    </div>

                    {can.periodes_manage && (
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => {
                                setEditingAnnee(null);
                                anneeForm.reset();
                                anneeForm.setData({
                                    code: '',
                                    nom: '',
                                    nom_en: '',
                                    date_debut: '',
                                    date_fin: '',
                                    statut: 'en_preparation',
                                    mode_calcul_moyenne: 'arithmetique_simple',
                                    is_current: false,
                                    auto_generate_structure: true,
                                });
                                setIsAnneeModalOpen(true);
                            }}
                            className="gap-2 cursor-pointer bg-primary text-primary-foreground hover:bg-primary/90"
                        >
                            <Plus className="size-4" />
                            <span>Nouvelle Année Scolaire</span>
                        </Button>
                    )}
                </div>

                {/* 2. SÉLECTEUR DE CAMPUS / ÉTABLISSEMENT */}
                {etablissements && etablissements.length > 0 && (
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        {/* En-tête contrasté et plus foncé */}
                        <div className="bg-gradient-to-r from-slate-200/95 via-emerald-100/60 to-slate-200/80 dark:from-slate-800 dark:via-emerald-950/30 dark:to-slate-800/90 px-6 py-3.5 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2.5">
                                <div className="flex size-7 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs">
                                    <Building2 className="size-4" />
                                </div>
                                <span className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                    Campus & Établissement
                                </span>
                            </div>
                            {etablissements.length > 1 && (
                                <span className="text-xs text-slate-600 dark:text-slate-400 font-medium">
                                    Sélectionnez un campus pour configurer ses trimestres et verrouillages d'évaluation.
                                </span>
                            )}
                        </div>

                        {/* Corps du Campus */}
                        <div className="p-4 sm:p-5 bg-card">
                            {etablissements.length > 1 ? (
                                <div className="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
                                    {etablissements.map((etab) => {
                                        const isSelected = selectedEtablissement?.id === etab.id;
                                        return (
                                            <button
                                                key={etab.id}
                                                type="button"
                                                onClick={() => handleSelectEtablissement(etab.id)}
                                                className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all cursor-pointer border ${
                                                    isSelected
                                                        ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-900'
                                                }`}
                                            >
                                                <span className="font-mono text-[11px] opacity-85">{etab.code}</span>
                                                <span>{etab.nom}</span>
                                                {isSelected && <Check className="size-3 stroke-3 ml-0.5" />}
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Badge variant="secondary" className="font-mono font-bold text-emerald-800 bg-emerald-100 border border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300">
                                        {selectedEtablissement?.code}
                                    </Badge>
                                    <span className="font-semibold text-foreground text-sm">{selectedEtablissement?.nom}</span>
                                    <span className="text-slate-500">(Campus unique de l'institution)</span>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* 3. SÉLECTEUR ET STATUT DE L'ANNÉE SCOLAIRE */}
                <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                    {/* En-tête contrasté et foncé contenant la ligne des codes d'années et les actions */}
                    <div className="bg-gradient-to-r from-slate-200/95 via-sky-100/70 to-slate-200/80 dark:from-slate-800 dark:via-sky-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-600 to-indigo-600 text-white font-bold shadow-xs shrink-0">
                                <Calendar className="size-4" />
                            </div>

                            <div className="flex items-center gap-2 overflow-x-auto pb-0.5 max-w-full">
                                {annees.map((annee) => {
                                    const isSelected = selectedAnnee?.id === annee.id;
                                    return (
                                        <button
                                            key={annee.id}
                                            type="button"
                                            onClick={() => handleSelectAnnee(annee.id)}
                                            className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all cursor-pointer border ${
                                                isSelected
                                                    ? 'bg-sky-600 text-white border-sky-700 shadow-xs'
                                                    : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-900'
                                            }`}
                                        >
                                            <span>{annee.code}</span>
                                            {annee.is_current && (
                                                <span className="relative flex size-2 shrink-0" title="Année active par défaut">
                                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                                    <span className="relative inline-flex size-2 rounded-full bg-emerald-400 ring-1 ring-white/70" />
                                                </span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {selectedAnnee && (
                            <div className="flex items-center gap-2">
                                {!selectedAnnee.is_current && can.periodes_manage && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleSetCurrentAnnee(selectedAnnee)}
                                        className="h-8 text-xs cursor-pointer gap-1.5 bg-white dark:bg-slate-800 text-sky-700 dark:text-sky-300 border-slate-300 dark:border-slate-600 hover:bg-sky-50 shadow-xs font-semibold"
                                    >
                                        <Check className="size-3.5" />
                                        <span>Définir comme année active</span>
                                    </Button>
                                )}

                                {can.periodes_manage && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setEditingAnnee(selectedAnnee);
                                            anneeForm.setData({
                                                code: selectedAnnee.code,
                                                nom: selectedAnnee.nom,
                                                nom_en: selectedAnnee.nom_en || '',
                                                date_debut: formatDateForInput(selectedAnnee.date_debut),
                                                date_fin: formatDateForInput(selectedAnnee.date_fin),
                                                statut: selectedAnnee.statut,
                                                mode_calcul_moyenne: selectedAnnee.mode_calcul_moyenne,
                                                is_current: selectedAnnee.is_current,
                                                auto_generate_structure: false,
                                            });
                                            setIsAnneeModalOpen(true);
                                        }}
                                        className="h-8 text-xs cursor-pointer gap-1.5 bg-white dark:bg-slate-800 text-blue-700 dark:text-blue-300 border-slate-300 dark:border-slate-600 hover:bg-blue-50 shadow-xs font-semibold"
                                    >
                                        <Edit2 className="size-3.5 text-blue-600" />
                                        <span>Modifier l'année</span>
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Corps de la carte Année */}
                    {selectedAnnee && (
                        <div className="p-5 bg-card flex flex-wrap items-center justify-between gap-4 text-xs">
                            <div className="flex flex-wrap items-center gap-3 text-muted-foreground">
                                <span className="font-bold text-foreground text-sm">{selectedAnnee.nom}</span>
                                <span>•</span>
                                <span className="flex items-center gap-1.5 font-medium">
                                    <Calendar className="size-3.5 text-sky-600 dark:text-sky-400" />
                                    Du {formatDate(selectedAnnee.date_debut)} au {formatDate(selectedAnnee.date_fin)}
                                </span>
                            </div>

                            <div className="flex items-center gap-2">
                                {selectedAnnee.is_current ? (
                                    <Badge className="bg-emerald-600 hover:bg-emerald-600 text-white shadow-xs gap-1.5 px-3 py-1 text-xs font-semibold ring-2 ring-emerald-500/20">
                                        <span className="relative flex size-2 shrink-0">
                                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-200 opacity-80" />
                                            <span className="relative inline-flex size-2 rounded-full bg-white" />
                                        </span>
                                        <span>Année Active</span>
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary" className="border border-slate-300 dark:border-slate-700 text-muted-foreground px-3 py-1 text-xs">
                                        Année Non Active
                                    </Badge>
                                )}
                                <Badge variant="outline" className="capitalize border-slate-300 dark:border-slate-700 px-2.5 py-1 text-xs">
                                    {selectedAnnee.statut.replace('_', ' ')}
                                </Badge>
                            </div>
                        </div>
                    )}
                </div>

                {selectedAnnee && (
                    <>
                        {/* 4. DÉCOUPAGE PÉRIODIQUE (TRIMESTRES & SÉQUENCES DE L'ÉTABLISSEMENT) */}
                        <div className="space-y-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
                                        <Calendar className="size-5 text-sky-600" />
                                        Découpage des Trimestres & Séquences
                                        {selectedEtablissement && (
                                            <Badge variant="outline" className="ml-1 text-xs border-emerald-300 text-emerald-700 dark:border-emerald-700 dark:text-emerald-300 bg-emerald-50/50">
                                                {selectedEtablissement.nom}
                                            </Badge>
                                        )}
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Structure chronologique des périodes d'examen et contrôle de la saisie des notes pour cet établissement.
                                    </p>
                                </div>

                                <div className="flex items-center gap-2.5">
                                    {periodes.length === 0 && can.periodes_manage && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleGenerateStandardStructure}
                                            className="gap-2 cursor-pointer text-xs h-9 text-emerald-600 border-emerald-200 hover:bg-emerald-50 dark:border-emerald-800"
                                        >
                                            <CalendarRange className="size-3.5" />
                                            <span>Initialiser le standard officiel (3 Trimestres / 6 Séquences)</span>
                                        </Button>
                                    )}

                                    {can.periodes_manage && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() => {
                                                setEditingTrimestre(null);
                                                trimestreForm.reset();
                                                trimestreForm.setData({
                                                    etablissement_id: selectedEtablissement?.id || 0,
                                                    annee_scolaire_id: selectedAnnee?.id || 0,
                                                    code: '',
                                                    nom: '',
                                                    nom_en: '',
                                                    ordre: periodes.length + 1,
                                                    date_debut: '',
                                                    date_fin: '',
                                                    statut: 'a_venir',
                                                });
                                                setIsTrimestreModalOpen(true);
                                            }}
                                            className="gap-2 cursor-pointer text-xs h-9 bg-primary text-primary-foreground hover:bg-primary/90"
                                        >
                                            <Plus className="size-4" />
                                            <span>Ajouter un Trimestre</span>
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {/* Liste des Trimestres */}
                            {periodes.length === 0 ? (
                                <div className="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-border bg-card/40">
                                    <CalendarRange className="size-12 text-muted-foreground/40 mb-3" />
                                    <h4 className="text-base font-semibold text-foreground">Aucun trimestre configuré</h4>
                                    <p className="text-xs text-muted-foreground mt-1 max-w-md">
                                        L'établissement <strong>{selectedEtablissement?.nom}</strong> ne comporte encore aucun découpage pour l'année scolaire <strong>{selectedAnnee.nom}</strong>.
                                    </p>
                                    {can.periodes_manage && (
                                        <Button
                                            type="button"
                                            onClick={handleGenerateStandardStructure}
                                            className="mt-4 gap-2 cursor-pointer text-xs bg-emerald-600 text-white hover:bg-emerald-700"
                                        >
                                            <CalendarRange className="size-3.5" />
                                            <span>Initialiser le standard officiel pour cet établissement</span>
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-6">
                                    {periodes.map((periode, index) => {
                                        const totalPonderation = periode.evaluations.reduce((acc, curr) => acc + curr.ponderation, 0);
                                        const is100Percent = Math.abs(totalPonderation - 100) < 0.01;
                                        const evalColumns = makeEvaluationColumns(periode);

                                        return (
                                            <div
                                                key={periode.id}
                                                className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600"
                                            >
                                                {/* =========================================================================
                                                 * EN-TÊTE DU TRIMESTRE (Bandeau contrasté, plus foncé et bien délimité)
                                                 * ========================================================================= */}
                                                <div className="bg-gradient-to-r from-slate-200/95 via-sky-100/70 to-slate-200/80 dark:from-slate-800 dark:via-sky-950/40 dark:to-slate-800/90 px-6 py-4.5 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                                    <div className="flex items-center gap-3.5">
                                                        {/* Badge visuel T1, T2, T3 avec dégradé distinctif */}
                                                        <div className="size-10 rounded-xl bg-gradient-to-br from-sky-600 to-indigo-600 text-white font-bold text-sm flex items-center justify-center shadow-xs shrink-0 tracking-tight">
                                                            {`T${periode.ordre || (index + 1)}`}
                                                        </div>

                                                        <div className="space-y-1">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="font-mono text-xs font-bold text-sky-800 dark:text-sky-300 bg-white dark:bg-slate-900 px-2.5 py-0.5 rounded-md border border-slate-300 dark:border-slate-700 shadow-xs">
                                                                    {periode.code}
                                                                </span>
                                                                <h4 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                                                    {periode.nom}
                                                                </h4>
                                                            </div>

                                                            <div className="flex flex-wrap items-center gap-2.5 text-xs text-muted-foreground">
                                                                <span className="inline-flex items-center gap-1 font-medium">
                                                                    <Calendar className="size-3.5 text-sky-600 dark:text-sky-400" />
                                                                    {periode.date_debut && periode.date_fin ? (
                                                                        <span>Du {formatDate(periode.date_debut)} au {formatDate(periode.date_fin)}</span>
                                                                    ) : (
                                                                        <span className="italic text-muted-foreground">Dates non renseignées</span>
                                                                    )}
                                                                </span>
                                                                <span>•</span>
                                                                {/* Badge de statut expressif */}
                                                                {periode.statut === 'en_cours' ? (
                                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100/90 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-300/70 dark:border-emerald-800">
                                                                        <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                                                        En cours
                                                                    </span>
                                                                ) : periode.statut === 'cloturee' ? (
                                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200/80 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-300 dark:border-slate-700">
                                                                        <Lock className="size-3 text-slate-500" />
                                                                        Clôturé
                                                                    </span>
                                                                ) : (
                                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100/80 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-300/60 dark:border-sky-800">
                                                                        <Clock className="size-3 text-sky-600 dark:text-sky-400" />
                                                                        À venir
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Indicateur de poids et actions du trimestre */}
                                                    <div className="flex flex-wrap items-center gap-2.5">
                                                        {selectedAnnee.mode_calcul_moyenne === 'ponderee_pourcentage' && (
                                                            <div className="flex items-center gap-1.5">
                                                                <span className="text-xs font-medium text-muted-foreground hidden sm:inline">Poids total :</span>
                                                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-bold shadow-2xs ${
                                                                    is100Percent
                                                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-300/80 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                                                                        : 'bg-amber-50 text-amber-700 border-amber-300/80 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800'
                                                                }`}>
                                                                    {is100Percent ? (
                                                                        <CheckCircle2 className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                                                    ) : (
                                                                        <AlertCircle className="size-3.5 text-amber-600 dark:text-amber-400" />
                                                                    )}
                                                                    <span>{totalPonderation}% {is100Percent ? '✓' : '(Incomplet)'}</span>
                                                                </span>
                                                            </div>
                                                        )}

                                                        {can.periodes_manage && (
                                                            <div className="flex items-center gap-1.5">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    onClick={() => {
                                                                        setTargetPeriodeId(periode.id);
                                                                        setEditingEvaluation(null);
                                                                        evaluationForm.reset();
                                                                        evaluationForm.setData({
                                                                            periode_id: periode.id,
                                                                            code: '',
                                                                            nom: '',
                                                                            nom_en: '',
                                                                            ordre: periode.evaluations.length + 1,
                                                                            ponderation: 50,
                                                                            date_debut: '',
                                                                            date_fin: '',
                                                                            date_limite_saisie: '',
                                                                            statut: 'a_venir',
                                                                        });
                                                                        setIsEvaluationModalOpen(true);
                                                                    }}
                                                                    className="h-8 text-xs gap-1.5 cursor-pointer bg-sky-600 text-white hover:bg-sky-700 shadow-xs font-semibold"
                                                                >
                                                                    <Plus className="size-3.5" />
                                                                    <span>Ajouter une Évaluation</span>
                                                                </Button>

                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    title="Modifier le trimestre"
                                                                    onClick={() => {
                                                                        setEditingTrimestre(periode);
                                                                        trimestreForm.setData({
                                                                            annee_scolaire_id: periode.annee_scolaire_id,
                                                                            code: periode.code,
                                                                            nom: periode.nom,
                                                                            nom_en: periode.nom_en || '',
                                                                            ordre: periode.ordre,
                                                                            date_debut: formatDateForInput(periode.date_debut),
                                                                            date_fin: formatDateForInput(periode.date_fin),
                                                                            statut: periode.statut,
                                                                        });
                                                                        setIsTrimestreModalOpen(true);
                                                                    }}
                                                                    className="size-8 rounded-lg bg-white dark:bg-slate-800 text-blue-600 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-blue-700 cursor-pointer shadow-xs"
                                                                >
                                                                    <Edit2 className="size-3.5" />
                                                                </Button>

                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    title="Supprimer le trimestre"
                                                                    onClick={() => {
                                                                        confirmDelete({
                                                                            title: 'Supprimer le trimestre',
                                                                            itemType: 'le trimestre',
                                                                            itemName: periode.nom,
                                                                            description: 'Toutes les séquences et les évaluations associées à ce trimestre seront également supprimées.',
                                                                            onConfirm: () => {
                                                                                router.delete(`/administration/periodes/trimestres/${periode.id}`);
                                                                            },
                                                                        });
                                                                    }}
                                                                    className="size-8 rounded-lg bg-white dark:bg-slate-800 text-rose-600 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-rose-700 cursor-pointer shadow-xs"
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                </Button>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* =========================================================================
                                                 * CORPS DU TRIMESTRE (Tableau des Évaluations & Séquences)
                                                 * ========================================================================= */}
                                                <div className="p-5 bg-card space-y-3">
                                                    <div className="flex items-center justify-between text-xs text-muted-foreground px-1">
                                                        <span className="font-semibold uppercase tracking-wider text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                                            <Calendar className="size-3.5 text-sky-600 dark:text-sky-400" />
                                                            Séquences & Évaluations du {periode.nom}
                                                        </span>
                                                        <span className="font-medium">
                                                            {periode.evaluations.length} évaluation{periode.evaluations.length > 1 ? 's' : ''} configurée{periode.evaluations.length > 1 ? 's' : ''}
                                                        </span>
                                                    </div>

                                                    <DataTable
                                                        columns={evalColumns}
                                                        data={periode.evaluations}
                                                        containerClassName="border-2 border-slate-300/85 dark:border-slate-700 shadow-xs"
                                                        headerClassName="bg-gradient-to-r from-slate-200/90 via-slate-100/95 to-slate-200/85 dark:from-slate-800 dark:via-slate-850 dark:to-slate-800 border-b-2 border-slate-300 dark:border-slate-700"
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>

            {/* =========================================================================
             * MODALES CRUD
             * ========================================================================= */}

            {/* Modale Année Scolaire */}
            <Dialog
                open={isAnneeModalOpen}
                onOpenChange={(open) => {
                    setIsAnneeModalOpen(open);
                    if (!open) {
                        setEditingAnnee(null);
                        anneeForm.reset();
                        anneeForm.setData({
                            code: '',
                            nom: '',
                            nom_en: '',
                            date_debut: '',
                            date_fin: '',
                            statut: 'en_preparation',
                            mode_calcul_moyenne: 'arithmetique_simple',
                            is_current: false,
                            auto_generate_structure: true,
                        });
                    }
                }}
            >
                <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editingAnnee ? 'Modifier l\'Année Scolaire' : 'Nouvelle Année Scolaire'}
                        </DialogTitle>
                        <DialogDescription>
                            Définit le cadre temporel officiel des activités scolaires et administratives.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitAnnee} className="space-y-4 pt-2">
                        {/* Première ligne en 2 colonnes : Code de l'année & Statut */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code de l'année scolaire *</Label>
                                <Input
                                    value={anneeForm.data.code}
                                    onChange={(e) => anneeForm.setData('code', e.target.value)}
                                    placeholder="ex: 2028/2029"
                                    required
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Statut de l'année scolaire *</Label>
                                <Select
                                    value={anneeForm.data.statut}
                                    onValueChange={(val) => anneeForm.setData('statut', val)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="en_preparation">En préparation</SelectItem>
                                        <SelectItem value="en_cours">En cours</SelectItem>
                                        <SelectItem value="cloturee">Clôturée</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Deuxième ligne en 2 colonnes : Dates d'ouverture et de fermeture */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Date d'ouverture (Début) *</Label>
                                <Input
                                    type="date"
                                    value={anneeForm.data.date_debut}
                                    onChange={(e) => anneeForm.setData('date_debut', e.target.value)}
                                    required
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Date de fermeture (Fin) *</Label>
                                <Input
                                    type="date"
                                    value={anneeForm.data.date_fin}
                                    onChange={(e) => anneeForm.setData('date_fin', e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        {!editingAnnee && (
                            <div className="p-3.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200/70 dark:border-emerald-800/70 space-y-1.5">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={anneeForm.data.auto_generate_structure}
                                        onChange={(e) => anneeForm.setData('auto_generate_structure', e.target.checked)}
                                        className="rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                    />
                                    <span className="text-xs font-bold text-emerald-900 dark:text-emerald-200">
                                        Initialiser automatiquement le découpage standard pour {selectedEtablissement?.nom || 'l\'établissement'}
                                    </span>
                                </label>
                                <p className="text-[11px] text-emerald-700 dark:text-emerald-300 pl-5">
                                    Pré-remplit les 3 trimestres officiels et leurs 6 séquences avec pondérations équitables (50% / 50%).
                                </p>
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsAnneeModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={anneeForm.processing}>
                                {editingAnnee ? 'Mettre à jour' : 'Créer l\'année'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modale Trimestre */}
            <Dialog
                open={isTrimestreModalOpen}
                onOpenChange={(open) => {
                    setIsTrimestreModalOpen(open);
                    if (!open) {
                        setEditingTrimestre(null);
                        trimestreForm.reset();
                        trimestreForm.setData({
                            etablissement_id: selectedEtablissement?.id || 0,
                            annee_scolaire_id: selectedAnnee?.id || 0,
                            code: '',
                            nom: '',
                            nom_en: '',
                            ordre: periodes.length + 1,
                            date_debut: '',
                            date_fin: '',
                            statut: 'a_venir',
                        });
                    }
                }}
            >
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingTrimestre ? 'Modifier le Trimestre' : 'Nouveau Trimestre'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitTrimestre} className="space-y-4 pt-2">
                        {selectedEtablissement && (
                            <div className="flex items-center gap-2 p-2.5 rounded-lg bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/60 text-xs text-emerald-800 dark:text-emerald-300">
                                <Building2 className="size-3.5 shrink-0" />
                                <span>Établissement cible : <strong>{selectedEtablissement.nom}</strong> ({selectedEtablissement.code})</span>
                            </div>
                        )}
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code *</Label>
                                <Input
                                    value={trimestreForm.data.code}
                                    onChange={(e) => trimestreForm.setData('code', e.target.value)}
                                    placeholder="TRIM-1"
                                    required
                                />
                            </div>
                            <div className="sm:col-span-2 space-y-1">
                                <Label className="text-xs font-semibold">Libellé du trimestre *</Label>
                                <Input
                                    value={trimestreForm.data.nom}
                                    onChange={(e) => trimestreForm.setData('nom', e.target.value)}
                                    placeholder="ex: 1er Trimestre"
                                    required
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Ordre</Label>
                                <Input
                                    type="number"
                                    min="1"
                                    value={trimestreForm.data.ordre}
                                    onChange={(e) => trimestreForm.setData('ordre', parseInt(e.target.value) || 1)}
                                    required
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Date Début</Label>
                                <Input
                                    type="date"
                                    value={trimestreForm.data.date_debut}
                                    onChange={(e) => trimestreForm.setData('date_debut', e.target.value)}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Date Fin</Label>
                                <Input
                                    type="date"
                                    value={trimestreForm.data.date_fin}
                                    onChange={(e) => trimestreForm.setData('date_fin', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs font-semibold">Statut</Label>
                            <Select
                                value={trimestreForm.data.statut}
                                onValueChange={(val) => trimestreForm.setData('statut', val)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="a_venir">À venir</SelectItem>
                                    <SelectItem value="en_cours">En cours</SelectItem>
                                    <SelectItem value="cloturee">Clôturée</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsTrimestreModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={trimestreForm.processing}>
                                {editingTrimestre ? 'Mettre à jour' : 'Ajouter le trimestre'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modale Séquence / Évaluation */}
            <Dialog
                open={isEvaluationModalOpen}
                onOpenChange={(open) => {
                    setIsEvaluationModalOpen(open);
                    if (!open) {
                        setEditingEvaluation(null);
                        evaluationForm.reset();
                        evaluationForm.setData({
                            periode_id: targetPeriodeId || 0,
                            code: '',
                            nom: '',
                            nom_en: '',
                            ordre: 1,
                            ponderation: 50,
                            date_debut: '',
                            date_fin: '',
                            date_limite_saisie: '',
                            statut: 'a_venir',
                        });
                    }
                }}
            >
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingEvaluation ? 'Modifier l\'Évaluation' : 'Nouvelle Séquence d\'Évaluation'}
                        </DialogTitle>
                        <DialogDescription>
                            Composante du trimestre pour la collecte des notes et le calcul des moyennes.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitEvaluation} className="space-y-4 pt-2">
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code *</Label>
                                <Input
                                    value={evaluationForm.data.code}
                                    onChange={(e) => evaluationForm.setData('code', e.target.value)}
                                    placeholder="SEQ-1"
                                    required
                                />
                            </div>
                            <div className="sm:col-span-2 space-y-1">
                                <Label className="text-xs font-semibold">Libellé de la séquence *</Label>
                                <Input
                                    value={evaluationForm.data.nom}
                                    onChange={(e) => evaluationForm.setData('nom', e.target.value)}
                                    placeholder="ex: Séquence 1"
                                    required
                                />
                            </div>
                        </div>

                        {selectedAnnee?.mode_calcul_moyenne === 'ponderee_pourcentage' && (
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Poids / Pondération (%) *</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    value={evaluationForm.data.ponderation}
                                    onChange={(e) => evaluationForm.setData('ponderation', parseFloat(e.target.value) || 0)}
                                    required
                                />
                            </div>
                        )}

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Date Début</Label>
                                <Input
                                    type="date"
                                    value={evaluationForm.data.date_debut}
                                    onChange={(e) => evaluationForm.setData('date_debut', e.target.value)}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Date Fin</Label>
                                <Input
                                    type="date"
                                    value={evaluationForm.data.date_fin}
                                    onChange={(e) => evaluationForm.setData('date_fin', e.target.value)}
                                />
                            </div>
                        </div>

                        {/* Dernière ligne en 2 colonnes : Ordre chronologique & Date butoir */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Ordre chronologique *</Label>
                                <Input
                                    type="number"
                                    min="1"
                                    value={evaluationForm.data.ordre}
                                    onChange={(e) => evaluationForm.setData('ordre', parseInt(e.target.value) || 1)}
                                    required
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold flex items-center gap-1.5">
                                    <Clock className="size-3 text-sky-600" />
                                    Date Butoir Saisie (Optionnelle)
                                </Label>
                                <Input
                                    type="date"
                                    value={evaluationForm.data.date_limite_saisie}
                                    onChange={(e) => evaluationForm.setData('date_limite_saisie', e.target.value)}
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsEvaluationModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={evaluationForm.processing}>
                                {editingEvaluation ? 'Mettre à jour' : 'Ajouter la séquence'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Dialogue de confirmation de suppression globale */}
            <ConfirmDialog />
        </>
    );
}

/**
 * Layout racine et breadcrumb.
 */
PeriodesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Périodes & Évaluation',
            href: '/administration/periodes',
        },
    ],
};

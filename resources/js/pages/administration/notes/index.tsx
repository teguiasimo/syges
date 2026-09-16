import { Head, router, usePage } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    AlertCircle,
    Award,
    BookOpen,
    Calculator,
    CheckCircle2,
    Edit3,
    GraduationCap,
    Lock,
    Save,
    Unlock,
    Users,
} from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/contexts/language-context';
import type {
    AttributionNote,
    CoteAPC,
    EvaluationNote,
    NoteLigneGrille,
    StatutPresence,
} from '@/types/note';

/**
 * Propriétés transmises par Inertia à la page de saisie des notes.
 */
interface NotesPageProps {
    annees: Array<{ id: number; nom: string; is_active: boolean }>;
    anneeActiveId: number;
    periodes: Array<{
        id: number;
        nom: string;
        evaluations: EvaluationNote[];
    }>;
    classes: Array<{ id: number; nom: string; code: string }>;
    attributions: AttributionNote[];
    isGestionnaire: boolean;
    filters: {
        annee_id?: number | string;
        classe_id?: number | string;
        attribution_id?: number | string;
        evaluation_id?: number | string;
    };
}

/**
 * Page principale du Module 10 : Saisie des Notes & Évaluations.
 *
 * Implémente :
 * - Le contrôle d'accès strict (attributions enseignant vs gestionnaire).
 * - La grille tabulaire TanStack Table avec navigation au clavier.
 * - La gestion des présences et appréciations.
 * - Le calcul automatique de la cote APC (CTBA, CBA, CMA, CNA).
 * - Le verrouillage et déverrouillage sécurisé.
 *
 * @param {NotesPageProps} props Propriétés du composant.
 * @returns {JSX.Element}
 */
export default function NotesIndex({
    annees,
    anneeActiveId,
    periodes,
    classes,
    attributions,
    isGestionnaire,
    filters,
}: NotesPageProps) {
    const { t } = useTranslation();
    const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props;

    // États des filtres de sélection
    const [selectedAnnee, setSelectedAnnee] = useState<string>(
        String(filters.annee_id || anneeActiveId || (annees[0]?.id ?? ''))
    );
    const [selectedClasse, setSelectedClasse] = useState<string>(
        String(filters.classe_id || '')
    );
    const [selectedAttribution, setSelectedAttribution] = useState<string>(
        String(filters.attribution_id || '')
    );
    const [selectedEvaluation, setSelectedEvaluation] = useState<string>(
        String(filters.evaluation_id || '')
    );

    // Données de la grille de notes
    const [grille, setGrille] = useState<NoteLigneGrille[]>([]);
    const [competenceSession, setCompetenceSession] = useState<string>('');
    const [isLocked, setIsLocked] = useState<boolean>(false);
    const [isLoadingGrille, setIsLoadingGrille] = useState<boolean>(false);
    const [isSaving, setIsSaving] = useState<boolean>(false);

    // Références pour la navigation au clavier (haut / bas / entrée)
    const inputsRef = useRef<Record<number, HTMLInputElement | null>>({});

    // Notifications Flash
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, {
                position: 'top-right',
                className: 'bg-emerald-600 text-white border-emerald-700',
            });
        }
        if (flash?.error) {
            toast.error(flash.error, {
                position: 'top-right',
            });
        }
    }, [flash]);

    // Filtrer les attributions selon la classe sélectionnée
    const attributionsDeLaClasse = useMemo(() => {
        if (!selectedClasse) return [];
        return attributions.filter((a) => String(a.classe_id) === selectedClasse);
    }, [attributions, selectedClasse]);

    // Liste plate de toutes les évaluations disponibles
    const toutesLesEvaluations = useMemo(() => {
        return periodes.flatMap((p) => p.evaluations);
    }, [periodes]);

    // Charger la grille lorsque le triplet Attribution + Évaluation est sélectionné
    useEffect(() => {
        if (selectedAttribution && selectedEvaluation) {
            setIsLoadingGrille(true);
            fetch(
                `/administration/notes/grille?attribution_id=${selectedAttribution}&evaluation_id=${selectedEvaluation}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                }
            )
                .then((res) => {
                    if (!res.ok) throw new Error('Erreur de chargement');
                    return res.json();
                })
                .then((data) => {
                    const loadedGrille = data.grille || [];
                    setGrille(loadedGrille);
                    setIsLocked(Boolean(data.is_locked));
                    // Pré-remplir la compétence générale de session si déjà saisie
                    const firstComp = loadedGrille.find((g: any) => g.competence_evaluee)?.competence_evaluee || '';
                    setCompetenceSession(firstComp);
                })
                .catch((err) => {
                    console.error(err);
                    toast.error('Impossible de charger la grille de notes.', {
                        position: 'top-right',
                    });
                })
                .finally(() => {
                    setIsLoadingGrille(false);
                });
        } else {
            setGrille([]);
            setIsLocked(false);
            setCompetenceSession('');
        }
    }, [selectedAttribution, selectedEvaluation]);

    /**
     * Met à jour un champ spécifique pour un élève dans l'état local de la grille.
     */
    const updateLigne = (
        inscriptionId: number,
        field: keyof NoteLigneGrille,
        value: any
    ) => {
        setGrille((prev) =>
            prev.map((row) => {
                if (row.inscription_id !== inscriptionId) return row;

                const updated = { ...row, [field]: value };

                // Recalcul immédiat de la cote APC si la note change
                if (field === 'valeur_note') {
                    const num = Number(value);
                    if (value !== '' && !isNaN(num)) {
                        if (num >= 16.0) updated.cote = 'CTBA';
                        else if (num >= 14.0) updated.cote = 'CBA';
                        else if (num >= 10.0) updated.cote = 'CMA';
                        else updated.cote = 'CNA';
                    } else {
                        updated.cote = null;
                    }
                }

                // Réinitialiser la note si le statut de présence n'est pas "présent"
                if (field === 'statut_presence' && value !== 'present') {
                    updated.valeur_note = null;
                    updated.cote = null;
                }

                return updated;
            })
        );
    };

    /**
     * Enregistre l'ensemble des notes saisies en base de données.
     */
    const handleSaveGrille = () => {
        if (!selectedAttribution || !selectedEvaluation) return;

        setIsSaving(true);
        router.post(
            '/administration/notes/sauvegarder',
            {
                attribution_id: Number(selectedAttribution),
                evaluation_id: Number(selectedEvaluation),
                notes: grille.map((g) => ({
                    inscription_id: g.inscription_id,
                    valeur_note: g.valeur_note,
                    statut_presence: g.statut_presence,
                    appreciation: g.appreciation,
                    competence_evaluee: g.competence_evaluee,
                    cote: g.cote,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSaving(false);
                    toast.success('Notes enregistrées avec succès !', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-emerald-700',
                    });
                },
                onError: (err) => {
                    setIsSaving(false);
                    toast.error('Erreur lors de l\'enregistrement des notes.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Verrouille la grille de notes pour empêcher les modifications.
     */
    const handleLockGrille = () => {
        if (!selectedAttribution || !selectedEvaluation) return;

        router.post(
            '/administration/notes/verrouiller',
            {
                attribution_id: Number(selectedAttribution),
                evaluation_id: Number(selectedEvaluation),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsLocked(true);
                    toast.success('Session de notes verrouillée avec succès.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-emerald-700',
                    });
                },
            }
        );
    };

    /**
     * Déverrouille la grille de notes (Censeur / Super-Admin).
     */
    const handleUnlockGrille = () => {
        if (!selectedAttribution || !selectedEvaluation) return;

        router.post(
            '/administration/notes/deverrouiller',
            {
                attribution_id: Number(selectedAttribution),
                evaluation_id: Number(selectedEvaluation),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsLocked(false);
                    toast.success('Session déverrouillée avec succès.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-emerald-700',
                    });
                },
            }
        );
    };

    // Statistiques rapides de la saisie
    const totalEleves = grille.length;
    const notesValides = grille
        .filter((g) => g.statut_presence === 'present' && g.valeur_note !== null)
        .map((g) => Number(g.valeur_note));
    const moyenneProvisoire =
        notesValides.length > 0
            ? (notesValides.reduce((a, b) => a + b, 0) / notesValides.length).toFixed(2)
            : '-';

    /**
     * Définition des colonnes de la table TanStack Table (@tanstack/react-table).
     */
    const columns = useMemo<ColumnDef<NoteLigneGrille>[]>(() => {
        return [
            {
                accessorKey: 'matricule',
                header: t('notes.col_matricule'),
                cell: ({ row }) => (
                    <span className="font-mono text-xs font-semibold text-muted-foreground">
                        {row.original.matricule}
                    </span>
                ),
            },
            {
                accessorKey: 'nom_complet',
                header: t('notes.col_eleve'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <div className="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                            {row.original.nom_complet.charAt(0).toUpperCase()}
                        </div>
                        <span className="font-medium text-foreground">
                            {row.original.nom_complet}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'statut_presence',
                header: t('notes.col_statut'),
                cell: ({ row }) => {
                    const item = row.original;
                    return (
                        <Select
                            disabled={isLocked}
                            value={item.statut_presence}
                            onValueChange={(val: StatutPresence) =>
                                updateLigne(item.inscription_id, 'statut_presence', val)
                            }
                        >
                            <SelectTrigger className="h-8 w-32 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="present">
                                    <span className="text-emerald-600 font-medium">Présent</span>
                                </SelectItem>
                                <SelectItem value="absent_justifie">
                                    <span className="text-amber-600 font-medium">Abs. Justifié</span>
                                </SelectItem>
                                <SelectItem value="absent_non_justifie">
                                    <span className="text-rose-600 font-medium">Abs. Non Just.</span>
                                </SelectItem>
                                <SelectItem value="non_evalue">
                                    <span className="text-muted-foreground">Non Évalué</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    );
                },
            },
            {
                accessorKey: 'valeur_note',
                header: t('notes.col_note'),
                cell: ({ row, table }) => {
                    const item = row.original;
                    const rowIndex = row.index;
                    const rows = table.getRowModel().rows;

                    return (
                        <div className="flex items-center gap-1.5">
                            <Input
                                ref={(el) => {
                                    inputsRef.current[item.inscription_id] = el;
                                }}
                                disabled={isLocked || item.statut_presence !== 'present'}
                                type="number"
                                min="0"
                                max="20"
                                step="0.25"
                                placeholder="-- / 20"
                                className="h-8 w-24 text-center font-bold text-sm"
                                value={item.valeur_note ?? ''}
                                onChange={(e) => {
                                    const val = e.target.value === '' ? null : parseFloat(e.target.value);
                                    updateLigne(item.inscription_id, 'valeur_note', val);
                                }}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === 'ArrowDown') {
                                        e.preventDefault();
                                        const nextRow = rows[rowIndex + 1];
                                        if (nextRow) {
                                            inputsRef.current[nextRow.original.inscription_id]?.focus();
                                        }
                                    } else if (e.key === 'ArrowUp') {
                                        e.preventDefault();
                                        const prevRow = rows[rowIndex - 1];
                                        if (prevRow) {
                                            inputsRef.current[prevRow.original.inscription_id]?.focus();
                                        }
                                    }
                                }}
                            />
                        </div>
                    );
                },
            },
            {
                accessorKey: 'cote',
                header: t('notes.col_cote'),
                cell: ({ row }) => {
                    const cote = row.original.cote;
                    if (!cote) return <span className="text-muted-foreground text-xs">-</span>;

                    const styles: Record<CoteAPC, string> = {
                        CTBA: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300',
                        CBA: 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950 dark:text-blue-300',
                        CMA: 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950 dark:text-amber-300',
                        CNA: 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-300',
                    };

                    return (
                        <Badge variant="outline" className={`font-bold text-xs ${styles[cote]}`}>
                            {cote}
                        </Badge>
                    );
                },
            },
            {
                accessorKey: 'appreciation',
                header: t('notes.col_appreciation'),
                cell: ({ row }) => {
                    const item = row.original;
                    return (
                        <div className="flex items-center gap-1">
                            <Input
                                disabled={isLocked}
                                type="text"
                                placeholder="Appréciation..."
                                className="h-8 text-xs min-w-[160px]"
                                value={item.appreciation}
                                onChange={(e) =>
                                    updateLigne(item.inscription_id, 'appreciation', e.target.value)
                                }
                            />
                        </div>
                    );
                },
            },
            {
                accessorKey: 'competence_evaluee',
                header: t('notes.col_competence'),
                cell: ({ row }) => {
                    const item = row.original;
                    return (
                        <Input
                            disabled={isLocked}
                            type="text"
                            placeholder="Compétence spécifique APC..."
                            className="h-8 text-xs min-w-[200px]"
                            value={item.competence_evaluee}
                            onChange={(e) =>
                                updateLigne(item.inscription_id, 'competence_evaluee', e.target.value)
                            }
                        />
                    );
                },
            },
        ];
    }, [isLocked, t]);

    return (
        <>
            <Head title={t('notes.title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                {/* En-tête de page */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {t('notes.title')}
                            </h1>
                            {isLocked ? (
                                <Badge variant="destructive" className="flex items-center gap-1">
                                    <Lock className="h-3 w-3" />
                                    {t('notes.locked_badge')}
                                </Badge>
                            ) : (
                                <Badge variant="secondary" className="flex items-center gap-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    <CheckCircle2 className="h-3 w-3" />
                                    {t('notes.open_badge')}
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            {t('notes.subtitle')}
                        </p>
                    </div>

                    {/* Actions de sauvegarde et verrouillage */}
                    {grille.length > 0 && (
                        <div className="flex items-center gap-2">
                            {isLocked && isGestionnaire && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleUnlockGrille}
                                    className="gap-1.5 border-amber-500 text-amber-600 hover:bg-amber-50"
                                >
                                    <Unlock className="h-4 w-4" />
                                    {t('notes.btn_unlock')}
                                </Button>
                            )}

                            {!isLocked && (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleLockGrille}
                                        className="gap-1.5"
                                    >
                                        <Lock className="h-4 w-4" />
                                        {t('notes.btn_lock')}
                                    </Button>

                                    <Button
                                        size="sm"
                                        onClick={handleSaveGrille}
                                        disabled={isSaving}
                                        className="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                                    >
                                        <Save className="h-4 w-4" />
                                        {isSaving ? 'Enregistrement...' : t('notes.btn_save')}
                                    </Button>
                                </>
                            )}
                        </div>
                    )}
                </div>

                {/* Barre des Filtres Sélecteurs */}
                <Card className="border-border/60 shadow-sm">
                    <CardContent className="p-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                            {/* Année Scolaire */}
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                    {t('notes.select_annee')}
                                </label>
                                <Select
                                    value={selectedAnnee}
                                    onValueChange={(val) => {
                                        setSelectedAnnee(val);
                                        setSelectedClasse('');
                                        setSelectedAttribution('');
                                    }}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Choisir une année" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {annees.map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>
                                                {a.nom} {a.is_active && '(Actuelle)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Classe */}
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                    {t('notes.select_classe')}
                                </label>
                                <Select
                                    value={selectedClasse}
                                    onValueChange={(val) => {
                                        setSelectedClasse(val);
                                        setSelectedAttribution('');
                                    }}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Choisir une classe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classes.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Matière / Cours Attribué */}
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                    {t('notes.select_matiere')}
                                </label>
                                <Select
                                    disabled={!selectedClasse || attributionsDeLaClasse.length === 0}
                                    value={selectedAttribution}
                                    onValueChange={setSelectedAttribution}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue
                                            placeholder={
                                                attributionsDeLaClasse.length === 0
                                                    ? 'Aucun cours disponible'
                                                    : 'Choisir une matière'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {attributionsDeLaClasse.map((attr) => (
                                            <SelectItem key={attr.id} value={String(attr.id)}>
                                                {attr.matiere?.nom} (Prof: {attr.personnel?.nom_complet})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Évaluation / Séquence */}
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                    {t('notes.select_periode')}
                                </label>
                                <Select
                                    value={selectedEvaluation}
                                    onValueChange={setSelectedEvaluation}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Choisir une évaluation" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {periodes.map((p) => (
                                            <React.Fragment key={p.id}>
                                                <div className="px-2 py-1 text-xs font-bold text-muted-foreground bg-muted/40">
                                                    {p.nom}
                                                </div>
                                                {p.evaluations.map((ev) => (
                                                    <SelectItem key={ev.id} value={String(ev.id)}>
                                                        {ev.nom} ({ev.code})
                                                    </SelectItem>
                                                ))}
                                            </React.Fragment>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Synthèse provisoire de la saisie */}
                {grille.length > 0 && (
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <Card className="border-border/60">
                            <CardContent className="p-3 flex items-center gap-3">
                                <div className="p-2 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50">
                                    <Users className="h-5 w-5" />
                                </div>
                                <div>
                                    <div className="text-xs text-muted-foreground font-medium">Effectif classe</div>
                                    <div className="text-lg font-bold text-foreground">{totalEleves}</div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-border/60">
                            <CardContent className="p-3 flex items-center gap-3">
                                <div className="p-2 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50">
                                    <CheckCircle2 className="h-5 w-5" />
                                </div>
                                <div>
                                    <div className="text-xs text-muted-foreground font-medium">{t('notes.graded_count')}</div>
                                    <div className="text-lg font-bold text-foreground">
                                        {notesValides.length} / {totalEleves}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-border/60">
                            <CardContent className="p-3 flex items-center gap-3">
                                <div className="p-2 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50">
                                    <Calculator className="h-5 w-5" />
                                </div>
                                <div>
                                    <div className="text-xs text-muted-foreground font-medium">{t('notes.class_average_preview')}</div>
                                    <div className="text-lg font-bold text-foreground">{moyenneProvisoire} / 20</div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-border/60">
                            <CardContent className="p-3 flex items-center gap-3">
                                <div className="p-2 rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/50">
                                    <Award className="h-5 w-5" />
                                </div>
                                <div>
                                    <div className="text-xs text-muted-foreground font-medium">Cote APC dominante</div>
                                    <div className="text-lg font-bold text-foreground">
                                        {Number(moyenneProvisoire) >= 16
                                            ? 'CTBA'
                                            : Number(moyenneProvisoire) >= 14
                                            ? 'CBA'
                                            : Number(moyenneProvisoire) >= 10
                                            ? 'CMA'
                                            : 'CNA'}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Tableau TanStack Table ou Message de sélection */}
                {isLoadingGrille ? (
                    <div className="flex flex-col items-center justify-center p-12 bg-card rounded-lg border border-border/60">
                        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent mb-3" />
                        <p className="text-sm text-muted-foreground font-medium">
                            Chargement de la grille d'évaluation...
                        </p>
                    </div>
                ) : grille.length > 0 ? (
                    <div className="flex flex-col gap-3">
                        {/* Barre d'affectation globale de compétence de session */}
                        <Card className="border-indigo-100 bg-indigo-50/50 dark:border-indigo-950 dark:bg-indigo-950/20">
                            <CardContent className="p-3.5 flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1">
                                    <label className="text-xs font-semibold text-indigo-950 dark:text-indigo-200 block mb-1">
                                        Compétence évaluée pour cette session (APC - Nouvelles normes Cameroun)
                                    </label>
                                    <Input
                                        disabled={isLocked}
                                        type="text"
                                        placeholder="Ex: Résoudre des situations-problèmes relatives aux équations linéaires..."
                                        value={competenceSession}
                                        onChange={(e) => setCompetenceSession(e.target.value)}
                                        className="h-8 text-xs bg-background"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={isLocked || !competenceSession.trim()}
                                    onClick={() => {
                                        setGrille((prev) =>
                                            prev.map((item) => ({
                                                ...item,
                                                competence_evaluee: competenceSession,
                                            }))
                                        );
                                        toast.success('Compétence appliquée à tous les élèves de la classe !', {
                                            position: 'top-right',
                                            className: 'bg-emerald-600 text-white border-emerald-700',
                                        });
                                    }}
                                    className="sm:self-end h-8 text-xs bg-indigo-600 hover:bg-indigo-700 text-white gap-1 shrink-0"
                                >
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    Appliquer à toute la classe
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Grille TanStack Table */}
                        <Card className="border-border/60 shadow-sm overflow-hidden">
                            <CardContent className="p-0">
                                <DataTable
                                    columns={columns}
                                    data={grille}
                                    searchPlaceholder="Rechercher un élève par nom ou matricule..."
                                    defaultPageSize={50}
                                />
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <div className="flex flex-col items-center justify-center p-12 bg-muted/20 rounded-lg border border-dashed border-border/80 text-center">
                        <Edit3 className="h-10 w-10 text-muted-foreground/60 mb-2" />
                        <h3 className="text-base font-semibold text-foreground">
                            {t('notes.grille_title')}
                        </h3>
                        <p className="text-sm text-muted-foreground max-w-md mt-1">
                            {t('notes.no_course_selected')}
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Fil d'Ariane et configuration de mise en page pour la page Saisie des Notes.
 */
NotesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Saisie des Notes',
            href: '/administration/notes',
        },
    ],
};


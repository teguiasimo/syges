import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    type ColumnDef,
    type SortingState,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    flexRender,
} from '@tanstack/react-table';
import {
    Award,
    BookOpen,
    Copy,
    Plus,
    Trash2,
    Edit2,
    Filter,
    Search,
    RotateCw,
    School,
    CheckCircle2,
    Layers,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Sparkles,
    AlertCircle,
    ArrowLeft,
    SlidersHorizontal,
    Tag,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toast } from 'sonner';
import { formatDate } from '@/lib/utils';
import type { CompetencePeriodiqueItem, CoteConfigurationItem, MatiereRef, PeriodeRef } from '@/types/pedagogie';
import type { EtablissementRef, AnneeScolaireRef } from '@/types/eleve';

/**
 * Interface des propriétés passées par le contrôleur CompetencePeriodiqueController.
 */
interface CompetencesPageProps {
    competences: CompetencePeriodiqueItem[];
    cotes: CoteConfigurationItem[];
    matieres: MatiereRef[];
    periodes: PeriodeRef[];
    etablissements: EtablissementRef[];
    selectedEtabId: number;
    selectedAnneeId: number;
    anneesScolaires: AnneeScolaireRef[];
    filters: {
        etablissement_id?: string;
        classe_id?: string;
        matiere_id?: string;
        periode_id?: string;
        evaluation_id?: string;
        search?: string;
    };
}

/**
 * Page principale de gestion de la Pédagogie par Compétences (APC) et Cotes d'Acquisition.
 *
 * Fonctionnalités :
 * - Banque de compétences périodiques officielles par classe, matière et période.
 * - Duplication et transfert en masse des compétences vers des classes parallèles.
 * - Paramétrage des cotes d'acquisition personnalisées (A/B/C/D ou CTBA/CBA).
 */
export default function CompetencesIndex({
    competences = [],
    cotes = [],
    matieres = [],
    periodes = [],
    etablissements = [],
    selectedEtabId,
    selectedAnneeId,
    anneesScolaires = [],
    filters = {},
}: CompetencesPageProps) {
    // États de filtrage
    const [selectedEtab, setSelectedEtab] = useState<number>(selectedEtabId);
    const [selectedClasse, setSelectedClasse] = useState<string>(filters.classe_id || '');
    const [selectedMatiere, setSelectedMatiere] = useState<string>(filters.matiere_id || '');
    const [selectedPeriode, setSelectedPeriode] = useState<string>(filters.periode_id || '');
    const [searchTerm, setSearchTerm] = useState<string>(filters.search || '');

    // États des dialogues
    const [isCreateOpen, setIsCreateOpen] = useState<boolean>(false);
    const [isEditOpen, setIsEditOpen] = useState<boolean>(false);
    const [isTransferOpen, setIsTransferOpen] = useState<boolean>(false);
    const [isCoteModalOpen, setIsCoteModalOpen] = useState<boolean>(false);
    const [editingCompetence, setEditingCompetence] = useState<CompetencePeriodiqueItem | null>(null);

    // Formulaire d'ajout / édition de compétence
    const [formData, setFormData] = useState({
        classe_id: '',
        matiere_id: '',
        periode_id: '',
        evaluation_id: '',
        libelle_competence: '',
        description: '',
    });

    // Formulaire de transfert / duplication
    const [transferData, setTransferData] = useState({
        classe_source_id: '',
        classes_cibles_ids: [] as number[],
        periode_id: '',
        ecraser_existant: false,
    });

    // Formulaire de configuration des cotes
    const [coteFormData, setCoteFormData] = useState({
        code_cote: '',
        libelle: '',
        libelle_en: '',
        borne_minimale: 0,
        borne_maximale: 20,
        couleur: '#10B981',
        ordre: 1,
    });

    // Liste aplatie de toutes les classes de l'établissement actif
    const currentEtab = useMemo(() => {
        return etablissements.find((e) => e.id === selectedEtab) || etablissements[0];
    }, [etablissements, selectedEtab]);

    const allClasses = useMemo(() => {
        const list: Array<{ id: number; nom: string; code?: string }> = [];
        if (!currentEtab?.sections) return list;
        for (const sec of currentEtab.sections) {
            for (const cyc of sec.cycles || []) {
                for (const niv of cyc.niveaux || []) {
                    for (const cls of niv.classes || []) {
                        list.push(cls);
                    }
                }
            }
        }
        return list;
    }, [currentEtab]);

    // Filtrage dynamique
    const handleFilterChange = (params: Record<string, string | number>) => {
        router.get(
            '/administration/pedagogie/competences',
            {
                etablissement_id: selectedEtab,
                classe_id: selectedClasse,
                matiere_id: selectedMatiere,
                periode_id: selectedPeriode,
                search: searchTerm,
                ...params,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    /**
     * Enregistre une nouvelle compétence.
     */
    const handleCreateCompetence = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/administration/pedagogie/competences',
            {
                etablissement_id: selectedEtab,
                classe_id: formData.classe_id,
                matiere_id: formData.matiere_id,
                periode_id: formData.periode_id || null,
                evaluation_id: formData.evaluation_id || null,
                libelle_competence: formData.libelle_competence,
                description: formData.description || null,
            },
            {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    setFormData({
                        classe_id: '',
                        matiere_id: '',
                        periode_id: '',
                        evaluation_id: '',
                        libelle_competence: '',
                        description: '',
                    });
                    toast.success('Compétence périodique ajoutée au référentiel.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur lors de la création.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Met à jour une compétence existante.
     */
    const handleUpdateCompetence = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingCompetence) return;

        router.put(
            `/administration/pedagogie/competences/${editingCompetence.id}`,
            {
                libelle_competence: formData.libelle_competence,
                description: formData.description || null,
            },
            {
                onSuccess: () => {
                    setIsEditOpen(false);
                    setEditingCompetence(null);
                    toast.success('Compétence mise à jour avec succès.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur lors de la mise à jour.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Supprime une compétence.
     */
    const handleDeleteCompetence = (comp: CompetencePeriodiqueItem) => {
        if (!confirm(`Supprimer la compétence : "${comp.libelle_competence}" ?`)) return;

        router.delete(`/administration/pedagogie/competences/${comp.id}`, {
            onSuccess: () => {
                toast.success('Compétence supprimée.', {
                    position: 'top-right',
                });
            },
        });
    };

    /**
     * Exécute le transfert et la duplication inter-classes.
     */
    const handleTransfer = (e: React.FormEvent) => {
        e.preventDefault();
        if (!transferData.classe_source_id) {
            toast.error('Veuillez sélectionner une classe source.', { position: 'top-right' });
            return;
        }
        if (transferData.classes_cibles_ids.length === 0) {
            toast.error('Veuillez sélectionner au moins une classe cible.', { position: 'top-right' });
            return;
        }

        router.post(
            '/administration/pedagogie/competences/transferer',
            {
                classe_source_id: transferData.classe_source_id,
                classes_cibles_ids: transferData.classes_cibles_ids,
                periode_id: transferData.periode_id || null,
                ecraser_existant: transferData.ecraser_existant,
            },
            {
                onSuccess: () => {
                    setIsTransferOpen(false);
                    setTransferData({
                        classe_source_id: '',
                        classes_cibles_ids: [],
                        periode_id: '',
                        ecraser_existant: false,
                    });
                    toast.success('Compétences dupliquées avec succès vers les classes sélectionnées.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur lors de la duplication.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Enregistre ou modifie une cote d'acquisition.
     */
    const handleSaveCote = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/administration/pedagogie/competences/cotes',
            {
                etablissement_id: selectedEtab,
                ...coteFormData,
            },
            {
                onSuccess: () => {
                    setIsCoteModalOpen(false);
                    setCoteFormData({
                        code_cote: '',
                        libelle: '',
                        libelle_en: '',
                        borne_minimale: 0,
                        borne_maximale: 20,
                        couleur: '#10B981',
                        ordre: 1,
                    });
                    toast.success('Cote d\'acquisition enregistrée.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur lors de l\'enregistrement de la cote.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Supprime une cote d'acquisition.
     */
    const handleDeleteCote = (cote: CoteConfigurationItem) => {
        if (!confirm(`Supprimer la cote "${cote.code_cote}" ? Le barème officiel MINESEC s'appliquera.`)) return;

        router.delete(`/administration/pedagogie/competences/cotes/${cote.id}`, {
            onSuccess: () => {
                toast.success('Cote personnalisée supprimée.', {
                    position: 'top-right',
                });
            },
        });
    };

    // Configuration TanStack Table
    const [sorting, setSorting] = useState<SortingState>([]);

    const columns: ColumnDef<CompetencePeriodiqueItem>[] = useMemo(
        () => [
            {
                accessorKey: 'classe.nom',
                header: 'Classe',
                cell: ({ row }) => (
                    <Badge variant="outline" className="font-semibold text-xs border-indigo-200 bg-indigo-50/60 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300">
                        {row.original.classe?.nom || 'N/A'}
                    </Badge>
                ),
            },
            {
                accessorKey: 'matiere.nom',
                header: 'Matière',
                cell: ({ row }) => (
                    <div>
                        <span className="font-medium text-sm text-foreground">{row.original.matiere?.nom || 'Matière'}</span>
                        <span className="ml-1.5 text-xs text-muted-foreground uppercase">({row.original.matiere?.code})</span>
                    </div>
                ),
            },
            {
                accessorKey: 'periode.nom',
                header: 'Période / Éval',
                cell: ({ row }) => (
                    <div className="flex flex-col gap-0.5">
                        <span className="text-xs font-medium text-foreground">
                            {row.original.periode?.nom || 'Toute l\'année'}
                        </span>
                        {row.original.evaluation && (
                            <span className="text-[11px] text-muted-foreground">
                                Éval: {row.original.evaluation.nom}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'libelle_competence',
                header: 'Énoncé de la Compétence (APC)',
                cell: ({ row }) => (
                    <div className="max-w-md">
                        <p className="text-sm font-medium text-foreground leading-snug">
                            {row.original.libelle_competence}
                        </p>
                        {row.original.description && (
                            <p className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                                {row.original.description}
                            </p>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'is_active',
                header: 'Statut',
                cell: ({ row }) => (
                    <Badge
                        variant="secondary"
                        className={
                            row.original.is_active
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300'
                                : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                        }
                    >
                        {row.original.is_active ? 'Actif' : 'Désactivé'}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: 'Actions',
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                            onClick={() => {
                                setEditingCompetence(row.original);
                                setFormData({
                                    classe_id: String(row.original.classe_id),
                                    matiere_id: String(row.original.matiere_id),
                                    periode_id: row.original.periode_id ? String(row.original.periode_id) : '',
                                    evaluation_id: row.original.evaluation_id ? String(row.original.evaluation_id) : '',
                                    libelle_competence: row.original.libelle_competence,
                                    description: row.original.description || '',
                                });
                                setIsEditOpen(true);
                            }}
                            title="Modifier"
                        >
                            <Edit2 className="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                            onClick={() => handleDeleteCompetence(row.original)}
                            title="Supprimer"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ),
            },
        ],
        []
    );

    const table = useReactTable({
        data: competences,
        columns,
        state: {
            sorting,
        },
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getRowId: (row) => String(row.id),
        initialState: {
            pagination: {
                pageSize: 15,
            },
        },
    });

    return (
        <>
            <Head title="Pédagogie APC - Compétences & Cotes" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8">
                {/* En-tête de page & navigation */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Button variant="ghost" size="sm" asChild className="h-8 px-2 gap-1.5 text-xs text-muted-foreground hover:text-foreground">
                                <Link href="/administration/notes">
                                    <ArrowLeft className="size-3.5" />
                                    Retour aux Évaluations & Notes
                                </Link>
                            </Button>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2.5 rounded-xl bg-linear-to-br from-indigo-600 to-violet-700 text-white shadow-md shadow-indigo-500/20">
                                <Award className="size-6" />
                            </div>
                            <div>
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                                    Pédagogie APC & Cotes d'Acquisition
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    Gestion du référentiel officiel des compétences périodiques, duplication inter-classes et barèmes de cotes.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Actions principales */}
                    <div className="flex items-center gap-2 flex-wrap">
                        <Button
                            variant="outline"
                            onClick={() => setIsTransferOpen(true)}
                            className="gap-2 h-9 text-xs font-semibold border-indigo-300 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300"
                        >
                            <Copy className="size-3.5" />
                            Dupliquer / Transférer
                        </Button>
                        <Button
                            onClick={() => {
                                setFormData({
                                    classe_id: selectedClasse || (allClasses[0]?.id ? String(allClasses[0].id) : ''),
                                    matiere_id: selectedMatiere || (matieres[0]?.id ? String(matieres[0].id) : ''),
                                    periode_id: selectedPeriode || '',
                                    evaluation_id: '',
                                    libelle_competence: '',
                                    description: '',
                                });
                                setIsCreateOpen(true);
                            }}
                            className="gap-2 h-9 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm"
                        >
                            <Plus className="size-4" />
                            Nouvelle Compétence
                        </Button>
                    </div>
                </div>

                {/* Onglets : Compétences APC vs Cotes d'acquisition */}
                <Tabs defaultValue="competences" className="w-full">
                    <TabsList className="grid w-full sm:w-80 grid-cols-2">
                        <TabsTrigger value="competences" className="text-xs font-medium">
                            <BookOpen className="size-3.5 mr-1.5" />
                            Compétences ({competences.length})
                        </TabsTrigger>
                        <TabsTrigger value="cotes" className="text-xs font-medium">
                            <SlidersHorizontal className="size-3.5 mr-1.5" />
                            Barème des Cotes ({cotes.length})
                        </TabsTrigger>
                    </TabsList>

                    {/* ONGLET 1 : COMPÉTENCES */}
                    <TabsContent value="competences" className="mt-4 space-y-4">
                        {/* Barre de filtres */}
                        <Card className="border-border/60 shadow-sm">
                            <CardContent className="p-4 sm:p-5">
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                                    {/* Classe */}
                                    <div>
                                        <label className="text-xs font-medium text-muted-foreground mb-1 block">Classe</label>
                                        <select
                                            value={selectedClasse}
                                            onChange={(e) => {
                                                setSelectedClasse(e.target.value);
                                                handleFilterChange({ classe_id: e.target.value });
                                            }}
                                            className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-ring"
                                        >
                                            <option value="">Toutes les classes</option>
                                            {allClasses.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.nom}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Matière */}
                                    <div>
                                        <label className="text-xs font-medium text-muted-foreground mb-1 block">Matière</label>
                                        <select
                                            value={selectedMatiere}
                                            onChange={(e) => {
                                                setSelectedMatiere(e.target.value);
                                                handleFilterChange({ matiere_id: e.target.value });
                                            }}
                                            className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-ring"
                                        >
                                            <option value="">Toutes les matières</option>
                                            {matieres.map((m) => (
                                                <option key={m.id} value={m.id}>
                                                    {m.nom} ({m.code})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Période */}
                                    <div>
                                        <label className="text-xs font-medium text-muted-foreground mb-1 block">Période</label>
                                        <select
                                            value={selectedPeriode}
                                            onChange={(e) => {
                                                setSelectedPeriode(e.target.value);
                                                handleFilterChange({ periode_id: e.target.value });
                                            }}
                                            className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-ring"
                                        >
                                            <option value="">Toute l'année</option>
                                            {periodes.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.nom}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Recherche par mot clé */}
                                    <div className="lg:col-span-2">
                                        <label className="text-xs font-medium text-muted-foreground mb-1 block">Recherche</label>
                                        <div className="relative">
                                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                            <Input
                                                placeholder="Rechercher un intitulé de compétence..."
                                                value={searchTerm}
                                                onChange={(e) => setSearchTerm(e.target.value)}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        handleFilterChange({ search: searchTerm });
                                                    }
                                                }}
                                                className="pl-8 h-9 text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Tableau TanStack Table */}
                        <Card className="border-border/60 shadow-sm overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse text-sm">
                                    <thead>
                                        {table.getHeaderGroups().map((headerGroup) => (
                                            <tr key={headerGroup.id} className="border-b bg-muted/40 text-muted-foreground text-xs font-semibold">
                                                {headerGroup.headers.map((header) => (
                                                    <th key={header.id} className="p-3.5">
                                                        {header.isPlaceholder
                                                            ? null
                                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                                    </th>
                                                ))}
                                            </tr>
                                        ))}
                                    </thead>
                                    <tbody className="divide-y divide-border/50">
                                        {table.getRowModel().rows.length > 0 ? (
                                            table.getRowModel().rows.map((row) => (
                                                <tr key={row.id} className="hover:bg-muted/30 transition-colors">
                                                    {row.getVisibleCells().map((cell) => (
                                                        <td key={cell.id} className="p-3.5">
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={columns.length} className="text-center py-12 text-muted-foreground">
                                                    <div className="flex flex-col items-center justify-center gap-2">
                                                        <BookOpen className="size-8 text-muted-foreground/40" />
                                                        <p className="text-sm font-medium">Aucune compétence trouvée pour ces critères.</p>
                                                        <p className="text-xs">Cliquez sur « Nouvelle Compétence » pour alimenter le référentiel.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination TanStack Table */}
                            {table.getPageCount() > 1 && (
                                <div className="flex items-center justify-between p-4 border-t text-xs text-muted-foreground">
                                    <span>
                                        Page {table.getState().pagination.pageIndex + 1} sur {table.getPageCount()} ({competences.length} au total)
                                    </span>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-7"
                                            onClick={() => table.setPageIndex(0)}
                                            disabled={!table.getCanPreviousPage()}
                                        >
                                            <ChevronsLeft className="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-7"
                                            onClick={() => table.previousPage()}
                                            disabled={!table.getCanPreviousPage()}
                                        >
                                            <ChevronLeft className="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-7"
                                            onClick={() => table.nextPage()}
                                            disabled={!table.getCanNextPage()}
                                        >
                                            <ChevronRight className="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-7"
                                            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
                                            disabled={!table.getCanNextPage()}
                                        >
                                            <ChevronsRight className="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Card>
                    </TabsContent>

                    {/* ONGLET 2 : COTES D'ACQUISITION */}
                    <TabsContent value="cotes" className="mt-4 space-y-4">
                        <Card className="border-border/60 shadow-sm">
                            <CardHeader className="flex flex-row items-center justify-between pb-3">
                                <div>
                                    <CardTitle className="text-base font-semibold">Grille des Cotes d'Acquisition APC</CardTitle>
                                    <CardDescription className="text-xs">
                                        Barème appliqué sur les bulletins officiels pour traduire les notes chiffrées en cotes qualitatives.
                                    </CardDescription>
                                </div>
                                <Button
                                    size="sm"
                                    onClick={() => {
                                        setCoteFormData({
                                            code_cote: '',
                                            libelle: '',
                                            libelle_en: '',
                                            borne_minimale: 0,
                                            borne_maximale: 20,
                                            couleur: '#10B981',
                                            ordre: cotes.length + 1,
                                        });
                                        setIsCoteModalOpen(true);
                                    }}
                                    className="gap-1.5 h-8 text-xs bg-indigo-600 hover:bg-indigo-700 text-white"
                                >
                                    <Plus className="size-3.5" />
                                    Ajouter une Cote
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {cotes.length === 0 ? (
                                    <div className="p-4 rounded-lg bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 text-sky-900 dark:text-sky-200 text-xs leading-relaxed">
                                        <div className="flex items-start gap-2.5">
                                            <Sparkles className="size-4 shrink-0 text-sky-600 mt-0.5" />
                                            <div>
                                                <p className="font-semibold mb-1">Barème National MINESEC par Défaut Actif :</p>
                                                <ul className="list-disc list-inside space-y-1 text-muted-foreground dark:text-sky-300">
                                                    <li><strong>CTBA (Compétence Très Bien Acquise)</strong> : Note [18,00 - 20,00] (Vert émeraude)</li>
                                                    <li><strong>CBA (Compétence Bien Acquise)</strong> : Note [14,00 - 17,99] (Bleu océan)</li>
                                                    <li><strong>CMA (Compétence Moyennement Acquise)</strong> : Note [10,00 - 13,99] (Ambre / Jaune)</li>
                                                    <li><strong>CNA (Compétence Non Acquise)</strong> : Note [0,00 - 9,99] (Rouge corail)</li>
                                                </ul>
                                                <p className="mt-2 text-[11px]">
                                                    Vous pouvez ajouter ci-dessus des cotes alphabétiques (A, B, C, D) si votre établissement suit ce standard.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left border-collapse text-xs">
                                            <thead>
                                                <tr className="border-b bg-muted/40 text-muted-foreground font-semibold">
                                                    <th className="p-3">Code</th>
                                                    <th className="p-3">Libellé Français</th>
                                                    <th className="p-3">Libellé Anglais</th>
                                                    <th className="p-3">Intervalle de Note</th>
                                                    <th className="p-3">Couleur</th>
                                                    <th className="p-3">Ordre</th>
                                                    <th className="p-3 text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border/50">
                                                {cotes.map((cote) => (
                                                    <tr key={cote.id} className="hover:bg-muted/30">
                                                        <td className="p-3">
                                                            <Badge
                                                                style={{ backgroundColor: cote.couleur }}
                                                                className="text-white font-bold text-xs"
                                                            >
                                                                {cote.code_cote}
                                                            </Badge>
                                                        </td>
                                                        <td className="p-3 font-medium text-foreground">{cote.libelle}</td>
                                                        <td className="p-3 text-muted-foreground">{cote.libelle_en || '-'}</td>
                                                        <td className="p-3 font-mono font-semibold">
                                                            [{cote.borne_minimale} - {cote.borne_maximale}] / 20
                                                        </td>
                                                        <td className="p-3">
                                                            <div className="flex items-center gap-1.5">
                                                                <span
                                                                    className="size-3.5 rounded-full border border-gray-300 shadow-sm"
                                                                    style={{ backgroundColor: cote.couleur }}
                                                                />
                                                                <span className="font-mono text-[11px] text-muted-foreground">{cote.couleur}</span>
                                                            </div>
                                                        </td>
                                                        <td className="p-3 text-muted-foreground">{cote.ordre}</td>
                                                        <td className="p-3 text-right">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                                                                onClick={() => handleDeleteCote(cote)}
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* MODAL 1 : NOUVELLE COMPÉTENCE */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Plus className="size-4 text-indigo-600" />
                            Ajouter une Compétence Périodique (APC)
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Associe une compétence officielle du programme à une classe, une matière et une période.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateCompetence} className="space-y-3.5 py-2">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Classe *</label>
                                <select
                                    required
                                    value={formData.classe_id}
                                    onChange={(e) => setFormData({ ...formData, classe_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Sélectionner...</option>
                                    {allClasses.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.nom}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Matière *</label>
                                <select
                                    required
                                    value={formData.matiere_id}
                                    onChange={(e) => setFormData({ ...formData, matiere_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Sélectionner...</option>
                                    {matieres.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.nom} ({m.code})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Période (Trimestre)</label>
                                <select
                                    value={formData.periode_id}
                                    onChange={(e) => setFormData({ ...formData, periode_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Toute l'année</option>
                                    {periodes.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.nom}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Évaluation (Optionnelle)</label>
                                <select
                                    value={formData.evaluation_id}
                                    onChange={(e) => setFormData({ ...formData, evaluation_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Toutes les séquences</option>
                                    {periodes
                                        .find((p) => String(p.id) === formData.periode_id)
                                        ?.evaluations?.map((ev) => (
                                            <option key={ev.id} value={ev.id}>
                                                {ev.nom} ({ev.code})
                                            </option>
                                        ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">
                                Énoncé de la Compétence Périodique *
                            </label>
                            <Input
                                required
                                placeholder="Ex: Résoudre un problème de la vie courante faisant appel aux équations du 1er degré..."
                                value={formData.libelle_competence}
                                onChange={(e) => setFormData({ ...formData, libelle_competence: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">
                                Description détaillée ou critères d'évaluation (Optionnel)
                            </label>
                            <textarea
                                rows={3}
                                placeholder="Précisions pédagogiques, indicateurs de réussite..."
                                value={formData.description}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                className="w-full p-2.5 rounded-md border border-input bg-background text-xs"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsCreateOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-indigo-600 hover:bg-indigo-700 text-white">
                                Enregistrer
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 2 : MODIFIER UNE COMPÉTENCE */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Edit2 className="size-4 text-blue-600" />
                            Modifier la Compétence
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleUpdateCompetence} className="space-y-3.5 py-2">
                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Énoncé de la Compétence *</label>
                            <Input
                                required
                                value={formData.libelle_competence}
                                onChange={(e) => setFormData({ ...formData, libelle_competence: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">Description</label>
                            <textarea
                                rows={3}
                                value={formData.description}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                className="w-full p-2.5 rounded-md border border-input bg-background text-xs"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsEditOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-blue-600 hover:bg-blue-700 text-white">
                                Mettre à jour
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 3 : TRANSFERT & DUPLICATION INTER-CLASSES */}
            <Dialog open={isTransferOpen} onOpenChange={setIsTransferOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Copy className="size-4 text-indigo-600" />
                            Duplication Inter-Classes des Compétences APC
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Copiez instantanément toutes les compétences d'une classe source vers une ou plusieurs classes parallèles (ex: de 6e A vers 6e B, 6e C).
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleTransfer} className="space-y-4 py-2">
                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Classe Source (Modèle) *</label>
                            <select
                                required
                                value={transferData.classe_source_id}
                                onChange={(e) => setTransferData({ ...transferData, classe_source_id: e.target.value })}
                                className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs font-medium"
                            >
                                <option value="">Sélectionner la classe source...</option>
                                {allClasses.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.nom}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">Période concernée</label>
                            <select
                                value={transferData.periode_id}
                                onChange={(e) => setTransferData({ ...transferData, periode_id: e.target.value })}
                                className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                            >
                                <option value="">Toutes les périodes</option>
                                {periodes.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.nom}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1.5 block">
                                Classes Cibles (Cocher les classes à alimenter) *
                            </label>
                            <div className="max-h-48 overflow-y-auto p-3 border rounded-md divide-y divide-border/40 bg-muted/20 space-y-1.5">
                                {allClasses
                                    .filter((c) => String(c.id) !== transferData.classe_source_id)
                                    .map((c) => {
                                        const isChecked = transferData.classes_cibles_ids.includes(c.id);
                                        return (
                                            <label
                                                key={c.id}
                                                className="flex items-center gap-2 pt-1.5 cursor-pointer text-xs font-medium text-foreground hover:text-indigo-600"
                                            >
                                                <Checkbox
                                                    checked={isChecked}
                                                    onCheckedChange={(checked) => {
                                                        if (checked) {
                                                            setTransferData({
                                                                ...transferData,
                                                                classes_cibles_ids: [...transferData.classes_cibles_ids, c.id],
                                                            });
                                                        } else {
                                                            setTransferData({
                                                                ...transferData,
                                                                classes_cibles_ids: transferData.classes_cibles_ids.filter((id) => id !== c.id),
                                                            });
                                                        }
                                                    }}
                                                />
                                                <span>{c.nom}</span>
                                            </label>
                                        );
                                    })}
                            </div>
                        </div>

                        <div className="flex items-center gap-2 p-2.5 rounded-md bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                            <Checkbox
                                id="ecraser"
                                checked={transferData.ecraser_existant}
                                onCheckedChange={(c) => setTransferData({ ...transferData, ecraser_existant: !!c })}
                            />
                            <label htmlFor="ecraser" className="text-xs text-amber-900 dark:text-amber-200 cursor-pointer">
                                Écraser et remplacer les compétences existantes dans les classes cibles
                            </label>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsTransferOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-indigo-600 hover:bg-indigo-700 text-white">
                                Lancer la Duplication
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 4 : AJOUTER / CONFIGURER UNE COTE */}
            <Dialog open={isCoteModalOpen} onOpenChange={setIsCoteModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <SlidersHorizontal className="size-4 text-indigo-600" />
                            Configurer une Cote d'Acquisition
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Définissez le code (ex: A, B+, CTBA), les bornes de notes et la couleur associée.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSaveCote} className="space-y-3.5 py-2">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Code Cote *</label>
                                <Input
                                    required
                                    placeholder="Ex: A, B, CTBA..."
                                    value={coteFormData.code_cote}
                                    onChange={(e) => setCoteFormData({ ...coteFormData, code_cote: e.target.value.toUpperCase() })}
                                    className="h-9 text-xs font-bold uppercase"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Couleur du Badge *</label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        value={coteFormData.couleur}
                                        onChange={(e) => setCoteFormData({ ...coteFormData, couleur: e.target.value })}
                                        className="size-9 rounded border cursor-pointer p-0.5"
                                    />
                                    <Input
                                        value={coteFormData.couleur}
                                        onChange={(e) => setCoteFormData({ ...coteFormData, couleur: e.target.value })}
                                        className="h-9 text-xs font-mono"
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Libellé (Français) *</label>
                            <Input
                                required
                                placeholder="Ex: Compétence Parfaitement Acquise"
                                value={coteFormData.libelle}
                                onChange={(e) => setCoteFormData({ ...coteFormData, libelle: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">Libellé (Anglais / Bilingue)</label>
                            <Input
                                placeholder="Ex: Mastered Competence"
                                value={coteFormData.libelle_en}
                                onChange={(e) => setCoteFormData({ ...coteFormData, libelle_en: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Borne Min (Note /20) *</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="20"
                                    required
                                    value={coteFormData.borne_minimale}
                                    onChange={(e) => setCoteFormData({ ...coteFormData, borne_minimale: parseFloat(e.target.value) || 0 })}
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Borne Max (Note /20) *</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="20"
                                    required
                                    value={coteFormData.borne_maximale}
                                    onChange={(e) => setCoteFormData({ ...coteFormData, borne_maximale: parseFloat(e.target.value) || 0 })}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsCoteModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-indigo-600 hover:bg-indigo-700 text-white">
                                Sauvegarder la Cote
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Configuration du layout applicatif et du fil d'Ariane.
 */
CompetencesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Pédagogie & Évaluations',
            href: '/administration/notes',
        },
        {
            title: 'Compétences APC & Cotes',
            href: '/administration/pedagogie/competences',
        },
    ],
};

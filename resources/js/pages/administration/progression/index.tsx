import { Head, router } from '@inertiajs/react';
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    SortingState,
    useReactTable,
} from '@tanstack/react-table';
import {
    AlertCircle,
    BookOpen,
    Calendar,
    CheckCircle2,
    Clock,
    Edit2,
    Plus,
    Printer,
    Search,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { FicheProgression, ProgressionPageProps } from '@/types/progression';
import { NouvelleSeanceModal } from './modals/nouvelle-seance-modal';
import { VisaSeanceModal } from './modals/visa-seance-modal';

const _breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administration', href: '/administration' },
    { title: 'Cahier de Texte & Progression', href: '/administration/progression-pedagogique' },
];

/**
 * Page principale de gestion du Cahier de Texte & Fiches de Progression Pédagogique.
 * Conforme au Manuel Aurore 1.0.2 (§5.1.8) et au standard ministériel Gesco V1.1.
 * 
 * Implémentée avec TanStack Table, affichage des KPI d'avancement du programme,
 * filtres dynamiques, apposition du visa hiérarchique et génération de la fiche d'inspection en PDF.
 */
export default function ProgressionPedagogiqueIndex({
    fiches,
    stats,
    filters,
    classes,
    matieres,
    enseignants: _enseignants,
    periodes,
    attributions,
}: ProgressionPageProps) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [search, setSearch] = useState(filters.search || '');
    const [selectedClasse, setSelectedClasse] = useState(filters.classe_id ? String(filters.classe_id) : 'all');
    const [selectedMatiere, setSelectedMatiere] = useState(filters.matiere_id ? String(filters.matiere_id) : 'all');
    const [selectedPeriode, setSelectedPeriode] = useState(filters.periode_id ? String(filters.periode_id) : 'all');
    const [selectedStatut, setSelectedStatut] = useState(filters.statut || 'all');
    const [selectedVisa, setSelectedVisa] = useState(filters.visa || 'all');

    // Modals state
    const [nouvelleSeanceModalOpen, setNouvelleSeanceModalOpen] = useState(false);
    const [editingSeance, setEditingSeance] = useState<FicheProgression | null>(null);
    const [visaModalOpen, setVisaModalOpen] = useState(false);
    const [targetSeanceVisa, setTargetSeanceVisa] = useState<FicheProgression | null>(null);

    // Détermine si une attribution précise est sélectionnée pour impression
    const currentAttributionId = useMemo(() => {
        if (selectedClasse !== 'all' && selectedMatiere !== 'all') {
            const match = attributions.find(
                (a) => String(a.classe_id) === selectedClasse && String(a.matiere_id) === selectedMatiere
            );
            return match ? match.id : null;
        }
        return null;
    }, [selectedClasse, selectedMatiere, attributions]);

    // Filtrage et rechargement Inertia
    const handleApplyFilters = (newFilters?: Partial<typeof filters>) => {
        const queryParams: Record<string, string> = {};

        const cl = newFilters?.classe_id !== undefined ? newFilters.classe_id : selectedClasse;
        if (cl && cl !== 'all') queryParams.classe_id = String(cl);

        const mat = newFilters?.matiere_id !== undefined ? newFilters.matiere_id : selectedMatiere;
        if (mat && mat !== 'all') queryParams.matiere_id = String(mat);

        const per = newFilters?.periode_id !== undefined ? newFilters.periode_id : selectedPeriode;
        if (per && per !== 'all') queryParams.periode_id = String(per);

        const st = newFilters?.statut !== undefined ? newFilters.statut : selectedStatut;
        if (st && st !== 'all') queryParams.statut = String(st);

        const vi = newFilters?.visa !== undefined ? newFilters.visa : selectedVisa;
        if (vi && vi !== 'all') queryParams.visa = String(vi);

        const sc = newFilters?.search !== undefined ? newFilters.search : search;
        if (sc) queryParams.search = sc;

        router.get('/administration/progression-pedagogique', queryParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleDelete = (seance: FicheProgression) => {
        if (confirm(`Êtes-vous sûr de vouloir supprimer cette séance (${seance.titre_lecon}) ?`)) {
            router.delete(`/administration/progression-pedagogique/${seance.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Séance supprimée avec succès', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white font-medium border-none shadow-lg',
                    });
                },
            });
        }
    };

    // Définition des colonnes TanStack Table
    const columns = useMemo<ColumnDef<FicheProgression>[]>(
        () => [
            {
                accessorKey: 'date_seance',
                header: 'Date & Semaine',
                cell: ({ row }) => {
                    const seance = row.original;
                    return (
                        <div className="flex flex-col">
                            <span className="font-semibold text-slate-800 flex items-center gap-1.5">
                                <Calendar className="w-3.5 h-3.5 shrink-0 text-teal-600" />
                                {formatDate(seance.date_seance)}
                            </span>
                            {seance.semaine_numero && (
                                <span className="text-[11px] text-slate-500">
                                    Semaine {seance.semaine_numero}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'cours',
                header: 'Classe & Matière',
                cell: ({ row }) => {
                    const seance = row.original;
                    const attr = seance.attribution;
                    return (
                        <div className="flex flex-col">
                            <span className="font-medium text-slate-900">
                                {attr?.classe?.nom ?? 'N/A'}
                            </span>
                            <span className="text-xs text-teal-600 font-semibold">
                                {attr?.matiere?.nom ?? 'N/A'}
                            </span>
                        </div>
                    );
                },
            },
            {
                id: 'enseignant',
                header: 'Enseignant',
                cell: ({ row }) => {
                    const prof = row.original.attribution?.personnel;
                    return (
                        <div className="text-xs font-medium text-slate-700">
                            {prof ? `${prof.nom} ${prof.prenom ?? ''}` : '---'}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'titre_lecon',
                header: 'Chapitre & Contenu Didactique',
                cell: ({ row }) => {
                    const seance = row.original;
                    return (
                        <div className="flex flex-col max-w-md whitespace-normal">
                            <span className="text-xs font-bold text-slate-900">
                                {seance.chapitre}
                            </span>
                            <span className="text-xs text-slate-700 font-medium">
                                {seance.titre_lecon}
                            </span>
                            {seance.travail_a_faire && (
                                <span className="text-[11px] text-blue-600 mt-0.5 line-clamp-1 italic">
                                    <strong>Devoir :</strong> {seance.travail_a_faire}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'heures',
                header: 'Volume Horaire',
                cell: ({ row }) => {
                    const seance = row.original;
                    return (
                        <div className="flex items-center gap-1 text-xs">
                            <Clock className="w-3.5 h-3.5 text-slate-400" />
                            <span className="font-bold text-teal-600">
                                {seance.heures_effectuees}h
                            </span>
                            <span className="text-slate-400">/ {seance.heures_prevues}h</span>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'statut',
                header: 'Statut',
                cell: ({ row }) => {
                    const st = row.original.statut;
                    if (st === 'fait') {
                        return (
                            <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 border border-emerald-300">
                                Fait
                            </Badge>
                        );
                    }
                    if (st === 'en_cours') {
                        return (
                            <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100 border border-amber-300">
                                En cours
                            </Badge>
                        );
                    }
                    return (
                        <Badge className="bg-rose-100 text-rose-800 hover:bg-rose-100 border border-rose-300">
                            Reporté
                        </Badge>
                    );
                },
            },
            {
                accessorKey: 'visa_censeur',
                header: 'Visa Censeur',
                cell: ({ row }) => {
                    const seance = row.original;
                    if (seance.visa_censeur) {
                        return (
                            <div className="flex flex-col">
                                <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
                                    <ShieldCheck className="w-3.5 h-3.5" /> Visé
                                </span>
                                {seance.date_visa && (
                                    <span className="text-[10px] text-slate-400">
                                        le {formatDate(seance.date_visa)}
                                    </span>
                                )}
                            </div>
                        );
                    }
                    return (
                        <span className="inline-flex items-center gap-1 text-xs font-medium text-amber-600">
                            <AlertCircle className="w-3.5 h-3.5" /> En attente
                        </span>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                cell: ({ row }) => {
                    const seance = row.original;
                    return (
                        <div className="flex items-center gap-1">
                            {/* Bouton Visa */}
                            <Button
                                variant="ghost"
                                size="sm"
                                title={seance.visa_censeur ? 'Modifier le visa' : 'Viser cette séance'}
                                className={`h-8 w-8 p-0 ${
                                    seance.visa_censeur
                                        ? 'text-emerald-600 hover:bg-emerald-50'
                                        : 'text-teal-600 hover:bg-teal-50'
                                }`}
                                onClick={() => {
                                    setTargetSeanceVisa(seance);
                                    setVisaModalOpen(true);
                                }}
                            >
                                <ShieldCheck className="w-4 h-4" />
                            </Button>

                            {/* Bouton Modifier */}
                            <Button
                                variant="ghost"
                                size="sm"
                                title="Modifier la séance"
                                className="h-8 w-8 p-0 text-slate-600 hover:bg-slate-100"
                                onClick={() => {
                                    setEditingSeance(seance);
                                    setNouvelleSeanceModalOpen(true);
                                }}
                            >
                                <Edit2 className="w-3.5 h-3.5" />
                            </Button>

                            {/* Bouton Supprimer */}
                            <Button
                                variant="ghost"
                                size="sm"
                                title="Supprimer la séance"
                                className="h-8 w-8 p-0 text-rose-500 hover:bg-rose-50 hover:text-rose-600"
                                onClick={() => handleDelete(seance)}
                            >
                                <Trash2 className="w-3.5 h-3.5" />
                            </Button>
                        </div>
                    );
                },
            },
        ],
        []
    );

    // Initialisation TanStack Table
    const table = useReactTable({
        data: fiches.data,
        columns,
        state: { sorting },
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        manualPagination: true,
    });

    return (
        <>
            <Head title="Cahier de Texte & Progression Pédagogique" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête de page & Actions */}
                <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground flex items-start sm:items-center gap-2.5">
                            <BookOpen className="w-7 h-7 shrink-0 text-teal-600 mt-0.5 sm:mt-0" />
                            <span>Cahier de Texte & Progression Pédagogique</span>
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Suivi didactique des séances dispensées, contrôle horaire ministériel et visa hiérarchique du censeur.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {currentAttributionId && (
                            <Button
                                variant="outline"
                                className="gap-1.5 text-slate-700 border-teal-200 hover:bg-teal-50"
                                onClick={() => {
                                    window.open(`/administration/progression-pedagogique/imprimer/${currentAttributionId}`, '_blank');
                                }}
                            >
                                <Printer className="w-4 h-4 shrink-0 text-teal-600" />
                                Fiche d'Inspection PDF
                            </Button>
                        )}

                        <Button
                            className="bg-teal-600 hover:bg-teal-700 text-white gap-1.5 shadow-xs"
                            onClick={() => {
                                setEditingSeance(null);
                                setNouvelleSeanceModalOpen(true);
                            }}
                        >
                            <Plus className="w-4 h-4 shrink-0" />
                            Consigner une Séance
                        </Button>
                    </div>
                </div>

                {/* Indicateurs clés de progression — format sobre et compact harmonisé avec le tableau de bord */}
                <div className="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="border-border/80 shadow-2xs py-4 gap-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0 px-4">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Séances Consignées
                            </CardTitle>
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <BookOpen className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <div className="text-2xl font-bold tracking-tight tabular-nums text-foreground">
                                {stats.total_seances}
                            </div>
                            <div className="text-xs text-muted-foreground mt-1 flex items-center justify-between gap-2">
                                <span>{stats.total_visees} visées</span>
                                <span className="text-amber-600 font-medium">{stats.total_en_attente_visa} en attente</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs py-4 gap-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0 px-4">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Heures Dispensées
                            </CardTitle>
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                                <Clock className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <div className="text-2xl font-bold tracking-tight tabular-nums text-foreground">
                                {Number(stats.total_heures_effectuees).toFixed(1)} h
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Sur {Number(stats.total_heures_prevues).toFixed(1)} h programmées
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs py-4 gap-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0 px-4">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Avancement Programme
                            </CardTitle>
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <CheckCircle2 className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <div className="text-2xl font-bold tracking-tight tabular-nums text-emerald-600">
                                {stats.taux_realisation} %
                            </div>
                            <div className="w-full bg-muted h-1.5 rounded-full overflow-hidden mt-2">
                                <div
                                    className="bg-emerald-500 h-full rounded-full transition-all duration-500"
                                    style={{ width: `${Math.min(stats.taux_realisation, 100)}%` }}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs py-4 gap-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0 px-4">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Contrôle & Visas Censeur
                            </CardTitle>
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                <ShieldCheck className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <div className="text-2xl font-bold tracking-tight tabular-nums text-foreground">
                                {stats.total_visees} / {stats.total_seances}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.total_seances > 0
                                    ? `${Math.round((stats.total_visees / stats.total_seances) * 100)}% des cours visés`
                                    : 'Aucun cours consigné'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Barre de filtres de recherche */}
                <Card className="shadow-sm">
                    <CardContent className="p-4 sm:p-5 space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3">
                            {/* Recherche textuelle */}
                            <div className="space-y-1 sm:col-span-2 xl:col-span-2 min-w-0">
                                <label className="text-xs font-semibold text-slate-600">
                                    Recherche (Leçon, Chapitre...)
                                </label>
                                <div className="relative">
                                    <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                                    <Input
                                        placeholder="Titre de leçon, chapitre..."
                                        className="pl-9 h-9 text-xs"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleApplyFilters({ search })}
                                    />
                                </div>
                            </div>

                            {/* Classe */}
                            <div className="space-y-1 min-w-0">
                                <label className="text-xs font-semibold text-slate-600">
                                    Classe
                                </label>
                                <SearchableSelect
                                    options={[
                                        { value: 'all', label: 'Toutes les classes' },
                                        ...classes.map((cl) => ({
                                            value: String(cl.id),
                                            label: cl.nom,
                                        })),
                                    ]}
                                    value={selectedClasse}
                                    onValueChange={(val) => {
                                        const nextVal = val || 'all';
                                        setSelectedClasse(nextVal);
                                        handleApplyFilters({ classe_id: nextVal });
                                    }}
                                    placeholder="Toutes classes"
                                    searchPlaceholder="Rechercher une classe..."
                                    emptyMessage="Aucune classe trouvée."
                                    className="h-9 text-xs font-normal"
                                />
                            </div>

                            {/* Matière */}
                            <div className="space-y-1 min-w-0">
                                <label className="text-xs font-semibold text-slate-600">
                                    Discipline / Matière
                                </label>
                                <SearchableSelect
                                    options={[
                                        { value: 'all', label: 'Toutes les matières' },
                                        ...matieres.map((m) => ({
                                            value: String(m.id),
                                            label: m.nom,
                                        })),
                                    ]}
                                    value={selectedMatiere}
                                    onValueChange={(val) => {
                                        const nextVal = val || 'all';
                                        setSelectedMatiere(nextVal);
                                        handleApplyFilters({ matiere_id: nextVal });
                                    }}
                                    placeholder="Toutes matières"
                                    searchPlaceholder="Rechercher une matière..."
                                    emptyMessage="Aucune matière trouvée."
                                    className="h-9 text-xs font-normal"
                                />
                            </div>

                            {/* Statut */}
                            <div className="space-y-1 min-w-0">
                                <label className="text-xs font-semibold text-slate-600">
                                    État d'avancement
                                </label>
                                <Select
                                    value={selectedStatut}
                                    onValueChange={(val) => {
                                        setSelectedStatut(val);
                                        handleApplyFilters({ statut: val });
                                    }}
                                >
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder="Tous statuts" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tous statuts</SelectItem>
                                        <SelectItem value="fait">Fait (Achevée)</SelectItem>
                                        <SelectItem value="en_cours">En cours</SelectItem>
                                        <SelectItem value="reporte">Reportée</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Visa Censeur */}
                            <div className="space-y-1 min-w-0">
                                <label className="text-xs font-semibold text-slate-600">
                                    Contrôle Visa
                                </label>
                                <Select
                                    value={selectedVisa}
                                    onValueChange={(val) => {
                                        setSelectedVisa(val);
                                        handleApplyFilters({ visa: val });
                                    }}
                                >
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder="Tous visas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tous les cours</SelectItem>
                                        <SelectItem value="vise">Visés par censeur</SelectItem>
                                        <SelectItem value="en_attente">En attente de visa</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Bouton de réinitialisation si filtres actifs */}
                        {(selectedClasse !== 'all' || selectedMatiere !== 'all' || selectedPeriode !== 'all' || selectedStatut !== 'all' || selectedVisa !== 'all' || search) && (
                            <div className="flex justify-end pt-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-xs text-slate-500 hover:text-slate-800"
                                    onClick={() => {
                                        setSelectedClasse('all');
                                        setSelectedMatiere('all');
                                        setSelectedPeriode('all');
                                        setSelectedStatut('all');
                                        setSelectedVisa('all');
                                        setSearch('');
                                        router.get('/administration/progression-pedagogique');
                                    }}
                                >
                                    Réinitialiser tous les filtres
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Tableau TanStack Table */}
                <Card className="shadow-sm overflow-hidden min-w-0">
                    <div className="overflow-x-auto">
                        <Table className="min-w-205">
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id} className="bg-slate-50">
                                        {headerGroup.headers.map((header) => (
                                            <TableHead key={header.id} className="text-xs font-bold uppercase">
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(
                                                          header.column.columnDef.header,
                                                          header.getContext()
                                                      )}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows?.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <TableRow
                                            key={row.id}
                                            data-state={row.getIsSelected() && 'selected'}
                                            className="hover:bg-slate-50/70 transition-colors"
                                        >
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell key={cell.id} className="py-3">
                                                    {flexRender(
                                                        cell.column.columnDef.cell,
                                                        cell.getContext()
                                                    )}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={columns.length}
                                            className="h-32 text-center text-slate-500"
                                        >
                                            Aucune séance enregistrée dans le cahier de texte correspondant à ces critères.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Pagination Inertia avec retour à la ligne sur petits écrans */}
                    {fiches.last_page > 1 && (
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-2 px-4 py-3 border-t bg-slate-50 text-xs">
                            <span className="text-slate-500 text-center sm:text-left">
                                Affichage de {fiches.data.length} sur {fiches.total} séances consignées
                            </span>
                            <div className="flex flex-wrap items-center justify-center gap-1">
                                {fiches.links.map((link, idx) => (
                                    <Button
                                        key={idx}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        className={`h-7 px-2.5 text-xs ${link.active ? 'bg-teal-600 text-white' : ''}`}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            {/* Modals */}
            <NouvelleSeanceModal
                open={nouvelleSeanceModalOpen}
                onOpenChange={setNouvelleSeanceModalOpen}
                editingSeance={editingSeance}
                attributions={attributions}
                periodes={periodes}
                defaultAttributionId={currentAttributionId}
            />

            <VisaSeanceModal
                open={visaModalOpen}
                onOpenChange={setVisaModalOpen}
                seance={targetSeanceVisa}
            />
        </>
    );
}

/**
 * Configuration du layout persistant et des fils d'Ariane pour la page Cahier de Texte & Progression.
 * Conforme au système de layout automatique Inertia (app.tsx).
 */
ProgressionPedagogiqueIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Cahier de Texte & Progression',
            href: '/administration/progression-pedagogique',
        },
    ],
};

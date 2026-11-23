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
    Archive,
    Binary,
    Calendar,
    Copy,
    GraduationCap,
    Plus,
    RotateCcw,
    Search,
    Settings,
    Users,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    ArchiveAdministrative,
    ArchivagePageProps,
    ConfigurationNumerotation,
} from '@/types/archivage';
import { ArchiverModal } from './modals/archiver-modal';
import { ConfigurationNumerotationModal } from './modals/configuration-numerotation-modal';
import { DupliquerAnneeModal } from './modals/dupliquer-annee-modal';

const _breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administration', href: '/administration' },
    { title: 'Centre d\'Archivage & Reconduction', href: '/administration/archivage' },
];

/**
 * Centre d'Archivage Administratif, Masques de Numérotation & Reconduction Annuelle.
 * Conforme aux sections 3.15, 3.16, 3.27.8 et 3.28.3 du Manuel Aurore.
 */
export default function ArchivageIndex({
    archives,
    stats,
    filters,
    elevesActifs,
    personnelsActifs,
    configurationsNumerotation,
    annees,
}: ArchivagePageProps) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [search, setSearch] = useState(filters.search || '');
    const [typeEntite, setTypeEntite] = useState(filters.type_entite || 'all');
    const [selectedMotif, _setSelectedMotif] = useState(filters.motif || 'all');
    const [selectedStatut, setSelectedStatut] = useState(filters.statut || 'archive');

    // Modals
    const [archiverModalOpen, setArchiverModalOpen] = useState(false);
    const [dupliquerModalOpen, setDupliquerModalOpen] = useState(false);
    const [configModalOpen, setConfigModalOpen] = useState(false);
    const [selectedConfig, setSelectedConfig] = useState<ConfigurationNumerotation | null>(null);

    const handleApplyFilters = (newFilters?: Partial<typeof filters>) => {
        const queryParams: Record<string, string> = {};

        const te = newFilters?.type_entite !== undefined ? newFilters.type_entite : typeEntite;
        if (te && te !== 'all') queryParams.type_entite = te;

        const mo = newFilters?.motif !== undefined ? newFilters.motif : selectedMotif;
        if (mo && mo !== 'all') queryParams.motif = mo;

        const st = newFilters?.statut !== undefined ? newFilters.statut : selectedStatut;
        if (st && st !== 'all') queryParams.statut = st;

        const sc = newFilters?.search !== undefined ? newFilters.search : search;
        if (sc) queryParams.search = sc;

        router.get('/administration/archivage', queryParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleRestaurer = (archive: ArchiveAdministrative) => {
        if (confirm(`Confirmez-vous la réintégration / restauration du dossier de « ${archive.nom_complet} » ?`)) {
            router.post(`/administration/archivage/${archive.id}/restaurer`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Dossier de « ${archive.nom_complet} » réintégré avec succès`, {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white font-medium border-emerald-700 shadow-lg',
                    });
                },
            });
        }
    };

    // Colonnes TanStack Table pour les Archives
    const columns = useMemo<ColumnDef<ArchiveAdministrative>[]>(
        () => [
            {
                accessorKey: 'date_archivage',
                header: 'Date Archivage',
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-800">
                        <Calendar className="w-3.5 h-3.5 text-teal-600" />
                        {formatDate(row.original.date_archivage)}
                    </div>
                ),
            },
            {
                accessorKey: 'type_entite',
                header: 'Catégorie',
                cell: ({ row }) => {
                    const type = row.original.type_entite;
                    return type === 'eleve' ? (
                        <Badge className="bg-blue-100 text-blue-800 hover:bg-blue-100 border border-blue-200 gap-1">
                            <GraduationCap className="w-3 h-3" /> Élève
                        </Badge>
                    ) : (
                        <Badge className="bg-cyan-100 text-cyan-800 hover:bg-cyan-100 border border-cyan-200 gap-1">
                            <Users className="w-3 h-3" /> Personnel
                        </Badge>
                    );
                },
            },
            {
                accessorKey: 'nom_complet',
                header: 'Identité & Matricule',
                cell: ({ row }) => {
                    const item = row.original;
                    return (
                        <div className="flex flex-col">
                            <span className="font-bold text-slate-900 text-xs">
                                {item.nom_complet}
                            </span>
                            <span className="text-[11px] font-mono text-slate-500">
                                {item.matricule}
                            </span>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'derniere_classe_ou_fonction',
                header: 'Dernière Affectation',
                cell: ({ row }) => (
                    <span className="text-xs text-slate-700 font-medium">
                        {row.original.derniere_classe_ou_fonction ?? '---'}
                    </span>
                ),
            },
            {
                accessorKey: 'motif',
                header: 'Motif de Sortie',
                cell: ({ row }) => {
                    const m = row.original.motif;
                    return (
                        <div className="flex flex-col">
                            <span className="text-xs font-semibold text-slate-800">
                                {row.original.motif_libelle || m}
                            </span>
                            {row.original.destination && (
                                <span className="text-[11px] text-teal-600">
                                    Vers : {row.original.destination}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'statut',
                header: 'État Dossier',
                cell: ({ row }) => {
                    const st = row.original.statut;
                    return st === 'archive' ? (
                        <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100 border border-amber-300">
                            Archivé
                        </Badge>
                    ) : (
                        <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 border border-emerald-300">
                            Réintégré
                        </Badge>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                cell: ({ row }) => {
                    const item = row.original;
                    return (
                        <div className="flex items-center gap-1">
                            {item.statut === 'archive' && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    title="Réintégrer / Restaurer le dossier"
                                    className="h-8 gap-1 text-xs text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 font-semibold"
                                    onClick={() => handleRestaurer(item)}
                                >
                                    <RotateCcw className="w-3.5 h-3.5" /> Réintégrer
                                </Button>
                            )}
                        </div>
                    );
                },
            },
        ],
        []
    );

    const table = useReactTable({
        data: archives.data,
        columns,
        state: { sorting },
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        manualPagination: true,
    });

    const getDocumentTypeTitle = (type: string) => {
        switch (type) {
            case 'matricule_eleve':
                return 'Matricules Élèves';
            case 'matricule_personnel':
                return 'Matricules Personnel / Enseignants';
            case 'recu_scolarite':
                return 'Reçus de Paiement Scolarité';
            case 'recu_divers':
                return 'Reçus Recettes Hors Scolarité';
            case 'certificat_scolarite':
                return 'Certificats de Scolarité';
            case 'certificat_radiation':
                return 'Certificats de Radiation';
            default:
                return type;
        }
    };

    return (
        <>
            <Head title="Centre d'Archivage & Reconduction Annuelle" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="p-4 sm:p-6 lg:p-8 space-y-6 w-full min-w-0">
                {/* En-tête */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <Archive className="w-6 h-6 sm:w-7 sm:h-7 text-teal-600 shrink-0" />
                            <span>Centre d'Archivage Administratif & Rentrée</span>
                        </h1>
                        <p className="text-xs sm:text-sm text-muted-foreground mt-1">
                            Traçabilité légale des départs d'élèves/personnel, masques de numérotation séquentiels et assistant de duplication annuelle.
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto">
                        <Button
                            variant="outline"
                            className="w-full sm:w-auto justify-center gap-1.5 border-teal-200 text-teal-700 hover:bg-teal-50"
                            onClick={() => setDupliquerModalOpen(true)}
                        >
                            <Copy className="w-4 h-4 text-teal-600" />
                            <span>Reconduction Annuelle 1-Clic</span>
                        </Button>

                        <Button
                            className="w-full sm:w-auto justify-center bg-teal-600 hover:bg-teal-700 text-white gap-1.5 shadow-xs"
                            onClick={() => setArchiverModalOpen(true)}
                        >
                            <Plus className="w-4 h-4" />
                            <span>Archiver un Dossier</span>
                        </Button>
                    </div>
                </div>

                {/* Indicateurs clés d'archivage — format sobre et compact harmonisé avec le tableau de bord */}
                <div className="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="border-border/80 shadow-2xs py-4 gap-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0 px-4">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Dossiers Archivés
                            </CardTitle>
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                                <Archive className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <div className="text-2xl font-bold tracking-tight tabular-nums text-foreground">
                                {stats.total_archives}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Archives historiques sécurisées
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs py-4 gap-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0 px-4">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Élèves Archivés
                            </CardTitle>
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <GraduationCap className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <div className="text-2xl font-bold tracking-tight tabular-nums text-blue-600">
                                {stats.total_eleves}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Diplômés, transférés ou exclus
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs py-4 gap-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0 px-4">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Personnels Archivés
                            </CardTitle>
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                                <Users className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <div className="text-2xl font-bold tracking-tight tabular-nums text-sky-600">
                                {stats.total_personnels}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Retraités, démissionnaires, fins de contrat
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs py-4 gap-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-0 px-4">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Masques Numérotation
                            </CardTitle>
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                <Binary className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent className="px-4">
                            <div className="text-2xl font-bold tracking-tight tabular-nums text-foreground">
                                {configurationsNumerotation.length} Types
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Préfixes et souches configurés
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Onglets : Registre d'Archives vs Masques de numérotation */}
                <Tabs defaultValue="registre" className="space-y-4">
                    <TabsList className="flex flex-wrap h-auto gap-1 bg-slate-100 p-1">
                        <TabsTrigger value="registre" className="gap-2 text-xs">
                            <Archive className="w-4 h-4" />
                            Registre des Archives Administratives
                        </TabsTrigger>
                        <TabsTrigger value="numerotation" className="gap-2 text-xs">
                            <Binary className="w-4 h-4" />
                            Masques & Souches de Numérotation
                        </TabsTrigger>
                    </TabsList>

                    {/* ONGLET 1 : REGISTRE DES ARCHIVES */}
                    <TabsContent value="registre" className="space-y-4">
                        {/* Filtres de recherche */}
                        <Card className="shadow-sm">
                            <CardContent className="pt-5 space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                                    <div className="space-y-1 sm:col-span-2">
                                        <label className="text-xs font-semibold text-slate-600">
                                            Recherche (Nom, Matricule, Destination...)
                                        </label>
                                        <div className="relative">
                                            <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                                            <Input
                                                placeholder="Rechercher une archive..."
                                                className="pl-9 h-9 text-xs"
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                onKeyDown={(e) => e.key === 'Enter' && handleApplyFilters({ search })}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-slate-600">
                                            Catégorie
                                        </label>
                                        <Select
                                            value={typeEntite}
                                            onValueChange={(val) => {
                                                setTypeEntite(val);
                                                handleApplyFilters({ type_entite: val });
                                            }}
                                        >
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue placeholder="Toutes catégories" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Élèves & Personnels</SelectItem>
                                                <SelectItem value="eleve">Élèves uniquement</SelectItem>
                                                <SelectItem value="personnel">Personnel uniquement</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-slate-600">
                                            État de l'archive
                                        </label>
                                        <Select
                                            value={selectedStatut}
                                            onValueChange={(val) => {
                                                setSelectedStatut(val);
                                                handleApplyFilters({ statut: val });
                                            }}
                                        >
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Tous états</SelectItem>
                                                <SelectItem value="archive">Archivés uniquement</SelectItem>
                                                <SelectItem value="restaure">Réintégrés / Restaurés</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* TanStack Table des Archives */}
                        <Card className="shadow-sm overflow-hidden">
                            <div className="overflow-x-auto">
                                <Table>
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
                                                    Aucun dossier archivé trouvé.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Pagination */}
                            {archives.last_page > 1 && (
                                <div className="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t bg-slate-50 text-xs">
                                    <span className="text-slate-500 text-center sm:text-left">
                                        Affichage de {archives.data.length} sur {archives.total} dossiers
                                    </span>
                                    <div className="flex flex-wrap items-center justify-center gap-1">
                                        {archives.links.map((link, idx) => (
                                            <Button
                                                key={idx}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                disabled={!link.url}
                                                className={`h-7 px-2.5 text-xs ${link.active ? 'bg-teal-600 text-white hover:bg-teal-700' : ''}`}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </Card>
                    </TabsContent>

                    {/* ONGLET 2 : MASQUES DE NUMÉROTATION */}
                    <TabsContent value="numerotation" className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {configurationsNumerotation.map((config) => {
                                const currentYear = new Date().getFullYear();
                                let preview = config.format_masque.replace('{PREFIX}', config.prefixe);
                                preview = preview.replace('{ANNEE}', String(currentYear));
                                if (/\{NUM:(\d+)\}/.test(preview)) {
                                    preview = preview.replace(/\{NUM:(\d+)\}/, (_, p1) =>
                                        String(config.compteur_actuel).padStart(parseInt(p1, 10), '0')
                                    );
                                } else {
                                    preview = preview.replace('{NUM}', String(config.compteur_actuel));
                                }

                                return (
                                    <Card key={config.id} className="shadow-sm border hover:border-teal-300 transition-all">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <CardTitle className="text-sm font-bold text-slate-900">
                                                        {getDocumentTypeTitle(config.type_document)}
                                                    </CardTitle>
                                                    <CardDescription className="text-xs mt-0.5">
                                                        Masque : <code className="text-teal-600 font-mono">{config.format_masque}</code>
                                                    </CardDescription>
                                                </div>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 w-7 p-0"
                                                    onClick={() => {
                                                        setSelectedConfig(config);
                                                        setConfigModalOpen(true);
                                                    }}
                                                >
                                                    <Settings className="w-3.5 h-3.5" />
                                                </Button>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-2">
                                            <div className="rounded-md bg-slate-50 p-2.5 text-center border">
                                                <div className="text-[10px] uppercase font-bold text-slate-500">
                                                    Prochain Numéro
                                                </div>
                                                <div className="text-base font-black font-mono tracking-wider text-teal-700 mt-0.5">
                                                    {preview}
                                                </div>
                                            </div>
                                            <div className="flex justify-between text-xs text-slate-500 pt-1">
                                                <span>Préfixe : <strong>{config.prefixe}</strong></span>
                                                <span>Compteur : <strong>#{config.compteur_actuel}</strong></span>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Modals */}
            <ArchiverModal
                open={archiverModalOpen}
                onOpenChange={setArchiverModalOpen}
                elevesActifs={elevesActifs}
                personnelsActifs={personnelsActifs}
            />

            <ConfigurationNumerotationModal
                open={configModalOpen}
                onOpenChange={setConfigModalOpen}
                configuration={selectedConfig}
            />

            <DupliquerAnneeModal
                open={dupliquerModalOpen}
                onOpenChange={setDupliquerModalOpen}
                annees={annees}
            />
        </>
    );
}

/**
 * Configuration du layout persistant et des fils d'Ariane pour la page Centre d'Archivage & Reconduction.
 * Conforme au système de layout automatique Inertia (app.tsx).
 */
ArchivageIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Centre d\'Archivage & Reconduction',
            href: '/administration/archivage',
        },
    ],
};

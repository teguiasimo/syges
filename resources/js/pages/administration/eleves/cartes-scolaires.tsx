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
    CreditCard,
    Printer,
    Search,
    Filter,
    School,
    CheckCircle2,
    AlertCircle,
    RotateCw,
    Users,
    Sparkles,
    FileDown,
    ArrowLeft,
    Check,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Camera,
    Eye,
    QrCode,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { toast } from 'sonner';
import { formatDate } from '@/lib/utils';

import type { Inscription, EtablissementRef, AnneeScolaireRef } from '@/types/eleve';

/**
 * Propriétés reçues par la page des Cartes Scolaires depuis CarteScolaireController.
 */
interface CartesScolairesPageProps {
    inscriptions: Inscription[];
    etablissements: EtablissementRef[];
    selectedEtabId: number;
    selectedAnneeId: number;
    anneesScolaires: AnneeScolaireRef[];
    stats: {
        total_inscrits: number;
        total_avec_photo: number;
        total_sans_photo: number;
        taux_couverture_photo: number;
    };
    filters: {
        etablissement_id?: string;
        classe_id?: string;
        has_photo?: string;
        search?: string;
    };
}

/**
 * Page de gestion, prévisualisation interactive 3D et impression en planche des Cartes Scolaires (Phase 2).
 * 
 * Conforme aux normes officielles camerounaises et au spécimen du Collège Bilingue St Abraham de Foumban.
 * Implémente TanStack Table, filtres hiérarchiques et générateur PDF de planches A4 massicotables.
 */
export default function CartesScolairesIndex({
    inscriptions,
    etablissements,
    selectedEtabId,
    selectedAnneeId,
    anneesScolaires,
    stats,
    filters,
}: CartesScolairesPageProps) {
    // État du tri pour TanStack Table
    const [sorting, setSorting] = useState<SortingState>([]);
    // État de la sélection des lignes (clé: inscription_id)
    const [rowSelection, setRowSelection] = useState<Record<string, boolean>>({});
    // Élève actif pour la prévisualisation 3D interactive
    const [previewInscription, setPreviewInscription] = useState<Inscription | null>(
        inscriptions.length > 0 ? inscriptions[0] : null
    );
    // Face affichée sur le prévisualisateur 3D ('recto' ou 'verso')
    const [cardSide, setCardSide] = useState<'recto' | 'verso'>('recto');

    // Établissement actif sélectionné
    const activeEtablissement = useMemo(() => {
        return etablissements.find((e) => e.id === selectedEtabId) || etablissements[0];
    }, [etablissements, selectedEtabId]);

    // Liste aplatie de toutes les classes de l'établissement actif pour le filtre
    const classesList = useMemo(() => {
        const list: { id: number; nom: string; niveauNom?: string }[] = [];
        activeEtablissement?.sections?.forEach((s) => {
            s.cycles?.forEach((c) => {
                c.niveaux?.forEach((n) => {
                    n.classes?.forEach((cls) => {
                        list.push({
                            id: cls.id,
                            nom: cls.nom,
                            niveauNom: n.nom,
                        });
                    });
                });
            });
        });
        return list;
    }, [activeEtablissement]);

    /**
     * Applique les filtres en rechargeant la page via Inertia.
     */
    const handleFilterChange = (newFilters: Partial<typeof filters>) => {
        router.get(
            '/administration/eleves/cartes-scolaires',
            {
                ...filters,
                ...newFilters,
                annee_scolaire_id: selectedAnneeId,
                etablissement_id: selectedEtabId,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    /**
     * Identifiants des inscriptions actuellement cochées dans le tableau TanStack.
     */
    const selectedIds = useMemo(() => {
        return Object.keys(rowSelection)
            .filter((key) => rowSelection[key])
            .map((key) => Number(key));
    }, [rowSelection]);

    /**
     * Lance l'impression en masse des planches A4 (Recto, Verso ou Alterné) via formulaire POST dynamique.
     *
     * @param face Mode d'impression ('recto', 'verso', 'recto_verso')
     */
    const handlePrintPlanche = (face: 'recto' | 'verso' | 'recto_verso') => {
        const idsToPrint = selectedIds.length > 0 ? selectedIds : inscriptions.map((i) => i.id);

        if (idsToPrint.length === 0) {
            toast.error('Aucun élève disponible pour l’impression des cartes scolaires.', {
                position: 'top-right',
            });
            return;
        }

        // Création d'un formulaire temporaire pour ouvrir le flux PDF DomPDF dans un nouvel onglet
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/administration/eleves/cartes-scolaires/imprimer-planche';
        form.target = '_blank';

        // Token CSRF Laravel
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }

        const faceInput = document.createElement('input');
        faceInput.type = 'hidden';
        faceInput.name = 'face';
        faceInput.value = face;
        form.appendChild(faceInput);

        idsToPrint.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'inscription_ids[]';
            input.value = String(id);
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        toast.success(`Planche A4 (${face}) générée pour ${idsToPrint.length} élève(s).`, {
            position: 'top-right',
        });
    };

    /**
     * Définition des colonnes du tableau TanStack Table.
     */
    const columns = useMemo<ColumnDef<Inscription>[]>(
        () => [
            {
                id: 'select',
                header: ({ table }) => (
                    <Checkbox
                        checked={
                            table.getIsAllPageRowsSelected() ||
                            (table.getIsSomePageRowsSelected() && 'indeterminate')
                        }
                        onCheckedChange={(val) => table.toggleAllPageRowsSelected(!!val)}
                        aria-label="Tout sélectionner"
                        className="translate-y-0.5"
                    />
                ),
                cell: ({ row }) => (
                    <Checkbox
                        checked={row.getIsSelected()}
                        onCheckedChange={(val) => row.toggleSelected(!!val)}
                        aria-label="Sélectionner la ligne"
                        className="translate-y-0.5"
                    />
                ),
                enableSorting: false,
                enableHiding: false,
            },
            {
                id: 'photo',
                header: 'Photo',
                cell: ({ row }) => {
                    const eleve = row.original.eleve;
                    return (
                        <div className="size-10 rounded-md border border-slate-200 dark:border-slate-800 overflow-hidden bg-slate-100 dark:bg-slate-900 flex items-center justify-center shrink-0">
                            {eleve.photo_url ? (
                                <img
                                    src={`/${eleve.photo_url}`}
                                    alt={eleve.nom}
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <Camera className="size-4 text-slate-400" />
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'eleve.matricule',
                header: 'Matricule',
                cell: ({ row }) => (
                    <span className="font-mono text-xs font-bold text-rose-600 dark:text-rose-400">
                        {row.original.eleve.matricule}
                    </span>
                ),
            },
            {
                accessorKey: 'eleve.nom',
                header: 'Nom & Prénoms',
                cell: ({ row }) => {
                    const e = row.original.eleve;
                    return (
                        <div>
                            <div className="font-semibold text-foreground leading-snug">
                                {e.nom} {e.prenom || ''}
                            </div>
                            <div className="text-[11px] text-muted-foreground">
                                Né(e) le {formatDate(e.date_naissance)} {e.lieu_naissance ? `à ${e.lieu_naissance}` : ''}
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'classe.nom',
                header: 'Classe',
                cell: ({ row }) => (
                    <Badge variant="outline" className="font-semibold bg-blue-50/50 dark:bg-blue-950/20 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-900">
                        {row.original.classe?.nom || 'N/A'}
                    </Badge>
                ),
            },
            {
                id: 'status_photo',
                header: 'Photo',
                cell: ({ row }) => {
                    const hasPhoto = !emptyCheck(row.original.eleve.photo_url);
                    return hasPhoto ? (
                        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 className="size-3.5" />
                            Prête
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                            <AlertCircle className="size-3.5" />
                            Manquante
                        </span>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                cell: ({ row }) => {
                    const insc = row.original;
                    const isSelected = previewInscription?.id === insc.id;
                    return (
                        <div className="flex items-center gap-1.5">
                            <Button
                                size="sm"
                                variant={isSelected ? 'default' : 'outline'}
                                className="h-7 text-xs gap-1"
                                onClick={() => setPreviewInscription(insc)}
                            >
                                <Eye className="size-3.5" />
                                <span>Aperçu</span>
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                className="h-7 text-xs text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40"
                                asChild
                            >
                                <a
                                    href={`/administration/eleves/${insc.id}/carte-scolaire-pdf`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileDown className="size-3.5 mr-1" />
                                    PDF
                                </a>
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [previewInscription]
    );

    /**
     * Initialisation de l'instance TanStack Table.
     */
    const table = useReactTable({
        data: inscriptions,
        columns,
        state: {
            sorting,
            rowSelection,
        },
        onSortingChange: setSorting,
        onRowSelectionChange: setRowSelection,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getRowId: (row) => String(row.id),
        initialState: {
            pagination: {
                pageSize: 10,
            },
        },
    });

    return (
        <>
            <Head title="Générateur de Cartes Scolaires & Badges" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8">
                {/* En-tête de page & navigation */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Button variant="ghost" size="sm" asChild className="h-8 px-2 gap-1.5 text-xs text-muted-foreground hover:text-foreground">
                                <Link href="/administration/eleves">
                                    <ArrowLeft className="size-3.5" />
                                    Retour à la Base Élèves
                                </Link>
                            </Button>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2.5 rounded-xl bg-linear-to-br from-blue-600 to-indigo-700 text-white shadow-md shadow-blue-500/20">
                                <CreditCard className="size-6" />
                            </div>
                            <div>
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                                    Cartes d'Identité Scolaires
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    Badges officiels bilingues avec photos numériques, QR code de contrôle et planches A4 prêtes pour massicot.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Actions d'impression globales */}
                    <div className="flex items-center gap-2 flex-wrap">
                        <Button
                            variant="outline"
                            onClick={() => handlePrintPlanche('recto')}
                            className="gap-2 h-9 text-xs font-semibold border-sky-300 text-sky-700 dark:border-sky-800 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-950/50"
                        >
                            <Printer className="size-4" />
                            <span>Imprimer Planche Recto (A4)</span>
                        </Button>

                        <Button
                            variant="outline"
                            onClick={() => handlePrintPlanche('verso')}
                            className="gap-2 h-9 text-xs font-semibold border-indigo-300 text-indigo-700 dark:border-indigo-800 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50"
                        >
                            <QrCode className="size-4" />
                            <span>Imprimer Planche Verso (A4)</span>
                        </Button>

                        <Button
                            onClick={() => handlePrintPlanche('recto_verso')}
                            className="gap-2 h-9 text-xs font-semibold bg-linear-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-sm"
                        >
                            <Sparkles className="size-4" />
                            <span>Recto & Verso Alternés</span>
                        </Button>
                    </div>
                </div>

                {/* 4 Cartes Supérieures KPI de Couverture */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Élèves Inscrits</p>
                                <p className="text-2xl font-bold text-foreground mt-1">{stats.total_inscrits}</p>
                                <p className="text-[11px] text-muted-foreground mt-0.5">Année active</p>
                            </div>
                            <div className="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
                                <Users className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Avec Photo Numérique</p>
                                <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                                    {stats.total_avec_photo}
                                </p>
                                <p className="text-[11px] text-emerald-600/80 font-medium mt-0.5">Badges prêts</p>
                            </div>
                            <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                                <CheckCircle2 className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Sans Photo</p>
                                <p className="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                                    {stats.total_sans_photo}
                                </p>
                                <p className="text-[11px] text-amber-600/80 font-medium mt-0.5">À compléter</p>
                            </div>
                            <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                                <AlertCircle className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div className="flex-1 mr-3">
                                <p className="text-xs font-medium text-muted-foreground">Taux de Couverture</p>
                                <p className="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                                    {stats.taux_couverture_photo}%
                                </p>
                                <div className="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                    <div
                                        className="bg-indigo-600 h-full rounded-full transition-all duration-500"
                                        style={{ width: `${stats.taux_couverture_photo}%` }}
                                    />
                                </div>
                            </div>
                            <div className="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 shrink-0">
                                <Camera className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Grille Principale : Tableau à Gauche + Prévisualisation 3D à Droite */}
                <div className="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                    {/* Colonne Tableau TanStack (7 / 12) */}
                    <div className="xl:col-span-7 flex flex-col gap-4">
                        {/* Barre des Filtres */}
                        <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                            <CardContent className="p-4 flex flex-col md:flex-row gap-3 items-center">
                                {/* Sélecteur de Classe */}
                                <div className="w-full md:w-52">
                                    <select
                                        value={filters.classe_id || ''}
                                        onChange={(e) => handleFilterChange({ classe_id: e.target.value })}
                                        className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="">Toutes les classes</option>
                                        {classesList.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.nom} {c.niveauNom ? `(${c.niveauNom})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* Filtre Photo */}
                                <div className="w-full md:w-44">
                                    <select
                                        value={filters.has_photo || 'all'}
                                        onChange={(e) => handleFilterChange({ has_photo: e.target.value })}
                                        className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="all">Toutes les photos</option>
                                        <option value="with_photo">Avec photo uniquement</option>
                                        <option value="without_photo">Sans photo uniquement</option>
                                    </select>
                                </div>

                                {/* Recherche Textuelle */}
                                <div className="w-full md:flex-1 relative">
                                    <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Rechercher par nom, matricule..."
                                        value={filters.search || ''}
                                        onChange={(e) => handleFilterChange({ search: e.target.value })}
                                        className="pl-8 h-9 text-xs"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Bandeau d'Actions groupées lorsque des lignes sont cochées */}
                        {selectedIds.length > 0 && (
                            <div className="flex items-center justify-between p-3 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 text-blue-950 dark:text-blue-200">
                                <span className="text-xs font-semibold flex items-center gap-2">
                                    <Check className="size-4 text-blue-600" />
                                    {selectedIds.length} élève(s) sélectionné(s) sur cette page
                                </span>
                                <div className="flex items-center gap-2">
                                    <Button
                                        size="sm"
                                        variant="default"
                                        className="h-7 text-xs bg-blue-600 hover:bg-blue-700 gap-1.5"
                                        onClick={() => handlePrintPlanche('recto')}
                                    >
                                        <Printer className="size-3.5" />
                                        Imprimer Planche Sélectionnée
                                    </Button>
                                </div>
                            </div>
                        )}

                        {/* Tableau TanStack Table */}
                        <Card className="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse text-xs">
                                    <thead>
                                        {table.getHeaderGroups().map((headerGroup) => (
                                            <tr key={headerGroup.id} className="border-b border-border bg-muted/40 font-semibold text-muted-foreground">
                                                {headerGroup.headers.map((header) => (
                                                    <th key={header.id} className="px-3 py-2.5">
                                                        {header.isPlaceholder
                                                            ? null
                                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                                    </th>
                                                ))}
                                            </tr>
                                        ))}
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {table.getRowModel().rows.length > 0 ? (
                                            table.getRowModel().rows.map((row) => (
                                                <tr
                                                    key={row.id}
                                                    className={`hover:bg-muted/30 transition-colors cursor-pointer ${
                                                        previewInscription?.id === row.original.id ? 'bg-blue-50/60 dark:bg-blue-950/30' : ''
                                                    }`}
                                                    onClick={() => setPreviewInscription(row.original)}
                                                >
                                                    {row.getVisibleCells().map((cell) => (
                                                        <td key={cell.id} className="px-3 py-2">
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={columns.length} className="h-32 text-center text-muted-foreground">
                                                    Aucun élève ne correspond aux critères de filtre sélectionnés.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination TanStack */}
                            <div className="flex items-center justify-between p-3 border-t border-border bg-card">
                                <div className="text-xs text-muted-foreground">
                                    {table.getFilteredSelectedRowModel().rows.length} sur{' '}
                                    {table.getFilteredRowModel().rows.length} ligne(s) sélectionnée(s)
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => table.setPageIndex(0)}
                                        disabled={!table.getCanPreviousPage()}
                                        className="size-7 p-0"
                                    >
                                        <ChevronsLeft className="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => table.previousPage()}
                                        disabled={!table.getCanPreviousPage()}
                                        className="size-7 p-0"
                                    >
                                        <ChevronLeft className="size-3.5" />
                                    </Button>
                                    <span className="text-xs px-2 font-medium">
                                        Page {table.getState().pagination.pageIndex + 1} sur {table.getPageCount() || 1}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => table.nextPage()}
                                        disabled={!table.getCanNextPage()}
                                        className="size-7 p-0"
                                    >
                                        <ChevronRight className="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => table.setPageIndex(table.getPageCount() - 1)}
                                        disabled={!table.getCanNextPage()}
                                        className="size-7 p-0"
                                    >
                                        <ChevronsRight className="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    </div>

                    {/* Colonne Prévisualisation 3D Interactive (5 / 12) */}
                    <div className="xl:col-span-5 sticky top-6 flex flex-col gap-4">
                        <Card className="border-slate-200 dark:border-slate-800 shadow-md overflow-hidden bg-slate-50/50 dark:bg-slate-900/30">
                            <CardHeader className="p-4 pb-2 border-b border-border/60">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="text-base font-bold text-foreground flex items-center gap-2">
                                            <Sparkles className="size-4 text-blue-600" />
                                            Aperçu Dynamique HD
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Spécimen officiel conforme Collège St Abraham de Foumban
                                        </CardDescription>
                                    </div>

                                    {/* Bouton de bascule Recto / Verso */}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="h-8 gap-1.5 text-xs font-semibold shadow-sm"
                                        onClick={() => setCardSide(cardSide === 'recto' ? 'verso' : 'recto')}
                                    >
                                        <RotateCw className="size-3.5 text-blue-600" />
                                        <span>Afficher {cardSide === 'recto' ? 'le Verso' : 'le Recto'}</span>
                                    </Button>
                                </div>
                            </CardHeader>

                            <CardContent className="p-6 flex flex-col items-center justify-center">
                                {/* Carte Interactive avec Dimensions Ratio Exact 85.6 x 54 mm */}
                                <div
                                    className="w-85 h-53.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 shadow-xl overflow-hidden relative cursor-pointer select-none transition-all duration-300 hover:scale-[1.02]"
                                    onClick={() => setCardSide(cardSide === 'recto' ? 'verso' : 'recto')}
                                    title="Cliquez sur la carte pour la retourner"
                                >
                                    {cardSide === 'recto' ? (
                                        /* RECTO */
                                        <div className="p-2.5 h-full flex flex-col justify-between text-[7px] leading-tight">
                                            {/* En-tête Bilingue Officiel */}
                                            <div className="flex justify-between items-center text-[5.5px] leading-tight text-slate-800 font-bold border-b pb-1">
                                                <div className="w-[38%] text-left">
                                                    <div>RÉPUBLIQUE DU CAMEROUN</div>
                                                    <div className="font-normal italic text-[4.8px] text-slate-500">Paix – Travail – Patrie</div>
                                                    <div className="truncate">DÉLÉGATION DU CENTRE</div>
                                                </div>
                                                <div className="w-[20%] text-center">
                                                    {(activeEtablissement?.effective_logo || activeEtablissement?.logo) ? (
                                                        <img
                                                            src={
                                                                (activeEtablissement.effective_logo || activeEtablissement.logo)!.startsWith('/')
                                                                    ? (activeEtablissement.effective_logo || activeEtablissement.logo)!
                                                                    : `/${activeEtablissement.effective_logo || activeEtablissement.logo}`
                                                            }
                                                            alt="Logo"
                                                            className="size-6 object-contain mx-auto"
                                                        />
                                                    ) : (
                                                        <div className="size-6 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center mx-auto text-[8px]">
                                                            ★
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="w-[38%] text-right">
                                                    <div>REPUBLIC OF CAMEROON</div>
                                                    <div className="font-normal italic text-[4.8px] text-slate-500">Peace – Work – Fatherland</div>
                                                    <div className="truncate">REGIONAL DELEGATION</div>
                                                </div>
                                            </div>

                                            {/* Nom de l'école */}
                                            <div className="text-center font-extrabold text-[8.5px] text-blue-700 uppercase tracking-tight truncate my-0.5">
                                                {activeEtablissement?.nom || 'COLLÈGE BILINGUE ST ABRAHAM'}
                                            </div>

                                            {/* Bandeau Titre Tricolore */}
                                            <div className="flex items-center h-4 rounded bg-sky-100 border border-sky-300 overflow-hidden my-0.5">
                                                <div className="flex w-6 h-full text-[6px] text-yellow-300 font-bold items-center justify-center">
                                                    <div className="w-1/3 h-full bg-emerald-600" />
                                                    <div className="w-1/3 h-full bg-red-600 flex items-center justify-center">★</div>
                                                    <div className="w-1/3 h-full bg-yellow-400" />
                                                </div>
                                                <div className="flex-1 text-center font-black text-[7.5px] text-sky-900 tracking-wider">
                                                    CARTE D'IDENTITE SCOLAIRE
                                                </div>
                                            </div>

                                            {/* Année Scolaire */}
                                            <div className="text-center font-bold text-red-600 text-[7px]">
                                                Année Scolaire {previewInscription?.annee_scolaire?.nom || '2025/2026'}
                                            </div>

                                            {/* Données de l'élève & Photo */}
                                            <div className="flex gap-2 items-start mt-0.5">
                                                {/* Photo */}
                                                <div className="w-15.5 h-19.5 rounded border border-slate-300 bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center shadow-inner">
                                                    {previewInscription?.eleve?.photo_url ? (
                                                        <img
                                                            src={`/${previewInscription.eleve.photo_url}`}
                                                            alt="Élève"
                                                            className="w-full h-full object-cover"
                                                        />
                                                    ) : (
                                                        <span className="text-[7px] text-slate-400 font-bold">PHOTO</span>
                                                    )}
                                                </div>

                                                {/* Données textuelles */}
                                                <div className="flex-1 text-[6.5px] space-y-0.5">
                                                    <div className="truncate">
                                                        <span className="font-bold text-slate-600">Matricule : </span>
                                                        <span className="font-mono font-bold text-red-600">
                                                            {previewInscription?.eleve?.matricule || '250111417'}
                                                        </span>
                                                    </div>
                                                    <div className="truncate">
                                                        <span className="font-bold text-slate-600">Nom : </span>
                                                        <span className="font-bold text-blue-900">
                                                            {previewInscription?.eleve?.nom || 'NIEN NTIENGEM'}{' '}
                                                            {previewInscription?.eleve?.prenom || 'OUTJA JASMIN'}
                                                        </span>
                                                    </div>
                                                    <div className="truncate">
                                                        <span className="font-bold text-slate-600">Né(e) le : </span>
                                                        <span>
                                                            {previewInscription?.eleve?.date_naissance
                                                                ? formatDate(previewInscription.eleve.date_naissance)
                                                                : '16/05/2014'}{' '}
                                                            A {previewInscription?.eleve?.lieu_naissance || 'FOUMBAN'}
                                                        </span>
                                                    </div>
                                                    <div className="truncate">
                                                        <span className="font-bold text-slate-600">Classe : </span>
                                                        <span className="font-bold text-red-700">
                                                            {previewInscription?.classe?.nom || '6 EME'}
                                                        </span>
                                                    </div>
                                                    <div className="truncate">
                                                        <span className="font-bold text-slate-600">Direction : </span>
                                                        <span className="text-emerald-700 font-bold">
                                                            {activeEtablissement?.telephone_1 || '673980799'}
                                                        </span>
                                                    </div>
                                                    <div className="truncate">
                                                        <span className="font-bold text-slate-600">Sexe : </span>
                                                        <span className="font-bold text-red-600">
                                                            {previewInscription?.eleve?.sexe || 'F'}
                                                        </span>
                                                    </div>
                                                </div>

                                                {/* Cachet & Signature */}
                                                <div className="w-14 text-center shrink-0 flex flex-col items-center justify-center">
                                                    <div className="size-11 rounded-full border border-dashed border-indigo-400 bg-indigo-50/60 flex items-center justify-center text-[4.5px] font-bold text-indigo-700 uppercase leading-tight p-0.5">
                                                        CACHET
                                                        <br />
                                                        DIRECTION
                                                    </div>
                                                    <span className="text-[5px] font-bold text-blue-900 mt-0.5 truncate max-w-full">
                                                        {activeEtablissement?.nom_chef_etablissement || 'Le Principal'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        /* VERSO */
                                        <div className="p-3 h-full flex flex-col justify-between text-center relative bg-slate-50 dark:bg-slate-900/50">
                                            <div>
                                                <div className="font-extrabold text-[8.5px] text-blue-700 uppercase truncate">
                                                    {activeEtablissement?.nom || 'COLLÈGE BILINGUE CATHOLIQUE SAINT ABRAHAM'}
                                                </div>
                                                <div className="text-[6.5px] font-bold text-slate-700 mt-0.5">
                                                    Adresse : {activeEtablissement?.adresse || 'FOUMBAN'} | Tél :{' '}
                                                    {activeEtablissement?.telephone_1 || '673980799'}
                                                </div>
                                            </div>

                                            {/* Logo central & QR Code */}
                                            <div className="flex items-center justify-around my-2">
                                                <div className="size-14 flex items-center justify-center">
                                                    {(activeEtablissement?.effective_logo || activeEtablissement?.logo) ? (
                                                        <img
                                                            src={
                                                                (activeEtablissement.effective_logo || activeEtablissement.logo)!.startsWith('/')
                                                                    ? (activeEtablissement.effective_logo || activeEtablissement.logo)!
                                                                    : `/${activeEtablissement.effective_logo || activeEtablissement.logo}`
                                                            }
                                                            alt="Logo"
                                                            className="size-12 object-contain opacity-80"
                                                        />
                                                    ) : (
                                                        <div className="size-12 rounded-full border border-slate-300 flex items-center justify-center text-slate-400 font-bold text-xl">
                                                            ★
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="flex flex-col items-center">
                                                    <div className="size-14 p-1 rounded bg-white border border-slate-300 flex items-center justify-center shadow-sm">
                                                        <QrCode className="size-12 text-slate-900" />
                                                    </div>
                                                    <span className="text-[5px] font-bold text-slate-500 uppercase mt-0.5">
                                                        Contrôle Sécurité
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="text-[5.5px] text-slate-500 leading-tight px-2">
                                                Cette carte est strictement personnelle et obligatoire dans l'enceinte de
                                                l'établissement. En cas de perte, prière de la rapporter à la Direction.
                                            </div>

                                            {/* Devise en bas */}
                                            <div className="absolute bottom-0 left-0 right-0 h-5 bg-sky-600 text-white font-bold text-[7px] flex items-center justify-center tracking-wider">
                                                Respect – Travail – Réussite
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <p className="text-[11px] text-muted-foreground mt-3 flex items-center gap-1">
                                    <RotateCw className="size-3 text-blue-600" />
                                    Cliquez sur le badge pour basculer Recto / Verso
                                </p>

                                {/* Action Unitaire pour cet élève */}
                                {previewInscription && (
                                    <div className="mt-4 w-full flex items-center justify-center">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            className="h-8 gap-1.5 text-xs w-full max-w-70"
                                            asChild
                                        >
                                            <a
                                                href={`/administration/eleves/${previewInscription.id}/carte-scolaire-pdf`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileDown className="size-3.5" />
                                                Télécharger le badge CR80 unitaire (PDF)
                                            </a>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * Utilitaire pour vérifier si une chaîne ou valeur est vide.
 */
function emptyCheck(val: string | null | undefined): boolean {
    return !val || val.trim() === '';
}

/**
 * Configuration du layout applicatif et des fils d'Ariane.
 */
CartesScolairesIndex.layout = {
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
            title: 'Cartes Scolaires & Badges',
            href: '/administration/eleves/cartes-scolaires',
        },
    ],
};

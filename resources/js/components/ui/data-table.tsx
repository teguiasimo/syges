import {
    type ColumnDef,
    type ColumnFiltersState,
    type SortingState,
    type VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import * as React from 'react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/**
 * Propriétés du composant générique DataTable propulsé par TanStack Table.
 *
 * @template TData - Type des données de chaque ligne.
 * @template TValue - Type de valeur pour les cellules.
 */
interface DataTableProps<TData, TValue> {
    /** Définition des colonnes du tableau TanStack */
    columns: ColumnDef<TData, TValue>[];
    /** Tableau des données à afficher */
    data: TData[];
    /** Placeholder du champ de recherche globale */
    searchPlaceholder?: string;
    /** Composants de filtres additionnels à insérer à côté de la recherche */
    filterSlot?: React.ReactNode;
    /** Actions d'en-tête (ex: bouton de création) */
    actionSlot?: React.ReactNode;
    /** Nombre d'éléments par page par défaut (défaut: 10) */
    defaultPageSize?: number;
    /** Classes CSS additionnelles pour le conteneur du tableau */
    containerClassName?: string;
    /** Classes CSS additionnelles pour l'en-tête de colonnes TableHeader */
    headerClassName?: string;
}

/**
 * Composant de tableau de données universel basé sur TanStack Table pour SYGES.
 *
 * Fournit automatiquement :
 * - Le tri interactif par colonnes.
 * - Le filtrage textuel global.
 * - La pagination complète avec choix de la taille de page.
 * - Un design soigné, responsive et conforme au système de design de SYGES.
 *
 * @template TData - Modèle des lignes.
 * @template TValue - Valeurs des colonnes.
 */
export function DataTable<TData, TValue>({
    columns,
    data,
    searchPlaceholder = 'Rechercher...',
    filterSlot,
    actionSlot,
    defaultPageSize = 10,
    containerClassName,
    headerClassName,
}: DataTableProps<TData, TValue>) {
    // États internes pour TanStack Table
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});

    // Initialisation de la table TanStack
    const table = useReactTable({
        data,
        columns,
        state: {
            sorting,
            columnFilters,
            globalFilter,
            columnVisibility,
        },
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: setGlobalFilter,
        onColumnVisibilityChange: setColumnVisibility,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        initialState: {
            pagination: {
                pageSize: defaultPageSize,
            },
        },
    });

    const pageIndex = table.getState().pagination.pageIndex;
    const pageSize = table.getState().pagination.pageSize;
    const totalRows = table.getFilteredRowModel().rows.length;
    const startRow = totalRows === 0 ? 0 : pageIndex * pageSize + 1;
    const endRow = Math.min((pageIndex + 1) * pageSize, totalRows);

    return (
        <div className="flex flex-col gap-4">
            {/* Barre d'outils supérieure : Recherche, Filtres & Actions */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div className="flex flex-1 flex-wrap items-center gap-2.5">
                    {/* Recherche globale */}
                    <div className="relative w-full sm:w-72">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                        <Input
                            placeholder={searchPlaceholder}
                            value={globalFilter ?? ''}
                            onChange={(e) => setGlobalFilter(e.target.value)}
                            className="pl-9 pr-8 bg-card h-9 text-xs sm:text-sm"
                        />
                        {globalFilter && (
                            <button
                                type="button"
                                onClick={() => setGlobalFilter('')}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground/60 hover:text-foreground p-0.5 rounded cursor-pointer"
                                title="Effacer la recherche"
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </div>

                    {/* Emplacement pour filtres supplémentaires (ex: filtre par rôle, par statut) */}
                    {filterSlot}
                </div>

                {/* Bouton d'action / Création */}
                {actionSlot && <div className="flex items-center gap-2">{actionSlot}</div>}
            </div>

            {/* Tableau principal TanStack */}
            <div className={cn("rounded-xl border border-slate-200/90 dark:border-slate-800 bg-card overflow-hidden shadow-2xs", containerClassName)}>
                <Table>
                    <TableHeader className={cn("bg-slate-100/90 dark:bg-slate-850 border-b-2 border-slate-200/90 dark:border-slate-700", headerClassName)}>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="hover:bg-transparent border-b-2 border-slate-200/80 dark:border-slate-700">
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead
                                            key={header.id}
                                            className="text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200 py-3 px-4"
                                        >
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef.header,
                                                      header.getContext()
                                                  )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() && 'selected'}
                                    className="hover:bg-muted/30 transition-colors"
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} className="py-3 text-sm">
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
                                    className="h-32 text-center text-muted-foreground"
                                >
                                    <div className="flex flex-col items-center justify-center gap-1.5 py-6">
                                        <SlidersHorizontal className="size-6 text-muted-foreground/40" />
                                        <p className="text-sm font-medium text-foreground">
                                            Aucun résultat trouvé
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Aucun enregistrement ne correspond aux critères actuels.
                                        </p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pied de tableau : Informations de pagination et sélecteurs de page */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-muted-foreground px-1">
                <div>
                    Affichage de <span className="font-semibold text-foreground">{startRow}</span> à{' '}
                    <span className="font-semibold text-foreground">{endRow}</span> sur{' '}
                    <span className="font-semibold text-foreground">{totalRows}</span> enregistrement{totalRows > 1 ? 's' : ''}
                </div>

                <div className="flex items-center gap-4 self-end sm:self-auto">
                    {/* Sélecteur du nombre d'éléments par page */}
                    <div className="flex items-center gap-1.5">
                        <span>Lignes :</span>
                        <select
                            value={table.getState().pagination.pageSize}
                            onChange={(e) => {
                                table.setPageSize(Number(e.target.value));
                            }}
                            className="h-8 rounded-md border border-input bg-card px-2 text-xs text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring cursor-pointer"
                        >
                            {[10, 20, 30, 50].map((size) => (
                                <option key={size} value={size}>
                                    {size}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Navigation entre les pages */}
                    <div className="flex items-center gap-1">
                        <span className="mr-2 font-medium">
                            Page {table.getState().pagination.pageIndex + 1} sur{' '}
                            {Math.max(1, table.getPageCount())}
                        </span>

                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={() => table.setPageIndex(0)}
                            disabled={!table.getCanPreviousPage()}
                            title="Première page"
                        >
                            <ChevronsLeft className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                            title="Page précédente"
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                            title="Page suivante"
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
                            disabled={!table.getCanNextPage()}
                            title="Dernière page"
                        >
                            <ChevronsRight className="size-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}

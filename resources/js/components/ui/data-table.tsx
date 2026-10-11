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
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * Libellés par défaut en français pour les colonnes usuelles dans les tableaux SYGES.
 */
const DEFAULT_COLUMN_LABELS: Record<string, string> = {
    matiere: 'Discipline / Matière',
    code: 'Code',
    nom: 'Intitulé',
    groupe: 'Bloc Pédagogique',
    serie: 'Série',
    coefficient: 'Coefficient',
    note_max: 'Barème Max',
    note_eliminatoire: 'Note Éliminatoire',
    regles: 'Règles & Bonus',
    libelle_bulletin: 'Libellé Relevé',
    portee: 'Portée / Campus',
    is_active: 'Statut',
    actions: 'Actions',
    appreciation: "Appréciation de l'enseignant",
    competence_evaluee: 'Compétence évaluée (APC)',
    valeur_note: 'Note / 20',
    statut_presence: 'Présence',
    cote: 'Cote APC',
    matricule: 'Matricule',
    nom_complet: 'Nom & Prénoms',
    fonction_principale: 'Fonction & Contrat',
    charge_enseignement: 'Enseignement',
    telephone: 'Contact',
    compte_syges: 'Compte SYGES',
    statut: 'Statut',
};

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
    /** Nombre d'éléments par page initial (alias de defaultPageSize) */
    initialPageSize?: number;
    /** Classes CSS additionnelles pour le conteneur du tableau */
    containerClassName?: string;
    /** Classes CSS additionnelles pour la balise table elle-même */
    tableClassName?: string;
    /** Classes CSS additionnelles pour l'en-tête de colonnes TableHeader */
    headerClassName?: string;
    /** Visibilité initiale des colonnes (ex: { note_max: false, note_eliminatoire: false, regles: false }) */
    initialColumnVisibility?: VisibilityState;
    /** Active ou non le bouton de sélection des colonnes visibles (activé par défaut si initialColumnVisibility est spécifié) */
    showColumnVisibilityToggle?: boolean;
    /** Dictionnaire de libellés personnalisés pour les colonnes dans le menu de sélection */
    columnLabels?: Record<string, string>;
}

/**
 * Composant de tableau de données universel basé sur TanStack Table pour SYGES.
 *
 * Fournit automatiquement :
 * - Le tri interactif par colonnes.
 * - Le filtrage textuel global.
 * - La pagination complète avec choix de la taille de page.
 * - La gestion de visibilité optionnelle des colonnes.
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
    initialPageSize,
    containerClassName,
    tableClassName,
    headerClassName,
    initialColumnVisibility,
    showColumnVisibilityToggle,
    columnLabels,
}: DataTableProps<TData, TValue>) {
    const effectivePageSize = initialPageSize ?? defaultPageSize;

    // États internes pour TanStack Table
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>(
        initialColumnVisibility ?? {}
    );

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
                pageSize: effectivePageSize,
            },
            columnVisibility: initialColumnVisibility ?? {},
        },
    });

    const isVisibilityToggleActive = showColumnVisibilityToggle ?? Boolean(initialColumnVisibility);
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

                {/* Actions et sélecteur de colonnes */}
                <div className="flex items-center gap-2">
                    {isVisibilityToggleActive && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-1.5 text-xs cursor-pointer bg-card border-input hover:bg-accent"
                                    title="Choisir les colonnes affichées dans le tableau"
                                >
                                    <SlidersHorizontal className="size-3.5 text-muted-foreground" />
                                    <span>Colonnes</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                <DropdownMenuLabel className="text-xs font-semibold">
                                    Colonnes affichées
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {table
                                    .getAllColumns()
                                    .filter((column) => column.getCanHide())
                                    .map((column) => {
                                        const label =
                                            columnLabels?.[column.id] ||
                                            DEFAULT_COLUMN_LABELS[column.id] ||
                                            column.id;
                                        return (
                                            <DropdownMenuCheckboxItem
                                                key={column.id}
                                                className="text-xs capitalize cursor-pointer"
                                                checked={column.getIsVisible()}
                                                onCheckedChange={(value) => column.toggleVisibility(!!value)}
                                            >
                                                {label}
                                            </DropdownMenuCheckboxItem>
                                        );
                                    })}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    {/* Bouton d'action / Création */}
                    {actionSlot}
                </div>
            </div>

            {/* Tableau principal TanStack */}
            <div className={cn("rounded-xl border border-slate-200/90 dark:border-slate-800 bg-card overflow-hidden shadow-2xs", containerClassName)}>
                <Table className={tableClassName}>
                    <TableHeader className={cn("bg-slate-100/90 dark:bg-slate-850 border-b-2 border-slate-200/90 dark:border-slate-700", headerClassName)}>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="hover:bg-transparent border-b-2 border-slate-200/80 dark:border-slate-700">
                                {headerGroup.headers.map((header) => {
                                    const explicitSize = header.column.columnDef.size;
                                    const meta = header.column.columnDef.meta as any;
                                    const colStyle: React.CSSProperties = {
                                        ...(explicitSize ? { width: `${explicitSize}px`, minWidth: `${explicitSize}px` } : {}),
                                        ...(meta?.style || {}),
                                    };
                                    return (
                                        <TableHead
                                            key={header.id}
                                            style={Object.keys(colStyle).length > 0 ? colStyle : undefined}
                                            className={cn(
                                                "text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200 py-3 px-4",
                                                meta?.headerClassName
                                            )}
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
                                    {row.getVisibleCells().map((cell) => {
                                        const explicitSize = cell.column.columnDef.size;
                                        const meta = cell.column.columnDef.meta as any;
                                        const colStyle: React.CSSProperties = {
                                            ...(explicitSize ? { width: `${explicitSize}px`, minWidth: `${explicitSize}px` } : {}),
                                            ...(meta?.style || {}),
                                        };
                                        return (
                                            <TableCell
                                                key={cell.id}
                                                style={Object.keys(colStyle).length > 0 ? colStyle : undefined}
                                                className={cn("py-3 text-sm px-4", meta?.className)}
                                            >
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext()
                                                )}
                                            </TableCell>
                                        );
                                    })}
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

                <div className="flex items-center gap-3 sm:gap-4 flex-wrap justify-between sm:justify-end w-full sm:w-auto">
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
                            {[10, 20, 30, 50, 100].map((size) => (
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

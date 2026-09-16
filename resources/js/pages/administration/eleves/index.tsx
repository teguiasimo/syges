import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
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
    GraduationCap,
    Plus,
    UserCheck,
    ArrowRightLeft,
    FileSpreadsheet,
    Search,
    Filter,
    Users,
    User,
    Phone,
    School,
    MoreHorizontal,
    Eye,
    Edit3,
    Trash2,
    CheckCircle2,
    Clock,
    Ban,
    AlertTriangle,
    Check,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { toast } from 'sonner';

import { CreateEleveModal } from './modals/create-eleve-modal';
import { EditEleveModal } from './modals/edit-eleve-modal';
import { ReinscriptionModal } from './modals/reinscription-modal';
import { TransfertModal } from './modals/transfert-modal';
import { EleveDetailDrawer } from './modals/eleve-detail-drawer';
import { ImportCsvModal } from './modals/import-csv-modal';

import type {
    Inscription,
    Eleve,
    EtablissementRef,
    AnneeScolaireRef,
    EleveStats,
} from '@/types/eleve';

/**
 * Propriétés reçues par la page Élèves depuis le contrôleur Laravel.
 */
interface ElevesPageProps {
    inscriptions: Inscription[];
    anneesScolaires: AnneeScolaireRef[];
    selectedAnneeId: number;
    etablissements: EtablissementRef[];
    stats: EleveStats;
    filters: {
        etablissement_id?: string;
        cycle_id?: string;
        niveau_id?: string;
        classe_id?: string;
        statut?: string;
        is_redoublant?: string;
        sexe?: string;
        search?: string;
    };
    can: {
        eleves_create: boolean;
        eleves_edit: boolean;
        eleves_delete: boolean;
        inscriptions_manage: boolean;
        inscriptions_transfer: boolean;
    };
}

/**
 * Page principale du Module 5 : Gestion des Élèves & Inscriptions (SYGES).
 * Implémentée avec TanStack Table pour le tri, la sélection groupée et la pagination.
 *
 * @param {ElevesPageProps} props
 * @returns {JSX.Element}
 */
export default function ElevesIndex({
    inscriptions = [],
    anneesScolaires = [],
    selectedAnneeId,
    etablissements = [],
    stats,
    filters,
    can,
}: ElevesPageProps) {
    // États des modales
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [selectedEleveForEdit, setSelectedEleveForEdit] = useState<Eleve | null>(null);

    const [reinscriptionModalOpen, setReinscriptionModalOpen] = useState(false);
    const [transfertModalOpen, setTransfertModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);

    // État du tiroir de détails
    const [detailDrawerOpen, setDetailDrawerOpen] = useState(false);
    const [selectedInscriptionForDetail, setSelectedInscriptionForDetail] = useState<Inscription | null>(null);

    // Dialogue de confirmation de suppression
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [eleveToDelete, setEleveToDelete] = useState<Eleve | null>(null);
    const [deleting, setDeleting] = useState(false);

    // Sélection multiple de lignes pour le transfert
    const [rowSelection, setRowSelection] = useState<Record<string, boolean>>({});

    // État de tri TanStack
    const [sorting, setSorting] = useState<SortingState>([]);

    // Filtres dynamiques locaux
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedEtab, setSelectedEtab] = useState(filters.etablissement_id || '');
    const [selectedClasse, setSelectedClasse] = useState(filters.classe_id || '');
    const [selectedStatut, setSelectedStatut] = useState(filters.statut || '');
    const [selectedSexe, setSelectedSexe] = useState(filters.sexe || '');

    // Application des filtres côté client ou navigation Inertia
    const handleFilterChange = (params: Record<string, string>) => {
        router.get('/administration/eleves', {
            annee_scolaire_id: selectedAnneeId,
            etablissement_id: selectedEtab,
            classe_id: selectedClasse,
            statut: selectedStatut,
            sexe: selectedSexe,
            search: searchTerm,
            ...params,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    // Année scolaire active sélectionnée
    const currentAnneeObj = anneesScolaires.find((a) => a.id === selectedAnneeId);

    // Gestion du changement de statut d'inscription
    const handleStatutChange = (inscriptionId: number, nouveauStatut: string) => {
        router.patch(`/administration/inscriptions/${inscriptionId}/statut`, {
            statut: nouveauStatut,
        }, {
            onSuccess: () => {
                toast.success('Statut mis à jour avec succès !');
            },
            onError: () => {
                toast.error('Erreur lors de la mise à jour du statut.');
            },
        });
    };

    // Confirmation de suppression
    const confirmDelete = () => {
        if (!eleveToDelete) return;
        setDeleting(true);
        router.delete(`/administration/eleves/${eleveToDelete.id}`, {
            onSuccess: () => {
                setDeleting(false);
                setDeleteDialogOpen(false);
                setEleveToDelete(null);
                toast.success('Élève supprimé avec succès.');
            },
            onError: () => {
                setDeleting(false);
                toast.error('Impossible de supprimer cet élève.');
            },
        });
    };

    // Définition des colonnes TanStack Table
    const columns = useMemo<ColumnDef<Inscription>[]>(() => [
        {
            id: 'select',
            header: ({ table }) => (
                <Checkbox
                    checked={table.getIsAllPageRowsSelected()}
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    aria-label="Tout sélectionner"
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    aria-label="Sélectionner la ligne"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: 'eleve.nom',
            header: 'Élève & Identification',
            cell: ({ row }) => {
                const insc = row.original;
                const el = insc.eleve;
                return (
                    <div className="flex items-center gap-3">
                        <div className="size-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                            {el.photo_url ? (
                                <img src={el.photo_url} alt={el.nom_complet} className="size-full object-cover" />
                            ) : (
                                <User className="size-5 text-primary/70" />
                            )}
                        </div>
                        <div className="min-w-0">
                            <button
                                type="button"
                                onClick={() => {
                                    setSelectedInscriptionForDetail(insc);
                                    setDetailDrawerOpen(true);
                                }}
                                className="text-sm font-bold text-foreground hover:text-primary transition-colors text-left truncate block"
                            >
                                {el.nom_complet}
                            </button>
                            <div className="flex items-center gap-1.5 mt-0.5">
                                <Badge variant="outline" className="font-mono text-[10px] px-1.5 py-0 bg-muted/30">
                                    {el.matricule}
                                </Badge>
                                {el.matricule_national && (
                                    <span className="text-[10px] text-muted-foreground font-mono truncate max-w-[120px]">
                                        Nat: {el.matricule_national}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'eleve.sexe',
            header: 'Sexe & Âge',
            cell: ({ row }) => {
                const el = row.original.eleve;
                const age = el.date_naissance
                    ? Math.floor((new Date().getTime() - new Date(el.date_naissance).getTime()) / (365.25 * 24 * 60 * 60 * 1000))
                    : null;
                return (
                    <div className="text-xs space-y-0.5">
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
                            {age !== null && <span className="font-medium text-muted-foreground">{age} ans</span>}
                        </div>
                        {el.date_naissance && (
                            <span className="text-[11px] text-muted-foreground block truncate">
                                {new Date(el.date_naissance).toLocaleDateString('fr-FR')} {el.lieu_naissance ? `(${el.lieu_naissance})` : ''}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'classe.nom',
            header: 'Classe & Établissement',
            cell: ({ row }) => {
                const cls = row.original.classe;
                return (
                    <div className="text-xs">
                        <span className="font-bold text-foreground text-sm block">
                            {cls?.nom ?? 'Non assignée'}
                        </span>
                        <span className="text-[11px] text-muted-foreground truncate block">
                            {cls?.niveau?.nom ?? ''} • {cls?.niveau?.cycle?.section?.etablissement?.nom ?? ''}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'eleve.telephone_parent',
            header: 'Parent / Tuteur',
            cell: ({ row }) => {
                const el = row.original.eleve;
                return (
                    <div className="text-xs space-y-0.5">
                        <span className="font-medium text-foreground block truncate">
                            {el.nom_tuteur || el.nom_pere || el.nom_mere || 'Tuteur non spécifié'}
                        </span>
                        {el.telephone_parent ? (
                            <a
                                href={`tel:${el.telephone_parent}`}
                                className="font-mono text-[11px] text-primary hover:underline flex items-center gap-1"
                            >
                                <Phone className="size-3" />
                                {el.telephone_parent}
                            </a>
                        ) : (
                            <span className="text-[11px] text-muted-foreground">Aucun numéro</span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'is_redoublant',
            header: 'Régime',
            cell: ({ row }) => {
                const isRedoublant = row.original.is_redoublant;
                return (
                    <Badge
                        variant="outline"
                        className={`text-[10px] font-semibold ${
                            isRedoublant
                                ? 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300'
                                : 'bg-zinc-50 text-zinc-700 border-zinc-200 dark:bg-zinc-900 dark:text-zinc-300'
                        }`}
                    >
                        {isRedoublant ? 'Redoublant' : 'Nouveau'}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'statut',
            header: 'Statut Inscription',
            cell: ({ row }) => {
                const statut = row.original.statut;
                const statusMap: Record<string, { label: string; className: string }> = {
                    validee: { label: 'Validée', className: 'bg-emerald-500/15 text-emerald-700 border-emerald-300 dark:text-emerald-400 dark:border-emerald-800' },
                    en_attente: { label: 'En attente', className: 'bg-amber-500/15 text-amber-700 border-amber-300 dark:text-amber-400 dark:border-amber-800' },
                    suspendue: { label: 'Suspendue', className: 'bg-rose-500/15 text-rose-700 border-rose-300 dark:text-rose-400 dark:border-rose-800' },
                    exclu: { label: 'Exclu', className: 'bg-red-700/15 text-red-800 border-red-400 dark:text-red-300 dark:border-red-800' },
                    transfere: { label: 'Transféré', className: 'bg-blue-500/15 text-blue-700 border-blue-300 dark:text-blue-400 dark:border-blue-800' },
                };
                const config = statusMap[statut] || { label: statut, className: 'bg-muted text-muted-foreground' };

                return (
                    <Badge variant="outline" className={`text-[11px] font-bold px-2 py-0.5 border ${config.className}`}>
                        {config.label}
                    </Badge>
                );
            },
        },
        {
            id: 'actions',
            header: () => <span className="text-right block">Actions</span>,
            cell: ({ row }) => {
                const insc = row.original;
                const el = insc.eleve;

                return (
                    <div className="flex items-center justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            onClick={() => {
                                setSelectedInscriptionForDetail(insc);
                                setDetailDrawerOpen(true);
                            }}
                            title="Fiche complète"
                        >
                            <Eye className="size-4 text-muted-foreground hover:text-foreground" />
                        </Button>

                        {can.eleves_edit && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                onClick={() => {
                                    setSelectedEleveForEdit(el);
                                    setEditModalOpen(true);
                                }}
                                title="Modifier"
                            >
                                <Edit3 className="size-4 text-muted-foreground hover:text-primary" />
                            </Button>
                        )}

                        {can.inscriptions_manage && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon" className="size-8">
                                        <MoreHorizontal className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-48">
                                    <DropdownMenuLabel className="text-xs">Changer de statut</DropdownMenuLabel>
                                    {insc.statut !== 'validee' && (
                                        <DropdownMenuItem onClick={() => handleStatutChange(insc.id, 'validee')} className="gap-2">
                                            <CheckCircle2 className="size-4 text-emerald-600" />
                                            Valider l'inscription
                                        </DropdownMenuItem>
                                    )}
                                    {insc.statut !== 'suspendue' && (
                                        <DropdownMenuItem onClick={() => handleStatutChange(insc.id, 'suspendue')} className="gap-2">
                                            <Clock className="size-4 text-amber-600" />
                                            Suspendre temporairement
                                        </DropdownMenuItem>
                                    )}
                                    {insc.statut !== 'exclu' && (
                                        <DropdownMenuItem onClick={() => handleStatutChange(insc.id, 'exclu')} className="gap-2 text-destructive">
                                            <Ban className="size-4" />
                                            Marquer Exclu
                                        </DropdownMenuItem>
                                    )}
                                    {can.eleves_delete && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                onClick={() => {
                                                    setEleveToDelete(el);
                                                    setDeleteDialogOpen(true);
                                                }}
                                                className="gap-2 text-destructive"
                                            >
                                                <Trash2 className="size-4" />
                                                Supprimer l'élève
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                );
            },
        },
    ], [can]);

    // Initialisation de TanStack Table
    const table = useReactTable({
        data: inscriptions,
        columns,
        state: {
            sorting,
            rowSelection,
        },
        enableRowSelection: true,
        onRowSelectionChange: setRowSelection,
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        initialState: {
            pagination: {
                pageSize: 15,
            },
        },
    });

    // Élèves sélectionnés via les cases à cocher
    const selectedInscriptions = useMemo(() => {
        return table.getSelectedRowModel().rows.map((r) => r.original);
    }, [rowSelection, inscriptions]);

    // Liste des classes pour le filtre
    const filterClasses = useMemo(() => {
        const list: { id: number; nom: string; niveauNom: string }[] = [];
        etablissements.forEach((etab) => {
            if (!selectedEtab || String(etab.id) === selectedEtab) {
                etab.sections?.forEach((s) => {
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
            }
        });
        return list;
    }, [etablissements, selectedEtab]);

    return (
        <>
            <Head title="Gestion des Élèves & Inscriptions" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8">
                {/* En-tête de page & Année scolaire active */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-900/40">
                                <GraduationCap className="size-6" />
                            </div>
                            <div>
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                                    Élèves & Inscriptions
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground mt-0.5">
                                    Base Élève Globale, admissions, réinscriptions et suivi de la scolarité.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Sélecteur d'Année Scolaire & Boutons d'Action Principaux */}
                    <div className="flex items-center gap-2 flex-wrap">
                        <select
                            value={selectedAnneeId}
                            onChange={(e) => handleFilterChange({ annee_scolaire_id: e.target.value })}
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            {anneesScolaires.map((a) => (
                                <option key={a.id} value={a.id}>
                                    Année {a.nom} {a.is_current ? '★' : ''}
                                </option>
                            ))}
                        </select>

                        {can.inscriptions_manage && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setImportModalOpen(true)}
                                className="gap-1.5 h-9"
                            >
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                <span className="hidden sm:inline">Importer CSV</span>
                            </Button>
                        )}

                        {can.inscriptions_transfer && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setTransfertModalOpen(true)}
                                disabled={selectedInscriptions.length === 0}
                                className="gap-1.5 h-9"
                            >
                                <ArrowRightLeft className="size-4 text-indigo-600" />
                                <span>Transférer ({selectedInscriptions.length})</span>
                            </Button>
                        )}

                        {can.inscriptions_manage && (
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setReinscriptionModalOpen(true)}
                                className="gap-1.5 h-9"
                            >
                                <UserCheck className="size-4 text-emerald-600" />
                                <span className="hidden sm:inline">Réinscrire Ancien</span>
                            </Button>
                        )}

                        {can.eleves_create && (
                            <Button
                                size="sm"
                                onClick={() => setCreateModalOpen(true)}
                                className="gap-1.5 h-9"
                            >
                                <Plus className="size-4" />
                                Nouvel Élève
                            </Button>
                        )}
                    </div>
                </div>

                {/* Grille des 4 KPIs en temps réel */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="rounded-2xl border border-border/80 bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Total Inscrits
                            </span>
                            <div className="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40">
                                <Users className="size-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-foreground">{stats.totalInscrits}</span>
                            <Badge variant="outline" className="text-[10px] text-emerald-600 border-emerald-300">
                                Validés
                            </Badge>
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-1">Année {currentAnneeObj?.nom ?? ''}</p>
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Parité Filles / Garçons
                            </span>
                            <div className="p-1.5 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40">
                                <User className="size-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-center justify-between">
                            <div>
                                <span className="text-base font-bold text-rose-600">{stats.totalFilles} F</span>
                                <span className="text-xs text-muted-foreground ml-1">
                                    ({stats.totalInscrits > 0 ? Math.round((stats.totalFilles / stats.totalInscrits) * 100) : 0}%)
                                </span>
                            </div>
                            <div className="text-right">
                                <span className="text-base font-bold text-blue-600">{stats.totalGarcons} G</span>
                                <span className="text-xs text-muted-foreground ml-1">
                                    ({stats.totalInscrits > 0 ? Math.round((stats.totalGarcons / stats.totalInscrits) * 100) : 0}%)
                                </span>
                            </div>
                        </div>
                        <div className="w-full bg-muted rounded-full h-1.5 mt-2 overflow-hidden flex">
                            <div
                                className="bg-rose-500 h-full"
                                style={{
                                    width: `${stats.totalInscrits > 0 ? (stats.totalFilles / stats.totalInscrits) * 100 : 50}%`,
                                }}
                            />
                            <div
                                className="bg-blue-500 h-full"
                                style={{
                                    width: `${stats.totalInscrits > 0 ? (stats.totalGarcons / stats.totalInscrits) * 100 : 50}%`,
                                }}
                            />
                        </div>
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Régime Scolaire
                            </span>
                            <div className="p-1.5 rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/40">
                                <School className="size-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-foreground">
                                {stats.totalInscrits - stats.totalRedoublants}
                            </span>
                            <span className="text-xs text-muted-foreground">Nouveaux</span>
                            <span className="text-xs text-amber-600 font-semibold ml-auto">
                                {stats.totalRedoublants} Redoublant(s)
                            </span>
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-1">
                            Taux de redoublement : {stats.totalInscrits > 0 ? Math.round((stats.totalRedoublants / stats.totalInscrits) * 100) : 0}%
                        </p>
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                En Attente & Non Inscrits
                            </span>
                            <div className="p-1.5 rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/40">
                                <Clock className="size-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-amber-600">{stats.totalEnAttente}</span>
                            <span className="text-xs text-muted-foreground">à valider</span>
                            <span className="text-xs text-muted-foreground ml-auto font-medium">
                                {stats.nonInscritsCount} ancien(s) dispo
                            </span>
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-1">Dossiers de pré-inscription</p>
                    </div>
                </div>

                {/* Filtres Drill-down & Barre de recherche */}
                <div className="rounded-2xl border border-border/80 bg-card p-4 shadow-xs space-y-3">
                    <div className="flex flex-col md:flex-row items-center gap-3">
                        {/* Barre de recherche */}
                        <div className="relative flex-1 w-full">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                            <Input
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleFilterChange({ search: searchTerm })}
                                placeholder="Rechercher par nom, prénom, matricule, téléphone du parent..."
                                className="pl-9 h-9"
                            />
                        </div>

                        {/* Filtre Établissement */}
                        <select
                            value={selectedEtab}
                            onChange={(e) => {
                                setSelectedEtab(e.target.value);
                                setSelectedClasse('');
                                handleFilterChange({ etablissement_id: e.target.value, classe_id: '' });
                            }}
                            className="w-full md:w-48 h-9 rounded-md border border-input bg-background px-3 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">Tous les établissements</option>
                            {etablissements.map((etab) => (
                                <option key={etab.id} value={etab.id}>
                                    {etab.nom}
                                </option>
                            ))}
                        </select>

                        {/* Filtre Classe */}
                        <select
                            value={selectedClasse}
                            onChange={(e) => {
                                setSelectedClasse(e.target.value);
                                handleFilterChange({ classe_id: e.target.value });
                            }}
                            className="w-full md:w-44 h-9 rounded-md border border-input bg-background px-3 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">Toutes les classes</option>
                            {filterClasses.map((cls) => (
                                <option key={cls.id} value={cls.id}>
                                    {cls.nom} ({cls.niveauNom})
                                </option>
                            ))}
                        </select>

                        {/* Filtre Statut Inscription */}
                        <select
                            value={selectedStatut}
                            onChange={(e) => {
                                setSelectedStatut(e.target.value);
                                handleFilterChange({ statut: e.target.value });
                            }}
                            className="w-full md:w-36 h-9 rounded-md border border-input bg-background px-3 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">Tous les statuts</option>
                            <option value="validee">Validées</option>
                            <option value="en_attente">En attente</option>
                            <option value="suspendue">Suspendues</option>
                            <option value="exclu">Exclus</option>
                            <option value="transfere">Transférés</option>
                        </select>

                        {/* Filtre Sexe */}
                        <select
                            value={selectedSexe}
                            onChange={(e) => {
                                setSelectedSexe(e.target.value);
                                handleFilterChange({ sexe: e.target.value });
                            }}
                            className="w-full md:w-28 h-9 rounded-md border border-input bg-background px-3 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">Tous sexes</option>
                            <option value="M">Garçons (M)</option>
                            <option value="F">Filles (F)</option>
                        </select>

                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => handleFilterChange({})}
                            className="h-9 px-4 shrink-0"
                        >
                            Filtrer
                        </Button>
                    </div>
                </div>

                {/* TABLEAU DE DONNÉES TANSTACK TABLE */}
                <div className="rounded-2xl border border-border/80 bg-card overflow-hidden shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <tr key={headerGroup.id} className="border-b border-border bg-muted/40">
                                        {headerGroup.headers.map((header) => (
                                            <th
                                                key={header.id}
                                                className="px-4 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider select-none"
                                            >
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(header.column.columnDef.header, header.getContext())}
                                            </th>
                                        ))}
                                    </tr>
                                ))}
                            </thead>
                            <tbody className="divide-y divide-border">
                                {table.getRowModel().rows?.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className={`hover:bg-muted/30 transition-colors ${
                                                row.getIsSelected() ? 'bg-primary/5' : ''
                                            }`}
                                        >
                                            {row.getVisibleCells().map((cell) => (
                                                <td key={cell.id} className="px-4 py-3 align-middle">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={columns.length} className="h-32 text-center text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center gap-1.5">
                                                <Users className="size-8 text-muted-foreground/40" />
                                                <p className="text-sm font-medium">Aucun élève trouvé</p>
                                                <p className="text-xs text-muted-foreground/70">
                                                    Modifiez vos filtres ou enregistrez un nouvel élève.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination TanStack Table */}
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-t border-border bg-muted/20 text-xs text-muted-foreground">
                        <div className="flex items-center gap-2">
                            <span>
                                {table.getFilteredSelectedRowModel().rows.length} sur{' '}
                                {table.getFilteredRowModel().rows.length} ligne(s) sélectionnée(s)
                            </span>
                        </div>

                        <div className="flex items-center gap-4">
                            <div className="flex items-center gap-1.5">
                                <span>Lignes par page :</span>
                                <select
                                    value={table.getState().pagination.pageSize}
                                    onChange={(e) => table.setPageSize(Number(e.target.value))}
                                    className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                >
                                    {[10, 15, 25, 50, 100].map((size) => (
                                        <option key={size} value={size}>
                                            {size}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <span>
                                Page {table.getState().pagination.pageIndex + 1} sur{' '}
                                {table.getPageCount() || 1}
                            </span>

                            <div className="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => table.setPageIndex(0)}
                                    disabled={!table.getCanPreviousPage()}
                                >
                                    <ChevronsLeft className="size-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => table.previousPage()}
                                    disabled={!table.getCanPreviousPage()}
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => table.nextPage()}
                                    disabled={!table.getCanNextPage()}
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    onClick={() => table.setPageIndex(table.getPageCount() - 1)}
                                    disabled={!table.getCanNextPage()}
                                >
                                    <ChevronsRight className="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* MODALES & TIROIRS */}
            {can.eleves_create && (
                <CreateEleveModal
                    open={createModalOpen}
                    onOpenChange={setCreateModalOpen}
                    etablissements={etablissements}
                    anneesScolaires={anneesScolaires}
                    selectedAnneeId={selectedAnneeId}
                />
            )}

            {can.eleves_edit && (
                <EditEleveModal
                    open={editModalOpen}
                    onOpenChange={setEditModalOpen}
                    eleve={selectedEleveForEdit}
                />
            )}

            {can.inscriptions_manage && (
                <ReinscriptionModal
                    open={reinscriptionModalOpen}
                    onOpenChange={setReinscriptionModalOpen}
                    etablissements={etablissements}
                    anneesScolaires={anneesScolaires}
                    selectedAnneeId={selectedAnneeId}
                />
            )}

            {can.inscriptions_transfer && (
                <TransfertModal
                    open={transfertModalOpen}
                    onOpenChange={setTransfertModalOpen}
                    inscriptions={selectedInscriptions}
                    etablissements={etablissements}
                />
            )}

            {can.inscriptions_manage && (
                <ImportCsvModal
                    open={importModalOpen}
                    onOpenChange={setImportModalOpen}
                    etablissements={etablissements}
                    anneesScolaires={anneesScolaires}
                    selectedAnneeId={selectedAnneeId}
                />
            )}

            <EleveDetailDrawer
                open={detailDrawerOpen}
                onOpenChange={setDetailDrawerOpen}
                inscription={selectedInscriptionForDetail}
                onEdit={(el) => {
                    setSelectedEleveForEdit(el);
                    setEditModalOpen(true);
                }}
            />

            {/* Confirmation Dialog de suppression */}
            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="text-destructive flex items-center gap-2">
                            <AlertTriangle className="size-5" />
                            Supprimer l'élève
                        </DialogTitle>
                        <DialogDescription>
                            Êtes-vous sûr de vouloir supprimer définitivement l'élève{' '}
                            <strong>{eleveToDelete?.nom_complet}</strong> (Matricule : {eleveToDelete?.matricule}) ?
                            Cette action supprimera également son inscription de l'année scolaire.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button variant="ghost" onClick={() => setDeleteDialogOpen(false)}>
                            Annuler
                        </Button>
                        <Button variant="destructive" disabled={deleting} onClick={confirmDelete}>
                            {deleting ? 'Suppression...' : 'Confirmer la suppression'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Configuration du layout et des fils d'Ariane pour la page Élèves.
 */
ElevesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Élèves & Inscriptions',
            href: '/administration/eleves',
        },
    ],
};

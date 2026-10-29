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
    Wallet,
    TrendingDown,
    TrendingUp,
    Scale,
    FileSpreadsheet,
    Plus,
    Printer,
    Edit2,
    Trash2,
    Search,
    RotateCw,
    School,
    CheckCircle2,
    XCircle,
    ArrowLeft,
    Tag,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    FileText,
    DollarSign,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from 'sonner';
import { formatDate, formatDateForInput } from '@/lib/utils';
import type {
    DepenseItem,
    CategorieDepenseItem,
    CaisseRef,
    FinanceDepenseStats,
    ModeReglement,
    StatutDepense,
} from '@/types/depense';
import type { EtablissementRef, AnneeScolaireRef } from '@/types/eleve';

/**
 * Propriétés passées par le contrôleur DepenseController.
 */
interface DepensesPageProps {
    depenses: DepenseItem[];
    categories: CategorieDepenseItem[];
    caisses: CaisseRef[];
    stats: FinanceDepenseStats;
    etablissements: EtablissementRef[];
    selectedEtabId: number;
    selectedAnneeId: number;
    anneesScolaires: AnneeScolaireRef[];
    filters: {
        etablissement_id?: string;
        annee_scolaire_id?: string;
        caisse_id?: string;
        categorie_depense_id?: string;
        statut?: string;
        search?: string;
    };
}

/**
 * Page principale de gestion des Dépenses, Charges et Fiches de Décharge (Phase 1).
 *
 * Fonctionnalités :
 * - Suivi de l'ensemble des décaissements et imputations budgétaires.
 * - Génération certifiée des Fiches de Décharge / Bons de décaissement de caisse.
 * - Suivi du solde net d'exploitation (Recettes vs Dépenses) et édition du Bilan Financier.
 */
export default function DepensesIndex({
    depenses = [],
    categories = [],
    caisses = [],
    stats,
    etablissements = [],
    selectedEtabId,
    selectedAnneeId,
    anneesScolaires = [],
    filters = {},
}: DepensesPageProps) {
    const [selectedEtab, setSelectedEtab] = useState<number>(selectedEtabId);
    const [selectedAnnee, setSelectedAnnee] = useState<number>(selectedAnneeId);
    const [selectedCaisse, setSelectedCaisse] = useState<string>(filters.caisse_id || '');
    const [selectedCategorie, setSelectedCategorie] = useState<string>(filters.categorie_depense_id || '');
    const [selectedStatut, setSelectedStatut] = useState<string>(filters.statut || '');
    const [searchTerm, setSearchTerm] = useState<string>(filters.search || '');

    // États des dialogues
    const [isCreateOpen, setIsCreateOpen] = useState<boolean>(false);
    const [isEditOpen, setIsEditOpen] = useState<boolean>(false);
    const [isCategoryModalOpen, setIsCategoryModalOpen] = useState<boolean>(false);
    const [editingDepense, setEditingDepense] = useState<DepenseItem | null>(null);

    // Formulaire de dépense
    const [formData, setFormData] = useState({
        caisse_id: caisses[0]?.id ? String(caisses[0].id) : '',
        categorie_depense_id: categories[0]?.id ? String(categories[0].id) : '',
        beneficiaire: '',
        montant: '',
        date_depense: formatDateForInput(new Date()),
        motif: '',
        mode_reglement: 'especes' as ModeReglement,
        reference_paiement: '',
        statut: 'validee' as StatutDepense,
    });

    // Formulaire de catégorie
    const [catFormData, setCatFormData] = useState({
        code: '',
        nom: '',
        description: '',
        couleur: '#64748b',
    });

    // Filtrage dynamique
    const handleFilterChange = (params: Record<string, string | number>) => {
        router.get(
            '/administration/finances/depenses',
            {
                etablissement_id: selectedEtab,
                annee_scolaire_id: selectedAnnee,
                caisse_id: selectedCaisse,
                categorie_depense_id: selectedCategorie,
                statut: selectedStatut,
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
     * Enregistre une nouvelle dépense.
     */
    const handleCreateDepense = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/administration/finances/depenses',
            {
                etablissement_id: selectedEtab,
                annee_scolaire_id: selectedAnnee,
                caisse_id: formData.caisse_id,
                categorie_depense_id: formData.categorie_depense_id,
                beneficiaire: formData.beneficiaire,
                montant: parseFloat(formData.montant),
                date_depense: formData.date_depense,
                motif: formData.motif,
                mode_reglement: formData.mode_reglement,
                reference_paiement: formData.reference_paiement || null,
                statut: formData.statut,
            },
            {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    setFormData({
                        caisse_id: caisses[0]?.id ? String(caisses[0].id) : '',
                        categorie_depense_id: categories[0]?.id ? String(categories[0].id) : '',
                        beneficiaire: '',
                        montant: '',
                        date_depense: formatDateForInput(new Date()),
                        motif: '',
                        mode_reglement: 'especes',
                        reference_paiement: '',
                        statut: 'validee',
                    });
                    toast.success('Dépense enregistrée. Bon de décaissement prêt pour émargement.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur lors de l’enregistrement.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Met à jour une dépense existante.
     */
    const handleUpdateDepense = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingDepense) return;

        router.put(
            `/administration/finances/depenses/${editingDepense.id}`,
            {
                caisse_id: formData.caisse_id,
                categorie_depense_id: formData.categorie_depense_id,
                beneficiaire: formData.beneficiaire,
                montant: parseFloat(formData.montant),
                date_depense: formData.date_depense,
                motif: formData.motif,
                mode_reglement: formData.mode_reglement,
                reference_paiement: formData.reference_paiement || null,
                statut: formData.statut,
            },
            {
                onSuccess: () => {
                    setIsEditOpen(false);
                    setEditingDepense(null);
                    toast.success('Dépense mise à jour avec succès.', {
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
     * Supprime une dépense.
     */
    const handleDeleteDepense = (dep: DepenseItem) => {
        if (!confirm(`Supprimer la dépense ${dep.numero_piece} (${dep.montant} FCFA) ?`)) return;

        router.delete(`/administration/finances/depenses/${dep.id}`, {
            onSuccess: () => {
                toast.success('Dépense supprimée.', {
                    position: 'top-right',
                });
            },
        });
    };

    /**
     * Enregistre une nouvelle catégorie budgétaire.
     */
    const handleCreateCategory = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/administration/finances/depenses/categories',
            {
                etablissement_id: selectedEtab,
                ...catFormData,
            },
            {
                onSuccess: () => {
                    setIsCategoryModalOpen(false);
                    setCatFormData({
                        code: '',
                        nom: '',
                        description: '',
                        couleur: '#64748b',
                    });
                    toast.success('Catégorie budgétaire créée.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur de création de catégorie.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    // Configuration TanStack Table
    const [sorting, setSorting] = useState<SortingState>([]);

    const columns: ColumnDef<DepenseItem>[] = useMemo(
        () => [
            {
                accessorKey: 'numero_piece',
                header: 'N° Pièce',
                cell: ({ row }) => (
                    <span className="font-mono font-bold text-xs text-foreground whitespace-nowrap">
                        {row.original.numero_piece}
                    </span>
                ),
            },
            {
                accessorKey: 'date_depense',
                header: 'Date',
                cell: ({ row }) => (
                    <span className="text-xs text-foreground font-medium whitespace-nowrap">
                        {formatDate(row.original.date_depense)}
                    </span>
                ),
            },
            {
                accessorKey: 'beneficiaire',
                header: 'Bénéficiaire & Motif',
                cell: ({ row }) => (
                    <div className="min-w-44 max-w-xs">
                        <div className="font-semibold text-sm text-foreground">
                            {row.original.beneficiaire}
                        </div>
                        <p className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                            {row.original.motif}
                        </p>
                    </div>
                ),
            },
            {
                accessorKey: 'categorieDepense.nom',
                header: 'Imputation Budgétaire',
                cell: ({ row }) => (
                    <Badge
                        variant="outline"
                        style={{ borderColor: row.original.categorieDepense?.couleur }}
                        className="text-xs font-semibold"
                    >
                        <span
                            className="size-2 rounded-full mr-1.5"
                            style={{ backgroundColor: row.original.categorieDepense?.couleur || '#64748b' }}
                        />
                        {row.original.categorieDepense?.nom || 'Dépenses diverses'}
                    </Badge>
                ),
            },
            {
                accessorKey: 'caisse.nom',
                header: 'Caisse',
                cell: ({ row }) => (
                    <span className="text-xs text-muted-foreground font-medium">
                        {row.original.caisse?.nom || 'Caisse Principale'}
                    </span>
                ),
            },
            {
                accessorKey: 'mode_reglement',
                header: 'Règlement',
                cell: ({ row }) => (
                    <div className="text-xs">
                        <span className="font-medium text-foreground uppercase">
                            {row.original.mode_reglement}
                        </span>
                        {row.original.reference_paiement && (
                            <div className="font-mono text-[11px] text-muted-foreground">
                                {row.original.reference_paiement}
                            </div>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'montant',
                header: 'Montant Décaissé',
                cell: ({ row }) => (
                    <span className="font-mono font-bold text-sm text-rose-600 dark:text-rose-400 whitespace-nowrap tabular-nums">
                        {new Intl.NumberFormat('fr-FR').format(row.original.montant)} FCFA
                    </span>
                ),
            },
            {
                accessorKey: 'statut',
                header: 'Statut',
                cell: ({ row }) => {
                    const st = row.original.statut;
                    if (st === 'validee') {
                        return <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 whitespace-nowrap">Validée</Badge>;
                    }
                    if (st === 'en_attente') {
                        return <Badge className="bg-amber-100 text-amber-800 border-amber-300 whitespace-nowrap">En attente</Badge>;
                    }
                    return <Badge className="bg-rose-100 text-rose-800 border-rose-300 whitespace-nowrap">Annulée</Badge>;
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5 whitespace-nowrap">
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 px-2 gap-1 text-[11px] font-medium text-teal-700 border-teal-200 hover:bg-teal-50"
                            onClick={() => {
                                window.open(`/administration/finances/depenses/${row.original.id}/decharge-pdf`, '_blank');
                                toast.success('Fiche de décharge générée en PDF.', { position: 'top-right' });
                            }}
                            title="Imprimer la Fiche de Décharge (Bon de décaissement)"
                        >
                            <Printer className="size-3" />
                            Décharge (PDF)
                        </Button>

                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                            onClick={() => {
                                setEditingDepense(row.original);
                                setFormData({
                                    caisse_id: String(row.original.caisse_id),
                                    categorie_depense_id: String(row.original.categorie_depense_id),
                                    beneficiaire: row.original.beneficiaire,
                                    montant: String(row.original.montant),
                                    date_depense: formatDateForInput(row.original.date_depense),
                                    motif: row.original.motif,
                                    mode_reglement: row.original.mode_reglement,
                                    reference_paiement: row.original.reference_paiement || '',
                                    statut: row.original.statut,
                                });
                                setIsEditOpen(true);
                            }}
                            title="Modifier"
                        >
                            <Edit2 className="size-3.5" />
                        </Button>

                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                            onClick={() => handleDeleteDepense(row.original)}
                            title="Supprimer"
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    </div>
                ),
            },
        ],
        []
    );

    const table = useReactTable({
        data: depenses,
        columns,
        state: { sorting },
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getRowId: (row) => String(row.id),
        initialState: {
            pagination: { pageSize: 15 },
        },
    });

    /**
     * Ouvre le Bilan Financier Synthétique Entrées/Sorties en PDF.
     */
    const handlePrintBilan = () => {
        const url = `/administration/finances/bilan-pdf?etablissement_id=${selectedEtab}&annee_scolaire_id=${selectedAnnee}`;
        window.open(url, '_blank');
        toast.success('Bilan financier généré en PDF.', { position: 'top-right' });
    };

    return (
        <>
            <Head title="Gestion des Dépenses & Bilan Financier" />

            {/* Conteneur principal responsive adapté aux mobiles, tablettes et laptops */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 min-w-0">
                {/* En-tête & navigation */}
                <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                            <Button variant="ghost" size="sm" asChild className="h-8 px-2 gap-1.5 text-xs text-muted-foreground hover:text-foreground">
                                <Link href="/administration/finances">
                                    <ArrowLeft className="size-3.5 shrink-0" />
                                    Retour au Grand Livre Financier
                                </Link>
                            </Button>
                        </div>
                        <div className="flex items-start sm:items-center gap-2.5 min-w-0">
                            <div className="p-2.5 rounded-xl bg-linear-to-br from-amber-600 to-orange-700 text-white shadow-md shadow-amber-500/20 shrink-0">
                                <Wallet className="size-6" />
                            </div>
                            <div className="min-w-0">
                                <h1 className="text-xl sm:text-2xl lg:text-3xl font-bold tracking-tight text-foreground">
                                    Gestion des Dépenses & Bilan Financier
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    Suivi des sorties de caisse, bons de décaissement certifiés et compte d'exploitation synthétique.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Actions d'ajout & Bilan */}
                    <div className="flex flex-col sm:flex-row sm:items-center gap-2 flex-wrap w-full sm:w-auto">
                        <Button
                            variant="outline"
                            onClick={handlePrintBilan}
                            className="gap-2 h-9 text-xs font-semibold w-full sm:w-auto justify-center border-teal-300 text-teal-700 hover:bg-teal-50 dark:border-teal-800 dark:text-teal-300"
                        >
                            <FileSpreadsheet className="size-3.5 shrink-0" />
                            Bilan Entrées/Sorties (PDF)
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setCatFormData({
                                    code: `CHG-${categories.length + 1}`,
                                    nom: '',
                                    description: '',
                                    couleur: '#0284c7',
                                });
                                setIsCategoryModalOpen(true);
                            }}
                            className="gap-2 h-9 text-xs font-semibold w-full sm:w-auto justify-center border-border text-foreground hover:bg-muted"
                        >
                            <Tag className="size-3.5 shrink-0" />
                            Nouvelle Catégorie
                        </Button>
                        <Button
                            onClick={() => {
                                setFormData({
                                    caisse_id: caisses[0]?.id ? String(caisses[0].id) : '',
                                    categorie_depense_id: categories[0]?.id ? String(categories[0].id) : '',
                                    beneficiaire: '',
                                    montant: '',
                                    date_depense: formatDateForInput(new Date()),
                                    motif: '',
                                    mode_reglement: 'especes',
                                    reference_paiement: '',
                                    statut: 'validee',
                                });
                                setIsCreateOpen(true);
                            }}
                            className="gap-2 h-9 text-xs font-semibold w-full sm:w-auto justify-center bg-rose-600 hover:bg-rose-700 text-white shadow-sm"
                        >
                            <Plus className="size-4 shrink-0" />
                            Enregistrer une Dépense
                        </Button>
                    </div>
                </div>

                {/* Cartes KPI Synthèse Entrées vs Sorties */}
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-start sm:items-center justify-between gap-2">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-medium text-muted-foreground">Total Recettes Encaissées</p>
                                <h3 className="text-lg sm:text-xl 2xl:text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400 mt-0.5 font-mono tabular-nums wrap-break-word leading-tight">
                                    {new Intl.NumberFormat('fr-FR').format(stats.total_recettes)}
                                </h3>
                                <p className="text-[10px] text-muted-foreground">Scolarité + Divers</p>
                            </div>
                            <div className="p-2.5 rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 shrink-0">
                                <TrendingUp className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-start sm:items-center justify-between gap-2">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-medium text-muted-foreground">Total Dépenses Décaissées</p>
                                <h3 className="text-lg sm:text-xl 2xl:text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400 mt-0.5 font-mono tabular-nums wrap-break-word leading-tight">
                                    {new Intl.NumberFormat('fr-FR').format(stats.total_depenses)}
                                </h3>
                                <p className="text-[10px] text-muted-foreground">{stats.nb_depenses} opération(s)</p>
                            </div>
                            <div className="p-2.5 rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 shrink-0">
                                <TrendingDown className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-start sm:items-center justify-between gap-2">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-medium text-muted-foreground">Résultat Net d'Exploitation</p>
                                <h3 className={`text-lg sm:text-xl 2xl:text-2xl font-bold tracking-tight mt-0.5 font-mono tabular-nums wrap-break-word leading-tight ${stats.solde_net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}>
                                    {new Intl.NumberFormat('fr-FR').format(stats.solde_net)}
                                </h3>
                                <p className="text-[10px] font-semibold text-muted-foreground">
                                    {stats.solde_net >= 0 ? 'Excédent budgétaire' : 'Déficit budgétaire'}
                                </p>
                            </div>
                            <div className={`p-2.5 rounded-lg shrink-0 ${stats.solde_net >= 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'}`}>
                                <Scale className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-start sm:items-center justify-between gap-2">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-medium text-muted-foreground">Caisses Actives</p>
                                <h3 className="text-lg sm:text-xl 2xl:text-2xl font-bold tracking-tight text-foreground mt-0.5 font-mono tabular-nums leading-tight">{caisses.length}</h3>
                                <p className="text-[10px] text-muted-foreground">{categories.length} catégories budgétaires</p>
                            </div>
                            <div className="p-2.5 rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 shrink-0">
                                <Wallet className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filtres */}
                <Card className="border-border/60 shadow-sm">
                    <CardContent className="p-4 sm:p-5">
                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 items-end">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Caisse</label>
                                <select
                                    value={selectedCaisse}
                                    onChange={(e) => {
                                        setSelectedCaisse(e.target.value);
                                        handleFilterChange({ caisse_id: e.target.value });
                                    }}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Toutes les caisses</option>
                                    {caisses.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.nom}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Catégorie Budgétaire</label>
                                <select
                                    value={selectedCategorie}
                                    onChange={(e) => {
                                        setSelectedCategorie(e.target.value);
                                        handleFilterChange({ categorie_depense_id: e.target.value });
                                    }}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Toutes les catégories</option>
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>
                                            {cat.nom}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Statut</label>
                                <select
                                    value={selectedStatut}
                                    onChange={(e) => {
                                        setSelectedStatut(e.target.value);
                                        handleFilterChange({ statut: e.target.value });
                                    }}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Tous les statuts</option>
                                    <option value="validee">Validée</option>
                                    <option value="en_attente">En attente</option>
                                    <option value="annulee">Annulée</option>
                                </select>
                            </div>

                            <div className="sm:col-span-2 xl:col-span-2">
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Recherche</label>
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        placeholder="N° Pièce, bénéficiaire, motif..."
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
                                            <th key={header.id} className="px-3 py-2.5 sm:p-3.5 whitespace-nowrap">
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
                                                <td key={cell.id} className="px-3 py-2.5 sm:p-3.5">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={columns.length} className="text-center py-12 px-4 text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <Wallet className="size-8 text-muted-foreground/40" />
                                                <p className="text-sm font-medium">Aucune dépense enregistrée pour cette sélection.</p>
                                                <p className="text-xs">Cliquez sur « Enregistrer une Dépense » pour saisir un décaissement de caisse.</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination TanStack Table responsive */}
                    {table.getPageCount() > 1 && (
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 border-t text-xs text-muted-foreground">
                            <span>
                                Page {table.getState().pagination.pageIndex + 1} sur {table.getPageCount()} ({depenses.length} au total)
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
            </div>

            {/* MODAL 1 : NOUVELLE DÉPENSE */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Wallet className="size-4 text-rose-600 shrink-0" />
                            Enregistrer un Décaissement / Dépense de Caisse
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Saisie de la sortie de fonds et génération de la Fiche de Décharge officielle.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateDepense} className="space-y-3.5 py-2">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Caisse Débitée *</label>
                                <select
                                    required
                                    value={formData.caisse_id}
                                    onChange={(e) => setFormData({ ...formData, caisse_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs font-medium"
                                >
                                    {caisses.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.nom}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Catégorie Budgétaire *</label>
                                <select
                                    required
                                    value={formData.categorie_depense_id}
                                    onChange={(e) => setFormData({ ...formData, categorie_depense_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>
                                            {cat.nom}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Bénéficiaire des fonds *</label>
                                <Input
                                    required
                                    placeholder="Nom du prestataire, employé..."
                                    value={formData.beneficiaire}
                                    onChange={(e) => setFormData({ ...formData, beneficiaire: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Montant (FCFA) *</label>
                                <Input
                                    type="number"
                                    min="1"
                                    required
                                    placeholder="Ex: 50000"
                                    value={formData.montant}
                                    onChange={(e) => setFormData({ ...formData, montant: e.target.value })}
                                    className="h-9 text-xs font-mono font-bold"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Date décaissement *</label>
                                <Input
                                    type="date"
                                    required
                                    value={formData.date_depense}
                                    onChange={(e) => setFormData({ ...formData, date_depense: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Mode de règlement *</label>
                                <select
                                    value={formData.mode_reglement}
                                    onChange={(e) => setFormData({ ...formData, mode_reglement: e.target.value as ModeReglement })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="especes">Espèces (Caisse)</option>
                                    <option value="virement">Virement bancaire</option>
                                    <option value="cheque">Chèque</option>
                                    <option value="orange_money">Orange Money</option>
                                    <option value="mtn_momo">MTN Mobile Money</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Motif circonstancié de la dépense *</label>
                            <textarea
                                required
                                rows={2}
                                placeholder="Achat de 10 cartons de craies, vacation des enseignants d'EPS..."
                                value={formData.motif}
                                onChange={(e) => setFormData({ ...formData, motif: e.target.value })}
                                className="w-full p-2.5 rounded-md border border-input bg-background text-xs"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">Référence de paiement (Optionnel)</label>
                            <Input
                                placeholder="N° de chèque, transaction ID Mobile Money..."
                                value={formData.reference_paiement}
                                onChange={(e) => setFormData({ ...formData, reference_paiement: e.target.value })}
                                className="h-9 text-xs font-mono"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" className="w-full sm:w-auto" onClick={() => setIsCreateOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="w-full sm:w-auto bg-rose-600 hover:bg-rose-700 text-white">
                                Valider et Générer Décharge
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 2 : NOUVELLE CATÉGORIE BUDGÉTAIRE */}
            <Dialog open={isCategoryModalOpen} onOpenChange={setIsCategoryModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Tag className="size-4 text-blue-600 shrink-0" />
                            Nouvelle Catégorie Budgétaire
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCreateCategory} className="space-y-3.5 py-2">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Code *</label>
                                <Input
                                    required
                                    placeholder="Ex: CHG-EAU"
                                    value={catFormData.code}
                                    onChange={(e) => setCatFormData({ ...catFormData, code: e.target.value.toUpperCase() })}
                                    className="h-9 text-xs font-mono font-bold"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Couleur du Badge *</label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        value={catFormData.couleur}
                                        onChange={(e) => setCatFormData({ ...catFormData, couleur: e.target.value })}
                                        className="size-9 rounded border cursor-pointer p-0.5 shrink-0"
                                    />
                                    <Input
                                        value={catFormData.couleur}
                                        onChange={(e) => setCatFormData({ ...catFormData, couleur: e.target.value })}
                                        className="h-9 text-xs font-mono"
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Nom de la catégorie *</label>
                            <Input
                                required
                                placeholder="Ex: Eau, Électricité & Gaz"
                                value={catFormData.nom}
                                onChange={(e) => setCatFormData({ ...catFormData, nom: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">Description</label>
                            <Input
                                placeholder="Factures Eneo, Camwater..."
                                value={catFormData.description}
                                onChange={(e) => setCatFormData({ ...catFormData, description: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" className="w-full sm:w-auto" onClick={() => setIsCategoryModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white">
                                Créer la Catégorie
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Configuration du layout applicatif et des fils d'Ariane.
 */
DepensesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Gestion Financière & Caisse',
            href: '/administration/finances',
        },
        {
            title: 'Dépenses & Bilan Financier',
            href: '/administration/finances/depenses',
        },
    ],
};

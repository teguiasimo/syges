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
    ShieldAlert,
    HeartPulse,
    Printer,
    Plus,
    Trash2,
    Edit2,
    Search,
    RotateCw,
    School,
    CheckCircle2,
    XCircle,
    Clock,
    FileText,
    ArrowLeft,
    AlertCircle,
    UserCheck,
    Check,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Activity,
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
import { toast } from 'sonner';
import { formatDate, formatDateForInput } from '@/lib/utils';
import type { DispenseItem, DispenseStats, TypeDispense, StatutDispense } from '@/types/dispense';
import type { MatiereRef, PeriodeRef } from '@/types/pedagogie';
import type { Inscription, EtablissementRef, AnneeScolaireRef } from '@/types/eleve';

/**
 * Propriétés passées par le contrôleur DispenseEleveController.
 */
interface DispensesPageProps {
    dispenses: DispenseItem[];
    stats: DispenseStats;
    inscriptions: Inscription[];
    matieres: MatiereRef[];
    periodes: PeriodeRef[];
    etablissements: EtablissementRef[];
    selectedEtabId: number;
    selectedAnneeId: number;
    anneesScolaires: AnneeScolaireRef[];
    filters: {
        etablissement_id?: string;
        annee_scolaire_id?: string;
        classe_id?: string;
        matiere_id?: string;
        type_dispense?: string;
        statut?: string;
        search?: string;
    };
}

/**
 * Page principale de gestion des Dérogations & Dispenses Officielles (Phase 4).
 *
 * Fonctionnalités :
 * - Suivi et validation des dispenses médicales (EPS, inaptitude physique, certificats médicaux).
 * - Neutralisation dynamique des coefficients sur les bulletins trimestriels.
 * - Registre et impression du Procès-Verbal officiel (PV) de dispenses pour les examens MINESEC.
 */
export default function DispensesIndex({
    dispenses = [],
    stats,
    inscriptions = [],
    matieres = [],
    periodes = [],
    etablissements = [],
    selectedEtabId,
    selectedAnneeId,
    anneesScolaires = [],
    filters = {},
}: DispensesPageProps) {
    // États de filtrage
    const [selectedEtab, setSelectedEtab] = useState<number>(selectedEtabId);
    const [selectedAnnee, setSelectedAnnee] = useState<number>(selectedAnneeId);
    const [selectedClasse, setSelectedClasse] = useState<string>(filters.classe_id || '');
    const [selectedMatiere, setSelectedMatiere] = useState<string>(filters.matiere_id || '');
    const [selectedType, setSelectedType] = useState<string>(filters.type_dispense || '');
    const [selectedStatut, setSelectedStatut] = useState<string>(filters.statut || '');
    const [searchTerm, setSearchTerm] = useState<string>(filters.search || '');

    // États des dialogues
    const [isCreateOpen, setIsCreateOpen] = useState<boolean>(false);
    const [isEditOpen, setIsEditOpen] = useState<boolean>(false);
    const [editingDispense, setEditingDispense] = useState<DispenseItem | null>(null);

    // Formulaire de création
    const [formData, setFormData] = useState({
        inscription_id: '',
        matiere_id: '',
        periode_id: '',
        type_dispense: 'medicale' as TypeDispense,
        motif: '',
        reference_certificat: '',
        medecin_ou_autorite: '',
        date_debut: '',
        date_fin: '',
        neutraliser_coefficient: true,
        statut: 'validee' as StatutDispense,
    });

    // Formulaire d'édition
    const [editFormData, setEditFormData] = useState({
        matiere_id: '',
        periode_id: '',
        type_dispense: 'medicale' as TypeDispense,
        motif: '',
        reference_certificat: '',
        medecin_ou_autorite: '',
        date_debut: '',
        date_fin: '',
        neutraliser_coefficient: true,
        statut: 'validee' as StatutDispense,
    });

    // Liste des classes de l'établissement sélectionné
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
            '/administration/pedagogie/dispenses',
            {
                etablissement_id: selectedEtab,
                annee_scolaire_id: selectedAnnee,
                classe_id: selectedClasse,
                matiere_id: selectedMatiere,
                type_dispense: selectedType,
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
     * Enregistre une nouvelle dispense officielle.
     */
    const handleCreateDispense = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/administration/pedagogie/dispenses',
            {
                etablissement_id: selectedEtab,
                annee_scolaire_id: selectedAnnee,
                inscription_id: formData.inscription_id,
                matiere_id: formData.matiere_id,
                periode_id: formData.periode_id || null,
                type_dispense: formData.type_dispense,
                motif: formData.motif,
                reference_certificat: formData.reference_certificat || null,
                medecin_ou_autorite: formData.medecin_ou_autorite || null,
                date_debut: formData.date_debut || null,
                date_fin: formData.date_fin || null,
                neutraliser_coefficient: formData.neutraliser_coefficient,
                statut: formData.statut,
            },
            {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    setFormData({
                        inscription_id: '',
                        matiere_id: '',
                        periode_id: '',
                        type_dispense: 'medicale',
                        motif: '',
                        reference_certificat: '',
                        medecin_ou_autorite: '',
                        date_debut: '',
                        date_fin: '',
                        neutraliser_coefficient: true,
                        statut: 'validee',
                    });
                    toast.success('Dispense officielle enregistrée avec succès.', {
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
     * Met à jour une dispense existante.
     */
    const handleUpdateDispense = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingDispense) return;

        router.put(
            `/administration/pedagogie/dispenses/${editingDispense.id}`,
            {
                matiere_id: editFormData.matiere_id,
                periode_id: editFormData.periode_id || null,
                type_dispense: editFormData.type_dispense,
                motif: editFormData.motif,
                reference_certificat: editFormData.reference_certificat || null,
                medecin_ou_autorite: editFormData.medecin_ou_autorite || null,
                date_debut: editFormData.date_debut || null,
                date_fin: editFormData.date_fin || null,
                neutraliser_coefficient: editFormData.neutraliser_coefficient,
                statut: editFormData.statut,
            },
            {
                onSuccess: () => {
                    setIsEditOpen(false);
                    setEditingDispense(null);
                    toast.success('Dispense officielle mise à jour.', {
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
     * Supprime une dispense officielle.
     */
    const handleDeleteDispense = (disp: DispenseItem) => {
        if (!confirm(`Supprimer la dispense pour ${disp.eleve.nom} ${disp.eleve.prenom} (${disp.matiere.nom}) ?`)) return;

        router.delete(`/administration/pedagogie/dispenses/${disp.id}`, {
            onSuccess: () => {
                toast.success('Dispense supprimée avec succès.', {
                    position: 'top-right',
                });
            },
        });
    };

    /**
     * Change rapidement le statut d'une dispense (valider, rejeter).
     */
    const handleToggleStatut = (disp: DispenseItem, newStatut: StatutDispense) => {
        router.patch(`/administration/pedagogie/dispenses/${disp.id}/statut`, {
            statut: newStatut,
        }, {
            onSuccess: () => {
                toast.success(`Statut mis à jour : ${newStatut}.`, {
                    position: 'top-right',
                });
            },
        });
    };

    // Configuration TanStack Table
    const [sorting, setSorting] = useState<SortingState>([]);

    const columns: ColumnDef<DispenseItem>[] = useMemo(
        () => [
            {
                accessorKey: 'eleve.nom',
                header: 'Élève & Classe',
                cell: ({ row }) => (
                    <div>
                        <div className="font-semibold text-foreground text-sm">
                            {row.original.eleve.nom} {row.original.eleve.prenom}
                        </div>
                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground mt-0.5">
                            <span className="font-mono">{row.original.eleve.matricule}</span>
                            <span>•</span>
                            <Badge variant="outline" className="text-[11px] h-4 px-1.5 border-indigo-200 bg-indigo-50/50 text-indigo-700">
                                {row.original.inscription?.classe?.nom || 'Classe'}
                            </Badge>
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'matiere.nom',
                header: 'Matière Dispensée',
                cell: ({ row }) => (
                    <div>
                        <span className="font-semibold text-sm text-foreground">{row.original.matiere?.nom}</span>
                        <span className="ml-1 text-xs text-muted-foreground font-mono">({row.original.matiere?.code})</span>
                        {row.original.neutraliser_coefficient && (
                            <div className="text-[11px] font-semibold text-emerald-600 mt-0.5">
                                Coef. Neutralisé (0)
                            </div>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'type_dispense',
                header: 'Type & Motif',
                cell: ({ row }) => (
                    <div className="max-w-xs">
                        <Badge
                            variant="secondary"
                            className={
                                row.original.type_dispense === 'medicale'
                                    ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300 border-sky-300'
                                    : 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border-purple-300'
                            }
                        >
                            {row.original.type_dispense === 'medicale' ? 'Médicale / EPS' : ucfirst(row.original.type_dispense)}
                        </Badge>
                        <p className="text-xs text-foreground mt-1 line-clamp-2 leading-relaxed">
                            {row.original.motif}
                        </p>
                    </div>
                ),
            },
            {
                accessorKey: 'reference_certificat',
                header: 'Certificat / Médecin',
                cell: ({ row }) => (
                    <div className="text-xs">
                        <div className="font-mono font-medium text-foreground">
                            {row.original.reference_certificat || 'Sans référence'}
                        </div>
                        {row.original.medecin_ou_autorite && (
                            <div className="text-muted-foreground text-[11px] mt-0.5">
                                {row.original.medecin_ou_autorite}
                            </div>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'periode.nom',
                header: 'Validité',
                cell: ({ row }) => (
                    <div className="text-xs">
                        <div className="font-medium text-foreground">
                            {row.original.periode?.nom || 'Toute l\'année'}
                        </div>
                        {row.original.date_debut && (
                            <div className="text-[11px] text-muted-foreground mt-0.5">
                                Du {formatDate(row.original.date_debut)} {row.original.date_fin ? `au ${formatDate(row.original.date_fin)}` : ''}
                            </div>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'statut',
                header: 'Statut',
                cell: ({ row }) => {
                    const st = row.original.statut;
                    if (st === 'validee') {
                        return (
                            <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 text-xs">
                                <CheckCircle2 className="size-3 mr-1" />
                                Validée
                            </Badge>
                        );
                    }
                    if (st === 'en_attente') {
                        return (
                            <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 text-xs">
                                <Clock className="size-3 mr-1" />
                                En attente
                            </Badge>
                        );
                    }
                    if (st === 'rejetee') {
                        return (
                            <Badge className="bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 text-xs">
                                <XCircle className="size-3 mr-1" />
                                Rejetée
                            </Badge>
                        );
                    }
                    return (
                        <Badge className="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 text-xs">
                            Expirée
                        </Badge>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                cell: ({ row }) => (
                    <div className="flex items-center gap-1">
                        {row.original.statut === 'en_attente' && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50"
                                onClick={() => handleToggleStatut(row.original, 'validee')}
                                title="Valider la dispense"
                            >
                                <Check className="size-3.5" />
                            </Button>
                        )}
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                            onClick={() => {
                                setEditingDispense(row.original);
                                setEditFormData({
                                    matiere_id: String(row.original.matiere_id),
                                    periode_id: row.original.periode_id ? String(row.original.periode_id) : '',
                                    type_dispense: row.original.type_dispense,
                                    motif: row.original.motif,
                                    reference_certificat: row.original.reference_certificat || '',
                                    medecin_ou_autorite: row.original.medecin_ou_autorite || '',
                                    date_debut: formatDateForInput(row.original.date_debut),
                                    date_fin: formatDateForInput(row.original.date_fin),
                                    neutraliser_coefficient: row.original.neutraliser_coefficient,
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
                            onClick={() => handleDeleteDispense(row.original)}
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
        data: dispenses,
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

    /**
     * Ouvre le Procès-Verbal officiel au format PDF.
     */
    const handlePrintPv = () => {
        const url = `/administration/pedagogie/dispenses/pv-pdf?etablissement_id=${selectedEtab}&annee_scolaire_id=${selectedAnnee}&classe_id=${selectedClasse}&matiere_id=${selectedMatiere}`;
        window.open(url, '_blank');
        toast.success('Procès-Verbal officiel des dispenses généré en PDF.', {
            position: 'top-right',
        });
    };

    return (
        <>
            <Head title="Dérogations & Dispenses Officielles (EPS & Médicales)" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8">
                {/* En-tête de page & navigation */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Button variant="ghost" size="sm" asChild className="h-8 px-2 gap-1.5 text-xs text-muted-foreground hover:text-foreground">
                                <Link href="/administration/notes">
                                    <ArrowLeft className="size-3.5" />
                                    Retour aux Évaluations & Bulletins
                                </Link>
                            </Button>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2.5 rounded-xl bg-linear-to-br from-teal-600 to-emerald-700 text-white shadow-md shadow-teal-500/20">
                                <HeartPulse className="size-6" />
                            </div>
                            <div>
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                                    Dérogations & Dispenses Officielles
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    Dispenses d'Éducation Physique et Sportive (EPS), inaptitudes médicales et neutralisation automatique des coefficients.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Actions principales */}
                    <div className="flex items-center gap-2 flex-wrap">
                        <Button
                            variant="outline"
                            onClick={handlePrintPv}
                            className="gap-2 h-9 text-xs font-semibold border-teal-300 text-teal-700 hover:bg-teal-50 dark:border-teal-800 dark:text-teal-300"
                        >
                            <Printer className="size-3.5" />
                            Imprimer PV Officiel (PDF)
                        </Button>
                        <Button
                            onClick={() => {
                                const firstEps = matieres.find((m) => m.code.includes('EPS') || m.nom.toLowerCase().includes('physique'));
                                setFormData({
                                    inscription_id: inscriptions[0]?.id ? String(inscriptions[0].id) : '',
                                    matiere_id: firstEps?.id ? String(firstEps.id) : (matieres[0]?.id ? String(matieres[0].id) : ''),
                                    periode_id: '',
                                    type_dispense: 'medicale',
                                    motif: 'Inaptitude physique temporaire certifiée par médecin',
                                    reference_certificat: '',
                                    medecin_ou_autorite: '',
                                    date_debut: '',
                                    date_fin: '',
                                    neutraliser_coefficient: true,
                                    statut: 'validee',
                                });
                                setIsCreateOpen(true);
                            }}
                            className="gap-2 h-9 text-xs font-semibold bg-teal-600 hover:bg-teal-700 text-white shadow-sm"
                        >
                            <Plus className="size-4" />
                            Enregistrer une Dispense
                        </Button>
                    </div>
                </div>

                {/* Cartes KPI */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Total Dispenses</p>
                                <h3 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">{stats.total_dispenses}</h3>
                            </div>
                            <div className="p-2.5 rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                                <FileText className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Dispenses EPS (Sport)</p>
                                <h3 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">{stats.total_eps}</h3>
                            </div>
                            <div className="p-2.5 rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                <Activity className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Dispenses Validées</p>
                                <h3 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">{stats.total_validees}</h3>
                            </div>
                            <div className="p-2.5 rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <CheckCircle2 className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">En Attente de Visa</p>
                                <h3 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">{stats.total_en_attente}</h3>
                            </div>
                            <div className="p-2.5 rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                <Clock className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filtres de recherche */}
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

                            {/* Type */}
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Type de dispense</label>
                                <select
                                    value={selectedType}
                                    onChange={(e) => {
                                        setSelectedType(e.target.value);
                                        handleFilterChange({ type_dispense: e.target.value });
                                    }}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-ring"
                                >
                                    <option value="">Tous les types</option>
                                    <option value="medicale">Médicale (Inaptitude EPS)</option>
                                    <option value="administrative">Administrative</option>
                                    <option value="handicap">Handicap permanent</option>
                                    <option value="derogation_special">Dérogation spéciale</option>
                                </select>
                            </div>

                            {/* Statut */}
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Statut</label>
                                <select
                                    value={selectedStatut}
                                    onChange={(e) => {
                                        setSelectedStatut(e.target.value);
                                        handleFilterChange({ statut: e.target.value });
                                    }}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-ring"
                                >
                                    <option value="">Tous les statuts</option>
                                    <option value="validee">Validée</option>
                                    <option value="en_attente">En attente</option>
                                    <option value="rejetee">Rejetée</option>
                                    <option value="expiree">Expirée</option>
                                </select>
                            </div>

                            {/* Recherche textuelle */}
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Recherche</label>
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Élève, matricule, certificat..."
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
                                                <HeartPulse className="size-8 text-muted-foreground/40" />
                                                <p className="text-sm font-medium">Aucune dispense officielle enregistrée pour cette sélection.</p>
                                                <p className="text-xs">Cliquez sur « Enregistrer une Dispense » pour ajouter une inaptitude médicale ou dérogation.</p>
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
                                Page {table.getState().pagination.pageIndex + 1} sur {table.getPageCount()} ({dispenses.length} au total)
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

            {/* MODAL 1 : NOUVELLE DISPENSE */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <HeartPulse className="size-4 text-teal-600" />
                            Enregistrer une Dispense / Dérogation Officielle
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Enregistre une inaptitude médicale physique (EPS) ou dérogation de matière.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateDispense} className="space-y-3.5 py-2">
                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Élève concerné *</label>
                            <select
                                required
                                value={formData.inscription_id}
                                onChange={(e) => setFormData({ ...formData, inscription_id: e.target.value })}
                                className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs font-medium"
                            >
                                <option value="">Sélectionner un élève...</option>
                                {inscriptions.map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.eleve.nom} {i.eleve.prenom} ({i.eleve.matricule} - {i.classe?.nom})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Matière *</label>
                                <select
                                    required
                                    value={formData.matiere_id}
                                    onChange={(e) => setFormData({ ...formData, matiere_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Sélectionner la matière...</option>
                                    {matieres.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.nom} ({m.code})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Type de dispense *</label>
                                <select
                                    value={formData.type_dispense}
                                    onChange={(e) => setFormData({ ...formData, type_dispense: e.target.value as TypeDispense })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="medicale">Médicale (Inaptitude EPS)</option>
                                    <option value="administrative">Administrative</option>
                                    <option value="handicap">Handicap permanent</option>
                                    <option value="derogation_special">Dérogation spéciale</option>
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Période concernée</label>
                                <select
                                    value={formData.periode_id}
                                    onChange={(e) => setFormData({ ...formData, periode_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="">Toute l'année scolaire</option>
                                    {periodes.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.nom}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Statut</label>
                                <select
                                    value={formData.statut}
                                    onChange={(e) => setFormData({ ...formData, statut: e.target.value as StatutDispense })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    <option value="validee">Validée</option>
                                    <option value="en_attente">En attente de visa</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">
                                Motif circonstancié ou diagnostic médical *
                            </label>
                            <Input
                                required
                                placeholder="Ex: Fracture plâtrée du poignet droit, asthme d'effort certifié..."
                                value={formData.motif}
                                onChange={(e) => setFormData({ ...formData, motif: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Réf. Certificat Médical</label>
                                <Input
                                    placeholder="Ex: CM-2026/0412"
                                    value={formData.reference_certificat}
                                    onChange={(e) => setFormData({ ...formData, reference_certificat: e.target.value })}
                                    className="h-9 text-xs font-mono"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Médecin / Hôpital</label>
                                <Input
                                    placeholder="Ex: Dr. TCHATCHOUANG (CHU Yaoundé)"
                                    value={formData.medecin_ou_autorite}
                                    onChange={(e) => setFormData({ ...formData, medecin_ou_autorite: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Date début</label>
                                <Input
                                    type="date"
                                    value={formData.date_debut}
                                    onChange={(e) => setFormData({ ...formData, date_debut: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Date fin (optionnelle)</label>
                                <Input
                                    type="date"
                                    value={formData.date_fin}
                                    onChange={(e) => setFormData({ ...formData, date_fin: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-2 p-2.5 rounded-md bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                            <Checkbox
                                id="neutraliser"
                                checked={formData.neutraliser_coefficient}
                                onCheckedChange={(c) => setFormData({ ...formData, neutraliser_coefficient: !!c })}
                            />
                            <label htmlFor="neutraliser" className="text-xs text-emerald-900 dark:text-emerald-200 cursor-pointer font-medium">
                                Neutraliser le coefficient de la matière sur le bulletin (Recommandé MINESEC)
                            </label>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsCreateOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-teal-600 hover:bg-teal-700 text-white">
                                Valider la Dispense
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 2 : MODIFIER UNE DISPENSE */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Edit2 className="size-4 text-blue-600" />
                            Modifier la Dispense
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleUpdateDispense} className="space-y-3.5 py-2">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Matière *</label>
                                <select
                                    required
                                    value={editFormData.matiere_id}
                                    onChange={(e) => setEditFormData({ ...editFormData, matiere_id: e.target.value })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs"
                                >
                                    {matieres.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.nom} ({m.code})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Statut *</label>
                                <select
                                    value={editFormData.statut}
                                    onChange={(e) => setEditFormData({ ...editFormData, statut: e.target.value as StatutDispense })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs font-medium"
                                >
                                    <option value="validee">Validée</option>
                                    <option value="en_attente">En attente</option>
                                    <option value="rejetee">Rejetée</option>
                                    <option value="expiree">Expirée</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Motif circonstancié *</label>
                            <Input
                                required
                                value={editFormData.motif}
                                onChange={(e) => setEditFormData({ ...editFormData, motif: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Réf. Certificat Médical</label>
                                <Input
                                    value={editFormData.reference_certificat}
                                    onChange={(e) => setEditFormData({ ...editFormData, reference_certificat: e.target.value })}
                                    className="h-9 text-xs font-mono"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Médecin / Hôpital</label>
                                <Input
                                    value={editFormData.medecin_ou_autorite}
                                    onChange={(e) => setEditFormData({ ...editFormData, medecin_ou_autorite: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Date début</label>
                                <Input
                                    type="date"
                                    value={editFormData.date_debut}
                                    onChange={(e) => setEditFormData({ ...editFormData, date_debut: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">Date fin</label>
                                <Input
                                    type="date"
                                    value={editFormData.date_fin}
                                    onChange={(e) => setEditFormData({ ...editFormData, date_fin: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-2 p-2.5 rounded-md bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                            <Checkbox
                                id="neutraliser_edit"
                                checked={editFormData.neutraliser_coefficient}
                                onCheckedChange={(c) => setEditFormData({ ...editFormData, neutraliser_coefficient: !!c })}
                            />
                            <label htmlFor="neutraliser_edit" className="text-xs text-emerald-900 dark:text-emerald-200 cursor-pointer font-medium">
                                Neutraliser le coefficient de la matière sur le bulletin
                            </label>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsEditOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-blue-600 hover:bg-blue-700 text-white">
                                Sauvegarder
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
DispensesIndex.layout = {
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
            title: 'Dérogations & Dispenses Officielles',
            href: '/administration/pedagogie/dispenses',
        },
    ],
};

function ucfirst(str: string): string {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

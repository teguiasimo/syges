import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    AlertTriangle,
    ArrowUpDown,
    BookOpen,
    Briefcase,
    Building2,
    Check,
    CheckCircle2,
    Clock,
    Edit2,
    Eye,
    Filter,
    GraduationCap,
    KeyRound,
    MoreHorizontal,
    Phone,
    Plus,
    RefreshCw,
    Search,
    Shield,
    Trash2,
    UserCheck,
    UserPlus,
    Users,
    UserX,
    X,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/contexts/language-context';
import type {
    Departement,
    Personnel,
    PersonnelFilters,
    PersonnelPageProps,
} from '@/types/personnel';
import { CreatePersonnelModal } from './modals/create-personnel-modal';
import { DepartementModal } from './modals/departement-modal';
import { EditPersonnelModal } from './modals/edit-personnel-modal';
import { PersonnelDetailDrawer } from './modals/personnel-detail-drawer';

/**
 * Fonctions types prédéfinies pour le filtre.
 */
const FONCTIONS_LISTE = [
    'Enseignant',
    'Directeur des Études',
    'Censeur',
    'Surveillant Général',
    'Économe / Comptable',
    'Secrétaire',
    'Documentaliste / Bibliothécaire',
    'Autre',
];

/**
 * Types de contrats pour le filtre.
 */
const CONTRATS_LISTE = [
    'CDI (Contrat à Durée Indéterminée)',
    'CDD (Contrat à Durée Déterminée)',
    'Vacataire / Horaire',
    'Fonctionnaire détaché',
];

/**
 * Statuts possibles pour le filtre et le changement rapide.
 */
const STATUTS_LISTE = [
    'Actif',
    'En congé',
    'Suspendu',
    'Démissionnaire',
    'Retraité',
    'Licencié',
];

/**
 * Page principale du Module 8 : Personnel & Ressources Humaines (RH).
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 8.1, 8.2, 8.3).
 *
 * Utilise la bibliothèque TanStack Table (@tanstack/react-table) pour l'affichage
 * universel des registres du personnel et des départements pédagogiques.
 */
export default function PersonnelsIndex({
    personnels,
    departements,
    etablissements,
    availableUsers,
    roles,
    stats,
    can,
    filters: initialFilters,
}: PersonnelPageProps) {
    const { t } = useTranslation();

    // Onglet actif : 'personnel' | 'departements'
    const [activeTab, setActiveTab] = useState<'personnel' | 'departements'>('personnel');

    // États d'ouverture des modales
    const [isCreatePersonnelOpen, setIsCreatePersonnelOpen] = useState(false);
    const [isEditPersonnelOpen, setIsEditPersonnelOpen] = useState(false);
    const [selectedPersonnelForEdit, setSelectedPersonnelForEdit] = useState<Personnel | null>(null);

    const [isDetailOpen, setIsDetailOpen] = useState(false);
    const [selectedPersonnelForDetail, setSelectedPersonnelForDetail] = useState<Personnel | null>(null);

    const [isDepartementModalOpen, setIsDepartementModalOpen] = useState(false);
    const [selectedDepartementForEdit, setSelectedDepartementForEdit] = useState<Departement | null>(null);

    // Modale de changement de statut rapide
    const [personnelToToggleStatus, setPersonnelToToggleStatus] = useState<Personnel | null>(null);
    const [newStatusValue, setNewStatusValue] = useState<string>('Actif');

    // Modales de suppression / archivage
    const [personnelToDelete, setPersonnelToDelete] = useState<Personnel | null>(null);
    const [departementToDelete, setDepartementToDelete] = useState<Departement | null>(null);

    // Filtres d'affichage locaux
    const [filterEtablissement, setFilterEtablissement] = useState<string>(initialFilters?.etablissement_id || 'all');
    const [filterDepartement, setFilterDepartement] = useState<string>(initialFilters?.departement_id || 'all');
    const [filterFonction, setFilterFonction] = useState<string>(initialFilters?.fonction || 'all');
    const [filterContrat, setFilterContrat] = useState<string>(initialFilters?.contrat || 'all');
    const [filterStatut, setFilterStatut] = useState<string>(initialFilters?.statut || 'all');

    /**
     * Application des filtres côté client pour une réactivité instantanée.
     */
    const filteredPersonnels = useMemo(() => {
        return personnels.filter((p) => {
            if (filterEtablissement !== 'all' && String(p.etablissement_id) !== filterEtablissement) {
                return false;
            }
            if (filterDepartement !== 'all') {
                if (filterDepartement === 'none' && p.departement_id !== null) return false;
                if (filterDepartement !== 'none' && String(p.departement_id) !== filterDepartement) return false;
            }
            if (filterFonction !== 'all' && p.fonction_principale !== filterFonction) {
                return false;
            }
            if (filterContrat !== 'all' && p.type_contrat !== filterContrat) {
                return false;
            }
            if (filterStatut !== 'all' && p.statut !== filterStatut) {
                return false;
            }
            return true;
        });
    }, [personnels, filterEtablissement, filterDepartement, filterFonction, filterContrat, filterStatut]);

    /**
     * Réinitialisation de l'ensemble des filtres.
     */
    const handleResetFilters = () => {
        setFilterEtablissement('all');
        setFilterDepartement('all');
        setFilterFonction('all');
        setFilterContrat('all');
        setFilterStatut('all');
    };

    /**
     * Confirmation de changement de statut du personnel.
     */
    const handleConfirmStatusChange = () => {
        if (!personnelToToggleStatus) return;

        router.patch(
            `/administration/personnels/${personnelToToggleStatus.id}/toggle-status`,
            { statut: newStatusValue },
            {
                onSuccess: () => {
                    toast.success('Statut mis à jour !', {
                        description: `Le statut de ${personnelToToggleStatus.nom} a été basculé sur "${newStatusValue}".`,
                    });
                    setPersonnelToToggleStatus(null);
                },
                onError: () => {
                    toast.error('Erreur lors du changement de statut');
                },
            }
        );
    };

    /**
     * Confirmation d'archivage / suppression de la fiche d'un personnel.
     */
    const handleConfirmDeletePersonnel = () => {
        if (!personnelToDelete) return;

        router.delete(`/administration/personnels/${personnelToDelete.id}`, {
            onSuccess: () => {
                toast.success('Fiche archivée avec succès !', {
                    description: `Le membre du personnel ${personnelToDelete.nom} a été archivé.`,
                });
                setPersonnelToDelete(null);
            },
            onError: () => {
                toast.error('Impossible d\'archiver ce personnel');
            },
        });
    };

    /**
     * Confirmation de suppression d'un département pédagogique.
     */
    const handleConfirmDeleteDepartement = () => {
        if (!departementToDelete) return;

        router.delete(`/administration/personnels/departements/${departementToDelete.id}`, {
            onSuccess: () => {
                toast.success('Département supprimé avec succès !', {
                    description: `Le département ${departementToDelete.nom} a été retiré.`,
                });
                setDepartementToDelete(null);
            },
            onError: () => {
                toast.error('Erreur lors de la suppression du département');
            },
        });
    };

    // ==========================================
    // DÉFINITION DES COLONNES TANSTACK TABLE - PERSONNEL
    // ==========================================
    const personnelColumns = useMemo<ColumnDef<Personnel>[]>(() => {
        return [
            {
                accessorKey: 'matricule',
                header: () => <span className="font-semibold text-xs">Matricule</span>,
                cell: ({ row }) => (
                    <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-muted text-muted-foreground border">
                        {row.original.matricule}
                    </span>
                ),
            },
            {
                accessorKey: 'nom_complet',
                header: () => <span className="font-semibold text-xs">Nom & Prénoms</span>,
                cell: ({ row }) => {
                    const p = row.original;
                    return (
                        <div className="flex items-center gap-2.5">
                            <div className="size-8 rounded-full bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-300 font-bold text-xs flex items-center justify-center border border-teal-200 dark:border-teal-800">
                                {p.nom.slice(0, 1)}
                                {p.prenom ? p.prenom.slice(0, 1) : ''}
                            </div>
                            <div>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedPersonnelForDetail(p);
                                        setIsDetailOpen(true);
                                    }}
                                    className="font-medium text-foreground hover:text-teal-600 dark:hover:text-teal-400 hover:underline text-left cursor-pointer text-xs sm:text-sm block"
                                >
                                    {p.nom} {p.prenom}
                                </button>
                                <span className="text-[11px] text-muted-foreground">
                                    {p.sexe === 'M' ? 'Homme' : 'Femme'}
                                </span>
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'fonction_principale',
                header: () => <span className="font-semibold text-xs">Fonction & Contrat</span>,
                cell: ({ row }) => {
                    const p = row.original;
                    return (
                        <div className="space-y-0.5">
                            <span className="font-medium text-xs text-foreground block">
                                {p.fonction_principale}
                            </span>
                            <span className="text-[11px] text-muted-foreground block truncate max-w-35">
                                {p.type_contrat}
                            </span>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'etablissement.nom',
                header: () => <span className="font-semibold text-xs">Établissement & Dpt</span>,
                cell: ({ row }) => {
                    const p = row.original;
                    return (
                        <div className="space-y-0.5 text-xs">
                            <span className="font-medium text-foreground block truncate max-w-32.5">
                                {p.etablissement ? p.etablissement.nom : '-'}
                            </span>
                            <span className="text-[11px] text-muted-foreground block truncate max-w-32.5">
                                {p.departement ? p.departement.nom : <em className="text-muted-foreground/60">Sans département</em>}
                            </span>
                        </div>
                    );
                },
            },
            {
                id: 'charge_enseignement',
                header: () => <span className="font-semibold text-xs">Enseignement</span>,
                cell: ({ row }) => {
                    const p = row.original;
                    if (!p.is_enseignant) {
                        return <span className="text-muted-foreground/60 text-xs">Administratif</span>;
                    }
                    return (
                        <div className="space-y-0.5">
                            <Badge variant="outline" className="bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/40 dark:text-teal-400 dark:border-teal-800 text-[11px] font-medium">
                                <GraduationCap className="size-3 mr-1" />
                                {p.volume_horaire_hebdo}h / sem.
                            </Badge>
                            {p.specialite && (
                                <span className="text-[11px] text-muted-foreground block truncate max-w-30">
                                    {p.specialite}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'telephone',
                header: () => <span className="font-semibold text-xs">Contact</span>,
                cell: ({ row }) => {
                    const p = row.original;
                    return (
                        <div className="text-xs space-y-0.5">
                            <a href={`tel:${p.telephone}`} className="text-foreground hover:text-teal-600 flex items-center gap-1 font-mono">
                                <Phone className="size-3 text-muted-foreground" />
                                {p.telephone}
                            </a>
                            {p.email && (
                                <span className="text-[11px] text-muted-foreground block truncate max-w-32.5">
                                    {p.email}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'compte_syges',
                header: () => <span className="font-semibold text-xs">Compte SYGES</span>,
                cell: ({ row }) => {
                    const p = row.original;
                    if (p.user) {
                        return (
                            <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800 text-[11px] font-medium inline-flex items-center gap-1">
                                <KeyRound className="size-3 text-emerald-600" />
                                {p.user.login}
                            </Badge>
                        );
                    }
                    return (
                        <span className="text-[11px] text-muted-foreground/60 italic">
                            Non lié
                        </span>
                    );
                },
            },
            {
                accessorKey: 'statut',
                header: () => <span className="font-semibold text-xs">Statut</span>,
                cell: ({ row }) => {
                    const statut = row.original.statut;
                    let colorClass = 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300';
                    if (statut === 'Actif') {
                        colorClass = 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800';
                    } else if (statut === 'En congé') {
                        colorClass = 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-400 dark:border-blue-800';
                    } else if (statut === 'Suspendu') {
                        colorClass = 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800';
                    }

                    return (
                        <button
                            type="button"
                            onClick={() => {
                                setPersonnelToToggleStatus(row.original);
                                setNewStatusValue(statut);
                            }}
                            title="Cliquer pour changer le statut"
                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border cursor-pointer hover:opacity-80 transition-opacity ${colorClass}`}
                        >
                            {statut}
                        </button>
                    );
                },
            },
            {
                id: 'actions',
                header: () => <div className="text-right pr-2 font-semibold text-xs">Actions</div>,
                cell: ({ row }) => {
                    const p = row.original;
                    return (
                        <div className="flex items-center justify-end gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => {
                                    setSelectedPersonnelForDetail(p);
                                    setIsDetailOpen(true);
                                }}
                                title="Voir la fiche détaillée"
                                className="size-8 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted cursor-pointer"
                            >
                                <Eye className="size-4" />
                            </Button>

                            {can.manage && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => {
                                        setSelectedPersonnelForEdit(p);
                                        setIsEditPersonnelOpen(true);
                                    }}
                                    title="Modifier la fiche"
                                    className="size-8 rounded-lg text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/50 cursor-pointer"
                                >
                                    <Edit2 className="size-4" />
                                </Button>
                            )}

                            {can.delete && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setPersonnelToDelete(p)}
                                    title="Archiver ce personnel"
                                    className="size-8 rounded-lg text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 cursor-pointer"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    );
                },
            },
        ];
    }, [can]);

    // ==========================================
    // DÉFINITION DES COLONNES TANSTACK TABLE - DÉPARTEMENTS
    // ==========================================
    const departementColumns = useMemo<ColumnDef<Departement>[]>(() => {
        return [
            {
                accessorKey: 'code',
                header: () => <span className="font-semibold text-xs">Code</span>,
                cell: ({ row }) => (
                    <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-muted text-muted-foreground border">
                        {row.original.code || '-'}
                    </span>
                ),
            },
            {
                accessorKey: 'nom',
                header: () => <span className="font-semibold text-xs">Nom du Département</span>,
                cell: ({ row }) => {
                    const dep = row.original;
                    return (
                        <div>
                            <span className="font-semibold text-sm text-foreground block">
                                {dep.nom}
                            </span>
                            {dep.description && (
                                <span className="text-xs text-muted-foreground line-clamp-1">
                                    {dep.description}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'chef',
                header: () => <span className="font-semibold text-xs">Chef de Département</span>,
                cell: ({ row }) => {
                    const dep = row.original;
                    if (dep.chef_departement) {
                        return (
                            <div className="flex items-center gap-2 text-xs font-medium text-foreground">
                                <div className="size-6 rounded-full bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-300 font-bold text-[10px] flex items-center justify-center">
                                    {dep.chef_departement.nom.slice(0, 1)}
                                </div>
                                <span>
                                    {dep.chef_departement.nom} {dep.chef_departement.prenom}
                                </span>
                            </div>
                        );
                    }
                    return (
                        <span className="text-xs text-muted-foreground/60 italic">
                            Non désigné
                        </span>
                    );
                },
            },
            {
                accessorKey: 'personnels_count',
                header: () => <span className="font-semibold text-xs">Effectif Rattaché</span>,
                cell: ({ row }) => (
                    <Badge variant="outline" className="text-xs font-semibold">
                        <Users className="size-3 mr-1 text-teal-600" />
                        {row.original.personnels_count ?? 0} enseignant(s)
                    </Badge>
                ),
            },
            {
                accessorKey: 'is_active',
                header: () => <span className="font-semibold text-xs">Statut</span>,
                cell: ({ row }) => (
                    <Badge
                        className={`text-xs font-medium ${
                            row.original.is_active
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800'
                                : 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400'
                        }`}
                    >
                        {row.original.is_active ? 'Actif' : 'Inactif'}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: () => <div className="text-right pr-2 font-semibold text-xs">Actions</div>,
                cell: ({ row }) => {
                    const dep = row.original;
                    return (
                        <div className="flex items-center justify-end gap-1">
                            {can.departements && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => {
                                        setSelectedDepartementForEdit(dep);
                                        setIsDepartementModalOpen(true);
                                    }}
                                    title="Modifier le département"
                                    className="size-8 rounded-lg text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/50 cursor-pointer"
                                >
                                    <Edit2 className="size-4" />
                                </Button>
                            )}

                            {can.departements && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setDepartementToDelete(dep)}
                                    title="Supprimer le département"
                                    className="size-8 rounded-lg text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 cursor-pointer"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    );
                },
            },
        ];
    }, [can]);

    return (
        <>
            <Head title="Personnel & Ressources Humaines - Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full">
                {/* EN-TÊTE PRINCIPAL */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-xl bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400 border border-teal-200 dark:border-teal-900/50">
                                <Briefcase className="size-7" />
                            </div>
                            <div>
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                                    Personnel & Ressources Humaines
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground mt-0.5">
                                    Gestion du personnel, contrats, qualifications pédagogiques et départements.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5 flex-wrap">
                        {can.departements && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setSelectedDepartementForEdit(null);
                                    setIsDepartementModalOpen(true);
                                }}
                                className="cursor-pointer font-medium text-xs sm:text-sm"
                            >
                                <BookOpen className="size-4 mr-1.5 text-teal-600" />
                                Nouveau département
                            </Button>
                        )}

                        {can.manage && (
                            <Button
                                type="button"
                                onClick={() => setIsCreatePersonnelOpen(true)}
                                className="bg-teal-600 hover:bg-teal-700 text-white cursor-pointer font-semibold shadow-sm text-xs sm:text-sm"
                            >
                                <UserPlus className="size-4 mr-1.5" />
                                Nouveau personnel
                            </Button>
                        )}
                    </div>
                </div>

                {/* KPI SYNTHÈSE STATISTIQUE */}
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Effectif Total
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-foreground">{stats.total}</span>
                            <Users className="size-4 text-muted-foreground" />
                        </div>
                    </div>

                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Personnel Actif
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-emerald-600">{stats.actifs}</span>
                            <UserCheck className="size-4 text-emerald-600" />
                        </div>
                    </div>

                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Enseignants
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-teal-600">{stats.enseignants}</span>
                            <GraduationCap className="size-4 text-teal-600" />
                        </div>
                    </div>

                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Départements
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-blue-600">{stats.departementsCount}</span>
                            <BookOpen className="size-4 text-blue-600" />
                        </div>
                    </div>

                    <div className="p-4 rounded-xl bg-card border shadow-xs space-y-1 col-span-2 sm:col-span-1">
                        <span className="text-[11px] text-muted-foreground uppercase font-semibold tracking-wider">
                            Comptes SYGES
                        </span>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl font-bold text-purple-600">{stats.avecCompte}</span>
                            <KeyRound className="size-4 text-purple-600" />
                        </div>
                    </div>
                </div>

                {/* ONGLET DE NAVIGATION */}
                <div className="flex items-center gap-2 border-b">
                    <button
                        type="button"
                        onClick={() => setActiveTab('personnel')}
                        className={`pb-3 px-4 font-semibold text-sm transition-colors border-b-2 cursor-pointer flex items-center gap-2 ${
                            activeTab === 'personnel'
                                ? 'border-teal-600 text-teal-600 dark:text-teal-400'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Users className="size-4" />
                        <span>Registre du Personnel ({personnels.length})</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('departements')}
                        className={`pb-3 px-4 font-semibold text-sm transition-colors border-b-2 cursor-pointer flex items-center gap-2 ${
                            activeTab === 'departements'
                                ? 'border-teal-600 text-teal-600 dark:text-teal-400'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <BookOpen className="size-4" />
                        <span>Départements Pédagogiques ({departements.length})</span>
                    </button>
                </div>

                {/* CONTENU ONGLET 1 : REGISTRE DU PERSONNEL */}
                {activeTab === 'personnel' && (
                    <div className="space-y-4">
                        <DataTable
                            columns={personnelColumns}
                            data={filteredPersonnels}
                            searchPlaceholder="Rechercher par nom, matricule, téléphone..."
                            filterSlot={
                                <div className="flex flex-wrap items-center gap-2">
                                    {/* Filtre Établissement */}
                                    <Select
                                        value={filterEtablissement}
                                        onValueChange={setFilterEtablissement}
                                    >
                                        <SelectTrigger className="h-9 w-37.5 text-xs bg-card">
                                            <SelectValue placeholder="Établissement" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les campus</SelectItem>
                                            {etablissements.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)}>
                                                    {e.code}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    {/* Filtre Département */}
                                    <Select
                                        value={filterDepartement}
                                        onValueChange={setFilterDepartement}
                                    >
                                        <SelectTrigger className="h-9 w-37.5 text-xs bg-card">
                                            <SelectValue placeholder="Département" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les départements</SelectItem>
                                            <SelectItem value="none">Sans département</SelectItem>
                                            {departements.map((d) => (
                                                <SelectItem key={d.id} value={String(d.id)}>
                                                    {d.code || d.nom}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    {/* Filtre Fonction */}
                                    <Select
                                        value={filterFonction}
                                        onValueChange={setFilterFonction}
                                    >
                                        <SelectTrigger className="h-9 w-35 text-xs bg-card">
                                            <SelectValue placeholder="Fonction" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Toutes fonctions</SelectItem>
                                            {FONCTIONS_LISTE.map((fn) => (
                                                <SelectItem key={fn} value={fn}>
                                                    {fn}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    {/* Filtre Statut */}
                                    <Select
                                        value={filterStatut}
                                        onValueChange={setFilterStatut}
                                    >
                                        <SelectTrigger className="h-9 w-30 text-xs bg-card">
                                            <SelectValue placeholder="Statut" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous statuts</SelectItem>
                                            {STATUTS_LISTE.map((st) => (
                                                <SelectItem key={st} value={st}>
                                                    {st}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    {/* Bouton de réinitialisation si un filtre est actif */}
                                    {(filterEtablissement !== 'all' ||
                                        filterDepartement !== 'all' ||
                                        filterFonction !== 'all' ||
                                        filterStatut !== 'all') && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={handleResetFilters}
                                            className="h-9 text-xs text-muted-foreground hover:text-foreground cursor-pointer"
                                        >
                                            <RefreshCw className="size-3 mr-1" />
                                            Réinitialiser
                                        </Button>
                                    )}
                                </div>
                            }
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 2 : DÉPARTEMENTS PÉDAGOGIQUES */}
                {activeTab === 'departements' && (
                    <div className="space-y-4">
                        <DataTable
                            columns={departementColumns}
                            data={departements}
                            searchPlaceholder="Rechercher un département..."
                        />
                    </div>
                )}
            </div>

            {/* MODALE DE CRÉATION DE PERSONNEL */}
            <CreatePersonnelModal
                isOpen={isCreatePersonnelOpen}
                onClose={() => setIsCreatePersonnelOpen(false)}
                etablissements={etablissements}
                departements={departements}
                availableUsers={availableUsers}
                roles={roles}
            />

            {/* MODALE DE MODIFICATION DE PERSONNEL */}
            <EditPersonnelModal
                personnel={selectedPersonnelForEdit}
                isOpen={isEditPersonnelOpen}
                onClose={() => {
                    setIsEditPersonnelOpen(false);
                    setSelectedPersonnelForEdit(null);
                }}
                etablissements={etablissements}
                departements={departements}
                availableUsers={availableUsers}
            />

            {/* TIROIR / MODALE DE DÉTAIL DU PERSONNEL */}
            <PersonnelDetailDrawer
                personnel={selectedPersonnelForDetail}
                isOpen={isDetailOpen}
                onClose={() => {
                    setIsDetailOpen(false);
                    setSelectedPersonnelForDetail(null);
                }}
                onEdit={(p) => {
                    setSelectedPersonnelForEdit(p);
                    setIsEditPersonnelOpen(true);
                }}
            />

            {/* MODALE DE CRÉATION / MODIFICATION DE DÉPARTEMENT */}
            <DepartementModal
                departement={selectedDepartementForEdit}
                isOpen={isDepartementModalOpen}
                onClose={() => {
                    setIsDepartementModalOpen(false);
                    setSelectedDepartementForEdit(null);
                }}
                personnels={personnels}
            />

            {/* MODALE DE CONFIRMATION DU CHANGEMENT DE STATUT */}
            <Dialog
                open={!!personnelToToggleStatus}
                onOpenChange={(open) => !open && setPersonnelToToggleStatus(null)}
            >
                <DialogContent className="max-w-md p-6">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            Changer le statut de l'employé
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground mt-1">
                            Modifier le statut administratif de{' '}
                            <strong>
                                {personnelToToggleStatus?.nom} {personnelToToggleStatus?.prenom}
                            </strong>{' '}
                            ({personnelToToggleStatus?.matricule}).
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-3">
                        <Label htmlFor="quick_status" className="text-xs font-semibold">
                            Nouveau statut administratif
                        </Label>
                        <Select value={newStatusValue} onValueChange={setNewStatusValue}>
                            <SelectTrigger id="quick_status" className="mt-1 h-9 text-sm">
                                <SelectValue placeholder="Sélectionner un statut" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUTS_LISTE.map((st) => (
                                    <SelectItem key={st} value={st}>
                                        {st}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPersonnelToToggleStatus(null)}
                            className="cursor-pointer"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="button"
                            onClick={handleConfirmStatusChange}
                            className="bg-teal-600 hover:bg-teal-700 text-white cursor-pointer font-semibold"
                        >
                            Confirmer la modification
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* MODALE DE CONFIRMATION DE SUPPRESSION / ARCHIVAGE PERSONNEL */}
            <Dialog
                open={!!personnelToDelete}
                onOpenChange={(open) => !open && setPersonnelToDelete(null)}
            >
                <DialogContent className="max-w-md p-6">
                    <DialogHeader>
                        <div className="size-10 rounded-full bg-rose-100 dark:bg-rose-950/50 text-rose-600 flex items-center justify-center mb-2">
                            <AlertTriangle className="size-5" />
                        </div>
                        <DialogTitle className="text-lg font-bold text-foreground">
                            Archiver ce membre du personnel ?
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground mt-1">
                            Êtes-vous sûr de vouloir archiver la fiche de{' '}
                            <strong className="text-foreground">
                                {personnelToDelete?.nom} {personnelToDelete?.prenom}
                            </strong>{' '}
                            (Matricule : {personnelToDelete?.matricule}) ?
                            <br />
                            Cette action effectue un archivage sécurisé (Soft Delete). Les cours et historiques passés resteront intacts.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2 mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPersonnelToDelete(null)}
                            className="cursor-pointer"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleConfirmDeletePersonnel}
                            className="cursor-pointer font-semibold"
                        >
                            Confirmer l'archivage
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* MODALE DE CONFIRMATION DE SUPPRESSION DÉPARTEMENT */}
            <Dialog
                open={!!departementToDelete}
                onOpenChange={(open) => !open && setDepartementToDelete(null)}
            >
                <DialogContent className="max-w-md p-6">
                    <DialogHeader>
                        <div className="size-10 rounded-full bg-rose-100 dark:bg-rose-950/50 text-rose-600 flex items-center justify-center mb-2">
                            <AlertTriangle className="size-5" />
                        </div>
                        <DialogTitle className="text-lg font-bold text-foreground">
                            Supprimer ce département pédagogique ?
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground mt-1">
                            Voulez-vous supprimer le département{' '}
                            <strong className="text-foreground">{departementToDelete?.nom}</strong> ?
                            <br />
                            Les enseignants rattachés seront dissociés de ce département mais conservés dans le système.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2 mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDepartementToDelete(null)}
                            className="cursor-pointer"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleConfirmDeleteDepartement}
                            className="cursor-pointer font-semibold"
                        >
                            Supprimer le département
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Fil d'Ariane (Breadcrumbs) pour la page de gestion du personnel.
 */
PersonnelsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Personnel & RH',
            href: '/administration/personnels',
        },
    ],
};

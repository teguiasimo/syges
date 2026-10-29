import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    Users,
    UserPlus,
    UserCheck,
    UserX,
    Shield,
    ShieldCheck,
    Mail,
    Lock,
    Edit2,
    Check,
    X,
    Crown,
    Globe,
    ArrowUpDown,
    CheckCircle2,
    XCircle,
    KeyRound,
    RefreshCw,
    AlertTriangle,
    Eye,
    EyeOff,
    Building2,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MultiSelect, type MultiSelectOption } from '@/components/ui/multi-select';
import { truncateText } from '@/lib/utils';

/**
 * Interface décrivant un compte utilisateur de la plateforme SYGES.
 */
export interface UserItem {
    /** Identifiant numérique unique */
    id: number;
    /** Nom complet de l'utilisateur */
    name: string;
    /** Identifiant de connexion unique */
    login: string;
    /** Adresse email unique */
    email: string;
    /** Indique si le compte est actif ou suspendu */
    isActive: boolean;
    /** Identifiant de l'établissement / campus de rattachement */
    etablissementId?: number | null;
    /** Nom du campus de rattachement */
    etablissementNom?: string | null;
    /** Code du campus de rattachement */
    etablissementCode?: string | null;
    /** Noms techniques des rôles Spatie attribués */
    roles: string[];
    /** Libellés conviviaux des rôles pour l'affichage */
    rolesDisplay: string[];
    /** Vrai si le compte est Super Administrateur */
    isSuperAdmin: boolean;
    /** Vrai s'il s'agit du compte actuellement connecté */
    isCurrentAuth: boolean;
    /** Date de création du compte formatée (dd/mm/yyyy) */
    createdAt?: string;
}

/**
 * Interface décrivant un rôle de sécurité disponible dans le système.
 */
export interface AvailableRole {
    /** Identifiant numérique du rôle */
    id: number;
    /** Identifiant technique (slug) */
    name: string;
    /** Libellé convivial (ex: "Directeur des Études") */
    displayName: string;
}

/**
 * Interface décrivant un établissement / campus succinct.
 */
export interface EtablissementOption {
    id: number;
    nom: string;
    code?: string;
}

/**
 * Propriétés reçues par la vue Inertia depuis UserController.
 */
interface UsersPageProps {
    users: UserItem[];
    availableRoles: AvailableRole[];
    etablissements?: EtablissementOption[];
    currentEtablissementId?: number | null;
    isSuperAdmin?: boolean;
    can?: {
        create?: boolean;
        edit?: boolean;
        block?: boolean;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

/**
 * Page de gestion des Utilisateurs de la plateforme SYGES.
 *
 * Utilise la bibliothèque TanStack Table (@tanstack/react-table) pour l'affichage
 * universel des données sous forme de tableau interactif (tri, recherche, pagination).
 *
 * Permet :
 * - La consultation de la liste des utilisateurs avec leurs rôles, statuts et campus de rattachement.
 * - Le filtrage par rôle et par statut d'activation (Actif / Bloqué).
 * - La création d'un utilisateur avec login, email, mot de passe, confirmation, établissement et rôles.
 * - La modification d'un utilisateur existant.
 * - Le blocage / déblocage instantané avec modale de confirmation.
 * - La suppression définitive avec sécurités sur le super-admin et l'utilisateur connecté.
 */
export default function UsersIndex({
    users,
    availableRoles,
    etablissements = [],
    currentEtablissementId,
    isSuperAdmin = false,
    can,
}: UsersPageProps) {
    const { flash } = usePage<{ flash?: { success?: string | null; error?: string | null } }>().props;

    // Habilitations de l'utilisateur connecté
    const canCreate = can?.create ?? true;
    const canEdit = can?.edit ?? true;
    const canBlock = can?.block ?? true;

    // États d'ouverture des modales
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [userToToggle, setUserToToggle] = useState<UserItem | null>(null);
    const [userToEdit, setUserToEdit] = useState<UserItem | null>(null);

    // Visibilité des mots de passe dans les formulaires
    const [showPassword, setShowPassword] = useState(false);
    const [showPasswordConfirm, setShowPasswordConfirm] = useState(false);

    // Filtre par statut pour la barre d'outils
    const [selectedStatusFilter, setSelectedStatusFilter] = useState<string>('all');

    // Formulaire de création
    const createForm = useForm({
        name: '',
        login: '',
        email: '',
        password: '',
        password_confirmation: '',
        etablissement_id: (currentEtablissementId ? String(currentEtablissementId) : '') as string,
        roles: [] as string[],
        is_active: true,
    });

    // Formulaire de modification
    const editForm = useForm({
        name: '',
        login: '',
        email: '',
        password: '',
        password_confirmation: '',
        etablissement_id: '' as string,
        roles: [] as string[],
    });

    // Écoute des messages flash du serveur pour afficher les notifications Toast conformes aux règles SYGES (top-right + vert succès)
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, {
                position: 'top-right',
                className: 'bg-emerald-600 text-white border-none',
            });
        }
        if (flash?.error) {
            toast.error(flash.error, {
                position: 'top-right',
            });
        }
    }, [flash]);

    /**
     * Calcule les métriques globales affichées dans les cartes KPI supérieures.
     */
    const stats = useMemo(() => {
        const total = users.length;
        const active = users.filter((u) => u.isActive).length;
        const blocked = total - active;
        const withRoles = users.filter((u) => u.roles.length > 0).length;

        return { total, active, blocked, withRoles };
    }, [users]);

    /**
     * Données filtrées selon les filtres secondaires (rôle, statut).
     */
    const filteredData = useMemo(() => {
        return users.filter((user) => {
            // Filtre par statut d'activation
            if (selectedStatusFilter === 'active' && !user.isActive) {
                return false;
            }
            if (selectedStatusFilter === 'blocked' && user.isActive) {
                return false;
            }
            return true;
        });
    }, [users, selectedStatusFilter]);

    /**
     * Options de rôles formatées pour le composant MultiSelect (création).
     */
    const roleOptions: MultiSelectOption[] = useMemo(() => {
        return availableRoles.map((role) => ({
            value: role.name,
            label: role.displayName,
        }));
    }, [availableRoles]);

    /**
     * Options de rôles pour la modification, avec protection du rôle Super Admin.
     */
    const editRoleOptions: MultiSelectOption[] = useMemo(() => {
        return availableRoles.map((role) => ({
            value: role.name,
            label: role.displayName,
            disabled: userToEdit?.isSuperAdmin && role.name === 'super-admin',
            description:
                userToEdit?.isSuperAdmin && role.name === 'super-admin'
                    ? 'Rôle protégé pour le Super Administrateur'
                    : undefined,
        }));
    }, [availableRoles, userToEdit]);

    /**
     * Ouvre la modale de modification en pré-remplissant les données de l'utilisateur.
     */
    const handleOpenEdit = (user: UserItem) => {
        setUserToEdit(user);
        editForm.setData({
            name: user.name,
            login: user.login,
            email: user.email,
            password: '',
            password_confirmation: '',
            etablissement_id: user.etablissementId ? String(user.etablissementId) : '',
            roles: [...user.roles],
        });
        editForm.clearErrors();
        setShowPassword(false);
        setShowPasswordConfirm(false);
        setIsEditModalOpen(true);
    };

    /**
     * Soumission du formulaire de création d'utilisateur.
     */
    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/administration/users', {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateModalOpen(false);
                createForm.reset();
            },
        });
    };

    /**
     * Soumission du formulaire de modification d'utilisateur.
     */
    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!userToEdit) return;

        editForm.put(`/administration/users/${userToEdit.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsEditModalOpen(false);
                setUserToEdit(null);
                editForm.reset();
            },
        });
    };

    /**
     * Exécute le basculement du statut (Actif / Bloqué).
     */
    const handleConfirmToggleStatus = () => {
        if (!userToToggle) return;

        router.patch(
            `/administration/users/${userToToggle.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setUserToToggle(null);
                },
            }
        );
    };



    /**
     * Génère les initiales à partir du nom complet pour l'avatar.
     */
    const getInitials = (name: string): string => {
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) {
            return parts[0].substring(0, 2).toUpperCase();
        }
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    };

    /**
     * Définition des colonnes du tableau TanStack Table.
     */
    const columns: ColumnDef<UserItem>[] = useMemo(() => {
        const baseColumns: ColumnDef<UserItem>[] = [
            {
                accessorKey: 'name',
                header: ({ column }) => {
                    return (
                        <button
                            type="button"
                            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                            className="flex items-center gap-1 hover:text-foreground transition-colors cursor-pointer"
                        >
                            <span>Utilisateur</span>
                            <ArrowUpDown className="size-3.5" />
                        </button>
                    );
                },
                cell: ({ row }) => {
                    const user = row.original;
                    const initials = getInitials(user.name);

                    return (
                        <div className="flex items-center gap-3">
                            {/* Avatar personnalisé */}
                            <div
                                className={`flex size-9 shrink-0 items-center justify-center rounded-full font-semibold text-xs border ${
                                    user.isSuperAdmin
                                        ? 'bg-amber-100 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800'
                                        : user.isActive
                                        ? 'bg-emerald-100 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800'
                                        : 'bg-muted text-muted-foreground border-border'
                                }`}
                            >
                                {initials}
                            </div>

                            <div className="min-w-0 flex flex-col">
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-foreground truncate text-sm">
                                        {user.name}
                                    </span>
                                    {user.isCurrentAuth && (
                                        <Badge
                                            variant="secondary"
                                            className="text-[10px] px-1.5 py-0 bg-primary/10 text-primary font-medium border-primary/20"
                                        >
                                            Vous
                                        </Badge>
                                    )}
                                </div>
                                <span className="text-xs text-muted-foreground truncate flex items-center gap-1">
                                    <Mail className="size-3 shrink-0" />
                                    {user.email}
                                </span>
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'login',
                header: ({ column }) => {
                    return (
                        <button
                            type="button"
                            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                            className="flex items-center gap-1 hover:text-foreground transition-colors cursor-pointer"
                        >
                            <span>Login</span>
                            <ArrowUpDown className="size-3.5" />
                        </button>
                    );
                },
                cell: ({ row }) => {
                    const login = row.original.login;
                    return (
                        <span className="inline-flex rounded-md bg-muted/50 px-2 py-0.5 font-mono text-xs font-semibold text-foreground border border-border/60">
                            {login}
                        </span>
                    );
                },
            },
            {
                accessorKey: 'etablissementNom',
                header: ({ column }) => {
                    return (
                        <button
                            type="button"
                            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                            className="flex items-center gap-1 hover:text-foreground transition-colors cursor-pointer"
                        >
                            <span>Campus</span>
                            <ArrowUpDown className="size-3.5" />
                        </button>
                    );
                },
                cell: ({ row }) => {
                    const user = row.original;
                    if (user.isSuperAdmin || !user.etablissementId) {
                        return (
                            <Badge
                                variant="outline"
                                className="text-[11px] font-medium py-0.5 px-2 bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-800 flex items-center gap-1 w-fit"
                                title="Accès transversal à l'ensemble des campus (Super-Admin)"
                            >
                                <Globe className="size-3 text-teal-600 shrink-0" />
                                <span>Accès Global</span>
                            </Badge>
                        );
                    }

                    // Affiche le code court de l'établissement en priorité pour alléger le tableau
                    const codeDisplay = user.etablissementCode || (user.etablissementNom ? truncateText(user.etablissementNom, 18) : 'Campus');

                    return (
                        <div className="flex items-center" title={user.etablissementNom || undefined}>
                            <Badge
                                variant="outline"
                                className="text-[11px] font-semibold py-0.5 px-2 bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800 flex items-center gap-1.5 w-fit max-w-[180px] hover:bg-sky-100 transition-colors cursor-help"
                            >
                                <Building2 className="size-3 text-sky-500 shrink-0" />
                                <span className="truncate">{codeDisplay}</span>
                            </Badge>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'roles',
                header: 'Rôles attribués',
                cell: ({ row }) => {
                    const user = row.original;

                    if (!user.rolesDisplay || user.rolesDisplay.length === 0) {
                        return (
                            <span className="text-xs text-muted-foreground/70 italic">
                                Aucun rôle
                            </span>
                        );
                    }

                    return (
                        <div className="flex flex-wrap gap-1.5 max-w-xs">
                            {user.rolesDisplay.map((roleName, index) => {
                                const isSuper = user.isSuperAdmin && user.roles[index] === 'super-admin';
                                const isAdmin = user.roles[index] === 'admin';

                                return (
                                    <Badge
                                        key={index}
                                        variant="outline"
                                        className={`text-[11px] font-medium py-0 px-2 flex items-center gap-1 rounded-md ${
                                            isSuper
                                                ? 'bg-amber-500/10 text-amber-700 border-amber-300 dark:text-amber-300 dark:border-amber-700/60'
                                                : isAdmin
                                                ? 'bg-blue-500/10 text-blue-700 border-blue-300 dark:text-blue-300 dark:border-blue-700/60'
                                                : 'bg-emerald-500/10 text-emerald-700 border-emerald-300 dark:text-emerald-300 dark:border-emerald-700/60'
                                        }`}
                                    >
                                        {isSuper && <Crown className="size-3 text-amber-500" />}
                                        {isAdmin && <ShieldCheck className="size-3 text-blue-500" />}
                                        {!isSuper && !isAdmin && <Shield className="size-3 text-emerald-500" />}
                                        {roleName}
                                    </Badge>
                                );
                            })}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'isActive',
                header: ({ column }) => {
                    return (
                        <button
                            type="button"
                            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                            className="flex items-center gap-1 hover:text-foreground transition-colors cursor-pointer"
                        >
                            <span>Statut</span>
                            <ArrowUpDown className="size-3.5" />
                        </button>
                    );
                },
                cell: ({ row }) => {
                    const user = row.original;
                    const canToggle = !user.isSuperAdmin && !user.isCurrentAuth && canBlock;

                    if (!canToggle) {
                        return (
                            <div className="flex items-center gap-2">
                                {user.isActive ? (
                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/70">
                                        <span className="size-1.5 rounded-full bg-emerald-500" />
                                        Actif
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/70">
                                        <Lock className="size-3" />
                                        Bloqué
                                    </span>
                                )}
                            </div>
                        );
                    }

                    return (
                        <div className="flex items-center gap-2">
                            {user.isActive ? (
                                <button
                                    type="button"
                                    onClick={() => setUserToToggle(user)}
                                    title="Cliquer pour bloquer ce compte"
                                    className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all cursor-pointer hover:bg-emerald-100 dark:hover:bg-emerald-950/60 bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/70"
                                >
                                    <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                    Actif
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => setUserToToggle(user)}
                                    title="Cliquer pour réactiver ce compte"
                                    className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all cursor-pointer hover:bg-rose-100 dark:hover:bg-rose-950/60 bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/70"
                                >
                                    <Lock className="size-3" />
                                    Bloqué
                                </button>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'createdAt',
                header: 'Inscrit le',
                cell: ({ row }) => (
                    <span className="text-xs text-muted-foreground font-mono">
                        {row.original.createdAt ?? '—'}
                    </span>
                ),
            },
        ];

        // La colonne Actions affiche directement les boutons d'action rapide (Modifier, Bloquer/Débloquer)
        // afin d'offrir un accès immédiat en 1 clic sans menu déroulant intermédiaire.
        if (canEdit || canBlock) {
            baseColumns.push({
                id: 'actions',
                header: () => <div className="text-right pr-2 font-medium">Actions</div>,
                cell: ({ row }) => {
                    const user = row.original;
                    const canModifyStatus = !user.isSuperAdmin && !user.isCurrentAuth && canBlock;

                    return (
                        <div className="flex items-center justify-end gap-1.5">
                            {/* Bouton de modification rapide (Bleu) */}
                            {canEdit && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => handleOpenEdit(user)}
                                    title="Modifier les informations"
                                    className="size-8 rounded-lg bg-blue-50/90 text-blue-600 border border-blue-200/70 hover:bg-blue-100 hover:text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 dark:border-blue-800/60 dark:hover:bg-blue-900/60 transition-all shadow-xs cursor-pointer"
                                >
                                    <Edit2 className="size-4 text-blue-600 dark:text-blue-400" />
                                    <span className="sr-only">Modifier</span>
                                </Button>
                            )}

                            {/* Bouton de blocage / réactivation instantané (Ambre / Émeraude) */}
                            {canModifyStatus && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setUserToToggle(user)}
                                    title={user.isActive ? 'Bloquer ce compte' : 'Réactiver ce compte'}
                                    className={`size-8 rounded-lg transition-all shadow-xs cursor-pointer ${
                                        user.isActive
                                            ? 'bg-amber-50/90 text-amber-600 border border-amber-200/70 hover:bg-amber-100 hover:text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800/60 dark:hover:bg-amber-900/60'
                                            : 'bg-emerald-50/90 text-emerald-600 border border-emerald-200/70 hover:bg-emerald-100 hover:text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800/60 dark:hover:bg-emerald-900/60'
                                    }`}
                                >
                                    {user.isActive ? (
                                        <Lock className="size-4 text-amber-600 dark:text-amber-400" />
                                    ) : (
                                        <UserCheck className="size-4 text-emerald-600 dark:text-emerald-400" />
                                    )}
                                    <span className="sr-only">
                                        {user.isActive ? 'Bloquer' : 'Débloquer'}
                                    </span>
                                </Button>
                            )}
                        </div>
                    );
                },
            });
        }

        return baseColumns;
    }, [canEdit, canBlock]);

    return (
        <>
            <Head title="Gestion des Utilisateurs - Administration" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête principal : Titre, sous-titre et bouton d'action responsive */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl sm:text-2xl lg:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5 sm:gap-3">
                            <Users className="size-6 sm:size-8 text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <span>Gestion des Utilisateurs</span>
                        </h1>
                        <p className="text-muted-foreground text-xs sm:text-sm mt-1">
                            Créez, administrez et contrôlez les comptes d'accès ainsi que leurs rôles de sécurité sur SYGES.
                        </p>
                    </div>

                    {canCreate && (
                        <Button
                            onClick={() => {
                                createForm.reset();
                                createForm.clearErrors();
                                setShowPassword(false);
                                setShowPasswordConfirm(false);
                                setIsCreateModalOpen(true);
                            }}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-sm flex items-center gap-2 w-full sm:w-auto cursor-pointer"
                        >
                            <UserPlus className="size-4 shrink-0" />
                            <span>Nouvel utilisateur</span>
                        </Button>
                    )}
                </div>

                {/* Synthèse statistique KPI compacte et optimisée avec grille responsive */}
                <div className="grid grid-cols-1 min-[400px]:grid-cols-2 lg:grid-cols-4 gap-2.5">
                    <div className="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-border/80 bg-card">
                        <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <Users className="size-3.5" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider truncate">
                                Total Utilisateurs
                            </p>
                            <p className="text-base font-bold text-foreground leading-tight mt-0.5 tabular-nums">
                                {stats.total}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-border/80 bg-card">
                        <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <UserCheck className="size-3.5" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider truncate">
                                Comptes Actifs
                            </p>
                            <p className="text-base font-bold text-foreground leading-tight mt-0.5 tabular-nums">
                                {stats.active}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-border/80 bg-card">
                        <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-rose-500/10 text-rose-600 dark:text-rose-400">
                            <UserX className="size-3.5" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider truncate">
                                Comptes Bloqués
                            </p>
                            <p className="text-base font-bold text-foreground leading-tight mt-0.5 tabular-nums">
                                {stats.blocked}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-border/80 bg-card">
                        <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <ShieldCheck className="size-3.5" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider truncate">
                                Rôles Disponibles
                            </p>
                            <p className="text-base font-bold text-foreground leading-tight mt-0.5 tabular-nums">
                                {availableRoles.length}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Tableau TanStack Table universel */}
                <DataTable
                    columns={columns}
                    data={filteredData}
                    searchPlaceholder="Rechercher par nom, login, email..."
                    filterSlot={
                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                            {/* Filtre par statut */}
                            <Select
                                value={selectedStatusFilter}
                                onValueChange={(val) => setSelectedStatusFilter(val)}
                            >
                                <SelectTrigger className="h-9 w-full sm:w-36 text-xs bg-card">
                                    <SelectValue placeholder="Statut" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous les statuts</SelectItem>
                                    <SelectItem value="active">Actifs uniquement</SelectItem>
                                    <SelectItem value="blocked">Bloqués uniquement</SelectItem>
                                </SelectContent>
                            </Select>

                            {/* Bouton de réinitialisation du filtre si actif */}
                            {selectedStatusFilter !== 'all' && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSelectedStatusFilter('all')}
                                    className="h-9 px-2 text-xs text-muted-foreground hover:text-foreground cursor-pointer"
                                    title="Réinitialiser le filtre"
                                >
                                    <RefreshCw className="size-3.5 mr-1" />
                                    Effacer
                                </Button>
                            )}
                        </div>
                    }
                />
            </div>

            {/* ========================================================================= */}
            {/* MODALE DE CRÉATION D'UTILISATEUR */}
            {/* ========================================================================= */}
            <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-lg font-bold">
                            <UserPlus className="size-5 text-emerald-600" />
                            Créer un nouvel utilisateur
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCreateSubmit} className="space-y-4 py-2">
                        {/* Nom complet */}
                        <div className="space-y-1.5">
                            <Label htmlFor="create-name" className="text-xs font-semibold">
                                Nom complet <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="create-name"
                                placeholder="ex: Kaboré Alain"
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                className="h-9 text-sm"
                                required
                            />
                            {createForm.errors.name && (
                                <p className="text-xs text-destructive">{createForm.errors.name}</p>
                            )}
                        </div>

                        {/* Login et Email en grille 2 colonnes */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="create-login" className="text-xs font-semibold">
                                    Login de connexion <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="create-login"
                                    placeholder="ex: yannick"
                                    value={createForm.data.login}
                                    onChange={(e) => createForm.setData('login', e.target.value.toLowerCase())}
                                    className="h-9 text-sm font-mono"
                                    required
                                />
                                {createForm.errors.login && (
                                    <p className="text-xs text-destructive">{createForm.errors.login}</p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="create-email" className="text-xs font-semibold flex items-center gap-1">
                                    <Mail className="size-3 text-muted-foreground" />
                                    Adresse Email <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="create-email"
                                    type="email"
                                    placeholder="ex: alain@syges.com"
                                    value={createForm.data.email}
                                    onChange={(e) => createForm.setData('email', e.target.value)}
                                    className="h-9 text-sm"
                                    required
                                />
                                {createForm.errors.email && (
                                    <p className="text-xs text-destructive">{createForm.errors.email}</p>
                                )}
                            </div>
                        </div>

                        {/* Mot de passe et confirmation */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="create-password" className="text-xs font-semibold">
                                    Mot de passe <span className="text-destructive">*</span>
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="create-password"
                                        type={showPassword ? 'text' : 'password'}
                                        placeholder="Entrez le mot de passe"
                                        value={createForm.data.password}
                                        onChange={(e) => createForm.setData('password', e.target.value)}
                                        className="h-9 text-sm pr-9"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                                        tabIndex={-1}
                                    >
                                        {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                                {createForm.errors.password && (
                                    <p className="text-xs text-destructive">{createForm.errors.password}</p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="create-password-confirm" className="text-xs font-semibold">
                                    Confirmer mot de passe <span className="text-destructive">*</span>
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="create-password-confirm"
                                        type={showPasswordConfirm ? 'text' : 'password'}
                                        placeholder="Confirmer le mot de passe"
                                        value={createForm.data.password_confirmation}
                                        onChange={(e) =>
                                            createForm.setData('password_confirmation', e.target.value)
                                        }
                                        className="h-9 text-sm pr-9"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPasswordConfirm(!showPasswordConfirm)}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                                        tabIndex={-1}
                                    >
                                        {showPasswordConfirm ? (
                                            <EyeOff className="size-4" />
                                        ) : (
                                            <Eye className="size-4" />
                                        )}
                                    </button>
                                </div>
                                {createForm.errors.password_confirmation && (
                                    <p className="text-xs text-destructive">
                                        {createForm.errors.password_confirmation}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Établissement / Campus de rattachement */}
                        {etablissements.length > 0 && (
                            <div className="space-y-1.5 pt-1">
                                <Label htmlFor="create-etablissement" className="text-xs font-semibold flex items-center gap-1">
                                    <Building2 className="size-3 text-muted-foreground" />
                                    Campus / Établissement <span className="text-destructive">*</span>
                                </Label>
                                {isSuperAdmin ? (
                                    <Select
                                        value={createForm.data.etablissement_id || 'none'}
                                        onValueChange={(val) =>
                                            createForm.setData('etablissement_id', val === 'none' ? '' : val)
                                        }
                                    >
                                        <SelectTrigger id="create-etablissement" className="h-9 text-sm">
                                            <SelectValue placeholder="Sélectionner le campus de rattachement..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                <span className="font-semibold text-teal-600 dark:text-teal-400">
                                                    Accès Global (Aucun campus spécifique / Super-Admin)
                                                </span>
                                            </SelectItem>
                                            {etablissements.map((etab) => (
                                                <SelectItem key={etab.id} value={String(etab.id)}>
                                                    <span className="font-semibold text-foreground">{etab.code || truncateText(etab.nom, 24)}</span>
                                                    {etab.code && etab.nom && (
                                                        <span className="text-muted-foreground text-xs ml-1.5 font-normal">
                                                            ({truncateText(etab.nom, 28)})
                                                        </span>
                                                    )}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <div className="flex items-center gap-2 p-2.5 rounded-md border bg-muted/40 text-xs font-medium text-foreground">
                                        <Building2 className="size-4 text-sky-600 shrink-0" />
                                        {(() => {
                                            const currentEtab = etablissements.find((e) => e.id === currentEtablissementId);
                                            return (
                                                <span title={currentEtab?.nom || undefined}>
                                                    <strong className="font-semibold text-foreground">
                                                        {currentEtab?.code || truncateText(currentEtab?.nom || 'Campus assigné', 24)}
                                                    </strong>
                                                    {currentEtab?.code && currentEtab?.nom && (
                                                        <span className="text-muted-foreground ml-1.5 font-normal">
                                                            ({truncateText(currentEtab.nom, 28)})
                                                        </span>
                                                    )}{' '}
                                                    <span className="text-muted-foreground font-light text-[11px]">(Verrouillé)</span>
                                                </span>
                                            );
                                        })()}
                                    </div>
                                )}
                                {createForm.errors.etablissement_id && (
                                    <p className="text-xs text-destructive">{createForm.errors.etablissement_id}</p>
                                )}
                            </div>
                        )}

                        {/* Attribution des rôles (Select Multiple shadcn/ui) */}
                        <div className="space-y-1.5 pt-1">
                            <Label className="text-xs font-semibold flex items-center justify-between">
                                <span>
                                    Rôles & Habilitations <span className="text-destructive">*</span>
                                </span>
                                <span className="text-[11px] font-normal text-muted-foreground">
                                    {createForm.data.roles.length} rôle(s) sélectionné(s)
                                </span>
                            </Label>
                            <MultiSelect
                                options={roleOptions}
                                selected={createForm.data.roles}
                                onChange={(roles) => createForm.setData('roles', roles)}
                                placeholder="Choisir un ou plusieurs rôles d'accès..."
                            />
                            {createForm.errors.roles && (
                                <p className="text-xs text-destructive">{createForm.errors.roles}</p>
                            )}
                        </div>

                        <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateModalOpen(false)}
                                className="w-full sm:w-auto cursor-pointer"
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white w-full sm:w-auto cursor-pointer"
                            >
                                {createForm.processing ? 'Création en cours...' : 'Créer l\'utilisateur'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ========================================================================= */}
            {/* MODALE DE MODIFICATION D'UTILISATEUR */}
            {/* ========================================================================= */}
            <Dialog open={isEditModalOpen} onOpenChange={setIsEditModalOpen}>
                <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-lg font-bold">
                            <Edit2 className="size-5 text-blue-600" />
                            Modifier l'utilisateur
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleEditSubmit} className="space-y-4 py-2">
                        {/* Nom complet */}
                        <div className="space-y-1.5">
                            <Label htmlFor="edit-name" className="text-xs font-semibold">
                                Nom complet <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit-name"
                                placeholder="ex: Kaboré Alain"
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                                className="h-9 text-sm"
                                required
                            />
                            {editForm.errors.name && (
                                <p className="text-xs text-destructive">{editForm.errors.name}</p>
                            )}
                        </div>

                        {/* Login et Email */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="edit-login" className="text-xs font-semibold">
                                    Login de connexion <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="edit-login"
                                    placeholder="ex: yannick"
                                    value={editForm.data.login}
                                    onChange={(e) => editForm.setData('login', e.target.value.toLowerCase())}
                                    className="h-9 text-sm font-mono"
                                    required
                                />
                                {editForm.errors.login && (
                                    <p className="text-xs text-destructive">{editForm.errors.login}</p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="edit-email" className="text-xs font-semibold flex items-center gap-1">
                                    <Mail className="size-3 text-muted-foreground" />
                                    Adresse Email <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="edit-email"
                                    type="email"
                                    placeholder="ex: alain@syges.com"
                                    value={editForm.data.email}
                                    onChange={(e) => editForm.setData('email', e.target.value)}
                                    className="h-9 text-sm"
                                    required
                                />
                                {editForm.errors.email && (
                                    <p className="text-xs text-destructive">{editForm.errors.email}</p>
                                )}
                            </div>
                        </div>

                        {/* Nouveau mot de passe optionnel */}
                        <div className="rounded-lg border border-border/80 bg-muted/20 p-3 space-y-3">
                            <div>
                                <p className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <KeyRound className="size-3.5 text-primary" />
                                    Modifier le mot de passe (optionnel)
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    Laissez ces champs vides si vous souhaitez conserver le mot de passe actuel.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit-password" className="text-xs font-medium">
                                        Nouveau mot de passe
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="edit-password"
                                            type={showPassword ? 'text' : 'password'}
                                            placeholder="Nouveau mot de passe"
                                            value={editForm.data.password}
                                            onChange={(e) => editForm.setData('password', e.target.value)}
                                            className="h-9 text-sm pr-9 bg-card"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                                            tabIndex={-1}
                                        >
                                            {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                        </button>
                                    </div>
                                    {editForm.errors.password && (
                                        <p className="text-xs text-destructive">{editForm.errors.password}</p>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="edit-password-confirm" className="text-xs font-medium">
                                        Confirmer mot de passe
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="edit-password-confirm"
                                            type={showPasswordConfirm ? 'text' : 'password'}
                                            placeholder="Confirmer le mot de passe"
                                            value={editForm.data.password_confirmation}
                                            onChange={(e) =>
                                                editForm.setData('password_confirmation', e.target.value)
                                            }
                                            className="h-9 text-sm pr-9 bg-card"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPasswordConfirm(!showPasswordConfirm)}
                                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                                            tabIndex={-1}
                                        >
                                            {showPasswordConfirm ? (
                                                <EyeOff className="size-4" />
                                            ) : (
                                                <Eye className="size-4" />
                                            )}
                                        </button>
                                    </div>
                                    {editForm.errors.password_confirmation && (
                                        <p className="text-xs text-destructive">
                                            {editForm.errors.password_confirmation}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Établissement / Campus de rattachement pour modification */}
                        {etablissements.length > 0 && isSuperAdmin && !userToEdit?.isSuperAdmin && (
                            <div className="space-y-1.5 pt-1">
                                <Label htmlFor="edit-etablissement" className="text-xs font-semibold flex items-center gap-1">
                                    <Building2 className="size-3 text-muted-foreground" />
                                    Campus / Établissement
                                </Label>
                                <Select
                                    value={editForm.data.etablissement_id || 'none'}
                                    onValueChange={(val) =>
                                        editForm.setData('etablissement_id', val === 'none' ? '' : val)
                                    }
                                >
                                    <SelectTrigger id="edit-etablissement" className="h-9 text-sm">
                                        <SelectValue placeholder="Sélectionner le campus..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            <span className="font-semibold text-teal-600 dark:text-teal-400">
                                                Accès Global (Aucun campus spécifique)
                                            </span>
                                        </SelectItem>
                                        {etablissements.map((etab) => (
                                            <SelectItem key={etab.id} value={String(etab.id)}>
                                                <span className="font-semibold text-foreground">{etab.code || truncateText(etab.nom, 24)}</span>
                                                {etab.code && etab.nom && (
                                                    <span className="text-muted-foreground text-xs ml-1.5 font-normal">
                                                        ({truncateText(etab.nom, 28)})
                                                    </span>
                                                )}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {editForm.errors.etablissement_id && (
                                    <p className="text-xs text-destructive">{editForm.errors.etablissement_id}</p>
                                )}
                            </div>
                        )}

                        {/* Attribution des rôles (Select Multiple shadcn/ui) */}
                        <div className="space-y-1.5 pt-1">
                            <Label className="text-xs font-semibold flex items-center justify-between">
                                <span>
                                    Rôles & Habilitations <span className="text-destructive">*</span>
                                </span>
                                <span className="text-[11px] font-normal text-muted-foreground">
                                    {editForm.data.roles.length} rôle(s) sélectionné(s)
                                </span>
                            </Label>
                            <MultiSelect
                                options={editRoleOptions}
                                selected={editForm.data.roles}
                                onChange={(roles) => editForm.setData('roles', roles)}
                                placeholder="Choisir un ou plusieurs rôles d'accès..."
                            />
                            {editForm.errors.roles && (
                                <p className="text-xs text-destructive">{editForm.errors.roles}</p>
                            )}
                        </div>

                        <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsEditModalOpen(false)}
                                className="w-full sm:w-auto cursor-pointer"
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                                className="bg-primary hover:bg-primary/90 text-primary-foreground w-full sm:w-auto cursor-pointer"
                            >
                                {editForm.processing ? 'Enregistrement...' : 'Mettre à jour'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ========================================================================= */}
            {/* MODALE DE CONFIRMATION DE BASCULEMENT DE STATUT (ACTIF / BLOQUÉ) */}
            {/* ========================================================================= */}
            <Dialog open={!!userToToggle} onOpenChange={(open) => !open && setUserToToggle(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base font-bold">
                            {userToToggle?.isActive ? (
                                <>
                                    <Lock className="size-5 text-amber-500" />
                                    Bloquer l'accès de l'utilisateur
                                </>
                            ) : (
                                <>
                                    <UserCheck className="size-5 text-emerald-500" />
                                    Réactiver l'accès de l'utilisateur
                                </>
                            )}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground mt-2">
                            {userToToggle?.isActive ? (
                                <>
                                    Êtes-vous sûr de vouloir bloquer le compte de{' '}
                                    <strong className="text-foreground">{userToToggle?.name}</strong> (
                                    <code className="font-mono text-[11px]">{userToToggle?.login}</code>) ?
                                    L'utilisateur sera immédiatement déconnecté et ne pourra plus accéder à SYGES.
                                </>
                            ) : (
                                <>
                                    Êtes-vous sûr de vouloir réactiver le compte de{' '}
                                    <strong className="text-foreground">{userToToggle?.name}</strong> ?
                                    L'utilisateur pourra à nouveau se connecter avec ses identifiants habituels.
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2 sm:gap-0 mt-4">
                        <Button
                            variant="outline"
                            onClick={() => setUserToToggle(null)}
                            className="cursor-pointer"
                        >
                            Annuler
                        </Button>
                        <Button
                            variant={userToToggle?.isActive ? 'default' : 'default'}
                            onClick={handleConfirmToggleStatus}
                            className={
                                userToToggle?.isActive
                                    ? 'bg-amber-600 hover:bg-amber-700 text-white cursor-pointer'
                                    : 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer'
                            }
                        >
                            {userToToggle?.isActive ? 'Confirmer le blocage' : 'Réactiver le compte'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>


        </>
    );
}

/**
 * Fil d'Ariane (Breadcrumbs) pour la page de gestion des utilisateurs.
 */
UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Gestion des Utilisateurs',
            href: '/administration/users',
        },
    ],
};

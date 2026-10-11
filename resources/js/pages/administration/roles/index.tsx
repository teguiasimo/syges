import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Shield,
    ShieldCheck,
    Users,
    GraduationCap,
    FileSpreadsheet,
    CalendarRange,
    Settings,
    Plus,
    Trash2,
    Check,
    Search,
    AlertCircle,
    KeyRound,
    Lock,
    UserCheck,
    Crown,
    Sparkles,
    CheckCheck,
    CheckSquare,
    Square,
    ChevronDown,
    ChevronRight,
    Save,
    ChevronsUpDown,
    Info,
    School,
    Layers,
    BookOpen,
    Briefcase,
    Network,
    Award,
    Receipt,
    Wallet,
    Scale,
    Gavel,
    HeartHandshake,
    Clock,
    Calendar,
    MessageSquare,
    BarChart,
    Archive,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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

/**
 * Représentation d'une permission individuelle issue de la base de données.
 *
 * @property {number} id - Identifiant numérique unique.
 * @property {string} name - Nom technique Spatie (ex: 'users.view').
 * @property {string} label - Libellé compréhensible pour l'utilisateur.
 * @property {string} description - Explication détaillée du droit conféré.
 */
export interface PermissionItem {
    id: number;
    name: string;
    label: string;
    description: string;
}

/**
 * Représentation d'un module regroupant plusieurs permissions scolaires.
 *
 * @property {string} id - Clé unique du module.
 * @property {string} name - Nom du module (ex: 'Utilisateurs & Profils').
 * @property {string} description - Courte description du domaine.
 * @property {string} icon - Nom de l'icône Lucide correspondante.
 * @property {PermissionItem[]} permissions - Liste des permissions associées.
 */
export interface PermissionModule {
    id: string;
    name: string;
    description: string;
    icon: string;
    permissions: PermissionItem[];
}

/**
 * Représentation d'un rôle dans le système SYGES.
 *
 * @property {number} id - Identifiant numérique du rôle.
 * @property {string} name - Identifiant technique (slug, ex: 'directeur-etudes').
 * @property {string} displayName - Libellé convivial (ex: 'Directeur des Études').
 * @property {boolean} isSystem - Vrai si le rôle est protégé (super-admin, admin).
 * @property {boolean} isSuperAdmin - Vrai si le rôle est le super-administrateur.
 * @property {number} usersCount - Nombre d'utilisateurs actuellement associés.
 * @property {number} permissionsCount - Nombre de permissions accordées.
 * @property {string[]} permissions - Tableau des noms de permissions accordées.
 * @property {string} [createdAt] - Date formatée de création.
 */
export interface RoleItem {
    id: number;
    name: string;
    displayName: string;
    isSystem: boolean;
    isSuperAdmin: boolean;
    usersCount: number;
    permissionsCount: number;
    permissions: string[];
    createdAt?: string;
}

/**
 * Propriétés transmises par Inertia depuis le contrôleur RolePermissionController.
 */
interface RolesPageProps {
    roles: RoleItem[];
    modules: PermissionModule[];
    totalPermissions: number;
    can?: {
        create?: boolean;
        edit?: boolean;
        delete?: boolean;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

/**
 * Dictionnaire associant le nom de l'icône Lucide au composant React correspondant.
 */
const moduleIconMap: Record<string, React.ElementType> = {
    School,
    Layers,
    CalendarRange,
    BookOpen,
    GraduationCap,
    Briefcase,
    Network,
    FileSpreadsheet,
    Award,
    Receipt,
    Wallet,
    Scale,
    Gavel,
    HeartHandshake,
    Sparkles,
    Clock,
    Calendar,
    MessageSquare,
    BarChart,
    Archive,
    Users,
    ShieldCheck,
    Settings,
};

/**
 * Page de gestion des Rôles et Permissions de SYGES.
 *
 * Adopte une ergonomie moderne "Master-Detail" en deux colonnes :
 * - Colonne gauche : Recherche et liste des rôles sur une seule colonne.
 * - Colonne droite : Configuration détaillée des permissions du rôle sélectionné,
 *   présentées par modules sous forme d'accordéons interactifs.
 * - Modale de création simplifiée : Demande uniquement le nom du rôle.
 *
 * @param {RolesPageProps} props - Données fournies par le contrôleur Inertia.
 * @returns {JSX.Element} L'interface de gestion des rôles.
 */
export default function RolesIndex({ roles, modules, totalPermissions, can }: RolesPageProps) {
    const { flash } = usePage<{ flash?: { success?: string | null; error?: string | null } }>().props;

    // Habilitations de l'utilisateur connecté
    const canCreate = can?.create ?? true;
    const canEdit = can?.edit ?? true;
    const canDelete = can?.delete ?? true;

    // Rôle actuellement sélectionné pour l'affichage et l'édition des permissions
    const [selectedRoleId, setSelectedRoleId] = useState<number>(roles[0]?.id ?? 1);

    // État local des permissions pour le rôle sélectionné
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);

    // État d'ouverture des accordéons de modules (par défaut tous repliés)
    const [openModules, setOpenModules] = useState<Record<string, boolean>>({});

    // Champ de recherche dans la liste des rôles
    const [searchQuery, setSearchQuery] = useState('');

    // Champ de recherche dans les permissions unitaires
    const [permissionSearch, setPermissionSearch] = useState('');

    // État pour la modale simplifiée de création de rôle
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);

    // Rôle ciblé par la modale de confirmation de suppression
    const [roleToDelete, setRoleToDelete] = useState<RoleItem | null>(null);

    // État d'enregistrement des permissions
    const [isSaving, setIsSaving] = useState(false);

    // Formulaire Inertia dédié à la création rapide d'un rôle
    const createForm = useForm<{ name: string }>({
        name: '',
    });

    /**
     * Retrouve l'objet complet du rôle actuellement sélectionné.
     */
    const currentRole = useMemo(() => {
        return roles.find((r) => r.id === selectedRoleId) || roles[0] || null;
    }, [roles, selectedRoleId]);

    /**
     * Initialise les permissions locales lors du changement de rôle sélectionné
     * ou lors de la mise à jour des rôles via les props Inertia.
     */
    useEffect(() => {
        if (currentRole) {
            setSelectedPermissions([...currentRole.permissions]);
        }
    }, [currentRole]);

    /**
     * Affiche les notifications Toast dès la réception des messages flash de session.
     */
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    /**
     * Filtrage de la liste des rôles selon la saisie de l'utilisateur.
     */
    const filteredRoles = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return roles;

        return roles.filter(
            (role) =>
                role.displayName.toLowerCase().includes(query) ||
                role.name.toLowerCase().includes(query)
        );
    }, [roles, searchQuery]);

    /**
     * Filtrage des modules et permissions selon la saisie dans le champ de recherche de permissions.
     */
    const filteredModules = useMemo(() => {
        const query = permissionSearch.trim().toLowerCase();
        if (!query) return modules;

        return modules
            .map((m) => {
                const matched = m.permissions.filter(
                    (p) =>
                        p.name.toLowerCase().includes(query) ||
                        p.label.toLowerCase().includes(query) ||
                        p.description.toLowerCase().includes(query)
                );
                return {
                    ...m,
                    permissions: matched,
                };
            })
            .filter((m) => m.permissions.length > 0);
    }, [modules, permissionSearch]);

    /**
     * Calcul des métriques statistiques globales affichées en en-tête.
     */
    const stats = useMemo(() => {
        return {
            totalRoles: roles.length,
            totalPermissions,
            totalAssignedUsers: roles.reduce((acc, r) => acc + r.usersCount, 0),
            systemRoles: roles.filter((r) => r.isSystem).length,
        };
    }, [roles, totalPermissions]);

    /**
     * Détermine si des modifications de permissions n'ont pas encore été enregistrées.
     */
    const hasUnsavedChanges = useMemo(() => {
        if (!currentRole) return false;
        if (currentRole.isSuperAdmin) return false;

        const currentSorted = [...currentRole.permissions].sort();
        const selectedSorted = [...selectedPermissions].sort();

        if (currentSorted.length !== selectedSorted.length) return true;
        return currentSorted.some((p, i) => p !== selectedSorted[i]);
    }, [currentRole, selectedPermissions]);

    /**
     * Bascule l'état d'ouverture d'un accordéon de module.
     *
     * @param {string} moduleId - Identifiant unique du module.
     */
    const toggleModuleAccordion = (moduleId: string) => {
        setOpenModules((prev) => ({
            ...prev,
            [moduleId]: !prev[moduleId],
        }));
    };

    /**
     * Déplie ou replie simultanément l'ensemble des modules.
     */
    const toggleAllAccordions = (expand: boolean) => {
        const updated: Record<string, boolean> = {};
        modules.forEach((m) => {
            updated[m.id] = expand;
        });
        setOpenModules(updated);
    };

    /**
     * Coche ou décoche une permission individuelle pour le rôle courant.
     *
     * @param {string} permissionName - Identifiant technique de la permission.
     */
    const handleTogglePermission = (permissionName: string) => {
        if (currentRole?.isSuperAdmin) return;

        setSelectedPermissions((prev) =>
            prev.includes(permissionName)
                ? prev.filter((p) => p !== permissionName)
                : [...prev, permissionName]
        );
    };

    /**
     * Sélectionne ou désélectionne l'ensemble des permissions d'un module spécifique.
     *
     * @param {PermissionModule} module - Le module concerné.
     */
    const handleToggleModulePermissions = (module: PermissionModule) => {
        if (currentRole?.isSuperAdmin) return;

        const modulePermissionNames = module.permissions.map((p) => p.name);
        const allSelected = modulePermissionNames.every((p) => selectedPermissions.includes(p));

        if (allSelected) {
            // Décocher toutes les permissions du module
            setSelectedPermissions((prev) =>
                prev.filter((p) => !modulePermissionNames.includes(p))
            );
        } else {
            // Ajouter toutes les permissions du module
            setSelectedPermissions((prev) =>
                Array.from(new Set([...prev, ...modulePermissionNames]))
            );
        }
    };

    /**
     * Coche ou décoche la totalité des permissions disponibles dans l'application.
     */
    const handleToggleAllGlobal = () => {
        if (currentRole?.isSuperAdmin) return;

        const allPermissionNames = modules.flatMap((m) => m.permissions.map((p) => p.name));
        const allSelected = allPermissionNames.every((p) => selectedPermissions.includes(p));

        if (allSelected) {
            setSelectedPermissions([]);
        } else {
            setSelectedPermissions(allPermissionNames);
        }
    };

    /**
     * Enregistre les permissions modifiées pour le rôle actuellement sélectionné.
     */
    const handleSavePermissions = () => {
        if (!currentRole || currentRole.isSuperAdmin) return;

        setIsSaving(true);
        router.put(
            `/administration/roles/${currentRole.id}`,
            {
                permissions: selectedPermissions,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            }
        );
    };

    /**
     * Soumission du formulaire de création rapide d'un rôle.
     *
     * @param {React.FormEvent} e - Événement de soumission de formulaire.
     */
    const handleCreateRoleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        createForm.post('/administration/roles', {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                createForm.reset();
            },
        });
    };

    /**
     * Confirme et exécute la suppression du rôle ciblé.
     */
    const handleConfirmDelete = () => {
        if (!roleToDelete) return;

        router.delete(`/administration/roles/${roleToDelete.id}`, {
            onSuccess: () => {
                setRoleToDelete(null);
                // Si le rôle supprimé était celui sélectionné, repositionner sur le premier disponible
                if (selectedRoleId === roleToDelete.id) {
                    const fallback = roles.find((r) => r.id !== roleToDelete.id);
                    if (fallback) {
                        setSelectedRoleId(fallback.id);
                    }
                }
            },
        });
    };

    return (
        <>
            <Head title="Rôles & Permissions - Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full">
                {/* En-tête principal : Titre, sous-titre et action de création */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-3">
                            <ShieldCheck className="size-8 text-amber-500" />
                            Rôles & Permissions
                        </h1>
                        <p className="text-muted-foreground text-sm mt-1">
                            Sélectionnez un rôle à gauche pour configurer et ajuster ses habilitations par module scolaire.
                        </p>
                    </div>

                    {canCreate && (
                        <Button
                            onClick={() => {
                                createForm.clearErrors();
                                createForm.reset();
                                setIsCreateModalOpen(true);
                            }}
                            className="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold shadow-sm flex items-center gap-2 self-start sm:self-auto cursor-pointer"
                        >
                            <Plus className="size-4" />
                            Nouveau rôle
                        </Button>
                    )}
                </div>

                {/* Synthèse statistique compacte et discrète */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div className="flex items-center gap-3 px-3.5 py-2.5 rounded-xl border border-border/80 bg-card/70 hover:bg-card transition-colors">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600">
                            <Shield className="size-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[11px] font-medium text-muted-foreground truncate">Total des Rôles</p>
                            <p className="text-base font-bold text-foreground leading-none mt-0.5">{stats.totalRoles}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 px-3.5 py-2.5 rounded-xl border border-border/80 bg-card/70 hover:bg-card transition-colors">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600">
                            <KeyRound className="size-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[11px] font-medium text-muted-foreground truncate">Permissions actives</p>
                            <p className="text-base font-bold text-foreground leading-none mt-0.5">{stats.totalPermissions}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 px-3.5 py-2.5 rounded-xl border border-border/80 bg-card/70 hover:bg-card transition-colors">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                            <Users className="size-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[11px] font-medium text-muted-foreground truncate">Utilisateurs rattachés</p>
                            <p className="text-base font-bold text-foreground leading-none mt-0.5">{stats.totalAssignedUsers}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 px-3.5 py-2.5 rounded-xl border border-border/80 bg-card/70 hover:bg-card transition-colors">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-purple-500/10 text-purple-600">
                            <Lock className="size-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[11px] font-medium text-muted-foreground truncate">Rôles protégés</p>
                            <p className="text-base font-bold text-foreground leading-none mt-0.5">{stats.systemRoles}</p>
                        </div>
                    </div>
                </div>

                {/* DISPOSITION EN 2 COLONNES (MASTER-DETAIL) */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    {/* ========================================================================= */}
                    {/* COLONNE GAUCHE (4 colonnes sur desktop) : Recherche & Liste des Rôles */}
                    {/* ========================================================================= */}
                    <div className="lg:col-span-4 flex flex-col gap-4">
                        {/* Zone de recherche */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                            <Input
                                placeholder="Rechercher un rôle..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-9 bg-card"
                            />
                        </div>

                        {/* Liste des rôles sur une seule colonne verticale */}
                        <div className="flex flex-col gap-2.5">
                            {filteredRoles.map((role) => {
                                const isSelected = role.id === selectedRoleId;
                                const percent =
                                    totalPermissions > 0
                                        ? Math.round((role.permissionsCount / totalPermissions) * 100)
                                        : 0;
                                const canDeleteRole = !role.isSystem && role.usersCount === 0 && canDelete;

                                return (
                                    <div
                                        key={role.id}
                                        onClick={() => setSelectedRoleId(role.id)}
                                        className={`group relative flex flex-col gap-2 p-4 rounded-xl border transition-all cursor-pointer select-none ${
                                            isSelected
                                                ? 'bg-primary/5 border-primary shadow-xs ring-1 ring-primary/40'
                                                : 'bg-card border-border/80 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-xs'
                                        }`}
                                    >
                                        {/* En-tête de la carte de rôle */}
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-2.5 min-w-0">
                                                <div
                                                    className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${
                                                        role.isSuperAdmin
                                                            ? 'bg-amber-500/10 text-amber-600'
                                                            : role.isSystem
                                                            ? 'bg-blue-500/10 text-blue-600'
                                                            : 'bg-emerald-500/10 text-emerald-600'
                                                    }`}
                                                >
                                                    {role.isSuperAdmin ? (
                                                        <Crown className="size-4" />
                                                    ) : (
                                                        <Shield className="size-4" />
                                                    )}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <h3
                                                        className={`text-sm font-bold truncate ${
                                                            isSelected ? 'text-primary' : 'text-foreground'
                                                        }`}
                                                    >
                                                        {role.displayName}
                                                    </h3>
                                                    <p className="text-[11px] font-mono text-muted-foreground truncate">
                                                        {role.name}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Badge du type de rôle */}
                                            {role.isSystem ? (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-[10px] font-semibold uppercase shrink-0"
                                                >
                                                    Système
                                                </Badge>
                                            ) : (
                                                <div className="flex items-center gap-1 shrink-0">
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px] font-semibold uppercase border-emerald-300 text-emerald-700 dark:text-emerald-400"
                                                    >
                                                        Perso
                                                    </Badge>
                                                    {canDeleteRole && (
                                                        <button
                                                            type="button"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setRoleToDelete(role);
                                                            }}
                                                            className="p-1 rounded text-muted-foreground/60 hover:text-destructive hover:bg-destructive/10 transition-colors"
                                                            title="Supprimer ce rôle"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Statistiques et jauge de couverture */}
                                        <div className="flex items-center justify-between text-xs text-muted-foreground pt-1">
                                            <span className="flex items-center gap-1 font-medium text-[11px]">
                                                <UserCheck className="size-3 text-slate-500" />
                                                {role.usersCount} utilisateur{role.usersCount > 1 ? 's' : ''}
                                            </span>
                                            <span className="font-semibold text-foreground text-[11px]">
                                                {role.permissionsCount} / {totalPermissions} ({percent}%)
                                            </span>
                                        </div>

                                        <div className="w-full bg-muted rounded-full h-1.5 overflow-hidden">
                                            <div
                                                className={`h-full rounded-full transition-all duration-300 ${
                                                    percent === 100
                                                        ? 'bg-emerald-500'
                                                        : percent > 50
                                                        ? 'bg-amber-500'
                                                        : 'bg-blue-500'
                                                }`}
                                                style={{ width: `${percent}%` }}
                                            />
                                        </div>

                                        {/* Indicateur de sélection à droite */}
                                        {isSelected && (
                                            <div className="absolute -right-2 top-1/2 -translate-y-1/2 hidden lg:flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm">
                                                <ChevronRight className="size-3.5" />
                                            </div>
                                        )}
                                    </div>
                                );
                            })}

                            {filteredRoles.length === 0 && (
                                <div className="p-6 text-center border rounded-xl bg-card">
                                    <AlertCircle className="size-8 text-muted-foreground/60 mx-auto mb-2" />
                                    <p className="text-sm font-semibold text-foreground">Aucun rôle trouvé</p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Modifiez votre recherche.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* ========================================================================= */}
                    {/* COLONNE DROITE (8 colonnes sur desktop) : Permissions en Accordéons */}
                    {/* ========================================================================= */}
                    <div className="lg:col-span-8 flex flex-col gap-4">
                        {currentRole ? (
                            <Card className="border-border/80 shadow-xs">
                                {/* En-tête du panneau de détail */}
                                <CardHeader className="p-5 pb-4 border-b">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${
                                                    currentRole.isSuperAdmin
                                                        ? 'bg-amber-500/10 text-amber-600 border border-amber-500/20'
                                                        : currentRole.isSystem
                                                        ? 'bg-blue-500/10 text-blue-600 border border-blue-500/20'
                                                        : 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20'
                                                }`}
                                            >
                                                {currentRole.isSuperAdmin ? (
                                                    <Crown className="size-6" />
                                                ) : (
                                                    <ShieldCheck className="size-6" />
                                                )}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <CardTitle className="text-lg sm:text-xl font-bold">
                                                        {currentRole.displayName}
                                                    </CardTitle>
                                                    {currentRole.isSystem ? (
                                                        <Badge variant="secondary" className="text-[10px] font-semibold uppercase">
                                                            Système
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline" className="text-[10px] font-semibold uppercase border-emerald-300 text-emerald-700 dark:text-emerald-400">
                                                            Personnalisé
                                                        </Badge>
                                                    )}
                                                </div>
                                                <CardDescription className="text-xs mt-0.5">
                                                    {currentRole.isSuperAdmin
                                                        ? 'Toutes les permissions sont accordées de façon permanente.'
                                                        : `${selectedPermissions.length} permission(s) sélectionnée(s) sur un total de ${totalPermissions}.`}
                                                </CardDescription>
                                            </div>
                                        </div>

                                        {/* Bouton d'enregistrement principal (affiché uniquement si modification autorisée) */}
                                        {!currentRole.isSuperAdmin && canEdit && (
                                            <Button
                                                onClick={handleSavePermissions}
                                                disabled={isSaving || !hasUnsavedChanges}
                                                className={`font-semibold flex items-center gap-2 self-start sm:self-auto cursor-pointer ${
                                                    hasUnsavedChanges
                                                        ? 'bg-primary hover:bg-primary/90 text-primary-foreground shadow-sm'
                                                        : 'opacity-60'
                                                }`}
                                            >
                                                {isSaving ? (
                                                    <span className="flex items-center gap-1.5">
                                                        <span className="size-3.5 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin" />
                                                        Enregistrement...
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1.5">
                                                        <Save className="size-4" />
                                                        {hasUnsavedChanges ? 'Enregistrer les modifications' : 'Permissions à jour'}
                                                    </span>
                                                )}
                                            </Button>
                                        )}
                                    </div>

                                    {/* Barre d'actions globales (Tout cocher, Tout déplier/replier) */}
                                    <div className="flex flex-wrap items-center justify-between gap-2 pt-3 mt-2 text-xs border-t">
                                        {!currentRole.isSuperAdmin && canEdit && (
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleToggleAllGlobal}
                                                    className="h-7 text-xs flex items-center gap-1.5 cursor-pointer"
                                                >
                                                    <CheckCheck className="size-3.5" />
                                                    {selectedPermissions.length === totalPermissions
                                                        ? 'Tout désélectionner'
                                                        : 'Tout sélectionner'}
                                                </Button>

                                                {hasUnsavedChanges && (
                                                    <span className="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.5 rounded-full border border-amber-200 dark:border-amber-900/40">
                                                        <Info className="size-3" />
                                                        Modifications en attente
                                                    </span>
                                                )}
                                            </div>
                                        )}

                                        <div className="flex items-center gap-1 text-muted-foreground ml-auto">
                                            <button
                                                type="button"
                                                onClick={() => toggleAllAccordions(true)}
                                                className="hover:text-foreground text-[11px] font-medium px-2 py-1 rounded hover:bg-muted transition-colors cursor-pointer"
                                            >
                                                Tout déplier
                                            </button>
                                            <span>·</span>
                                            <button
                                                type="button"
                                                onClick={() => toggleAllAccordions(false)}
                                                className="hover:text-foreground text-[11px] font-medium px-2 py-1 rounded hover:bg-muted transition-colors cursor-pointer"
                                            >
                                                Tout replier
                                            </button>
                                        </div>
                                    </div>
                                </CardHeader>

                                {/* Corps du panneau : Liste des Accordéons par Module Scolaire */}
                                <CardContent className="p-5 flex flex-col gap-4">
                                    {/* Champ de recherche rapide de permissions */}
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Filtrer les permissions (ex: supprimer, classe, note, caisse, bulletin)..."
                                            value={permissionSearch}
                                            onChange={(e) => setPermissionSearch(e.target.value)}
                                            className="pl-9 pr-16 h-9 bg-card text-xs border-border/80"
                                        />
                                        {permissionSearch && (
                                            <button
                                                type="button"
                                                onClick={() => setPermissionSearch('')}
                                                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground text-xs font-medium cursor-pointer"
                                            >
                                                Effacer
                                            </button>
                                        )}
                                    </div>

                                    {filteredModules.map((module) => {
                                        const IconComp = moduleIconMap[module.icon] || Shield;
                                        const isOpen = Boolean(openModules[module.id]) || Boolean(permissionSearch.trim());
                                        const modulePermissionNames = module.permissions.map((p) => p.name);

                                        // Calcul du nombre de permissions sélectionnées dans ce module
                                        const selectedInModule = currentRole.isSuperAdmin
                                            ? module.permissions.length
                                            : module.permissions.filter((p) =>
                                                  selectedPermissions.includes(p.name)
                                              ).length;
                                        const allInModuleSelected = selectedInModule === module.permissions.length;

                                        return (
                                            <div
                                                key={module.id}
                                                className="border border-border/80 rounded-xl overflow-hidden bg-card transition-all"
                                            >
                                                {/* En-tête de l'accordéon (Cliquable pour déplier/replier) */}
                                                <div
                                                    onClick={() => toggleModuleAccordion(module.id)}
                                                    className="flex items-center justify-between p-3.5 bg-muted/20 hover:bg-muted/40 transition-colors cursor-pointer select-none"
                                                >
                                                    <div className="flex items-center gap-3 min-w-0">
                                                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                            <IconComp className="size-4" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="flex items-center gap-2">
                                                                <h4 className="text-sm font-bold text-foreground">
                                                                    {module.name}
                                                                </h4>
                                                                <Badge
                                                                    variant={allInModuleSelected ? 'default' : 'secondary'}
                                                                    className="text-[10px] font-semibold"
                                                                >
                                                                    {selectedInModule} / {module.permissions.length}
                                                                </Badge>
                                                            </div>
                                                            <p className="text-xs text-muted-foreground truncate mt-0.5">
                                                                {module.description}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div className="flex items-center gap-2 shrink-0 ml-3">
                                                        {!currentRole.isSuperAdmin && canEdit && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    handleToggleModulePermissions(module);
                                                                }}
                                                                className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground cursor-pointer"
                                                            >
                                                                {allInModuleSelected ? (
                                                                    <Square className="size-3.5 mr-1" />
                                                                ) : (
                                                                    <CheckSquare className="size-3.5 mr-1" />
                                                                )}
                                                                {allInModuleSelected ? 'Aucun' : 'Tous'}
                                                            </Button>
                                                        )}

                                                        <div className="p-1 text-muted-foreground/70">
                                                            {isOpen ? (
                                                                <ChevronDown className="size-4" />
                                                            ) : (
                                                                <ChevronRight className="size-4" />
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Contenu dépliable : Grille des permissions du module */}
                                                {isOpen && (
                                                    <div className="p-4 pt-3 border-t bg-card/60">
                                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                                            {module.permissions.map((permission) => {
                                                                const isChecked =
                                                                    currentRole.isSuperAdmin ||
                                                                    selectedPermissions.includes(permission.name);

                                                                return (
                                                                    <div
                                                                        key={permission.id}
                                                                        onClick={() =>
                                                                            canEdit &&
                                                                            !currentRole.isSuperAdmin &&
                                                                            handleTogglePermission(permission.name)
                                                                        }
                                                                        className={`flex items-start gap-2.5 px-3 py-2.5 rounded-lg border transition-all select-none ${
                                                                            canEdit && !currentRole.isSuperAdmin
                                                                                ? 'cursor-pointer'
                                                                                : 'cursor-default'
                                                                        } ${
                                                                            isChecked
                                                                                ? 'bg-primary/10 border-primary shadow-xs ring-1 ring-primary/20'
                                                                                : 'bg-card border-border/70 hover:border-slate-400 dark:hover:border-slate-600 hover:bg-muted/30'
                                                                        }`}
                                                                    >
                                                                        <Checkbox
                                                                            checked={isChecked}
                                                                            disabled={currentRole.isSuperAdmin}
                                                                            className={`pointer-events-none mt-0.5 shrink-0 ${
                                                                                !isChecked ? 'border-slate-400 dark:border-slate-500' : ''
                                                                            }`}
                                                                        />
                                                                        <div className="min-w-0 flex-1">
                                                                            <span
                                                                                className={`text-xs select-none leading-snug block ${
                                                                                    isChecked
                                                                                        ? 'font-semibold text-foreground'
                                                                                        : 'font-medium text-foreground/90'
                                                                                }`}
                                                                            >
                                                                                {permission.label}
                                                                            </span>
                                                                            {permission.description && (
                                                                                <span
                                                                                    className="text-[10px] text-muted-foreground block line-clamp-2 mt-0.5 leading-tight"
                                                                                    title={permission.description}
                                                                                >
                                                                                    {permission.description}
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}

                                    {filteredModules.length === 0 && (
                                        <div className="p-8 text-center border rounded-xl bg-card">
                                            <AlertCircle className="size-8 text-muted-foreground/60 mx-auto mb-2" />
                                            <p className="text-sm font-semibold text-foreground">Aucune permission trouvée</p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Aucune permission ne correspond au filtre « {permissionSearch} ».
                                            </p>
                                        </div>
                                    )}

                                    {/* Bouton d'enregistrement secondaire en bas de page (affiché uniquement si modification autorisée) */}
                                    {!currentRole.isSuperAdmin && canEdit && (
                                        <div className="flex justify-end pt-3 border-t">
                                            <Button
                                                onClick={handleSavePermissions}
                                                disabled={isSaving || !hasUnsavedChanges}
                                                className={`font-semibold flex items-center gap-2 cursor-pointer ${
                                                    hasUnsavedChanges
                                                        ? 'bg-primary hover:bg-primary/90 text-primary-foreground shadow-sm'
                                                        : 'opacity-60'
                                                }`}
                                            >
                                                {isSaving ? (
                                                    <span className="flex items-center gap-1.5">
                                                        <span className="size-3.5 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin" />
                                                        Enregistrement...
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1.5">
                                                        <Save className="size-4" />
                                                        Enregistrer les permissions
                                                    </span>
                                                )}
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="p-12 text-center border rounded-2xl bg-card">
                                <Shield className="size-12 text-muted-foreground/50 mx-auto mb-3" />
                                <h3 className="text-lg font-semibold text-foreground">Aucun rôle sélectionné</h3>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Veuillez cliquer sur un rôle dans la colonne de gauche pour afficher ses permissions.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* ========================================================================= */}
            {/* MODALE SIMPLIFIÉE DE CRÉATION DE RÔLE (Nom uniquement)                    */}
            {/* ========================================================================= */}
            <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Plus className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-lg font-bold">Créer un nouveau rôle</DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                    Donnez un nom au nouveau rôle. Vous pourrez ensuite lui assigner ses permissions dans la colonne de droite.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form onSubmit={handleCreateRoleSubmit} className="space-y-4 pt-2">
                        <div className="space-y-2">
                            <Label htmlFor="create-role-name" className="text-sm font-semibold">
                                Nom du rôle <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="create-role-name"
                                type="text"
                                autoFocus
                                placeholder="Ex: Surveillant Général, Comptable, Censeur..."
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                className="bg-card"
                            />
                            {createForm.errors.name && (
                                <p className="text-xs text-destructive font-medium">
                                    {createForm.errors.name}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateModalOpen(false)}
                                className="cursor-pointer"
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing || !createForm.data.name.trim()}
                                className="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold cursor-pointer"
                            >
                                {createForm.processing ? (
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-3.5 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin" />
                                        Création...
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1.5">
                                        <Check className="size-4" />
                                        Créer le rôle
                                    </span>
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ========================================================================= */}
            {/* MODALE DE CONFIRMATION DE SUPPRESSION DE RÔLE                             */}
            {/* ========================================================================= */}
            <Dialog open={!!roleToDelete} onOpenChange={(open) => !open && setRoleToDelete(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-destructive/10 text-destructive border border-destructive/20">
                                <Trash2 className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-lg font-bold">Confirmer la suppression</DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                    Cette opération est irréversible et supprimera le profil d'accès.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="py-2 text-sm text-foreground">
                        Êtes-vous certain de vouloir supprimer définitivement le rôle{' '}
                        <strong className="font-semibold text-foreground">
                            « {roleToDelete?.displayName} »
                        </strong>{' '}
                        ?
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setRoleToDelete(null)}
                            className="cursor-pointer"
                        >
                            Annuler
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleConfirmDelete}
                            className="cursor-pointer"
                        >
                            Supprimer définitivement
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Configuration du fil d'Ariane (Breadcrumbs) pour la page Rôles & Permissions.
 */
RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Rôles & Permissions',
            href: '/administration/roles',
        },
    ],
};

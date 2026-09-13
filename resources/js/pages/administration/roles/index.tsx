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
    Pencil,
    Trash2,
    Check,
    Search,
    AlertCircle,
    KeyRound,
    Lock,
    UserCheck,
    Sparkles,
    CheckCheck,
    ArrowLeft,
    CheckSquare,
    Square,
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
import { Separator } from '@/components/ui/separator';

/**
 * Représentation d'une permission individuelle.
 */
export interface PermissionItem {
    id: number;
    name: string;
    label: string;
    description: string;
}

/**
 * Représentation d'un module regroupant plusieurs permissions.
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
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

/**
 * Dictionnaire d'association des icônes Lucide selon le nom de module.
 */
const moduleIconMap: Record<string, React.ElementType> = {
    Users: Users,
    ShieldCheck: ShieldCheck,
    GraduationCap: GraduationCap,
    FileSpreadsheet: FileSpreadsheet,
    CalendarRange: CalendarRange,
    Settings: Settings,
};

/**
 * Page de gestion des Rôles et Permissions de SYGES.
 * Permet de visualiser les profils de sécurité, d'en créer de nouveaux,
 * et d'ajuster finement les permissions attribuées par module scolaire.
 *
 * @param {RolesPageProps} props - Propriétés fournies par le contrôleur Inertia.
 * @returns {JSX.Element} La vue interactive de gestion des rôles.
 */
export default function RolesIndex({ roles, modules, totalPermissions }: RolesPageProps) {
    const { flash } = usePage<{ flash?: { success?: string | null; error?: string | null } }>().props;

    // État pour la recherche et le filtrage des rôles
    const [searchQuery, setSearchQuery] = useState('');

    // État pour le dialogue de création / édition
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<RoleItem | null>(null);

    // État pour le dialogue de confirmation de suppression
    const [roleToDelete, setRoleToDelete] = useState<RoleItem | null>(null);

    // Formulaire Inertia pour créer ou modifier un rôle
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<{
        name: string;
        permissions: string[];
    }>({
        name: '',
        permissions: [],
    });

    /**
     * Déclenchement des notifications Toast lors de la réception des messages flash.
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
     * Liste des rôles filtrés selon la recherche textuelle.
     */
    const filteredRoles = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return roles;

        return roles.filter(
            (role) =>
                role.displayName.toLowerCase().includes(query) ||
                role.name.toLowerCase().includes(query) ||
                role.permissions.some((p) => p.toLowerCase().includes(query))
        );
    }, [roles, searchQuery]);

    /**
     * Calcul des métriques statistiques globales.
     */
    const stats = useMemo(() => {
        const totalRoles = roles.length;
        const systemRoles = roles.filter((r) => r.isSystem).length;
        const totalAssignedUsers = roles.reduce((acc, r) => acc + r.usersCount, 0);

        return {
            totalRoles,
            systemRoles,
            totalAssignedUsers,
            totalPermissions,
        };
    }, [roles, totalPermissions]);

    /**
     * Ouvre la modale pour créer un nouveau rôle.
     */
    const handleOpenCreateModal = () => {
        clearErrors();
        reset();
        setEditingRole(null);
        setData({
            name: '',
            permissions: [],
        });
        setIsFormModalOpen(true);
    };

    /**
     * Ouvre la modale pour modifier un rôle existant.
     *
     * @param {RoleItem} role - Le rôle sélectionné pour édition.
     */
    const handleOpenEditModal = (role: RoleItem) => {
        clearErrors();
        setEditingRole(role);
        setData({
            name: role.displayName || role.name,
            permissions: [...role.permissions],
        });
        setIsFormModalOpen(true);
    };

    /**
     * Bascule l'état d'une permission individuelle dans le formulaire.
     *
     * @param {string} permissionName - L'identifiant technique de la permission.
     */
    const togglePermission = (permissionName: string) => {
        if (editingRole?.isSuperAdmin) return; // Le super admin possède toutes les permissions

        const exists = data.permissions.includes(permissionName);
        if (exists) {
            setData('permissions', data.permissions.filter((p) => p !== permissionName));
        } else {
            setData('permissions', [...data.permissions, permissionName]);
        }
    };

    /**
     * Sélectionne ou désélectionne l'ensemble des permissions d'un module donné.
     *
     * @param {PermissionModule} module - Le module concerné.
     */
    const toggleModulePermissions = (module: PermissionModule) => {
        if (editingRole?.isSuperAdmin) return;

        const modulePermissionNames = module.permissions.map((p) => p.name);
        const allSelected = modulePermissionNames.every((p) => data.permissions.includes(p));

        if (allSelected) {
            // Retirer toutes les permissions du module
            setData(
                'permissions',
                data.permissions.filter((p) => !modulePermissionNames.includes(p))
            );
        } else {
            // Ajouter toutes les permissions du module absentes
            const newPermissions = Array.from(new Set([...data.permissions, ...modulePermissionNames]));
            setData('permissions', newPermissions);
        }
    };

    /**
     * Coche ou décoche la totalité des permissions disponibles dans l'application.
     */
    const toggleAllPermissionsGlobal = () => {
        if (editingRole?.isSuperAdmin) return;

        const allAvailableNames = modules.flatMap((m) => m.permissions.map((p) => p.name));
        const allSelected = allAvailableNames.every((p) => data.permissions.includes(p));

        if (allSelected) {
            setData('permissions', []);
        } else {
            setData('permissions', allAvailableNames);
        }
    };

    /**
     * Soumet le formulaire de création ou de mise à jour du rôle.
     *
     * @param {React.FormEvent} e - Événement de soumission de formulaire.
     */
    const handleSubmitForm = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingRole) {
            put(`/administration/roles/${editingRole.id}`, {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                    reset();
                },
            });
        } else {
            post('/administration/roles', {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                    reset();
                },
            });
        }
    };

    /**
     * Confirme et exécute la suppression d'un rôle.
     */
    const handleConfirmDelete = () => {
        if (!roleToDelete) return;

        router.delete(`/administration/roles/${roleToDelete.id}`, {
            onSuccess: () => {
                setRoleToDelete(null);
            },
        });
    };

    return (
        <>
            <Head title="Rôles & Permissions - Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full">
                {/* En-tête principal avec bouton retour et action Nouveau rôle */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground mb-1">
                            <button
                                onClick={() => router.visit('/administration')}
                                className="hover:text-foreground flex items-center gap-1 transition-colors cursor-pointer"
                            >
                                <ArrowLeft className="size-3.5" />
                                Administration
                            </button>
                            <span>/</span>
                            <span className="text-foreground">Rôles & Permissions</span>
                        </div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-3">
                            <ShieldCheck className="size-8 text-amber-500" />
                            Rôles & Permissions
                        </h1>
                        <p className="text-muted-foreground text-sm mt-1">
                            Définissez les profils de sécurité et contrôlez les privilèges d'accès aux modules scolaires.
                        </p>
                    </div>

                    <Button
                        onClick={handleOpenCreateModal}
                        className="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold shadow-sm flex items-center gap-2 self-start sm:self-auto cursor-pointer"
                    >
                        <Plus className="size-4" />
                        Nouveau rôle
                    </Button>
                </div>

                {/* Cartes récapitulatives / Statistiques */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="p-4 flex items-center gap-4">
                        <div className="flex size-11 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 border border-amber-200/60 dark:border-amber-900/40">
                            <Shield className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground font-medium">Total des Rôles</p>
                            <p className="text-xl font-bold text-foreground">{stats.totalRoles}</p>
                        </div>
                    </Card>

                    <Card className="p-4 flex items-center gap-4">
                        <div className="flex size-11 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 border border-blue-200/60 dark:border-blue-900/40">
                            <KeyRound className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground font-medium">Permissions actives</p>
                            <p className="text-xl font-bold text-foreground">{stats.totalPermissions}</p>
                        </div>
                    </Card>

                    <Card className="p-4 flex items-center gap-4">
                        <div className="flex size-11 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 border border-emerald-200/60 dark:border-emerald-900/40">
                            <Users className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground font-medium">Utilisateurs rattachés</p>
                            <p className="text-xl font-bold text-foreground">{stats.totalAssignedUsers}</p>
                        </div>
                    </Card>

                    <Card className="p-4 flex items-center gap-4">
                        <div className="flex size-11 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 border border-purple-200/60 dark:border-purple-900/40">
                            <Lock className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground font-medium">Rôles système protégés</p>
                            <p className="text-xl font-bold text-foreground">{stats.systemRoles}</p>
                        </div>
                    </Card>
                </div>

                {/* Barre de recherche et filtrage */}
                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                        <Input
                            placeholder="Rechercher un rôle par libellé ou permission..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9 bg-card"
                        />
                    </div>
                </div>

                {/* Grille des cartes de rôles */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {filteredRoles.map((role) => {
                        const percent = totalPermissions > 0 ? Math.round((role.permissionsCount / totalPermissions) * 100) : 0;
                        const canDelete = !role.isSystem && role.usersCount === 0;

                        return (
                            <Card
                                key={role.id}
                                className="group flex flex-col justify-between border-border/80 hover:border-slate-300 dark:hover:border-slate-700 transition-all shadow-xs hover:shadow-sm"
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${
                                                    role.isSuperAdmin
                                                        ? 'bg-amber-500/10 text-amber-600 border border-amber-500/20'
                                                        : role.isSystem
                                                        ? 'bg-blue-500/10 text-blue-600 border border-blue-500/20'
                                                        : 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20'
                                                }`}
                                            >
                                                {role.isSuperAdmin ? (
                                                    <Sparkles className="size-5" />
                                                ) : (
                                                    <Shield className="size-5" />
                                                )}
                                            </div>
                                            <div>
                                                <CardTitle className="text-base font-bold text-foreground group-hover:text-primary transition-colors">
                                                    {role.displayName}
                                                </CardTitle>
                                                <CardDescription className="text-xs font-mono mt-0.5">
                                                    {role.name}
                                                </CardDescription>
                                            </div>
                                        </div>

                                        {role.isSystem ? (
                                            <Badge variant="secondary" className="text-[10px] font-semibold uppercase">
                                                Système
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-[10px] font-semibold uppercase border-emerald-300 text-emerald-700 dark:text-emerald-400">
                                                Personnalisé
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent className="flex flex-col gap-4 flex-1">
                                    {/* Statistiques d'assignation et ratio de permissions */}
                                    <div className="flex items-center justify-between text-xs text-muted-foreground border-y py-2.5">
                                        <span className="flex items-center gap-1.5 font-medium">
                                            <UserCheck className="size-3.5 text-slate-500" />
                                            {role.usersCount} utilisateur{role.usersCount > 1 ? 's' : ''}
                                        </span>
                                        <span className="font-semibold text-foreground">
                                            {role.permissionsCount} / {totalPermissions} droits ({percent}%)
                                        </span>
                                    </div>

                                    {/* Jauge de couverture des permissions */}
                                    <div className="w-full bg-muted rounded-full h-2 overflow-hidden">
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

                                    {/* Modules actifs pour ce rôle */}
                                    <div>
                                        <p className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/80 mb-2">
                                            Modules couverts
                                        </p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {modules.map((m) => {
                                                const hasAny = m.permissions.some((p) =>
                                                    role.permissions.includes(p.name)
                                                );
                                                if (!hasAny) return null;

                                                const IconComp = moduleIconMap[m.icon] || Shield;
                                                return (
                                                    <span
                                                        key={m.id}
                                                        className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-md bg-secondary text-secondary-foreground"
                                                        title={m.name}
                                                    >
                                                        <IconComp className="size-3" />
                                                        {m.name.split(' ')[0]}
                                                    </span>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {/* Boutons d'action */}
                                    <div className="flex items-center gap-2 pt-2 mt-auto">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleOpenEditModal(role)}
                                            className="flex-1 text-xs flex items-center justify-center gap-1.5 cursor-pointer"
                                        >
                                            <Pencil className="size-3.5" />
                                            {role.isSuperAdmin ? 'Voir les droits' : 'Modifier les droits'}
                                        </Button>

                                        {!role.isSystem && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={!canDelete}
                                                onClick={() => setRoleToDelete(role)}
                                                className="text-xs text-destructive hover:bg-destructive/10 hover:text-destructive border-border/80 cursor-pointer disabled:opacity-40"
                                                title={
                                                    !canDelete
                                                        ? 'Ce rôle ne peut pas être supprimé car des utilisateurs lui sont rattachés.'
                                                        : 'Supprimer ce rôle'
                                                }
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {filteredRoles.length === 0 && (
                    <div className="flex flex-col items-center justify-center p-12 text-center border rounded-2xl bg-card">
                        <AlertCircle className="size-10 text-muted-foreground/60 mb-3" />
                        <h3 className="text-lg font-semibold text-foreground">Aucun rôle trouvé</h3>
                        <p className="text-sm text-muted-foreground mt-1 max-w-sm">
                            Aucun rôle ne correspond à vos critères de recherche « {searchQuery} ».
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSearchQuery('')}
                            className="mt-4 cursor-pointer"
                        >
                            Réinitialiser le filtre
                        </Button>
                    </div>
                )}
            </div>

            {/* MODALE DE CRÉATION ET MODIFICATION DE RÔLE */}
            <Dialog open={isFormModalOpen} onOpenChange={setIsFormModalOpen}>
                <DialogContent className="sm:max-w-3xl max-h-[85vh] flex flex-col p-0 overflow-hidden">
                    <DialogHeader className="p-6 pb-4 border-b">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 border border-amber-200/60 dark:border-amber-900/40">
                                <ShieldCheck className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-xl font-bold">
                                    {editingRole ? `Modifier le rôle : ${editingRole.displayName}` : 'Créer un nouveau rôle'}
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                    Définissez le libellé du rôle et cochez les permissions accordées par module.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <form onSubmit={handleSubmitForm} className="flex flex-col flex-1 overflow-hidden">
                        <div className="flex-1 overflow-y-auto p-6 space-y-6">
                            {/* Information spéciale pour le rôle Super Administrateur */}
                            {editingRole?.isSuperAdmin && (
                                <div className="p-4 rounded-xl border border-amber-300/80 bg-amber-50/70 dark:bg-amber-950/30 dark:border-amber-800 text-amber-900 dark:text-amber-200 text-xs flex items-start gap-3">
                                    <Sparkles className="size-5 text-amber-600 shrink-0 mt-0.5" />
                                    <div>
                                        <p className="font-semibold text-sm">Rôle Super Administrateur protégé</p>
                                        <p className="mt-0.5 text-amber-800/90 dark:text-amber-300">
                                            Le Super Administrateur dispose automatiquement de la totalité des privilèges du système SYGES. Ses permissions ne peuvent pas être restreintes afin de garantir la continuité de l'administration.
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Champ de saisie du nom du rôle */}
                            <div className="space-y-2">
                                <Label htmlFor="role-name" className="text-sm font-semibold">
                                    Nom du rôle <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="role-name"
                                    type="text"
                                    disabled={editingRole?.isSuperAdmin}
                                    placeholder="Ex: Comptable, Surveillant Général..."
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="bg-card"
                                />
                                {errors.name && (
                                    <p className="text-xs text-destructive font-medium">{errors.name}</p>
                                )}
                            </div>

                            <Separator />

                            {/* Section des permissions par modules scolaires */}
                            <div className="space-y-4">
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div>
                                        <h3 className="text-sm font-bold uppercase tracking-wider text-foreground">
                                            Habilitations & Permissions ({data.permissions.length} sélectionnée{data.permissions.length > 1 ? 's' : ''})
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Activez les droits d'accès spécifiques pour chaque module scolaire.
                                        </p>
                                    </div>

                                    {!editingRole?.isSuperAdmin && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={toggleAllPermissionsGlobal}
                                            className="text-xs flex items-center gap-1.5 self-start sm:self-auto cursor-pointer"
                                        >
                                            <CheckCheck className="size-3.5" />
                                            {data.permissions.length === totalPermissions
                                                ? 'Tout désélectionner'
                                                : 'Tout sélectionner'}
                                        </Button>
                                    )}
                                </div>

                                {/* Liste des modules avec leurs permissions */}
                                <div className="space-y-4">
                                    {modules.map((module) => {
                                        const IconComp = moduleIconMap[module.icon] || Shield;
                                        const modulePermissionNames = module.permissions.map((p) => p.name);
                                        const selectedInModule = module.permissions.filter((p) =>
                                            data.permissions.includes(p.name)
                                        ).length;
                                        const allInModuleSelected = selectedInModule === module.permissions.length;

                                        return (
                                            <div
                                                key={module.id}
                                                className="border rounded-xl p-4 bg-card/60 space-y-3"
                                            >
                                                {/* En-tête du module */}
                                                <div className="flex items-center justify-between gap-3 border-b pb-2.5">
                                                    <div className="flex items-center gap-2.5">
                                                        <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                            <IconComp className="size-4" />
                                                        </div>
                                                        <div>
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
                                                            <p className="text-xs text-muted-foreground">
                                                                {module.description}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    {!editingRole?.isSuperAdmin && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => toggleModulePermissions(module)}
                                                            className="text-xs h-7 px-2 text-muted-foreground hover:text-foreground cursor-pointer"
                                                        >
                                                            {allInModuleSelected ? (
                                                                <Square className="size-3.5 mr-1" />
                                                            ) : (
                                                                <CheckSquare className="size-3.5 mr-1" />
                                                            )}
                                                            {allInModuleSelected ? 'Aucun' : 'Tous'}
                                                        </Button>
                                                    )}
                                                </div>

                                                {/* Grille des permissions du module */}
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                                    {module.permissions.map((permission) => {
                                                        const isChecked =
                                                            editingRole?.isSuperAdmin ||
                                                            data.permissions.includes(permission.name);

                                                        return (
                                                            <div
                                                                key={permission.id}
                                                                onClick={() => togglePermission(permission.name)}
                                                                className={`flex items-start gap-3 p-2.5 rounded-lg border transition-colors cursor-pointer select-none ${
                                                                    isChecked
                                                                        ? 'bg-primary/5 border-primary/40'
                                                                        : 'bg-card border-border/70 hover:bg-muted/50'
                                                                }`}
                                                            >
                                                                <Checkbox
                                                                    checked={isChecked}
                                                                    disabled={editingRole?.isSuperAdmin}
                                                                    onCheckedChange={() => togglePermission(permission.name)}
                                                                    className="mt-0.5"
                                                                />
                                                                <div className="flex-1 min-w-0">
                                                                    <div className="flex items-center justify-between gap-1">
                                                                        <p className="text-xs font-semibold text-foreground">
                                                                            {permission.label}
                                                                        </p>
                                                                    </div>
                                                                    <p className="text-[11px] text-muted-foreground leading-snug mt-0.5">
                                                                        {permission.description}
                                                                    </p>
                                                                    <span className="inline-block font-mono text-[10px] text-muted-foreground/60 mt-1">
                                                                        {permission.name}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>

                        {/* Pied de page du dialogue */}
                        <DialogFooter className="p-4 border-t bg-muted/20 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsFormModalOpen(false)}
                                className="cursor-pointer"
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold cursor-pointer"
                            >
                                {processing ? (
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-3.5 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin" />
                                        Enregistrement...
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-1.5">
                                        <Check className="size-4" />
                                        {editingRole ? 'Mettre à jour le rôle' : 'Créer le rôle'}
                                    </span>
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODALE DE CONFIRMATION DE SUPPRESSION */}
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
                                    Cette opération est irréversible et retirera le profil d'accès.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="py-2 text-sm text-foreground">
                        Êtes-vous certain de vouloir supprimer définitivement le rôle{' '}
                        <strong className="font-semibold text-foreground">« {roleToDelete?.displayName} »</strong> ?
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

import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    AlertTriangle,
    ArrowUpCircle,
    CheckCircle2,
    Clock,
    CloudDownload,
    Database,
    Download,
    GitBranch,
    GitCommit,
    History,
    Layers,
    Loader2,
    Mail,
    RefreshCw,
    Rocket,
    ShieldCheck,
    Sparkles,
    Tag,
    Wifi,
    WifiOff,
    Wrench,
    XCircle,
} from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn, formatDate } from '@/lib/utils';

/**
 * Catégorie normalisée SemVer d'une version applicative SYGES.
 * - `major` : fonctionnalités lourdes ou changements architecturaux majeurs (`vX.0.0`).
 * - `minor` : nouvelles fonctionnalités métier rétrocompatibles (`v0.X.0`).
 * - `patch` : correctifs de bugs et ajustements de stabilité (`v0.0.X`).
 */
export type ReleaseType = 'major' | 'minor' | 'patch';

/**
 * Informations détaillées sur la version locale actuellement déployée sur le serveur.
 */
export interface LocalVersionInfo {
    version: string;
    version_number: string;
    is_tagged_release: boolean;
    branch: string;
    commit_short: string;
    commit_full: string;
    commit_date: string;
    commit_time: string;
    commit_message: string;
    remote_url: string;
}

/**
 * Représentation d'un tag Git SemVer validé (`vX.Y.Z`) dans le gestionnaire de versions.
 */
export interface SemverTagItem {
    tag: string;
    version_number: string;
    type: ReleaseType;
    type_label: string;
    date: string;
    subject: string;
    body: string;
    commit_short: string;
    status: 'installed' | 'available' | 'older';
}

/**
 * Représentation d'un fichier de sauvegarde SQL présent dans `storage/app/backups`.
 */
export interface BackupFileItem {
    filename: string;
    size: number;
    size_formatted: string;
    created_date: string;
    created_time: string;
    created_at: string;
}

/**
 * Entrée du journal d'historique des mises à jour appliquées sur le serveur.
 */
export interface UpdateHistoryItem {
    from_version: string;
    to_version: string;
    release_type: ReleaseType;
    release_type_label: string;
    backup_filename: string;
    email_sent: boolean;
    triggered_by: string;
    status: 'success' | 'failed';
    updated_date: string;
    updated_time: string;
    message: string;
}

/**
 * Élément du journal des commits entre la version locale et un tag distant disponible.
 */
export interface ChangelogCommitItem {
    hash: string;
    date: string;
    message: string;
}

/**
 * Étape unitaire exécutée au sein du pipeline transactionnel de mise à jour.
 */
export interface PipelineStepItem {
    key: string;
    label: string;
    status: 'success' | 'error' | 'pending';
    detail: string;
}

/**
 * Diagnostic retourné par l'endpoint `POST /administration/system-update/check`.
 */
interface UpdateCheckResult {
    success: boolean;
    online: boolean;
    has_update: boolean;
    is_up_to_date: boolean;
    current_version?: string;
    latest_version?: string;
    release_type?: ReleaseType;
    release_type_label?: string;
    release_date?: string;
    release_subject?: string;
    release_notes?: string;
    available_count?: number;
    changelog?: ChangelogCommitItem[];
    tags_catalog?: SemverTagItem[];
    version_info?: LocalVersionInfo;
    message: string;
}

/**
 * Propriétés injectées par Inertia depuis `SystemUpdateController::index`.
 */
interface SystemUpdatePageProps {
    versionInfo: LocalVersionInfo;
    tagsCatalog: SemverTagItem[];
    backups: BackupFileItem[];
    history: UpdateHistoryItem[];
    hasInternet: boolean;
    developerEmail: string;
    csrfToken: string;
}

/**
 * Récupère le jeton CSRF courant depuis la balise `<meta name="csrf-token">` ou la prop initiale.
 *
 * @param {string} fallbackToken Jeton CSRF initial fourni par Inertia.
 * @returns {string} Jeton CSRF valide pour les requêtes `fetch` POST.
 */
function resolveCsrfToken(fallbackToken: string): string {
    if (typeof document !== 'undefined') {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const content = meta?.getAttribute('content');
        if (content) {
            return content;
        }
    }
    return fallbackToken;
}

/**
 * Extrait la valeur décodée du cookie `XSRF-TOKEN` de Laravel s'il est présent.
 *
 * @returns {string | null} Jeton XSRF décodé ou `null`.
 */
function resolveXsrfCookie(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Composant badge sémantique illustrant le type d'une version SemVer (`major`, `minor`, `patch`).
 *
 * @param {{ type: ReleaseType }} props Type de version SemVer.
 * @returns {JSX.Element} Badge coloré avec icône distinctive.
 */
function ReleaseTypeBadge({ type }: { type: ReleaseType }) {
    if (type === 'major') {
        return (
            <Badge
                variant="outline"
                className="gap-1.5 border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800/70 dark:bg-rose-950/50 dark:text-rose-300 font-semibold"
            >
                <Rocket className="size-3.5" />
                <span>Majeure (Fonctionnalités lourdes)</span>
            </Badge>
        );
    }

    if (type === 'minor') {
        return (
            <Badge
                variant="outline"
                className="gap-1.5 border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800/70 dark:bg-sky-950/50 dark:text-sky-300 font-semibold"
            >
                <Sparkles className="size-3.5" />
                <span>Mineure (Nouvelles fonctionnalités)</span>
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="gap-1.5 border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800/70 dark:bg-emerald-950/50 dark:text-emerald-300 font-semibold"
        >
            <Wrench className="size-3.5" />
            <span>Patch (Correction de bugs)</span>
        </Badge>
    );
}

/**
 * Page d'administration du Gestionnaire de Versions SemVer et de Mise à Jour Système distante (GitHub).
 *
 * Réservée exclusivement au Super Administrateur (`superadmin`).
 * Permet de :
 * - Contrôler la connectivité Internet du serveur local,
 * - Vérifier la présence de tags SemVer validés (`vX.Y.Z`) sur GitHub (en ignorant tout push non tagué),
 * - Sauvegarder la base SQL localement et l'expédier par email au développeur,
 * - Appliquer la mise à jour complète (Backup SQL -> Maintenance -> Checkout Tag -> Migrate -> Optimize -> Up),
 * - Consulter les tableaux TanStack des versions, des archives `.sql` et de l'historique des mises à jour.
 *
 * @param {SystemUpdatePageProps} props Propriétés Inertia transmises par le contrôleur.
 * @returns {JSX.Element} Interface complète de gestion des mises à jour système.
 */
export default function SystemUpdateIndex({
    versionInfo: initialVersionInfo,
    tagsCatalog: initialTagsCatalog,
    backups: initialBackups,
    history: initialHistory,
    hasInternet: initialHasInternet,
    developerEmail,
    csrfToken,
}: SystemUpdatePageProps) {
    // États réactifs synchronisés avec les retours JSON du backend
    const [versionInfo, setVersionInfo] = useState<LocalVersionInfo>(initialVersionInfo);
    const [tagsCatalog, setTagsCatalog] = useState<SemverTagItem[]>(initialTagsCatalog);
    const [backups, setBackups] = useState<BackupFileItem[]>(initialBackups);
    const [history, setHistory] = useState<UpdateHistoryItem[]>(initialHistory);
    const [isOnline, setIsOnline] = useState<boolean>(initialHasInternet);

    // États d'opération asynchrone
    const [isChecking, setIsChecking] = useState<boolean>(false);
    const [isUpdating, setIsUpdating] = useState<boolean>(false);
    const [isBackingUp, setIsBackingUp] = useState<boolean>(false);

    // Résultat de la dernière recherche de mise à jour et rapport de pipeline
    const [checkResult, setCheckResult] = useState<UpdateCheckResult | null>(null);
    const [pipelineSteps, setPipelineSteps] = useState<PipelineStepItem[]>([]);
    const [completedVersion, setCompletedVersion] = useState<string | null>(null);

    // Modale de confirmation avant lancement effectif d'une mise à jour
    const [confirmTargetTag, setConfirmTargetTag] = useState<string | null>(null);
    const [confirmReleaseType, setConfirmReleaseType] = useState<ReleaseType>('patch');

    // Écouteur passif de connectivité navigateur pour un retour visuel immédiat
    useEffect(() => {
        const handleBrowserOnline = () => setIsOnline(true);
        const handleBrowserOffline = () => setIsOnline(false);

        window.addEventListener('online', handleBrowserOnline);
        window.addEventListener('offline', handleBrowserOffline);

        return () => {
            window.removeEventListener('online', handleBrowserOnline);
            window.removeEventListener('offline', handleBrowserOffline);
        };
    }, []);

    /**
     * Envoie une requête JSON sécurisée par CSRF vers un endpoint d'administration.
     */
    const postJson = useCallback(
        async <T,>(url: string, payload: Record<string, unknown> = {}): Promise<{ ok: boolean; data: T }> => {
            const headers: Record<string, string> = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': resolveCsrfToken(csrfToken),
            };

            const xsrf = resolveXsrfCookie();
            if (xsrf) {
                headers['X-XSRF-TOKEN'] = xsrf;
            }

            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify(payload),
            });

            const data = (await response.json()) as T;
            return { ok: response.ok, data };
        },
        [csrfToken],
    );

    /**
     * Vérifie la connexion Internet côté serveur et interroge GitHub pour détecter un tag SemVer validé.
     */
    const handleCheckUpdates = useCallback(async () => {
        setIsChecking(true);
        setPipelineSteps([]);

        try {
            const { data } = await postJson<UpdateCheckResult>('/administration/system-update/check');

            setIsOnline(Boolean(data.online));
            setCheckResult(data);

            if (data.tags_catalog) {
                setTagsCatalog(data.tags_catalog);
            }
            if (data.version_info) {
                setVersionInfo(data.version_info);
            }

            if (!data.online) {
                toast.error(data.message);
                return;
            }

            if (!data.success) {
                toast.error(data.message);
                return;
            }

            if (data.has_update) {
                toast.success(data.message);
            } else {
                toast.success(data.message);
            }
        } catch {
            setIsOnline(false);
            toast.error(
                "Impossible de contacter le serveur ou la connexion Internet ne passe pas. Vérifiez votre accès réseau.",
            );
        } finally {
            setIsChecking(false);
        }
    }, [postJson]);

    /**
     * Ouvre la boîte de dialogue de confirmation pour installer un tag SemVer validé précis.
     */
    const promptInstallTag = useCallback((tag: string, type: ReleaseType) => {
        setConfirmTargetTag(tag);
        setConfirmReleaseType(type);
    }, []);

    /**
     * Exécute le pipeline complet de mise à jour après validation dans la modale de confirmation.
     */
    const handleRunUpdate = useCallback(async () => {
        const target = confirmTargetTag;
        setConfirmTargetTag(null);
        setIsUpdating(true);
        setCompletedVersion(null);
        setPipelineSteps([
            {
                key: 'running',
                label: 'Exécution du pipeline de mise à jour sécurisé en cours...',
                status: 'pending',
                detail: 'Sauvegarde SQL, téléchargement du tag GitHub, migrations et optimisation des caches.',
            },
        ]);

        try {
            const { ok, data } = await postJson<{
                success: boolean;
                online: boolean;
                new_version?: string;
                steps?: PipelineStepItem[];
                version_info?: LocalVersionInfo;
                tags_catalog?: SemverTagItem[];
                history?: UpdateHistoryItem[];
                backups?: BackupFileItem[];
                message: string;
            }>('/administration/system-update/run', target ? { tag: target } : {});

            setIsOnline(Boolean(data.online));

            if (data.steps) {
                setPipelineSteps(data.steps);
            }

            if (ok && data.success) {
                if (data.version_info) setVersionInfo(data.version_info);
                if (data.tags_catalog) setTagsCatalog(data.tags_catalog);
                if (data.history) setHistory(data.history);
                if (data.backups) setBackups(data.backups);

                setCheckResult(null);
                setCompletedVersion(data.new_version ?? target ?? null);
                toast.success(data.message);
            } else {
                if (data.backups) setBackups(data.backups);
                if (data.history) setHistory(data.history);
                toast.error(data.message || "La mise à jour n'a pas pu aboutir.");
            }
        } catch {
            toast.error(
                "La communication avec le serveur a été interrompue pendant l'opération. Veuillez actualiser la page.",
            );
        } finally {
            setIsUpdating(false);
        }
    }, [confirmTargetTag, postJson]);

    /**
     * Génère manuellement une sauvegarde SQL immédiate et l'envoie par email au développeur.
     */
    const handleManualBackup = useCallback(async () => {
        setIsBackingUp(true);
        try {
            const { ok, data } = await postJson<{
                success: boolean;
                backups?: BackupFileItem[];
                message: string;
            }>('/administration/system-update/backup');

            if (ok && data.success) {
                if (data.backups) {
                    setBackups(data.backups);
                }
                toast.success(data.message);
            } else {
                toast.error(data.message || 'Échec de la sauvegarde SQL.');
            }
        } catch {
            toast.error('Une erreur réseau est survenue lors de la génération de la sauvegarde SQL.');
        } finally {
            setIsBackingUp(false);
        }
    }, [postJson]);

    // Dernière version validée disponible détectée dans le catalogue local/synchronisé
    const highestAvailableTag = useMemo(
        () => tagsCatalog.find((item) => item.status === 'available') ?? null,
        [tagsCatalog],
    );

    /**
     * Colonnes TanStack Table pour le Gestionnaire des Versions (Tags Git SemVer validés).
     */
    const tagsColumns = useMemo<ColumnDef<SemverTagItem>[]>(
        () => [
            {
                accessorKey: 'tag',
                header: 'Tag Version',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2.5">
                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 px-2.5 py-1 font-mono text-xs font-bold text-foreground border border-slate-200 dark:border-slate-700">
                            <Tag className="size-3.5 text-sky-600 dark:text-sky-400" />
                            {row.original.tag}
                        </span>
                        <span className="font-mono text-[11px] text-muted-foreground">
                            #{row.original.commit_short}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'type',
                header: 'Catégorie SemVer',
                cell: ({ row }) => <ReleaseTypeBadge type={row.original.type} />,
            },
            {
                accessorKey: 'date',
                header: 'Date de publication',
                cell: ({ row }) => (
                    <span className="font-medium text-xs text-foreground whitespace-nowrap">
                        {formatDate(row.original.date)}
                    </span>
                ),
            },
            {
                accessorKey: 'subject',
                header: 'Contenu / Notes de version',
                cell: ({ row }) => (
                    <div className="max-w-md">
                        <p className="text-xs sm:text-sm font-medium text-foreground line-clamp-2">
                            {row.original.subject}
                        </p>
                    </div>
                ),
            },
            {
                accessorKey: 'status',
                header: 'État',
                cell: ({ row }) => {
                    const status = row.original.status;
                    if (status === 'installed') {
                        return (
                            <Badge className="gap-1 bg-emerald-600 hover:bg-emerald-600 text-white">
                                <CheckCircle2 className="size-3.5" />
                                Version active
                            </Badge>
                        );
                    }
                    if (status === 'available') {
                        return (
                            <Badge
                                variant="outline"
                                className="gap-1 border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-300 font-semibold"
                            >
                                <ArrowUpCircle className="size-3.5" />
                                Mise à jour prête
                            </Badge>
                        );
                    }
                    return (
                        <Badge variant="secondary" className="text-muted-foreground">
                            Antérieure
                        </Badge>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Action',
                cell: ({ row }) => {
                    if (row.original.status !== 'available') {
                        return (
                            <span className="text-xs text-muted-foreground italic">
                                {row.original.status === 'installed' ? 'Déployée' : '—'}
                            </span>
                        );
                    }

                    return (
                        <Button
                            type="button"
                            size="sm"
                            disabled={isUpdating || !isOnline}
                            onClick={() => promptInstallTag(row.original.tag, row.original.type)}
                            className="h-8 gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs cursor-pointer shadow-xs"
                        >
                            <CloudDownload className="size-3.5" />
                            <span>Installer {row.original.tag}</span>
                        </Button>
                    );
                },
            },
        ],
        [isOnline, isUpdating, promptInstallTag],
    );

    /**
     * Colonnes TanStack Table pour les Archives de Sauvegarde SQL (`storage/app/backups`).
     */
    const backupsColumns = useMemo<ColumnDef<BackupFileItem>[]>(
        () => [
            {
                accessorKey: 'filename',
                header: 'Fichier SQL',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Database className="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <span className="font-mono text-xs font-semibold text-foreground break-all">
                            {row.original.filename}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'size_formatted',
                header: 'Taille',
                cell: ({ row }) => (
                    <Badge variant="secondary" className="font-mono text-xs">
                        {row.original.size_formatted}
                    </Badge>
                ),
            },
            {
                accessorKey: 'created_date',
                header: 'Date & Heure',
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5 text-xs text-foreground whitespace-nowrap">
                        <Clock className="size-3.5 text-muted-foreground" />
                        <span className="font-medium">{formatDate(row.original.created_date)}</span>
                        <span className="text-muted-foreground">à {row.original.created_time}</span>
                    </div>
                ),
            },
            {
                id: 'actions',
                header: 'Téléchargement',
                cell: ({ row }) => (
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1.5 border-sky-200 bg-sky-50/80 text-sky-700 hover:bg-sky-100 hover:text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300 text-xs cursor-pointer"
                    >
                        <a
                            href={`/administration/system-update/backups/${encodeURIComponent(row.original.filename)}`}
                            download={row.original.filename}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Download className="size-3.5" />
                            <span>Télécharger .SQL</span>
                        </a>
                    </Button>
                ),
            },
        ],
        [],
    );

    /**
     * Colonnes TanStack Table pour le Journal d'Historique des Mises à Jour.
     */
    const historyColumns = useMemo<ColumnDef<UpdateHistoryItem>[]>(
        () => [
            {
                accessorKey: 'updated_date',
                header: 'Date & Heure',
                cell: ({ row }) => (
                    <div className="text-xs whitespace-nowrap">
                        <span className="font-semibold text-foreground">
                            {formatDate(row.original.updated_date)}
                        </span>{' '}
                        <span className="text-muted-foreground">à {row.original.updated_time}</span>
                    </div>
                ),
            },
            {
                accessorKey: 'to_version',
                header: 'Transition de version',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2 font-mono text-xs">
                        <span className="rounded bg-muted px-2 py-0.5 text-muted-foreground">
                            {row.original.from_version}
                        </span>
                        <span className="text-muted-foreground">→</span>
                        <span className="rounded bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800 px-2 py-0.5 font-bold text-sky-700 dark:text-sky-300">
                            {row.original.to_version}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'release_type',
                header: 'Catégorie',
                cell: ({ row }) => <ReleaseTypeBadge type={row.original.release_type} />,
            },
            {
                accessorKey: 'backup_filename',
                header: 'Sauvegarde préalable',
                cell: ({ row }) => (
                    <div className="flex flex-col gap-0.5">
                        <span className="font-mono text-[11px] text-foreground">
                            {row.original.backup_filename}
                        </span>
                        <span className="text-[11px] text-muted-foreground">
                            {row.original.email_sent
                                ? 'Copie envoyée par email au développeur'
                                : 'Conservée sur le serveur local'}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'status',
                header: 'Statut',
                cell: ({ row }) =>
                    row.original.status === 'success' ? (
                        <Badge className="gap-1 bg-emerald-600 hover:bg-emerald-600 text-white">
                            <CheckCircle2 className="size-3.5" />
                            Réussie
                        </Badge>
                    ) : (
                        <Badge variant="destructive" className="gap-1">
                            <XCircle className="size-3.5" />
                            Échec sécurisé
                        </Badge>
                    ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Mise à jour & Versions Système" />

            {/* Conteneur principal plein écran avec marges adaptatives et débordement maîtrisé */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 min-w-0 w-full">
                {/* En-tête principal responsive avec indicateur réseau en direct et boutons d'action */}
                <div className="flex flex-col gap-4 2xl:flex-row 2xl:items-center 2xl:justify-between">
                    <div className="space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-xl sm:text-2xl lg:text-3xl font-bold tracking-tight text-foreground">
                                Mise à jour & Gestionnaire de Versions
                            </h1>
                            <Badge
                                variant="outline"
                                className={cn(
                                    'gap-1.5 px-2.5 py-1 text-xs font-semibold shrink-0',
                                    isOnline
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                        : 'border-red-300 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-300',
                                )}
                            >
                                {isOnline ? (
                                    <>
                                        <Wifi className="size-3.5" />
                                        <span>Internet opérationnel</span>
                                    </>
                                ) : (
                                    <>
                                        <WifiOff className="size-3.5" />
                                        <span>Connexion Internet absente</span>
                                    </>
                                )}
                            </Badge>
                        </div>
                        <p className="text-xs sm:text-sm text-muted-foreground max-w-3xl">
                            Déploiement sécurisé en un clic depuis les tags officiels validés sur GitHub (SemVer{' '}
                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs font-mono">vX.Y.Z</code>),
                            avec sauvegarde SQL automatique et notification développeur.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isBackingUp || isUpdating}
                            onClick={handleManualBackup}
                            className="gap-2 cursor-pointer w-full sm:w-auto"
                        >
                            {isBackingUp ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Database className="size-4 text-emerald-600 dark:text-emerald-400" />
                            )}
                            <span>Sauvegarder la BDD (.SQL)</span>
                        </Button>

                        <Button
                            type="button"
                            disabled={isChecking || isUpdating}
                            onClick={handleCheckUpdates}
                            className="gap-2 bg-sky-600 hover:bg-sky-700 text-white cursor-pointer shadow-xs w-full sm:w-auto"
                        >
                            {isChecking ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RefreshCw className="size-4" />
                            )}
                            <span>Rechercher une mise à jour</span>
                        </Button>

                        {(checkResult?.has_update || highestAvailableTag) && (
                            <Button
                                type="button"
                                disabled={isUpdating || !isOnline}
                                onClick={() =>
                                    promptInstallTag(
                                        checkResult?.latest_version ?? highestAvailableTag!.tag,
                                        checkResult?.release_type ?? highestAvailableTag!.type,
                                    )
                                }
                                className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-sm font-semibold w-full sm:w-auto"
                            >
                                {isUpdating ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <CloudDownload className="size-4" />
                                )}
                                <span>
                                    Mettre à jour ({checkResult?.latest_version ?? highestAvailableTag?.tag})
                                </span>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Alerte explicite si la connexion Internet ne passe pas */}
                {!isOnline && (
                    <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-2xl border border-red-300 bg-red-50/90 dark:border-red-900 dark:bg-red-950/40 p-4 sm:p-5">
                        <div className="flex items-start gap-3.5">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/60 text-red-700 dark:text-red-300">
                                <WifiOff className="size-5" />
                            </div>
                            <div className="space-y-1">
                                <h2 className="text-sm sm:text-base font-bold text-red-900 dark:text-red-200">
                                    La connexion Internet ne passe pas sur ce serveur
                                </h2>
                                <p className="text-xs sm:text-sm text-red-800/90 dark:text-red-300/90">
                                    Impossible de joindre GitHub pour télécharger de nouvelles versions. Veuillez
                                    connecter la machine à Internet (Wi-Fi, câble réseau ou partage 4G) puis
                                    cliquez sur <strong>Rechercher une mise à jour</strong>.
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={isChecking}
                            onClick={handleCheckUpdates}
                            className="border-red-300 bg-white hover:bg-red-50 text-red-800 shrink-0 cursor-pointer"
                        >
                            <RefreshCw className={cn('size-3.5 mr-1.5', isChecking && 'animate-spin')} />
                            Tester à nouveau
                        </Button>
                    </div>
                )}

                {/* Bannière verte de fin de mise à jour invitant à actualiser la page */}
                {completedVersion && (
                    <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-2xl border-2 border-emerald-400 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/50 p-5 shadow-sm">
                        <div className="flex items-start gap-3.5">
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
                                <CheckCircle2 className="size-6" />
                            </div>
                            <div className="space-y-1">
                                <h2 className="text-base sm:text-lg font-bold text-emerald-950 dark:text-emerald-100">
                                    Mise à jour vers {completedVersion} terminée avec succès !
                                </h2>
                                <p className="text-xs sm:text-sm text-emerald-900/90 dark:text-emerald-200/90">
                                    La base de données a été sauvegardée et migrée, et tous les caches du serveur
                                    ont été optimisés. Cliquez sur le bouton ci-contre pour actualiser la page et
                                    utiliser immédiatement les nouvelles fonctionnalités.
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shrink-0 cursor-pointer shadow-xs"
                        >
                            <RefreshCw className="size-4" />
                            <span>Actualiser la page maintenant</span>
                        </Button>
                    </div>
                )}

                {/* Grille de cartes KPI résumant l'état technique actuel */}
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <Card className="border-slate-200/90 dark:border-slate-800 shadow-2xs">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center justify-between text-xs font-semibold uppercase tracking-wider">
                                <span>Version Installée</span>
                                <Tag className="size-4 text-sky-600 dark:text-sky-400" />
                            </CardDescription>
                            <CardTitle className="text-2xl font-extrabold font-mono text-foreground">
                                {versionInfo.version}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground space-y-1">
                            <div className="flex items-center gap-1.5">
                                <GitCommit className="size-3.5 shrink-0" />
                                <span className="font-mono">#{versionInfo.commit_short}</span>
                                <span>• {formatDate(versionInfo.commit_date)}</span>
                            </div>
                            <p className="truncate" title={versionInfo.commit_message}>
                                {versionInfo.commit_message || 'Version initiale SYGES'}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/90 dark:border-slate-800 shadow-2xs">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center justify-between text-xs font-semibold uppercase tracking-wider">
                                <span>Politique de Versions</span>
                                <Layers className="size-4 text-emerald-600 dark:text-emerald-400" />
                            </CardDescription>
                            <CardTitle className="text-lg font-bold text-foreground">
                                Tags SemVer Validés
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground space-y-1">
                            <div className="flex items-center gap-1.5">
                                <GitBranch className="size-3.5 shrink-0" />
                                <span>Branche : </span>
                                <code className="font-mono font-semibold text-foreground">
                                    {versionInfo.branch}
                                </code>
                            </div>
                            <p>Les pushs non tagués sont ignorés.</p>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/90 dark:border-slate-800 shadow-2xs">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center justify-between text-xs font-semibold uppercase tracking-wider">
                                <span>Sauvegardes SQL</span>
                                <Database className="size-4 text-amber-600 dark:text-amber-400" />
                            </CardDescription>
                            <CardTitle className="text-2xl font-extrabold text-foreground">
                                {backups.length}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground space-y-1">
                            <p>
                                {backups[0]
                                    ? `Dernière : ${formatDate(backups[0].created_date)} à ${backups[0].created_time}`
                                    : 'Sauvegarde auto avant chaque mise à jour'}
                            </p>
                            <p className="truncate">Stockage : storage/app/backups</p>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/90 dark:border-slate-800 shadow-2xs">
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center justify-between text-xs font-semibold uppercase tracking-wider">
                                <span>Copie Développeur</span>
                                <Mail className="size-4 text-blue-600 dark:text-blue-400" />
                            </CardDescription>
                            <CardTitle className="text-sm sm:text-base font-bold text-foreground truncate">
                                {developerEmail}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground space-y-1">
                            <div className="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400 font-medium">
                                <ShieldCheck className="size-3.5 shrink-0" />
                                <span>Accès exclusif Super Admin</span>
                            </div>
                            <p>Expédition automatique du dump .SQL</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Carte détaillée lorsqu'une nouvelle version SemVer validée est détectée */}
                {checkResult && checkResult.online && checkResult.has_update && checkResult.latest_version && (
                    <Card className="border-2 border-sky-300 dark:border-sky-800 bg-sky-50/40 dark:bg-sky-950/20 shadow-sm">
                        <CardHeader className="pb-3">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2.5">
                                        <Badge className="bg-sky-600 hover:bg-sky-600 text-white font-mono text-xs px-2.5 py-0.5">
                                            {checkResult.current_version} → {checkResult.latest_version}
                                        </Badge>
                                        {checkResult.release_type && (
                                            <ReleaseTypeBadge type={checkResult.release_type} />
                                        )}
                                        {checkResult.release_date && (
                                            <span className="text-xs text-muted-foreground">
                                                Publiée le {formatDate(checkResult.release_date)}
                                            </span>
                                        )}
                                    </div>
                                    <CardTitle className="text-lg sm:text-xl font-bold text-foreground pt-1">
                                        {checkResult.release_subject ||
                                            `Nouvelle version ${checkResult.latest_version} disponible`}
                                    </CardTitle>
                                </div>

                                <Button
                                    type="button"
                                    disabled={isUpdating}
                                    onClick={() =>
                                        promptInstallTag(
                                            checkResult.latest_version!,
                                            checkResult.release_type ?? 'patch',
                                        )
                                    }
                                    className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold cursor-pointer shrink-0"
                                >
                                    <CloudDownload className="size-4" />
                                    <span>Lancer la mise à jour vers {checkResult.latest_version}</span>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {checkResult.release_notes &&
                                checkResult.release_notes !== checkResult.release_subject && (
                                    <div className="rounded-xl border border-slate-200 dark:border-slate-800 bg-card p-3.5 text-xs sm:text-sm whitespace-pre-line text-foreground">
                                        {checkResult.release_notes}
                                    </div>
                                )}

                            {checkResult.changelog && checkResult.changelog.length > 0 && (
                                <div className="space-y-2">
                                    <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                        Détail des correctifs et évolutions inclus ({checkResult.changelog.length})
                                    </h3>
                                    <div className="max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-card divide-y divide-border">
                                        {checkResult.changelog.map((commit) => (
                                            <div
                                                key={commit.hash}
                                                className="flex items-center justify-between gap-3 px-3.5 py-2 text-xs"
                                            >
                                                <div className="flex items-center gap-2.5 min-w-0">
                                                    <code className="font-mono text-[11px] text-sky-700 dark:text-sky-400 shrink-0">
                                                        {commit.hash}
                                                    </code>
                                                    <span className="truncate text-foreground">
                                                        {commit.message}
                                                    </span>
                                                </div>
                                                <span className="text-muted-foreground shrink-0">
                                                    {formatDate(commit.date)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Bandeau de confirmation lorsque l'application est déjà à jour après vérification */}
                {checkResult && checkResult.online && checkResult.is_up_to_date && !completedVersion && (
                    <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/70 dark:border-emerald-900/60 dark:bg-emerald-950/30 px-4 py-3 text-xs sm:text-sm text-emerald-900 dark:text-emerald-200">
                        <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <span>{checkResult.message}</span>
                    </div>
                )}

                {/* Bandeau d'erreur technique Git/SSH éventuel */}
                {checkResult && checkResult.online && !checkResult.success && (
                    <div className="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 p-4 text-xs sm:text-sm text-amber-950 dark:text-amber-200">
                        <AlertTriangle className="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <p className="font-semibold">{checkResult.message}</p>
                            <p className="text-xs text-amber-800/90 dark:text-amber-300/80">
                                Consultez la documentation{' '}
                                <code className="font-mono">docs/GUIDE_DEPLOYMENT_SERVEUR.md</code> pour la
                                configuration de la clé SSH GitHub sans mot de passe.
                            </p>
                        </div>
                    </div>
                )}

                {/* Journal visuel en direct des étapes du pipeline lors d'une mise à jour */}
                {pipelineSteps.length > 0 && (
                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base font-bold">
                                Progression des étapes de mise à jour du serveur
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Exécution séquentielle contrôlée (Sauvegarde SQL, Maintenance, Git Tag, Migrate,
                                Optimize Clear & Optimize).
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                {pipelineSteps.map((step) => (
                                    <div
                                        key={step.key}
                                        className={cn(
                                            'flex items-start gap-3 rounded-xl border p-3 text-xs',
                                            step.status === 'success' &&
                                                'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-950/20',
                                            step.status === 'error' &&
                                                'border-red-200 bg-red-50/60 dark:border-red-900/60 dark:bg-red-950/30',
                                            step.status === 'pending' &&
                                                'border-sky-200 bg-sky-50/50 dark:border-sky-900/50 dark:bg-sky-950/20',
                                        )}
                                    >
                                        {step.status === 'success' && (
                                            <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                                        )}
                                        {step.status === 'error' && (
                                            <XCircle className="size-4 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                                        )}
                                        {step.status === 'pending' && (
                                            <Loader2 className="size-4 text-sky-600 animate-spin shrink-0 mt-0.5" />
                                        )}
                                        <div className="min-w-0 space-y-0.5">
                                            <p className="font-semibold text-foreground">{step.label}</p>
                                            <p className="text-muted-foreground break-words">{step.detail}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Légende pédagogique de la norme SemVer exigée */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div className="flex items-start gap-3 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-card p-3.5">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400">
                            <Rocket className="size-4" />
                        </div>
                        <div className="text-xs space-y-0.5">
                            <p className="font-bold text-foreground">Version Majeure (vX.0.0)</p>
                            <p className="text-muted-foreground">
                                Refontes globales et fonctionnalités lourdes structurantes du système.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-card p-3.5">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400">
                            <Sparkles className="size-4" />
                        </div>
                        <div className="text-xs space-y-0.5">
                            <p className="font-bold text-foreground">Version Mineure (v0.X.0)</p>
                            <p className="text-muted-foreground">
                                Ajout de nouvelles fonctionnalités métier demandées par l'établissement.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-card p-3.5">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                            <Wrench className="size-4" />
                        </div>
                        <div className="text-xs space-y-0.5">
                            <p className="font-bold text-foreground">Patch Correctif (v0.0.X)</p>
                            <p className="text-muted-foreground">
                                Corrections rapides de bugs, sécurité et optimisations sans changement lourd.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Onglets contenant les 3 tableaux de données TanStack Table */}
                <Tabs defaultValue="versions" className="space-y-4">
                    <div className="overflow-x-auto pb-1">
                        <TabsList className="h-auto p-1 gap-1">
                            <TabsTrigger value="versions" className="gap-2 py-1.5 text-xs sm:text-sm cursor-pointer">
                                <Tag className="size-4 text-sky-600 dark:text-sky-400" />
                                <span>Catalogue des Versions ({tagsCatalog.length})</span>
                            </TabsTrigger>
                            <TabsTrigger value="backups" className="gap-2 py-1.5 text-xs sm:text-sm cursor-pointer">
                                <Database className="size-4 text-emerald-600 dark:text-emerald-400" />
                                <span>Sauvegardes SQL ({backups.length})</span>
                            </TabsTrigger>
                            <TabsTrigger value="history" className="gap-2 py-1.5 text-xs sm:text-sm cursor-pointer">
                                <History className="size-4 text-amber-600 dark:text-amber-400" />
                                <span>Historique des Mises à Jour ({history.length})</span>
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="versions">
                        <DataTable
                            columns={tagsColumns}
                            data={tagsCatalog}
                            searchPlaceholder="Rechercher un tag (ex: v1.1.0) ou une note de version..."
                        />
                    </TabsContent>

                    <TabsContent value="backups">
                        <DataTable
                            columns={backupsColumns}
                            data={backups}
                            searchPlaceholder="Rechercher un fichier de sauvegarde .sql..."
                            actionSlot={
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={isBackingUp || isUpdating}
                                    onClick={handleManualBackup}
                                    className="h-9 gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs cursor-pointer"
                                >
                                    {isBackingUp ? (
                                        <Loader2 className="size-3.5 animate-spin" />
                                    ) : (
                                        <Database className="size-3.5" />
                                    )}
                                    <span>Nouvelle sauvegarde .SQL</span>
                                </Button>
                            }
                        />
                    </TabsContent>

                    <TabsContent value="history">
                        <DataTable
                            columns={historyColumns}
                            data={history}
                            searchPlaceholder="Rechercher dans l'historique des mises à jour..."
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {/* Modale de confirmation avant lancement du pipeline de mise à jour */}
            <Dialog
                open={confirmTargetTag !== null}
                onOpenChange={(open) => {
                    if (!open && !isUpdating) {
                        setConfirmTargetTag(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-lg">
                            <CloudDownload className="size-5 text-emerald-600" />
                            <span>Confirmer la mise à jour vers {confirmTargetTag}</span>
                        </DialogTitle>
                        <DialogDescription className="pt-1">
                            Vous êtes sur le point de mettre à niveau SYGES de{' '}
                            <strong className="font-mono text-foreground">{versionInfo.version}</strong> vers{' '}
                            <strong className="font-mono text-foreground">{confirmTargetTag}</strong>.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 py-2 text-xs sm:text-sm">
                        <div className="flex items-center gap-2">
                            <span className="text-muted-foreground">Type de version :</span>
                            <ReleaseTypeBadge type={confirmReleaseType} />
                        </div>

                        <div className="rounded-xl border border-slate-200 dark:border-slate-800 bg-muted/40 p-3.5 space-y-2">
                            <p className="font-semibold text-foreground text-xs uppercase tracking-wider">
                                Opérations automatiques exécutées par le serveur :
                            </p>
                            <ol className="list-decimal list-inside space-y-1.5 text-xs text-muted-foreground">
                                <li>
                                    Sauvegarde intégrale de la base de données en fichier{' '}
                                    <code className="font-mono text-foreground">.sql</code> + envoi par email à{' '}
                                    <strong className="text-foreground">{developerEmail}</strong>.
                                </li>
                                <li>Activation temporaire du mode maintenance sécurisé.</li>
                                <li>
                                    Téléchargement du tag validé{' '}
                                    <code className="font-mono text-foreground">{confirmTargetTag}</code> depuis
                                    GitHub (code + interface compilée).
                                </li>
                                <li>
                                    Création et migration automatique des nouvelles tables SQL (
                                    <code className="font-mono">migrate --force</code>).
                                </li>
                                <li>
                                    Vidage et reconstruction des caches (
                                    <code className="font-mono">optimize:clear</code> puis{' '}
                                    <code className="font-mono">optimize</code>).
                                </li>
                                <li>Réouverture immédiate de l'application.</li>
                            </ol>
                        </div>
                    </div>

                    <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmTargetTag(null)}
                            className="w-full sm:w-auto cursor-pointer"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="button"
                            onClick={handleRunUpdate}
                            className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold cursor-pointer w-full sm:w-auto"
                        >
                            <CloudDownload className="size-4" />
                            <span>Confirmer et installer {confirmTargetTag}</span>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Configuration du layout principal et du fil d'Ariane de la page Mise à jour Système.
 */
SystemUpdateIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Mise à jour & Versions',
            href: '/administration/system-update',
        },
    ],
};


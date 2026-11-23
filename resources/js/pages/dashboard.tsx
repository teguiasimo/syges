import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpRight,
    Award,
    BookOpen,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Edit3,
    FileSpreadsheet,
    GraduationCap,
    Lock,
    TrendingUp,
    Users,
} from 'lucide-react';
import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Progress } from '@/components/ui/progress';
import {
    CarteRepartitionClasses,
    CarteRepartitionCycles,
} from '@/components/dashboard/rapports-graphiques-ministere';
import { usePermission } from '@/hooks/use-permission';
import { SearchableSelect } from '@/components/ui/searchable-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn, formatAnneeLabel } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    StatistiqueDemographique,
    StatistiqueFinanciere,
    StatistiquePedagogique,
} from '@/types/statistique-deliberation';

interface EtablissementOption {
    id: number;
    nom: string;
    code: string;
}

interface AnneeOption {
    id: number;
    nom: string;
    code?: string;
}

export interface EnseignantAttributionStat {
    id: number;
    classeNom: string;
    matiereNom: string;
    volumeHoraire: number;
    effectif: number;
    notesSaisies: number;
    pourcentageSaisie: number;
}

export interface EnseignantStats {
    nom: string;
    matricule: string;
    departement: string;
    totalClasses: number;
    totalEleves: number;
    totalHeures: number;
    evaluationActiveNom: string;
    attributions: EnseignantAttributionStat[];
}

interface DashboardProps {
    hasAccess?: boolean;
    supportsAllEtablissements?: boolean;
    isAllEtablissements?: boolean;
    isEnseignantDashboard?: boolean;
    enseignantStats?: EnseignantStats | null;
    etablissements: EtablissementOption[];
    currentEtablissement: EtablissementOption | null;
    annees: AnneeOption[];
    currentAnneeId: number;
    demographie?: StatistiqueDemographique | null;
    pedagogie?: StatistiquePedagogique | null;
    finances?: StatistiqueFinanciere | null;
}

/**
 * Configuration d'un raccourci opérationnel affiché sur le tableau de bord exécutif.
 */
interface QuickShortcutItem {
    /** Identifiant unique du module */
    key: string;
    /** Route de destination Inertia */
    href: string;
    /** Libellé principal du raccourci */
    title: string;
    /** Sous-titre descriptif */
    subtitle: string;
    /** Icône Lucide représentative du module */
    icon: React.ComponentType<{ className?: string }>;
    /** Classes de couleur du conteneur d'icône (repos et survol) */
    iconClassName?: string;
    /** Classe de bordure appliquée à la carte lors du survol */
    cardHoverClassName?: string;
    /** Classe de couleur appliquée à la flèche directionnelle lors du survol */
    arrowHoverClassName?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tableau de bord',
        href: '/dashboard',
    },
];

/**
 * Calcule les classes de grille adaptatives selon le nombre d'éléments visibles (1 à 4)
 * afin d'éviter toute colonne vide lorsque des modules sont masqués par RBAC.
 *
 * @param {number} count - Nombre d'éléments visibles.
 * @returns {string} Classes CSS Grid responsives.
 */
function resolveResponsiveGridClass(count: number): string {
    if (count <= 1) return 'grid-cols-1';
    if (count === 2) return 'grid-cols-1 sm:grid-cols-2';
    if (count === 3) return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3';
    return 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4';
}

/**
 * Raccourci rapide avec icône colorée encadrée et inversion chromatique au survol.
 *
 * @param {{ item: QuickShortcutItem }} props - Configuration du module cible.
 * @returns {JSX.Element} Lien interactif du tableau de bord.
 */
function DashboardQuickLink({ item }: { item: QuickShortcutItem }) {
    const Icon = item.icon;
    return (
        <Link
            href={item.href}
            className={cn(
                'group flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3.5 py-3 text-left transition-colors hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                item.cardHoverClassName || 'hover:border-primary/40'
            )}
        >
            <div className="flex items-center gap-3 min-w-0">
                <div
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-md transition-colors',
                        item.iconClassName || 'bg-muted text-primary group-hover:bg-primary group-hover:text-primary-foreground'
                    )}
                >
                    <Icon className="size-4" />
                </div>
                <div className="min-w-0">
                    <div className="font-semibold text-sm text-foreground truncate">{item.title}</div>
                    <div className="text-xs text-muted-foreground truncate">{item.subtitle}</div>
                </div>
            </div>
            <ArrowUpRight
                className={cn(
                    'size-4 shrink-0 text-muted-foreground transition-colors',
                    item.arrowHoverClassName || 'group-hover:text-primary'
                )}
            />
        </Link>
    );
}



/**
 * Page principale du Tableau de bord exécutif de SYGES (Module 15).
 * Intègre le principe Zero-Trust : si l'utilisateur ne possède aucun rôle ni permission,
 * les données statistiques, financières et les alertes de décrochage sont totalement masquées.
 *
 * @param {DashboardProps} props - Propriétés du tableau de bord.
 * @returns {JSX.Element} Vue du tableau de bord ou vue restreinte sécurisée.
 */
export default function Dashboard({
    hasAccess = true,
    supportsAllEtablissements = false,
    isAllEtablissements = false,
    isEnseignantDashboard = false,
    enseignantStats = null,
    etablissements,
    currentEtablissement,
    annees,
    currentAnneeId,
    demographie,
    pedagogie,
    finances,
}: DashboardProps) {
    const { roles, can, canAny } = usePermission();
    const hasRole = roles.length > 0;
    const roleName = roles.join(', ');

    /**
     * Si l'utilisateur est authentifié mais ne possède aucune permission opérationnelle (Zero-Trust),
     * afficher l'état de sécurité restreint sans aucune fuite d'information.
     */
    if (hasAccess === false) {
        return (
            <>
                <Head title="Espace Restreint - SYGES" />
                <div className="flex flex-1 flex-col items-center justify-center p-6 text-center">
                    <div className="max-w-md flex flex-col items-center gap-4 p-8 rounded-2xl border border-dashed border-border bg-card/60 shadow-xs">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                            <Lock className="size-7" />
                        </div>
                        <div className="space-y-2">
                            <div className="flex justify-center">
                                <Badge variant="outline" className="text-xs px-2.5 py-0.5 font-medium">
                                    {hasRole ? `Rôle : ${roleName}` : 'Aucun rôle attribué'}
                                </Badge>
                            </div>
                            <h2 className="text-xl font-bold tracking-tight text-foreground">
                                {hasRole ? 'Rôle sans permission active' : 'Espace Restreint'}
                            </h2>
                            <p className="text-sm text-muted-foreground leading-relaxed">
                                {hasRole
                                    ? `Votre compte possède le rôle « ${roleName} », mais aucune permission ne lui est associée (0 droit actif). Vous ne pouvez accéder à aucun module ni donnée confidentielle de l'établissement.`
                                    : "Votre compte ne dispose d'aucun rôle ni privilège d'accès. Vous ne pouvez accéder à aucun module ni donnée de l'établissement."}
                            </p>
                        </div>
                        <div className="text-xs text-muted-foreground bg-muted/50 rounded-lg p-3 w-full border border-border/50">
                            Veuillez vous rapprocher de la direction ou de l'administrateur de votre établissement pour l'attribution de vos droits d'accès.
                        </div>
                    </div>
                </div>
            </>
        );
    }

    /**
     * Changement d'établissement (campus ou « Tous les établissements ») ou d'année scolaire avec rechargement partiel Inertia.
     *
     * @param {'etablissement_id' | 'annee_id'} key Clé du filtre modifié.
     * @param {string} value Nouvelle valeur sélectionnée (ID campus, 'all' ou ID d'année scolaire).
     */
    const handleFilterChange = (key: 'etablissement_id' | 'annee_id', value: string) => {
        const activeEtabParam = isAllEtablissements ? 'all' : currentEtablissement?.id;

        router.get(
            '/dashboard',
            {
                etablissement_id: key === 'etablissement_id' ? value : activeEtabParam,
                annee_id: key === 'annee_id' ? value : currentAnneeId,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Année scolaire activement sélectionnée (pour affichage concis dans le déclencheur Select)
    const selectedAnnee = React.useMemo(() => {
        return annees.find((an) => an.id === currentAnneeId);
    }, [annees, currentAnneeId]);

    /**
     * Si l'utilisateur est un enseignant sans prérogatives de gestion globale,
     * afficher l'espace pédagogique personnel sans fuite de données financières.
     */
    if (isEnseignantDashboard && enseignantStats) {
        return (
            <EnseignantDashboardView
                stats={enseignantStats}
                etablissements={etablissements}
                currentEtablissement={currentEtablissement}
                annees={annees}
                currentAnneeId={currentAnneeId}
                onFilterChange={handleFilterChange}
            />
        );
    }

    // Habilitations RBAC du profil connecté pour conditionner KPIs, raccourcis et graphiques
    const canViewDemographie = canAny(['statistiques.view', 'eleves.view', 'classes.view']);
    const canViewPedagogie = canAny(['statistiques.view', 'deliberations.view', 'bulletins.view', 'notes.view']);
    const canViewFinances = canAny(['finances.view', 'finances.manage', 'finances.etats']) && Boolean(finances);

    const visibleKpiCount =
        (canViewDemographie ? 2 : 0) + (canViewPedagogie ? 1 : 0) + (canViewFinances ? 1 : 0);

    // Raccourcis exécutifs filtrés selon les permissions effectives pour prévenir toute erreur 403
    const executiveShortcuts: QuickShortcutItem[] = [
        ...(can('deliberations.view')
            ? [
                  {
                      key: 'deliberations',
                      href: '/administration/deliberations',
                      title: 'Conseil & Délibérations',
                      subtitle: 'PV & Décisions de passage',
                      icon: Award,
                      iconClassName:
                          'bg-amber-100/80 text-amber-600 group-hover:bg-amber-600 group-hover:text-white',
                      cardHoverClassName: 'hover:border-amber-500/40',
                      arrowHoverClassName: 'group-hover:text-amber-600',
                  },
              ]
            : []),
        ...(can('statistiques.view')
            ? [
                  {
                      key: 'statistiques',
                      href: '/administration/statistiques',
                      title: 'Statistiques Ministérielles',
                      subtitle: 'Bordereaux MINESEC',
                      icon: BookOpen,
                      iconClassName:
                          'bg-teal-100/80 text-teal-600 group-hover:bg-teal-600 group-hover:text-white',
                      cardHoverClassName: 'hover:border-teal-500/40',
                      arrowHoverClassName: 'group-hover:text-teal-600',
                  },
              ]
            : []),
        ...(can('bulletins.view')
            ? [
                  {
                      key: 'bulletins',
                      href: '/administration/bulletins',
                      title: 'Bulletins Scolaires',
                      subtitle: 'APC & Standards',
                      icon: GraduationCap,
                      iconClassName:
                          'bg-blue-100/80 text-blue-600 group-hover:bg-blue-600 group-hover:text-white',
                      cardHoverClassName: 'hover:border-blue-500/40',
                      arrowHoverClassName: 'group-hover:text-blue-600',
                  },
              ]
            : []),
        ...(canAny(['finances.view', 'finances.manage'])
            ? [
                  {
                      key: 'finances',
                      href: '/administration/finances',
                      title: 'Caisse & Versements',
                      subtitle: 'Reçus & Clôtures',
                      icon: DollarSign,
                      iconClassName:
                          'bg-emerald-100/80 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white',
                      cardHoverClassName: 'hover:border-emerald-500/40',
                      arrowHoverClassName: 'group-hover:text-emerald-600',
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title="Tableau de bord exécutif" />

            {/* Conteneur principal pleine largeur sans plafond artificiel sur les très grands écrans */}
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6 w-full min-w-0">
                {/* En-tête avec Sélecteurs de contexte */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Tableau de Bord Exécutif
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pilotage stratégique, démographique, pédagogique et financier de l'établissement
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto min-w-0">
                        {etablissements.length > 1 && (
                            <div className="flex items-center gap-2 w-full sm:w-64 min-w-0">
                                <Building2 className="size-4 text-muted-foreground shrink-0" />
                                <SearchableSelect
                                    options={[
                                        ...(supportsAllEtablissements
                                            ? [{ value: 'all', label: 'Tous les établissements' }]
                                            : []),
                                        ...etablissements.map((etab) => ({
                                            value: etab.id.toString(),
                                            label: etab.nom,
                                            description: etab.code ? `Code : ${etab.code}` : undefined,
                                        })),
                                    ]}
                                    value={isAllEtablissements ? 'all' : (currentEtablissement?.id.toString() || '')}
                                    onValueChange={(val) => {
                                        if (!val && !supportsAllEtablissements) return;
                                        handleFilterChange('etablissement_id', val || 'all');
                                    }}
                                    placeholder="Établissement"
                                    searchPlaceholder="Rechercher un établissement..."
                                    emptyMessage="Aucun établissement trouvé."
                                    className="h-9 text-xs sm:text-sm font-normal"
                                />
                            </div>
                        )}

                        <div className="flex items-center gap-2 w-full sm:w-auto">
                            <Calendar className="size-4 text-muted-foreground shrink-0" />
                            <Select
                                value={currentAnneeId.toString()}
                                onValueChange={(val) => handleFilterChange('annee_id', val)}
                            >
                                <SelectTrigger className="w-full sm:w-36">
                                    <SelectValue placeholder="Session">
                                        {selectedAnnee ? formatAnneeLabel(selectedAnnee.nom, selectedAnnee.code) : undefined}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    {annees.map((an) => (
                                        <SelectItem key={an.id} value={an.id.toString()}>
                                            {formatAnneeLabel(an.nom, an.code)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                {/* État alternatif si le rôle actif ne couvre aucun bloc statistique/financier */}
                {visibleKpiCount === 0 && executiveShortcuts.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-sm font-medium text-foreground">
                                Aucun indicateur de pilotage n&apos;est associé à vos habilitations actuelles
                                {hasRole ? ` (${roleName})` : ''}.
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Utilisez le menu latéral pour accéder directement à vos modules autorisés.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Cartes de Synthèse Supérieures (KPIs 15.1, 15.2, 15.3) */}
                {visibleKpiCount > 0 && (
                    <div className={cn('grid gap-4', resolveResponsiveGridClass(visibleKpiCount))}>
                        {canViewDemographie && (
                            <>
                                {/* Effectif Global */}
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                                        <CardTitle className="text-sm font-medium text-muted-foreground">
                                            Effectif Consolidé
                                        </CardTitle>
                                        <Users className="size-4 text-primary" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-foreground">
                                            {demographie?.effectif_total ?? 0} élèves
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-1">
                                            {demographie?.repartition_statut?.nouveaux ?? 0} nouveaux • {demographie?.repartition_statut?.redoublants ?? 0} redoublants ({demographie?.repartition_statut?.taux_redoublants ?? 0}%)
                                        </p>
                                    </CardContent>
                                </Card>

                                {/* Parité Filles / Garçons (Jauge bicolore 100% équilibrée Filles & Garçons) */}
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                                        <CardTitle className="text-sm font-medium text-muted-foreground">
                                            Parité de Scolarisation
                                        </CardTitle>
                                        <Users className="size-4 text-sky-500" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex items-baseline justify-between">
                                            <div className="text-2xl font-bold text-foreground flex items-baseline gap-1.5">
                                                <span className="text-rose-600 font-mono">
                                                    {demographie?.parite?.taux_filles ?? 0}%
                                                </span>
                                                <span className="text-xs font-semibold text-rose-600/80 uppercase">
                                                    F
                                                </span>
                                                <span className="text-muted-foreground/30 font-light text-base mx-0.5">/</span>
                                                <span className="text-sky-600 font-mono">
                                                    {demographie?.parite?.taux_garcons ?? 0}%
                                                </span>
                                                <span className="text-xs font-semibold text-sky-600/80 uppercase">
                                                    G
                                                </span>
                                            </div>
                                        </div>

                                        {/* Barre de parité segmentée bicolore (Filles + Garçons = 100% de la largeur) */}
                                        <div className="mt-2.5 h-2.5 w-full overflow-hidden rounded-full bg-muted flex shadow-inner">
                                            <div
                                                className="h-full bg-rose-500 transition-all duration-500"
                                                style={{ width: `${demographie?.parite?.taux_filles ?? 0}%` }}
                                                title={`Filles : ${demographie?.parite?.taux_filles ?? 0}% (${demographie?.parite?.filles ?? 0} élèves)`}
                                            />
                                            <div
                                                className="h-full bg-sky-500 transition-all duration-500"
                                                style={{ width: `${demographie?.parite?.taux_garcons ?? 0}%` }}
                                                title={`Garçons : ${demographie?.parite?.taux_garcons ?? 0}% (${demographie?.parite?.garcons ?? 0} élèves)`}
                                            />
                                        </div>

                                        {/* Légende détaillée bicolore avec effectifs chiffrés */}
                                        <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                            <div className="flex items-center gap-1.5 font-medium">
                                                <span className="size-2 rounded-full bg-rose-500 inline-block shrink-0" />
                                                <span>{demographie?.parite?.filles ?? 0} Filles</span>
                                            </div>
                                            <div className="flex items-center gap-1.5 font-medium">
                                                <span className="size-2 rounded-full bg-sky-500 inline-block shrink-0" />
                                                <span>{demographie?.parite?.garcons ?? 0} Garçons</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </>
                        )}

                        {canViewPedagogie && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        Taux de Réussite
                                    </CardTitle>
                                    <TrendingUp className="size-4 text-emerald-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-foreground">
                                        {pedagogie?.taux_reussite_global ?? 0}%
                                    </div>
                                    <div className="mt-2 flex items-center gap-2">
                                        <Progress value={pedagogie?.taux_reussite_global ?? 0} className="h-2 bg-emerald-100 [&>div]:bg-emerald-500" />
                                    </div>
                                    <p className="text-xs text-muted-foreground mt-1.5">
                                        {pedagogie?.total_admis ?? 0} admis / {pedagogie?.total_evalues ?? 0} évalués (≥10/20)
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {canViewFinances && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        Recouvrement Frais
                                    </CardTitle>
                                    <DollarSign className="size-4 text-amber-500" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-foreground">
                                        {finances?.taux_recouvrement ?? 0}%
                                    </div>
                                    <div className="mt-2 flex items-center gap-2">
                                        <Progress value={finances?.taux_recouvrement ?? 0} className="h-2 bg-amber-100 [&>div]:bg-amber-500" />
                                    </div>
                                    <p className="text-xs text-muted-foreground mt-1.5">
                                        {new Intl.NumberFormat('fr-FR').format(finances?.total_creances ?? 0)} FCFA créances
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Liens Rapides vers les Modules Clés autorisés avec icônes encadrées au survol */}
                {executiveShortcuts.length > 0 && (
                    <div className={cn('grid gap-3', resolveResponsiveGridClass(executiveShortcuts.length))}>
                        {executiveShortcuts.map((item) => (
                            <DashboardQuickLink key={item.key} item={item} />
                        ))}
                    </div>
                )}

                {/* Section Graphiques démographiques (masquée si aucune permission sur les effectifs/classes) */}
                {canViewDemographie && (
                    <>
                        {/* Section Graphique Panoramique : Répartition des élèves par classe (Pleine largeur) */}
                        <CarteRepartitionClasses
                            classes={demographie?.densite?.details ?? []}
                        />

                        {/* Section Mixte : Répartition par Cycle (70%) & Densitométrie des Classes (30%) */}
                        <div className="grid gap-6 grid-cols-1 xl:grid-cols-[7fr_3fr]">
                            {/* 1. Répartition des élèves par cycle (Diagramme Donut calqué sur la maquette ministérielle - 70%) */}
                            <CarteRepartitionCycles
                                repartition={demographie?.repartition_cycles ?? []}
                                totalEleves={demographie?.effectif_total ?? 0}
                            />

                            {/* 2. Densitométrie et Capacité des Classes (30%) */}
                            <Card className="flex flex-col h-full min-w-0">
                                <CardHeader className="pb-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <CardTitle className="text-base">Densitométrie des Classes</CardTitle>
                                            <CardDescription>
                                                Moyenne : {demographie?.densite?.moyenne_par_classe ?? 0} élèves / salle
                                            </CardDescription>
                                        </div>
                                        {(demographie?.densite?.classes_surchargees ?? 0) > 0 && (
                                            <Badge variant="destructive">
                                                {demographie?.densite?.classes_surchargees} surchargée(s)
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="flex-1 space-y-3.5 max-h-100 overflow-y-auto pr-1">
                                    {(demographie?.densite?.details ?? []).map((cls) => (
                                        <div key={cls.id} className="space-y-1 rounded-lg border p-2.5">
                                            <div className="flex items-center justify-between text-xs gap-1.5">
                                                <span className="font-semibold text-foreground truncate" title={cls.nom}>
                                                    {cls.nom}
                                                </span>
                                                <span className={`font-mono text-[11px] shrink-0 ${cls.is_surchargee ? 'text-destructive font-bold' : 'text-muted-foreground'}`}>
                                                    {cls.effectif} / {cls.capacite} ({cls.taux_occupation}%)
                                                </span>
                                            </div>
                                            <Progress
                                                value={Math.min(cls.taux_occupation, 100)}
                                                className={`h-1.5 ${cls.is_surchargee ? '[&>div]:bg-destructive' : ''}`}
                                            />
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

/**
 * Vue spécifique du Tableau de bord dédiée aux Enseignants.
 *
 * Affiche l'activité pédagogique personnelle du professeur :
 * - Ses classes et cours assignés dans le campus sélectionné
 * - L'état d'avancement de saisie des notes pour la séquence active
 * - Des raccourcis directs vers ses modules utiles (Saisie des notes, Relevés de notes, Cahier de texte, Emploi du temps)
 * - Aucune fuite de données financières, administratives ou confidentielles
 *
 * @param props Propriétés de la vue enseignant.
 * @returns {JSX.Element}
 */
function EnseignantDashboardView({
    stats,
    etablissements,
    currentEtablissement,
    annees,
    currentAnneeId,
    onFilterChange,
}: {
    stats: EnseignantStats;
    etablissements: EtablissementOption[];
    currentEtablissement: EtablissementOption | null;
    annees: AnneeOption[];
    currentAnneeId: number;
    onFilterChange: (key: 'etablissement_id' | 'annee_id', value: string) => void;
}) {
    const { can, canAny } = usePermission();
    const canSaisirNotes = canAny(['notes.view', 'notes.create', 'notes.manage']);
    const canVoirReleves = can('releves_notes.view');
    const canVoirProgression = canAny(['progression.view', 'evaluations.view']);
    const canVoirEmploi = canAny(['emplois_du_temps.view', 'emplois_du_temps.manage']);

    // Colonnes TanStack Table pour les attributions pédagogiques de l'enseignant
    const attributionColumns = React.useMemo<ColumnDef<EnseignantAttributionStat>[]>(() => [
        {
            accessorKey: 'classeNom',
            header: 'Classe',
            cell: ({ row }) => (
                <div className="font-semibold text-sm text-foreground flex items-center gap-1.5">
                    <GraduationCap className="size-4 text-primary shrink-0" />
                    {row.original.classeNom}
                </div>
            ),
        },
        {
            accessorKey: 'matiereNom',
            header: 'Matière / Discipline',
            cell: ({ row }) => (
                <div className="text-sm font-medium text-foreground flex items-center gap-1.5">
                    <BookOpen className="size-3.5 text-amber-500 shrink-0" />
                    {row.original.matiereNom}
                </div>
            ),
        },
        {
            accessorKey: 'volumeHoraire',
            header: 'Charge Hebdo',
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground font-mono">
                    {row.original.volumeHoraire} h/semaine
                </span>
            ),
        },
        {
            accessorKey: 'effectif',
            header: 'Effectif',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-xs font-mono">
                    {row.original.effectif} élèves
                </Badge>
            ),
        },
        {
            accessorKey: 'pourcentageSaisie',
            header: () => (
                <div>
                    <div>Notes Séquence</div>
                    <div className="text-[10px] text-muted-foreground font-normal">{stats.evaluationActiveNom}</div>
                </div>
            ),
            cell: ({ row }) => {
                const pct = row.original.pourcentageSaisie;
                return (
                    <div className="flex flex-col gap-1 w-32">
                        <div className="flex justify-between text-xs">
                            <span className="text-muted-foreground font-mono">
                                {row.original.notesSaisies}/{row.original.effectif}
                            </span>
                            <span className={`font-bold font-mono ${pct === 100 ? 'text-emerald-600' : 'text-primary'}`}>
                                {pct}%
                            </span>
                        </div>
                        <Progress value={pct} className="h-1.5" />
                    </div>
                );
            },
        },
        ...(canVoirReleves || canSaisirNotes
            ? [
                  {
                      id: 'actions',
                      header: () => <div className="text-right">Actions</div>,
                      cell: ({ row }: { row: { original: EnseignantAttributionStat } }) => (
                          <div className="flex items-center justify-end gap-1.5">
                              {canVoirReleves && (
                                  <Button variant="outline" size="sm" asChild className="h-7 text-xs gap-1">
                                      <Link href={`/administration/notes/releves?attribution_id=${row.original.id}&annee_id=${currentAnneeId}`}>
                                          <FileSpreadsheet className="size-3.5 text-amber-600" />
                                          Relevé
                                      </Link>
                                  </Button>
                              )}
                              {canSaisirNotes && (
                                  <Button size="sm" asChild className="h-7 text-xs gap-1 bg-rose-600 hover:bg-rose-700 text-white">
                                      <Link href={`/administration/notes?attribution_id=${row.original.id}&annee_id=${currentAnneeId}`}>
                                          <ArrowUpRight className="size-3.5" />
                                          Saisir
                                      </Link>
                                  </Button>
                              )}
                          </div>
                      ),
                  },
              ]
            : []),
    ], [currentAnneeId, stats.evaluationActiveNom, canVoirReleves, canSaisirNotes]);

    return (
        <>
            <Head title="Espace Pédagogique Enseignant" />

            {/* Conteneur principal plein écran sans contrainte artificielle de largeur pour les écrans 22" */}
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6 w-full min-w-0">
                {/* En-tête Enseignant */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Espace Pédagogique Enseignant
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Bienvenue, <span className="font-semibold text-foreground">{stats.nom}</span> • Département : {stats.departement} • Matricule : <span className="font-mono">{stats.matricule}</span>
                        </p>
                    </div>

                    {/* Sélecteurs de Contexte */}
                    <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto min-w-0">
                        {etablissements.length > 1 && (
                            <div className="flex items-center gap-2 w-full sm:w-64 min-w-0">
                                <Building2 className="size-4 text-muted-foreground shrink-0" />
                                <SearchableSelect
                                    options={etablissements.map((etab) => ({
                                        value: etab.id.toString(),
                                        label: etab.nom,
                                        description: etab.code ? `Code : ${etab.code}` : undefined,
                                    }))}
                                    value={currentEtablissement ? currentEtablissement.id.toString() : ''}
                                    onValueChange={(val) => {
                                        if (val) onFilterChange('etablissement_id', val);
                                    }}
                                    placeholder="Établissement"
                                    searchPlaceholder="Rechercher un établissement..."
                                    emptyMessage="Aucun établissement trouvé."
                                    className="h-9 text-xs sm:text-sm font-normal"
                                />
                            </div>
                        )}

                        <div className="flex items-center gap-2 w-full sm:w-auto">
                            <Calendar className="size-4 text-muted-foreground shrink-0" />
                            <Select
                                value={currentAnneeId.toString()}
                                onValueChange={(val) => onFilterChange('annee_id', val)}
                            >
                                <SelectTrigger className="w-full sm:w-36">
                                    <SelectValue placeholder="Session">
                                        {(() => {
                                            const an = annees.find((a) => a.id === currentAnneeId);
                                            return an ? formatAnneeLabel(an.nom, an.code) : undefined;
                                        })()}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    {annees.map((an) => (
                                        <SelectItem key={an.id} value={an.id.toString()}>
                                            {formatAnneeLabel(an.nom, an.code)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                {/* 4 Cartes d'Indicateurs Pédagogiques */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-border/60 shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Classes Assignées</CardTitle>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <GraduationCap className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
                                {stats.totalClasses}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">Classes actives sous votre charge</p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Élèves Pris en Charge</CardTitle>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <Users className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
                                {stats.totalEleves}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">Effectif total dans vos cours</p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Charge Horaire Hebdo</CardTitle>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                                <Clock className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight text-foreground font-mono">
                                {stats.totalHeures} h
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">Volume horaire statutaire / semaine</p>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-xs">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Évaluation en Cours</CardTitle>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                <CheckCircle2 className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-base font-bold tracking-tight text-foreground truncate">
                                {stats.evaluationActiveNom}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">Séquence académique active</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Raccourcis Opérationnels Pédagogiques filtrés par permission */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {canSaisirNotes && (
                        <Link
                            href="/administration/notes"
                            className="group flex items-center gap-3 p-3.5 rounded-xl border border-border/70 bg-card hover:bg-muted/40 transition-colors shadow-2xs"
                        >
                            <div className="flex size-10 items-center justify-center rounded-lg bg-rose-50 text-rose-600 group-hover:scale-105 transition-transform">
                                <Edit3 className="size-5" />
                            </div>
                            <div>
                                <div className="text-sm font-semibold text-foreground group-hover:text-rose-600 transition-colors">
                                    Saisie des Notes
                                </div>
                                <div className="text-xs text-muted-foreground line-clamp-1">
                                    Enregistrer les notes séquentielles
                                </div>
                            </div>
                        </Link>
                    )}

                    {canVoirReleves && (
                        <Link
                            href="/administration/notes/releves"
                            className="group flex items-center gap-3 p-3.5 rounded-xl border border-border/70 bg-card hover:bg-muted/40 transition-colors shadow-2xs"
                        >
                            <div className="flex size-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 group-hover:scale-105 transition-transform">
                                <FileSpreadsheet className="size-5" />
                            </div>
                            <div>
                                <div className="text-sm font-semibold text-foreground group-hover:text-amber-600 transition-colors">
                                    Relevés de Notes
                                </div>
                                <div className="text-xs text-muted-foreground line-clamp-1">
                                    Fiches de notation vierges & remplies
                                </div>
                            </div>
                        </Link>
                    )}

                    {canVoirProgression && (
                        <Link
                            href="/administration/progression-pedagogique"
                            className="group flex items-center gap-3 p-3.5 rounded-xl border border-border/70 bg-card hover:bg-muted/40 transition-colors shadow-2xs"
                        >
                            <div className="flex size-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 group-hover:scale-105 transition-transform">
                                <BookOpen className="size-5" />
                            </div>
                            <div>
                                <div className="text-sm font-semibold text-foreground group-hover:text-indigo-600 transition-colors">
                                    Cahier de Texte
                                </div>
                                <div className="text-xs text-muted-foreground line-clamp-1">
                                    Progression & consignation des cours
                                </div>
                            </div>
                        </Link>
                    )}

                    {canVoirEmploi && (
                        <Link
                            href="/administration/emplois-du-temps"
                            className="group flex items-center gap-3 p-3.5 rounded-xl border border-border/70 bg-card hover:bg-muted/40 transition-colors shadow-2xs"
                        >
                            <div className="flex size-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600 group-hover:scale-105 transition-transform">
                                <Calendar className="size-5" />
                            </div>
                            <div>
                                <div className="text-sm font-semibold text-foreground group-hover:text-violet-600 transition-colors">
                                    Mon Emploi du Temps
                                </div>
                                <div className="text-xs text-muted-foreground line-clamp-1">
                                    Horaires de cours hebdomadaires
                                </div>
                            </div>
                        </Link>
                    )}
                </div>

                {/* Tableau TanStack des Cours & Attributions de l'enseignant */}
                <Card className="border-border/60 shadow-xs">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold">
                            Mes Classes & Cours Attribués ({stats.attributions.length})
                        </CardTitle>
                        <CardDescription>
                            État d'avancement de la saisie des notes pour chaque classe et discipline
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable columns={attributionColumns} data={stats.attributions} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/**
 * Configuration du layout persistant et des fils d'Ariane pour le Tableau de bord.
 * Transmis au layout par défaut (AppLayout) géré par Inertia.
 */
Dashboard.layout = {
    breadcrumbs,
};

import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    AlertTriangle,
    ArrowUpRight,
    Award,
    BookOpen,
    Building2,
    Calendar,
    CheckCircle2,
    DollarSign,
    GraduationCap,
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    EleveDecrochage,
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
}

interface DashboardProps {
    etablissements: EtablissementOption[];
    currentEtablissement: EtablissementOption | null;
    annees: AnneeOption[];
    currentAnneeId: number;
    demographie: StatistiqueDemographique;
    pedagogie: StatistiquePedagogique;
    finances: StatistiqueFinanciere;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tableau de bord',
        href: '/dashboard',
    },
];

/**
 * Colonnes pour la table TanStack des élèves en risque de décrochage scolaire.
 */
const decrochageColumns: ColumnDef<EleveDecrochage>[] = [
    {
        accessorKey: 'matricule',
        header: 'Matricule',
        cell: ({ row }) => <span className="font-mono text-xs font-semibold">{row.getValue('matricule')}</span>,
    },
    {
        accessorKey: 'nom_complet',
        header: 'Élève',
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-medium text-sm text-foreground">{row.getValue('nom_complet')}</span>
                <span className="text-xs text-muted-foreground">{row.original.classe_nom}</span>
            </div>
        ),
    },
    {
        accessorKey: 'moyenne_generale',
        header: 'Moyenne',
        cell: ({ row }) => {
            const mg = row.original.moyenne_generale;
            return (
                <Badge variant={mg < 8.5 ? 'destructive' : 'secondary'} className="font-mono">
                    {mg.toFixed(2)}/20
                </Badge>
            );
        },
    },
    {
        accessorKey: 'absences_non_justifiees',
        header: 'Abs. Non Justifiées',
        cell: ({ row }) => {
            const abs = row.original.absences_non_justifiees;
            return (
                <span className={`text-xs font-medium ${abs >= 15 ? 'text-destructive font-bold' : 'text-muted-foreground'}`}>
                    {abs}h
                </span>
            );
        },
    },
    {
        accessorKey: 'motif',
        header: 'Diagnostic & Motif',
        cell: ({ row }) => (
            <div className="flex items-center gap-1.5">
                <AlertTriangle className={`size-3.5 shrink-0 ${row.original.gravite === 'critique' ? 'text-destructive' : 'text-amber-500'}`} />
                <span className="text-xs">{row.original.motif}</span>
            </div>
        ),
    },
];

/**
 * Page principale du Tableau de bord exécutif de SYGES (Module 15).
 *
 * @param {DashboardProps} props
 * @returns {JSX.Element}
 */
export default function Dashboard({
    etablissements,
    currentEtablissement,
    annees,
    currentAnneeId,
    demographie,
    pedagogie,
    finances,
}: DashboardProps) {
    /**
     * Changement d'établissement ou d'année scolaire avec rechargement partiel Inertia.
     */
    const handleFilterChange = (key: 'etablissement_id' | 'annee_id', value: string) => {
        router.get(
            '/dashboard',
            {
                etablissement_id: key === 'etablissement_id' ? value : currentEtablissement?.id,
                annee_id: key === 'annee_id' ? value : currentAnneeId,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tableau de bord exécutif" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
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

                    <div className="flex flex-wrap items-center gap-3">
                        {etablissements.length > 1 && (
                            <div className="flex items-center gap-2">
                                <Building2 className="size-4 text-muted-foreground" />
                                <Select
                                    value={currentEtablissement?.id.toString()}
                                    onValueChange={(val) => handleFilterChange('etablissement_id', val)}
                                >
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue placeholder="Établissement" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {etablissements.map((etab) => (
                                            <SelectItem key={etab.id} value={etab.id.toString()}>
                                                {etab.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div className="flex items-center gap-2">
                            <Calendar className="size-4 text-muted-foreground" />
                            <Select
                                value={currentAnneeId.toString()}
                                onValueChange={(val) => handleFilterChange('annee_id', val)}
                            >
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Année Scolaire" />
                                </SelectTrigger>
                                <SelectContent>
                                    {annees.map((an) => (
                                        <SelectItem key={an.id} value={an.id.toString()}>
                                            {an.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                {/* Cartes de Synthèse Supérieures (KPIs 15.1, 15.2, 15.3) */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                                {demographie.effectif_total} élèves
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {demographie.repartition_statut.nouveaux} nouveaux • {demographie.repartition_statut.redoublants} redoublants ({demographie.repartition_statut.taux_redoublants}%)
                            </p>
                        </CardContent>
                    </Card>

                    {/* Parité Filles / Garçons */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Parité de Scolarisation
                            </CardTitle>
                            <GraduationCap className="size-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {demographie.parite.taux_filles}% Filles
                            </div>
                            <div className="mt-2 flex items-center gap-2">
                                <Progress value={demographie.parite.taux_filles} className="h-2" />
                            </div>
                            <p className="text-xs text-muted-foreground mt-1.5">
                                {demographie.parite.filles} Filles / {demographie.parite.garcons} Garçons
                            </p>
                        </CardContent>
                    </Card>

                    {/* Taux de Réussite Académique */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Taux de Réussite
                            </CardTitle>
                            <TrendingUp className="size-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {pedagogie.taux_reussite_global}%
                            </div>
                            <div className="mt-2 flex items-center gap-2">
                                <Progress value={pedagogie.taux_reussite_global} className="h-2 bg-emerald-100 dark:bg-emerald-950 [&>div]:bg-emerald-500" />
                            </div>
                            <p className="text-xs text-muted-foreground mt-1.5">
                                {pedagogie.total_admis} admis / {pedagogie.total_evalues} évalués (≥10/20)
                            </p>
                        </CardContent>
                    </Card>

                    {/* Recouvrement Financier */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Recouvrement Frais
                            </CardTitle>
                            <DollarSign className="size-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {finances.taux_recouvrement}%
                            </div>
                            <div className="mt-2 flex items-center gap-2">
                                <Progress value={finances.taux_recouvrement} className="h-2 bg-amber-100 dark:bg-amber-950 [&>div]:bg-amber-500" />
                            </div>
                            <p className="text-xs text-muted-foreground mt-1.5">
                                {new Intl.NumberFormat('fr-FR').format(finances.total_creances)} FCFA créances
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Liens Rapides vers les Modules Clés */}
                <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                    <Button variant="outline" className="h-auto justify-between p-3.5 text-left" asChild>
                        <Link href="/administration/deliberations">
                            <div className="flex items-center gap-2.5">
                                <Award className="size-5 text-amber-600" />
                                <div>
                                    <div className="font-semibold text-sm">Conseil & Délibérations</div>
                                    <div className="text-xs text-muted-foreground">PV & Décisions de passage</div>
                                </div>
                            </div>
                            <ArrowUpRight className="size-4 text-muted-foreground" />
                        </Link>
                    </Button>

                    <Button variant="outline" className="h-auto justify-between p-3.5 text-left" asChild>
                        <Link href="/administration/statistiques">
                            <div className="flex items-center gap-2.5">
                                <BookOpen className="size-5 text-blue-600" />
                                <div>
                                    <div className="font-semibold text-sm">Statistiques Ministérielles</div>
                                    <div className="text-xs text-muted-foreground">Bordereaux MINESEC</div>
                                </div>
                            </div>
                            <ArrowUpRight className="size-4 text-muted-foreground" />
                        </Link>
                    </Button>

                    <Button variant="outline" className="h-auto justify-between p-3.5 text-left" asChild>
                        <Link href="/administration/bulletins">
                            <div className="flex items-center gap-2.5">
                                <GraduationCap className="size-5 text-emerald-600" />
                                <div>
                                    <div className="font-semibold text-sm">Bulletins Scolaires</div>
                                    <div className="text-xs text-muted-foreground">APC & Standards</div>
                                </div>
                            </div>
                            <ArrowUpRight className="size-4 text-muted-foreground" />
                        </Link>
                    </Button>

                    <Button variant="outline" className="h-auto justify-between p-3.5 text-left" asChild>
                        <Link href="/administration/finances">
                            <div className="flex items-center gap-2.5">
                                <DollarSign className="size-5 text-purple-600" />
                                <div>
                                    <div className="font-semibold text-sm">Caisse & Versements</div>
                                    <div className="text-xs text-muted-foreground">Reçus & Clôtures</div>
                                </div>
                            </div>
                            <ArrowUpRight className="size-4 text-muted-foreground" />
                        </Link>
                    </Button>
                </div>

                {/* Section Principale : Décrochage Scolaire & Densitométrie des Classes */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Alerte Décrochage Scolaire (TanStack Table) */}
                    <div className="lg:col-span-2 flex flex-col gap-4">
                        <Card className="flex-1">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <AlertTriangle className="size-4 text-destructive" />
                                            Surveillance & Risque de Décrochage Scolaire
                                        </CardTitle>
                                        <CardDescription>
                                            Élèves identifiés avec moyenne générale &lt; 8.5/20 ou absences non justifiées &ge; 15h
                                        </CardDescription>
                                    </div>
                                    <Badge variant={pedagogie.total_decrochage > 0 ? 'destructive' : 'secondary'}>
                                        {pedagogie.total_decrochage} élève(s) en alerte
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <DataTable
                                    columns={decrochageColumns}
                                    data={pedagogie.eleves_decrochage}
                                    searchPlaceholder="Rechercher un élève en difficulté..."
                                    defaultPageSize={5}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Densitométrie et Capacité des Classes */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-base">Densitométrie des Classes</CardTitle>
                                    <CardDescription>
                                        Moyenne : {demographie.densite.moyenne_par_classe} élèves / salle
                                    </CardDescription>
                                </div>
                                {demographie.densite.classes_surchargees > 0 && (
                                    <Badge variant="destructive">
                                        {demographie.densite.classes_surchargees} surchargée(s)
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3.5 max-h-[420px] overflow-y-auto pr-1">
                            {demographie.densite.details.map((cls) => (
                                <div key={cls.id} className="space-y-1 rounded-lg border p-2.5">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="font-semibold text-foreground">{cls.nom}</span>
                                        <span className={`font-mono ${cls.is_surchargee ? 'text-destructive font-bold' : 'text-muted-foreground'}`}>
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
            </div>
        </AppLayout>
    );
}

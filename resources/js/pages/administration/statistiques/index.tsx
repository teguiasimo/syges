import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Award,
    BookOpen,
    Building2,
    Calendar,
    DollarSign,
    Download,
    FileSpreadsheet,
    GraduationCap,
    Printer,
    Search,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { BreadcrumbItem } from '@/types';
import type {
    LigneBordereauOfficiel,
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

interface ClasseOption {
    id: number;
    nom: string;
}

interface StatistiquesPageProps {
    etablissements: EtablissementOption[];
    currentEtablissement: EtablissementOption | null;
    annees: AnneeOption[];
    currentAnneeId: number;
    classes: ClasseOption[];
    selectedClasseId: number | null;
    demographie: StatistiqueDemographique;
    pedagogie: StatistiquePedagogique;
    finances: StatistiqueFinanciere;
    listesOfficielles: LigneBordereauOfficiel[];
    filters: {
        tab?: string;
        classe_id?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Administration',
        href: '/administration',
    },
    {
        title: 'Statistiques & Rapports Officiels',
        href: '/administration/statistiques',
    },
];

/**
 * Colonnes TanStack Table pour le Bordereau Officiel des Examens Ministériels (Gesco 15.1).
 */
const bordereauColumns: ColumnDef<LigneBordereauOfficiel>[] = [
    {
        accessorKey: 'rang_alphabetique',
        header: 'N°',
        cell: ({ row }) => <span className="font-mono text-xs font-bold text-center block w-6">{row.original.rang_alphabetique}</span>,
    },
    {
        accessorKey: 'matricule',
        header: 'Matricule Interne',
        cell: ({ row }) => <span className="font-mono text-xs font-semibold">{row.original.matricule}</span>,
    },
    {
        accessorKey: 'matricule_ministere',
        header: 'Matricule Ministère',
        cell: ({ row }) => (
            <span className="font-mono text-xs font-bold text-blue-700 dark:text-blue-400">
                {row.original.matricule_ministere}
            </span>
        ),
    },
    {
        accessorKey: 'nom_complet',
        header: 'Nom(s) et Prénom(s)',
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-semibold text-sm text-foreground">{row.original.nom_complet}</span>
                <span className="text-xs text-muted-foreground">{row.original.classe_nom}</span>
            </div>
        ),
    },
    {
        accessorKey: 'sexe',
        header: 'Sexe',
        cell: ({ row }) => (
            <Badge variant="outline" className="text-xs">
                {row.original.sexe === 'F' ? 'F' : 'M'}
            </Badge>
        ),
    },
    {
        accessorKey: 'date_naissance',
        header: 'Date & Lieu Naissance',
        cell: ({ row }) => (
            <div className="flex flex-col text-xs">
                <span>{row.original.date_naissance}</span>
                <span className="text-muted-foreground">{row.original.lieu_naissance}</span>
            </div>
        ),
    },
    {
        accessorKey: 'statut_candidat',
        header: 'Statut Candidat',
        cell: ({ row }) => (
            <Badge variant={row.original.is_redoublant ? 'secondary' : 'outline'} className="text-xs">
                {row.original.statut_candidat}
            </Badge>
        ),
    },
];

/**
 * Page des Statistiques Scolaires & Bordereaux Réglementaires MINESEC / MINEDUB (Module 15).
 *
 * @param {StatistiquesPageProps} props
 * @returns {JSX.Element}
 */
export default function StatistiquesIndex({
    etablissements,
    currentEtablissement,
    annees,
    currentAnneeId,
    classes,
    selectedClasseId,
    demographie,
    pedagogie,
    finances,
    listesOfficielles,
    filters,
}: StatistiquesPageProps) {
    const activeTab = filters.tab || 'demographie';

    const handleNavigation = (params: Record<string, string | number | null | undefined>) => {
        router.get(
            '/administration/statistiques',
            {
                etablissement_id: currentEtablissement?.id,
                annee_id: currentAnneeId,
                classe_id: selectedClasseId,
                tab: activeTab,
                ...params,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    return (
        <>
            <Head title="Statistiques & Rapports Officiels" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* En-tête de page */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <BookOpen className="size-6 text-blue-600" />
                            Statistiques & Rapports Ministériels
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Rapports de rentrée, bordereaux officiels des examens, parité et tableaux de bord réglementaires
                        </p>
                    </div>

                    {/* Sélecteurs établissement & année */}
                    <div className="flex flex-wrap items-center gap-3">
                        {etablissements.length > 1 && (
                            <Select
                                value={currentEtablissement?.id.toString()}
                                onValueChange={(val) => handleNavigation({ etablissement_id: val })}
                            >
                                <SelectTrigger className="w-47.5 h-9">
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
                        )}

                        <Select
                            value={currentAnneeId.toString()}
                            onValueChange={(val) => handleNavigation({ annee_id: val })}
                        >
                            <SelectTrigger className="w-35 h-9">
                                <SelectValue placeholder="Année" />
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

                {/* Onglets thématiques */}
                <Tabs
                    value={activeTab}
                    onValueChange={(val: string) => handleNavigation({ tab: val })}
                    className="w-full"
                >
                    <TabsList className="grid grid-cols-1 sm:grid-cols-3 w-full max-w-lg mb-4 h-auto p-1 gap-1">
                        <TabsTrigger value="demographie" className="text-xs">
                            Démographie & Effectifs
                        </TabsTrigger>
                        <TabsTrigger value="examens" className="text-xs">
                            Bordereaux Examens
                        </TabsTrigger>
                        <TabsTrigger value="finances" className="text-xs">
                            Synthèse Financière
                        </TabsTrigger>
                    </TabsList>

                    {/* Onglet 1 : Démographie & Effectifs (Gesco 15.1) */}
                    <TabsContent value="demographie" className="space-y-6">
                        {/* 4 Cartes Supérieures */}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card className="p-4">
                                <div className="text-xs font-medium text-muted-foreground">Effectif Total Enregistré</div>
                                <div className="text-2xl font-bold text-foreground mt-1">{demographie.effectif_total} élèves</div>
                                {/* Affichage épuré de l'année scolaire sans préfixe redondant */}
                                <div className="text-xs text-muted-foreground mt-1">
                                    {demographie.annee_scolaire.toLowerCase().startsWith('année')
                                        ? demographie.annee_scolaire
                                        : `Année ${demographie.annee_scolaire}`}
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="text-xs font-medium text-muted-foreground">Parité de Genre</div>
                                <div className="text-2xl font-bold text-blue-600 mt-1">{demographie.parite.ratio_texte}</div>
                                <div className="mt-2">
                                    <Progress value={demographie.parite.taux_filles} className="h-1.5" />
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="text-xs font-medium text-muted-foreground">Nouveaux vs Redoublants</div>
                                <div className="text-2xl font-bold text-foreground mt-1">
                                    {demographie.repartition_statut.taux_redoublants}% redoublants
                                </div>
                                <div className="text-xs text-muted-foreground mt-1">
                                    {demographie.repartition_statut.nouveaux} nouveaux • {demographie.repartition_statut.redoublants} redoublants
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="text-xs font-medium text-muted-foreground">Densité Moyenne par Classe</div>
                                <div className="text-2xl font-bold text-foreground mt-1">
                                    {demographie.densite.moyenne_par_classe} élèves
                                </div>
                                <div className="text-xs text-muted-foreground mt-1">
                                    {demographie.densite.classes_surchargees} classe(s) surchargée(s) sur {demographie.densite.nombre_classes}
                                </div>
                            </Card>
                        </div>

                        {/* Tableau Détaillé de la Densitométrie des Classes */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Répartition et Taux de Remplissage par Classe</CardTitle>
                                <CardDescription>
                                    Contrôle des capacités d'accueil conformément aux directives d'effectifs MINESEC
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                                    {demographie.densite.details.map((cls) => (
                                        <div key={cls.id} className="rounded-lg border p-3.5 space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold text-sm">{cls.nom}</span>
                                                <Badge variant={cls.is_surchargee ? 'destructive' : 'secondary'} className="text-[10px]">
                                                    {cls.is_surchargee ? 'Surchargée' : 'Conforme'}
                                                </Badge>
                                            </div>
                                            <div className="flex items-center justify-between text-xs text-muted-foreground font-mono">
                                                <span>Effectif : {cls.effectif} élèves</span>
                                                <span>Capacité : {cls.capacite}</span>
                                            </div>
                                            <Progress
                                                value={Math.min(cls.taux_occupation, 100)}
                                                className={`h-2 ${cls.is_surchargee ? '[&>div]:bg-destructive' : ''}`}
                                            />
                                            <div className="text-right text-[11px] text-muted-foreground font-mono">
                                                Taux : {cls.taux_occupation}%
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Onglet 2 : Bordereaux Officiels des Examens (Gesco 15.1) */}
                    <TabsContent value="examens" className="space-y-4">
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <FileSpreadsheet className="size-4 text-primary" />
                                            Bordereau Officiel des Inscriptions aux Examens
                                        </CardTitle>
                                        <CardDescription>
                                            Liste alphabétique réglementaire avec Matricule National Ministère (MINESEC / MINEDUB)
                                        </CardDescription>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <Select
                                            value={selectedClasseId ? selectedClasseId.toString() : 'all'}
                                            onValueChange={(val) => handleNavigation({ classe_id: val === 'all' ? null : val })}
                                        >
                                            <SelectTrigger className="w-45 h-9">
                                                <SelectValue placeholder="Filtrer par classe" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Toutes les classes</SelectItem>
                                                {classes.map((cls) => (
                                                    <SelectItem key={cls.id} value={cls.id.toString()}>
                                                        {cls.nom}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        {currentEtablissement && (
                                            <Button size="sm" className="gap-1.5" asChild>
                                                <a
                                                    href={`/administration/statistiques/bordereau?etablissement_id=${currentEtablissement.id}&annee_id=${currentAnneeId}${selectedClasseId ? `&classe_id=${selectedClasseId}` : ''}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Printer className="size-4" />
                                                    Exporter en PDF Officiel
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <DataTable
                                    columns={bordereauColumns}
                                    data={listesOfficielles}
                                    searchPlaceholder="Rechercher par nom d'élève ou matricule national..."
                                    defaultPageSize={15}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Onglet 3 : Synthèse Financière Consolidée (Gesco 15.3) */}
                    <TabsContent value="finances" className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card className="p-4">
                                <div className="text-xs font-medium text-muted-foreground">Total Facturé Net</div>
                                <div className="text-2xl font-bold text-foreground mt-1">
                                    {new Intl.NumberFormat('fr-FR').format(finances.total_facture)} FCFA
                                </div>
                                <div className="text-xs text-muted-foreground mt-1">{finances.total_comptes} comptes scolarité</div>
                            </Card>

                            <Card className="p-4">
                                <div className="text-xs font-medium text-muted-foreground">Total Encaissé / Recouvré</div>
                                <div className="text-2xl font-bold text-emerald-600 mt-1">
                                    {new Intl.NumberFormat('fr-FR').format(finances.total_recouvre)} FCFA
                                </div>
                                <div className="text-xs text-muted-foreground mt-1">{finances.nombre_soldes} comptes soldés</div>
                            </Card>

                            <Card className="p-4">
                                <div className="text-xs font-medium text-muted-foreground">Reste à Recouvrer (Créances)</div>
                                <div className="text-2xl font-bold text-destructive mt-1">
                                    {new Intl.NumberFormat('fr-FR').format(finances.total_creances)} FCFA
                                </div>
                                <div className="text-xs text-muted-foreground mt-1">{finances.nombre_debiteurs} comptes débiteurs</div>
                            </Card>

                            <Card className="p-4">
                                <div className="text-xs font-medium text-muted-foreground">Taux de Recouvrement Global</div>
                                <div className="text-2xl font-bold text-primary mt-1">{finances.taux_recouvrement}%</div>
                                <div className="mt-2">
                                    <Progress value={finances.taux_recouvrement} className="h-1.5" />
                                </div>
                            </Card>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

/**
 * Configuration du layout persistant et des fils d'Ariane pour la page Statistiques.
 */
StatistiquesIndex.layout = {
    breadcrumbs,
};


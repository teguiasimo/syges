import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    AlertCircle,
    AlertTriangle,
    Award,
    BookOpen,
    Calendar,
    CalendarDays,
    CheckCircle2,
    Clock,
    DollarSign,
    Download,
    FileText,
    GraduationCap,
    Lock,
    Printer,
    Receipt,
    ShieldAlert,
    Sparkles,
    User,
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    EnfantResume,
    LigneMatierePortail,
    ProfilCompletEnfant,
    SanctionPortail,
    SeanceEmploiTempsPortail,
    VersementPortail,
} from '@/types/portail-parent';

interface PortailPageProps {
    enfants: EnfantResume[];
    enfantActif: EnfantResume | null;
    profil: ProfilCompletEnfant | null;
    filters: {
        periode_id?: number;
        tab?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Portail Parents & Élèves',
        href: '/portail',
    },
];

/**
 * Colonnes TanStack Table pour le relevé des notes par matière (Gesco 16.2).
 */
const notesColumns: ColumnDef<LigneMatierePortail>[] = [
    {
        accessorKey: 'matiere_nom',
        header: 'Discipline / Matière',
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-semibold text-sm text-foreground">{row.original.matiere_nom}</span>
                <span className="text-xs text-muted-foreground">{row.original.enseignant_nom}</span>
            </div>
        ),
    },
    {
        accessorKey: 'coefficient',
        header: 'Coeff.',
        cell: ({ row }) => <span className="font-mono text-xs font-bold text-center block w-8">{row.original.coefficient}</span>,
    },
    {
        accessorKey: 'moyenne',
        header: 'Note / 20',
        cell: ({ row }) => {
            const val = row.original.moyenne ?? row.original.note;
            if (val === null || val === undefined) {
                return <span className="text-xs text-muted-foreground italic">Non évalué</span>;
            }
            const color =
                val >= 12
                    ? 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300'
                    : val >= 10
                    ? 'text-blue-700 bg-blue-50 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300'
                    : val >= 8.5
                    ? 'text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300'
                    : 'text-rose-700 bg-rose-50 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300';
            return (
                <div className="flex items-center gap-2">
                    <span className={`px-2 py-0.5 rounded font-mono font-bold text-xs border ${color}`}>
                        {Number(val).toFixed(2)} / 20
                    </span>
                    {row.original.est_eliminatoire && (
                        <Badge variant="destructive" className="text-[10px] px-1 py-0 h-4">
                            Éliminatoire
                        </Badge>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'cote_apc',
        header: 'Cote APC',
        cell: ({ row }) => (
            row.original.cote_apc ? (
                <Badge variant="outline" className="text-xs font-mono">
                    {row.original.cote_apc}
                </Badge>
            ) : <span className="text-xs text-muted-foreground">-</span>
        ),
    },
    {
        accessorKey: 'appreciation',
        header: 'Appréciation Enseignant',
        cell: ({ row }) => (
            <span className="text-xs text-muted-foreground italic">
                {row.original.appreciation || 'Travail régulier'}
            </span>
        ),
    },
];

/**
 * Colonnes TanStack Table pour l'emploi du temps hebdomadaire (Gesco 16.2).
 */
const emploiTempsColumns: ColumnDef<SeanceEmploiTempsPortail>[] = [
    {
        accessorKey: 'jour_libelle',
        header: 'Jour',
        cell: ({ row }) => <span className="font-semibold text-xs text-foreground">{row.original.jour_libelle}</span>,
    },
    {
        accessorKey: 'plage_horaire',
        header: 'Horaire',
        cell: ({ row }) => <span className="font-mono text-xs text-muted-foreground">{row.original.plage_horaire}</span>,
    },
    {
        accessorKey: 'matiere_nom',
        header: 'Cours / Matière',
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-medium text-sm text-foreground">{row.original.matiere_nom}</span>
                <span className="text-xs text-muted-foreground">{row.original.enseignant_nom}</span>
            </div>
        ),
    },
    {
        accessorKey: 'salle_nom',
        header: 'Salle de cours',
        cell: ({ row }) => <span className="text-xs font-mono text-muted-foreground">{row.original.salle_nom}</span>,
    },
    {
        accessorKey: 'statut',
        header: 'Statut',
        cell: ({ row }) => (
            row.original.is_annule ? (
                <Badge variant="destructive" className="text-xs">
                    Cours Annulé
                </Badge>
            ) : (
                <Badge variant="outline" className="text-emerald-700 bg-emerald-50 border-emerald-300 text-xs">
                    Confirmé
                </Badge>
            )
        ),
    },
];

/**
 * Colonnes TanStack Table pour les versements financiers et reçus (Gesco 16.2).
 */
const versementsColumns: ColumnDef<VersementPortail>[] = [
    {
        accessorKey: 'numero_recu',
        header: 'N° Reçu',
        cell: ({ row }) => (
            <span className="font-mono text-xs font-bold text-primary">
                {row.original.numero_recu}
            </span>
        ),
    },
    {
        accessorKey: 'date_versement',
        header: 'Date',
        cell: ({ row }) => <span className="text-xs text-muted-foreground">{row.original.date_versement}</span>,
    },
    {
        accessorKey: 'montant',
        header: 'Montant Versé',
        cell: ({ row }) => (
            <span className="font-mono text-xs font-bold text-emerald-600">
                {new Intl.NumberFormat('fr-FR').format(row.original.montant)} FCFA
            </span>
        ),
    },
    {
        accessorKey: 'mode_paiement',
        header: 'Mode',
        cell: ({ row }) => <Badge variant="outline" className="text-xs">{row.original.mode_paiement}</Badge>,
    },
    {
        id: 'actions',
        header: 'Reçu PDF',
        cell: ({ row }) => (
            <Button size="sm" variant="ghost" className="h-8 gap-1 text-xs text-primary" asChild>
                <a href={`/portail/recu/${row.original.id}`} target="_blank" rel="noreferrer">
                    <Receipt className="size-3.5" />
                    Télécharger
                </a>
            </Button>
        ),
    },
];

/**
 * Colonnes TanStack Table pour le registre des sanctions disciplinaires.
 */
const sanctionsColumns: ColumnDef<SanctionPortail>[] = [
    {
        accessorKey: 'date_decision',
        header: 'Date',
        cell: ({ row }) => <span className="text-xs text-muted-foreground">{row.original.date_decision}</span>,
    },
    {
        accessorKey: 'nature_sanction',
        header: 'Sanction',
        cell: ({ row }) => (
            <Badge variant="destructive" className="text-xs">
                {row.original.nature_sanction}
            </Badge>
        ),
    },
    {
        accessorKey: 'motif',
        header: 'Motif de la sanction',
        cell: ({ row }) => <span className="text-xs text-muted-foreground">{row.original.motif}</span>,
    },
];

/**
 * Page principale du Portail Parents & Élèves (Module 16 Gesco V1.1).
 *
 * @param {PortailPageProps} props
 * @returns {JSX.Element}
 */
export default function PortailParentIndex({
    enfants,
    enfantActif,
    profil,
    filters,
}: PortailPageProps) {
    const activeTab = filters.tab || 'academique';

    const handleNavigation = (params: Record<string, string | number | null | undefined>) => {
        router.get(
            '/portail',
            {
                enfant_id: enfantActif?.id,
                periode_id: profil?.active_periode_id,
                tab: activeTab,
                ...params,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Transformation des lignes de matières pour TanStack Table
    const matieresArray: LigneMatierePortail[] = React.useMemo(() => {
        if (!profil?.academique?.lignes_matieres) return [];
        if (Array.isArray(profil.academique.lignes_matieres)) {
            return profil.academique.lignes_matieres;
        }
        return Object.values(profil.academique.lignes_matieres);
    }, [profil]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Portail Parents & Élèves" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* 1. Sélecteur Multi-Enfants (Gesco 16.1) */}
                {enfants.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Users className="size-4 text-primary" />
                            Mes Enfants Rattachés ({enfants.length})
                        </span>
                        <div className="flex flex-wrap gap-2.5">
                            {enfants.map((enf) => {
                                const isSelected = enf.id === enfantActif?.id;
                                const classeNom = enf.inscriptions?.[0]?.classe?.nom || 'Inscrit';
                                return (
                                    <button
                                        key={enf.id}
                                        type="button"
                                        onClick={() => handleNavigation({ enfant_id: enf.id })}
                                        className={`flex items-center gap-3 px-4 py-2.5 rounded-xl border text-left transition-all ${
                                            isSelected
                                                ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                                : 'bg-card text-card-foreground border-border hover:bg-muted/50'
                                        }`}
                                    >
                                        <div className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-sm">
                                            {enf.nom.charAt(0)}
                                        </div>
                                        <div className="flex flex-col">
                                            <span className="font-semibold text-sm leading-tight">
                                                {enf.nom} {enf.prenom}
                                            </span>
                                            <span className={`text-xs ${isSelected ? 'text-primary-foreground/80' : 'text-muted-foreground'}`}>
                                                {classeNom} • {enf.matricule}
                                            </span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* 2. Profil de l'enfant actif */}
                {profil ? (
                    <>
                        {/* Carte récapitulative de l'enfant */}
                        <Card className="border-border/80 bg-linear-to-r from-card via-card to-primary/5">
                            <CardContent className="p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                <div className="flex items-center gap-4">
                                    <div className="flex size-14 items-center justify-center rounded-2xl bg-primary/10 border border-primary/20 text-primary font-bold text-xl">
                                        {profil.eleve.nom.charAt(0)}
                                    </div>
                                    <div className="space-y-0.5">
                                        <h2 className="text-xl font-bold text-foreground">
                                            {profil.eleve.nom_complet}
                                        </h2>
                                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                            <span className="font-semibold text-primary">{profil.classe?.nom}</span>
                                            <span>•</span>
                                            <span>Matricule : <strong>{profil.eleve.matricule}</strong></span>
                                            <span>•</span>
                                            <span>Prof. Principal : {profil.classe?.professeur_principal}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Résumé financier & état du verrou */}
                                <div className="flex flex-wrap items-center gap-2 sm:self-center">
                                    {profil.verrou_solvabilite.est_bloque ? (
                                        <Badge variant="destructive" className="gap-1.5 py-1 px-2.5">
                                            <Lock className="size-3.5" />
                                            Solde Restant Exigible
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline" className="text-emerald-700 bg-emerald-50 border-emerald-300 gap-1.5 py-1 px-2.5">
                                            <CheckCircle2 className="size-3.5" />
                                            En règle avec l'intendance
                                        </Badge>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Onglets de navigation principale (Gesco 16.2) */}
                        <Tabs
                            value={activeTab}
                            onValueChange={(val: string) => handleNavigation({ tab: val })}
                            className="w-full"
                        >
                            <TabsList className="grid grid-cols-4 max-w-2xl mb-4">
                                <TabsTrigger value="academique" className="text-xs gap-1.5">
                                    <BookOpen className="size-3.5" />
                                    Notes & Bulletins
                                </TabsTrigger>
                                <TabsTrigger value="emploi-temps" className="text-xs gap-1.5">
                                    <CalendarDays className="size-3.5" />
                                    Emploi du Temps
                                </TabsTrigger>
                                <TabsTrigger value="discipline" className="text-xs gap-1.5">
                                    <ShieldAlert className="size-3.5" />
                                    Discipline
                                </TabsTrigger>
                                <TabsTrigger value="finances" className="text-xs gap-1.5">
                                    <DollarSign className="size-3.5" />
                                    Finances & Reçus
                                </TabsTrigger>
                            </TabsList>

                            {/* Onglet 1 : Suivi Académique & Notes (Gesco 16.2) */}
                            <TabsContent value="academique" className="space-y-4">
                                {/* Sélecteur de Trimestre & Téléchargement Bulletin */}
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-muted/40 p-3.5 rounded-xl border">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-semibold text-muted-foreground">Période :</span>
                                        <Select
                                            value={profil.active_periode_id ? profil.active_periode_id.toString() : ''}
                                            onValueChange={(val) => handleNavigation({ periode_id: val })}
                                        >
                                            <SelectTrigger className="w-45 h-8 text-xs">
                                                <SelectValue placeholder="Choisir un trimestre" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {profil.periodes.map((p) => (
                                                    <SelectItem key={p.id} value={p.id.toString()}>
                                                        {p.nom}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/* Action de Téléchargement OU Message de Verrou (Gesco 11.5 & 16.3) */}
                                    {profil.verrou_solvabilite.est_bloque ? (
                                        <div className="flex items-center gap-2 text-xs font-semibold text-destructive">
                                            <Lock className="size-4" />
                                            <span>Bulletin verrouillé (insolvabilité)</span>
                                        </div>
                                    ) : (
                                        profil.inscription && (
                                            <Button size="sm" className="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white" asChild>
                                                <a
                                                    href={`/portail/bulletin/${profil.inscription.id}${profil.active_periode_id ? `?periode_id=${profil.active_periode_id}` : ''}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Download className="size-4" />
                                                    Télécharger le Bulletin Officiel (PDF)
                                                </a>
                                            </Button>
                                        )
                                    )}
                                </div>

                                {/* Encadré Rouge Réglementaire de Verrou Financier (Gesco 11.5 & 16.3) */}
                                {profil.verrou_solvabilite.est_bloque && (
                                    <div className="rounded-xl border border-destructive/50 bg-destructive/10 p-4 text-destructive space-y-1">
                                        <div className="flex items-center gap-2 font-bold text-sm">
                                            <AlertCircle className="size-5 shrink-0" />
                                            <span>Accès restreint au bulletin de notes</span>
                                        </div>
                                        <p className="text-xs pl-7 text-foreground">
                                            {profil.verrou_solvabilite.message}
                                        </p>
                                    </div>
                                )}

                                {/* Carte Moyenne Générale & Rang */}
                                {profil.academique && (
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <Card className="p-4">
                                            <span className="text-xs font-medium text-muted-foreground">Moyenne Générale</span>
                                            <div className="text-2xl font-bold text-foreground mt-1">
                                                {Number(profil.academique.moyenne_generale).toFixed(2)} / 20
                                            </div>
                                            <span className="text-xs text-muted-foreground mt-0.5 block">
                                                {profil.academique.total_coefficients} coefficients cumulés
                                            </span>
                                        </Card>

                                        <Card className="p-4">
                                            <span className="text-xs font-medium text-muted-foreground">Rang dans la Classe</span>
                                            <div className="text-2xl font-bold text-primary mt-1">
                                                {profil.academique.rang_general ? `${profil.academique.rang_general}e` : '-'}
                                            </div>
                                            <span className="text-xs text-muted-foreground mt-0.5 block">
                                                Sur {profil.statistiques_classe?.moyenne_classe ? 'l\'ensemble des élèves' : 'la classe'}
                                            </span>
                                        </Card>

                                        <Card className="p-4">
                                            <span className="text-xs font-medium text-muted-foreground">Moyenne de Classe</span>
                                            <div className="text-2xl font-bold text-muted-foreground mt-1">
                                                {profil.statistiques_classe?.moyenne_classe ? Number(profil.statistiques_classe.moyenne_classe).toFixed(2) : '-'} / 20
                                            </div>
                                            <span className="text-xs text-muted-foreground mt-0.5 block">
                                                Taux réussite : {profil.statistiques_classe?.taux_reussite ?? 0}%
                                            </span>
                                        </Card>
                                    </div>
                                )}

                                {/* Tableau TanStack des Notes par Matière */}
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base">Détail des Matières & Évaluations</CardTitle>
                                        <CardDescription>
                                            Notes individuelles, appréciations pédagogiques et compétences acquises
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <DataTable
                                            columns={notesColumns}
                                            data={matieresArray}
                                            searchPlaceholder="Rechercher une matière..."
                                            defaultPageSize={12}
                                        />
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Onglet 2 : Emploi du Temps Hebdomadaire (Gesco 13 & 16.2) */}
                            <TabsContent value="emploi-temps" className="space-y-4">
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <Calendar className="size-4 text-primary" />
                                            Planning Hebdomadaire des Cours ({profil.classe?.nom})
                                        </CardTitle>
                                        <CardDescription>
                                            Matières, horaires, enseignants et salles de classe attitrées
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <DataTable
                                            columns={emploiTempsColumns}
                                            data={profil.emploi_du_temps}
                                            searchPlaceholder="Filtrer par jour ou matière..."
                                            defaultPageSize={15}
                                        />
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Onglet 3 : Discipline & Assiduité (Gesco 12 & 16.2) */}
                            <TabsContent value="discipline" className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <Card className="p-4">
                                        <span className="text-xs font-medium text-muted-foreground">Absences Justifiées</span>
                                        <div className="text-2xl font-bold text-foreground mt-1">
                                            {profil.discipline.absences_justifiees} heures
                                        </div>
                                        <span className="text-xs text-muted-foreground mt-0.5 block">Motifs régularisés</span>
                                    </Card>

                                    <Card className="p-4">
                                        <span className="text-xs font-medium text-muted-foreground">Absences Non Justifiées</span>
                                        <div className={`text-2xl font-bold mt-1 ${profil.discipline.absences_non_justifiees >= 15 ? 'text-destructive font-extrabold' : 'text-foreground'}`}>
                                            {profil.discipline.absences_non_justifiees} heures
                                        </div>
                                        {profil.discipline.absences_non_justifiees >= 15 ? (
                                            <span className="text-xs text-destructive font-semibold mt-0.5 block">
                                                Alerte : Seuil de déchéance des mentions atteint (&ge;15h)
                                            </span>
                                        ) : (
                                            <span className="text-xs text-muted-foreground mt-0.5 block">Seuil d'alerte : 15h</span>
                                        )}
                                    </Card>

                                    <Card className="p-4">
                                        <span className="text-xs font-medium text-muted-foreground">Consignes & Retenues</span>
                                        <div className="text-2xl font-bold text-amber-600 mt-1">
                                            {profil.discipline.consignes} heures
                                        </div>
                                        <span className="text-xs text-muted-foreground mt-0.5 block">Heures de retenue effectuées</span>
                                    </Card>
                                </div>

                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <ShieldAlert className="size-4 text-destructive" />
                                            Registre des Sanctions Disciplinaires
                                        </CardTitle>
                                        <CardDescription>Historique des avertissements, blâmes et exclusions</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <DataTable
                                            columns={sanctionsColumns}
                                            data={profil.discipline.sanctions}
                                            searchPlaceholder="Rechercher une sanction..."
                                            defaultPageSize={5}
                                        />
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Onglet 4 : Espace Financier & Reçus (Gesco 11 & 16.2) */}
                            <TabsContent value="finances" className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <Card className="p-4">
                                        <span className="text-xs font-medium text-muted-foreground">Montant Net Scolarité</span>
                                        <div className="text-2xl font-bold text-foreground mt-1">
                                            {new Intl.NumberFormat('fr-FR').format(profil.finances.montant_net_exigible)} FCFA
                                        </div>
                                        <span className="text-xs text-muted-foreground mt-0.5 block">Frais obligatoires annuels</span>
                                    </Card>

                                    <Card className="p-4">
                                        <span className="text-xs font-medium text-muted-foreground">Total Déjà Réglé</span>
                                        <div className="text-2xl font-bold text-emerald-600 mt-1">
                                            {new Intl.NumberFormat('fr-FR').format(profil.finances.total_paye)} FCFA
                                        </div>
                                        <span className="text-xs text-muted-foreground mt-0.5 block">Versements encaissés</span>
                                    </Card>

                                    <Card className="p-4">
                                        <span className="text-xs font-medium text-muted-foreground">Solde Restant Dû</span>
                                        <div className={`text-2xl font-bold mt-1 ${profil.finances.solde_restant > 0 ? 'text-destructive' : 'text-emerald-600'}`}>
                                            {new Intl.NumberFormat('fr-FR').format(profil.finances.solde_restant)} FCFA
                                        </div>
                                        <span className="text-xs text-muted-foreground mt-0.5 block">
                                            Statut : {profil.finances.statut_financier === 'solde' ? 'Soldé' : 'Débiteur'}
                                        </span>
                                    </Card>
                                </div>

                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <Receipt className="size-4 text-primary" />
                                            Historique des Versements & Reçus Officiels
                                        </CardTitle>
                                        <CardDescription>
                                            Téléchargement direct des reçus PDF certifiés avec QR Code de sécurité
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <DataTable
                                            columns={versementsColumns}
                                            data={profil.finances.versements}
                                            searchPlaceholder="Rechercher par numéro de reçu..."
                                            defaultPageSize={10}
                                        />
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </>
                ) : (
                    <Card className="p-12 text-center text-muted-foreground">
                        <Users className="size-12 mx-auto mb-3 stroke-1 text-muted-foreground/60" />
                        <h3 className="font-semibold text-lg text-foreground">Aucun enfant sélectionné</h3>
                        <p className="text-sm mt-1">Veuillez sélectionner un enfant ci-dessus pour consulter son dossier.</p>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

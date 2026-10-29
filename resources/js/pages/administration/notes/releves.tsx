import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    Award,
    BookOpen,
    Calendar,
    Download,
    Edit3,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    Printer,
    School,
    Users,
} from 'lucide-react';
import React, { useMemo } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatAnneeLabel } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

/**
 * Interface représentant une évaluation sommative / séquentielle.
 */
interface EvaluationItem {
    id: number;
    nom: string;
    code: string;
    ordre: number;
}

/**
 * Interface représentant une période pédagogique (ex: Trimestre 1).
 */
interface PeriodeItem {
    id: number;
    nom: string;
    code: string;
    evaluations: EvaluationItem[];
}

/**
 * Interface représentant une attribution de cours pour un enseignant.
 */
interface AttributionItem {
    id: number;
    classe_id: number;
    matiere_id: number;
    personnel_id: number;
    volume_horaire_reel?: number | null;
    classe?: {
        id: number;
        nom: string;
        code: string;
    };
    matiere?: {
        id: number;
        nom: string;
        code: string;
    };
    personnel?: {
        id: number;
        nom: string;
        prenom?: string;
        matricule: string;
    };
}

/**
 * Interface représentant une classe de cours.
 */
interface ClasseItem {
    id: number;
    nom: string;
    code: string;
    niveau?: {
        id: number;
        nom: string;
    };
}

/**
 * Ligne de données élève pour la grille TanStack Table.
 */
interface EleveReleveLigne {
    numero: number;
    inscription_id: number;
    eleve_id: number;
    matricule: string;
    nom: string;
    prenom: string;
    nom_complet: string;
    sexe: string;
    date_naissance?: string | null;
    notes: Record<number, {
        valeur_note: number | null;
        statut_presence: string;
        appreciation?: string | null;
        is_verrouillee?: boolean;
    } | undefined>;
    moyenne: number | null;
}

/**
 * Propriétés transmises au composant par le contrôleur Inertia.
 */
interface RelevesProps {
    etablissements: Array<{ id: number; nom: string; code: string }>;
    currentEtablissement: { id: number; nom: string; code: string } | null;
    annees: Array<{ id: number; nom: string; code?: string; session?: string; is_active?: boolean; is_current?: boolean }>;
    anneeActiveId: number;
    periodes: PeriodeItem[];
    classes: ClasseItem[];
    selectedClasse: ClasseItem | null;
    attributions: AttributionItem[];
    selectedAttribution: AttributionItem | null;
    selectedEvaluations: EvaluationItem[];
    eleves: EleveReleveLigne[];
    isGestionnaire: boolean;
    filters: {
        annee_id: number;
        classe_id: number;
        attribution_id: number;
        periode_id: number;
        evaluation_id: number;
        mode: 'rempli' | 'vierge';
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Accueil',
        href: '/dashboard',
    },
    {
        title: 'Pédagogie & Évaluations',
        href: '/administration',
    },
    {
        title: 'Relevés de Notes',
        href: '/administration/notes/releves',
    },
];

/**
 * Page de Consultation et d'Exportation des Relevés de Notes / Fiches Officielles de Notation.
 *
 * Conforme au principe de sécurité du moindre privilège (Least Privilege) et aux directives SYGES :
 * - Permet la sélection par classe seule (fiche manuscrite polyvalente à photocopier) ou par attribution (classe + matière).
 * - Tableau de données propulsé obligatoirement par TanStack Table.
 * - Dates formatées en jj-mm-aaaa via formatDate.
 * - Toasts positionnés en top-right et verts pour les succès.
 * - Multi-tenant SaaS : cloisonnement strict par établissement.
 *
 * @param {RelevesProps} props Propriétés du composant.
 * @returns {JSX.Element}
 */
export default function RelevesNotesIndex({
    etablissements = [],
    currentEtablissement = null,
    annees = [],
    anneeActiveId = 0,
    periodes = [],
    classes = [],
    selectedClasse = null,
    attributions = [],
    selectedAttribution = null,
    selectedEvaluations = [],
    eleves = [],
    isGestionnaire = false,
    filters = {
        annee_id: 0,
        classe_id: 0,
        attribution_id: 0,
        periode_id: 0,
        evaluation_id: 0,
        mode: 'rempli',
    },
}: RelevesProps) {
    /**
     * Met à jour les filtres d'affichage et recharge les données via Inertia.
     *
     * @param {string} key Nom du filtre à modifier.
     * @param {string | number} value Nouvelle valeur.
     */
    const handleFilterChange = (key: string, value: string | number) => {
        const nextFilters: Record<string, string | number> = {
            ...filters,
            [key]: value,
        };

        // Si on change de classe, on réinitialise l'attribution à 0 (Fiche polyvalente par défaut)
        if (key === 'classe_id') {
            nextFilters.attribution_id = 0;
            nextFilters.mode = 'vierge';
        }

        // Si on sélectionne la fiche vierge polyvalente (attribution_id = 0), basculer en mode vierge
        if (key === 'attribution_id' && Number(value) === 0) {
            nextFilters.mode = 'vierge';
        }

        // Réinitialiser l'évaluation si on change de période
        if (key === 'periode_id') {
            nextFilters.evaluation_id = 0;
        }

        router.get(
            '/administration/notes/releves',
            nextFilters,
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    /**
     * Génère et ouvre le document PDF officiel de l'État de Remplissage de la Classe (Grand Babillard Multidisciplinaire).
     * Présente la grille croisée complète de la classe avec toutes les matières en colonnes et les élèves en lignes.
     */
    const handlePrintEtatRemplissage = () => {
        if (!selectedClasse) {
            toast.error('Veuillez d\'abord sélectionner une classe.', { position: 'top-right' });
            return;
        }

        const queryParams = new URLSearchParams({
            classe_id: selectedClasse.id.toString(),
            annee_id: (filters.annee_id || anneeActiveId).toString(),
            periode_id: (filters.periode_id || 0).toString(),
            evaluation_id: (filters.evaluation_id || 0).toString(),
        });

        const url = `/administration/notes/releves/etat-remplissage?${queryParams.toString()}`;
        window.open(url, '_blank');

        toast.success('Génération du babillard / état de remplissage de la classe en cours...', {
            position: 'top-right',
        });
    };

    /**
     * Déclenche l'ouverture ou le téléchargement du document PDF officiel.
     *
     * @param {'rempli' | 'vierge'} [forceMode] Mode optionnel pour forcer une version vierge ou remplie.
     */
    const handlePrintPdf = (forceMode?: 'rempli' | 'vierge') => {
        if (!selectedClasse) {
            toast.error('Veuillez d\'abord sélectionner une classe.', { position: 'top-right' });
            return;
        }

        const modeToUse = forceMode || filters.mode;

        // Si on tente d'imprimer en mode rempli sans matière sélectionnée
        if (modeToUse === 'rempli' && !selectedAttribution) {
            toast.error('Veuillez sélectionner une matière spécifique pour imprimer les notes saisies.', { position: 'top-right' });
            return;
        }

        const queryParams = new URLSearchParams({
            classe_id: selectedClasse.id.toString(),
            attribution_id: (selectedAttribution?.id ?? 0).toString(),
            annee_id: (filters.annee_id || anneeActiveId).toString(),
            periode_id: (filters.periode_id || 0).toString(),
            evaluation_id: (filters.evaluation_id || 0).toString(),
            mode: modeToUse,
        });

        const url = `/administration/notes/releves/pdf?${queryParams.toString()}`;
        window.open(url, '_blank');

        toast.success(
            modeToUse === 'vierge'
                ? (!selectedAttribution
                    ? 'Génération de la fiche de notation vierge polyvalente (à photocopier)...'
                    : 'Génération de la fiche de notation vierge en cours...')
                : 'Génération du relevé de notes officiel en cours...',
            { position: 'top-right' }
        );
    };

    /**
     * Définition dynamique des colonnes du tableau TanStack Table.
     */
    const columns = useMemo<ColumnDef<EleveReleveLigne>[]>(() => {
        const cols: ColumnDef<EleveReleveLigne>[] = [
            {
                accessorKey: 'numero',
                header: 'N°',
                cell: ({ row }) => (
                    <span className="font-semibold text-xs text-muted-foreground">
                        {row.original.numero}
                    </span>
                ),
            },
            {
                accessorKey: 'matricule',
                header: 'Matricule',
                cell: ({ row }) => (
                    <span className="font-mono text-xs font-semibold text-foreground">
                        {row.original.matricule}
                    </span>
                ),
            },
            {
                accessorKey: 'nom_complet',
                header: 'Nom & Prénom de l\'élève',
                cell: ({ row }) => (
                    <div className="flex flex-col">
                        <span className="font-medium text-sm text-foreground">
                            {row.original.nom_complet}
                        </span>
                        {row.original.date_naissance && (
                            <span className="text-[11px] text-muted-foreground">
                                Né(e) le {formatDate(row.original.date_naissance)}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'sexe',
                header: 'Sexe',
                cell: ({ row }) => (
                    <Badge variant={row.original.sexe === 'F' ? 'secondary' : 'outline'} className="text-[11px]">
                        {row.original.sexe}
                    </Badge>
                ),
            },
        ];

        // Colonnes pour chaque évaluation sélectionnée
        selectedEvaluations.forEach((evalItem) => {
            cols.push({
                id: `eval_${evalItem.id}`,
                header: () => (
                    <div className="text-center">
                        <div>{evalItem.nom}</div>
                        <div className="text-[10px] font-normal text-muted-foreground">/ 20</div>
                    </div>
                ),
                cell: ({ row }) => {
                    const noteInfo = row.original.notes?.[evalItem.id];

                    if (filters.mode === 'vierge' || !selectedAttribution) {
                        return <span className="text-muted-foreground/30 text-xs italic">—</span>;
                    }

                    if (!noteInfo || noteInfo.valeur_note === null || noteInfo.valeur_note === undefined) {
                        return <span className="text-muted-foreground/50 text-xs italic">Non saisie</span>;
                    }

                    const numVal = Number(noteInfo.valeur_note);
                    if (isNaN(numVal)) {
                        return <span className="text-muted-foreground/50 text-xs italic">—</span>;
                    }

                    return (
                        <div className="text-center">
                            <span
                                className={`font-mono text-xs font-bold ${
                                    numVal < 10
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : 'text-emerald-600 dark:text-emerald-400'
                                }`}
                            >
                                {numVal.toFixed(2)}
                            </span>
                        </div>
                    );
                },
            });
        });

        // Colonne moyenne si plusieurs évaluations et si une matière spécifique est sélectionnée en mode rempli
        if (selectedEvaluations.length > 1 && filters.mode === 'rempli' && selectedAttribution) {
            cols.push({
                accessorKey: 'moyenne',
                header: () => (
                    <div className="text-center font-bold">
                        <div>Moyenne</div>
                        <div className="text-[10px] font-normal text-muted-foreground">/ 20</div>
                    </div>
                ),
                cell: ({ row }) => {
                    const rawMoy = row.original.moyenne;
                    if (rawMoy === null || rawMoy === undefined) {
                        return <span className="text-center block text-xs text-muted-foreground">—</span>;
                    }
                    const numMoy = Number(rawMoy);
                    if (isNaN(numMoy)) {
                        return <span className="text-center block text-xs text-muted-foreground">—</span>;
                    }
                    return (
                        <div className="text-center">
                            <Badge
                                variant={numMoy < 10 ? 'destructive' : 'default'}
                                className="font-mono text-xs px-2 py-0.5"
                            >
                                {numMoy.toFixed(2)}/20
                            </Badge>
                        </div>
                    );
                },
            });
        }

        return cols;
    }, [selectedEvaluations, filters.mode, selectedAttribution]);

    // Période courante sélectionnée
    const currentPeriode = periodes.find((p) => p.id === Number(filters.periode_id));

    return (
        <>
            <Head title="Relevés de Notes & Fiches de Notation" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête de la page */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0">
                        <div className="flex items-start sm:items-center gap-2.5">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">
                                <FileSpreadsheet className="size-5" />
                            </div>
                            <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                                Relevés de Notes & Fiches de Notation
                            </h1>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            Génération des fiches officielles de report de notes (format papier vierge ou polyvalente à photocopier) et des relevés de notes saisis
                        </p>
                    </div>

                    {/* Actions d'impression PDF */}
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="default"
                            size="sm"
                            onClick={handlePrintEtatRemplissage}
                            disabled={!selectedClasse}
                            className="gap-1.5 bg-blue-600 hover:bg-blue-700 text-white shadow-xs"
                            title="Tableau croisé : toutes les matières en colonnes et les élèves en lignes pour affichage et contrôle"
                        >
                            <FileSpreadsheet className="size-4 shrink-0" />
                            État de Remplissage de la Classe (PDF)
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handlePrintPdf('vierge')}
                            disabled={!selectedClasse}
                            className="gap-1.5"
                        >
                            <Printer className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            Fiche Vierge (PDF)
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handlePrintPdf('rempli')}
                            disabled={!selectedClasse || !selectedAttribution}
                            className="gap-1.5 border-amber-600 text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/40 disabled:opacity-50"
                        >
                            <Download className="size-4 shrink-0" />
                            Relevé Rempli (Matière)
                        </Button>
                    </div>
                </div>

                {/* Filtres de sélection contextuelle */}
                <Card className="border-border/60 shadow-xs">
                    <CardHeader className="pb-3 px-4 sm:px-6">
                        <CardTitle className="text-base font-semibold flex items-center gap-2">
                            <GraduationCap className="size-4 shrink-0 text-primary" />
                            Sélection de la Classe & de la Matière
                        </CardTitle>
                        <CardDescription>
                            Choisissez votre classe et éventuellement votre matière pour afficher la liste des élèves ou tirer votre fiche de notes
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-4 sm:px-6">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            {/* 1. Année Scolaire (affichée sans redondance) */}
                            <div className="flex flex-col gap-1.5 min-w-0">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Calendar className="size-3.5" />
                                    Année Scolaire
                                </label>
                                <Select
                                    value={filters.annee_id?.toString() || anneeActiveId.toString()}
                                    onValueChange={(val) => handleFilterChange('annee_id', val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <div className="flex items-center gap-2 truncate">
                                            {(() => {
                                                const an = annees.find((a) => a.id.toString() === (filters.annee_id?.toString() || anneeActiveId.toString()));
                                                return an && (an.is_current || an.is_active) ? (
                                                    <span className="relative flex h-2 w-2 shrink-0" title="Année scolaire active">
                                                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                        <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 shadow-xs shadow-emerald-500/50"></span>
                                                    </span>
                                                ) : null;
                                            })()}
                                            <SelectValue placeholder="Année">
                                                {(() => {
                                                    const an = annees.find((a) => a.id.toString() === (filters.annee_id?.toString() || anneeActiveId.toString()));
                                                    return an ? formatAnneeLabel(an.nom, an.code, an.session) : undefined;
                                                })()}
                                            </SelectValue>
                                        </div>
                                    </SelectTrigger>
                                    <SelectContent>
                                        {annees.map((a) => (
                                            <SelectItem key={a.id} value={a.id.toString()}>
                                                <div className="flex items-center gap-2">
                                                    {(a.is_current || a.is_active) && (
                                                        <span className="relative flex h-2 w-2 shrink-0">
                                                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                            <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                                        </span>
                                                    )}
                                                    <span>{formatAnneeLabel(a.nom, a.code, a.session)}</span>
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* 2. Sélection de la Classe */}
                            <div className="flex flex-col gap-1.5 min-w-0">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <School className="size-3.5" />
                                    Classe
                                </label>
                                <Select
                                    value={filters.classe_id?.toString() || (selectedClasse?.id?.toString() ?? '')}
                                    onValueChange={(val) => handleFilterChange('classe_id', val)}
                                >
                                    <SelectTrigger className="w-full min-w-0 overflow-hidden [&>span]:truncate">
                                        <SelectValue placeholder="Sélectionnez une classe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classes.map((cls) => (
                                            <SelectItem key={cls.id} value={cls.id.toString()}>
                                                {cls.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* 3. Matière & Enseignant (optionnelle pour permettre la fiche polyvalente) */}
                            <div className="flex flex-col gap-1.5 min-w-0">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <BookOpen className="size-3.5" />
                                    Matière & Enseignant
                                </label>
                                <Select
                                    value={filters.attribution_id?.toString() || (selectedAttribution?.id?.toString() ?? '0')}
                                    onValueChange={(val) => handleFilterChange('attribution_id', val)}
                                    disabled={!selectedClasse}
                                >
                                    <SelectTrigger className="w-full min-w-0 overflow-hidden [&>span]:truncate">
                                        <SelectValue placeholder="Toutes matières / Vierge" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0" className="font-medium text-amber-700 dark:text-amber-400">
                                            📋 Fiche vierge polyvalente (toutes matières)
                                        </SelectItem>
                                        {attributions.map((attr) => (
                                            <SelectItem key={attr.id} value={attr.id.toString()} className="max-w-md truncate">
                                                {attr.matiere?.nom}
                                                {isGestionnaire && attr.personnel ? ` (${attr.personnel.nom})` : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* 4. Période / Trimestre */}
                            <div className="flex flex-col gap-1.5 min-w-0">
                                <label className="text-xs font-medium text-muted-foreground">
                                    Période / Trimestre
                                </label>
                                <Select
                                    value={filters.periode_id?.toString() || (periodes[0]?.id?.toString() ?? '0')}
                                    onValueChange={(val) => handleFilterChange('periode_id', val)}
                                >
                                    <SelectTrigger className="w-full min-w-0 overflow-hidden [&>span]:truncate">
                                        <SelectValue placeholder="Période" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">Toutes les périodes</SelectItem>
                                        {periodes.map((p) => (
                                            <SelectItem key={p.id} value={p.id.toString()}>
                                                {p.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* 5. Séquence / Évaluation */}
                            <div className="flex flex-col gap-1.5 min-w-0">
                                <label className="text-xs font-medium text-muted-foreground">
                                    Évaluation Ciblée
                                </label>
                                <Select
                                    value={filters.evaluation_id?.toString() || '0'}
                                    onValueChange={(val) => handleFilterChange('evaluation_id', val)}
                                >
                                    <SelectTrigger className="w-full min-w-0 overflow-hidden [&>span]:truncate">
                                        <SelectValue placeholder="Toutes les évaluations" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">Toutes les évaluations</SelectItem>
                                        {(currentPeriode?.evaluations ?? []).map((ev) => (
                                            <SelectItem key={ev.id} value={ev.id.toString()}>
                                                {ev.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Sélecteur de mode d'affichage rapide */}
                        <div className="flex flex-wrap items-center justify-between gap-3 mt-4 pt-3 border-t border-border/50">
                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span>Mode de vue :</span>
                                <Badge
                                    variant={filters.mode === 'rempli' ? 'default' : 'outline'}
                                    className={selectedAttribution ? 'cursor-pointer hover:opacity-80' : 'opacity-40 cursor-not-allowed'}
                                    onClick={() => {
                                        if (selectedAttribution) {
                                            handleFilterChange('mode', 'rempli');
                                        } else {
                                            toast.error('Veuillez sélectionner une matière spécifique pour afficher les notes saisies.', { position: 'top-right' });
                                        }
                                    }}
                                >
                                    Notes Remplies
                                </Badge>
                                <Badge
                                    variant={filters.mode === 'vierge' ? 'default' : 'outline'}
                                    className="cursor-pointer hover:opacity-80"
                                    onClick={() => handleFilterChange('mode', 'vierge')}
                                >
                                    Fiche Vierge
                                </Badge>
                            </div>

                            {selectedAttribution && (
                                <Link
                                    href={`/administration/notes?attribution_id=${selectedAttribution.id}&annee_id=${filters.annee_id || anneeActiveId}`}
                                    className="text-xs font-medium text-rose-600 hover:text-rose-700 dark:text-rose-400 flex items-center gap-1"
                                >
                                    <Edit3 className="size-3.5 shrink-0" />
                                    Basculer vers la Saisie des Notes
                                </Link>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Bannière récapitulative du cours sélectionné */}
                {selectedClasse ? (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <Card className="bg-primary/5 border-primary/20 p-4 flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <GraduationCap className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <div className="text-xs text-muted-foreground">Classe assignée</div>
                                <div className="text-base font-bold text-foreground truncate">
                                    {selectedClasse.nom}
                                </div>
                            </div>
                        </Card>

                        <Card className="bg-amber-500/5 border-amber-500/20 p-4 flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <BookOpen className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <div className="text-xs text-muted-foreground">Discipline / Matière</div>
                                <div className="text-base font-bold text-foreground truncate">
                                    {selectedAttribution ? selectedAttribution.matiere?.nom : 'Fiche vierge polyvalente'}
                                </div>
                                <div className="text-[11px] text-muted-foreground truncate">
                                    {selectedAttribution
                                        ? (selectedAttribution.personnel ? `${selectedAttribution.personnel.nom} ${selectedAttribution.personnel.prenom ?? ''}` : 'Enseignant assigné')
                                        : 'À renseigner au stylo (multi-matières)'}
                                </div>
                            </div>
                        </Card>

                        <Card className="bg-emerald-500/5 border-emerald-500/20 p-4 flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <Users className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <div className="text-xs text-muted-foreground">Effectif total</div>
                                <div className="text-base font-bold text-foreground tabular-nums">
                                    {eleves.length} élèves inscrits
                                </div>
                            </div>
                        </Card>
                    </div>
                ) : (
                    <Card className="p-6 sm:p-8 text-center border-dashed">
                        <div className="flex flex-col items-center gap-2">
                            <FileSpreadsheet className="size-8 text-muted-foreground/60" />
                            <h3 className="font-semibold text-foreground">Aucune classe sélectionnée</h3>
                            <p className="text-sm text-muted-foreground max-w-sm">
                                Veuillez sélectionner une classe dans les filtres ci-dessus pour afficher la liste des élèves et générer les relevés de notes.
                            </p>
                        </div>
                    </Card>
                )}

                {/* Tableau TanStack Table officiel */}
                {selectedClasse && (
                    <Card className="border-border/60 shadow-xs overflow-hidden min-w-0">
                        <CardHeader className="pb-3 flex flex-wrap items-center justify-between gap-2 px-4 sm:px-6">
                            <div className="min-w-0">
                                <CardTitle className="text-base font-semibold">
                                    Liste Officielle des Élèves ({eleves.length}) — {selectedClasse.nom}
                                </CardTitle>
                                <CardDescription>
                                    {!selectedAttribution || filters.mode === 'vierge'
                                        ? 'Aperçu du gabarit pour saisie manuelle. Cliquez sur « Fiche Vierge (PDF) » pour imprimer ou photocopier.'
                                        : 'Aperçu des notes enregistrées. Cliquez sur « Imprimer Relevé Rempli (PDF) » pour l\'export officiel.'}
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent className="px-3 sm:px-6">
                            <DataTable columns={columns} data={eleves} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

/**
 * Configuration de la mise en page persistante et du fil d'Ariane pour les Relevés de Notes.
 */
RelevesNotesIndex.layout = {
    breadcrumbs,
};

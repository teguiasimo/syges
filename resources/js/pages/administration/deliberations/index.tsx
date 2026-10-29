import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    AlertCircle,
    AlertTriangle,
    Award,
    BookOpen,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    FileText,
    HelpCircle,
    Printer,
    Save,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    UserCheck,
    Users,
} from 'lucide-react';
import * as React from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { BreadcrumbItem } from '@/types';
import type { DeliberationData, LigneDeliberation } from '@/types/statistique-deliberation';

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
    niveau?: { nom: string };
}

interface PeriodeOption {
    id: number;
    nom: string;
    ordre: number;
}

interface DeliberationsPageProps {
    etablissements: EtablissementOption[];
    currentEtablissement: EtablissementOption | null;
    annees: AnneeOption[];
    currentAnneeId: number;
    classes: ClasseOption[];
    selectedClasseId: number | null;
    periodes: PeriodeOption[];
    selectedPeriodeId: number | null;
    typeSession: 'trimestre' | 'annuel';
    deliberationData: DeliberationData | null;
    canValider: boolean;
    canManage: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Administration',
        href: '/administration',
    },
    {
        title: 'Délibérations du Conseil de Classe',
        href: '/administration/deliberations',
    },
];

/**
 * Page de gestion des Délibérations du Conseil de Classe et PV (Module 15 & Module 2.6).
 *
 * Implémente :
 * - Le tableau TanStack interactif des notes, moyennes, rangs et mentions d'honneur.
 * - Le blocage d'office des mentions en cas d'absences non justifiées >= 15h ou note éliminatoire (12.4 & 4.3).
 * - La proposition automatisée de passage de fin d'année (Promu / Redouble / Exclu) (2.6 Étape A).
 * - Le vote du conseil avec modification individuelle du statut retenu.
 * - La validation humaine obligatoire de promotion par la Direction (2.6 Étape B).
 * - L'édition et le téléchargement du Procès-Verbal officiel du Conseil de Classe en PDF.
 *
 * @param {DeliberationsPageProps} props
 * @returns {JSX.Element}
 */
export default function DeliberationsIndex({
    etablissements,
    currentEtablissement,
    annees,
    currentAnneeId,
    classes,
    selectedClasseId,
    periodes,
    selectedPeriodeId,
    typeSession,
    deliberationData,
    canValider,
    canManage,
}: DeliberationsPageProps) {
    // État local des lignes pour édition interactive avant sauvegarde
    const [lignesLocales, setLignesLocales] = useState<LigneDeliberation[]>(
        deliberationData?.eleves ?? []
    );
    const [isSaving, setIsSaving] = useState(false);
    const [isValidationModalOpen, setIsValidationModalOpen] = useState(false);
    const [isValidating, setIsValidating] = useState(false);

    // Mettre à jour l'état local lors d'un changement de données reçues
    React.useEffect(() => {
        setLignesLocales(deliberationData?.eleves ?? []);
    }, [deliberationData]);

    /**
     * Rechargement avec nouveaux paramètres.
     */
    const handleNavigation = (params: Record<string, string | number | null | undefined>) => {
        router.get(
            '/administration/deliberations',
            {
                etablissement_id: currentEtablissement?.id,
                annee_id: currentAnneeId,
                classe_id: selectedClasseId,
                type: typeSession,
                periode_id: selectedPeriodeId,
                ...params,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    /**
     * Modification d'un champ d'une ligne d'élève.
     */
    const handleLineChange = (
        inscriptionId: number,
        field: keyof LigneDeliberation,
        value: any
    ) => {
        setLignesLocales((prev) =>
            prev.map((ligne) => {
                if (ligne.inscription_id === inscriptionId) {
                    return { ...ligne, [field]: value };
                }
                return ligne;
            })
        );
    };

    /**
     * Sauvegarde des délibérations et appréciations saisies (Brouillon de séance).
     */
    const handleSaveDeliberations = () => {
        if (!selectedClasseId) return;

        setIsSaving(true);
        router.post(
            '/administration/deliberations/sauvegarder',
            {
                classe_id: selectedClasseId,
                annee_id: currentAnneeId,
                periode_id: typeSession === 'trimestre' ? selectedPeriodeId : null,
                lignes: lignesLocales as any,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Délibérations enregistrées avec succès.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-emerald-700',
                    });
                    setIsSaving(false);
                },
                onError: () => {
                    toast.error('Erreur lors de la sauvegarde des délibérations.', {
                        position: 'top-right',
                    });
                    setIsSaving(false);
                },
            }
        );
    };

    /**
     * Validation définitive de la promotion (Module 2.6 Étape B).
     */
    const handleValiderPromotions = () => {
        if (!selectedClasseId) return;

        setIsValidating(true);
        router.post(
            '/administration/deliberations/valider-promotion',
            {
                classe_id: selectedClasseId,
                annee_id: currentAnneeId,
                decisions: lignesLocales.map((l) => ({
                    inscription_id: l.inscription_id,
                    statut_retenu: l.statut_retenu,
                    appreciation_globale: l.appreciation_globale,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Promotions de fin d\'année validées et scellées.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-emerald-700',
                    });
                    setIsValidating(false);
                    setIsValidationModalOpen(false);
                },
                onError: () => {
                    toast.error('Erreur lors de la validation des promotions.', {
                        position: 'top-right',
                    });
                    setIsValidating(false);
                },
            }
        );
    };

    /**
     * Formate de manière sécurisée une moyenne numérique sur 20.
     *
     * @param {number | string | undefined | null} val - Valeur de la moyenne
     * @returns {string} Moyenne formatée à 2 décimales ou '-' si absente
     */
    const formatMoyenne = (val: number | string | undefined | null): string => {
        if (val === undefined || val === null || val === '') return '-';
        const num = typeof val === 'number' ? val : Number(val);
        return isNaN(num) ? '-' : num.toFixed(2);
    };

    /**
     * Définition des colonnes TanStack Table pour la délibération.
     */
    const columns = React.useMemo<ColumnDef<LigneDeliberation>[]>(
        () => [
            {
                accessorKey: 'rang',
                header: 'Rang',
                cell: ({ row }) => (
                    <span className="font-bold text-xs text-center block w-8">
                        {row.original.rang ? `${row.original.rang}e` : '-'}
                    </span>
                ),
            },
            {
                accessorKey: 'nom_complet',
                header: 'Élève',
                cell: ({ row }) => (
                    <div className="flex flex-col min-w-37.5">
                        <span className="font-semibold text-sm text-foreground">
                            {row.original.nom_complet}
                        </span>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span className="font-mono">{row.original.matricule}</span>
                            <span>•</span>
                            <span>{row.original.sexe === 'F' ? 'Fille' : 'Garçon'}</span>
                            {row.original.is_redoublant && (
                                <Badge variant="secondary" className="text-[10px] px-1 py-0 h-4">
                                    Redoublant
                                </Badge>
                            )}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'moyenne_obtenue',
                header: 'Moyenne',
                cell: ({ row }) => {
                    const moy = Number(row.original.moyenne_obtenue) || 0;
                    const color =
                        moy >= 12
                            ? 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : moy >= 10
                            ? 'text-blue-700 bg-blue-50 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300'
                            : moy >= 8.5
                            ? 'text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300'
                            : 'text-rose-700 bg-rose-50 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300';
                    return (
                        <span className={`inline-block px-2 py-0.5 rounded font-mono font-bold text-xs border ${color}`}>
                            {moy.toFixed(2)}/20
                        </span>
                    );
                },
            },
            {
                accessorKey: 'absences_non_justifiees',
                header: 'Abs. NJ',
                cell: ({ row }) => {
                    const abs = row.original.absences_non_justifiees;
                    return (
                        <div className="flex flex-col items-center">
                            <span className={`text-xs font-semibold ${abs >= 15 ? 'text-destructive' : 'text-muted-foreground'}`}>
                                {abs}h
                            </span>
                            {abs >= 15 && (
                                <span className="text-[10px] text-destructive italic font-medium">
                                    Déchéance &ge;15h
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'mention_obtenue',
                header: 'Distinction / Mention',
                cell: ({ row }) => {
                    const l = row.original;
                    if (l.mention_bloquee) {
                        return (
                            <Badge variant="destructive" className="text-xs">
                                Refusée ({l.motif_blocage_mention === 'absences_excessives' ? '&ge;15h abs.' : 'Note élim.'})
                            </Badge>
                        );
                    }
                    if (l.mention_obtenue === 'felicitations') {
                        return <Badge className="bg-emerald-600 text-white text-xs">Félicitations</Badge>;
                    }
                    if (l.mention_obtenue === 'encouragements') {
                        return <Badge className="bg-blue-600 text-white text-xs">Encouragements</Badge>;
                    }
                    if (l.mention_obtenue === 'tableau_honneur') {
                        return <Badge className="bg-amber-600 text-white text-xs">Tableau d'Honneur</Badge>;
                    }
                    return <span className="text-xs text-muted-foreground">-</span>;
                },
            },
            {
                accessorKey: 'statut_propose',
                header: 'Prop. Auto',
                cell: ({ row }) => {
                    if (typeSession !== 'annuel') return <span className="text-xs text-muted-foreground">-</span>;
                    const st = row.original.statut_propose;
                    return (
                        <span className={`text-xs font-medium ${st === 'promu' ? 'text-emerald-600' : st === 'redouble' ? 'text-amber-600' : 'text-destructive'}`}>
                            {st === 'promu' ? 'Promu' : st === 'redouble' ? 'Redouble' : 'Exclu'}
                        </span>
                    );
                },
            },
            {
                accessorKey: 'statut_retenu',
                header: 'Décision Conseil',
                cell: ({ row }) => {
                    const l = row.original;
                    if (typeSession !== 'annuel') {
                        return (
                            <span className="text-xs text-muted-foreground italic">
                                {l.decision_conseil || 'Délibéré'}
                            </span>
                        );
                    }
                    return (
                        <Select
                            value={l.statut_retenu}
                            onValueChange={(val) => handleLineChange(l.inscription_id, 'statut_retenu', val)}
                            disabled={!canManage || l.est_valide}
                        >
                            <SelectTrigger className="h-8 w-32.5 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="promu" className="text-emerald-600 font-medium">
                                    Promu
                                </SelectItem>
                                <SelectItem value="redouble" className="text-amber-600 font-medium">
                                    Redouble
                                </SelectItem>
                                <SelectItem value="exclu" className="text-destructive font-medium">
                                    Exclu
                                </SelectItem>
                                <SelectItem value="en_attente" className="text-muted-foreground font-medium">
                                    En attente
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    );
                },
            },
            {
                accessorKey: 'appreciation_globale',
                header: 'Appréciation Générale du Conseil',
                cell: ({ row }) => {
                    const l = row.original;
                    return (
                        <Input
                            value={l.appreciation_globale}
                            onChange={(e) => handleLineChange(l.inscription_id, 'appreciation_globale', e.target.value)}
                            disabled={!canManage || l.est_valide}
                            className="h-8 text-xs min-w-60"
                            placeholder="Avis motivé du conseil de classe..."
                        />
                    );
                },
            },
            {
                accessorKey: 'est_valide',
                header: 'État',
                cell: ({ row }) => {
                    const l = row.original;
                    return l.est_valide ? (
                        <Badge variant="outline" className="text-emerald-700 bg-emerald-50 border-emerald-300 gap-1 text-[11px]">
                            <ShieldCheck className="size-3" />
                            Validé
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="text-amber-700 bg-amber-50 border-amber-300 gap-1 text-[11px]">
                            <Clock className="size-3" />
                            Provisoire
                        </Badge>
                    );
                },
            },
        ],
        [typeSession, canManage, lignesLocales]
    );

    const stats = deliberationData?.statistiques_classe ?? {};
    const totalEleves = lignesLocales.length;
    const totalAdmis = lignesLocales.filter((e) => e.moyenne_obtenue >= 10.0).length;
    const tauxReussite = totalEleves > 0 ? Math.round((totalAdmis / totalEleves) * 100) : 0;

    return (
        <>
            <Head title="Délibérations du Conseil de Classe" />

            {/* Conteneur principal responsive sans débordement horizontal */}
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 min-w-0 w-full">
                {/* En-tête de page */}
                <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div className="min-w-0">
                        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground flex items-start sm:items-center gap-2.5">
                            <Award className="size-6 shrink-0 text-amber-600 mt-0.5 sm:mt-0" />
                            <span>Délibérations & Conseil de Classe</span>
                        </h1>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            Attribution des mentions, procès-verbal officiel et validation humaine des passages de classe (Gesco 15.2 & 2.6)
                        </p>
                    </div>

                    {/* Actions d'impression et sauvegarde */}
                    <div className="flex flex-wrap items-center gap-2">
                        {selectedClasseId && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1.5"
                                asChild
                            >
                                <a
                                    href={`/administration/deliberations/pv-pdf?classe_id=${selectedClasseId}&annee_id=${currentAnneeId}${typeSession === 'trimestre' && selectedPeriodeId ? `&periode_id=${selectedPeriodeId}` : ''}`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer className="size-4" />
                                    Imprimer le PV Officiel
                                </a>
                            </Button>
                        )}

                        {canManage && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1.5"
                                onClick={handleSaveDeliberations}
                                disabled={isSaving || totalEleves === 0}
                            >
                                <Save className="size-4" />
                                {isSaving ? 'Enregistrement...' : 'Sauvegarder le vote'}
                            </Button>
                        )}

                        {canValider && typeSession === 'annuel' && (
                            <Button
                                size="sm"
                                className="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                                onClick={() => setIsValidationModalOpen(true)}
                                disabled={isValidating || totalEleves === 0}
                            >
                                <UserCheck className="size-4" />
                                Valider la Promotion (Définitif)
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filtres de sélection : Établissement, Classe, Session, Trimestre */}
                <Card>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 items-end">
                            {/* Établissement */}
                            {etablissements.length > 1 && (
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-muted-foreground">Établissement</label>
                                    <Select
                                        value={currentEtablissement?.id ? currentEtablissement.id.toString() : undefined}
                                        onValueChange={(val) => handleNavigation({ etablissement_id: val })}
                                    >
                                        <SelectTrigger className="h-9">
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

                            {/* Classe */}
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold text-muted-foreground">Classe à délibérer</label>
                                <Select
                                    value={selectedClasseId ? selectedClasseId.toString() : undefined}
                                    onValueChange={(val) => handleNavigation({ classe_id: val })}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Choisir une classe" />
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

                            {/* Type de session (Trimestre vs Annuel) */}
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold text-muted-foreground">Portée de la session</label>
                                <Tabs
                                    value={typeSession}
                                    onValueChange={(val: string) => handleNavigation({ type: val as 'trimestre' | 'annuel' })}
                                    className="w-full"
                                >
                                    <TabsList className="grid grid-cols-2 w-full h-9">
                                        <TabsTrigger value="trimestre" className="text-xs">
                                            Trimestriel
                                        </TabsTrigger>
                                        <TabsTrigger value="annuel" className="text-xs">
                                            Bilan Annuel
                                        </TabsTrigger>
                                    </TabsList>
                                </Tabs>
                            </div>

                            {/* Période / Trimestre */}
                            {typeSession === 'trimestre' && (
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-muted-foreground">Période d'évaluation</label>
                                    <Select
                                        value={selectedPeriodeId ? selectedPeriodeId.toString() : undefined}
                                        onValueChange={(val) => handleNavigation({ periode_id: val })}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Choisir un trimestre" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {periodes.map((p) => (
                                                <SelectItem key={p.id} value={p.id.toString()}>
                                                    {p.nom}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Synthèse Chiffrée de la Classe */}
                {deliberationData && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                        <Card className="p-3.5 min-w-0">
                            <span className="text-xs font-medium text-muted-foreground">Effectif Délibéré</span>
                            <div className="text-xl font-bold text-foreground mt-0.5 tabular-nums">{totalEleves} élèves</div>
                            <span className="text-[11px] text-muted-foreground truncate block">
                                Prof. Principal : {deliberationData.classe?.professeur_principal ?? 'Non assigné'}
                            </span>
                        </Card>

                        <Card className="p-3.5 min-w-0">
                            <span className="text-xs font-medium text-muted-foreground">Admis (&ge;10/20)</span>
                            <div className="text-xl font-bold text-emerald-600 mt-0.5 tabular-nums">{totalAdmis} élèves</div>
                            <span className="text-[11px] text-muted-foreground">{tauxReussite}% de réussite</span>
                        </Card>

                        <Card className="p-3.5 min-w-0">
                            <span className="text-xs font-medium text-muted-foreground">Moyenne de Classe</span>
                            <div className="text-xl font-bold text-foreground mt-0.5 tabular-nums">
                                {formatMoyenne(stats.moyenne_classe)} / 20
                            </div>
                            <span className="text-[11px] text-muted-foreground">Moyenne générale calculée</span>
                        </Card>

                        <Card className="p-3.5 min-w-0">
                            <span className="text-xs font-medium text-muted-foreground">Plus Forte Moyenne</span>
                            <div className="text-xl font-bold text-blue-600 mt-0.5 tabular-nums">
                                {formatMoyenne(stats.moyenne_max)} / 20
                            </div>
                            <span className="text-[11px] text-muted-foreground">Major de promotion</span>
                        </Card>

                        <Card className="p-3.5 min-w-0">
                            <span className="text-xs font-medium text-muted-foreground">Plus Faible Moyenne</span>
                            <div className="text-xl font-bold text-destructive mt-0.5 tabular-nums">
                                {formatMoyenne(stats.moyenne_min)} / 20
                            </div>
                            <span className="text-[11px] text-muted-foreground">Seuil minimum classe</span>
                        </Card>
                    </div>
                )}

                {/* Tableau Principal des Délibérations TanStack */}
                <Card className="overflow-hidden min-w-0">
                    <CardHeader className="pb-3 px-4 sm:px-6">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <CardTitle className="text-base flex items-center gap-2">
                                    <BookOpen className="size-4 shrink-0 text-primary" />
                                    Grille de Délibération & Décisions du Conseil
                                </CardTitle>
                                <CardDescription>
                                    {deliberationData?.classe ? `${deliberationData.classe.nom} • ${typeSession === 'annuel' ? 'Bilan Annuel Définitif' : (deliberationData.periode?.nom ?? 'Trimestre')}` : 'Sélectionnez une classe pour afficher les délibérations'}
                                </CardDescription>
                            </div>

                            {deliberationData?.toutes_validees && (
                                <Badge className="bg-emerald-600 text-white gap-1.5 w-fit shrink-0">
                                    <Check className="size-3.5" />
                                    Promotion Annuelle Validée
                                </Badge>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="px-3 sm:px-6">
                        {deliberationData ? (
                            lignesLocales.length > 0 ? (
                                <DataTable
                                    columns={columns}
                                    data={lignesLocales}
                                    searchPlaceholder="Rechercher par nom d'élève ou matricule..."
                                    defaultPageSize={20}
                                />
                            ) : (
                                <div className="flex flex-col items-center justify-center p-12 text-center text-muted-foreground">
                                    <AlertCircle className="size-10 mb-2 stroke-1 text-amber-500" />
                                    <p className="font-semibold text-sm text-foreground">Aucune note ou élève évalué pour cette classe</p>
                                    <p className="text-xs mt-1 max-w-md">
                                        Les délibérations ne peuvent être calculées car aucun bulletin ou note n'a encore été enregistré pour cette classe et cette session.
                                    </p>
                                </div>
                            )
                        ) : (
                            <div className="flex flex-col items-center justify-center p-12 text-center text-muted-foreground">
                                <HelpCircle className="size-10 mb-2 stroke-1 text-muted-foreground/60" />
                                <p className="font-medium text-sm">Aucune classe sélectionnée</p>
                                <p className="text-xs">Veuillez sélectionner une classe ci-dessus pour charger la grille de délibération.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Modale de Confirmation de Validation de Promotion (Module 2.6 Étape B) */}
            <Dialog open={isValidationModalOpen} onOpenChange={setIsValidationModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-foreground">
                            <ShieldCheck className="size-5 text-emerald-600" />
                            Validation Officielle de la Promotion de Fin d'Année
                        </DialogTitle>
                        <DialogDescription>
                            Conformément au <strong>Cahier des Charges Gesco V1.1 (Module 2.6 & 15.2)</strong>, la validation de la promotion est un acte officiel sous la responsabilité du Chef d'Établissement.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 py-3 text-sm">
                        <div className="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/40 p-3 text-amber-900 dark:text-amber-200 text-xs">
                            <p className="font-semibold mb-1">Attention : Action définitive</p>
                            <p>
                                Cette validation va sceller les statuts de fin d'année (Promu, Redouble, Exclu) pour les <strong>{totalEleves} élèves</strong> de la classe <strong>{deliberationData?.classe?.nom ?? ''}</strong>. Les décisions retenues écraseront toute proposition automatique antérieure.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 min-[380px]:grid-cols-3 gap-2 text-center text-xs">
                            <div className="p-2 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/50 dark:border-emerald-800 dark:text-emerald-200">
                                <span className="block font-bold text-base">{lignesLocales.filter((l) => l.statut_retenu === 'promu').length}</span>
                                <span>Promus</span>
                            </div>
                            <div className="p-2 rounded bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-950/50 dark:border-amber-800 dark:text-amber-200">
                                <span className="block font-bold text-base">{lignesLocales.filter((l) => l.statut_retenu === 'redouble').length}</span>
                                <span>Redoublants</span>
                            </div>
                            <div className="p-2 rounded bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/50 dark:border-rose-800 dark:text-rose-200">
                                <span className="block font-bold text-base">{lignesLocales.filter((l) => l.statut_retenu === 'exclu').length}</span>
                                <span>Exclus</span>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsValidationModalOpen(false)}>
                            Annuler
                        </Button>
                        <Button
                            className="bg-emerald-600 hover:bg-emerald-700 text-white"
                            onClick={handleValiderPromotions}
                            disabled={isValidating}
                        >
                            {isValidating ? 'Validation en cours...' : 'Confirmer et Valider la Promotion'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Configuration du layout persistant et des fils d'Ariane pour la page Délibérations.
 */
DeliberationsIndex.layout = {
    breadcrumbs,
};


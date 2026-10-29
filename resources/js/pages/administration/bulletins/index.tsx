import { Head, router, usePage } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    Award,
    BookOpen,
    Calendar,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Filter,
    Layers,
    Printer,
    RefreshCw,
    School,
    SlidersHorizontal,
    TrendingUp,
    Users,
    X,
} from 'lucide-react';
import React, { useMemo, useState, useEffect } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/contexts/language-context';
import { formatAnneeLabel } from '@/lib/utils';
import type {
    DonneesClasseBulletin,
    EleveBulletin,
    ModeBulletin,
} from '@/types/bulletin';
import type { CoteAPC } from '@/types/note';

/**
 * Propriétés reçues par la page Bulletins Scolaires via Inertia.
 */
interface BulletinsPageProps {
    annees: Array<{ id: number; nom: string; code?: string; session?: string; is_active?: boolean; is_current?: boolean }>;
    anneeActiveId: number;
    etablissements?: Array<{ id: number; nom: string; code: string; ordre_enseignement?: string }>;
    periodes: Array<{
        id: number;
        nom: string;
        code: string;
        evaluations?: Array<{ id: number; nom: string; code: string }>;
    }>;
    classes: Array<{ id: number; nom: string; code: string; niveau: { nom: string } }>;
    donneesClasse: DonneesClasseBulletin | null;
    filters: {
        type_bulletin?: 'sequentiel' | 'trimestriel' | 'annuel';
        annee_id?: number | string;
        etablissement_id?: number | string;
        classe_id?: number | string;
        periode_id?: number | string;
        evaluation_id?: number | string;
        mode_bulletin?: ModeBulletin;
    };
}

/**
 * Page principale du Module 10 : Centre de Calcul et d'Édition des Bulletins Scolaires.
 *
 * Prend en charge les 3 vagues de bulletins officiels :
 * 1. Séquentiel (par séquence / évaluation)
 * 2. Trimestriel (bilan trimestriel)
 * 3. Annuel (bilan annuel et décision du conseil de classe)
 *
 * Conforme au Cahier des Charges Gesco V1.1 (Section 10) et aux exigences officielles :
 * - Double format configurable : Avec Compétences (APC Cameroun) vs Sans Compétences (Standard).
 * - TanStack Table pour l'exploration fluide des résultats de classe.
 * - Prévisualisation intégrée haute fidélité et export PDF individuel ou de classe entière.
 */
export default function BulletinsIndex({
    annees,
    anneeActiveId,
    etablissements = [],
    periodes,
    classes,
    donneesClasse,
    filters,
}: BulletinsPageProps) {
    const { t } = useTranslation();
    const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props;

    // Filtres d'état
    const [selectedEtab, setSelectedEtab] = useState<string>(
        String(filters.etablissement_id || '')
    );
    const [typeBulletin, setTypeBulletin] = useState<'sequentiel' | 'trimestriel' | 'annuel'>(
        filters.type_bulletin || 'sequentiel'
    );
    const [selectedAnnee, setSelectedAnnee] = useState<string>(
        String(filters.annee_id || anneeActiveId || (annees[0]?.id ?? ''))
    );

    // Année scolaire activement sélectionnée (pour affichage concis dans le déclencheur Select)
    const selectedAnneeObj = useMemo(() => {
        return annees.find((a) => String(a.id) === selectedAnnee);
    }, [annees, selectedAnnee]);

    const [selectedClasse, setSelectedClasse] = useState<string>(
        String(filters.classe_id || '')
    );
    const [selectedPeriode, setSelectedPeriode] = useState<string>(
        String(filters.periode_id || '')
    );
    const [selectedEvaluation, setSelectedEvaluation] = useState<string>(
        String(filters.evaluation_id || '')
    );
    // Le format du bulletin est déterminé strictement par l'établissement de la classe sélectionnée
    const modeBulletin: ModeBulletin = filters.mode_bulletin || 'sans_competences';

    // Liste plate des évaluations de l'année sélectionnée
    const toutesLesEvaluations = useMemo(() => {
        return periodes.flatMap((p) => p.evaluations || []);
    }, [periodes]);


    // État pour la modale de saisie de discipline
    const [disciplineStudent, setDisciplineStudent] = useState<EleveBulletin | null>(null);
    const [disciplineForm, setDisciplineForm] = useState({
        absences_justifiees: 0,
        absences_non_justifiees: 0,
        retards: 0,
        consignes: 0,
        avertissements_conduite: 0,
        blames_conduite: 0,
        exclusions_jours: 0,
        decision_conseil: '',
    });
    const [isSavingDiscipline, setIsSavingDiscipline] = useState(false);

    // Notifications Flash
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, {
                position: 'top-right',
                className: 'bg-emerald-600 text-white border-emerald-700',
            });
        }
        if (flash?.error) {
            toast.error(flash.error, {
                position: 'top-right',
            });
        }
    }, [flash]);

    /**
     * Recharge la page avec les nouveaux filtres sélectionnés (le mode de bulletin étant automatiquement déduit de l'établissement).
     */
    const handleFilterChange = (
        newType?: 'sequentiel' | 'trimestriel' | 'annuel',
        newClasseId?: string,
        newPeriodeId?: string,
        newEvaluationId?: string
    ) => {
        const type = newType !== undefined ? newType : typeBulletin;
        const cId = newClasseId !== undefined ? newClasseId : selectedClasse;
        const pId = newPeriodeId !== undefined ? newPeriodeId : selectedPeriode;
        const eId = newEvaluationId !== undefined ? newEvaluationId : selectedEvaluation;

        const params: Record<string, any> = {
            type_bulletin: type,
            annee_id: selectedAnnee,
            etablissement_id: selectedEtab,
            classe_id: cId,
        };

        if (type === 'sequentiel' && eId) {
            params.evaluation_id = eId;
        } else if (type === 'trimestriel' && pId) {
            params.periode_id = pId;
        }

        const isReady =
            cId &&
            (type === 'annuel' ||
                (type === 'sequentiel' && eId) ||
                (type === 'trimestriel' && pId));

        if (isReady) {
            router.get('/administration/bulletins', params, {
                preserveState: true,
                preserveScroll: true,
            });
        }
    };

    /**
     * Ouvre la modale de saisie disciplinaire pour l'élève sélectionné.
     */
    const openDisciplineModal = (eleve: EleveBulletin) => {
        setDisciplineStudent(eleve);
        setDisciplineForm({
            absences_justifiees: eleve.discipline?.absences_justifiees ?? 0,
            absences_non_justifiees: eleve.discipline?.absences_non_justifiees ?? 0,
            retards: eleve.discipline?.retards ?? 0,
            consignes: eleve.discipline?.consignes ?? 0,
            avertissements_conduite: eleve.discipline?.avertissements ?? 0,
            blames_conduite: eleve.discipline?.blames ?? 0,
            exclusions_jours: eleve.discipline?.exclusions ?? 0,
            decision_conseil: eleve.discipline?.decision_conseil || '',
        });
    };

    /**
     * Enregistre les données d'assiduité et de conduite pour le bulletin.
     */
    const handleSaveDiscipline = () => {
        if (!disciplineStudent) return;
        const targetPeriodeId = selectedPeriode || periodes[0]?.id;
        if (!targetPeriodeId) return;

        setIsSavingDiscipline(true);
        router.post(
            '/administration/bulletins/discipline',
            {
                inscription_id: disciplineStudent.inscription_id,
                periode_id: Number(targetPeriodeId),
                ...disciplineForm,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSavingDiscipline(false);
                    setDisciplineStudent(null);
                    toast.success('Données disciplinaires enregistrées avec succès !', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-emerald-700',
                    });
                },
                onError: () => {
                    setIsSavingDiscipline(false);
                    toast.error("Erreur lors de l'enregistrement de la discipline.", {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Construit l'URL de prévisualisation HTML pour un élève.
     */
    const getPreviewUrl = (student: EleveBulletin) => {
        const query = new URLSearchParams({
            type_bulletin: typeBulletin,
            mode: modeBulletin,
            annee_id: selectedAnnee,
        });
        if (typeBulletin === 'sequentiel' && selectedEvaluation) {
            query.set('evaluation_id', selectedEvaluation);
        } else if (typeBulletin === 'trimestriel' && selectedPeriode) {
            query.set('periode_id', selectedPeriode);
        }
        return `/administration/bulletins/previsualiser/${student.inscription_id}?${query.toString()}`;
    };

    /**
     * Construit l'URL de téléchargement PDF pour un élève.
     */
    const getPdfUrl = (student: EleveBulletin) => {
        const query = new URLSearchParams({
            type_bulletin: typeBulletin,
            mode: modeBulletin,
            annee_id: selectedAnnee,
        });
        if (typeBulletin === 'sequentiel' && selectedEvaluation) {
            query.set('evaluation_id', selectedEvaluation);
        } else if (typeBulletin === 'trimestriel' && selectedPeriode) {
            query.set('periode_id', selectedPeriode);
        }
        return `/administration/bulletins/pdf/${student.inscription_id}?${query.toString()}`;
    };

    /**
     * Construit l'URL de téléchargement groupé du cahier de classe complet.
     */
    const getClassPdfUrl = () => {
        if (!selectedClasse) return null;
        const query = new URLSearchParams({
            type_bulletin: typeBulletin,
            mode: modeBulletin,
            annee_id: selectedAnnee,
        });
        if (typeBulletin === 'sequentiel' && selectedEvaluation) {
            query.set('evaluation_id', selectedEvaluation);
        } else if (typeBulletin === 'trimestriel' && selectedPeriode) {
            query.set('periode_id', selectedPeriode);
        }
        return `/administration/bulletins/classe-pdf/${selectedClasse}?${query.toString()}`;
    };

    // Définition des colonnes du tableau TanStack Table
    const columns: ColumnDef<EleveBulletin>[] = useMemo(() => {
        return [
            {
                accessorKey: 'rang_general',
                header: t('bulletins.col_rang'),
                cell: ({ row }) => {
                    const rang = row.original.rang_general;
                    const isFirst = rang === '1er' || rang === '1er ex';
                    const isNC = rang === 'NC';
                    const motifNC = row.original.motif_non_classe;
                    return (
                        <span
                            title={isNC ? (motifNC || 'Non classé (quota de coefficients insuffisant)') : undefined}
                            className={`font-bold px-2 py-1 rounded text-xs inline-flex items-center justify-center ${
                                isNC
                                    ? 'bg-rose-100 text-rose-800 border border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800 cursor-help'
                                    : isFirst
                                    ? 'bg-amber-100 text-amber-900 border border-amber-300 dark:bg-amber-950 dark:text-amber-200'
                                    : 'bg-muted text-foreground'
                            }`}
                        >
                            {rang}
                        </span>
                    );
                },
            },
            {
                accessorKey: 'nom_complet',
                header: t('bulletins.col_eleve'),
                cell: ({ row }) => (
                    <div>
                        <div className="font-semibold text-foreground">
                            {row.original.nom_complet}
                        </div>
                        <div className="text-xs text-muted-foreground font-mono">
                            {row.original.matricule}
                            {row.original.is_redoublant && (
                                <span className="ml-1.5 text-amber-600 font-normal">
                                    (Redoublant)
                                </span>
                            )}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'total_points',
                header: t('bulletins.col_total'),
                cell: ({ row }) => (
                    <div className="text-xs font-medium">
                        <span className="font-bold text-foreground">
                            {row.original.total_points.toFixed(2)}
                        </span>
                        <span className="text-muted-foreground ml-1">
                            / Coef {row.original.total_coefficients.toFixed(1)}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'moyenne_generale',
                header: typeBulletin === 'annuel' ? 'Moy. Annuelle' : t('bulletins.col_moyenne'),
                cell: ({ row }) => {
                    const moy = row.original.moyenne_generale;
                    const aElim = row.original.a_note_eliminatoire;
                    const isSuccess = moy >= 10.0;

                    return (
                        <div className="flex items-center gap-1.5">
                            <span
                                className={`font-bold text-sm ${
                                    isSuccess ? 'text-emerald-600' : 'text-rose-600'
                                }`}
                            >
                                {moy.toFixed(2)}
                            </span>
                            {aElim && (
                                <Badge
                                    variant="destructive"
                                    className="text-[9px] px-1 py-0 h-4"
                                    title="Note éliminatoire obtenue dans une matière"
                                >
                                    Élim.
                                </Badge>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'cote_globale',
                header: t('bulletins.col_cote'),
                cell: ({ row }) => {
                    const cote = row.original.cote_globale;
                    const styles: Record<CoteAPC, string> = {
                        CTBA: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300',
                        CBA: 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950 dark:text-blue-300',
                        CMA: 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950 dark:text-amber-300',
                        CNA: 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-300',
                    };

                    return (
                        <Badge variant="outline" className={`font-bold text-xs ${styles[cote]}`}>
                            {cote}
                        </Badge>
                    );
                },
            },
            {
                accessorKey: 'distinction',
                header: typeBulletin === 'annuel' ? 'Décision Conseil' : t('bulletins.col_mention'),
                cell: ({ row }) => {
                    const eleve = row.original;
                    if (typeBulletin === 'annuel') {
                        const decision = (eleve as any).decision_finale || 'En délibération';
                        const isAdmis = decision.toLowerCase().includes('admis');
                        return (
                            <Badge
                                variant="outline"
                                className={`text-xs font-semibold ${
                                    isAdmis
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-rose-50 text-rose-700 border-rose-300 dark:bg-rose-950 dark:text-rose-300'
                                }`}
                            >
                                {decision}
                            </Badge>
                        );
                    }

                    const distinction = eleve.distinction;
                    const aElim = eleve.a_note_eliminatoire;

                    return (
                        <span
                            className={`text-xs font-medium ${
                                aElim
                                    ? 'text-rose-600 italic'
                                    : eleve.moyenne_generale >= 12
                                    ? 'text-emerald-700 font-semibold'
                                    : 'text-muted-foreground'
                            }`}
                        >
                            {distinction}
                        </span>
                    );
                },
            },
            {
                id: 'actions',
                header: t('bulletins.col_actions'),
                cell: ({ row }) => {
                    const eleve = row.original;
                    const pdfUrl = getPdfUrl(eleve);

                    return (
                        <div className="flex items-center gap-1.5">
                            {/* Bouton Discipline */}
                            {typeBulletin === 'trimestriel' && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => openDisciplineModal(eleve)}
                                    className="h-8 px-2 text-xs text-muted-foreground hover:text-foreground"
                                    title={t('bulletins.btn_discipline')}
                                >
                                    <Users className="h-3.5 w-3.5" />
                                </Button>
                            )}

                            {/* Bouton Prévisualiser dans un nouvel onglet */}
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="h-8 px-2.5 text-xs gap-1 hover:bg-sky-50 dark:hover:bg-sky-950/40 hover:text-sky-600"
                            >
                                <a
                                    href={getPreviewUrl(eleve)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Ouvrir l'aperçu officiel sur toute la page dans un nouvel onglet"
                                >
                                    <Eye className="h-3.5 w-3.5" />
                                    {t('bulletins.btn_preview')}
                                </a>
                            </Button>

                            {/* Bouton Télécharger PDF */}
                            <Button
                                size="sm"
                                asChild
                                className="h-8 px-2.5 text-xs gap-1 bg-sky-600 hover:bg-sky-700 text-white"
                            >
                                <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                                    <Download className="h-3.5 w-3.5" />
                                    PDF
                                </a>
                            </Button>
                        </div>
                    );
                },
            },
        ];
    }, [typeBulletin, selectedPeriode, selectedEvaluation, selectedAnnee, modeBulletin, t]);

    const urlBundlePdf = getClassPdfUrl();

    return (
        <>
            <Head title={t('bulletins.title')} />

            {/* Conteneur principal responsive sans débordement horizontal */}
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6 lg:p-8 min-w-0 w-full">
                {/* En-tête de page */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                            {t('bulletins.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            {t('bulletins.subtitle')}
                        </p>
                    </div>

                    {/* Action de téléchargement groupé */}
                    {urlBundlePdf && donneesClasse && (
                        <Button
                            asChild
                            className="w-full sm:w-auto gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm shrink-0"
                        >
                            <a href={urlBundlePdf} target="_blank" rel="noopener noreferrer">
                                <Download className="h-4 w-4 shrink-0" />
                                <span className="truncate">{t('bulletins.btn_download_class')}</span>
                            </a>
                        </Button>
                    )}
                </div>

                {/* Sélecteur Maître de Campus (Multi-Établissements) */}
                {etablissements && etablissements.length > 1 && (
                    <div className="flex items-center gap-2 overflow-x-auto pb-1">
                        <span className="text-xs font-semibold text-muted-foreground shrink-0 flex items-center gap-1.5 mr-1">
                            <School className="size-4 text-primary" />
                            Campus :
                        </span>
                        {etablissements.map((etab) => {
                            const isSelected = selectedEtab === String(etab.id) || (!selectedEtab && etab.id === etablissements[0].id);
                            return (
                                <Button
                                    key={etab.id}
                                    type="button"
                                    variant={isSelected ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => {
                                        setSelectedEtab(String(etab.id));
                                        setSelectedClasse('');
                                        setSelectedPeriode('');
                                        setSelectedEvaluation('');
                                        router.get(
                                            '/administration/bulletins',
                                            {
                                                type_bulletin: typeBulletin,
                                                annee_id: selectedAnnee,
                                                etablissement_id: String(etab.id),
                                            },
                                            { preserveState: false }
                                        );
                                    }}
                                    className="h-8 shrink-0 text-xs font-medium rounded-full cursor-pointer gap-1.5"
                                >
                                    <School className="size-3.5 shrink-0" />
                                    {etab.nom}
                                </Button>
                            );
                        })}
                    </div>
                )}

                {/* Filtres : Choix de la Vague (Séquentiel, Trimestriel, Annuel) & Paramètres */}
                <Card className="border-border/60 shadow-sm">
                    <CardContent className="p-3.5 sm:p-4 flex flex-col gap-4">
                        {/* 1. Commutateur de Vague de Bulletin (empilé sur mobile, 3 colonnes dès sm) */}
                        <div className="flex flex-col lg:flex-row lg:items-center justify-between pb-3 border-b border-border/50 gap-2.5">
                            <div className="flex items-center gap-2">
                                <FileText className="h-4 w-4 shrink-0 text-primary" />
                                <span className="text-xs font-bold text-foreground uppercase tracking-wider">
                                    Vague de Bulletin :
                                </span>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-3 w-full lg:w-auto rounded-lg border border-border/80 p-1 bg-muted/40 gap-1">
                                <Button
                                    type="button"
                                    variant={typeBulletin === 'sequentiel' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => {
                                        setTypeBulletin('sequentiel');
                                        const targetEval = selectedEvaluation || String(toutesLesEvaluations[0]?.id || '');
                                        if (!selectedEvaluation && targetEval) setSelectedEvaluation(targetEval);
                                        handleFilterChange('sequentiel', selectedClasse, selectedPeriode, targetEval);
                                    }}
                                    className={`text-xs h-8 px-3 gap-1.5 justify-center ${
                                        typeBulletin === 'sequentiel'
                                            ? 'bg-primary text-primary-foreground shadow-sm font-semibold'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    <Clock className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">1. Séquentiel (Évaluation)</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant={typeBulletin === 'trimestriel' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => {
                                        setTypeBulletin('trimestriel');
                                        const targetPer = selectedPeriode || String(periodes[0]?.id || '');
                                        if (!selectedPeriode && targetPer) setSelectedPeriode(targetPer);
                                        handleFilterChange('trimestriel', selectedClasse, targetPer, selectedEvaluation);
                                    }}
                                    className={`text-xs h-8 px-3 gap-1.5 justify-center ${
                                        typeBulletin === 'trimestriel'
                                            ? 'bg-primary text-primary-foreground shadow-sm font-semibold'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    <Calendar className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">2. Trimestriel (Bilan)</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant={typeBulletin === 'annuel' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => {
                                        setTypeBulletin('annuel');
                                        handleFilterChange('annuel', selectedClasse, selectedPeriode, selectedEvaluation);
                                    }}
                                    className={`text-xs h-8 px-3 gap-1.5 justify-center ${
                                        typeBulletin === 'annuel'
                                            ? 'bg-primary text-primary-foreground shadow-sm font-semibold'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    <Award className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">3. Annuel (Fin d'Année)</span>
                                </Button>
                            </div>
                        </div>

                        {/* 2. Filtres Sélecteurs */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            {/* Année Scolaire */}
                            <div className="min-w-0">
                                <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                    Année Scolaire
                                </label>
                                <Select
                                    value={selectedAnnee}
                                    onValueChange={(val) => {
                                        setSelectedAnnee(val);
                                        setSelectedClasse('');
                                        setSelectedPeriode('');
                                        setSelectedEvaluation('');
                                    }}
                                >
                                    <SelectTrigger className="h-9 w-full min-w-0">
                                        <div className="flex items-center gap-2 truncate">
                                            {(selectedAnneeObj?.is_active || selectedAnneeObj?.is_current) && (
                                                <span className="relative flex h-2 w-2 shrink-0" title="Année scolaire active">
                                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                    <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 shadow-xs shadow-emerald-500/50"></span>
                                                </span>
                                            )}
                                            <SelectValue placeholder="Année">
                                                {selectedAnneeObj
                                                    ? formatAnneeLabel(selectedAnneeObj.nom, selectedAnneeObj.code, selectedAnneeObj.session)
                                                    : undefined}
                                            </SelectValue>
                                        </div>
                                    </SelectTrigger>
                                    <SelectContent>
                                        {annees.map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>
                                                <div className="flex items-center gap-2">
                                                    {(a.is_active || a.is_current) && (
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

                            {/* Classe */}
                            <div className="min-w-0">
                                <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                    {t('bulletins.select_classe')}
                                </label>
                                <Select
                                    value={selectedClasse}
                                    onValueChange={(val) => {
                                        setSelectedClasse(val);
                                        const targetEval = (typeBulletin === 'sequentiel' && !selectedEvaluation)
                                            ? String(toutesLesEvaluations[0]?.id || '')
                                            : selectedEvaluation;
                                        const targetPer = (typeBulletin === 'trimestriel' && !selectedPeriode)
                                            ? String(periodes[0]?.id || '')
                                            : selectedPeriode;
                                        if (targetEval && !selectedEvaluation) setSelectedEvaluation(targetEval);
                                        if (targetPer && !selectedPeriode) setSelectedPeriode(targetPer);
                                        handleFilterChange(typeBulletin, val, targetPer, targetEval);
                                    }}
                                >
                                    <SelectTrigger className="h-9 w-full min-w-0">
                                        <SelectValue placeholder="Choisir une classe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classes.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.nom} ({c.niveau.nom})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Évaluation OU Trimestre OU Année selon la vague */}
                            {typeBulletin === 'sequentiel' ? (
                                <div className="min-w-0">
                                    <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                        Séquence / Évaluation
                                    </label>
                                    <Select
                                        value={selectedEvaluation}
                                        onValueChange={(val) => {
                                            setSelectedEvaluation(val);
                                            handleFilterChange(typeBulletin, selectedClasse, selectedPeriode, val);
                                        }}
                                    >
                                        <SelectTrigger className="h-9 w-full min-w-0">
                                            <SelectValue placeholder="Choisir une séquence" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {toutesLesEvaluations.map((ev) => (
                                                <SelectItem key={ev.id} value={String(ev.id)}>
                                                    {ev.nom} ({ev.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : typeBulletin === 'trimestriel' ? (
                                <div className="min-w-0">
                                    <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                        {t('bulletins.select_periode')}
                                    </label>
                                    <Select
                                        value={selectedPeriode}
                                        onValueChange={(val) => {
                                            setSelectedPeriode(val);
                                            handleFilterChange(typeBulletin, selectedClasse, val, selectedEvaluation);
                                        }}
                                    >
                                        <SelectTrigger className="h-9 w-full min-w-0">
                                            <SelectValue placeholder="Choisir un trimestre" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {periodes.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.nom}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : (
                                <div className="flex flex-col justify-end min-w-0">
                                    <label className="text-xs font-semibold text-muted-foreground mb-1 block">
                                        Portée du bilan
                                    </label>
                                    <div className="h-9 px-3 rounded-md border border-border/80 bg-muted/20 flex items-center text-xs text-muted-foreground font-medium truncate">
                                        Synthèse globale des 3 trimestres de l'année
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* 3. Format Officiel Appliqué (Strictement déterminé par l'établissement) */}
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-3 border-t border-border/50 gap-2 text-xs">
                            <div className="flex flex-wrap items-center gap-2">
                                <FileText className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                <span className="font-semibold text-foreground">
                                    Format officiel de l'établissement :
                                </span>
                                <Badge variant="outline" className="font-semibold text-xs px-2.5 py-0.5 border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                                    {modeBulletin === 'sans_competences'
                                        ? 'Standard (Sans compétences)'
                                        : 'Approche Par Compétences (APC)'}
                                </Badge>
                            </div>
                            <span className="text-[11px] text-muted-foreground italic">
                                Réglementé par la configuration globale de l'établissement
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* Synthèse du Profil de la classe si chargée */}
                {donneesClasse && (
                    <div className="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3">
                        <Card className="border-border/60">
                            <CardContent className="p-3 flex items-center gap-3">
                                <div className="p-2 shrink-0 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50">
                                    <Users className="h-5 w-5" />
                                </div>
                                <div className="min-w-0">
                                    <div className="text-xs text-muted-foreground font-medium truncate">Effectif total</div>
                                    <div className="text-lg font-bold text-foreground tabular-nums">
                                        {donneesClasse.stats_classe.effectif ?? donneesClasse.stats_classe.effectif_total ?? 0}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-border/60">
                            <CardContent className="p-3 flex items-center gap-3">
                                <div className="p-2 shrink-0 rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/50">
                                    <FileSpreadsheet className="h-5 w-5" />
                                </div>
                                <div className="min-w-0">
                                    <div className="text-xs text-muted-foreground font-medium truncate">
                                        {typeBulletin === 'annuel' ? 'M.G.A. Classe' : t('bulletins.class_average')}
                                    </div>
                                    <div className="text-lg font-bold text-foreground tabular-nums">
                                        {(donneesClasse.stats_classe.moyenne_classe ?? donneesClasse.stats_classe.moyenne_generale ?? 0).toFixed(2)} / 20
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-border/60">
                            <CardContent className="p-3 flex items-center gap-3">
                                <div className="p-2 shrink-0 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50">
                                    <CheckCircle2 className="h-5 w-5" />
                                </div>
                                <div className="min-w-0">
                                    <div className="text-xs text-muted-foreground font-medium truncate">{t('bulletins.pass_rate')}</div>
                                    <div className="text-lg font-bold text-emerald-600 tabular-nums">
                                        {(donneesClasse.stats_classe.taux_reussite ?? 0).toFixed(1)} %
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-border/60">
                            <CardContent className="p-3 flex items-center gap-3">
                                <div className="p-2 shrink-0 rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50">
                                    <TrendingUp className="h-5 w-5" />
                                </div>
                                <div className="min-w-0">
                                    <div className="text-xs text-muted-foreground font-medium truncate">Extrêmes [Min - Max]</div>
                                    <div className="text-sm font-bold text-foreground tabular-nums">
                                        {(donneesClasse.stats_classe.moyenne_min ?? donneesClasse.stats_classe.min_generale ?? 0).toFixed(2)} &mdash; {(donneesClasse.stats_classe.moyenne_max ?? donneesClasse.stats_classe.max_generale ?? 0).toFixed(2)}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Tableau TanStack Table des Bulletins */}
                {donneesClasse ? (
                    <Card className="border-border/60 shadow-sm overflow-hidden min-w-0">
                        <div className="flex items-center justify-between px-4 py-2.5 border-b bg-muted/20 flex-wrap gap-2">
                            <span className="text-xs font-semibold text-muted-foreground">
                                {donneesClasse.eleves.length} élèves classés
                            </span>
                            <div className="flex items-center gap-2 flex-wrap">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs font-semibold text-teal-600 dark:text-teal-400 border-teal-200 dark:border-teal-800 hover:bg-teal-50 dark:hover:bg-teal-950/40 cursor-pointer shadow-xs"
                                    onClick={() => {
                                        const perId = selectedPeriode ? `/${selectedPeriode}` : '';
                                        window.open(`/administration/documents/babillard/${selectedClasse}${perId}`, '_blank');
                                    }}
                                    title="Afficher et imprimer le babillard officiel d'affichage de la classe"
                                >
                                    <Printer className="size-3.5 shrink-0 text-teal-600 dark:text-teal-400" />
                                    <span>Babillard (PDF)</span>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 cursor-pointer shadow-xs"
                                    onClick={() => {
                                        window.open(`/administration/documents/liste-eleves/${selectedClasse}?type=definitive&annee_id=${selectedAnnee}`, '_blank');
                                    }}
                                    title="Télécharger la liste nominative définitive des élèves de la classe"
                                >
                                    <FileText className="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    <span>Liste Élèves (PDF)</span>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs font-semibold text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800 hover:bg-blue-50 dark:hover:bg-blue-950/40 cursor-pointer shadow-xs"
                                    onClick={() => {
                                        window.open(`/administration/documents/fiche-appel/${selectedClasse}?annee_id=${selectedAnnee}`, '_blank');
                                    }}
                                    title="Télécharger la fiche d'émargement et d'appel mensuelle de cette classe"
                                >
                                    <Printer className="size-3.5 shrink-0 text-blue-600 dark:text-blue-400" />
                                    <span>Fiche d'Appel (PDF)</span>
                                </Button>
                            </div>
                        </div>
                        <CardContent className="p-0">
                            <DataTable
                                columns={columns}
                                data={donneesClasse.eleves}
                                searchPlaceholder="Rechercher un élève..."
                                defaultPageSize={50}
                            />
                        </CardContent>
                    </Card>
                ) : (
                    <div className="flex flex-col items-center justify-center p-6 sm:p-12 bg-muted/20 rounded-lg border border-dashed border-border/80 text-center">
                        <FileText className="h-10 w-10 text-muted-foreground/60 mb-2" />
                        <h3 className="text-base font-semibold text-foreground">
                            {t('bulletins.title')}
                        </h3>
                        <p className="text-sm text-muted-foreground max-w-md mt-1">
                            {typeBulletin === 'sequentiel'
                                ? 'Veuillez sélectionner une classe et une évaluation séquentielle pour afficher les résultats.'
                                : typeBulletin === 'annuel'
                                ? 'Veuillez sélectionner une classe pour afficher le bilan annuel de fin d\'année.'
                                : t('bulletins.select_class_prompt')}
                        </p>
                    </div>
                )}
            </div>



            {/* Modale de Saisie Disciplinaire */}
            <Dialog open={!!disciplineStudent} onOpenChange={(open) => !open && setDisciplineStudent(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-base">
                            {t('bulletins.discipline_title')} : {disciplineStudent?.nom_complet}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {t('bulletins.discipline_subtitle')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 py-2">
                        <div>
                            <Label className="text-xs">{t('bulletins.abs_non_justifiees')}</Label>
                            <Input
                                type="number"
                                min={0}
                                className="h-8 text-xs mt-1"
                                value={disciplineForm.absences_non_justifiees}
                                onChange={(e) =>
                                    setDisciplineForm({
                                        ...disciplineForm,
                                        absences_non_justifiees: parseInt(e.target.value) || 0,
                                    })
                                }
                            />
                        </div>

                        <div>
                            <Label className="text-xs">{t('bulletins.abs_justifiees')}</Label>
                            <Input
                                type="number"
                                min={0}
                                className="h-8 text-xs mt-1"
                                value={disciplineForm.absences_justifiees}
                                onChange={(e) =>
                                    setDisciplineForm({
                                        ...disciplineForm,
                                        absences_justifiees: parseInt(e.target.value) || 0,
                                    })
                                }
                            />
                        </div>

                        <div>
                            <Label className="text-xs">{t('bulletins.retards')}</Label>
                            <Input
                                type="number"
                                min={0}
                                className="h-8 text-xs mt-1"
                                value={disciplineForm.retards}
                                onChange={(e) =>
                                    setDisciplineForm({
                                        ...disciplineForm,
                                        retards: parseInt(e.target.value) || 0,
                                    })
                                }
                            />
                        </div>

                        <div>
                            <Label className="text-xs">{t('bulletins.consignes')}</Label>
                            <Input
                                type="number"
                                min={0}
                                className="h-8 text-xs mt-1"
                                value={disciplineForm.consignes}
                                onChange={(e) =>
                                    setDisciplineForm({
                                        ...disciplineForm,
                                        consignes: parseInt(e.target.value) || 0,
                                    })
                                }
                            />
                        </div>

                        <div>
                            <Label className="text-xs">{t('bulletins.avertissements')}</Label>
                            <Input
                                type="number"
                                min={0}
                                className="h-8 text-xs mt-1"
                                value={disciplineForm.avertissements_conduite}
                                onChange={(e) =>
                                    setDisciplineForm({
                                        ...disciplineForm,
                                        avertissements_conduite: parseInt(e.target.value) || 0,
                                    })
                                }
                            />
                        </div>

                        <div>
                            <Label className="text-xs">{t('bulletins.blames')}</Label>
                            <Input
                                type="number"
                                min={0}
                                className="h-8 text-xs mt-1"
                                value={disciplineForm.blames_conduite}
                                onChange={(e) =>
                                    setDisciplineForm({
                                        ...disciplineForm,
                                        blames_conduite: parseInt(e.target.value) || 0,
                                    })
                                }
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <Label className="text-xs">{t('bulletins.exclusions')}</Label>
                            <Input
                                type="number"
                                min={0}
                                className="h-8 text-xs mt-1"
                                value={disciplineForm.exclusions_jours}
                                onChange={(e) =>
                                    setDisciplineForm({
                                        ...disciplineForm,
                                        exclusions_jours: parseInt(e.target.value) || 0,
                                    })
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <Label className="text-xs">{t('bulletins.decision_conseil')}</Label>
                        <Textarea
                            placeholder="Avis ou décision officielle du conseil de classe..."
                            className="text-xs mt-1 min-h-15"
                            value={disciplineForm.decision_conseil}
                            onChange={(e) =>
                                setDisciplineForm({
                                    ...disciplineForm,
                                    decision_conseil: e.target.value,
                                })
                            }
                        />
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setDisciplineStudent(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            size="sm"
                            disabled={isSavingDiscipline}
                            onClick={handleSaveDiscipline}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white"
                        >
                            {isSavingDiscipline ? 'Enregistrement...' : 'Enregistrer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Fil d'Ariane et configuration de mise en page pour la page Bulletins Scolaires.
 */
BulletinsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Bulletins Scolaires',
            href: '/administration/bulletins',
        },
    ],
};

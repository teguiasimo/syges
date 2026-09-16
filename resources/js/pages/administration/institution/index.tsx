import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    School,
    Building2,
    Layers,
    GraduationCap,
    BookOpen,
    Users,
    Plus,
    Edit2,
    Trash2,
    ChevronRight,
    Home,
    MapPin,
    Mail,
    Phone,
    Globe,
    FileText,
    CheckCircle2,
    XCircle,
    ArrowUpDown,
    Check,
    FolderTree,
    Sparkles,
    Shield,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

/**
 * Types TypeScript pour la structure académique.
 */
interface InstitutionItem {
    id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    devise?: string | null;
    devise_en?: string | null;
    email?: string | null;
    telephone_1?: string | null;
    telephone_2?: string | null;
    boite_postale?: string | null;
    site_web?: string | null;
    region?: string | null;
    region_en?: string | null;
    departement?: string | null;
    departement_en?: string | null;
    arrondissement?: string | null;
    arrondissement_en?: string | null;
    ville?: string | null;
    delegation_regionale?: string | null;
    delegation_regionale_en?: string | null;
    delegation_departementale?: string | null;
    delegation_departementale_en?: string | null;
}

interface EtablissementItem {
    id: number;
    institution_id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    statut_juridique: string;
    ordre_enseignement: string;
    decret_creation?: string | null;
    nom_chef_etablissement?: string | null;
    email?: string | null;
    telephone_1?: string | null;
    telephone_2?: string | null;
    adresse?: string | null;
    sections_count?: number;
}

interface SectionItem {
    id: number;
    etablissement_id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    langue: string;
    cycles_count?: number;
}

interface CycleItem {
    id: number;
    section_id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    diplome_fin_cycle?: string | null;
    ordre: number;
    niveaux_count?: number;
}

interface NiveauItem {
    id: number;
    cycle_id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    ordre: number;
    classes_count?: number;
}

interface ClasseItem {
    id: number;
    niveau_id: number;
    code: string;
    nom: string;
    serie?: string | null;
    capacite_max: number;
    is_active: boolean;
}

interface BreadcrumbStep {
    label: string;
    level: 'institution' | 'etablissement' | 'section' | 'cycle' | 'niveau';
    params: Record<string, any>;
}

interface InstitutionPageProps {
    institution: InstitutionItem;
    etablissements: EtablissementItem[];
    sections: SectionItem[];
    cycles: CycleItem[];
    niveaux: NiveauItem[];
    classes: ClasseItem[];
    selectedEtablissement?: EtablissementItem | null;
    selectedSection?: SectionItem | null;
    selectedCycle?: CycleItem | null;
    selectedNiveau?: NiveauItem | null;
    breadcrumbs: BreadcrumbStep[];
    can: {
        institutions_edit: boolean;
        etablissements_manage: boolean;
        structures_manage: boolean;
        classes_manage: boolean;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

/**
 * Vue d'administration de l'Institution et du parcours pédagogique complet (Approche A - Drill-Down).
 *
 * Permet de naviguer de l'organisme faîtier jusqu'aux classes d'élèves
 * avec des tableaux TanStack Table interactifs, des modales dédiées et un fil d'Ariane réactif.
 */
export default function InstitutionIndex({
    institution,
    etablissements,
    sections,
    cycles,
    niveaux,
    classes,
    selectedEtablissement,
    selectedSection,
    selectedCycle,
    selectedNiveau,
    breadcrumbs,
    can,
}: InstitutionPageProps) {
    const { flash } = usePage<{ flash?: { success?: string | null; error?: string | null } }>().props;

    // Notifications Toasts
    React.useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, {
                style: {
                    backgroundColor: '#10b981',
                    color: '#ffffff',
                    border: '1px solid #059669',
                },
            });
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    // États des modales d'édition / création
    const [isEtablissementModalOpen, setIsEtablissementModalOpen] = useState(false);
    const [editingEtablissement, setEditingEtablissement] = useState<EtablissementItem | null>(null);

    const [isSectionModalOpen, setIsSectionModalOpen] = useState(false);
    const [editingSection, setEditingSection] = useState<SectionItem | null>(null);

    const [isCycleModalOpen, setIsCycleModalOpen] = useState(false);
    const [editingCycle, setEditingCycle] = useState<CycleItem | null>(null);

    const [isNiveauModalOpen, setIsNiveauModalOpen] = useState(false);
    const [editingNiveau, setEditingNiveau] = useState<NiveauItem | null>(null);

    const [isClasseModalOpen, setIsClasseModalOpen] = useState(false);
    const [editingClasse, setEditingClasse] = useState<ClasseItem | null>(null);

    // Formulaires Inertia

    const etablissementForm = useForm({
        code: '',
        nom: '',
        nom_en: '',
        statut_juridique: 'Prive_Confessionnel',
        ordre_enseignement: 'Secondaire_General',
        decret_creation: '',
        nom_chef_etablissement: '',
        email: '',
        telephone_1: '',
        telephone_2: '',
        adresse: '',
    });

    const sectionForm = useForm({
        etablissement_id: selectedEtablissement?.id || 0,
        code: '',
        nom: '',
        nom_en: '',
        langue: 'fr',
    });

    const cycleForm = useForm({
        section_id: selectedSection?.id || 0,
        code: '',
        nom: '',
        nom_en: '',
        diplome_fin_cycle: '',
        ordre: 1,
    });

    const niveauForm = useForm({
        cycle_id: selectedCycle?.id || 0,
        code: '',
        nom: '',
        nom_en: '',
        ordre: 1,
    });

    const classeForm = useForm({
        niveau_id: selectedNiveau?.id || 0,
        code: '',
        nom: '',
        serie: '',
        capacite_max: 50,
    });

    // Gestion du fil d'ariane
    const handleNavigateBreadcrumb = (params: Record<string, any>) => {
        router.get('/administration/institution', params, { preserveScroll: true });
    };

    /* =========================================================================
     * COLONNES TANSTACK TABLE
     * ========================================================================= */

    // 1. Tableau Établissements
    const etablissementColumns: ColumnDef<EtablissementItem>[] = useMemo(() => [
        {
            accessorKey: 'code',
            header: ({ column }) => (
                <button
                    type="button"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    className="flex items-center gap-1 font-semibold hover:text-foreground cursor-pointer"
                >
                    <span>Code</span>
                    <ArrowUpDown className="size-3.5" />
                </button>
            ),
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                    {row.original.code}
                </span>
            ),
        },
        {
            accessorKey: 'nom',
            header: 'Nom de l\'Établissement',
            cell: ({ row }) => (
                <div>
                    <div className="font-semibold text-sm text-foreground">{row.original.nom}</div>
                    {row.original.nom_en && (
                        <div className="text-xs text-muted-foreground italic">{row.original.nom_en}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'statut_juridique',
            header: 'Statut & Ordre',
            cell: ({ row }) => (
                <div className="flex flex-col gap-1">
                    <Badge variant="outline" className="w-fit text-[11px]">
                        {row.original.statut_juridique.replace('_', ' ')}
                    </Badge>
                    <span className="text-[11px] text-muted-foreground">
                        {row.original.ordre_enseignement.replace('_', ' ')}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'nom_chef_etablissement',
            header: 'Chef d\'Établissement',
            cell: ({ row }) => (
                <span className="text-xs text-foreground font-medium">
                    {row.original.nom_chef_etablissement || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'sections_count',
            header: 'Sections',
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <Layers className="size-3" />
                    {row.original.sections_count || 0} section(s)
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right pr-2 font-medium">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        {/* Bouton d'exploration Drill-down */}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="Explorer les sections de cet établissement"
                            onClick={() => handleNavigateBreadcrumb({ etablissement_id: item.id })}
                            className="size-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/60 cursor-pointer shadow-xs"
                        >
                            <ChevronRight className="size-4" />
                            <span className="sr-only">Explorer</span>
                        </Button>

                        {can.etablissements_manage && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier l'établissement"
                                    onClick={() => {
                                        setEditingEtablissement(item);
                                        etablissementForm.setData({
                                            code: item.code,
                                            nom: item.nom,
                                            nom_en: item.nom_en || '',
                                            statut_juridique: item.statut_juridique,
                                            ordre_enseignement: item.ordre_enseignement,
                                            decret_creation: item.decret_creation || '',
                                            nom_chef_etablissement: item.nom_chef_etablissement || '',
                                            email: item.email || '',
                                            telephone_1: item.telephone_1 || '',
                                            telephone_2: item.telephone_2 || '',
                                            adresse: item.adresse || '',
                                        });
                                        setIsEtablissementModalOpen(true);
                                    }}
                                    className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 hover:text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 dark:border-blue-800/60 cursor-pointer shadow-xs"
                                >
                                    <Edit2 className="size-4" />
                                    <span className="sr-only">Modifier</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Supprimer l'établissement"
                                    onClick={() => {
                                        if (confirm(`Confirmer la suppression de l'établissement « ${item.nom} » ?`)) {
                                            router.delete(`/administration/etablissements/${item.id}`);
                                        }
                                    }}
                                    className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 hover:text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-800/60 cursor-pointer shadow-xs"
                                >
                                    <Trash2 className="size-4" />
                                    <span className="sr-only">Supprimer</span>
                                </Button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ], [can.etablissements_manage]);

    // 2. Tableau Sections
    const sectionColumns: ColumnDef<SectionItem>[] = useMemo(() => [
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                    {row.original.code}
                </span>
            ),
        },
        {
            accessorKey: 'nom',
            header: 'Section',
            cell: ({ row }) => (
                <div>
                    <div className="font-semibold text-sm text-foreground">{row.original.nom}</div>
                    {row.original.nom_en && (
                        <div className="text-xs text-muted-foreground italic">{row.original.nom_en}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'langue',
            header: 'Langue d\'enseignement',
            cell: ({ row }) => (
                <Badge
                    variant="outline"
                    className={`font-semibold uppercase ${
                        row.original.langue === 'fr'
                            ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300'
                            : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300'
                    }`}
                >
                    {row.original.langue === 'fr' ? 'Français (FR)' : 'English (EN)'}
                </Badge>
            ),
        },
        {
            accessorKey: 'cycles_count',
            header: 'Cycles',
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300">
                    <GraduationCap className="size-3" />
                    {row.original.cycles_count || 0} cycle(s)
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right pr-2 font-medium">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="Explorer les cycles de cette section"
                            onClick={() =>
                                handleNavigateBreadcrumb({
                                    etablissement_id: selectedEtablissement?.id,
                                    section_id: item.id,
                                })
                            }
                            className="size-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 cursor-pointer shadow-xs"
                        >
                            <ChevronRight className="size-4" />
                            <span className="sr-only">Explorer</span>
                        </Button>

                        {can.structures_manage && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier la section"
                                    onClick={() => {
                                        setEditingSection(item);
                                        sectionForm.setData({
                                            etablissement_id: item.etablissement_id,
                                            code: item.code,
                                            nom: item.nom,
                                            nom_en: item.nom_en || '',
                                            langue: item.langue,
                                        });
                                        setIsSectionModalOpen(true);
                                    }}
                                    className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 hover:text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 cursor-pointer shadow-xs"
                                >
                                    <Edit2 className="size-4" />
                                    <span className="sr-only">Modifier</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Supprimer la section"
                                    onClick={() => {
                                        if (confirm(`Confirmer la suppression de la section « ${item.nom} » ?`)) {
                                            router.delete(`/administration/sections/${item.id}`);
                                        }
                                    }}
                                    className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 hover:text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                                >
                                    <Trash2 className="size-4" />
                                    <span className="sr-only">Supprimer</span>
                                </Button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ], [selectedEtablissement, can.structures_manage]);

    // 3. Tableau Cycles
    const cycleColumns: ColumnDef<CycleItem>[] = useMemo(() => [
        {
            accessorKey: 'ordre',
            header: 'Ordre',
            cell: ({ row }) => (
                <span className="font-mono text-xs font-bold text-muted-foreground">
                    #{row.original.ordre}
                </span>
            ),
        },
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                    {row.original.code}
                </span>
            ),
        },
        {
            accessorKey: 'nom',
            header: 'Nom du Cycle',
            cell: ({ row }) => (
                <div>
                    <div className="font-semibold text-sm text-foreground">{row.original.nom}</div>
                    {row.original.nom_en && (
                        <div className="text-xs text-muted-foreground italic">{row.original.nom_en}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'diplome_fin_cycle',
            header: 'Diplôme Officiel',
            cell: ({ row }) => (
                row.original.diplome_fin_cycle ? (
                    <Badge variant="secondary" className="font-mono text-xs">
                        {row.original.diplome_fin_cycle}
                    </Badge>
                ) : (
                    <span className="text-xs text-muted-foreground italic">Aucun</span>
                )
            ),
        },
        {
            accessorKey: 'niveaux_count',
            header: 'Niveaux',
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-violet-50 text-violet-700 border border-violet-200 dark:bg-violet-950/40 dark:text-violet-300">
                    <BookOpen className="size-3" />
                    {row.original.niveaux_count || 0} niveau(x)
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right pr-2 font-medium">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="Explorer les niveaux de ce cycle"
                            onClick={() =>
                                handleNavigateBreadcrumb({
                                    etablissement_id: selectedEtablissement?.id,
                                    section_id: selectedSection?.id,
                                    cycle_id: item.id,
                                })
                            }
                            className="size-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 cursor-pointer shadow-xs"
                        >
                            <ChevronRight className="size-4" />
                            <span className="sr-only">Explorer</span>
                        </Button>

                        {can.structures_manage && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier le cycle"
                                    onClick={() => {
                                        setEditingCycle(item);
                                        cycleForm.setData({
                                            section_id: item.section_id,
                                            code: item.code,
                                            nom: item.nom,
                                            nom_en: item.nom_en || '',
                                            diplome_fin_cycle: item.diplome_fin_cycle || '',
                                            ordre: item.ordre,
                                        });
                                        setIsCycleModalOpen(true);
                                    }}
                                    className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 hover:text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 cursor-pointer shadow-xs"
                                >
                                    <Edit2 className="size-4" />
                                    <span className="sr-only">Modifier</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Supprimer le cycle"
                                    onClick={() => {
                                        if (confirm(`Confirmer la suppression du cycle « ${item.nom} » ?`)) {
                                            router.delete(`/administration/cycles/${item.id}`);
                                        }
                                    }}
                                    className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 hover:text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                                >
                                    <Trash2 className="size-4" />
                                    <span className="sr-only">Supprimer</span>
                                </Button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ], [selectedEtablissement, selectedSection, can.structures_manage]);

    // 4. Tableau Niveaux
    const niveauColumns: ColumnDef<NiveauItem>[] = useMemo(() => [
        {
            accessorKey: 'ordre',
            header: 'Ordre',
            cell: ({ row }) => (
                <span className="font-mono text-xs font-bold text-muted-foreground">
                    #{row.original.ordre}
                </span>
            ),
        },
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                    {row.original.code}
                </span>
            ),
        },
        {
            accessorKey: 'nom',
            header: 'Nom du Niveau',
            cell: ({ row }) => (
                <div>
                    <div className="font-semibold text-sm text-foreground">{row.original.nom}</div>
                    {row.original.nom_en && (
                        <div className="text-xs text-muted-foreground italic">{row.original.nom_en}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'classes_count',
            header: 'Classes rattachées',
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200 dark:bg-cyan-950/40 dark:text-cyan-300">
                    <Users className="size-3" />
                    {row.original.classes_count || 0} classe(s)
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right pr-2 font-medium">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="Explorer les classes de ce niveau"
                            onClick={() =>
                                handleNavigateBreadcrumb({
                                    etablissement_id: selectedEtablissement?.id,
                                    section_id: selectedSection?.id,
                                    cycle_id: selectedCycle?.id,
                                    niveau_id: item.id,
                                })
                            }
                            className="size-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 cursor-pointer shadow-xs"
                        >
                            <ChevronRight className="size-4" />
                            <span className="sr-only">Explorer</span>
                        </Button>

                        {can.structures_manage && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier le niveau"
                                    onClick={() => {
                                        setEditingNiveau(item);
                                        niveauForm.setData({
                                            cycle_id: item.cycle_id,
                                            code: item.code,
                                            nom: item.nom,
                                            nom_en: item.nom_en || '',
                                            ordre: item.ordre,
                                        });
                                        setIsNiveauModalOpen(true);
                                    }}
                                    className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 hover:text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 cursor-pointer shadow-xs"
                                >
                                    <Edit2 className="size-4" />
                                    <span className="sr-only">Modifier</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Supprimer le niveau"
                                    onClick={() => {
                                        if (confirm(`Confirmer la suppression du niveau « ${item.nom} » ?`)) {
                                            router.delete(`/administration/niveaux/${item.id}`);
                                        }
                                    }}
                                    className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 hover:text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                                >
                                    <Trash2 className="size-4" />
                                    <span className="sr-only">Supprimer</span>
                                </Button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ], [selectedEtablissement, selectedSection, selectedCycle, can.structures_manage]);

    // 5. Tableau Classes
    const classeColumns: ColumnDef<ClasseItem>[] = useMemo(() => [
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                    {row.original.code}
                </span>
            ),
        },
        {
            accessorKey: 'nom',
            header: 'Nom de la Classe',
            cell: ({ row }) => (
                <span className="font-semibold text-sm text-foreground">{row.original.nom}</span>
            ),
        },
        {
            accessorKey: 'serie',
            header: 'Série / Filière',
            cell: ({ row }) => (
                row.original.serie ? (
                    <Badge variant="outline" className="text-xs font-mono">
                        {row.original.serie}
                    </Badge>
                ) : (
                    <span className="text-xs text-muted-foreground italic">Générale</span>
                )
            ),
        },
        {
            accessorKey: 'capacite_max',
            header: 'Capacité max',
            cell: ({ row }) => (
                <span className="text-xs font-semibold text-muted-foreground">
                    {row.original.capacite_max} élèves
                </span>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Statut',
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <button
                        type="button"
                        onClick={() => {
                            if (can.classes_manage) {
                                router.patch(`/administration/classes/${item.id}/toggle-active`, {}, { preserveScroll: true });
                            }
                        }}
                        title={can.classes_manage ? 'Cliquer pour basculer le statut' : undefined}
                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all ${
                            can.classes_manage ? 'cursor-pointer' : 'cursor-default'
                        } ${
                            item.is_active
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300'
                        }`}
                    >
                        <span className={`size-1.5 rounded-full ${item.is_active ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                        {item.is_active ? 'Active' : 'Inactive'}
                    </button>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right pr-2 font-medium">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                if (!can.classes_manage) return null;

                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="Modifier la classe"
                            onClick={() => {
                                setEditingClasse(item);
                                classeForm.setData({
                                    niveau_id: item.niveau_id,
                                    code: item.code,
                                    nom: item.nom,
                                    serie: item.serie || '',
                                    capacite_max: item.capacite_max,
                                });
                                setIsClasseModalOpen(true);
                            }}
                            className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 hover:text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 cursor-pointer shadow-xs"
                        >
                            <Edit2 className="size-4" />
                            <span className="sr-only">Modifier</span>
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="Supprimer la classe"
                            onClick={() => {
                                if (confirm(`Confirmer la suppression de la classe « ${item.nom} » ?`)) {
                                    router.delete(`/administration/classes/${item.id}`);
                                }
                            }}
                            className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 hover:text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                        >
                            <Trash2 className="size-4" />
                            <span className="sr-only">Supprimer</span>
                        </Button>
                    </div>
                );
            },
        },
    ], [can.classes_manage]);

    /* =========================================================================
     * SOUMISSION DES FORMULAIRES
     * ========================================================================= */

    const submitEtablissement = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingEtablissement) {
            etablissementForm.put(`/administration/etablissements/${editingEtablissement.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEtablissementModalOpen(false);
                    setEditingEtablissement(null);
                },
            });
        } else {
            etablissementForm.post('/administration/etablissements', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEtablissementModalOpen(false);
                    etablissementForm.reset();
                },
            });
        }
    };

    const submitSection = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingSection) {
            sectionForm.put(`/administration/sections/${editingSection.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSectionModalOpen(false);
                    setEditingSection(null);
                },
            });
        } else {
            sectionForm.post('/administration/sections', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSectionModalOpen(false);
                    sectionForm.reset();
                },
            });
        }
    };

    const submitCycle = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingCycle) {
            cycleForm.put(`/administration/cycles/${editingCycle.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsCycleModalOpen(false);
                    setEditingCycle(null);
                },
            });
        } else {
            cycleForm.post('/administration/cycles', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsCycleModalOpen(false);
                    cycleForm.reset();
                },
            });
        }
    };

    const submitNiveau = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingNiveau) {
            niveauForm.put(`/administration/niveaux/${editingNiveau.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsNiveauModalOpen(false);
                    setEditingNiveau(null);
                },
            });
        } else {
            niveauForm.post('/administration/niveaux', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsNiveauModalOpen(false);
                    niveauForm.reset();
                },
            });
        }
    };

    const submitClasse = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingClasse) {
            classeForm.put(`/administration/classes/${editingClasse.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsClasseModalOpen(false);
                    setEditingClasse(null);
                },
            });
        } else {
            classeForm.post('/administration/classes', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsClasseModalOpen(false);
                    classeForm.reset();
                },
            });
        }
    };

    return (
        <>
            <Head title="Institution & Campus - Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full">
                {/* 1. EN-TÊTE ET FIL D'ARIANE DYNAMIQUE (DRILL-DOWN) */}
                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                                <School className="size-7 text-indigo-600 dark:text-indigo-400" />
                                Institution & Structure Pédagogique
                            </h1>
                            <p className="text-muted-foreground text-sm mt-1">
                                Organisez votre groupe scolaire, campus, sections, cycles, niveaux et classes.
                            </p>
                        </div>
                    </div>

                    {/* Fil d'Ariane interactif (Approche A) */}
                    <nav className="flex items-center flex-wrap gap-2 text-xs text-muted-foreground p-3 rounded-xl bg-muted/40 border border-border/70">
                        <button
                            type="button"
                            onClick={() => handleNavigateBreadcrumb({})}
                            className="flex items-center gap-1.5 font-medium hover:text-foreground transition-colors cursor-pointer"
                        >
                            <Home className="size-3.5 text-primary" />
                            <span>{institution.nom}</span>
                        </button>

                        {breadcrumbs.slice(1).map((step, idx) => (
                            <React.Fragment key={idx}>
                                <ChevronRight className="size-3 text-muted-foreground/60" />
                                <button
                                    type="button"
                                    onClick={() => handleNavigateBreadcrumb(step.params)}
                                    className={`font-medium transition-colors cursor-pointer ${
                                        idx === breadcrumbs.length - 2
                                            ? 'text-foreground font-semibold'
                                            : 'hover:text-foreground'
                                    }`}
                                >
                                    {step.label}
                                </button>
                            </React.Fragment>
                        ))}
                    </nav>
                </div>

                {/* 2. SI NOUS SOMMES AU NIVEAU RACINE : FICHE INSTITUTION + TABLEAU ÉTABLISSEMENTS */}
                {!selectedEtablissement && (
                    <div className="space-y-6">
                        {/* Fiche d'identité de l'institution */}
                        <div className="rounded-2xl border border-border/80 bg-card p-6 shadow-xs relative overflow-hidden">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="space-y-1 max-w-2xl">
                                    <div className="flex items-center gap-2">
                                        <Badge variant="secondary" className="font-mono text-xs font-bold text-primary">
                                            {institution.code}
                                        </Badge>
                                        <h2 className="text-xl font-bold text-foreground">{institution.nom}</h2>
                                    </div>
                                    {institution.nom_en && (
                                        <p className="text-xs text-muted-foreground italic">{institution.nom_en}</p>
                                    )}
                                    {institution.devise && (
                                        <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400 pt-1">
                                            « {institution.devise} » {institution.devise_en ? `/ "${institution.devise_en}"` : ''}
                                        </p>
                                    )}
                                </div>

                                {can.institutions_edit && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        className="cursor-pointer gap-2"
                                    >
                                        <Link href="/administration/institution/edit">
                                            <Edit2 className="size-3.5 text-blue-600" />
                                            <span>Modifier l'institution</span>
                                        </Link>
                                    </Button>
                                )}
                            </div>

                            {/* Coordonnées & Tutelles */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-5 mt-5 border-t border-border/60 text-xs text-muted-foreground">
                                <div className="space-y-1.5">
                                    <span className="font-semibold text-foreground flex items-center gap-1.5">
                                        <MapPin className="size-3.5 text-rose-500" /> Localisation & Siège
                                    </span>
                                    <p>{institution.ville || 'Ville non définie'} • Région {institution.region || '—'}</p>
                                    <p>Département du {institution.departement || '—'} {institution.boite_postale ? `(${institution.boite_postale})` : ''}</p>
                                </div>

                                <div className="space-y-1.5">
                                    <span className="font-semibold text-foreground flex items-center gap-1.5">
                                        <Phone className="size-3.5 text-emerald-500" /> Contacts & Web
                                    </span>
                                    <p>Tél : {institution.telephone_1 || '—'} {institution.telephone_2 ? `/ ${institution.telephone_2}` : ''}</p>
                                    <p>Email : {institution.email || '—'}</p>
                                </div>

                                <div className="space-y-1.5">
                                    <span className="font-semibold text-foreground flex items-center gap-1.5">
                                        <Shield className="size-3.5 text-indigo-500" /> Tutelle Officielle (MINESEC/MINEDUB)
                                    </span>
                                    <p>{institution.delegation_regionale || 'Délégation Régionale non renseignée'}</p>
                                    <p>{institution.delegation_departementale || 'Délégation Départementale non renseignée'}</p>
                                </div>
                            </div>
                        </div>

                        {/* Liste des campus et établissements */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
                                        <Building2 className="size-5 text-emerald-600" />
                                        Campus & Établissements
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Cliquez sur la flèche verte pour explorer les sections et classes d'un campus.
                                    </p>
                                </div>

                                {can.etablissements_manage && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() => {
                                            setEditingEtablissement(null);
                                            etablissementForm.reset();
                                            setIsEtablissementModalOpen(true);
                                        }}
                                        className="gap-2 cursor-pointer bg-primary text-primary-foreground hover:bg-primary/90"
                                    >
                                        <Plus className="size-4" />
                                        <span>Ajouter un Établissement</span>
                                    </Button>
                                )}
                            </div>

                            <DataTable
                                columns={etablissementColumns}
                                data={etablissements}
                                searchPlaceholder="Rechercher un établissement par code ou nom..."
                            />
                        </div>
                    </div>
                )}

                {/* 3. NIVEAU 2 : SECTIONS DE L'ÉTABLISSEMENT SÉLECTIONNÉ */}
                {selectedEtablissement && !selectedSection && (
                    <div className="space-y-6">
                        <div className="rounded-2xl border border-border/80 bg-card p-5 flex items-center justify-between">
                            <div>
                                <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                    Établissement actif
                                </span>
                                <h2 className="text-xl font-bold text-foreground flex items-center gap-2 mt-0.5">
                                    <Building2 className="size-5 text-indigo-600" />
                                    {selectedEtablissement.nom}
                                    <Badge variant="outline" className="font-mono text-xs">{selectedEtablissement.code}</Badge>
                                </h2>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Chef d'établissement : <span className="font-medium text-foreground">{selectedEtablissement.nom_chef_etablissement || 'Non désigné'}</span> • Statut : <span className="font-medium text-foreground">{selectedEtablissement.statut_juridique.replace('_', ' ')}</span>
                                </p>
                            </div>

                            {can.structures_manage && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => {
                                        setEditingSection(null);
                                        sectionForm.setData({
                                            etablissement_id: selectedEtablissement.id,
                                            code: '',
                                            nom: '',
                                            nom_en: '',
                                            langue: 'fr',
                                        });
                                        setIsSectionModalOpen(true);
                                    }}
                                    className="gap-2 cursor-pointer"
                                >
                                    <Plus className="size-4" />
                                    <span>Ajouter une Section</span>
                                </Button>
                            )}
                        </div>

                        <div className="space-y-3">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <Layers className="size-4 text-emerald-600" />
                                Sections d'Enseignement (Sous-systèmes)
                            </h3>
                            <DataTable
                                columns={sectionColumns}
                                data={sections}
                                searchPlaceholder="Rechercher une section..."
                            />
                        </div>
                    </div>
                )}

                {/* 4. NIVEAU 3 : CYCLES DE LA SECTION SÉLECTIONNÉE */}
                {selectedSection && !selectedCycle && (
                    <div className="space-y-6">
                        <div className="rounded-2xl border border-border/80 bg-card p-5 flex items-center justify-between">
                            <div>
                                <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                    Section active
                                </span>
                                <h2 className="text-xl font-bold text-foreground flex items-center gap-2 mt-0.5">
                                    <Layers className="size-5 text-emerald-600" />
                                    {selectedSection.nom}
                                    <Badge variant="outline" className="font-mono text-xs uppercase">{selectedSection.langue}</Badge>
                                </h2>
                            </div>

                            {can.structures_manage && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => {
                                        setEditingCycle(null);
                                        cycleForm.setData({
                                            section_id: selectedSection.id,
                                            code: '',
                                            nom: '',
                                            nom_en: '',
                                            diplome_fin_cycle: '',
                                            ordre: (cycles.length || 0) + 1,
                                        });
                                        setIsCycleModalOpen(true);
                                    }}
                                    className="gap-2 cursor-pointer"
                                >
                                    <Plus className="size-4" />
                                    <span>Ajouter un Cycle</span>
                                </Button>
                            )}
                        </div>

                        <div className="space-y-3">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <GraduationCap className="size-4 text-indigo-600" />
                                Cycles d'Enseignement
                            </h3>
                            <DataTable
                                columns={cycleColumns}
                                data={cycles}
                                searchPlaceholder="Rechercher un cycle..."
                            />
                        </div>
                    </div>
                )}

                {/* 5. NIVEAU 4 : NIVEAUX DU CYCLE SÉLECTIONNÉ */}
                {selectedCycle && !selectedNiveau && (
                    <div className="space-y-6">
                        <div className="rounded-2xl border border-border/80 bg-card p-5 flex items-center justify-between">
                            <div>
                                <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                    Cycle actif
                                </span>
                                <h2 className="text-xl font-bold text-foreground flex items-center gap-2 mt-0.5">
                                    <GraduationCap className="size-5 text-indigo-600" />
                                    {selectedCycle.nom}
                                    {selectedCycle.diplome_fin_cycle && (
                                        <Badge variant="secondary" className="text-xs font-mono">{selectedCycle.diplome_fin_cycle}</Badge>
                                    )}
                                </h2>
                            </div>

                            {can.structures_manage && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => {
                                        setEditingNiveau(null);
                                        niveauForm.setData({
                                            cycle_id: selectedCycle.id,
                                            code: '',
                                            nom: '',
                                            nom_en: '',
                                            ordre: (niveaux.length || 0) + 1,
                                        });
                                        setIsNiveauModalOpen(true);
                                    }}
                                    className="gap-2 cursor-pointer"
                                >
                                    <Plus className="size-4" />
                                    <span>Ajouter un Niveau</span>
                                </Button>
                            )}
                        </div>

                        <div className="space-y-3">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <BookOpen className="size-4 text-violet-600" />
                                Niveaux d'Étude (Paliers académiques)
                            </h3>
                            <DataTable
                                columns={niveauColumns}
                                data={niveaux}
                                searchPlaceholder="Rechercher un niveau..."
                            />
                        </div>
                    </div>
                )}

                {/* 6. NIVEAU 5 : CLASSES DU NIVEAU SÉLECTIONNÉ */}
                {selectedNiveau && (
                    <div className="space-y-6">
                        <div className="rounded-2xl border border-border/80 bg-card p-5 flex items-center justify-between">
                            <div>
                                <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                    Niveau d'étude actif
                                </span>
                                <h2 className="text-xl font-bold text-foreground flex items-center gap-2 mt-0.5">
                                    <BookOpen className="size-5 text-violet-600" />
                                    Niveau {selectedNiveau.nom}
                                    <Badge variant="outline" className="font-mono text-xs">{selectedNiveau.code}</Badge>
                                </h2>
                            </div>

                            {can.classes_manage && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => {
                                        setEditingClasse(null);
                                        classeForm.setData({
                                            niveau_id: selectedNiveau.id,
                                            code: '',
                                            nom: '',
                                            serie: '',
                                            capacite_max: 50,
                                        });
                                        setIsClasseModalOpen(true);
                                    }}
                                    className="gap-2 cursor-pointer"
                                >
                                    <Plus className="size-4" />
                                    <span>Créer une Classe</span>
                                </Button>
                            )}
                        </div>

                        <div className="space-y-3">
                            <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
                                <Users className="size-4 text-cyan-600" />
                                Classes d'Élèves
                            </h3>
                            <DataTable
                                columns={classeColumns}
                                data={classes}
                                searchPlaceholder="Rechercher une classe..."
                            />
                        </div>
                    </div>
                )}
            </div>

            {/* =========================================================================
             * MODALES CRUD
             * ========================================================================= */}



            {/* Modale Établissement */}
            <Dialog open={isEtablissementModalOpen} onOpenChange={setIsEtablissementModalOpen}>
                <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editingEtablissement ? 'Modifier l\'Établissement' : 'Créer un nouvel Établissement (Campus)'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitEtablissement} className="space-y-4 pt-2">
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code MINESEC/DUB *</Label>
                                <Input
                                    value={etablissementForm.data.code}
                                    onChange={(e) => etablissementForm.setData('code', e.target.value)}
                                    placeholder="ex: CSJB"
                                    required
                                />
                            </div>
                            <div className="sm:col-span-2 space-y-1">
                                <Label className="text-xs font-semibold">Nom de l'établissement (FR) *</Label>
                                <Input
                                    value={etablissementForm.data.nom}
                                    onChange={(e) => etablissementForm.setData('nom', e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs font-semibold">Nom de l'établissement (EN)</Label>
                            <Input
                                value={etablissementForm.data.nom_en}
                                onChange={(e) => etablissementForm.setData('nom_en', e.target.value)}
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Statut juridique</Label>
                                <Select
                                    value={etablissementForm.data.statut_juridique}
                                    onValueChange={(val) => etablissementForm.setData('statut_juridique', val)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Prive_Confessionnel">Privé Confessionnel</SelectItem>
                                        <SelectItem value="Prive_Laic">Privé Laïc</SelectItem>
                                        <SelectItem value="Public">Public</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Ordre d'enseignement</Label>
                                <Select
                                    value={etablissementForm.data.ordre_enseignement}
                                    onValueChange={(val) => etablissementForm.setData('ordre_enseignement', val)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Secondaire_General">Secondaire Général</SelectItem>
                                        <SelectItem value="Secondaire_Technique">Secondaire Technique</SelectItem>
                                        <SelectItem value="Primaire">Primaire</SelectItem>
                                        <SelectItem value="Maternelle">Maternelle</SelectItem>
                                        <SelectItem value="Polyvalent">Polyvalent</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Chef d'Établissement (Proviseur/Directeur)</Label>
                                <Input
                                    value={etablissementForm.data.nom_chef_etablissement}
                                    onChange={(e) => etablissementForm.setData('nom_chef_etablissement', e.target.value)}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Décret / Arrêté d'ouverture</Label>
                                <Input
                                    value={etablissementForm.data.decret_creation}
                                    onChange={(e) => etablissementForm.setData('decret_creation', e.target.value)}
                                    placeholder="Arrêté N°..."
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Téléphone</Label>
                                <Input
                                    value={etablissementForm.data.telephone_1}
                                    onChange={(e) => etablissementForm.setData('telephone_1', e.target.value)}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Email du campus</Label>
                                <Input
                                    type="email"
                                    value={etablissementForm.data.email}
                                    onChange={(e) => etablissementForm.setData('email', e.target.value)}
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsEtablissementModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={etablissementForm.processing}>
                                {editingEtablissement ? 'Mettre à jour' : 'Créer l\'établissement'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modale Section */}
            <Dialog open={isSectionModalOpen} onOpenChange={setIsSectionModalOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingSection ? 'Modifier la Section' : 'Ajouter une Section'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitSection} className="space-y-4 pt-2">
                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code *</Label>
                                <Input
                                    value={sectionForm.data.code}
                                    onChange={(e) => sectionForm.setData('code', e.target.value)}
                                    placeholder="FR, EN"
                                    required
                                />
                            </div>
                            <div className="col-span-2 space-y-1">
                                <Label className="text-xs font-semibold">Nom de la Section *</Label>
                                <Input
                                    value={sectionForm.data.nom}
                                    onChange={(e) => sectionForm.setData('nom', e.target.value)}
                                    placeholder="ex: Section Francophone"
                                    required
                                />
                            </div>
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs font-semibold">Nom en Anglais</Label>
                            <Input
                                value={sectionForm.data.nom_en}
                                onChange={(e) => sectionForm.setData('nom_en', e.target.value)}
                                placeholder="ex: Francophone Section"
                            />
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs font-semibold">Langue d'enseignement *</Label>
                            <Select
                                value={sectionForm.data.langue}
                                onValueChange={(val) => sectionForm.setData('langue', val)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fr">Français (FR)</SelectItem>
                                    <SelectItem value="en">Anglais (EN)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsSectionModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={sectionForm.processing}>
                                {editingSection ? 'Mettre à jour' : 'Enregistrer la section'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modale Cycle */}
            <Dialog open={isCycleModalOpen} onOpenChange={setIsCycleModalOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingCycle ? 'Modifier le Cycle' : 'Ajouter un Cycle'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitCycle} className="space-y-4 pt-2">
                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code *</Label>
                                <Input
                                    value={cycleForm.data.code}
                                    onChange={(e) => cycleForm.setData('code', e.target.value)}
                                    placeholder="CYC1"
                                    required
                                />
                            </div>
                            <div className="col-span-2 space-y-1">
                                <Label className="text-xs font-semibold">Nom du Cycle *</Label>
                                <Input
                                    value={cycleForm.data.nom}
                                    onChange={(e) => cycleForm.setData('nom', e.target.value)}
                                    placeholder="ex: Premier Cycle"
                                    required
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Diplôme Préparé</Label>
                                <Input
                                    value={cycleForm.data.diplome_fin_cycle}
                                    onChange={(e) => cycleForm.setData('diplome_fin_cycle', e.target.value)}
                                    placeholder="BEPC, BACC, GCE..."
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Ordre chronologique *</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={cycleForm.data.ordre}
                                    onChange={(e) => cycleForm.setData('ordre', parseInt(e.target.value) || 1)}
                                    required
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsCycleModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={cycleForm.processing}>
                                {editingCycle ? 'Mettre à jour' : 'Enregistrer le cycle'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modale Niveau */}
            <Dialog open={isNiveauModalOpen} onOpenChange={setIsNiveauModalOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingNiveau ? 'Modifier le Niveau d\'étude' : 'Ajouter un Niveau d\'étude'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitNiveau} className="space-y-4 pt-2">
                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code *</Label>
                                <Input
                                    value={niveauForm.data.code}
                                    onChange={(e) => niveauForm.setData('code', e.target.value)}
                                    placeholder="6EME"
                                    required
                                />
                            </div>
                            <div className="col-span-2 space-y-1">
                                <Label className="text-xs font-semibold">Nom du Niveau *</Label>
                                <Input
                                    value={niveauForm.data.nom}
                                    onChange={(e) => niveauForm.setData('nom', e.target.value)}
                                    placeholder="ex: Sixième"
                                    required
                                />
                            </div>
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs font-semibold">Ordre de progression (1, 2, 3...) *</Label>
                            <Input
                                type="number"
                                min={1}
                                value={niveauForm.data.ordre}
                                onChange={(e) => niveauForm.setData('ordre', parseInt(e.target.value) || 1)}
                                required
                            />
                            <p className="text-[11px] text-muted-foreground">
                                Sert à ordonner la hiérarchie et le futur moteur de passage en classe supérieure.
                            </p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsNiveauModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={niveauForm.processing}>
                                {editingNiveau ? 'Mettre à jour' : 'Enregistrer le niveau'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modale Classe */}
            <Dialog open={isClasseModalOpen} onOpenChange={setIsClasseModalOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingClasse ? 'Modifier la Classe' : 'Créer une nouvelle Classe'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitClasse} className="space-y-4 pt-2">
                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code *</Label>
                                <Input
                                    value={classeForm.data.code}
                                    onChange={(e) => classeForm.setData('code', e.target.value)}
                                    placeholder="6M1"
                                    required
                                />
                            </div>
                            <div className="col-span-2 space-y-1">
                                <Label className="text-xs font-semibold">Nom de la Classe *</Label>
                                <Input
                                    value={classeForm.data.nom}
                                    onChange={(e) => classeForm.setData('nom', e.target.value)}
                                    placeholder="ex: 6ème Moderne 1"
                                    required
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Série / Filière</Label>
                                <Input
                                    value={classeForm.data.serie}
                                    onChange={(e) => classeForm.setData('serie', e.target.value)}
                                    placeholder="A4, C, D, Général..."
                                />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Capacité maximale *</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    max={150}
                                    value={classeForm.data.capacite_max}
                                    onChange={(e) => classeForm.setData('capacite_max', parseInt(e.target.value) || 50)}
                                    required
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsClasseModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={classeForm.processing}>
                                {editingClasse ? 'Mettre à jour' : 'Créer la classe'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Fil d'Ariane du Layout racine.
 */
InstitutionIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Institution & Campus',
            href: '/administration/institution',
        },
    ],
};

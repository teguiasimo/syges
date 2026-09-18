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
    Shield,
    Sliders,
    Lock,
    Calendar,
    Tag,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { usePermission } from '@/hooks/use-permission';
import { useConfirmDelete } from '@/components/confirm-delete-dialog';
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
import { SearchableSelect } from '@/components/ui/searchable-select';
import { DIPLOMES_CYCLE_CAMEROUN } from '@/constants/academic-referentials';

/**
 * Types TypeScript pour la structure académique.
 */
interface InstitutionItem {
    id: number;
    code: string;
    sigle?: string | null;
    logo?: string | null;
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

interface SerieItem {
    id: number;
    etablissement_id: number;
    nom: string;
}

interface ClasseItem {
    id: number;
    niveau_id: number;
    code: string;
    nom: string;
    serie?: string | null;
    capacite_max: number;
    is_active: boolean;
    attributions_count?: number;
    inscriptions_count?: number;
    seances_emploi_temps_count?: number;
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
    series?: SerieItem[];
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
    series = [],
    selectedEtablissement,
    selectedSection,
    selectedCycle,
    selectedNiveau,
    breadcrumbs,
    can: propCan,
}: InstitutionPageProps) {
    const { can: checkPerm } = usePermission();
    const can = {
        institutions_edit: propCan?.institutions_edit ?? checkPerm('institutions.edit'),
        etablissements_manage: propCan?.etablissements_manage ?? checkPerm('etablissements.manage'),
        structures_manage: propCan?.structures_manage ?? checkPerm('structures.manage'),
        classes_manage: propCan?.classes_manage ?? checkPerm('classes.manage'),
    };

    const { flash } = usePage<{ flash?: { success?: string | null; error?: string | null } }>().props;

    // Référence pour mémoriser le dernier message affiché et éviter les doublons (notamment en React StrictMode)
    const lastToastRef = React.useRef<string | null>(null);

    // Notifications Toasts avec déduplication stricte
    React.useEffect(() => {
        if (flash?.success && lastToastRef.current !== flash.success) {
            lastToastRef.current = flash.success;
            toast.success(flash.success, {
                id: 'institution-flash-success',
                style: {
                    backgroundColor: '#10b981',
                    color: '#ffffff',
                    border: '1px solid #059669',
                },
            });
        }
        if (flash?.error && lastToastRef.current !== flash.error) {
            lastToastRef.current = flash.error;
            toast.error(flash.error, {
                id: 'institution-flash-error',
            });
        }
    }, [flash]);

    // Hook universel de confirmation de suppression (boîte modale personnalisée)
    const { confirmDelete, ConfirmDialog } = useConfirmDelete();

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

    // Interface et valeurs initiales vierges du formulaire établissement
    interface EtablissementFormData {
        code: string;
        nom: string;
        nom_en: string;
        statut_juridique: string;
        ordre_enseignement: string;
        decret_creation: string;
        nom_chef_etablissement: string;
        email: string;
        telephone_1: string;
        telephone_2: string;
        adresse: string;
    }

    const initialEtablissementFormValues: EtablissementFormData = {
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
    };

    const etablissementForm = useForm<EtablissementFormData>(initialEtablissementFormValues);

    /**
     * Réinitialise complètement les champs du formulaire d'établissement et efface les erreurs.
     */
    const resetEtablissementForm = () => {
        setEditingEtablissement(null);
        etablissementForm.clearErrors();
        etablissementForm.setData(initialEtablissementFormValues);
    };

    /**
     * Ouvre la modale pour l'ajout d'un nouvel établissement vierge.
     */
    const handleOpenCreateEtablissement = () => {
        resetEtablissementForm();
        setIsEtablissementModalOpen(true);
    };

    /**
     * Ouvre la modale pour modifier un établissement existant avec ses données chargées.
     *
     * @param {EtablissementItem} item L'établissement à éditer.
     */
    const handleOpenEditEtablissement = (item: EtablissementItem) => {
        setEditingEtablissement(item);
        etablissementForm.clearErrors();
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
    };

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
        serie: 'GENERAL',
        capacite_max: 50,
    });

    // Gestion autonome des Séries d'orientation de l'établissement
    const [isSerieModalOpen, setIsSerieModalOpen] = useState(false);
    const [editingSerie, setEditingSerie] = useState<SerieItem | null>(null);
    const serieForm = useForm({
        etablissement_id: selectedEtablissement?.id || 0,
        nom: '',
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
                                    variant="ghost"
                                    size="icon"
                                    asChild
                                    title="Paramètres & Règles de l'établissement"
                                    className="size-8 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/60 cursor-pointer shadow-xs"
                                >
                                    <Link href={`/administration/etablissements/${item.id}/settings`}>
                                        <Sliders className="size-4" />
                                        <span className="sr-only">Paramètres & Règles</span>
                                    </Link>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier l'établissement"
                                    onClick={() => handleOpenEditEtablissement(item)}
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
                                        confirmDelete({
                                            title: "Supprimer l'établissement",
                                            itemType: "l'établissement",
                                            itemName: item.nom,
                                            description: "Cette action effacera définitivement ce campus ainsi que toutes les sections, cycles, niveaux, classes et inscriptions rattachés.",
                                            onConfirm: () => router.delete(`/administration/etablissements/${item.id}`),
                                        });
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
                <div className="font-semibold text-sm text-foreground">{row.original.nom}</div>
            ),
        },
        {
            accessorKey: 'langue',
            header: 'Langue d\'enseignement',
            cell: ({ row }) => (
                <Badge
                    variant="outline"
                    className={`font-semibold uppercase ${row.original.langue === 'fr'
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
                                        confirmDelete({
                                            title: "Supprimer la section",
                                            itemType: "la section",
                                            itemName: item.nom,
                                            description: "Cette suppression supprimera l'ensemble des cycles, niveaux et classes rattachés à cette section.",
                                            onConfirm: () => router.delete(`/administration/sections/${item.id}`),
                                        });
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
                <span className="font-mono text-xs font-bold text-muted-foreground bg-muted/60 px-2 py-0.5 rounded">
                    #{row.original.ordre}
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
                    <Badge variant="secondary" className="font-semibold text-xs bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300">
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
                                            diplome_fin_cycle: item.diplome_fin_cycle || 'AUCUN',
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
                                        confirmDelete({
                                            title: "Supprimer le cycle",
                                            itemType: "le cycle",
                                            itemName: item.nom,
                                            description: "Cette action effacera tous les niveaux scolaires et les classes associés à ce cycle.",
                                            onConfirm: () => router.delete(`/administration/cycles/${item.id}`),
                                        });
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
                <span className="font-mono text-xs font-bold text-muted-foreground bg-muted/60 px-2 py-0.5 rounded">
                    #{row.original.ordre}
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
                                        confirmDelete({
                                            title: "Supprimer le niveau",
                                            itemType: "le niveau",
                                            itemName: item.nom,
                                            description: "Toutes les classes rattachées à ce niveau ainsi que leurs cours seront définitivement supprimés.",
                                            onConfirm: () => router.delete(`/administration/niveaux/${item.id}`),
                                        });
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
            header: 'Série',
            cell: ({ row }) => {
                const serie = row.original.serie;
                if (!serie) {
                    return <span className="text-xs text-muted-foreground italic">Tronc commun</span>;
                }
                return (
                    <Badge variant="outline" className="text-xs font-mono font-bold text-primary bg-primary/10 border-primary/20">
                        {serie}
                    </Badge>
                );
            },
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
            id: 'donnees_pedagogiques',
            header: 'Données rattachées',
            cell: ({ row }) => {
                const item = row.original;
                const inscriptions = item.inscriptions_count ?? 0;
                const attributions = item.attributions_count ?? 0;
                const hasData = inscriptions > 0 || attributions > 0;

                if (!hasData) {
                    return (
                        <span className="inline-flex items-center text-xs text-muted-foreground italic">
                            Classe vierge (0 élève, 0 cours)
                        </span>
                    );
                }

                return (
                    <div className="flex items-center gap-1.5 flex-wrap">
                        <span
                            title={`${inscriptions} élève(s) inscrit(s)`}
                            className={`inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-md border ${
                                inscriptions > 0
                                    ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800'
                                    : 'bg-muted/50 text-muted-foreground border-muted'
                            }`}
                        >
                            <Users className="size-3" />
                            {inscriptions} élève{inscriptions > 1 ? 's' : ''}
                        </span>
                        <span
                            title={`${attributions} matière(s) / enseignant(s) attribué(s)`}
                            className={`inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-md border ${
                                attributions > 0
                                    ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800'
                                    : 'bg-muted/50 text-muted-foreground border-muted'
                            }`}
                        >
                            <BookOpen className="size-3" />
                            {attributions} cours
                        </span>
                    </div>
                );
            },
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
                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all ${can.classes_manage ? 'cursor-pointer' : 'cursor-default'
                            } ${item.is_active
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

                const hasDependencies = (item.attributions_count ?? 0) > 0
                    || (item.inscriptions_count ?? 0) > 0
                    || (item.seances_emploi_temps_count ?? 0) > 0;

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

                        {hasDependencies ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title="Suppression verrouillée : des matières/enseignants ou des élèves y sont déjà associés. Cliquez pour plus de détails."
                                onClick={() => {
                                    confirmDelete({
                                        title: "Suppression verrouillée",
                                        variant: "warning",
                                        itemType: "la classe",
                                        itemName: item.nom,
                                        confirmLabel: item.is_active ? "Désactiver la classe" : "Compris",
                                        cancelLabel: "Fermer",
                                        description: (
                                            <div className="space-y-3 text-sm text-left">
                                                <p>
                                                    La classe <strong>« {item.nom} »</strong> ne peut pas être supprimée car elle contient des données pédagogiques actives :
                                                </p>
                                                <div className="bg-amber-500/10 border border-amber-200 dark:border-amber-800 rounded-lg p-3 space-y-1.5 text-xs text-foreground">
                                                    {(item.attributions_count ?? 0) > 0 && (
                                                        <div className="flex items-center gap-2">
                                                            <BookOpen className="size-3.5 text-amber-600 shrink-0" />
                                                            <span><strong>{item.attributions_count}</strong> matière(s) et enseignant(s) attribué(s)</span>
                                                        </div>
                                                    )}
                                                    {(item.inscriptions_count ?? 0) > 0 && (
                                                        <div className="flex items-center gap-2">
                                                            <Users className="size-3.5 text-blue-600 shrink-0" />
                                                            <span><strong>{item.inscriptions_count}</strong> élève(s) inscrit(s)</span>
                                                        </div>
                                                    )}
                                                    {(item.seances_emploi_temps_count ?? 0) > 0 && (
                                                        <div className="flex items-center gap-2">
                                                            <Calendar className="size-3.5 text-emerald-600 shrink-0" />
                                                            <span><strong>{item.seances_emploi_temps_count}</strong> cours à l'emploi du temps</span>
                                                        </div>
                                                    )}
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Pour préserver l'historique scolaire, les notes et les bulletins officiels, la suppression définitive est protégée. Vous pouvez <strong>désactiver la classe</strong> pour qu'elle n'apparaisse plus dans les activités courantes.
                                                </p>
                                            </div>
                                        ),
                                        onConfirm: () => {
                                            if (item.is_active) {
                                                router.patch(`/administration/classes/${item.id}/toggle-active`, {}, { preserveScroll: true });
                                            }
                                        },
                                    });
                                }}
                                className="size-8 rounded-lg bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100 hover:text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 cursor-pointer shadow-xs"
                            >
                                <Lock className="size-4" />
                                <span className="sr-only">Suppression verrouillée</span>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title="Supprimer la classe"
                                onClick={() => {
                                    confirmDelete({
                                        title: "Supprimer la classe",
                                        itemType: "la classe",
                                        itemName: item.nom,
                                        description: "Cette classe est vierge (aucune matière attribuée, aucun élève inscrit). Elle sera définitivement supprimée.",
                                        onConfirm: () => router.delete(`/administration/classes/${item.id}`),
                                    });
                                }}
                                className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 hover:text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                            >
                                <Trash2 className="size-4" />
                                <span className="sr-only">Supprimer</span>
                            </Button>
                        )}
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
                    resetEtablissementForm();
                },
            });
        } else {
            etablissementForm.post('/administration/etablissements', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEtablissementModalOpen(false);
                    resetEtablissementForm();
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

    /**
     * Ouvre la modale pour créer une nouvelle série pour l'établissement actif.
     */
    const handleOpenCreateSerie = () => {
        setEditingSerie(null);
        serieForm.clearErrors();
        serieForm.setData({
            etablissement_id: selectedEtablissement?.id || 0,
            nom: '',
        });
        setIsSerieModalOpen(true);
    };

    /**
     * Ouvre la modale pour modifier une série existante.
     *
     * @param {SerieItem} s La série à éditer.
     */
    const handleOpenEditSerie = (s: SerieItem) => {
        setEditingSerie(s);
        serieForm.clearErrors();
        serieForm.setData({
            etablissement_id: s.etablissement_id,
            nom: s.nom,
        });
        setIsSerieModalOpen(true);
    };

    /**
     * Soumission du formulaire d'enregistrement ou de modification d'une série.
     */
    const submitSerie = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingSerie) {
            serieForm.put(`/administration/series/${editingSerie.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSerieModalOpen(false);
                    setEditingSerie(null);
                    serieForm.reset();
                },
            });
        } else {
            serieForm.post('/administration/series', {
                preserveScroll: true,
                onSuccess: () => {
                    // Présélectionne directement la nouvelle série dans la modale de classe si ouverte
                    if (isClasseModalOpen && serieForm.data.nom) {
                        classeForm.setData('serie', serieForm.data.nom.toUpperCase());
                    }
                    setIsSerieModalOpen(false);
                    setEditingSerie(null);
                    serieForm.reset();
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

                    {/* Fil d'Ariane interactif (Approche A) harmonisé avec style contrasté */}
                    <nav className="flex items-center flex-wrap gap-2 text-xs text-slate-700 dark:text-slate-300 p-3.5 rounded-2xl bg-gradient-to-r from-slate-200/90 via-slate-100/95 to-slate-200/80 dark:from-slate-850 dark:via-slate-800 dark:to-slate-850 border-2 border-slate-300/90 dark:border-slate-700 shadow-xs">
                        <button
                            type="button"
                            onClick={() => handleNavigateBreadcrumb({})}
                            className="flex items-center gap-1.5 font-semibold text-slate-800 dark:text-slate-200 hover:text-primary transition-colors cursor-pointer"
                        >
                            <Home className="size-3.5 text-indigo-600 dark:text-indigo-400" />
                            <span>{institution.nom}</span>
                        </button>

                        {breadcrumbs.slice(1).map((step, idx) => (
                            <React.Fragment key={idx}>
                                <ChevronRight className="size-3 text-slate-400 dark:text-slate-500" />
                                <button
                                    type="button"
                                    onClick={() => handleNavigateBreadcrumb(step.params)}
                                    className={`transition-colors cursor-pointer ${
                                        idx === breadcrumbs.length - 2
                                            ? 'text-slate-900 dark:text-white font-bold bg-white dark:bg-slate-900 px-2.5 py-0.5 rounded-lg border border-slate-300 dark:border-slate-700 shadow-xs'
                                            : 'font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
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
                        {/* Fiche d'identité de l'institution avec en-tête contrasté */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            {/* Bandeau d'en-tête contrasté */}
                            <div className="bg-gradient-to-r from-slate-200/95 via-indigo-100/70 to-slate-200/80 dark:from-slate-800 dark:via-indigo-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white font-bold shadow-xs shrink-0">
                                        <School className="size-4" />
                                    </div>
                                    <div>
                                        <span className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                            Groupe Scolaire & Institution Faîtière
                                        </span>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Organisme faîtier, tutelles administratives et siège social de référence
                                        </p>
                                    </div>
                                </div>

                                {can.institutions_edit && (
                                    <Button
                                        size="sm"
                                        asChild
                                        className="gap-2 cursor-pointer bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm text-xs h-9"
                                    >
                                        <Link href="/administration/institution/edit">
                                            <Edit2 className="size-3.5" />
                                            <span>Modifier l'institution</span>
                                        </Link>
                                    </Button>
                                )}
                            </div>

                            {/* Corps de la fiche */}
                            <div className="p-6 bg-card">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="flex items-start gap-4 sm:gap-5 max-w-3xl">
                                        {institution.logo && (
                                            <div className="size-16 sm:size-20 shrink-0 rounded-2xl border border-border/80 bg-slate-50 dark:bg-slate-900 p-1.5 shadow-xs flex items-center justify-center overflow-hidden">
                                                <img
                                                    src={institution.logo}
                                                    alt={`Logo de ${institution.nom}`}
                                                    className="max-h-full max-w-full object-contain"
                                                />
                                            </div>
                                        )}

                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {institution.sigle && (
                                                    <Badge variant="secondary" className="font-mono text-xs font-bold text-primary">
                                                        {institution.sigle}
                                                    </Badge>
                                                )}
                                                <h2 className="text-xl sm:text-2xl font-bold text-foreground">{institution.nom}</h2>
                                            </div>
                                            {institution.nom_en && (
                                                <p className="text-xs sm:text-sm text-muted-foreground italic">{institution.nom_en}</p>
                                            )}
                                            {institution.devise && (
                                                <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400 pt-1">
                                                    « {institution.devise} » {institution.devise_en ? `/ "${institution.devise_en}"` : ''}
                                                </p>
                                            )}
                                        </div>
                                    </div>
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
                        </div>

                        {/* Liste des campus et établissements encadrée dans une carte avec bandeau d'en-tête contrasté */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-emerald-100/70 to-slate-200/80 dark:from-slate-800 dark:via-emerald-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white font-bold shadow-xs shrink-0">
                                        <Building2 className="size-4" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                            Campus & Établissements Scolaires
                                        </h3>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Cliquez sur la flèche verte d'un établissement pour explorer ses sections, cycles et classes.
                                        </p>
                                    </div>
                                </div>

                                {can.etablissements_manage && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={handleOpenCreateEtablissement}
                                        className="gap-2 cursor-pointer bg-emerald-600 text-white hover:bg-emerald-700 shadow-xs text-xs h-9"
                                    >
                                        <Plus className="size-4" />
                                        <span>Ajouter un Établissement</span>
                                    </Button>
                                )}
                            </div>

                            <div className="p-5 sm:p-6 bg-card">
                                <DataTable
                                    columns={etablissementColumns}
                                    data={etablissements}
                                    searchPlaceholder="Rechercher un établissement par code ou nom..."
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* 3. NIVEAU 2 : SECTIONS DE L'ÉTABLISSEMENT SÉLECTIONNÉ */}
                {selectedEtablissement && !selectedSection && (
                    <div className="space-y-6">
                        {/* En-tête de l'établissement actif */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-emerald-100/70 to-slate-200/80 dark:from-slate-800 dark:via-emerald-950/40 dark:to-slate-800/90 px-6 py-4.5 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3.5">
                                    <div className="size-10 rounded-xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white font-bold text-sm flex items-center justify-center shadow-xs shrink-0">
                                        <Building2 className="size-5" />
                                    </div>

                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs font-bold text-emerald-800 dark:text-emerald-300 bg-white dark:bg-slate-900 px-2.5 py-0.5 rounded-md border border-slate-300 dark:border-slate-700 shadow-xs">
                                                {selectedEtablissement.code}
                                            </span>
                                            <h2 className="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                                {selectedEtablissement.nom}
                                            </h2>
                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                                Établissement Actif
                                            </span>
                                        </div>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Chef d'établissement : <span className="font-medium text-slate-900 dark:text-white">{selectedEtablissement.nom_chef_etablissement || 'Non désigné'}</span> • Statut : <span className="font-medium text-slate-900 dark:text-white">{selectedEtablissement.statut_juridique.replace('_', ' ')}</span>
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    {can.structures_manage && (
                                        <>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={handleOpenCreateSerie}
                                                className="gap-2 cursor-pointer border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs h-9"
                                            >
                                                <Tag className="size-3.5 text-primary" />
                                                <span>Gérer les Séries ({series.length})</span>
                                            </Button>

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
                                                className="gap-2 cursor-pointer bg-emerald-600 text-white hover:bg-emerald-700 text-xs h-9"
                                            >
                                                <Plus className="size-4" />
                                                <span>Ajouter une Section</span>
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Tableau des sections encadré dans une carte avec bandeau d'en-tête */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-teal-100/70 to-slate-200/80 dark:from-slate-800 dark:via-teal-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-emerald-700 text-white font-bold shadow-xs shrink-0">
                                        <Layers className="size-4" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                            Sections d'Enseignement (Sous-systèmes)
                                        </h3>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Sous-systèmes éducatifs francophone, anglophone ou technique de l'établissement.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="p-5 sm:p-6 bg-card">
                                <DataTable
                                    columns={sectionColumns}
                                    data={sections}
                                    searchPlaceholder="Rechercher une section..."
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* 4. NIVEAU 3 : CYCLES DE LA SECTION SÉLECTIONNÉE */}
                {selectedSection && !selectedCycle && (
                    <div className="space-y-6">
                        {/* En-tête de la section active */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-teal-100/70 to-slate-200/80 dark:from-slate-800 dark:via-teal-950/40 dark:to-slate-800/90 px-6 py-4.5 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3.5">
                                    <div className="size-10 rounded-xl bg-gradient-to-br from-teal-600 to-emerald-700 text-white font-bold text-sm flex items-center justify-center shadow-xs shrink-0">
                                        <Layers className="size-5" />
                                    </div>

                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs font-bold text-teal-800 dark:text-teal-300 bg-white dark:bg-slate-900 px-2.5 py-0.5 rounded-md border border-slate-300 dark:border-slate-700 shadow-xs uppercase">
                                                {selectedSection.langue}
                                            </span>
                                            <h2 className="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                                {selectedSection.nom}
                                            </h2>
                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-950/60 text-teal-800 dark:text-teal-300 border border-teal-300 dark:border-teal-700">
                                                Section Active
                                            </span>
                                        </div>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Sous-système d'enseignement de l'établissement <span className="font-medium text-slate-900 dark:text-white">{selectedEtablissement?.nom}</span>
                                        </p>
                                    </div>
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
                                                diplome_fin_cycle: 'AUCUN',
                                                ordre: (cycles.length || 0) + 1,
                                            });
                                            setIsCycleModalOpen(true);
                                        }}
                                        className="gap-2 cursor-pointer bg-primary text-primary-foreground hover:bg-primary/90 text-xs h-9"
                                    >
                                        <Plus className="size-4" />
                                        <span>Ajouter un Cycle</span>
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* Tableau des cycles encadré dans une carte avec bandeau d'en-tête */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-indigo-100/70 to-slate-200/80 dark:from-slate-800 dark:via-indigo-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white font-bold shadow-xs shrink-0">
                                        <GraduationCap className="size-4" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                            Cycles d'Enseignement
                                        </h3>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Cycles officiels (1er Cycle, 2nd Cycle...) et diplômes de fin de cursus préparés.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="p-5 sm:p-6 bg-card">
                                <DataTable
                                    columns={cycleColumns}
                                    data={cycles}
                                    searchPlaceholder="Rechercher un cycle..."
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* 5. NIVEAU 4 : NIVEAUX DU CYCLE SÉLECTIONNÉ */}
                {selectedCycle && !selectedNiveau && (
                    <div className="space-y-6">
                        {/* En-tête du cycle actif */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-indigo-100/70 to-slate-200/80 dark:from-slate-800 dark:via-indigo-950/40 dark:to-slate-800/90 px-6 py-4.5 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3.5">
                                    <div className="size-10 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white font-bold text-sm flex items-center justify-center shadow-xs shrink-0">
                                        <GraduationCap className="size-5" />
                                    </div>

                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs font-bold text-indigo-800 dark:text-indigo-300 bg-white dark:bg-slate-900 px-2.5 py-0.5 rounded-md border border-slate-300 dark:border-slate-700 shadow-xs">
                                                {selectedCycle.code}
                                            </span>
                                            <h2 className="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                                {selectedCycle.nom}
                                            </h2>
                                            {selectedCycle.diplome_fin_cycle && (
                                                <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded-md bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700">
                                                    {selectedCycle.diplome_fin_cycle}
                                                </span>
                                            )}
                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700">
                                                Cycle Actif
                                            </span>
                                        </div>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Cycle d'enseignement au sein de la section <span className="font-medium text-slate-900 dark:text-white">{selectedSection?.nom}</span>
                                        </p>
                                    </div>
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
                                        className="gap-2 cursor-pointer bg-primary text-primary-foreground hover:bg-primary/90 text-xs h-9"
                                    >
                                        <Plus className="size-4" />
                                        <span>Ajouter un Niveau</span>
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* Tableau des niveaux encadré dans une carte avec bandeau d'en-tête */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-violet-100/70 to-slate-200/80 dark:from-slate-800 dark:via-violet-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 to-purple-700 text-white font-bold shadow-xs shrink-0">
                                        <BookOpen className="size-4" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                            Niveaux d'Étude (Paliers académiques)
                                        </h3>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Paliers académiques et hiérarchie de progression des élèves.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="p-5 sm:p-6 bg-card">
                                <DataTable
                                    columns={niveauColumns}
                                    data={niveaux}
                                    searchPlaceholder="Rechercher un niveau..."
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* 6. NIVEAU 5 : CLASSES DU NIVEAU SÉLECTIONNÉ */}
                {selectedNiveau && (
                    <div className="space-y-6">
                        {/* En-tête du niveau actif */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-violet-100/70 to-slate-200/80 dark:from-slate-800 dark:via-violet-950/40 dark:to-slate-800/90 px-6 py-4.5 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3.5">
                                    <div className="size-10 rounded-xl bg-gradient-to-br from-violet-600 to-purple-700 text-white font-bold text-sm flex items-center justify-center shadow-xs shrink-0">
                                        <BookOpen className="size-5" />
                                    </div>

                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs font-bold text-violet-800 dark:text-violet-300 bg-white dark:bg-slate-900 px-2.5 py-0.5 rounded-md border border-slate-300 dark:border-slate-700 shadow-xs">
                                                #{selectedNiveau.ordre}
                                            </span>
                                            <h2 className="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                                Niveau {selectedNiveau.nom}
                                            </h2>
                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-violet-100 dark:bg-violet-950/60 text-violet-800 dark:text-violet-300 border border-violet-300 dark:border-violet-700">
                                                Niveau Actif
                                            </span>
                                        </div>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Paliers d'enseignement au sein du cycle <span className="font-medium text-slate-900 dark:text-white">{selectedCycle?.nom}</span>
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    {can.structures_manage && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={handleOpenCreateSerie}
                                            className="gap-2 cursor-pointer border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs h-9"
                                        >
                                            <Tag className="size-3.5 text-primary" />
                                            <span>Gérer les Séries ({series.length})</span>
                                        </Button>
                                    )}

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
                                                    serie: 'GENERAL',
                                                    capacite_max: 50,
                                                });
                                                setIsClasseModalOpen(true);
                                            }}
                                            className="gap-2 cursor-pointer bg-primary text-primary-foreground hover:bg-primary/90 text-xs h-9"
                                        >
                                            <Plus className="size-4" />
                                            <span>Créer une Classe</span>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Tableau des classes encadré dans une carte avec bandeau d'en-tête */}
                        <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                            <div className="bg-gradient-to-r from-slate-200/95 via-sky-100/70 to-slate-200/80 dark:from-slate-800 dark:via-sky-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-600 to-indigo-600 text-white font-bold shadow-xs shrink-0">
                                        <Users className="size-4" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                            Classes d'Élèves
                                        </h3>
                                        <p className="text-xs text-slate-600 dark:text-slate-400">
                                            Divisions pédagogiques, effectifs maximaux et cours dispensés.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="p-5 sm:p-6 bg-card">
                                <DataTable
                                    columns={classeColumns}
                                    data={classes}
                                    searchPlaceholder="Rechercher une classe..."
                                />
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* =========================================================================
             * MODALES CRUD
             * ========================================================================= */}



            {/* Modale Établissement */}
            <Dialog
                open={isEtablissementModalOpen}
                onOpenChange={(open) => {
                    setIsEtablissementModalOpen(open);
                    if (!open) {
                        resetEtablissementForm();
                    }
                }}
            >
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
                                <Label className="text-xs font-semibold">Chef d'Établissement</Label>
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
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setIsEtablissementModalOpen(false);
                                    resetEtablissementForm();
                                }}
                            >
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
                        {/* Champ 1 : Nom de la Section */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">Nom de la Section *</Label>
                            <Input
                                value={sectionForm.data.nom}
                                onChange={(e) => {
                                    sectionForm.setData('nom', e.target.value);
                                    if (sectionForm.errors.nom) {
                                        sectionForm.clearErrors('nom');
                                    }
                                }}
                                placeholder="ex: Section Francophone, Section Technique, etc."
                                required
                                autoFocus
                            />
                            {sectionForm.errors.nom && (
                                <p className="text-[11px] text-rose-500 font-medium">{sectionForm.errors.nom}</p>
                            )}
                            <p className="text-[11px] text-muted-foreground">
                                Saisissez l'intitulé de la section souhaitée (doit être unique pour cet établissement).
                            </p>
                        </div>

                        {/* Champ 2 : Langue d'enseignement */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">Langue d'enseignement *</Label>
                            <Select
                                value={sectionForm.data.langue}
                                onValueChange={(val) => {
                                    sectionForm.setData('langue', val);
                                    if (sectionForm.errors.langue) {
                                        sectionForm.clearErrors('langue');
                                    }
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner la langue..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fr">Français (FR)</SelectItem>
                                    <SelectItem value="en">Anglais (EN)</SelectItem>
                                </SelectContent>
                            </Select>
                            {sectionForm.errors.langue && (
                                <p className="text-[11px] text-rose-500 font-medium">{sectionForm.errors.langue}</p>
                            )}
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
                        <DialogDescription className="text-xs text-muted-foreground">
                            Définissez le cycle d'enseignement, son diplôme officiel préparé et sa position dans le cursus.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitCycle} className="space-y-4 pt-2">
                        {/* Champ 1 : Nom du Cycle */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">Nom du Cycle *</Label>
                            <Input
                                value={cycleForm.data.nom}
                                onChange={(e) => {
                                    cycleForm.setData('nom', e.target.value);
                                    if (cycleForm.errors.nom) {
                                        cycleForm.clearErrors('nom');
                                    }
                                }}
                                placeholder="ex: Premier Cycle, Second Cycle, Cycle Primaire..."
                                required
                                autoFocus
                            />
                            {cycleForm.errors.nom && (
                                <p className="text-[11px] text-rose-500 font-medium">{cycleForm.errors.nom}</p>
                            )}
                            <p className="text-[11px] text-muted-foreground">
                                Le nom du cycle doit être unique au sein de cet établissement.
                            </p>
                        </div>

                        {/* Champ 2 : Diplôme Officiel Préparé avec zone de recherche instantanée */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">Diplôme Officiel Préparé</Label>
                            <SearchableSelect
                                options={DIPLOMES_CYCLE_CAMEROUN}
                                value={cycleForm.data.diplome_fin_cycle || 'AUCUN'}
                                onChange={(val) => {
                                    cycleForm.setData('diplome_fin_cycle', val === 'AUCUN' ? '' : val);
                                }}
                                placeholder="Sélectionner ou rechercher un diplôme..."
                                searchPlaceholder="Rechercher par nom, sigle ou ordre (ex: BEPC, Bac, CAP, CEP, BTS...)"
                            />
                            <p className="text-[11px] text-muted-foreground">
                                Classés par ordre d'enseignement (Secondaire Général, Technique, Primaire...) avec filtre de recherche en temps réel.
                            </p>
                        </div>

                        {/* Champ 3 : Ordre chronologique */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">Ordre chronologique *</Label>
                            <Input
                                type="number"
                                min={1}
                                value={cycleForm.data.ordre}
                                onChange={(e) => cycleForm.setData('ordre', parseInt(e.target.value) || 1)}
                                required
                            />
                            {cycleForm.errors.ordre && (
                                <p className="text-[11px] text-rose-500 font-medium">{cycleForm.errors.ordre}</p>
                            )}
                            <p className="text-[11px] text-muted-foreground">
                                Séquence d'enchaînement des cycles dans le cursus (ex: 1 pour Premier Cycle, 2 pour Second Cycle).
                            </p>
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
                        <DialogDescription className="text-xs text-muted-foreground">
                            Configurez le niveau d'enseignement et son ordre chronologique de progression.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitNiveau} className="space-y-4 pt-2">
                        {/* Champ 1 : Nom du Niveau */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">Nom du Niveau *</Label>
                            <Input
                                value={niveauForm.data.nom}
                                onChange={(e) => {
                                    niveauForm.setData('nom', e.target.value);
                                    if (niveauForm.errors.nom) {
                                        niveauForm.clearErrors('nom');
                                    }
                                }}
                                placeholder="ex: Sixième, Troisième, Form 1, Terminale, CM2..."
                                required
                                autoFocus
                            />
                            {niveauForm.errors.nom && (
                                <p className="text-[11px] text-rose-500 font-medium">{niveauForm.errors.nom}</p>
                            )}
                            <p className="text-[11px] text-muted-foreground">
                                Le nom du niveau doit être unique au sein de cet établissement.
                            </p>
                        </div>

                        {/* Champ 2 : Ordre de progression */}
                        <div className="space-y-1.5">
                            <Label className="text-xs font-semibold">Ordre de progression (1, 2, 3...) *</Label>
                            <Input
                                type="number"
                                min={1}
                                value={niveauForm.data.ordre}
                                onChange={(e) => niveauForm.setData('ordre', parseInt(e.target.value) || 1)}
                                required
                            />
                            {niveauForm.errors.ordre && (
                                <p className="text-[11px] text-rose-500 font-medium">{niveauForm.errors.ordre}</p>
                            )}
                            <p className="text-[11px] text-muted-foreground">
                                Définit la hiérarchie du cursus et le moteur de passage en classe supérieure.
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
                        <DialogDescription>
                            Configurez le code court, l'intitulé et la série d'orientation de la classe.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitClasse} className="space-y-4 pt-2">
                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1">
                                <Label className="text-xs font-semibold">Code *</Label>
                                <Input
                                    value={classeForm.data.code}
                                    onChange={(e) => classeForm.setData('code', e.target.value)}
                                    placeholder="ex: TLE-A4-ALL"
                                    required
                                />
                                {classeForm.errors.code && (
                                    <p className="text-xs text-rose-500 font-medium">{classeForm.errors.code}</p>
                                )}
                            </div>
                            <div className="col-span-2 space-y-1">
                                <Label className="text-xs font-semibold">Nom de la Classe *</Label>
                                <Input
                                    value={classeForm.data.nom}
                                    onChange={(e) => classeForm.setData('nom', e.target.value)}
                                    placeholder="ex: Terminale A4 Allemand"
                                    required
                                />
                                {classeForm.errors.nom && (
                                    <p className="text-xs text-rose-500 font-medium">{classeForm.errors.nom}</p>
                                )}
                            </div>
                        </div>

                        {/* Sélecteur de Série avec GENERAL par défaut */}
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <Label className="text-xs font-semibold">
                                    Série de la classe
                                </Label>
                                <button
                                    type="button"
                                    onClick={handleOpenCreateSerie}
                                    className="text-xs text-primary hover:underline font-semibold cursor-pointer inline-flex items-center gap-1"
                                >
                                    <Plus className="size-3" />
                                    Ajouter / Gérer les séries
                                </button>
                            </div>

                            <Select
                                value={classeForm.data.serie || 'GENERAL'}
                                onValueChange={(val) => classeForm.setData('serie', val)}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Sélectionner une série..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {series.map((s) => (
                                        <SelectItem key={s.id} value={s.nom}>
                                            <span className="font-mono font-bold mr-1.5">{s.nom}</span>
                                            {s.nom === 'GENERAL' && (
                                                <span className="text-xs text-muted-foreground italic">(Par défaut)</span>
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {classeForm.errors.serie && (
                                <p className="text-xs text-rose-500 font-medium">{classeForm.errors.serie}</p>
                            )}
                        </div>

                        {/* Capacité maximale */}
                        <div className="space-y-1">
                            <Label className="text-xs font-semibold">Capacité maximale de la salle (élèves) *</Label>
                            <Input
                                type="number"
                                min={1}
                                max={150}
                                value={classeForm.data.capacite_max}
                                onChange={(e) => classeForm.setData('capacite_max', parseInt(e.target.value) || 50)}
                                required
                            />
                        </div>

                        <DialogFooter className="pt-2">
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

            {/* Modale de Gestion des Séries de l'Établissement (champ unique nom) */}
            <Dialog open={isSerieModalOpen} onOpenChange={setIsSerieModalOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Tag className="size-5 text-primary" />
                            <span>{editingSerie ? 'Modifier la Série' : 'Séries de l’Établissement'}</span>
                        </DialogTitle>
                        <DialogDescription>
                            Séries proposées (GENERAL par défaut, C, D, E, TI, ALLEMAND, ESPAGNOL, A4 ALL, A4 ESP). Ajoutez-en d'autres au besoin.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 pt-1">
                        {/* Formulaire d'ajout rapide (uniquement le nom) */}
                        <form onSubmit={submitSerie} className="space-y-2 bg-muted/40 p-3.5 rounded-xl border border-border/80">
                            <div className="flex items-center justify-between">
                                <Label className="text-xs font-bold uppercase tracking-wider text-foreground">
                                    {editingSerie ? 'Modifier le nom de la série' : 'Ajouter une nouvelle série'}
                                </Label>
                                {editingSerie && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setEditingSerie(null);
                                            serieForm.reset();
                                        }}
                                        className="text-[11px] text-muted-foreground hover:text-foreground underline cursor-pointer"
                                    >
                                        Mode ajout
                                    </button>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <Input
                                    value={serieForm.data.nom}
                                    onChange={(e) => serieForm.setData('nom', e.target.value.toUpperCase())}
                                    placeholder="ex: SES, F4, A4 ALL..."
                                    required
                                    className="font-mono font-bold"
                                />
                                {editingSerie && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setEditingSerie(null);
                                            serieForm.reset();
                                        }}
                                    >
                                        Annuler
                                    </Button>
                                )}
                                <Button type="submit" size="sm" disabled={serieForm.processing} className="gap-1.5 shrink-0 cursor-pointer">
                                    <Plus className="size-3.5" />
                                    <span>{editingSerie ? 'Modifier' : 'Ajouter'}</span>
                                </Button>
                            </div>
                            {serieForm.errors.nom && (
                                <p className="text-[11px] text-rose-500 font-medium">{serieForm.errors.nom}</p>
                            )}
                        </form>

                        {/* Liste des séries configurées */}
                        <div className="space-y-2">
                            <h4 className="text-xs font-semibold text-muted-foreground flex items-center justify-between">
                                <span>Séries enregistrées ({series.length})</span>
                            </h4>
                            {series.length === 0 ? (
                                <p className="text-xs text-muted-foreground italic py-3 text-center border border-dashed rounded-lg">
                                    Aucune série enregistrée pour cet établissement.
                                </p>
                            ) : (
                                <div className="max-h-56 overflow-y-auto space-y-1.5 pr-1">
                                    {series.map((s) => (
                                        <div
                                            key={s.id}
                                            className="flex items-center justify-between p-2 rounded-lg bg-card border border-border/80 hover:bg-muted/30 transition-colors"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline" className="font-mono text-xs font-bold text-primary bg-primary/10">
                                                    {s.nom}
                                                </Badge>
                                                {s.nom === 'GENERAL' && (
                                                    <span className="text-[11px] text-muted-foreground italic">(Par défaut)</span>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleOpenEditSerie(s)}
                                                    className="size-7 text-blue-600 hover:bg-blue-50 cursor-pointer"
                                                    title="Modifier cette série"
                                                >
                                                    <Edit2 className="size-3.5" />
                                                    <span className="sr-only">Modifier</span>
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        confirmDelete({
                                                            title: "Supprimer la série",
                                                            itemType: "la série",
                                                            itemName: s.nom,
                                                            description: "Cette série ne doit pas être assignée à des classes existantes.",
                                                            onConfirm: () => router.delete(`/administration/series/${s.id}`),
                                                        });
                                                    }}
                                                    className="size-7 text-rose-600 hover:bg-rose-50 cursor-pointer"
                                                    title="Supprimer cette série"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    <span className="sr-only">Supprimer</span>
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="pt-2">
                        <Button type="button" variant="outline" onClick={() => setIsSerieModalOpen(false)}>
                            Fermer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Boîte modale globale de confirmation de suppression */}
            <ConfirmDialog />
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

import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    BookOpen,
    Plus,
    Edit2,
    Trash2,
    Layers,
    SlidersHorizontal,
    Calculator,
    AlertTriangle,
    CheckCircle2,
    Sparkles,
    Shield,
    ArrowUpDown,
    Check,
    X,
    Filter,
    Building2,
    Globe,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { usePermission } from '@/hooks/use-permission';
import { useConfirmDelete } from '@/components/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
 * Interface représentant un Groupe de Matières (Bloc Pédagogique).
 */
interface GroupeMatiereItem {
    id: number;
    etablissement_id?: number | null;
    etablissement?: {
        id: number;
        nom: string;
        code?: string;
    } | null;
    code: string;
    nom: string;
    nom_en?: string | null;
    couleur?: string | null;
    ordre: number;
    matiere_niveaux_count?: number;
}

/**
 * Interface représentant une Matière dans le Catalogue Général.
 */
interface MatiereItem {
    id: number;
    etablissement_id?: number | null;
    etablissement?: {
        id: number;
        nom: string;
        code?: string;
    } | null;
    code: string;
    nom: string;
    nom_en?: string | null;
    libelle_bulletin?: string | null;
    libelle_bulletin_en?: string | null;
    is_active: boolean;
    matiere_niveaux_count?: number;
}

/**
 * Interface représentant un Établissement scolaire.
 */
interface EtablissementItem {
    id: number;
    nom: string;
    code: string;
}

/**
 * Interface représentant un Niveau académique.
 */
interface NiveauItem {
    id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    ordre: number;
    etablissement_id?: number | null;
    etablissement?: {
        id: number;
        nom: string;
        code?: string;
    };
    cycle?: {
        id: number;
        nom: string;
        section?: {
            id: number;
            nom: string;
            etablissement?: {
                id: number;
                nom: string;
                code?: string;
            };
        };
    };
}

/**
 * Interface représentant un Coefficient par Niveau et Série.
 */
interface CoefficientItem {
    id: number;
    niveau_id: number;
    matiere_id: number;
    groupe_matiere_id: number;
    serie: string;
    coefficient: number;
    note_max: number;
    note_eliminatoire?: number | null;
    is_facultative: boolean;
    is_comptabilisee: boolean;
    matiere: MatiereItem;
    groupe_matiere: GroupeMatiereItem;
}

/**
 * Propriétés transmises au composant principal MatiereIndex.
 */
interface MatiereIndexProps {
    etablissements?: EtablissementItem[];
    selectedEtablissement?: EtablissementItem | null;
    selectedEtablissementId?: number | null;
    groupes: GroupeMatiereItem[];
    matieres: MatiereItem[];
    niveaux: NiveauItem[];
    selectedNiveau: NiveauItem | null;
    seriesDisponibles: string[];
    selectedSerie: string;
    coefficients: CoefficientItem[];
    can: {
        matieres_manage: boolean;
    };
    flash?: {
        success?: string;
        error?: string;
    };
}

/**
 * Page principale de gestion des Matières & Coefficients (Module 4 Gesco).
 *
 * Organisée en 3 vues complémentaires :
 * 1. Grille des Coefficients (filtrable par niveau & série avec calculs en temps réel).
 * 2. Catalogue Général des Matières (référentiel central bilingue FR/EN).
 * 3. Blocs Pédagogiques (groupes de matières MINESEC/MINEDUB avec ordre sur bulletin).
 *
 * @param {MatiereIndexProps} props Propriétés du composant.
 * @returns {JSX.Element} Vue complète avec tables TanStack et modales.
 */
export default function MatiereIndex({
    etablissements = [],
    selectedEtablissement = null,
    selectedEtablissementId = null,
    groupes,
    matieres,
    niveaux,
    selectedNiveau,
    seriesDisponibles,
    selectedSerie,
    coefficients,
    can,
    flash,
}: MatiereIndexProps) {
    // 1. Détection robuste des privilèges d'administration via usePermission
    const { can: checkPerm, isSuperAdmin } = usePermission();
    const canManage = Boolean(can?.matieres_manage || checkPerm('matieres.manage') || isSuperAdmin);

    // Boîte de dialogue unifiée de confirmation de suppression
    const { confirmDelete, ConfirmDialog } = useConfirmDelete();

    // Toasts de notification en haut à droite avec style vert conforme aux règles UI
    useEffect(() => {
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

    // 2. Navigation par onglets
    const [activeTab, setActiveTab] = useState<'coefficients' | 'catalogue' | 'groupes'>('coefficients');

    // 3. États des modales
    const [isCoefficientModalOpen, setIsCoefficientModalOpen] = useState(false);
    const [editingCoefficient, setEditingCoefficient] = useState<CoefficientItem | null>(null);

    const [isMatiereModalOpen, setIsMatiereModalOpen] = useState(false);
    const [editingMatiere, setEditingMatiere] = useState<MatiereItem | null>(null);

    const [isGroupeModalOpen, setIsGroupeModalOpen] = useState(false);
    const [editingGroupe, setEditingGroupe] = useState<GroupeMatiereItem | null>(null);

    // 4. Formulaire pour Coefficient
    const coefficientForm = useForm({
        niveau_id: selectedNiveau?.id || 0,
        matiere_id: 0,
        groupe_matiere_id: 0,
        serie: 'Toutes',
        coefficient: 2.0,
        note_max: 20.0,
        note_eliminatoire: '' as string | number,
        is_facultative: false,
        is_comptabilisee: true,
    });

    // 5. Formulaire pour Matière
    const matiereForm = useForm({
        etablissement_id: (selectedEtablissement?.id || null) as number | null,
        code: '',
        nom: '',
        nom_en: '',
        libelle_bulletin: '',
        libelle_bulletin_en: '',
        is_active: true,
    });

    // 6. Formulaire pour Groupe de matières
    const groupeForm = useForm({
        etablissement_id: (selectedEtablissement?.id || null) as number | null,
        code: '',
        nom: '',
        nom_en: '',
        couleur: 'blue',
        ordre: 1,
    });

    // Synchronisation du niveau dans le formulaire lors du changement de sélection
    useEffect(() => {
        if (selectedNiveau) {
            coefficientForm.setData('niveau_id', selectedNiveau.id);
        }
    }, [selectedNiveau]);

    // Handlers d'ouverture rapide des modales
    const handleOpenAddCoefficient = () => {
        const targetNiveau = selectedNiveau || niveaux[0];
        setEditingCoefficient(null);
        coefficientForm.reset();
        coefficientForm.setData({
            niveau_id: targetNiveau?.id || 0,
            matiere_id: matieres[0]?.id || 0,
            groupe_matiere_id: groupes[0]?.id || 0,
            serie: selectedSerie === 'Toutes' ? 'Toutes' : selectedSerie,
            coefficient: 2.0,
            note_max: 20.0,
            note_eliminatoire: '',
            is_facultative: false,
            is_comptabilisee: true,
        });
        setIsCoefficientModalOpen(true);
    };

    const handleOpenAddMatiere = () => {
        setEditingMatiere(null);
        matiereForm.reset();
        matiereForm.setData({
            etablissement_id: selectedEtablissement?.id || null,
            code: '',
            nom: '',
            nom_en: '',
            libelle_bulletin: '',
            libelle_bulletin_en: '',
            is_active: true,
        });
        setIsMatiereModalOpen(true);
    };

    const handleOpenAddGroupe = () => {
        setEditingGroupe(null);
        groupeForm.reset();
        groupeForm.setData({
            etablissement_id: selectedEtablissement?.id || null,
            code: '',
            nom: '',
            nom_en: '',
            couleur: 'blue',
            ordre: groupes.length + 1,
        });
        setIsGroupeModalOpen(true);
    };

    /**
     * Détermine le nom lisible de l'établissement rattaché à un niveau scolaire.
     * Explore en cascade l'association directe ou la chaîne cycle -> section -> établissement.
     *
     * @param {NiveauItem | null | undefined} niv Instance du niveau scolaire.
     * @returns {string} Nom de l'établissement d'attache.
     */
    const getEtablissementName = (niv?: NiveauItem | null): string => {
        if (!niv) return '';
        return niv.etablissement?.nom || niv.cycle?.section?.etablissement?.nom || '';
    };

    /**
     * Filtre les données pédagogiques selon le campus / établissement sélectionné.
     *
     * @param {number | string} etablissementId Identifiant de l'établissement ou 'all'.
     */
    const handleSelectEtablissement = (etablissementId: number | string) => {
        router.get(
            '/administration/matieres',
            {
                ...(etablissementId !== 'all' ? { etablissement_id: etablissementId } : {}),
                serie: 'Toutes',
            },
            { preserveScroll: true, preserveState: true }
        );
    };

    /**
     * Filtre la grille des coefficients selon le niveau académique sélectionné.
     * Conserve le contexte de l'établissement actif si renseigné.
     *
     * @param {string} niveauId Identifiant du niveau sélectionné.
     */
    const handleSelectNiveau = (niveauId: string) => {
        router.get(
            '/administration/matieres',
            {
                ...(selectedEtablissementId ? { etablissement_id: selectedEtablissementId } : {}),
                niveau_id: niveauId,
                serie: 'Toutes',
            },
            { preserveScroll: true, preserveState: true }
        );
    };

    /**
     * Filtre la grille des coefficients selon la série pédagogique sélectionnée.
     *
     * @param {string} serie Nom de la série (ex: 'Toutes', 'C', 'D', 'TI').
     */
    const handleSelectSerie = (serie: string) => {
        if (!selectedNiveau) return;
        router.get(
            '/administration/matieres',
            {
                ...(selectedEtablissementId ? { etablissement_id: selectedEtablissementId } : {}),
                niveau_id: selectedNiveau.id,
                serie,
            },
            { preserveScroll: true, preserveState: true }
        );
    };

    // Auto-génération des standards MINESEC/MINEDUB pour le campus actif
    const handleSeedStandards = () => {
        if (!canManage) return;
        confirmDelete({
            title: 'Initialisation du catalogue officiel',
            variant: 'primary',
            confirmLabel: 'Initialiser le catalogue',
            promptText: `Voulez-vous initialiser le catalogue officiel et les blocs standards pour ${selectedEtablissement ? selectedEtablissement.nom : "l'établissement actif"} ?`,
            description: `Les matières officielles du tronc commun et les blocs pédagogiques adaptés à l'ordre d'enseignement de l'établissement seront configurés dans le système.`,
            onConfirm: () => {
                router.post(
                    '/administration/matieres/standards',
                    {
                        etablissement_id: selectedEtablissement?.id,
                    },
                    {
                        preserveScroll: true,
                    }
                );
            },
        });
    };

    // Statistiques rapides pour le niveau sélectionné
    const statsCoefficients = useMemo(() => {
        const totalMatieres = coefficients.length;
        const totalCoefficients = coefficients.reduce((acc, curr) => acc + (curr.is_comptabilisee ? curr.coefficient : 0), 0);
        const totalFacultatives = coefficients.filter(c => c.is_facultative).length;
        const totalEliminatoires = coefficients.filter(c => c.note_eliminatoire !== null && c.note_eliminatoire !== undefined).length;
        return { totalMatieres, totalCoefficients, totalFacultatives, totalEliminatoires };
    }, [coefficients]);

    // Soumission du formulaire Coefficient
    const submitCoefficient = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...coefficientForm.data,
            note_eliminatoire: coefficientForm.data.note_eliminatoire === '' ? null : Number(coefficientForm.data.note_eliminatoire),
        };

        if (editingCoefficient) {
            router.put(`/administration/matieres/coefficients/${editingCoefficient.id}`, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsCoefficientModalOpen(false);
                    setEditingCoefficient(null);
                },
            });
        } else {
            router.post('/administration/matieres/coefficients', payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsCoefficientModalOpen(false);
                    coefficientForm.reset();
                },
            });
        }
    };

    // Soumission du formulaire Matière
    const submitMatiere = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingMatiere) {
            matiereForm.put(`/administration/matieres/catalogue/${editingMatiere.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsMatiereModalOpen(false);
                    setEditingMatiere(null);
                },
            });
        } else {
            matiereForm.post('/administration/matieres/catalogue', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsMatiereModalOpen(false);
                    matiereForm.reset();
                },
            });
        }
    };

    // Soumission du formulaire Groupe de matières
    const submitGroupe = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingGroupe) {
            groupeForm.put(`/administration/matieres/groupes/${editingGroupe.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsGroupeModalOpen(false);
                    setEditingGroupe(null);
                },
            });
        } else {
            groupeForm.post('/administration/matieres/groupes', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsGroupeModalOpen(false);
                    groupeForm.reset();
                },
            });
        }
    };

    // Helper de badges couleur pour les blocs
    const getBlocBadgeStyle = (couleur?: string | null) => {
        switch (couleur) {
            case 'blue':
                return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900/50';
            case 'emerald':
                return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/50';
            case 'amber':
                return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/50';
            case 'purple':
                return 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-900/50';
            case 'rose':
                return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/50';
            default:
                return 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900/50';
        }
    };

    // =========================================================================
    // COLONNES TANSTACK TABLE 1 : COEFFICIENTS DU NIVEAU
    // =========================================================================
    const coefficientColumns: ColumnDef<CoefficientItem>[] = [
        {
            id: 'matiere',
            accessorFn: (row) => `${row.matiere.code} ${row.matiere.nom} ${row.matiere.nom_en || ''}`,
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    className="-ml-3 h-8 text-xs font-semibold uppercase tracking-wider"
                >
                    Discipline / Matière
                    <ArrowUpDown className="ml-2 h-3.5 w-3.5" />
                </Button>
            ),
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex flex-col gap-0.5">
                        <div className="flex items-center gap-2">
                            <Badge variant="outline" className="font-mono text-xs font-semibold px-2 py-0.5">
                                {item.matiere.code}
                            </Badge>
                            <span className="font-medium text-foreground text-sm">{item.matiere.nom}</span>
                        </div>
                        {item.matiere.nom_en && (
                            <span className="text-xs text-muted-foreground italic pl-1">
                                {item.matiere.nom_en}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'groupe',
            accessorFn: (row) => row.groupe_matiere.nom,
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Bloc Pédagogique</span>,
            cell: ({ row }) => {
                const groupe = row.original.groupe_matiere;
                return (
                    <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border ${getBlocBadgeStyle(groupe.couleur)}`}>
                        {groupe.nom}
                    </span>
                );
            },
        },
        {
            accessorKey: 'serie',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Série</span>,
            cell: ({ row }) => (
                <Badge variant="secondary" className="text-xs font-normal">
                    {row.original.serie || 'Toutes'}
                </Badge>
            ),
        },
        {
            accessorKey: 'coefficient',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    className="h-8 text-xs font-semibold uppercase tracking-wider"
                >
                    Coef.
                    <ArrowUpDown className="ml-2 h-3.5 w-3.5" />
                </Button>
            ),
            cell: ({ row }) => (
                <span className="font-bold text-sm text-foreground px-2 py-1 bg-muted rounded-md inline-block min-w-10 text-center">
                    {row.original.coefficient}
                </span>
            ),
        },
        {
            accessorKey: 'note_max',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Barème Max</span>,
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground font-medium">
                    / {row.original.note_max} pts
                </span>
            ),
        },
        {
            accessorKey: 'note_eliminatoire',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Note Éliminatoire</span>,
            cell: ({ row }) => {
                const note = row.original.note_eliminatoire;
                if (note !== null && note !== undefined) {
                    return (
                        <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/50 flex items-center gap-1 w-fit text-xs">
                            <AlertTriangle className="size-3" />
                            ≤ {note}/20
                        </Badge>
                    );
                }
                return <span className="text-xs text-muted-foreground italic">Aucune</span>;
            },
        },
        {
            id: 'regles',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Règles & Bonus</span>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex flex-wrap gap-1">
                        {item.is_facultative && (
                            <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/50 text-xs">
                                Bonus &gt; 10
                            </Badge>
                        )}
                        {item.is_comptabilisee ? (
                            <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/50 text-xs">
                                Comptabilisée
                            </Badge>
                        ) : (
                            <Badge variant="outline" className="bg-muted text-muted-foreground text-xs">
                                Hors-moyenne
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right pr-2 text-xs font-semibold uppercase tracking-wider">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        {canManage && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier le coefficient"
                                    onClick={() => {
                                        setEditingCoefficient(item);
                                        coefficientForm.setData({
                                            niveau_id: item.niveau_id,
                                            matiere_id: item.matiere_id,
                                            groupe_matiere_id: item.groupe_matiere_id,
                                            serie: item.serie || 'Toutes',
                                            coefficient: item.coefficient,
                                            note_max: item.note_max,
                                            note_eliminatoire: item.note_eliminatoire ?? '',
                                            is_facultative: item.is_facultative,
                                            is_comptabilisee: item.is_comptabilisee,
                                        });
                                        setIsCoefficientModalOpen(true);
                                    }}
                                    className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-400 cursor-pointer shadow-xs"
                                >
                                    <Edit2 className="size-3.5" />
                                    <span className="sr-only">Modifier</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Retirer de la grille"
                                    onClick={() => {
                                        confirmDelete({
                                            title: 'Retirer la matière de la grille',
                                            itemType: 'la discipline',
                                            itemName: item.matiere.nom,
                                            description: `Cette matière ne sera plus enseignée pour ce niveau et ses coefficients seront retirés de la grille d'évaluation.`,
                                            confirmLabel: 'Retirer de la grille',
                                            onConfirm: () => {
                                                router.delete(`/administration/matieres/coefficients/${item.id}`, {
                                                    preserveScroll: true,
                                                });
                                            },
                                        });
                                    }}
                                    className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                                >
                                    <Trash2 className="size-3.5" />
                                    <span className="sr-only">Supprimer</span>
                                </Button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ];

    // =========================================================================
    // COLONNES TANSTACK TABLE 2 : CATALOGUE GÉNÉRAL DES MATIÈRES
    // =========================================================================
    const catalogueColumns: ColumnDef<MatiereItem>[] = [
        {
            accessorKey: 'code',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    className="-ml-3 h-8 text-xs font-semibold uppercase tracking-wider"
                >
                    Code
                    <ArrowUpDown className="ml-2 h-3.5 w-3.5" />
                </Button>
            ),
            cell: ({ row }) => (
                <Badge variant="outline" className="font-mono text-xs font-bold px-2 py-0.5">
                    {row.original.code}
                </Badge>
            ),
        },
        {
            accessorKey: 'nom',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    className="h-8 text-xs font-semibold uppercase tracking-wider"
                >
                    Intitulé Français (FR)
                    <ArrowUpDown className="ml-2 h-3.5 w-3.5" />
                </Button>
            ),
            cell: ({ row }) => (
                <span className="font-medium text-foreground text-sm">{row.original.nom}</span>
            ),
        },
        {
            accessorKey: 'nom_en',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">English Title (EN)</span>,
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground italic">
                    {row.original.nom_en || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'libelle_bulletin',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Libellé Relevé</span>,
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.libelle_bulletin || row.original.nom}
                </span>
            ),
        },
        {
            id: 'portee',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Portée / Campus</span>,
            cell: ({ row }) => {
                const etab = row.original.etablissement;
                if (etab) {
                    return (
                        <Badge variant="outline" className="text-xs bg-slate-50 text-slate-700 border-slate-300 dark:bg-slate-900/50 dark:text-slate-300 gap-1 font-normal">
                            <Building2 className="size-3 text-violet-600 dark:text-violet-400" />
                            <span>{etab.code || etab.nom}</span>
                        </Badge>
                    );
                }
                return (
                    <Badge variant="outline" className="text-xs bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-900/50 gap-1 font-normal">
                        <Globe className="size-3 text-violet-500" />
                        <span>Tronc Commun</span>
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'is_active',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Statut</span>,
            cell: ({ row }) => (
                row.original.is_active ? (
                    <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/50 text-xs">
                        Actif
                    </Badge>
                ) : (
                    <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/50 text-xs">
                        Désactivé
                    </Badge>
                )
            ),
        },
        {
            id: 'niveaux_count',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Grilles Actives</span>,
            cell: ({ row }) => (
                <Badge variant="secondary" className="text-xs">
                    {row.original.matiere_niveaux_count || 0} niveau(x)
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right pr-2 text-xs font-semibold uppercase tracking-wider">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        {canManage && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier la matière"
                                    onClick={() => {
                                        setEditingMatiere(item);
                                        matiereForm.setData({
                                            etablissement_id: item.etablissement_id || null,
                                            code: item.code,
                                            nom: item.nom,
                                            nom_en: item.nom_en || '',
                                            libelle_bulletin: item.libelle_bulletin || '',
                                            libelle_bulletin_en: item.libelle_bulletin_en || '',
                                            is_active: item.is_active,
                                        });
                                        setIsMatiereModalOpen(true);
                                    }}
                                    className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-400 cursor-pointer shadow-xs"
                                >
                                    <Edit2 className="size-3.5" />
                                    <span className="sr-only">Modifier</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Supprimer du catalogue"
                                    onClick={() => {
                                        confirmDelete({
                                            title: 'Supprimer la matière du catalogue',
                                            itemType: 'la matière',
                                            itemName: item.nom,
                                            description: `La matière sera définitivement supprimée du catalogue général. Assurez-vous qu'elle n'est plus utilisée dans aucune grille.`,
                                            onConfirm: () => {
                                                router.delete(`/administration/matieres/catalogue/${item.id}`, {
                                                    preserveScroll: true,
                                                });
                                            },
                                        });
                                    }}
                                    className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                                >
                                    <Trash2 className="size-3.5" />
                                    <span className="sr-only">Supprimer</span>
                                </Button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ];

    // =========================================================================
    // COLONNES TANSTACK TABLE 3 : BLOCS PÉDAGOGIQUES (GROUPES)
    // =========================================================================
    const groupeColumns: ColumnDef<GroupeMatiereItem>[] = [
        {
            accessorKey: 'ordre',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    className="-ml-3 h-8 text-xs font-semibold uppercase tracking-wider"
                >
                    Ordre
                    <ArrowUpDown className="ml-2 h-3.5 w-3.5" />
                </Button>
            ),
            cell: ({ row }) => (
                <span className="size-7 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">
                    #{row.original.ordre}
                </span>
            ),
        },
        {
            accessorKey: 'code',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Code Bloc</span>,
            cell: ({ row }) => (
                <Badge variant="outline" className="font-mono text-xs font-semibold">
                    {row.original.code}
                </Badge>
            ),
        },
        {
            accessorKey: 'nom',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    className="h-8 text-xs font-semibold uppercase tracking-wider"
                >
                    Intitulé Officiel (FR)
                    <ArrowUpDown className="ml-2 h-3.5 w-3.5" />
                </Button>
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className={`inline-block size-3 rounded-full ${getBlocBadgeStyle(row.original.couleur).split(' ')[0]}`} />
                    <span className="font-semibold text-foreground text-sm">{row.original.nom}</span>
                </div>
            ),
        },
        {
            accessorKey: 'nom_en',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">English Title (EN)</span>,
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground italic">
                    {row.original.nom_en || '—'}
                </span>
            ),
        },
        {
            id: 'etablissement',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Campus</span>,
            cell: ({ row }) => {
                const etab = row.original.etablissement;
                if (etab) {
                    return (
                        <span className="text-xs font-medium text-foreground flex items-center gap-1">
                            <Building2 className="size-3 text-violet-600 dark:text-violet-400" />
                            {etab.nom}
                        </span>
                    );
                }
                return (
                    <span className="text-xs text-muted-foreground italic flex items-center gap-1">
                        <Globe className="size-3 text-muted-foreground" />
                        Tronc Commun
                    </span>
                );
            },
        },
        {
            id: 'disciplines_associees',
            header: () => <span className="text-xs font-semibold uppercase tracking-wider">Disciplines Rattachées</span>,
            cell: ({ row }) => (
                <Badge variant="secondary" className="text-xs">
                    {row.original.matiere_niveaux_count || 0} association(s)
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right pr-2 text-xs font-semibold uppercase tracking-wider">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        {canManage && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Modifier le bloc"
                                    onClick={() => {
                                        setEditingGroupe(item);
                                        groupeForm.setData({
                                            etablissement_id: item.etablissement_id || null,
                                            code: item.code,
                                            nom: item.nom,
                                            nom_en: item.nom_en || '',
                                            couleur: item.couleur || 'blue',
                                            ordre: item.ordre,
                                        });
                                        setIsGroupeModalOpen(true);
                                    }}
                                    className="size-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-400 cursor-pointer shadow-xs"
                                >
                                    <Edit2 className="size-3.5" />
                                    <span className="sr-only">Modifier</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="Supprimer le bloc"
                                    onClick={() => {
                                        confirmDelete({
                                            title: 'Supprimer le bloc pédagogique',
                                            itemType: 'le bloc pédagogique',
                                            itemName: item.nom,
                                            description: `Ce bloc sera définitivement supprimé. Les matières associées ne seront plus regroupées sous cette classification.`,
                                            onConfirm: () => {
                                                router.delete(`/administration/matieres/groupes/${item.id}`, {
                                                    preserveScroll: true,
                                                });
                                            },
                                        });
                                    }}
                                    className="size-8 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-400 cursor-pointer shadow-xs"
                                >
                                    <Trash2 className="size-3.5" />
                                    <span className="sr-only">Supprimer</span>
                                </Button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title="Matières & Coefficients - Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full">
                {/* 1. EN-TÊTE DU MODULE */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <BookOpen className="size-7 text-violet-600 dark:text-violet-400" />
                            Matières & Coefficients
                        </h1>
                        <p className="text-muted-foreground text-xs sm:text-sm mt-1">
                            Catalogue central des disciplines, blocs pédagogiques officiels (MINESEC/MINEDUB) et grille des coefficients par niveau.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {canManage && (
                            <>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={handleOpenAddMatiere}
                                    className="gap-1.5 cursor-pointer bg-violet-600 hover:bg-violet-700 text-white shadow-xs font-medium"
                                >
                                    <Plus className="size-4" />
                                    <span>Nouvelle Matière</span>
                                </Button>

                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={handleOpenAddGroupe}
                                    className="gap-1.5 cursor-pointer bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs font-medium"
                                >
                                    <Plus className="size-4" />
                                    <span>Nouveau Bloc Pédagogique</span>
                                </Button>

                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={handleOpenAddCoefficient}
                                    className="gap-1.5 cursor-pointer bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs font-medium"
                                >
                                    <Plus className="size-4" />
                                    <span>Ajouter une matière au niveau</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleSeedStandards}
                                    className="gap-1.5 cursor-pointer border-violet-200 hover:bg-violet-50 text-violet-700 dark:border-violet-900/50 dark:hover:bg-violet-950/50 dark:text-violet-300"
                                >
                                    <Sparkles className="size-4 text-violet-600 dark:text-violet-400" />
                                    <span>Initialiser les Standards</span>
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* 2. SÉLECTEUR DE CAMPUS / ÉTABLISSEMENT */}
                {etablissements && etablissements.length > 0 && (
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-xs overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        {/* En-tête contrasté */}
                        <div className="bg-gradient-to-r from-slate-200/95 via-violet-100/60 to-slate-200/80 dark:from-slate-800 dark:via-violet-950/30 dark:to-slate-800/90 px-6 py-3.5 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2.5">
                                <div className="flex size-7 items-center justify-center rounded-lg bg-violet-600 text-white shadow-xs">
                                    <Building2 className="size-4" />
                                </div>
                                <span className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                    Campus & Établissement
                                </span>
                            </div>
                            {etablissements.length > 1 && (
                                <span className="text-xs text-slate-600 dark:text-slate-400 font-medium">
                                    Sélectionnez un campus pour configurer ses blocs pédagogiques, son catalogue et ses coefficients.
                                </span>
                            )}
                        </div>

                        {/* Corps du Campus */}
                        <div className="p-4 sm:p-5 bg-card">
                            {etablissements.length > 1 ? (
                                <div className="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
                                    {etablissements.map((etab) => {
                                        const isSelected = selectedEtablissement?.id === etab.id;
                                        return (
                                            <button
                                                key={etab.id}
                                                type="button"
                                                onClick={() => handleSelectEtablissement(etab.id)}
                                                className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all cursor-pointer border ${
                                                    isSelected
                                                        ? 'bg-violet-600 text-white border-violet-600 shadow-xs'
                                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-900'
                                                }`}
                                            >
                                                <span className="font-mono text-[11px] opacity-85">{etab.code}</span>
                                                <span>{etab.nom}</span>
                                                {isSelected && <Check className="size-3 stroke-3 ml-0.5" />}
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Badge variant="secondary" className="font-mono font-bold text-violet-800 bg-violet-100 border border-violet-300 dark:bg-violet-950/60 dark:text-violet-300">
                                        {selectedEtablissement?.code}
                                    </Badge>
                                    <span className="font-semibold text-foreground text-sm">{selectedEtablissement?.nom}</span>
                                    <span className="text-slate-500">(Campus unique de l'institution)</span>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* 3. BARRE D'ONGLETS STYLISÉE */}
                <div className="flex border-b border-border/80 gap-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('coefficients')}
                        className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-all cursor-pointer ${
                            activeTab === 'coefficients'
                                ? 'border-violet-600 text-violet-600 dark:text-violet-400 dark:border-violet-400 font-semibold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <SlidersHorizontal className="size-4" />
                        <span>Grille des Coefficients</span>
                        <Badge variant="secondary" className="ml-1 text-xs">
                            {coefficients.length}
                        </Badge>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('catalogue')}
                        className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-all cursor-pointer ${
                            activeTab === 'catalogue'
                                ? 'border-violet-600 text-violet-600 dark:text-violet-400 dark:border-violet-400 font-semibold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <BookOpen className="size-4" />
                        <span>Catalogue des Matières</span>
                        <Badge variant="secondary" className="ml-1 text-xs">
                            {matieres.length}
                        </Badge>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('groupes')}
                        className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-all cursor-pointer ${
                            activeTab === 'groupes'
                                ? 'border-violet-600 text-violet-600 dark:text-violet-400 dark:border-violet-400 font-semibold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Layers className="size-4" />
                        <span>Blocs Pédagogiques</span>
                        <Badge variant="secondary" className="ml-1 text-xs">
                            {groupes.length}
                        </Badge>
                    </button>
                </div>

                {/* ============================================================= */}
                {/* ONGLET 1 : GRILLE DES COEFFICIENTS                            */}
                {/* ============================================================= */}
                {activeTab === 'coefficients' && (
                    <div className="space-y-6">
                        {/* Barre de sélection du campus, du niveau et de la série */}
                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-xl border border-border/70 bg-card shadow-xs">
                            <div className="flex flex-wrap items-center gap-3">
                                {/* Indicateur contextuel du Campus scolaire actif */}
                                {selectedEtablissement && (
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline" className="font-medium text-xs bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/50 dark:text-violet-300 gap-1.5 py-1 px-2.5">
                                            <Building2 className="size-3.5 text-violet-600 dark:text-violet-400" />
                                            <span>Campus : <strong>{selectedEtablissement.nom}</strong></span>
                                        </Badge>
                                    </div>
                                )}

                                {/* Filtre par Niveau scolaire */}
                                <div className="flex items-center gap-2">
                                    <Filter className="size-4 text-muted-foreground" />
                                    <span className="text-xs font-semibold uppercase text-muted-foreground">Niveau :</span>
                                    <Select
                                        value={selectedNiveau ? String(selectedNiveau.id) : ''}
                                        onValueChange={handleSelectNiveau}
                                    >
                                        <SelectTrigger className="w-64 cursor-pointer">
                                            <SelectValue placeholder="Sélectionner un niveau" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {niveaux.map((niv) => {
                                                const etabName = getEtablissementName(niv);
                                                return (
                                                    <SelectItem key={niv.id} value={String(niv.id)} className="cursor-pointer">
                                                        <div className="flex flex-col text-left py-0.5">
                                                            <span className="font-medium text-foreground">{niv.nom} ({niv.code})</span>
                                                            {etabName && (
                                                                <span className="text-[11px] text-muted-foreground">
                                                                    {etabName}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {seriesDisponibles.length > 1 && (
                                    <div className="flex items-center gap-1.5 ml-2">
                                        <span className="text-xs font-semibold uppercase text-muted-foreground">Série :</span>
                                        <div className="flex flex-wrap gap-1">
                                            {seriesDisponibles.map((s) => (
                                                <Button
                                                    key={s}
                                                    type="button"
                                                    variant={selectedSerie === s ? 'default' : 'outline'}
                                                    size="sm"
                                                    onClick={() => handleSelectSerie(s)}
                                                    className="h-8 text-xs cursor-pointer"
                                                >
                                                    {s}
                                                </Button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Bouton d'ajout d'une discipline à la grille */}
                            {canManage && selectedNiveau && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={handleOpenAddCoefficient}
                                    className="gap-2 cursor-pointer bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    <Plus className="size-4" />
                                    <span>Ajouter une matière au niveau</span>
                                </Button>
                            )}
                        </div>

                        {/* Bandeau d'information contextuelle d'appartenance de la grille */}
                        {selectedNiveau && (
                            <div className="flex flex-wrap items-center gap-2.5 px-4 py-2.5 rounded-xl bg-violet-50/50 dark:bg-violet-950/20 border border-violet-200/60 dark:border-violet-900/40 text-xs">
                                <span className="font-semibold text-foreground flex items-center gap-1.5">
                                    <Building2 className="size-3.5 text-violet-600 dark:text-violet-400" />
                                    Établissement rattaché :
                                </span>
                                <Badge variant="outline" className="bg-background text-foreground font-medium text-xs border-violet-200 dark:border-violet-800">
                                    {getEtablissementName(selectedNiveau) || 'Établissement Principal'}
                                </Badge>

                                {selectedNiveau.cycle?.section?.nom && (
                                    <>
                                        <span className="text-muted-foreground/50">•</span>
                                        <span className="text-muted-foreground">
                                            Section : <strong className="text-foreground">{selectedNiveau.cycle.section.nom}</strong>
                                        </span>
                                    </>
                                )}

                                {selectedNiveau.cycle?.nom && (
                                    <>
                                        <span className="text-muted-foreground/50">•</span>
                                        <span className="text-muted-foreground">
                                            Cycle : <strong className="text-foreground">{selectedNiveau.cycle.nom}</strong>
                                        </span>
                                    </>
                                )}
                            </div>
                        )}

                        {/* Cartes récapitulatives / métriques d'évaluation du niveau */}
                        {selectedNiveau && (
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <Card className="border-border/60 shadow-xs">
                                    <CardContent className="p-4 flex flex-col justify-center">
                                        <span className="text-xs font-medium text-muted-foreground uppercase">Total Matières</span>
                                        <div className="mt-1 flex items-baseline gap-2">
                                            <span className="text-2xl font-bold text-foreground">{statsCoefficients.totalMatieres}</span>
                                            <span className="text-xs text-muted-foreground">disciplines</span>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="border-border/60 shadow-xs">
                                    <CardContent className="p-4 flex flex-col justify-center">
                                        <span className="text-xs font-medium text-muted-foreground uppercase">Somme Coefficients</span>
                                        <div className="mt-1 flex items-baseline gap-2">
                                            <span className="text-2xl font-bold text-violet-600 dark:text-violet-400">
                                                {statsCoefficients.totalCoefficients}
                                            </span>
                                            <span className="text-xs text-muted-foreground">points pondérés</span>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="border-border/60 shadow-xs">
                                    <CardContent className="p-4 flex flex-col justify-center">
                                        <span className="text-xs font-medium text-muted-foreground uppercase">Matières Facultatives</span>
                                        <div className="mt-1 flex items-baseline gap-2">
                                            <span className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                                {statsCoefficients.totalFacultatives}
                                            </span>
                                            <span className="text-xs text-muted-foreground">bonus &gt; 10</span>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="border-border/60 shadow-xs">
                                    <CardContent className="p-4 flex flex-col justify-center">
                                        <span className="text-xs font-medium text-muted-foreground uppercase">Notes Éliminatoires</span>
                                        <div className="mt-1 flex items-baseline gap-2">
                                            <span className="text-2xl font-bold text-rose-600 dark:text-rose-400">
                                                {statsCoefficients.totalEliminatoires}
                                            </span>
                                            <span className="text-xs text-muted-foreground">disqualifiantes</span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* État vide si aucune matière n'est configurée pour ce niveau */}
                        {coefficients.length === 0 ? (
                            <div className="flex flex-col items-center justify-center p-8 rounded-xl border border-dashed border-border/80 bg-muted/20 text-center space-y-3">
                                <div className="size-12 rounded-full bg-violet-100 dark:bg-violet-950/50 flex items-center justify-center text-violet-600 dark:text-violet-400">
                                    <BookOpen className="size-6" />
                                </div>
                                <div className="space-y-1">
                                    <h3 className="text-sm font-semibold text-foreground">Aucune matière configurée pour ce niveau</h3>
                                    <p className="text-xs text-muted-foreground max-w-sm">
                                        Ce niveau n'a pas encore de coefficients attribués. Vous pouvez assigner une matière ou initialiser les standards officiels.
                                    </p>
                                </div>
                                {canManage && (
                                    <div className="flex items-center gap-2 pt-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={handleOpenAddCoefficient}
                                            className="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer"
                                        >
                                            <Plus className="size-4" />
                                            <span>Ajouter une matière au niveau</span>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ) : (
                            /* Table TanStack des coefficients */
                            <DataTable
                                columns={coefficientColumns}
                                data={coefficients}
                                searchPlaceholder="Rechercher une matière ou un bloc..."
                            />
                        )}
                    </div>
                )}

                {/* ============================================================= */}
                {/* ONGLET 2 : CATALOGUE GÉNÉRAL DES MATIÈRES                     */}
                {/* ============================================================= */}
                {activeTab === 'catalogue' && (
                    <div className="space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-medium text-foreground">
                                    Référentiel des Disciplines & Matières
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Matières du tronc commun institutionnel et disciplines spécifiques rattachées au campus actif {selectedEtablissement ? `(${selectedEtablissement.nom})` : ''}.
                                </p>
                            </div>
                            {canManage && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={handleOpenAddMatiere}
                                    className="gap-2 cursor-pointer bg-violet-600 hover:bg-violet-700 text-white font-medium shadow-xs shrink-0"
                                >
                                    <Plus className="size-4" />
                                    <span>Nouvelle Matière</span>
                                </Button>
                            )}
                        </div>

                        <DataTable
                            columns={catalogueColumns}
                            data={matieres}
                            searchPlaceholder="Rechercher par code ou nom de matière..."
                        />
                    </div>
                )}

                {/* ============================================================= */}
                {/* ONGLET 3 : BLOCS PÉDAGOGIQUES (GROUPES)                       */}
                {/* ============================================================= */}
                {activeTab === 'groupes' && (
                    <div className="space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-medium text-foreground flex items-center gap-2">
                                    Blocs Pédagogiques & Pôles d'Enseignement
                                    {selectedEtablissement && (
                                        <Badge variant="outline" className="text-xs font-normal border-violet-200 bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                                            {selectedEtablissement.nom}
                                        </Badge>
                                    )}
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Structure officielle du bulletin de notes et ordre d'apparition des disciplines pour ce campus.
                                </p>
                            </div>
                            {canManage && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={handleOpenAddGroupe}
                                    className="gap-2 cursor-pointer bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs shrink-0"
                                >
                                    <Plus className="size-4" />
                                    <span>Nouveau Bloc Pédagogique</span>
                                </Button>
                            )}
                        </div>

                        <DataTable
                            columns={groupeColumns}
                            data={groupes}
                            searchPlaceholder="Rechercher un bloc pédagogique..."
                        />
                    </div>
                )}
            </div>

            {/* ================================================================= */}
            {/* MODALE 1 : CONFIGURATION COEFFICIENT / ASSOCIATION NIVEAU         */}
            {/* ================================================================= */}
            <Dialog open={isCoefficientModalOpen} onOpenChange={setIsCoefficientModalOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingCoefficient ? 'Modifier le Coefficient' : 'Ajouter une Matière à la Grille'}
                        </DialogTitle>
                        <DialogDescription>
                            Configurez le poids académique, le bloc pédagogique et les règles d'évaluation pour ce niveau.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitCoefficient} className="space-y-4 pt-2">
                        {/* Niveau et Établissement concernés */}
                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <Label className="text-xs font-semibold">Niveau Scolaire</Label>
                                {(getEtablissementName(selectedNiveau) || selectedEtablissement?.nom) && (
                                    <span className="text-[11px] text-muted-foreground font-normal flex items-center gap-1">
                                        <Building2 className="size-3 text-violet-600 dark:text-violet-400" />
                                        Établissement : <strong className="text-foreground">{getEtablissementName(selectedNiveau) || selectedEtablissement?.nom}</strong>
                                    </span>
                                )}
                            </div>
                            <Input
                                value={selectedNiveau ? `${selectedNiveau.nom} (${selectedNiveau.code})` : ''}
                                disabled
                                className="bg-muted text-sm"
                            />
                        </div>

                        {/* Matière du catalogue */}
                        <div>
                            <Label htmlFor="coef_matiere_id" className="text-xs font-semibold">
                                Discipline / Matière <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                disabled={Boolean(editingCoefficient)}
                                value={String(coefficientForm.data.matiere_id || '')}
                                onValueChange={(val) => coefficientForm.setData('matiere_id', Number(val))}
                            >
                                <SelectTrigger id="coef_matiere_id" className="mt-1 cursor-pointer">
                                    <SelectValue placeholder="Sélectionner une discipline..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {matieres.map((m) => (
                                        <SelectItem key={m.id} value={String(m.id)} className="cursor-pointer">
                                            [{m.code}] {m.nom} {m.nom_en ? `(${m.nom_en})` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {coefficientForm.errors.matiere_id && (
                                <p className="text-xs text-rose-500 mt-1">{coefficientForm.errors.matiere_id}</p>
                            )}
                        </div>

                        {/* Bloc Pédagogique */}
                        <div>
                            <Label htmlFor="coef_groupe_id" className="text-xs font-semibold">
                                Bloc Pédagogique (Ordre sur bulletin) <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={String(coefficientForm.data.groupe_matiere_id || '')}
                                onValueChange={(val) => coefficientForm.setData('groupe_matiere_id', Number(val))}
                            >
                                <SelectTrigger id="coef_groupe_id" className="mt-1 cursor-pointer">
                                    <SelectValue placeholder="Sélectionner un bloc..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {groupes.map((g) => (
                                        <SelectItem key={g.id} value={String(g.id)} className="cursor-pointer">
                                            #{g.ordre} - {g.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {coefficientForm.errors.groupe_matiere_id && (
                                <p className="text-xs text-rose-500 mt-1">{coefficientForm.errors.groupe_matiere_id}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            {/* Série */}
                            <div>
                                <Label htmlFor="coef_serie" className="text-xs font-semibold">Série / Filière</Label>
                                <Input
                                    id="coef_serie"
                                    value={coefficientForm.data.serie}
                                    onChange={(e) => coefficientForm.setData('serie', e.target.value)}
                                    placeholder="ex: Toutes, C, D, A4..."
                                    className="mt-1 text-sm"
                                />
                                {coefficientForm.errors.serie && (
                                    <p className="text-xs text-rose-500 mt-1">{coefficientForm.errors.serie}</p>
                                )}
                            </div>

                            {/* Coefficient */}
                            <div>
                                <Label htmlFor="coef_val" className="text-xs font-semibold">
                                    Coefficient <span className="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="coef_val"
                                    type="number"
                                    step="0.25"
                                    min="0.25"
                                    max="50"
                                    value={coefficientForm.data.coefficient}
                                    onChange={(e) => coefficientForm.setData('coefficient', Number(e.target.value))}
                                    className="mt-1 text-sm font-semibold"
                                />
                                {coefficientForm.errors.coefficient && (
                                    <p className="text-xs text-rose-500 mt-1">{coefficientForm.errors.coefficient}</p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            {/* Note Max */}
                            <div>
                                <Label htmlFor="coef_note_max" className="text-xs font-semibold">Note Maximale (Barème)</Label>
                                <Input
                                    id="coef_note_max"
                                    type="number"
                                    step="1"
                                    min="1"
                                    max="100"
                                    value={coefficientForm.data.note_max}
                                    onChange={(e) => coefficientForm.setData('note_max', Number(e.target.value))}
                                    className="mt-1 text-sm"
                                />
                            </div>

                            {/* Note Éliminatoire */}
                            <div>
                                <Label htmlFor="coef_note_elim" className="text-xs font-semibold">Note Éliminatoire</Label>
                                <Input
                                    id="coef_note_elim"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="20"
                                    placeholder="Optionnelle (ex: 5.0)"
                                    value={coefficientForm.data.note_eliminatoire}
                                    onChange={(e) => coefficientForm.setData('note_eliminatoire', e.target.value)}
                                    className="mt-1 text-sm"
                                />
                                <span className="text-[11px] text-muted-foreground">Exclut du Tableau d'Honneur (V1.1)</span>
                            </div>
                        </div>

                        {/* Options supplémentaires */}
                        <div className="pt-2 space-y-3 border-t border-border/60">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="coef_facultative"
                                    checked={coefficientForm.data.is_facultative}
                                    onCheckedChange={(checked) => coefficientForm.setData('is_facultative', Boolean(checked))}
                                />
                                <Label htmlFor="coef_facultative" className="text-xs cursor-pointer">
                                    Matière facultative (seuls les points au-dessus de 10 sont comptabilisés en bonus)
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="coef_comptabilisee"
                                    checked={coefficientForm.data.is_comptabilisee}
                                    onCheckedChange={(checked) => coefficientForm.setData('is_comptabilisee', Boolean(checked))}
                                />
                                <Label htmlFor="coef_comptabilisee" className="text-xs cursor-pointer">
                                    Comptabiliser dans le calcul de la moyenne générale
                                </Label>
                            </div>
                        </div>

                        <DialogFooter className="pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCoefficientModalOpen(false)}
                                className="cursor-pointer"
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={coefficientForm.processing}
                                className="bg-violet-600 hover:bg-violet-700 text-white cursor-pointer"
                            >
                                {coefficientForm.processing ? 'Enregistrement...' : 'Enregistrer'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ================================================================= */}
            {/* MODALE 2 : CATALOGUE DES MATIÈRES (CRÉATION / MODIFICATION)       */}
            {/* ================================================================= */}
            <Dialog open={isMatiereModalOpen} onOpenChange={setIsMatiereModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingMatiere ? 'Modifier la Matière' : 'Ajouter une Discipline au Catalogue'}
                        </DialogTitle>
                        <DialogDescription>
                            Définissez le code et les intitulés bilingues (FR/EN) pour cette matière.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitMatiere} className="space-y-4 pt-2">
                        {/* Portée / Rattachement Campus */}
                        <div>
                            <Label htmlFor="mat_etablissement_id" className="text-xs font-semibold">
                                Portée & Rattachement Campus
                            </Label>
                            <Select
                                value={matiereForm.data.etablissement_id ? String(matiereForm.data.etablissement_id) : 'common'}
                                onValueChange={(val) =>
                                    matiereForm.setData('etablissement_id', val === 'common' ? null : Number(val))
                                }
                            >
                                <SelectTrigger id="mat_etablissement_id" className="mt-1 cursor-pointer">
                                    <SelectValue placeholder="Sélectionner la portée..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="common" className="cursor-pointer">
                                        🌐 Tronc Commun Institutionnel (Partagé par tous les campus)
                                    </SelectItem>
                                    {etablissements.map((etab) => (
                                        <SelectItem key={etab.id} value={String(etab.id)} className="cursor-pointer">
                                            🏫 Spécifique au campus : {etab.nom} ({etab.code})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-[11px] text-muted-foreground mt-1">
                                Les matières du tronc commun sont exploitables dans tous les établissements du groupe scolaire.
                            </p>
                        </div>

                        <div>
                            <Label htmlFor="mat_code" className="text-xs font-semibold">
                                Code Officiel <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="mat_code"
                                value={matiereForm.data.code}
                                onChange={(e) => matiereForm.setData('code', e.target.value.toUpperCase())}
                                placeholder="ex: MATH, FRAN, SVT..."
                                className="mt-1 font-mono uppercase text-sm"
                                required
                            />
                            {matiereForm.errors.code && (
                                <p className="text-xs text-rose-500 mt-1">{matiereForm.errors.code}</p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="mat_nom" className="text-xs font-semibold">
                                Intitulé Français (FR) <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="mat_nom"
                                value={matiereForm.data.nom}
                                onChange={(e) => matiereForm.setData('nom', e.target.value)}
                                placeholder="ex: Mathématiques"
                                className="mt-1 text-sm"
                                required
                            />
                            {matiereForm.errors.nom && (
                                <p className="text-xs text-rose-500 mt-1">{matiereForm.errors.nom}</p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="mat_nom_en" className="text-xs font-semibold">English Title (EN)</Label>
                            <Input
                                id="mat_nom_en"
                                value={matiereForm.data.nom_en}
                                onChange={(e) => matiereForm.setData('nom_en', e.target.value)}
                                placeholder="ex: Mathematics"
                                className="mt-1 text-sm"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="mat_lib_fr" className="text-xs font-semibold">Libellé Relevé (FR)</Label>
                                <Input
                                    id="mat_lib_fr"
                                    value={matiereForm.data.libelle_bulletin}
                                    onChange={(e) => matiereForm.setData('libelle_bulletin', e.target.value)}
                                    placeholder="Optionnel"
                                    className="mt-1 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="mat_lib_en" className="text-xs font-semibold">Report Label (EN)</Label>
                                <Input
                                    id="mat_lib_en"
                                    value={matiereForm.data.libelle_bulletin_en}
                                    onChange={(e) => matiereForm.setData('libelle_bulletin_en', e.target.value)}
                                    placeholder="Optionnel"
                                    className="mt-1 text-sm"
                                />
                            </div>
                        </div>

                        <div className="flex items-center space-x-2 pt-2">
                            <Checkbox
                                id="mat_active"
                                checked={matiereForm.data.is_active}
                                onCheckedChange={(checked) => matiereForm.setData('is_active', Boolean(checked))}
                            />
                            <Label htmlFor="mat_active" className="text-xs cursor-pointer">
                                Discipline active dans les établissements
                            </Label>
                        </div>

                        <DialogFooter className="pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsMatiereModalOpen(false)}
                                className="cursor-pointer"
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={matiereForm.processing}
                                className="bg-violet-600 hover:bg-violet-700 text-white cursor-pointer"
                            >
                                {matiereForm.processing ? 'Enregistrement...' : 'Enregistrer'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ================================================================= */}
            {/* MODALE 3 : BLOC PÉDAGOGIQUE (CRÉATION / MODIFICATION)              */}
            {/* ================================================================= */}
            <Dialog open={isGroupeModalOpen} onOpenChange={setIsGroupeModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingGroupe ? 'Modifier le Bloc Pédagogique' : 'Nouveau Bloc Pédagogique'}
                        </DialogTitle>
                        <DialogDescription>
                            Configurez la catégorie officielle et son rang d'apparition sur le bulletin scolaire.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitGroupe} className="space-y-4 pt-2">
                        {/* Campus de rattachement du bloc */}
                        <div>
                            <Label className="text-xs font-semibold">Campus Scolaire de Rattachement</Label>
                            <div className="flex items-center gap-2 mt-1 p-2.5 rounded-lg bg-muted/60 border border-border/80 text-xs">
                                <Building2 className="size-4 text-violet-600 dark:text-violet-400 shrink-0" />
                                <span className="font-medium text-foreground">
                                    {selectedEtablissement ? `${selectedEtablissement.nom} (${selectedEtablissement.code})` : 'Établissement par défaut'}
                                </span>
                            </div>
                            <p className="text-[11px] text-muted-foreground mt-1">
                                Ce bloc pédagogique structure les moyennes et l'ordre des matières sur le bulletin de ce campus.
                            </p>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="grp_code" className="text-xs font-semibold">
                                    Code Bloc <span className="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="grp_code"
                                    value={groupeForm.data.code}
                                    onChange={(e) => groupeForm.setData('code', e.target.value.toUpperCase())}
                                    placeholder="ex: BLOC-SCI"
                                    className="mt-1 font-mono uppercase text-sm"
                                    required
                                />
                                {groupeForm.errors.code && (
                                    <p className="text-xs text-rose-500 mt-1">{groupeForm.errors.code}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="grp_ordre" className="text-xs font-semibold">
                                    Ordre sur Relevé <span className="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="grp_ordre"
                                    type="number"
                                    min="1"
                                    max="50"
                                    value={groupeForm.data.ordre}
                                    onChange={(e) => groupeForm.setData('ordre', Number(e.target.value))}
                                    className="mt-1 text-sm font-semibold"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="grp_nom" className="text-xs font-semibold">
                                Intitulé Officiel (FR) <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="grp_nom"
                                value={groupeForm.data.nom}
                                onChange={(e) => groupeForm.setData('nom', e.target.value)}
                                placeholder="ex: Enseignements Scientifiques & Technologiques"
                                className="mt-1 text-sm"
                                required
                            />
                            {groupeForm.errors.nom && (
                                <p className="text-xs text-rose-500 mt-1">{groupeForm.errors.nom}</p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="grp_nom_en" className="text-xs font-semibold">English Title (EN)</Label>
                            <Input
                                id="grp_nom_en"
                                value={groupeForm.data.nom_en}
                                onChange={(e) => groupeForm.setData('nom_en', e.target.value)}
                                placeholder="ex: Scientific & Technological Subjects"
                                className="mt-1 text-sm"
                            />
                        </div>

                        <div>
                            <Label htmlFor="grp_color" className="text-xs font-semibold">Couleur d'Identification</Label>
                            <Select
                                value={groupeForm.data.couleur}
                                onValueChange={(val) => groupeForm.setData('couleur', val)}
                            >
                                <SelectTrigger id="grp_color" className="mt-1 cursor-pointer">
                                    <SelectValue placeholder="Sélectionner une couleur..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="blue" className="cursor-pointer">Bleu (Sciences)</SelectItem>
                                    <SelectItem value="emerald" className="cursor-pointer">Émeraude / Vert (Lettres)</SelectItem>
                                    <SelectItem value="amber" className="cursor-pointer">Ambre / Orange (Arts & Pratique)</SelectItem>
                                    <SelectItem value="purple" className="cursor-pointer">Violet (Technologies)</SelectItem>
                                    <SelectItem value="rose" className="cursor-pointer">Rose (Développement Personnel)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <DialogFooter className="pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsGroupeModalOpen(false)}
                                className="cursor-pointer"
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={groupeForm.processing}
                                className="bg-violet-600 hover:bg-violet-700 text-white cursor-pointer"
                            >
                                {groupeForm.processing ? 'Enregistrement...' : 'Enregistrer'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Dialogue de confirmation de suppression personnalisé */}
            <ConfirmDialog />
        </>
    );
}

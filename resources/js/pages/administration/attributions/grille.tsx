import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    AlertCircle,
    ArrowLeft,
    Atom,
    Award,
    BookOpen,
    Check,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    HelpCircle,
    Layers,
    Palette,
    Plus,
    RefreshCw,
    RotateCcw,
    Save,
    School,
    Search,
    Shield,
    SlidersHorizontal,
    Sparkles,
    TrendingDown,
    TrendingUp,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import {
    SearchableSelect,
    type SearchableOption,
} from '@/components/ui/searchable-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    ClasseWithAttributions,
    EnseignantWithWorkload,
    MatiereNiveauRef,
} from '@/types/attribution';
import { ProfesseurPrincipalModal } from './modals/professeur-principal-modal';

/**
 * Propriétés transmises par le contrôleur Inertia pour la page dédiée de la grille de classe.
 */
interface GrillePageProps {
    /** Liste des années scolaires disponibles */
    anneesScolaires: { id: number; nom: string; is_current: boolean }[];
    /** Identifiant de l'année scolaire active */
    selectedAnneeId: number;
    /** Liste des campus scolaires */
    etablissements: any[];
    /** Liste complète des classes actives disponibles */
    classes: ClasseWithAttributions[];
    /** Classe courante sélectionnée pour l'affichage de la grille */
    selectedClasse: ClasseWithAttributions | null;
    /** Maquette des matières et coefficients requises pour ce niveau/série */
    matiereNiveaux: MatiereNiveauRef[];
    /** Corps enseignant avec calcul de la charge horaire */
    enseignants: EnseignantWithWorkload[];
    /** Statistiques consolidées de couverture pour la classe */
    stats: {
        totalRequises: number;
        pourvuesCount: number;
        totalHeures: number;
        totalCoefficients: number;
        tauxCouverture: number;
    };
    /** Droits et permissions RBAC de l'utilisateur connecté */
    can: {
        view: boolean;
        create: boolean;
        edit: boolean;
        prof_principal: boolean;
        delete: boolean;
        manage: boolean;
    };
    /** Filtres actifs */
    filters: {
        annee_scolaire_id: number;
        etablissement_id: string;
        classe_id: string;
    };
}

/**
 * Représentation locale de l'état d'une ligne d'attribution dans la grille.
 */
interface MatrixRowState {
    matiere_id: number;
    code: string;
    nom: string;
    libelle_bulletin: string;
    coefficient: number;
    groupe_nom: string;
    groupe_code: string;
    groupe_couleur?: string | null;
    personnel_id: number | null;
    volume_horaire_reel: number;
    is_titulaire: boolean;
    initial_personnel_id: number | null;
    initial_volume_horaire_reel: number;
}

/**
 * Configuration visuelle et stylistique d'un pôle pédagogique (badge, icône, couleurs).
 */
interface PoleBadgeConfig {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    className: string;
    iconColor: string;
}

/**
 * Détermine l'icône, le libellé et le jeu de couleurs harmonieux pour chaque pôle pédagogique.
 * Conforme aux directives de design SYGES (identifiabilité visuelle immédiate des disciplines).
 *
 * @param {string} nom Intitulé du pôle (ex: "Scientifiques", "Littéraires", "Complémentaires")
 * @param {string} [code] Code technique (ex: "BLOC-SCI", "BLOC-LIT", "BLOC-COMP")
 * @param {string | null} [couleur] Couleur déclarée en base de données
 * @returns {PoleBadgeConfig}
 */
function getPoleBadgeConfig(
    nom: string,
    code?: string,
    couleur?: string | null
): PoleBadgeConfig {
    const lowerNom = (nom || '').toLowerCase();
    const upperCode = (code || '').toUpperCase();
    const lowerColor = (couleur || '').toLowerCase();

    // 1. Pôle Scientifique (Maths, Physique, Chimie, SVT, Informatique)
    if (
        upperCode.includes('SCI') ||
        lowerNom.includes('sci') ||
        lowerColor === 'blue' ||
        lowerColor === 'sky'
    ) {
        return {
            icon: Atom,
            label: nom || 'Scientifiques',
            className:
                'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800',
            iconColor: 'text-sky-600 dark:text-sky-400',
        };
    }

    // 2. Pôle Littéraire (Français, Anglais, Langues, Histoire-Géo, Philo, ECM)
    if (
        upperCode.includes('LIT') ||
        lowerNom.includes('lit') ||
        lowerNom.includes('lettre') ||
        lowerNom.includes('langue') ||
        lowerColor === 'emerald'
    ) {
        return {
            icon: BookOpen,
            label: nom || 'Littéraires',
            className:
                'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800',
            iconColor: 'text-indigo-600 dark:text-indigo-400',
        };
    }

    // 3. Pôle Complémentaire / Divers (EPS, Dessin, Musique, TM, EVA, Religion)
    if (
        upperCode.includes('COMP') ||
        lowerNom.includes('comp') ||
        lowerNom.includes('diver') ||
        lowerColor === 'amber' ||
        lowerColor === 'orange'
    ) {
        return {
            icon: Sparkles,
            label: nom || 'Complémentaires',
            className:
                'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
            iconColor: 'text-amber-600 dark:text-amber-400',
        };
    }

    // 4. Pôle Professionnel / Technique
    if (
        lowerNom.includes('tech') ||
        lowerNom.includes('pro') ||
        lowerNom.includes('indus')
    ) {
        return {
            icon: Layers,
            label: nom,
            className:
                'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/60 dark:text-teal-300 dark:border-teal-800',
            iconColor: 'text-teal-600 dark:text-teal-400',
        };
    }

    // 5. Pôle Artistique & Sportif
    if (
        lowerNom.includes('art') ||
        lowerNom.includes('sport') ||
        lowerNom.includes('eps')
    ) {
        return {
            icon: Palette,
            label: nom,
            className:
                'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
            iconColor: 'text-rose-600 dark:text-rose-400',
        };
    }

    // 6. Tronc Commun / Général par défaut
    return {
        icon: Layers,
        label: nom || 'Tronc Commun',
        className:
            'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        iconColor: 'text-slate-500 dark:text-slate-400',
    };
}

/**
 * Page dédiée : Grille de Répartition Pédagogique d'une Classe.
 *
 * Permet au Censeur / Directeur des Études de visualiser en plein écran l'ensemble
 * de la programmation officielle d'une classe (matières, coefficients, groupes),
 * d'affecter les enseignants et de sauvegarder la maquette en une seule transaction.
 *
 * @param {GrillePageProps} props Propriétés Inertia
 * @returns {JSX.Element}
 */
export default function AttributionsGrillePage({
    anneesScolaires,
    selectedAnneeId,
    classes,
    selectedClasse,
    matiereNiveaux,
    enseignants,
    stats,
    can,
}: GrillePageProps) {
    const [rows, setRows] = useState<MatrixRowState[]>([]);
    const [isSaving, setIsSaving] = useState(false);
    const [isPPModalOpen, setIsPPModalOpen] = useState(false);

    // Initialisation dynamique des lignes d'affectations à partir des données transmises
    useEffect(() => {
        if (!selectedClasse) {
            setRows([]);
            return;
        }

        const newRows: MatrixRowState[] = matiereNiveaux.map((mn) => {
            const existing = selectedClasse.attributions.find(
                (a) => a.matiere_id === mn.matiere_id
            );

            const initialProfId = existing ? existing.personnel_id : null;
            const initialVolume = existing
                ? Number(existing.volume_horaire_reel)
                : mn.coefficient >= 3
                  ? 4.0
                  : 2.0;

            // Nom épuré sans parenthèses redondantes du code
            const cleanNom = (mn.matiere?.nom || 'Matière inconnue').replace(
                new RegExp(`\\s*\\(${mn.matiere?.code}\\)$`, 'i'),
                ''
            );

            return {
                matiere_id: mn.matiere_id,
                code: mn.matiere?.code || 'DISC',
                nom: cleanNom,
                libelle_bulletin: mn.matiere?.libelle_bulletin || cleanNom,
                coefficient: mn.coefficient,
                groupe_nom: mn.groupe_matiere?.nom || 'Tronc Commun',
                groupe_code: mn.groupe_matiere?.code || '',
                groupe_couleur: mn.groupe_matiere?.couleur || null,
                personnel_id: initialProfId,
                volume_horaire_reel: initialVolume,
                is_titulaire: existing ? existing.is_titulaire : true,
                initial_personnel_id: initialProfId,
                initial_volume_horaire_reel: initialVolume,
            };
        });

        // Tri par groupe pédagogique puis par coefficient décroissant
        newRows.sort((a, b) => {
            if (a.groupe_nom !== b.groupe_nom) {
                return a.groupe_nom.localeCompare(b.groupe_nom);
            }
            return b.coefficient - a.coefficient;
        });

        setRows(newRows);
    }, [selectedClasse, matiereNiveaux]);

    /**
     * Détecte si des modifications non sauvegardées existent par rapport à la base.
     */
    const hasUnsavedChanges = useMemo(() => {
        return rows.some(
            (r) =>
                r.personnel_id !== r.initial_personnel_id ||
                r.volume_horaire_reel !== r.initial_volume_horaire_reel
        );
    }, [rows]);

    /**
     * Nombre de matières actuellement pourvues dans l'état local.
     */
    const currentPourvuesCount = useMemo(() => {
        return rows.filter((r) => r.personnel_id !== null).length;
    }, [rows]);

    /**
     * Volume horaire total calculé dans l'état local.
     */
    const currentTotalHours = useMemo(() => {
        return rows.reduce((sum, r) => sum + (r.personnel_id ? Number(r.volume_horaire_reel) : 0), 0);
    }, [rows]);

    /**
     * Options des enseignants formatées pour SearchableSelect.
     */
    const enseignantOptions: SearchableOption[] = useMemo(() => {
        const list: SearchableOption[] = [
            {
                value: 'none',
                label: '— Non pourvu (Poste vacant) —',
                group: 'Statut du cours',
                description: 'Aucun enseignant affecté à ce cours',
            },
        ];

        enseignants.forEach((ens) => {
            list.push({
                value: String(ens.id),
                label: `${ens.nom} ${ens.prenom || ''} (${ens.matricule})`,
                description: `${ens.specialite || 'Enseignement'} • [${ens.heures_attribuees}h / ${ens.volume_horaire_hebdo}h]`,
                group: ens.departement?.nom || 'Corps professoral',
            });
        });

        return list;
    }, [enseignants]);

    /**
     * Options des classes pour le sélecteur de changement de classe rapide.
     */
    const classeOptions: SearchableOption[] = useMemo(() => {
        return classes.map((c) => ({
            value: String(c.id),
            label: `${c.nom}${c.serie ? ` (${c.serie})` : ''}`,
            group: c.niveau?.nom || 'Classes',
            description: `${c.attributions.length} cours affectés`,
        }));
    }, [classes]);

    /**
     * Met à jour une valeur dans une ligne d'attribution.
     */
    const handleUpdateRow = (
        matiereId: number,
        field: 'personnel_id' | 'volume_horaire_reel',
        value: any
    ) => {
        setRows((prev) =>
            prev.map((r) => {
                if (r.matiere_id === matiereId) {
                    return { ...r, [field]: value };
                }
                return r;
            })
        );
    };

    /**
     * Réinitialise toutes les modifications locales vers l'état en base de données.
     */
    const handleReset = () => {
        setRows((prev) =>
            prev.map((r) => ({
                ...r,
                personnel_id: r.initial_personnel_id,
                volume_horaire_reel: r.initial_volume_horaire_reel,
            }))
        );
        toast.info('Modifications locales réinitialisées.');
    };

    /**
     * Enregistre en bloc toutes les affectations de la classe.
     */
    const handleSaveBatch = () => {
        if (!selectedClasse) return;
        setIsSaving(true);

        const assignments = rows.map((r) => ({
            matiere_id: r.matiere_id,
            personnel_id: r.personnel_id,
            volume_horaire_reel: r.volume_horaire_reel,
        }));

        router.post(
            `/administration/attributions/classes/${selectedClasse.id}/batch`,
            {
                annee_scolaire_id: selectedAnneeId,
                assignments,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Grille de répartition enregistrée avec succès !', {
                        description: `La programmation de la classe ${selectedClasse.nom} est à jour.`,
                    });
                    setIsSaving(false);
                },
                onError: () => {
                    toast.error('Erreur lors de l\'enregistrement de la grille', {
                        description: 'Veuillez vérifier les informations saisies.',
                    });
                    setIsSaving(false);
                },
            }
        );
    };

    /**
     * Changement de classe courante.
     */
    const handleClassChange = (classeIdStr: string) => {
        if (!classeIdStr || classeIdStr === String(selectedClasse?.id)) return;

        if (hasUnsavedChanges) {
            const confirmed = window.confirm(
                'Vous avez des modifications non enregistrées sur cette grille. Souhaitez-vous continuer sans enregistrer ?'
            );
            if (!confirmed) return;
        }

        router.visit(`/administration/attributions/grille?classe_id=${classeIdStr}&annee_scolaire_id=${selectedAnneeId}`, {
            preserveState: false,
        });
    };

    // ==========================================
    // COLONNES TANSTACK TABLE DE LA GRILLE
    // ==========================================
    const columns = useMemo<ColumnDef<MatrixRowState>[]>(() => {
        return [
            {
                id: 'index',
                header: () => <span className="font-semibold text-xs">N°</span>,
                cell: ({ row }) => (
                    <span className="font-mono text-xs text-muted-foreground font-semibold">
                        {String(row.index + 1).padStart(2, '0')}
                    </span>
                ),
                size: 45,
                meta: {
                    headerClassName: 'text-center w-[45px]',
                    className: 'text-center w-[45px]',
                },
            },
            {
                accessorKey: 'nom',
                header: () => (
                    <span className="font-semibold text-xs">
                        Discipline & Libellé Bulletin
                    </span>
                ),
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <div className="space-y-0.5 whitespace-normal pr-2">
                            <div className="flex items-center gap-1.5 flex-wrap">
                                <span className="font-bold text-sm text-foreground leading-snug">
                                    {r.nom}
                                </span>
                                <span className="font-mono text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 px-1.5 py-0.2 rounded shrink-0">
                                    {r.code}
                                </span>
                            </div>
                            <span
                                className="text-[11px] text-muted-foreground block truncate max-w-67.5"
                                title={r.libelle_bulletin}
                            >
                                Relevé / Bulletin : <em>{r.libelle_bulletin}</em>
                            </span>
                        </div>
                    );
                },
                size: 280,
                meta: {
                    headerClassName: 'w-[280px] max-w-[300px]',
                    className: 'w-[280px] max-w-[300px]',
                },
            },
            {
                accessorKey: 'groupe_nom',
                header: () => (
                    <span className="font-semibold text-xs">
                        Pôle Pédagogique
                    </span>
                ),
                cell: ({ row }) => {
                    const config = getPoleBadgeConfig(
                        row.original.groupe_nom,
                        row.original.groupe_code,
                        row.original.groupe_couleur
                    );
                    const Icon = config.icon;
                    return (
                        <span
                            className={cn(
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold border shadow-2xs whitespace-nowrap',
                                config.className
                            )}
                        >
                            <Icon className={cn('size-3.5 shrink-0', config.iconColor)} />
                            <span>{config.label}</span>
                        </span>
                    );
                },
                size: 170,
                meta: {
                    headerClassName: 'w-[170px]',
                    className: 'w-[170px]',
                },
            },
            {
                accessorKey: 'coefficient',
                header: () => (
                    <span className="font-semibold text-xs text-center block">
                        Coef.
                    </span>
                ),
                cell: ({ row }) => {
                    const coef = row.original.coefficient;
                    return (
                        <div className="text-center">
                            <span
                                className={`inline-flex items-center justify-center size-7 rounded-lg font-bold text-xs ${
                                    coef >= 4
                                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-900'
                                        : coef >= 3
                                          ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-900'
                                          : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700'
                                }`}
                            >
                                {coef}
                            </span>
                        </div>
                    );
                },
                size: 65,
                meta: {
                    headerClassName: 'text-center w-[65px]',
                    className: 'text-center w-[65px]',
                },
            },
            {
                id: 'enseignant_select',
                header: () => (
                    <span className="font-semibold text-xs">
                        Enseignant Responsable
                    </span>
                ),
                cell: ({ row }) => {
                    const r = row.original;
                    const currentValue = r.personnel_id ? String(r.personnel_id) : 'none';

                    return (
                        <div className="w-full min-w-65">
                            <SearchableSelect
                                options={enseignantOptions}
                                value={currentValue}
                                onChange={(val) => {
                                    handleUpdateRow(
                                        r.matiere_id,
                                        'personnel_id',
                                        val === 'none' ? null : Number(val)
                                    );
                                }}
                                placeholder="Affecter un enseignant..."
                                searchPlaceholder="Chercher un enseignant ou matricule..."
                                className="h-9 text-xs w-full"
                                disabled={!can.manage}
                            />
                        </div>
                    );
                },
                meta: {
                    headerClassName: 'min-w-[280px]',
                    className: 'min-w-[280px]',
                },
            },
            {
                id: 'volume_horaire',
                header: () => (
                    <span className="font-semibold text-xs text-center block">
                        Vol. Hebdo (h)
                    </span>
                ),
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <div className="w-20 mx-auto">
                            <Input
                                type="number"
                                min="0"
                                max="50"
                                step="0.5"
                                value={r.volume_horaire_reel}
                                onChange={(e) => {
                                    handleUpdateRow(
                                        r.matiere_id,
                                        'volume_horaire_reel',
                                        Number(e.target.value)
                                    );
                                }}
                                disabled={!can.manage}
                                className="h-8 text-xs font-semibold text-center"
                            />
                        </div>
                    );
                },
                size: 105,
                meta: {
                    headerClassName: 'text-center w-[105px]',
                    className: 'text-center w-[105px]',
                },
            },
            {
                id: 'statut',
                header: () => (
                    <span className="font-semibold text-xs text-center block">
                        Statut
                    </span>
                ),
                cell: ({ row }) => {
                    const isAssigned = row.original.personnel_id !== null;
                    return (
                        <div className="text-center">
                            {isAssigned ? (
                                <span className="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    <CheckCircle2 className="size-3 text-emerald-600" /> Pourvu
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    <AlertCircle className="size-3 text-amber-600" /> Vacant
                                </span>
                            )}
                        </div>
                    );
                },
                size: 105,
                meta: {
                    headerClassName: 'text-center w-[105px]',
                    className: 'text-center w-[105px]',
                },
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) => {
                    const r = row.original;
                    if (!r.personnel_id || !can.manage) return null;

                    return (
                        <div className="text-center">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => handleUpdateRow(r.matiere_id, 'personnel_id', null)}
                                title="Dissocier l'enseignant de cette matière"
                                className="size-7 text-muted-foreground hover:text-rose-600 cursor-pointer"
                            >
                                <X className="size-3.5" />
                            </Button>
                        </div>
                    );
                },
                size: 45,
                meta: {
                    headerClassName: 'text-center w-[45px]',
                    className: 'text-center w-[45px]',
                },
            },
        ];
    }, [enseignantOptions, can.manage]);

    return (
        <>
            <Head
                title={
                    selectedClasse
                        ? `Grille Pédagogique - ${selectedClasse.nom} - SYGES`
                        : 'Grille Pédagogique des Classes - SYGES'
                }
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full pb-24">
                {/* FIL D'ARIANE ET NAVIGATION SUPÉRIEURE */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b pb-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Link
                                href="/administration/attributions"
                                className="hover:text-indigo-600 transition-colors inline-flex items-center gap-1 font-medium"
                            >
                                <ArrowLeft className="size-3.5" />
                                Répartition Pédagogique
                            </Link>
                            <span>/</span>
                            <span className="text-foreground font-semibold">Grille de Classe</span>
                        </div>

                        <div className="flex items-center gap-3 pt-1">
                            <div className="p-2.5 rounded-xl bg-linear-to-br from-indigo-500 to-indigo-700 text-white shadow-xs">
                                <FileSpreadsheet className="size-6" />
                            </div>
                            <div>
                                <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                                    Grille de Programmation : {selectedClasse ? selectedClasse.nom : 'Aucune classe'}
                                    {selectedClasse?.serie && (
                                        <Badge variant="outline" className="font-semibold text-xs border-indigo-300 text-indigo-700">
                                            Série {selectedClasse.serie}
                                        </Badge>
                                    )}
                                </h1>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Maquette officielle des disciplines, coefficients et affectation intégrale des professeurs pour cette classe.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* SÉLECTEUR RAPIDE DE CLASSE ET BOUTONS D'ACTION */}
                    <div className="flex items-center gap-3 flex-wrap w-full sm:w-auto">
                        <div className="w-full sm:w-56">
                            <SearchableSelect
                                options={classeOptions}
                                value={selectedClasse ? String(selectedClasse.id) : ''}
                                onChange={handleClassChange}
                                placeholder="Changer de classe..."
                                searchPlaceholder="Rechercher une classe..."
                                className="h-9 text-xs font-semibold bg-background w-full"
                            />
                        </div>

                        {can.manage && (
                            <Button
                                type="button"
                                disabled={isSaving}
                                onClick={handleSaveBatch}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-xs text-xs h-9 cursor-pointer w-full sm:w-auto"
                            >
                                <Save className="size-4 mr-1.5" />
                                {isSaving ? 'Enregistrement...' : 'Enregistrer la Grille'}
                            </Button>
                        )}
                    </div>
                </div>

                {/* BANNIÈRE RÉSUMÉ DE LA CLASSE (KPI & PROFESSEUR PRINCIPAL) */}
                {selectedClasse && (
                    <div className="rounded-2xl border bg-card p-5 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-center">
                        {/* 1. Niveau & Cycle */}
                        <div className="space-y-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground block">
                                Niveau & Cycle
                            </span>
                            <div className="flex items-center gap-2">
                                <span className="font-bold text-sm text-foreground">
                                    {selectedClasse.niveau?.nom || 'Niveau standard'}
                                </span>
                            </div>
                            <span className="text-xs text-muted-foreground block">
                                {selectedClasse.niveau?.cycle?.nom || 'Enseignement secondaire'}
                            </span>
                        </div>

                        {/* 2. Professeur Principal */}
                        <div className="space-y-1 sm:col-span-1 lg:col-span-2 border-t sm:border-t-0 sm:border-l sm:pl-4 border-border">
                            <div className="flex items-center justify-between">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground block">
                                    Professeur Principal
                                </span>
                                {can.prof_principal && (
                                    <button
                                        type="button"
                                        onClick={() => setIsPPModalOpen(true)}
                                        className="text-[11px] text-amber-600 hover:underline font-medium cursor-pointer"
                                    >
                                        Modifier
                                    </button>
                                )}
                            </div>
                            {selectedClasse.professeur_principal ? (
                                <div className="flex items-center gap-2 pt-0.5">
                                    <Award className="size-4 text-amber-600 shrink-0" />
                                    <div>
                                        <span className="font-bold text-xs text-foreground block">
                                            {selectedClasse.professeur_principal.nom} {selectedClasse.professeur_principal.prenom}
                                        </span>
                                        <span className="text-[11px] text-muted-foreground font-mono">
                                            Matr. {selectedClasse.professeur_principal.matricule}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => setIsPPModalOpen(true)}
                                    className="text-xs text-amber-600 hover:underline font-medium inline-flex items-center gap-1 cursor-pointer pt-0.5"
                                >
                                    <Plus className="size-3" /> Désigner le professeur principal
                                </button>
                            )}
                        </div>

                        {/* 3. Total Coefficients & Total Heures */}
                        <div className="space-y-1 border-t lg:border-t-0 lg:border-l lg:pl-4 border-border">
                            <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground block">
                                Total Coefficients & Heures
                            </span>
                            <div className="flex items-baseline gap-2">
                                <span className="font-extrabold text-lg text-foreground">
                                    {stats.totalCoefficients} Coefs
                                </span>
                                <span className="text-xs text-muted-foreground font-semibold">
                                    • {currentTotalHours.toFixed(1)} h / sem
                                </span>
                            </div>
                        </div>

                        {/* 4. Taux de Couverture des cours */}
                        <div className="space-y-1.5 border-t lg:border-t-0 lg:border-l lg:pl-4 border-border">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    Couverture
                                </span>
                                <span className={`font-bold text-xs ${
                                    currentPourvuesCount === rows.length ? 'text-emerald-600' : 'text-amber-600'
                                }`}>
                                    {currentPourvuesCount} / {rows.length} cours
                                </span>
                            </div>
                            <div className="w-full h-2 bg-muted rounded-full overflow-hidden">
                                <div
                                    className={`h-full transition-all duration-300 ${
                                        currentPourvuesCount === rows.length ? 'bg-emerald-500' : 'bg-amber-500'
                                    }`}
                                    style={{
                                        width: `${rows.length > 0 ? (currentPourvuesCount / rows.length) * 100 : 0}%`,
                                    }}
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* TABLEAU TANSTACK TABLE DE LA GRILLE PÉDAGOGIQUE */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <h2 className="text-base font-bold text-foreground">
                                Disciplines au Programme ({rows.length})
                            </h2>
                            {hasUnsavedChanges && (
                                <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border-amber-300 text-[11px] animate-pulse">
                                    Modifications non enregistrées
                                </Badge>
                            )}
                        </div>

                        {hasUnsavedChanges && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleReset}
                                className="text-xs h-8 cursor-pointer"
                            >
                                <RotateCcw className="size-3.5 mr-1" />
                                Annuler les changements
                            </Button>
                        )}
                    </div>

                    <div className="rounded-xl border bg-card shadow-xs overflow-hidden">
                        <DataTable
                            columns={columns}
                            data={rows}
                            searchPlaceholder="Filtrer une discipline dans la grille..."
                            defaultPageSize={50}
                            tableClassName="min-w-[960px] w-full"
                        />
                    </div>
                </div>

                {/* BARRE FLOTTANTE D'ACTION INFÉRIEURE EN CAS DE MODIFICATION */}
                {hasUnsavedChanges && can.manage && (
                    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-foreground text-background w-[calc(100%-2rem)] max-w-xl px-4 py-3 sm:px-6 sm:py-3.5 rounded-2xl shadow-2xl border border-border flex flex-col sm:flex-row items-center justify-between gap-3 animate-in slide-in-from-bottom-5">
                        <div className="flex items-center gap-2 text-center sm:text-left">
                            <div className="size-2 rounded-full bg-amber-400 animate-ping shrink-0" />
                            <span className="text-xs font-semibold">
                                Vous avez des affectations non sauvegardées pour cette classe.
                            </span>
                        </div>

                        <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={handleReset}
                                className="text-xs text-background hover:bg-background/20 h-8 cursor-pointer flex-1 sm:flex-initial"
                            >
                                Réinitialiser
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                disabled={isSaving}
                                onClick={handleSaveBatch}
                                className="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs h-8 cursor-pointer shadow-sm flex-1 sm:flex-initial"
                            >
                                <Save className="size-3.5 mr-1.5" />
                                {isSaving ? 'Enregistrement...' : 'Enregistrer maintenant'}
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {/* MODALE DE DÉSIGNATION DU PROFESSEUR PRINCIPAL */}
            <ProfesseurPrincipalModal
                classe={selectedClasse}
                isOpen={isPPModalOpen}
                onClose={() => setIsPPModalOpen(false)}
                enseignants={enseignants}
            />
        </>
    );
}

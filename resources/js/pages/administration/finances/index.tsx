import React, { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    AlertCircle,
    Award,
    Building2,
    Calendar,
    CheckCircle2,
    CheckSquare,
    ChevronDown,
    Compass,
    CreditCard,
    DollarSign,
    Edit,
    FileSpreadsheet,
    FileText,
    FolderOpen,
    FolderPlus,
    Layers,
    Lock,
    Plus,
    Printer,
    Receipt,
    ShieldCheck,
    SlidersHorizontal,
    Smartphone,
    Square,
    Tag,
    Trash2,
    Unlock,
    Wallet,
    X,
} from 'lucide-react';
import { toast } from 'sonner';
import { calculerSyntheseCategoriesGrille } from '@/lib/finance-categories';
import { formatDate } from '@/lib/utils';
import { useConfirmDelete } from '@/components/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EtablissementRef } from '@/types/eleve';
import {
    BourseRemise,
    CanFinancePermissions,
    CategorieFinanciere,
    CategorieFrais,
    ClotureCaisse,
    CompteFinancierEleve,
    formatCibleImputationLabel,
    FraisScolaire,
    JournalDuJour,
    NiveauFinanceRef,
    ReglementDivers,
    TypeFrais,
    VersementCaisse,
} from '@/types/finance';
import PanneauAffectationNiveaux from './components/panneau-affectation-niveaux';
import AnnulerVersementModal from './modals/annuler-versement-modal';
import ClotureCaisseModal from './modals/cloture-caisse-modal';
import GenerateurEtatsFinanciersModal, {
    type PeriodePreset,
    type TypeEtatFinancier,
} from './modals/generateur-etats-modal';
import ModeleBourseModal from './modals/modele-bourse-modal';
import NouveauReglementDiversModal from './modals/nouveau-reglement-divers-modal';
import TarifModal from './modals/tarif-modal';
import TypeFraisModal from './modals/type-frais-modal';

/**
 * Propriétés transmises par FinanceController::index à la vue principale de configuration financière & caisse.
 */
interface FinancesIndexProps {
    versements: VersementCaisse[];
    comptes: CompteFinancierEleve[];
    categoriesFrais?: CategorieFrais[];
    typesFrais: TypeFrais[];
    tarifs: FraisScolaire[];
    fraisOptionnels?: FraisScolaire[];
    reglementsDivers: ReglementDivers[];
    boursesRemises: BourseRemise[];
    categoriesRecettes?: CategorieFinanciere[];
    categoriesBourses?: CategorieFinanciere[];
    journalDuJour: JournalDuJour;
    historiqueClotures: ClotureCaisse[];
    niveaux: NiveauFinanceRef[];
    etablissements?: EtablissementRef[];
    currentEtablissement: { id: number; nom: string; verrou_solvabilite_actif?: boolean } | null;
    isSuperAdmin?: boolean;
    currentAnnee: { id: number; code: string; date_debut?: string | null; date_fin?: string | null } | null;
    filters: Record<string, string | undefined>;
    can?: CanFinancePermissions;
}

/**
 * Onglets épurés et ordonnés chronologiquement du Module Finances & Caisse (de 01 à 06).
 */
type FinanceTabKey =
    | 'types_frais'
    | 'tarifs'
    | 'bourses_remises'
    | 'versements'
    | 'reglements_divers'
    | 'journal';

/**
 * Vue principale du Module 11 : Catalogue des Frais, Grille par Niveau, Remises & Journal de Caisse.
 *
 * Parcours sans répétition :
 * - 01. Catalogue des Frais (avec sous-module léger « Catégories de Frais » : Libellé + Description)
 * - 02. Grille par Niveau (rattachement en masse d'un frais à plusieurs niveaux + synthèse par catégorie)
 * - 03. Bourses & Remises
 * - 04. Historique des Versements
 * - 05. Recettes Diverses
 * - 06. Journal & Clôtures
 *
 * @param {FinancesIndexProps} props Propriétés Inertia
 * @returns {JSX.Element} Interface principale Finances & Tarifs
 */
export default function FinancesIndex({
    versements,
    comptes,
    categoriesFrais = [],
    typesFrais,
    tarifs,
    reglementsDivers,
    boursesRemises,
    categoriesRecettes = [],
    categoriesBourses = [],
    journalDuJour,
    historiqueClotures,
    niveaux,
    etablissements = [],
    currentEtablissement,
    isSuperAdmin = false,
    currentAnnee,
    filters,
    can,
}: FinancesIndexProps) {
    // Droits granulaires Spatie Permission pour cadrer les actions sensibles du module financier
    const canEtats = can?.etats ?? can?.view ?? true;
    const canConfigureFrais = can?.frais_configure ?? can?.manage ?? false;
    const canManageBourses = can?.bourses_manage ?? can?.manage ?? false;
    const canRecettesDiverses = can?.recettes_diverses_create ?? can?.versements_create ?? false;
    const canCancelVersements = can?.versements_cancel ?? can?.manage ?? false;
    const canCloture = can?.caisses_cloture ?? can?.manage ?? false;
    const canVerrouSolvabilite = can?.verrou_solvabilite ?? can?.manage ?? false;

    const validTabs: FinanceTabKey[] = [
        'types_frais',
        'tarifs',
        'bourses_remises',
        'versements',
        'reglements_divers',
        'journal',
    ];
    const initialTab: FinanceTabKey = validTabs.includes(filters?.tab as FinanceTabKey)
        ? (filters.tab as FinanceTabKey)
        : 'types_frais';

    const [activeTab, setActiveTab] = useState<FinanceTabKey>(initialTab);
    const { confirmDelete, ConfirmDialog } = useConfirmDelete();

    // Sous-module Catégories de Frais dans l'Onglet 01 (filtre + formulaire léger Libellé + Description)
    const [catalogueCategorieFilter, setCatalogueCategorieFilter] = useState<string>('all');
    const [isCategorieFormOpen, setIsCategorieFormOpen] = useState<boolean>(false);
    const [categorieToEdit, setCategorieToEdit] = useState<CategorieFrais | null>(null);
    const [catNom, setCatNom] = useState<string>('');
    const [catDescription, setCatDescription] = useState<string>('');
    const [savingCategorie, setSavingCategorie] = useState<boolean>(false);

    // Filtres rapides et mode d'affichage pleine largeur de l'Onglet 02 (Grille par Niveau)
    const [grilleMode, setGrilleMode] = useState<'liste' | 'affectation'>('liste');
    const [grilleNiveauFilter, setGrilleNiveauFilter] = useState<string>('all');
    const [grilleCategorieFilter, setGrilleCategorieFilter] = useState<string>('all');

    // États des modales et de l'annulation unitaire / groupée de versements
    const [isTypeFraisModalOpen, setIsTypeFraisModalOpen] = useState(false);
    const [typeFraisToEdit, setTypeFraisToEdit] = useState<TypeFrais | null>(null);

    const [preselectedTypeFraisForNiveaux, setPreselectedTypeFraisForNiveaux] =
        useState<TypeFrais | null>(null);

    const [isTarifModalOpen, setIsTarifModalOpen] = useState(false);
    const [tarifToEdit, setTarifToEdit] = useState<FraisScolaire | null>(null);

    const [isModeleBourseModalOpen, setIsModeleBourseModalOpen] = useState(false);
    const [bourseToEdit, setBourseToEdit] = useState<BourseRemise | null>(null);

    const [isReglementDiversModalOpen, setIsReglementDiversModalOpen] = useState(false);
    const [versementToCancel, setVersementToCancel] = useState<VersementCaisse | null>(null);
    const [selectedVersementIds, setSelectedVersementIds] = useState<number[]>([]);
    const [isBatchCancelModalOpen, setIsBatchCancelModalOpen] = useState<boolean>(false);
    const [isClotureModalOpen, setIsClotureModalOpen] = useState(false);

    // Générateur d'états financiers multicritères (période date_debut/date_fin + hiérarchie scolaire)
    const [isEtatsModalOpen, setIsEtatsModalOpen] = useState<boolean>(false);
    const [etatInitialType, setEtatInitialType] = useState<TypeEtatFinancier>('journal_synthetique');
    const [etatInitialPreset, setEtatInitialPreset] = useState<PeriodePreset>('mois');

    /**
     * Ouvre la fenêtre modale du générateur d'états financiers avec un type d'état et une période pré-sélectionnés.
     */
    const openGenerateurEtats = (
        type: TypeEtatFinancier = 'journal_synthetique',
        preset: PeriodePreset = 'mois'
    ) => {
        setEtatInitialType(type);
        setEtatInitialPreset(preset);
        setIsEtatsModalOpen(true);
    };

    /**
     * Changement d'établissement actif pour le Super-Administrateur multi-campus.
     */
    const handleChangeEtablissement = (etabId: string) => {
        router.get(
            '/administration/finances',
            { etablissement_id: etabId, tab: activeTab },
            { preserveScroll: true }
        );
    };

    /**
     * Bascule directement vers l'espace pleine largeur à 2 colonnes de l'Onglet 02
     * pour rattacher un ou plusieurs frais du Catalogue aux Niveaux (sans modale étriquée).
     */
    const ouvrirAffectationPleineLargeur = (fraisPreselectionne: TypeFrais | null = null) => {
        setPreselectedTypeFraisForNiveaux(fraisPreselectionne);
        setGrilleMode('affectation');
        setActiveTab('tarifs');
    };

    /**
     * Liste des versements valides actuellement cochés pour une annulation groupée.
     */
    const selectedValidVersements = useMemo(
        () =>
            versements.filter(
                (v) => v.statut === 'valide' && selectedVersementIds.includes(v.id)
            ),
        [versements, selectedVersementIds]
    );

    /**
     * Ouvre le formulaire intégré de création ou modification d'une Catégorie de Frais.
     */
    const openCategorieEditor = (cat: CategorieFrais | null = null) => {
        setCategorieToEdit(cat);
        setCatNom(cat?.nom ?? '');
        setCatDescription(cat?.description ?? '');
        setIsCategorieFormOpen(true);
    };

    /**
     * Enregistre (création ou édition) une Catégorie de Frais dans le sous-module léger de l'Onglet 01.
     */
    const handleSaveCategorieFrais = (e: React.FormEvent) => {
        e.preventDefault();
        const nomTrim = catNom.trim();
        if (!nomTrim || !currentEtablissement) return;

        setSavingCategorie(true);

        if (categorieToEdit) {
            router.put(
                `/administration/finances/categories-frais/${categorieToEdit.id}`,
                {
                    nom: nomTrim,
                    description: catDescription.trim() || null,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success(`Catégorie « ${nomTrim} » mise à jour.`, {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        });
                        setIsCategorieFormOpen(false);
                        setCategorieToEdit(null);
                        setCatNom('');
                        setCatDescription('');
                    },
                    onError: (err) => {
                        const first = Object.values(err)[0] as string;
                        toast.error(first || 'Impossible de modifier cette catégorie.', {
                            position: 'top-right',
                        });
                    },
                    onFinish: () => setSavingCategorie(false),
                }
            );
            return;
        }

        router.post(
            '/administration/finances/categories-frais',
            {
                etablissement_id: currentEtablissement.id,
                nom: nomTrim,
                description: catDescription.trim() || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Catégorie « ${nomTrim} » enregistrée avec succès.`, {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    setIsCategorieFormOpen(false);
                    setCatNom('');
                    setCatDescription('');
                },
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    toast.error(first || 'Impossible de créer cette catégorie.', {
                        position: 'top-right',
                    });
                },
                onFinish: () => setSavingCategorie(false),
            }
        );
    };

    /**
     * Liste des frais du Catalogue filtrée selon la catégorie sélectionnée dans le sous-module.
     */
    const filteredCatalogueFrais = useMemo(() => {
        if (catalogueCategorieFilter === 'all') return typesFrais;
        if (catalogueCategorieFilter === 'uncategorized') {
            return typesFrais.filter((tf) => !tf.categorie_frais_id);
        }
        return typesFrais.filter(
            (tf) => String(tf.categorie_frais_id ?? '') === catalogueCategorieFilter
        );
    }, [typesFrais, catalogueCategorieFilter]);

    /**
     * Liste des lignes de la Grille par Niveau filtrée par Niveau et/ou Catégorie.
     */
    const filteredTarifs = useMemo(() => {
        return tarifs.filter((t) => {
            if (grilleNiveauFilter !== 'all' && String(t.niveau_id) !== grilleNiveauFilter) {
                return false;
            }
            if (grilleCategorieFilter !== 'all') {
                const catId = t.type_frais?.categorie_frais_id;
                if (String(catId ?? '') !== grilleCategorieFilter) return false;
            }
            return true;
        });
    }, [tarifs, grilleNiveauFilter, grilleCategorieFilter]);

    /**
     * Cartes de synthèse par Catégorie pour l'onglet `02. Grille par Niveau`.
     */
    const syntheseGrilleParCategorie = useMemo(() => {
        const baseTarifsNiveau =
            grilleNiveauFilter === 'all'
                ? tarifs
                : tarifs.filter((t) => String(t.niveau_id) === grilleNiveauFilter);
        return calculerSyntheseCategoriesGrille(baseTarifsNiveau, categoriesFrais);
    }, [tarifs, categoriesFrais, grilleNiveauFilter]);

    /**
     * Total obligatoire unitaire lorsqu'un Niveau spécifique est sélectionné dans l'Onglet 02.
     */
    const totalObligatoireNiveauSelectionne = useMemo(() => {
        if (grilleNiveauFilter === 'all') return null;
        return syntheseGrilleParCategorie.reduce((sum, item) => sum + item.montantObligatoire, 0);
    }, [grilleNiveauFilter, syntheseGrilleParCategorie]);

    /**
     * 01. Colonnes TanStack Table pour le Catalogue des Frais.
     */
    const typesFraisColumns = useMemo<ColumnDef<TypeFrais>[]>(() => [
        {
            accessorKey: 'ordre_priorite',
            header: 'Priorité',
            cell: ({ row }) => (
                <Badge className="bg-slate-800 dark:bg-slate-700 text-white text-[10px] font-bold tabular-nums">
                    #{row.original.ordre_priorite ?? 10}
                </Badge>
            ),
        },
        {
            accessorKey: 'libelle',
            header: 'Frais & Infos complémentaires',
            cell: ({ row }) => (
                <div>
                    <div className="font-bold text-xs text-slate-900 dark:text-slate-100">
                        {row.original.libelle}
                    </div>
                    {row.original.description && (
                        <div className="text-[11px] text-slate-500 line-clamp-1 mt-0.5">
                            {row.original.description}
                        </div>
                    )}
                </div>
            ),
        },
        {
            id: 'categorie_frais',
            header: 'Catégorie',
            cell: ({ row }) => {
                const nomCat = row.original.categorie_frais?.nom;
                return nomCat ? (
                    <Badge
                        variant="outline"
                        className="text-[10px] font-semibold bg-slate-50 dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300"
                    >
                        {nomCat}
                    </Badge>
                ) : (
                    <span className="text-[11px] text-slate-400 italic">Non classé</span>
                );
            },
        },
        {
            accessorKey: 'obligatoire',
            header: 'Caractère',
            cell: ({ row }) =>
                row.original.obligatoire ? (
                    <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 text-[10px] font-semibold">
                        Obligatoire
                    </Badge>
                ) : (
                    <Badge className="bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border-sky-300 text-[10px] font-semibold">
                        Optionnel (à la carte)
                    </Badge>
                ),
        },
        {
            accessorKey: 'date_limite_defaut',
            header: 'Date Butoir',
            cell: ({ row }) => (
                <span className="text-xs text-slate-600 dark:text-slate-400 flex items-center gap-1">
                    <Calendar className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    {row.original.date_limite_defaut
                        ? formatDate(row.original.date_limite_defaut)
                        : "Dès l'inscription"}
                </span>
            ),
        },
        {
            accessorKey: 'montant_defaut',
            header: () => <div className="text-right">Montant</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-extrabold text-slate-900 dark:text-slate-100 tabular-nums">
                    {Number(row.original.montant_defaut || 0).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            id: 'niveaux_rattaches',
            header: 'Niveaux Rattachés',
            cell: ({ row }) => {
                const affectations = row.original.frais_scolaires ?? [];
                const nomsNiveaux = Array.from(
                    new Set(affectations.map((a) => a.niveau?.nom).filter(Boolean))
                ) as string[];

                return (
                    <div className="flex flex-wrap items-center gap-1">
                        {nomsNiveaux.length === 0 ? (
                            <span className="text-[11px] text-amber-600 font-medium">
                                Aucun niveau
                            </span>
                        ) : (
                            <>
                                {nomsNiveaux.slice(0, 3).map((nom) => (
                                    <Badge
                                        key={nom}
                                        variant="secondary"
                                        className="text-[10px] px-1.5 py-0 font-semibold"
                                    >
                                        {nom}
                                    </Badge>
                                ))}
                                {nomsNiveaux.length > 3 && (
                                    <Badge
                                        variant="outline"
                                        className="text-[10px] px-1.5 py-0 font-bold text-emerald-700 border-emerald-300"
                                        title={nomsNiveaux.join(', ')}
                                    >
                                        +{nomsNiveaux.length - 3}
                                    </Badge>
                                )}
                            </>
                        )}
                        {canConfigureFrais && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-6 px-1.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-50"
                                title="Rattacher ce frais à un ou plusieurs niveaux dans l'espace pleine largeur"
                                onClick={() => ouvrirAffectationPleineLargeur(row.original)}
                            >
                                + Niveaux
                            </Button>
                        )}
                    </div>
                );
            },
        },
        ...(canConfigureFrais
            ? [
                  {
                      id: 'actions',
                      header: () => <div className="text-right">Actions</div>,
                      cell: ({ row }: { row: { original: TypeFrais } }) => {
                          const nbRattachements = (row.original.frais_scolaires ?? []).length;
                          const estLieAuxNiveaux = nbRattachements > 0;

                          return (
                              <div className="text-right flex items-center justify-end gap-1">
                                  <Button
                                      variant="ghost"
                                      size="sm"
                                      className="h-7 w-7 p-0 text-slate-500 hover:text-emerald-700"
                                      title="Modifier ce frais (synchronise les niveaux rattachés)"
                                      onClick={() => {
                                          setTypeFraisToEdit(row.original);
                                          setIsTypeFraisModalOpen(true);
                                      }}
                                  >
                                      <Edit className="w-3.5 h-3.5" />
                                  </Button>
                                  <Button
                                      variant="ghost"
                                      size="sm"
                                      className={`h-7 w-7 p-0 ${
                                          estLieAuxNiveaux
                                              ? 'text-slate-300 hover:text-amber-600'
                                              : 'text-red-500 hover:text-red-700 hover:bg-red-50'
                                      }`}
                                      title={
                                          estLieAuxNiveaux
                                              ? `Suppression verrouillée : ce frais est rattaché à ${nbRattachements} ligne(s) de niveau`
                                              : 'Supprimer ce frais du catalogue'
                                      }
                                      onClick={() => {
                                          if (estLieAuxNiveaux) {
                                              toast.error(
                                                  `Suppression impossible : le frais « ${row.original.libelle} » est actuellement appliqué à ${nbRattachements} niveau(x). Retirez-le d'abord de la Grille par Niveau.`,
                                                  { position: 'top-right' }
                                              );
                                              return;
                                          }

                                          confirmDelete({
                                              title: 'Supprimer ce frais du catalogue',
                                              itemType: 'le frais',
                                              itemName: row.original.libelle,
                                              description:
                                                  'Êtes-vous sûr de vouloir supprimer ce frais non rattaché du catalogue ?',
                                              onConfirm: () => {
                                                  router.delete(
                                                      `/administration/finances/types-frais/${row.original.id}`,
                                                      {
                                                          preserveScroll: true,
                                                          onSuccess: () =>
                                                              toast.success(
                                                                  'Frais retiré du catalogue.',
                                                                  {
                                                                      position: 'top-right',
                                                                      className:
                                                                          'bg-emerald-600 text-white border-none',
                                                                  }
                                                              ),
                                                          onError: (err) => {
                                                              const msg =
                                                                  (err as Record<string, string>)
                                                                      ?.error ||
                                                                  Object.values(err)[0] ||
                                                                  'Suppression impossible : ce frais est déjà lié.';
                                                              toast.error(String(msg), {
                                                                  position: 'top-right',
                                                              });
                                                          },
                                                      }
                                                  );
                                              },
                                          });
                                      }}
                                  >
                                      {estLieAuxNiveaux ? (
                                          <Lock className="w-3.5 h-3.5" />
                                      ) : (
                                          <Trash2 className="w-3.5 h-3.5" />
                                      )}
                                  </Button>
                              </div>
                          );
                      },
                  } satisfies ColumnDef<TypeFrais>,
              ]
            : []),
    ], [canConfigureFrais, confirmDelete]);

    /**
     * 02. Colonnes TanStack Table pour la Grille par Niveau (avec Catégorie, Priorité et Date Butoir jj-mm-aaaa).
     */
    const tarifsColumns = useMemo<ColumnDef<FraisScolaire>[]>(() => [
        {
            accessorKey: 'niveau.nom',
            header: 'Niveau',
            cell: ({ row }) => (
                <div className="flex items-center gap-1.5">
                    <span className="font-bold text-xs text-slate-900 dark:text-slate-100">
                        {row.original.niveau?.nom ?? 'Tous niveaux'}
                    </span>
                    {row.original.serie && (
                        <Badge variant="outline" className="text-[10px]">
                            Série {row.original.serie}
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            id: 'categorie_nom',
            header: 'Catégorie',
            cell: ({ row }) => {
                const nomCat = row.original.type_frais?.categorie_frais?.nom;
                return (
                    <Badge
                        variant="outline"
                        className="text-[10px] font-semibold bg-slate-50 dark:bg-slate-900"
                    >
                        {nomCat ?? 'Scolarité'}
                    </Badge>
                );
            },
        },
        {
            id: 'frais_libelle',
            header: 'Frais Configuré',
            cell: ({ row }) => {
                const libelle =
                    row.original.libelle_tranche ||
                    row.original.type_frais?.libelle ||
                    `Tranche ${row.original.tranche_numero}`;
                return (
                    <div className="flex items-center gap-1.5">
                        <span className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                            {libelle}
                        </span>
                        {!row.original.type_frais?.obligatoire && (
                            <Badge className="bg-sky-100 text-sky-800 border-sky-200 text-[10px]">
                                Optionnel
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'priorite_effective',
            header: 'Priorité',
            cell: ({ row }) => {
                const prio =
                    row.original.ordre_priorite ?? row.original.type_frais?.ordre_priorite ?? 10;
                return (
                    <Badge
                        variant="outline"
                        className="text-[10px] font-bold text-slate-700 dark:text-slate-300 tabular-nums"
                    >
                        #{prio}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'date_limite',
            header: 'Date Butoir',
            cell: ({ row }) => (
                <span className="text-xs text-slate-600 dark:text-slate-400 flex items-center gap-1">
                    <Calendar className="w-3.5 h-3.5 text-slate-400" />
                    {row.original.date_limite
                        ? formatDate(row.original.date_limite)
                        : "Dès l'inscription"}
                </span>
            ),
        },
        {
            accessorKey: 'montant',
            header: () => <div className="text-right">Montant Exigible</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-extrabold text-slate-900 dark:text-slate-100 tabular-nums">
                    {Number(row.original.montant).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        ...(canConfigureFrais
            ? [
                  {
                      id: 'actions',
                      header: () => <div className="text-right">Actions</div>,
                      cell: ({ row }: { row: { original: FraisScolaire } }) => (
                          <div className="text-right flex items-center justify-end gap-1">
                              <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-7 w-7 p-0 text-slate-600 hover:text-emerald-700"
                                  title="Ajuster cette ligne de niveau"
                                  onClick={() => {
                                      setTarifToEdit(row.original);
                                      setIsTarifModalOpen(true);
                                  }}
                              >
                                  <Edit className="w-3.5 h-3.5" />
                              </Button>
                              <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-7 w-7 p-0 text-red-500 hover:text-red-700"
                                  title="Retirer ce frais de ce niveau"
                                  onClick={() => {
                                      const tarifNom = `${row.original.type_frais?.libelle ?? 'Frais'} (${row.original.niveau?.nom ?? 'Niveau'})`;
                                      confirmDelete({
                                          title: 'Retirer ce frais du niveau',
                                          itemType: 'le rattachement',
                                          itemName: tarifNom,
                                          description:
                                              'Ce frais ne sera plus appliqué à ce niveau et les soldes des élèves de ce niveau seront recalculés.',
                                          onConfirm: () => {
                                              router.delete(
                                                  `/administration/finances/tarifs/${row.original.id}`,
                                                  {
                                                      preserveScroll: true,
                                                      onSuccess: () =>
                                                          toast.success(
                                                              'Frais retiré de ce niveau avec succès.',
                                                              {
                                                                  position: 'top-right',
                                                                  className:
                                                                      'bg-emerald-600 text-white border-none',
                                                              }
                                                          ),
                                                      onError: (err) => {
                                                          const msg =
                                                              (err as Record<string, string>)
                                                                  ?.error ||
                                                              Object.values(err)[0] ||
                                                              'Suppression impossible : cette ligne est déjà mouvementée.';
                                                          toast.error(String(msg), {
                                                              position: 'top-right',
                                                          });
                                                      },
                                                  }
                                              );
                                          },
                                      });
                                  }}
                              >
                                  <Trash2 className="w-3.5 h-3.5" />
                              </Button>
                          </div>
                      ),
                  } satisfies ColumnDef<FraisScolaire>,
              ]
            : []),
    ], [canConfigureFrais, confirmDelete]);

    /**
     * 03. Colonnes TanStack Table épurées pour les Modèles de Bourses & Remises.
     */
    const boursesRemisesColumns = useMemo<ColumnDef<BourseRemise>[]>(() => [
        {
            accessorKey: 'nom',
            header: 'Libellé de la Remise / Bourse',
            cell: ({ row }) => (
                <div className="text-xs font-bold text-slate-800 dark:text-slate-200">
                    {row.original.nom}
                </div>
            ),
        },
        {
            accessorKey: 'categorie',
            header: 'Catégorie',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">
                    {row.original.categorie}
                </Badge>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Nature',
            cell: ({ row }) => (
                <Badge
                    className={
                        row.original.type === 'bourse'
                            ? 'bg-emerald-700 text-white text-[10px]'
                            : 'bg-amber-600 text-white text-[10px]'
                    }
                >
                    {row.original.type === 'bourse' ? "Bourse d'études" : 'Remise / Bonus'}
                </Badge>
            ),
        },
        {
            accessorKey: 'valeur',
            header: () => <div className="text-right">Avantage Accordé</div>,
            cell: ({ row }) => {
                const b = row.original;
                return (
                    <div className="text-right text-xs font-extrabold text-emerald-700 dark:text-emerald-400 tabular-nums">
                        {b.mode_calcul === 'pourcentage'
                            ? `-${b.valeur} %`
                            : `-${Number(b.valeur).toLocaleString('fr-FR')} FCFA`}
                    </div>
                );
            },
        },
        {
            id: 'imputation',
            header: 'Imputation par défaut',
            cell: ({ row }) => (
                <span className="text-[11px] font-medium text-slate-600 dark:text-slate-400">
                    Par élève • {formatCibleImputationLabel(row.original.cible_imputation)}
                </span>
            ),
        },
        ...(canManageBourses
            ? [
                  {
                      id: 'actions',
                      header: () => <div className="text-right">Actions</div>,
                      cell: ({ row }: { row: { original: BourseRemise } }) => {
                          const b = row.original;
                          return (
                              <div className="flex items-center justify-end gap-1">
                                  <Button
                                      variant="ghost"
                                      size="sm"
                                      className="h-7 w-7 p-0 text-slate-600 hover:text-emerald-700"
                                      onClick={() => {
                                          setBourseToEdit(b);
                                          setIsModeleBourseModalOpen(true);
                                      }}
                                  >
                                      <Edit className="w-3.5 h-3.5" />
                                  </Button>
                                  <Button
                                      variant="ghost"
                                      size="sm"
                                      className="h-7 w-7 p-0 text-red-500 hover:text-red-700"
                                      onClick={() => {
                                          confirmDelete({
                                              title: 'Supprimer ce modèle',
                                              itemType: 'le modèle',
                                              itemName: b.nom,
                                              description:
                                                  'Êtes-vous sûr de vouloir supprimer ce modèle de bourse/remise ?',
                                              onConfirm: () => {
                                                  router.delete(
                                                      `/administration/finances/bourses-remises/${b.id}`,
                                                      {
                                                          preserveScroll: true,
                                                          onSuccess: () =>
                                                              toast.success(
                                                                  'Modèle supprimé avec succès.',
                                                                  {
                                                                      position: 'top-right',
                                                                      className:
                                                                          'bg-emerald-600 text-white border-none',
                                                                  }
                                                              ),
                                                          onError: (err) => {
                                                              const msg =
                                                                  (err as Record<string, string>)
                                                                      ?.error ||
                                                                  Object.values(err)[0] ||
                                                                  'Suppression impossible : ce modèle est déjà attribué à des élèves.';
                                                              toast.error(String(msg), {
                                                                  position: 'top-right',
                                                              });
                                                          },
                                                      }
                                                  );
                                              },
                                          });
                                      }}
                                  >
                                      <Trash2 className="w-3.5 h-3.5" />
                                  </Button>
                              </div>
                          );
                      },
                  } satisfies ColumnDef<BourseRemise>,
              ]
            : []),
    ], [canManageBourses, confirmDelete]);

    /**
     * 04. Colonnes TanStack Table pour l'Historique des Versements Scolarité
     * (avec recherche par nom/matricule et cases à cocher pour annuler plusieurs opérations en 1 clic).
     */
    const versementsColumns = useMemo<ColumnDef<VersementCaisse>[]>(() => [
        ...(canCancelVersements
            ? [
                  {
                      id: 'select',
                      header: ({ table }) => {
                          const validFilteredIds = table
                              .getFilteredRowModel()
                              .rows.filter((r) => r.original.statut === 'valide')
                              .map((r) => r.original.id);
                          const allChecked =
                              validFilteredIds.length > 0 &&
                              validFilteredIds.every((id) => selectedVersementIds.includes(id));

                          return (
                              <button
                                  type="button"
                                  disabled={validFilteredIds.length === 0}
                                  title={
                                      allChecked
                                          ? 'Tout décocher'
                                          : `Cocher les ${validFilteredIds.length} reçus valides affichés`
                                  }
                                  onClick={() => {
                                      if (allChecked) {
                                          setSelectedVersementIds((prev) =>
                                              prev.filter((id) => !validFilteredIds.includes(id))
                                          );
                                      } else {
                                          setSelectedVersementIds((prev) =>
                                              Array.from(new Set([...prev, ...validFilteredIds]))
                                          );
                                      }
                                  }}
                                  className="flex items-center justify-center cursor-pointer disabled:opacity-30"
                              >
                                  {allChecked ? (
                                      <CheckSquare className="w-4 h-4 text-red-600" />
                                  ) : (
                                      <Square className="w-4 h-4 text-slate-400" />
                                  )}
                              </button>
                          );
                      },
                      cell: ({ row }) => {
                          const v = row.original;
                          if (v.statut !== 'valide') {
                              return <span className="inline-block w-4" />;
                          }
                          const checked = selectedVersementIds.includes(v.id);
                          return (
                              <button
                                  type="button"
                                  onClick={() =>
                                      setSelectedVersementIds((prev) =>
                                          prev.includes(v.id)
                                              ? prev.filter((id) => id !== v.id)
                                              : [...prev, v.id]
                                      )
                                  }
                                  className="flex items-center justify-center cursor-pointer"
                                  title="Sélectionner ce reçu pour une annulation groupée"
                              >
                                  {checked ? (
                                      <CheckSquare className="w-4 h-4 text-red-600" />
                                  ) : (
                                      <Square className="w-4 h-4 text-slate-400 hover:text-slate-600" />
                                  )}
                              </button>
                          );
                      },
                  } satisfies ColumnDef<VersementCaisse>,
              ]
            : []),
        {
            accessorKey: 'reference_recu',
            header: 'N° Reçu',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-slate-800 dark:text-slate-200">
                    {row.original.reference_recu}
                </span>
            ),
        },
        {
            accessorKey: 'date_versement',
            header: 'Date',
            cell: ({ row }) => (
                <span className="text-xs">{formatDate(row.original.date_versement)}</span>
            ),
        },
        {
            id: 'eleve',
            accessorFn: (row) =>
                `${row.compte_financier?.eleve?.nom ?? ''} ${row.compte_financier?.eleve?.prenom ?? ''} ${row.compte_financier?.eleve?.matricule ?? ''} ${row.inscription?.classe?.nom ?? ''}`,
            header: 'Élève / Classe',
            cell: ({ row }) => {
                const eleve = row.original.compte_financier?.eleve;
                const classe = row.original.inscription?.classe?.nom ?? 'N/A';
                return (
                    <div>
                        <div className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                            {eleve?.nom} {eleve?.prenom}
                        </div>
                        <div className="text-[10px] text-slate-500">
                            Mat: {eleve?.matricule} | {classe}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'montant_total',
            header: () => <div className="text-right">Montant</div>,
            cell: ({ row }) => (
                <div className="text-right font-bold text-xs text-emerald-600 tabular-nums">
                    {Number(row.original.montant_total).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'mode_paiement',
            header: 'Mode',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px] uppercase">
                    {row.original.mode_paiement.replace(/_/g, ' ')}
                </Badge>
            ),
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: ({ row }) => {
                const s = row.original.statut;
                return (
                    <Badge
                        variant={s === 'valide' ? 'default' : 'destructive'}
                        className={s === 'valide' ? 'bg-emerald-600 text-white text-[10px]' : 'text-[10px]'}
                    >
                        {s === 'valide' ? 'Validé' : 'Annulé'}
                    </Badge>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => {
                const v = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 text-slate-600 hover:text-emerald-700"
                            onClick={() =>
                                window.open(`/administration/finances/versements/${v.id}/recu`, '_blank')
                            }
                        >
                            <Printer className="w-3.5 h-3.5" /> Reçu
                        </Button>
                        {canCancelVersements && v.statut === 'valide' && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs text-red-500 hover:text-red-700"
                                onClick={() => setVersementToCancel(v)}
                            >
                                Annuler
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ], [canCancelVersements, selectedVersementIds]);

    /**
     * 05. Colonnes TanStack Table pour les Recettes Diverses.
     */
    const reglementsDiversColumns = useMemo<ColumnDef<ReglementDivers>[]>(() => [
        {
            accessorKey: 'reference_recu',
            header: 'N° Reçu',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-slate-800 dark:text-slate-200">
                    {row.original.reference_recu}
                </span>
            ),
        },
        {
            accessorKey: 'date_reglement',
            header: 'Date',
            cell: ({ row }) => (
                <span className="text-xs">{formatDate(row.original.date_reglement)}</span>
            ),
        },
        {
            accessorKey: 'payeur_nom',
            header: 'Payeur / Client',
            cell: ({ row }) => (
                <div>
                    <div className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                        {row.original.payeur_nom}
                    </div>
                    {row.original.payeur_telephone && (
                        <div className="text-[10px] text-slate-500">{row.original.payeur_telephone}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'categorie',
            header: 'Catégorie',
            cell: ({ row }) => (
                <Badge variant="secondary" className="text-[10px]">
                    {row.original.categorie}
                </Badge>
            ),
        },
        {
            accessorKey: 'libelle',
            header: 'Désignation',
            cell: ({ row }) => <span className="text-xs font-medium">{row.original.libelle}</span>,
        },
        {
            accessorKey: 'montant',
            header: () => <div className="text-right">Montant</div>,
            cell: ({ row }) => (
                <div className="text-right font-bold text-xs text-emerald-600 tabular-nums">
                    {Number(row.original.montant).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: ({ row }) => {
                const s = row.original.statut;
                return (
                    <Badge
                        variant={s === 'valide' ? 'default' : 'destructive'}
                        className={s === 'valide' ? 'bg-emerald-600 text-white text-[10px]' : 'text-[10px]'}
                    >
                        {s === 'valide' ? 'Validé' : 'Annulé'}
                    </Badge>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => {
                const r = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 text-slate-600 hover:text-emerald-700"
                            onClick={() =>
                                window.open(
                                    `/administration/finances/reglements-divers/${r.id}/recu`,
                                    '_blank'
                                )
                            }
                        >
                            <Printer className="w-3.5 h-3.5" /> Reçu
                        </Button>
                        {canCancelVersements && r.statut === 'valide' && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs text-red-500 hover:text-red-700"
                                onClick={() => {
                                    confirmDelete({
                                        title: 'Annuler cette recette diverse',
                                        itemType: 'la recette',
                                        itemName: r.reference_recu,
                                        description:
                                            'Êtes-vous sûr de vouloir annuler cette écriture de recette ?',
                                        onConfirm: () => {
                                            router.post(
                                                `/administration/finances/reglements-divers/${r.id}/annuler`,
                                                {
                                                    motif_annulation:
                                                        'Annulation administrative à la demande du responsable',
                                                },
                                                {
                                                    onSuccess: () =>
                                                        toast.success('Recette diverse annulée.', {
                                                            position: 'top-right',
                                                            className:
                                                                'bg-emerald-600 text-white border-none',
                                                        }),
                                                }
                                            );
                                        },
                                    });
                                }}
                            >
                                Annuler
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ], [canCancelVersements, confirmDelete]);

    /**
     * 06. Colonnes TanStack Table pour l'Historique des Clôtures Journalières.
     */
    const cloturesColumns = useMemo<ColumnDef<ClotureCaisse>[]>(() => [
        {
            accessorKey: 'date_cloture',
            header: 'Date Clôture',
            cell: ({ row }) => (
                <span className="font-semibold text-xs">{formatDate(row.original.date_cloture)}</span>
            ),
        },
        {
            accessorKey: 'nombre_transactions',
            header: 'Nb Transactions',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">
                    {row.original.nombre_transactions} reçus
                </Badge>
            ),
        },
        {
            accessorKey: 'total_especes',
            header: () => <div className="text-right">Espèces</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs tabular-nums">
                    {Number(row.original.total_especes).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'total_mobile_money',
            header: () => <div className="text-right">Mobile Money</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs tabular-nums">
                    {Number(row.original.total_mobile_money).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'total_general',
            header: () => <div className="text-right">Total Général</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-bold text-emerald-600 tabular-nums">
                    {Number(row.original.total_general).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: () => (
                <Badge className="bg-slate-800 text-white text-[10px] flex items-center gap-1 w-fit">
                    <Lock className="w-3 h-3" /> Verrouillé
                </Badge>
            ),
        },
    ], []);

    /**
     * Active ou désactive le verrou de solvabilité sur les bulletins scolaires de l'établissement.
     */
    const handleToggleVerrouSolvabilite = () => {
        if (!currentEtablissement) return;
        const nouvelEtat = !currentEtablissement.verrou_solvabilite_actif;

        router.post(
            `/administration/finances/etablissements/${currentEtablissement.id}/verrou-solvabilite`,
            { verrou_solvabilite_actif: nouvelEtat },
            {
                onSuccess: () => {
                    toast.success(
                        `Le verrou de solvabilité est désormais ${nouvelEtat ? 'ACTIVÉ' : 'DÉSACTIVÉ'}.`,
                        {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        }
                    );
                },
            }
        );
    };

    const nomNiveauFiltre = useMemo(() => {
        if (grilleNiveauFilter === 'all') return null;
        return niveaux.find((n) => String(n.id) === grilleNiveauFilter)?.nom ?? null;
    }, [niveaux, grilleNiveauFilter]);

    return (
        <>
            <Head title="Finances & Comptabilité Scolaire - SYGES" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 min-w-0 w-full">
                {/* En-tête de la page */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div className="min-w-0">
                        <div className="flex items-start sm:items-center gap-2.5 min-w-0">
                            <div className="p-2 bg-emerald-600 rounded-lg text-white shrink-0">
                                <Wallet className="w-5 h-5" />
                            </div>
                            <div className="min-w-0">
                                <h1 className="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                                    Finances & Paramétrage Scolaire
                                </h1>
                                <p className="text-xs text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span>
                                        Établissement :{' '}
                                        <strong className="text-slate-800 dark:text-slate-200">
                                            {currentEtablissement?.nom}
                                        </strong>
                                    </span>
                                    {currentAnnee && (
                                        <span>
                                            | Année Scolaire :{' '}
                                            <span className="font-semibold text-emerald-600">
                                                {currentAnnee.code}
                                            </span>
                                        </span>
                                    )}
                                    <span>
                                        | Effectif suivi : <strong>{comptes.length} élèves</strong>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        {/* Sélecteur d'établissement rapide pour le Super-Admin multi-campus */}
                        {isSuperAdmin && etablissements.length > 1 && (
                            <Select
                                value={currentEtablissement ? String(currentEtablissement.id) : ''}
                                onValueChange={handleChangeEtablissement}
                            >
                                <SelectTrigger className="h-9 w-full sm:w-56 text-xs font-semibold bg-white dark:bg-slate-900">
                                    <div className="flex items-center gap-1.5 truncate">
                                        <Building2 className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                        <SelectValue placeholder="Choisir un établissement..." />
                                    </div>
                                </SelectTrigger>
                                <SelectContent>
                                    {etablissements.map((et) => (
                                        <SelectItem key={et.id} value={String(et.id)}>
                                            {et.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {/* Menu d'éditions et rapports PDF filtrables (dates + établissement/section/cycle/niveau/classe) */}
                        {canEtats && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="text-xs h-9 font-semibold flex items-center gap-1.5 border-sky-300 text-sky-800 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-300"
                                    >
                                        <Printer className="w-4 h-4 shrink-0 text-sky-600" />
                                        <span>États & Journaux (PDF)</span>
                                        <ChevronDown className="w-3.5 h-3.5 opacity-60" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-64">
                                    <DropdownMenuLabel className="text-xs text-slate-500">
                                        États Filtrables (Dates & Niveau/Classe)
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        className="text-xs flex items-center gap-2 cursor-pointer font-semibold text-emerald-700"
                                        onClick={() => openGenerateurEtats('journal_synthetique', 'aujourdhui')}
                                    >
                                        <Calendar className="w-3.5 h-3.5 text-emerald-600" />
                                        <span>État Journalier (Aujourd'hui)</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        className="text-xs flex items-center gap-2 cursor-pointer font-semibold text-teal-700"
                                        onClick={() => openGenerateurEtats('journal_synthetique', 'mois')}
                                    >
                                        <FileSpreadsheet className="w-3.5 h-3.5 text-teal-600" />
                                        <span>État Mensuel / Par Niveau (PDF)</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        className="text-xs flex items-center gap-2 cursor-pointer"
                                        onClick={() => openGenerateurEtats('journal_detaille', 'mois')}
                                    >
                                        <FileText className="w-3.5 h-3.5 text-sky-600" />
                                        <span>Journal Détaillé des Reçus (PDF)</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        className="text-xs flex items-center gap-2 cursor-pointer text-red-600 font-medium"
                                        onClick={() => openGenerateurEtats('liste_insolvables', 'annee')}
                                    >
                                        <AlertCircle className="w-3.5 h-3.5 text-red-600" />
                                        <span>Liste des Insolvables (PDF)</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        className="text-xs flex items-center gap-2 cursor-pointer text-emerald-600 font-medium"
                                        onClick={() => openGenerateurEtats('liste_en_regle', 'annee')}
                                    >
                                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                                        <span>Élèves en Règle (PDF)</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}

                        <Button
                            asChild
                            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 font-semibold flex-1 sm:flex-initial justify-center shadow-sm"
                        >
                            <Link
                                href="/administration/finances/scolarites"
                                className="flex items-center gap-1.5"
                            >
                                <FolderOpen className="w-4 h-4 shrink-0" />
                                Encaisser Scolarité ({comptes.length} élèves)
                            </Link>
                        </Button>

                        {canRecettesDiverses && (
                            <Button
                                onClick={() => setIsReglementDiversModalOpen(true)}
                                variant="outline"
                                className="text-xs h-9 font-semibold flex-1 sm:flex-initial justify-center flex items-center gap-1.5 border-emerald-300 text-emerald-800 hover:bg-emerald-50"
                            >
                                <Receipt className="w-4 h-4 shrink-0" /> Recette Diverse
                            </Button>
                        )}
                    </div>
                </div>

                {/* Cartes KPIs des recettes du jour */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5 gap-3">
                    <Card className="border-slate-200 dark:border-slate-800 bg-emerald-50/40 dark:bg-emerald-950/20">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">
                                    Total Recettes (Jour)
                                </span>
                                <DollarSign className="w-4 h-4 text-emerald-600 shrink-0" />
                            </div>
                            <div className="text-base sm:text-lg font-extrabold text-emerald-700 dark:text-emerald-400 mt-1 tabular-nums">
                                {Number(journalDuJour.total_general).toLocaleString('fr-FR')} FCFA
                            </div>
                            <span className="text-[10px] text-slate-500">
                                {journalDuJour.nombre_transactions} opération(s) aujourd'hui
                            </span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">Espèces</span>
                                <Receipt className="w-4 h-4 text-slate-500 shrink-0" />
                            </div>
                            <div className="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-200 mt-1 tabular-nums">
                                {Number(journalDuJour.total_especes).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">Mobile Money</span>
                                <Smartphone className="w-4 h-4 text-amber-600 shrink-0" />
                            </div>
                            <div className="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-200 mt-1 tabular-nums">
                                {Number(journalDuJour.total_mobile_money).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">
                                    Virements / Chèques
                                </span>
                                <CreditCard className="w-4 h-4 text-sky-600 shrink-0" />
                            </div>
                            <div className="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-200 mt-1 tabular-nums">
                                {(
                                    Number(journalDuJour.total_virements) +
                                    Number(journalDuJour.total_cheques)
                                ).toLocaleString('fr-FR')}{' '}
                                FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className={`sm:col-span-2 lg:col-span-1 border-slate-200 dark:border-slate-800 ${
                            journalDuJour.est_cloture
                                ? 'bg-slate-100 dark:bg-slate-900'
                                : 'bg-amber-50/50 dark:bg-amber-950/20'
                        }`}
                    >
                        <CardContent className="p-3 flex flex-col justify-between h-full">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">État Journée</span>
                                {journalDuJour.est_cloture ? (
                                    <Lock className="w-4 h-4 text-slate-700 shrink-0" />
                                ) : (
                                    <Unlock className="w-4 h-4 text-amber-600 shrink-0" />
                                )}
                            </div>
                            <div className="mt-1 flex flex-wrap items-center justify-between gap-1.5">
                                <span className="text-xs font-bold">
                                    {journalDuJour.est_cloture
                                        ? 'Journée Clôturée'
                                        : 'Encaissements Ouverts'}
                                </span>
                                {!journalDuJour.est_cloture && canCloture && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="h-6 text-[10px] bg-amber-600 hover:bg-amber-700 text-white border-none"
                                        onClick={() => setIsClotureModalOpen(true)}
                                    >
                                        Clôturer
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Bandeau repère du parcours sans répétition */}
                <div className="p-3 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                    <div className="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                        <Compass className="w-4 h-4 text-emerald-600 shrink-0" />
                        <span className="font-bold text-slate-900 dark:text-slate-100">
                            Parcours Rapide sans Répétition :
                        </span>
                    </div>
                    <div className="flex items-center gap-1.5 overflow-x-auto scrollbar-none py-0.5 text-[11px] font-medium">
                        <div className="flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 shrink-0">
                            <span className="w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold">
                                1
                            </span>
                            <span>Configurer les Frais au Catalogue (01)</span>
                        </div>
                        <span className="text-slate-400 font-bold">→</span>
                        <div className="flex items-center gap-1 px-2.5 py-1 rounded-md bg-sky-50 dark:bg-sky-950/60 text-sky-800 dark:text-sky-300 border border-sky-200 shrink-0">
                            <span className="w-4 h-4 rounded-full bg-sky-600 text-white flex items-center justify-center text-[10px] font-bold">
                                2
                            </span>
                            <span>Cocher les Niveaux en 1 clic (02)</span>
                        </div>
                        <span className="text-slate-400 font-bold">→</span>
                        <Link
                            href="/administration/finances/scolarites"
                            className="flex items-center gap-1 px-2.5 py-1 rounded-md bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 shrink-0 font-semibold"
                        >
                            <span className="w-4 h-4 rounded-full bg-amber-600 text-white flex items-center justify-center text-[10px] font-bold">
                                3
                            </span>
                            <span>Encaisser par Classe & Dossier Élève</span>
                        </Link>
                    </div>
                </div>

                {/* 6 Onglets essentiels alignés par ordre logique */}
                <div className="flex items-center overflow-x-auto overscroll-x-contain border-b border-slate-200 dark:border-slate-800 gap-1 sm:gap-2 text-xs sm:text-sm font-medium max-w-full scrollbar-none">
                    <button
                        onClick={() => setActiveTab('types_frais')}
                        className={`pb-2.5 px-3 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${
                            activeTab === 'types_frais'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Tag className="w-4 h-4 shrink-0" />
                        1. Catalogue des Frais ({typesFrais.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('tarifs')}
                        className={`pb-2.5 px-3 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${
                            activeTab === 'tarifs'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Layers className="w-4 h-4 shrink-0" />
                        2. Grille par Niveau ({tarifs.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('bourses_remises')}
                        className={`pb-2.5 px-3 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${
                            activeTab === 'bourses_remises'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Award className="w-4 h-4 shrink-0" />
                        3. Bourses & Remises ({boursesRemises.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('versements')}
                        className={`pb-2.5 px-3 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${
                            activeTab === 'versements'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Receipt className="w-4 h-4 shrink-0" />
                        4. Historique Versements ({versements.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('reglements_divers')}
                        className={`pb-2.5 px-3 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${
                            activeTab === 'reglements_divers'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Wallet className="w-4 h-4 shrink-0" />
                        5. Recettes Diverses ({reglementsDivers.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('journal')}
                        className={`pb-2.5 px-3 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${
                            activeTab === 'journal'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Calendar className="w-4 h-4 shrink-0" />
                        6. Journal & Clôtures ({historiqueClotures.length})
                    </button>
                </div>

                {/* ONGLET 01 : CATALOGUE DES FRAIS + SOUS-MODULE CATÉGORIES DE FRAIS */}
                {activeTab === 'types_frais' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 dark:text-emerald-300/5 select-none pointer-events-none font-mono">
                                01
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Étape 1 sur 6 • Configuration Unique sans Répétition
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Tag className="w-5 h-5 text-emerald-600 shrink-0" />
                                        Catalogue des Frais ({typesFrais.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Configurez chaque frais une seule fois (ex: <strong>Inscription SIL - CM2</strong>, <strong>Tranche 1 SIL - CM2</strong>, <strong>CEPE</strong>, <strong>Fête</strong>) avec sa catégorie, son montant, sa priorité et sa date butoir, puis rattachez-le en un clic aux niveaux concernés.
                                    </p>
                                </div>
                                {canConfigureFrais && (
                                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                                        {typesFrais.length > 0 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => ouvrirAffectationPleineLargeur(null)}
                                                className="text-xs h-9 font-semibold border-emerald-300 text-emerald-800 hover:bg-emerald-100/60 flex items-center gap-1.5"
                                            >
                                                <Layers className="w-4 h-4 shrink-0" />
                                                Rattacher aux Niveaux
                                            </Button>
                                        )}
                                        <Button
                                            onClick={() => {
                                                setTypeFraisToEdit(null);
                                                setIsTypeFraisModalOpen(true);
                                            }}
                                            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                        >
                                            <Plus className="w-4 h-4 shrink-0" /> Nouveau Frais
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* SOUS-MODULE LÉGER : CATÉGORIES DE FRAIS (Libellé + Description uniquement) */}
                        <Card className="border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
                            <CardContent className="p-3.5 sm:p-4 space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <FolderPlus className="w-4 h-4 text-emerald-600 shrink-0" />
                                        <div>
                                            <h4 className="text-xs font-bold text-slate-900 dark:text-slate-100">
                                                Sous-module : Catégories de Frais ({categoriesFrais.length})
                                            </h4>
                                            <p className="text-[11px] text-slate-500">
                                                Sert à regrouper les frais (Scolarité, Frais d'Examen, Fête, Services...) et à obtenir le montant global par catégorie d'une classe ou d'un niveau.
                                            </p>
                                        </div>
                                    </div>

                                    {canConfigureFrais && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                if (isCategorieFormOpen && !categorieToEdit) {
                                                    setIsCategorieFormOpen(false);
                                                } else {
                                                    openCategorieEditor(null);
                                                }
                                            }}
                                            className="h-8 text-xs font-semibold border-slate-300 flex items-center gap-1"
                                        >
                                            {isCategorieFormOpen && !categorieToEdit ? (
                                                <>
                                                    <X className="w-3.5 h-3.5" /> Fermer
                                                </>
                                            ) : (
                                                <>
                                                    <Plus className="w-3.5 h-3.5 text-emerald-600" /> Nouvelle Catégorie
                                                </>
                                            )}
                                        </Button>
                                    )}
                                </div>

                                {/* Formulaire compact (Libellé + Description uniquement) */}
                                {isCategorieFormOpen && (
                                    <form
                                        onSubmit={handleSaveCategorieFrais}
                                        className="p-3 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20 grid grid-cols-1 md:grid-cols-12 gap-2.5 items-end"
                                    >
                                        <div className="md:col-span-4 space-y-1">
                                            <Label htmlFor="sub_cat_nom" className="text-[11px] font-semibold">
                                                Libellé de la catégorie <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="sub_cat_nom"
                                                placeholder="Ex: Scolarité, Frais d'Examen, Fête..."
                                                value={catNom}
                                                onChange={(e) => setCatNom(e.target.value)}
                                                className="h-8 text-xs bg-white dark:bg-slate-900"
                                                required
                                            />
                                        </div>
                                        <div className="md:col-span-5 space-y-1">
                                            <Label htmlFor="sub_cat_desc" className="text-[11px] font-semibold">
                                                Description <span className="text-slate-400 font-normal">(optionnel)</span>
                                            </Label>
                                            <Input
                                                id="sub_cat_desc"
                                                placeholder="Ex: Regroupe inscription et tranches, ou CEPE / BEPC..."
                                                value={catDescription}
                                                onChange={(e) => setCatDescription(e.target.value)}
                                                className="h-8 text-xs bg-white dark:bg-slate-900"
                                            />
                                        </div>
                                        <div className="md:col-span-3 flex items-center justify-end gap-1.5">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-8 text-xs"
                                                onClick={() => {
                                                    setIsCategorieFormOpen(false);
                                                    setCategorieToEdit(null);
                                                }}
                                            >
                                                Annuler
                                            </Button>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={savingCategorie || !catNom.trim()}
                                                className="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold"
                                            >
                                                {savingCategorie
                                                    ? 'Sauvegarde...'
                                                    : categorieToEdit
                                                      ? 'Mettre à jour'
                                                      : 'Enregistrer'}
                                            </Button>
                                        </div>
                                    </form>
                                )}

                                {/* Pastilles interactives des catégories (filtrage + édition/suppression) */}
                                <div className="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setCatalogueCategorieFilter('all')}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors cursor-pointer ${
                                            catalogueCategorieFilter === 'all'
                                                ? 'bg-slate-900 text-white border-slate-900 dark:bg-slate-100 dark:text-slate-900'
                                                : 'bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100'
                                        }`}
                                    >
                                        Tous les frais ({typesFrais.length})
                                    </button>

                                    {categoriesFrais.map((cat) => {
                                        const nbFrais = typesFrais.filter(
                                            (t) => Number(t.categorie_frais_id) === cat.id
                                        ).length;
                                        const isSelected = catalogueCategorieFilter === String(cat.id);

                                        return (
                                            <div
                                                key={cat.id}
                                                title={cat.description ?? cat.nom}
                                                className={`inline-flex items-center rounded-lg border text-xs transition-all ${
                                                    isSelected
                                                        ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                                        : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:border-emerald-400'
                                                }`}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() => setCatalogueCategorieFilter(String(cat.id))}
                                                    className="px-2.5 py-1.5 font-semibold flex items-center gap-1.5 cursor-pointer"
                                                >
                                                    <span>{cat.nom}</span>
                                                    <span
                                                        className={`text-[10px] px-1.5 py-0 rounded-full font-bold tabular-nums ${
                                                            isSelected
                                                                ? 'bg-white/20 text-white'
                                                                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'
                                                        }`}
                                                    >
                                                        {nbFrais}
                                                    </span>
                                                </button>
                                                {canConfigureFrais && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() => openCategorieEditor(cat)}
                                                            title="Modifier cette catégorie"
                                                            className={`p-1 hover:opacity-100 opacity-70 cursor-pointer ${
                                                                isSelected
                                                                    ? 'text-white'
                                                                    : 'text-slate-500 hover:text-emerald-700'
                                                            }`}
                                                        >
                                                            <Edit className="w-3 h-3" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (nbFrais > 0) {
                                                                    toast.error(
                                                                        `Impossible de supprimer la catégorie « ${cat.nom} » : elle regroupe déjà ${nbFrais} frais dans le catalogue.`,
                                                                        { position: 'top-right' }
                                                                    );
                                                                    return;
                                                                }

                                                                confirmDelete({
                                                                    title: 'Supprimer cette catégorie de frais',
                                                                    itemType: 'la catégorie',
                                                                    itemName: cat.nom,
                                                                    description:
                                                                        'Êtes-vous sûr de vouloir supprimer cette catégorie vide ?',
                                                                    onConfirm: () => {
                                                                        router.delete(
                                                                            `/administration/finances/categories-frais/${cat.id}`,
                                                                            {
                                                                                preserveScroll: true,
                                                                                onSuccess: () => {
                                                                                    if (
                                                                                        catalogueCategorieFilter ===
                                                                                        String(cat.id)
                                                                                    ) {
                                                                                        setCatalogueCategorieFilter(
                                                                                            'all'
                                                                                        );
                                                                                    }
                                                                                    toast.success(
                                                                                        'Catégorie supprimée.',
                                                                                        {
                                                                                            position:
                                                                                                'top-right',
                                                                                            className:
                                                                                                'bg-emerald-600 text-white border-none',
                                                                                        }
                                                                                    );
                                                                                },
                                                                                onError: (errors) => {
                                                                                    const msg =
                                                                                        (Object.values(
                                                                                            errors
                                                                                        )[0] as string) ||
                                                                                        'Impossible de supprimer cette catégorie car elle est déjà utilisée.';
                                                                                    toast.error(msg, {
                                                                                        position:
                                                                                            'top-right',
                                                                                    });
                                                                                },
                                                                            }
                                                                        );
                                                                    },
                                                                });
                                                            }}
                                                            title={
                                                                nbFrais > 0
                                                                    ? `Suppression verrouillée : ${nbFrais} frais utilisent cette catégorie`
                                                                    : 'Supprimer cette catégorie'
                                                            }
                                                            className={`p-1 pr-2 hover:opacity-100 opacity-70 cursor-pointer ${
                                                                isSelected
                                                                    ? 'text-white'
                                                                    : nbFrais > 0
                                                                      ? 'text-slate-400 hover:text-amber-600'
                                                                      : 'text-red-500 hover:text-red-700'
                                                            }`}
                                                        >
                                                            {nbFrais > 0 ? (
                                                                <Lock className="w-3 h-3" />
                                                            ) : (
                                                                <Trash2 className="w-3 h-3" />
                                                            )}
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>

                        <DataTable
                            columns={typesFraisColumns}
                            data={filteredCatalogueFrais}
                            searchPlaceholder="Rechercher un frais (ex: Inscription SIL - CM2, CEPE, Fête...)"
                        />
                    </div>
                )}

                {/* ONGLET 02 : GRILLE PAR NIVEAU & ESPACE PLEINE LARGEUR D'AFFECTATION */}
                {activeTab === 'tarifs' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 dark:text-emerald-300/5 select-none pointer-events-none font-mono">
                                02
                            </div>
                            <div className="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Étape 2 sur 6 • Affectation Rapide Multi-Niveaux
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Layers className="w-5 h-5 text-emerald-600 shrink-0" />
                                        {grilleMode === 'affectation'
                                            ? 'Espace Pleine Largeur : Rattacher des Frais du Catalogue aux Niveaux'
                                            : `Grille des Frais par Niveau (${tarifs.length})`}
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        {grilleMode === 'affectation'
                                            ? 'Vue aérée en 2 colonnes côte à côte conçue pour gérer confortablement de nombreux frais et tous les niveaux du complexe scolaire.'
                                            : 'Consultez la grille appliquée par niveau, filtrez par catégorie pour obtenir les montants globaux ou basculez vers l’espace pleine largeur pour rattacher plusieurs frais.'}
                                    </p>
                                </div>
                                {canConfigureFrais && (
                                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                                        {/* Sélecteur de sous-vue : Grille & Synthèse vs Espace d'affectation 2 colonnes */}
                                        <div className="inline-flex items-center p-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setGrilleMode('liste');
                                                    setPreselectedTypeFraisForNiveaux(null);
                                                }}
                                                className={`px-3 py-1.5 rounded-md text-xs font-semibold transition-colors cursor-pointer ${
                                                    grilleMode === 'liste'
                                                        ? 'bg-emerald-600 text-white shadow-2xs'
                                                        : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
                                                }`}
                                            >
                                                Grille & Synthèse ({tarifs.length})
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setGrilleMode('affectation')}
                                                className={`px-3 py-1.5 rounded-md text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer ${
                                                    grilleMode === 'affectation'
                                                        ? 'bg-emerald-600 text-white shadow-2xs'
                                                        : 'text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'
                                                }`}
                                            >
                                                <Plus className="w-3.5 h-3.5" />
                                                Rattacher des Frais aux Niveaux
                                            </button>
                                        </div>

                                        {grilleMode === 'liste' && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => {
                                                    setTarifToEdit(null);
                                                    setIsTarifModalOpen(true);
                                                }}
                                                className="text-xs h-9 font-semibold border-slate-300 flex items-center gap-1.5 bg-white dark:bg-slate-900"
                                            >
                                                <SlidersHorizontal className="w-3.5 h-3.5" />
                                                Ajout unitaire
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        {grilleMode === 'affectation' ? (
                            <PanneauAffectationNiveaux
                                anneeId={currentAnnee?.id ?? 0}
                                etablissementId={currentEtablissement?.id ?? 0}
                                typesFrais={typesFrais}
                                categoriesFrais={categoriesFrais}
                                niveaux={niveaux}
                                preselectedTypeFrais={preselectedTypeFraisForNiveaux}
                                onOpenCreateTypeFrais={() => {
                                    setTypeFraisToEdit(null);
                                    setIsTypeFraisModalOpen(true);
                                }}
                                onClosePanel={() => {
                                    setGrilleMode('liste');
                                    setPreselectedTypeFraisForNiveaux(null);
                                }}
                            />
                        ) : (
                            <>
                                {/* Barre de filtrage par Niveau + par Catégorie & Synthèse par Catégorie */}
                                <Card className="border-slate-200 dark:border-slate-800">
                                    <CardContent className="p-4 space-y-4">
                                        <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1 max-w-xl">
                                                <div className="space-y-1">
                                                    <Label className="text-[11px] font-semibold text-slate-600">
                                                        Filtrer par Niveau Pédagogique
                                                    </Label>
                                                    <Select
                                                        value={grilleNiveauFilter}
                                                        onValueChange={setGrilleNiveauFilter}
                                                    >
                                                        <SelectTrigger className="h-9 text-xs">
                                                            <SelectValue placeholder="Tous les niveaux" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">
                                                                Tous les niveaux ({niveaux.length})
                                                            </SelectItem>
                                                            {niveaux.map((n) => (
                                                                <SelectItem key={n.id} value={String(n.id)}>
                                                                    {n.nom}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div className="space-y-1">
                                                    <Label className="text-[11px] font-semibold text-slate-600">
                                                        Filtrer par Catégorie de Frais
                                                    </Label>
                                                    <Select
                                                        value={grilleCategorieFilter}
                                                        onValueChange={setGrilleCategorieFilter}
                                                    >
                                                        <SelectTrigger className="h-9 text-xs">
                                                            <SelectValue placeholder="Toutes les catégories" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">
                                                                Toutes les catégories
                                                            </SelectItem>
                                                            {categoriesFrais.map((c) => (
                                                                <SelectItem key={c.id} value={String(c.id)}>
                                                                    {c.nom}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            {totalObligatoireNiveauSelectionne !== null && nomNiveauFiltre && (
                                                <div className="px-3.5 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-right">
                                                    <div className="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                                                        Total Obligatoire • {nomNiveauFiltre}
                                                    </div>
                                                    <div className="text-base font-black text-emerald-700 dark:text-emerald-400 tabular-nums">
                                                        {totalObligatoireNiveauSelectionne.toLocaleString('fr-FR')}{' '}
                                                        FCFA / élève
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {/* Cartes de Synthèse par Catégorie */}
                                        {syntheseGrilleParCategorie.length > 0 && (
                                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 pt-1">
                                                {syntheseGrilleParCategorie.map((cat) => {
                                                    const isCatSelected =
                                                        cat.categorieId !== null &&
                                                        grilleCategorieFilter === String(cat.categorieId);
                                                    return (
                                                        <button
                                                            key={cat.key}
                                                            type="button"
                                                            onClick={() => {
                                                                if (cat.categorieId === null) return;
                                                                setGrilleCategorieFilter(
                                                                    isCatSelected ? 'all' : String(cat.categorieId)
                                                                );
                                                            }}
                                                            className={`text-left p-3 rounded-lg border transition-all cursor-pointer ${
                                                                isCatSelected
                                                                    ? 'border-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/30'
                                                                    : 'border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/50 hover:border-emerald-300'
                                                            }`}
                                                        >
                                                            <div className="flex items-center justify-between gap-1">
                                                                <span className="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">
                                                                    {cat.nom}
                                                                </span>
                                                                <Badge variant="outline" className="text-[10px] px-1.5 py-0">
                                                                    {grilleNiveauFilter === 'all'
                                                                        ? `${cat.nbNiveaux} niv.`
                                                                        : `${cat.nbLignes} frais`}
                                                                </Badge>
                                                            </div>

                                                            {grilleNiveauFilter !== 'all' ? (
                                                                <div className="mt-1.5">
                                                                    {cat.montantObligatoire > 0 && (
                                                                        <div className="text-sm font-black text-emerald-700 dark:text-emerald-400 tabular-nums">
                                                                            {cat.montantObligatoire.toLocaleString('fr-FR')}{' '}
                                                                            FCFA
                                                                        </div>
                                                                    )}
                                                                    {cat.montantOptionnel > 0 && (
                                                                        <div className="text-[11px] font-semibold text-sky-700 dark:text-sky-400 tabular-nums">
                                                                            Optionnel :{' '}
                                                                            {cat.montantOptionnel.toLocaleString('fr-FR')}{' '}
                                                                            FCFA
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <div className="mt-1.5 text-[11px] text-slate-500">
                                                                    {cat.nbLignes} affectation(s) sur {cat.nbNiveaux}{' '}
                                                                    niveau(x)
                                                                </div>
                                                            )}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Verrou de solvabilité */}
                                <Card className="border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                                    <CardContent className="p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                        <div className="space-y-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <ShieldCheck className="w-5 h-5 text-emerald-600 shrink-0" />
                                                <h4 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                                                    Verrou de Solvabilité des Bulletins Scolaires
                                                </h4>
                                            </div>
                                            <p className="text-xs text-slate-500 max-w-2xl">
                                                Lorsqu'il est activé, le téléchargement des bulletins sur le portail parent/élève est bloqué tant qu'un solde demeure impayé.
                                            </p>
                                        </div>
                                        {canVerrouSolvabilite ? (
                                            <Button
                                                onClick={handleToggleVerrouSolvabilite}
                                                variant={
                                                    currentEtablissement?.verrou_solvabilite_actif
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                                className="text-xs h-9 font-semibold shrink-0 w-full sm:w-auto"
                                            >
                                                {currentEtablissement?.verrou_solvabilite_actif
                                                    ? 'Désactiver le Verrou'
                                                    : 'Activer le Verrou de Solvabilité'}
                                            </Button>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className={`text-xs px-3 py-1 font-bold shrink-0 ${
                                                    currentEtablissement?.verrou_solvabilite_actif
                                                        ? 'border-red-300 text-red-700 bg-red-50'
                                                        : 'border-slate-300 text-slate-600 bg-white'
                                                }`}
                                            >
                                                {currentEtablissement?.verrou_solvabilite_actif
                                                    ? 'Verrou Activé'
                                                    : 'Verrou Désactivé'}
                                            </Badge>
                                        )}
                                    </CardContent>
                                </Card>

                                <DataTable
                                    columns={tarifsColumns}
                                    data={filteredTarifs}
                                    searchPlaceholder="Rechercher par niveau, catégorie ou frais..."
                                />
                            </>
                        )}
                    </div>
                )}

                {/* ONGLET 03 : BOURSES & REMISES */}
                {activeTab === 'bourses_remises' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/40 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-amber-900/10 dark:text-amber-300/5 select-none pointer-events-none font-mono">
                                03
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-amber-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Étape 3 sur 6 • Réductions Individuelles
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Award className="w-5 h-5 text-amber-600 shrink-0" />
                                        Modèles de Bourses, Bonus & Remises ({boursesRemises.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Créez vos modèles de réductions (ex: <strong>Paiement en avance -3 000 FCFA</strong>, Fratrie, Enfant du personnel). Ils s'attribuent ensuite au cas par cas dans le dossier de l'élève concerné.
                                    </p>
                                </div>
                                {canManageBourses && (
                                    <Button
                                        onClick={() => {
                                            setBourseToEdit(null);
                                            setIsModeleBourseModalOpen(true);
                                        }}
                                        className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                    >
                                        <Plus className="w-4 h-4 shrink-0" /> Nouveau Modèle de Remise
                                    </Button>
                                )}
                            </div>
                        </div>

                        <DataTable
                            columns={boursesRemisesColumns}
                            data={boursesRemises}
                            searchPlaceholder="Rechercher un modèle de remise ou bourse..."
                        />
                    </div>
                )}

                {/* ONGLET 04 : HISTORIQUE DES VERSEMENTS SCOLARITÉ */}
                {activeTab === 'versements' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 dark:text-emerald-300/5 select-none pointer-events-none font-mono">
                                04
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Étape 4 sur 6 • Suivi & Annulation des Reçus Scolarité
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Receipt className="w-5 h-5 text-emerald-600 shrink-0" />
                                        Historique des Versements Scolarité ({versements.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Consultez tous les reçus émis, réimprimez un duplicata PDF ou annulez une erreur de versement (à l'unité ou en cochant toutes les opérations d'un élève en une seule fois).
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shrink-0 shadow-sm"
                                >
                                    <Link
                                        href="/administration/finances/scolarites"
                                        className="flex items-center gap-1.5"
                                    >
                                        <FolderOpen className="w-4 h-4 shrink-0" /> Aller au Guichet par Classe
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        {/* Barre d'annulation groupée (ex: annuler les 8 opérations d'un élève en une seule fois) */}
                        {canCancelVersements && selectedValidVersements.length > 0 && (
                            <div className="p-3.5 rounded-xl border border-red-300 dark:border-red-900 bg-red-50/80 dark:bg-red-950/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                                <div className="flex items-center gap-2.5 text-xs text-red-950 dark:text-red-200">
                                    <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />
                                    <div>
                                        <span className="font-bold">
                                            {selectedValidVersements.length} reçu(s) sélectionné(s)
                                        </span>{' '}
                                        pour un cumul de{' '}
                                        <strong className="tabular-nums font-extrabold">
                                            {selectedValidVersements
                                                .reduce((sum, item) => sum + Number(item.montant_total || 0), 0)
                                                .toLocaleString('fr-FR')}{' '}
                                            FCFA
                                        </strong>
                                        .
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2 shrink-0">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSelectedVersementIds([])}
                                        className="h-8 text-xs bg-white dark:bg-slate-900"
                                    >
                                        Tout désélectionner
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => setIsBatchCancelModalOpen(true)}
                                        className="h-8 text-xs font-bold shadow-xs"
                                    >
                                        Annuler la sélection ({selectedValidVersements.length} reçu
                                        {selectedValidVersements.length > 1 ? 's' : ''})
                                    </Button>
                                </div>
                            </div>
                        )}

                        <DataTable
                            columns={versementsColumns}
                            data={versements}
                            searchPlaceholder="Rechercher par nom de l'élève, matricule, classe ou N° reçu..."
                        />
                    </div>
                )}

                {/* ONGLET 05 : RECETTES DIVERSES */}
                {activeTab === 'reglements_divers' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-sky-50/50 dark:bg-sky-950/20 border border-sky-200/80 dark:border-sky-800/40 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-sky-900/10 dark:text-sky-300/5 select-none pointer-events-none font-mono">
                                05
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-sky-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Étape 5 sur 6 • Recettes Annexes
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Wallet className="w-5 h-5 text-sky-600 shrink-0" />
                                        Recettes Diverses & Encaissements Libres ({reglementsDivers.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Encaissez les recettes hors scolarité (tenues, macarons, cantine libre, dons...) avec vos propres catégories personnalisées.
                                    </p>
                                </div>
                                {canRecettesDiverses && (
                                    <Button
                                        onClick={() => setIsReglementDiversModalOpen(true)}
                                        className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                    >
                                        <Plus className="w-4 h-4 shrink-0" /> Nouvelle Recette Diverse
                                    </Button>
                                )}
                            </div>
                        </div>

                        <DataTable
                            columns={reglementsDiversColumns}
                            data={reglementsDivers}
                            searchPlaceholder="Rechercher par référence, payeur, catégorie ou libellé..."
                        />
                    </div>
                )}

                {/* ONGLET 06 : JOURNAL & CLÔTURES */}
                {activeTab === 'journal' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-slate-100/80 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-slate-900/10 dark:text-slate-300/5 select-none pointer-events-none font-mono">
                                06
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-slate-800 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Étape 6 sur 6 • Arrêté Journalier
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Calendar className="w-5 h-5 text-slate-700 dark:text-slate-300 shrink-0" />
                                        Historique des Clôtures Journalières ({historiqueClotures.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        En fin de journée, clôturez les encaissements afin de figer les écritures comptables et valider les totaux par mode de règlement.
                                    </p>
                                </div>
                                {!journalDuJour.est_cloture && canCloture && (
                                    <Button
                                        onClick={() => setIsClotureModalOpen(true)}
                                        className="bg-amber-600 hover:bg-amber-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                    >
                                        <Lock className="w-4 h-4 shrink-0" /> Clôturer la Journée
                                    </Button>
                                )}
                            </div>
                        </div>

                        <DataTable columns={cloturesColumns} data={historiqueClotures} />
                    </div>
                )}
            </div>

            {/* Modales du Module Finances */}
            <TypeFraisModal
                open={isTypeFraisModalOpen}
                onOpenChange={(open) => {
                    setIsTypeFraisModalOpen(open);
                    if (!open) setTypeFraisToEdit(null);
                }}
                etablissementId={currentEtablissement?.id ?? 0}
                categoriesFrais={categoriesFrais}
                typeFraisToEdit={typeFraisToEdit}
            />

            <TarifModal
                open={isTarifModalOpen}
                onOpenChange={(open) => {
                    setIsTarifModalOpen(open);
                    if (!open) setTarifToEdit(null);
                }}
                niveaux={niveaux}
                typesFrais={typesFrais}
                anneeId={currentAnnee?.id ?? 0}
                etablissementId={currentEtablissement?.id ?? 0}
                tarifToEdit={tarifToEdit}
                onOpenCreateTypeFrais={() => {
                    setTypeFraisToEdit(null);
                    setIsTypeFraisModalOpen(true);
                }}
            />

            <ModeleBourseModal
                isOpen={isModeleBourseModalOpen}
                onClose={() => {
                    setIsModeleBourseModalOpen(false);
                    setBourseToEdit(null);
                }}
                etablissementId={currentEtablissement?.id ?? 0}
                anneeId={currentAnnee?.id}
                categories={categoriesBourses}
                modelToEdit={bourseToEdit}
            />

            <NouveauReglementDiversModal
                isOpen={isReglementDiversModalOpen}
                onClose={() => setIsReglementDiversModalOpen(false)}
                etablissementId={currentEtablissement?.id ?? 0}
                categories={categoriesRecettes}
            />

            <AnnulerVersementModal
                open={Boolean(versementToCancel) || isBatchCancelModalOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setVersementToCancel(null);
                        setIsBatchCancelModalOpen(false);
                    }
                }}
                versement={versementToCancel}
                versementsLot={isBatchCancelModalOpen ? selectedValidVersements : []}
                onCancelledSuccess={() => setSelectedVersementIds([])}
            />

            <ClotureCaisseModal
                open={isClotureModalOpen}
                onOpenChange={setIsClotureModalOpen}
                journal={journalDuJour}
                etablissementId={currentEtablissement?.id ?? 0}
            />

            {canEtats && (
                <GenerateurEtatsFinanciersModal
                    open={isEtatsModalOpen}
                    onOpenChange={setIsEtatsModalOpen}
                    etablissements={etablissements}
                    currentEtablissementId={currentEtablissement?.id ?? null}
                    currentEtablissementNom={currentEtablissement?.nom ?? null}
                    isSuperAdmin={isSuperAdmin}
                    currentAnnee={currentAnnee}
                    initialType={etatInitialType}
                    initialPreset={etatInitialPreset}
                    allowBilanEntreesSorties={can?.manage ?? false}
                />
            )}

            <ConfirmDialog />
        </>
    );
}

/**
 * Fil d'Ariane pour la page Finances & Paramétrage Scolaire.
 */
FinancesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Finances & Paramétrage',
            href: '/administration/finances',
        },
    ],
};

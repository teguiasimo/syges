import React, { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    AlertCircle,
    ArrowLeft,
    Award,
    Calendar,
    CheckCircle2,
    CreditCard,
    DollarSign,
    Edit,
    FolderOpen,
    FolderPlus,
    Layers,
    Lock,
    Plus,
    Printer,
    Receipt,
    ShieldCheck,
    Smartphone,
    Tag,
    Trash2,
    Unlock,
    Wallet,
    X,
} from 'lucide-react';
import { toast } from 'sonner';
import { calculerSyntheseCategoriesGrille } from '@/lib/finance-categories';
import { formatDate, formatDateForInput } from '@/lib/utils';
import { useConfirmDelete } from '@/components/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
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
    formatBaseCalculRemiseLabel,
    formatCibleImputationLabel,
    FraisScolaire,
    JournalDuJour,
    ModePaiement,
    NiveauFinanceRef,
    ReglementDivers,
    TypeFrais,
    VersementCaisse,
} from '@/types/finance';
import PanneauAffectationNiveaux from './components/panneau-affectation-niveaux';
import AnnulerVersementModal from './modals/annuler-versement-modal';
import ClotureCaisseModal from './modals/cloture-caisse-modal';
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
    const canEditVersements = can?.versements_edit ?? can?.manage ?? false;
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
    const [reglementToEdit, setReglementToEdit] = useState<ReglementDivers | null>(null);
    const [versementToCancel, setVersementToCancel] = useState<VersementCaisse | null>(null);
    const [versementToEdit, setVersementToEdit] = useState<VersementCaisse | null>(null);
    const [editVersMontant, setEditVersMontant] = useState<string>('');
    const [editVersDate, setEditVersDate] = useState<string>('');
    const [editVersMode, setEditVersMode] = useState<ModePaiement>('especes');
    const [editVersRef, setEditVersRef] = useState<string>('');
    const [editVersPayeur, setEditVersPayeur] = useState<string>('');
    const [savingEditVersement, setSavingEditVersement] = useState<boolean>(false);
    const [selectedVersementIds, setSelectedVersementIds] = useState<number[]>([]);
    const [isBatchCancelModalOpen, setIsBatchCancelModalOpen] = useState<boolean>(false);
    const [isClotureModalOpen, setIsClotureModalOpen] = useState(false);

    /**
     * Prépare l'édition rapide d'un versement de scolarité depuis l'onglet Historique & Reçus.
     *
     * @param {VersementCaisse} v Versement valide sélectionné
     */
    const openEditVersementForm = (v: VersementCaisse) => {
        setVersementToEdit(v);
        setEditVersMontant(String(Number(v.montant_total || 0)));
        setEditVersDate(formatDateForInput(v.date_versement) || formatDateForInput(new Date()));
        setEditVersMode((v.mode_paiement as ModePaiement) || 'especes');
        setEditVersRef(v.reference_paiement || '');
        setEditVersPayeur(v.payeur_nom || '');
    };

    /**
     * Soumet la modification d'un versement de scolarité et recalcule le dossier élève.
     *
     * @param {React.FormEvent} e Événement formulaire
     */
    const handleSaveEditVersement = (e: React.FormEvent) => {
        e.preventDefault();
        if (!versementToEdit) return;

        const montant = Math.round((parseFloat(editVersMontant) || 0) * 100) / 100;
        if (montant <= 0) {
            toast.error('Veuillez saisir un montant valide supérieur à 0 FCFA.', {
                position: 'top-right',
            });
            return;
        }

        setSavingEditVersement(true);
        router.put(
            `/administration/finances/versements/${versementToEdit.id}`,
            {
                montant_total: montant,
                date_versement: editVersDate,
                mode_paiement: editVersMode,
                reference_paiement: editVersRef.trim() || null,
                payeur_nom: editVersPayeur.trim() || null,
                mode_allocation: 'auto',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        `Reçu ${versementToEdit.reference_recu} modifié (${montant.toLocaleString('fr-FR')} FCFA).`,
                        {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        }
                    );
                    setVersementToEdit(null);
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || 'Impossible de modifier ce versement.', {
                        position: 'top-right',
                    });
                },
                onFinish: () => setSavingEditVersement(false),
            }
        );
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
    const _ouvrirAffectationPleineLargeur = (fraisPreselectionne: TypeFrais | null = null) => {
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
                <Badge className="bg-slate-800 text-white text-[10px] font-bold tabular-nums">
                    #{row.original.ordre_priorite ?? 10}
                </Badge>
            ),
        },
        {
            accessorKey: 'libelle',
            header: 'Frais & Infos complémentaires',
            cell: ({ row }) => (
                <div>
                    <div className="font-bold text-xs text-slate-900">
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
                        className="text-[10px] font-semibold bg-slate-50 border-slate-300 text-slate-700"
                    >
                        {nomCat}
                    </Badge>
                ) : (
                    <span className="text-[11px] text-slate-400 italic">Non classé</span>
                );
            },
        },
        {
            accessorKey: 'date_limite_defaut',
            header: 'Date Butoir',
            cell: ({ row }) => (
                <span className="text-xs text-slate-600 flex items-center gap-1">
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
                <div className="text-right text-xs font-extrabold text-slate-900 tabular-nums">
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
                    <span className="font-bold text-xs text-slate-900">
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
                        className="text-[10px] font-semibold bg-slate-50"
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
                        <span className="font-semibold text-xs text-slate-800">
                            {libelle}
                        </span>
                        {row.original.obligatoire === false ? (
                            <Badge className="bg-sky-100 text-sky-800 border-sky-300 text-[10px] font-semibold">
                                Optionnel
                            </Badge>
                        ) : (
                            <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 text-[10px] font-semibold">
                                Obligatoire
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
                        className="text-[10px] font-bold text-slate-700 tabular-nums"
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
                <span className="text-xs text-slate-600 flex items-center gap-1">
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
                <div className="text-right text-xs font-extrabold text-slate-900 tabular-nums">
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
            accessorKey: 'categorie',
            header: 'Catégorie',
            cell: ({ row }) => {
                const isBourse = row.original.type === 'bourse' || row.original.categorie?.toUpperCase() === 'BOURSE';
                return (
                    <Badge
                        className={
                            isBourse
                                ? 'bg-emerald-700 text-white text-[10px] font-bold'
                                : 'bg-amber-600 text-white text-[10px] font-bold'
                        }
                    >
                        {isBourse ? 'BOURSE' : 'REMISE'}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'nom',
            header: 'Libellé de la Remise / Bourse',
            cell: ({ row }) => {
                const b = row.original;
                return (
                    <div className="space-y-0.5">
                        <div className="text-xs font-bold text-slate-800">
                            {b.nom}
                        </div>
                        <div className="text-[11px] text-slate-500">
                            Imputation : {formatCibleImputationLabel(b.cible_imputation)}
                            {b.mode_calcul === 'pourcentage' &&
                                ` • Base : ${formatBaseCalculRemiseLabel(b.base_calcul)}`}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'valeur',
            header: () => <div className="text-right">Avantage Accordé</div>,
            cell: ({ row }) => {
                const b = row.original;
                return (
                    <div className="text-right text-xs font-extrabold text-emerald-700 tabular-nums">
                        {b.mode_calcul === 'pourcentage'
                            ? `-${b.valeur} %`
                            : `-${Number(b.valeur).toLocaleString('fr-FR')} FCFA`}
                    </div>
                );
            },
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
                      size: 48,
                      enableSorting: false,
                      enableHiding: false,
                      meta: {
                          className: 'w-12 min-w-12 max-w-12 text-center px-1 whitespace-nowrap',
                          headerClassName: 'w-12 min-w-12 max-w-12 text-center px-1 whitespace-nowrap',
                      },
                      header: ({ table }) => {
                          const validFilteredIds = table
                              .getFilteredRowModel()
                              .rows.filter((r) => r.original.statut === 'valide')
                              .map((r) => r.original.id);
                          const allChecked =
                              validFilteredIds.length > 0 &&
                              validFilteredIds.every((id) => selectedVersementIds.includes(id));

                          return (
                              <div className="flex items-center justify-center">
                                  <Checkbox
                                      checked={allChecked}
                                      disabled={validFilteredIds.length === 0}
                                      onCheckedChange={() => {
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
                                      title={
                                          allChecked
                                              ? 'Tout décocher'
                                              : `Cocher les ${validFilteredIds.length} reçus valides affichés`
                                      }
                                      aria-label="Tout sélectionner"
                                      className="border-slate-400 data-[state=checked]:bg-red-600 data-[state=checked]:border-red-600"
                                  />
                              </div>
                          );
                      },
                      cell: ({ row }) => {
                          const v = row.original;
                          if (v.statut !== 'valide') {
                              return (
                                  <div className="flex items-center justify-center">
                                      <span className="inline-block w-4 h-4" />
                                  </div>
                              );
                          }
                          const checked = selectedVersementIds.includes(v.id);
                          return (
                              <div className="flex items-center justify-center">
                                  <Checkbox
                                      checked={checked}
                                      onCheckedChange={() =>
                                          setSelectedVersementIds((prev) =>
                                              prev.includes(v.id)
                                                  ? prev.filter((id) => id !== v.id)
                                                  : [...prev, v.id]
                                          )
                                      }
                                      title="Sélectionner ce reçu pour une annulation groupée"
                                      aria-label="Sélectionner pour annulation"
                                      className="border-slate-400 data-[state=checked]:bg-red-600 data-[state=checked]:border-red-600"
                                  />
                              </div>
                          );
                      },
                  } satisfies ColumnDef<VersementCaisse>,
              ]
            : []),
        {
            accessorKey: 'reference_recu',
            meta: {
                className: 'whitespace-nowrap font-mono',
                headerClassName: 'whitespace-nowrap',
            },
            header: 'N° Reçu',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-slate-800">
                    {row.original.reference_recu}
                </span>
            ),
        },
        {
            accessorKey: 'date_versement',
            meta: {
                className: 'whitespace-nowrap',
                headerClassName: 'whitespace-nowrap',
            },
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
            meta: {
                className: 'w-full min-w-55',
                headerClassName: 'w-full min-w-55',
            },
            cell: ({ row }) => {
                const eleve = row.original.compte_financier?.eleve;
                const classe = row.original.inscription?.classe?.nom ?? 'N/A';
                return (
                    <div>
                        <div className="font-semibold text-xs text-slate-800">
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
                            className="h-7 text-xs px-2 flex items-center gap-1 text-slate-600 hover:text-emerald-700"
                            title="Imprimer le reçu format A4"
                            onClick={() =>
                                window.open(`/administration/finances/versements/${v.id}/recu`, '_blank')
                            }
                        >
                            <Printer className="w-3.5 h-3.5" /> A4
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs px-2 flex items-center gap-1 text-slate-600 hover:text-sky-700"
                            title="Imprimer en ticket de caisse 80mm"
                            onClick={() =>
                                window.open(`/administration/finances/versements/${v.id}/recu?format=ticket`, '_blank')
                            }
                        >
                            <Printer className="w-3.5 h-3.5" /> Ticket 80mm
                        </Button>
                        {canEditVersements && v.statut === 'valide' && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs px-2 flex items-center gap-1 text-amber-700 hover:text-amber-900 hover:bg-amber-50"
                                title="Modifier ce versement"
                                onClick={() => openEditVersementForm(v)}
                            >
                                <Edit className="w-3.5 h-3.5" /> Modifier
                            </Button>
                        )}
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
    ], [canEditVersements, canCancelVersements, selectedVersementIds]);

    /**
     * 05. Colonnes TanStack Table pour les Recettes Diverses.
     */
    const reglementsDiversColumns = useMemo<ColumnDef<ReglementDivers>[]>(() => [
        {
            accessorKey: 'reference_recu',
            header: 'N° Reçu',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-slate-800">
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
                    <div className="font-semibold text-xs text-slate-800">
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
            header: 'Commentaire',
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
                        {canRecettesDiverses && r.statut === 'valide' && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs flex items-center gap-1 text-slate-600 hover:text-emerald-700"
                                title="Modifier cette recette diverse"
                                onClick={() => {
                                    setReglementToEdit(r);
                                    setIsReglementDiversModalOpen(true);
                                }}
                            >
                                <Edit className="w-3.5 h-3.5" /> Modifier
                            </Button>
                        )}
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
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 pb-4">
                    <div className="min-w-0">
                        <div className="flex items-start sm:items-center gap-2.5 min-w-0">
                            <div className="p-2 bg-emerald-600 rounded-lg text-white shrink-0">
                                <Wallet className="w-5 h-5" />
                            </div>
                            <div className="min-w-0">
                                <h1 className="text-xl sm:text-2xl font-black tracking-tight text-slate-900">
                                    Finances & Paramétrage Scolaire
                                </h1>
                                <p className="text-xs text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span>
                                        Établissement :{' '}
                                        <strong className="text-slate-800">
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

                    <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto min-w-0">
                        {/* Sélecteur d'établissement rapide pour le Super-Admin multi-campus */}
                        {isSuperAdmin && etablissements.length > 1 && (
                            <div className="w-full sm:w-64 min-w-0">
                                <SearchableSelect
                                    options={etablissements.map((et) => ({
                                        value: String(et.id),
                                        label: et.nom,
                                        description: et.code ? `Code : ${et.code}` : undefined,
                                    }))}
                                    value={currentEtablissement ? String(currentEtablissement.id) : ''}
                                    onValueChange={(val) => {
                                        if (val) handleChangeEtablissement(val);
                                    }}
                                    placeholder="Choisir un établissement..."
                                    searchPlaceholder="Rechercher un établissement..."
                                    emptyMessage="Aucun établissement trouvé."
                                    className="h-9 text-xs font-semibold bg-white"
                                />
                            </div>
                        )}

                        {/* Bouton d'accès au Centre dédié des États & Rapports PDF */}
                        {canEtats && (
                            <Button
                                asChild
                                variant="outline"
                                className="text-xs h-9 font-semibold flex items-center gap-1.5 border-sky-300 text-sky-800 hover:bg-sky-50 shadow-2xs"
                            >
                                <Link href="/administration/finances/etats">
                                    <Printer className="w-4 h-4 shrink-0 text-sky-600" />
                                    <span>Centre des États & Rapports (PDF)</span>
                                </Link>
                            </Button>
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
                    </div>
                </div>

                {/* Cartes KPIs des recettes du jour */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5 gap-3">
                    <Card className="border-slate-200 bg-emerald-50/40">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">
                                    Total Recettes (Jour)
                                </span>
                                <DollarSign className="w-4 h-4 text-emerald-600 shrink-0" />
                            </div>
                            <div className="text-base sm:text-lg font-extrabold text-emerald-700 mt-1 tabular-nums">
                                {Number(journalDuJour.total_general).toLocaleString('fr-FR')} FCFA
                            </div>
                            <span className="text-[10px] text-slate-500">
                                {journalDuJour.nombre_transactions} opération(s) aujourd'hui
                            </span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">Espèces</span>
                                <Receipt className="w-4 h-4 text-slate-500 shrink-0" />
                            </div>
                            <div className="text-sm sm:text-base font-bold text-slate-800 mt-1 tabular-nums">
                                {Number(journalDuJour.total_especes).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">Mobile Money</span>
                                <Smartphone className="w-4 h-4 text-amber-600 shrink-0" />
                            </div>
                            <div className="text-sm sm:text-base font-bold text-slate-800 mt-1 tabular-nums">
                                {Number(journalDuJour.total_mobile_money).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">
                                    Virements / Chèques
                                </span>
                                <CreditCard className="w-4 h-4 text-sky-600 shrink-0" />
                            </div>
                            <div className="text-sm sm:text-base font-bold text-slate-800 mt-1 tabular-nums">
                                {(
                                    Number(journalDuJour.total_virements) +
                                    Number(journalDuJour.total_cheques)
                                ).toLocaleString('fr-FR')}{' '}
                                FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className={`sm:col-span-2 lg:col-span-1 border-slate-200 ${
                            journalDuJour.est_cloture
                                ? 'bg-slate-100'
                                : 'bg-amber-50/50'
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

                {/* 6 Onglets essentiels alignés par ordre logique */}
                <div className="flex items-center overflow-x-auto overscroll-x-contain border-b border-slate-200 gap-1 sm:gap-2 text-xs sm:text-sm font-medium max-w-full scrollbar-none">
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
                        <div className="relative overflow-hidden rounded-xl bg-emerald-50/50 border border-emerald-200/80 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 select-none pointer-events-none font-mono">
                                01
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Configuration des Frais
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 mt-1 flex items-center gap-2">
                                        <Tag className="w-5 h-5 text-emerald-600 shrink-0" />
                                        Catalogue des Frais ({typesFrais.length})
                                    </h3>
                                </div>
                                {canConfigureFrais && (
                                    <div className="flex flex-wrap items-center gap-2 shrink-0">
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
                        <Card className="border-slate-200 bg-white shadow-xs">
                            <CardContent className="p-3.5 sm:p-4 space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <FolderPlus className="w-4 h-4 text-emerald-600 shrink-0" />
                                        <div>
                                            <h4 className="text-xs font-bold text-slate-900">
                                                Sous-module : Catégories de Frais ({categoriesFrais.length})
                                            </h4>
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
                                        className="p-3 rounded-lg border border-emerald-200 bg-emerald-50/40 grid grid-cols-1 md:grid-cols-12 gap-2.5 items-end"
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
                                                className="h-8 text-xs bg-white"
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
                                                className="h-8 text-xs bg-white"
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
                                                ? 'bg-slate-900 text-white border-slate-900'
                                                : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
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
                                                        : 'bg-white text-slate-700 border-slate-200 hover:border-emerald-400'
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
                                                                : 'bg-slate-100 text-slate-600'
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
                        <div className="relative overflow-hidden rounded-xl bg-emerald-50/50 border border-emerald-200/80 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 select-none pointer-events-none font-mono">
                                02
                            </div>
                            <div className="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Grille par Niveau
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 mt-1 flex items-center gap-2">
                                        <Layers className="w-5 h-5 text-emerald-600 shrink-0" />
                                        {grilleMode === 'affectation'
                                            ? 'Rattacher des Frais du Catalogue aux Niveaux'
                                            : `Grille des Frais par Niveau (${tarifs.length})`}
                                    </h3>
                                </div>
                                {canConfigureFrais && (
                                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                                        {grilleMode === 'liste' ? (
                                            <Button
                                                type="button"
                                                onClick={() => setGrilleMode('affectation')}
                                                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 font-semibold flex items-center gap-1.5 shadow-sm"
                                            >
                                                <Plus className="w-4 h-4 shrink-0" />
                                                Rattacher des Frais aux Niveaux
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => {
                                                    setGrilleMode('liste');
                                                    setPreselectedTypeFraisForNiveaux(null);
                                                }}
                                                className="text-xs h-9 font-semibold border-slate-300 flex items-center gap-1.5 bg-white text-slate-700 hover:bg-slate-100"
                                            >
                                                <ArrowLeft className="w-4 h-4 shrink-0" />
                                                Revenir à la Grille
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
                                <Card className="border-slate-200">
                                    <CardContent className="p-4 space-y-4">
                                        <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1 max-w-xl min-w-0">
                                                <div className="space-y-1 min-w-0">
                                                    <Label className="text-[11px] font-semibold text-slate-600">
                                                        Filtrer par Niveau Pédagogique
                                                    </Label>
                                                    <SearchableSelect
                                                        options={[
                                                            { value: 'all', label: `Tous les niveaux (${niveaux.length})` },
                                                            ...niveaux.map((n) => ({
                                                                value: String(n.id),
                                                                label: n.nom,
                                                            })),
                                                        ]}
                                                        value={grilleNiveauFilter}
                                                        onValueChange={(val) => setGrilleNiveauFilter(val || 'all')}
                                                        placeholder="Tous les niveaux"
                                                        searchPlaceholder="Rechercher un niveau..."
                                                        emptyMessage="Aucun niveau trouvé."
                                                        className="h-9 text-xs font-normal"
                                                    />
                                                </div>

                                                <div className="space-y-1 min-w-0">
                                                    <Label className="text-[11px] font-semibold text-slate-600">
                                                        Filtrer par Catégorie de Frais
                                                    </Label>
                                                    <SearchableSelect
                                                        options={[
                                                            { value: 'all', label: 'Toutes les catégories' },
                                                            ...categoriesFrais.map((c) => ({
                                                                value: String(c.id),
                                                                label: c.nom,
                                                            })),
                                                        ]}
                                                        value={grilleCategorieFilter}
                                                        onValueChange={(val) => setGrilleCategorieFilter(val || 'all')}
                                                        placeholder="Toutes les catégories"
                                                        searchPlaceholder="Rechercher une catégorie..."
                                                        emptyMessage="Aucune catégorie trouvée."
                                                        className="h-9 text-xs font-normal"
                                                    />
                                                </div>
                                            </div>

                                            {totalObligatoireNiveauSelectionne !== null && nomNiveauFiltre && (
                                                <div className="px-3.5 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-right">
                                                    <div className="text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                                                        Total Obligatoire • {nomNiveauFiltre}
                                                    </div>
                                                    <div className="text-base font-black text-emerald-700 tabular-nums">
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
                                                                    ? 'border-emerald-500 bg-emerald-50/70'
                                                                    : 'border-slate-200 bg-slate-50/60 hover:border-emerald-300'
                                                            }`}
                                                        >
                                                            <div className="flex items-center justify-between gap-1">
                                                                <span className="text-xs font-bold text-slate-800 truncate">
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
                                                                        <div className="text-sm font-black text-emerald-700 tabular-nums">
                                                                            {cat.montantObligatoire.toLocaleString('fr-FR')}{' '}
                                                                            FCFA
                                                                        </div>
                                                                    )}
                                                                    {cat.montantOptionnel > 0 && (
                                                                        <div className="text-[11px] font-semibold text-sky-700 tabular-nums">
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
                                <Card className="border-slate-200 bg-slate-50">
                                    <CardContent className="p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                        <div className="space-y-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <ShieldCheck className="w-5 h-5 text-emerald-600 shrink-0" />
                                                <h4 className="text-sm font-bold text-slate-900">
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
                        <div className="relative overflow-hidden rounded-xl bg-amber-50/50 border border-amber-200/80 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-amber-900/10 select-none pointer-events-none font-mono">
                                03
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-amber-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Bourses & Remises
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 mt-1 flex items-center gap-2">
                                        <Award className="w-5 h-5 text-amber-600 shrink-0" />
                                        Modèles de Bourses, Bonus & Remises ({boursesRemises.length})
                                    </h3>
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
                        <div className="relative overflow-hidden rounded-xl bg-emerald-50/50 border border-emerald-200/80 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 select-none pointer-events-none font-mono">
                                04
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Versements Scolarité
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 mt-1 flex items-center gap-2">
                                        <Receipt className="w-5 h-5 text-emerald-600 shrink-0" />
                                        Historique des Versements Scolarité ({versements.length})
                                    </h3>
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
                            <div className="p-3.5 rounded-xl border border-red-300 bg-red-50/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                                <div className="flex items-center gap-2.5 text-xs text-red-950">
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
                                        className="h-8 text-xs bg-white"
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

                        {versementToEdit && canEditVersements && (
                            <form
                                onSubmit={handleSaveEditVersement}
                                className="p-4 rounded-xl bg-amber-50/80 border-2 border-amber-300 space-y-3 text-xs shadow-xs"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h4 className="font-bold text-amber-950 flex items-center gap-1.5">
                                            <Edit className="w-4 h-4 text-amber-700" />
                                            Modifier le Reçu N° {versementToEdit.reference_recu} —{' '}
                                            {versementToEdit.compte_financier?.eleve?.nom}{' '}
                                            {versementToEdit.compte_financier?.eleve?.prenom}
                                        </h4>
                                        <p className="text-[11px] text-amber-800">
                                            Montant initial :{' '}
                                            <strong>
                                                {Number(versementToEdit.montant_total).toLocaleString('fr-FR')} FCFA
                                            </strong>{' '}
                                            • Le solde de l'élève et les états financiers seront recalculés automatiquement.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setVersementToEdit(null)}
                                        className="text-slate-500 hover:text-slate-800 p-1 cursor-pointer"
                                    >
                                        <X className="w-4 h-4" />
                                    </button>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                                    <div className="space-y-1">
                                        <Label className="text-xs">
                                            Nouveau montant (FCFA) <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            type="number"
                                            min="1"
                                            value={editVersMontant}
                                            onChange={(e) => setEditVersMontant(e.target.value)}
                                            className="h-9 bg-white font-bold tabular-nums"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">
                                            Date du versement <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            type="date"
                                            max={formatDateForInput(new Date())}
                                            value={formatDateForInput(editVersDate)}
                                            onChange={(e) => setEditVersDate(e.target.value)}
                                            className="h-9 bg-white text-xs"
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Mode de règlement</Label>
                                        <Select
                                            value={editVersMode}
                                            onValueChange={(v: ModePaiement) => setEditVersMode(v)}
                                        >
                                            <SelectTrigger className="h-9 bg-white text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="especes">Espèces</SelectItem>
                                                <SelectItem value="mobile_money_manuel">Orange / MTN Money</SelectItem>
                                                <SelectItem value="virement">Virement bancaire</SelectItem>
                                                <SelectItem value="cheque">Chèque</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Payeur / Parent</Label>
                                        <Input
                                            value={editVersPayeur}
                                            onChange={(e) => setEditVersPayeur(e.target.value)}
                                            placeholder="Nom du déposant"
                                            className="h-9 bg-white text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setVersementToEdit(null)}
                                        className="h-8 text-xs"
                                    >
                                        Annuler
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={savingEditVersement}
                                        className="h-8 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold"
                                    >
                                        <CheckCircle2 className="w-3.5 h-3.5 mr-1.5" />
                                        {savingEditVersement ? 'Enregistrement...' : 'Enregistrer la modification'}
                                    </Button>
                                </div>
                            </form>
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
                        <div className="relative overflow-hidden rounded-xl bg-sky-50/50 border border-sky-200/80 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-sky-900/10 select-none pointer-events-none font-mono">
                                05
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-sky-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Recettes Diverses
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 mt-1 flex items-center gap-2">
                                        <Wallet className="w-5 h-5 text-sky-600 shrink-0" />
                                        Recettes Diverses & Encaissements Libres ({reglementsDivers.length})
                                    </h3>
                                </div>
                                <div className="flex flex-wrap items-center gap-2 shrink-0">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        className="h-9 text-xs border-slate-300 hover:bg-slate-100 gap-1.5 cursor-pointer shadow-xs bg-white"
                                        title="Consulter et imprimer l'état filtrable des recettes diverses dans le Centre des États"
                                    >
                                        <Link href={`/administration/finances/etats?type=recettes_diverses&etablissement_id=${currentEtablissement?.id ?? ''}&annee_scolaire_id=${currentAnnee?.id ?? ''}`}>
                                            <Printer className="w-3.5 h-3.5 text-slate-600" />
                                            <span>État des Recettes Diverses</span>
                                        </Link>
                                    </Button>
                                    {canRecettesDiverses && (
                                        <Button
                                            onClick={() => {
                                                setReglementToEdit(null);
                                                setIsReglementDiversModalOpen(true);
                                            }}
                                            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 flex items-center gap-1.5 shadow-sm"
                                        >
                                            <Plus className="w-4 h-4 shrink-0" /> Nouvelle Recette Diverse
                                        </Button>
                                    )}
                                </div>
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
                        <div className="relative overflow-hidden rounded-xl bg-slate-100/80 border border-slate-200 p-4 sm:p-5 shadow-xs">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-slate-900/10 select-none pointer-events-none font-mono">
                                06
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <Badge className="bg-slate-800 text-white text-[10px] font-bold uppercase tracking-wider">
                                        Journal & Clôtures
                                    </Badge>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 mt-1 flex items-center gap-2">
                                        <Calendar className="w-5 h-5 text-slate-700 shrink-0" />
                                        Historique des Clôtures Journalières ({historiqueClotures.length})
                                    </h3>
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
                onClose={() => {
                    setIsReglementDiversModalOpen(false);
                    setReglementToEdit(null);
                }}
                etablissementId={currentEtablissement?.id ?? 0}
                categories={categoriesRecettes}
                reglementToEdit={reglementToEdit}
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

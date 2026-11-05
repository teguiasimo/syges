import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    DollarSign,
    Receipt,
    Users,
    Layers,
    Lock,
    Unlock,
    Plus,
    Printer,
    Search,
    ShieldCheck,
    Calendar,
    Wallet,
    CreditCard,
    Smartphone,
    Award,
    Trash2,
    Edit,
    Building2,
    Tag,
    Clock,
    CalendarClock,
    AlertCircle,
    CheckCircle2,
    Percent,
    Bus,
    FileText,
    FileSpreadsheet,
    ChevronDown,
    Compass,
} from 'lucide-react';
import { toast } from 'sonner';
import { formatDate } from '@/lib/utils';
import { useConfirmDelete } from '@/components/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    CompteFinancierEleve,
    FraisScolaire,
    JournalDuJour,
    TypeFrais,
    VersementCaisse,
    ClotureCaisse,
    Caisse,
    ReglementDivers,
    BourseRemise,
    ModeleEcheance,
    PenaliteConfiguration,
} from '@/types/finance';
import NouveauVersementModal from './modals/nouveau-versement-modal';
import RemiseBourseModal from './modals/remise-bourse-modal';
import AnnulerVersementModal from './modals/annuler-versement-modal';
import ClotureCaisseModal from './modals/cloture-caisse-modal';
import TarifModal from './modals/tarif-modal';
import TypeFraisModal from './modals/type-frais-modal';
import NouveauReglementDiversModal from './modals/nouveau-reglement-divers-modal';
import NouvelleCaisseModal from './modals/nouvelle-caisse-modal';
import ModeleBourseModal from './modals/modele-bourse-modal';
import AttribuerBourseModal from './modals/attribuer-bourse-modal';
import ModeleEcheanceModal from './modals/modele-echeance-modal';
import ConfigurationPenaliteModal from './modals/configuration-penalite-modal';
import SouscriptionOptionModal from './modals/souscription-option-modal';
import DossierFinancierModal from './modals/dossier-financier-modal';

interface FinancesIndexProps {
    versements: VersementCaisse[];
    comptes: CompteFinancierEleve[];
    typesFrais: TypeFrais[];
    tarifs: FraisScolaire[];
    fraisOptionnels?: FraisScolaire[];
    caisses: Caisse[];
    reglementsDivers: ReglementDivers[];
    boursesRemises: BourseRemise[];
    modelesEcheances: ModeleEcheance[];
    penalitesConfigurations: PenaliteConfiguration[];
    journalDuJour: JournalDuJour;
    historiqueClotures: ClotureCaisse[];
    classes: { id: number; nom: string }[];
    niveaux: { id: number; nom: string }[];
    caissiers: { id: number; nom: string; prenom: string }[];
    etablissements: { id: number; nom: string; verrou_solvabilite_actif?: boolean }[];
    currentEtablissement: { id: number; nom: string; verrou_solvabilite_actif?: boolean } | null;
    anneesScolaires: { id: number; code: string }[];
    currentAnnee: { id: number; code: string } | null;
    filters: Record<string, any>;
}

/**
 * Vue principale du Module 11 : Gestion de la Caisse & Paiements (Gesco V1.1 & Aurore).
 * Conforme aux règles SYGES : TanStack Table intégrée, toasts en haut à droite,
 * dates jj-mm-aaaa, journal comptable infalsifiable, reçus sécurisés et verrou de solvabilité.
 *
 * @param {FinancesIndexProps} props
 * @returns {JSX.Element}
 */
export default function FinancesIndex({
    versements,
    comptes,
    typesFrais,
    tarifs,
    fraisOptionnels = [],
    caisses,
    reglementsDivers,
    boursesRemises,
    modelesEcheances,
    penalitesConfigurations,
    journalDuJour,
    historiqueClotures,
    classes,
    niveaux,
    caissiers,
    etablissements,
    currentEtablissement,
    currentAnnee,
    filters,
}: FinancesIndexProps) {
    const [activeTab, setActiveTab] = useState<
        'types_frais' | 'tarifs' | 'echeances_penalites' | 'bourses_remises' | 'caisses' | 'comptes' | 'versements' | 'reglements_divers' | 'journal'
    >((filters.tab as any) || 'types_frais');

    // Boîte de dialogue unifiée de confirmation de suppression
    const { confirmDelete, ConfirmDialog } = useConfirmDelete();

    // États des modales existantes
    const [isVersementModalOpen, setIsVersementModalOpen] = useState(false);
    const [selectedCompteForPayment, setSelectedCompteForPayment] = useState<number | null>(null);
    const [compteForAjustement, setCompteForAjustement] = useState<CompteFinancierEleve | null>(null);
    const [versementToCancel, setVersementToCancel] = useState<VersementCaisse | null>(null);
    const [isClotureModalOpen, setIsClotureModalOpen] = useState(false);
    const [isTarifModalOpen, setIsTarifModalOpen] = useState(false);
    const [isTypeFraisModalOpen, setIsTypeFraisModalOpen] = useState(false);
    const [typeFraisToEdit, setTypeFraisToEdit] = useState<TypeFrais | null>(null);

    // États des nouvelles modales Aurore
    const [isReglementDiversModalOpen, setIsReglementDiversModalOpen] = useState(false);
    const [isCaisseModalOpen, setIsCaisseModalOpen] = useState(false);
    const [caisseToEdit, setCaisseToEdit] = useState<Caisse | null>(null);
    const [isModeleBourseModalOpen, setIsModeleBourseModalOpen] = useState(false);
    const [bourseToEdit, setBourseToEdit] = useState<BourseRemise | null>(null);
    const [isAttribuerBourseModalOpen, setIsAttribuerBourseModalOpen] = useState(false);
    const [compteForBourseAttribution, setCompteForBourseAttribution] = useState<CompteFinancierEleve | null>(null);
    const [isModeleEcheanceModalOpen, setIsModeleEcheanceModalOpen] = useState(false);
    const [isConfigPenaliteModalOpen, setIsConfigPenaliteModalOpen] = useState(false);

    // États pour le Dossier Financier et les Souscriptions Optionnelles
    const [selectedCompteForDossier, setSelectedCompteForDossier] = useState<CompteFinancierEleve | null>(null);
    const [isDossierModalOpen, setIsDossierModalOpen] = useState(false);
    const [selectedCompteForOption, setSelectedCompteForOption] = useState<CompteFinancierEleve | null>(null);
    const [isOptionModalOpen, setIsOptionModalOpen] = useState(false);

    // Filtres locaux
    const [searchCompte, setSearchCompte] = useState(filters.search_compte || '');
    const [selectedClasse, setSelectedClasse] = useState(filters.classe_id ? String(filters.classe_id) : 'all');
    const [selectedStatutFinancier, setSelectedStatutFinancier] = useState(filters.statut_financier || 'all');

    // 1. Colonnes TanStack Table pour les Versements
    const versementsColumns = useMemo<ColumnDef<VersementCaisse>[]>(() => [
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
            cell: ({ row }) => <span className="text-xs">{formatDate(row.original.date_versement)}</span>,
        },
        {
            id: 'eleve',
            header: 'Élève / Classe',
            cell: ({ row }) => {
                const eleve = row.original.compte_financier?.eleve;
                const classe = row.original.inscription?.classe?.nom ?? 'N/A';
                return (
                    <div>
                        <div className="font-medium text-xs text-slate-800 dark:text-slate-200">
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
                <div className="text-right font-bold text-xs text-emerald-600">
                    {Number(row.original.montant_total).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'mode_paiement',
            header: 'Mode',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px] uppercase">
                    {row.original.mode_paiement.replace('_', ' ')}
                </Badge>
            ),
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: ({ row }) => {
                const s = row.original.statut;
                return (
                    <Badge variant={s === 'valide' ? 'default' : 'destructive'} className="text-[10px]">
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
                            onClick={() => window.open(`/administration/finances/versements/${v.id}/recu`, '_blank')}
                        >
                            <Printer className="w-3.5 h-3.5" /> Reçu
                        </Button>
                        {v.statut === 'valide' && (
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
    ], []);

    // 2. Colonnes TanStack Table pour les Comptes Élèves
    const comptesColumns = useMemo<ColumnDef<CompteFinancierEleve>[]>(() => [
        {
            accessorKey: 'eleve.matricule',
            header: 'Matricule',
            cell: ({ row }) => (
                <span className="font-mono text-xs font-semibold">{row.original.eleve?.matricule}</span>
            ),
        },
        {
            id: 'nom_complet',
            header: 'Nom & Prénom',
            cell: ({ row }) => (
                <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                    {row.original.eleve?.nom} {row.original.eleve?.prenom}
                </div>
            ),
        },
        {
            id: 'classe',
            header: 'Classe',
            cell: ({ row }) => (
                <span className="text-xs text-slate-600 dark:text-slate-400">
                    {row.original.inscription?.classe?.nom ?? 'N/A'}
                </span>
            ),
        },
        {
            accessorKey: 'montant_net_exigible',
            header: () => <div className="text-right">Exigible Net</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-medium">
                    {Number(row.original.montant_net_exigible).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'remise_bourse',
            header: () => <div className="text-right">Bourse / Remise</div>,
            cell: ({ row }) => {
                const remise = Number(row.original.remise_bourse || 0);
                return remise > 0 ? (
                    <div className="text-right text-xs font-semibold text-indigo-600">
                        -{remise.toLocaleString('fr-FR')} FCFA
                    </div>
                ) : (
                    <div className="text-right text-xs text-slate-400">-</div>
                );
            },
        },
        {
            accessorKey: 'total_paye',
            header: () => <div className="text-right">Total Payé</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-bold text-emerald-600">
                    {Number(row.original.total_paye).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'solde_restant',
            header: () => <div className="text-right">Reste à Payer</div>,
            cell: ({ row }) => {
                const solde = Number(row.original.solde_restant);
                return (
                    <div className={`text-right text-xs font-bold ${solde > 0 ? 'text-red-600' : 'text-emerald-600'}`}>
                        {solde.toLocaleString('fr-FR')} FCFA
                    </div>
                );
            },
        },
        {
            accessorKey: 'statut_financier',
            header: 'Statut',
            cell: ({ row }) => {
                const st = row.original.statut_financier;
                switch (st) {
                    case 'solde':
                        return <Badge className="bg-emerald-600 text-white text-[10px]">Soldé</Badge>;
                    case 'en_regle_tranche_2':
                        return <Badge className="bg-blue-600 text-white text-[10px]">En Règle Tranche 2</Badge>;
                    case 'en_regle_tranche_1':
                        return <Badge className="bg-sky-600 text-white text-[10px]">En Règle Tranche 1</Badge>;
                    default:
                        return <Badge variant="destructive" className="text-[10px]">Insolvable</Badge>;
                }
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => {
                const c = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 text-blue-700 hover:bg-blue-50 border-blue-200"
                            onClick={() => {
                                setSelectedCompteForDossier(c);
                                setIsDossierModalOpen(true);
                            }}
                            title="Consulter l'échéancier et le dossier financier"
                        >
                            <Layers className="w-3.5 h-3.5" /> Échéancier
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 text-purple-700 hover:bg-purple-50"
                            onClick={() => {
                                setSelectedCompteForOption(c);
                                setIsOptionModalOpen(true);
                            }}
                            title="Souscriptions aux options (Bus, Cantine...)"
                        >
                            <Bus className="w-3.5 h-3.5" /> Options
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 text-emerald-700 hover:bg-emerald-50"
                            onClick={() => {
                                setSelectedCompteForPayment(c.id);
                                setIsVersementModalOpen(true);
                            }}
                        >
                            <DollarSign className="w-3.5 h-3.5" /> Encaisser
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 text-slate-600 hover:text-slate-900"
                            onClick={() => window.open(`/administration/finances/comptes/${c.id}/fiche-pdf`, '_blank')}
                            title="Imprimer le relevé financier officiel pour les parents"
                        >
                            <Printer className="w-3.5 h-3.5" /> Fiche
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 text-indigo-700 hover:bg-indigo-50"
                            onClick={() => {
                                setCompteForBourseAttribution(c);
                                setIsAttribuerBourseModalOpen(true);
                            }}
                        >
                            <Award className="w-3.5 h-3.5" /> Bourse
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs text-slate-500 hover:text-slate-800"
                            onClick={() => setCompteForAjustement(c)}
                        >
                            Ajuster
                        </Button>
                    </div>
                );
            },
        },
    ], []);

    // 3. Colonnes TanStack Table pour les Règlements Divers (Aurore §4.2)
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
            cell: ({ row }) => <span className="text-xs">{formatDate(row.original.date_reglement)}</span>,
        },
        {
            accessorKey: 'payeur_nom',
            header: 'Payeur / Bénéficiaire',
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
                <Badge variant="secondary" className="text-[10px] uppercase">
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
                <div className="text-right font-bold text-xs text-emerald-600">
                    {Number(row.original.montant).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            id: 'caisse',
            header: 'Caisse',
            cell: ({ row }) => (
                <span className="text-xs text-slate-600">{row.original.caisse?.nom ?? 'Caisse Principale'}</span>
            ),
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: ({ row }) => {
                const s = row.original.statut;
                return (
                    <Badge variant={s === 'valide' ? 'default' : 'destructive'} className="text-[10px]">
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
                            onClick={() => window.open(`/administration/finances/reglements-divers/${r.id}/recu`, '_blank')}
                        >
                            <Printer className="w-3.5 h-3.5" /> Reçu
                        </Button>
                        {r.statut === 'valide' && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs text-red-500 hover:text-red-700"
                                onClick={() => {
                                    confirmDelete({
                                        title: 'Annuler ce règlement divers',
                                        itemType: 'le règlement',
                                        itemName: r.reference_recu,
                                        description: 'Êtes-vous sûr de vouloir annuler cette écriture de recette ? Cette opération est irréversible.',
                                        onConfirm: () => {
                                            router.post(`/administration/finances/reglements-divers/${r.id}/annuler`, {
                                                motif_annulation: 'Annulation administrative à la demande du responsable',
                                            }, {
                                                onSuccess: () => toast.success('Règlement divers annulé.'),
                                            });
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
    ], []);

    // 4. Colonnes TanStack Table pour les Caisses Multiples (Aurore §3.20)
    const caissesColumns = useMemo<ColumnDef<Caisse>[]>(() => [
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => <span className="font-mono font-bold text-xs">{row.original.code}</span>,
        },
        {
            accessorKey: 'nom',
            header: 'Nom de la Caisse',
            cell: ({ row }) => (
                <div>
                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                        {row.original.nom}
                    </div>
                    {row.original.description && (
                        <div className="text-[10px] text-slate-500">{row.original.description}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'is_principale',
            header: 'Rôle',
            cell: ({ row }) => (
                row.original.is_principale ? (
                    <Badge className="bg-blue-600 text-white text-[10px]">Caisse Principale</Badge>
                ) : (
                    <Badge variant="outline" className="text-[10px]">Caisse Annexe</Badge>
                )
            ),
        },
        {
            id: 'responsable',
            header: 'Responsable',
            cell: ({ row }) => {
                const resp = row.original.responsable;
                return (
                    <span className="text-xs text-slate-600">
                        {resp ? `${resp.nom} ${resp.prenom}` : 'Non assigné'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'solde_initial',
            header: () => <div className="text-right">Solde Ouverture</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-medium">
                    {Number(row.original.solde_initial).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Statut',
            cell: ({ row }) => (
                <Badge variant={row.original.is_active ? 'default' : 'secondary'} className="text-[10px]">
                    {row.original.is_active ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => {
                const c = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs text-slate-600 hover:text-blue-600"
                            onClick={() => {
                                setCaisseToEdit(c);
                                setIsCaisseModalOpen(true);
                            }}
                        >
                            <Edit className="w-3.5 h-3.5" />
                        </Button>
                        {!c.is_principale && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs text-red-500 hover:text-red-700"
                                onClick={() => {
                                    confirmDelete({
                                        title: 'Supprimer la caisse',
                                        itemType: 'la caisse',
                                        itemName: c.nom,
                                        description: 'Êtes-vous sûr de vouloir supprimer cette caisse ?',
                                        onConfirm: () => {
                                            router.delete(`/administration/finances/caisses/${c.id}`, {
                                                onSuccess: () => toast.success('Caisse supprimée avec succès.'),
                                            });
                                        },
                                    });
                                }}
                            >
                                <Trash2 className="w-3.5 h-3.5" />
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ], []);

    // 5. Colonnes TanStack Table pour les Modèles de Bourses & Remises (Aurore §3.19, §3.21, §3.25)
    const boursesRemisesColumns = useMemo<ColumnDef<BourseRemise>[]>(() => [
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => <span className="font-mono font-bold text-xs">{row.original.code}</span>,
        },
        {
            accessorKey: 'nom',
            header: 'Libellé du modèle',
            cell: ({ row }) => (
                <div>
                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                        {row.original.nom}
                    </div>
                    {row.original.description && (
                        <div className="text-[10px] text-slate-500">{row.original.description}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => (
                <Badge className={row.original.type === 'bourse' ? 'bg-purple-600 text-white text-[10px]' : 'bg-indigo-600 text-white text-[10px]'}>
                    {row.original.type === 'bourse' ? 'Bourse d\'études' : 'Remise'}
                </Badge>
            ),
        },
        {
            accessorKey: 'categorie',
            header: 'Catégorie',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px] uppercase">
                    {row.original.categorie}
                </Badge>
            ),
        },
        {
            accessorKey: 'valeur',
            header: () => <div className="text-right">Taux / Montant</div>,
            cell: ({ row }) => {
                const b = row.original;
                return (
                    <div className="text-right text-xs font-bold text-slate-800 dark:text-slate-200">
                        {b.mode_calcul === 'pourcentage'
                            ? `${b.valeur} %`
                            : `${Number(b.valeur).toLocaleString('fr-FR')} FCFA`}
                    </div>
                );
            },
        },
        {
            id: 'cible',
            header: 'Application',
            cell: ({ row }) => {
                const b = row.original;
                return (
                    <span className="text-xs text-slate-600">
                        {b.niveau?.nom ?? 'Tous niveaux'} {b.type_frais ? `(${b.type_frais.libelle})` : ''}
                    </span>
                );
            },
        },
        {
            accessorKey: 'is_active',
            header: 'Statut',
            cell: ({ row }) => (
                <Badge variant={row.original.is_active ? 'default' : 'secondary'} className="text-[10px]">
                    {row.original.is_active ? 'Actif' : 'Inactif'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => {
                const b = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs text-slate-600 hover:text-blue-600"
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
                            className="h-7 text-xs text-red-500 hover:text-red-700"
                            onClick={() => {
                                confirmDelete({
                                    title: 'Supprimer ce modèle',
                                    itemType: 'le modèle',
                                    itemName: b.nom,
                                    description: 'Êtes-vous sûr de vouloir supprimer ce modèle de bourse/remise ?',
                                    onConfirm: () => {
                                        router.delete(`/administration/finances/bourses-remises/${b.id}`, {
                                            onSuccess: () => toast.success('Modèle supprimé.'),
                                        });
                                    },
                                });
                            }}
                        >
                            <Trash2 className="w-3.5 h-3.5" />
                        </Button>
                    </div>
                );
            },
        },
    ], []);

    // 6. Colonnes TanStack Table pour les Échéanciers Calendaires (Aurore §3.24)
    const modelesEcheancesColumns = useMemo<ColumnDef<ModeleEcheance>[]>(() => [
        {
            accessorKey: 'nom',
            header: 'Nom de l\'Échéancier',
            cell: ({ row }) => (
                <div>
                    <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                        {row.original.nom}
                    </div>
                    {row.original.description && (
                        <div className="text-[10px] text-slate-500">{row.original.description}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'is_default',
            header: 'Défaut',
            cell: ({ row }) => (
                row.original.is_default ? (
                    <Badge className="bg-emerald-600 text-white text-[10px]">Actif par défaut</Badge>
                ) : (
                    <span className="text-xs text-slate-400">-</span>
                )
            ),
        },
        {
            id: 'tranches',
            header: 'Tranches & Dates Limites',
            cell: ({ row }) => {
                const tranches = row.original.tranches || [];
                return (
                    <div className="flex flex-wrap gap-1.5 whitespace-normal min-w-50">
                        {tranches.map((t) => (
                            <Badge key={t.id} variant="outline" className="text-[10px] bg-slate-50">
                                {t.libelle}: {formatDate(t.date_limite)} ({t.pourcentage_exigible}%)
                            </Badge>
                        ))}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => (
                <div className="text-right">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 text-xs text-red-500 hover:text-red-700"
                        onClick={() => {
                            confirmDelete({
                                title: "Supprimer l'échéancier",
                                itemType: "l'échéancier",
                                itemName: row.original.nom,
                                description: 'Êtes-vous sûr de vouloir supprimer cet échéancier calendaire ?',
                                onConfirm: () => {
                                    router.delete(`/administration/finances/modeles-echeances/${row.original.id}`, {
                                        onSuccess: () => toast.success('Échéancier supprimé.'),
                                    });
                                },
                            });
                        }}
                    >
                        <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                </div>
            ),
        },
    ], []);

    // 7. Colonnes TanStack Table pour la Nomenclature des Types de Frais
    const typesFraisColumns = useMemo<ColumnDef<TypeFrais>[]>(() => [
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => (
                <Badge variant="outline" className="font-mono text-[11px] font-semibold bg-slate-100 dark:bg-slate-800">
                    {row.original.code}
                </Badge>
            ),
        },
        {
            accessorKey: 'libelle',
            header: 'Libellé de la Nature du Frais',
            cell: ({ row }) => (
                <div>
                    <div className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                        {row.original.libelle}
                    </div>
                    {row.original.description && (
                        <div className="text-[11px] text-slate-500 line-clamp-1">
                            {row.original.description}
                        </div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'obligatoire',
            header: 'Caractère',
            cell: ({ row }) => (
                row.original.obligatoire ? (
                    <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 text-[10px] font-medium">
                        Obligatoire
                    </Badge>
                ) : (
                    <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-300 text-[10px] font-medium">
                        Optionnel
                    </Badge>
                )
            ),
        },
        {
            accessorKey: 'periodicite',
            header: 'Périodicité',
            cell: ({ row }) => (
                <span className="capitalize text-xs text-slate-600 dark:text-slate-400">
                    {row.original.periodicite}
                </span>
            ),
        },
        {
            accessorKey: 'montant_defaut',
            header: () => <div className="text-right">Montant Indicatif</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-semibold text-slate-900 dark:text-slate-100">
                    {row.original.montant_defaut > 0
                        ? `${Number(row.original.montant_defaut).toLocaleString('fr-FR')} FCFA`
                        : <span className="text-slate-400 italic">Selon niveau</span>
                    }
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => (
                <div className="text-right flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 w-7 p-0 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                        title="Modifier le type de frais"
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
                        className="h-7 w-7 p-0 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/30"
                        title="Supprimer le type de frais"
                        onClick={() => {
                            confirmDelete({
                                title: 'Supprimer ce type de frais',
                                itemType: 'le type de frais',
                                itemName: row.original.libelle,
                                description: 'Attention : supprimer ce type de frais retirera cette catégorie.',
                                onConfirm: () => {
                                    router.delete(`/administration/finances/types-frais/${row.original.id}`, {
                                        onSuccess: () => toast.success('Type de frais supprimé avec succès.', {
                                            position: 'top-right',
                                            className: 'bg-emerald-600 text-white border-none',
                                        }),
                                    });
                                },
                            });
                        }}
                    >
                        <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                </div>
            ),
        },
    ], [confirmDelete]);

    // 8. Colonnes TanStack Table pour la Matrice Tarifaire
    const tarifsColumns = useMemo<ColumnDef<FraisScolaire>[]>(() => [
        {
            accessorKey: 'type_frais.libelle',
            header: 'Nature du Frais',
            cell: ({ row }) => (
                <div className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                    {row.original.type_frais?.libelle}
                </div>
            ),
        },
        {
            accessorKey: 'niveau.nom',
            header: 'Niveau',
            cell: ({ row }) => (
                <span className="text-xs text-slate-600 dark:text-slate-400">
                    {row.original.niveau?.nom ?? 'Tous niveaux'}
                </span>
            ),
        },
        {
            accessorKey: 'tranche_numero',
            header: 'Tranche',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">
                    Tranche {row.original.tranche_numero}
                </Badge>
            ),
        },
        {
            accessorKey: 'montant',
            header: () => <div className="text-right">Montant Exigible</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-bold text-slate-900 dark:text-slate-100">
                    {Number(row.original.montant).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => (
                <div className="text-right">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 text-xs text-red-500 hover:text-red-700"
                        onClick={() => {
                            const tarifNom = `${row.original.type_frais?.libelle ?? 'Frais'} - ${row.original.niveau?.nom ?? 'Tous niveaux'}`;
                            confirmDelete({
                                title: 'Supprimer la ligne tarifaire',
                                itemType: 'le tarif',
                                itemName: tarifNom,
                                description: 'Cette ligne de tarification sera supprimée.',
                                onConfirm: () => {
                                    router.delete(`/administration/finances/tarifs/${row.original.id}`, {
                                        onSuccess: () => toast.success('Ligne tarifaire supprimée avec succès.'),
                                    });
                                },
                            });
                        }}
                    >
                        <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                </div>
            ),
        },
    ], []);

    // 8. Colonnes TanStack Table pour l'Historique des Clôtures
    const cloturesColumns = useMemo<ColumnDef<ClotureCaisse>[]>(() => [
        {
            accessorKey: 'date_cloture',
            header: 'Date Clôture',
            cell: ({ row }) => <span className="font-semibold text-xs">{formatDate(row.original.date_cloture)}</span>,
        },
        {
            accessorKey: 'nombre_transactions',
            header: 'Nb Transactions',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">{row.original.nombre_transactions} reçus</Badge>
            ),
        },
        {
            accessorKey: 'total_especes',
            header: () => <div className="text-right">Espèces</div>,
            cell: ({ row }) => <div className="text-right text-xs">{Number(row.original.total_especes).toLocaleString('fr-FR')} FCFA</div>,
        },
        {
            accessorKey: 'total_mobile_money',
            header: () => <div className="text-right">Mobile Money</div>,
            cell: ({ row }) => <div className="text-right text-xs">{Number(row.original.total_mobile_money).toLocaleString('fr-FR')} FCFA</div>,
        },
        {
            accessorKey: 'total_general',
            header: () => <div className="text-right">Total Général</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-bold text-emerald-600">
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

    // Bascule du verrou de solvabilité sur l'établissement
    const handleToggleVerrouSolvabilite = () => {
        if (!currentEtablissement) return;
        const nouvelEtat = !currentEtablissement.verrou_solvabilite_actif;

        router.post(`/administration/finances/etablissements/${currentEtablissement.id}/verrou-solvabilite`, {
            verrou_solvabilite_actif: nouvelEtat,
        }, {
            onSuccess: () => {
                toast.success(`Le verrou de solvabilité est désormais ${nouvelEtat ? 'ACTIVÉ' : 'DÉSACTIVÉ'}.`);
            },
        });
    };

    // Déclenchement du calcul et application des pénalités de retard
    const handleAppliquerPenalites = () => {
        if (!currentEtablissement || !currentAnnee) return;

        router.post('/administration/finances/penalites/appliquer-echeances', {
            etablissement_id: currentEtablissement.id,
            annee_scolaire_id: currentAnnee.id,
        });
    };

    // Données filtrées pour l'onglet Comptes
    const filteredComptes = useMemo(() => {
        return comptes.filter((c) => {
            if (selectedClasse !== 'all' && String(c.inscription?.classe_id) !== selectedClasse) {
                return false;
            }
            if (selectedStatutFinancier !== 'all' && c.statut_financier !== selectedStatutFinancier) {
                return false;
            }
            if (searchCompte.trim()) {
                const s = searchCompte.toLowerCase();
                const nom = (c.eleve?.nom || '').toLowerCase();
                const prenom = (c.eleve?.prenom || '').toLowerCase();
                const mat = (c.eleve?.matricule || '').toLowerCase();
                return nom.includes(s) || prenom.includes(s) || mat.includes(s);
            }
            return true;
        });
    }, [comptes, selectedClasse, selectedStatutFinancier, searchCompte]);

    const activePenaliteConfig = penalitesConfigurations[0];

    return (
        <>
            <Head title="Gestion Financière & Caisse Avancée - SYGES" />

            {/* Conteneur principal responsive avec marges adaptatives mobile/tablette/desktop */}
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
                                    Finances & Comptabilité Scolaire
                                </h1>
                                <p className="text-xs text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span>Établissement : <strong className="text-slate-800 dark:text-slate-200">{currentEtablissement?.nom}</strong></span>
                                    {currentAnnee && <span>| Année Scolaire : <span className="font-semibold text-emerald-600">{currentAnnee.code}</span></span>}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        {/* Menu d'éditions et rapports financiers officiels */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="text-xs h-9 font-semibold flex items-center gap-1.5 border-blue-300 text-blue-800 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300"
                                >
                                    <Printer className="w-4 h-4 shrink-0 text-blue-600" />
                                    <span>États & Rapports (PDF)</span>
                                    <ChevronDown className="w-3.5 h-3.5 opacity-60" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-60">
                                <DropdownMenuLabel className="text-xs text-slate-500">Rapports Financiers & Caisse</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="text-xs flex items-center gap-2 cursor-pointer"
                                    onClick={() => window.open('/administration/finances/etats/journal-detaille-pdf', '_blank')}
                                >
                                    <FileText className="w-3.5 h-3.5 text-blue-600" />
                                    <span>Journal Détaillé (PDF)</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    className="text-xs flex items-center gap-2 cursor-pointer"
                                    onClick={() => window.open('/administration/finances/etats/journal-synthetique-pdf', '_blank')}
                                >
                                    <FileSpreadsheet className="w-3.5 h-3.5 text-indigo-600" />
                                    <span>Journal Synthétique (PDF)</span>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="text-xs flex items-center gap-2 cursor-pointer text-red-600 dark:text-red-400 font-medium"
                                    onClick={() => {
                                        const query = selectedClasse !== 'all' ? `?classe_id=${selectedClasse}` : '';
                                        window.open(`/administration/finances/etats/insolvables-pdf${query}`, '_blank');
                                    }}
                                >
                                    <AlertCircle className="w-3.5 h-3.5 text-red-600" />
                                    <span>Liste des Insolvables (PDF)</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    className="text-xs flex items-center gap-2 cursor-pointer text-emerald-600 dark:text-emerald-400 font-medium"
                                    onClick={() => {
                                        const query = selectedClasse !== 'all' ? `?classe_id=${selectedClasse}` : '';
                                        window.open(`/administration/finances/etats/en-regle-pdf${query}`, '_blank');
                                    }}
                                >
                                    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Élèves en Règle (PDF)</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button
                            onClick={() => {
                                setSelectedCompteForPayment(null);
                                setIsVersementModalOpen(true);
                            }}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 font-semibold flex-1 sm:flex-initial justify-center flex items-center gap-1.5 shadow-sm"
                        >
                            <Plus className="w-4 h-4 shrink-0" /> Encaisser Scolarité
                        </Button>
                        <Button
                            onClick={() => setIsReglementDiversModalOpen(true)}
                            variant="outline"
                            className="text-xs h-9 font-semibold flex-1 sm:flex-initial justify-center flex items-center gap-1.5 border-emerald-300 text-emerald-800 hover:bg-emerald-50"
                        >
                            <Receipt className="w-4 h-4 shrink-0" /> Recette Diverse
                        </Button>
                    </div>
                </div>

                {/* Cartes KPIs du Jour (Encaissements Journaliers) : empilement progressif pour montants FCFA longs */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5 gap-3">
                    <Card className="border-slate-200 dark:border-slate-800 bg-emerald-50/40 dark:bg-emerald-950/20">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">Total Recettes (Jour)</span>
                                <DollarSign className="w-4 h-4 text-emerald-600 shrink-0" />
                            </div>
                            <div className="text-base sm:text-lg font-extrabold text-emerald-700 dark:text-emerald-400 mt-1 tabular-nums leading-tight wrap-break-word">
                                {Number(journalDuJour.total_general).toLocaleString('fr-FR')} FCFA
                            </div>
                            <span className="text-[10px] text-slate-500">{journalDuJour.nombre_transactions} opérations aujourd'hui</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">Espèces Caisse</span>
                                <Receipt className="w-4 h-4 text-slate-500 shrink-0" />
                            </div>
                            <div className="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-200 mt-1 tabular-nums leading-tight wrap-break-word">
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
                            <div className="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-200 mt-1 tabular-nums leading-tight wrap-break-word">
                                {Number(journalDuJour.total_mobile_money).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">Virements / Chèques</span>
                                <CreditCard className="w-4 h-4 text-blue-600 shrink-0" />
                            </div>
                            <div className="text-sm sm:text-base font-bold text-slate-800 dark:text-slate-200 mt-1 tabular-nums leading-tight wrap-break-word">
                                {(Number(journalDuJour.total_virements) + Number(journalDuJour.total_cheques)).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className={`sm:col-span-2 lg:col-span-1 border-slate-200 dark:border-slate-800 ${journalDuJour.est_cloture ? 'bg-slate-100 dark:bg-slate-900' : 'bg-amber-50/50 dark:bg-amber-950/20'}`}>
                        <CardContent className="p-3 flex flex-col justify-between h-full">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold text-slate-500">État Caisse Journalière</span>
                                {journalDuJour.est_cloture ? <Lock className="w-4 h-4 text-slate-700 shrink-0" /> : <Unlock className="w-4 h-4 text-amber-600 shrink-0" />}
                            </div>
                            <div className="mt-1 flex flex-wrap items-center justify-between gap-1.5">
                                <span className="text-xs font-bold">
                                    {journalDuJour.est_cloture ? 'Journée Clôturée' : 'Caisse Ouverte'}
                                </span>
                                {!journalDuJour.est_cloture && (
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

                {/* Guide du cycle de configuration avec filigrane discret */}
                <div className="p-3 bg-gradient-to-r from-slate-50 via-white to-slate-50 dark:from-slate-900/80 dark:via-slate-900/40 dark:to-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                    <div className="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                        <Compass className="w-4 h-4 text-emerald-600 shrink-0" />
                        <span className="font-bold text-slate-900 dark:text-slate-100">Cycle de Configuration & Gestion Financière :</span>
                        <span className="hidden xl:inline text-slate-500">Ordre chronologique recommandé pour démarrer et exploiter les finances</span>
                    </div>
                    <div className="flex items-center gap-1.5 overflow-x-auto scrollbar-none py-0.5 text-[11px] font-medium">
                        <div className="flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shrink-0">
                            <span className="w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold">1</span>
                            <span>Paramétrage Initial (1 à 5)</span>
                        </div>
                        <span className="text-slate-400 font-bold">→</span>
                        <div className="flex items-center gap-1 px-2.5 py-1 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800 shrink-0">
                            <span className="w-4 h-4 rounded-full bg-blue-600 text-white flex items-center justify-center text-[10px] font-bold">2</span>
                            <span>Exploitation & Guichet (6 à 8)</span>
                        </div>
                        <span className="text-slate-400 font-bold">→</span>
                        <div className="flex items-center gap-1 px-2.5 py-1 rounded-md bg-purple-50 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800 shrink-0">
                            <span className="w-4 h-4 rounded-full bg-purple-600 text-white flex items-center justify-center text-[10px] font-bold">3</span>
                            <span>Audit & Clôture (9)</span>
                        </div>
                    </div>
                </div>

                {/* Onglets de navigation complets ordonnés selon le cycle chronologique de configuration */}
                <div className="flex items-center overflow-x-auto overscroll-x-contain border-b border-slate-200 dark:border-slate-800 gap-1 sm:gap-2 text-xs sm:text-sm font-medium max-w-full scrollbar-none">
                    <button
                        onClick={() => setActiveTab('types_frais')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'types_frais'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <Tag className="w-4 h-4 shrink-0" />
                        1. Types de Frais ({typesFrais.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('tarifs')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'tarifs'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <Layers className="w-4 h-4 shrink-0" />
                        2. Grille Tarifaire ({tarifs.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('echeances_penalites')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'echeances_penalites'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <CalendarClock className="w-4 h-4 shrink-0" />
                        3. Échéanciers & Pénalités
                    </button>
                    <button
                        onClick={() => setActiveTab('bourses_remises')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'bourses_remises'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <Award className="w-4 h-4 shrink-0" />
                        4. Bourses & Remises ({boursesRemises.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('caisses')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'caisses'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <Building2 className="w-4 h-4 shrink-0" />
                        5. Caisses & Guichets ({caisses.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('comptes')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'comptes'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <Users className="w-4 h-4 shrink-0" />
                        6. Comptes Élèves ({comptes.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('versements')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'versements'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <Receipt className="w-4 h-4 shrink-0" />
                        7. Versements Scolarité ({versements.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('reglements_divers')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'reglements_divers'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <Tag className="w-4 h-4 shrink-0" />
                        8. Règlements Divers ({reglementsDivers.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('journal')}
                        className={`pb-2.5 px-2.5 whitespace-nowrap shrink-0 flex items-center gap-1.5 border-b-2 transition-colors cursor-pointer ${activeTab === 'journal'
                            ? 'border-emerald-600 text-emerald-600 font-bold'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                    >
                        <Calendar className="w-4 h-4 shrink-0" />
                        9. Journal & Clôtures ({historiqueClotures.length})
                    </button>
                </div>

                {/* CONTENU ONGLET 1 : TYPES DE FRAIS (NOMENCLATURE) */}
                {activeTab === 'types_frais' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-emerald-50/70 via-white to-emerald-50/30 dark:from-emerald-950/30 dark:via-slate-900 dark:to-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 dark:text-emerald-300/5 select-none pointer-events-none font-mono">
                                01
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 1 sur 9 • Priorité Configuration
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Socle financier</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Tag className="w-5 h-5 text-emerald-600 shrink-0" />
                                        Nomenclature des Types de Frais ({typesFrais.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Première étape obligatoire : définissez ici toutes les catégories de frais de l'établissement (Scolarité, Droits d'inscription, APEE, Transport, Cantine, Uniformes) avant de leur affecter des montants par niveau.
                                    </p>
                                </div>
                                <Button
                                    onClick={() => {
                                        setTypeFraisToEdit(null);
                                        setIsTypeFraisModalOpen(true);
                                    }}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Plus className="w-4 h-4 shrink-0" /> Nouveau Type de Frais
                                </Button>
                            </div>
                        </div>

                        <DataTable
                            columns={typesFraisColumns}
                            data={typesFrais}
                            searchPlaceholder="Rechercher par code, libellé de frais..."
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 2 : GRILLE TARIFAIRE & VERROU */}
                {activeTab === 'tarifs' && (
                    <div className="space-y-6">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-emerald-50/70 via-white to-emerald-50/30 dark:from-emerald-950/30 dark:via-slate-900 dark:to-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 dark:text-emerald-300/5 select-none pointer-events-none font-mono">
                                02
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 2 sur 9 • Priorité Configuration
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Tarification & Exigibilité</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Layers className="w-5 h-5 text-emerald-600 shrink-0" />
                                        Grille Tarifaire par Niveau & Échéances ({tarifs.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Deuxième étape : fixez les montants exigibles pour chaque niveau académique, répartis par tranche avec les dates limites d'exigibilité et verrou de solvabilité.
                                    </p>
                                </div>
                                <Button
                                    onClick={() => setIsTarifModalOpen(true)}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Plus className="w-4 h-4 shrink-0" /> Ajouter un Tarif
                                </Button>
                            </div>
                        </div>

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
                                        Lorsque ce verrou est activé, le téléchargement des bulletins scolaires sur les portails parents/élèves
                                        est automatiquement bloqué tant qu'un solde financier reste exigible à la date limite.
                                    </p>
                                </div>
                                <Button
                                    onClick={handleToggleVerrouSolvabilite}
                                    variant={currentEtablissement?.verrou_solvabilite_actif ? 'destructive' : 'default'}
                                    className="text-xs h-9 font-semibold shrink-0 w-full sm:w-auto"
                                >
                                    {currentEtablissement?.verrou_solvabilite_actif
                                        ? 'Désactiver le Verrou'
                                        : 'Activer le Verrou de Solvabilité'}
                                </Button>
                            </CardContent>
                        </Card>

                        <DataTable
                            columns={tarifsColumns}
                            data={tarifs}
                            searchPlaceholder="Filtrer par niveau, type de frais..."
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 3 : ÉCHÉANCIERS & PÉNALITÉS */}
                {activeTab === 'echeances_penalites' && (
                    <div className="space-y-6">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-amber-50/70 via-white to-amber-50/30 dark:from-amber-950/30 dark:via-slate-900 dark:to-amber-950/20 border border-amber-200/80 dark:border-amber-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-amber-900/10 dark:text-amber-300/5 select-none pointer-events-none font-mono">
                                03
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-amber-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 3 sur 9 • Priorité Configuration
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Règles de Retard & Modèles</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <CalendarClock className="w-5 h-5 text-amber-600 shrink-0" />
                                        Échéanciers de Paiement & Majorations de Retard
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Troisième étape : configurez les modèles d'échéances et activez la politique de pénalités applicables automatiquement aux élèves en retard de paiement.
                                    </p>
                                </div>
                                <Button
                                    onClick={() => setIsModeleEcheanceModalOpen(true)}
                                    className="bg-blue-600 hover:bg-blue-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Plus className="w-4 h-4 shrink-0" /> Créer un Échéancier
                                </Button>
                            </div>
                        </div>

                        {/* Carte de paramétrage de la pénalité de retard */}
                        <Card className="border-slate-200 dark:border-slate-800 bg-amber-50/40 dark:bg-amber-950/20">
                            <CardContent className="p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                <div className="space-y-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Clock className="w-5 h-5 text-amber-600 shrink-0" />
                                        <h4 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                                            Règle de Pénalité Financière de Retard
                                        </h4>
                                    </div>
                                    <p className="text-xs text-slate-600 max-w-2xl">
                                        Règle active : <strong>{activePenaliteConfig ? activePenaliteConfig.nom : 'Aucune règle configurée'}</strong>
                                        {activePenaliteConfig && (
                                            <> — {activePenaliteConfig.mode_calcul === 'pourcentage' ? `${activePenaliteConfig.valeur}%` : `${Number(activePenaliteConfig.valeur).toLocaleString('fr-FR')} FCFA`} de majoration après {activePenaliteConfig.jours_grace} jours de grâce.</>
                                        )}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2 shrink-0 w-full sm:w-auto">
                                    <Button
                                        onClick={() => setIsConfigPenaliteModalOpen(true)}
                                        variant="outline"
                                        size="sm"
                                        className="text-xs h-8 flex-1 sm:flex-initial justify-center"
                                    >
                                        Configurer la Règle
                                    </Button>
                                    <Button
                                        onClick={handleAppliquerPenalites}
                                        size="sm"
                                        className="bg-amber-600 hover:bg-amber-700 text-white text-xs h-8 flex-1 sm:flex-initial justify-center flex items-center gap-1"
                                    >
                                        <AlertCircle className="w-3.5 h-3.5 shrink-0" /> Calculer les Retards
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Modèles d'échéances calendaires */}
                        <div className="space-y-3">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Modèles d'Échéanciers Types</h4>
                            <DataTable
                                columns={modelesEcheancesColumns}
                                data={modelesEcheances}
                            />
                        </div>
                    </div>
                )}

                {/* CONTENU ONGLET 4 : BOURSES & REMISES */}
                {activeTab === 'bourses_remises' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-teal-50/70 via-white to-teal-50/30 dark:from-teal-950/30 dark:via-slate-900 dark:to-teal-950/20 border border-teal-200/80 dark:border-teal-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-teal-900/10 dark:text-teal-300/5 select-none pointer-events-none font-mono">
                                04
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-teal-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 4 sur 9 • Priorité Configuration
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Politiques d'Aide</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Award className="w-5 h-5 text-teal-600 shrink-0" />
                                        Catalogue des Bourses d'Études & Modèles de Remises ({boursesRemises.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Quatrième étape : paramétrez les réductions tarifaires par profil familial (fratrie), mérite scolaire (excellence) ou critères sociaux (% ou montant fixe).
                                    </p>
                                </div>
                                <Button
                                    onClick={() => {
                                        setBourseToEdit(null);
                                        setIsModeleBourseModalOpen(true);
                                    }}
                                    className="bg-teal-600 hover:bg-teal-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Plus className="w-4 h-4 shrink-0" /> Nouveau Modèle de Bourse
                                </Button>
                            </div>
                        </div>

                        <DataTable
                            columns={boursesRemisesColumns}
                            data={boursesRemises}
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 5 : CAISSES MULTIPLES */}
                {activeTab === 'caisses' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-blue-50/70 via-white to-blue-50/30 dark:from-blue-950/30 dark:via-slate-900 dark:to-blue-950/20 border border-blue-200/80 dark:border-blue-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-blue-900/10 dark:text-blue-300/5 select-none pointer-events-none font-mono">
                                05
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-blue-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 5 sur 9 • Infrastructure de Caisse
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Guichets d'encaissement</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Building2 className="w-5 h-5 text-blue-600 shrink-0" />
                                        Caisses & Guichets d'Encaissement ({caisses.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Cinquième étape : configurez les caisses physiques (Caisse Principale, Guichet Maternelle, Guichet Primaire, Collège) et affectez les caissiers autorisés.
                                    </p>
                                </div>
                                <Button
                                    onClick={() => {
                                        setCaisseToEdit(null);
                                        setIsCaisseModalOpen(true);
                                    }}
                                    className="bg-blue-600 hover:bg-blue-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Plus className="w-4 h-4 shrink-0" /> Ajouter une Caisse
                                </Button>
                            </div>
                        </div>

                        <DataTable
                            columns={caissesColumns}
                            data={caisses}
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 6 : COMPTES ÉLÈVES */}
                {activeTab === 'comptes' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-indigo-50/70 via-white to-indigo-50/30 dark:from-indigo-950/30 dark:via-slate-900 dark:to-indigo-950/20 border border-indigo-200/80 dark:border-indigo-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-indigo-900/10 dark:text-indigo-300/5 select-none pointer-events-none font-mono">
                                06
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 6 sur 9 • Exploitation & Dossiers
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Portefeuille Élèves</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Users className="w-5 h-5 text-indigo-600 shrink-0" />
                                        Comptes Financiers des Élèves ({comptes.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Consultez le solde de chaque élève, souscrivez des services optionnels (bus, cantine), attribuez des bourses et imprimez les fiches individuelles de scolarité.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-3">
                            <div className="relative w-full sm:w-64 lg:w-72">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                                <Input
                                    placeholder="Nom, prénom ou matricule..."
                                    value={searchCompte}
                                    onChange={(e) => setSearchCompte(e.target.value)}
                                    className="pl-9 h-9 text-xs"
                                />
                            </div>

                            <Select value={selectedClasse} onValueChange={setSelectedClasse}>
                                <SelectTrigger className="w-full sm:w-48 h-9 text-xs">
                                    <SelectValue placeholder="Toutes les classes" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Toutes les classes</SelectItem>
                                    {classes.map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>{c.nom}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select value={selectedStatutFinancier} onValueChange={setSelectedStatutFinancier}>
                                <SelectTrigger className="w-full sm:w-48 h-9 text-xs">
                                    <SelectValue placeholder="Tous les statuts" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous les statuts</SelectItem>
                                    <SelectItem value="solde">Soldé</SelectItem>
                                    <SelectItem value="en_regle_tranche_2">En Règle Tranche 2</SelectItem>
                                    <SelectItem value="en_regle_tranche_1">En Règle Tranche 1</SelectItem>
                                    <SelectItem value="insolvable">Insolvable</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <DataTable
                            columns={comptesColumns}
                            data={filteredComptes}
                            showColumnVisibilityToggle
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 7 : VERSEMENTS SCOLARITÉ */}
                {activeTab === 'versements' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-emerald-50/70 via-white to-emerald-50/30 dark:from-emerald-950/30 dark:via-slate-900 dark:to-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-emerald-900/10 dark:text-emerald-300/5 select-none pointer-events-none font-mono">
                                07
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 7 sur 9 • Guichet d'Encaissement
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Paiements Directs</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Receipt className="w-5 h-5 text-emerald-600 shrink-0" />
                                        Versements Scolarité & Reçus Sécurisés ({versements.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Encaissez les frais de scolarité au guichet, ventilez automatiquement le paiement sur les tranches échues et générez instantanément le reçu officiel infalsifiable.
                                    </p>
                                </div>
                                <Button
                                    onClick={() => {
                                        setSelectedCompteForPayment(null);
                                        setIsVersementModalOpen(true);
                                    }}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Plus className="w-4 h-4 shrink-0" /> Nouveau Versement
                                </Button>
                            </div>
                        </div>

                        <DataTable
                            columns={versementsColumns}
                            data={versements}
                            searchPlaceholder="Rechercher par référence reçu, élève, caissier..."
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 8 : RÈGLEMENTS DIVERS */}
                {activeTab === 'reglements_divers' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-blue-50/70 via-white to-blue-50/30 dark:from-blue-950/30 dark:via-slate-900 dark:to-blue-950/20 border border-blue-200/80 dark:border-blue-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-blue-900/10 dark:text-blue-300/5 select-none pointer-events-none font-mono">
                                08
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-blue-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 8 sur 9 • Recettes Annexes
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Hors Scolarité</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Tag className="w-5 h-5 text-blue-600 shrink-0" />
                                        Règlements Divers & Encaissements Libres ({reglementsDivers.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Enregistrement des recettes exceptionnelles et annexes non rattachées à la scolarité des élèves (uniformes, macarons, repas occasionnels, dons, duplicatas).
                                    </p>
                                </div>
                                <Button
                                    onClick={() => setIsReglementDiversModalOpen(true)}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                >
                                    <Plus className="w-4 h-4 shrink-0" /> Nouvelle Recette Diverse
                                </Button>
                            </div>
                        </div>

                        <DataTable
                            columns={reglementsDiversColumns}
                            data={reglementsDivers}
                            searchPlaceholder="Rechercher par référence, payeur, libellé..."
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 9 : JOURNAL & CLÔTURES */}
                {activeTab === 'journal' && (
                    <div className="space-y-4">
                        <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-purple-50/70 via-white to-purple-50/30 dark:from-purple-950/30 dark:via-slate-900 dark:to-purple-950/20 border border-purple-200/80 dark:border-purple-800/40 p-4 sm:p-5 shadow-sm">
                            <div className="absolute right-4 -top-3 text-7xl sm:text-8xl font-black text-purple-900/10 dark:text-purple-300/5 select-none pointer-events-none font-mono">
                                09
                            </div>
                            <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-purple-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                            Étape 9 sur 9 • Contrôle, Audit & Clôture
                                        </Badge>
                                        <span className="text-xs text-slate-500 font-medium hidden sm:inline">Arrêtés comptables</span>
                                    </div>
                                    <h3 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-1 flex items-center gap-2">
                                        <Calendar className="w-5 h-5 text-purple-600 shrink-0" />
                                        Historique des Clôtures Journalières de Caisse ({historiqueClotures.length})
                                    </h3>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 max-w-3xl mt-0.5">
                                        Chaque clôture fige les écritures comptables, vérifie le solde réel par rapport au théorique et garantit l'intégrité de la caisse.
                                    </p>
                                </div>
                                {!journalDuJour.est_cloture && (
                                    <Button
                                        onClick={() => setIsClotureModalOpen(true)}
                                        className="bg-amber-600 hover:bg-amber-700 text-white text-xs h-9 shrink-0 flex items-center gap-1.5 shadow-sm"
                                    >
                                        <Lock className="w-4 h-4 shrink-0" /> Clôturer la Journée
                                    </Button>
                                )}
                            </div>
                        </div>

                        <DataTable
                            columns={cloturesColumns}
                            data={historiqueClotures}
                        />
                    </div>
                )}
            </div>

            {/* Modales de gestion */}
            <NouveauVersementModal
                open={isVersementModalOpen}
                onOpenChange={setIsVersementModalOpen}
                comptes={comptes}
                typesFrais={typesFrais}
                tarifs={tarifs}
                preselectedCompteId={selectedCompteForPayment}
            />

            <RemiseBourseModal
                open={Boolean(compteForAjustement)}
                onOpenChange={(open) => !open && setCompteForAjustement(null)}
                compte={compteForAjustement}
            />

            <AnnulerVersementModal
                open={Boolean(versementToCancel)}
                onOpenChange={(open) => !open && setVersementToCancel(null)}
                versement={versementToCancel}
            />

            <ClotureCaisseModal
                open={isClotureModalOpen}
                onOpenChange={setIsClotureModalOpen}
                journal={journalDuJour}
                etablissementId={currentEtablissement?.id ?? 0}
            />

            <TarifModal
                open={isTarifModalOpen}
                onOpenChange={setIsTarifModalOpen}
                niveaux={niveaux}
                typesFrais={typesFrais}
                anneeId={currentAnnee?.id ?? 0}
                etablissementId={currentEtablissement?.id ?? 0}
                onOpenCreateTypeFrais={() => {
                    setTypeFraisToEdit(null);
                    setIsTypeFraisModalOpen(true);
                }}
            />

            <TypeFraisModal
                open={isTypeFraisModalOpen}
                onOpenChange={(open) => {
                    setIsTypeFraisModalOpen(open);
                    if (!open) setTypeFraisToEdit(null);
                }}
                etablissementId={currentEtablissement?.id ?? 0}
                typeFraisToEdit={typeFraisToEdit}
            />

            {/* Nouvelles modales Aurore */}
            <NouveauReglementDiversModal
                isOpen={isReglementDiversModalOpen}
                onClose={() => setIsReglementDiversModalOpen(false)}
                caisses={caisses}
                caissiers={caissiers}
            />

            <NouvelleCaisseModal
                isOpen={isCaisseModalOpen}
                onClose={() => {
                    setIsCaisseModalOpen(false);
                    setCaisseToEdit(null);
                }}
                etablissementId={currentEtablissement?.id ?? 0}
                caissiers={caissiers}
                caisseToEdit={caisseToEdit}
            />

            <ModeleBourseModal
                isOpen={isModeleBourseModalOpen}
                onClose={() => {
                    setIsModeleBourseModalOpen(false);
                    setBourseToEdit(null);
                }}
                etablissementId={currentEtablissement?.id ?? 0}
                anneeId={currentAnnee?.id}
                niveaux={niveaux}
                typesFrais={typesFrais}
                modelToEdit={bourseToEdit}
            />

            <AttribuerBourseModal
                isOpen={isAttribuerBourseModalOpen}
                onClose={() => {
                    setIsAttribuerBourseModalOpen(false);
                    setCompteForBourseAttribution(null);
                }}
                compte={compteForBourseAttribution}
                boursesRemises={boursesRemises}
            />

            <ModeleEcheanceModal
                isOpen={isModeleEcheanceModalOpen}
                onClose={() => setIsModeleEcheanceModalOpen(false)}
                etablissementId={currentEtablissement?.id ?? 0}
                anneeId={currentAnnee?.id ?? 0}
            />

            <ConfigurationPenaliteModal
                isOpen={isConfigPenaliteModalOpen}
                onClose={() => setIsConfigPenaliteModalOpen(false)}
                etablissementId={currentEtablissement?.id ?? 0}
                config={activePenaliteConfig}
            />

            {/* Modal de consultation du Dossier Financier & Échéancier complet de l'élève */}
            <DossierFinancierModal
                isOpen={isDossierModalOpen}
                onClose={() => {
                    setIsDossierModalOpen(false);
                    setSelectedCompteForDossier(null);
                }}
                compte={selectedCompteForDossier}
                onNouveauVersement={(c) => {
                    setSelectedCompteForPayment(c.id);
                    setIsVersementModalOpen(true);
                }}
            />

            {/* Modal de souscription aux prestations optionnelles (Bus scolaire, Cantine, Internat...) */}
            <SouscriptionOptionModal
                isOpen={isOptionModalOpen}
                onClose={() => {
                    setIsOptionModalOpen(false);
                    setSelectedCompteForOption(null);
                }}
                compte={selectedCompteForOption}
                fraisOptionnels={fraisOptionnels || []}
            />

            {/* Dialogue de confirmation de suppression unifié */}
            <ConfirmDialog />
        </>
    );
}

/**
 * Fil d'Ariane pour la page Finance & Caisse.
 */
FinancesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Caisse & Paiements',
            href: '/administration/finances',
        },
    ],
};

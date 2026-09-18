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
    AlertTriangle,
    Search,
    ShieldCheck,
    CheckCircle2,
    Calendar,
    Wallet,
    CreditCard,
    Smartphone,
    Award,
    Trash2,
} from 'lucide-react';
import { toast } from 'sonner';
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
    CompteFinancierEleve,
    FraisScolaire,
    JournalDuJour,
    TypeFrais,
    VersementCaisse,
    ClotureCaisse,
} from '@/types/finance';
import NouveauVersementModal from './modals/nouveau-versement-modal';
import RemiseBourseModal from './modals/remise-bourse-modal';
import AnnulerVersementModal from './modals/annuler-versement-modal';
import ClotureCaisseModal from './modals/cloture-caisse-modal';
import TarifModal from './modals/tarif-modal';

interface FinancesIndexProps {
    versements: VersementCaisse[];
    comptes: CompteFinancierEleve[];
    typesFrais: TypeFrais[];
    tarifs: FraisScolaire[];
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
 * Vue principale du Module 11 : Gestion de la Caisse & Paiements (Gesco V1.1).
 * Conforme aux règles SYGES : TanStack Table intégrée, toasts verts en haut à droite,
 * journal comptable infalsifiable, reçus sécurisés et verrou de solvabilité.
 *
 * @param {FinancesIndexProps} props
 * @returns {JSX.Element}
 */
export default function FinancesIndex({
    versements,
    comptes,
    typesFrais,
    tarifs,
    journalDuJour,
    historiqueClotures,
    classes,
    niveaux,
    currentEtablissement,
    currentAnnee,
    filters,
}: FinancesIndexProps) {
    const [activeTab, setActiveTab] = useState<'versements' | 'comptes' | 'journal' | 'tarifs'>(
        (filters.tab as any) || 'versements'
    );

    // Boîte de dialogue unifiée de confirmation de suppression
    const { confirmDelete, ConfirmDialog } = useConfirmDelete();

    // États des modales
    const [isVersementModalOpen, setIsVersementModalOpen] = useState(false);
    const [selectedCompteForPayment, setSelectedCompteForPayment] = useState<number | null>(null);
    const [compteForBourse, setCompteForBourse] = useState<CompteFinancierEleve | null>(null);
    const [versementToCancel, setVersementToCancel] = useState<VersementCaisse | null>(null);
    const [isClotureModalOpen, setIsClotureModalOpen] = useState(false);
    const [isTarifModalOpen, setIsTarifModalOpen] = useState(false);

    // Filtres locaux
    const [searchVersement, setSearchVersement] = useState(filters.search || '');
    const [searchCompte, setSearchCompte] = useState(filters.search_compte || '');
    const [selectedClasse, setSelectedClasse] = useState(filters.classe_id ? String(filters.classe_id) : 'all');
    const [selectedStatutFinancier, setSelectedStatutFinancier] = useState(filters.statut_financier || 'all');

    // 1. Définition des colonnes TanStack Table pour les Versements
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
            header: 'Date & Heure',
            cell: ({ row }) => {
                const d = new Date(row.original.date_versement);
                return (
                    <div className="text-xs">
                        <span className="font-medium text-slate-700 dark:text-slate-300">
                            {d.toLocaleDateString('fr-FR')}
                        </span>
                        <span className="text-slate-400 block text-[11px]">
                            {d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'eleve',
            header: 'Élève & Classe',
            cell: ({ row }) => {
                const eleve = row.original.compte_financier?.eleve;
                const classe = row.original.inscription?.classe;
                return (
                    <div>
                        <div className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                            {eleve?.nom} {eleve?.prenom}
                        </div>
                        <div className="text-[11px] text-slate-500">
                            Mat: {eleve?.matricule} • Classe: <span className="font-medium">{classe?.nom ?? 'N/A'}</span>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'montant_total',
            header: () => <div className="text-right">Montant (FCFA)</div>,
            cell: ({ row }) => (
                <div className="text-right font-bold text-xs text-emerald-600 dark:text-emerald-400">
                    {Number(row.original.montant_total).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'mode_paiement',
            header: 'Mode Règlement',
            cell: ({ row }) => {
                const mode = row.original.mode_paiement;
                switch (mode) {
                    case 'especes':
                        return <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[10px]">Espèces</Badge>;
                    case 'mobile_money_manuel':
                        return <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 text-[10px]">Mobile Money</Badge>;
                    case 'virement':
                        return <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 text-[10px]">Virement</Badge>;
                    case 'cheque':
                        return <Badge variant="outline" className="bg-purple-50 text-purple-700 border-purple-200 text-[10px]">Chèque</Badge>;
                    default:
                        return <Badge variant="outline">{mode}</Badge>;
                }
            },
        },
        {
            accessorKey: 'recu_par',
            header: 'Encaissé par',
            cell: ({ row }) => (
                <span className="text-xs text-slate-600 dark:text-slate-400">
                    {row.original.recu_par || 'Service Intendance'}
                </span>
            ),
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: ({ row }) => {
                const isAnnule = row.original.statut === 'annule';
                return isAnnule ? (
                    <Badge variant="destructive" className="text-[10px]">Annulé</Badge>
                ) : (
                    <Badge variant="secondary" className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 text-[10px]">
                        Validé
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
                            variant="outline"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 text-slate-700 hover:text-slate-900"
                            onClick={() => window.open(`/administration/finances/versements/${v.id}/recu`, '_blank')}
                        >
                            <Printer className="w-3.5 h-3.5 text-blue-600" />
                            Reçu PDF
                        </Button>
                        {v.statut === 'valide' && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs text-red-500 hover:text-red-700 hover:bg-red-50"
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

    // 2. Définition des colonnes TanStack Table pour les Comptes Élèves
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
                            className="h-7 text-xs text-emerald-700 hover:text-emerald-900 border-emerald-300 hover:bg-emerald-50"
                            onClick={() => {
                                setSelectedCompteForPayment(c.id);
                                setIsVersementModalOpen(true);
                            }}
                        >
                            <DollarSign className="w-3.5 h-3.5 mr-0.5" /> Encaisser
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs text-indigo-600 hover:text-indigo-800"
                            onClick={() => setCompteForBourse(c)}
                        >
                            <Award className="w-3.5 h-3.5 mr-0.5" /> Bourse
                        </Button>
                    </div>
                );
            },
        },
    ], []);

    // 3. Définition des colonnes TanStack Table pour la Matrice Tarifaire
    const tarifsColumns = useMemo<ColumnDef<FraisScolaire>[]>(() => [
        {
            id: 'niveau',
            header: 'Niveau',
            cell: ({ row }) => (
                <span className="font-semibold text-xs">{row.original.niveau?.nom ?? 'N/A'}</span>
            ),
        },
        {
            accessorKey: 'serie',
            header: 'Série / Filière',
            cell: ({ row }) => (
                <span className="text-xs text-slate-600">{row.original.serie || 'Toutes séries (Défaut)'}</span>
            ),
        },
        {
            id: 'type_frais',
            header: 'Type de Frais',
            cell: ({ row }) => (
                <span className="text-xs font-medium">{row.original.type_frais?.libelle ?? 'Frais'}</span>
            ),
        },
        {
            accessorKey: 'tranche_numero',
            header: 'Tranche',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">
                    {row.original.libelle_tranche || `Tranche ${row.original.tranche_numero}`}
                </Badge>
            ),
        },
        {
            accessorKey: 'montant',
            header: () => <div className="text-right">Montant (FCFA)</div>,
            cell: ({ row }) => (
                <div className="text-right font-bold text-xs">
                    {Number(row.original.montant).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'date_limite',
            header: 'Échéance',
            cell: ({ row }) => {
                const dl = row.original.date_limite;
                return (
                    <span className="text-xs text-slate-500">
                        {dl ? new Date(dl).toLocaleDateString('fr-FR') : 'Sans date limite'}
                    </span>
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
                        className="h-7 text-xs text-red-500 hover:text-red-700 cursor-pointer"
                        onClick={() => {
                            const tarifNom = `${row.original.type_frais?.libelle ?? 'Frais'} - ${row.original.niveau?.nom ?? 'Tous niveaux'}`;
                            confirmDelete({
                                title: 'Supprimer la ligne tarifaire',
                                itemType: 'le tarif',
                                itemName: tarifNom,
                                description: 'Cette ligne de tarification sera supprimée. Les comptes élèves existants conserveront leur historique comptable.',
                                onConfirm: () => {
                                    router.delete(`/administration/finances/tarifs/${row.original.id}`, {
                                        onSuccess: () =>
                                            toast.success('Ligne tarifaire supprimée avec succès.', {
                                                position: 'top-right',
                                                style: {
                                                    backgroundColor: '#10b981',
                                                    color: '#ffffff',
                                                    border: '1px solid #059669',
                                                },
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
    ], []);

    // 4. Définition des colonnes TanStack Table pour l'Historique des Clôtures
    const cloturesColumns = useMemo<ColumnDef<ClotureCaisse>[]>(() => [
        {
            accessorKey: 'date_cloture',
            header: 'Date Clôture',
            cell: ({ row }) => {
                const d = new Date(row.original.date_cloture);
                return <span className="font-semibold text-xs">{d.toLocaleDateString('fr-FR')}</span>;
            },
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
                toast.success(`Le verrou de solvabilité est désormais ${nouvelEtat ? 'ACTIVÉ' : 'DÉSACTIVÉ'}.`, {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
            },
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

    return (
        <>
            <Head title="Gestion de la Caisse & Paiements - SYGES" />

            <div className="space-y-6">
                {/* En-tête de la page avec résumé financier */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <div className="p-2 bg-emerald-600 rounded-lg text-white">
                                <Wallet className="w-5 h-5" />
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                    Caisse & Paiements Scolaires
                                </h1>
                                <p className="text-xs text-slate-500">
                                    Établissement : <strong>{currentEtablissement?.nom}</strong> | Année :{' '}
                                    <strong>{currentAnnee?.code ?? '2026-2027'}</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            onClick={() => {
                                setSelectedCompteForPayment(null);
                                setIsVersementModalOpen(true);
                            }}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1.5 shadow-sm text-xs h-9"
                        >
                            <Plus className="w-4 h-4" />
                            Nouveau Versement (Guichet)
                        </Button>
                    </div>
                </div>

                {/* Cartes KPIs du Jour (Encaissements Journaliers) */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <Card className="border-slate-200 dark:border-slate-800 bg-emerald-50/40 dark:bg-emerald-950/20">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Total Général (Jour)</span>
                                <DollarSign className="w-4 h-4 text-emerald-600" />
                            </div>
                            <div className="text-lg font-extrabold text-emerald-700 dark:text-emerald-400 mt-1">
                                {Number(journalDuJour.total_general).toLocaleString('fr-FR')} FCFA
                            </div>
                            <span className="text-[10px] text-slate-500">{journalDuJour.nombre_transactions} encaissements</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Espèces Physiques</span>
                                <Receipt className="w-4 h-4 text-slate-500" />
                            </div>
                            <div className="text-base font-bold text-slate-800 dark:text-slate-200 mt-1">
                                {Number(journalDuJour.total_especes).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Mobile Money (Manuel)</span>
                                <Smartphone className="w-4 h-4 text-amber-600" />
                            </div>
                            <div className="text-base font-bold text-slate-800 dark:text-slate-200 mt-1">
                                {Number(journalDuJour.total_mobile_money).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Virements / Chèques</span>
                                <CreditCard className="w-4 h-4 text-blue-600" />
                            </div>
                            <div className="text-base font-bold text-slate-800 dark:text-slate-200 mt-1">
                                {(Number(journalDuJour.total_virements) + Number(journalDuJour.total_cheques)).toLocaleString('fr-FR')} FCFA
                            </div>
                        </CardContent>
                    </Card>

                    <Card className={`border-slate-200 dark:border-slate-800 ${journalDuJour.est_cloture ? 'bg-slate-100 dark:bg-slate-900' : 'bg-amber-50/50 dark:bg-amber-950/20'}`}>
                        <CardContent className="p-3 flex flex-col justify-between h-full">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">État de la Caisse</span>
                                {journalDuJour.est_cloture ? <Lock className="w-4 h-4 text-slate-700" /> : <Unlock className="w-4 h-4 text-amber-600" />}
                            </div>
                            <div className="mt-1 flex items-center justify-between">
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

                {/* Onglets de navigation */}
                <div className="flex border-b border-slate-200 dark:border-slate-800 gap-4 text-sm font-medium">
                    <button
                        onClick={() => setActiveTab('versements')}
                        className={`pb-2.5 flex items-center gap-1.5 border-b-2 transition-colors ${
                            activeTab === 'versements'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Receipt className="w-4 h-4" />
                        Guichet & Versements ({versements.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('comptes')}
                        className={`pb-2.5 flex items-center gap-1.5 border-b-2 transition-colors ${
                            activeTab === 'comptes'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Users className="w-4 h-4" />
                        Comptes Financiers Élèves ({comptes.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('journal')}
                        className={`pb-2.5 flex items-center gap-1.5 border-b-2 transition-colors ${
                            activeTab === 'journal'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Calendar className="w-4 h-4" />
                        Journal & Clôtures ({historiqueClotures.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('tarifs')}
                        className={`pb-2.5 flex items-center gap-1.5 border-b-2 transition-colors ${
                            activeTab === 'tarifs'
                                ? 'border-emerald-600 text-emerald-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Layers className="w-4 h-4" />
                        Matrice Tarifaire & Verrou
                    </button>
                </div>

                {/* CONTENU ONGLET 1 : VERSEMENTS */}
                {activeTab === 'versements' && (
                    <div className="space-y-4">
                        <DataTable
                            columns={versementsColumns}
                            data={versements}
                            searchPlaceholder="Rechercher par référence reçu, élève, caissier..."
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 2 : COMPTES ÉLÈVES */}
                {activeTab === 'comptes' && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="relative w-72">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                                <Input
                                    placeholder="Nom, prénom ou matricule..."
                                    value={searchCompte}
                                    onChange={(e) => setSearchCompte(e.target.value)}
                                    className="pl-9 h-9 text-xs"
                                />
                            </div>

                            <Select value={selectedClasse} onValueChange={setSelectedClasse}>
                                <SelectTrigger className="w-48 h-9 text-xs">
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
                                <SelectTrigger className="w-48 h-9 text-xs">
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
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 3 : JOURNAL & CLÔTURES */}
                {activeTab === 'journal' && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-slate-800 dark:text-slate-200">
                                    Historique des Clôtures Journalières de Caisse
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Chaque clôture fige les écritures comptables et garantit l'intégrité de la caisse.
                                </p>
                            </div>
                            {!journalDuJour.est_cloture && (
                                <Button
                                    onClick={() => setIsClotureModalOpen(true)}
                                    className="bg-amber-600 hover:bg-amber-700 text-white text-xs h-8 flex items-center gap-1"
                                >
                                    <Lock className="w-3.5 h-3.5" /> Clôturer la Journée
                                </Button>
                            )}
                        </div>

                        <DataTable
                            columns={cloturesColumns}
                            data={historiqueClotures}
                        />
                    </div>
                )}

                {/* CONTENU ONGLET 4 : MATRICE TARIFAIRE & VERROU DE SOLVABILITÉ */}
                {activeTab === 'tarifs' && (
                    <div className="space-y-6">
                        {/* Paramètre du Verrou de Solvabilité (Gesco V1.1 - Module 11.5) */}
                        <Card className="border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                            <CardContent className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <ShieldCheck className="w-5 h-5 text-indigo-600" />
                                        <h4 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                                            Verrou de Solvabilité des Bulletins Scolaires (Module 11.5)
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
                                    className="text-xs h-9 font-semibold shrink-0"
                                >
                                    {currentEtablissement?.verrou_solvabilite_actif
                                        ? 'Désactiver le Verrou'
                                        : 'Activer le Verrou de Solvabilité'}
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Grille tarifaire */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-sm font-bold text-slate-800 dark:text-slate-200">
                                        Grille Tarifaire (Niveau / Série / Tranches)
                                    </h3>
                                    <p className="text-xs text-slate-500">
                                        Définit les frais exigibles appliqués automatiquement aux nouveaux élèves inscrits.
                                    </p>
                                </div>
                                <Button
                                    onClick={() => setIsTarifModalOpen(true)}
                                    size="sm"
                                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs h-8 flex items-center gap-1"
                                >
                                    <Plus className="w-3.5 h-3.5" /> Ajouter un Tarif
                                </Button>
                            </div>

                            <DataTable
                                columns={tarifsColumns}
                                data={tarifs}
                            />
                        </div>
                    </div>
                )}
            </div>

            {/* Modales */}
            <NouveauVersementModal
                open={isVersementModalOpen}
                onOpenChange={setIsVersementModalOpen}
                comptes={comptes}
                typesFrais={typesFrais}
                tarifs={tarifs}
                preselectedCompteId={selectedCompteForPayment}
            />

            <RemiseBourseModal
                open={Boolean(compteForBourse)}
                onOpenChange={(open) => !open && setCompteForBourse(null)}
                compte={compteForBourse}
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

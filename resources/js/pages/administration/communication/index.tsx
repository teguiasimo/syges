import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    MessageSquare,
    Send,
    Radio,
    Coins,
    Settings,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Search,
    Plus,
    Filter,
    ShieldAlert,
    Clock,
    User,
    Sparkles,
} from 'lucide-react';
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
import { formatDate } from '@/lib/utils';
import {
    HistoriqueSms,
    SmsCampagne,
    SmsConfiguration,
    TypeCible,
    TypeNotification,
} from '@/types/communication';
import NouvelleCampagneModal from './modals/nouvelle-campagne-modal';
import SmsDirectModal from './modals/sms-direct-modal';
import ConfigPasserelleModal from './modals/config-passerelle-modal';
import RechargerSoldeModal from './modals/recharger-solde-modal';

interface CommunicationIndexProps {
    config: SmsConfiguration;
    campagnes: SmsCampagne[];
    historiqueSms: HistoriqueSms[];
    classes: { id: number; nom: string }[];
    niveaux: { id: number; nom: string }[];
    sections: { id: number; nom: string }[];
    etablissements: { id: number; nom: string }[];
    currentEtablissement: { id: number; nom: string } | null;
    filters: Record<string, any>;
}

/**
 * Vue principale du Module 14 : Communication & Notifications SMS (Gesco V1.1).
 *
 * Implémente :
 * - Gestion du solde et configuration de la passerelle SMS (Gesco 14.1).
 * - Envoi de notifications automatiques et campagnes groupées ciblées (14.2).
 * - Traçabilité exhaustive avec TanStack Table (14.3).
 * - Sanitisation stricte GSM 7-bit pour optimiser la facturation (14.4).
 *
 * @param {CommunicationIndexProps} props
 * @returns {JSX.Element}
 */
export default function CommunicationIndex({
    config,
    campagnes,
    historiqueSms,
    classes,
    niveaux,
    sections,
    currentEtablissement,
    filters,
}: CommunicationIndexProps) {
    const [activeTab, setActiveTab] = useState<'campagnes' | 'historique' | 'passerelle'>(
        (filters.tab as any) || 'campagnes'
    );

    // Modales
    const [isCampagneModalOpen, setIsCampagneModalOpen] = useState(false);
    const [isSmsDirectModalOpen, setIsSmsDirectModalOpen] = useState(false);
    const [isConfigModalOpen, setIsConfigModalOpen] = useState(false);
    const [isRechargerModalOpen, setIsRechargerModalOpen] = useState(false);

    // Filtres
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedTypeNotif, setSelectedTypeNotif] = useState(filters.type_notification || 'all');

    // 1. Colonnes TanStack Table pour les Campagnes de Masse
    const campagnesColumns = useMemo<ColumnDef<SmsCampagne>[]>(() => [
        {
            accessorKey: 'created_at',
            header: 'Date & Heure',
            cell: ({ row }) => {
                const date = row.original.created_at ? new Date(row.original.created_at) : new Date();
                return (
                    <div>
                        {/* Formatage obligatoire jj-mm-aaaa via formatDate */}
                        <span className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                            {row.original.created_at ? formatDate(row.original.created_at) : '-'}
                        </span>
                        <div className="text-[10px] text-slate-500 font-mono">
                            {date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'titre',
            header: 'Objet de la Campagne',
            cell: ({ row }) => (
                <div className="min-w-0">
                    <span className="font-bold text-xs text-slate-900 dark:text-slate-100 block truncate">{row.original.titre}</span>
                    <p className="text-[10px] text-slate-500 line-clamp-1 max-w-xs" title={row.original.message_sanitise}>
                        {row.original.message_sanitise}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'cible_type',
            header: 'Cible',
            cell: ({ row }) => {
                const map: Record<TypeCible, { label: string; className: string }> = {
                    tous: { label: 'Tout l\'établissement', className: 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/40 dark:text-teal-300' },
                    section: { label: 'Section', className: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300' },
                    niveau: { label: 'Niveau', className: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300' },
                    classe: { label: 'Classe', className: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300' },
                    insolvables: { label: 'Parents Insolvables', className: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300' },
                };
                const conf = map[row.original.cible_type] || { label: row.original.cible_type, className: '' };
                return (
                    <Badge variant="outline" className={`text-[10px] whitespace-nowrap ${conf.className}`}>
                        {conf.label}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'nombre_destinataires',
            header: () => <div className="text-center">Destinataires</div>,
            cell: ({ row }) => (
                <div className="text-center font-bold text-xs text-slate-800 dark:text-slate-200">
                    {row.original.nombre_destinataires} parents
                </div>
            ),
        },
        {
            accessorKey: 'total_credits',
            header: () => <div className="text-center">Crédits Déduits</div>,
            cell: ({ row }) => (
                <div className="text-center font-mono font-bold text-xs text-teal-600 dark:text-teal-400">
                    {row.original.total_credits} SMS
                </div>
            ),
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: () => (
                <Badge className="bg-emerald-600 text-white text-[10px] flex items-center gap-1 w-fit">
                    <CheckCircle2 className="w-3 h-3" /> Diffusé
                </Badge>
            ),
        },
        {
            id: 'lanceur',
            header: 'Diffusé Par',
            cell: ({ row }) => (
                <span className="text-xs text-slate-500 font-medium">
                    {row.original.lanceur?.name ?? 'Direction'}
                </span>
            ),
        },
    ], []);

    // 2. Colonnes TanStack Table pour l'Historique Détaillé
    const historiqueColumns = useMemo<ColumnDef<HistoriqueSms>[]>(() => [
        {
            accessorKey: 'created_at',
            header: 'Date & Heure',
            cell: ({ row }) => {
                const date = row.original.created_at ? new Date(row.original.created_at) : new Date();
                return (
                    <div className="font-mono text-xs">
                        {/* Formatage obligatoire jj-mm-aaaa via formatDate */}
                        <span className="font-semibold">{row.original.created_at ? formatDate(row.original.created_at) : '-'}</span>
                        <span className="text-slate-400 ml-1">{date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'destinataire_telephone',
            header: 'Destinataire',
            cell: ({ row }) => (
                <div className="min-w-0">
                    <div className="font-bold text-xs text-slate-900 dark:text-slate-100 truncate">
                        {row.original.destinataire_nom ?? 'Parent d\'élève'}
                    </div>
                    <span className="font-mono text-[10px] text-teal-600 dark:text-teal-400 font-semibold">
                        {row.original.destinataire_telephone}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'type_notification',
            header: 'Nature',
            cell: ({ row }) => {
                const map: Record<TypeNotification, { label: string; className: string }> = {
                    alerte_absence: { label: 'Alerte Absence', className: 'bg-amber-100 text-amber-800 border-amber-200' },
                    alerte_sanction: { label: 'Alerte Sanction', className: 'bg-rose-100 text-rose-800 border-rose-200' },
                    rappel_paiement: { label: 'Rappel Échéance', className: 'bg-blue-100 text-blue-800 border-blue-200' },
                    confirmation_recu: { label: 'Reçu de Caisse', className: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
                    campagne_masse: { label: 'Campagne Masse', className: 'bg-slate-100 text-slate-800 border-slate-200' },
                    personnalise: { label: 'Direct Unitaire', className: 'bg-purple-100 text-purple-800 border-purple-200' },
                };
                const c = map[row.original.type_notification] || { label: row.original.type_notification, className: '' };
                return (
                    <Badge variant="outline" className={`text-[10px] ${c.className}`}>
                        {c.label}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'message',
            header: 'Message (GSM 7-bit)',
            cell: ({ row }) => (
                <p className="text-xs text-slate-700 dark:text-slate-300 max-w-85 truncate" title={row.original.message}>
                    {row.original.message}
                </p>
            ),
        },
        {
            accessorKey: 'credits_consommes',
            header: () => <div className="text-center">Crédits</div>,
            cell: ({ row }) => (
                <div className="text-center font-mono text-xs font-bold text-slate-700">
                    {row.original.credits_consommes}
                </div>
            ),
        },
        {
            accessorKey: 'statut_distribution',
            header: 'Distribution',
            cell: ({ row }) => {
                const st = row.original.statut_distribution;
                return st === 'envoye' ? (
                    <Badge className="bg-emerald-600 text-white text-[10px] flex items-center gap-1 w-fit">
                        <CheckCircle2 className="w-3 h-3" /> Délivré
                    </Badge>
                ) : (
                    <Badge variant="destructive" className="text-[10px] flex items-center gap-1 w-fit">
                        <XCircle className="w-3 h-3" /> Échec
                    </Badge>
                );
            },
        },
    ], []);

    // Filtrage des éléments
    const filteredCampagnes = useMemo(() => {
        if (!searchQuery.trim()) return campagnes;
        const q = searchQuery.toLowerCase();
        return campagnes.filter(
            (c) => c.titre.toLowerCase().includes(q) || c.message_sanitise.toLowerCase().includes(q)
        );
    }, [campagnes, searchQuery]);

    const filteredHistorique = useMemo(() => {
        return historiqueSms.filter((h) => {
            if (selectedTypeNotif !== 'all' && h.type_notification !== selectedTypeNotif) {
                return false;
            }
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const nom = (h.destinataire_nom || '').toLowerCase();
                const tel = h.destinataire_telephone.toLowerCase();
                const msg = h.message.toLowerCase();
                return nom.includes(q) || tel.includes(q) || msg.includes(q);
            }
            return true;
        });
    }, [historiqueSms, selectedTypeNotif, searchQuery]);

    const isSoldeBas = config.solde_credits <= config.seuil_alerte_solde;

    return (
        <>
            <Head title="Communication & Notifications SMS - SYGES" />

            {/* Conteneur principal plein écran avec espacement adaptatif et débordement maîtrisé */}
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 min-w-0 w-full">
                {/* En-tête responsive avec boutons d'actions repliables */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 bg-teal-600 rounded-lg text-white shrink-0">
                                <MessageSquare className="w-5 h-5" />
                            </div>
                            <div>
                                <h1 className="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100">
                                    Communication & Notifications SMS
                                </h1>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    Établissement : <strong>{currentEtablissement?.nom}</strong> | Expéditeur officiel :{' '}
                                    <strong className="font-mono text-teal-600 dark:text-teal-400">{config.sender_id}</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            onClick={() => setIsSmsDirectModalOpen(true)}
                            variant="outline"
                            className="text-xs h-9 flex items-center gap-1.5"
                        >
                            <MessageSquare className="w-4 h-4" />
                            <span>SMS Direct</span>
                        </Button>
                        <Button
                            onClick={() => setIsCampagneModalOpen(true)}
                            className="bg-teal-600 hover:bg-teal-700 text-white flex items-center gap-1.5 shadow-sm text-xs h-9"
                        >
                            <Send className="w-4 h-4" />
                            <span>Diffuser une Campagne</span>
                        </Button>
                    </div>
                </div>

                {/* Cartes KPI et Solde adaptatives */}
                <div className="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3">
                    <Card className={`border-slate-200 dark:border-slate-800 ${isSoldeBas ? 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-200' : 'bg-emerald-50/40 dark:bg-emerald-950/20'}`}>
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-600">Solde Crédits SMS</span>
                                <Coins className={`w-4 h-4 ${isSoldeBas ? 'text-rose-600' : 'text-emerald-600'}`} />
                            </div>
                            <div className={`text-xl font-extrabold mt-1 tabular-nums ${isSoldeBas ? 'text-rose-700' : 'text-emerald-700 dark:text-emerald-400'}`}>
                                {config.solde_credits} SMS
                            </div>
                            <div className="flex items-center justify-between mt-1">
                                <span className="text-[10px] text-slate-500">Alerte seuil : {config.seuil_alerte_solde}</span>
                                <button
                                    onClick={() => setIsRechargerModalOpen(true)}
                                    className="text-[10px] font-bold text-teal-600 hover:underline"
                                >
                                    Recharger +
                                </button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Campagnes Diffusées</span>
                                <Send className="w-4 h-4 text-teal-600" />
                            </div>
                            <div className="text-lg font-extrabold text-slate-900 dark:text-slate-100 mt-1 tabular-nums">
                                {campagnes.length}
                            </div>
                            <span className="text-[10px] text-slate-400">Diffusion groupée aux parents</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Total SMS Émis</span>
                                <Radio className="w-4 h-4 text-slate-500" />
                            </div>
                            <div className="text-lg font-extrabold text-slate-900 dark:text-slate-100 mt-1 tabular-nums">
                                {historiqueSms.length}
                            </div>
                            <span className="text-[10px] text-slate-400">Audit trail & traçabilité</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Norme GSM 7-bit</span>
                                <Sparkles className="w-4 h-4 text-amber-500" />
                            </div>
                            <div className="text-base font-bold text-slate-800 dark:text-slate-200 mt-1">
                                160 car. / SMS
                            </div>
                            <span className="text-[10px] text-slate-400">Accents sanitisiés sans surcoût</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Onglets et Filtres avec défilement fluide */}
                <div className="space-y-4">
                    <div className="border-b border-slate-200 dark:border-slate-800 pb-2">
                        {/* Onglets avec défilement horizontal sur mobile */}
                        <div className="flex items-center gap-2 overflow-x-auto pb-1 -mb-1 w-full shrink-0">
                            <button
                                onClick={() => setActiveTab('campagnes')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 whitespace-nowrap shrink-0 ${
                                    activeTab === 'campagnes'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <Send className="w-3.5 h-3.5 shrink-0" />
                                <span>Campagnes SMS ({campagnes.length})</span>
                            </button>
                            <button
                                onClick={() => setActiveTab('historique')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 whitespace-nowrap shrink-0 ${
                                    activeTab === 'historique'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <Radio className="w-3.5 h-3.5 shrink-0" />
                                <span>Journal des SMS ({historiqueSms.length})</span>
                            </button>
                            <button
                                onClick={() => setActiveTab('passerelle')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 whitespace-nowrap shrink-0 ${
                                    activeTab === 'passerelle'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <Settings className="w-3.5 h-3.5 shrink-0" />
                                <span>Passerelle Télécom & Sender ID</span>
                            </button>
                        </div>
                    </div>

                    {/* Barre d'outils de filtres responsive */}
                    {activeTab !== 'passerelle' && (
                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <div className="relative flex-1 min-w-0">
                                <Search className="w-4 h-4 absolute left-2.5 top-2.5 text-slate-400" />
                                <Input
                                    placeholder="Rechercher par titre, destinataire ou contenu..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="h-9 pl-9 text-xs w-full"
                                />
                            </div>

                            {activeTab === 'historique' && (
                                <div className="w-full sm:w-52 shrink-0">
                                    <Select value={selectedTypeNotif} onValueChange={setSelectedTypeNotif}>
                                        <SelectTrigger className="h-9 text-xs w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous types</SelectItem>
                                            <SelectItem value="alerte_absence">Alertes Absence</SelectItem>
                                            <SelectItem value="alerte_sanction">Alertes Sanction</SelectItem>
                                            <SelectItem value="rappel_paiement">Rappels Échéance</SelectItem>
                                            <SelectItem value="confirmation_recu">Reçus de Caisse</SelectItem>
                                            <SelectItem value="campagne_masse">Campagnes de Masse</SelectItem>
                                            <SelectItem value="personnalise">SMS Directs</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Rendu des Tables TanStack avec carte enveloppante scrollable */}
                    {activeTab === 'campagnes' && (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-3 sm:p-4 overflow-hidden min-w-0">
                            <DataTable
                                columns={campagnesColumns}
                                data={filteredCampagnes}
                            />
                        </div>
                    )}

                    {activeTab === 'historique' && (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-3 sm:p-4 overflow-hidden min-w-0">
                            <DataTable
                                columns={historiqueColumns}
                                data={filteredHistorique}
                            />
                        </div>
                    )}

                    {activeTab === 'passerelle' && (
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <Card className="border-slate-200 dark:border-slate-800">
                                <CardContent className="p-4 space-y-4">
                                    <div className="flex items-center justify-between border-b pb-3">
                                        <div className="flex items-center gap-2">
                                            <Settings className="w-5 h-5 text-teal-600 shrink-0" />
                                            <div>
                                                <h2 className="text-sm font-bold text-slate-800 dark:text-slate-100">
                                                    Passerelle Télécom Active
                                                </h2>
                                                <span className="text-[10px] text-slate-500">
                                                    Opérateur : <strong>{config.fournisseur.toUpperCase()}</strong>
                                                </span>
                                            </div>
                                        </div>
                                        <Badge className={config.is_active ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'}>
                                            {config.is_active ? 'Opérationnelle' : 'Désactivée'}
                                        </Badge>
                                    </div>

                                    <div className="space-y-2 text-xs">
                                        <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                            <span className="text-slate-500">Sender ID (Expéditeur GSM) :</span>
                                            <span className="font-mono font-bold text-teal-600 dark:text-teal-400">{config.sender_id}</span>
                                        </div>
                                        <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                            <span className="text-slate-500">Solde de Crédits Actuel :</span>
                                            <span className="font-mono font-bold text-emerald-600">{config.solde_credits} SMS</span>
                                        </div>
                                        <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                            <span className="text-slate-500">Alerte Seuil Bas :</span>
                                            <span className="font-mono text-slate-800 dark:text-slate-200">{config.seuil_alerte_solde} SMS</span>
                                        </div>
                                        <div className="flex justify-between py-1">
                                            <span className="text-slate-500">URL Endpoint :</span>
                                            <span className="font-mono text-slate-600 truncate max-w-[180px] sm:max-w-xs">{config.api_url || 'Par défaut SDK'}</span>
                                        </div>
                                    </div>

                                    <div className="pt-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                        <Button
                                            onClick={() => setIsConfigModalOpen(true)}
                                            variant="outline"
                                            className="text-xs h-9 flex-1"
                                        >
                                            Modifier les Identifiants
                                        </Button>
                                        <Button
                                            onClick={() => setIsRechargerModalOpen(true)}
                                            className="bg-amber-500 hover:bg-amber-600 text-white text-xs h-9 flex-1"
                                        >
                                            Recharger des Crédits
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200 dark:border-slate-800">
                                <CardContent className="p-4 space-y-3">
                                    <h2 className="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                        <Sparkles className="w-4 h-4 text-amber-500 shrink-0" />
                                        <span>Norme GSM 7-bit & Tarification</span>
                                    </h2>
                                    <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                        SYGES intègre un filtre de sanitisation automatique (Gesco V1.1 - Module 14.4).
                                        Tous les caractères accentués français (ex: <code>é, è, ê, à, ç</code>) sont automatiquement convertis
                                        vers leurs équivalents ASCII (<code>e, e, e, a, c</code>).
                                    </p>
                                    <div className="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-md border border-slate-200 dark:border-slate-700 text-xs space-y-1">
                                        <div className="flex justify-between font-semibold">
                                            <span>Format GSM 7-bit standard :</span>
                                            <span className="text-emerald-600">160 caractères = 1 crédit</span>
                                        </div>
                                        <div className="flex justify-between text-slate-400">
                                            <span>Format Unicode (évité par SYGES) :</span>
                                            <span className="text-rose-500">70 caractères = 1 crédit</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>

            {/* Modales */}
            <NouvelleCampagneModal
                open={isCampagneModalOpen}
                onOpenChange={setIsCampagneModalOpen}
                etablissementId={currentEtablissement?.id ?? 0}
                classes={classes}
                niveaux={niveaux}
                sections={sections}
                soldeCredits={config.solde_credits}
            />

            <SmsDirectModal
                open={isSmsDirectModalOpen}
                onOpenChange={setIsSmsDirectModalOpen}
                etablissementId={currentEtablissement?.id ?? 0}
            />

            <ConfigPasserelleModal
                open={isConfigModalOpen}
                onOpenChange={setIsConfigModalOpen}
                config={config}
            />

            <RechargerSoldeModal
                open={isRechargerModalOpen}
                onOpenChange={setIsRechargerModalOpen}
                etablissementId={currentEtablissement?.id ?? 0}
                soldeActuel={config.solde_credits}
            />
        </>
    );
}

/**
 * Fil d'Ariane pour la page Communication.
 */
CommunicationIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Communication & SMS',
            href: '/administration/communication',
        },
    ],
};

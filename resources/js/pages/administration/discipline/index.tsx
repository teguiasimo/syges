import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    ShieldAlert,
    UserX,
    Clock,
    AlertOctagon,
    Award,
    CheckCircle2,
    XCircle,
    Plus,
    Search,
    Trash2,
    CheckSquare,
    Filter,
    FileText,
} from 'lucide-react';
import { toast } from 'sonner';
import { useConfirmDelete } from '@/components/confirm-delete-dialog';
import { formatDate } from '@/lib/utils';
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
    Assiduite,
    SanctionDisciplinaire,
    SyntheseDisciplinaire,
    TypeSanction,
} from '@/types/discipline';
import NouvelleAssiduiteModal from './modals/nouvelle-assiduite-modal';
import JustifierModal from './modals/justifier-modal';
import NouvelleSanctionModal from './modals/nouvelle-sanction-modal';

interface DisciplineIndexProps {
    assiduites: Assiduite[];
    sanctions: SanctionDisciplinaire[];
    syntheses: SyntheseDisciplinaire[];
    periodes: { id: number; nom: string; code: string }[];
    currentPeriode: { id: number; nom: string; code: string } | null;
    classes: { id: number; nom: string }[];
    matieres: { id: number; nom: string }[];
    inscriptions: any[];
    etablissements: { id: number; nom: string }[];
    currentEtablissement: { id: number; nom: string } | null;
    anneesScolaires: { id: number; code: string }[];
    currentAnnee: { id: number; code: string } | null;
    filters: Record<string, any>;
}

/**
 * Vue principale du Module 12 : Absences, Retards & Discipline (Gesco V1.1).
 *
 * Implémente :
 * - Feuille d'assiduité temps-réel (Absences et retards horodatés).
 * - Justification a posteriori avec archivage du motif.
 * - Registre des sanctions avec contrôle de sévérité.
 * - Synthèse disciplinaire trimestrielle synchronisée avec les bulletins (Module 10).
 * - Détection automatique de déchéance des mentions honorifiques (Module 12.4: NJ >= 15h).
 *
 * @param {DisciplineIndexProps} props
 * @returns {JSX.Element}
 */
export default function DisciplineIndex({
    assiduites,
    sanctions,
    syntheses,
    periodes,
    currentPeriode,
    classes,
    matieres,
    inscriptions,
    currentEtablissement,
    currentAnnee,
    filters,
}: DisciplineIndexProps) {
    const [activeTab, setActiveTab] = useState<'assiduites' | 'sanctions' | 'synthese'>(
        (filters.tab as any) || 'assiduites'
    );

    // Boîte de dialogue unifiée de confirmation de suppression
    const { confirmDelete, ConfirmDialog } = useConfirmDelete();

    // Modales
    const [isAssiduiteModalOpen, setIsAssiduiteModalOpen] = useState(false);
    const [isSanctionModalOpen, setIsSanctionModalOpen] = useState(false);
    const [assiduiteToJustify, setAssiduiteToJustify] = useState<Assiduite | null>(null);

    // Filtres d'affichage
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedClasse, setSelectedClasse] = useState(filters.classe_id ? String(filters.classe_id) : 'all');
    const [selectedType, setSelectedType] = useState(filters.type_evenement || 'all');
    const [selectedJustif, setSelectedJustif] = useState(filters.statut_justification || 'all');

    // Suppression d'un événement d'assiduité
    const handleDeleteAssiduite = (item: Assiduite) => {
        const eleveNom = item.inscription?.eleve
            ? `${item.inscription.eleve.nom} ${item.inscription.eleve.prenom ?? ''}`.trim()
            : 'l’élève';
        confirmDelete({
            title: "Supprimer l'événement d'assiduité",
            itemType: "l'absence / retard",
            itemName: `${item.type_evenement} (${eleveNom})`,
            description: "Cet événement sera définitivement supprimé. Le décompte d'heures et les récapitulatifs du bulletin seront recalculés.",
            onConfirm: () => {
                router.delete(`/administration/discipline/assiduites/${item.id}`, {
                    onSuccess: () => {
                        toast.success('Événement d’assiduité supprimé avec succès.', {
                            position: 'top-right',
                            style: {
                                backgroundColor: '#10b981',
                                color: '#ffffff',
                                border: '1px solid #059669',
                            },
                        });
                    },
                    onError: () => {
                        toast.error('Erreur lors de la suppression.', { position: 'top-right' });
                    },
                });
            },
        });
    };

    // Suppression d'une sanction
    const handleDeleteSanction = (item: SanctionDisciplinaire) => {
        const eleveNom = item.inscription?.eleve
            ? `${item.inscription.eleve.nom} ${item.inscription.eleve.prenom ?? ''}`.trim()
            : 'l’élève';
        confirmDelete({
            title: 'Annuler la sanction disciplinaire',
            itemType: 'la sanction',
            itemName: `${item.type_sanction} (${eleveNom})`,
            description: "Cette sanction disciplinaire sera définitivement annulée et retirée du dossier de l'élève.",
            confirmLabel: 'Annuler la sanction',
            onConfirm: () => {
                router.delete(`/administration/discipline/sanctions/${item.id}`, {
                    onSuccess: () => {
                        toast.success('Sanction disciplinaire retirée avec succès.', {
                            position: 'top-right',
                            style: {
                                backgroundColor: '#10b981',
                                color: '#ffffff',
                                border: '1px solid #059669',
                            },
                        });
                    },
                    onError: () => {
                        toast.error('Erreur lors de la suppression de la sanction.', { position: 'top-right' });
                    },
                });
            },
        });
    };

    // 1. Colonnes TanStack Table pour la Feuille d'Assiduité
    const assiduiteColumns = useMemo<ColumnDef<Assiduite>[]>(() => [
        {
            accessorKey: 'date_evenement',
            header: 'Date & Période',
            cell: ({ row }) => (
                <div>
                    <span className="font-semibold text-xs text-slate-800 dark:text-slate-200">
                        {formatDate(row.original.date_evenement)}
                    </span>
                    <div className="text-[10px] text-slate-500">
                        Signalé par {row.original.signalant?.name ?? 'Vie Scolaire'}
                    </div>
                </div>
            ),
        },
        {
            id: 'eleve',
            header: 'Élève',
            cell: ({ row }) => (
                <div>
                    <div className="font-bold text-xs text-slate-900 dark:text-slate-100">
                        {row.original.inscription?.eleve?.nom} {row.original.inscription?.eleve?.prenom}
                    </div>
                    <div className="text-[10px] text-slate-500 font-mono">
                        {row.original.inscription?.eleve?.matricule}
                    </div>
                </div>
            ),
        },
        {
            id: 'classe',
            header: 'Classe',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">
                    {row.original.inscription?.classe?.nom ?? 'N/A'}
                </Badge>
            ),
        },
        {
            accessorKey: 'matiere',
            header: 'Matière / Cours',
            cell: ({ row }) => (
                <span className="text-xs text-slate-700 dark:text-slate-300">
                    {row.original.matiere?.nom ?? 'Étude / Surveillance'}
                </span>
            ),
        },
        {
            accessorKey: 'type_evenement',
            header: 'Nature',
            cell: ({ row }) => {
                const type = row.original.type_evenement;
                return type === 'absence' ? (
                    <Badge className="bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-200 text-[10px] flex items-center gap-1 w-fit">
                        <UserX className="w-3 h-3" /> Absence
                    </Badge>
                ) : (
                    <Badge className="bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-200 text-[10px] flex items-center gap-1 w-fit">
                        <Clock className="w-3 h-3" /> Retard
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'nombre_heures',
            header: 'Volume',
            cell: ({ row }) => (
                <span className="font-bold text-xs">
                    {row.original.nombre_heures}h
                </span>
            ),
        },
        {
            accessorKey: 'statut_justification',
            header: 'Statut Justification',
            cell: ({ row }) => {
                const justif = row.original.statut_justification === 'justifiee';
                return (
                    <div>
                        {justif ? (
                            <Badge className="bg-emerald-600 text-white text-[10px] flex items-center gap-1 w-fit">
                                <CheckCircle2 className="w-3 h-3" /> Justifiée
                            </Badge>
                        ) : (
                            <Badge variant="destructive" className="text-[10px] flex items-center gap-1 w-fit">
                                <XCircle className="w-3 h-3" /> Non Justifiée
                            </Badge>
                        )}
                        {row.original.motif_justification && (
                            <p className="text-[10px] text-slate-500 italic mt-0.5 max-w-50 truncate" title={row.original.motif_justification}>
                                {row.original.motif_justification}
                            </p>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => (
                <div className="flex items-center justify-end gap-1.5">
                    {row.original.statut_justification === 'non_justifiee' && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-7 px-2 text-xs text-emerald-700 hover:text-emerald-800 hover:bg-emerald-50"
                            onClick={() => setAssiduiteToJustify(row.original)}
                        >
                            <CheckSquare className="w-3.5 h-3.5 mr-1" />
                            Justifier
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="ghost"
                        className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600"
                        onClick={() => handleDeleteAssiduite(row.original)}
                        title="Supprimer"
                    >
                        <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                </div>
            ),
        },
    ], []);

    // 2. Colonnes TanStack Table pour le Registre des Sanctions
    const sanctionsColumns = useMemo<ColumnDef<SanctionDisciplinaire>[]>(() => [
        {
            accessorKey: 'date_decision',
            header: 'Date',
            cell: ({ row }) => (
                <span className="font-semibold text-xs">{formatDate(row.original.date_decision)}</span>
            ),
        },
        {
            id: 'eleve',
            header: 'Élève',
            cell: ({ row }) => (
                <div>
                    <div className="font-bold text-xs text-slate-900 dark:text-slate-100">
                        {row.original.inscription?.eleve?.nom} {row.original.inscription?.eleve?.prenom}
                    </div>
                    <div className="text-[10px] text-slate-500 font-mono">
                        {row.original.inscription?.eleve?.matricule}
                    </div>
                </div>
            ),
        },
        {
            id: 'classe',
            header: 'Classe',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">
                    {row.original.inscription?.classe?.nom ?? 'N/A'}
                </Badge>
            ),
        },
        {
            accessorKey: 'type_sanction',
            header: 'Sanction Prononcée',
            cell: ({ row }) => {
                const map: Record<TypeSanction, { label: string; className: string }> = {
                    avertissement: { label: 'Avertissement Conduite', className: 'bg-amber-100 text-amber-800 border-amber-300' },
                    blame: { label: 'Blâme Conduite', className: 'bg-orange-100 text-orange-800 border-orange-300' },
                    retenue: { label: 'Retenue / Consigne', className: 'bg-blue-100 text-blue-800 border-blue-300' },
                    exclusion_temporaire: { label: 'Exclusion Temporaire', className: 'bg-rose-100 text-rose-800 border-rose-300' },
                    exclusion_definitive: { label: 'Exclusion Définitive', className: 'bg-red-700 text-white' },
                };
                const config = map[row.original.type_sanction] || { label: row.original.type_sanction, className: 'bg-slate-100 text-slate-800' };

                return (
                    <Badge variant="outline" className={`text-[10px] font-semibold ${config.className}`}>
                        {config.label}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'nombre_jours_ou_heures',
            header: 'Durée / Impact',
            cell: ({ row }) => (
                <span className="font-bold text-xs">
                    {row.original.nombre_jours_ou_heures} {row.original.type_sanction === 'retenue' ? 'h' : 'jour(s)'}
                </span>
            ),
        },
        {
            accessorKey: 'motif',
            header: 'Motif',
            cell: ({ row }) => (
                <span className="text-xs text-slate-700 dark:text-slate-300 max-w-65 line-clamp-2" title={row.original.motif}>
                    {row.original.motif}
                </span>
            ),
        },
        {
            accessorKey: 'decide_par',
            header: 'Décidé Par',
            cell: ({ row }) => (
                <span className="text-xs text-slate-500 font-medium">
                    {row.original.decide_par}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => (
                <div className="flex items-center justify-end">
                    <Button
                        size="sm"
                        variant="ghost"
                        className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600"
                        onClick={() => handleDeleteSanction(row.original)}
                        title="Retirer la sanction"
                    >
                        <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                </div>
            ),
        },
    ], []);

    // 3. Colonnes TanStack Table pour la Synthèse du Bulletin & Déchéance des Mentions
    const syntheseColumns = useMemo<ColumnDef<SyntheseDisciplinaire>[]>(() => [
        {
            id: 'eleve',
            header: 'Élève',
            cell: ({ row }) => (
                <div>
                    <div className="font-bold text-xs text-slate-900 dark:text-slate-100">
                        {row.original.inscription?.eleve?.nom} {row.original.inscription?.eleve?.prenom}
                    </div>
                    <div className="text-[10px] text-slate-500 font-mono">
                        {row.original.inscription?.eleve?.matricule}
                    </div>
                </div>
            ),
        },
        {
            id: 'classe',
            header: 'Classe',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">
                    {row.original.inscription?.classe?.nom ?? 'N/A'}
                </Badge>
            ),
        },
        {
            accessorKey: 'absences_justifiees',
            header: () => <div className="text-center">Abs. Justifiées</div>,
            cell: ({ row }) => (
                <div className="text-center text-xs text-slate-600">
                    {row.original.absences_justifiees}h
                </div>
            ),
        },
        {
            accessorKey: 'absences_non_justifiees',
            header: () => <div className="text-center">Abs. Non Justifiées</div>,
            cell: ({ row }) => {
                const nj = Number(row.original.absences_non_justifiees || 0);
                return (
                    <div className={`text-center text-xs font-bold ${nj >= 15 ? 'text-rose-600' : 'text-slate-800 dark:text-slate-200'}`}>
                        {nj}h
                    </div>
                );
            },
        },
        {
            accessorKey: 'retards',
            header: () => <div className="text-center">Retards</div>,
            cell: ({ row }) => (
                <div className="text-center text-xs text-slate-600">
                    {row.original.retards}
                </div>
            ),
        },
        {
            id: 'sanctions_resume',
            header: 'Sanctions (A / B / Excl)',
            cell: ({ row }) => (
                <div className="flex items-center gap-1.5 text-xs">
                    <span title="Avertissements" className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-semibold text-[10px]">
                        {row.original.avertissements_conduite} A
                    </span>
                    <span title="Blâmes" className="px-1.5 py-0.5 rounded bg-orange-50 text-orange-700 font-semibold text-[10px]">
                        {row.original.blames_conduite} B
                    </span>
                    <span title="Exclusion (jours)" className="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 font-semibold text-[10px]">
                        {row.original.exclusions_jours} j
                    </span>
                </div>
            ),
        },
        {
            id: 'decheance_mentions',
            header: 'Statut Mentions & Honneur',
            cell: ({ row }) => {
                const nj = Number(row.original.absences_non_justifiees || 0);
                const hasExclusion = Number(row.original.exclusions_jours || 0) > 0;
                const dechu = nj >= 15 || hasExclusion;

                return dechu ? (
                    <Badge variant="destructive" className="text-[10px] flex items-center gap-1 w-fit">
                        <AlertOctagon className="w-3 h-3" />
                        Déchéance Tableau d'Honneur ({nj >= 15 ? 'Absences ≥ 15h' : 'Exclusion'})
                    </Badge>
                ) : (
                    <Badge className="bg-emerald-600 text-white text-[10px] flex items-center gap-1 w-fit">
                        <Award className="w-3 h-3" /> Éligible Distinctions
                    </Badge>
                );
            },
        },
    ], []);

    // Données filtrées
    const filteredAssiduites = useMemo(() => {
        return assiduites.filter((item) => {
            if (selectedClasse !== 'all' && String(item.inscription?.classe_id) !== selectedClasse) {
                return false;
            }
            if (selectedType !== 'all' && item.type_evenement !== selectedType) {
                return false;
            }
            if (selectedJustif !== 'all' && item.statut_justification !== selectedJustif) {
                return false;
            }
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const nom = (item.inscription?.eleve?.nom || '').toLowerCase();
                const prenom = (item.inscription?.eleve?.prenom || '').toLowerCase();
                const mat = (item.inscription?.eleve?.matricule || '').toLowerCase();
                return nom.includes(q) || prenom.includes(q) || mat.includes(q);
            }
            return true;
        });
    }, [assiduites, selectedClasse, selectedType, selectedJustif, searchQuery]);

    const filteredSanctions = useMemo(() => {
        return sanctions.filter((item) => {
            if (selectedClasse !== 'all' && String(item.inscription?.classe_id) !== selectedClasse) {
                return false;
            }
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const nom = (item.inscription?.eleve?.nom || '').toLowerCase();
                const prenom = (item.inscription?.eleve?.prenom || '').toLowerCase();
                const mat = (item.inscription?.eleve?.matricule || '').toLowerCase();
                return nom.includes(q) || prenom.includes(q) || mat.includes(q);
            }
            return true;
        });
    }, [sanctions, selectedClasse, searchQuery]);

    const filteredSyntheses = useMemo(() => {
        return syntheses.filter((item) => {
            if (selectedClasse !== 'all' && String(item.inscription?.classe_id) !== selectedClasse) {
                return false;
            }
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const nom = (item.inscription?.eleve?.nom || '').toLowerCase();
                const prenom = (item.inscription?.eleve?.prenom || '').toLowerCase();
                const mat = (item.inscription?.eleve?.matricule || '').toLowerCase();
                return nom.includes(q) || prenom.includes(q) || mat.includes(q);
            }
            return true;
        });
    }, [syntheses, selectedClasse, searchQuery]);

    // Métriques globales pour les KPI cards
    const kpis = useMemo(() => {
        const totalHeuresAbsence = assiduites
            .filter((a) => a.type_evenement === 'absence')
            .reduce((acc, curr) => acc + Number(curr.nombre_heures), 0);

        const totalAbsencesNJ = assiduites
            .filter((a) => a.type_evenement === 'absence' && a.statut_justification === 'non_justifiee')
            .reduce((acc, curr) => acc + Number(curr.nombre_heures), 0);

        const totalRetards = assiduites.filter((a) => a.type_evenement === 'retard').length;

        const totalElevesDechus = syntheses.filter(
            (s) => Number(s.absences_non_justifiees) >= 15 || Number(s.exclusions_jours) > 0
        ).length;

        return {
            totalHeuresAbsence,
            totalAbsencesNJ,
            totalRetards,
            totalSanctions: sanctions.length,
            totalElevesDechus,
        };
    }, [assiduites, sanctions, syntheses]);

    return (
        <>
            <Head title="Discipline & Vie Scolaire - SYGES" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête de la page */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <div className="p-2 bg-slate-900 dark:bg-slate-100 rounded-lg text-white dark:text-slate-900 shrink-0">
                                <ShieldAlert className="w-5 h-5 text-rose-500" />
                            </div>
                            <div>
                                <h1 className="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100">
                                    Absences, Retards & Discipline
                                </h1>
                                <p className="text-xs text-slate-500">
                                    Établissement : <strong>{currentEtablissement?.nom}</strong> | Période :{' '}
                                    <strong>{currentPeriode?.nom ?? 'Période Active'}</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                        <Button
                            onClick={() => setIsAssiduiteModalOpen(true)}
                            className="bg-slate-900 hover:bg-slate-800 text-white flex items-center justify-center gap-1.5 shadow-sm text-xs h-9 w-full sm:w-auto"
                        >
                            <Plus className="w-4 h-4" />
                            Signaler Absence / Retard
                        </Button>
                        <Button
                            onClick={() => setIsSanctionModalOpen(true)}
                            className="bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center gap-1.5 shadow-sm text-xs h-9 w-full sm:w-auto"
                        >
                            <AlertOctagon className="w-4 h-4" />
                            Prononcer une Sanction
                        </Button>
                    </div>
                </div>

                {/* Cartes KPIs Discipline */}
                <div className="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-5 gap-3">
                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Total Absences</span>
                                <UserX className="w-4 h-4 text-slate-500" />
                            </div>
                            <div className="text-lg font-extrabold text-slate-900 dark:text-slate-100 mt-1">
                                {kpis.totalHeuresAbsence}h
                            </div>
                            <span className="text-[10px] text-slate-400">Toutes causes confondues</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800 bg-rose-50/30 dark:bg-rose-950/20">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-rose-600">Non Justifiées</span>
                                <XCircle className="w-4 h-4 text-rose-600" />
                            </div>
                            <div className="text-lg font-extrabold text-rose-700 dark:text-rose-400 mt-1">
                                {kpis.totalAbsencesNJ}h
                            </div>
                            <span className="text-[10px] text-rose-500">Impactent le bulletin</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Total Retards</span>
                                <Clock className="w-4 h-4 text-amber-600" />
                            </div>
                            <div className="text-lg font-extrabold text-amber-700 dark:text-amber-400 mt-1">
                                {kpis.totalRetards}
                            </div>
                            <span className="text-[10px] text-slate-400">Événements horodatés</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Sanctions Actives</span>
                                <AlertOctagon className="w-4 h-4 text-rose-600" />
                            </div>
                            <div className="text-lg font-extrabold text-slate-900 dark:text-slate-100 mt-1">
                                {kpis.totalSanctions}
                            </div>
                            <span className="text-[10px] text-slate-400">Décisions notifiées</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800 bg-amber-50/30 dark:bg-amber-950/20 min-[420px]:col-span-2 lg:col-span-1">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-amber-700">Déchéance Honneur</span>
                                <Award className="w-4 h-4 text-amber-700" />
                            </div>
                            <div className="text-lg font-extrabold text-amber-800 dark:text-amber-400 mt-1">
                                {kpis.totalElevesDechus} élève(s)
                            </div>
                            <span className="text-[10px] text-amber-600">≥ 15h NJ ou exclusion</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Onglets et Filtres */}
                <div className="space-y-4">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-2">
                        {/* Onglets avec scroll horizontal fluide */}
                        <div className="flex items-center gap-2 overflow-x-auto pb-1 shrink-0">
                            <button
                                onClick={() => setActiveTab('assiduites')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 whitespace-nowrap shrink-0 ${
                                    activeTab === 'assiduites'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <UserX className="w-3.5 h-3.5" />
                                Feuille d'Assiduité ({filteredAssiduites.length})
                            </button>
                            <button
                                onClick={() => setActiveTab('sanctions')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 whitespace-nowrap shrink-0 ${
                                    activeTab === 'sanctions'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <AlertOctagon className="w-3.5 h-3.5" />
                                Registre des Sanctions ({filteredSanctions.length})
                            </button>
                            <button
                                onClick={() => setActiveTab('synthese')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 whitespace-nowrap shrink-0 ${
                                    activeTab === 'synthese'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <FileText className="w-3.5 h-3.5" />
                                Synthèse Bulletins & Déchéances ({filteredSyntheses.length})
                            </button>
                        </div>

                        {/* Filtre de Période (Trimestre) */}
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-slate-500 font-medium whitespace-nowrap">Trimestre :</span>
                            <Select
                                value={String(currentPeriode?.id ?? '')}
                                onValueChange={(val) => {
                                    router.get(
                                        '/administration/discipline',
                                        { ...filters, periode_id: val },
                                        { preserveState: true }
                                    );
                                }}
                            >
                                <SelectTrigger className="h-8 text-xs w-full sm:w-44">
                                    <SelectValue placeholder="Sélectionner..." />
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
                    </div>

                    {/* Barre d'outils de filtres communs */}
                    <div className="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3">
                        <div className="relative flex-1 min-w-50">
                            <Search className="w-4 h-4 absolute left-2.5 top-2.5 text-slate-400" />
                            <Input
                                placeholder="Rechercher par élève ou matricule..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="h-9 pl-9 text-xs"
                            />
                        </div>

                        <div className="w-full sm:w-44">
                            <Select value={selectedClasse} onValueChange={setSelectedClasse}>
                                <SelectTrigger className="h-9 text-xs">
                                    <SelectValue placeholder="Toutes les classes" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Toutes les classes</SelectItem>
                                    {classes.map((cl) => (
                                        <SelectItem key={cl.id} value={String(cl.id)}>
                                            {cl.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {activeTab === 'assiduites' && (
                            <>
                                <div className="w-full sm:w-36">
                                    <Select value={selectedType} onValueChange={setSelectedType}>
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous types</SelectItem>
                                            <SelectItem value="absence">Absences</SelectItem>
                                            <SelectItem value="retard">Retards</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="w-full sm:w-40">
                                    <Select value={selectedJustif} onValueChange={setSelectedJustif}>
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Toutes justifications</SelectItem>
                                            <SelectItem value="justifiee">Justifiées</SelectItem>
                                            <SelectItem value="non_justifiee">Non justifiées</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </>
                        )}
                    </div>

                    {/* Affichage des Tableaux via TanStack Table */}
                    {activeTab === 'assiduites' && (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-2 sm:p-4 overflow-x-auto">
                            <DataTable
                                columns={assiduiteColumns}
                                data={filteredAssiduites}
                            />
                        </div>
                    )}

                    {activeTab === 'sanctions' && (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-2 sm:p-4 overflow-x-auto">
                            <DataTable
                                columns={sanctionsColumns}
                                data={filteredSanctions}
                            />
                        </div>
                    )}

                    {activeTab === 'synthese' && (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-2 sm:p-4 overflow-x-auto">
                            <div className="mb-3 text-xs text-slate-500 flex items-center gap-1.5">
                                <Award className="w-4 h-4 text-amber-600 shrink-0" />
                                <span>
                                    Conformément à la règle 12.4 de Gesco V1.1 : tout élève ayant accumulé <strong>≥ 15 heures d'absences non justifiées</strong> ou ayant fait l'objet d'une exclusion est automatiquement déchu de son éligibilité au Tableau d'Honneur et aux mentions honorifiques sur son bulletin de notes.
                                </span>
                            </div>
                            <DataTable
                                columns={syntheseColumns}
                                data={filteredSyntheses}
                            />
                        </div>
                    )}
                </div>
            </div>

            {/* Modales */}
            <NouvelleAssiduiteModal
                open={isAssiduiteModalOpen}
                onOpenChange={setIsAssiduiteModalOpen}
                inscriptions={inscriptions}
                matieres={matieres}
                periodeId={currentPeriode?.id ?? 0}
            />

            <JustifierModal
                open={Boolean(assiduiteToJustify)}
                onOpenChange={(open) => !open && setAssiduiteToJustify(null)}
                assiduite={assiduiteToJustify}
            />

            <NouvelleSanctionModal
                open={isSanctionModalOpen}
                onOpenChange={setIsSanctionModalOpen}
                inscriptions={inscriptions}
                periodeId={currentPeriode?.id ?? 0}
            />

            {/* Dialogue de confirmation de suppression unifié */}
            <ConfirmDialog />
        </>
    );
}

/**
 * Fil d'Ariane pour la page Discipline.
 */
DisciplineIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Absences & Discipline',
            href: '/administration/discipline',
        },
    ],
};

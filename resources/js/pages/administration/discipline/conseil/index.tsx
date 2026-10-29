import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    type ColumnDef,
    type SortingState,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    flexRender,
} from '@tanstack/react-table';
import {
    Gavel,
    Clock,
    FileText,
    Users,
    AlertOctagon,
    Plus,
    Printer,
    Edit2,
    Trash2,
    Search,
    RotateCw,
    School,
    CheckCircle2,
    XCircle,
    Calendar,
    ChevronDown,
    ChevronUp,
    ArrowLeft,
    Check,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    UserPlus,
    Mail,
    Send,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toast } from 'sonner';
import { formatDate, formatDateForInput } from '@/lib/utils';
import type {
    ConseilDisciplineSessionItem,
    DossierConseilDisciplineItem,
    HeureConsigneItem,
    ConseilDisciplineStats,
    DecisionConseil,
    StatutSession,
    StatutConsigne,
} from '@/types/conseil-discipline';
import type { Inscription, EtablissementRef, AnneeScolaireRef } from '@/types/eleve';

/**
 * Propriétés passées par le contrôleur ConseilDisciplineController.
 */
interface ConseilDisciplinePageProps {
    sessions: ConseilDisciplineSessionItem[];
    consignes: HeureConsigneItem[];
    stats: ConseilDisciplineStats;
    inscriptions: Inscription[];
    etablissements: EtablissementRef[];
    selectedEtabId: number;
    selectedAnneeId: number;
    anneesScolaires: AnneeScolaireRef[];
    filters: {
        etablissement_id?: string;
        annee_scolaire_id?: string;
        statut?: string;
        search?: string;
    };
}

/**
 * Page principale de gestion du Conseil de Discipline et des Heures de Consigne (Phase 5).
 *
 * Fonctionnalités :
 * - Organisation et tenue des sessions officielles de Conseil de Discipline.
 * - Émission des lettres de convocation officielles avec coupon-réponse d'accusé de réception.
 * - Saisie des délibérations, plaidoiries et sanctions majeures (exclusions, blâmes, avertissements).
 * - Édition certifiée du Procès-Verbal officiel de CD (PDF DomPDF).
 * - Registre d'assiduité des heures de retenue et corvées du samedi.
 */
export default function ConseilDisciplineIndex({
    sessions = [],
    consignes = [],
    stats,
    inscriptions = [],
    etablissements = [],
    selectedEtabId,
    selectedAnneeId,
    anneesScolaires = [],
    filters = {},
}: ConseilDisciplinePageProps) {
    const [selectedEtab, setSelectedEtab] = useState<number>(selectedEtabId);
    const [selectedAnnee, setSelectedAnnee] = useState<number>(selectedAnneeId);
    const [selectedStatut, setSelectedStatut] = useState<string>(filters.statut || '');
    const [searchTerm, setSearchTerm] = useState<string>(filters.search || '');

    // Récupération de l'établissement actif pour préremplir les sessions
    const currentEtab = useMemo(() => etablissements.find((e) => e.id === selectedEtab), [etablissements, selectedEtab]);

    // États des dialogues
    const [isSessionModalOpen, setIsSessionModalOpen] = useState<boolean>(false);
    const [isDossierModalOpen, setIsDossierModalOpen] = useState<boolean>(false);
    const [isConsigneModalOpen, setIsConsigneModalOpen] = useState<boolean>(false);
    const [isEditDossierOpen, setIsEditDossierOpen] = useState<boolean>(false);

    const [activeSession, setActiveSession] = useState<ConseilDisciplineSessionItem | null>(null);
    const [editingDossier, setEditingDossier] = useState<DossierConseilDisciplineItem | null>(null);
    const [expandedSessionIds, setExpandedSessionIds] = useState<number[]>([]);

    // Formulaire de session
    const [sessionFormData, setSessionFormData] = useState({
        numero_session: '',
        date_conseil: '',
        lieu: 'Salle des Actes / Bureau du Chef d’Établissement',
        president_seance: '',
        rapporteur: '',
        membres_presents: '',
        observations_generales: '',
        statut: 'planifie' as StatutSession,
    });

    // Formulaire de dossier
    const [dossierFormData, setDossierFormData] = useState({
        inscription_id: '',
        motifs_renvoi: '',
        plaidoirie_eleve: '',
        decision_finale: 'en_attente' as DecisionConseil,
        nombre_jours_exclusion: 3,
        nombre_heures_consigne: 4,
        date_convocation: '',
        parent_present: false,
        is_convocation_envoyee: true,
    });

    // Formulaire d'heure de consigne
    const [consigneFormData, setConsigneFormData] = useState({
        inscription_id: '',
        date_consigne: '',
        heure_debut: '08:00',
        heure_fin: '12:00',
        nombre_heures: 4,
        motif: '',
        travail_assigne: 'Devoirs de mathématiques sous surveillance et remise en état de la cour',
        surveillant_responsable: 'Surveillant Général',
        statut: 'programmee' as StatutConsigne,
    });

    /**
     * Bascule l'expansion d'une session pour visualiser ses dossiers d'élèves.
     */
    const toggleExpand = (sessionId: number) => {
        setExpandedSessionIds((prev) =>
            prev.includes(sessionId) ? prev.filter((id) => id !== sessionId) : [...prev, sessionId]
        );
    };

    /**
     * Enregistre une nouvelle session de CD.
     */
    const handleCreateSession = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/administration/discipline/conseil/sessions',
            {
                etablissement_id: selectedEtab,
                annee_scolaire_id: selectedAnnee,
                ...sessionFormData,
            },
            {
                onSuccess: () => {
                    setIsSessionModalOpen(false);
                    setSessionFormData({
                        numero_session: '',
                        date_conseil: '',
                        lieu: 'Salle des Actes / Bureau du Chef d’Établissement',
                        president_seance: '',
                        rapporteur: '',
                        membres_presents: '',
                        observations_generales: '',
                        statut: 'planifie',
                    });
                    toast.success('Session de Conseil de Discipline créée.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur de création.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Enregistre un dossier d'élève dans la session sélectionnée.
     */
    const handleCreateDossier = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeSession) return;

        router.post(
            `/administration/discipline/conseil/sessions/${activeSession.id}/dossiers`,
            {
                inscription_id: dossierFormData.inscription_id,
                motifs_renvoi: dossierFormData.motifs_renvoi,
                plaidoirie_eleve: dossierFormData.plaidoirie_eleve || null,
                decision_finale: dossierFormData.decision_finale,
                nombre_jours_exclusion:
                    dossierFormData.decision_finale === 'exclusion_temporaire'
                        ? dossierFormData.nombre_jours_exclusion
                        : null,
                nombre_heures_consigne:
                    dossierFormData.decision_finale === 'consigne'
                        ? dossierFormData.nombre_heures_consigne
                        : null,
                date_convocation: dossierFormData.date_convocation || null,
                parent_present: dossierFormData.parent_present,
                is_convocation_envoyee: dossierFormData.is_convocation_envoyee,
            },
            {
                onSuccess: () => {
                    setIsDossierModalOpen(false);
                    setDossierFormData({
                        inscription_id: '',
                        motifs_renvoi: '',
                        plaidoirie_eleve: '',
                        decision_finale: 'en_attente',
                        nombre_jours_exclusion: 3,
                        nombre_heures_consigne: 4,
                        date_convocation: '',
                        parent_present: false,
                        is_convocation_envoyee: true,
                    });
                    toast.success('Élève traduit et dossier ajouté au Conseil de Discipline.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur lors de l’ajout du dossier.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Met à jour la délibération d'un dossier d'élève.
     */
    const handleUpdateDossier = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingDossier) return;

        router.put(
            `/administration/discipline/conseil/dossiers/${editingDossier.id}`,
            {
                motifs_renvoi: dossierFormData.motifs_renvoi,
                plaidoirie_eleve: dossierFormData.plaidoirie_eleve || null,
                decision_finale: dossierFormData.decision_finale,
                nombre_jours_exclusion:
                    dossierFormData.decision_finale === 'exclusion_temporaire'
                        ? dossierFormData.nombre_jours_exclusion
                        : null,
                nombre_heures_consigne:
                    dossierFormData.decision_finale === 'consigne'
                        ? dossierFormData.nombre_heures_consigne
                        : null,
                date_convocation: dossierFormData.date_convocation || null,
                parent_present: dossierFormData.parent_present,
                is_convocation_envoyee: dossierFormData.is_convocation_envoyee,
            },
            {
                onSuccess: () => {
                    setIsEditDossierOpen(false);
                    setEditingDossier(null);
                    toast.success('Délibération du dossier mise à jour.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur lors de la mise à jour.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Supprime un dossier d'élève.
     */
    const handleDeleteDossier = (dossier: DossierConseilDisciplineItem) => {
        if (!confirm(`Retirer le dossier de l'élève ${dossier.eleve.nom} ${dossier.eleve.prenom} de la session ?`)) return;

        router.delete(`/administration/discipline/conseil/dossiers/${dossier.id}`, {
            onSuccess: () => {
                toast.success('Dossier élève retiré du conseil.', {
                    position: 'top-right',
                });
            },
        });
    };

    /**
     * Supprime une session entière.
     */
    const handleDeleteSession = (session: ConseilDisciplineSessionItem) => {
        if (!confirm(`Supprimer la session ${session.numero_session} et tous ses dossiers associés ?`)) return;

        router.delete(`/administration/discipline/conseil/sessions/${session.id}`, {
            onSuccess: () => {
                toast.success('Session de Conseil de Discipline supprimée.', {
                    position: 'top-right',
                });
            },
        });
    };

    /**
     * Enregistre une séance d'heure de consigne.
     */
    const handleCreateConsigne = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/administration/discipline/heures-consigne',
            {
                etablissement_id: selectedEtab,
                annee_scolaire_id: selectedAnnee,
                ...consigneFormData,
            },
            {
                onSuccess: () => {
                    setIsConsigneModalOpen(false);
                    setConsigneFormData({
                        inscription_id: '',
                        date_consigne: '',
                        heure_debut: '08:00',
                        heure_fin: '12:00',
                        nombre_heures: 4,
                        motif: '',
                        travail_assigne: 'Devoirs sous surveillance',
                        surveillant_responsable: 'Surveillant Général',
                        statut: 'programmee',
                    });
                    toast.success('Séance de consigne programmée avec succès.', {
                        position: 'top-right',
                    });
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || 'Erreur lors de la programmation de la consigne.', {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Met à jour le statut d'une heure de consigne.
     */
    const handleUpdateConsigneStatut = (consigne: HeureConsigneItem, newStatut: StatutConsigne) => {
        router.put(
            `/administration/discipline/heures-consigne/${consigne.id}`,
            {
                statut: newStatut,
            },
            {
                onSuccess: () => {
                    toast.success(`Consigne marquée comme ${newStatut}.`, {
                        position: 'top-right',
                    });
                },
            }
        );
    };

    /**
     * Supprime une consigne.
     */
    const handleDeleteConsigne = (consigne: HeureConsigneItem) => {
        if (!confirm(`Supprimer la consigne pour ${consigne.eleve.nom} ${consigne.eleve.prenom} ?`)) return;

        router.delete(`/administration/discipline/heures-consigne/${consigne.id}`, {
            onSuccess: () => {
                toast.success('Séance de consigne supprimée.', {
                    position: 'top-right',
                });
            },
        });
    };

    // Table TanStack des Sessions de CD
    const [sortingSessions, setSortingSessions] = useState<SortingState>([]);
    const sessionColumns: ColumnDef<ConseilDisciplineSessionItem>[] = useMemo(
        () => [
            {
                accessorKey: 'numero_session',
                header: 'Réf. Session',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-6 text-muted-foreground"
                            onClick={() => toggleExpand(row.original.id)}
                        >
                            {expandedSessionIds.includes(row.original.id) ? (
                                <ChevronUp className="size-4" />
                            ) : (
                                <ChevronDown className="size-4" />
                            )}
                        </Button>
                        <div>
                            <span className="font-bold text-foreground text-sm font-mono">{row.original.numero_session}</span>
                            <div className="text-[11px] text-muted-foreground">{row.original.lieu}</div>
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'date_conseil',
                header: 'Date & Heure',
                cell: ({ row }) => (
                    <div className="text-xs">
                        <div className="font-semibold text-foreground">{formatDate(row.original.date_conseil)}</div>
                        <div className="text-muted-foreground text-[11px]">
                            {new Date(row.original.date_conseil).toLocaleTimeString('fr-FR', {
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'president_seance',
                header: 'Président / Rapporteur',
                cell: ({ row }) => (
                    <div className="text-xs">
                        <div className="font-medium text-foreground">{row.original.president_seance}</div>
                        <div className="text-muted-foreground text-[11px]">Rapporteur : {row.original.rapporteur}</div>
                    </div>
                ),
            },
            {
                id: 'dossiers_count',
                header: 'Dossiers Traduits',
                cell: ({ row }) => (
                    <Badge variant="outline" className="font-semibold border-indigo-200 bg-indigo-50/50 text-indigo-700">
                        {row.original.dossiers.length} élève(s)
                    </Badge>
                ),
            },
            {
                accessorKey: 'statut',
                header: 'Statut',
                cell: ({ row }) => {
                    const st = row.original.statut;
                    if (st === 'tenu') {
                        return <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">Tenu</Badge>;
                    }
                    if (st === 'en_cours') {
                        return <Badge className="bg-amber-100 text-amber-800 border-amber-300">En cours</Badge>;
                    }
                    if (st === 'planifie') {
                        return <Badge className="bg-sky-100 text-sky-800 border-sky-300">Planifié</Badge>;
                    }
                    return <Badge className="bg-gray-100 text-gray-700">Annulé</Badge>;
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                cell: ({ row }) => (
                    <div className="flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 px-2 gap-1 text-[11px] font-medium text-indigo-600 border-indigo-200 hover:bg-indigo-50"
                            onClick={() => {
                                setActiveSession(row.original);
                                setDossierFormData({
                                    inscription_id: inscriptions[0]?.id ? String(inscriptions[0].id) : '',
                                    motifs_renvoi: '',
                                    plaidoirie_eleve: '',
                                    decision_finale: 'en_attente',
                                    nombre_jours_exclusion: 3,
                                    nombre_heures_consigne: 4,
                                    date_convocation: formatDateForInput(new Date()),
                                    parent_present: false,
                                    is_convocation_envoyee: true,
                                });
                                setIsDossierModalOpen(true);
                            }}
                            title="Traduire un élève"
                        >
                            <UserPlus className="size-3.5" />
                            Traduire élève
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 px-2 gap-1 text-[11px] font-medium text-teal-700 border-teal-200 hover:bg-teal-50"
                            onClick={() => {
                                window.open(`/administration/discipline/conseil/sessions/${row.original.id}/pv-pdf`, '_blank');
                                toast.success('PV officiel de Conseil de Discipline généré.', { position: 'top-right' });
                            }}
                            title="Imprimer PV officiel de CD"
                        >
                            <Printer className="size-3.5" />
                            PV (PDF)
                        </Button>

                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                            onClick={() => handleDeleteSession(row.original)}
                            title="Supprimer la session"
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    </div>
                ),
            },
        ],
        [expandedSessionIds, inscriptions]
    );

    const sessionTable = useReactTable({
        data: sessions,
        columns: sessionColumns,
        state: { sorting: sortingSessions },
        onSortingChange: setSortingSessions,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getRowId: (row) => String(row.id),
    });

    // Table TanStack des Heures de Consigne
    const [sortingConsignes, setSortingConsignes] = useState<SortingState>([]);
    const consigneColumns: ColumnDef<HeureConsigneItem>[] = useMemo(
        () => [
            {
                accessorKey: 'eleve.nom',
                header: 'Élève & Classe',
                cell: ({ row }) => (
                    <div>
                        <div className="font-semibold text-foreground text-sm">
                            {row.original.eleve.nom} {row.original.eleve.prenom}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.eleve.matricule} • {row.original.inscription.classe?.nom}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'date_consigne',
                header: 'Date & Horaires',
                cell: ({ row }) => (
                    <div className="text-xs">
                        <div className="font-medium text-foreground">{formatDate(row.original.date_consigne)}</div>
                        <div className="text-muted-foreground text-[11px]">
                            {row.original.heure_debut?.substring(0, 5)} - {row.original.heure_fin?.substring(0, 5)} ({row.original.nombre_heures}h)
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'motif',
                header: 'Motif & Travail Assigné',
                cell: ({ row }) => (
                    <div className="max-w-xs text-xs">
                        <p className="font-medium text-foreground line-clamp-1">{row.original.motif}</p>
                        {row.original.travail_assigne && (
                            <p className="text-[11px] text-muted-foreground line-clamp-1 mt-0.5">
                                Tâche : {row.original.travail_assigne}
                            </p>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'surveillant_responsable',
                header: 'Surveillant',
                cell: ({ row }) => (
                    <span className="text-xs text-muted-foreground">{row.original.surveillant_responsable || '-'}</span>
                ),
            },
            {
                accessorKey: 'statut',
                header: 'Statut',
                cell: ({ row }) => {
                    const st = row.original.statut;
                    if (st === 'effectuee') {
                        return <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">Effectuée</Badge>;
                    }
                    if (st === 'programmee') {
                        return <Badge className="bg-amber-100 text-amber-800 border-amber-300">Programmée</Badge>;
                    }
                    if (st === 'non_effectuee') {
                        return <Badge className="bg-rose-100 text-rose-800 border-rose-300">Absent / Non effectuée</Badge>;
                    }
                    return <Badge className="bg-gray-100 text-gray-700">Reportée</Badge>;
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                cell: ({ row }) => (
                    <div className="flex items-center gap-1">
                        {row.original.statut === 'programmee' && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50"
                                onClick={() => handleUpdateConsigneStatut(row.original, 'effectuee')}
                                title="Marquer comme effectuée"
                            >
                                <Check className="size-3.5" />
                            </Button>
                        )}
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                            onClick={() => handleDeleteConsigne(row.original)}
                            title="Supprimer la consigne"
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    </div>
                ),
            },
        ],
        []
    );

    const consigneTable = useReactTable({
        data: consignes,
        columns: consigneColumns,
        state: { sorting: sortingConsignes },
        onSortingChange: setSortingConsignes,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getRowId: (row) => String(row.id),
    });

    return (
        <>
            <Head title="Conseil de Discipline & Heures de Consigne" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête & navigation */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Button variant="ghost" size="sm" asChild className="h-8 px-2 gap-1.5 text-xs text-muted-foreground hover:text-foreground">
                                <Link href="/administration/discipline">
                                    <ArrowLeft className="size-3.5" />
                                    Retour à la Vie Scolaire & Assiduité
                                </Link>
                            </Button>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <div className="p-2.5 rounded-xl bg-linear-to-br from-rose-600 to-red-800 text-white shadow-md shadow-rose-500/20 shrink-0">
                                <Gavel className="size-6" />
                            </div>
                            <div>
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                                    Conseil de Discipline & Consignes
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    Convocations officielles, procès-verbaux de délibération, exclusions et heures de retenue du samedi.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Actions d'ajout responsive */}
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                        <Button
                            variant="outline"
                            onClick={() => {
                                setConsigneFormData({
                                    inscription_id: inscriptions[0]?.id ? String(inscriptions[0].id) : '',
                                    date_consigne: formatDateForInput(new Date()),
                                    heure_debut: '08:00',
                                    heure_fin: '12:00',
                                    nombre_heures: 4,
                                    motif: '',
                                    travail_assigne: 'Devoirs sous surveillance',
                                    surveillant_responsable: 'Surveillant Général',
                                    statut: 'programmee',
                                });
                                setIsConsigneModalOpen(true);
                            }}
                            className="gap-2 h-9 text-xs font-semibold border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300 w-full sm:w-auto"
                        >
                            <Clock className="size-3.5" />
                            Programmer Consigne
                        </Button>
                        <Button
                            onClick={() => {
                                const seqNum = sessions.length + 1;
                                setSessionFormData({
                                    numero_session: `CD-2026/S${seqNum}`,
                                    date_conseil: formatDateForInput(new Date()) + 'T09:00',
                                    lieu: 'Salle des Actes / Bureau du Chef d’Établissement',
                                    president_seance: currentEtab?.nom_chef_etablissement || 'Le Chef d’Établissement',
                                    rapporteur: 'Le Censeur / Surveillant Général',
                                    membres_presents: 'Proviseur, Censeur, SG, Représentant Enseignants, Président APEE',
                                    observations_generales: '',
                                    statut: 'planifie',
                                });
                                setIsSessionModalOpen(true);
                            }}
                            className="gap-2 h-9 text-xs font-semibold bg-rose-600 hover:bg-rose-700 text-white shadow-sm w-full sm:w-auto"
                        >
                            <Plus className="size-4" />
                            Nouvelle Session CD
                        </Button>
                    </div>
                </div>

                {/* Cartes KPI responsive */}
                <div className="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Sessions de Conseil</p>
                                <h3 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">{stats.total_sessions}</h3>
                            </div>
                            <div className="p-2.5 rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                <Gavel className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Élèves Traduits</p>
                                <h3 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">{stats.total_dossiers}</h3>
                            </div>
                            <div className="p-2.5 rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                <Users className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Exclusions Prononcées</p>
                                <h3 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">{stats.total_exclusions}</h3>
                            </div>
                            <div className="p-2.5 rounded-lg bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                                <AlertOctagon className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Heures de Consigne Cumulées</p>
                                <h3 className="text-2xl font-bold tracking-tight text-foreground mt-0.5">{stats.total_heures_consigne} h</h3>
                            </div>
                            <div className="p-2.5 rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                <Clock className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Onglets : Sessions vs Consignes */}
                <Tabs defaultValue="sessions" className="w-full">
                    <TabsList className="grid w-full sm:w-96 grid-cols-2">
                        <TabsTrigger value="sessions" className="text-xs font-medium">
                            <Gavel className="size-3.5 mr-1.5" />
                            Conseil de Discipline ({sessions.length})
                        </TabsTrigger>
                        <TabsTrigger value="consignes" className="text-xs font-medium">
                            <Clock className="size-3.5 mr-1.5" />
                            Heures de Consigne ({consignes.length})
                        </TabsTrigger>
                    </TabsList>

                    {/* ONGLET 1 : SESSIONS DE CONSEIL DE DISCIPLINE */}
                    <TabsContent value="sessions" className="mt-4 space-y-4">
                        <Card className="border-border/60 shadow-sm overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse text-sm">
                                    <thead>
                                        {sessionTable.getHeaderGroups().map((headerGroup) => (
                                            <tr key={headerGroup.id} className="border-b bg-muted/40 text-muted-foreground text-xs font-semibold">
                                                {headerGroup.headers.map((header) => (
                                                    <th key={header.id} className="p-3.5">
                                                        {header.isPlaceholder
                                                            ? null
                                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                                    </th>
                                                ))}
                                            </tr>
                                        ))}
                                    </thead>
                                    <tbody className="divide-y divide-border/50">
                                        {sessionTable.getRowModel().rows.length > 0 ? (
                                            sessionTable.getRowModel().rows.map((row) => (
                                                <React.Fragment key={row.id}>
                                                    <tr className="hover:bg-muted/30 transition-colors">
                                                        {row.getVisibleCells().map((cell) => (
                                                            <td key={cell.id} className="p-3.5">
                                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                            </td>
                                                        ))}
                                                    </tr>

                                                    {/* Ligne dépliée des dossiers d'élèves de cette session */}
                                                    {expandedSessionIds.includes(row.original.id) && (
                                                        <tr className="bg-muted/15 border-y border-indigo-100 dark:border-indigo-950">
                                                            <td colSpan={sessionColumns.length} className="p-4">
                                                                <div className="space-y-3">
                                                                    <div className="flex items-center justify-between">
                                                                        <h4 className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                                                            <Users className="size-3.5 text-indigo-600" />
                                                                            Dossiers des élèves traduits pour {row.original.numero_session} ({row.original.dossiers.length})
                                                                        </h4>
                                                                    </div>

                                                                    {row.original.dossiers.length === 0 ? (
                                                                        <p className="text-xs text-muted-foreground italic py-2">
                                                                            Aucun dossier d'élève rattaché à cette session. Cliquez sur « Traduire élève » ci-dessus pour ajouter une comparution.
                                                                        </p>
                                                                    ) : (
                                                                        <div className="grid grid-cols-1 gap-2.5">
                                                                            {row.original.dossiers.map((dossier) => (
                                                                                <div
                                                                                    key={dossier.id}
                                                                                    className="p-3 rounded-lg border bg-background shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs"
                                                                                >
                                                                                    <div>
                                                                                        <div className="flex items-center gap-2">
                                                                                            <span className="font-semibold text-foreground text-sm">
                                                                                                {dossier.eleve.nom} {dossier.eleve.prenom}
                                                                                            </span>
                                                                                            <span className="font-mono text-xs text-muted-foreground">
                                                                                                ({dossier.eleve.matricule} - {dossier.inscription?.classe?.nom})
                                                                                            </span>
                                                                                            {dossier.parent_present && (
                                                                                                <Badge variant="outline" className="text-[10px] h-4 border-emerald-300 text-emerald-700 bg-emerald-50">
                                                                                                    Parent présent
                                                                                                </Badge>
                                                                                            )}
                                                                                        </div>
                                                                                        <p className="text-foreground text-xs mt-1">
                                                                                            <strong className="text-rose-600">Motif :</strong> {dossier.motifs_renvoi}
                                                                                        </p>
                                                                                        {dossier.plaidoirie_eleve && (
                                                                                            <p className="text-muted-foreground text-[11px] mt-0.5 italic">
                                                                                                Défense : {dossier.plaidoirie_eleve}
                                                                                            </p>
                                                                                        )}
                                                                                    </div>

                                                                                    <div className="flex items-center gap-2 shrink-0">
                                                                                        <div>
                                                                                            {dossier.decision_finale === 'exclusion_definitive' && (
                                                                                                <Badge className="bg-rose-600 text-white font-bold text-xs">Exclusion Définitive</Badge>
                                                                                            )}
                                                                                            {dossier.decision_finale === 'exclusion_temporaire' && (
                                                                                                <Badge className="bg-rose-100 text-rose-800 border-rose-300 text-xs">
                                                                                                    Exclusion {dossier.nombre_jours_exclusion}j
                                                                                                </Badge>
                                                                                            )}
                                                                                            {dossier.decision_finale === 'consigne' && (
                                                                                                <Badge className="bg-amber-100 text-amber-800 border-amber-300 text-xs">
                                                                                                    Consigne {dossier.nombre_heures_consigne}h
                                                                                                </Badge>
                                                                                            )}
                                                                                            {dossier.decision_finale === 'blame' && (
                                                                                                <Badge className="bg-amber-100 text-amber-800 border-amber-300 text-xs">Blâme</Badge>
                                                                                            )}
                                                                                            {dossier.decision_finale === 'avertissement' && (
                                                                                                <Badge className="bg-amber-100 text-amber-800 border-amber-300 text-xs">Avertissement</Badge>
                                                                                            )}
                                                                                            {dossier.decision_finale === 'acquittement' && (
                                                                                                <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 text-xs">Acquitté</Badge>
                                                                                            )}
                                                                                            {dossier.decision_finale === 'en_attente' && (
                                                                                                <Badge variant="outline" className="text-xs">En délibération</Badge>
                                                                                            )}
                                                                                        </div>

                                                                                        <Button
                                                                                            variant="outline"
                                                                                            size="sm"
                                                                                            className="h-7 px-2 gap-1 text-[11px] font-medium text-blue-700 border-blue-200 hover:bg-blue-50"
                                                                                            onClick={() => {
                                                                                                window.open(`/administration/discipline/conseil/dossiers/${dossier.id}/convocation-pdf`, '_blank');
                                                                                                toast.success('Lettre de convocation avec coupon générée en PDF.', { position: 'top-right' });
                                                                                            }}
                                                                                            title="Télécharger la convocation officielle"
                                                                                        >
                                                                                            <Mail className="size-3" />
                                                                                            Convocation
                                                                                        </Button>

                                                                                        <Button
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            className="size-7 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                                                            onClick={() => {
                                                                                                setEditingDossier(dossier);
                                                                                                setDossierFormData({
                                                                                                    inscription_id: String(dossier.inscription_id),
                                                                                                    motifs_renvoi: dossier.motifs_renvoi,
                                                                                                    plaidoirie_eleve: dossier.plaidoirie_eleve || '',
                                                                                                    decision_finale: dossier.decision_finale,
                                                                                                    nombre_jours_exclusion: dossier.nombre_jours_exclusion || 3,
                                                                                                    nombre_heures_consigne: dossier.nombre_heures_consigne || 4,
                                                                                                    date_convocation: formatDateForInput(dossier.date_convocation),
                                                                                                    parent_present: dossier.parent_present,
                                                                                                    is_convocation_envoyee: dossier.is_convocation_envoyee,
                                                                                                });
                                                                                                setIsEditDossierOpen(true);
                                                                                            }}
                                                                                            title="Modifier la délibération"
                                                                                        >
                                                                                            <Edit2 className="size-3.5" />
                                                                                        </Button>

                                                                                        <Button
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            className="size-7 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                                                                                            onClick={() => handleDeleteDossier(dossier)}
                                                                                            title="Supprimer ce dossier"
                                                                                        >
                                                                                            <Trash2 className="size-3.5" />
                                                                                        </Button>
                                                                                    </div>
                                                                                </div>
                                                                            ))}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    )}
                                                </React.Fragment>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={sessionColumns.length} className="text-center py-12 text-muted-foreground">
                                                    <div className="flex flex-col items-center justify-center gap-2">
                                                        <Gavel className="size-8 text-muted-foreground/40" />
                                                        <p className="text-sm font-medium">Aucune session de Conseil de Discipline enregistrée.</p>
                                                        <p className="text-xs">Cliquez sur « Nouvelle Session CD » pour planifier une séance officielle.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </TabsContent>

                    {/* ONGLET 2 : HEURES DE CONSIGNE (RETENUES DU SAMEDI) */}
                    <TabsContent value="consignes" className="mt-4 space-y-4">
                        <Card className="border-border/60 shadow-sm overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse text-sm">
                                    <thead>
                                        {consigneTable.getHeaderGroups().map((headerGroup) => (
                                            <tr key={headerGroup.id} className="border-b bg-muted/40 text-muted-foreground text-xs font-semibold">
                                                {headerGroup.headers.map((header) => (
                                                    <th key={header.id} className="p-3.5">
                                                        {header.isPlaceholder
                                                            ? null
                                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                                    </th>
                                                ))}
                                            </tr>
                                        ))}
                                    </thead>
                                    <tbody className="divide-y divide-border/50">
                                        {consigneTable.getRowModel().rows.length > 0 ? (
                                            consigneTable.getRowModel().rows.map((row) => (
                                                <tr key={row.id} className="hover:bg-muted/30 transition-colors">
                                                    {row.getVisibleCells().map((cell) => (
                                                        <td key={cell.id} className="p-3.5">
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={consigneColumns.length} className="text-center py-12 text-muted-foreground">
                                                    <div className="flex flex-col items-center justify-center gap-2">
                                                        <Clock className="size-8 text-muted-foreground/40" />
                                                        <p className="text-sm font-medium">Aucune heure de consigne enregistrée.</p>
                                                        <p className="text-xs">Cliquez sur « Programmer Consigne » pour sanctionner une retenue du samedi.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* MODAL 1 : NOUVELLE SESSION DE CD */}
            <Dialog open={isSessionModalOpen} onOpenChange={setIsSessionModalOpen}>
                <DialogContent className="w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Gavel className="size-4 text-rose-600" />
                            Créer une Session de Conseil de Discipline
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCreateSession} className="space-y-3.5 py-2">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Numéro de session *</label>
                                <Input
                                    required
                                    placeholder="Ex: CD-2026/S1"
                                    value={sessionFormData.numero_session}
                                    onChange={(e) => setSessionFormData({ ...sessionFormData, numero_session: e.target.value })}
                                    className="h-9 text-xs font-mono"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Date & Heure *</label>
                                <Input
                                    type="datetime-local"
                                    required
                                    value={sessionFormData.date_conseil}
                                    onChange={(e) => setSessionFormData({ ...sessionFormData, date_conseil: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Lieu de la session *</label>
                            <Input
                                required
                                value={sessionFormData.lieu}
                                onChange={(e) => setSessionFormData({ ...sessionFormData, lieu: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Président de séance *</label>
                                <Input
                                    required
                                    placeholder="Nom du Proviseur / Principal"
                                    value={sessionFormData.president_seance}
                                    onChange={(e) => setSessionFormData({ ...sessionFormData, president_seance: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Rapporteur *</label>
                                <Input
                                    required
                                    placeholder="Nom du Censeur / Surveillant Général"
                                    value={sessionFormData.rapporteur}
                                    onChange={(e) => setSessionFormData({ ...sessionFormData, rapporteur: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">Membres présents (liste nominative)</label>
                            <textarea
                                rows={2}
                                placeholder="Proviseur, Censeur, Surveillant Général, Représentants Enseignants, Président APEE..."
                                value={sessionFormData.membres_presents}
                                onChange={(e) => setSessionFormData({ ...sessionFormData, membres_presents: e.target.value })}
                                className="w-full p-2.5 rounded-md border border-input bg-background text-xs"
                            />
                        </div>

                        <DialogFooter className="pt-2 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsSessionModalOpen(false)} className="w-full sm:w-auto">
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-rose-600 hover:bg-rose-700 text-white w-full sm:w-auto">
                                Créer la Session
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 2 : TRADUIRE UN ÉLÈVE EN CD */}
            <Dialog open={isDossierModalOpen} onOpenChange={setIsDossierModalOpen}>
                <DialogContent className="w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <UserPlus className="size-4 text-teal-600" />
                            <span>Traduire un Élève devant {activeSession?.numero_session}</span>
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCreateDossier} className="space-y-3.5 py-2">
                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Élève à traduire *</label>
                            <select
                                required
                                value={dossierFormData.inscription_id}
                                onChange={(e) => setDossierFormData({ ...dossierFormData, inscription_id: e.target.value })}
                                className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs font-medium"
                            >
                                <option value="">Sélectionner un élève...</option>
                                {inscriptions.map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.eleve.nom} {i.eleve.prenom} ({i.eleve.matricule} - {i.classe?.nom})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Faits et fautes graves reprochés *</label>
                            <textarea
                                required
                                rows={3}
                                placeholder="Détail des infractions disciplinaires constatées..."
                                value={dossierFormData.motifs_renvoi}
                                onChange={(e) => setDossierFormData({ ...dossierFormData, motifs_renvoi: e.target.value })}
                                className="w-full p-2.5 rounded-md border border-input bg-background text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Décision / Sanction *</label>
                                <select
                                    value={dossierFormData.decision_finale}
                                    onChange={(e) => setDossierFormData({ ...dossierFormData, decision_finale: e.target.value as DecisionConseil })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs font-medium"
                                >
                                    <option value="en_attente">En délibération (Attente)</option>
                                    <option value="acquittement">Acquittement</option>
                                    <option value="avertissement">Avertissement</option>
                                    <option value="blame">Blâme de conduite</option>
                                    <option value="consigne">Consigne / Retenue</option>
                                    <option value="exclusion_temporaire">Exclusion temporaire</option>
                                    <option value="exclusion_definitive">Exclusion définitive</option>
                                </select>
                            </div>

                            {dossierFormData.decision_finale === 'exclusion_temporaire' && (
                                <div>
                                    <label className="text-xs font-semibold text-foreground mb-1 block">Nombre de jours d'exclusion *</label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={30}
                                        value={dossierFormData.nombre_jours_exclusion}
                                        onChange={(e) => setDossierFormData({ ...dossierFormData, nombre_jours_exclusion: parseInt(e.target.value) || 1 })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            )}

                            {dossierFormData.decision_finale === 'consigne' && (
                                <div>
                                    <label className="text-xs font-semibold text-foreground mb-1 block">Heures de consigne *</label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={20}
                                        value={dossierFormData.nombre_heures_consigne}
                                        onChange={(e) => setDossierFormData({ ...dossierFormData, nombre_heures_consigne: parseInt(e.target.value) || 1 })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            )}
                        </div>

                        <div className="flex items-center gap-2 p-2 rounded-md bg-muted/40">
                            <Checkbox
                                id="parent_pres"
                                checked={dossierFormData.parent_present}
                                onCheckedChange={(c) => setDossierFormData({ ...dossierFormData, parent_present: !!c })}
                            />
                            <label htmlFor="parent_pres" className="text-xs text-foreground cursor-pointer font-medium">
                                Parent ou tuteur légal présent lors de la comparution
                            </label>
                        </div>

                        <DialogFooter className="pt-2 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsDossierModalOpen(false)} className="w-full sm:w-auto">
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-teal-600 hover:bg-teal-700 text-white w-full sm:w-auto">
                                Enregistrer le Dossier
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 3 : MODIFIER DÉLIBÉRATION */}
            <Dialog open={isEditDossierOpen} onOpenChange={setIsEditDossierOpen}>
                <DialogContent className="w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Edit2 className="size-4 text-blue-600" />
                            <span>Délibération du Dossier</span>
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleUpdateDossier} className="space-y-3.5 py-2">
                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Faits reprochés *</label>
                            <textarea
                                required
                                rows={2}
                                value={dossierFormData.motifs_renvoi}
                                onChange={(e) => setDossierFormData({ ...dossierFormData, motifs_renvoi: e.target.value })}
                                className="w-full p-2.5 rounded-md border border-input bg-background text-xs"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">Plaidoirie / Défense de l'élève</label>
                            <textarea
                                rows={2}
                                value={dossierFormData.plaidoirie_eleve}
                                onChange={(e) => setDossierFormData({ ...dossierFormData, plaidoirie_eleve: e.target.value })}
                                className="w-full p-2.5 rounded-md border border-input bg-background text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Sanction arrêtée *</label>
                                <select
                                    value={dossierFormData.decision_finale}
                                    onChange={(e) => setDossierFormData({ ...dossierFormData, decision_finale: e.target.value as DecisionConseil })}
                                    className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs font-medium"
                                >
                                    <option value="en_attente">En délibération</option>
                                    <option value="acquittement">Acquittement</option>
                                    <option value="avertissement">Avertissement</option>
                                    <option value="blame">Blâme de conduite</option>
                                    <option value="consigne">Consigne / Retenue</option>
                                    <option value="exclusion_temporaire">Exclusion temporaire</option>
                                    <option value="exclusion_definitive">Exclusion définitive</option>
                                </select>
                            </div>

                            {dossierFormData.decision_finale === 'exclusion_temporaire' && (
                                <div>
                                    <label className="text-xs font-semibold text-foreground mb-1 block">Jours d'exclusion *</label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={30}
                                        value={dossierFormData.nombre_jours_exclusion}
                                        onChange={(e) => setDossierFormData({ ...dossierFormData, nombre_jours_exclusion: parseInt(e.target.value) || 1 })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            )}

                            {dossierFormData.decision_finale === 'consigne' && (
                                <div>
                                    <label className="text-xs font-semibold text-foreground mb-1 block">Heures de consigne *</label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={20}
                                        value={dossierFormData.nombre_heures_consigne}
                                        onChange={(e) => setDossierFormData({ ...dossierFormData, nombre_heures_consigne: parseInt(e.target.value) || 1 })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            )}
                        </div>

                        <div className="flex items-center gap-2 p-2 rounded-md bg-muted/40">
                            <Checkbox
                                id="parent_pres_edit"
                                checked={dossierFormData.parent_present}
                                onCheckedChange={(c) => setDossierFormData({ ...dossierFormData, parent_present: !!c })}
                            />
                            <label htmlFor="parent_pres_edit" className="text-xs text-foreground cursor-pointer font-medium">
                                Parent présent lors de la délibération
                            </label>
                        </div>

                        <DialogFooter className="pt-2 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsEditDossierOpen(false)} className="w-full sm:w-auto">
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-teal-600 hover:bg-teal-700 text-white w-full sm:w-auto">
                                Sauvegarder la Décision
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL 4 : PROGRAMMER UNE CONSIGNE */}
            <Dialog open={isConsigneModalOpen} onOpenChange={setIsConsigneModalOpen}>
                <DialogContent className="w-[95vw] sm:max-w-md max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold flex items-center gap-2">
                            <Clock className="size-4 text-amber-600" />
                            <span>Programmer une Séance de Consigne (Retenue)</span>
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCreateConsigne} className="space-y-3.5 py-2">
                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Élève *</label>
                            <select
                                required
                                value={consigneFormData.inscription_id}
                                onChange={(e) => setConsigneFormData({ ...consigneFormData, inscription_id: e.target.value })}
                                className="w-full h-9 px-3 rounded-md border border-input bg-background text-xs font-medium"
                            >
                                <option value="">Sélectionner un élève...</option>
                                {inscriptions.map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.eleve.nom} {i.eleve.prenom} ({i.eleve.matricule} - {i.classe?.nom})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Date retenue *</label>
                                <Input
                                    type="date"
                                    required
                                    value={consigneFormData.date_consigne}
                                    onChange={(e) => setConsigneFormData({ ...consigneFormData, date_consigne: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-foreground mb-1 block">Volume horaire *</label>
                                <Input
                                    type="number"
                                    min={1}
                                    max={12}
                                    required
                                    value={consigneFormData.nombre_heures}
                                    onChange={(e) => setConsigneFormData({ ...consigneFormData, nombre_heures: parseInt(e.target.value) || 1 })}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1 block">Motif de la consigne *</label>
                            <Input
                                required
                                placeholder="Ex: Retards répétitifs, bavardages persistants..."
                                value={consigneFormData.motif}
                                onChange={(e) => setConsigneFormData({ ...consigneFormData, motif: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground mb-1 block">Travail ou corvée assignée</label>
                            <Input
                                value={consigneFormData.travail_assigne}
                                onChange={(e) => setConsigneFormData({ ...consigneFormData, travail_assigne: e.target.value })}
                                className="h-9 text-xs"
                            />
                        </div>

                        <DialogFooter className="pt-2 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsConsigneModalOpen(false)} className="w-full sm:w-auto">
                                Annuler
                            </Button>
                            <Button type="submit" size="sm" className="bg-amber-600 hover:bg-amber-700 text-white w-full sm:w-auto">
                                Programmer
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Configuration du layout et des fils d'Ariane.
 */
ConseilDisciplineIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Vie Scolaire & Discipline',
            href: '/administration/discipline',
        },
        {
            title: 'Conseil de Discipline & Consignes',
            href: '/administration/discipline/conseil',
        },
    ],
};

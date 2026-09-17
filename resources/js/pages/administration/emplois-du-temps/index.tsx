import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    CalendarDays,
    Clock,
    DoorOpen,
    Users,
    Plus,
    CalendarCheck,
    CalendarX,
    UserCheck,
    RotateCcw,
    Trash2,
    Search,
    AlertCircle,
    CheckCircle2,
    Layers,
    FileSpreadsheet,
    MapPin,
    ShieldAlert,
} from 'lucide-react';
import { toast } from 'sonner';
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
    DecompteRH,
    JourSemaine,
    Salle,
    SeanceEmploiTemps,
    StatutSeance,
    TypeSalle,
} from '@/types/emploi-du-temps';
import NouvelleSeanceModal from './modals/nouvelle-seance-modal';
import RemplacementModal from './modals/remplacement-modal';
import AnnulerSeanceModal from './modals/annuler-seance-modal';
import NouvelleSalleModal from './modals/nouvelle-salle-modal';

interface EmploiDuTempsIndexProps {
    seances: SeanceEmploiTemps[];
    classes: { id: number; nom: string }[];
    currentClasse: { id: number; nom: string } | null;
    salles: Salle[];
    currentSalle: Salle | null;
    enseignants: any[];
    currentEnseignant: any | null;
    attributions: any[];
    decomptesRH: Record<number, DecompteRH>;
    etablissements: { id: number; nom: string }[];
    currentEtablissement: { id: number; nom: string } | null;
    anneesScolaires: { id: number; code: string }[];
    currentAnnee: { id: number; code: string } | null;
    filters: Record<string, any>;
}

/**
 * Vue principale du Module 13 : Emplois du Temps & Gestion des Salles (Gesco V1.1).
 *
 * Implémente :
 * - Grille hebdomadaire avec moteur anti-conflits systématique (Classe, Salle, Enseignant).
 * - Gestion du référentiel des salles physiques et contrôle des capacités.
 * - Remplacements d'enseignants et annulations avec traçabilité.
 * - Décompte RH des heures effectives pour les enseignants titulaires et vacataires.
 *
 * @param {EmploiDuTempsIndexProps} props
 * @returns {JSX.Element}
 */
export default function EmploiDuTempsIndex({
    seances,
    classes,
    currentClasse,
    salles,
    currentSalle,
    enseignants,
    attributions,
    decomptesRH,
    currentEtablissement,
    currentAnnee,
    filters,
}: EmploiDuTempsIndexProps) {
    const [activeTab, setActiveTab] = useState<'grille_classe' | 'grille_salle' | 'salles' | 'decomptes_rh'>(
        (filters.tab as any) || 'grille_classe'
    );

    // Modales
    const [isSeanceModalOpen, setIsSeanceModalOpen] = useState(false);
    const [isSalleModalOpen, setIsSalleModalOpen] = useState(false);
    const [seanceToReplace, setSeanceToReplace] = useState<SeanceEmploiTemps | null>(null);
    const [seanceToCancel, setSeanceToCancel] = useState<SeanceEmploiTemps | null>(null);

    // Filtres
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedJour, setSelectedJour] = useState<string>('all');
    const [selectedClasseId, setSelectedClasseId] = useState<string>(
        filters.classe_id ? String(filters.classe_id) : classes[0]?.id ? String(classes[0].id) : 'all'
    );
    const [selectedSalleId, setSelectedSalleId] = useState<string>(
        filters.salle_id ? String(filters.salle_id) : 'all'
    );

    // Suppression d'une séance
    const handleDeleteSeance = (seance: SeanceEmploiTemps) => {
        if (!confirm('Êtes-vous certain de vouloir supprimer cette séance de l\'emploi du temps ?')) return;

        router.delete(`/administration/emplois-du-temps/seances/${seance.id}`, {
            onSuccess: () => {
                toast.success('Séance retirée de la grille hebdomadaire.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
            },
            onError: () => {
                toast.error('Erreur lors de la suppression de la séance.', { position: 'top-right' });
            },
        });
    };

    // Rétablissement d'une séance annulée ou remplacée
    const handleRetablirSeance = (seance: SeanceEmploiTemps) => {
        router.post(`/administration/emplois-du-temps/seances/${seance.id}/retablir`, {}, {
            onSuccess: () => {
                toast.success('Séance rétablie au statut actif.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
            },
            onError: () => {
                toast.error('Erreur lors du rétablissement de la séance.', { position: 'top-right' });
            },
        });
    };

    // Suppression d'une salle
    const handleDeleteSalle = (salle: Salle) => {
        if (!confirm(`Supprimer la salle "${salle.nom}" ? Cette action est irréversible.`)) return;

        router.delete(`/administration/emplois-du-temps/salles/${salle.id}`, {
            onSuccess: () => {
                toast.success('Salle retirée du répertoire.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
            },
            onError: () => {
                toast.error('Erreur lors de la suppression de la salle.', { position: 'top-right' });
            },
        });
    };

    // 1. Colonnes TanStack Table pour la Grille des Cours
    const seanceColumns = useMemo<ColumnDef<SeanceEmploiTemps>[]>(() => [
        {
            accessorKey: 'jour_semaine',
            header: 'Jour',
            cell: ({ row }) => {
                const colors: Record<JourSemaine, string> = {
                    lundi: 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    mardi: 'bg-sky-50 text-sky-700 border-sky-200',
                    mercredi: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    jeudi: 'bg-amber-50 text-amber-700 border-amber-200',
                    vendredi: 'bg-violet-50 text-violet-700 border-violet-200',
                    samedi: 'bg-rose-50 text-rose-700 border-rose-200',
                };
                const jour = row.original.jour_semaine;
                return (
                    <Badge variant="outline" className={`capitalize text-[10px] font-bold ${colors[jour] || ''}`}>
                        {jour}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'heure_debut',
            header: 'Créneau Horaire',
            cell: ({ row }) => (
                <div className="flex items-center gap-1.5 font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">
                    <Clock className="w-3.5 h-3.5 text-slate-400" />
                    <span>{row.original.heure_debut.substring(0, 5)} - {row.original.heure_fin.substring(0, 5)}</span>
                    <span className="text-[10px] text-slate-500 font-normal">({row.original.duree_heures ?? 1}h)</span>
                </div>
            ),
        },
        {
            id: 'matiere',
            header: 'Matière / Discipline',
            cell: ({ row }) => (
                <div>
                    <span className="font-bold text-xs text-slate-900 dark:text-slate-100">
                        {row.original.attribution?.matiere?.nom ?? 'Cours'}
                    </span>
                    <div className="text-[10px] text-slate-500">
                        Classe : <strong>{row.original.classe?.nom}</strong>
                    </div>
                </div>
            ),
        },
        {
            id: 'enseignant',
            header: 'Enseignant',
            cell: ({ row }) => {
                const titulaire = row.original.attribution?.personnel;
                const remplacant = row.original.remplacant;

                return (
                    <div>
                        <div className="text-xs font-medium text-slate-800 dark:text-slate-200">
                            Prof. {titulaire?.nom} {titulaire?.prenom}
                        </div>
                        {remplacant && (
                            <div className="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold flex items-center gap-1">
                                <UserCheck className="w-3 h-3" />
                                Remplacé par : Prof. {remplacant.nom} {remplacant.prenom}
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'salle',
            header: 'Salle / Local',
            cell: ({ row }) => {
                const s = row.original.salle;
                return s ? (
                    <Badge variant="outline" className="text-[10px] flex items-center gap-1 w-fit">
                        <MapPin className="w-3 h-3 text-slate-400" />
                        {s.nom} ({s.capacite_places} pl.)
                    </Badge>
                ) : (
                    <span className="text-xs text-slate-400 italic">Non assignée</span>
                );
            },
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: ({ row }) => {
                const st = row.original.statut;
                switch (st) {
                    case 'actif':
                        return (
                            <Badge className="bg-emerald-600 text-white text-[10px] flex items-center gap-1 w-fit">
                                <CheckCircle2 className="w-3 h-3" /> Actif
                            </Badge>
                        );
                    case 'remplace':
                        return (
                            <Badge className="bg-indigo-600 text-white text-[10px] flex items-center gap-1 w-fit">
                                <UserCheck className="w-3 h-3" /> Remplacé
                            </Badge>
                        );
                    case 'annule':
                        return (
                            <Badge variant="destructive" className="text-[10px] flex items-center gap-1 w-fit">
                                <CalendarX className="w-3 h-3" /> Annulé
                            </Badge>
                        );
                }
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => {
                const seance = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        {seance.statut === 'actif' && (
                            <>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="h-7 px-2 text-[10px] text-indigo-700 hover:bg-indigo-50"
                                    onClick={() => setSeanceToReplace(seance)}
                                    title="Affecter un remplaçant"
                                >
                                    <UserCheck className="w-3.5 h-3.5 mr-1" />
                                    Remplacer
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="h-7 px-2 text-[10px] text-rose-700 hover:bg-rose-50"
                                    onClick={() => setSeanceToCancel(seance)}
                                    title="Annuler le cours"
                                >
                                    <CalendarX className="w-3.5 h-3.5 mr-1" />
                                    Annuler
                                </Button>
                            </>
                        )}
                        {seance.statut !== 'actif' && (
                            <Button
                                size="sm"
                                variant="outline"
                                className="h-7 px-2 text-[10px] text-emerald-700 hover:bg-emerald-50"
                                onClick={() => handleRetablirSeance(seance)}
                                title="Rétablir le cours normal"
                            >
                                <RotateCcw className="w-3.5 h-3.5 mr-1" />
                                Rétablir
                            </Button>
                        )}
                        <Button
                            size="sm"
                            variant="ghost"
                            className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600"
                            onClick={() => handleDeleteSeance(seance)}
                            title="Supprimer la séance"
                        >
                            <Trash2 className="w-3.5 h-3.5" />
                        </Button>
                    </div>
                );
            },
        },
    ], []);

    // 2. Colonnes TanStack Table pour le Répertoire des Salles
    const sallesColumns = useMemo<ColumnDef<Salle>[]>(() => [
        {
            accessorKey: 'code',
            header: 'Code Local',
            cell: ({ row }) => (
                <span className="font-mono font-bold text-xs text-slate-800 dark:text-slate-200">
                    {row.original.code}
                </span>
            ),
        },
        {
            accessorKey: 'nom',
            header: 'Nom de la Salle',
            cell: ({ row }) => (
                <div>
                    <span className="font-bold text-xs text-slate-900 dark:text-slate-100">{row.original.nom}</span>
                    {row.original.description && (
                        <p className="text-[10px] text-slate-500 truncate max-w-50">{row.original.description}</p>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'type_salle',
            header: 'Type de Salle',
            cell: ({ row }) => {
                const map: Record<TypeSalle, string> = {
                    standard: 'Salle Standard',
                    laboratoire: 'Laboratoire',
                    informatique: 'Informatique',
                    sport: 'Sport / EPS',
                    polyvalente: 'Polyvalente',
                };
                return (
                    <Badge variant="outline" className="text-[10px] capitalize">
                        {map[row.original.type_salle] || row.original.type_salle}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'capacite_places',
            header: () => <div className="text-center">Capacité (Places)</div>,
            cell: ({ row }) => (
                <div className="text-center font-bold text-xs">
                    {row.original.capacite_places} places
                </div>
            ),
        },
        {
            accessorKey: 'statut',
            header: 'Statut',
            cell: ({ row }) => {
                const dispo = row.original.statut === 'disponible';
                return dispo ? (
                    <Badge className="bg-emerald-600 text-white text-[10px] flex items-center gap-1 w-fit">
                        <CheckCircle2 className="w-3 h-3" /> Disponible
                    </Badge>
                ) : (
                    <Badge variant="destructive" className="text-[10px] flex items-center gap-1 w-fit">
                        <AlertCircle className="w-3 h-3" /> Indisponible
                    </Badge>
                );
            },
        },
        {
            id: 'cours_count',
            header: () => <div className="text-center">Cours Planifiés</div>,
            cell: ({ row }) => (
                <div className="text-center text-xs font-semibold text-indigo-600">
                    {row.original.seances_emploi_temps_count ?? 0} séance(s)
                </div>
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
                        onClick={() => handleDeleteSalle(row.original)}
                        title="Supprimer la salle"
                    >
                        <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                </div>
            ),
        },
    ], []);

    // 3. Colonnes TanStack Table pour le Décompte RH des Enseignants
    const rhColumns = useMemo<ColumnDef<any>[]>(() => [
        {
            accessorKey: 'matricule',
            header: 'Matricule',
            cell: ({ row }) => (
                <span className="font-mono text-xs font-semibold text-slate-600">{row.original.matricule}</span>
            ),
        },
        {
            id: 'nom',
            header: 'Enseignant',
            cell: ({ row }) => (
                <div className="font-bold text-xs text-slate-900 dark:text-slate-100">
                    Prof. {row.original.nom} {row.original.prenom}
                </div>
            ),
        },
        {
            accessorKey: 'type_contrat',
            header: 'Statut RH',
            cell: ({ row }) => (
                <Badge variant="outline" className="text-[10px]">
                    {row.original.type_contrat ?? 'Permanent'}
                </Badge>
            ),
        },
        {
            id: 'heures_regulieres',
            header: () => <div className="text-center">Heures Titulaire</div>,
            cell: ({ row }) => {
                const dec = decomptesRH[row.original.id];
                return (
                    <div className="text-center text-xs font-semibold text-slate-700">
                        {dec?.heures_regulieres ?? 0}h / sem
                    </div>
                );
            },
        },
        {
            id: 'heures_remplacement',
            header: () => <div className="text-center">Remplacements (+)</div>,
            cell: ({ row }) => {
                const dec = decomptesRH[row.original.id];
                const h = dec?.heures_remplacement ?? 0;
                return (
                    <div className={`text-center text-xs font-semibold ${h > 0 ? 'text-indigo-600 font-bold' : 'text-slate-400'}`}>
                        +{h}h
                    </div>
                );
            },
        },
        {
            id: 'heures_annulees',
            header: () => <div className="text-center">Annulations (-)</div>,
            cell: ({ row }) => {
                const dec = decomptesRH[row.original.id];
                const h = dec?.heures_annulees ?? 0;
                return (
                    <div className={`text-center text-xs ${h > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'}`}>
                        -{h}h
                    </div>
                );
            },
        },
        {
            id: 'total_effectif',
            header: () => <div className="text-center">Total Réel Effectif</div>,
            cell: ({ row }) => {
                const dec = decomptesRH[row.original.id];
                const total = dec?.total_heures_effectives ?? 0;
                return (
                    <div className="text-center font-bold text-xs text-emerald-600">
                        {total}h effectives
                    </div>
                );
            },
        },
    ], [decomptesRH]);

    // Filtrage des séances de cours
    const filteredSeances = useMemo(() => {
        return seances.filter((s) => {
            if (activeTab === 'grille_classe') {
                if (selectedClasseId !== 'all' && String(s.classe_id) !== selectedClasseId) {
                    return false;
                }
            } else if (activeTab === 'grille_salle') {
                if (selectedSalleId !== 'all' && String(s.salle_id) !== selectedSalleId) {
                    return false;
                }
            }

            if (selectedJour !== 'all' && s.jour_semaine !== selectedJour) {
                return false;
            }

            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const mat = (s.attribution?.matiere?.nom || '').toLowerCase();
                const prof = (s.attribution?.personnel?.nom || '').toLowerCase();
                const classe = (s.classe?.nom || '').toLowerCase();
                const salle = (s.salle?.nom || '').toLowerCase();
                return mat.includes(q) || prof.includes(q) || classe.includes(q) || salle.includes(q);
            }
            return true;
        });
    }, [seances, activeTab, selectedClasseId, selectedSalleId, selectedJour, searchQuery]);

    // KPIs d'emploi du temps
    const kpis = useMemo(() => {
        const totalSeances = seances.length;
        const totalActives = seances.filter((s) => s.statut === 'actif').length;
        const totalRemplacements = seances.filter((s) => s.statut === 'remplace').length;
        const totalAnnulations = seances.filter((s) => s.statut === 'annule').length;
        const totalSalles = salles.length;

        return {
            totalSeances,
            totalActives,
            totalRemplacements,
            totalAnnulations,
            totalSalles,
        };
    }, [seances, salles]);

    return (
        <>
            <Head title="Emplois du Temps & Salles - SYGES" />

            <div className="space-y-6">
                {/* En-tête */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <div className="p-2 bg-indigo-600 rounded-lg text-white">
                                <CalendarDays className="w-5 h-5" />
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                    Emplois du Temps & Gestion des Salles
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
                            onClick={() => setIsSalleModalOpen(true)}
                            variant="outline"
                            className="text-xs h-9 flex items-center gap-1.5"
                        >
                            <DoorOpen className="w-4 h-4" />
                            Nouvelle Salle
                        </Button>
                        <Button
                            onClick={() => setIsSeanceModalOpen(true)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1.5 shadow-sm text-xs h-9"
                        >
                            <Plus className="w-4 h-4" />
                            Planifier un Cours
                        </Button>
                    </div>
                </div>

                {/* Cartes KPI */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Séances Hebdo</span>
                                <CalendarDays className="w-4 h-4 text-indigo-600" />
                            </div>
                            <div className="text-lg font-extrabold text-slate-900 dark:text-slate-100 mt-1">
                                {kpis.totalSeances}
                            </div>
                            <span className="text-[10px] text-slate-400">{kpis.totalActives} actives normales</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Locaux & Salles</span>
                                <DoorOpen className="w-4 h-4 text-slate-500" />
                            </div>
                            <div className="text-lg font-extrabold text-slate-900 dark:text-slate-100 mt-1">
                                {kpis.totalSalles}
                            </div>
                            <span className="text-[10px] text-slate-400">Standard, labo & info</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800 bg-indigo-50/40 dark:bg-indigo-950/20">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-indigo-600">Remplacements</span>
                                <UserCheck className="w-4 h-4 text-indigo-600" />
                            </div>
                            <div className="text-lg font-extrabold text-indigo-700 dark:text-indigo-400 mt-1">
                                {kpis.totalRemplacements}
                            </div>
                            <span className="text-[10px] text-indigo-500">Collègues mobilisés</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800 bg-rose-50/30 dark:bg-rose-950/20">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-rose-600">Annulations</span>
                                <CalendarX className="w-4 h-4 text-rose-600" />
                            </div>
                            <div className="text-lg font-extrabold text-rose-700 dark:text-rose-400 mt-1">
                                {kpis.totalAnnulations}
                            </div>
                            <span className="text-[10px] text-rose-500">Heures non dispensées</span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200 dark:border-slate-800">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-slate-500">Moteur Anti-Conflit</span>
                                <ShieldAlert className="w-4 h-4 text-emerald-600" />
                            </div>
                            <div className="text-base font-bold text-emerald-600 mt-1 flex items-center gap-1">
                                <CheckCircle2 className="w-4 h-4" /> Actif 100%
                            </div>
                            <span className="text-[10px] text-slate-400">Zéro collision garantie</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Onglets et Filtres */}
                <div className="space-y-4">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-2">
                        {/* Onglets */}
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => setActiveTab('grille_classe')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 ${
                                    activeTab === 'grille_classe'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <CalendarDays className="w-3.5 h-3.5" />
                                Emploi du Temps par Classe
                            </button>
                            <button
                                onClick={() => setActiveTab('grille_salle')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 ${
                                    activeTab === 'grille_salle'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <MapPin className="w-3.5 h-3.5" />
                                Occupation des Salles
                            </button>
                            <button
                                onClick={() => setActiveTab('salles')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 ${
                                    activeTab === 'salles'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <DoorOpen className="w-3.5 h-3.5" />
                                Répertoire des Salles ({salles.length})
                            </button>
                            <button
                                onClick={() => setActiveTab('decomptes_rh')}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5 ${
                                    activeTab === 'decomptes_rh'
                                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                                }`}
                            >
                                <Users className="w-3.5 h-3.5" />
                                Décompte RH Enseignants
                            </button>
                        </div>
                    </div>

                    {/* Barre de filtres contextuels */}
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-50">
                            <Search className="w-4 h-4 absolute left-2.5 top-2.5 text-slate-400" />
                            <Input
                                placeholder="Rechercher matière, enseignant ou salle..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="h-9 pl-9 text-xs"
                            />
                        </div>

                        {activeTab === 'grille_classe' && (
                            <div className="w-48">
                                <Select value={selectedClasseId} onValueChange={setSelectedClasseId}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder="Toutes les classes" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Toutes les classes</SelectItem>
                                        {classes.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {activeTab === 'grille_salle' && (
                            <div className="w-48">
                                <Select value={selectedSalleId} onValueChange={setSelectedSalleId}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder="Toutes les salles" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Toutes les salles</SelectItem>
                                        {salles.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.nom} ({s.code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {(activeTab === 'grille_classe' || activeTab === 'grille_salle') && (
                            <div className="w-36">
                                <Select value={selectedJour} onValueChange={setSelectedJour}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tous les jours</SelectItem>
                                        <SelectItem value="lundi">Lundi</SelectItem>
                                        <SelectItem value="mardi">Mardi</SelectItem>
                                        <SelectItem value="mercredi">Mercredi</SelectItem>
                                        <SelectItem value="jeudi">Jeudi</SelectItem>
                                        <SelectItem value="vendredi">Vendredi</SelectItem>
                                        <SelectItem value="samedi">Samedi</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>

                    {/* Rendu des Tables TanStack */}
                    {(activeTab === 'grille_classe' || activeTab === 'grille_salle') && (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-4">
                            <DataTable
                                columns={seanceColumns}
                                data={filteredSeances}
                            />
                        </div>
                    )}

                    {activeTab === 'salles' && (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-4">
                            <DataTable
                                columns={sallesColumns}
                                data={salles}
                            />
                        </div>
                    )}

                    {activeTab === 'decomptes_rh' && (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 p-4">
                            <div className="mb-3 text-xs text-slate-500 flex items-center gap-1.5">
                                <Users className="w-4 h-4 text-indigo-600" />
                                <span>
                                    Interconnexion RH (Gesco 13.5) : calcul automatique hebdomadaire des heures régulières, remplacements effectués (+) et cours annulés (-) pour la paie et la vacation des enseignants.
                                </span>
                            </div>
                            <DataTable
                                columns={rhColumns}
                                data={enseignants}
                            />
                        </div>
                    )}
                </div>
            </div>

            {/* Modales */}
            <NouvelleSeanceModal
                open={isSeanceModalOpen}
                onOpenChange={setIsSeanceModalOpen}
                anneeId={currentAnnee?.id ?? 0}
                classes={classes}
                salles={salles}
                attributions={attributions}
                selectedClasseId={selectedClasseId !== 'all' ? Number(selectedClasseId) : (classes[0]?.id ?? 0)}
            />

            <RemplacementModal
                open={Boolean(seanceToReplace)}
                onOpenChange={(open) => !open && setSeanceToReplace(null)}
                seance={seanceToReplace}
                enseignants={enseignants}
            />

            <AnnulerSeanceModal
                open={Boolean(seanceToCancel)}
                onOpenChange={(open) => !open && setSeanceToCancel(null)}
                seance={seanceToCancel}
            />

            <NouvelleSalleModal
                open={isSalleModalOpen}
                onOpenChange={setIsSalleModalOpen}
                etablissementId={currentEtablissement?.id ?? 0}
            />
        </>
    );
}

/**
 * Fil d'Ariane pour la page Emplois du Temps.
 */
EmploiDuTempsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Emplois du Temps & Salles',
            href: '/administration/emplois-du-temps',
        },
    ],
};

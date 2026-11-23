import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowLeftRight,
    ArrowRight,
    CheckCircle2,
    EyeOff,
    FileCheck2,
    GitMerge,
    History,
    Loader2,
    RotateCcw,
    Scale,
    Search,
    ShieldCheck,
    Users,
    Wallet,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { EleveAvatar } from '@/components/eleves/eleve-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { SearchableSelect, type SearchableOption } from '@/components/ui/searchable-select';
import { formatDate } from '@/lib/utils';

/**
 * Résumé d'un élève dans une paire de doublons suspects.
 */
interface EleveSuspectCard {
    eleve_id: number;
    inscription_id: number;
    matricule: string;
    nom: string;
    prenom?: string | null;
    nom_complet: string;
    sexe?: string | null;
    date_naissance?: string | null;
    lieu_naissance?: string | null;
    telephone_parent?: string | null;
    photo_url?: string | null;
    classe_id?: number | null;
    classe_nom: string;
    etablissement_nom: string;
    etablissement_code: string;
    statut: string;
    total_paye: number;
    solde_restant: number;
    nb_versements: number;
    nb_notes: number;
}

/**
 * Paire suspecte renvoyée par le scanner de doublons.
 */
interface DoublonPair {
    pair_key: string;
    score: number;
    niveau_confiance: 'eleve' | 'moyen';
    raisons: string[];
    master: EleveSuspectCard;
    duplicate: EleveSuspectCard;
}

/**
 * Option légère d'élève pour la sélection manuelle de fusion libre.
 */
interface EleveOptionItem {
    id: number;
    matricule: string;
    nom_complet: string;
    classe_nom: string;
    etablissement_code: string;
}

/**
 * Paire explicitement ignorée (jumeaux / homonymes).
 */
interface IgnoredPairItem {
    id: number;
    motif?: string | null;
    created_at?: string | null;
    ignored_by: string;
    eleve_min: { id: number; matricule: string; nom_complet: string };
    eleve_max: { id: number; matricule: string; nom_complet: string };
}

/**
 * Entrée du journal d'audit des fusions déjà exécutées.
 */
interface FusionAuditItem {
    id: number;
    master_eleve_id: number;
    master_matricule: string;
    master_nom_complet: string;
    absorbed_matricule: string;
    absorbed_nom_complet: string;
    etablissement_code: string;
    merged_by: string;
    montant_versements_transferes: number;
    nb_versements_transferes: number;
    nb_notes_transferees: number;
    nb_conflits_notes_resolus: number;
    created_at?: string | null;
}

/**
 * Profil complet retourné par l'endpoint `/administration/eleves/doublons/preview`.
 */
interface PreviewEleveData {
    id: number;
    matricule: string;
    matricule_national?: string | null;
    nom: string;
    prenom?: string | null;
    nom_complet: string;
    sexe?: string | null;
    date_naissance?: string | null;
    lieu_naissance?: string | null;
    nationalite?: string | null;
    photo_url?: string | null;
    nom_pere?: string | null;
    nom_mere?: string | null;
    nom_tuteur?: string | null;
    telephone_parent?: string | null;
    email_parent?: string | null;
    adresse?: string | null;
    classe_nom: string;
    etablissement_nom: string;
    nb_inscriptions: number;
    total_paye: number;
    solde_restant: number;
    nb_versements: number;
    options_actives: string[];
    nb_notes: number;
    nb_assiduites: number;
    nb_sanctions: number;
}

/**
 * Conflit de note entre le dossier Maître et le dossier Doublon sur une même matière et évaluation.
 */
interface NoteConflictItem {
    duplicate_note_id: number;
    master_note_id: number;
    annee_code: string;
    matiere_nom: string;
    matiere_code: string;
    periode_nom: string;
    evaluation_nom: string;
    master_note: {
        valeur_note: number | null;
        statut_presence: string;
        appreciation?: string | null;
        cote?: string | null;
    };
    duplicate_note: {
        valeur_note: number | null;
        statut_presence: string;
        appreciation?: string | null;
        cote?: string | null;
    };
}

/**
 * Réponse JSON de la prévisualisation de fusion.
 */
interface MergePreviewPayload {
    master: PreviewEleveData;
    duplicate: PreviewEleveData;
    suggested_identity: Record<string, 'master' | 'duplicate'>;
    conflits_notes: NoteConflictItem[];
    resume_transfert: {
        nb_versements: number;
        montant_versements: number;
        nb_notes_sans_conflit: number;
        nb_conflits_notes: number;
        nb_assiduites: number;
        nb_sanctions: number;
        nb_inscriptions_autres_annees: number;
    };
}

/**
 * Propriétés Inertia transmises par EleveDoublonController::index.
 */
interface ElevesDoublonsPageProps {
    suspects: DoublonPair[];
    elevesOptions: EleveOptionItem[];
    ignores: IgnoredPairItem[];
    audits: FusionAuditItem[];
    anneesScolaires: Array<{ id: number; code: string; nom: string; is_current: boolean }>;
    selectedAnneeId: number;
    etablissements: Array<{ id: number; code: string; nom: string }>;
    selectedEtablissementId: string;
    canAccessAll: boolean;
}

/**
 * Libellés lisibles pour chaque champ d'identité comparé dans la modale de fusion.
 */
const IDENTITY_FIELD_LABELS: Array<{ key: keyof PreviewEleveData; label: string; isDate?: boolean }> = [
    { key: 'nom', label: 'Nom de famille' },
    { key: 'prenom', label: 'Prénom(s)' },
    { key: 'matricule_national', label: 'Matricule national' },
    { key: 'sexe', label: 'Sexe' },
    { key: 'date_naissance', label: 'Date de naissance', isDate: true },
    { key: 'lieu_naissance', label: 'Lieu de naissance' },
    { key: 'nationalite', label: 'Nationalité' },
    { key: 'nom_pere', label: 'Nom du père' },
    { key: 'nom_mere', label: 'Nom de la mère' },
    { key: 'nom_tuteur', label: 'Tuteur légal' },
    { key: 'telephone_parent', label: 'Téléphone parent' },
    { key: 'email_parent', label: 'Email parent' },
    { key: 'adresse', label: 'Adresse / Quartier' },
];

/**
 * Page dédiée « Doublons & Fusion des Élèves » dans SYGES.
 *
 * Offre aux administrateurs habilités (`eleves.merge`) :
 * 1. La détection automatique des paires suspectes avec score de confiance et motifs.
 * 2. Une sélection manuelle libre permettant de fusionner n'importe quels 2 dossiers élèves.
 * 3. Un assistant de fusion côte à côte avec inversion Maître/Doublon, choix champ par champ
 *    et arbitrage explicite de chaque conflit de notes avant validation atomique.
 * 4. La gestion des paires légitimes (jumeaux / homonymes) et le journal d'audit complet.
 *
 * @param {ElevesDoublonsPageProps} props Propriétés Inertia
 * @returns {JSX.Element} Interface de gestion et fusion des doublons
 */
export default function ElevesDoublonsPage({
    suspects,
    elevesOptions,
    ignores,
    audits,
    anneesScolaires,
    selectedAnneeId,
    etablissements,
    selectedEtablissementId,
    canAccessAll,
}: ElevesDoublonsPageProps) {
    const [activeTab, setActiveTab] = useState<'suspects' | 'ignores' | 'audits'>('suspects');
    const [searchSuspect, setSearchSuspect] = useState('');

    // Sélection manuelle libre de deux dossiers à fusionner
    const [manualMasterId, setManualMasterId] = useState('');
    const [manualDuplicateId, setManualDuplicateId] = useState('');

    // Modale d'ignorance (jumeaux / homonymes)
    const [ignorePair, setIgnorePair] = useState<{ a: EleveSuspectCard; b: EleveSuspectCard } | null>(null);
    const [ignoreMotif, setIgnoreMotif] = useState('Jumeaux / Vrais homonymes');
    const [submittingIgnore, setSubmittingIgnore] = useState(false);

    // Modale interactive de prévisualisation et fusion
    const [mergeModalOpen, setMergeModalOpen] = useState(false);
    const [loadingPreview, setLoadingPreview] = useState(false);
    const [preview, setPreview] = useState<MergePreviewPayload | null>(null);
    const [identityChoices, setIdentityChoices] = useState<Record<string, 'master' | 'duplicate'>>({});
    const [noteResolutions, setNoteResolutions] = useState<Record<string, 'master' | 'duplicate'>>({});
    const [merging, setMerging] = useState(false);

    /**
     * Navigation lors du changement d'année scolaire ou de campus.
     */
    const handleScopeChange = (params: { annee_scolaire_id?: string; etablissement_id?: string }) => {
        router.get(
            '/administration/eleves/doublons',
            {
                annee_scolaire_id: params.annee_scolaire_id ?? String(selectedAnneeId),
                etablissement_id: params.etablissement_id ?? selectedEtablissementId,
            },
            { preserveState: false, replace: true }
        );
    };

    /**
     * Options formatées pour les sélecteurs manuels de fusion libre.
     */
    const eleveSearchOptions: SearchableOption[] = useMemo(
        () =>
            elevesOptions.map((el) => ({
                value: String(el.id),
                label: `${el.nom_complet} (${el.matricule})`,
                description: `${el.classe_nom}${el.etablissement_code ? ` • ${el.etablissement_code}` : ''}`,
            })),
        [elevesOptions]
    );

    /**
     * Filtrage local rapide sur les paires suspectes détectées.
     */
    const filteredSuspects = useMemo(() => {
        const q = searchSuspect.trim().toLowerCase();
        if (!q) return suspects;
        return suspects.filter(
            (s) =>
                s.master.nom_complet.toLowerCase().includes(q) ||
                s.duplicate.nom_complet.toLowerCase().includes(q) ||
                s.master.matricule.toLowerCase().includes(q) ||
                s.duplicate.matricule.toLowerCase().includes(q) ||
                s.master.classe_nom.toLowerCase().includes(q) ||
                s.duplicate.classe_nom.toLowerCase().includes(q)
        );
    }, [suspects, searchSuspect]);

    /**
     * Charge la prévisualisation comparative détaillée entre deux élèves et ouvre la modale de fusion.
     *
     * @param {number} masterId Identifiant de l'élève conservé (Maître).
     * @param {number} duplicateId Identifiant de l'élève à absorber (Doublon).
     */
    const openMergePreview = async (masterId: number, duplicateId: number) => {
        if (masterId === duplicateId) {
            toast.error('Veuillez sélectionner deux élèves différents.', { position: 'top-right' });
            return;
        }

        setMergeModalOpen(true);
        setLoadingPreview(true);
        setPreview(null);
        setNoteResolutions({});

        try {
            const response = await fetch(
                `/administration/eleves/doublons/preview?master_id=${masterId}&duplicate_id=${duplicateId}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            if (!response.ok) {
                throw new Error('Impossible de charger la comparaison des deux dossiers.');
            }

            const data: MergePreviewPayload = await response.json();
            setPreview(data);
            setIdentityChoices(data.suggested_identity || {});
            // Force un arbitrage explicite de l'utilisateur pour chaque conflit de note détecté
            setNoteResolutions({});
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Erreur lors du chargement de la prévisualisation.', {
                position: 'top-right',
            });
            setMergeModalOpen(false);
        } finally {
            setLoadingPreview(false);
        }
    };

    /**
     * Inverse en 1 clic le dossier Maître et le dossier Doublon dans la modale ouverte.
     */
    const handleSwapMasterDuplicate = () => {
        if (!preview || loadingPreview || merging) return;
        void openMergePreview(preview.duplicate.id, preview.master.id);
    };

    /**
     * Vérifie que tous les conflits de notes ont bien reçu une décision explicite avant validation.
     */
    const unresolvedConflictsCount = useMemo(() => {
        if (!preview) return 0;
        return preview.conflits_notes.filter((c) => !noteResolutions[String(c.duplicate_note_id)]).length;
    }, [preview, noteResolutions]);

    /**
     * Exécute la fusion atomique côté serveur après validation complète.
     */
    const handleConfirmMerge = () => {
        if (!preview || merging) return;
        if (unresolvedConflictsCount > 0) {
            toast.error(
                `Veuillez arbitrer les ${unresolvedConflictsCount} conflit(s) de notes avant de lancer la fusion.`,
                { position: 'top-right' }
            );
            return;
        }

        setMerging(true);
        router.post(
            '/administration/eleves/doublons/merge',
            {
                master_id: preview.master.id,
                duplicate_id: preview.duplicate.id,
                identity_choices: identityChoices,
                note_resolutions: noteResolutions,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMerging(false);
                    setMergeModalOpen(false);
                    setPreview(null);
                    setManualMasterId('');
                    setManualDuplicateId('');
                    toast.success('Fusion exécutée avec succès ! Tous les reçus et données ont été unifiés.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-emerald-700',
                    });
                },
                onError: (errors) => {
                    setMerging(false);
                    const firstErr = Object.values(errors)[0] || 'Erreur lors de la fusion des dossiers.';
                    toast.error(String(firstErr), { position: 'top-right' });
                },
            }
        );
    };

    /**
     * Enregistre une paire comme faux positif légitime (jumeaux / homonymes).
     */
    const handleConfirmIgnore = () => {
        if (!ignorePair || submittingIgnore) return;
        setSubmittingIgnore(true);
        router.post(
            '/administration/eleves/doublons/ignore',
            {
                eleve_a_id: ignorePair.a.eleve_id,
                eleve_b_id: ignorePair.b.eleve_id,
                motif: ignoreMotif.trim() || 'Jumeaux / Homonymes légitimes',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSubmittingIgnore(false);
                    setIgnorePair(null);
                    toast.success('Paire marquée comme non-doublon.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-emerald-700',
                    });
                },
                onError: () => {
                    setSubmittingIgnore(false);
                    toast.error("Impossible d'ignorer cette paire.", { position: 'top-right' });
                },
            }
        );
    };

    /**
     * Rétablit une paire ignorée dans le scanner de doublons.
     */
    const handleUnignore = (ignoreId: number) => {
        router.delete(`/administration/eleves/doublons/ignore/${ignoreId}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Paire rétablie dans le contrôle des doublons.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-emerald-700',
                });
            },
        });
    };

    /**
     * Colonnes TanStack Table pour le journal d'audit des fusions.
     */
    const auditColumns: ColumnDef<FusionAuditItem>[] = useMemo(
        () => [
            {
                accessorKey: 'created_at',
                header: 'Date',
                cell: ({ row }) => (
                    <span className="text-xs font-medium text-slate-700 tabular-nums whitespace-nowrap">
                        {row.original.created_at ? formatDate(row.original.created_at) : '-'}
                    </span>
                ),
            },
            {
                id: 'absorbed',
                accessorFn: (row) => `${row.absorbed_matricule} ${row.absorbed_nom_complet}`,
                header: 'Doublon absorbé',
                cell: ({ row }) => (
                    <div className="space-y-0.5">
                        <div className="font-semibold text-xs text-slate-800 line-through decoration-rose-400">
                            {row.original.absorbed_nom_complet}
                        </div>
                        <Badge variant="outline" className="font-mono text-[10px] bg-rose-50 text-rose-700 border-rose-200">
                            {row.original.absorbed_matricule}
                        </Badge>
                    </div>
                ),
            },
            {
                id: 'master',
                accessorFn: (row) => `${row.master_matricule} ${row.master_nom_complet}`,
                header: 'Dossier Maître conservé',
                cell: ({ row }) => (
                    <div className="space-y-0.5">
                        <Link
                            href={`/administration/eleves?search=${encodeURIComponent(row.original.master_matricule)}`}
                            className="font-bold text-xs text-emerald-700 hover:underline block"
                        >
                            {row.original.master_nom_complet}
                        </Link>
                        <Badge variant="outline" className="font-mono text-[10px] bg-emerald-50 text-emerald-700 border-emerald-200">
                            {row.original.master_matricule}
                        </Badge>
                    </div>
                ),
            },
            {
                id: 'transferts',
                header: 'Données transférées',
                cell: ({ row }) => (
                    <div className="flex flex-wrap items-center gap-1.5 text-[11px]">
                        <Badge variant="outline" className="bg-emerald-50/70 text-emerald-800 border-emerald-200 tabular-nums">
                            {row.original.nb_versements_transferes} vers. ({row.original.montant_versements_transferes.toLocaleString('fr-FR')} F)
                        </Badge>
                        <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 tabular-nums">
                            {row.original.nb_notes_transferees} note(s)
                        </Badge>
                        {row.original.nb_conflits_notes_resolus > 0 && (
                            <Badge variant="outline" className="bg-amber-50 text-amber-800 border-amber-200 tabular-nums">
                                {row.original.nb_conflits_notes_resolus} conflit(s) arbitré(s)
                            </Badge>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'merged_by',
                header: 'Validé par',
                cell: ({ row }) => (
                    <span className="text-xs text-slate-600">{row.original.merged_by}</span>
                ),
            },
        ],
        []
    );

    const highConfidenceCount = suspects.filter((s) => s.niveau_confiance === 'eleve').length;

    return (
        <>
            <Head title="Doublons & Fusion des Élèves - SYGES" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête de page */}
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b pb-5">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                            <Link
                                href="/administration/eleves"
                                className="inline-flex items-center gap-1 hover:text-foreground transition-colors font-medium"
                            >
                                <ArrowLeft className="size-3.5" />
                                <span>Retour au registre des élèves</span>
                            </Link>
                        </div>
                        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 shrink-0">
                                <GitMerge className="size-6" />
                            </div>
                            <span>Contrôle des Doublons & Fusion de Dossiers</span>
                        </h1>
                        <p className="text-xs sm:text-sm text-muted-foreground">
                            Détectez les élèves saisis en double, arbitrez les conflits de notes et fusionnez leurs dossiers sans aucune perte comptable.
                        </p>
                    </div>

                    {/* Filtres Année & Campus */}
                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            value={selectedAnneeId}
                            onChange={(e) => handleScopeChange({ annee_scolaire_id: e.target.value })}
                            aria-label="Année scolaire"
                            className="h-9 rounded-md border border-input bg-background px-3 text-xs font-semibold"
                        >
                            {anneesScolaires.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.code} {a.is_current ? '★' : ''}
                                </option>
                            ))}
                        </select>

                        {canAccessAll && etablissements.length > 1 && (
                            <select
                                value={selectedEtablissementId}
                                onChange={(e) => handleScopeChange({ etablissement_id: e.target.value })}
                                aria-label="Campus scolaire"
                                className="h-9 rounded-md border border-input bg-background px-3 text-xs font-semibold"
                            >
                                <option value="all">Tous les campus</option>
                                {etablissements.map((et) => (
                                    <option key={et.id} value={String(et.id)}>
                                        {et.code} — {et.nom}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>

                {/* 4 Cartes KPI compactes et sobres */}
                <div className="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="border-border/80 shadow-2xs">
                        <CardContent className="py-4 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Paires suspectes</p>
                                <p className="text-2xl font-bold tracking-tight tabular-nums text-foreground mt-0.5">
                                    {suspects.length}
                                </p>
                                <p className="text-[11px] text-amber-700 font-medium mt-0.5">
                                    {highConfidenceCount} forte(s) probabilité(s)
                                </p>
                            </div>
                            <div className="size-10 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center shrink-0">
                                <AlertTriangle className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs">
                        <CardContent className="py-4 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Élèves analysés</p>
                                <p className="text-2xl font-bold tracking-tight tabular-nums text-foreground mt-0.5">
                                    {elevesOptions.length}
                                </p>
                                <p className="text-[11px] text-muted-foreground mt-0.5">Sur le périmètre sélectionné</p>
                            </div>
                            <div className="size-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center shrink-0">
                                <Users className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs">
                        <CardContent className="py-4 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Fusions réalisées</p>
                                <p className="text-2xl font-bold tracking-tight tabular-nums text-emerald-700 mt-0.5">
                                    {audits.length}
                                </p>
                                <p className="text-[11px] text-muted-foreground mt-0.5">Traçabilité comptable active</p>
                            </div>
                            <div className="size-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center shrink-0">
                                <ShieldCheck className="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/80 shadow-2xs">
                        <CardContent className="py-4 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Paires ignorées</p>
                                <p className="text-2xl font-bold tracking-tight tabular-nums text-slate-700 mt-0.5">
                                    {ignores.length}
                                </p>
                                <p className="text-[11px] text-muted-foreground mt-0.5">Jumeaux & vrais homonymes</p>
                            </div>
                            <div className="size-10 rounded-xl bg-slate-100 text-slate-600 border border-slate-200 flex items-center justify-center shrink-0">
                                <EyeOff className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Fusion manuelle rapide de 2 dossiers quelconques */}
                <Card className="border-border/80 shadow-2xs bg-slate-50/60">
                    <CardContent className="py-4 space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <GitMerge className="size-4 text-blue-600" />
                                    Fusion manuelle de deux dossiers spécifiques
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Vous avez repéré un doublon qui ne figure pas dans la liste automatique (ex. faute d'orthographe importante) ? Sélectionnez directement les deux fiches ci-dessous.
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div className="md:col-span-5 space-y-1 min-w-0">
                                <Label className="text-xs font-semibold text-emerald-800">
                                    1. Dossier Maître (à conserver)
                                </Label>
                                <SearchableSelect
                                    options={eleveSearchOptions}
                                    value={manualMasterId}
                                    onValueChange={setManualMasterId}
                                    placeholder="Sélectionner le dossier principal..."
                                    searchPlaceholder="Rechercher par nom ou matricule..."
                                    emptyMessage="Aucun élève trouvé."
                                    className="h-9 text-xs bg-white"
                                />
                            </div>

                            <div className="md:col-span-5 space-y-1 min-w-0">
                                <Label className="text-xs font-semibold text-amber-800">
                                    2. Dossier Doublon (à absorber)
                                </Label>
                                <SearchableSelect
                                    options={eleveSearchOptions.filter((o) => o.value !== manualMasterId)}
                                    value={manualDuplicateId}
                                    onValueChange={setManualDuplicateId}
                                    placeholder="Sélectionner le dossier en double..."
                                    searchPlaceholder="Rechercher par nom ou matricule..."
                                    emptyMessage="Aucun élève trouvé."
                                    className="h-9 text-xs bg-white"
                                />
                            </div>

                            <div className="md:col-span-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={!manualMasterId || !manualDuplicateId || manualMasterId === manualDuplicateId}
                                    onClick={() => openMergePreview(Number(manualMasterId), Number(manualDuplicateId))}
                                    className="w-full h-9 gap-1.5 bg-blue-600 hover:bg-blue-700 text-white cursor-pointer font-semibold"
                                >
                                    <GitMerge className="size-3.5" />
                                    Comparer
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Barre d'onglets */}
                <div className="flex border-b border-border/80 gap-2 overflow-x-auto">
                    <button
                        type="button"
                        onClick={() => setActiveTab('suspects')}
                        className={`flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-medium border-b-2 transition-colors cursor-pointer shrink-0 ${
                            activeTab === 'suspects'
                                ? 'border-amber-600 text-amber-700 font-semibold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <AlertTriangle className="size-4" />
                        <span>Doublons détectés</span>
                        <Badge variant="secondary" className="text-xs">{suspects.length}</Badge>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('ignores')}
                        className={`flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-medium border-b-2 transition-colors cursor-pointer shrink-0 ${
                            activeTab === 'ignores'
                                ? 'border-slate-700 text-slate-900 font-semibold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <EyeOff className="size-4" />
                        <span>Jumeaux / Ignorés</span>
                        <Badge variant="secondary" className="text-xs">{ignores.length}</Badge>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('audits')}
                        className={`flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-medium border-b-2 transition-colors cursor-pointer shrink-0 ${
                            activeTab === 'audits'
                                ? 'border-emerald-600 text-emerald-700 font-semibold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <History className="size-4" />
                        <span>Journal d'Audit des Fusions</span>
                        <Badge variant="secondary" className="text-xs">{audits.length}</Badge>
                    </button>
                </div>

                {/* ONGLET 1 : PAIRES SUSPECTES */}
                {activeTab === 'suspects' && (
                    <div className="space-y-4">
                        <div className="relative max-w-md">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                            <Input
                                value={searchSuspect}
                                onChange={(e) => setSearchSuspect(e.target.value)}
                                placeholder="Filtrer par nom, matricule ou classe..."
                                className="pl-9 h-9 text-xs"
                            />
                        </div>

                        {filteredSuspects.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-emerald-300 bg-emerald-50/40 p-10 text-center space-y-2">
                                <div className="size-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
                                    <CheckCircle2 className="size-6" />
                                </div>
                                <h3 className="text-sm font-bold text-emerald-950">Aucun doublon suspect détecté</h3>
                                <p className="text-xs text-muted-foreground max-w-md mx-auto">
                                    Votre registre d'élèves est propre sur ce périmètre. Vous pouvez toujours utiliser la fusion manuelle ci-dessus si besoin.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {filteredSuspects.map((pair) => (
                                    <div
                                        key={pair.pair_key}
                                        className="rounded-2xl border border-border/80 bg-card p-4 shadow-2xs space-y-3"
                                    >
                                        {/* Bandeau score & motifs */}
                                        <div className="flex flex-wrap items-center justify-between gap-2 pb-2.5 border-b border-border/60">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={`text-xs font-bold tabular-nums ${
                                                        pair.niveau_confiance === 'eleve'
                                                            ? 'bg-rose-50 text-rose-700 border-rose-200'
                                                            : 'bg-amber-50 text-amber-800 border-amber-200'
                                                    }`}
                                                >
                                                    Similarité {pair.score} %
                                                </Badge>
                                                {pair.raisons.map((r, i) => (
                                                    <Badge key={i} variant="secondary" className="text-[11px] font-medium">
                                                        {r}
                                                    </Badge>
                                                ))}
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        setIgnoreMotif('Jumeaux / Vrais homonymes');
                                                        setIgnorePair({ a: pair.master, b: pair.duplicate });
                                                    }}
                                                    className="h-8 text-xs gap-1.5 cursor-pointer"
                                                >
                                                    <EyeOff className="size-3.5 text-slate-500" />
                                                    Ne sont pas des doublons
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={() => openMergePreview(pair.master.eleve_id, pair.duplicate.eleve_id)}
                                                    className="h-8 text-xs gap-1.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold cursor-pointer"
                                                >
                                                    <GitMerge className="size-3.5" />
                                                    Comparer & Fusionner
                                                </Button>
                                            </div>
                                        </div>

                                        {/* Comparaison rapide des 2 fiches */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            {[
                                                { role: 'Maître suggéré', badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200', item: pair.master },
                                                { role: 'Doublon présumé', badgeClass: 'bg-amber-50 text-amber-800 border-amber-200', item: pair.duplicate },
                                            ].map(({ role, badgeClass, item }) => (
                                                <div
                                                    key={item.eleve_id}
                                                    className="rounded-xl border border-border/70 bg-slate-50/50 p-3 flex items-start gap-3"
                                                >
                                                    <div className="size-10 rounded-xl border border-border bg-white overflow-hidden shrink-0 flex items-center justify-center">
                                                        <EleveAvatar photoUrl={item.photo_url} alt={item.nom_complet} />
                                                    </div>
                                                    <div className="min-w-0 flex-1 space-y-1">
                                                        <div className="flex items-center justify-between gap-2 flex-wrap">
                                                            <span className="font-bold text-sm text-foreground truncate">
                                                                {item.nom_complet}
                                                            </span>
                                                            <Badge variant="outline" className={`text-[10px] ${badgeClass}`}>
                                                                {role}
                                                            </Badge>
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                                            <span className="font-mono font-semibold text-slate-700">{item.matricule}</span>
                                                            <span>•</span>
                                                            <span className="font-semibold text-slate-800">{item.classe_nom}</span>
                                                            {item.date_naissance && (
                                                                <>
                                                                    <span>•</span>
                                                                    <span>Né(e) le {formatDate(item.date_naissance)}</span>
                                                                </>
                                                            )}
                                                            {item.telephone_parent && (
                                                                <>
                                                                    <span>•</span>
                                                                    <span className="font-mono">{item.telephone_parent}</span>
                                                                </>
                                                            )}
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-2 pt-1 text-[11px]">
                                                            <span className="inline-flex items-center gap-1 text-emerald-700 font-semibold tabular-nums">
                                                                <Wallet className="size-3" />
                                                                {item.total_paye.toLocaleString('fr-FR')} F ({item.nb_versements} reçu(s))
                                                            </span>
                                                            <span className="inline-flex items-center gap-1 text-blue-700 font-medium tabular-nums">
                                                                <FileCheck2 className="size-3" />
                                                                {item.nb_notes} note(s)
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* ONGLET 2 : PAIRES IGNORÉES (JUMEAUX / HOMONYMES) */}
                {activeTab === 'ignores' && (
                    <div className="space-y-3">
                        {ignores.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-border p-8 text-center text-xs text-muted-foreground">
                                Aucune paire n'a encore été marquée comme jumeaux ou vrais homonymes.
                            </div>
                        ) : (
                            ignores.map((ig) => (
                                <div
                                    key={ig.id}
                                    className="rounded-xl border border-border/80 bg-card p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                                >
                                    <div className="space-y-1 text-xs">
                                        <div className="flex flex-wrap items-center gap-2 font-semibold text-foreground">
                                            <span>{ig.eleve_min.nom_complet}</span>
                                            <Badge variant="outline" className="font-mono text-[10px]">{ig.eleve_min.matricule}</Badge>
                                            <span className="text-muted-foreground">≠</span>
                                            <span>{ig.eleve_max.nom_complet}</span>
                                            <Badge variant="outline" className="font-mono text-[10px]">{ig.eleve_max.matricule}</Badge>
                                        </div>
                                        <div className="text-[11px] text-muted-foreground">
                                            Motif : <strong>{ig.motif || 'Non précisé'}</strong> • Marqué par {ig.ignored_by}
                                            {ig.created_at ? ` le ${formatDate(ig.created_at)}` : ''}
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleUnignore(ig.id)}
                                        className="h-8 text-xs gap-1.5 cursor-pointer shrink-0"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Rétablir dans le scan
                                    </Button>
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* ONGLET 3 : JOURNAL D'AUDIT DES FUSIONS (TANSTACK TABLE) */}
                {activeTab === 'audits' && (
                    <DataTable
                        columns={auditColumns}
                        data={audits}
                        searchPlaceholder="Rechercher un ancien matricule absorbé, un élève ou un opérateur..."
                    />
                )}
            </div>

            {/* MODALE : MARQUER COMME JUMEAUX / HOMONYMES */}
            <Dialog open={Boolean(ignorePair)} onOpenChange={(open) => !open && setIgnorePair(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <EyeOff className="size-4 text-slate-600" />
                            Confirmer : ce ne sont pas des doublons
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Ces deux fiches ne seront plus signalées ensemble par le détecteur automatique (vous pourrez toujours rétablir cette paire dans l'onglet « Jumeaux / Ignorés »).
                        </DialogDescription>
                    </DialogHeader>

                    {ignorePair && (
                        <div className="space-y-3 text-xs">
                            <div className="rounded-lg border bg-slate-50 p-3 space-y-1">
                                <div>
                                    <strong>{ignorePair.a.nom_complet}</strong> ({ignorePair.a.matricule})
                                </div>
                                <div>
                                    <strong>{ignorePair.b.nom_complet}</strong> ({ignorePair.b.matricule})
                                </div>
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="ignore-motif" className="text-xs font-semibold">
                                    Motif (optionnel)
                                </Label>
                                <Input
                                    id="ignore-motif"
                                    value={ignoreMotif}
                                    onChange={(e) => setIgnoreMotif(e.target.value)}
                                    placeholder="Ex: Jumeaux, Frères dans la même classe, Vrais homonymes..."
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => setIgnorePair(null)}>
                            Annuler
                        </Button>
                        <Button
                            type="button"
                            disabled={submittingIgnore}
                            onClick={handleConfirmIgnore}
                            className="bg-slate-800 hover:bg-slate-900 text-white cursor-pointer"
                        >
                            {submittingIgnore ? 'Enregistrement...' : 'Confirmer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* MODALE INTERACTIVE DE FUSION CÔTE À CÔTE + ARBITRAGE DES CONFLITS DE NOTES */}
            <Dialog open={mergeModalOpen} onOpenChange={(open) => !merging && setMergeModalOpen(open)}>
                <DialogContent className="w-[95vw] sm:max-w-4xl max-h-[92vh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="flex items-center justify-between gap-2 pr-6 text-lg">
                            <span className="flex items-center gap-2">
                                <GitMerge className="size-5 text-amber-600" />
                                Assistant de Fusion de Doublon Élève
                            </span>
                            {preview && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={loadingPreview || merging}
                                    onClick={handleSwapMasterDuplicate}
                                    className="h-8 text-xs gap-1.5 cursor-pointer"
                                >
                                    <ArrowLeftRight className="size-3.5 text-blue-600" />
                                    Inverser Maître ↔ Doublon
                                </Button>
                            )}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Toutes les données du <strong>Doublon</strong> (paiements, reçus, bourses, frais annexes, notes, absences, sanctions) seront transférées dans le dossier <strong>Maître</strong>. L'ancien matricule restera retrouvable via la recherche.
                        </DialogDescription>
                    </DialogHeader>

                    {loadingPreview || !preview ? (
                        <div className="py-12 flex flex-col items-center justify-center gap-2 text-muted-foreground text-xs">
                            <Loader2 className="size-6 animate-spin text-blue-600" />
                            <span>Analyse financière et détection des conflits de notes en cours...</span>
                        </div>
                    ) : (
                        <div className="space-y-5 text-xs">
                            {/* 1. Cartes récapitulatives Maître vs Doublon */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div className="rounded-xl border-2 border-emerald-300 bg-emerald-50/40 p-3.5 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-emerald-600 text-white text-[10px]">
                                            DOSSIER MAÎTRE (CONSERVÉ)
                                        </Badge>
                                        <span className="font-mono font-bold text-emerald-800">{preview.master.matricule}</span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="size-12 rounded-xl border bg-white overflow-hidden shrink-0 flex items-center justify-center">
                                            <EleveAvatar photoUrl={preview.master.photo_url} alt={preview.master.nom_complet} />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="font-bold text-sm text-foreground truncate">{preview.master.nom_complet}</p>
                                            <p className="text-[11px] text-muted-foreground">{preview.master.classe_nom}</p>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-3 gap-2 pt-1 text-[11px]">
                                        <div className="rounded-lg bg-white border p-2">
                                            <span className="text-muted-foreground block">Versé</span>
                                            <strong className="text-emerald-700 tabular-nums">
                                                {preview.master.total_paye.toLocaleString('fr-FR')} F
                                            </strong>
                                        </div>
                                        <div className="rounded-lg bg-white border p-2">
                                            <span className="text-muted-foreground block">Reçus</span>
                                            <strong className="tabular-nums">{preview.master.nb_versements}</strong>
                                        </div>
                                        <div className="rounded-lg bg-white border p-2">
                                            <span className="text-muted-foreground block">Notes</span>
                                            <strong className="tabular-nums">{preview.master.nb_notes}</strong>
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-xl border-2 border-amber-300 bg-amber-50/40 p-3.5 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-amber-600 text-white text-[10px]">
                                            DOUBLON (ABSORBÉ PUIS SUPPRIMÉ)
                                        </Badge>
                                        <span className="font-mono font-bold text-amber-900">{preview.duplicate.matricule}</span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="size-12 rounded-xl border bg-white overflow-hidden shrink-0 flex items-center justify-center">
                                            <EleveAvatar photoUrl={preview.duplicate.photo_url} alt={preview.duplicate.nom_complet} />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="font-bold text-sm text-foreground truncate">{preview.duplicate.nom_complet}</p>
                                            <p className="text-[11px] text-muted-foreground">{preview.duplicate.classe_nom}</p>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-3 gap-2 pt-1 text-[11px]">
                                        <div className="rounded-lg bg-white border p-2">
                                            <span className="text-muted-foreground block">À transférer</span>
                                            <strong className="text-emerald-700 tabular-nums">
                                                {preview.duplicate.total_paye.toLocaleString('fr-FR')} F
                                            </strong>
                                        </div>
                                        <div className="rounded-lg bg-white border p-2">
                                            <span className="text-muted-foreground block">Reçus</span>
                                            <strong className="tabular-nums">{preview.duplicate.nb_versements}</strong>
                                        </div>
                                        <div className="rounded-lg bg-white border p-2">
                                            <span className="text-muted-foreground block">Notes</span>
                                            <strong className="tabular-nums">{preview.duplicate.nb_notes}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* 2. Résumé de ce qui sera transféré automatiquement */}
                            <div className="rounded-xl border border-blue-200 bg-blue-50/50 p-3 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-blue-950">
                                <span className="font-bold flex items-center gap-1.5">
                                    <ArrowRight className="size-4 text-blue-600" />
                                    Transferts automatiques :
                                </span>
                                <span>
                                    <strong>{preview.resume_transfert.nb_versements}</strong> reçu(s) ({preview.resume_transfert.montant_versements.toLocaleString('fr-FR')} FCFA)
                                </span>
                                <span>
                                    <strong>{preview.resume_transfert.nb_notes_sans_conflit}</strong> note(s) sans conflit
                                </span>
                                <span>
                                    <strong>{preview.resume_transfert.nb_assiduites}</strong> absence(s)/retard(s)
                                </span>
                                <span>
                                    <strong>{preview.resume_transfert.nb_sanctions}</strong> sanction(s)
                                </span>
                                {preview.resume_transfert.nb_inscriptions_autres_annees > 0 && (
                                    <span>
                                        <strong>{preview.resume_transfert.nb_inscriptions_autres_annees}</strong> inscription(s) d'autres années
                                    </span>
                                )}
                            </div>

                            {/* 3. Arbitrage obligatoire des conflits de notes (si détectés) */}
                            {preview.conflits_notes.length > 0 && (
                                <div className="rounded-xl border-2 border-rose-300 bg-rose-50/40 p-4 space-y-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2 text-rose-900 font-bold">
                                            <Scale className="size-4 text-rose-600 shrink-0" />
                                            <span>
                                                Conflits de notes à arbitrer ({preview.conflits_notes.length - unresolvedConflictsCount} / {preview.conflits_notes.length} résolu(s))
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    const all: Record<string, 'master' | 'duplicate'> = {};
                                                    preview.conflits_notes.forEach((c) => {
                                                        all[String(c.duplicate_note_id)] = 'master';
                                                    });
                                                    setNoteResolutions(all);
                                                }}
                                                className="h-7 text-[11px] bg-white cursor-pointer"
                                            >
                                                Tout garder (Maître)
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    const all: Record<string, 'master' | 'duplicate'> = {};
                                                    preview.conflits_notes.forEach((c) => {
                                                        all[String(c.duplicate_note_id)] = 'duplicate';
                                                    });
                                                    setNoteResolutions(all);
                                                }}
                                                className="h-7 text-[11px] bg-white cursor-pointer"
                                            >
                                                Tout prendre (Doublon)
                                            </Button>
                                        </div>
                                    </div>

                                    <p className="text-[11px] text-rose-800">
                                        Les évaluations ci-dessous possèdent une note différente sur les deux dossiers. Cliquez sur la note que vous souhaitez conserver pour chaque ligne :
                                    </p>

                                    <div className="space-y-2">
                                        {preview.conflits_notes.map((c) => {
                                            const key = String(c.duplicate_note_id);
                                            const choice = noteResolutions[key];
                                            return (
                                                <div
                                                    key={key}
                                                    className="rounded-lg border border-rose-200 bg-white p-2.5 grid grid-cols-1 sm:grid-cols-12 gap-2 items-center"
                                                >
                                                    <div className="sm:col-span-4">
                                                        <p className="font-bold text-slate-900">{c.matiere_nom}</p>
                                                        <p className="text-[11px] text-muted-foreground">
                                                            {c.evaluation_nom} {c.periode_nom ? `• ${c.periode_nom}` : ''} ({c.annee_code})
                                                        </p>
                                                    </div>

                                                    <div className="sm:col-span-4">
                                                        <button
                                                            type="button"
                                                            onClick={() => setNoteResolutions((prev) => ({ ...prev, [key]: 'master' }))}
                                                            className={`w-full text-left p-2 rounded-lg border transition-all cursor-pointer ${
                                                                choice === 'master'
                                                                    ? 'border-emerald-600 bg-emerald-50 ring-1 ring-emerald-600'
                                                                    : 'border-slate-200 hover:border-emerald-300'
                                                            }`}
                                                        >
                                                            <span className="text-[10px] font-semibold text-emerald-700 block">
                                                                Note Maître ({preview.master.matricule})
                                                            </span>
                                                            <span className="font-bold text-sm tabular-nums text-foreground">
                                                                {c.master_note.valeur_note !== null
                                                                    ? `${c.master_note.valeur_note} / 20`
                                                                    : c.master_note.statut_presence}
                                                            </span>
                                                        </button>
                                                    </div>

                                                    <div className="sm:col-span-4">
                                                        <button
                                                            type="button"
                                                            onClick={() => setNoteResolutions((prev) => ({ ...prev, [key]: 'duplicate' }))}
                                                            className={`w-full text-left p-2 rounded-lg border transition-all cursor-pointer ${
                                                                choice === 'duplicate'
                                                                    ? 'border-amber-600 bg-amber-50 ring-1 ring-amber-600'
                                                                    : 'border-slate-200 hover:border-amber-300'
                                                            }`}
                                                        >
                                                            <span className="text-[10px] font-semibold text-amber-800 block">
                                                                Note Doublon ({preview.duplicate.matricule})
                                                            </span>
                                                            <span className="font-bold text-sm tabular-nums text-foreground">
                                                                {c.duplicate_note.valeur_note !== null
                                                                    ? `${c.duplicate_note.valeur_note} / 20`
                                                                    : c.duplicate_note.statut_presence}
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* 4. Choix champ par champ de l'identité finale conservée */}
                            <div className="rounded-xl border border-border overflow-hidden">
                                <div className="bg-slate-100 px-3.5 py-2 border-b font-bold text-slate-800 flex items-center justify-between">
                                    <span>Identité finale à conserver sur la fiche Maître</span>
                                    <span className="text-[11px] font-normal text-muted-foreground">
                                        Cliquez sur une valeur pour choisir la meilleure orthographe / donnée
                                    </span>
                                </div>
                                <div className="divide-y divide-border">
                                    {/* Choix de la photo si au moins une fiche possède une photo */}
                                    {(preview.master.photo_url || preview.duplicate.photo_url) && (
                                        <div className="grid grid-cols-1 sm:grid-cols-12 gap-2 p-2.5 items-center bg-white">
                                            <div className="sm:col-span-3 font-semibold text-slate-600">Photo d'identité</div>
                                            <div className="sm:col-span-4">
                                                <button
                                                    type="button"
                                                    onClick={() => setIdentityChoices((p) => ({ ...p, photo_url: 'master' }))}
                                                    className={`w-full flex items-center gap-2 p-1.5 rounded-lg border cursor-pointer ${
                                                        (identityChoices.photo_url ?? 'master') === 'master'
                                                            ? 'border-emerald-600 bg-emerald-50/70'
                                                            : 'border-slate-200'
                                                    }`}
                                                >
                                                    <div className="size-8 rounded-md overflow-hidden border bg-white shrink-0 flex items-center justify-center">
                                                        <EleveAvatar photoUrl={preview.master.photo_url} alt={preview.master.nom_complet} />
                                                    </div>
                                                    <span className="text-[11px]">
                                                        {preview.master.photo_url ? 'Photo Maître' : 'Aucune photo'}
                                                    </span>
                                                </button>
                                            </div>
                                            <div className="sm:col-span-5">
                                                <button
                                                    type="button"
                                                    disabled={!preview.duplicate.photo_url}
                                                    onClick={() => setIdentityChoices((p) => ({ ...p, photo_url: 'duplicate' }))}
                                                    className={`w-full flex items-center gap-2 p-1.5 rounded-lg border cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ${
                                                        identityChoices.photo_url === 'duplicate'
                                                            ? 'border-amber-600 bg-amber-50/70'
                                                            : 'border-slate-200'
                                                    }`}
                                                >
                                                    <div className="size-8 rounded-md overflow-hidden border bg-white shrink-0 flex items-center justify-center">
                                                        <EleveAvatar photoUrl={preview.duplicate.photo_url} alt={preview.duplicate.nom_complet} />
                                                    </div>
                                                    <span className="text-[11px]">
                                                        {preview.duplicate.photo_url ? 'Adopter photo Doublon' : 'Aucune photo'}
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    )}

                                    {IDENTITY_FIELD_LABELS.map(({ key, label, isDate }) => {
                                        const mRaw = preview.master[key] as string | null | undefined;
                                        const dRaw = preview.duplicate[key] as string | null | undefined;
                                        if (!mRaw && !dRaw) return null;

                                        const mDisplay = mRaw ? (isDate ? formatDate(mRaw) : String(mRaw)) : '— (vide)';
                                        const dDisplay = dRaw ? (isDate ? formatDate(dRaw) : String(dRaw)) : '— (vide)';
                                        const selected = identityChoices[key as string] ?? 'master';

                                        return (
                                            <div key={key} className="grid grid-cols-1 sm:grid-cols-12 gap-2 p-2.5 items-center bg-white">
                                                <div className="sm:col-span-3 font-semibold text-slate-600">{label}</div>
                                                <div className="sm:col-span-4">
                                                    <button
                                                        type="button"
                                                        onClick={() => setIdentityChoices((p) => ({ ...p, [key]: 'master' }))}
                                                        className={`w-full text-left px-2.5 py-1.5 rounded-lg border text-xs transition-colors cursor-pointer ${
                                                            selected === 'master'
                                                                ? 'border-emerald-600 bg-emerald-50/70 font-semibold text-emerald-950'
                                                                : 'border-slate-200 text-slate-600 hover:border-emerald-300'
                                                        }`}
                                                    >
                                                        {mDisplay}
                                                    </button>
                                                </div>
                                                <div className="sm:col-span-5">
                                                    <button
                                                        type="button"
                                                        disabled={!dRaw}
                                                        onClick={() => setIdentityChoices((p) => ({ ...p, [key]: 'duplicate' }))}
                                                        className={`w-full text-left px-2.5 py-1.5 rounded-lg border text-xs transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ${
                                                            selected === 'duplicate'
                                                                ? 'border-amber-600 bg-amber-50/70 font-semibold text-amber-950'
                                                                : 'border-slate-200 text-slate-600 hover:border-amber-300'
                                                        }`}
                                                    >
                                                        {dDisplay}
                                                    </button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    )}

                    <DialogFooter className="pt-3 border-t flex flex-col-reverse sm:flex-row gap-2 sm:justify-between sm:items-center">
                        <p className="text-[11px] text-muted-foreground">
                            Action tracée dans le journal d'audit • Les numéros de reçus papier restent inchangés.
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={merging}
                                onClick={() => setMergeModalOpen(false)}
                            >
                                Annuler
                            </Button>
                            <Button
                                type="button"
                                disabled={!preview || loadingPreview || merging || unresolvedConflictsCount > 0}
                                onClick={handleConfirmMerge}
                                className="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold cursor-pointer"
                            >
                                {merging ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <GitMerge className="size-4" />
                                )}
                                {merging
                                    ? 'Fusion en cours...'
                                    : unresolvedConflictsCount > 0
                                      ? `Arbitrer ${unresolvedConflictsCount} conflit(s) de notes`
                                      : 'Valider la Fusion définitive'}
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Fil d'Ariane (Breadcrumbs) pour la page Doublons & Fusion.
 */
ElevesDoublonsPage.layout = {
    breadcrumbs: [
        { title: 'Administration', href: '/administration' },
        { title: 'Élèves & Inscriptions', href: '/administration/eleves' },
        { title: 'Doublons & Fusion', href: '/administration/eleves/doublons' },
    ],
};

import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    ExternalLink,
    GraduationCap,
    PlusCircle,
    Upload,
    User,
    Users,
    X,
} from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EleveAvatar } from '@/components/eleves/eleve-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SearchableSelect, type SearchableOption } from '@/components/ui/searchable-select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    buildClasseSearchOptions,
    buildEtablissementSearchOptions,
    flattenClassesFromEtablissements,
    formatDate,
    formatDateForInput,
} from '@/lib/utils';
import type {
    AnneeScolaireRef,
    EtablissementRef,
} from '@/types/eleve';

/**
 * Propriétés transmises par le contrôleur Inertia pour la création d'un élève.
 */
interface CreateElevePageProps {
    /** Liste des campus / établissements scolaires avec leur hiérarchie académique */
    etablissements: EtablissementRef[];
    /** Liste des années scolaires enregistrées */
    anneesScolaires: AnneeScolaireRef[];
    /** Identifiant de l'année scolaire active par défaut */
    selectedAnneeId: number;
}

/**
 * Page dédiée à la création et à l'inscription initiale d'un nouvel élève dans SYGES.
 * Présente un formulaire ergonomique à 2 blocs thématiques :
 * 1. État Civil, Identité & Affectation Scolaire (dégradé indigo)
 * 2. Filiation & Responsables Légaux (dégradé teal)
 *
 * @param {CreateElevePageProps} props Propriétés du composant
 * @returns {JSX.Element} Page de création d'élève
 */
export default function CreateEleve({
    etablissements,
    anneesScolaires,
    selectedAnneeId,
}: CreateElevePageProps) {
    // État de chargement et gestion des erreurs de validation
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Sélecteurs pour l'établissement et la classe d'affectation
    const [selectedEtabId, setSelectedEtabId] = useState<string>('');
    const [selectedClasseId, setSelectedClasseId] = useState<string>('');

    // Gestion de la photo de profil avec prévisualisation
    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const photoInputRef = useRef<HTMLInputElement>(null);

    // Détection temps réel d'éventuels doublons existants avant enregistrement
    interface PotentialDuplicateItem {
        eleve_id: number;
        matricule: string;
        nom_complet: string;
        sexe?: string | null;
        date_naissance?: string | null;
        telephone_parent?: string | null;
        photo_url?: string | null;
        classe_nom: string;
        etablissement_nom: string;
        score: number;
        raisons: string[];
    }
    const [potentialDuplicates, setPotentialDuplicates] = useState<PotentialDuplicateItem[]>([]);
    const [confirmNotDuplicate, setConfirmNotDuplicate] = useState(false);
    const duplicateAlertRef = useRef<HTMLDivElement>(null);

    // Données du formulaire
    const [formData, setFormData] = useState({
        // 1. État Civil
        matricule: '',
        matricule_national: '',
        nom: '',
        prenom: '',
        sexe: '' as '' | 'M' | 'F',
        date_naissance: '',
        lieu_naissance: '',
        nationalite: 'CAMEROUNAISE',

        // 2. Filiation & Tuteurs
        nom_pere: '',
        nom_mere: '',
        nom_tuteur: '',
        telephone_parent: '',
        email_parent: '',
        adresse: '',       // Quartier / adresse physique du domicile familial

        // 3. Affectation Scolaire
        annee_scolaire_id: String(selectedAnneeId || ''),
        statut: 'validee' as 'validee' | 'en_attente',
        is_redoublant: '0',
    });

    /**
     * Liste aplatie et options formatées pour SearchableSelect, filtrées par campus sans coupure de nom.
     */
    const availableClasses = useMemo(
        () => flattenClassesFromEtablissements(etablissements, selectedEtabId),
        [etablissements, selectedEtabId]
    );

    const classeOptions: SearchableOption[] = useMemo(
        () => buildClasseSearchOptions(availableClasses),
        [availableClasses]
    );

    const etablissementOptions: SearchableOption[] = useMemo(
        () => [{ value: '', label: 'Tous les campus' }, ...buildEtablissementSearchOptions(etablissements)],
        [etablissements]
    );

    /**
     * Gestionnaire de chargement de la photo d'identité.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} e Événement de sélection de fichier
     */
    const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // Vérifier la taille maximale avant l'envoi (5 Mo) pour prévenir un rejet serveur silencieux
        if (file.size > 5 * 1024 * 1024) {
            setErrors((prev) => ({ ...prev, photo: 'La photo ne doit pas dépasser 5 Mo.' }));
            toast.error('Photo trop volumineuse (max 5 Mo).', {
                position: 'top-right',
            });
            if (photoInputRef.current) {
                photoInputRef.current.value = '';
            }
            return;
        }

        setErrors((prev) => {
            if (!prev.photo) return prev;
            const next = { ...prev };
            delete next.photo;
            return next;
        });
        setPhotoFile(file);
        const reader = new FileReader();
        reader.onload = (event) => {
            setPhotoPreview(event.target?.result as string);
        };
        reader.readAsDataURL(file);
    };

    /**
     * Supprime la photo sélectionnée et réinitialise l'aperçu.
     */
    const removePhoto = () => {
        setPhotoFile(null);
        setPhotoPreview(null);
        setErrors((prev) => {
            if (!prev.photo) return prev;
            const next = { ...prev };
            delete next.photo;
            return next;
        });
        if (photoInputRef.current) {
            photoInputRef.current.value = '';
        }
    };

    /**
     * Réinitialise l'ensemble du formulaire pour saisir un nouvel élève.
     */
    const resetForm = () => {
        setFormData({
            matricule: '',
            matricule_national: '',
            nom: '',
            prenom: '',
            sexe: '' as '' | 'M' | 'F',
            date_naissance: '',
            lieu_naissance: '',
            nationalite: 'CAMEROUNAISE',
            nom_pere: '',
            nom_mere: '',
            nom_tuteur: '',
            telephone_parent: '',
            email_parent: '',
            adresse: '',
            annee_scolaire_id: String(selectedAnneeId || ''),
            statut: 'validee',
            is_redoublant: '0',
        });
        setSelectedClasseId('');
        setPotentialDuplicates([]);
        setConfirmNotDuplicate(false);
        removePhoto();
        setErrors({});
    };

    /**
     * Vérifie silencieusement en temps réel (debounce 400 ms) si un dossier élève proche
     * existe déjà dans la base afin de prévenir les doublons sans bloquer les jumeaux/homonymes.
     */
    useEffect(() => {
        const nomTrim = formData.nom.trim();
        if (nomTrim.length < 2) {
            setPotentialDuplicates([]);
            setConfirmNotDuplicate(false);
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const xsrfMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
                const xsrfToken = xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : '';

                const res = await fetch('/administration/eleves/check-doublons', {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfMeta ? { 'X-CSRF-TOKEN': csrfMeta } : {}),
                        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                    },
                    body: JSON.stringify({
                        nom: nomTrim,
                        prenom: formData.prenom.trim() || null,
                        date_naissance: formData.date_naissance || null,
                        telephone_parent: formData.telephone_parent.trim() || null,
                        classe_id: selectedClasseId ? Number(selectedClasseId) : null,
                        annee_scolaire_id: formData.annee_scolaire_id ? Number(formData.annee_scolaire_id) : null,
                    }),
                });

                if (!res.ok) return;
                const payload = (await res.json()) as { matches?: PotentialDuplicateItem[] };
                const nextMatches = payload.matches ?? [];
                setPotentialDuplicates(nextMatches);
                setConfirmNotDuplicate(false);
            } catch {
                // Requête annulée lors d'une frappe rapide : ignorée silencieusement
            }
        }, 400);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [
        formData.nom,
        formData.prenom,
        formData.date_naissance,
        formData.telephone_parent,
        formData.annee_scolaire_id,
        selectedClasseId,
    ]);

    /**
     * Soumission du formulaire avec choix de l'action après enregistrement.
     *
     * @param {'save_and_return' | 'save_and_new'} action Type d'action après succès
     */
    const submitForm = (action: 'save_and_return' | 'save_and_new') => {
        setErrors({});

        // Validations préalables
        const newErrors: Record<string, string> = {};
        if (!formData.nom.trim()) {
            newErrors.nom = 'Le nom de famille est obligatoire.';
        }
        if (!selectedClasseId) {
            newErrors.classe_id = 'Veuillez sélectionner la classe d\'affectation de l\'élève.';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            toast.error('Veuillez renseigner tous les champs obligatoires signalés en rouge.', {
                position: 'top-right',
            });
            return;
        }

        // Garde-fou anti-doublon : si un ou plusieurs profils très proches existent, demander confirmation explicite
        if (potentialDuplicates.length > 0 && !confirmNotDuplicate) {
            toast.error('Doublon potentiel détecté : vérifiez les fiches existantes ou confirmez qu’il s’agit d’un élève différent.', {
                position: 'top-right',
            });
            duplicateAlertRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        setLoading(true);

        const payload = new FormData();
        Object.entries(formData).forEach(([key, val]) => {
            if (val !== null && val !== undefined) {
                payload.append(key, val);
            }
        });

        payload.set('classe_id', selectedClasseId);
        payload.set('is_redoublant', formData.is_redoublant === '1' ? '1' : '0');
        payload.set('_action', action);

        if (photoFile) {
            payload.append('photo', photoFile);
        }

        router.post('/administration/eleves', payload, {
            forceFormData: true,
            preserveScroll: action === 'save_and_new',
            onSuccess: () => {
                setLoading(false);
                toast.success('Élève enregistré et inscrit avec succès !', {
                    position: 'top-right',
                    description: action === 'save_and_new'
                        ? `${formData.nom} a été enregistré. Le formulaire est réinitialisé pour saisir le suivant.`
                        : `${formData.nom} a été inscrit et ajouté au registre des élèves.`,
                    style: {
                        backgroundColor: '#10b981',
                        color: '#ffffff',
                        border: '1px solid #059669',
                    },
                });

                if (action === 'save_and_new') {
                    resetForm();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },
            onError: (errs) => {
                setLoading(false);
                setErrors(errs);
                toast.error('Erreur lors de l\'enregistrement de l\'élève.', {
                    position: 'top-right',
                    description: 'Veuillez vérifier les champs signalés en rouge.',
                });
            },
        });
    };

    return (
        <>
            <Head title="Nouvel Élève - Inscription Scolaire - SYGES" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0 pb-20">
                {/* EN-TÊTE DE PAGE AVEC NAVIGATION RETOUR ET BOUTONS D'ACTION */}
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b pb-5">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                            <Link
                                href="/administration/eleves"
                                className="inline-flex items-center gap-1 hover:text-foreground transition-colors font-medium cursor-pointer"
                            >
                                <ArrowLeft className="size-3.5" />
                                <span>Retour au registre des élèves</span>
                            </Link>
                        </div>
                        <h1 className="text-xl sm:text-2xl lg:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-teal-50 text-teal-600 border border-teal-200 shrink-0">
                                <GraduationCap className="size-6" />
                            </div>
                            <span>Nouvel Élève & Inscription Scolaire</span>
                        </h1>
                        <p className="text-xs sm:text-sm text-muted-foreground">
                            Enregistrement dans la Base Globale et affectation dans la classe d'accueil pour l'année scolaire sélectionnée.
                        </p>
                    </div>

                    {/* ACTIONS DU HAUT — hiérarchie : Annuler (ghost) | + Nouveau (outline) | Enregistrer (solid) */}
                    <TooltipProvider>
                        <div className="flex items-center gap-2 shrink-0 flex-wrap w-full lg:w-auto">
                            {/* Annuler — retour sans sauvegarder */}
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        asChild
                                        className="cursor-pointer text-xs h-9 text-muted-foreground hover:text-foreground"
                                    >
                                        <Link href="/administration/eleves">
                                            <X className="size-3.5 mr-1" />
                                            Annuler
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="bottom" className="bg-white text-slate-700 border border-slate-200 shadow-md font-normal">
                                    Abandonner la saisie et retourner au registre des élèves
                                </TooltipContent>
                            </Tooltip>

                            {/* Séparateur vertical */}
                            <div className="h-6 w-px bg-border hidden sm:block" />

                            {/* Enregistrer & Nouveau — inscrire et enchaîner */}
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={loading}
                                        onClick={() => submitForm('save_and_new')}
                                        className="cursor-pointer text-xs h-9 border-teal-500/50 text-teal-700 hover:bg-teal-50 font-medium gap-1.5"
                                    >
                                        <PlusCircle className="size-3.5 text-teal-600" />
                                        + Nouveau
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="bottom" className="bg-white text-slate-700 border border-slate-200 shadow-md font-normal">
                                    Enregistrer cet élève et vider le formulaire pour inscrire le suivant
                                </TooltipContent>
                            </Tooltip>

                            {/* Enregistrer & Retourner — action principale */}
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        disabled={loading}
                                        onClick={() => submitForm('save_and_return')}
                                        className="bg-teal-600 hover:bg-teal-700 text-white cursor-pointer font-semibold shadow-sm text-xs h-9 px-4 gap-1.5"
                                    >
                                        <Check className="size-3.5" />
                                        {loading ? 'Enregistrement...' : 'Enregistrer'}
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="bottom" className="bg-white text-slate-700 border border-slate-200 shadow-md font-normal">
                                    Enregistrer l'élève et retourner à la liste du registre
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </TooltipProvider>
                </div>

                {/* FORMULAIRE DÉCOUPÉ EN 4 CARTES THÉMATIQUES */}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submitForm('save_and_return');
                    }}
                    className="space-y-8"
                >
                    {/* BANDEAU D'ALERTE ANTI-DOUBLON EN TEMPS RÉEL (SANS BLOQUER LES JUMEAUX / HOMONYMES) */}
                    {potentialDuplicates.length > 0 && (
                        <div
                            ref={duplicateAlertRef}
                            className="rounded-2xl border-2 border-amber-400 bg-amber-50/90 p-4 sm:p-5 space-y-3 shadow-xs"
                        >
                            <div className="flex items-start gap-3">
                                <div className="p-2 rounded-xl bg-amber-100 text-amber-700 border border-amber-300 shrink-0">
                                    <AlertTriangle className="size-5" />
                                </div>
                                <div className="min-w-0 flex-1 space-y-1">
                                    <h3 className="text-sm font-bold text-amber-950">
                                        Doublon potentiel détecté ({potentialDuplicates.length} dossier(s) similaire(s) déjà existant(s))
                                    </h3>
                                    <p className="text-xs text-amber-900">
                                        Vérifiez ci-dessous si cet élève n&apos;est pas déjà enregistré afin d&apos;éviter de créer un deuxième matricule pour la même personne.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                {potentialDuplicates.map((dup) => (
                                    <div
                                        key={dup.eleve_id}
                                        className="rounded-xl border border-amber-200 bg-white p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                                    >
                                        <div className="flex items-center gap-3 min-w-0">
                                            <div className="size-10 rounded-xl border bg-slate-50 overflow-hidden shrink-0 flex items-center justify-center">
                                                <EleveAvatar photoUrl={dup.photo_url} alt={dup.nom_complet} />
                                            </div>
                                            <div className="min-w-0 space-y-0.5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-bold text-sm text-foreground">{dup.nom_complet}</span>
                                                    <Badge variant="outline" className="font-mono text-[10px]">
                                                        {dup.matricule}
                                                    </Badge>
                                                    <Badge variant="outline" className="text-[10px] bg-amber-50 text-amber-800 border-amber-300 tabular-nums">
                                                        {dup.score} %
                                                    </Badge>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                                    <span className="font-semibold text-slate-700">{dup.classe_nom}</span>
                                                    {dup.date_naissance && (
                                                        <>
                                                            <span>•</span>
                                                            <span>Né(e) le {formatDate(dup.date_naissance)}</span>
                                                        </>
                                                    )}
                                                    {dup.telephone_parent && (
                                                        <>
                                                            <span>•</span>
                                                            <span className="font-mono">{dup.telephone_parent}</span>
                                                        </>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap gap-1 pt-0.5">
                                                    {dup.raisons.map((r, idx) => (
                                                        <span
                                                            key={idx}
                                                            className="text-[10px] px-1.5 py-0.5 rounded bg-amber-100/80 text-amber-900 font-medium"
                                                        >
                                                            {r}
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="h-8 text-xs gap-1.5 border-amber-300 text-amber-900 hover:bg-amber-100 shrink-0"
                                        >
                                            <a
                                                href={`/administration/eleves?search=${encodeURIComponent(dup.matricule)}`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                Voir ce dossier
                                            </a>
                                        </Button>
                                    </div>
                                ))}
                            </div>

                            {/* Case de confirmation explicite permettant d'inscrire un vrai jumeau ou homonyme */}
                            <label
                                htmlFor="confirm-not-duplicate"
                                className="flex items-start gap-2.5 pt-2 border-t border-amber-200 cursor-pointer select-none text-xs text-amber-950 font-medium"
                            >
                                <Checkbox
                                    id="confirm-not-duplicate"
                                    checked={confirmNotDuplicate}
                                    onCheckedChange={(v) => setConfirmNotDuplicate(Boolean(v))}
                                    className="mt-0.5 border-amber-600 data-[state=checked]:bg-amber-600 data-[state=checked]:border-amber-600"
                                />
                                <span>
                                    Je confirme qu&apos;il s&apos;agit bien d&apos;un élève différent (ex. jumeaux ou vrais homonymes) et je souhaite créer un nouveau dossier.
                                </span>
                            </label>
                        </div>
                    )}

                    {/* BLOC 1 : ÉTAT CIVIL, IDENTITÉ & AFFECTATION SCOLAIRE */}
                    <div className="rounded-2xl border-2 border-slate-300/90 bg-card shadow-sm overflow-visible transition-all duration-200 hover:shadow-md hover:border-slate-400">
                        {/* En-tête de bloc avec bannière dégradée */}
                        <div className="rounded-t-2xl bg-linear-to-r from-slate-200/95 via-teal-100/70 to-slate-200/80 px-6 py-4 border-b-2 border-slate-300 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-teal-600 to-teal-800 text-white font-bold shadow-xs shrink-0">
                                <User className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 tracking-tight">
                                    1. État Civil, Identité & Affectation Scolaire
                                </h2>
                                <p className="text-xs text-slate-600">
                                    Informations d'état civil, identification, photo officielle et affectation dans la classe d'accueil.
                                </p>
                            </div>
                        </div>

                        {/* Corps du bloc État Civil */}
                        <div className="p-6 bg-card space-y-6">
                            {/* Zone Photo d'identité */}
                            <div className="flex flex-col sm:flex-row items-center gap-6 p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <div className="relative size-24 rounded-full overflow-hidden border-2 border-dashed border-indigo-300 bg-white flex items-center justify-center shadow-xs shrink-0">
                                    {photoPreview ? (
                                        <img
                                            src={photoPreview}
                                            alt="Aperçu photo"
                                            className="w-full h-full object-cover"
                                        />
                                    ) : (
                                        <User className="size-10 text-indigo-400" />
                                    )}
                                </div>
                                <div className="space-y-1.5 text-center sm:text-left flex-1">
                                    <div className="text-sm font-semibold text-foreground">Photo d'identité officielle</div>
                                    <p className="text-xs text-muted-foreground">
                                        Format JPG, PNG ou WEBP, max 5 Mo. Recommandé pour les cartes d'élèves et trombinoscopes.
                                    </p>
                                    <div className="flex items-center gap-2 pt-1 justify-center sm:justify-start">
                                        <input
                                            ref={photoInputRef}
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={handlePhotoChange}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => photoInputRef.current?.click()}
                                            className="h-8 text-xs cursor-pointer gap-1.5"
                                        >
                                            <Upload className="size-3.5" />
                                            {photoPreview ? 'Modifier la photo' : 'Charger une photo'}
                                        </Button>
                                        {photoPreview && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={removePhoto}
                                                className="h-8 text-xs text-rose-500 hover:text-rose-600 hover:bg-rose-50 cursor-pointer"
                                            >
                                                Supprimer
                                            </Button>
                                        )}
                                    </div>
                                    {errors.photo && <p className="text-rose-500 text-xs mt-1">{errors.photo}</p>}
                                </div>
                            </div>

                            {/* ═══════════════════════════════════════════════════════════
                                 Grille État Civil — disposition 4 colonnes sur écran large :
                                 Ligne 1 : Nom (col-span-2) | Prénom (col-span-2)
                                 Ligne 2 : Date naissance | Lieu naissance | Genre | Nationalité
                                 Ligne 3 : Matricule interne (col-span-2) | Matricule national (col-span-2)
                                 ═══════════════════════════════════════════════════════════ */}
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">

                                {/* ─── Ligne 1 : Identité principale ─── */}
                                <div className="col-span-2">
                                    <Label htmlFor="nom" className="text-xs font-semibold">
                                        Nom de famille <span className="text-rose-500">*</span>
                                    </Label>
                                    <Input
                                        id="nom"
                                        required
                                        value={formData.nom}
                                        onChange={(e) => setFormData({ ...formData, nom: e.target.value.toUpperCase() })}
                                        placeholder="ex: FOKOU"
                                        className="mt-1 h-9 text-sm uppercase"
                                    />
                                    {errors.nom && <p className="text-rose-500 text-xs mt-1">{errors.nom}</p>}
                                </div>

                                <div className="col-span-2">
                                    <Label htmlFor="prenom" className="text-xs font-semibold">
                                        Prénom(s)
                                    </Label>
                                    <Input
                                        id="prenom"
                                        value={formData.prenom}
                                        onChange={(e) => setFormData({ ...formData, prenom: e.target.value })}
                                        placeholder="ex: Paul Alexandre"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.prenom && <p className="text-rose-500 text-xs mt-1">{errors.prenom}</p>}
                                </div>

                                {/* ─── Ligne 2 : 4 champs biographiques alignés naturellement ─── */}
                                <div>
                                    <Label htmlFor="date_naissance" className="text-xs font-semibold">
                                        Date de naissance
                                    </Label>
                                    <Input
                                        id="date_naissance"
                                        type="date"
                                        value={formData.date_naissance ? formatDateForInput(formData.date_naissance) : ''}
                                        onChange={(e) => setFormData({ ...formData, date_naissance: e.target.value })}
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.date_naissance && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.date_naissance}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="lieu_naissance" className="text-xs font-semibold">
                                        Lieu de naissance
                                    </Label>
                                    <Input
                                        id="lieu_naissance"
                                        value={formData.lieu_naissance}
                                        onChange={(e) => setFormData({ ...formData, lieu_naissance: e.target.value })}
                                        placeholder="ex: Bafoussam"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.lieu_naissance && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.lieu_naissance}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="sexe" className="text-xs font-semibold">
                                        Genre <span className="text-[10px] font-normal text-muted-foreground">(Optionnel)</span>
                                    </Label>
                                    <Select
                                        value={formData.sexe || 'NONE'}
                                        onValueChange={(val: string) =>
                                            setFormData({ ...formData, sexe: (val === 'NONE' ? '' : val) as '' | 'M' | 'F' })
                                        }
                                    >
                                        <SelectTrigger id="sexe" className="mt-1 h-9 text-sm">
                                            <SelectValue placeholder="Non défini (À compléter)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="NONE">Non défini (À compléter)</SelectItem>
                                            <SelectItem value="M">Masculin (Garçon)</SelectItem>
                                            <SelectItem value="F">Féminin (Fille)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.sexe && <p className="text-rose-500 text-xs mt-1">{errors.sexe}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="nationalite" className="text-xs font-semibold">
                                        Nationalité
                                    </Label>
                                    <Input
                                        id="nationalite"
                                        value={formData.nationalite}
                                        onChange={(e) => setFormData({ ...formData, nationalite: e.target.value.toUpperCase() })}
                                        placeholder="ex: CAMEROUNAISE"
                                        className="mt-1 h-9 text-sm uppercase"
                                    />
                                    {errors.nationalite && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.nationalite}</p>
                                    )}
                                </div>

                                {/* ─── Ligne 3 : Matricules partagés équitablement sur 2×2 cols (matricule interne non cliquable) ─── */}
                                <div className="col-span-2">
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="text-xs font-semibold text-slate-700">
                                            Matricule interne
                                        </span>
                                        <span className="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Attribué automatiquement
                                        </span>
                                    </div>
                                    <div className="cursor-not-allowed select-none">
                                        <Input
                                            id="matricule"
                                            readOnly
                                            disabled
                                            aria-readonly="true"
                                            tabIndex={-1}
                                            value={formData.matricule}
                                            placeholder="ex: ESP-PRI-2026-0001 (Attribué automatiquement)"
                                            className="h-9 text-sm font-mono uppercase bg-slate-100/80 text-slate-500 border-dashed focus-visible:ring-0 pointer-events-none select-none disabled:opacity-90"
                                        />
                                    </div>
                                    <p className="text-[11px] text-muted-foreground mt-1">
                                        Format officiel structuré <code>[CODE_CAMPUS]-[ANNÉE]-[0001]</code>. Généré et garanti unique selon l'établissement de la classe choisie.
                                    </p>
                                    {errors.matricule && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.matricule}</p>
                                    )}
                                </div>

                                <div className="col-span-2">
                                    <div className="flex items-center justify-between mb-1">
                                        <Label htmlFor="matricule_national" className="text-xs font-semibold">
                                            Matricule National (MINESEC / Examens)
                                        </Label>
                                        <span className="text-[10px] text-muted-foreground font-normal">Optionnel</span>
                                    </div>
                                    <Input
                                        id="matricule_national"
                                        value={formData.matricule_national}
                                        onChange={(e) => setFormData({ ...formData, matricule_national: e.target.value })}
                                        placeholder="Optionnel - Matricule National"
                                        className="h-9 text-sm font-mono uppercase"
                                    />
                                    <p className="text-[11px] text-muted-foreground mt-1">
                                        Identifiant national de l'élève délivré par le ministère pour les examens officiels.
                                    </p>
                                    {errors.matricule_national && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.matricule_national}</p>
                                    )}
                                </div>

                                {/* ═══════════════════════════════════════════════════════════
                                     Sous-section Affectation Scolaire — grille 4 colonnes :
                                     Ligne 1 : Année | Filtre campus | Classe | Statut
                                     Ligne 2 : Redoublant (checkbox)
                                     ═══════════════════════════════════════════════════════════ */}
                                <div className="col-span-2 sm:col-span-4 pt-4 border-t border-slate-200">
                                    <div className="flex items-center gap-2 mb-3">
                                        <GraduationCap className="size-4 text-indigo-600" />
                                        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                                            Affectation Scolaire & Inscription
                                        </h3>
                                    </div>

                                    {/* ─── 4 sélecteurs avec espacement aéré sans chevauchement ─── */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                        <div>
                                            <Label htmlFor="annee_scolaire_id" className="text-xs font-semibold">
                                                Année Scolaire <span className="text-rose-500">*</span>
                                            </Label>
                                            <Select
                                                value={formData.annee_scolaire_id}
                                                onValueChange={(val) => setFormData({ ...formData, annee_scolaire_id: val })}
                                            >
                                                <SelectTrigger id="annee_scolaire_id" className="mt-1 h-9 text-sm">
                                                    <SelectValue placeholder="Sélectionner l'année" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {anneesScolaires.map((annee) => {
                                                        const codeAnnee = annee.code || annee.nom.replace(/^année\s*scolaire\s*/i, '').trim();
                                                        return (
                                                            <SelectItem key={annee.id} value={String(annee.id)}>
                                                                {codeAnnee} {annee.is_current ? '(En cours)' : ''}
                                                            </SelectItem>
                                                        );
                                                    })}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="min-w-0">
                                            <Label htmlFor="filtre_etablissement" className="text-xs font-semibold">
                                                Filtre par Campus
                                            </Label>
                                            <div className="mt-1">
                                                <SearchableSelect
                                                    id="filtre_etablissement"
                                                    options={etablissementOptions}
                                                    value={selectedEtabId}
                                                    onValueChange={(val) => {
                                                        setSelectedEtabId(val);
                                                        setSelectedClasseId('');
                                                    }}
                                                    placeholder="Tous les campus"
                                                    searchPlaceholder="Rechercher un campus..."
                                                    emptyMessage="Aucun campus trouvé pour"
                                                />
                                            </div>
                                        </div>

                                        <div className="min-w-0">
                                            <Label htmlFor="classe_id" className="text-xs font-semibold">
                                                Classe d'affectation <span className="text-rose-500">*</span>
                                            </Label>
                                            <div className="mt-1">
                                                <SearchableSelect
                                                    options={classeOptions}
                                                    value={selectedClasseId}
                                                    onValueChange={(val) => setSelectedClasseId(val)}
                                                    placeholder="Sélectionner la classe..."
                                                    searchPlaceholder="Rechercher une classe..."
                                                    emptyMessage="Aucune classe trouvée."
                                                />
                                            </div>
                                            {errors.classe_id && (
                                                <p className="text-rose-500 text-xs mt-1">{errors.classe_id}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor="statut" className="text-xs font-semibold">
                                                Statut inscription <span className="text-rose-500">*</span>
                                            </Label>
                                            <Select
                                                value={formData.statut}
                                                onValueChange={(val: 'validee' | 'en_attente') =>
                                                    setFormData({ ...formData, statut: val })
                                                }
                                            >
                                                <SelectTrigger id="statut" className="mt-1 h-9 text-sm">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="validee">Validée (Définitive)</SelectItem>
                                                    <SelectItem value="en_attente">En attente de pièces/frais</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    {/* ─── Checkbox Redoublant sous les sélecteurs ─── */}
                                    <div className="flex items-center space-x-3 pt-3">
                                            <Checkbox
                                                id="is_redoublant"
                                                checked={formData.is_redoublant === '1'}
                                                onCheckedChange={(checked) =>
                                                    setFormData({ ...formData, is_redoublant: checked ? '1' : '0' })
                                                }
                                            />
                                            <Label
                                                htmlFor="is_redoublant"
                                                className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                            >
                                                Redoublant
                                            </Label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    {/* BLOC 2 : FILIATION & RESPONSABLES LÉGAUX */}
                    <div className="rounded-2xl border-2 border-slate-300/90 bg-card shadow-sm overflow-visible transition-all duration-200 hover:shadow-md hover:border-slate-400">
                        {/* En-tête de bloc dégradé teal */}
                        <div className="rounded-t-2xl bg-linear-to-r from-slate-200/95 via-teal-100/70 to-slate-200/80 px-6 py-4 border-b-2 border-slate-300 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-teal-600 to-emerald-700 text-white font-bold shadow-xs shrink-0">
                                <Users className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 tracking-tight">
                                    2. Filiation & Coordonnées des Responsables Légaux
                                </h2>
                                <p className="text-xs text-slate-600">
                                    Contacts parents et tuteurs essentiels pour les alertes SMS de scolarité, discipline et portail parents.
                                </p>
                            </div>
                        </div>

                        {/* Corps du bloc Filiation */}
                        <div className="p-6 bg-card space-y-6">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                {/* Ligne 1 : Filiation — Noms des parents et tuteur */}
                                <div>
                                    <Label htmlFor="nom_pere" className="text-xs font-semibold">
                                        Nom complet du père
                                    </Label>
                                    <Input
                                        id="nom_pere"
                                        value={formData.nom_pere}
                                        onChange={(e) => setFormData({ ...formData, nom_pere: e.target.value })}
                                        placeholder="ex: FOKOU Martin"
                                        className="mt-1 h-9 text-sm"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="nom_mere" className="text-xs font-semibold">
                                        Nom complet de la mère
                                    </Label>
                                    <Input
                                        id="nom_mere"
                                        value={formData.nom_mere}
                                        onChange={(e) => setFormData({ ...formData, nom_mere: e.target.value })}
                                        placeholder="ex: NGO NDJOCK Suzanne"
                                        className="mt-1 h-9 text-sm"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="nom_tuteur" className="text-xs font-semibold">
                                        Nom du tuteur légal (si applicable)
                                    </Label>
                                    <Input
                                        id="nom_tuteur"
                                        value={formData.nom_tuteur}
                                        onChange={(e) => setFormData({ ...formData, nom_tuteur: e.target.value })}
                                        placeholder="ex: Oncle, Tante, etc."
                                        className="mt-1 h-9 text-sm"
                                    />
                                </div>

                                {/* Ligne 2 : Coordonnées de contact \u2014 Téléphone | Email | Adresse */}
                                <div>
                                    <Label htmlFor="telephone_parent" className="text-xs font-semibold">
                                        Téléphone parent / tuteur
                                    </Label>
                                    <Input
                                        id="telephone_parent"
                                        value={formData.telephone_parent}
                                        onChange={(e) => setFormData({ ...formData, telephone_parent: e.target.value })}
                                        placeholder="ex : 699 00 11 22"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    <p className="text-[11px] text-teal-600 mt-1 font-medium">
                                        SMS de présence, bulletins et accès portail parents.
                                    </p>
                                    {errors.telephone_parent && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.telephone_parent}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="email_parent" className="text-xs font-semibold">
                                        Email du parent
                                    </Label>
                                    <Input
                                        id="email_parent"
                                        type="email"
                                        value={formData.email_parent}
                                        onChange={(e) => setFormData({ ...formData, email_parent: e.target.value })}
                                        placeholder="ex: parent@domaine.com"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.email_parent && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.email_parent}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="adresse" className="text-xs font-semibold">
                                        Adresse / Quartier de résidence
                                    </Label>
                                    <Input
                                        id="adresse"
                                        value={formData.adresse}
                                        onChange={(e) => setFormData({ ...formData, adresse: e.target.value })}
                                        placeholder="ex: Quartier Bafoussam Centre"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.adresse && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.adresse}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>



                    {/* BARRE D'ACTIONS INFÉRIEURE RESPONSIVE */}
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="cursor-pointer text-xs h-10 w-full sm:w-auto"
                        >
                            <Link href="/administration/eleves">
                                <ArrowLeft className="size-4 mr-1.5" />
                                Annuler et retourner à la liste
                            </Link>
                        </Button>

                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full sm:w-auto">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={loading}
                                onClick={() => submitForm('save_and_new')}
                                className="cursor-pointer text-xs h-10 border-teal-600/40 text-teal-700 hover:bg-teal-50 font-medium w-full sm:w-auto"
                            >
                                <PlusCircle className="size-4 mr-1.5 text-teal-600" />
                                Enregistrer & Nouveau
                            </Button>

                            <Button
                                type="button"
                                disabled={loading}
                                onClick={() => submitForm('save_and_return')}
                                className="bg-teal-600 hover:bg-teal-700 text-white cursor-pointer font-semibold shadow-sm text-xs h-10 px-5 w-full sm:w-auto"
                            >
                                <Check className="size-4 mr-1.5" />
                                {loading ? 'Enregistrement...' : 'Enregistrer & Retourner'}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

/**
 * Fil d'Ariane (Breadcrumbs) pour la page de création et d'inscription d'un nouvel élève.
 */
CreateEleve.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Élèves & Inscriptions',
            href: '/administration/eleves',
        },
        {
            title: 'Nouvel Élève',
            href: '/administration/eleves/create',
        },
    ],
};

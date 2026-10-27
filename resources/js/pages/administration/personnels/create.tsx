import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Briefcase,
    Building2,
    Calendar,
    Check,
    GraduationCap,
    KeyRound,
    Mail,
    Phone,
    PlusCircle,
    Save,
    Shield,
    Sparkles,
    User,
    UserCheck,
    UserPlus,
    X,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDateForInput } from '@/lib/utils';
import type {
    AvailableUserOption,
    Departement,
    EtablissementOption,
} from '@/types/personnel';

/**
 * Propriétés transmises à la page de création d'un personnel.
 */
interface CreatePersonnelPageProps {
    /** Liste des campus / établissements scolaires disponibles */
    etablissements: EtablissementOption[];
    /** Campus sélectionné par défaut selon le contexte */
    selectedEtablissement?: EtablissementOption | null;
    /** Liste des départements pédagogiques configurés */
    departements: Departement[];
    /** Comptes utilisateurs applicatifs sans fiche personnel liée */
    availableUsers: AvailableUserOption[];
    /** Noms des rôles Spatie disponibles dans le système */
    roles: string[];
}

/**
 * Fonctions types courantes pour le personnel scolaire.
 */
const FONCTIONS_PREDEFINIES = [
    'Enseignant',
    'Directeur des Études',
    'Censeur',
    'Surveillant Général',
    'Économe / Comptable',
    'Secrétaire',
    'Documentaliste / Bibliothécaire',
    'Infirmier(ère)',
    'Psychologue scolaire',
    'Agent d\'entretien',
    'Gardien / Sécurité',
    'Chauffeur',
    'Autre',
];

/**
 * Types de contrats de travail usuels dans l'enseignement.
 */
const TYPES_CONTRAT = [
    'Permanent',
    'CDI (Contrat à Durée Indéterminée)',
    'CDD (Contrat à Durée Déterminée)',
    'Vacataire / Horaire',
    'Fonctionnaire',
    'Stagiaire',
    'Prestataire externe',
];

/**
 * Statuts administratifs applicables aux employés.
 */
const STATUTS_ADMIN = [
    'Actif',
    'En congé',
    'Suspendu',
    'Démissionnaire',
    'Retraité',
    'Licencié',
];

/**
 * Page dédiée à la création et à l'enregistrement d'un nouveau membre du personnel.
 * Offre un environnement spacieux et ergonomique avec actions multiples :
 * - Annuler (retour à l'index)
 * - Enregistrer & Nouveau (création puis réinitialisation du formulaire pour le personnel suivant)
 * - Enregistrer & Retourner (création puis redirection vers la liste du personnel)
 */
export default function CreatePersonnel({
    etablissements,
    selectedEtablissement,
    departements,
    availableUsers,
    roles,
}: CreatePersonnelPageProps) {
    // Mode de gestion du compte d'accès utilisateur : 'create' | 'link' | 'none'
    const [accountMode, setAccountMode] = useState<'create' | 'link' | 'none'>('create');

    // Établissement par défaut
    const defaultEtablissementId =
        selectedEtablissement?.id ??
        (etablissements.length > 0 ? etablissements[0].id : '');

    // Formulaire Inertia de création
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
        // 1. État Civil & Contact
        nom: '',
        prenom: '',
        sexe: 'M' as 'M' | 'F',
        date_naissance: '',
        lieu_naissance: '',
        telephone: '',
        telephone_secondaire: '',
        email: '',
        adresse: '',

        // 2. Affectation & Statut Professionnel
        etablissement_id: defaultEtablissementId,
        departement_id: '' as string | number,
        fonction_principale: 'Enseignant',
        type_contrat: 'CDI (Contrat à Durée Indéterminée)',
        date_embauche: formatDateForInput(new Date()),
        statut: 'Actif',

        // 3. Données RH & Qualification
        numero_cnps: '',
        diplome_plus_eleve: '',
        observations: '',

        // 4. Spécificités Pédagogiques Enseignant
        is_enseignant: true,
        specialite: '',
        volume_horaire_hebdo: 18,
        taux_horaire_vacation: '',

        // 5. Compte Utilisateur SYGES
        create_user_account: true,
        user_id: '' as string | number,
        user_login: '',
        user_email: '',
        user_password: '',
        user_role: 'enseignant',

        // 6. Action de redirection
        create_another: false,
    });

    /**
     * Génère automatiquement un identifiant (login) suggéré lors de la saisie du nom.
     *
     * @param {string} newNom Le nouveau nom saisi.
     */
    const handleNomChange = (newNom: string) => {
        setData((prev) => {
            const cleanNom = newNom
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]/g, '');
            const suggestedLogin = cleanNom ? `${cleanNom}${Math.floor(10 + Math.random() * 90)}` : '';

            return {
                ...prev,
                nom: newNom,
                user_login:
                    prev.user_login === '' ||
                    prev.user_login.startsWith(prev.nom.toLowerCase().slice(0, 3))
                        ? suggestedLogin
                        : prev.user_login,
            };
        });
    };

    /**
     * Traitement de la soumission du formulaire selon l'intention utilisateur.
     *
     * @param {boolean} createAnother Indique si le formulaire doit être réaffiché après création.
     */
    const submitForm = (createAnother: boolean) => {
        transform((currentData) => ({
            ...currentData,
            create_another: createAnother,
        }));

        post('/administration/personnels', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Personnel enregistré avec succès !', {
                    description: createAnother
                        ? `${data.nom} a été ajouté. Le formulaire est réinitialisé pour renseigner le membre suivant.`
                        : `${data.nom} a été ajouté au registre du personnel.`,
                    style: {
                        backgroundColor: '#10b981',
                        color: '#ffffff',
                        border: '1px solid #059669',
                    },
                });

                if (createAnother) {
                    reset();
                    clearErrors();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },
            onError: () => {
                toast.error('Erreur lors de l\'enregistrement', {
                    description: 'Veuillez vérifier les champs signalés en rouge.',
                });
            },
        });
    };

    return (
        <>
            <Head title="Nouveau membre du personnel - Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-7xl mx-auto w-full pb-20">
                {/* EN-TÊTE DE PAGE AVEC NAVIGATION RETOUR ET BOUTONS D'ACTION */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b pb-5">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                            <Link
                                href="/administration/personnels"
                                className="inline-flex items-center gap-1 hover:text-foreground transition-colors font-medium cursor-pointer"
                            >
                                <ArrowLeft className="size-3.5" />
                                <span>Retour au registre du personnel</span>
                            </Link>
                        </div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-900/50">
                                <Briefcase className="size-6" />
                            </div>
                            Nouveau Membre du Personnel
                        </h1>
                        <p className="text-xs sm:text-sm text-muted-foreground">
                            Renseignez la fiche administrative, contractuelle et pédagogique. Vous pouvez simultanément créer un compte d'accès à SYGES.
                        </p>
                    </div>

                    {/* ACTIONS RAPIDES DU HAUT */}
                    <div className="flex items-center gap-2 flex-wrap sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="cursor-pointer text-xs h-9"
                        >
                            <Link href="/administration/personnels">
                                <X className="size-3.5 mr-1" />
                                Annuler
                            </Link>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => submitForm(true)}
                            className="cursor-pointer text-xs h-9 border-indigo-600/40 text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 font-medium"
                        >
                            <PlusCircle className="size-4 mr-1.5 text-indigo-600 dark:text-indigo-400" />
                            Enregistrer & Nouveau
                        </Button>

                        <Button
                            type="button"
                            disabled={processing}
                            onClick={() => submitForm(false)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer font-semibold shadow-sm text-xs h-9"
                        >
                            <Check className="size-4 mr-1.5" />
                            {processing ? 'Enregistrement...' : 'Enregistrer & Retourner'}
                        </Button>
                    </div>
                </div>

                {/* FORMULAIRE DÉCOUPÉ EN CARTES SPÉCIALISÉES */}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submitForm(false);
                    }}
                    className="space-y-8"
                >
                    {/* SECTION 1 : ÉTAT CIVIL & COORDONNÉES */}
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        {/* En-tête de bloc avec bannière dégradée et badge thématique */}
                        <div className="bg-linear-to-r from-slate-200/95 via-sky-100/70 to-slate-200/80 dark:from-slate-800 dark:via-sky-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-sky-600 to-blue-700 text-white font-bold shadow-xs shrink-0">
                                <User className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                    1. État Civil & Coordonnées de Contact
                                </h2>
                                <p className="text-xs text-slate-600 dark:text-slate-400">
                                    Informations d'identité civile, date et lieu de naissance, et coordonnées officielles de l'employé.
                                </p>
                            </div>
                        </div>

                        {/* Corps du bloc État Civil (3 lignes max) */}
                        <div className="p-6 bg-card space-y-4">
                            {/* Ligne 1 : Nom, Prénom, Genre */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <Label htmlFor="nom" className="text-xs font-semibold">
                                        Nom de famille <span className="text-rose-500">*</span>
                                    </Label>
                                    <Input
                                        id="nom"
                                        required
                                        value={data.nom}
                                        onChange={(e) => handleNomChange(e.target.value)}
                                        placeholder="ex: KOUASSI"
                                        className="mt-1 h-9 text-sm uppercase"
                                    />
                                    {errors.nom && <p className="text-rose-500 text-xs mt-1">{errors.nom}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="prenom" className="text-xs font-semibold">
                                        Prénom(s)
                                    </Label>
                                    <Input
                                        id="prenom"
                                        value={data.prenom}
                                        onChange={(e) => setData('prenom', e.target.value)}
                                        placeholder="ex: Jean-Marc"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.prenom && <p className="text-rose-500 text-xs mt-1">{errors.prenom}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="sexe" className="text-xs font-semibold">
                                        Genre <span className="text-rose-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.sexe}
                                        onValueChange={(val: 'M' | 'F') => setData('sexe', val)}
                                    >
                                        <SelectTrigger id="sexe" className="mt-1 h-9 text-sm">
                                            <SelectValue placeholder="Choisir le genre" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="M">Masculin (M)</SelectItem>
                                            <SelectItem value="F">Féminin (F)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.sexe && <p className="text-rose-500 text-xs mt-1">{errors.sexe}</p>}
                                </div>
                            </div>

                            {/* Ligne 2 : Date naissance, Lieu naissance, Téléphone principal */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <Label htmlFor="date_naissance" className="text-xs font-semibold">
                                        Date de naissance
                                    </Label>
                                    <Input
                                        id="date_naissance"
                                        type="date"
                                        value={formatDateForInput(data.date_naissance)}
                                        onChange={(e) => setData('date_naissance', e.target.value)}
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.date_naissance && <p className="text-rose-500 text-xs mt-1">{errors.date_naissance}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="lieu_naissance" className="text-xs font-semibold">
                                        Lieu de naissance
                                    </Label>
                                    <Input
                                        id="lieu_naissance"
                                        value={data.lieu_naissance}
                                        onChange={(e) => setData('lieu_naissance', e.target.value)}
                                        placeholder="ex: Yaoundé, Douala..."
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.lieu_naissance && <p className="text-rose-500 text-xs mt-1">{errors.lieu_naissance}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="telephone" className="text-xs font-semibold">
                                        Téléphone principal <span className="text-rose-500">*</span>
                                    </Label>
                                    <Input
                                        id="telephone"
                                        required
                                        value={data.telephone}
                                        onChange={(e) => setData('telephone', e.target.value)}
                                        placeholder="ex: 699001122"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.telephone && <p className="text-rose-500 text-xs mt-1">{errors.telephone}</p>}
                                </div>
                            </div>

                            {/* Ligne 3 : Téléphone secondaire, Email, Adresse */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <Label htmlFor="telephone_secondaire" className="text-xs font-semibold">
                                        Téléphone secondaire / WhatsApp
                                    </Label>
                                    <Input
                                        id="telephone_secondaire"
                                        value={data.telephone_secondaire}
                                        onChange={(e) => setData('telephone_secondaire', e.target.value)}
                                        placeholder="ex: 677001122"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.telephone_secondaire && <p className="text-rose-500 text-xs mt-1">{errors.telephone_secondaire}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="email" className="text-xs font-semibold">
                                        Email personnel / de contact
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => {
                                            setData('email', e.target.value);
                                            if (accountMode === 'create' && data.user_email === '') {
                                                setData('user_email', e.target.value);
                                            }
                                        }}
                                        placeholder="ex: jean.kouassi@syges.pro"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.email && <p className="text-rose-500 text-xs mt-1">{errors.email}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="adresse" className="text-xs font-semibold">
                                        Adresse géographique de résidence
                                    </Label>
                                    <Input
                                        id="adresse"
                                        value={data.adresse}
                                        onChange={(e) => setData('adresse', e.target.value)}
                                        placeholder="ex: Quartier Bonapriso, Rue 12"
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.adresse && <p className="text-rose-500 text-xs mt-1">{errors.adresse}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* SECTION 2 : AFFECTATION & STATUT PROFESSIONNEL (2 lignes max) */}
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        {/* En-tête de bloc avec bannière dégradée et badge thématique */}
                        <div className="bg-linear-to-r from-slate-200/95 via-indigo-100/70 to-slate-200/80 dark:from-slate-800 dark:via-indigo-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-indigo-600 to-violet-700 text-white font-bold shadow-xs shrink-0">
                                <Building2 className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                    2. Affectation Institutionnelle & Contrat de Travail
                                </h2>
                                <p className="text-xs text-slate-600 dark:text-slate-400">
                                    Campus de rattachement, département académique, type de contrat, fonction administrative et statut.
                                </p>
                            </div>
                        </div>

                        {/* Corps du bloc Affectation & Contrat (2 lignes max) */}
                        <div className="p-6 bg-card space-y-4">
                            {/* Ligne 1 : Établissement, Département, Fonction principale */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="min-w-0">
                                    <Label htmlFor="etablissement_id" className="text-xs font-semibold">
                                        Établissement / Campus d'affectation <span className="text-rose-500">*</span>
                                    </Label>
                                    <Select
                                        value={String(data.etablissement_id)}
                                        onValueChange={(val) => setData('etablissement_id', Number(val))}
                                    >
                                        <SelectTrigger id="etablissement_id" className="mt-1 h-9 text-sm w-full min-w-0">
                                            <SelectValue placeholder="Sélectionner le campus" />
                                        </SelectTrigger>
                                        <SelectContent className="max-w-[calc(100vw-2rem)]">
                                            {etablissements.map((etab) => (
                                                <SelectItem key={etab.id} value={String(etab.id)} title={`${etab.nom} (${etab.code})`}>
                                                    <span className="truncate block max-w-xs sm:max-w-sm">{etab.nom} ({etab.code})</span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.etablissement_id && <p className="text-rose-500 text-xs mt-1">{errors.etablissement_id}</p>}
                                </div>

                                <div className="min-w-0">
                                    <Label htmlFor="departement_id" className="text-xs font-semibold">
                                        Département pédagogique (discipline)
                                    </Label>
                                    <Select
                                        value={data.departement_id ? String(data.departement_id) : 'none'}
                                        onValueChange={(val) => setData('departement_id', val === 'none' ? '' : Number(val))}
                                    >
                                        <SelectTrigger id="departement_id" className="mt-1 h-9 text-sm w-full min-w-0">
                                            <SelectValue placeholder="Aucun département" />
                                        </SelectTrigger>
                                        <SelectContent className="max-w-[calc(100vw-2rem)]">
                                            <SelectItem value="none">Aucun département</SelectItem>
                                            {departements.map((dep) => (
                                                <SelectItem key={dep.id} value={String(dep.id)} title={`${dep.nom} ${dep.code ? `(${dep.code})` : ''}`}>
                                                    <span className="truncate block max-w-xs sm:max-w-sm">{dep.nom} {dep.code ? `(${dep.code})` : ''}</span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.departement_id && <p className="text-rose-500 text-xs mt-1">{errors.departement_id}</p>}
                                </div>

                                <div className="min-w-0">
                                    <Label htmlFor="fonction_principale" className="text-xs font-semibold">
                                        Fonction principale <span className="text-rose-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.fonction_principale}
                                        onValueChange={(val) => {
                                            setData((prev) => ({
                                                ...prev,
                                                fonction_principale: val,
                                                is_enseignant: val === 'Enseignant' ? true : prev.is_enseignant,
                                                user_role: val === 'Enseignant' ? 'enseignant' : prev.user_role,
                                            }));
                                        }}
                                    >
                                        <SelectTrigger id="fonction_principale" className="mt-1 h-9 text-sm w-full min-w-0">
                                            <SelectValue placeholder="Choisir une fonction" />
                                        </SelectTrigger>
                                        <SelectContent className="max-w-[calc(100vw-2rem)]">
                                            {FONCTIONS_PREDEFINIES.map((f) => (
                                                <SelectItem key={f} value={f}>
                                                    {f}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.fonction_principale && (
                                        <p className="text-rose-500 text-xs mt-1">{errors.fonction_principale}</p>
                                    )}
                                </div>
                            </div>

                                                        {/* Ligne 2 : Type de contrat, Date d'embauche, Statut administratif */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="min-w-0">
                                    <Label htmlFor="type_contrat" className="text-xs font-semibold">
                                        Type de contrat de travail <span className="text-rose-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.type_contrat}
                                        onValueChange={(val) => setData('type_contrat', val)}
                                    >
                                        <SelectTrigger id="type_contrat" className="mt-1 h-9 text-sm w-full min-w-0">
                                            <SelectValue placeholder="Sélectionner le contrat" />
                                        </SelectTrigger>
                                        <SelectContent className="max-w-[calc(100vw-2rem)]">
                                            {TYPES_CONTRAT.map((c) => (
                                                <SelectItem key={c} value={c} title={c}>
                                                    <span className="truncate block max-w-xs">{c}</span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.type_contrat && <p className="text-rose-500 text-xs mt-1">{errors.type_contrat}</p>}
                                </div>

                                <div className="min-w-0">
                                    <Label htmlFor="date_embauche" className="text-xs font-semibold">
                                        Date de prise de service / Embauche <span className="text-rose-500">*</span>
                                    </Label>
                                    <Input
                                        id="date_embauche"
                                        type="date"
                                        required
                                        value={formatDateForInput(data.date_embauche)}
                                        onChange={(e) => setData('date_embauche', e.target.value)}
                                        className="mt-1 h-9 text-sm"
                                    />
                                    {errors.date_embauche && <p className="text-rose-500 text-xs mt-1">{errors.date_embauche}</p>}
                                </div>

                                <div className="min-w-0">
                                    <Label htmlFor="statut" className="text-xs font-semibold">
                                        Statut administratif initial <span className="text-rose-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.statut}
                                        onValueChange={(val) => setData('statut', val)}
                                    >
                                        <SelectTrigger id="statut" className="mt-1 h-9 text-sm w-full min-w-0">
                                            <SelectValue placeholder="Sélectionner le statut" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {STATUTS_ADMIN.map((s) => (
                                                <SelectItem key={s} value={s}>
                                                    {s}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.statut && <p className="text-rose-500 text-xs mt-1">{errors.statut}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* SECTION 3 : QUALIFICATION & PÉDAGOGIE */}
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        {/* En-tête de bloc avec bannière dégradée et badge thématique */}
                        <div className="bg-linear-to-r from-slate-200/95 via-amber-100/70 to-slate-200/80 dark:from-slate-800 dark:via-amber-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-amber-500 to-orange-600 text-white font-bold shadow-xs shrink-0">
                                <GraduationCap className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                    3. Qualification & Paramètres Pédagogiques
                                </h2>
                                <p className="text-xs text-slate-600 dark:text-slate-400">
                                    Niveau académique certifié, immatriculation sociale, profil enseignant, volume horaire et taux de vacation.
                                </p>
                            </div>
                        </div>

                        {/* Corps du bloc Qualification & Pédagogie */}
                        <div className="p-6 bg-card space-y-6">

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="diplome_plus_eleve" className="text-xs font-semibold">
                                    Diplôme le plus élevé certifié
                                </Label>
                                <Input
                                    id="diplome_plus_eleve"
                                    value={data.diplome_plus_eleve}
                                    onChange={(e) => setData('diplome_plus_eleve', e.target.value)}
                                    placeholder="ex: Master II, Doctorat, CAPES, BAC+5..."
                                    className="mt-1 h-9 text-sm"
                                />
                                {errors.diplome_plus_eleve && <p className="text-rose-500 text-xs mt-1">{errors.diplome_plus_eleve}</p>}
                            </div>

                            <div>
                                <Label htmlFor="numero_cnps" className="text-xs font-semibold">
                                    N° de Sécurité Sociale / CNPS
                                </Label>
                                <Input
                                    id="numero_cnps"
                                    value={data.numero_cnps}
                                    onChange={(e) => setData('numero_cnps', e.target.value)}
                                    placeholder="ex: 1234567890"
                                    className="mt-1 h-9 text-sm"
                                />
                                {errors.numero_cnps && <p className="text-rose-500 text-xs mt-1">{errors.numero_cnps}</p>}
                            </div>
                        </div>

                        {/* Option Enseignant */}
                        <div className="p-4 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-200/60 dark:border-indigo-900/40 space-y-4">
                            <div className="flex items-center gap-2.5">
                                <Checkbox
                                    id="is_enseignant"
                                    checked={data.is_enseignant}
                                    onCheckedChange={(checked) => setData('is_enseignant', !!checked)}
                                />
                                <Label htmlFor="is_enseignant" className="text-xs font-semibold text-foreground cursor-pointer">
                                    Ce membre du personnel est un enseignant (dispense des cours et des évaluations)
                                </Label>
                            </div>

                            {data.is_enseignant && (
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-indigo-200/50 dark:border-indigo-900/30">
                                    <div>
                                        <Label htmlFor="specialite" className="text-xs font-semibold">
                                            Discipline / Spécialité principale
                                        </Label>
                                        <Input
                                            id="specialite"
                                            value={data.specialite}
                                            onChange={(e) => setData('specialite', e.target.value)}
                                            placeholder="ex: Mathématiques, Sciences Physiques..."
                                            className="mt-1 h-9 text-sm bg-background"
                                        />
                                        {errors.specialite && <p className="text-rose-500 text-xs mt-1">{errors.specialite}</p>}
                                    </div>

                                    <div>
                                        <Label htmlFor="volume_horaire_hebdo" className="text-xs font-semibold">
                                            Volume horaire légal / contractuel (h/semaine)
                                        </Label>
                                        <Input
                                            id="volume_horaire_hebdo"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.5"
                                            value={data.volume_horaire_hebdo}
                                            onChange={(e) => setData('volume_horaire_hebdo', Number(e.target.value))}
                                            className="mt-1 h-9 text-sm bg-background"
                                        />
                                        {errors.volume_horaire_hebdo && <p className="text-rose-500 text-xs mt-1">{errors.volume_horaire_hebdo}</p>}
                                    </div>

                                    <div>
                                        <Label htmlFor="taux_horaire_vacation" className="text-xs font-semibold">
                                            Taux horaire de vacation (FCFA / heure)
                                        </Label>
                                        <Input
                                            id="taux_horaire_vacation"
                                            type="number"
                                            min="0"
                                            step="100"
                                            value={data.taux_horaire_vacation}
                                            onChange={(e) => setData('taux_horaire_vacation', e.target.value)}
                                            placeholder="ex: 5000 (optionnel)"
                                            className="mt-1 h-9 text-sm bg-background"
                                        />
                                        {errors.taux_horaire_vacation && <p className="text-rose-500 text-xs mt-1">{errors.taux_horaire_vacation}</p>}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="observations" className="text-xs font-semibold">
                                Observations ou notes RH internes
                            </Label>
                            <Textarea
                                id="observations"
                                rows={3}
                                value={data.observations}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('observations', e.target.value)}
                                placeholder="Mentions particulières, antécédents ou dispositions contractuelles spécifiques..."
                                className="mt-1 text-xs"
                            />
                            {errors.observations && <p className="text-rose-500 text-xs mt-1">{errors.observations}</p>}
                        </div>
                    </div>
                </div>

                    {/* SECTION 4 : COMPTE D'ACCÈS LOGICIEL SYGES */}
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        {/* En-tête de bloc avec bannière dégradée et badge thématique */}
                        <div className="bg-linear-to-r from-slate-200/95 via-purple-100/70 to-slate-200/80 dark:from-slate-800 dark:via-purple-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-purple-600 to-indigo-700 text-white font-bold shadow-xs shrink-0">
                                <KeyRound className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                    4. Compte d'Accès Logiciel SYGES
                                </h2>
                                <p className="text-xs text-slate-600 dark:text-slate-400">
                                    Génération automatique des identifiants de connexion, attribution des rôles applicatifs et sécurité du compte.
                                </p>
                            </div>
                        </div>

                        {/* Corps du bloc Compte d'accès SYGES */}
                        <div className="p-6 bg-card space-y-6">

                        {/* Sélecteur de mode de compte */}
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <button
                                type="button"
                                onClick={() => {
                                    setAccountMode('create');
                                    setData('create_user_account', true);
                                    setData('user_id', '');
                                }}
                                className={`p-4 rounded-xl border text-left cursor-pointer transition-all ${
                                    accountMode === 'create'
                                        ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/30 ring-1 ring-purple-600'
                                        : 'border-border hover:bg-muted/40'
                                }`}
                            >
                                <div className="flex items-center gap-2 font-semibold text-xs text-foreground">
                                    <Sparkles className="size-4 text-purple-600 dark:text-purple-400" />
                                    Créer un nouveau compte
                                </div>
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    Génère automatiquement les identifiants de connexion pour cet employé.
                                </p>
                            </button>

                            <button
                                type="button"
                                onClick={() => {
                                    setAccountMode('link');
                                    setData('create_user_account', false);
                                }}
                                className={`p-4 rounded-xl border text-left cursor-pointer transition-all ${
                                    accountMode === 'link'
                                        ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/30 ring-1 ring-purple-600'
                                        : 'border-border hover:bg-muted/40'
                                }`}
                            >
                                <div className="flex items-center gap-2 font-semibold text-xs text-foreground">
                                    <UserCheck className="size-4 text-purple-600 dark:text-purple-400" />
                                    Lier un compte existant
                                </div>
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    Associer la fiche à un compte utilisateur déjà présent dans l'application.
                                </p>
                            </button>

                            <button
                                type="button"
                                onClick={() => {
                                    setAccountMode('none');
                                    setData('create_user_account', false);
                                    setData('user_id', '');
                                }}
                                className={`p-4 rounded-xl border text-left cursor-pointer transition-all ${
                                    accountMode === 'none'
                                        ? 'border-slate-500 bg-slate-50/50 dark:bg-slate-900/50 ring-1 ring-slate-500'
                                        : 'border-border hover:bg-muted/40'
                                }`}
                            >
                                <div className="flex items-center gap-2 font-semibold text-xs text-foreground">
                                    <Shield className="size-4 text-muted-foreground" />
                                    Aucun compte d'accès
                                </div>
                                <p className="text-[11px] text-muted-foreground mt-1">
                                    Personnel de service ou sans besoin de se connecter à SYGES.
                                </p>
                            </button>
                        </div>

                        {/* Formulaire de création de compte utilisateur */}
                        {accountMode === 'create' && (
                            <div className="p-4 rounded-xl bg-muted/40 border border-border space-y-4 animate-in fade-in-50">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <Label htmlFor="user_login" className="text-xs font-semibold">
                                            Identifiant de connexion (Login)
                                        </Label>
                                        <Input
                                            id="user_login"
                                            value={data.user_login}
                                            onChange={(e) => setData('user_login', e.target.value.toLowerCase())}
                                            placeholder="Par défaut : matricule enseignant (ex: 26PLYCEXC0001)"
                                            className="mt-1 h-9 text-sm font-mono"
                                        />
                                        <p className="text-[11px] text-muted-foreground mt-1">
                                            Si vous laissez vide, le login correspondra automatiquement au matricule de l'enseignant.
                                        </p>
                                        {errors.user_login && <p className="text-rose-500 text-xs mt-1">{errors.user_login}</p>}
                                    </div>

                                    <div>
                                        <Label htmlFor="user_email" className="text-xs font-semibold">
                                            Email de connexion au portail
                                        </Label>
                                        <Input
                                            id="user_email"
                                            type="email"
                                            value={data.user_email}
                                            onChange={(e) => setData('user_email', e.target.value)}
                                            placeholder="Par défaut : email de contact ou auto-généré"
                                            className="mt-1 h-9 text-sm"
                                        />
                                        <p className="text-[11px] text-muted-foreground mt-1">
                                            Optionnel : si vide, l'email de contact ou une adresse interne sera utilisée.
                                        </p>
                                        {errors.user_email && <p className="text-rose-500 text-xs mt-1">{errors.user_email}</p>}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <Label htmlFor="user_password" className="text-xs font-semibold">
                                            Mot de passe initial
                                        </Label>
                                        <Input
                                            id="user_password"
                                            type="text"
                                            value={data.user_password}
                                            onChange={(e) => setData('user_password', e.target.value)}
                                            placeholder="Par défaut : matricule de l'enseignant"
                                            className="mt-1 h-9 text-sm font-mono"
                                        />
                                        <p className="text-[11px] text-muted-foreground mt-1">
                                            Si vous laissez vide, le mot de passe initial sera automatiquement le matricule de l'enseignant (ex: <code>26PLYCEXC0001</code>). L'utilisateur pourra le modifier depuis son compte.
                                        </p>
                                        {errors.user_password && <p className="text-rose-500 text-xs mt-1">{errors.user_password}</p>}
                                    </div>

                                    <div>
                                        <Label htmlFor="user_role" className="text-xs font-semibold">
                                            Rôle d'habilitation SYGES
                                        </Label>
                                        <Select
                                            value={data.user_role}
                                            onValueChange={(val) => setData('user_role', val)}
                                        >
                                            <SelectTrigger id="user_role" className="mt-1 h-9 text-sm">
                                                <SelectValue placeholder="Choisir un rôle" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {roles.map((r) => (
                                                    <SelectItem key={r} value={r}>
                                                        {r}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.user_role && <p className="text-rose-500 text-xs mt-1">{errors.user_role}</p>}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Liaison avec un compte utilisateur existant */}
                        {accountMode === 'link' && (
                            <div className="p-4 rounded-xl bg-muted/40 border border-border space-y-3 animate-in fade-in-50">
                                <Label htmlFor="user_id" className="text-xs font-semibold">
                                    Sélectionnez le compte utilisateur SYGES à associer
                                </Label>
                                <Select
                                    value={data.user_id ? String(data.user_id) : ''}
                                    onValueChange={(val) => setData('user_id', Number(val))}
                                >
                                    <SelectTrigger id="user_id" className="h-9 text-sm">
                                        <SelectValue placeholder="Choisir un compte utilisateur disponible" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableUsers.length === 0 ? (
                                            <SelectItem value="none" disabled>
                                                Aucun compte utilisateur disponible non associé
                                            </SelectItem>
                                        ) : (
                                            availableUsers.map((u) => (
                                                <SelectItem key={u.id} value={String(u.id)}>
                                                    {u.name} (login: {u.login}, email: {u.email})
                                                </SelectItem>
                                            ))
                                        )}
                                    </SelectContent>
                                </Select>
                                {errors.user_id && <p className="text-rose-500 text-xs mt-1">{errors.user_id}</p>}
                            </div>
                        )}
                        </div>
                    </div>

                    {/* BARRE D'ACTIONS INFÉRIEURE */}
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-6 border-t border-border/60">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="cursor-pointer text-xs h-9 w-full sm:w-auto"
                        >
                            <Link href="/administration/personnels">
                                <X className="size-3.5 mr-1.5" />
                                Annuler
                            </Link>
                        </Button>

                        <div className="flex items-center gap-2.5 w-full sm:w-auto">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => submitForm(true)}
                                className="cursor-pointer text-xs h-9 flex-1 sm:flex-initial border-indigo-600/40 text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 font-medium"
                            >
                                <PlusCircle className="size-4 mr-1.5 text-indigo-600 dark:text-indigo-400" />
                                Enregistrer & Nouveau
                            </Button>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer font-semibold shadow-sm text-xs h-9 flex-1 sm:flex-initial"
                            >
                                <Check className="size-4 mr-1.5" />
                                {processing ? 'Enregistrement...' : 'Enregistrer & Retourner'}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

/**
 * Fil d'Ariane (Breadcrumbs) pour la page de création d'un membre du personnel.
 */
CreatePersonnel.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Personnel & RH',
            href: '/administration/personnels',
        },
        {
            title: 'Nouveau Personnel',
            href: '/administration/personnels/create',
        },
    ],
};

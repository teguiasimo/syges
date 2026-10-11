import { useForm } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    Calendar,
    GraduationCap,
    KeyRound,
    Mail,
    Phone,
    Plus,
    Shield,
    Sparkles,
    User,
    UserCheck,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    AvailableUserOption,
    Departement,
    EtablissementOption,
} from '@/types/personnel';

/**
 * Propriétés attendues par la modale de création d'un membre du personnel.
 */
interface CreatePersonnelModalProps {
    /** Indique si la modale est ouverte */
    isOpen: boolean;
    /** Callback de fermeture de la modale */
    onClose: () => void;
    /** Liste des établissements scolaires disponibles */
    etablissements: EtablissementOption[];
    /** Liste des départements pédagogiques */
    departements: Departement[];
    /** Comptes utilisateurs existants sans fiche de personnel */
    availableUsers: AvailableUserOption[];
    /** Noms des rôles Spatie disponibles */
    roles: string[];
}

/**
 * Fonctions types prédéfinies pour le personnel scolaire.
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
 * Types de contrats de travail usuels.
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
 * Statuts administratifs possibles.
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
 * Modale de création d'un membre du personnel avec support de création
 * simultanée du compte utilisateur d'accès logiciel (Sections Gesco 7 & 8 unifiées).
 */
export function CreatePersonnelModal({
    isOpen,
    onClose,
    etablissements,
    departements,
    availableUsers,
    roles,
}: CreatePersonnelModalProps) {
    // Mode de gestion du compte utilisateur : 'create' | 'link' | 'none'
    const [accountMode, setAccountMode] = useState<'create' | 'link' | 'none'>('create');

    const defaultEtablissementId = etablissements.length > 0 ? etablissements[0].id : 1;

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        // État Civil
        nom: '',
        prenom: '',
        sexe: 'M' as 'M' | 'F',
        date_naissance: '',
        lieu_naissance: '',
        telephone: '',
        telephone_secondaire: '',
        email: '',
        adresse: '',

        // Contrat & Affectation
        etablissement_id: defaultEtablissementId,
        departement_id: '' as string | number,
        fonction_principale: 'Enseignant',
        type_contrat: 'CDI (Contrat à Durée Indéterminée)',
        date_embauche: new Date().toISOString().split('T')[0],
        statut: 'Actif',
        numero_cnps: '',
        diplome_plus_eleve: '',
        observations: '',

        // Pédagogie / Enseignement
        is_enseignant: true,
        specialite: '',
        volume_horaire_hebdo: 18,
        taux_horaire_vacation: '',

        // Compte Utilisateur
        create_user_account: true,
        user_id: '' as string | number,
        user_login: '',
        user_email: '',
        user_password: '',
        user_role: 'enseignant',
    });

    /**
     * Génère automatiquement une suggestion d'identifiant (login) à partir du nom saisi.
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
                user_login: prev.user_login === '' || prev.user_login.startsWith(prev.nom.toLowerCase().slice(0, 3)) ? suggestedLogin : prev.user_login,
            };
        });
    };

    /**
     * Soumission du formulaire d'enregistrement.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/administration/personnels', {
            onSuccess: () => {
                toast.success('Personnel enregistré avec succès !', {
                    description: `${data.nom} ${data.prenom} a été ajouté au registre du personnel.`,
                });
                reset();
                clearErrors();
                onClose();
            },
            onError: () => {
                toast.error('Erreur lors de l\'enregistrement', {
                    description: 'Veuillez vérifier les champs signalés en rouge.',
                });
            },
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-xl font-bold flex items-center gap-2 text-foreground">
                        <div className="p-2 rounded-lg bg-teal-50 dark:bg-teal-950/50 text-teal-600 border border-teal-200 dark:border-teal-900/50">
                            <Briefcase className="size-5" />
                        </div>
                        Enregistrer un nouveau membre du personnel
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm text-muted-foreground mt-1">
                        Renseignez la fiche administrative, contractuelle et pédagogique. Vous pouvez simultanément lui créer un compte d'accès à SYGES.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6 pt-2">
                    {/* SECTION 1 : ÉTAT CIVIL & COORDONNÉES */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 border-b pb-2">
                            <User className="size-4 text-teal-600" />
                            <h3 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                1. État Civil & Contact
                            </h3>
                        </div>

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
                                    className="mt-1 h-9 text-sm"
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
                                        <SelectValue placeholder="Sélectionner" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="M">Masculin (M)</SelectItem>
                                        <SelectItem value="F">Féminin (F)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div>
                                <Label htmlFor="telephone" className="text-xs font-semibold">
                                    Téléphone principal <span className="text-rose-500">*</span>
                                </Label>
                                <div className="relative mt-1">
                                    <Phone className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                    <Input
                                        id="telephone"
                                        required
                                        value={data.telephone}
                                        onChange={(e) => setData('telephone', e.target.value)}
                                        placeholder="ex: 0708091011"
                                        className="pl-8 h-9 text-sm"
                                    />
                                </div>
                                {errors.telephone && <p className="text-rose-500 text-xs mt-1">{errors.telephone}</p>}
                            </div>

                            <div>
                                <Label htmlFor="telephone_secondaire" className="text-xs font-semibold">
                                    Téléphone secondaire
                                </Label>
                                <Input
                                    id="telephone_secondaire"
                                    value={data.telephone_secondaire}
                                    onChange={(e) => setData('telephone_secondaire', e.target.value)}
                                    placeholder="ex: 0102030405"
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="email" className="text-xs font-semibold">
                                    Email de contact
                                </Label>
                                <div className="relative mt-1">
                                    <Mail className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="jean.kouassi@exemple.com"
                                        className="pl-8 h-9 text-sm"
                                    />
                                </div>
                                {errors.email && <p className="text-rose-500 text-xs mt-1">{errors.email}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <Label htmlFor="date_naissance" className="text-xs font-semibold">
                                    Date de naissance
                                </Label>
                                <Input
                                    id="date_naissance"
                                    type="date"
                                    value={data.date_naissance}
                                    onChange={(e) => setData('date_naissance', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="lieu_naissance" className="text-xs font-semibold">
                                    Lieu de naissance
                                </Label>
                                <Input
                                    id="lieu_naissance"
                                    value={data.lieu_naissance}
                                    onChange={(e) => setData('lieu_naissance', e.target.value)}
                                    placeholder="ex: Abidjan"
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="adresse" className="text-xs font-semibold">
                                    Adresse / Quartier
                                </Label>
                                <Input
                                    id="adresse"
                                    value={data.adresse}
                                    onChange={(e) => setData('adresse', e.target.value)}
                                    placeholder="ex: Cocody Angré 8ème Tranche"
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    {/* SECTION 2 : AFFECTATION & CONTRAT */}
                    <div className="space-y-4 pt-2">
                        <div className="flex items-center gap-2 border-b pb-2">
                            <Building2 className="size-4 text-teal-600" />
                            <h3 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                2. Affectation & Données RH
                            </h3>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <Label htmlFor="etablissement_id" className="text-xs font-semibold">
                                    Établissement d'affectation <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={String(data.etablissement_id)}
                                    onValueChange={(val) => setData('etablissement_id', Number(val))}
                                >
                                    <SelectTrigger id="etablissement_id" className="mt-1 h-9 text-sm">
                                        <SelectValue placeholder="Sélectionner un établissement" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {etablissements.map((etab) => (
                                            <SelectItem key={etab.id} value={String(etab.id)}>
                                                {etab.nom} ({etab.code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
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
                                        }));
                                    }}
                                >
                                    <SelectTrigger id="fonction_principale" className="mt-1 h-9 text-sm">
                                        <SelectValue placeholder="Choisir une fonction" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {FONCTIONS_PREDEFINIES.map((fn) => (
                                            <SelectItem key={fn} value={fn}>
                                                {fn}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="type_contrat" className="text-xs font-semibold">
                                    Type de contrat <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.type_contrat}
                                    onValueChange={(val) => setData('type_contrat', val)}
                                >
                                    <SelectTrigger id="type_contrat" className="mt-1 h-9 text-sm">
                                        <SelectValue placeholder="Choisir un contrat" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {TYPES_CONTRAT.map((ct) => (
                                            <SelectItem key={ct} value={ct}>
                                                {ct}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div>
                                <Label htmlFor="date_embauche" className="text-xs font-semibold">
                                    Date d'embauche <span className="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="date_embauche"
                                    type="date"
                                    required
                                    value={data.date_embauche}
                                    onChange={(e) => setData('date_embauche', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="statut" className="text-xs font-semibold">
                                    Statut administratif <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.statut}
                                    onValueChange={(val) => setData('statut', val)}
                                >
                                    <SelectTrigger id="statut" className="mt-1 h-9 text-sm">
                                        <SelectValue placeholder="Choisir un statut" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {STATUTS_ADMIN.map((st) => (
                                            <SelectItem key={st} value={st}>
                                                {st}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="numero_cnps" className="text-xs font-semibold">
                                    Numéro CNPS / Sécu
                                </Label>
                                <Input
                                    id="numero_cnps"
                                    value={data.numero_cnps}
                                    onChange={(e) => setData('numero_cnps', e.target.value)}
                                    placeholder="ex: 123456789"
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="diplome_plus_eleve" className="text-xs font-semibold">
                                    Diplôme le plus élevé
                                </Label>
                                <Input
                                    id="diplome_plus_eleve"
                                    value={data.diplome_plus_eleve}
                                    onChange={(e) => setData('diplome_plus_eleve', e.target.value)}
                                    placeholder="ex: Master II, CAPES, BAC+5"
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    {/* SECTION 3 : QUALIFICATION PÉDAGOGIQUE (ENSEIGNANT) */}
                    <div className="space-y-4 pt-2 border-t">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <GraduationCap className="size-4 text-teal-600" />
                                <h3 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                    3. Profil Pédagogique (Enseignant)
                                </h3>
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_enseignant"
                                    checked={data.is_enseignant}
                                    onCheckedChange={(checked) => setData('is_enseignant', !!checked)}
                                />
                                <Label htmlFor="is_enseignant" className="text-xs font-medium cursor-pointer">
                                    Activer les paramètres enseignants
                                </Label>
                            </div>
                        </div>

                        {data.is_enseignant && (
                            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-teal-50/50 dark:bg-teal-950/20 border border-teal-100 dark:border-teal-900/40 animate-in fade-in-50">
                                <div>
                                    <Label htmlFor="departement_id" className="text-xs font-semibold">
                                        Département pédagogique
                                    </Label>
                                    <Select
                                        value={data.departement_id ? String(data.departement_id) : 'none'}
                                        onValueChange={(val) => setData('departement_id', val === 'none' ? '' : Number(val))}
                                    >
                                        <SelectTrigger id="departement_id" className="mt-1 h-9 text-sm bg-background">
                                            <SelectValue placeholder="Aucun département" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Aucun département</SelectItem>
                                            {departements.map((dep) => (
                                                <SelectItem key={dep.id} value={String(dep.id)}>
                                                    {dep.nom} {dep.code ? `(${dep.code})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label htmlFor="specialite" className="text-xs font-semibold">
                                        Spécialité / Discipline
                                    </Label>
                                    <Input
                                        id="specialite"
                                        value={data.specialite}
                                        onChange={(e) => setData('specialite', e.target.value)}
                                        placeholder="ex: Mathématiques"
                                        className="mt-1 h-9 text-sm bg-background"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="volume_horaire_hebdo" className="text-xs font-semibold">
                                        Quota légal hebdo (heures)
                                    </Label>
                                    <Input
                                        id="volume_horaire_hebdo"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        max="60"
                                        value={data.volume_horaire_hebdo}
                                        onChange={(e) => setData('volume_horaire_hebdo', parseFloat(e.target.value) || 0)}
                                        className="mt-1 h-9 text-sm bg-background"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="taux_horaire_vacation" className="text-xs font-semibold">
                                        Taux vacation (FCFA / heure)
                                    </Label>
                                    <Input
                                        id="taux_horaire_vacation"
                                        type="number"
                                        step="500"
                                        min="0"
                                        value={data.taux_horaire_vacation}
                                        onChange={(e) => setData('taux_horaire_vacation', e.target.value)}
                                        placeholder="ex: 5000"
                                        className="mt-1 h-9 text-sm bg-background"
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    {/* SECTION 4 : COMPTE D'ACCÈS LOGICIEL UTILISATEUR */}
                    <div className="space-y-4 pt-2 border-t">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <KeyRound className="size-4 text-emerald-600" />
                                <h3 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                    4. Compte d'Accès SYGES (Optionnel)
                                </h3>
                            </div>

                            {/* Sélecteur de mode de compte */}
                            <div className="flex items-center gap-3">
                                <label className="flex items-center gap-1.5 text-xs cursor-pointer">
                                    <input
                                        type="radio"
                                        name="account_mode"
                                        checked={accountMode === 'create'}
                                        onChange={() => {
                                            setAccountMode('create');
                                            setData('create_user_account', true);
                                            setData('user_id', '');
                                        }}
                                        className="text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span>Créer un compte</span>
                                </label>

                                {availableUsers.length > 0 && (
                                    <label className="flex items-center gap-1.5 text-xs cursor-pointer">
                                        <input
                                            type="radio"
                                            name="account_mode"
                                            checked={accountMode === 'link'}
                                            onChange={() => {
                                                setAccountMode('link');
                                                setData('create_user_account', false);
                                            }}
                                            className="text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <span>Lier un compte existant</span>
                                    </label>
                                )}

                                <label className="flex items-center gap-1.5 text-xs cursor-pointer">
                                    <input
                                        type="radio"
                                        name="account_mode"
                                        checked={accountMode === 'none'}
                                        onChange={() => {
                                            setAccountMode('none');
                                            setData('create_user_account', false);
                                            setData('user_id', '');
                                        }}
                                        className="text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span>Pas de compte</span>
                                </label>
                            </div>
                        </div>

                        {/* Formulaire de création de compte utilisateur */}
                        {accountMode === 'create' && (
                            <div className="p-4 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 space-y-4 animate-in fade-in-50">
                                <div className="flex items-center gap-2 text-xs text-emerald-800 dark:text-emerald-300 font-medium">
                                    <Sparkles className="size-4 text-emerald-600" />
                                    <span>Un compte utilisateur actif sera immédiatement créé et relié à cette fiche de personnel.</span>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                                    <div>
                                        <Label htmlFor="user_login" className="text-xs font-semibold">
                                            Identifiant (Login)
                                        </Label>
                                        <Input
                                            id="user_login"
                                            value={data.user_login}
                                            onChange={(e) => setData('user_login', e.target.value)}
                                            placeholder="Défaut : matricule"
                                            className="mt-1 h-9 text-sm bg-background font-mono"
                                        />
                                        {errors.user_login && <p className="text-rose-500 text-xs mt-1">{errors.user_login}</p>}
                                    </div>

                                    <div>
                                        <Label htmlFor="user_email" className="text-xs font-semibold">
                                            Email de connexion
                                        </Label>
                                        <Input
                                            id="user_email"
                                            type="email"
                                            value={data.user_email}
                                            onChange={(e) => setData('user_email', e.target.value)}
                                            placeholder={data.email || 'auto-généré si vide'}
                                            className="mt-1 h-9 text-sm bg-background"
                                        />
                                        {errors.user_email && <p className="text-rose-500 text-xs mt-1">{errors.user_email}</p>}
                                    </div>

                                    <div>
                                        <Label htmlFor="user_password" className="text-xs font-semibold">
                                            Mot de passe initial
                                        </Label>
                                        <Input
                                            id="user_password"
                                            type="text"
                                            value={data.user_password}
                                            onChange={(e) => setData('user_password', e.target.value)}
                                            placeholder="Défaut : matricule"
                                            className="mt-1 h-9 text-sm bg-background font-mono"
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="user_role" className="text-xs font-semibold">
                                            Rôle de sécurité Spatie
                                        </Label>
                                        <Select
                                            value={data.user_role}
                                            onValueChange={(val) => setData('user_role', val)}
                                        >
                                            <SelectTrigger id="user_role" className="mt-1 h-9 text-sm bg-background">
                                                <SelectValue placeholder="Rôle de l'utilisateur" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {roles.map((r) => (
                                                    <SelectItem key={r} value={r}>
                                                        {r}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
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
                                        {availableUsers.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.name} (login: {u.login}, email: {u.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>

                    <DialogFooter className="gap-2 border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            className="cursor-pointer"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-teal-600 hover:bg-teal-700 text-white cursor-pointer font-semibold"
                        >
                            <Plus className="size-4 mr-1.5" />
                            {processing ? 'Enregistrement...' : 'Enregistrer le personnel'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

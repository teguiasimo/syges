import { useForm } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    Check,
    GraduationCap,
    KeyRound,
    Mail,
    Phone,
    User,
} from 'lucide-react';
import React, { useEffect } from 'react';
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
import type {
    AvailableUserOption,
    Departement,
    EtablissementOption,
    Personnel,
} from '@/types/personnel';

/**
 * Propriétés attendues par la modale de modification d'une fiche personnel.
 */
interface EditPersonnelModalProps {
    /** Membre du personnel sélectionné pour modification */
    personnel: Personnel | null;
    /** Indique si la modale est ouverte */
    isOpen: boolean;
    /** Callback de fermeture */
    onClose: () => void;
    /** Liste des établissements scolaires */
    etablissements: EtablissementOption[];
    /** Liste des départements pédagogiques */
    departements: Departement[];
    /** Comptes utilisateurs disponibles pour liaison */
    availableUsers: AvailableUserOption[];
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
 * Modale de modification de la fiche administrative, contractuelle et pédagogique d'un employé.
 */
export function EditPersonnelModal({
    personnel,
    isOpen,
    onClose,
    etablissements,
    departements,
    availableUsers,
}: EditPersonnelModalProps) {
    const { data, setData, put, processing, errors, reset, clearErrors } = useForm({
        nom: '',
        prenom: '',
        sexe: 'M' as 'M' | 'F',
        date_naissance: '',
        lieu_naissance: '',
        telephone: '',
        telephone_secondaire: '',
        email: '',
        adresse: '',
        etablissement_id: 1,
        departement_id: '' as string | number,
        fonction_principale: 'Enseignant',
        type_contrat: 'CDI (Contrat à Durée Indéterminée)',
        date_embauche: '',
        statut: 'Actif',
        numero_cnps: '',
        diplome_plus_eleve: '',
        observations: '',
        is_enseignant: true,
        specialite: '',
        volume_horaire_hebdo: 18,
        taux_horaire_vacation: '' as string | number,
        user_id: '' as string | number,
    });

    // Synchronisation des valeurs du formulaire lorsque le personnel sélectionné change
    useEffect(() => {
        if (personnel) {
            setData({
                nom: personnel.nom,
                prenom: personnel.prenom || '',
                sexe: personnel.sexe,
                date_naissance: personnel.date_naissance ? personnel.date_naissance.split('T')[0] : '',
                lieu_naissance: personnel.lieu_naissance || '',
                telephone: personnel.telephone,
                telephone_secondaire: personnel.telephone_secondaire || '',
                email: personnel.email || '',
                adresse: personnel.adresse || '',
                etablissement_id: personnel.etablissement_id,
                departement_id: personnel.departement_id || '',
                fonction_principale: personnel.fonction_principale,
                type_contrat: personnel.type_contrat,
                date_embauche: personnel.date_embauche ? personnel.date_embauche.split('T')[0] : '',
                statut: personnel.statut,
                numero_cnps: personnel.numero_cnps || '',
                diplome_plus_eleve: personnel.diplome_plus_eleve || '',
                observations: personnel.observations || '',
                is_enseignant: personnel.is_enseignant,
                specialite: personnel.specialite || '',
                volume_horaire_hebdo: personnel.volume_horaire_hebdo,
                taux_horaire_vacation: personnel.taux_horaire_vacation || '',
                user_id: personnel.user_id || '',
            });
            clearErrors();
        }
    }, [personnel]);

    /**
     * Traitement de la soumission de la mise à jour.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!personnel) return;

        put(`/administration/personnels/${personnel.id}`, {
            onSuccess: () => {
                toast.success('Fiche personnel mise à jour !', {
                    description: `Les modifications de ${data.nom} ont été enregistrées avec succès.`,
                });
                onClose();
            },
            onError: () => {
                toast.error('Erreur lors de la modification', {
                    description: 'Veuillez vérifier les informations renseignées.',
                });
            },
        });
    };

    if (!personnel) return null;

    // Utilisateurs disponibles comprenant l'utilisateur déjà rattaché
    const userOptions = [...availableUsers];
    if (personnel.user && !userOptions.find((u) => u.id === personnel.user?.id)) {
        userOptions.unshift({
            id: personnel.user.id,
            name: personnel.user.name,
            email: personnel.user.email,
            login: personnel.user.login,
        });
    }

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-xl font-bold flex items-center gap-2 text-foreground">
                        <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 border border-blue-200 dark:border-blue-900/50">
                            <Briefcase className="size-5" />
                        </div>
                        Modifier la fiche : {personnel.nom} {personnel.prenom}
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm text-muted-foreground mt-1 flex items-center gap-3">
                        <span>Matricule officiel : <strong className="text-foreground font-mono">{personnel.matricule}</strong></span>
                        {personnel.user && (
                            <span className="inline-flex items-center gap-1 text-purple-600 dark:text-purple-400 font-medium">
                                <KeyRound className="size-3.5" /> Compte utilisateur SYGES actif
                            </span>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6 pt-2">
                    {/* SECTION 1 : ÉTAT CIVIL */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 border-b pb-2">
                            <User className="size-4 text-blue-600" />
                            <h3 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                1. État Civil & Coordonnées
                            </h3>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <Label htmlFor="edit_nom" className="text-xs font-semibold">
                                    Nom de famille <span className="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="edit_nom"
                                    required
                                    value={data.nom}
                                    onChange={(e) => setData('nom', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                                {errors.nom && <p className="text-rose-500 text-xs mt-1">{errors.nom}</p>}
                            </div>

                            <div>
                                <Label htmlFor="edit_prenom" className="text-xs font-semibold">
                                    Prénom(s)
                                </Label>
                                <Input
                                    id="edit_prenom"
                                    value={data.prenom}
                                    onChange={(e) => setData('prenom', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                                {errors.prenom && <p className="text-rose-500 text-xs mt-1">{errors.prenom}</p>}
                            </div>

                            <div>
                                <Label htmlFor="edit_sexe" className="text-xs font-semibold">
                                    Genre <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.sexe}
                                    onValueChange={(val: 'M' | 'F') => setData('sexe', val)}
                                >
                                    <SelectTrigger id="edit_sexe" className="mt-1 h-9 text-sm">
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
                                <Label htmlFor="edit_telephone" className="text-xs font-semibold">
                                    Téléphone principal <span className="text-rose-500">*</span>
                                </Label>
                                <div className="relative mt-1">
                                    <Phone className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                    <Input
                                        id="edit_telephone"
                                        required
                                        value={data.telephone}
                                        onChange={(e) => setData('telephone', e.target.value)}
                                        className="pl-8 h-9 text-sm"
                                    />
                                </div>
                                {errors.telephone && <p className="text-rose-500 text-xs mt-1">{errors.telephone}</p>}
                            </div>

                            <div>
                                <Label htmlFor="edit_telephone_secondaire" className="text-xs font-semibold">
                                    Téléphone secondaire
                                </Label>
                                <Input
                                    id="edit_telephone_secondaire"
                                    value={data.telephone_secondaire}
                                    onChange={(e) => setData('telephone_secondaire', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="edit_email" className="text-xs font-semibold">
                                    Email de contact
                                </Label>
                                <div className="relative mt-1">
                                    <Mail className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                    <Input
                                        id="edit_email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="pl-8 h-9 text-sm"
                                    />
                                </div>
                                {errors.email && <p className="text-rose-500 text-xs mt-1">{errors.email}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <Label htmlFor="edit_date_naissance" className="text-xs font-semibold">
                                    Date de naissance
                                </Label>
                                <Input
                                    id="edit_date_naissance"
                                    type="date"
                                    value={data.date_naissance}
                                    onChange={(e) => setData('date_naissance', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_lieu_naissance" className="text-xs font-semibold">
                                    Lieu de naissance
                                </Label>
                                <Input
                                    id="edit_lieu_naissance"
                                    value={data.lieu_naissance}
                                    onChange={(e) => setData('lieu_naissance', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_adresse" className="text-xs font-semibold">
                                    Adresse / Quartier
                                </Label>
                                <Input
                                    id="edit_adresse"
                                    value={data.adresse}
                                    onChange={(e) => setData('adresse', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    {/* SECTION 2 : AFFECTATION & DONNÉES RH */}
                    <div className="space-y-4 pt-2">
                        <div className="flex items-center gap-2 border-b pb-2">
                            <Building2 className="size-4 text-blue-600" />
                            <h3 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                2. Affectation & Contrat
                            </h3>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <Label htmlFor="edit_etablissement_id" className="text-xs font-semibold">
                                    Établissement <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={String(data.etablissement_id)}
                                    onValueChange={(val) => setData('etablissement_id', Number(val))}
                                >
                                    <SelectTrigger id="edit_etablissement_id" className="mt-1 h-9 text-sm">
                                        <SelectValue placeholder="Choisir un établissement" />
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
                                <Label htmlFor="edit_fonction" className="text-xs font-semibold">
                                    Fonction principale <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.fonction_principale}
                                    onValueChange={(val) => setData('fonction_principale', val)}
                                >
                                    <SelectTrigger id="edit_fonction" className="mt-1 h-9 text-sm">
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
                                <Label htmlFor="edit_type_contrat" className="text-xs font-semibold">
                                    Type de contrat <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.type_contrat}
                                    onValueChange={(val) => setData('type_contrat', val)}
                                >
                                    <SelectTrigger id="edit_type_contrat" className="mt-1 h-9 text-sm">
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
                                <Label htmlFor="edit_date_embauche" className="text-xs font-semibold">
                                    Date d'embauche <span className="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="edit_date_embauche"
                                    type="date"
                                    required
                                    value={data.date_embauche}
                                    onChange={(e) => setData('date_embauche', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_statut" className="text-xs font-semibold">
                                    Statut administratif <span className="text-rose-500">*</span>
                                </Label>
                                <Select
                                    value={data.statut}
                                    onValueChange={(val) => setData('statut', val)}
                                >
                                    <SelectTrigger id="edit_statut" className="mt-1 h-9 text-sm">
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
                                <Label htmlFor="edit_numero_cnps" className="text-xs font-semibold">
                                    Numéro CNPS
                                </Label>
                                <Input
                                    id="edit_numero_cnps"
                                    value={data.numero_cnps}
                                    onChange={(e) => setData('numero_cnps', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_diplome" className="text-xs font-semibold">
                                    Diplôme le plus élevé
                                </Label>
                                <Input
                                    id="edit_diplome"
                                    value={data.diplome_plus_eleve}
                                    onChange={(e) => setData('diplome_plus_eleve', e.target.value)}
                                    className="mt-1 h-9 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    {/* SECTION 3 : QUALIFICATION PÉDAGOGIQUE */}
                    <div className="space-y-4 pt-2 border-t">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <GraduationCap className="size-4 text-blue-600" />
                                <h3 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                    3. Profil Pédagogique (Enseignant)
                                </h3>
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="edit_is_enseignant"
                                    checked={data.is_enseignant}
                                    onCheckedChange={(checked) => setData('is_enseignant', !!checked)}
                                />
                                <Label htmlFor="edit_is_enseignant" className="text-xs font-medium cursor-pointer">
                                    Activer les paramètres enseignants
                                </Label>
                            </div>
                        </div>

                        {data.is_enseignant && (
                            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-blue-50/40 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40 animate-in fade-in-50">
                                <div>
                                    <Label htmlFor="edit_departement_id" className="text-xs font-semibold">
                                        Département pédagogique
                                    </Label>
                                    <Select
                                        value={data.departement_id ? String(data.departement_id) : 'none'}
                                        onValueChange={(val) => setData('departement_id', val === 'none' ? '' : Number(val))}
                                    >
                                        <SelectTrigger id="edit_departement_id" className="mt-1 h-9 text-sm bg-background">
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
                                    <Label htmlFor="edit_specialite" className="text-xs font-semibold">
                                        Spécialité / Discipline
                                    </Label>
                                    <Input
                                        id="edit_specialite"
                                        value={data.specialite}
                                        onChange={(e) => setData('specialite', e.target.value)}
                                        className="mt-1 h-9 text-sm bg-background"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="edit_volume_horaire" className="text-xs font-semibold">
                                        Quota légal hebdo (heures)
                                    </Label>
                                    <Input
                                        id="edit_volume_horaire"
                                        type="number"
                                        step="0.5"
                                        value={data.volume_horaire_hebdo}
                                        onChange={(e) => setData('volume_horaire_hebdo', parseFloat(e.target.value) || 0)}
                                        className="mt-1 h-9 text-sm bg-background"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="edit_taux_vacation" className="text-xs font-semibold">
                                        Taux vacation (FCFA / heure)
                                    </Label>
                                    <Input
                                        id="edit_taux_vacation"
                                        type="number"
                                        step="500"
                                        value={data.taux_horaire_vacation}
                                        onChange={(e) => setData('taux_horaire_vacation', e.target.value)}
                                        className="mt-1 h-9 text-sm bg-background"
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    {/* SECTION 4 : COMPTE UTILISATEUR RATTACHÉ */}
                    <div className="space-y-4 pt-2 border-t">
                        <div className="flex items-center gap-2">
                            <KeyRound className="size-4 text-purple-600 dark:text-purple-400" />
                            <h3 className="text-sm font-semibold text-foreground uppercase tracking-wide">
                                4. Liaison Compte Utilisateur SYGES
                            </h3>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="edit_user_id" className="text-xs font-semibold">
                                    Compte utilisateur rattaché
                                </Label>
                                <Select
                                    value={data.user_id ? String(data.user_id) : 'none'}
                                    onValueChange={(val) => setData('user_id', val === 'none' ? '' : Number(val))}
                                >
                                    <SelectTrigger id="edit_user_id" className="mt-1 h-9 text-sm">
                                        <SelectValue placeholder="Aucun compte rattaché" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Aucun compte utilisateur (dissocier)</SelectItem>
                                        {userOptions.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.name} (login: {u.login})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
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
                            className="bg-blue-600 hover:bg-blue-700 text-white cursor-pointer font-semibold"
                        >
                            <Check className="size-4 mr-1.5" />
                            {processing ? 'Enregistrement...' : 'Mettre à jour la fiche'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

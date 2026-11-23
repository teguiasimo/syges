import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { User, Phone, HeartPulse, GraduationCap, Upload, X } from 'lucide-react';
import { SearchableSelect, type SearchableOption } from '@/components/ui/searchable-select';
import {
    buildClasseSearchOptions,
    buildEtablissementSearchOptions,
    flattenClassesFromEtablissements,
} from '@/lib/utils';
import type { EtablissementRef, AnneeScolaireRef } from '@/types/eleve';

/**
 * Propriétés du composant CreateEleveModal.
 */
interface CreateEleveModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissements: EtablissementRef[];
    anneesScolaires: AnneeScolaireRef[];
    selectedAnneeId: number;
}

/**
 * Modal d'enregistrement d'un nouvel élève dans la Base Globale et inscription scolaire.
 * Formulaire structuré en 4 onglets : Identité, Parents, Santé/Urgence, Scolarité.
 *
 * @param {CreateEleveModalProps} props
 * @returns {JSX.Element}
 */
export function CreateEleveModal({
    open,
    onOpenChange,
    etablissements,
    anneesScolaires,
    selectedAnneeId,
}: CreateEleveModalProps) {
    const [activeTab, setActiveTab] = useState<'identite' | 'parents' | 'sante' | 'scolarite'>('identite');
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Sélecteurs hiérarchiques pour la classe
    const [selectedEtabId, setSelectedEtabId] = useState<string>('');
    const [selectedClasseId, setSelectedClasseId] = useState<string>('');

    // Photo preview
    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);

    // Données du formulaire
    const [formData, setFormData] = useState({
        matricule: '',
        matricule_national: '',
        nom: '',
        prenom: '',
        sexe: '',
        date_naissance: '',
        lieu_naissance: '',
        nationalite: 'CAMEROUNAISE',
        nom_pere: '',
        nom_mere: '',
        nom_tuteur: '',
        telephone_parent: '',
        email_parent: '',
        profession_parent: '',
        adresse: '',
        ville: 'Yaoundé',
        groupe_sanguin: '',
        contact_urgence_nom: '',
        contact_urgence_telephone: '',
        allergies_conditions: '',
        annee_scolaire_id: String(selectedAnneeId || ''),
        statut: 'validee',
        is_redoublant: '0',
        etablissement_provenance: '',
        observations: '',
    });

    // Liste aplatie des classes et options formatées pour SearchableSelect (sans troncature du nom de campus)
    const availableClasses = React.useMemo(
        () => flattenClassesFromEtablissements(etablissements, selectedEtabId),
        [etablissements, selectedEtabId]
    );

    const classeOptions: SearchableOption[] = React.useMemo(
        () => buildClasseSearchOptions(availableClasses),
        [availableClasses]
    );

    const etablissementOptions: SearchableOption[] = React.useMemo(
        () => [{ value: '', label: 'Tous les campus / établissements' }, ...buildEtablissementSearchOptions(etablissements)],
        [etablissements]
    );

    /**
     * Valide la taille de la photo choisie (5 Mo max) et génère l'aperçu local.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} e Événement de sélection de fichier.
     */
    const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            setErrors((prev) => ({ ...prev, photo: 'La photo ne doit pas dépasser 5 Mo.' }));
            toast.error('Photo trop volumineuse (max 5 Mo).', {
                position: 'top-right',
            });
            e.target.value = '';
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
     * Supprime la photo sélectionnée et efface toute erreur associée.
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
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});

        // Vérifications préalables simples
        if (!formData.nom.trim()) {
            setErrors({ nom: 'Le nom de famille est obligatoire.' });
            setActiveTab('identite');
            return;
        }

        if (!selectedClasseId) {
            setErrors({ classe_id: 'Veuillez sélectionner la classe d\'affectation de l\'élève.' });
            setActiveTab('scolarite');
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

        if (photoFile) {
            payload.append('photo', photoFile);
        }

        router.post('/administration/eleves', payload, {
            forceFormData: true,
            onSuccess: () => {
                setLoading(false);
                toast.success('Élève enregistré et inscrit avec succès !', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                onOpenChange(false);
                // Réinitialisation
                setFormData({
                    matricule: '',
                    matricule_national: '',
                    nom: '',
                    prenom: '',
                    sexe: '',
                    date_naissance: '',
                    lieu_naissance: '',
                    nationalite: 'CAMEROUNAISE',
                    nom_pere: '',
                    nom_mere: '',
                    nom_tuteur: '',
                    telephone_parent: '',
                    email_parent: '',
                    profession_parent: '',
                    adresse: '',
                    ville: 'Yaoundé',
                    groupe_sanguin: '',
                    contact_urgence_nom: '',
                    contact_urgence_telephone: '',
                    allergies_conditions: '',
                    annee_scolaire_id: String(selectedAnneeId || ''),
                    statut: 'validee',
                    is_redoublant: '0',
                    etablissement_provenance: '',
                    observations: '',
                });
                setSelectedClasseId('');
                removePhoto();
            },
            onError: (errs) => {
                setLoading(false);
                setErrors(errs);
                if (errs.photo || errs.nom || errs.prenom || errs.matricule || errs.matricule_national) {
                    setActiveTab('identite');
                }
                toast.error('Veuillez vérifier les erreurs du formulaire.', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg sm:text-xl">
                        <GraduationCap className="size-6 text-primary shrink-0" />
                        <span>Nouvel Élève & Inscription Scolaire</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Renseignez l'identité civile de la Base Élève Globale et affectez l'élève à sa classe.
                    </DialogDescription>
                </DialogHeader>

                {/* Onglets de navigation dans le formulaire avec défilement horizontal fluide */}
                <div className="flex border-b border-border mt-2 gap-1 overflow-x-auto pb-1 shrink-0">
                    <button
                        type="button"
                        onClick={() => setActiveTab('identite')}
                        className={`flex items-center gap-2 px-3 sm:px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 whitespace-nowrap shrink-0 ${
                            activeTab === 'identite'
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <User className="size-4 shrink-0" />
                        <span>1. État Civil & Identité</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('parents')}
                        className={`flex items-center gap-2 px-3 sm:px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 whitespace-nowrap shrink-0 ${
                            activeTab === 'parents'
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Phone className="size-4 shrink-0" />
                        <span>2. Filiation & Contacts</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('sante')}
                        className={`flex items-center gap-2 px-3 sm:px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 whitespace-nowrap shrink-0 ${
                            activeTab === 'sante'
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <HeartPulse className="size-4 shrink-0" />
                        <span>3. Santé & Urgence</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('scolarite')}
                        className={`flex items-center gap-2 px-3 sm:px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 whitespace-nowrap shrink-0 ${
                            activeTab === 'scolarite'
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <GraduationCap className="size-4 shrink-0" />
                        <span>4. Affectation Scolaire</span>
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* ONGLET 1 : ÉTAT CIVIL */}
                    {activeTab === 'identite' && (
                        <div className="space-y-4 animate-in fade-in-50 duration-200">
                            <div className="flex flex-col sm:flex-row gap-6 items-start">
                                {/* Photo d'identité */}
                                <div className="flex flex-col items-center gap-2 shrink-0">
                                    <Label className="text-xs text-muted-foreground">Photo d'identité</Label>
                                    <div className="size-28 rounded-2xl border-2 border-dashed border-border flex items-center justify-center overflow-hidden bg-muted/30 relative">
                                        {photoPreview ? (
                                            <>
                                                <img src={photoPreview} alt="Aperçu" className="size-full object-cover" />
                                                <button
                                                    type="button"
                                                    onClick={removePhoto}
                                                    className="absolute top-1 right-1 bg-black/60 text-white rounded-full p-1 hover:bg-black"
                                                >
                                                    <X className="size-3.5" />
                                                </button>
                                            </>
                                        ) : (
                                            <div className="flex flex-col items-center gap-1 text-muted-foreground">
                                                <Upload className="size-6 text-muted-foreground/60" />
                                                <span className="text-[10px]">Photo</span>
                                            </div>
                                        )}
                                    </div>
                                    <label className="cursor-pointer">
                                        <span className="text-xs text-primary hover:underline font-medium">
                                            {photoPreview ? 'Changer la photo' : 'Parcourir...'}
                                        </span>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={handlePhotoChange}
                                            className="hidden"
                                        />
                                    </label>
                                    <span className="text-[10px] text-muted-foreground">JPG, PNG, WEBP (max 5 Mo)</span>
                                    {errors.photo && <p className="text-xs text-destructive text-center max-w-35">{errors.photo}</p>}
                                </div>

                                {/* Champs d'identification */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1 w-full">
                                    {/* Matricule interne non cliquable : généré automatiquement côté serveur */}
                                    <div>
                                        <div className="flex items-center justify-between mb-1">
                                            <span className="text-sm font-medium leading-none text-slate-700">
                                                Matricule Interne
                                            </span>
                                            <span className="text-[10px] px-1.5 py-0.5 rounded-full font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Automatique
                                            </span>
                                        </div>
                                        <div className="mt-1 cursor-not-allowed select-none">
                                            <Input
                                                id="matricule"
                                                readOnly
                                                disabled
                                                aria-readonly="true"
                                                tabIndex={-1}
                                                value={formData.matricule}
                                                placeholder="ex: ESP-PRI-2026-0001 (Automatique)"
                                                className="font-mono bg-slate-100/80 text-slate-500 border-dashed focus-visible:ring-0 pointer-events-none select-none disabled:opacity-90"
                                            />
                                        </div>
                                        <p className="text-[10px] text-muted-foreground mt-1">
                                            Format officiel <code>[CODE_CAMPUS]-[ANNÉE]-[0001]</code> attribué à l'enregistrement.
                                        </p>
                                        {errors.matricule && <p className="text-xs text-destructive mt-1">{errors.matricule}</p>}
                                    </div>

                                    <div>
                                        <Label htmlFor="matricule_national">
                                            Matricule National <span className="text-xs text-muted-foreground font-normal">(MINESEC / Examens)</span>
                                        </Label>
                                        <Input
                                            id="matricule_national"
                                            value={formData.matricule_national}
                                            onChange={(e) => setFormData({ ...formData, matricule_national: e.target.value })}
                                            placeholder="Ex: MIN-2026-XXXX"
                                            className="mt-1 font-mono"
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="nom">
                                            Nom(s) de famille <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="nom"
                                            value={formData.nom}
                                            onChange={(e) => setFormData({ ...formData, nom: e.target.value })}
                                            placeholder="Ex: KAMDEM"
                                            required
                                            className="mt-1 uppercase"
                                        />
                                        {errors.nom && <p className="text-xs text-destructive mt-1">{errors.nom}</p>}
                                    </div>

                                    <div>
                                        <Label htmlFor="prenom">Prénom(s)</Label>
                                        <Input
                                            id="prenom"
                                            value={formData.prenom}
                                            onChange={(e) => setFormData({ ...formData, prenom: e.target.value })}
                                            placeholder="Ex: Joseph Fabrice"
                                            className="mt-1"
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="sexe">
                                            Sexe <span className="text-[10px] font-normal text-muted-foreground">(Optionnel)</span>
                                        </Label>
                                        <select
                                            id="sexe"
                                            value={formData.sexe}
                                            onChange={(e) => setFormData({ ...formData, sexe: e.target.value })}
                                            className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                        >
                                            <option value="">Non défini (À compléter)</option>
                                            <option value="M">Masculin (M)</option>
                                            <option value="F">Féminin (F)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <Label htmlFor="date_naissance">Date de naissance</Label>
                                        <Input
                                            id="date_naissance"
                                            type="date"
                                            value={formData.date_naissance}
                                            onChange={(e) => setFormData({ ...formData, date_naissance: e.target.value })}
                                            className="mt-1"
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="lieu_naissance">Lieu de naissance</Label>
                                        <Input
                                            id="lieu_naissance"
                                            value={formData.lieu_naissance}
                                            onChange={(e) => setFormData({ ...formData, lieu_naissance: e.target.value })}
                                            placeholder="Ex: Douala"
                                            className="mt-1"
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="nationalite">Nationalité</Label>
                                        <Input
                                            id="nationalite"
                                            value={formData.nationalite}
                                            onChange={(e) => setFormData({ ...formData, nationalite: e.target.value })}
                                            placeholder="Ex: CAMEROUNAISE"
                                            className="mt-1"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ONGLET 2 : PARENTS & CONTACTS */}
                    {activeTab === 'parents' && (
                        <div className="space-y-4 animate-in fade-in-50 duration-200">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="sm:col-span-2">
                                    <Label htmlFor="telephone_parent">
                                        Téléphone Principal Parent/Tuteur <span className="text-[10px] font-normal text-muted-foreground">(Optionnel - SMS & Portail)</span>
                                    </Label>
                                    <Input
                                        id="telephone_parent"
                                        value={formData.telephone_parent}
                                        onChange={(e) => setFormData({ ...formData, telephone_parent: e.target.value })}
                                        placeholder="Ex: +237 699 00 11 22"
                                        className="mt-1 font-mono"
                                    />
                                    {errors.telephone_parent && (
                                        <p className="text-xs text-destructive mt-1">{errors.telephone_parent}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="nom_pere">Nom complet du Père</Label>
                                    <Input
                                        id="nom_pere"
                                        value={formData.nom_pere}
                                        onChange={(e) => setFormData({ ...formData, nom_pere: e.target.value })}
                                        placeholder="Ex: KAMDEM Pierre"
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="nom_mere">Nom complet de la Mère</Label>
                                    <Input
                                        id="nom_mere"
                                        value={formData.nom_mere}
                                        onChange={(e) => setFormData({ ...formData, nom_mere: e.target.value })}
                                        placeholder="Ex: NGO NJOCK Marie"
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="nom_tuteur">Nom complet du Tuteur (si différent)</Label>
                                    <Input
                                        id="nom_tuteur"
                                        value={formData.nom_tuteur}
                                        onChange={(e) => setFormData({ ...formData, nom_tuteur: e.target.value })}
                                        placeholder="Ex: Tuteur légal"
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="email_parent">Adresse e-mail du parent</Label>
                                    <Input
                                        id="email_parent"
                                        type="email"
                                        value={formData.email_parent}
                                        onChange={(e) => setFormData({ ...formData, email_parent: e.target.value })}
                                        placeholder="Ex: parent@gmail.com"
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="profession_parent">Profession du parent / tuteur</Label>
                                    <Input
                                        id="profession_parent"
                                        value={formData.profession_parent}
                                        onChange={(e) => setFormData({ ...formData, profession_parent: e.target.value })}
                                        placeholder="Ex: Enseignant, Commerçant..."
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="ville">Ville de résidence</Label>
                                    <Input
                                        id="ville"
                                        value={formData.ville}
                                        onChange={(e) => setFormData({ ...formData, ville: e.target.value })}
                                        placeholder="Ex: Yaoundé"
                                        className="mt-1"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <Label htmlFor="adresse">Adresse / Quartier de résidence</Label>
                                    <Input
                                        id="adresse"
                                        value={formData.adresse}
                                        onChange={(e) => setFormData({ ...formData, adresse: e.target.value })}
                                        placeholder="Ex: Quartier Bastos, descente ambassade..."
                                        className="mt-1"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ONGLET 3 : SANTÉ & URGENCE */}
                    {activeTab === 'sante' && (
                        <div className="space-y-4 animate-in fade-in-50 duration-200">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="groupe_sanguin">Groupe Sanguin</Label>
                                    <select
                                        id="groupe_sanguin"
                                        value={formData.groupe_sanguin}
                                        onChange={(e) => setFormData({ ...formData, groupe_sanguin: e.target.value })}
                                        className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="">Non renseigné</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>

                                <div>
                                    <Label htmlFor="contact_urgence_telephone">Téléphone d'Urgence</Label>
                                    <Input
                                        id="contact_urgence_telephone"
                                        value={formData.contact_urgence_telephone}
                                        onChange={(e) => setFormData({ ...formData, contact_urgence_telephone: e.target.value })}
                                        placeholder="Ex: +237 677 00 00 00"
                                        className="mt-1 font-mono"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <Label htmlFor="contact_urgence_nom">Nom de la personne à contacter en cas d'urgence</Label>
                                    <Input
                                        id="contact_urgence_nom"
                                        value={formData.contact_urgence_nom}
                                        onChange={(e) => setFormData({ ...formData, contact_urgence_nom: e.target.value })}
                                        placeholder="Ex: Dr. TCHOUA (Oncle)"
                                        className="mt-1"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <Label htmlFor="allergies_conditions">Allergies ou antécédents médicaux particuliers</Label>
                                    <textarea
                                        id="allergies_conditions"
                                        value={formData.allergies_conditions}
                                        onChange={(e) => setFormData({ ...formData, allergies_conditions: e.target.value })}
                                        placeholder="Ex: Asthmatique, allergique à la pénicilline..."
                                        rows={3}
                                        className="w-full mt-1 rounded-md border border-input bg-background p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ONGLET 4 : AFFECTATION SCOLAIRE */}
                    {activeTab === 'scolarite' && (
                        <div className="space-y-4 animate-in fade-in-50 duration-200">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="annee_scolaire_id">Année Scolaire</Label>
                                    <select
                                        id="annee_scolaire_id"
                                        value={formData.annee_scolaire_id}
                                        onChange={(e) => setFormData({ ...formData, annee_scolaire_id: e.target.value })}
                                        className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        {anneesScolaires.map((a) => {
                                            const codeAnnee = a.code || a.nom.replace(/^année\s*scolaire\s*/i, '').trim();
                                            return (
                                                <option key={a.id} value={a.id}>
                                                    {codeAnnee} {a.is_current ? '(En cours)' : ''}
                                                </option>
                                            );
                                        })}
                                    </select>
                                </div>

                                <div className="min-w-0">
                                    <Label htmlFor="filter_etab">Filtrer par Campus</Label>
                                    <div className="mt-1">
                                        <SearchableSelect
                                            id="filter_etab"
                                            options={etablissementOptions}
                                            value={selectedEtabId}
                                            onValueChange={(val) => {
                                                setSelectedEtabId(val);
                                                setSelectedClasseId('');
                                            }}
                                            placeholder="Tous les campus / établissements"
                                            searchPlaceholder="Rechercher un établissement..."
                                            emptyMessage="Aucun établissement trouvé pour"
                                        />
                                    </div>
                                </div>

                                <div className="sm:col-span-2 min-w-0">
                                    <Label htmlFor="classe_id">
                                        Classe d'Affectation <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="mt-1">
                                        <SearchableSelect
                                            options={classeOptions}
                                            value={selectedClasseId}
                                            onValueChange={(val) => setSelectedClasseId(val)}
                                            placeholder="-- Sélectionnez la classe d'affectation --"
                                            searchPlaceholder="Rechercher une classe..."
                                            emptyMessage="Aucune classe trouvée."
                                        />
                                    </div>
                                    {errors.classe_id && <p className="text-xs text-destructive mt-1">{errors.classe_id}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="statut">Statut de l'inscription</Label>
                                    <select
                                        id="statut"
                                        value={formData.statut}
                                        onChange={(e) => setFormData({ ...formData, statut: e.target.value })}
                                        className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="validee">Validée (Officielle, sur liste d'appel)</option>
                                        <option value="en_attente">En attente (Dossier à finaliser)</option>
                                    </select>
                                </div>

                                <div>
                                    <Label htmlFor="is_redoublant">Régime d'Admission</Label>
                                    <select
                                        id="is_redoublant"
                                        value={formData.is_redoublant}
                                        onChange={(e) => setFormData({ ...formData, is_redoublant: e.target.value })}
                                        className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="0">Nouvel élève / Promu (Non redoublant)</option>
                                        <option value="1">Redoublant de la classe</option>
                                    </select>
                                </div>

                                <div className="sm:col-span-2">
                                    <Label htmlFor="etablissement_provenance">
                                        Établissement de provenance <span className="text-xs text-muted-foreground font-normal">(Si transfert ou nouvel élève)</span>
                                    </Label>
                                    <Input
                                        id="etablissement_provenance"
                                        value={formData.etablissement_provenance}
                                        onChange={(e) => setFormData({ ...formData, etablissement_provenance: e.target.value })}
                                        placeholder="Ex: Lycée Bilingue d'Essos"
                                        className="mt-1"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <Label htmlFor="observations">Observations / Remarques administratives</Label>
                                    <Input
                                        id="observations"
                                        value={formData.observations}
                                        onChange={(e) => setFormData({ ...formData, observations: e.target.value })}
                                        placeholder="Observations sur le dossier..."
                                        className="mt-1"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Pied de dialogue responsive : navigation d'onglets et actions de soumission */}
                    <DialogFooter className="pt-4 border-t border-border flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-2">
                        <div className="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-start">
                            {activeTab !== 'identite' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        if (activeTab === 'scolarite') setActiveTab('sante');
                                        else if (activeTab === 'sante') setActiveTab('parents');
                                        else if (activeTab === 'parents') setActiveTab('identite');
                                    }}
                                >
                                    Précédent
                                </Button>
                            )}
                            {activeTab !== 'scolarite' && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => {
                                        if (activeTab === 'identite') setActiveTab('parents');
                                        else if (activeTab === 'parents') setActiveTab('sante');
                                        else if (activeTab === 'sante') setActiveTab('scolarite');
                                    }}
                                >
                                    Suivant
                                </Button>
                            )}
                        </div>

                        <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} className="flex-1 sm:flex-none">
                                Annuler
                            </Button>
                            <Button type="submit" disabled={loading} className="gap-2 flex-1 sm:flex-none">
                                {loading ? 'Enregistrement...' : 'Enregistrer & Inscrire'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

import React, { useState, useEffect, useRef } from 'react';
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
import { User, Phone, Edit3, Camera, Trash2, AlertCircle } from 'lucide-react';
import { formatDateForInput, resolveMediaUrl } from '@/lib/utils';
import type { Eleve } from '@/types/eleve';

/**
 * Propriétés du composant EditEleveModal.
 */
interface EditEleveModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    eleve: (Eleve & { is_redoublant?: boolean; inscription_id?: number }) | null;
}

/**
 * Modal d'édition des informations civiles, parentales et médicales d'un élève.
 *
 * @param {EditEleveModalProps} props
 * @returns {JSX.Element}
 */
export function EditEleveModal({ open, onOpenChange, eleve }: EditEleveModalProps) {
    const [loading, setLoading] = useState(false);
    const [activeTab, setActiveTab] = useState<'identite' | 'parents'>('identite');
    const [formData, setFormData] = useState<Omit<Partial<Eleve>, 'sexe'> & { sexe?: 'M' | 'F' | '' | null; is_redoublant?: boolean; inscription_id?: number }>({});
    const [errors, setErrors] = useState<Record<string, string>>({});

    // État local dédié à la prévisualisation, au remplacement et à la suppression de la photo d'identité
    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [previewLoadError, setPreviewLoadError] = useState(false);
    const [removePhotoFlag, setRemovePhotoFlag] = useState(false);
    const photoInputRef = useRef<HTMLInputElement>(null);

    /**
     * Synchronise les champs éditables et l'aperçu photo à chaque ouverture ou changement d'élève sélectionné.
     */
    useEffect(() => {
        if (eleve && open) {
            setFormData({
                matricule_national: eleve.matricule_national || '',
                nom: eleve.nom || '',
                prenom: eleve.prenom || '',
                sexe: eleve.sexe || '',
                date_naissance: formatDateForInput(eleve.date_naissance) || '',
                lieu_naissance: eleve.lieu_naissance || '',
                nationalite: eleve.nationalite || 'CAMEROUNAISE',
                is_redoublant: Boolean(eleve.is_redoublant),
                inscription_id: eleve.inscription_id,
                nom_pere: eleve.nom_pere || '',
                nom_mere: eleve.nom_mere || '',
                nom_tuteur: eleve.nom_tuteur || '',
                telephone_parent: eleve.telephone_parent || '',
                email_parent: eleve.email_parent || '',
                adresse: eleve.adresse || '',
            });
            setPhotoFile(null);
            setPhotoPreview(resolveMediaUrl(eleve.photo_url));
            setPreviewLoadError(false);
            setRemovePhotoFlag(false);
            setErrors({});
            setActiveTab('identite');
            if (photoInputRef.current) {
                photoInputRef.current.value = '';
            }
        }
    }, [eleve, open]);

    /**
     * Valide la taille de la nouvelle photo sélectionnée (5 Mo max) et met à jour l'aperçu immédiat.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} e Événement de changement sur l'input file.
     */
    const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

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
        setRemovePhotoFlag(false);
        setPreviewLoadError(false);

        const reader = new FileReader();
        reader.onload = (event) => {
            setPhotoPreview((event.target?.result as string) ?? null);
        };
        reader.readAsDataURL(file);
    };

    /**
     * Efface la photo sélectionnée ou marque la photo existante pour suppression lors de l'enregistrement.
     */
    const handleRemovePhoto = () => {
        setPhotoFile(null);
        setPhotoPreview(null);
        setPreviewLoadError(false);
        setRemovePhotoFlag(Boolean(eleve?.photo_url));
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
     * Soumet les modifications (y compris l'upload/suppression éventuelle de photo)
     * via POST + spoofing `_method=PUT` en multipart FormData.
     *
     * @param {React.FormEvent} e Événement de soumission du formulaire.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!eleve) return;

        setLoading(true);
        setErrors({});

        // Laravel/PHP n'analyse pas les fichiers multipart sur les requêtes PUT natives :
        // on utilise POST avec `_method=PUT` et `forceFormData: true`.
        const payload = new FormData();
        payload.append('_method', 'PUT');
        payload.append('matricule_national', formData.matricule_national ?? '');
        payload.append('nom', formData.nom ?? '');
        payload.append('prenom', formData.prenom ?? '');
        payload.append('sexe', formData.sexe ?? '');
        payload.append('date_naissance', formData.date_naissance ?? '');
        payload.append('lieu_naissance', formData.lieu_naissance ?? '');
        payload.append('nationalite', formData.nationalite ?? 'CAMEROUNAISE');
        payload.append('is_redoublant', formData.is_redoublant ? '1' : '0');
        if (formData.inscription_id !== undefined && formData.inscription_id !== null) {
            payload.append('inscription_id', String(formData.inscription_id));
        }
        payload.append('nom_pere', formData.nom_pere ?? '');
        payload.append('nom_mere', formData.nom_mere ?? '');
        payload.append('nom_tuteur', formData.nom_tuteur ?? '');
        payload.append('telephone_parent', formData.telephone_parent ?? '');
        payload.append('email_parent', formData.email_parent ?? '');
        payload.append('adresse', formData.adresse ?? '');

        if (photoFile) {
            payload.append('photo', photoFile);
        } else if (removePhotoFlag) {
            payload.append('remove_photo', '1');
        }

        router.post(`/administration/eleves/${eleve.id}`, payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setLoading(false);
                toast.success("Fiche de l'élève mise à jour avec succès !", {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-emerald-700',
                });
                onOpenChange(false);
            },
            onError: (errs) => {
                setLoading(false);
                setErrors(errs);
                // Orienter l'utilisateur vers l'onglet concerné par l'erreur de validation
                if (
                    errs.photo ||
                    errs.nom ||
                    errs.prenom ||
                    errs.sexe ||
                    errs.date_naissance ||
                    errs.lieu_naissance ||
                    errs.nationalite ||
                    errs.matricule_national
                ) {
                    setActiveTab('identite');
                } else if (
                    errs.telephone_parent ||
                    errs.email_parent ||
                    errs.nom_pere ||
                    errs.nom_mere ||
                    errs.nom_tuteur ||
                    errs.adresse
                ) {
                    setActiveTab('parents');
                }
                toast.error('Une erreur est survenue lors de la mise à jour.', {
                    position: 'top-right',
                });
            },
        });
    };

    if (!eleve) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-2xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg sm:text-xl">
                        <Edit3 className="size-5 text-primary shrink-0" />
                        <span>Modifier la fiche de {eleve.nom_complet}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Matricule : <span className="font-mono font-semibold">{eleve.matricule}</span>
                    </DialogDescription>
                </DialogHeader>

                {/* Onglets avec support de défilement horizontal sur mobile */}
                <div className="flex border-b border-border mt-2 gap-1 overflow-x-auto pb-1 shrink-0">
                    <button
                        type="button"
                        onClick={() => setActiveTab('identite')}
                        className={`flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 whitespace-nowrap shrink-0 ${
                            activeTab === 'identite'
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <User className="size-4 shrink-0" />
                        <span>État Civil</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('parents')}
                        className={`flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 whitespace-nowrap shrink-0 ${
                            activeTab === 'parents'
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Phone className="size-4 shrink-0" />
                        <span>Parents & Contacts</span>
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Synthèse des erreurs de validation serveur si présentes */}
                    {Object.keys(errors).length > 0 && (
                        <div className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                            <AlertCircle className="size-4 shrink-0 mt-0.5" />
                            <div className="space-y-0.5">
                                {Object.values(errors).map((message, idx) => (
                                    <p key={idx}>{message}</p>
                                ))}
                            </div>
                        </div>
                    )}

                    {activeTab === 'identite' && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 animate-in fade-in-50">
                            {/* Section dédiée à la modification ou suppression de la photo d'identité */}
                            <div className="sm:col-span-2 flex flex-col sm:flex-row items-center gap-4 p-3.5 rounded-xl bg-muted/40 border border-border">
                                <div className="size-20 rounded-2xl border-2 border-dashed border-primary/30 bg-background overflow-hidden flex items-center justify-center shrink-0 shadow-xs">
                                    {photoPreview && !previewLoadError ? (
                                        <img
                                            key={photoPreview}
                                            src={photoPreview}
                                            alt={eleve.nom_complet}
                                            className="size-full object-cover"
                                            onError={() => setPreviewLoadError(true)}
                                        />
                                    ) : (
                                        <User className="size-8 text-muted-foreground/60" />
                                    )}
                                </div>
                                <div className="flex-1 text-center sm:text-left space-y-1.5">
                                    <div className="text-sm font-semibold text-foreground">Photo d&apos;identité</div>
                                    <p className="text-xs text-muted-foreground">
                                        Format JPG, PNG ou WEBP (max 5 Mo). Visible dans la liste, la fiche détaillée et les cartes scolaires.
                                    </p>
                                    <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2 pt-0.5">
                                        <input
                                            ref={photoInputRef}
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp,image/gif"
                                            className="hidden"
                                            onChange={handlePhotoChange}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => photoInputRef.current?.click()}
                                            className="h-8 text-xs gap-1.5"
                                        >
                                            <Camera className="size-3.5" />
                                            {photoPreview ? 'Changer la photo' : 'Ajouter une photo'}
                                        </Button>
                                        {(photoPreview || eleve.photo_url) && !removePhotoFlag && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={handleRemovePhoto}
                                                className="h-8 text-xs text-destructive hover:text-destructive hover:bg-destructive/10 gap-1"
                                            >
                                                <Trash2 className="size-3.5" />
                                                Supprimer
                                            </Button>
                                        )}
                                    </div>
                                    {errors.photo && <p className="text-xs text-destructive">{errors.photo}</p>}
                                </div>
                            </div>
                            {/* Matricule interne strictement non cliquable et non modifiable (lecture seule) */}
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <span className="text-sm font-medium leading-none text-slate-700">
                                        Matricule Interne
                                    </span>
                                    <span className="text-[10px] px-1.5 py-0.5 rounded-full font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        Non modifiable
                                    </span>
                                </div>
                                <div className="mt-1 cursor-not-allowed select-none">
                                    <Input
                                        id="edit_matricule"
                                        value={eleve.matricule || ''}
                                        readOnly
                                        disabled
                                        aria-readonly="true"
                                        tabIndex={-1}
                                        className="font-mono bg-slate-100/80 text-slate-600 border-dashed focus-visible:ring-0 pointer-events-none select-none disabled:opacity-90"
                                    />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="edit_matricule_national">Matricule National MINESEC</Label>
                                <Input
                                    id="edit_matricule_national"
                                    value={formData.matricule_national || ''}
                                    onChange={(e) => setFormData({ ...formData, matricule_national: e.target.value })}
                                    placeholder="Ex: MIN-2026-XXXX"
                                    className="font-mono mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_nom">Nom de famille</Label>
                                <Input
                                    id="edit_nom"
                                    value={formData.nom || ''}
                                    onChange={(e) => setFormData({ ...formData, nom: e.target.value })}
                                    required
                                    className="mt-1 uppercase"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_prenom">Prénom(s)</Label>
                                <Input
                                    id="edit_prenom"
                                    value={formData.prenom || ''}
                                    onChange={(e) => setFormData({ ...formData, prenom: e.target.value })}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_sexe">Sexe</Label>
                                <select
                                    id="edit_sexe"
                                    value={formData.sexe || ''}
                                    onChange={(e) => setFormData({ ...formData, sexe: (e.target.value || '') as 'M' | 'F' | '' })}
                                    className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="">Non défini (À compléter)</option>
                                    <option value="M">Masculin (M)</option>
                                    <option value="F">Féminin (F)</option>
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="edit_date_naissance">Date de naissance</Label>
                                <Input
                                    id="edit_date_naissance"
                                    type="date"
                                    value={formData.date_naissance ? formatDateForInput(formData.date_naissance) : ''}
                                    onChange={(e) => setFormData({ ...formData, date_naissance: e.target.value })}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_lieu_naissance">Lieu de naissance</Label>
                                <Input
                                    id="edit_lieu_naissance"
                                    value={formData.lieu_naissance || ''}
                                    onChange={(e) => setFormData({ ...formData, lieu_naissance: e.target.value })}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_nationalite">Nationalité</Label>
                                <Input
                                    id="edit_nationalite"
                                    value={formData.nationalite || ''}
                                    onChange={(e) => setFormData({ ...formData, nationalite: e.target.value })}
                                    className="mt-1"
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="edit_is_redoublant">Régime d'Admission (Statut scolaire)</Label>
                                <select
                                    id="edit_is_redoublant"
                                    value={formData.is_redoublant ? '1' : '0'}
                                    onChange={(e) => setFormData({ ...formData, is_redoublant: e.target.value === '1' })}
                                    className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="0">Nouvel élève / Promu (Non redoublant)</option>
                                    <option value="1">Redoublant de la classe</option>
                                </select>
                            </div>
                        </div>
                    )}

                    {activeTab === 'parents' && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 animate-in fade-in-50">
                            <div className="sm:col-span-2">
                                <Label htmlFor="edit_telephone_parent">Téléphone Principal Parent/Tuteur</Label>
                                <Input
                                    id="edit_telephone_parent"
                                    value={formData.telephone_parent || ''}
                                    onChange={(e) => setFormData({ ...formData, telephone_parent: e.target.value })}
                                    className="mt-1 font-mono"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_nom_pere">Nom du Père</Label>
                                <Input
                                    id="edit_nom_pere"
                                    value={formData.nom_pere || ''}
                                    onChange={(e) => setFormData({ ...formData, nom_pere: e.target.value })}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_nom_mere">Nom de la Mère</Label>
                                <Input
                                    id="edit_nom_mere"
                                    value={formData.nom_mere || ''}
                                    onChange={(e) => setFormData({ ...formData, nom_mere: e.target.value })}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_nom_tuteur">Nom du Tuteur légal</Label>
                                <Input
                                    id="edit_nom_tuteur"
                                    value={formData.nom_tuteur || ''}
                                    onChange={(e) => setFormData({ ...formData, nom_tuteur: e.target.value })}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="edit_email_parent">Email du parent</Label>
                                <Input
                                    id="edit_email_parent"
                                    type="email"
                                    value={formData.email_parent || ''}
                                    onChange={(e) => setFormData({ ...formData, email_parent: e.target.value })}
                                    className="mt-1"
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="edit_adresse">Adresse / Quartier</Label>
                                <Input
                                    id="edit_adresse"
                                    value={formData.adresse || ''}
                                    onChange={(e) => setFormData({ ...formData, adresse: e.target.value })}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                    )}

                    {/* Pied de modal responsive avec boutons pleine largeur sur mobile */}
                    <DialogFooter className="pt-4 border-t border-border flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} className="w-full sm:w-auto">
                            Annuler
                        </Button>
                        <Button type="submit" disabled={loading} className="w-full sm:w-auto">
                            {loading ? 'Enregistrement...' : 'Enregistrer les modifications'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

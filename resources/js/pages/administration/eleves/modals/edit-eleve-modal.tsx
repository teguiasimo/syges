import React, { useState, useEffect } from 'react';
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
import { User, Phone, Edit3 } from 'lucide-react';
import type { Eleve } from '@/types/eleve';

/**
 * Propriétés du composant EditEleveModal.
 */
interface EditEleveModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    eleve: Eleve | null;
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
    const [formData, setFormData] = useState<Partial<Eleve>>({});

    useEffect(() => {
        if (eleve) {
            setFormData({
                matricule: eleve.matricule || '',
                matricule_national: eleve.matricule_national || '',
                nom: eleve.nom || '',
                prenom: eleve.prenom || '',
                sexe: eleve.sexe || 'M',
                date_naissance: eleve.date_naissance || '',
                lieu_naissance: eleve.lieu_naissance || '',
                nationalite: eleve.nationalite || 'CAMEROUNAISE',
                nom_pere: eleve.nom_pere || '',
                nom_mere: eleve.nom_mere || '',
                nom_tuteur: eleve.nom_tuteur || '',
                telephone_parent: eleve.telephone_parent || '',
                email_parent: eleve.email_parent || '',
                adresse: eleve.adresse || '',
            });
        }
    }, [eleve]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!eleve) return;

        setLoading(true);

        router.put(`/administration/eleves/${eleve.id}`, formData, {
            onSuccess: () => {
                setLoading(false);
                toast.success("Fiche de l'élève mise à jour avec succès !");
                onOpenChange(false);
            },
            onError: () => {
                setLoading(false);
                toast.error('Une erreur est survenue lors de la mise à jour.');
            },
        });
    };

    if (!eleve) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <Edit3 className="size-5 text-primary" />
                        Modifier la fiche de {eleve.nom_complet}
                    </DialogTitle>
                    <DialogDescription>
                        Matricule : <span className="font-mono font-semibold">{eleve.matricule}</span>
                    </DialogDescription>
                </DialogHeader>

                {/* Tabs */}
                <div className="flex border-b border-border mt-2 gap-1">
                    <button
                        type="button"
                        onClick={() => setActiveTab('identite')}
                        className={`flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 ${
                            activeTab === 'identite'
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <User className="size-4" />
                        État Civil
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('parents')}
                        className={`flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 ${
                            activeTab === 'parents'
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Phone className="size-4" />
                        Parents & Contacts
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {activeTab === 'identite' && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 animate-in fade-in-50">
                            <div>
                                <Label htmlFor="edit_matricule">Matricule Interne</Label>
                                <Input
                                    id="edit_matricule"
                                    value={formData.matricule || ''}
                                    onChange={(e) => setFormData({ ...formData, matricule: e.target.value })}
                                    required
                                    className="font-mono mt-1"
                                />
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
                                    value={formData.sexe || 'M'}
                                    onChange={(e) => setFormData({ ...formData, sexe: e.target.value as 'M' | 'F' })}
                                    className="w-full mt-1 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="M">Masculin (M)</option>
                                    <option value="F">Féminin (F)</option>
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="edit_date_naissance">Date de naissance</Label>
                                <Input
                                    id="edit_date_naissance"
                                    type="date"
                                    value={formData.date_naissance ? String(formData.date_naissance).slice(0, 10) : ''}
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
                        </div>
                    )}

                    {activeTab === 'parents' && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 animate-in fade-in-50">
                            <div className="sm:col-span-2">
                                <Label htmlFor="edit_telephone_parent">Téléphone Principal Parent/Tuteur *</Label>
                                <Input
                                    id="edit_telephone_parent"
                                    value={formData.telephone_parent || ''}
                                    onChange={(e) => setFormData({ ...formData, telephone_parent: e.target.value })}
                                    required
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

                    <DialogFooter className="pt-4 border-t border-border">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={loading}>
                            {loading ? 'Enregistrement...' : 'Enregistrer les modifications'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

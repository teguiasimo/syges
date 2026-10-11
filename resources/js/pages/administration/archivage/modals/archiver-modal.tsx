import { useForm } from '@inertiajs/react';
import { Archive, GraduationCap, Loader2, Save, Users } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
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
import { formatDateForInput } from '@/lib/utils';

interface ArchiverModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    elevesActifs: Array<{ id: number; matricule: string; nom: string; prenom?: string }>;
    personnelsActifs: Array<{
        id: number;
        matricule: string;
        nom: string;
        prenom?: string;
        fonction_principale: string;
    }>;
}

/**
 * Modal d'archivage administratif pour élèves et personnels sortants (Aurore §3.27.8 & §3.28.3).
 */
export function ArchiverModal({
    open,
    onOpenChange,
    elevesActifs,
    personnelsActifs,
}: ArchiverModalProps) {
    const [typeEntite, setTypeEntite] = useState<'eleve' | 'personnel'>('eleve');

    const { data, setData, post, processing, errors, reset } = useForm({
        type_entite: 'eleve',
        eleve_id: '',
        personnel_id: '',
        motif: 'diplome',
        date_archivage: formatDateForInput(new Date()),
        destination: '',
        observations: '',
    });

    const handleTypeChange = (type: 'eleve' | 'personnel') => {
        setTypeEntite(type);
        setData((prev) => ({
            ...prev,
            type_entite: type,
            eleve_id: '',
            personnel_id: '',
            motif: type === 'eleve' ? 'diplome' : 'retraite',
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/administration/archivage', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Dossier archivé avec succès dans le Centre d\'Archivage', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white font-medium border-none shadow-lg',
                });
                reset();
                onOpenChange(false);
            },
            onError: () => {
                toast.error('Veuillez vérifier les informations saisies', {
                    position: 'top-right',
                });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-xl">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
                        <Archive className="w-5 h-5" />
                        <DialogTitle>Archiver un dossier administratif</DialogTitle>
                    </div>
                    <DialogDescription>
                        Consignez le départ officiel d'un élève ou personnel avec motif légal. Le dossier reste consultable dans les archives historiques.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Choix Élève ou Personnel */}
                    <div className="flex rounded-lg border p-1 bg-slate-50 dark:bg-slate-900">
                        <button
                            type="button"
                            className={`flex-1 flex items-center justify-center gap-2 py-2 text-xs font-semibold rounded-md transition-all ${
                                typeEntite === 'eleve'
                                    ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-900'
                            }`}
                            onClick={() => handleTypeChange('eleve')}
                        >
                            <GraduationCap className="w-4 h-4" />
                            Archiver un Élève
                        </button>
                        <button
                            type="button"
                            className={`flex-1 flex items-center justify-center gap-2 py-2 text-xs font-semibold rounded-md transition-all ${
                                typeEntite === 'personnel'
                                    ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-900'
                            }`}
                            onClick={() => handleTypeChange('personnel')}
                        >
                            <Users className="w-4 h-4" />
                            Archiver un Membre du Personnel
                        </button>
                    </div>

                    {/* Sélection de la personne */}
                    {typeEntite === 'eleve' ? (
                        <div className="space-y-1.5">
                            <Label htmlFor="eleve_id" className="text-xs font-semibold">
                                Sélectionner l'Élève <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.eleve_id}
                                onValueChange={(val) => setData('eleve_id', val)}
                            >
                                <SelectTrigger id="eleve_id">
                                    <SelectValue placeholder="Choisir un élève actif..." />
                                </SelectTrigger>
                                <SelectContent className="max-h-60">
                                    {elevesActifs.map((el) => (
                                        <SelectItem key={el.id} value={String(el.id)}>
                                            {el.nom} {el.prenom} ({el.matricule})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.eleve_id && (
                                <p className="text-xs text-rose-500">{errors.eleve_id}</p>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-1.5">
                            <Label htmlFor="personnel_id" className="text-xs font-semibold">
                                Sélectionner le Membre du Personnel <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.personnel_id}
                                onValueChange={(val) => setData('personnel_id', val)}
                            >
                                <SelectTrigger id="personnel_id">
                                    <SelectValue placeholder="Choisir un personnel..." />
                                </SelectTrigger>
                                <SelectContent className="max-h-60">
                                    {personnelsActifs.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nom} {p.prenom} ({p.fonction_principale} - {p.matricule})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.personnel_id && (
                                <p className="text-xs text-rose-500">{errors.personnel_id}</p>
                            )}
                        </div>
                    )}

                    {/* Motif d'archivage & Date d'effet */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="motif" className="text-xs font-semibold">
                                Motif officiel du départ <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.motif}
                                onValueChange={(val) => setData('motif', val)}
                            >
                                <SelectTrigger id="motif">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {typeEntite === 'eleve' ? (
                                        <>
                                            <SelectItem value="diplome">Fin de cycle / Diplômé</SelectItem>
                                            <SelectItem value="transfert_radiation">Radiation / Transfert externe</SelectItem>
                                            <SelectItem value="exclusion_definitive">Exclusion définitive</SelectItem>
                                            <SelectItem value="abandon">Abandon de scolarité</SelectItem>
                                            <SelectItem value="deces">Décès</SelectItem>
                                            <SelectItem value="autre">Autre motif</SelectItem>
                                        </>
                                    ) : (
                                        <>
                                            <SelectItem value="retraite">Départ à la retraite</SelectItem>
                                            <SelectItem value="demission">Démission volontaire</SelectItem>
                                            <SelectItem value="fin_contrat">Fin de contrat / Vacation</SelectItem>
                                            <SelectItem value="licenciement">Licenciement</SelectItem>
                                            <SelectItem value="mutation">Mutation / Affectation ministérielle</SelectItem>
                                            <SelectItem value="deces">Décès</SelectItem>
                                            <SelectItem value="autre">Autre motif</SelectItem>
                                        </>
                                    )}
                                </SelectContent>
                            </Select>
                            {errors.motif && (
                                <p className="text-xs text-rose-500">{errors.motif}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="date_archivage" className="text-xs font-semibold">
                                Date d'effet de l'archivage <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="date_archivage"
                                type="date"
                                value={data.date_archivage}
                                onChange={(e) => setData('date_archivage', e.target.value)}
                                required
                            />
                            {errors.date_archivage && (
                                <p className="text-xs text-rose-500">{errors.date_archivage}</p>
                            )}
                        </div>
                    </div>

                    {/* Destination (Nouvelle école ou nouvelle structure) */}
                    <div className="space-y-1.5">
                        <Label htmlFor="destination" className="text-xs font-semibold">
                            Destination (Établissement d'accueil ou nouvel organisme)
                        </Label>
                        <Input
                            id="destination"
                            placeholder="Ex: Lycée Général Leclerc, Yaoundé"
                            value={data.destination}
                            onChange={(e) => setData('destination', e.target.value)}
                        />
                        {errors.destination && (
                            <p className="text-xs text-rose-500">{errors.destination}</p>
                        )}
                    </div>

                    {/* Observations / Références */}
                    <div className="space-y-1.5">
                        <Label htmlFor="observations" className="text-xs font-semibold">
                            Observations et références légales (PV du conseil, décision ministérielle...)
                        </Label>
                        <Textarea
                            id="observations"
                            rows={2}
                            placeholder="Ex: Procès-verbal du conseil de discipline n° 04/2026..."
                            value={data.observations}
                            onChange={(e) => setData('observations', e.target.value)}
                        />
                    </div>

                    <DialogFooter className="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white gap-1.5"
                            disabled={processing}
                        >
                            {processing ? (
                                <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                                <Save className="w-4 h-4" />
                            )}
                            Confirmer la mise en archive
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

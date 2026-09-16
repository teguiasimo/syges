import React from 'react';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { JourSemaine, Salle } from '@/types/emploi-du-temps';
import { CalendarPlus } from 'lucide-react';

interface NouvelleSeanceModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    anneeId: number;
    classes: { id: number; nom: string }[];
    salles: Salle[];
    attributions: any[];
    selectedClasseId: number;
}

/**
 * Modale de planification d'un cours hebdomadaire (Module 13.2 & 13.3).
 *
 * @param {NouvelleSeanceModalProps} props
 * @returns {JSX.Element}
 */
export default function NouvelleSeanceModal({
    open,
    onOpenChange,
    anneeId,
    classes,
    salles,
    attributions,
    selectedClasseId,
}: NouvelleSeanceModalProps) {
    const { data, setData, post, processing, reset, errors, transform } = useForm({
        annee_scolaire_id: anneeId,
        classe_id: selectedClasseId ? String(selectedClasseId) : '',
        attribution_id: '',
        salle_id: '',
        jour_semaine: 'lundi' as JourSemaine,
        heure_debut: '08:00',
        heure_fin: '10:00',
    });

    // Synchroniser la classe sélectionnée si elle change
    React.useEffect(() => {
        if (selectedClasseId && !data.classe_id) {
            setData('classe_id', String(selectedClasseId));
        }
    }, [selectedClasseId]);

    // Filtrer les cours disponibles pour la classe sélectionnée
    const filteredAttributions = React.useMemo(() => {
        if (!data.classe_id) return attributions;
        return attributions.filter((a) => String(a.classe_id) === String(data.classe_id));
    }, [attributions, data.classe_id]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formValues) => ({
            ...formValues,
            annee_scolaire_id: anneeId,
            classe_id: Number(formValues.classe_id),
            attribution_id: Number(formValues.attribution_id),
            salle_id: formValues.salle_id ? Number(formValues.salle_id) : null,
        }));

        post('/administration/emplois-du-temps/seances', {
            onSuccess: () => {
                toast.success('Séance planifiée avec succès (aucun conflit détecté).', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                reset();
                onOpenChange(false);
            },
            onError: (err) => {
                const message =
                    err.attribution_id ||
                    err.classe_id ||
                    err.salle_id ||
                    err.heure_fin ||
                    err.error ||
                    'Conflit détecté lors de la planification.';
                toast.error(message, { position: 'top-right' });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
                        <CalendarPlus className="w-5 h-5 text-indigo-600" />
                        Planifier un Cours Hebdomadaire
                    </DialogTitle>
                    <DialogDescription>
                        Programmation avec vérification automatique des conflits (enseignant, salle, classe).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-3.5 py-2">
                    <div className="space-y-1">
                        <Label htmlFor="classe_id" className="text-xs font-semibold">
                            Classe bénéficiaire <span className="text-rose-500">*</span>
                        </Label>
                        <Select
                            value={data.classe_id}
                            onValueChange={(val) => {
                                setData('classe_id', val);
                                setData('attribution_id', '');
                            }}
                        >
                            <SelectTrigger id="classe_id" className="text-xs h-9">
                                <SelectValue placeholder="Sélectionner une classe..." />
                            </SelectTrigger>
                            <SelectContent className="max-h-56">
                                {classes.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)} className="text-xs">
                                        {c.nom}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.classe_id && <p className="text-xs text-rose-500">{errors.classe_id}</p>}
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="attribution_id" className="text-xs font-semibold">
                            Cours / Matière & Enseignant <span className="text-rose-500">*</span>
                        </Label>
                        <Select
                            value={data.attribution_id}
                            onValueChange={(val) => setData('attribution_id', val)}
                            disabled={!data.classe_id}
                        >
                            <SelectTrigger id="attribution_id" className="text-xs h-9">
                                <SelectValue placeholder={data.classe_id ? 'Sélectionner un cours...' : 'Choisissez d\'abord une classe'} />
                            </SelectTrigger>
                            <SelectContent className="max-h-56">
                                {filteredAttributions.map((a) => (
                                    <SelectItem key={a.id} value={String(a.id)} className="text-xs">
                                        {a.matiere?.nom} — Prof. {a.personnel?.nom} {a.personnel?.prenom} ({a.volume_horaire_reel}h/sem)
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.attribution_id && <p className="text-xs text-rose-500">{errors.attribution_id}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="jour_semaine" className="text-xs font-semibold">
                                Jour <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.jour_semaine}
                                onValueChange={(val: JourSemaine) => setData('jour_semaine', val)}
                            >
                                <SelectTrigger id="jour_semaine" className="text-xs h-9">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="lundi">Lundi</SelectItem>
                                    <SelectItem value="mardi">Mardi</SelectItem>
                                    <SelectItem value="mercredi">Mercredi</SelectItem>
                                    <SelectItem value="jeudi">Jeudi</SelectItem>
                                    <SelectItem value="vendredi">Vendredi</SelectItem>
                                    <SelectItem value="samedi">Samedi</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="salle_id" className="text-xs font-semibold">
                                Salle physique (Optionnel)
                            </Label>
                            <Select
                                value={data.salle_id}
                                onValueChange={(val) => setData('salle_id', val)}
                            >
                                <SelectTrigger id="salle_id" className="text-xs h-9">
                                    <SelectValue placeholder="Attribution libre" />
                                </SelectTrigger>
                                <SelectContent className="max-h-56">
                                    <SelectItem value="none">Aucune salle spécifique</SelectItem>
                                    {salles.map((s) => (
                                        <SelectItem key={s.id} value={String(s.id)} className="text-xs">
                                            {s.nom} ({s.capacite_places} pl.)
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.salle_id && <p className="text-xs text-rose-500">{errors.salle_id}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="heure_debut" className="text-xs font-semibold">
                                Heure début <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="heure_debut"
                                type="time"
                                value={data.heure_debut}
                                onChange={(e) => setData('heure_debut', e.target.value)}
                                className="text-xs h-9"
                                required
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="heure_fin" className="text-xs font-semibold">
                                Heure fin <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="heure_fin"
                                type="time"
                                value={data.heure_fin}
                                onChange={(e) => setData('heure_fin', e.target.value)}
                                className="text-xs h-9"
                                required
                            />
                        </div>
                    </div>
                    {errors.heure_fin && <p className="text-xs text-rose-500">{errors.heure_fin}</p>}

                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                reset();
                                onOpenChange(false);
                            }}
                            disabled={processing}
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
                            disabled={processing}
                        >
                            {processing ? 'Contrôle anti-conflit...' : 'Enregistrer le Cours'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

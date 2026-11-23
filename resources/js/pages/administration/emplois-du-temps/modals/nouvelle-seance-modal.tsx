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
import {
    SearchableSelect,
    type SearchableOption,
} from '@/components/ui/searchable-select';
import { JourSemaine, Salle } from '@/types/emploi-du-temps';
import { CalendarPlus, Plus } from 'lucide-react';

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

    // Options adaptatives pour SearchableSelect : libellé concis et recherche instantanée
    const attributionOptions = React.useMemo<SearchableOption[]>(() => {
        return filteredAttributions.map((a) => {
            const code = a.matiere?.code ? `[${a.matiere.code}] ` : '';
            const prof = a.personnel ? `Prof. ${a.personnel.nom} ${a.personnel.prenom || ''}`.trim() : 'Professeur non assigné';
            const volume = a.volume_horaire_reel ? ` (${a.volume_horaire_reel}h/sem)` : '';
            const rawNom = a.matiere?.nom || 'Cours';
            // Libellé court pour ne pas surcharger la largeur de l'écran
            const shortNom = rawNom.length > 40 ? `${rawNom.substring(0, 37)}...` : rawNom;

            return {
                value: String(a.id),
                label: `${code}${shortNom} — ${prof}${volume}`,
                description: rawNom !== shortNom ? rawNom : undefined,
            };
        });
    }, [filteredAttributions]);

    /**
     * Enregistre le créneau de cours.
     * Permet soit de fermer la modale, soit de la laisser ouverte pour enchaîner plusieurs créneaux
     * pour le même cours sans devoir rouvrir et resélectionner la matière (gain de clics).
     *
     * @param {React.FormEvent} [e] Événement de soumission.
     * @param {boolean} [keepOpen=false] Indique si la modale doit rester ouverte pour le créneau suivant.
     */
    const handleSubmit = (e?: React.FormEvent, keepOpen = false) => {
        if (e) e.preventDefault();

        if (!data.classe_id) {
            toast.error('Veuillez sélectionner une classe bénéficiaire.', { position: 'top-right' });
            return;
        }
        if (!data.attribution_id) {
            toast.error('Veuillez sélectionner un cours et un enseignant.', { position: 'top-right' });
            return;
        }

        transform((formValues) => ({
            ...formValues,
            annee_scolaire_id: anneeId,
            classe_id: Number(formValues.classe_id),
            attribution_id: Number(formValues.attribution_id),
            salle_id: formValues.salle_id && formValues.salle_id !== 'none' ? Number(formValues.salle_id) : null,
        }));

        post('/administration/emplois-du-temps/seances', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    keepOpen
                        ? 'Créneau enregistré avec succès ! Vous pouvez directement définir le créneau suivant.'
                        : 'Séance planifiée avec succès (aucun conflit détecté).',
                    {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    }
                );

                if (keepOpen) {
                    // Conserve la classe, le cours et la salle pour enchaîner sans friction
                    setData((prev) => ({
                        ...prev,
                        heure_debut: '10:00',
                        heure_fin: '12:00',
                    }));
                } else {
                    reset();
                    onOpenChange(false);
                }
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
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base sm:text-lg font-bold text-slate-800">
                        <CalendarPlus className="w-5 h-5 text-teal-600 shrink-0" />
                        <span>Planifier un Cours Hebdomadaire</span>
                    </DialogTitle>
                    <DialogDescription>
                        Programmation avec vérification automatique des conflits (enseignant, salle, classe).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={(e) => handleSubmit(e, false)} className="space-y-3.5 py-2">
                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="classe_id" className="text-xs font-semibold">
                            Classe bénéficiaire <span className="text-rose-500">*</span>
                        </Label>
                        <SearchableSelect
                            id="classe_id"
                            options={classes.map((c) => ({
                                value: String(c.id),
                                label: c.nom,
                            }))}
                            value={data.classe_id}
                            onValueChange={(val) => {
                                setData('classe_id', val);
                                setData('attribution_id', '');
                            }}
                            placeholder="Sélectionner une classe..."
                            searchPlaceholder="Rechercher une classe..."
                            emptyMessage="Aucune classe trouvée."
                            className="text-xs h-9 font-normal"
                        />
                        {errors.classe_id && <p className="text-xs text-rose-500">{errors.classe_id}</p>}
                    </div>

                    <div className="space-y-1 min-w-0">
                        <Label htmlFor="attribution_id" className="text-xs font-semibold">
                            Cours / Matière & Enseignant <span className="text-rose-500">*</span>
                        </Label>
                        <SearchableSelect
                            disabled={!data.classe_id}
                            value={data.attribution_id}
                            onValueChange={(val) => setData('attribution_id', val)}
                            options={attributionOptions}
                            placeholder={data.classe_id ? 'Sélectionner un cours...' : 'Choisissez d\'abord une classe'}
                            searchPlaceholder="Rechercher une matière ou un enseignant..."
                            emptyMessage="Aucun cours trouvé pour"
                            className="text-xs h-9 w-full font-normal"
                        />
                        {errors.attribution_id && <p className="text-xs text-rose-500">{errors.attribution_id}</p>}
                    </div>

                    {/* Grille adaptative empilant jour/salle et horaires sur mobile */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1 min-w-0">
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

                        <div className="space-y-1 min-w-0">
                            <Label htmlFor="salle_id" className="text-xs font-semibold">
                                Salle physique (Optionnel)
                            </Label>
                            <SearchableSelect
                                id="salle_id"
                                options={[
                                    { value: 'none', label: 'Aucune salle spécifique' },
                                    ...salles.map((s) => ({
                                        value: String(s.id),
                                        label: s.nom,
                                        description: `${s.capacite_places} pl.`,
                                    })),
                                ]}
                                value={data.salle_id}
                                onValueChange={(val) => setData('salle_id', val)}
                                placeholder="Attribution libre"
                                searchPlaceholder="Rechercher une salle..."
                                emptyMessage="Aucune salle trouvée."
                                className="text-xs h-9 font-normal"
                            />
                            {errors.salle_id && <p className="text-xs text-rose-500">{errors.salle_id}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
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

                    <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                reset();
                                onOpenChange(false);
                            }}
                            disabled={processing}
                            className="w-full sm:w-auto"
                        >
                            Fermer
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleSubmit(undefined, true)}
                            disabled={processing || !data.attribution_id}
                            className="w-full sm:w-auto border-teal-200 text-teal-700 hover:bg-teal-50 font-semibold gap-1.5"
                            title="Enregistre ce créneau et garde la modale ouverte pour programmer un autre horaire"
                        >
                            <Plus className="w-4 h-4 text-teal-600 shrink-0" />
                            Enregistrer & Autre créneau
                        </Button>
                        <Button
                            type="submit"
                            className="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white font-semibold"
                            disabled={processing || !data.attribution_id}
                        >
                            {processing ? 'Enregistrement...' : 'Enregistrer & Fermer'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

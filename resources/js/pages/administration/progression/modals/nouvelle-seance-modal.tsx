import { useForm } from '@inertiajs/react';
import { BookOpen, Calendar, Clock, FileText, Loader2, Save } from 'lucide-react';
import React, { useEffect } from 'react';
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
import type { FicheProgression } from '@/types/progression';

interface NouvelleSeanceModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editingSeance?: FicheProgression | null;
    attributions: Array<{
        id: number;
        volume_horaire_reel: number;
        classe?: { id: number; nom: string; code: string };
        matiere?: { id: number; nom: string; code: string };
        personnel?: { id: number; nom: string; prenom?: string };
    }>;
    periodes: Array<{ id: number; nom: string; code: string }>;
    defaultAttributionId?: number | null;
}

/**
 * Modal de saisie / modification d'une séance dans le cahier de texte (Aurore 5.1.8).
 */
export function NouvelleSeanceModal({
    open,
    onOpenChange,
    editingSeance,
    attributions,
    periodes,
    defaultAttributionId,
}: NouvelleSeanceModalProps) {
    const isEdit = !!editingSeance;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        attribution_id: editingSeance?.attribution_id ? String(editingSeance.attribution_id) : (defaultAttributionId ? String(defaultAttributionId) : ''),
        periode_id: editingSeance?.periode_id ? String(editingSeance.periode_id) : '',
        date_seance: formatDateForInput(editingSeance?.date_seance || new Date()),
        semaine_numero: editingSeance?.semaine_numero ? String(editingSeance.semaine_numero) : '',
        chapitre: editingSeance?.chapitre || '',
        titre_lecon: editingSeance?.titre_lecon || '',
        resume_activite: editingSeance?.resume_activite || '',
        travail_a_faire: editingSeance?.travail_a_faire || '',
        heures_prevues: editingSeance?.heures_prevues ? String(editingSeance.heures_prevues) : '2.0',
        heures_effectuees: editingSeance?.heures_effectuees ? String(editingSeance.heures_effectuees) : '2.0',
        statut: editingSeance?.statut || 'fait',
        motif_report: editingSeance?.motif_report || '',
    });

    useEffect(() => {
        if (editingSeance) {
            setData({
                attribution_id: String(editingSeance.attribution_id),
                periode_id: editingSeance.periode_id ? String(editingSeance.periode_id) : '',
                date_seance: formatDateForInput(editingSeance.date_seance),
                semaine_numero: editingSeance.semaine_numero ? String(editingSeance.semaine_numero) : '',
                chapitre: editingSeance.chapitre,
                titre_lecon: editingSeance.titre_lecon,
                resume_activite: editingSeance.resume_activite || '',
                travail_a_faire: editingSeance.travail_a_faire || '',
                heures_prevues: String(editingSeance.heures_prevues),
                heures_effectuees: String(editingSeance.heures_effectuees),
                statut: editingSeance.statut,
                motif_report: editingSeance.motif_report || '',
            });
        } else {
            reset();
            if (defaultAttributionId) {
                setData((prev) => ({ ...prev, attribution_id: String(defaultAttributionId) }));
            }
        }
    }, [editingSeance, defaultAttributionId, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit) {
            put(`/administration/progression-pedagogique/${editingSeance.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Séance de cours mise à jour avec succès', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white font-medium border-none shadow-lg',
                    });
                    onOpenChange(false);
                },
                onError: () => {
                    toast.error('Erreur lors de la mise à jour de la séance', {
                        position: 'top-right',
                    });
                },
            });
        } else {
            post('/administration/progression-pedagogique', {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Séance enregistrée avec succès dans le cahier de texte', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white font-medium border-none shadow-lg',
                    });
                    reset();
                    onOpenChange(false);
                },
                onError: () => {
                    toast.error('Veuillez vérifier les champs du formulaire', {
                        position: 'top-right',
                    });
                },
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-teal-600 dark:text-teal-400">
                        <BookOpen className="w-5 h-5 shrink-0" />
                        <DialogTitle>
                            {isEdit ? 'Modifier la séance du cours' : 'Consigner une séance dans le cahier de texte'}
                        </DialogTitle>
                    </div>
                    <DialogDescription>
                        Renseignez les détails didactiques, le volume horaire et les devoirs assignés pour le suivi de la progression.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Attribution & Période */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5 min-w-0">
                            <Label htmlFor="attribution_id" className="text-xs font-semibold">
                                Cours & Classe <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.attribution_id}
                                onValueChange={(val) => setData('attribution_id', val)}
                                disabled={isEdit}
                            >
                                <SelectTrigger id="attribution_id" className="w-full min-w-0">
                                    <SelectValue placeholder="Sélectionner un cours..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {attributions.map((attr) => (
                                        <SelectItem key={attr.id} value={String(attr.id)}>
                                            {attr.classe?.nom} - {attr.matiere?.nom} ({attr.personnel?.nom})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.attribution_id && (
                                <p className="text-xs text-rose-500">{errors.attribution_id}</p>
                            )}
                        </div>

                        <div className="space-y-1.5 min-w-0">
                            <Label htmlFor="periode_id" className="text-xs font-semibold">
                                Trimestre / Période
                            </Label>
                            <Select
                                value={data.periode_id}
                                onValueChange={(val) => setData('periode_id', val)}
                            >
                                <SelectTrigger id="periode_id" className="w-full min-w-0">
                                    <SelectValue placeholder="Toutes périodes ou choisir..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {periodes.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nom} ({p.code})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.periode_id && (
                                <p className="text-xs text-rose-500">{errors.periode_id}</p>
                            )}
                        </div>
                    </div>

                    {/* Date de séance & Numéro de semaine */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="date_seance" className="text-xs font-semibold">
                                Date de la séance <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="date_seance"
                                type="date"
                                value={data.date_seance}
                                onChange={(e) => setData('date_seance', e.target.value)}
                                required
                            />
                            {errors.date_seance && (
                                <p className="text-xs text-rose-500">{errors.date_seance}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="semaine_numero" className="text-xs font-semibold">
                                Semaine pédagogique (1 à 36)
                            </Label>
                            <Input
                                id="semaine_numero"
                                type="number"
                                min="1"
                                max="52"
                                placeholder="Ex: 5"
                                value={data.semaine_numero}
                                onChange={(e) => setData('semaine_numero', e.target.value)}
                            />
                            {errors.semaine_numero && (
                                <p className="text-xs text-rose-500">{errors.semaine_numero}</p>
                            )}
                        </div>
                    </div>

                    {/* Chapitre & Titre de la leçon */}
                    <div className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="chapitre" className="text-xs font-semibold">
                                Chapitre / Module didactique <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="chapitre"
                                placeholder="Ex: Chapitre 2 : La trigonométrie"
                                value={data.chapitre}
                                onChange={(e) => setData('chapitre', e.target.value)}
                                required
                            />
                            {errors.chapitre && (
                                <p className="text-xs text-rose-500">{errors.chapitre}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="titre_lecon" className="text-xs font-semibold">
                                Titre précis de la leçon <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="titre_lecon"
                                placeholder="Ex: Formules d'addition et de duplication"
                                value={data.titre_lecon}
                                onChange={(e) => setData('titre_lecon', e.target.value)}
                                required
                            />
                            {errors.titre_lecon && (
                                <p className="text-xs text-rose-500">{errors.titre_lecon}</p>
                            )}
                        </div>
                    </div>

                    {/* Volume horaire prévu & effectué & Statut */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="heures_prevues" className="text-xs font-semibold">
                                Heures prévues (h) <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="heures_prevues"
                                type="number"
                                step="0.5"
                                min="0.5"
                                value={data.heures_prevues}
                                onChange={(e) => setData('heures_prevues', e.target.value)}
                                required
                            />
                            {errors.heures_prevues && (
                                <p className="text-xs text-rose-500">{errors.heures_prevues}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="heures_effectuees" className="text-xs font-semibold">
                                Heures effectuées (h) <span className="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="heures_effectuees"
                                type="number"
                                step="0.5"
                                min="0"
                                value={data.heures_effectuees}
                                onChange={(e) => setData('heures_effectuees', e.target.value)}
                                required
                            />
                            {errors.heures_effectuees && (
                                <p className="text-xs text-rose-500">{errors.heures_effectuees}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="statut" className="text-xs font-semibold">
                                État d'avancement <span className="text-rose-500">*</span>
                            </Label>
                            <Select
                                value={data.statut}
                                onValueChange={(val: any) => setData('statut', val)}
                            >
                                <SelectTrigger id="statut">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fait">Fait (Achevée)</SelectItem>
                                    <SelectItem value="en_cours">En cours</SelectItem>
                                    <SelectItem value="reporte">Reportée</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.statut && (
                                <p className="text-xs text-rose-500">{errors.statut}</p>
                            )}
                        </div>
                    </div>

                    {data.statut === 'reporte' && (
                        <div className="space-y-1.5">
                            <Label htmlFor="motif_report" className="text-xs font-semibold text-rose-600">
                                Motif du report
                            </Label>
                            <Input
                                id="motif_report"
                                placeholder="Ex: Jour férié imprévu, indisposition..."
                                value={data.motif_report}
                                onChange={(e) => setData('motif_report', e.target.value)}
                            />
                        </div>
                    )}

                    {/* Résumé de l'activité & Devoirs */}
                    <div className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="resume_activite" className="text-xs font-semibold">
                                Résumé de l'activité / Contenu enseigné
                            </Label>
                            <Textarea
                                id="resume_activite"
                                rows={2}
                                placeholder="Détails succincts sur les définitions vues, théorèmes appliqués, exercices résolus..."
                                value={data.resume_activite}
                                onChange={(e) => setData('resume_activite', e.target.value)}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="travail_a_faire" className="text-xs font-semibold">
                                Travail assigné aux élèves (Devoirs / Exercices de maison)
                            </Label>
                            <Textarea
                                id="travail_a_faire"
                                rows={2}
                                placeholder="Ex: Exercices 4 et 5 page 82 du manuel CIAM, réviser la démonstration..."
                                value={data.travail_a_faire}
                                onChange={(e) => setData('travail_a_faire', e.target.value)}
                            />
                        </div>
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
                            className="bg-teal-600 hover:bg-teal-700 text-white gap-1.5"
                            disabled={processing}
                        >
                            {processing ? (
                                <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                                <Save className="w-4 h-4" />
                            )}
                            {isEdit ? 'Enregistrer les modifications' : 'Consigner la séance'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

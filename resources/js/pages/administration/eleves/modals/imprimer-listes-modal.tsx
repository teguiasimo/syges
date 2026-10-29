import React, { useState, useEffect } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
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
import { Printer, FileText, School, Users, CheckCircle2, Calendar } from 'lucide-react';

interface ImprimerListesModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classes: Array<{ id: number; nom: string; niveauId?: number; niveauNom?: string }>;
    niveaux: Array<{ id: number; nom: string; code?: string }>;
    selectedClasseId?: string;
    selectedAnneeId: number;
    selectedEtabId?: string;
}

/**
 * Modale intuitive de génération et d'impression des états et listes administratives officielles.
 *
 * Permet d'éditer au choix :
 * - La liste définitive ou provisoire d'une classe ciblée.
 * - La liste consolidée de tous les élèves d'un niveau complet (ex: toutes les 6èmes, 5èmes, etc.).
 * - La fiche d'appel mensuelle d'émargement de classe.
 * - Le répertoire officiel récapitulatif des classes de l'établissement.
 *
 * @param {ImprimerListesModalProps} props
 * @returns {JSX.Element}
 */
export function ImprimerListesModal({
    open,
    onOpenChange,
    classes = [],
    niveaux = [],
    selectedClasseId,
    selectedAnneeId,
    selectedEtabId,
}: ImprimerListesModalProps) {
    const [scope, setScope] = useState<'classe' | 'niveau' | 'repertoire'>('classe');
    const [targetClasse, setTargetClasse] = useState<string>(selectedClasseId || (classes[0]?.id ? String(classes[0].id) : ''));
    const [targetNiveau, setTargetNiveau] = useState<string>(niveaux[0]?.id ? String(niveaux[0].id) : '');
    const [documentType, setDocumentType] = useState<'definitive' | 'provisoire' | 'fiche_appel'>('definitive');

    // Synchroniser avec la classe active si sélectionnée
    useEffect(() => {
        if (selectedClasseId) {
            setTargetClasse(selectedClasseId);
            setScope('classe');
        }
    }, [selectedClasseId]);

    // Formatage des options de classe pour SearchableSelect
    const classeOptions: SearchableOption[] = classes.map((cls) => ({
        value: String(cls.id),
        label: cls.niveauNom ? `${cls.nom} (${cls.niveauNom})` : cls.nom,
        group: cls.niveauNom,
    }));

    /**
     * Déclenche la génération du PDF dans un nouvel onglet navigateur.
     */
    const handlePrint = () => {
        let url = '';

        if (scope === 'classe') {
            if (!targetClasse) return;
            if (documentType === 'fiche_appel') {
                url = `/administration/documents/fiche-appel/${targetClasse}?annee_id=${selectedAnneeId}`;
            } else {
                url = `/administration/documents/liste-eleves/${targetClasse}?type=${documentType}&annee_id=${selectedAnneeId}`;
            }
        } else if (scope === 'niveau') {
            if (!targetNiveau) return;
            url = `/administration/documents/liste-eleves-niveau/${targetNiveau}?type=${documentType}&annee_id=${selectedAnneeId}${selectedEtabId ? `&etablissement_id=${selectedEtabId}` : ''}`;
        } else if (scope === 'repertoire') {
            const etabParam = selectedEtabId ? `&etablissement_id=${selectedEtabId}` : '';
            url = `/administration/documents/liste-classes?annee_id=${selectedAnneeId}${etabParam}`;
        }

        if (url) {
            window.open(url, '_blank');
            onOpenChange(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100">
                        <Printer className="size-5 text-teal-600 shrink-0" />
                        <span>Imprimer les Listes & Fiches d'Élèves</span>
                    </DialogTitle>
                    <DialogDescription>
                        Édition des états officiels au format PDF certifié (Ministère des Enseignements Secondaires).
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2 text-xs">
                    {/* Périmètre d'impression */}
                    <div className="space-y-2">
                        <Label className="text-xs font-semibold text-foreground">
                            1. Périmètre du document
                        </Label>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <button
                                type="button"
                                onClick={() => setScope('classe')}
                                className={`flex flex-col items-start gap-1 p-3 rounded-lg border text-left cursor-pointer transition-all ${
                                    scope === 'classe'
                                        ? 'border-teal-600 bg-teal-50/60 dark:bg-teal-950/40 text-teal-900 dark:text-teal-200 shadow-2xs font-semibold'
                                        : 'border-border hover:bg-muted/50 text-muted-foreground'
                                }`}
                            >
                                <div className="flex items-center gap-2">
                                    <span className={`size-3.5 rounded-full border flex items-center justify-center ${scope === 'classe' ? 'border-teal-600 bg-teal-600' : 'border-muted-foreground/50'}`}>
                                        {scope === 'classe' && <span className="size-1.5 rounded-full bg-white" />}
                                    </span>
                                    <span className="text-xs font-bold text-foreground">Par Classe</span>
                                </div>
                                <span className="text-[11px] font-normal text-muted-foreground ml-5">
                                    Ex: 6ème A, 3e ALL...
                                </span>
                            </button>

                            <button
                                type="button"
                                onClick={() => setScope('niveau')}
                                className={`flex flex-col items-start gap-1 p-3 rounded-lg border text-left cursor-pointer transition-all ${
                                    scope === 'niveau'
                                        ? 'border-teal-600 bg-teal-50/60 dark:bg-teal-950/40 text-teal-900 dark:text-teal-200 shadow-2xs font-semibold'
                                        : 'border-border hover:bg-muted/50 text-muted-foreground'
                                }`}
                            >
                                <div className="flex items-center gap-2">
                                    <span className={`size-3.5 rounded-full border flex items-center justify-center ${scope === 'niveau' ? 'border-teal-600 bg-teal-600' : 'border-muted-foreground/50'}`}>
                                        {scope === 'niveau' && <span className="size-1.5 rounded-full bg-white" />}
                                    </span>
                                    <span className="text-xs font-bold text-foreground">Par Niveau</span>
                                </div>
                                <span className="text-[11px] font-normal text-muted-foreground ml-5">
                                    Ex: Tout le niveau 3e, 6e...
                                </span>
                            </button>

                            <button
                                type="button"
                                onClick={() => setScope('repertoire')}
                                className={`flex flex-col items-start gap-1 p-3 rounded-lg border text-left cursor-pointer transition-all ${
                                    scope === 'repertoire'
                                        ? 'border-teal-600 bg-teal-50/60 dark:bg-teal-950/40 text-teal-900 dark:text-teal-200 shadow-2xs font-semibold'
                                        : 'border-border hover:bg-muted/50 text-muted-foreground'
                                }`}
                            >
                                <div className="flex items-center gap-2">
                                    <span className={`size-3.5 rounded-full border flex items-center justify-center ${scope === 'repertoire' ? 'border-teal-600 bg-teal-600' : 'border-muted-foreground/50'}`}>
                                        {scope === 'repertoire' && <span className="size-1.5 rounded-full bg-white" />}
                                    </span>
                                    <span className="text-xs font-bold text-foreground">Répertoire</span>
                                </div>
                                <span className="text-[11px] font-normal text-muted-foreground ml-5">
                                    Toutes les classes de l'école
                                </span>
                            </button>
                        </div>
                    </div>

                    {/* Sélection de la cible selon le périmètre */}
                    {scope === 'classe' && (
                        <div className="space-y-1.5 p-3 rounded-lg bg-muted/40 border border-border/70">
                            <Label htmlFor="classe-select" className="text-xs font-semibold">
                                Classe à imprimer <span className="text-rose-500">*</span>
                            </Label>
                            <SearchableSelect
                                value={targetClasse}
                                onValueChange={setTargetClasse}
                                options={classeOptions}
                                placeholder="Choisir une classe..."
                                searchPlaceholder="Rechercher une classe..."
                                className="h-9 w-full bg-background"
                            />
                        </div>
                    )}

                    {scope === 'niveau' && (
                        <div className="space-y-1.5 p-3 rounded-lg bg-muted/40 border border-border/70">
                            <Label htmlFor="niveau-select" className="text-xs font-semibold">
                                Niveau scolaire à imprimer <span className="text-rose-500">*</span>
                            </Label>
                            <Select value={targetNiveau} onValueChange={setTargetNiveau}>
                                <SelectTrigger id="niveau-select" className="h-9 w-full bg-background">
                                    <SelectValue placeholder="Choisir un niveau (ex: 6e, 5e, 3e...)" />
                                </SelectTrigger>
                                <SelectContent>
                                    {niveaux.map((n) => (
                                        <SelectItem key={n.id} value={String(n.id)} className="text-xs">
                                            {n.nom} {n.code ? `(${n.code})` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {scope === 'repertoire' && (
                        <div className="p-3 rounded-lg bg-teal-50/50 dark:bg-teal-950/20 border border-teal-200 dark:border-teal-800 text-teal-800 dark:text-teal-300 text-xs">
                            <p className="font-semibold flex items-center gap-1.5 mb-1">
                                <School className="size-4 text-teal-600" />
                                Tableau Récapitulatif Global
                            </p>
                            <p className="text-[11px] leading-relaxed text-muted-foreground">
                                Génère la liste officielle complète de toutes les classes de l'établissement avec effectifs globaux, quotas filles/garçons et assignation des professeurs principaux.
                            </p>
                        </div>
                    )}

                    {/* Type de document pour les options classe ou niveau */}
                    {scope !== 'repertoire' && (
                        <div className="space-y-2">
                            <Label className="text-xs font-semibold text-foreground">
                                2. Type d'état imprimable
                            </Label>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDocumentType('definitive')}
                                    className={`h-auto p-2.5 justify-start text-left cursor-pointer transition-all ${
                                        documentType === 'definitive'
                                            ? 'border-emerald-600 bg-emerald-50/60 dark:bg-emerald-950/40 text-emerald-950 dark:text-emerald-200 font-semibold shadow-2xs'
                                            : 'hover:bg-muted/50 text-muted-foreground'
                                    }`}
                                >
                                    <div className="flex items-start gap-2">
                                        <FileText className="size-4 text-emerald-600 mt-0.5 shrink-0" />
                                        <div>
                                            <div className="text-xs font-bold text-foreground">Liste Définitive</div>
                                            <div className="text-[11px] font-normal text-muted-foreground">
                                                Inscriptions confirmées & validées
                                            </div>
                                        </div>
                                    </div>
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDocumentType('provisoire')}
                                    className={`h-auto p-2.5 justify-start text-left cursor-pointer transition-all ${
                                        documentType === 'provisoire'
                                            ? 'border-amber-600 bg-amber-50/60 dark:bg-amber-950/40 text-amber-950 dark:text-amber-200 font-semibold shadow-2xs'
                                            : 'hover:bg-muted/50 text-muted-foreground'
                                    }`}
                                >
                                    <div className="flex items-start gap-2">
                                        <FileText className="size-4 text-amber-600 mt-0.5 shrink-0" />
                                        <div>
                                            <div className="text-xs font-bold text-foreground">Liste Provisoire</div>
                                            <div className="text-[11px] font-normal text-muted-foreground">
                                                Tous statuts (y compris en attente)
                                            </div>
                                        </div>
                                    </div>
                                </Button>

                                {scope === 'classe' && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setDocumentType('fiche_appel')}
                                        className={`sm:col-span-2 h-auto p-2.5 justify-start text-left cursor-pointer transition-all ${
                                            documentType === 'fiche_appel'
                                                ? 'border-sky-600 bg-sky-50/60 dark:bg-sky-950/40 text-sky-950 dark:text-sky-200 font-semibold shadow-2xs'
                                                : 'hover:bg-muted/50 text-muted-foreground'
                                        }`}
                                    >
                                        <div className="flex items-start gap-2">
                                            <Calendar className="size-4 text-sky-600 mt-0.5 shrink-0" />
                                            <div>
                                                <div className="text-xs font-bold text-foreground">Fiche d'Appel Mensuelle (Émargement)</div>
                                                <div className="text-[11px] font-normal text-muted-foreground">
                                                    Format paysage avec colonnes de pointage pour les enseignants
                                                </div>
                                            </div>
                                        </div>
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Annuler
                    </Button>
                    <Button
                        type="button"
                        onClick={handlePrint}
                        disabled={(scope === 'classe' && !targetClasse) || (scope === 'niveau' && !targetNiveau)}
                        className="bg-teal-600 hover:bg-teal-700 text-white font-semibold gap-1.5"
                    >
                        <Printer className="size-4" />
                        <span>Télécharger le PDF</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

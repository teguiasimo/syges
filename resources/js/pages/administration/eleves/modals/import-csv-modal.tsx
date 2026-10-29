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
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { toast } from 'sonner';
import { FileSpreadsheet, Upload, Download, AlertCircle } from 'lucide-react';
import { SearchableSelect, type SearchableOption } from '@/components/ui/searchable-select';
import type { EtablissementRef, AnneeScolaireRef, ClasseRef } from '@/types/eleve';

/**
 * Propriétés du composant ImportCsvModal.
 */
interface ImportCsvModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissements: EtablissementRef[];
    anneesScolaires: AnneeScolaireRef[];
    selectedAnneeId: number;
}

/**
 * Modal d'importation de fichiers CSV / Excel conforme au format officiel SAGEES Schooling (12-14 colonnes).
 *
 * @param {ImportCsvModalProps} props
 * @returns {JSX.Element}
 */
export function ImportCsvModal({
    open,
    onOpenChange,
    etablissements,
    anneesScolaires,
    selectedAnneeId,
}: ImportCsvModalProps) {
    const [loading, setLoading] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [targetClasseId, setTargetClasseId] = useState('');
    const [anneeId, setAnneeId] = useState(String(selectedAnneeId || ''));
    const [ignorerDoublons, setIgnorerDoublons] = useState(true);

    // Liste des classes
    const availableClasses: ClasseRef[] = React.useMemo(() => {
        const list: ClasseRef[] = [];
        etablissements.forEach((etab) => {
            etab.sections?.forEach((sec) => {
                sec.cycles?.forEach((cyc) => {
                    cyc.niveaux?.forEach((niv) => {
                        niv.classes?.forEach((cls) => {
                            list.push({
                                ...cls,
                                niveau: {
                                    id: niv.id,
                                    nom: niv.nom,
                                    code: niv.code,
                                    cycle: {
                                        id: cyc.id,
                                        nom: cyc.nom,
                                        section: {
                                            id: sec.id,
                                            nom: sec.nom,
                                            etablissement: {
                                                id: etab.id,
                                                nom: etab.nom,
                                            },
                                        },
                                    },
                                },
                            });
                        });
                    });
                });
            });
        });
        return list;
    }, [etablissements]);

    /**
     * Options formatées pour le sélecteur avec recherche de la classe de destination.
     * Tronque élégamment le nom des campus volumineux pour préserver l'ergonomie visuelle.
     */
    const classeOptions: SearchableOption[] = React.useMemo(() => {
        return availableClasses.map((cls) => {
            const etabNom = cls.niveau?.cycle?.section?.etablissement?.nom || '';
            const etabAbrege = etabNom.length > 25 ? `${etabNom.substring(0, 25)}…` : etabNom;
            return {
                value: String(cls.id),
                label: `${cls.nom} (${cls.niveau?.nom || 'Niveau'}${etabAbrege ? ` — ${etabAbrege}` : ''})`,
                group: cls.niveau?.nom || 'Classes',
            };
        });
    }, [availableClasses]);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files?.[0]) {
            setFile(e.target.files[0]);
        }
    };

    /**
     * Génère et télécharge un fichier modèle CSV standard compatible avec Excel (séparateur point-virgule et UTF-8 BOM).
     */
    const downloadTemplate = () => {
        const header = "Matricule;MatriculeNational;Nom;Prenom;Sexe;DateNaissance;LieuNaissance;Redoublant;TelephoneParent;Adresse;NomTuteur;Nationalite\n";
        const sampleRow1 = "26-0001;MIN-2026-001;TCHOUPOU;FRANCOIS;M;12/11/2009;Douala;N;+237 699001122;Bastos;TCHOUPOU Jean;CAMEROUNAISE\n";
        const sampleRow2 = ";;MOUNCHIKPOU;HAMED ANIF;M;06/09/2007;Foumban;N;+237 699451965;Foumban;MOUNCHIKPOU;CAMEROUNAISE\n";

        // Inclusion explicite du BOM UTF-8 (\uFEFF) pour garantir l'ouverture en colonnes distinctes dans Excel sous Windows
        const blob = new Blob(["\uFEFF" + header + sampleRow1 + sampleRow2], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'modele_import_eleves_syges.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!file) {
            toast.error('Veuillez sélectionner un fichier CSV.', { position: 'top-right' });
            return;
        }

        if (!targetClasseId) {
            toast.error('Veuillez sélectionner la classe de destination.', { position: 'top-right' });
            return;
        }

        setLoading(true);

        const formData = new FormData();
        formData.append('fichier', file);
        formData.append('classe_id', targetClasseId);
        formData.append('annee_scolaire_id', anneeId);
        formData.append('ignorer_doublons', ignorerDoublons ? '1' : '0');

        router.post('/administration/eleves/import-csv', formData, {
            onSuccess: () => {
                setLoading(false);
                toast.success('Importation réalisée avec succès !', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-emerald-700',
                });
                onOpenChange(false);
                setFile(null);
            },
            onError: (errs) => {
                setLoading(false);
                toast.error(errs.fichier || 'Erreur lors de l\'importation.', { position: 'top-right' });
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-lg sm:text-xl">
                        <FileSpreadsheet className="size-5 text-emerald-600 shrink-0" />
                        <span>Importer une Liste d'Élèves (CSV / Excel)</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs sm:text-sm">
                        Importez rapidement les élèves d'une classe au format standard SAGEES (compatible avec le fichier modèle <code>ELEVE.csv</code>).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    {/* Notice format responsive */}
                    <div className="p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/60 dark:bg-emerald-950/30 text-xs text-emerald-800 dark:text-emerald-300 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div className="flex items-start gap-2">
                            <AlertCircle className="size-4 shrink-0 mt-0.5" />
                            <div>
                                <p className="font-semibold">Format attendu (12 colonnes standard) :</p>
                                <p className="mt-0.5 text-[11px] leading-relaxed">
                                    Matricule, Matr. National, Nom*, Prénom, Sexe*, Date Naiss (JJ/MM/AAAA), Lieu, Redoublant (O/N), Téléphone, Adresse, Tuteur, Nationalité.
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={downloadTemplate}
                            className="w-full sm:w-auto shrink-0 gap-1.5 text-xs h-8 border-emerald-300 dark:border-emerald-700 hover:bg-emerald-100 dark:hover:bg-emerald-900/50"
                        >
                            <Download className="size-3.5" />
                            Modèle CSV
                        </Button>
                    </div>

                    {/* Sélection de l'année et classe de destination */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="import_annee">Année Scolaire *</Label>
                            <select
                                id="import_annee"
                                value={anneeId}
                                onChange={(e) => setAnneeId(e.target.value)}
                                required
                                className="w-full mt-1.5 h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                {anneesScolaires.map((a) => {
                                    // Nettoyage du préfixe redondant "Année Scolaire" pour n'afficher que le millésime/code (ex: 2026/2027)
                                    const codeAnnee = a.code || a.nom.replace(/^année\s*scolaire\s*/i, '').trim();
                                    return (
                                        <option key={a.id} value={a.id}>
                                            {codeAnnee}
                                        </option>
                                    );
                                })}
                            </select>
                        </div>

                        <div>
                            <Label htmlFor="import_classe">Classe de destination *</Label>
                            <div className="mt-1.5">
                                <SearchableSelect
                                    options={classeOptions}
                                    value={targetClasseId}
                                    onValueChange={setTargetClasseId}
                                    placeholder="Rechercher une classe..."
                                    searchPlaceholder="Taper le nom de la classe..."
                                    emptyMessage="Aucune classe trouvée."
                                />
                            </div>
                        </div>
                    </div>

                    {/* Sélection du fichier */}
                    <div>
                        <Label htmlFor="csv_file">Fichier CSV / TSV (.csv, .txt) *</Label>
                        <div className="mt-1.5 flex items-center gap-3">
                            <label className="flex-1 cursor-pointer flex items-center justify-center gap-2 border-2 border-dashed border-border rounded-xl p-4 bg-muted/20 hover:bg-muted/40 transition-colors">
                                <Upload className="size-5 text-muted-foreground" />
                                <span className="text-xs text-foreground font-medium">
                                    {file ? file.name : 'Sélectionnez ou glissez un fichier CSV ici...'}
                                </span>
                                <input
                                    id="csv_file"
                                    type="file"
                                    accept=".csv,.txt"
                                    onChange={handleFileChange}
                                    className="hidden"
                                    required
                                />
                            </label>
                        </div>
                    </div>

                    {/* Option doublons (SAGEES Schooling) */}
                    <div className="flex items-center space-x-2 pt-1">
                        <Checkbox
                            id="ignorer_doublons"
                            checked={ignorerDoublons}
                            onCheckedChange={(checked) => setIgnorerDoublons(Boolean(checked))}
                        />
                        <label
                            htmlFor="ignorer_doublons"
                            className="text-xs font-medium leading-none cursor-pointer select-none text-muted-foreground"
                        >
                            Ignorer les élèves doublons (si le matricule existe déjà en base)
                        </label>
                    </div>

                    {/* Pied de modal responsive avec boutons étirés sur smartphone */}
                    <DialogFooter className="pt-3 border-t border-border flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} className="w-full sm:w-auto">
                            Annuler
                        </Button>
                        <Button type="submit" disabled={loading || !file || !targetClasseId} className="gap-2 w-full sm:w-auto">
                            {loading ? 'Importation en cours...' : 'Lancer l\'Importation'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

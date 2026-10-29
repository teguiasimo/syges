import { router } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
} from '@tanstack/react-table';
import { Layers, Save } from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { truncateText } from '@/lib/utils';
import type {
    ClasseWithAttributions,
    EnseignantWithWorkload,
    MatiereNiveauRef,
} from '@/types/attribution';

/**
 * Propriétés attendues par la matrice d'attribution par classe.
 */
interface ClasseMatrixModalProps {
    classe: ClasseWithAttributions | null;
    isOpen: boolean;
    onClose: () => void;
    anneeScolaireId: number;
    enseignants: EnseignantWithWorkload[];
    matieres: { id: number; code: string; nom: string }[];
    matiereNiveaux: MatiereNiveauRef[];
}

/**
 * Ligne d'affectation dans la matrice de classe.
 */
interface MatrixRowState {
    matiere_id: number;
    code: string;
    nom: string;
    coefficient: number;
    groupe_nom: string;
    personnel_id: number | null;
    volume_horaire_reel: number;
}

/**
 * Modale de matrice d'attribution par classe (Outil principal du Censeur).
 * Permet de visualiser et de compléter en une seule vue l'ensemble des matières au programme
 * pour une classe donnée.
 */
export function ClasseMatrixModal({
    classe,
    isOpen,
    onClose,
    anneeScolaireId,
    enseignants,
    matieres,
    matiereNiveaux,
}: ClasseMatrixModalProps) {
    const [rows, setRows] = useState<MatrixRowState[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Construction dynamique des lignes de matières de la classe
    useEffect(() => {
        if (!classe) {
            setRows([]);
            return;
        }

        const niveauId = classe.niveau_id;
        const serie = classe.serie;

        // Matières requises pour le niveau et la série
        const relevantMatiereNiveaux = matiereNiveaux.filter((mn) => {
            return (
                mn.niveau_id === niveauId &&
                (mn.serie === 'Toutes' || mn.serie === null || mn.serie === '' || mn.serie === serie)
            );
        });

        const newRows: MatrixRowState[] = relevantMatiereNiveaux.map((mn) => {
            // Recherche si une attribution existe déjà dans cette classe
            const existingAttr = classe.attributions.find((a) => a.matiere_id === mn.matiere_id);

            return {
                matiere_id: mn.matiere_id,
                code: mn.matiere?.code || 'DISC',
                nom: mn.matiere?.nom || 'Matière inconnue',
                coefficient: mn.coefficient,
                groupe_nom: mn.groupe_matiere?.nom || 'Général',
                personnel_id: existingAttr ? existingAttr.personnel_id : null,
                volume_horaire_reel: existingAttr ? Number(existingAttr.volume_horaire_reel) : (mn.coefficient >= 3 ? 4.0 : 2.0),
            };
        });

        // Tri par groupe et coefficient
        newRows.sort((a, b) => b.coefficient - a.coefficient);
        setRows(newRows);
    }, [classe, matiereNiveaux, isOpen]);

    /**
     * Modification d'un enseignant ou volume horaire dans une ligne.
     */
    const handleRowChange = useCallback((
        matiereId: number,
        field: 'personnel_id' | 'volume_horaire_reel',
        value: number | null
    ) => {
        setRows((prev) =>
            prev.map((r) => {
                if (r.matiere_id === matiereId) {
                    return { ...r, [field]: value };
                }
                return r;
            })
        );
    }, []);

    /**
     * Colonnes TanStack Table pour la matrice des matières de la classe.
     */
    const matrixColumns = useMemo<ColumnDef<MatrixRowState>[]>(
        () => [
            {
                id: 'matiere',
                header: 'Matière au programme',
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <div className="py-0.5">
                            <div className="font-semibold text-foreground text-xs truncate max-w-[220px]" title={r.nom}>
                                {truncateText(r.nom, 40)}
                            </div>
                            <div className="flex items-center gap-1.5 mt-0.5 text-[11px] text-muted-foreground">
                                <span className="font-mono bg-muted px-1 rounded border">
                                    {r.code}
                                </span>
                                <span>•</span>
                                <span className="truncate max-w-37.5">{r.groupe_nom}</span>
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'coefficient',
                header: () => <div className="text-center">Coeff</div>,
                cell: ({ row }) => (
                    <div className="text-center font-bold text-xs text-foreground">
                        {Number(row.original.coefficient).toFixed(1)}
                    </div>
                ),
            },
            {
                id: 'enseignant',
                header: 'Enseignant Affecté',
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <div className="min-w-56">
                            <Select
                                value={r.personnel_id ? String(r.personnel_id) : 'none'}
                                onValueChange={(val) =>
                                    handleRowChange(
                                        r.matiere_id,
                                        'personnel_id',
                                        val === 'none' ? null : Number(val)
                                    )
                                }
                            >
                                <SelectTrigger className="h-8 text-xs bg-background">
                                    <SelectValue placeholder="Choisir un enseignant" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        <em className="text-muted-foreground">Non attribué (vacant)</em>
                                    </SelectItem>
                                    {enseignants.map((ens) => (
                                        <SelectItem key={ens.id} value={String(ens.id)}>
                                            {ens.nom} {ens.prenom} ({ens.matricule}) [{ens.heures_attribuees}h / {ens.volume_horaire_hebdo}h]
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    );
                },
            },
            {
                id: 'heures',
                header: () => <div className="text-center">Heures/sem</div>,
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <div className="text-center">
                            <Input
                                type="number"
                                step="0.5"
                                min="0"
                                max="30"
                                value={r.volume_horaire_reel}
                                onChange={(e) =>
                                    handleRowChange(
                                        r.matiere_id,
                                        'volume_horaire_reel',
                                        parseFloat(e.target.value) || 0
                                    )
                                }
                                className="h-8 text-xs text-center font-mono w-20 mx-auto"
                            />
                        </div>
                    );
                },
            },
            {
                id: 'statut',
                header: () => <div className="text-right">Statut</div>,
                cell: ({ row }) => (
                    <div className="text-right">
                        {row.original.personnel_id ? (
                            <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800 text-[10px] font-semibold">
                                Pourvu
                            </Badge>
                        ) : (
                            <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800 text-[10px] font-semibold">
                                Non pourvu
                            </Badge>
                        )}
                    </div>
                ),
            },
        ],
        [enseignants, handleRowChange]
    );

    const matrixTable = useReactTable({
        data: rows,
        columns: matrixColumns,
        getCoreRowModel: getCoreRowModel(),
    });

    /**
     * Soumission de l'ensemble de la grille en une seule transaction.
     */
    const handleSaveBatch = () => {
        if (!classe) return;
        setIsSubmitting(true);

        const assignments = rows.map((r) => ({
            matiere_id: r.matiere_id,
            personnel_id: r.personnel_id,
            volume_horaire_reel: r.volume_horaire_reel,
        }));

        router.post(
            `/administration/attributions/classes/${classe.id}/batch`,
            {
                annee_scolaire_id: anneeScolaireId,
                assignments,
            },
            {
                onSuccess: () => {
                    toast.success('Répartition de la classe enregistrée !', {
                        description: `Les attributions pour la classe ${classe.nom} ont été mises à jour.`,
                    });
                    setIsSubmitting(false);
                    onClose();
                },
                onError: () => {
                    toast.error('Erreur lors de l\'enregistrement de la matrice');
                    setIsSubmitting(false);
                },
            }
        );
    };

    if (!classe) return null;

    // Statistiques de pourvoi de la classe
    const pourvuesCount = rows.filter((r) => r.personnel_id !== null).length;
    const totalCount = rows.length;
    const pourvoiPercent = totalCount > 0 ? Math.round((pourvuesCount / totalCount) * 100) : 100;

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-4xl">
                <DialogHeader className="border-b pb-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div className="flex items-start sm:items-center gap-3 min-w-0">
                            <div className="p-2.5 rounded-xl bg-teal-50 dark:bg-teal-950/50 text-teal-600 border border-teal-200 dark:border-teal-900/50 shrink-0">
                                <Layers className="size-6" />
                            </div>
                            <div className="min-w-0">
                                <DialogTitle className="text-base sm:text-xl font-bold text-foreground flex flex-wrap items-center gap-2">
                                    <span>Grille des Attributions : {classe.nom}</span>
                                    {classe.serie && (
                                        <Badge variant="outline" className="text-xs">
                                            Série {classe.serie}
                                        </Badge>
                                    )}
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                    Niveau : {classe.niveau?.nom} | Campus : {classe.niveau?.cycle?.section?.etablissement?.nom || 'Principal'}
                                </DialogDescription>
                            </div>
                        </div>

                        {/* Taux de couverture de la classe */}
                        <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-muted/60 border text-xs font-semibold w-fit shrink-0">
                            <span className="text-muted-foreground">Couverture :</span>
                            <span className={pourvoiPercent === 100 ? 'text-emerald-600' : 'text-amber-600'}>
                                {pourvuesCount} / {totalCount} ({pourvoiPercent}%)
                            </span>
                        </div>
                    </div>
                </DialogHeader>

                {/* TABLEAU TANSTACK TABLE DES MATIÈRES AU PROGRAMME */}
                <div className="space-y-4 pt-2 min-w-0">
                    <div className="rounded-lg border overflow-hidden">
                        <Table className="min-w-[680px] text-xs">
                            <TableHeader className="bg-muted/50 text-muted-foreground font-semibold uppercase text-[10px] tracking-wider">
                                {matrixTable.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers.map((header) => (
                                            <TableHead key={header.id} className="py-2.5 px-3">
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(header.column.columnDef.header, header.getContext())}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {matrixTable.getRowModel().rows.length > 0 ? (
                                    matrixTable.getRowModel().rows.map((row) => (
                                        <TableRow key={row.id} className="hover:bg-muted/30 transition-colors">
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell key={cell.id} className="py-2.5 px-3">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={matrixColumns.length} className="h-20 text-center text-muted-foreground">
                                            Aucune matière configurée pour ce niveau.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <DialogFooter className="gap-2 border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        className="cursor-pointer w-full sm:w-auto"
                    >
                        Annuler
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSaveBatch}
                        disabled={isSubmitting}
                        className="bg-teal-600 hover:bg-teal-700 text-white cursor-pointer font-semibold w-full sm:w-auto"
                    >
                        <Save className="size-4 mr-1.5" />
                        {isSubmitting ? 'Enregistrement en cours...' : 'Enregistrer la répartition'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

import React, { useEffect, useState, useMemo } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CompteFinancierEleve } from '@/types/finance';
import {
    CreditCard,
    Printer,
    AlertTriangle,
    CheckCircle,
    Calendar,
    Wallet,
    Layers,
    DollarSign,
} from 'lucide-react';
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
    ColumnDef,
} from '@tanstack/react-table';

interface DossierFinancierModalProps {
    isOpen: boolean;
    onClose: () => void;
    compte: CompteFinancierEleve | null;
    onNouveauVersement?: (compte: CompteFinancierEleve) => void;
}

interface LigneEcheancier {
    type: 'obligatoire' | 'optionnel';
    id?: number;
    libelle: string;
    categorie: string;
    montant: number;
    date_limite: string | null;
    est_echu: boolean;
    montant_paye: number;
    montant_restant: number;
    statut: 'solde' | 'en_retard' | 'a_choir';
}

interface DossierData {
    lignes: LigneEcheancier[];
    total_exigible: number;
    total_paye: number;
    solde_restant: number;
    montant_echu_en_retard: number;
    statut_financier: string;
}

/**
 * Modal affichant le Dossier Financier détaillé & l'Échéancier complet d'un élève.
 *
 * Implémenté avec TanStack Table conformément aux règles UI SYGES.
 *
 * @param {DossierFinancierModalProps} props
 * @returns {JSX.Element}
 */
export default function DossierFinancierModal({
    isOpen,
    onClose,
    compte,
    onNouveauVersement,
}: DossierFinancierModalProps) {
    const [data, setData] = useState<DossierData | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    // Chargement de l'échéancier dynamique
    useEffect(() => {
        if (!isOpen || !compte) {
            setData(null);
            return;
        }

        setIsLoading(true);
        fetch(`/administration/finances/comptes/${compte.id}/dossier`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(res => res.json())
            .then((resData: DossierData) => {
                setData(resData);
                setIsLoading(false);
            })
            .catch(() => {
                setIsLoading(false);
            });
    }, [isOpen, compte]);

    // Définition des colonnes TanStack Table pour l'échéancier
    const columns = useMemo<ColumnDef<LigneEcheancier>[]>(() => [
        {
            id: 'rubrique',
            header: 'Rubrique / Tranche',
            cell: ({ row }) => (
                <div>
                    <div className="font-semibold text-xs text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                        {row.original.libelle}
                    </div>
                    <div className="text-[10px] text-slate-500">
                        {row.original.categorie}
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Nature',
            cell: ({ row }) => (
                <Badge
                    variant="outline"
                    className={`text-[10px] ${
                        row.original.type === 'obligatoire'
                            ? 'bg-blue-50 text-blue-700 border-blue-200'
                            : 'bg-purple-50 text-purple-700 border-purple-200'
                    }`}
                >
                    {row.original.type === 'obligatoire' ? 'Obligatoire' : 'Optionnel'}
                </Badge>
            ),
        },
        {
            accessorKey: 'date_limite',
            header: 'Échéance',
            cell: ({ row }) => (
                <div className="text-xs flex items-center gap-1 text-slate-600 dark:text-slate-300">
                    <Calendar className="w-3 h-3 text-slate-400" />
                    {row.original.date_limite || 'Rentrée'}
                </div>
            ),
        },
        {
            accessorKey: 'montant',
            header: () => <div className="text-right">Montant Exigible</div>,
            cell: ({ row }) => (
                <div className="text-right font-medium text-xs">
                    {Number(row.original.montant).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'montant_paye',
            header: () => <div className="text-right">Réglé</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs text-emerald-600 font-semibold">
                    {Number(row.original.montant_paye).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'montant_restant',
            header: () => <div className="text-right">Reste Dû</div>,
            cell: ({ row }) => (
                <div className={`text-right text-xs font-bold ${row.original.montant_restant > 0 ? 'text-red-600' : 'text-slate-400'}`}>
                    {Number(row.original.montant_restant).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'statut',
            header: () => <div className="text-center">Statut</div>,
            cell: ({ row }) => {
                const s = row.original.statut;
                if (s === 'solde') {
                    return (
                        <div className="flex justify-center">
                            <Badge className="bg-emerald-600 text-white text-[10px]">Soldé</Badge>
                        </div>
                    );
                }
                if (s === 'en_retard') {
                    return (
                        <div className="flex justify-center">
                            <Badge variant="destructive" className="text-[10px]">Échu en retard</Badge>
                        </div>
                    );
                }
                return (
                    <div className="flex justify-center">
                        <Badge variant="outline" className="text-[10px] text-amber-700 bg-amber-50 border-amber-200">À échoir</Badge>
                    </div>
                );
            },
        },
    ], []);

    const tableData = data?.lignes || [];

    const table = useReactTable({
        data: tableData,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    if (!compte) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center justify-between text-slate-800 dark:text-slate-100 pr-6">
                        <span className="flex items-center gap-2">
                            <CreditCard className="w-5 h-5 text-emerald-600" />
                            Dossier Financier & Échéancier de Paiement
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs flex items-center gap-1.5 text-blue-600 border-blue-200 hover:bg-blue-50"
                            onClick={() => window.open(`/administration/finances/comptes/${compte.id}/fiche-pdf`, '_blank')}
                        >
                            <Printer className="w-3.5 h-3.5" /> Relevé Parent (PDF)
                        </Button>
                    </DialogTitle>
                </DialogHeader>

                {/* Identité Élève */}
                <div className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3 rounded-lg flex items-center justify-between text-xs">
                    <div>
                        <div className="font-bold text-sm text-slate-900 dark:text-slate-100">
                            {compte.eleve?.nom} {compte.eleve?.prenom}
                        </div>
                        <div className="text-slate-500 mt-0.5">
                            Matricule : <span className="font-mono font-semibold text-slate-800 dark:text-slate-200">{compte.eleve?.matricule}</span>
                            {' • '}Classe : <span className="font-semibold text-slate-800 dark:text-slate-200">{compte.inscription?.classe?.nom ?? 'N/A'}</span>
                            {' • '}Tuteur : <span className="text-slate-700 dark:text-slate-300">{compte.eleve?.nom_tuteur || 'Non renseigné'} ({compte.eleve?.telephone_parent || 'N/A'})</span>
                        </div>
                    </div>
                </div>

                {/* 4 KPI Cards */}
                <div className="grid grid-cols-4 gap-2.5 my-2">
                    <div className="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                        <div className="flex items-center justify-between text-[11px] text-slate-500">
                            <span>Net Exigible</span>
                            <Wallet className="w-3.5 h-3.5 text-blue-500" />
                        </div>
                        <div className="text-sm font-bold text-slate-800 dark:text-slate-100 mt-1">
                            {Number(data?.total_exigible ?? compte.montant_net_exigible).toLocaleString('fr-FR')} FCFA
                        </div>
                    </div>

                    <div className="p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/20">
                        <div className="flex items-center justify-between text-[11px] text-emerald-700 dark:text-emerald-300">
                            <span>Total Réglé</span>
                            <CheckCircle className="w-3.5 h-3.5 text-emerald-600" />
                        </div>
                        <div className="text-sm font-bold text-emerald-700 dark:text-emerald-300 mt-1">
                            {Number(data?.total_paye ?? compte.total_paye).toLocaleString('fr-FR')} FCFA
                        </div>
                    </div>

                    <div className="p-2.5 rounded-lg border border-red-200 dark:border-red-900/40 bg-red-50/50 dark:bg-red-950/20">
                        <div className="flex items-center justify-between text-[11px] text-red-700 dark:text-red-300">
                            <span>Solde Restant Dû</span>
                            <DollarSign className="w-3.5 h-3.5 text-red-600" />
                        </div>
                        <div className="text-sm font-bold text-red-700 dark:text-red-300 mt-1">
                            {Number(data?.solde_restant ?? compte.solde_restant).toLocaleString('fr-FR')} FCFA
                        </div>
                    </div>

                    <div className={`p-2.5 rounded-lg border ${
                        (data?.montant_echu_en_retard || 0) > 0
                            ? 'border-red-300 bg-red-100/60 dark:bg-red-900/30'
                            : 'border-slate-200 bg-slate-50 dark:bg-slate-900'
                    }`}>
                        <div className="flex items-center justify-between text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                            <span>Retard Échu</span>
                            <AlertTriangle className={`w-3.5 h-3.5 ${(data?.montant_echu_en_retard || 0) > 0 ? 'text-red-600' : 'text-slate-400'}`} />
                        </div>
                        <div className={`text-sm font-bold mt-1 ${(data?.montant_echu_en_retard || 0) > 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-600'}`}>
                            {(data?.montant_echu_en_retard || 0) > 0
                                ? `${Number(data?.montant_echu_en_retard).toLocaleString('fr-FR')} FCFA`
                                : '0 FCFA (À jour)'}
                        </div>
                    </div>
                </div>

                {/* Table de l'échéancier TanStack Table */}
                <div className="mt-2">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                            <Layers className="w-4 h-4 text-indigo-600" />
                            Détail des Frais & Tranches Réglementaires
                        </span>
                        {isLoading && <span className="text-[11px] text-slate-400 animate-pulse">Calcul en direct...</span>}
                    </div>

                    <div className="border border-slate-200 dark:border-slate-800 rounded-md overflow-hidden">
                        <table className="w-full text-xs">
                            <thead className="bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                                {table.getHeaderGroups().map(headerGroup => (
                                    <tr key={headerGroup.id}>
                                        {headerGroup.headers.map(header => (
                                            <th key={header.id} className="p-2.5 font-semibold text-left">
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(header.column.columnDef.header, header.getContext())}
                                            </th>
                                        ))}
                                    </tr>
                                ))}
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {table.getRowModel().rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={columns.length} className="p-4 text-center text-slate-500">
                                            {isLoading ? 'Chargement de l\'échéancier...' : 'Aucune ligne de frais configurée.'}
                                        </td>
                                    </tr>
                                ) : (
                                    table.getRowModel().rows.map(row => (
                                        <tr key={row.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                            {row.getVisibleCells().map(cell => (
                                                <td key={cell.id} className="p-2.5">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <DialogFooter className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <Button variant="outline" size="sm" onClick={onClose}>
                        Fermer
                    </Button>
                    <div className="flex items-center gap-2">
                        {onNouveauVersement && (
                            <Button
                                size="sm"
                                className="bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1.5"
                                onClick={() => {
                                    onClose();
                                    onNouveauVersement(compte);
                                }}
                            >
                                <Wallet className="w-3.5 h-3.5" /> Encaisser un versement
                            </Button>
                        )}
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

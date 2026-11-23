import React, { useEffect, useMemo, useState } from 'react';
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
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatDateForInput } from '@/lib/utils';
import { EtablissementRef } from '@/types/eleve';
import {
    AlertCircle,
    Building2,
    CalendarRange,
    CheckCircle2,
    FileSpreadsheet,
    FileText,
    Filter,
    Lock,
    Printer,
    RotateCcw,
    Scale,
} from 'lucide-react';
import { toast } from 'sonner';

/**
 * Types d'états financiers officiels exportables en PDF depuis le générateur unifié.
 */
export type TypeEtatFinancier =
    | 'journal_detaille'
    | 'journal_synthetique'
    | 'liste_insolvables'
    | 'liste_en_regle'
    | 'bilan_entrees_sorties';

/**
 * Raccourcis calendaires rapides pour renseigner automatiquement `date_debut` et `date_fin`.
 */
export type PeriodePreset =
    | 'aujourdhui'
    | 'semaine'
    | 'mois'
    | 'annee'
    | 'personnalise';

/**
 * Propriétés du composant `GenerateurEtatsFinanciersModal`.
 */
export interface GenerateurEtatsFinanciersModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    etablissements: EtablissementRef[];
    currentEtablissementId?: number | null;
    currentEtablissementNom?: string | null;
    isSuperAdmin?: boolean;
    currentAnnee?: {
        id: number;
        code?: string;
        nom?: string;
        date_debut?: string | null;
        date_fin?: string | null;
    } | null;
    initialType?: TypeEtatFinancier;
    initialPreset?: PeriodePreset;
    initialSectionId?: string;
    initialCycleId?: string;
    initialNiveauId?: string;
    initialClasseId?: string;
    allowBilanEntreesSorties?: boolean;
}

/**
 * Calcule l'intervalle `[dateDebut, dateFin]` au format ISO `YYYY-MM-DD` pour un raccourci de période donné.
 *
 * @param {PeriodePreset} preset Raccourci sélectionné (journalier, hebdomadaire, mensuel, annuel)
 * @param {{ date_debut?: string | null; date_fin?: string | null } | null | undefined} annee Année scolaire active
 * @returns {{ debut: string; fin: string }} Bornes ISO compatibles `<input type="date">`
 */
function calculerBornesPeriode(
    preset: PeriodePreset,
    annee?: { date_debut?: string | null; date_fin?: string | null } | null
): { debut: string; fin: string } {
    const maintenant = new Date();
    const aujourdhuiIso = formatDateForInput(maintenant);

    if (preset === 'aujourdhui') {
        return { debut: aujourdhuiIso, fin: aujourdhuiIso };
    }

    if (preset === 'semaine') {
        const jourSemaine = maintenant.getDay(); // 0 = Dimanche, 1 = Lundi...
        const decalageLundi = jourSemaine === 0 ? -6 : 1 - jourSemaine;
        const lundi = new Date(maintenant);
        lundi.setDate(maintenant.getDate() + decalageLundi);
        return {
            debut: formatDateForInput(lundi),
            fin: aujourdhuiIso,
        };
    }

    if (preset === 'annee') {
        const debutAnnee = annee?.date_debut
            ? formatDateForInput(annee.date_debut)
            : `${maintenant.getMonth() >= 7 ? maintenant.getFullYear() : maintenant.getFullYear() - 1}-09-01`;
        return {
            debut: debutAnnee || aujourdhuiIso,
            fin: aujourdhuiIso,
        };
    }

    // Par défaut : mois en cours (du 1er du mois à aujourd'hui)
    const premierDuMois = new Date(maintenant.getFullYear(), maintenant.getMonth(), 1);
    return {
        debut: formatDateForInput(premierDuMois),
        fin: aujourdhuiIso,
    };
}

/**
 * Modale dédiée « Générateur d'États Financiers Multicritères » (Module 11 - SYGES).
 *
 * Permet de filtrer systématiquement tous les états financiers :
 * 1. Par intervalle de dates (`date_debut` → `date_fin`) avec raccourcis Journalier, Hebdomadaire, Mensuel et Annuel,
 * 2. Par Établissement (lorsque l'utilisateur dispose d'un accès multi-campus) puis en cascade par
 *    Section → Cycle → Niveau → Classe (ex: obtenir l'état mensuel du niveau CM2 ou l'état journalier de 6ème A).
 *
 * @param {GenerateurEtatsFinanciersModalProps} props Propriétés du générateur
 * @returns {JSX.Element} Fenêtre modale de paramétrage et d'édition PDF
 */
export default function GenerateurEtatsFinanciersModal({
    open,
    onOpenChange,
    etablissements,
    currentEtablissementId,
    currentEtablissementNom,
    isSuperAdmin = false,
    currentAnnee,
    initialType = 'journal_synthetique',
    initialPreset = 'mois',
    initialSectionId = 'all',
    initialCycleId = 'all',
    initialNiveauId = 'all',
    initialClasseId = 'all',
    allowBilanEntreesSorties = false,
}: GenerateurEtatsFinanciersModalProps) {
    const [typeEtat, setTypeEtat] = useState<TypeEtatFinancier>(initialType);
    const [preset, setPreset] = useState<PeriodePreset>(initialPreset);

    const initialDates = useMemo(
        () => calculerBornesPeriode(initialPreset, currentAnnee),
        [initialPreset, currentAnnee]
    );
    const [dateDebut, setDateDebut] = useState<string>(initialDates.debut);
    const [dateFin, setDateFin] = useState<string>(initialDates.fin);

    // Filtres hiérarchiques en entonnoir : Établissement -> Section -> Cycle -> Niveau -> Classe
    const [selectedEtabId, setSelectedEtabId] = useState<string>(() =>
        currentEtablissementId
            ? String(currentEtablissementId)
            : etablissements[0]
              ? String(etablissements[0].id)
              : ''
    );
    const [sectionId, setSectionId] = useState<string>(initialSectionId);
    const [cycleId, setCycleId] = useState<string>(initialCycleId);
    const [niveauId, setNiveauId] = useState<string>(initialNiveauId);
    const [classeId, setClasseId] = useState<string>(initialClasseId);
    const [modePaiement, setModePaiement] = useState<string>('all');

    /**
     * Synchronise les valeurs initiales à chaque ouverture de la fenêtre modale
     * (ex: pré-remplissage avec le niveau ou la classe active depuis le Guichet Scolarité).
     */
    useEffect(() => {
        if (!open) return;

        setTypeEtat(initialType);
        setPreset(initialPreset);
        const bornes = calculerBornesPeriode(initialPreset, currentAnnee);
        setDateDebut(bornes.debut);
        setDateFin(bornes.fin);

        const defaultEtab = currentEtablissementId
            ? String(currentEtablissementId)
            : etablissements[0]
              ? String(etablissements[0].id)
              : '';
        setSelectedEtabId(defaultEtab);
        setSectionId(initialSectionId || 'all');
        setCycleId(initialCycleId || 'all');
        setNiveauId(initialNiveauId || 'all');
        setClasseId(initialClasseId || 'all');
        setModePaiement('all');
    }, [
        open,
        initialType,
        initialPreset,
        initialSectionId,
        initialCycleId,
        initialNiveauId,
        initialClasseId,
        currentEtablissementId,
        currentAnnee,
        etablissements,
    ]);

    /**
     * Établissement actuellement ciblé dans le générateur.
     */
    const activeEtablissement = useMemo(
        () =>
            etablissements.find((e) => String(e.id) === selectedEtabId) ??
            etablissements[0] ??
            null,
        [etablissements, selectedEtabId]
    );

    /**
     * Sections rattachées à l'établissement sélectionné.
     */
    const availableSections = useMemo(
        () => activeEtablissement?.sections ?? [],
        [activeEtablissement]
    );

    /**
     * Cycles disponibles en cascade selon la Section choisie.
     */
    const availableCycles = useMemo(() => {
        const targetSections =
            sectionId === 'all'
                ? availableSections
                : availableSections.filter((s) => String(s.id) === sectionId);
        return targetSections.flatMap((s) => s.cycles ?? []);
    }, [availableSections, sectionId]);

    /**
     * Niveaux disponibles en cascade selon le Cycle (et la Section) choisi.
     */
    const availableNiveaux = useMemo(() => {
        const targetCycles =
            cycleId === 'all'
                ? availableCycles
                : availableCycles.filter((c) => String(c.id) === cycleId);
        return targetCycles.flatMap((c) => c.niveaux ?? []);
    }, [availableCycles, cycleId]);

    /**
     * Classes actives disponibles en cascade selon le Niveau choisi.
     */
    const availableClasses = useMemo(() => {
        const targetNiveaux =
            niveauId === 'all'
                ? availableNiveaux
                : availableNiveaux.filter((n) => String(n.id) === niveauId);
        return targetNiveaux.flatMap((n) => n.classes ?? []);
    }, [availableNiveaux, niveauId]);

    /**
     * Applique un raccourci temporel (Journalier, Hebdomadaire, Mensuel, Annuel).
     */
    const handleSelectPreset = (nouveauPreset: PeriodePreset) => {
        setPreset(nouveauPreset);
        if (nouveauPreset !== 'personnalise') {
            const bornes = calculerBornesPeriode(nouveauPreset, currentAnnee);
            setDateDebut(bornes.debut);
            setDateFin(bornes.fin);
        }
    };

    /**
     * Réinitialise les filtres pédagogiques tout en conservant l'établissement actif.
     */
    const handleResetHierarchie = () => {
        setSectionId('all');
        setCycleId('all');
        setNiveauId('all');
        setClasseId('all');
        setModePaiement('all');
    };

    const estBilanGlobal = typeEtat === 'bilan_entrees_sorties';
    const supporteModePaiement =
        typeEtat === 'journal_detaille' || typeEtat === 'journal_synthetique';

    /**
     * Libellé synthétique du périmètre choisi affiché en pied de modale avant génération.
     */
    const resumePerimetre = useMemo(() => {
        if (estBilanGlobal) {
            return 'Établissement entier (Recettes & Dépenses consolidées)';
        }
        const morceaux: string[] = [];
        if (sectionId !== 'all') {
            const s = availableSections.find((item) => String(item.id) === sectionId);
            if (s) morceaux.push(s.nom);
        }
        if (cycleId !== 'all') {
            const c = availableCycles.find((item) => String(item.id) === cycleId);
            if (c) morceaux.push(c.nom);
        }
        if (niveauId !== 'all') {
            const n = availableNiveaux.find((item) => String(item.id) === niveauId);
            if (n) morceaux.push(`Niveau ${n.nom}`);
        }
        if (classeId !== 'all') {
            const cl = availableClasses.find((item) => String(item.id) === classeId);
            if (cl) morceaux.push(`Classe ${cl.nom}`);
        }

        return morceaux.length > 0
            ? morceaux.join(' • ')
            : 'Toutes les sections, cycles, niveaux et classes';
    }, [
        estBilanGlobal,
        sectionId,
        cycleId,
        niveauId,
        classeId,
        availableSections,
        availableCycles,
        availableNiveaux,
        availableClasses,
    ]);

    /**
     * Construit l'URL avec tous les paramètres sélectionnés et ouvre l'état officiel PDF dans un nouvel onglet.
     */
    const handleGenererPdf = (e: React.FormEvent) => {
        e.preventDefault();

        if (dateDebut && dateFin && dateDebut > dateFin) {
            toast.error('La date de début ne peut pas être postérieure à la date de fin.', {
                position: 'top-right',
            });
            return;
        }

        const params = new URLSearchParams();
        if (selectedEtabId) {
            params.set('etablissement_id', selectedEtabId);
        }
        if (currentAnnee?.id) {
            params.set('annee_scolaire_id', String(currentAnnee.id));
        }
        if (dateDebut) {
            params.set('date_debut', dateDebut);
        }
        if (dateFin) {
            params.set('date_fin', dateFin);
        }

        if (!estBilanGlobal) {
            if (sectionId !== 'all') params.set('section_id', sectionId);
            if (cycleId !== 'all') params.set('cycle_id', cycleId);
            if (niveauId !== 'all') params.set('niveau_id', niveauId);
            if (classeId !== 'all') params.set('classe_id', classeId);
        }

        if (supporteModePaiement && modePaiement !== 'all') {
            params.set('mode_paiement', modePaiement);
        }

        const routesMap: Record<TypeEtatFinancier, string> = {
            journal_detaille: '/administration/finances/etats/journal-detaille-pdf',
            journal_synthetique: '/administration/finances/etats/journal-synthetique-pdf',
            liste_insolvables: '/administration/finances/etats/insolvables-pdf',
            liste_en_regle: '/administration/finances/etats/en-regle-pdf',
            bilan_entrees_sorties: '/administration/finances/bilan-pdf',
        };

        const baseUrl = routesMap[typeEtat];
        const query = params.toString();
        window.open(query ? `${baseUrl}?${query}` : baseUrl, '_blank');

        toast.success("Génération de l'état financier PDF lancée.", {
            position: 'top-right',
            className: 'bg-emerald-600 text-white border-none',
        });
    };

    const optionsEtats: Array<{
        key: TypeEtatFinancier;
        titre: string;
        description: string;
        icon: React.ReactNode;
        accent: string;
    }> = [
        {
            key: 'journal_synthetique',
            titre: 'État Synthétique (Jour / Niveau)',
            description: 'Cumuls par jour, par niveau/classe et par rubrique',
            icon: <FileSpreadsheet className="w-4 h-4 text-emerald-600 shrink-0" />,
            accent: 'border-emerald-500 bg-emerald-50/70',
        },
        {
            key: 'journal_detaille',
            titre: 'Journal Détaillé des Reçus',
            description: 'Liste chronologique complète des encaissements',
            icon: <FileText className="w-4 h-4 text-sky-600 shrink-0" />,
            accent: 'border-sky-500 bg-sky-50/70',
        },
        {
            key: 'liste_insolvables',
            titre: 'Liste des Élèves Insolvables',
            description: 'Élèves ayant une échéance échue impayée à la date de fin',
            icon: <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />,
            accent: 'border-red-500 bg-red-50/70',
        },
        {
            key: 'liste_en_regle',
            titre: 'Liste des Élèves en Règle',
            description: 'Élèves soldés ou à jour de leurs tranches à la date de fin',
            icon: <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />,
            accent: 'border-emerald-500 bg-emerald-50/70',
        },
        ...(allowBilanEntreesSorties
            ? [
                  {
                      key: 'bilan_entrees_sorties' as TypeEtatFinancier,
                      titre: 'Bilan Entrées / Sorties',
                      description: 'Compte d’exploitation Recettes vs Dépenses validées',
                      icon: <Scale className="w-4 h-4 text-amber-600 shrink-0" />,
                      accent: 'border-amber-500 bg-amber-50/70',
                  },
              ]
            : []),
    ];

    const canSwitchEtablissement = isSuperAdmin && etablissements.length > 1;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-slate-900">
                        <Printer className="w-5 h-5 text-emerald-600 shrink-0" />
                        <span>Générateur d'États Financiers (PDF)</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-slate-500">
                        Filtrez librement vos états par période (journalier, mensuel, annuel ou dates précises) et par périmètre scolaire (établissement, section, cycle, niveau ou classe).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleGenererPdf} className="space-y-4">
                    {/* 1. CHOIX DU MODÈLE D'ÉTAT */}
                    <div className="space-y-2">
                        <Label className="text-xs font-bold uppercase tracking-wider text-slate-700">
                            1. Nature de l'État à Générer
                        </Label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {optionsEtats.map((opt) => {
                                const actif = typeEtat === opt.key;
                                return (
                                    <button
                                        key={opt.key}
                                        type="button"
                                        onClick={() => setTypeEtat(opt.key)}
                                        className={`text-left p-3 rounded-xl border transition-all cursor-pointer flex items-start gap-2.5 ${
                                            actif
                                                ? `${opt.accent} ring-1 ring-emerald-500/30 shadow-2xs`
                                                : 'border-slate-200 bg-white hover:border-slate-300'
                                        }`}
                                    >
                                        <div className="mt-0.5">{opt.icon}</div>
                                        <div className="min-w-0">
                                            <div className="text-xs font-bold text-slate-900">
                                                {opt.titre}
                                            </div>
                                            <div className="text-[11px] text-slate-500 leading-snug mt-0.5">
                                                {opt.description}
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* 2. PÉRIODE : DATE DE DÉBUT & DATE DE FIN */}
                    <div className="p-3.5 rounded-xl border border-slate-200 bg-slate-50/70 space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                                <CalendarRange className="w-4 h-4 text-emerald-600" />
                                <span>2. Période (Date de début & Date de fin)</span>
                            </Label>

                            <Badge
                                variant="outline"
                                className="text-[11px] font-semibold bg-white border-emerald-200 text-emerald-800"
                            >
                                Du {formatDate(dateDebut)} au {formatDate(dateFin)}
                            </Badge>
                        </div>

                        {/* Raccourcis rapides : Journalier, Hebdomadaire, Mensuel, Annuel, Libre */}
                        <div className="flex flex-wrap items-center gap-1.5">
                            {(
                                [
                                    { id: 'aujourdhui', label: "Journalier (Aujourd'hui)" },
                                    { id: 'semaine', label: 'Cette semaine' },
                                    { id: 'mois', label: 'Mensuel (Mois en cours)' },
                                    { id: 'annee', label: 'Année scolaire' },
                                    { id: 'personnalise', label: 'Dates personnalisées' },
                                ] as Array<{ id: PeriodePreset; label: string }>
                            ).map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => handleSelectPreset(item.id)}
                                    className={`px-2.5 py-1 rounded-lg text-xs font-semibold border transition-colors cursor-pointer ${
                                        preset === item.id
                                            ? 'bg-emerald-600 text-white border-emerald-600'
                                            : 'bg-white text-slate-700 border-slate-200 hover:border-emerald-400'
                                    }`}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label htmlFor="etat_date_debut" className="text-xs font-semibold">
                                    Date de début
                                </Label>
                                <Input
                                    id="etat_date_debut"
                                    type="date"
                                    value={formatDateForInput(dateDebut)}
                                    onChange={(e) => {
                                        setDateDebut(e.target.value);
                                        setPreset('personnalise');
                                    }}
                                    className="h-9 text-xs bg-white"
                                    required
                                />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="etat_date_fin" className="text-xs font-semibold">
                                    Date de fin
                                </Label>
                                <Input
                                    id="etat_date_fin"
                                    type="date"
                                    value={formatDateForInput(dateFin)}
                                    onChange={(e) => {
                                        setDateFin(e.target.value);
                                        setPreset('personnalise');
                                    }}
                                    className="h-9 text-xs bg-white"
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    {/* 3. HIÉRARCHIE SCOLAIRE : ÉTABLISSEMENT -> SECTION -> CYCLE -> NIVEAU -> CLASSE */}
                    <div className="p-3.5 rounded-xl border border-slate-200 bg-white space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <Label className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                                <Filter className="w-4 h-4 text-emerald-600" />
                                <span>3. Périmètre Scolaire (Établissement → Classe)</span>
                            </Label>

                            {!estBilanGlobal &&
                                (sectionId !== 'all' ||
                                    cycleId !== 'all' ||
                                    niveauId !== 'all' ||
                                    classeId !== 'all' ||
                                    modePaiement !== 'all') && (
                                    <button
                                        type="button"
                                        onClick={handleResetHierarchie}
                                        className="text-[11px] font-semibold text-slate-500 hover:text-emerald-600 flex items-center gap-1 cursor-pointer"
                                    >
                                        <RotateCcw className="w-3 h-3" /> Tout afficher
                                    </button>
                                )}
                        </div>

                        {/* Sélecteur d'établissement (actif pour Super-Admin multi-campus, verrouillé sinon) */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="space-y-1 min-w-0">
                                <Label className="text-xs font-semibold flex items-center gap-1">
                                    <Building2 className="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Établissement</span>
                                </Label>
                                {canSwitchEtablissement ? (
                                    <SearchableSelect
                                        options={etablissements.map((et) => ({
                                            value: String(et.id),
                                            label: et.nom,
                                            description: et.code ? `Code : ${et.code}` : undefined,
                                        }))}
                                        value={selectedEtabId}
                                        onValueChange={(val) => {
                                            if (!val) return;
                                            setSelectedEtabId(val);
                                            setSectionId('all');
                                            setCycleId('all');
                                            setNiveauId('all');
                                            setClasseId('all');
                                        }}
                                        placeholder="Choisir un établissement..."
                                        searchPlaceholder="Rechercher un établissement..."
                                        emptyMessage="Aucun établissement trouvé."
                                        className="h-9 text-xs font-normal bg-white"
                                    />
                                ) : (
                                    <div className="h-9 px-3 rounded-md border border-slate-200 bg-slate-50 flex items-center justify-between text-xs font-semibold text-slate-700">
                                        <span className="truncate">
                                            {activeEtablissement?.nom ??
                                                currentEtablissementNom ??
                                                'Établissement actif'}
                                        </span>
                                        <Lock className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                    </div>
                                )}
                            </div>

                            {supporteModePaiement && (
                                <div className="space-y-1">
                                    <Label className="text-xs font-semibold">
                                        Mode de Règlement
                                    </Label>
                                    <Select value={modePaiement} onValueChange={setModePaiement}>
                                        <SelectTrigger className="h-9 text-xs bg-white">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les modes de paiement</SelectItem>
                                            <SelectItem value="especes">Espèces uniquement</SelectItem>
                                            <SelectItem value="mobile_money_manuel">
                                                Mobile Money (OM / MoMo)
                                            </SelectItem>
                                            <SelectItem value="virement">Virements bancaires</SelectItem>
                                            <SelectItem value="cheque">Chèques</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </div>

                        {estBilanGlobal ? (
                            <p className="text-[11px] text-slate-500 bg-amber-50/70 border border-amber-200/70 rounded-lg p-2.5">
                                Le bilan comptable Entrées / Sorties consolide les recettes et les dépenses de fonctionnement à l'échelle de l'établissement sélectionné sur la période choisie.
                            </p>
                        ) : (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                                {/* Section */}
                                <div className="space-y-1 min-w-0">
                                    <Label className="text-[11px] font-semibold text-slate-600">
                                        Section
                                    </Label>
                                    <Select
                                        value={sectionId}
                                        onValueChange={(val) => {
                                            setSectionId(val);
                                            setCycleId('all');
                                            setNiveauId('all');
                                            setClasseId('all');
                                        }}
                                    >
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue placeholder="Toutes les sections" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Toutes les sections</SelectItem>
                                            {availableSections.map((s) => (
                                                <SelectItem key={s.id} value={String(s.id)}>
                                                    {s.nom}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Cycle */}
                                <div className="space-y-1 min-w-0">
                                    <Label className="text-[11px] font-semibold text-slate-600">
                                        Cycle
                                    </Label>
                                    <Select
                                        value={cycleId}
                                        onValueChange={(val) => {
                                            setCycleId(val);
                                            setNiveauId('all');
                                            setClasseId('all');
                                        }}
                                    >
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue placeholder="Tous les cycles" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les cycles</SelectItem>
                                            {availableCycles.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.nom}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Niveau */}
                                <div className="space-y-1 min-w-0">
                                    <Label className="text-[11px] font-semibold text-slate-600">
                                        Niveau
                                    </Label>
                                    <Select
                                        value={niveauId}
                                        onValueChange={(val) => {
                                            setNiveauId(val);
                                            setClasseId('all');
                                        }}
                                    >
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue placeholder="Tous les niveaux" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les niveaux</SelectItem>
                                            {availableNiveaux.map((n) => (
                                                <SelectItem key={n.id} value={String(n.id)}>
                                                    {n.nom}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Classe */}
                                <div className="space-y-1 min-w-0">
                                    <Label className="text-[11px] font-semibold text-slate-600">
                                        Classe
                                    </Label>
                                    <SearchableSelect
                                        options={[
                                            { value: 'all', label: 'Toutes les classes' },
                                            ...availableClasses.map((cl) => ({
                                                value: String(cl.id),
                                                label: cl.nom,
                                            })),
                                        ]}
                                        value={classeId}
                                        onValueChange={(val) => setClasseId(val || 'all')}
                                        placeholder="Toutes les classes"
                                        searchPlaceholder="Rechercher une classe..."
                                        emptyMessage="Aucune classe trouvée."
                                        className="h-9 text-xs font-normal"
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    {/* RÉSUMÉ AVANT IMPRESSION */}
                    <div className="px-3.5 py-2.5 rounded-xl bg-emerald-50/70 border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                        <div className="text-slate-700">
                            <span className="font-bold text-emerald-800">
                                Périmètre retenu :
                            </span>{' '}
                            {resumePerimetre}
                        </div>
                        <span className="font-mono text-[11px] font-bold text-emerald-700 shrink-0">
                            {formatDate(dateDebut)} → {formatDate(dateFin)}
                        </span>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="text-xs h-9"
                        >
                            Fermer
                        </Button>
                        <Button
                            type="submit"
                            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold h-9 flex items-center gap-1.5"
                        >
                            <Printer className="w-4 h-4" />
                            Générer & Ouvrir l'État PDF
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}


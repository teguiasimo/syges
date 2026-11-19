import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Printer,
    RotateCcw,
    Scale,
    Search,
    User,
    Wallet,
    X,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
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
import { formatDateForInput } from '@/lib/utils';
import type { EtablissementRef } from '@/types/eleve';
import type {
    CanFinancePermissions,
    CategorieFrais,
    TypeFrais,
} from '@/types/finance';
import type { ModeFiltreFrais } from '@/lib/finance-categories';
import { toast } from 'sonner';

/**
 * Types des états financiers officiels (standard camerounais + suivis analytiques SYGES).
 */
export type TypeEtatFinancier =
    | 'journal_versements'
    | 'journal_resume'
    | 'journal_categories'
    | 'bilan_resume'
    | 'bilan_detaille'
    | 'bilan_reductions'
    | 'liste_insolvables'
    | 'suivi_frais'
    | 'releve_eleve'
    | 'recettes_diverses'
    | 'liste_en_regle'
    | 'bilan_entrees_sorties';

/**
 * Raccourcis de sélection temporelle.
 */
export type PeriodePreset =
    | 'aujourdhui'
    | 'semaine'
    | 'mois'
    | 'annee'
    | 'personnalise';

/**
 * Caisse de règlement de l'établissement.
 */
interface CaisseRef {
    id: number;
    nom: string;
    is_principale?: boolean;
}

/**
 * Élève pour la sélection du relevé individuel.
 */
interface EleveOption {
    id: number;
    matricule: string;
    nom: string;
    prenom: string;
    nom_complet: string;
    classe_id?: number | null;
    classe?: string;
    etablissement_id?: number | null;
}

/**
 * Propriétés transmises par FinanceController::etats.
 */
interface EtatsFinancesPageProps {
    etablissements: EtablissementRef[];
    currentEtablissement: EtablissementRef | null;
    isSuperAdmin: boolean;
    isGlobal?: boolean;
    anneesScolaires: Array<{
        id: number;
        code: string;
        nom?: string;
        date_debut?: string | null;
        date_fin?: string | null;
        is_current?: boolean;
    }>;
    currentAnnee: {
        id: number;
        code: string;
        nom?: string;
        date_debut?: string | null;
        date_fin?: string | null;
    } | null;
    caisses?: CaisseRef[];
    eleves?: EleveOption[];
    categoriesFrais?: CategorieFrais[];
    typesFrais?: TypeFrais[];
    filters?: {
        etablissement_id?: string | number;
        annee_scolaire_id?: string | number;
        section_id?: string;
        cycle_id?: string;
        niveau_id?: string;
        classe_id?: string;
        caisse_id?: string;
        mode_paiement?: string;
        date_debut?: string;
        date_fin?: string;
        eleve_id?: string | number;
        categorie_frais_id?: string | number;
        type_frais_id?: string | number;
        mode_filtre_frais?: ModeFiltreFrais;
        type?: string;
        preset?: PeriodePreset;
    };
    can: CanFinancePermissions;
}

/**
 * Calcule l'intervalle [dateDebut, dateFin] au format ISO YYYY-MM-DD selon le raccourci choisi.
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
        const jourSemaine = maintenant.getDay();
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

    // Par défaut : mois en cours
    const premierDuMois = new Date(maintenant.getFullYear(), maintenant.getMonth(), 1);
    return {
        debut: formatDateForInput(premierDuMois),
        fin: aujourdhuiIso,
    };
}

/**
 * Page officielle du Centre des États & Rapports Financiers SYGES.
 *
 * Supporte :
 * 1. Le mode multi-campus Complexe Scolaire (vision globale consolidée de tous les établissements ou par établissement individuel).
 * 2. Les 8 grands états officiels inspirés des standards scolaires camerounais.
 * 3. Le filtrage hiérarchique en cascade (Section -> Cycle -> Niveau -> Classe).
 * 4. La recherche et sélection rapide d'un élève pour son relevé de compte individuel.
 */
export default function EtatsFinancesPage({
    etablissements,
    currentEtablissement,
    isSuperAdmin,
    isGlobal = false,
    anneesScolaires,
    currentAnnee,
    eleves = [],
    categoriesFrais = [],
    typesFrais = [],
    filters = {},
}: EtatsFinancesPageProps) {
    // 1. Établissement actif ('all' pour vision consolidée Complexe Scolaire, sinon id de l'établissement)
    const selectedEtabId = isGlobal || filters.etablissement_id === 'all'
        ? 'all'
        : String(currentEtablissement?.id ?? etablissements[0]?.id ?? 'all');

    // 2. Sections, cycles, niveaux et classes de l'établissement actif
    const sections = useMemo(() => currentEtablissement?.sections ?? [], [currentEtablissement]);

    // 3. État des filtres hiérarchiques en cascade
    const [sectionId, setSectionId] = useState<string>(() =>
        filters.section_id
            ? String(filters.section_id)
            : sections.length === 1
              ? String(sections[0].id)
              : 'all'
    );
    const [cycleId, setCycleId] = useState<string>(() =>
        filters.cycle_id ? String(filters.cycle_id) : 'all'
    );
    const [niveauId, setNiveauId] = useState<string>(() =>
        filters.niveau_id ? String(filters.niveau_id) : 'all'
    );
    const [classeId, setClasseId] = useState<string>(() =>
        filters.classe_id ? String(filters.classe_id) : 'all'
    );

    // 4. Trésorerie (Caisse et Mode de règlement)
    const [caisseId, setCaisseId] = useState<string>(() =>
        filters.caisse_id ? String(filters.caisse_id) : 'all'
    );
    const [modePaiement, setModePaiement] = useState<string>(() =>
        filters.mode_paiement ? String(filters.mode_paiement) : 'all'
    );

    // 4bis. Ciblage analytique par Catégorie de frais, Rubrique précise & Palier
    const [categorieFraisId, setCategorieFraisId] = useState<string>(() =>
        filters.categorie_frais_id ? String(filters.categorie_frais_id) : 'all'
    );
    const [typeFraisId, setTypeFraisId] = useState<string>(() =>
        filters.type_frais_id ? String(filters.type_frais_id) : 'all'
    );
    const [modeFiltreFrais, setModeFiltreFrais] = useState<ModeFiltreFrais>(() =>
        filters.mode_filtre_frais ?? 'tous'
    );

    // Filtre des rubriques selon la catégorie choisie (en vision globale, inclut les homonymes des autres campus)
    const availableTypesFrais = useMemo(() => {
        if (categorieFraisId === 'all') {
            return typesFrais;
        }
        const selectedCat = categoriesFrais.find((c) => String(c.id) === categorieFraisId);
        const selectedCatNorm = selectedCat?.nom.trim().toLowerCase() ?? '';
        return typesFrais.filter((tf) => {
            if (String(tf.categorie_frais_id ?? '') === categorieFraisId) {
                return true;
            }
            if (isGlobal && selectedCatNorm && tf.categorie_frais?.nom) {
                return tf.categorie_frais.nom.trim().toLowerCase() === selectedCatNorm;
            }
            return false;
        });
    }, [typesFrais, categoriesFrais, categorieFraisId, isGlobal]);

    // 5. Élève sélectionné pour le relevé individuel (saisie interactive & popover compact de 6 suggestions)
    const [selectedEleveId, setSelectedEleveId] = useState<string>(() =>
        filters.eleve_id ? String(filters.eleve_id) : ''
    );
    const [eleveSearchTerm, setEleveSearchTerm] = useState<string>('');
    const [isEleveSearchOpen, setIsEleveSearchOpen] = useState<boolean>(false);
    const searchDropdownRef = useRef<HTMLDivElement>(null);

    // Fermeture automatique du popover lors d'un clic en dehors
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (
                searchDropdownRef.current &&
                !searchDropdownRef.current.contains(event.target as Node)
            ) {
                setIsEleveSearchOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    // 6. Intervalle temporel (date_debut -> date_fin)
    const presetInitial: PeriodePreset = filters.preset ?? (filters.date_debut ? 'personnalise' : 'mois');
    const [periodePreset, setPeriodePreset] = useState<PeriodePreset>(presetInitial);

    const bornesInitiales = useMemo(
        () => calculerBornesPeriode(presetInitial, currentAnnee),
        [presetInitial, currentAnnee]
    );

    const [dateDebut, setDateDebut] = useState<string>(
        filters.date_debut ? formatDateForInput(filters.date_debut) : bornesInitiales.debut
    );
    const [dateFin, setDateFin] = useState<string>(
        filters.date_fin ? formatDateForInput(filters.date_fin) : bornesInitiales.fin
    );

    /**
     * Applique un raccourci de période prédéfini.
     */
    const handleSelectPreset = (nouveauPreset: PeriodePreset) => {
        setPeriodePreset(nouveauPreset);
        if (nouveauPreset !== 'personnalise') {
            const { debut, fin } = calculerBornesPeriode(nouveauPreset, currentAnnee);
            setDateDebut(debut);
            setDateFin(fin);
        }
    };

    /**
     * Cycles filtrés selon la section sélectionnée.
     */
    const availableCycles = useMemo(() => {
        if (sectionId === 'all') {
            return sections.flatMap((s) => s.cycles ?? []);
        }
        const s = sections.find((item) => String(item.id) === sectionId);
        return s?.cycles ?? [];
    }, [sections, sectionId]);

    /**
     * Niveaux filtrés selon le cycle ou la section sélectionnée.
     */
    const availableNiveaux = useMemo(() => {
        if (cycleId !== 'all') {
            const c = availableCycles.find((item) => String(item.id) === cycleId);
            return c?.niveaux ?? [];
        }
        if (sectionId !== 'all') {
            return availableCycles.flatMap((c) => c.niveaux ?? []);
        }
        return sections.flatMap((s) => (s.cycles ?? []).flatMap((c) => c.niveaux ?? []));
    }, [sections, availableCycles, cycleId, sectionId]);

    /**
     * Classes filtrées selon le niveau, cycle ou section sélectionnée.
     */
    const availableClasses = useMemo(() => {
        if (niveauId !== 'all') {
            const n = availableNiveaux.find((item) => String(item.id) === niveauId);
            return n?.classes ?? [];
        }
        if (cycleId !== 'all') {
            return availableNiveaux.flatMap((n) => n.classes ?? []);
        }
        if (sectionId !== 'all') {
            return availableNiveaux.flatMap((n) => n.classes ?? []);
        }
        return sections.flatMap((s) =>
            (s.cycles ?? []).flatMap((c) => (c.niveaux ?? []).flatMap((n) => n.classes ?? []))
        );
    }, [sections, availableNiveaux, niveauId, cycleId, sectionId]);

    /**
     * Liste des élèves filtrés pour la recherche rapide du relevé individuel.
     */
    const filteredEleves = useMemo(() => {
        let list = eleves;
        if (classeId !== 'all') {
            list = list.filter((e) => String(e.classe_id) === classeId);
        }
        if (!eleveSearchTerm.trim()) {
            return list;
        }
        const term = eleveSearchTerm.toLowerCase().trim();
        return list.filter(
            (e) =>
                e.nom_complet.toLowerCase().includes(term) ||
                e.matricule.toLowerCase().includes(term) ||
                (e.classe ?? '').toLowerCase().includes(term)
        );
    }, [eleves, classeId, eleveSearchTerm]);

    /**
     * Maximum 6 suggestions d'élèves pour le popover compact de l'État #08 (Relevé Individuel).
     */
    const suggestionsEleves = useMemo(() => {
        return filteredEleves.slice(0, 6);
    }, [filteredEleves]);

    /**
     * Changement d'établissement (avec support 'all' pour la vision globale Complexe Scolaire).
     */
    const handleChangeEtablissement = (newEtabId: string) => {
        router.get(
            '/administration/finances/etats',
            {
                etablissement_id: newEtabId,
                annee_scolaire_id: currentAnnee?.id,
            },
            {
                preserveState: false,
                preserveScroll: true,
            }
        );
    };

    /**
     * Changement d'année scolaire.
     */
    const handleChangeAnnee = (newAnneeId: string) => {
        router.get(
            '/administration/finances/etats',
            {
                etablissement_id: selectedEtabId,
                annee_scolaire_id: newAnneeId,
            },
            {
                preserveState: false,
                preserveScroll: true,
            }
        );
    };

    /**
     * Réinitialise l'ensemble des filtres à leurs valeurs par défaut.
     */
    const handleResetFilters = () => {
        setSectionId('all');
        setCycleId('all');
        setNiveauId('all');
        setClasseId('all');
        setCaisseId('all');
        setModePaiement('all');
        setCategorieFraisId('all');
        setTypeFraisId('all');
        setModeFiltreFrais('tous');
        setSelectedEleveId('');
        setEleveSearchTerm('');
        setIsEleveSearchOpen(false);
        handleSelectPreset('mois');
        toast.info('Filtres réinitialisés au mois en cours et périmètre global.', {
            position: 'top-right',
        });
    };

    /**
     * Génère l'URL complète avec paramètres pour un état financier donné.
     */
    const buildEtatUrl = (type: TypeEtatFinancier): string => {
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

        if (sectionId !== 'all') params.set('section_id', sectionId);
        if (cycleId !== 'all') params.set('cycle_id', cycleId);
        if (niveauId !== 'all') params.set('niveau_id', niveauId);
        if (classeId !== 'all') params.set('classe_id', classeId);
        if (caisseId !== 'all') params.set('caisse_id', caisseId);
        if (modePaiement !== 'all') params.set('mode_paiement', modePaiement);
        if (categorieFraisId !== 'all') params.set('categorie_frais_id', categorieFraisId);
        if (typeFraisId !== 'all') params.set('type_frais_id', typeFraisId);
        if (modeFiltreFrais !== 'tous') params.set('mode_filtre_frais', modeFiltreFrais);
        if (selectedEleveId) params.set('eleve_id', selectedEleveId);

        const routesMap: Record<TypeEtatFinancier, string> = {
            journal_versements: '/administration/finances/etats/journal-versements-pdf',
            journal_resume: '/administration/finances/etats/journal-resume-pdf',
            journal_categories: '/administration/finances/etats/journal-categories-pdf',
            bilan_resume: '/administration/finances/etats/bilan-resume-pdf',
            bilan_detaille: '/administration/finances/etats/bilan-detaille-pdf',
            bilan_reductions: '/administration/finances/etats/bilan-reductions-pdf',
            liste_insolvables: '/administration/finances/etats/insolvables-pdf',
            suivi_frais: '/administration/finances/etats/suivi-frais-pdf',
            releve_eleve: '/administration/finances/etats/releve-eleve-pdf',
            recettes_diverses: '/administration/finances/etats/recettes-diverses-pdf',
            liste_en_regle: '/administration/finances/etats/en-regle-pdf',
            bilan_entrees_sorties: '/administration/finances/bilan-pdf',
        };

        const baseUrl = routesMap[type];
        const query = params.toString();
        return query ? `${baseUrl}?${query}` : baseUrl;
    };

    /**
     * Déclenche la génération et l'ouverture du PDF officiel.
     */
    const handleGenererEtat = (type: TypeEtatFinancier, nomEtat: string) => {
        if (dateDebut && dateFin && dateDebut > dateFin) {
            toast.error('La date de début ne peut pas être postérieure à la date de fin.', {
                position: 'top-right',
            });
            return;
        }

        if (type === 'releve_eleve' && !selectedEleveId) {
            toast.error("Veuillez sélectionner un élève dans la liste déroulante ci-dessous pour imprimer son relevé.", {
                position: 'top-right',
            });
            const selEl = document.getElementById('select-eleve-releve');
            if (selEl) {
                selEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                selEl.focus();
            }
            return;
        }

        const url = buildEtatUrl(type);
        window.open(url, '_blank');

        toast.success(`Édition PDF lancée : ${nomEtat}`, {
            position: 'top-right',
            className: 'bg-emerald-600 text-white border-none',
        });
    };

    /**
     * Génère et ouvre directement le relevé individuel officiel d'un élève sélectionné.
     *
     * @param {EleveOption} eleve - Informations de l'élève cible.
     */
    const handleGenererReleveEleve = (eleve: EleveOption) => {
        if (dateDebut && dateFin && dateDebut > dateFin) {
            toast.error('La date de début ne peut pas être postérieure à la date de fin.', {
                position: 'top-right',
            });
            return;
        }

        setSelectedEleveId(String(eleve.id));

        const params = new URLSearchParams();
        if (selectedEtabId) params.set('etablissement_id', selectedEtabId);
        if (currentAnnee?.id) params.set('annee_scolaire_id', String(currentAnnee.id));
        if (dateDebut) params.set('date_debut', dateDebut);
        if (dateFin) params.set('date_fin', dateFin);
        params.set('eleve_id', String(eleve.id));

        const url = `/administration/finances/etats/releve-eleve-pdf?${params.toString()}`;
        window.open(url, '_blank');

        toast.success(`Édition du relevé : ${eleve.nom_complet}`, {
            position: 'top-right',
            className: 'bg-emerald-600 text-white border-none',
        });
    };

    /**
     * Résumé textuel du périmètre hiérarchique actuellement configuré.
     */
    const resumePerimetre = useMemo(() => {
        const morceaux: string[] = [];
        if (selectedEtabId === 'all') {
            morceaux.push('Complexe Scolaire Global (Tous les établissements)');
        } else if (currentEtablissement) {
            morceaux.push(currentEtablissement.nom);
        }
        if (sectionId !== 'all') {
            const s = sections.find((item) => String(item.id) === sectionId);
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

        return morceaux.length > 0 ? morceaux.join(' • ') : 'Périmètre Global (Tous campus confondus)';
    }, [selectedEtabId, currentEtablissement, sectionId, cycleId, niveauId, classeId, sections, availableCycles, availableNiveaux, availableClasses]);

    /**
     * Les 8 grands états officiels du standard scolaire camerounais.
     * Configuration épurée et synthétique pour un affichage direct et ergonomique.
     */
    const etatsOfficiels: Array<{
        key: TypeEtatFinancier;
        numero: string;
        titre: string;
        format: 'A4 Portrait' | 'A4 Paysage';
        badgeCouleur: string;
        isTargetStudent?: boolean;
    }> = [
        {
            key: 'journal_versements',
            numero: '01',
            titre: 'Journal des Versements',
            format: 'A4 Paysage',
            badgeCouleur: 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300',
        },
        {
            key: 'journal_resume',
            numero: '02',
            titre: 'Journal Résumé des Versements',
            format: 'A4 Portrait',
            badgeCouleur: 'bg-teal-50 text-teal-800 border-teal-300 dark:bg-teal-950/60 dark:text-teal-300',
        },
        {
            key: 'journal_categories',
            numero: '03',
            titre: 'Journal des Versements par Catégories',
            format: 'A4 Paysage',
            badgeCouleur: 'bg-sky-50 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300',
        },
        {
            key: 'bilan_resume',
            numero: '04',
            titre: 'Bilan Résumé des Recouvrements',
            format: 'A4 Paysage',
            badgeCouleur: 'bg-indigo-50 text-indigo-800 border-indigo-300 dark:bg-indigo-950/60 dark:text-indigo-300',
        },
        {
            key: 'bilan_detaille',
            numero: '05',
            titre: 'Bilan Détaillé des Versements',
            format: 'A4 Paysage',
            badgeCouleur: 'bg-blue-50 text-blue-800 border-blue-300 dark:bg-blue-950/60 dark:text-blue-300',
        },
        {
            key: 'bilan_reductions',
            numero: '06',
            titre: 'Bilan des Bourses & Réductions',
            format: 'A4 Portrait',
            badgeCouleur: 'bg-purple-50 text-purple-800 border-purple-300 dark:bg-purple-950/60 dark:text-purple-300',
        },
        {
            key: 'liste_insolvables',
            numero: '07',
            titre: 'Liste des Élèves Insolvables',
            format: 'A4 Paysage',
            badgeCouleur: 'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300',
        },
        {
            key: 'suivi_frais',
            numero: '09',
            titre: 'Suivi Nominatif par Frais & Palier',
            format: 'A4 Paysage',
            badgeCouleur: 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300',
        },
    ];

    return (
        <>
            <Head title="Centre des États Financiers - SYGES" />

            {/* Conteneur principal pleine largeur sans plafond artificiel (adapté aux écrans larges et standards) */}
            <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6 min-w-0 w-full">
                {/* 1. En-tête compact & Navigation */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200 dark:border-slate-800">
                    <div className="flex items-center gap-2.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            asChild
                            className="h-8 px-2 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                        >
                            <Link href="/administration/finances" className="flex items-center gap-1.5">
                                <ArrowLeft className="w-3.5 h-3.5" />
                                <span>Finances</span>
                            </Link>
                        </Button>
                        <span className="text-slate-300 dark:text-slate-700">/</span>
                        <div className="flex items-center gap-2">
                            <Printer className="w-4 h-4 text-emerald-600" />
                            <h1 className="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100">
                                États Financiers
                            </h1>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {isSuperAdmin && (
                            <Select value={selectedEtabId} onValueChange={handleChangeEtablissement}>
                                <SelectTrigger className="h-8 min-w-50 text-xs bg-white dark:bg-slate-900 font-medium">
                                    <Building2 className="w-3.5 h-3.5 text-emerald-600 mr-1.5 shrink-0" />
                                    <SelectValue placeholder="Périmètre..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all" className="font-semibold text-emerald-700 dark:text-emerald-400">
                                        Complexe Global (Tous campus)
                                    </SelectItem>
                                    {etablissements.map((et) => (
                                        <SelectItem key={et.id} value={String(et.id)}>
                                            {et.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {currentAnnee && (
                            <Select value={String(currentAnnee.id)} onValueChange={handleChangeAnnee}>
                                <SelectTrigger className="h-8 text-xs font-semibold border-emerald-300 text-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    <SelectValue placeholder="Année" />
                                </SelectTrigger>
                                <SelectContent>
                                    {anneesScolaires.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            Année {a.code}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </div>
                </div>

                {/* 2. Barre de filtrage compacte et fluide */}
                <div className="bg-slate-50/70 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2.5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        {/* Raccourcis temporels */}
                        <div className="flex flex-wrap items-center gap-1">
                            {[
                                { id: 'aujourdhui', label: "Aujourd'hui" },
                                { id: 'semaine', label: 'Cette Semaine' },
                                { id: 'mois', label: 'Ce Mois-ci' },
                                { id: 'annee', label: 'Année' },
                                { id: 'personnalise', label: 'Libre' },
                            ].map((preset) => (
                                <button
                                    key={preset.id}
                                    type="button"
                                    onClick={() => handleSelectPreset(preset.id as PeriodePreset)}
                                    className={`px-2 py-0.5 text-[11px] font-semibold rounded transition-colors cursor-pointer ${
                                        periodePreset === preset.id
                                            ? 'bg-emerald-600 text-white shadow-xs'
                                            : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    }`}
                                >
                                    {preset.label}
                                </button>
                            ))}
                        </div>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleResetFilters}
                            className="h-6 text-[11px] text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 gap-1 px-2"
                        >
                            <RotateCcw className="w-3 h-3" />
                            Réinitialiser
                        </Button>
                    </div>

                    {/* Grille compacte des filtres */}
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2">
                        <div>
                            <Label htmlFor="date_debut" className="text-[10px] text-slate-500 font-medium">
                                Date début
                            </Label>
                            <Input
                                id="date_debut"
                                type="date"
                                value={formatDateForInput(dateDebut)}
                                onChange={(e) => {
                                    setDateDebut(e.target.value);
                                    setPeriodePreset('personnalise');
                                }}
                                className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5"
                            />
                        </div>

                        <div>
                            <Label htmlFor="date_fin" className="text-[10px] text-slate-500 font-medium">
                                Date fin
                            </Label>
                            <Input
                                id="date_fin"
                                type="date"
                                value={formatDateForInput(dateFin)}
                                onChange={(e) => {
                                    setDateFin(e.target.value);
                                    setPeriodePreset('personnalise');
                                }}
                                className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5"
                            />
                        </div>

                        <div>
                            <Label htmlFor="section_id" className="text-[10px] text-slate-500 font-medium">
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
                                <SelectTrigger id="section_id" className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5">
                                    <SelectValue placeholder="Toutes" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Toutes sections</SelectItem>
                                    {sections.map((s) => (
                                        <SelectItem key={s.id} value={String(s.id)}>
                                            {s.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="niveau_id" className="text-[10px] text-slate-500 font-medium">
                                Niveau
                            </Label>
                            <Select
                                value={niveauId}
                                onValueChange={(val) => {
                                    setNiveauId(val);
                                    setClasseId('all');
                                }}
                                disabled={availableNiveaux.length === 0}
                            >
                                <SelectTrigger id="niveau_id" className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5">
                                    <SelectValue placeholder="Tous" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous niveaux</SelectItem>
                                    {availableNiveaux.map((n) => (
                                        <SelectItem key={n.id} value={String(n.id)}>
                                            {n.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="classe_id" className="text-[10px] text-slate-500 font-medium">
                                Classe
                            </Label>
                            <Select
                                value={classeId}
                                onValueChange={setClasseId}
                                disabled={availableClasses.length === 0}
                            >
                                <SelectTrigger id="classe_id" className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5">
                                    <SelectValue placeholder="Toutes" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Toutes classes</SelectItem>
                                    {availableClasses.map((cl) => (
                                        <SelectItem key={cl.id} value={String(cl.id)}>
                                            {cl.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="mode_paiement" className="text-[10px] text-slate-500 font-medium">
                                Mode
                            </Label>
                            <Select value={modePaiement} onValueChange={setModePaiement}>
                                <SelectTrigger id="mode_paiement" className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5">
                                    <SelectValue placeholder="Tous" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous modes</SelectItem>
                                    <SelectItem value="especes">Espèces</SelectItem>
                                    <SelectItem value="mobile_money_manuel">Mobile Money</SelectItem>
                                    <SelectItem value="cheque">Chèque</SelectItem>
                                    <SelectItem value="virement">Virement</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Ligne 3 : Ciblage optionnel par Catégorie de Frais, Rubrique précise & Palier (États #01, #03 et #09) */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 border-t border-slate-200/70 dark:border-slate-800">
                        <div>
                            <Label htmlFor="categorie_frais_id" className="text-[10px] text-slate-500 font-medium">
                                Catégorie de Frais (#03, #09)
                            </Label>
                            <Select
                                value={categorieFraisId}
                                onValueChange={(val) => {
                                    setCategorieFraisId(val);
                                    setTypeFraisId('all');
                                }}
                            >
                                <SelectTrigger id="categorie_frais_id" className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5">
                                    <SelectValue placeholder="Toutes catégories" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Toutes les catégories</SelectItem>
                                    {categoriesFrais.map((cat) => (
                                        <SelectItem key={cat.id} value={String(cat.id)}>
                                            {cat.nom}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="type_frais_id" className="text-[10px] text-slate-500 font-medium">
                                Frais / Palier Précis (#01, #03, #09)
                            </Label>
                            <Select value={typeFraisId} onValueChange={setTypeFraisId}>
                                <SelectTrigger id="type_frais_id" className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5">
                                    <SelectValue placeholder="Tous les frais" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous les frais / rubriques</SelectItem>
                                    {availableTypesFrais.map((tf) => (
                                        <SelectItem key={tf.id} value={String(tf.id)}>
                                            {tf.libelle}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="mode_filtre_frais" className="text-[10px] text-slate-500 font-medium">
                                Condition sur le Frais (#09)
                            </Label>
                            <Select
                                value={modeFiltreFrais}
                                onValueChange={(val) => setModeFiltreFrais(val as ModeFiltreFrais)}
                            >
                                <SelectTrigger id="mode_filtre_frais" className="h-7 text-xs bg-white dark:bg-slate-900 mt-0.5">
                                    <SelectValue placeholder="Condition" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="tous">Tous les élèves concernés</SelectItem>
                                    <SelectItem value="uniquement_palier">
                                        Payé uniquement jusqu'à ce palier
                                    </SelectItem>
                                    <SelectItem value="solde">Ayant soldé ce frais / catégorie</SelectItem>
                                    <SelectItem value="partiel">Paiement partiel uniquement</SelectItem>
                                    <SelectItem value="non_paye">Non encore payé (0 FCFA)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                {/* 3. Les États & Bilans Globaux (#01 à #07 & #09) */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-xs text-slate-500">
                        <span className="font-bold uppercase tracking-wider text-[11px] text-slate-600 dark:text-slate-400">
                            États & Bilans Globaux (#01 à #07 & #09)
                        </span>
                        <span className="text-[11px] truncate max-w-md hidden sm:inline text-slate-400">
                            {resumePerimetre}
                        </span>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                        {etatsOfficiels.map((etat) => (
                            <div
                                key={etat.key}
                                className="flex items-center justify-between p-2.5 px-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-emerald-500 hover:shadow-2xs transition-all"
                            >
                                <div className="flex items-center gap-2 min-w-0">
                                    <span className="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 shrink-0">
                                        #{etat.numero}
                                    </span>
                                    <span className="text-xs sm:text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                                        {etat.titre}
                                    </span>
                                    <Badge variant="outline" className={`text-[9px] px-1.5 py-0 shrink-0 font-medium ${etat.badgeCouleur}`}>
                                        {etat.format}
                                    </Badge>
                                </div>

                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => handleGenererEtat(etat.key, etat.titre)}
                                    className="h-7 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shrink-0 ml-2 gap-1 px-2.5 shadow-2xs cursor-pointer"
                                >
                                    <Printer className="w-3 h-3" />
                                    <span>Imprimer</span>
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>

                {/* 4. État #08 : Relevé Individuel de l’Élève (Sélection compacte et Popover de 6 élèves max au clic) */}
                <div className="relative rounded-xl border border-amber-200/90 dark:border-amber-900/50 bg-amber-50/20 dark:bg-amber-950/10 p-3 sm:p-4 shadow-2xs">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-2 min-w-0">
                            <span className="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 shrink-0">
                                #08
                            </span>
                            <div className="min-w-0">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h3 className="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
                                        Relevé Individuel de l’Élève
                                    </h3>
                                    <Badge variant="outline" className="text-[9px] px-1.5 py-0 font-medium bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300">
                                        A4 Portrait
                                    </Badge>
                                </div>
                                <p className="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 hidden sm:block">
                                    Situation financière complète (échéancier des droits, versements, bourses et solde dû).
                                </p>
                            </div>
                        </div>

                        {/* Zone de recherche avec Dropdown / Popover flottant de 6 suggestions au clic */}
                        <div ref={searchDropdownRef} className="relative w-full sm:w-80 shrink-0">
                            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none" />
                            <Input
                                type="text"
                                placeholder="Cliquer pour rechercher un élève..."
                                value={eleveSearchTerm}
                                onFocus={() => setIsEleveSearchOpen(true)}
                                onClick={() => setIsEleveSearchOpen(true)}
                                onChange={(e) => {
                                    setEleveSearchTerm(e.target.value);
                                    setIsEleveSearchOpen(true);
                                }}
                                className="pl-8 pr-7 h-8 text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 focus-visible:ring-emerald-500 shadow-2xs"
                            />
                            {eleveSearchTerm && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setEleveSearchTerm('');
                                    }}
                                    className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5 cursor-pointer"
                                    title="Effacer la recherche"
                                >
                                    <X className="w-3.5 h-3.5" />
                                </button>
                            )}

                            {/* Menu déroulant / Popover flottant (affiché uniquement au focus / clic sur le champ) */}
                            {isEleveSearchOpen && (
                                <div className="absolute right-0 top-full mt-1.5 w-full sm:w-96 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl z-50 p-2 space-y-1 animate-in fade-in-0 zoom-in-95">
                                    <div className="flex items-center justify-between px-2 py-1 text-[11px] font-semibold text-slate-500 border-b border-slate-100 dark:border-slate-800">
                                        <span>Suggestions ({suggestionsEleves.length} sur {filteredEleves.length})</span>
                                        {classeId !== 'all' && (
                                            <span className="text-[10px] text-emerald-600 font-medium truncate max-w-30">
                                                Classe filtrée
                                            </span>
                                        )}
                                    </div>

                                    {suggestionsEleves.length > 0 ? (
                                        <div className="space-y-1 max-h-72 overflow-y-auto pt-1">
                                            {suggestionsEleves.map((el) => (
                                                <div
                                                    key={el.id}
                                                    className="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/80 border border-transparent hover:border-slate-200 dark:hover:border-slate-800 transition-colors gap-2"
                                                >
                                                    <div className="flex items-center gap-2 min-w-0">
                                                        <div className="size-7 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0 text-slate-600 dark:text-slate-300">
                                                            <User className="size-3.5" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="text-xs font-semibold text-slate-900 dark:text-slate-100 truncate" title={el.nom_complet}>
                                                                {el.nom_complet}
                                                            </div>
                                                            <div className="text-[10px] text-slate-500 flex items-center gap-1.5 truncate">
                                                                <span className="font-mono">Mat: {el.matricule}</span>
                                                                <span>•</span>
                                                                <span className="font-medium text-slate-600 dark:text-slate-400">{el.classe ?? 'N/A'}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() => {
                                                            handleGenererReleveEleve(el);
                                                            setIsEleveSearchOpen(false);
                                                        }}
                                                        className="h-7 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shrink-0 gap-1 px-2.5 shadow-2xs cursor-pointer"
                                                        title={`Imprimer le relevé individuel de ${el.nom_complet}`}
                                                    >
                                                        <Printer className="w-3 h-3" />
                                                        <span>Imprimer</span>
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="p-4 text-center text-xs text-slate-500">
                                            Aucun élève trouvé
                                            {eleveSearchTerm ? ` pour "${eleveSearchTerm}"` : ''}
                                            {classeId !== 'all' ? ' dans cette classe' : ''}.
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* 5. Autres éditions complémentaires */}
                <div className="pt-2 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span className="text-[11px] font-medium text-slate-400">
                        Autres éditions complémentaires :
                    </span>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleGenererEtat('recettes_diverses', 'État des Recettes Diverses')}
                            className="h-7 text-xs gap-1.5 bg-white dark:bg-slate-900"
                        >
                            <Wallet className="w-3 h-3 text-purple-600" />
                            Recettes Diverses
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleGenererEtat('liste_en_regle', 'Liste des Élèves en Règle')}
                            className="h-7 text-xs gap-1.5 bg-white dark:bg-slate-900"
                        >
                            <CheckCircle2 className="w-3 h-3 text-teal-600" />
                            Élèves en Règle
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleGenererEtat('bilan_entrees_sorties', 'Bilan Trésorerie')}
                            className="h-7 text-xs gap-1.5 bg-white dark:bg-slate-900"
                        >
                            <Scale className="w-3 h-3 text-indigo-600" />
                            Bilan Trésorerie
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * Fil d'Ariane officiel pour le Centre des États & Rapports Financiers.
 */
EtatsFinancesPage.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Finances & Caisse',
            href: '/administration/finances',
        },
        {
            title: 'Centre des États & Rapports',
            href: '/administration/finances/etats',
        },
    ],
};

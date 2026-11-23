import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { type ColumnDef } from '@tanstack/react-table';
import {
    Award,
    Building2,
    CheckCircle2,
    CreditCard,
    DollarSign,
    FileCheck2,
    FileSpreadsheet,
    Filter,
    FolderOpen,
    GraduationCap,
    PackagePlus,
    Printer,
    RotateCcw,
    Search,
    Tag,
    Users,
    Wallet,
} from 'lucide-react';
import {
    allouerLignesCompteEleve,
    calculerSyntheseCategoriesGuichet,
    evaluerCibleFraisEleve,
    type ModeFiltreFrais,
} from '@/lib/finance-categories';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
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
import type { EtablissementRef } from '@/types/eleve';
import type {
    BourseRemise,
    CanFinancePermissions,
    CategorieFinanciere,
    CategorieFrais,
    CompteFinancierEleve,
    FraisScolaire,
    TypeFrais,
} from '@/types/finance';
import DossierFinancierModal from './modals/dossier-financier-modal';
import ModeleBourseModal from './modals/modele-bourse-modal';

/**
 * Propriétés transmises par FinanceController::scolarites à la page dédiée du Guichet Scolarité.
 */
interface ScolaritesPageProps {
    comptes: CompteFinancierEleve[];
    categoriesFrais?: CategorieFrais[];
    typesFrais: TypeFrais[];
    tarifs: FraisScolaire[];
    fraisOptionnels: FraisScolaire[];
    boursesRemises: BourseRemise[];
    categoriesBourses: CategorieFinanciere[];
    estCaisseCloturee: boolean;
    etablissements: EtablissementRef[];
    currentEtablissement: EtablissementRef | null;
    isSuperAdmin: boolean;
    anneesScolaires: { id: number; code: string; date_debut?: string | null; date_fin?: string | null }[];
    currentAnnee: { id: number; code: string; date_debut?: string | null; date_fin?: string | null } | null;
    filters: {
        etablissement_id?: number | string;
        annee_scolaire_id?: number | string;
        classe_id?: number | string;
        compte_id?: number | string;
    };
    can: CanFinancePermissions;
}

/**
 * Page dédiée « Guichet Scolarité & Dossiers Élèves » (Module 11 - SYGES).
 *
 * Offre un parcours ergonomique en entonnoir :
 * Établissement (selon droits) → Section → Cycle → Niveau → Série (optionnel) → Classe,
 * accompagné de la synthèse financière par Catégorie de Frais (Scolarité, Frais d'Examen, Fête...)
 * et de la liste interactive des élèves (TanStack Table) permettant d'ouvrir en 1 clic
 * le Dossier Financier Tout-en-Un d'un élève.
 *
 * @param {ScolaritesPageProps} props Propriétés Inertia
 * @returns {JSX.Element} Page Guichet Scolarité
 */
export default function ScolaritesPage({
    comptes,
    categoriesFrais = [],
    typesFrais,
    tarifs,
    fraisOptionnels,
    boursesRemises,
    categoriesBourses,
    estCaisseCloturee,
    etablissements,
    currentEtablissement,
    isSuperAdmin,
    currentAnnee,
    filters,
    can,
}: ScolaritesPageProps) {
    // Sections de l'établissement actif
    const sections = useMemo(
        () => currentEtablissement?.sections ?? [],
        [currentEtablissement]
    );

    // Filtres hiérarchiques en cascade : Section -> Cycle -> Niveau -> Série -> Classe
    const [selectedSectionId, setSelectedSectionId] = useState<string>(() =>
        sections.length === 1 ? String(sections[0].id) : 'all'
    );
    const [selectedCycleId, setSelectedCycleId] = useState<string>('all');
    const [selectedNiveauId, setSelectedNiveauId] = useState<string>('all');
    const [selectedSerie, setSelectedSerie] = useState<string>('all');
    const [selectedClasseId, setSelectedClasseId] = useState<string>(() =>
        filters?.classe_id ? String(filters.classe_id) : 'all'
    );

    // Recherche rapide par matricule / nom et filtre par statut financier
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [selectedStatut, setSelectedStatut] = useState<string>('all');

    // Filtres analytiques par Catégorie de Frais, Frais précis (Inscription, Tranche 1...) et Condition / Palier
    const [selectedCategorieFraisId, setSelectedCategorieFraisId] = useState<string>('all');
    const [selectedTypeFraisId, setSelectedTypeFraisId] = useState<string>('all');
    const [selectedModeFrais, setSelectedModeFrais] = useState<ModeFiltreFrais>('tous');

    // Modale du Dossier Financier Tout-en-Un (on garde l'ID pour toujours lire le compte à jour depuis `comptes`)
    const [openCompteId, setOpenCompteId] = useState<number | null>(() =>
        filters?.compte_id ? Number(filters.compte_id) : null
    );
    const [isModeleBourseModalOpen, setIsModeleBourseModalOpen] = useState(false);

    // Application en 1 clic d'un frais optionnel du niveau à toute une classe (ex: Inscription P CDE sur Première CDE)
    const [selectedBulkOptionId, setSelectedBulkOptionId] = useState<string>('');
    const [isApplyingBulkOption, setIsApplyingBulkOption] = useState<boolean>(false);

    // Droits granulaires Spatie Permission résolus pour l'utilisateur courant
    const canEtats = can.etats ?? can.view;
    const canAttribuerBourses = can.bourses_attribuer ?? can.bourses_manage;
    const canManageDispenses = can.dispenses_manage ?? can.manage;
    const canManageOptions = can.options_manage ?? can.manage;
    const canConfigureFrais = can.frais_configure ?? can.manage;

    /**
     * Réinitialise intelligemment la section lorsque l'établissement actif change.
     */
    useEffect(() => {
        if (sections.length === 1) {
            setSelectedSectionId(String(sections[0].id));
        } else {
            setSelectedSectionId('all');
        }
        setSelectedCycleId('all');
        setSelectedNiveauId('all');
        setSelectedSerie('all');
    }, [currentEtablissement?.id]);

    /**
     * Cycles disponibles selon la Section choisie.
     */
    const availableCycles = useMemo(() => {
        const targetSections =
            selectedSectionId === 'all'
                ? sections
                : sections.filter((s) => String(s.id) === selectedSectionId);

        return targetSections.flatMap((s) => s.cycles ?? []);
    }, [sections, selectedSectionId]);

    /**
     * Niveaux disponibles selon le Cycle (ou la Section) choisi.
     */
    const availableNiveaux = useMemo(() => {
        const targetCycles =
            selectedCycleId === 'all'
                ? availableCycles
                : availableCycles.filter((c) => String(c.id) === selectedCycleId);

        return targetCycles.flatMap((c) => c.niveaux ?? []);
    }, [availableCycles, selectedCycleId]);

    /**
     * Classes brutes correspondant au Niveau / Cycle / Section sélectionné (avant filtre de Série).
     */
    const classesBeforeSerie = useMemo(() => {
        const targetNiveaux =
            selectedNiveauId === 'all'
                ? availableNiveaux
                : availableNiveaux.filter((n) => String(n.id) === selectedNiveauId);

        return targetNiveaux.flatMap((n) =>
            (n.classes ?? []).map((cl) => ({
                ...cl,
                niveauNom: n.nom,
            }))
        );
    }, [availableNiveaux, selectedNiveauId]);

    /**
     * Séries distinctes détectées automatiquement parmi les classes du niveau/cycle choisi.
     */
    const availableSeries = useMemo(() => {
        const set = new Set<string>();
        classesBeforeSerie.forEach((cl) => {
            if (cl.serie && cl.serie.trim() !== '') {
                set.add(cl.serie.trim());
            }
        });
        return Array.from(set).sort();
    }, [classesBeforeSerie]);

    /**
     * Classes finales proposées dans le sélecteur après application éventuelle de la Série.
     */
    const availableClasses = useMemo(() => {
        if (selectedSerie === 'all') return classesBeforeSerie;
        return classesBeforeSerie.filter((cl) => (cl.serie ?? '').trim() === selectedSerie);
    }, [classesBeforeSerie, selectedSerie]);

    /**
     * Comptage rapide des élèves par classe pour enrichir le menu déroulant des classes.
     */
    const effectifsParClasse = useMemo(() => {
        const map = new Map<number, number>();
        comptes.forEach((c) => {
            const cid = c.inscription?.classe_id;
            if (cid) {
                map.set(cid, (map.get(cid) || 0) + 1);
            }
        });
        return map;
    }, [comptes]);

    /**
     * Rubriques de frais disponibles selon la catégorie éventuellement sélectionnée.
     */
    const availableTypesFrais = useMemo(() => {
        if (selectedCategorieFraisId === 'all') {
            return typesFrais;
        }
        const catIdNum = Number(selectedCategorieFraisId);
        return typesFrais.filter((tf) => Number(tf.categorie_frais_id) === catIdNum);
    }, [typesFrais, selectedCategorieFraisId]);

    /**
     * Évaluation par élève des montants attendus / payés / restants sur la catégorie ou le frais ciblé.
     */
    const evaluationsFraisParCompte = useMemo(() => {
        const hasTargetFilter =
            selectedCategorieFraisId !== 'all' || selectedTypeFraisId !== 'all';
        if (!hasTargetFilter) {
            return null;
        }

        const mapCategories = new Map<number, CategorieFrais>();
        categoriesFrais.forEach((c) => mapCategories.set(c.id, c));
        const cacheTarifs = new Map<string, FraisScolaire[]>();
        const catIdNum = selectedCategorieFraisId !== 'all' ? Number(selectedCategorieFraisId) : null;
        const typeIdNum = selectedTypeFraisId !== 'all' ? Number(selectedTypeFraisId) : null;

        const map = new Map<number, { match: boolean; attendu: number; paye: number; restant: number }>();
        comptes.forEach((compte) => {
            const lignes = allouerLignesCompteEleve(compte, tarifs, mapCategories, cacheTarifs);
            map.set(
                compte.id,
                evaluerCibleFraisEleve(lignes, {
                    categorieFraisId: catIdNum,
                    typeFraisId: typeIdNum,
                    modeFiltre: selectedModeFrais,
                })
            );
        });

        return map;
    }, [
        comptes,
        tarifs,
        categoriesFrais,
        selectedCategorieFraisId,
        selectedTypeFraisId,
        selectedModeFrais,
    ]);

    /**
     * Liste filtrée des comptes élèves selon la hiérarchie académique, la recherche, le statut et le frais ciblé.
     */
    const filteredComptes = useMemo(() => {
        const allowedClasseIds = new Set(availableClasses.map((cl) => Number(cl.id)));
        const hasHierarchyFilter =
            selectedSectionId !== 'all' ||
            selectedCycleId !== 'all' ||
            selectedNiveauId !== 'all' ||
            selectedSerie !== 'all';

        return comptes.filter((c) => {
            const inscClasse = c.inscription?.classe;
            const classeId = Number(c.inscription?.classe_id ?? inscClasse?.id ?? 0);

            if (selectedClasseId !== 'all') {
                if (classeId !== Number(selectedClasseId)) return false;
            } else if (hasHierarchyFilter) {
                if (!allowedClasseIds.has(classeId)) return false;
            }

            if (selectedStatut !== 'all' && c.statut_financier !== selectedStatut) {
                return false;
            }

            if (evaluationsFraisParCompte && !evaluationsFraisParCompte.get(c.id)?.match) {
                return false;
            }

            if (searchQuery.trim() !== '') {
                const normalize = (val: string) =>
                    val
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase();
                const tokens = normalize(searchQuery).trim().split(/\s+/).filter(Boolean);
                const haystack = normalize(
                    [
                        c.eleve?.nom,
                        c.eleve?.prenom,
                        c.eleve?.matricule,
                        c.eleve?.telephone_parent,
                    ]
                        .filter(Boolean)
                        .join(' '),
                );
                // Permet la recherche multi-mots insensible aux accents dans n'importe quel ordre ("Nom Prénom" ou "Prénom Nom")
                if (!tokens.every((token) => haystack.includes(token))) {
                    return false;
                }
            }

            return true;
        });
    }, [
        comptes,
        availableClasses,
        selectedSectionId,
        selectedCycleId,
        selectedNiveauId,
        selectedSerie,
        selectedClasseId,
        selectedStatut,
        evaluationsFraisParCompte,
        searchQuery,
    ]);

    /**
     * Synthèse financière calculée uniquement sur la catégorie ou le frais ciblé pour la sélection.
     */
    const statsCibleFrais = useMemo(() => {
        if (!evaluationsFraisParCompte) {
            return null;
        }
        let attendu = 0;
        let paye = 0;
        let restant = 0;
        filteredComptes.forEach((c) => {
            const ev = evaluationsFraisParCompte.get(c.id);
            if (ev?.match) {
                attendu += ev.attendu;
                paye += ev.paye;
                restant += ev.restant;
            }
        });
        const taux = attendu > 0 ? Math.min(100, Math.round((paye / attendu) * 100)) : 0;
        return { effectif: filteredComptes.length, attendu, paye, restant, taux };
    }, [evaluationsFraisParCompte, filteredComptes]);

    /**
     * Indicateurs financiers consolidés en temps réel sur la sélection affichée.
     */
    const statsSelection = useMemo(() => {
        let totalExigible = 0;
        let totalPaye = 0;
        let totalReste = 0;
        let nbSoldes = 0;

        filteredComptes.forEach((c) => {
            totalExigible += Number(c.montant_net_exigible || 0);
            totalPaye += Number(c.total_paye || 0);
            totalReste += Number(c.solde_restant || 0);
            if (c.statut_financier === 'solde' && Number(c.montant_net_exigible) > 0) {
                nbSoldes++;
            }
        });

        const tauxRecouvrement =
            totalExigible > 0 ? Math.min(100, Math.round((totalPaye / totalExigible) * 100)) : 0;

        return {
            effectif: filteredComptes.length,
            nbSoldes,
            totalExigible,
            totalPaye,
            totalReste,
            tauxRecouvrement,
        };
    }, [filteredComptes]);

    /**
     * Synthèse financière dynamique par Catégorie de Frais (Scolarité, Frais d'Examen, Fête, Services...)
     * recalculée en temps réel selon la classe, le niveau, le cycle ou l'établissement sélectionné.
     */
    const syntheseCategories = useMemo(() => {
        let niveauUniqueId: number | null = null;
        let serieUnique: string | null = selectedSerie !== 'all' ? selectedSerie : null;

        if (selectedClasseId !== 'all') {
            const cl = availableClasses.find((c) => String(c.id) === selectedClasseId);
            if (cl) {
                niveauUniqueId = Number(cl.niveau_id);
                serieUnique = cl.serie ?? serieUnique;
            }
        } else if (selectedNiveauId !== 'all') {
            niveauUniqueId = Number(selectedNiveauId);
        }

        return calculerSyntheseCategoriesGuichet(
            filteredComptes,
            tarifs,
            categoriesFrais,
            niveauUniqueId,
            serieUnique
        );
    }, [
        filteredComptes,
        tarifs,
        categoriesFrais,
        selectedClasseId,
        selectedNiveauId,
        selectedSerie,
        availableClasses,
    ]);

    /**
     * Compte actuellement ouvert dans la modale Dossier Financier Tout-en-Un.
     */
    const selectedCompte = useMemo(
        () => (openCompteId ? comptes.find((c) => c.id === openCompteId) ?? null : null),
        [comptes, openCompteId]
    );

    /**
     * Classe actuellement sélectionnée dans le filtre (si une classe précise est filtrée).
     */
    const activeClasseObj = useMemo(
        () =>
            selectedClasseId !== 'all'
                ? availableClasses.find((cl) => String(cl.id) === selectedClasseId) ?? null
                : null,
        [availableClasses, selectedClasseId]
    );

    /**
     * Frais optionnels configurés sur le niveau de la classe sélectionnée (ex: Inscription P CDE, Inscription P TI...).
     */
    const optionsDuNiveauDeLaClasse = useMemo(() => {
        if (!activeClasseObj) return [];
        const nivId = Number(activeClasseObj.niveau_id);
        return fraisOptionnels.filter(
            (fo) => !fo.niveau_id || Number(fo.niveau_id) === nivId
        );
    }, [activeClasseObj, fraisOptionnels]);

    /**
     * Synchronise l'option par défaut lorsque la classe sélectionnée change.
     */
    useEffect(() => {
        if (optionsDuNiveauDeLaClasse.length > 0) {
            setSelectedBulkOptionId(String(optionsDuNiveauDeLaClasse[0].id));
        } else {
            setSelectedBulkOptionId('');
        }
    }, [activeClasseObj?.id, optionsDuNiveauDeLaClasse]);

    /**
     * Applique un frais optionnel du niveau à tous les élèves de la classe sélectionnée en 1 clic.
     */
    const handleApplyOptionToClasse = () => {
        if (!activeClasseObj || !selectedBulkOptionId) return;

        setIsApplyingBulkOption(true);
        router.post(
            '/administration/finances/souscriptions-optionnelles/classe',
            {
                classe_id: activeClasseObj.id,
                frais_scolaire_id: Number(selectedBulkOptionId),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success(
                        `Frais optionnel appliqué avec succès aux élèves de la classe ${activeClasseObj.nom} !`,
                        {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        }
                    );
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Impossible d'appliquer ce frais optionnel à la classe.", {
                        position: 'top-right',
                    });
                },
                onFinish: () => setIsApplyingBulkOption(false),
            }
        );
    };

    /**
     * Changement d'établissement pour le Super-Administrateur.
     */
    const handleChangeEtablissement = (etabId: string) => {
        router.get(
            '/administration/finances/scolarites',
            { etablissement_id: etabId },
            { preserveScroll: true }
        );
    };

    /**
     * Réinitialise tous les filtres de sélection.
     */
    const handleResetFilters = () => {
        setSelectedSectionId(sections.length === 1 ? String(sections[0].id) : 'all');
        setSelectedCycleId('all');
        setSelectedNiveauId('all');
        setSelectedSerie('all');
        setSelectedClasseId('all');
        setSelectedStatut('all');
        setSelectedCategorieFraisId('all');
        setSelectedTypeFraisId('all');
        setSelectedModeFrais('tous');
        setSearchQuery('');
    };

    /**
     * Colonnes TanStack Table pour la liste des élèves de la classe / sélection.
     */
    const columns = useMemo<ColumnDef<CompteFinancierEleve>[]>(() => [
        {
            accessorKey: 'eleve.matricule',
            header: 'Matricule',
            cell: ({ row }) => (
                <span className="font-mono text-xs font-bold text-slate-700">
                    {row.original.eleve?.matricule}
                </span>
            ),
        },
        {
            id: 'eleve_identite',
            // Expose l'identité complète (ordre Nom Prénom, Prénom Nom, version désaccentuée, matricule et téléphone)
            // afin que le filtre global de TanStack Table retrouve l'élève quelle que soit la saisie effectuée.
            accessorFn: (row) => {
                const el = row.eleve;
                const base = [el?.nom, el?.prenom, el?.nom, el?.matricule, el?.telephone_parent]
                    .filter(Boolean)
                    .join(' ');
                const unaccented = base.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                return unaccented !== base ? `${base} ${unaccented}` : base;
            },
            header: 'Élève',
            cell: ({ row }) => {
                const el = row.original.eleve;
                return (
                    <div>
                        <div className="font-bold text-xs text-slate-900">
                            {el?.nom} {el?.prenom}
                        </div>
                        {el?.telephone_parent && (
                            <div className="text-[10px] text-slate-500">
                                Tél. parent : {el.telephone_parent}
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'classe_nom',
            // Permet également de filtrer directement via la barre de recherche du tableau sur la classe ou le niveau.
            accessorFn: (row) => {
                const cl = row.inscription?.classe;
                return [cl?.nom, cl?.niveau?.nom].filter(Boolean).join(' ');
            },
            header: 'Classe',
            cell: ({ row }) => {
                const cl = row.original.inscription?.classe;
                return (
                    <div className="flex flex-col">
                        <span className="text-xs font-semibold text-slate-800">
                            {cl?.nom ?? 'N/A'}
                        </span>
                        {cl?.niveau?.nom && (
                            <span className="text-[10px] text-slate-500">{cl.niveau.nom}</span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'montant_net_exigible',
            header: () => <div className="text-right">Net à Payer</div>,
            cell: ({ row }) => {
                const c = row.original;
                const dispense = Number(c.total_dispense || 0);
                const remise = Number(c.remise_bourse || 0);
                const nbOptions =
                    c.souscriptions_optionnelles?.filter((s) => s.statut === 'actif').length || 0;

                return (
                    <div className="text-right">
                        <div className="text-xs font-semibold text-slate-900 tabular-nums">
                            {Number(c.montant_net_exigible).toLocaleString('fr-FR')} FCFA
                        </div>
                        <div className="flex flex-wrap items-center justify-end gap-1.5 mt-0.5">
                            {dispense > 0 && (
                                <span
                                    className="text-[10px] font-semibold text-sky-600 flex items-center gap-0.5"
                                    title="Dispense financière de tranche(s)"
                                >
                                    <FileCheck2 className="w-2.5 h-2.5" /> -{dispense.toLocaleString('fr-FR')}
                                </span>
                            )}
                            {remise > 0 && (
                                <span
                                    className="text-[10px] font-semibold text-emerald-600 flex items-center gap-0.5"
                                    title="Bourse ou remise commerciale"
                                >
                                    <Award className="w-2.5 h-2.5" /> -{remise.toLocaleString('fr-FR')}
                                </span>
                            )}
                            {nbOptions > 0 && (
                                <span className="text-[10px] font-semibold text-amber-600 flex items-center gap-0.5">
                                    <PackagePlus className="w-2.5 h-2.5" /> +{nbOptions} annexe(s)
                                </span>
                            )}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'total_paye',
            header: () => <div className="text-right">Total Versé</div>,
            cell: ({ row }) => (
                <div className="text-right text-xs font-bold text-emerald-600 tabular-nums">
                    {Number(row.original.total_paye).toLocaleString('fr-FR')} FCFA
                </div>
            ),
        },
        {
            accessorKey: 'solde_restant',
            header: () => <div className="text-right">Reste à Payer</div>,
            cell: ({ row }) => {
                const solde = Number(row.original.solde_restant);
                return (
                    <div
                        className={`text-right text-xs font-extrabold tabular-nums ${
                            solde > 0 ? 'text-red-600' : 'text-emerald-600'
                        }`}
                    >
                        {solde.toLocaleString('fr-FR')} FCFA
                    </div>
                );
            },
        },
        {
            accessorKey: 'statut_financier',
            header: 'Statut',
            cell: ({ row }) => {
                const c = row.original;
                if (
                    Number(c.montant_net_exigible) === 0 &&
                    Number(c.total_paye) === 0 &&
                    Number(c.total_dispense || 0) === 0 &&
                    Number(c.remise_bourse || 0) === 0
                ) {
                    return (
                        <Badge variant="outline" className="text-[10px] text-amber-700 bg-amber-50 border-amber-200">
                            Tarif à configurer
                        </Badge>
                    );
                }
                switch (c.statut_financier) {
                    case 'solde':
                        return <Badge className="bg-emerald-600 text-white text-[10px]">Soldé</Badge>;
                    case 'en_regle_tranche_2':
                        return <Badge className="bg-blue-600 text-white text-[10px]">En Règle T2+</Badge>;
                    case 'en_regle_tranche_1':
                        return <Badge className="bg-sky-600 text-white text-[10px]">En Règle T1</Badge>;
                    case 'inscrit_a_jour':
                        return <Badge className="bg-teal-600 text-white text-[10px]">Inscrit (À jour)</Badge>;
                    case 'acompte':
                        return (
                            <Badge variant="outline" className="bg-amber-50 text-amber-800 border-amber-300 text-[10px]">
                                Acompte / Partiel
                            </Badge>
                        );
                    case 'en_attente':
                        return (
                            <Badge variant="outline" className="bg-slate-100 text-slate-700 border-slate-300 text-[10px]">
                                En attente
                            </Badge>
                        );
                    default:
                        return <Badge variant="destructive" className="text-[10px]">Insolvable</Badge>;
                }
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Dossier & Encaissement</div>,
            cell: ({ row }) => {
                const c = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setOpenCompteId(c.id)}
                            className="h-8 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1.5 shadow-sm"
                        >
                            <FolderOpen className="w-3.5 h-3.5 shrink-0" />
                            Ouvrir Dossier & Encaisser
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 w-8 p-0 text-slate-500 hover:text-blue-700"
                            title="Imprimer le relevé financier PDF de l'élève"
                            onClick={() =>
                                window.open(`/administration/finances/comptes/${c.id}/fiche-pdf`, '_blank')
                            }
                        >
                            <Printer className="w-4 h-4" />
                        </Button>
                    </div>
                );
            },
        },
    ], []);

    return (
        <>
            <Head title="Guichet Scolarité & Dossiers Élèves - SYGES" />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6 lg:p-8 min-w-0 w-full">
                {/* En-tête de la page dédiée */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 pb-4">
                    <div className="flex items-start sm:items-center gap-3 min-w-0">
                        <div className="p-2.5 bg-emerald-600 rounded-xl text-white shadow-sm shrink-0">
                            <CreditCard className="w-5 h-5" />
                        </div>
                        <div className="min-w-0">
                            <h1 className="text-xl sm:text-2xl font-black tracking-tight text-slate-900">
                                Guichet Scolarité & Dossiers Élèves
                            </h1>
                            <p className="text-xs text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5">
                                <span>
                                    Établissement :{' '}
                                    <strong className="text-slate-800">
                                        {currentEtablissement?.nom ?? 'Aucun'}
                                    </strong>
                                </span>
                                {currentAnnee && (
                                    <span>
                                        | Année Scolaire :{' '}
                                        <span className="font-bold text-emerald-600">{currentAnnee.code}</span>
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>

                    {canEtats && (
                        <div className="flex items-center gap-2">
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="h-9 text-xs font-semibold text-sky-800 border-sky-300 hover:bg-sky-50 flex items-center gap-1.5 shadow-2xs"
                            >
                                <Link
                                    href={`/administration/finances/etats?${new URLSearchParams({
                                        ...(selectedSectionId !== 'all' ? { section_id: selectedSectionId } : {}),
                                        ...(selectedCycleId !== 'all' ? { cycle_id: selectedCycleId } : {}),
                                        ...(selectedNiveauId !== 'all' ? { niveau_id: selectedNiveauId } : {}),
                                        ...(selectedClasseId !== 'all' ? { classe_id: selectedClasseId } : {}),
                                    }).toString()}`}
                                >
                                    <FileSpreadsheet className="w-4 h-4 text-sky-600" />
                                    <span>Centre des États & Rapports (PDF)</span>
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                {/* Barre de filtrage hiérarchique en entonnoir : Établissement -> Section -> Cycle -> Niveau -> Série -> Classe */}
                <Card className="border-slate-200 shadow-sm bg-linear-to-b from-white to-slate-50/60">
                    <CardContent className="p-4 space-y-3.5">
                        <div className="flex items-center justify-between gap-2">
                            <div className="flex items-center gap-2 text-xs font-bold text-slate-800">
                                <Filter className="w-4 h-4 text-emerald-600" />
                                <span>Sélection de la Classe ou Recherche Rapide d'un Élève</span>
                            </div>
                            <button
                                type="button"
                                onClick={handleResetFilters}
                                className="text-xs font-semibold text-slate-500 hover:text-emerald-600 flex items-center gap-1 cursor-pointer"
                            >
                                <RotateCcw className="w-3.5 h-3.5" /> Réinitialiser
                            </button>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
                            {/* 1. Établissement (affiché uniquement si l'utilisateur a accès à plusieurs campus) */}
                            {isSuperAdmin && etablissements.length > 1 && (
                                <div className="space-y-1 min-w-0">
                                    <Label className="text-[11px] font-semibold text-slate-600 flex items-center gap-1">
                                        <Building2 className="w-3 h-3 text-emerald-600" /> Établissement
                                    </Label>
                                    <Select
                                        value={currentEtablissement ? String(currentEtablissement.id) : ''}
                                        onValueChange={handleChangeEtablissement}
                                    >
                                        <SelectTrigger className="h-9 text-xs bg-white">
                                            <SelectValue placeholder="Choisir..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {etablissements.map((et) => (
                                                <SelectItem key={et.id} value={String(et.id)}>
                                                    {et.nom}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* 2. Section */}
                            <div className="space-y-1 min-w-0">
                                <Label className="text-[11px] font-semibold text-slate-600">Section</Label>
                                <Select
                                    value={selectedSectionId}
                                    onValueChange={(val) => {
                                        setSelectedSectionId(val);
                                        setSelectedCycleId('all');
                                        setSelectedNiveauId('all');
                                        setSelectedSerie('all');
                                        setSelectedClasseId('all');
                                    }}
                                >
                                    <SelectTrigger className="h-9 text-xs bg-white">
                                        <SelectValue placeholder="Toutes les sections" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Toutes les sections</SelectItem>
                                        {sections.map((sec) => (
                                            <SelectItem key={sec.id} value={String(sec.id)}>
                                                {sec.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* 3. Cycle */}
                            <div className="space-y-1 min-w-0">
                                <Label className="text-[11px] font-semibold text-slate-600">Cycle</Label>
                                <Select
                                    value={selectedCycleId}
                                    onValueChange={(val) => {
                                        setSelectedCycleId(val);
                                        setSelectedNiveauId('all');
                                        setSelectedSerie('all');
                                        setSelectedClasseId('all');
                                    }}
                                >
                                    <SelectTrigger className="h-9 text-xs bg-white">
                                        <SelectValue placeholder="Tous les cycles" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tous les cycles</SelectItem>
                                        {availableCycles.map((cyc) => (
                                            <SelectItem key={cyc.id} value={String(cyc.id)}>
                                                {cyc.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* 4. Niveau */}
                            <div className="space-y-1 min-w-0">
                                <Label className="text-[11px] font-semibold text-slate-600">Niveau</Label>
                                <Select
                                    value={selectedNiveauId}
                                    onValueChange={(val) => {
                                        setSelectedNiveauId(val);
                                        setSelectedSerie('all');
                                        setSelectedClasseId('all');
                                    }}
                                >
                                    <SelectTrigger className="h-9 text-xs bg-white">
                                        <SelectValue placeholder="Tous les niveaux" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tous les niveaux</SelectItem>
                                        {availableNiveaux.map((niv) => (
                                            <SelectItem key={niv.id} value={String(niv.id)}>
                                                {niv.nom}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* 5. Série (optionnel) */}
                            <div className="space-y-1 min-w-0">
                                <Label className="text-[11px] font-semibold text-slate-600">
                                    Série <span className="text-slate-400 font-normal">(optionnel)</span>
                                </Label>
                                <Select
                                    value={selectedSerie}
                                    onValueChange={(val) => {
                                        setSelectedSerie(val);
                                        setSelectedClasseId('all');
                                    }}
                                    disabled={availableSeries.length === 0}
                                >
                                    <SelectTrigger className="h-9 text-xs bg-white">
                                        <SelectValue placeholder="Toutes les séries" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Toutes les séries</SelectItem>
                                        {availableSeries.map((sr) => (
                                            <SelectItem key={sr} value={sr}>
                                                {sr}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* 6. Classe */}
                            <div className="space-y-1 min-w-0">
                                <Label className="text-[11px] font-bold text-emerald-700 flex items-center gap-1">
                                    <GraduationCap className="w-3.5 h-3.5" /> Classe
                                </Label>
                                <SearchableSelect
                                    options={[
                                        { value: 'all', label: `Toutes les classes (${availableClasses.length})` },
                                        ...availableClasses.map((cl) => ({
                                            value: String(cl.id),
                                            label: cl.nom,
                                            description: `${effectifsParClasse.get(cl.id) || 0} él.`,
                                        })),
                                    ]}
                                    value={selectedClasseId}
                                    onValueChange={(val) => setSelectedClasseId(val || 'all')}
                                    placeholder="Toutes les classes"
                                    searchPlaceholder="Rechercher une classe..."
                                    emptyMessage="Aucune classe trouvée."
                                    className="h-9 text-xs font-semibold bg-white border-emerald-300"
                                />
                            </div>
                        </div>

                        {/* Barre secondaire : Recherche directe d'un élève + Statut financier */}
                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-1">
                            <div className="relative flex-1">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                                <Input
                                    placeholder="Rechercher rapidement un élève par nom, prénom, matricule ou téléphone parent..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-9 h-9 text-xs bg-white"
                                />
                            </div>

                            <Select value={selectedStatut} onValueChange={setSelectedStatut}>
                                <SelectTrigger className="w-full sm:w-56 h-9 text-xs bg-white">
                                    <SelectValue placeholder="Tous les statuts financiers" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous les statuts financiers</SelectItem>
                                    <SelectItem value="insolvable">Insolvable (En retard)</SelectItem>
                                    <SelectItem value="en_attente">En attente (avant échéance)</SelectItem>
                                    <SelectItem value="acompte">Acompte / Partiel</SelectItem>
                                    <SelectItem value="inscrit_a_jour">Inscrit (À jour)</SelectItem>
                                    <SelectItem value="en_regle_tranche_1">En Règle Tranche 1</SelectItem>
                                    <SelectItem value="en_regle_tranche_2">En Règle Tranche 2+</SelectItem>
                                    <SelectItem value="solde">Scolarité Soldée</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Barre tertiaire : Filtrage analytique par Catégorie de frais, Frais précis & Palier atteint */}
                        <div className="pt-2 border-t border-slate-200/80 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 items-end">
                            <div className="space-y-1 min-w-0">
                                <Label className="text-[11px] font-semibold text-slate-600 flex items-center gap-1">
                                    <Tag className="w-3 h-3 text-sky-600" /> Catégorie de Frais
                                </Label>
                                <Select
                                    value={selectedCategorieFraisId}
                                    onValueChange={(val) => {
                                        setSelectedCategorieFraisId(val);
                                        setSelectedTypeFraisId('all');
                                    }}
                                >
                                    <SelectTrigger className="h-9 text-xs bg-white">
                                        <SelectValue placeholder="Toutes les catégories" />
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

                            <div className="space-y-1 min-w-0">
                                <Label className="text-[11px] font-semibold text-slate-600">
                                    Frais / Rubrique Précis
                                </Label>
                                <SearchableSelect
                                    options={[
                                        { value: 'all', label: 'Tous les frais' },
                                        ...availableTypesFrais.map((tf) => ({
                                            value: String(tf.id),
                                            label: tf.libelle,
                                        })),
                                    ]}
                                    value={selectedTypeFraisId}
                                    onValueChange={(val) => setSelectedTypeFraisId(val || 'all')}
                                    placeholder="Tous les frais"
                                    searchPlaceholder="Rechercher un frais..."
                                    emptyMessage="Aucun frais trouvé."
                                    className="h-9 text-xs font-normal bg-white"
                                />
                            </div>

                            <div className="space-y-1 min-w-0">
                                <Label className="text-[11px] font-semibold text-slate-600">
                                    Condition / Palier de Paiement
                                </Label>
                                <Select
                                    value={selectedModeFrais}
                                    onValueChange={(val) => setSelectedModeFrais(val as ModeFiltreFrais)}
                                    disabled={selectedCategorieFraisId === 'all' && selectedTypeFraisId === 'all'}
                                >
                                    <SelectTrigger className="h-9 text-xs bg-white">
                                        <SelectValue placeholder="Condition..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="tous">Tous les élèves concernés</SelectItem>
                                        <SelectItem value="uniquement_palier">
                                            Payé uniquement jusqu'à ce palier
                                        </SelectItem>
                                        <SelectItem value="solde">Ayant soldé ce frais</SelectItem>
                                        <SelectItem value="partiel">Paiement partiel (acompte)</SelectItem>
                                        <SelectItem value="non_paye">Non encore payé (0 FCFA)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                {statsCibleFrais ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            const params = new URLSearchParams();
                                            if (currentEtablissement?.id) {
                                                params.set('etablissement_id', String(currentEtablissement.id));
                                            }
                                            if (currentAnnee?.id) {
                                                params.set('annee_scolaire_id', String(currentAnnee.id));
                                            }
                                            if (selectedSectionId !== 'all') params.set('section_id', selectedSectionId);
                                            if (selectedCycleId !== 'all') params.set('cycle_id', selectedCycleId);
                                            if (selectedNiveauId !== 'all') params.set('niveau_id', selectedNiveauId);
                                            if (selectedClasseId !== 'all') params.set('classe_id', selectedClasseId);
                                            if (selectedCategorieFraisId !== 'all') {
                                                params.set('categorie_frais_id', selectedCategorieFraisId);
                                            }
                                            if (selectedTypeFraisId !== 'all') {
                                                params.set('type_frais_id', selectedTypeFraisId);
                                            }
                                            params.set('mode_filtre_frais', selectedModeFrais);
                                            window.open(
                                                `/administration/finances/etats/suivi-frais-pdf?${params.toString()}`,
                                                '_blank'
                                            );
                                        }}
                                        className="w-full h-9 text-xs font-semibold text-sky-800 border-sky-300 bg-sky-50 hover:bg-sky-100 flex items-center justify-center gap-1.5"
                                    >
                                        <Printer className="w-3.5 h-3.5" />
                                        Imprimer cette Liste (PDF)
                                    </Button>
                                ) : (
                                    <p className="text-[11px] text-slate-400 italic pb-2">
                                        Choisissez une catégorie ou un frais pour isoler le palier (ex : Inscription seule).
                                    </p>
                                )}
                            </div>
                        </div>

                        {statsCibleFrais && (
                            <div className="rounded-lg border border-sky-200 bg-sky-50/70 px-3 py-2 flex flex-wrap items-center justify-between gap-3 text-xs">
                                <span className="font-bold text-sky-900">
                                    Synthèse sur le frais / catégorie sélectionné ({statsCibleFrais.effectif} élève(s)) :
                                </span>
                                <div className="flex flex-wrap items-center gap-4 tabular-nums">
                                    <span>
                                        Attendu :{' '}
                                        <strong>{statsCibleFrais.attendu.toLocaleString('fr-FR')} F</strong>
                                    </span>
                                    <span className="text-emerald-700">
                                        Encaissé :{' '}
                                        <strong>{statsCibleFrais.paye.toLocaleString('fr-FR')} F</strong>
                                    </span>
                                    <span className="text-red-700">
                                        Reste :{' '}
                                        <strong>{statsCibleFrais.restant.toLocaleString('fr-FR')} F</strong>
                                    </span>
                                    <Badge variant="outline" className="bg-white text-[10px]">
                                        Recouvrement {statsCibleFrais.taux} %
                                    </Badge>
                                </div>
                            </div>
                        )}

                        {/* Barre d'affectation rapide d'un frais optionnel du niveau à toute la classe sélectionnée (ex: Première CDE vs Première TI) */}
                        {canManageOptions && activeClasseObj && optionsDuNiveauDeLaClasse.length > 0 && (
                            <div className="pt-2 border-t border-slate-200/80 flex flex-col lg:flex-row lg:items-center justify-between gap-2.5 text-xs">
                                <div className="flex items-center gap-2 text-amber-900">
                                    <PackagePlus className="w-4 h-4 text-amber-600 shrink-0" />
                                    <span>
                                        <strong>
                                            Frais annexes & optionnels du niveau ({activeClasseObj.niveauNom}) :
                                        </strong>{' '}
                                        Appliquer un frais optionnel (ex : Inscription spécifique à la série/classe) à
                                        tous les élèves de <strong>{activeClasseObj.nom}</strong> en 1 clic :
                                    </span>
                                </div>

                                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 min-w-0">
                                    <div className="w-full sm:w-72 min-w-0">
                                        <SearchableSelect
                                            options={optionsDuNiveauDeLaClasse.map((opt) => ({
                                                value: String(opt.id),
                                                label: opt.type_frais?.libelle || opt.libelle_tranche || 'Frais optionnel',
                                                description: `${Number(opt.montant).toLocaleString('fr-FR')} FCFA`,
                                            }))}
                                            value={selectedBulkOptionId}
                                            onValueChange={setSelectedBulkOptionId}
                                            placeholder="Choisir le frais optionnel..."
                                            searchPlaceholder="Rechercher un frais optionnel..."
                                            emptyMessage="Aucun frais optionnel trouvé."
                                            className="h-8 text-xs font-normal bg-white border-amber-300"
                                        />
                                    </div>

                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={isApplyingBulkOption || !selectedBulkOptionId}
                                        onClick={handleApplyOptionToClasse}
                                        className="h-8 text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shrink-0"
                                    >
                                        {isApplyingBulkOption
                                            ? 'Application...'
                                            : `Appliquer à toute la ${activeClasseObj.nom}`}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* 4 KPIs dynamiques sur la classe / sélection affichée */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <Card className="border-slate-200">
                        <CardContent className="p-3.5">
                            <div className="flex items-center justify-between text-xs font-semibold text-slate-500">
                                <span>Élèves Affichés</span>
                                <Users className="w-4 h-4 text-emerald-600" />
                            </div>
                            <div className="text-lg font-black text-slate-900 mt-1 tabular-nums">
                                {statsSelection.effectif} élève(s)
                            </div>
                            <span className="text-[11px] text-emerald-600 font-semibold">
                                dont {statsSelection.nbSoldes} soldé(s)
                            </span>
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardContent className="p-3.5">
                            <div className="flex items-center justify-between text-xs font-semibold text-slate-500">
                                <span>Total Exigible (Sélection)</span>
                                <Wallet className="w-4 h-4 text-blue-600" />
                            </div>
                            <div className="text-lg font-black text-slate-900 mt-1 tabular-nums">
                                {statsSelection.totalExigible.toLocaleString('fr-FR')} FCFA
                            </div>
                            <span className="text-[11px] text-slate-500">Montant net après remises</span>
                        </CardContent>
                    </Card>

                    <Card className="border-emerald-200 bg-emerald-50/40">
                        <CardContent className="p-3.5">
                            <div className="flex items-center justify-between text-xs font-semibold text-emerald-700">
                                <span>Total Encaissé</span>
                                <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                            </div>
                            <div className="text-lg font-black text-emerald-700 mt-1 tabular-nums">
                                {statsSelection.totalPaye.toLocaleString('fr-FR')} FCFA
                            </div>
                            <span className="text-[11px] font-semibold text-emerald-700">
                                Taux de recouvrement : {statsSelection.tauxRecouvrement}%
                            </span>
                        </CardContent>
                    </Card>

                    <Card className="border-red-200 bg-red-50/30">
                        <CardContent className="p-3.5">
                            <div className="flex items-center justify-between text-xs font-semibold text-red-700">
                                <span>Reste à Recouvrer</span>
                                <DollarSign className="w-4 h-4 text-red-600" />
                            </div>
                            <div className="text-lg font-black text-red-600 mt-1 tabular-nums">
                                {statsSelection.totalReste.toLocaleString('fr-FR')} FCFA
                            </div>
                            <span className="text-[11px] text-slate-500">Solde cumulé dû</span>
                        </CardContent>
                    </Card>
                </div>

                {/* Synthèse des montants globaux par Catégorie de Frais (Scolarité, Frais d'Examen, Fête...) */}
                {syntheseCategories.length > 0 && (
                    <Card className="border-slate-200 shadow-xs">
                        <CardContent className="p-3.5 sm:p-4 space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <Tag className="w-4 h-4 text-emerald-600 shrink-0" />
                                    <h3 className="text-xs font-bold text-slate-900">
                                        Montants Globaux par Catégorie de Frais (Sélection Courante)
                                    </h3>
                                </div>
                                <span className="text-[11px] text-slate-500">
                                    Détail consolidé sur {statsSelection.effectif} élève(s)
                                </span>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                                {syntheseCategories.map((cat) => (
                                    <div
                                        key={cat.key}
                                        className="p-3 rounded-lg border border-slate-200 bg-slate-50/60 flex flex-col justify-between gap-2"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <div className="text-xs font-bold text-slate-900 truncate">
                                                    {cat.nom}
                                                </div>
                                                {cat.tarifUnitaireObligatoire !== null &&
                                                    cat.tarifUnitaireObligatoire > 0 && (
                                                        <div className="text-[10px] font-semibold text-slate-500 tabular-nums">
                                                            Tarif :{' '}
                                                            {cat.tarifUnitaireObligatoire.toLocaleString('fr-FR')}{' '}
                                                            FCFA / élève
                                                        </div>
                                                    )}
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className="text-[10px] px-1.5 py-0 font-bold tabular-nums shrink-0"
                                            >
                                                {cat.tauxRecouvrement}%
                                            </Badge>
                                        </div>

                                        <div className="space-y-1">
                                            <div className="flex items-baseline justify-between gap-2">
                                                <span className="text-[11px] text-slate-500">Attendu :</span>
                                                <span className="text-xs font-black text-slate-900 tabular-nums">
                                                    {cat.attenduGlobal.toLocaleString('fr-FR')} FCFA
                                                </span>
                                            </div>
                                            <div className="flex items-baseline justify-between gap-2">
                                                <span className="text-[11px] text-emerald-700">
                                                    Encaissé :
                                                </span>
                                                <span className="text-xs font-bold text-emerald-700 tabular-nums">
                                                    {cat.encaisseGlobal.toLocaleString('fr-FR')} FCFA
                                                </span>
                                            </div>
                                            <div className="flex items-baseline justify-between gap-2">
                                                <span className="text-[11px] text-slate-500">Reste :</span>
                                                <span
                                                    className={`text-xs font-bold tabular-nums ${
                                                        cat.resteGlobal > 0
                                                            ? 'text-red-600'
                                                            : 'text-emerald-600'
                                                    }`}
                                                >
                                                    {cat.resteGlobal.toLocaleString('fr-FR')} FCFA
                                                </span>
                                            </div>
                                        </div>

                                        <div className="w-full h-1.5 rounded-full bg-slate-200 overflow-hidden">
                                            <div
                                                className="h-full bg-emerald-600 transition-all"
                                                style={{ width: `${cat.tauxRecouvrement}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Tableau principal TanStack Table des élèves */}
                <DataTable
                    columns={columns}
                    data={filteredComptes}
                    searchPlaceholder="Rechercher dans le tableau par nom, prénom, matricule ou classe..."
                    showColumnVisibilityToggle
                />
            </div>

            {/* Modale Tout-en-Un du Dossier Financier Élève */}
            <DossierFinancierModal
                isOpen={Boolean(selectedCompte)}
                onClose={() => setOpenCompteId(null)}
                compte={selectedCompte}
                boursesRemises={boursesRemises}
                fraisOptionnels={fraisOptionnels}
                estCaisseCloturee={estCaisseCloturee}
                canEncaisser={can.versements_create}
                canModifierVersement={Boolean(can.versements_edit || can.manage)}
                canManageBourses={canAttribuerBourses}
                canManageDispenses={canManageDispenses}
                canManageOptions={canManageOptions}
                canConfigureFrais={canConfigureFrais}
                canManageArrieres={can.manage}
                onOpenCreateModeleBourse={
                    can.bourses_manage ? () => setIsModeleBourseModalOpen(true) : undefined
                }
            />

            {/* Modale rapide de création d'un nouveau modèle de bourse / remise sans quitter la page */}
            {can.bourses_manage && (
                <ModeleBourseModal
                    isOpen={isModeleBourseModalOpen}
                    onClose={() => setIsModeleBourseModalOpen(false)}
                    etablissementId={currentEtablissement?.id ?? 0}
                    anneeId={currentAnnee?.id}
                    categories={categoriesBourses}
                />
            )}
        </>
    );
}

/**
 * Fil d'Ariane pour la page Guichet Scolarité & Dossiers Élèves.
 */
ScolaritesPage.layout = {
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
            title: 'Guichet Scolarité & Dossiers Élèves',
            href: '/administration/finances/scolarites',
        },
    ],
};


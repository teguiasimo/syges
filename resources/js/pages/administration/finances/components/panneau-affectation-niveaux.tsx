import React, { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    CheckSquare,
    ChevronDown,
    ChevronRight,
    Layers,
    Plus,
    Search,
    SlidersHorizontal,
    Square,
    Tag,
    X,
} from 'lucide-react';
import { toast } from 'sonner';
import { formatDate } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CategorieFrais, NiveauFinanceRef, TypeFrais } from '@/types/finance';

/**
 * Propriétés du panneau pleine largeur d'affectation des Frais du Catalogue aux Niveaux pédagogiques.
 */
interface PanneauAffectationNiveauxProps {
    anneeId: number;
    etablissementId: number;
    typesFrais: TypeFrais[];
    categoriesFrais: CategorieFrais[];
    niveaux: NiveauFinanceRef[];
    preselectedTypeFrais?: TypeFrais | null;
    onOpenCreateTypeFrais: () => void;
    onClosePanel: () => void;
}

/**
 * Groupe de frais rattachés à une même catégorie pour un affichage aéré et repliable.
 */
interface GroupeFraisParCategorie {
    cle: string;
    nom: string;
    description: string | null;
    frais: TypeFrais[];
    montantCumule: number;
}

/**
 * Espace de travail pleine largeur en 2 colonnes côte à côte pour rattacher les Frais du Catalogue
 * aux Niveaux pédagogiques (`02. Grille par Niveau`).
 *
 * Conçu pour rester parfaitement lisible et aéré même lorsqu'un complexe scolaire gère
 * des dizaines de frais (Maternelle, Primaire, Secondaire, Examens, Services) et une vingtaine de niveaux :
 * - Colonne gauche : recherche instantanée, filtres et accordéons clairs par Catégorie de Frais.
 * - Colonne droite : recherche de niveau, cartes spacieuses par Section/Cycle avec coche en 1 clic
 *   et barre de contrôle affichant le montant cumulé par élève avant validation.
 *
 * @param {PanneauAffectationNiveauxProps} props Propriétés du composant
 * @returns {JSX.Element} Panneau pleine largeur d'affectation multi-niveaux
 */
export default function PanneauAffectationNiveaux({
    anneeId,
    etablissementId,
    typesFrais,
    categoriesFrais,
    niveaux,
    preselectedTypeFrais = null,
    onOpenCreateTypeFrais,
    onClosePanel,
}: PanneauAffectationNiveauxProps) {
    const [searchFrais, setSearchFrais] = useState<string>('');
    const [selectedCategorieFilter, setSelectedCategorieFilter] = useState<string>('all');
    const [collapsedCategories, setCollapsedCategories] = useState<Record<string, boolean>>({});

    const [searchNiveau, setSearchNiveau] = useState<string>('');
    const [selectedCycleFilter, setSelectedCycleFilter] = useState<string>('all');

    const [selectedTypeFraisIds, setSelectedTypeFraisIds] = useState<number[]>([]);
    const [selectedNiveauIds, setSelectedNiveauIds] = useState<number[]>([]);
    const [showSerieInput, setShowSerieInput] = useState<boolean>(false);
    const [serie, setSerie] = useState<string>('');
    const [isObligatoire, setIsObligatoire] = useState<boolean>(true);
    const [submitting, setSubmitting] = useState<boolean>(false);

    /**
     * Initialise la pré-sélection lorsqu'un frais spécifique est ciblé depuis l'onglet Catalogue.
     */
    useEffect(() => {
        if (preselectedTypeFrais) {
            setSelectedTypeFraisIds([preselectedTypeFrais.id]);
            const dejaRattaches = (preselectedTypeFrais.frais_scolaires ?? []).map((fs) =>
                Number(fs.niveau_id)
            );
            setSelectedNiveauIds(Array.from(new Set(dejaRattaches)));
        }
    }, [preselectedTypeFrais]);

    /**
     * Frais du catalogue filtrés par texte de recherche et catégorie.
     */
    const filteredTypesFrais = useMemo(() => {
        const terme = searchFrais.trim().toLowerCase();

        return typesFrais.filter((tf) => {
            if (
                selectedCategorieFilter !== 'all' &&
                String(tf.categorie_frais_id ?? 'none') !== selectedCategorieFilter
            ) {
                return false;
            }

            if (terme !== '') {
                const libelle = tf.libelle.toLowerCase();
                const catNom = (tf.categorie_frais?.nom ?? '').toLowerCase();
                const desc = (tf.description ?? '').toLowerCase();
                return libelle.includes(terme) || catNom.includes(terme) || desc.includes(terme);
            }

            return true;
        });
    }, [typesFrais, searchFrais, selectedCategorieFilter]);

    /**
     * Regroupe les frais filtrés par Catégorie de Frais pour une lecture structurée et aérée.
     */
    const groupesFraisParCategorie = useMemo<GroupeFraisParCategorie[]>(() => {
        const map = new Map<string, GroupeFraisParCategorie>();

        categoriesFrais.forEach((cat) => {
            map.set(String(cat.id), {
                cle: String(cat.id),
                nom: cat.nom,
                description: cat.description,
                frais: [],
                montantCumule: 0,
            });
        });

        filteredTypesFrais.forEach((tf) => {
            const cle = tf.categorie_frais_id ? String(tf.categorie_frais_id) : 'none';
            if (!map.has(cle)) {
                map.set(cle, {
                    cle,
                    nom: tf.categorie_frais?.nom ?? 'Autres Frais (Sans catégorie)',
                    description: tf.categorie_frais?.description ?? null,
                    frais: [],
                    montantCumule: 0,
                });
            }
            const grp = map.get(cle)!;
            grp.frais.push(tf);
            grp.montantCumule += Number(tf.montant_defaut || 0);
        });

        return Array.from(map.values()).filter((g) => g.frais.length > 0);
    }, [filteredTypesFrais, categoriesFrais]);

    /**
     * Regroupe l'ensemble des niveaux par Section & Cycle pédagogique.
     */
    const tousLesGroupesNiveaux = useMemo(() => {
        const map = new Map<string, { titre: string; niveaux: NiveauFinanceRef[] }>();

        niveaux.forEach((niv) => {
            const cycleNom = niv.cycle?.nom ?? 'Niveaux pédagogiques';
            const sectionNom = niv.cycle?.section?.nom;
            const cle = niv.cycle?.id ? `cycle_${niv.cycle.id}` : 'default';
            const titre = sectionNom ? `${sectionNom} • ${cycleNom}` : cycleNom;

            if (!map.has(cle)) {
                map.set(cle, { titre, niveaux: [] });
            }
            map.get(cle)!.niveaux.push(niv);
        });

        return Array.from(map.entries()).map(([cle, val]) => ({
            cle,
            titre: val.titre,
            niveaux: val.niveaux,
        }));
    }, [niveaux]);

    /**
     * Groupes de niveaux filtrés par cycle et par recherche rapide.
     */
    const groupesNiveauxAffiches = useMemo(() => {
        const terme = searchNiveau.trim().toLowerCase();

        return tousLesGroupesNiveaux
            .filter((g) => selectedCycleFilter === 'all' || g.cle === selectedCycleFilter)
            .map((g) => ({
                ...g,
                niveaux:
                    terme === ''
                        ? g.niveaux
                        : g.niveaux.filter(
                              (n) =>
                                  n.nom.toLowerCase().includes(terme) ||
                                  (n.code ?? '').toLowerCase().includes(terme) ||
                                  g.titre.toLowerCase().includes(terme)
                          ),
            }))
            .filter((g) => g.niveaux.length > 0);
    }, [tousLesGroupesNiveaux, selectedCycleFilter, searchNiveau]);

    /**
     * Comptabilise pour chaque niveau combien des frais actuellement cochés lui sont déjà rattachés.
     */
    const compteurDejaLieParNiveau = useMemo(() => {
        const map = new Map<number, number>();
        if (selectedTypeFraisIds.length === 0) return map;

        typesFrais
            .filter((tf) => selectedTypeFraisIds.includes(tf.id))
            .forEach((tf) => {
                const niveauxUniquesFrais = new Set<number>(
                    (tf.frais_scolaires ?? []).map((fs) => Number(fs.niveau_id))
                );
                niveauxUniquesFrais.forEach((nivId) => {
                    map.set(nivId, (map.get(nivId) ?? 0) + 1);
                });
            });

        return map;
    }, [selectedTypeFraisIds, typesFrais]);

    /**
     * Montant unitaire cumulé (par élève) des frais actuellement cochés dans la colonne de gauche.
     */
    const montantUnitaireSelectionne = useMemo(() => {
        return typesFrais
            .filter((tf) => selectedTypeFraisIds.includes(tf.id))
            .reduce((acc, tf) => acc + Number(tf.montant_defaut || 0), 0);
    }, [typesFrais, selectedTypeFraisIds]);

    /**
     * Bascule l'ouverture / fermeture visuelle d'un bloc de catégorie.
     */
    const toggleCollapseCategorie = (cle: string) => {
        setCollapsedCategories((prev) => ({
            ...prev,
            [cle]: !prev[cle],
        }));
    };

    /**
     * Coche ou décoche un frais individuel du catalogue.
     */
    const toggleTypeFrais = (id: number) => {
        setSelectedTypeFraisIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
        );
    };

    /**
     * Coche ou décoche simultanément tous les frais d'une liste donnée (ex: toute une catégorie).
     */
    const toggleGroupeFrais = (liste: TypeFrais[]) => {
        const ids = liste.map((f) => f.id);
        const tousCoches = ids.length > 0 && ids.every((id) => selectedTypeFraisIds.includes(id));

        if (tousCoches) {
            setSelectedTypeFraisIds((prev) => prev.filter((id) => !ids.includes(id)));
        } else {
            setSelectedTypeFraisIds((prev) => Array.from(new Set([...prev, ...ids])));
        }
    };

    /**
     * Coche ou décoche un niveau pédagogique individuel.
     */
    const toggleNiveau = (id: number) => {
        setSelectedNiveauIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
        );
    };

    /**
     * Coche ou décoche tous les niveaux d'un cycle ou d'une sélection affichée.
     */
    const toggleListeNiveaux = (liste: NiveauFinanceRef[]) => {
        const ids = liste.map((n) => n.id);
        const tousCoches = ids.length > 0 && ids.every((id) => selectedNiveauIds.includes(id));

        if (tousCoches) {
            setSelectedNiveauIds((prev) => prev.filter((id) => !ids.includes(id)));
        } else {
            setSelectedNiveauIds((prev) => Array.from(new Set([...prev, ...ids])));
        }
    };

    /**
     * Soumet l'affectation groupée multi-frais × multi-niveaux au serveur.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (selectedTypeFraisIds.length === 0 || selectedNiveauIds.length === 0) {
            toast.error('Veuillez cocher au moins un frais à gauche et au moins un niveau à droite.', {
                position: 'top-right',
            });
            return;
        }

        setSubmitting(true);
        router.post(
            '/administration/finances/tarifs/affecter-niveaux',
            {
                annee_scolaire_id: anneeId,
                etablissement_id: etablissementId,
                type_frais_ids: selectedTypeFraisIds,
                niveau_ids: selectedNiveauIds,
                serie: showSerieInput && serie.trim() !== '' ? serie.trim() : null,
                obligatoire: isObligatoire,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        `${selectedTypeFraisIds.length} frais appliqué(s) avec succès sur ${selectedNiveauIds.length} niveau(x).`,
                        {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        }
                    );
                    onClosePanel();
                },
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    toast.error(first || 'Erreur lors du rattachement aux niveaux.', {
                        position: 'top-right',
                    });
                },
                onFinish: () => setSubmitting(false),
            }
        );
    };

    const totalCombinaisons = selectedTypeFraisIds.length * selectedNiveauIds.length;
    const niveauxVisibles = useMemo(
        () => groupesNiveauxAffiches.flatMap((g) => g.niveaux),
        [groupesNiveauxAffiches]
    );

    return (
        <form onSubmit={handleSubmit} className="space-y-5">
            {/* Barre supérieure d'orientation et de retour */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-white dark:bg-slate-900 shadow-xs">
                <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-600 text-white text-xs font-black">
                            ⇄
                        </span>
                        <h4 className="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">
                            Espace d'Affectation Multi-Niveaux
                        </h4>
                    </div>
                    <p className="text-xs text-slate-500">
                        Sélectionnez à gauche les frais configurés au Catalogue, puis cochez à droite les niveaux ou cycles entiers.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2 shrink-0">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onOpenCreateTypeFrais}
                        className="h-9 text-xs font-semibold border-emerald-300 text-emerald-700 hover:bg-emerald-50"
                    >
                        <Plus className="w-3.5 h-3.5 mr-1" /> Nouveau Frais au Catalogue
                    </Button>
                </div>
            </div>

            {/* Disposition Pleine Largeur en 2 Grandes Colonnes Côte à Côte */}
            <div className="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                {/* ================================================================= */}
                {/* COLONNE GAUCHE (7/12) : CATALOGUE DES FRAIS GROUPÉS PAR CATÉGORIE */}
                {/* ================================================================= */}
                <Card className="xl:col-span-7 border-slate-200 dark:border-slate-800 shadow-xs">
                    <CardContent className="p-4 sm:p-5 space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <span className="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center font-bold shrink-0">
                                    1
                                </span>
                                <div>
                                    <h5 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                                        Choisir les Frais du Catalogue
                                    </h5>
                                    <p className="text-[11px] text-slate-500">
                                        {selectedTypeFraisIds.length} frais coché(s) sur {typesFrais.length} disponibles
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                {filteredTypesFrais.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => toggleGroupeFrais(filteredTypesFrais)}
                                        className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:underline cursor-pointer"
                                    >
                                        {filteredTypesFrais.every((f) => selectedTypeFraisIds.includes(f.id))
                                            ? 'Décocher les frais affichés'
                                            : `Tout cocher (${filteredTypesFrais.length})`}
                                    </button>
                                )}
                                {selectedTypeFraisIds.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setSelectedTypeFraisIds([])}
                                        className="text-xs text-slate-500 hover:text-red-600 cursor-pointer"
                                    >
                                        Réinitialiser
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Barre de recherche & filtres rapides des frais */}
                        <div className="space-y-3">
                            <div className="flex flex-col sm:flex-row gap-2.5">
                                <div className="relative flex-1">
                                    <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                    <Input
                                        placeholder="Rechercher un frais (ex: Inscription, Tranche 1, CEPE, Bus...)"
                                        value={searchFrais}
                                        onChange={(e) => setSearchFrais(e.target.value)}
                                        className="pl-9 pr-8 h-9 text-xs"
                                    />
                                    {searchFrais && (
                                        <button
                                            type="button"
                                            onClick={() => setSearchFrais('')}
                                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                                        >
                                            <X className="w-3.5 h-3.5" />
                                        </button>
                                    )}
                                </div>
                            </div>

                            {/* Filtre par Catégorie de Frais */}
                            {categoriesFrais.length > 0 && (
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedCategorieFilter('all')}
                                        className={`px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-colors cursor-pointer ${
                                            selectedCategorieFilter === 'all'
                                                ? 'bg-slate-900 text-white border-slate-900 dark:bg-slate-100 dark:text-slate-900'
                                                : 'bg-white dark:bg-slate-900 text-slate-600 border-slate-200 dark:border-slate-700 hover:bg-slate-50'
                                        }`}
                                    >
                                        Toutes les catégories ({typesFrais.length})
                                    </button>
                                    {categoriesFrais.map((cat) => {
                                        const count = typesFrais.filter(
                                            (t) => Number(t.categorie_frais_id) === cat.id
                                        ).length;
                                        if (count === 0) return null;
                                        const isActive = selectedCategorieFilter === String(cat.id);
                                        return (
                                            <button
                                                key={cat.id}
                                                type="button"
                                                onClick={() =>
                                                    setSelectedCategorieFilter(
                                                        isActive ? 'all' : String(cat.id)
                                                    )
                                                }
                                                className={`px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-colors cursor-pointer ${
                                                    isActive
                                                        ? 'bg-emerald-600 text-white border-emerald-600'
                                                        : 'bg-white dark:bg-slate-900 text-slate-600 border-slate-200 dark:border-slate-700 hover:border-emerald-300'
                                                }`}
                                            >
                                                {cat.nom} ({count})
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Liste aérée des frais groupés par Catégorie */}
                        {groupesFraisParCategorie.length === 0 ? (
                            <div className="p-6 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-2">
                                <p className="text-xs text-slate-500">
                                    Aucun frais du catalogue ne correspond aux filtres sélectionnés.
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={onOpenCreateTypeFrais}
                                    className="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    <Plus className="w-3.5 h-3.5 mr-1" /> Créer un nouveau frais
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {groupesFraisParCategorie.map((groupe) => {
                                    const isCollapsed = Boolean(collapsedCategories[groupe.cle]);
                                    const nbCochesDansGroupe = groupe.frais.filter((f) =>
                                        selectedTypeFraisIds.includes(f.id)
                                    ).length;
                                    const toutGroupeCoche =
                                        groupe.frais.length > 0 &&
                                        nbCochesDansGroupe === groupe.frais.length;

                                    return (
                                        <div
                                            key={groupe.cle}
                                            className="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-white dark:bg-slate-900"
                                        >
                                            {/* En-tête de la Catégorie */}
                                            <div className="px-4 py-2.5 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200/80 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => toggleCollapseCategorie(groupe.cle)}
                                                    className="flex items-center gap-2 text-left min-w-0 cursor-pointer"
                                                >
                                                    {isCollapsed ? (
                                                        <ChevronRight className="w-4 h-4 text-slate-500 shrink-0" />
                                                    ) : (
                                                        <ChevronDown className="w-4 h-4 text-slate-500 shrink-0" />
                                                    )}
                                                    <Tag className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                                    <span className="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
                                                        {groupe.nom}
                                                    </span>
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px] font-semibold px-1.5 py-0"
                                                    >
                                                        {nbCochesDansGroupe}/{groupe.frais.length}
                                                    </Badge>
                                                </button>

                                                <div className="flex items-center gap-3">
                                                    <span className="text-[11px] font-semibold text-slate-500 tabular-nums hidden sm:inline">
                                                        Cumul catégorie :{' '}
                                                        <strong className="text-slate-800 dark:text-slate-200">
                                                            {groupe.montantCumule.toLocaleString('fr-FR')} FCFA
                                                        </strong>
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleGroupeFrais(groupe.frais)}
                                                        className="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 hover:underline cursor-pointer"
                                                    >
                                                        {toutGroupeCoche
                                                            ? 'Décocher la catégorie'
                                                            : 'Cocher la catégorie'}
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Lignes spacieuses des frais de cette catégorie */}
                                            {!isCollapsed && (
                                                <div className="divide-y divide-slate-100 dark:divide-slate-800/80">
                                                    {groupe.frais.map((tf) => {
                                                        const checked = selectedTypeFraisIds.includes(
                                                            tf.id
                                                        );
                                                        const nbNivDejaLies = new Set(
                                                            (tf.frais_scolaires ?? []).map((fs) =>
                                                                Number(fs.niveau_id)
                                                            )
                                                        ).size;

                                                        return (
                                                            <div
                                                                key={tf.id}
                                                                onClick={() => toggleTypeFrais(tf.id)}
                                                                role="button"
                                                                tabIndex={0}
                                                                onKeyDown={(e) => {
                                                                    if (
                                                                        e.key === 'Enter' ||
                                                                        e.key === ' '
                                                                    ) {
                                                                        e.preventDefault();
                                                                        toggleTypeFrais(tf.id);
                                                                    }
                                                                }}
                                                                className={`px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 transition-colors cursor-pointer ${
                                                                    checked
                                                                        ? 'bg-emerald-50/70 dark:bg-emerald-950/30'
                                                                        : 'hover:bg-slate-50/80 dark:hover:bg-slate-800/30'
                                                                }`}
                                                            >
                                                                <div className="flex items-start gap-3 min-w-0">
                                                                    {checked ? (
                                                                        <CheckSquare className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                                                                    ) : (
                                                                        <Square className="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                                                                    )}
                                                                    <div className="min-w-0 space-y-0.5">
                                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                                            <span className="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
                                                                                {tf.libelle}
                                                                            </span>
                                                                            <Badge
                                                                                variant="outline"
                                                                                className="text-[10px] px-1.5 py-0 font-bold tabular-nums"
                                                                                title="Ordre de priorité de prélèvement"
                                                                            >
                                                                                Priorité #{tf.ordre_priorite ?? 10}
                                                                            </Badge>
                                                                        </div>

                                                                        {(tf.description ||
                                                                            tf.date_limite_defaut) && (
                                                                            <div className="text-[11px] text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                                                                {tf.date_limite_defaut && (
                                                                                    <span className="inline-flex items-center gap-1 font-medium text-slate-600 dark:text-slate-400">
                                                                                        <Calendar className="w-3 h-3 text-slate-400" />
                                                                                        Échéance :{' '}
                                                                                        {formatDate(
                                                                                            tf.date_limite_defaut
                                                                                        )}
                                                                                    </span>
                                                                                )}
                                                                                {tf.description && (
                                                                                    <span>{tf.description}</span>
                                                                                )}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>

                                                                <div className="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-1.5 pl-7 sm:pl-0 shrink-0">
                                                                    <span className="text-xs sm:text-sm font-black text-emerald-700 dark:text-emerald-400 tabular-nums">
                                                                        {Number(
                                                                            tf.montant_defaut || 0
                                                                        ).toLocaleString('fr-FR')}{' '}
                                                                        FCFA
                                                                    </span>
                                                                    <span
                                                                        className={`text-[10px] px-2 py-0.5 rounded-full font-semibold ${
                                                                            nbNivDejaLies > 0
                                                                                ? 'bg-emerald-100/80 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                                                        }`}
                                                                    >
                                                                        {nbNivDejaLies > 0
                                                                            ? `Déjà sur ${nbNivDejaLies} niv.`
                                                                            : 'Non rattaché'}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ================================================================= */}
                {/* COLONNE DROITE (5/12) : ARBORESCENCE DES NIVEAUX & VALIDATION     */}
                {/* ================================================================= */}
                <Card className="xl:col-span-5 xl:sticky xl:top-4 border-slate-200 dark:border-slate-800 shadow-xs">
                    <CardContent className="p-4 sm:p-5 space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <span className="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center font-bold shrink-0">
                                    2
                                </span>
                                <div>
                                    <h5 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                                        Cocher les Niveaux Cibles
                                    </h5>
                                    <p className="text-[11px] text-slate-500">
                                        {selectedNiveauIds.length} niveau(x) coché(s) sur {niveaux.length}
                                    </p>
                                </div>
                            </div>

                            {niveauxVisibles.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => toggleListeNiveaux(niveauxVisibles)}
                                    className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:underline cursor-pointer"
                                >
                                    {niveauxVisibles.every((n) => selectedNiveauIds.includes(n.id))
                                        ? 'Tout décocher'
                                        : 'Tout cocher'}
                                </button>
                            )}
                        </div>

                        {/* Recherche rapide de niveau + filtre par Cycle */}
                        <div className="space-y-2.5">
                            <div className="relative">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                <Input
                                    placeholder="Filtrer un niveau ou cycle (ex: SIL, CM2, Primaire, 6ème...)"
                                    value={searchNiveau}
                                    onChange={(e) => setSearchNiveau(e.target.value)}
                                    className="pl-9 pr-8 h-9 text-xs"
                                />
                                {searchNiveau && (
                                    <button
                                        type="button"
                                        onClick={() => setSearchNiveau('')}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                                    >
                                        <X className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>

                            {tousLesGroupesNiveaux.length > 1 && (
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedCycleFilter('all')}
                                        className={`px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-colors cursor-pointer ${
                                            selectedCycleFilter === 'all'
                                                ? 'bg-slate-900 text-white border-slate-900 dark:bg-slate-100 dark:text-slate-900'
                                                : 'bg-white dark:bg-slate-900 text-slate-600 border-slate-200 dark:border-slate-700 hover:bg-slate-50'
                                        }`}
                                    >
                                        Tous les cycles ({niveaux.length})
                                    </button>
                                    {tousLesGroupesNiveaux.map((grp) => {
                                        const isActif = selectedCycleFilter === grp.cle;
                                        return (
                                            <button
                                                key={grp.cle}
                                                type="button"
                                                onClick={() =>
                                                    setSelectedCycleFilter(isActif ? 'all' : grp.cle)
                                                }
                                                className={`px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-colors cursor-pointer ${
                                                    isActif
                                                        ? 'bg-emerald-600 text-white border-emerald-600'
                                                        : 'bg-white dark:bg-slate-900 text-slate-600 border-slate-200 dark:border-slate-700 hover:border-emerald-300'
                                                }`}
                                            >
                                                {grp.titre} ({grp.niveaux.length})
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Cartes spacieuses des Cycles & Tuiles de Niveaux */}
                        <div className="space-y-3.5">
                            {groupesNiveauxAffiches.map((groupe) => {
                                const nbCochesCycle = groupe.niveaux.filter((n) =>
                                    selectedNiveauIds.includes(n.id)
                                ).length;
                                const allCycleSelected =
                                    groupe.niveaux.length > 0 &&
                                    nbCochesCycle === groupe.niveaux.length;

                                return (
                                    <div
                                        key={groupe.cle}
                                        className="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/50 space-y-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <Layers className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                                <span className="text-xs font-bold text-slate-800 dark:text-slate-100">
                                                    {groupe.titre}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className="text-[10px] px-1.5 py-0 tabular-nums"
                                                >
                                                    {nbCochesCycle}/{groupe.niveaux.length}
                                                </Badge>
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() => toggleListeNiveaux(groupe.niveaux)}
                                                className="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 hover:underline cursor-pointer"
                                            >
                                                {allCycleSelected
                                                    ? 'Décocher ce cycle'
                                                    : `Tout cocher (${groupe.niveaux.length})`}
                                            </button>
                                        </div>

                                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            {groupe.niveaux.map((niv) => {
                                                const checked = selectedNiveauIds.includes(niv.id);
                                                const nbDejaLies =
                                                    compteurDejaLieParNiveau.get(niv.id) ?? 0;

                                                return (
                                                    <button
                                                        key={niv.id}
                                                        type="button"
                                                        onClick={() => toggleNiveau(niv.id)}
                                                        className={`p-2.5 rounded-lg text-left border transition-all flex items-start gap-2 cursor-pointer ${
                                                            checked
                                                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                                                : 'bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:border-emerald-400'
                                                        }`}
                                                    >
                                                        {checked ? (
                                                            <CheckSquare className="w-4 h-4 shrink-0 mt-0.5" />
                                                        ) : (
                                                            <Square className="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                                                        )}
                                                        <div className="min-w-0 flex-1">
                                                            <div className="text-xs font-bold leading-tight">
                                                                {niv.nom}
                                                            </div>
                                                            {nbDejaLies > 0 && (
                                                                <div
                                                                    className={`text-[10px] mt-0.5 font-medium ${
                                                                        checked
                                                                            ? 'text-emerald-100'
                                                                            : 'text-emerald-600 dark:text-emerald-400'
                                                                    }`}
                                                                >
                                                                    {selectedTypeFraisIds.length === 1
                                                                        ? 'Déjà appliqué'
                                                                        : `${nbDejaLies}/${selectedTypeFraisIds.length} déjà lié(s)`}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Option avancée repliable : Série spécifique (Second Cycle) */}
                        <div className="pt-2 border-t border-slate-200 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowSerieInput((prev) => !prev)}
                                className="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-emerald-700 flex items-center gap-1.5 cursor-pointer"
                            >
                                <SlidersHorizontal className="w-3.5 h-3.5" />
                                <span>
                                    {showSerieInput
                                        ? 'Masquer la restriction par série'
                                        : 'Option Second Cycle : restreindre à une série (ex: A4, C, D)'}
                                </span>
                            </button>

                            {showSerieInput && (
                                <div className="mt-2 space-y-1">
                                    <Label className="text-[11px] text-slate-500">
                                        Code série (laisser vide pour appliquer à toutes les séries du niveau)
                                    </Label>
                                    <Input
                                        placeholder="Ex: C, D, A4"
                                        value={serie}
                                        onChange={(e) => setSerie(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>
                            )}
                        </div>

                        {/* Bloc Récapitulatif en Direct & Bouton d'Application */}
                        <div className="p-3.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/70 space-y-3">
                            <div className="flex items-center justify-between gap-2 text-xs">
                                <span className="text-slate-600 dark:text-slate-300 font-medium">
                                    Cumul des frais cochés :
                                </span>
                                <span className="text-sm font-black text-emerald-700 dark:text-emerald-400 tabular-nums">
                                    {montantUnitaireSelectionne.toLocaleString('fr-FR')} FCFA / élève
                                </span>
                            </div>

                            {/* Caractère d'exigibilité pour ce rattachement */}
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 border-t border-emerald-200/80 dark:border-emerald-800/80 text-xs">
                                <span className="font-semibold text-slate-700 dark:text-slate-200">
                                    Caractère d'exigibilité :
                                </span>
                                <div className="inline-flex rounded-lg border border-slate-300 dark:border-slate-700 p-0.5 bg-white dark:bg-slate-900">
                                    <button
                                        type="button"
                                        onClick={() => setIsObligatoire(true)}
                                        className={`px-2.5 py-1 rounded-md text-[11px] font-bold transition-colors cursor-pointer ${
                                            isObligatoire
                                                ? 'bg-emerald-600 text-white shadow-2xs'
                                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                                        }`}
                                    >
                                        Obligatoire (tous)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setIsObligatoire(false)}
                                        className={`px-2.5 py-1 rounded-md text-[11px] font-bold transition-colors cursor-pointer ${
                                            !isObligatoire
                                                ? 'bg-sky-600 text-white shadow-2xs'
                                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                                        }`}
                                    >
                                        Optionnel (souscription)
                                    </button>
                                </div>
                            </div>

                            <div className="text-[11px] text-slate-600 dark:text-slate-400">
                                {totalCombinaisons > 0 ? (
                                    <span>
                                        Opération prête :{' '}
                                        <strong className="text-slate-900 dark:text-slate-100">
                                            {selectedTypeFraisIds.length} frais ×{' '}
                                            {selectedNiveauIds.length} niveau(x) = {totalCombinaisons}{' '}
                                            rattachement(s)
                                        </strong>
                                    </span>
                                ) : (
                                    <span>
                                        Cochez au moins 1 frais à gauche et 1 niveau à droite pour activer
                                        l'enregistrement.
                                    </span>
                                )}
                            </div>

                            <Button
                                type="submit"
                                disabled={submitting || totalCombinaisons === 0}
                                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-10 font-bold flex items-center justify-center gap-2 shadow-sm"
                            >
                                <CheckCircle2 className="w-4 h-4 shrink-0" />
                                {submitting
                                    ? 'Application et calcul des comptes en cours...'
                                    : totalCombinaisons > 0
                                      ? `Appliquer ces ${selectedTypeFraisIds.length} frais aux ${selectedNiveauIds.length} niveau(x)`
                                      : 'Sélectionnez les frais et les niveaux'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </form>
    );
}


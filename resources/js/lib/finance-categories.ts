import type {
    CategorieFrais,
    CompteFinancierEleve,
    FraisScolaire,
} from '@/types/finance';

/**
 * Synthèse consolidée d'une Catégorie de Frais dans la Grille par Niveau (`Finances & Tarifs`).
 */
export interface SyntheseCategorieGrille {
    /** Clé unique de regroupement */
    key: string;
    /** Identifiant de la catégorie (null si non catégorisé) */
    categorieId: number | null;
    /** Libellé de la catégorie (ex: Scolarité, Frais d'Examen, Fête) */
    nom: string;
    /** Description éventuelle de la catégorie */
    description: string | null;
    /** Cumul des frais obligatoires dans la sélection courante */
    montantObligatoire: number;
    /** Cumul des frais optionnels dans la sélection courante */
    montantOptionnel: number;
    /** Nombre de lignes d'affectation rattachées */
    nbLignes: number;
    /** Nombre de niveaux distincts couverts */
    nbNiveaux: number;
}

/**
 * Synthèse financière temps réel d'une Catégorie de Frais au Guichet (`Guichet & Scolarités`).
 */
export interface SyntheseCategorieGuichet {
    /** Clé unique de regroupement */
    key: string;
    /** Identifiant de la catégorie (null si hors catégorie) */
    categorieId: number | null;
    /** Libellé de la catégorie (ex: Scolarité, Frais d'Examen, Fête) */
    nom: string;
    /** Description éventuelle */
    description: string | null;
    /** Montant unitaire obligatoire par élève (renseigné lorsqu'un niveau ou une classe unique est ciblé) */
    tarifUnitaireObligatoire: number | null;
    /** Montant global net attendu sur l'ensemble des élèves filtrés */
    attenduGlobal: number;
    /** Montant global déjà encaissé sur cette catégorie pour les élèves filtrés */
    encaisseGlobal: number;
    /** Reste global à recouvrer sur cette catégorie */
    resteGlobal: number;
    /** Taux de recouvrement en pourcentage (0 à 100) */
    tauxRecouvrement: number;
    /** Nombre d'élèves concernés par au moins un frais actif dans cette catégorie */
    nbElevesConcernes: number;
}

/**
 * Résout le nom et l'identifiant de catégorie d'une ligne `FraisScolaire`.
 *
 * @param {FraisScolaire | undefined | null} frais Ligne de tarif scolaire
 * @param {Map<number, CategorieFrais>} mapCategories Dictionnaire des catégories par ID
 * @param {string} fallback Libellé de repli si aucune catégorie n'est associée
 * @returns {{ id: number | null; nom: string; description: string | null }}
 */
function resoudreCategorieFrais(
    frais: FraisScolaire | undefined | null,
    mapCategories: Map<number, CategorieFrais>,
    fallback = 'Autres Frais'
): { id: number | null; nom: string; description: string | null } {
    const tf = frais?.type_frais;
    const catDirecte = tf?.categorie_frais;
    if (catDirecte) {
        return {
            id: catDirecte.id,
            nom: catDirecte.nom,
            description: catDirecte.description ?? null,
        };
    }

    if (tf?.categorie_frais_id && mapCategories.has(tf.categorie_frais_id)) {
        const cat = mapCategories.get(tf.categorie_frais_id)!;
        return {
            id: cat.id,
            nom: cat.nom,
            description: cat.description ?? null,
        };
    }

    return {
        id: null,
        nom: fallback,
        description: null,
    };
}

/**
 * Retourne les tarifs obligatoires applicables à un niveau et une série donnée,
 * triés selon l'ordre exact de priorité de l'établissement (miroir de `FinanceService::obtenirTarifsObligatoiresApplicables`).
 *
 * @param {FraisScolaire[]} tarifs Ensemble des tarifs de l'année active
 * @param {number | null | undefined} niveauId Identifiant du niveau de l'élève
 * @param {string | null | undefined} serie Série éventuelle de la classe
 * @returns {FraisScolaire[]} Liste ordonnée des frais obligatoires applicables
 */
export function obtenirTarifsObligatoiresNiveau(
    tarifs: FraisScolaire[],
    niveauId?: number | null,
    serie?: string | null
): FraisScolaire[] {
    if (!niveauId) return [];

    const serieNorm = serie?.trim() ? serie.trim().toUpperCase() : null;

    const compatibles = tarifs.filter((f) => {
        if (Number(f.niveau_id) !== Number(niveauId)) return false;
        if (!f.type_frais?.obligatoire) return false;
        const fSerie = f.serie?.trim() ? f.serie.trim().toUpperCase() : null;
        if (!fSerie) return true;
        return serieNorm !== null && fSerie === serieNorm;
    });

    // Déduplication par (type_frais_id, tranche_numero) en privilégiant la série spécifique
    const groupes = new Map<string, FraisScolaire>();
    compatibles.forEach((f) => {
        const cle = `${f.type_frais_id}_${f.tranche_numero}`;
        const existant = groupes.get(cle);
        if (!existant) {
            groupes.set(cle, f);
            return;
        }
        const fEstSpecifique = Boolean(f.serie?.trim()) && f.serie!.trim().toUpperCase() === serieNorm;
        if (fEstSpecifique) {
            groupes.set(cle, f);
        }
    });

    return Array.from(groupes.values()).sort((a, b) => {
        const prioA = Number(a.ordre_priorite ?? a.type_frais?.ordre_priorite ?? 10);
        const prioB = Number(b.ordre_priorite ?? b.type_frais?.ordre_priorite ?? 10);
        if (prioA !== prioB) return prioA - prioB;
        if (Number(a.tranche_numero) !== Number(b.tranche_numero)) {
            return Number(a.tranche_numero) - Number(b.tranche_numero);
        }
        return Number(a.id) - Number(b.id);
    });
}

/**
 * Calcule la synthèse par Catégorie de Frais pour l'onglet `02. Grille par Niveau`.
 *
 * @param {FraisScolaire[]} tarifs Liste des lignes de tarifs (filtrées ou globales)
 * @param {CategorieFrais[]} categoriesFrais Catégories configurées pour l'établissement
 * @returns {SyntheseCategorieGrille[]} Cartes de synthèse par catégorie
 */
export function calculerSyntheseCategoriesGrille(
    tarifs: FraisScolaire[],
    categoriesFrais: CategorieFrais[]
): SyntheseCategorieGrille[] {
    const mapCategories = new Map<number, CategorieFrais>();
    categoriesFrais.forEach((c) => mapCategories.set(c.id, c));

    const accumulateur = new Map<
        string,
        SyntheseCategorieGrille & { niveauxSet: Set<number> }
    >();

    // Pré-initialiser les catégories actives pour un affichage stable
    categoriesFrais.forEach((cat) => {
        const key = `cat_${cat.id}`;
        accumulateur.set(key, {
            key,
            categorieId: cat.id,
            nom: cat.nom,
            description: cat.description ?? null,
            montantObligatoire: 0,
            montantOptionnel: 0,
            nbLignes: 0,
            nbNiveaux: 0,
            niveauxSet: new Set<number>(),
        });
    });

    tarifs.forEach((frais) => {
        const resolu = resoudreCategorieFrais(
            frais,
            mapCategories,
            frais.type_frais?.obligatoire ? 'Scolarité' : 'Transport & Services Annexes'
        );
        const key = resolu.id !== null ? `cat_${resolu.id}` : `nom_${resolu.nom}`;

        if (!accumulateur.has(key)) {
            accumulateur.set(key, {
                key,
                categorieId: resolu.id,
                nom: resolu.nom,
                description: resolu.description,
                montantObligatoire: 0,
                montantOptionnel: 0,
                nbLignes: 0,
                nbNiveaux: 0,
                niveauxSet: new Set<number>(),
            });
        }

        const item = accumulateur.get(key)!;
        const montant = Number(frais.montant || 0);
        if (frais.type_frais?.obligatoire) {
            item.montantObligatoire += montant;
        } else {
            item.montantOptionnel += montant;
        }
        item.nbLignes += 1;
        if (frais.niveau_id) {
            item.niveauxSet.add(Number(frais.niveau_id));
        }
    });

    return Array.from(accumulateur.values())
        .filter((item) => item.nbLignes > 0)
        .map(({ niveauxSet, ...rest }) => ({
            ...rest,
            nbNiveaux: niveauxSet.size,
        }));
}

/**
 * Calcule en temps réel les montants globaux (Attendu, Encaissé, Reste) par Catégorie de Frais
 * pour la sélection courante d'élèves dans `Guichet & Scolarités` (classe, niveau, cycle ou établissement).
 *
 * Prend fidèlement en compte :
 * - Les dispenses financières par tranche,
 * - Les bourses et remises individuelles,
 * - Les services optionnels souscrits (Bus, Cantine...),
 * - Les ventilations ciblées au guichet ainsi que la cascade automatique par ordre de priorité.
 *
 * @param {CompteFinancierEleve[]} comptes Liste filtrée des comptes élèves affichés
 * @param {FraisScolaire[]} tarifs Grille tarifaire complète de l'année active
 * @param {CategorieFrais[]} categoriesFrais Catégories de frais de l'établissement
 * @param {number | null} niveauUniqueId ID du niveau lorsqu'un seul niveau ou une seule classe est sélectionné
 * @param {string | null} serieUnique Série éventuelle de la classe ou du filtre sélectionné
 * @returns {SyntheseCategorieGuichet[]} Liste des totaux consolidés par catégorie
 */
export function calculerSyntheseCategoriesGuichet(
    comptes: CompteFinancierEleve[],
    tarifs: FraisScolaire[],
    categoriesFrais: CategorieFrais[],
    niveauUniqueId: number | null = null,
    serieUnique: string | null = null
): SyntheseCategorieGuichet[] {
    const mapCategories = new Map<number, CategorieFrais>();
    categoriesFrais.forEach((c) => mapCategories.set(c.id, c));

    const accumulateur = new Map<
        string,
        {
            key: string;
            categorieId: number | null;
            nom: string;
            description: string | null;
            tarifUnitaireObligatoire: number | null;
            attenduGlobal: number;
            encaisseGlobal: number;
            elevesSet: Set<number>;
        }
    >();

    const ensureEntry = (id: number | null, nom: string, description: string | null = null) => {
        const key = id !== null ? `cat_${id}` : `nom_${nom}`;
        if (!accumulateur.has(key)) {
            accumulateur.set(key, {
                key,
                categorieId: id,
                nom,
                description,
                tarifUnitaireObligatoire: null,
                attenduGlobal: 0,
                encaisseGlobal: 0,
                elevesSet: new Set<number>(),
            });
        }
        return accumulateur.get(key)!;
    };

    categoriesFrais.forEach((cat) => {
        ensureEntry(cat.id, cat.nom, cat.description ?? null);
    });

    // Si un seul niveau (ou une seule classe) est filtré, calculer le tarif unitaire obligatoire de référence par catégorie
    if (niveauUniqueId) {
        const tarifsNiveau = obtenirTarifsObligatoiresNiveau(tarifs, niveauUniqueId, serieUnique);
        tarifsNiveau.forEach((f) => {
            const cat = resoudreCategorieFrais(f, mapCategories, 'Scolarité');
            const entry = ensureEntry(cat.id, cat.nom, cat.description);
            entry.tarifUnitaireObligatoire = (entry.tarifUnitaireObligatoire ?? 0) + Number(f.montant || 0);
        });
    }

    // Cache des tarifs obligatoires par couple (niveau_id, serie)
    const cacheTarifsParCle = new Map<string, FraisScolaire[]>();

    comptes.forEach((compte) => {
        const classe = compte.inscription?.classe;
        const niveauId = classe?.niveau_id ?? null;
        const serie = classe?.serie ?? null;
        const cleCache = `${niveauId ?? 0}::${serie?.trim().toUpperCase() ?? ''}`;

        if (!cacheTarifsParCle.has(cleCache)) {
            cacheTarifsParCle.set(cleCache, obtenirTarifsObligatoiresNiveau(tarifs, niveauId, serie));
        }
        const fraisObligatoires = cacheTarifsParCle.get(cleCache)!;

        // 1. Dispenses par frais_scolaire_id
        const dispensesMap = new Map<number, number>();
        (compte.dispenses_financieres ?? []).forEach((d) => {
            dispensesMap.set(Number(d.frais_scolaire_id), Number(d.montant_dispense || 0));
        });

        const montantsApresDispense = fraisObligatoires.map((f) => {
            const brut = Number(f.montant || 0);
            const disp = Math.min(brut, dispensesMap.get(Number(f.id)) ?? 0);
            return Math.max(0, brut - disp);
        });

        // 2. Déduction de la remise totale sur les tranches obligatoires (en partant des tranches ciblées / dernière tranche)
        let remiseRestante = Math.max(0, Number(compte.remise_bourse || 0));
        const montantsNetsObligatoires = [...montantsApresDispense];

        // Imputation prioritaire des attributions ciblant un frais_scolaire_id précis
        (compte.bourses_remises ?? []).forEach((attr) => {
            if (remiseRestante <= 0 || !attr.frais_scolaire_id) return;
            const idx = fraisObligatoires.findIndex((f) => Number(f.id) === Number(attr.frais_scolaire_id));
            if (idx >= 0) {
                const ded = Math.min(remiseRestante, Number(attr.montant_calcule || 0), montantsNetsObligatoires[idx]);
                montantsNetsObligatoires[idx] -= ded;
                remiseRestante -= ded;
            }
        });

        // Reliquat de remise imputé de la dernière tranche vers la première
        for (let i = montantsNetsObligatoires.length - 1; i >= 0 && remiseRestante > 0; i--) {
            const ded = Math.min(remiseRestante, montantsNetsObligatoires[i]);
            montantsNetsObligatoires[i] -= ded;
            remiseRestante -= ded;
        }

        // Construction des lignes ordonnées du dossier élève pour ventiler exactement ses paiements
        interface LigneInterne {
            fraisScolaireId: number | null;
            entryKey: { id: number | null; nom: string; description: string | null };
            attendu: number;
            paye: number;
        }

        const lignesEleve: LigneInterne[] = [];

        const arrieres = Number(compte.arrieres_anterieurs || 0);
        if (arrieres > 0) {
            lignesEleve.push({
                fraisScolaireId: null,
                entryKey: { id: null, nom: 'Arriérés Antérieurs', description: null },
                attendu: arrieres,
                paye: 0,
            });
        }

        fraisObligatoires.forEach((f, idx) => {
            const attendu = montantsNetsObligatoires[idx];
            if (attendu <= 0) return;
            lignesEleve.push({
                fraisScolaireId: Number(f.id),
                entryKey: resoudreCategorieFrais(f, mapCategories, 'Scolarité'),
                attendu,
                paye: 0,
            });
        });

        (compte.souscriptions_optionnelles ?? []).forEach((sous) => {
            if (sous.statut !== 'actif') return;
            const attendu = Number(sous.montant || 0);
            if (attendu <= 0) return;
            lignesEleve.push({
                fraisScolaireId: sous.frais_scolaire_id ? Number(sous.frais_scolaire_id) : null,
                entryKey: resoudreCategorieFrais(
                    sous.frais_scolaire,
                    mapCategories,
                    'Transport & Services Annexes'
                ),
                attendu,
                paye: 0,
            });
        });

        const totalPenalites = (compte.penalites ?? [])
            .filter((p) => p.statut !== 'annule')
            .reduce((acc, p) => acc + Number(p.montant || 0), 0);
        if (totalPenalites > 0) {
            lignesEleve.push({
                fraisScolaireId: null,
                entryKey: { id: null, nom: 'Pénalités de Retard', description: null },
                attendu: totalPenalites,
                paye: 0,
            });
        }

        // 3. Répartition des versements valides en 2 passes (ciblée puis cascade par priorité)
        let budgetPaye = Math.max(0, Number(compte.total_paye || 0));

        (compte.versements ?? []).forEach((vers) => {
            if (vers.statut !== 'valide') return;
            (vers.ventilations ?? []).forEach((vent) => {
                if (!vent.frais_scolaire_id || budgetPaye <= 0) return;
                const cible = lignesEleve.find(
                    (l) => l.fraisScolaireId === Number(vent.frais_scolaire_id) && l.attendu > l.paye
                );
                if (cible) {
                    const affecte = Math.min(
                        budgetPaye,
                        Number(vent.montant || 0),
                        cible.attendu - cible.paye
                    );
                    cible.paye += affecte;
                    budgetPaye -= affecte;
                }
            });
        });

        for (const ligne of lignesEleve) {
            if (budgetPaye <= 0) break;
            const manque = ligne.attendu - ligne.paye;
            if (manque <= 0) continue;
            const affecte = Math.min(budgetPaye, manque);
            ligne.paye += affecte;
            budgetPaye -= affecte;
        }

        // 4. Consolidation dans l'accumulateur par Catégorie
        lignesEleve.forEach((ligne) => {
            const entry = ensureEntry(
                ligne.entryKey.id,
                ligne.entryKey.nom,
                ligne.entryKey.description
            );
            entry.attenduGlobal += ligne.attendu;
            entry.encaisseGlobal += ligne.paye;
            entry.elevesSet.add(Number(compte.id));
        });
    });

    return Array.from(accumulateur.values())
        .filter((item) => item.attenduGlobal > 0 || (item.tarifUnitaireObligatoire ?? 0) > 0)
        .map((item) => {
            const resteGlobal = Math.max(0, item.attenduGlobal - item.encaisseGlobal);
            const tauxRecouvrement =
                item.attenduGlobal > 0
                    ? Math.min(100, Math.round((item.encaisseGlobal / item.attenduGlobal) * 100))
                    : 0;
            return {
                key: item.key,
                categorieId: item.categorieId,
                nom: item.nom,
                description: item.description,
                tarifUnitaireObligatoire: item.tarifUnitaireObligatoire,
                attenduGlobal: item.attenduGlobal,
                encaisseGlobal: item.encaisseGlobal,
                resteGlobal,
                tauxRecouvrement,
                nbElevesConcernes: item.elevesSet.size,
            };
        });
}


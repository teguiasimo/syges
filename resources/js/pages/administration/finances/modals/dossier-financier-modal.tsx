import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    BaseCalculRemise,
    BourseRemise,
    CibleImputationRemise,
    CompteFinancierEleve,
    FormatImpressionRecu,
    FraisScolaire,
    ModePaiement,
    buildReceiptPrintUrl,
    formatBaseCalculRemiseLabel,
    formatCibleImputationLabel,
    getPreferredReceiptFormat,
    setPreferredReceiptFormat,
} from '@/types/finance';
import { formatDate, formatDateForInput } from '@/lib/utils';
import {
    AlertTriangle,
    Award,
    Bus,
    Calendar,
    CheckCircle2,
    CreditCard,
    DollarSign,
    ExternalLink,
    FileCheck2,
    History,
    Layers,
    Pencil,
    Plus,
    Printer,
    Receipt,
    SlidersHorizontal,
    Sparkles,
    Trash2,
    Wallet,
    X,
} from 'lucide-react';
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';

/**
 * Propriétés de la modale « Dossier Financier Tout-en-Un » d'un élève.
 */
interface DossierFinancierModalProps {
    isOpen: boolean;
    onClose: () => void;
    compte: CompteFinancierEleve | null;
    boursesRemises?: BourseRemise[];
    fraisOptionnels?: FraisScolaire[];
    estCaisseCloturee?: boolean;
    canEncaisser?: boolean;
    canModifierVersement?: boolean;
    canManageBourses?: boolean;
    canManageDispenses?: boolean;
    canManageOptions?: boolean;
    canConfigureFrais?: boolean;
    canManageArrieres?: boolean;
    onOpenCreateModeleBourse?: () => void;
    onNouveauVersement?: (compte: CompteFinancierEleve) => void;
}

/**
 * Structure d'une ligne d'échéancier retournée par l'API dossier élève.
 */
interface LigneEcheancier {
    type: 'arrieres' | 'obligatoire' | 'optionnel' | 'penalite';
    id?: number | null;
    type_frais_id?: number | null;
    souscription_id?: number;
    penalite_id?: number;
    ordre_priorite?: number;
    tranche_numero?: number;
    libelle: string;
    categorie: string;
    montant_brut?: number;
    dispense_appliquee?: number;
    dispense_id?: number | null;
    dispense_motif?: string | null;
    remise_appliquee?: number;
    montant: number;
    date_limite: string | null;
    date_limite_iso?: string | null;
    est_echu: boolean;
    montant_paye: number;
    montant_restant: number;
    statut: 'dispense' | 'solde' | 'en_retard' | 'a_choir';
}

/**
 * Structure d'une bourse ou remise attribuée à l'élève.
 */
interface BourseAttribueeItem {
    id: number;
    bourse_remise_id: number;
    nom: string;
    categorie: string;
    type: 'bourse' | 'remise';
    mode_calcul?: 'pourcentage' | 'montant_fixe';
    valeur?: number;
    base_calcul?: BaseCalculRemise | string;
    montant_calcule: number;
    cible_imputation?: CibleImputationRemise | string | null;
    frais_scolaire_id?: number | null;
    frais_cible_libelle?: string | null;
    motif: string | null;
    date_attribution: string | null;
}

/**
 * Structure d'une dispense financière accordée sur une tranche obligatoire.
 */
interface DispenseAttribueeItem {
    id: number;
    frais_scolaire_id: number;
    libelle_tranche: string;
    type_dispense: 'totale' | 'partielle';
    montant_brut_tranche: number;
    montant_dispense: number;
    motif: string;
    date_dispense: string | null;
}

/**
 * Structure d'une option active souscrite par l'élève.
 */
interface SouscriptionActiveItem {
    id: number;
    frais_scolaire_id: number;
    libelle: string;
    montant: number;
    date_souscription: string | null;
}

/**
 * Structure d'un frais optionnel configuré pour le niveau de l'élève,
 * permettant de cocher en 1 clic si ce frais s'applique à l'élève avant l'encaissement.
 */
interface FraisOptionnelNiveauItem {
    id: number;
    frais_scolaire_id?: number;
    type_frais_id?: number | null;
    libelle: string;
    categorie: string;
    montant: number;
    montant_standard?: number;
    date_limite: string | null;
    serie?: string | null;
    est_souscrit: boolean;
    souscription_id: number | null;
    montant_souscrit: number;
    montant_paye: number;
}

/**
 * Structure d'un reçu dans l'historique des versements de l'élève.
 */
interface VersementHistoriqueItem {
    id: number;
    reference_recu: string;
    date_versement: string | null;
    montant_total: number;
    mode_paiement: string;
    reference_paiement: string | null;
    payeur_nom: string | null;
    payeur_telephone?: string | null;
    observations?: string | null;
    recu_par: string | null;
    statut: 'valide' | 'annule';
    motif_annulation: string | null;
    ventilations: {
        id: number;
        type_frais_id?: number | null;
        frais_scolaire_id?: number | null;
        libelle: string;
        montant: number;
    }[];
}

/**
 * Réponse JSON complète du endpoint `/administration/finances/comptes/{compte}/dossier`.
 */
interface DossierData {
    niveau_nom?: string | null;
    nb_tarifs_niveau?: number;
    can_edit_versements?: boolean;
    lignes: LigneEcheancier[];
    bourses_attribuees?: BourseAttribueeItem[];
    dispenses_attribuees?: DispenseAttribueeItem[];
    souscriptions_actives?: SouscriptionActiveItem[];
    frais_optionnels_niveau?: FraisOptionnelNiveauItem[];
    versements?: VersementHistoriqueItem[];
    total_exigible: number;
    total_dispense?: number;
    total_paye: number;
    solde_restant: number;
    remise_bourse?: number;
    arrieres_anterieurs?: number;
    montant_echu_en_retard: number;
    statut_financier: string;
    date_min_versement?: string | null;
    date_limite_rentree?: string | null;
    remise_anticipee_modele?: {
        id: number;
        nom: string;
        valeur: number;
        mode_calcul: string;
        base_calcul?: BaseCalculRemise | string;
        cible_imputation?: CibleImputationRemise | string;
        montant_estime: number;
        deja_attribuee: boolean;
    } | null;
}

/**
 * Motifs fréquents proposés en 1 clic lors de l'octroi d'une dispense de tranche.
 */
const MOTIFS_DISPENSE_RAPIDES = [
    'Arrivée au 2ème trimestre',
    'Arrivée au 3ème trimestre',
    'Exonération fratrie / personnel',
    'Décision spéciale de la Direction',
];

/**
 * Construit une clé unique et stable pour identifier une ligne d'échéancier dans l'interface.
 *
 * @param {LigneEcheancier} ligne Ligne de l'échéancier
 * @param {number} fallbackIndex Index de secours
 * @returns {string} Clé unique de la ligne
 */
function getLigneUniqueKey(ligne: LigneEcheancier, fallbackIndex = 0): string {
    return `${ligne.type}-${ligne.id ?? ligne.souscription_id ?? ligne.penalite_id ?? fallbackIndex}`;
}

/**
 * Modale « Dossier Financier Tout-en-Un » d'un élève.
 *
 * Centralise dans une seule fenêtre fluide :
 * 1. L'échéancier trié selon l'ordre de priorité de l'établissement + les dispenses par tranche
 *    + l'encaissement direct (automatique selon priorité ou ciblé manuellement sur une ligne précise).
 * 2. L'octroi de dispenses financières (ex: arrivée au 2ème trimestre), l'attribution de bourses/remises
 *    avec choix flexible de la tranche cible, et le report d'arriérés.
 * 3. L'activation ou la résiliation des frais optionnels (Bus, Cantine...).
 * 4. L'historique complet des reçus avec réimpression PDF en 1 clic.
 *
 * Tous les tableaux sont implémentés avec TanStack Table et toutes les dates suivent le format jj-mm-aaaa.
 *
 * @param {DossierFinancierModalProps} props Propriétés du composant
 * @returns {JSX.Element | null} Modale du dossier élève
 */
export default function DossierFinancierModal({
    isOpen,
    onClose,
    compte,
    boursesRemises = [],
    fraisOptionnels = [],
    estCaisseCloturee = false,
    canEncaisser = true,
    canModifierVersement,
    canManageBourses = true,
    canManageDispenses,
    canManageOptions = true,
    canConfigureFrais = false,
    canManageArrieres = false,
    onOpenCreateModeleBourse,
}: DossierFinancierModalProps) {
    // Désactive l'auto-mémoïsation React Compiler sur ce composant contenant des tables TanStack Table v8 dynamiques
    'use no memo';

    const effectiveCanDispenses = canManageDispenses ?? canManageBourses;
    const aujourdhuiIso = formatDateForInput(new Date());

    // Conservation du dernier compte affiché afin de laisser Radix Dialog exécuter sa fermeture complète sans retour anticipé `null`
    const [displayCompte, setDisplayCompte] = useState<CompteFinancierEleve | null>(compte);
    const activeCompte = compte ?? displayCompte;
    const abortControllerRef = useRef<AbortController | null>(null);

    const [dossierTab, setDossierTab] = useState<'echeancier' | 'bourses' | 'options' | 'historique'>('echeancier');
    const [data, setData] = useState<DossierData | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    // États d'encaissement rapide intégré (mode automatique par priorité vs ciblage manuel + date réelle/rétroactive)
    const [modeAllocation, setModeAllocation] = useState<'auto' | 'manuel'>('auto');
    const [montantEncaissement, setMontantEncaissement] = useState<string>('');
    const [ciblagesManuels, setCiblagesManuels] = useState<Record<string, string>>({});
    const [dateVersement, setDateVersement] = useState<string>(aujourdhuiIso);
    const [appliquerRemiseAnticipee, setAppliquerRemiseAnticipee] = useState<boolean>(false);
    const [modePaiement, setModePaiement] = useState<ModePaiement>('especes');
    const [referencePaiement, setReferencePaiement] = useState<string>('');
    const [payeurNom, setPayeurNom] = useState<string>('');
    const [payeurTelephone, setPayeurTelephone] = useState<string>('');
    const [receiptFormat, setReceiptFormat] = useState<FormatImpressionRecu>(() =>
        getPreferredReceiptFormat()
    );
    const [isSubmittingPayment, setIsSubmittingPayment] = useState(false);

    // États de dispense financière (totale 100 % ou partielle + motif obligatoire)
    const [showInlineDispenseForm, setShowInlineDispenseForm] = useState(false);
    const [selectedDispenseFraisId, setSelectedDispenseFraisId] = useState<string>('');
    const [typeDispense, setTypeDispense] = useState<'totale' | 'partielle'>('totale');
    const [montantDispenseInput, setMontantDispenseInput] = useState<string>('');
    const [motifDispense, setMotifDispense] = useState<string>('');
    const [isSubmittingDispense, setIsSubmittingDispense] = useState(false);

    // États d'attribution de bourse / remise (avec choix flexible de la tranche cible) et d'arriérés
    const [selectedBourseId, setSelectedBourseId] = useState<string>('');
    const [selectedBourseCible, setSelectedBourseCible] = useState<string>('modele_defaut');
    const [motifBourse, setMotifBourse] = useState<string>('');
    const [arrieresInput, setArrieresInput] = useState<string>('0');
    const [isSubmittingBourse, setIsSubmittingBourse] = useState(false);

    // États de souscription à une option (Bus, Cantine...)
    const [selectedOptionId, setSelectedOptionId] = useState<string>('');
    const [montantOptionCustom, setMontantOptionCustom] = useState<string>('');
    const [observationsOption, setObservationsOption] = useState<string>('');
    const [isSubmittingOption, setIsSubmittingOption] = useState(false);
    const [togglingOptionFraisId, setTogglingOptionFraisId] = useState<number | null>(null);

    // États de modification directe d'un versement enregistré (montant, date, mode, payeur, référence)
    const [versementEnEdition, setVersementEnEdition] = useState<VersementHistoriqueItem | null>(null);
    const [editMontantTotal, setEditMontantTotal] = useState<string>('');
    const [editDateVersement, setEditDateVersement] = useState<string>(aujourdhuiIso);
    const [editModePaiement, setEditModePaiement] = useState<ModePaiement>('especes');
    const [editReferencePaiement, setEditReferencePaiement] = useState<string>('');
    const [editPayeurNom, setEditPayeurNom] = useState<string>('');
    const [editPayeurTelephone, setEditPayeurTelephone] = useState<string>('');
    const [editObservations, setEditObservations] = useState<string>('');
    const [isSubmittingEditVersement, setIsSubmittingEditVersement] = useState(false);

    const effectiveCanModifierVersement = Boolean(canModifierVersement ?? data?.can_edit_versements);

    /**
     * Synchronise l'aperçu du compte uniquement lorsqu'un compte valide est fourni.
     */
    useEffect(() => {
        if (compte) {
            setDisplayCompte(compte);
        }
    }, [compte]);

    /**
     * Recharge les données fraîches du dossier financier de l'élève depuis le backend
     * en annulant proprement toute requête précédente encore en cours.
     */
    const fetchDossier = useCallback((compteId: number) => {
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }
        const controller = new AbortController();
        abortControllerRef.current = controller;

        setIsLoading(true);
        fetch(`/administration/finances/comptes/${compteId}/dossier`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then((res) => res.json())
            .then((resData: DossierData) => {
                if (controller.signal.aborted) return;
                setData(resData);
                setArrieresInput(String(Number(resData.arrieres_anterieurs ?? 0)));
                setIsLoading(false);
            })
            .catch((err: unknown) => {
                if ((err as { name?: string })?.name === 'AbortError') return;
                setIsLoading(false);
            });
    }, []);

    /**
     * Initialisation à l'ouverture du dossier d'un élève et nettoyage à la fermeture.
     */
    useEffect(() => {
        if (!isOpen || !compte) {
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
            return;
        }

        const todayStr = formatDateForInput(new Date());
        setDossierTab('echeancier');
        setModeAllocation('auto');
        setMontantEncaissement('');
        setCiblagesManuels({});
        setDateVersement(todayStr);
        setAppliquerRemiseAnticipee(false);
        setModePaiement('especes');
        setReferencePaiement('');
        setPayeurNom(
            compte.eleve?.nom_tuteur ||
                compte.eleve?.nom_pere ||
                compte.eleve?.nom_mere ||
                ''
        );
        setPayeurTelephone(compte.eleve?.telephone_parent || '');
        setIsSubmittingPayment(false);
        setShowInlineDispenseForm(false);
        setSelectedDispenseFraisId('');
        setTypeDispense('totale');
        setMontantDispenseInput('');
        setMotifDispense('');
        setIsSubmittingDispense(false);
        setSelectedBourseId(boursesRemises[0] ? String(boursesRemises[0].id) : '');
        setSelectedBourseCible('modele_defaut');
        setMotifBourse('');
        setIsSubmittingBourse(false);
        setSelectedOptionId('');
        setMontantOptionCustom('');
        setObservationsOption('');
        setIsSubmittingOption(false);
        setTogglingOptionFraisId(null);
        setVersementEnEdition(null);
        setIsSubmittingEditVersement(false);

        fetchDossier(compte.id);

        return () => {
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, [isOpen, compte?.id, fetchDossier]);

    /**
     * Sélectionne par défaut le premier modèle de bourse lorsqu'un nouveau modèle vient d'être créé.
     */
    useEffect(() => {
        if (!selectedBourseId && boursesRemises.length > 0) {
            setSelectedBourseId(String(boursesRemises[0].id));
        }
    }, [boursesRemises, selectedBourseId]);

    /**
     * Lignes obligatoires du dossier (utilisées pour les dispenses et le ciblage précis de remises).
     */
    const lignesObligatoires = useMemo(
        () => (data?.lignes || []).filter((l) => l.type === 'obligatoire' && Boolean(l.id)),
        [data?.lignes]
    );

    /**
     * Lignes ayant encore un reste à payer (éligibles à l'encaissement automatique ou manuel).
     */
    const lignesImpayees = useMemo(
        () => (data?.lignes || []).filter((l) => Number(l.montant_restant) > 0),
        [data?.lignes]
    );

    /**
     * Pré-sélectionne la première tranche obligatoire lorsqu'aucune n'est encore choisie dans le formulaire de dispense.
     */
    useEffect(() => {
        if (!selectedDispenseFraisId && lignesObligatoires.length > 0) {
            setSelectedDispenseFraisId(String(lignesObligatoires[0].id));
        }
    }, [lignesObligatoires, selectedDispenseFraisId]);

    /**
     * Modèle de bourse actuellement sélectionné dans le sélecteur d'attribution.
     */
    const bourseModeleCourant = useMemo(
        () => boursesRemises.find((b) => String(b.id) === selectedBourseId) ?? null,
        [boursesRemises, selectedBourseId]
    );

    /**
     * Calcule en direct l'aperçu du montant qui sera déduit selon le modèle et la tranche cible sélectionnés.
     */
    const apercuAttributionBourse = useMemo(() => {
        if (!compte || !bourseModeleCourant) return null;

        const baseTotaleNette = Math.max(
            0,
            Number(compte.debit_initial || 0) - Number(data?.total_dispense ?? compte.total_dispense ?? 0)
        );

        let ligneCible: LigneEcheancier | null = null;
        let libelleCible = formatCibleImputationLabel(bourseModeleCourant.cible_imputation);

        if (selectedBourseCible.startsWith('frais:')) {
            const targetId = Number(selectedBourseCible.replace('frais:', ''));
            ligneCible = lignesObligatoires.find((l) => Number(l.id) === targetId) ?? null;
            libelleCible = ligneCible ? ligneCible.libelle : 'Ligne sélectionnée';
        } else {
            const cibleEffective =
                selectedBourseCible === 'modele_defaut'
                    ? bourseModeleCourant.cible_imputation || 'derniere_tranche'
                    : selectedBourseCible;
            libelleCible = formatCibleImputationLabel(cibleEffective);

            if (cibleEffective.startsWith('tranche_')) {
                const numTranche = parseInt(cibleEffective.replace('tranche_', ''), 10);
                ligneCible =
                    lignesObligatoires.find((l) => Number(l.tranche_numero) === numTranche) ??
                    lignesObligatoires[lignesObligatoires.length - 1] ??
                    null;
            } else if (cibleEffective === 'derniere_tranche' && lignesObligatoires.length > 0) {
                ligneCible = lignesObligatoires[lignesObligatoires.length - 1];
            }
        }

        let assietteCalcul = baseTotaleNette;
        if (
            bourseModeleCourant.mode_calcul === 'pourcentage' &&
            bourseModeleCourant.base_calcul === 'tranche_ciblee' &&
            ligneCible
        ) {
            assietteCalcul = Math.max(
                0,
                Number(ligneCible.montant_brut ?? ligneCible.montant) - Number(ligneCible.dispense_appliquee ?? 0)
            );
        }

        const montantCalcule =
            bourseModeleCourant.mode_calcul === 'pourcentage'
                ? Math.min(baseTotaleNette, Math.round((assietteCalcul * Number(bourseModeleCourant.valeur)) / 100))
                : Math.min(baseTotaleNette, Number(bourseModeleCourant.valeur));

        return {
            montantCalcule,
            assietteCalcul,
            libelleCible,
        };
    }, [compte, bourseModeleCourant, selectedBourseCible, lignesObligatoires, data?.total_dispense]);

    /**
     * Tranche obligatoire actuellement sélectionnée pour une dispense.
     */
    const ligneDispenseCourante = useMemo(
        () => lignesObligatoires.find((l) => String(l.id) === selectedDispenseFraisId) ?? null,
        [lignesObligatoires, selectedDispenseFraisId]
    );

    /**
     * Active ou désactive une ligne dans le mode d'encaissement « Cibler manuellement ».
     */
    const toggleLigneManuelle = useCallback((ligne: LigneEcheancier, index: number) => {
        const key = getLigneUniqueKey(ligne, index);
        setCiblagesManuels((prev) => {
            const next = { ...prev };
            if (next[key] !== undefined) {
                delete next[key];
            } else {
                next[key] = String(Number(ligne.montant_restant));
            }
            return next;
        });
    }, []);

    /**
     * Met à jour le montant affecté manuellement à une ligne précise (plafonné au reste dû de cette ligne).
     */
    const updateMontantLigneManuelle = useCallback(
        (ligne: LigneEcheancier, index: number, rawValue: string) => {
            const key = getLigneUniqueKey(ligne, index);
            const numeric = parseFloat(rawValue);
            const plafonne =
                !isNaN(numeric) && numeric > Number(ligne.montant_restant)
                    ? String(Number(ligne.montant_restant))
                    : rawValue;

            setCiblagesManuels((prev) => ({
                ...prev,
                [key]: plafonne,
            }));
        },
        []
    );

    /**
     * Prépare l'encaissement rapide lorsqu'on clique sur « Encaisser » depuis une ligne de l'échéancier :
     * - Si c'est la toute première ligne prioritaire impayée, on reste en mode automatique.
     * - Si c'est une autre ligne (ex: le parent vient payer uniquement le Bus ou la Tranche 2),
     *   on bascule automatiquement en mode « Cibler manuellement » sur cette ligne précise.
     */
    const handleEncaisserLigneSpecifique = useCallback(
        (ligne: LigneEcheancier) => {
            const indexDansImpayees = lignesImpayees.findIndex(
                (item, idx) =>
                    getLigneUniqueKey(item, idx) ===
                    getLigneUniqueKey(ligne, (data?.lignes || []).indexOf(ligne))
            );

            if (indexDansImpayees <= 0) {
                setModeAllocation('auto');
                setMontantEncaissement(String(Number(ligne.montant_restant)));
            } else {
                const key = getLigneUniqueKey(ligne, indexDansImpayees);
                setModeAllocation('manuel');
                setCiblagesManuels({ [key]: String(Number(ligne.montant_restant)) });
            }
        },
        [lignesImpayees, data?.lignes]
    );

    /**
     * Ouvre directement le panneau rapide de dispense pré-positionné sur une tranche obligatoire.
     */
    const handleOuvrirDispenseSurLigne = useCallback((ligne: LigneEcheancier) => {
        if (!ligne.id) return;
        setSelectedDispenseFraisId(String(ligne.id));
        setTypeDispense('totale');
        setMontantDispenseInput(String(Number(ligne.montant_brut ?? ligne.montant)));
        setMotifDispense(ligne.dispense_motif || '');
        setShowInlineDispenseForm(true);
    }, []);

    /**
     * Calcule les ventilations effectives du versement selon le mode choisi :
     * - Mode `auto` : cascade automatique selon l'ordre de priorité configuré par l'établissement.
     * - Mode `manuel` : affectation exacte sur la ou les lignes cochées par l'économe.
     */
    const ventilationsPrevisionnelles = useMemo(() => {
        const vents: {
            libelle: string;
            montant: number;
            type_frais_id: number | null;
            frais_scolaire_id: number | null;
        }[] = [];

        if (modeAllocation === 'manuel') {
            lignesImpayees.forEach((ligne, idx) => {
                const key = getLigneUniqueKey(ligne, idx);
                const saisie = ciblagesManuels[key];
                if (saisie === undefined) return;
                const val = Math.min(Number(ligne.montant_restant), parseFloat(saisie) || 0);
                if (val > 0) {
                    vents.push({
                        libelle: ligne.libelle,
                        montant: Math.round(val * 100) / 100,
                        type_frais_id: ligne.type_frais_id ?? null,
                        frais_scolaire_id:
                            ligne.type === 'obligatoire' || ligne.type === 'optionnel'
                                ? (ligne.id ?? null)
                                : null,
                    });
                }
            });
            return vents;
        }

        const montantNum = parseFloat(montantEncaissement);
        if (isNaN(montantNum) || montantNum <= 0) return [];

        let reliquat = montantNum;
        for (const ligne of lignesImpayees) {
            if (reliquat <= 0) break;
            const affecte = Math.min(reliquat, Number(ligne.montant_restant));
            if (affecte > 0) {
                vents.push({
                    libelle: ligne.libelle,
                    montant: affecte,
                    type_frais_id: ligne.type_frais_id ?? null,
                    frais_scolaire_id:
                        ligne.type === 'obligatoire' || ligne.type === 'optionnel'
                            ? (ligne.id ?? null)
                            : null,
                });
                reliquat = Math.round((reliquat - affecte) * 100) / 100;
            }
        }

        return vents;
    }, [modeAllocation, montantEncaissement, ciblagesManuels, lignesImpayees]);

    /**
     * Montant brut saisi par l'utilisateur en mode automatique (pour détecter un éventuel dépassement du reste à payer).
     */
    const montantSaisiBrut = useMemo(() => {
        if (modeAllocation === 'manuel') {
            return ventilationsPrevisionnelles.reduce((acc, v) => acc + v.montant, 0);
        }
        const parsed = parseFloat(montantEncaissement);
        return isNaN(parsed) || parsed <= 0 ? 0 : parsed;
    }, [modeAllocation, montantEncaissement, ventilationsPrevisionnelles]);

    /**
     * Montant total effectif du versement (somme exacte des rubriques impayées ventilées).
     */
    const montantTotalEffectif = useMemo(() => {
        return ventilationsPrevisionnelles.reduce((acc, v) => acc + v.montant, 0);
    }, [ventilationsPrevisionnelles]);

    /**
     * Bornes calendaires et éligibilité dynamique à la remise anticipée avant rentrée (ex: 4 %).
     */
    const dateMinVersement = data?.date_min_versement || '2020-01-01';
    const dateLimiteRentree = data?.date_limite_rentree || null;
    const remiseAnticipeeModele = data?.remise_anticipee_modele ?? null;
    const estEligibleRemiseAnticipee = Boolean(
        remiseAnticipeeModele &&
            !remiseAnticipeeModele.deja_attribuee &&
            dateLimiteRentree &&
            dateVersement &&
            dateVersement <= dateLimiteRentree
    );
    const reductionAnticipeeEstimee =
        appliquerRemiseAnticipee && estEligibleRemiseAnticipee
            ? Number(remiseAnticipeeModele?.montant_estime ?? 0)
            : 0;
    const estCaisseBloqueeSurDate = Boolean(estCaisseCloturee && dateVersement === aujourdhuiIso);

    /**
     * Enregistre le versement directement depuis le dossier de l'élève et actualise l'échéancier.
     * Vérifie strictement que la date est raisonnable (<= aujourd'hui) et que le montant ne dépasse pas le reste dû.
     */
    const handleEncaisserMaintenant = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte) return;

        if (!dateVersement || dateVersement > aujourdhuiIso) {
            toast.error("La date de versement ne peut pas être postérieure à la date du jour.", {
                position: 'top-right',
            });
            return;
        }

        if (dateVersement < dateMinVersement) {
            toast.error(`La date de versement ne peut pas être antérieure au ${formatDate(dateMinVersement)}.`, {
                position: 'top-right',
            });
            return;
        }

        if (estCaisseBloqueeSurDate) {
            toast.error(
                "La caisse d'aujourd'hui est déjà clôturée. Choisissez une date antérieure pour saisir un reçu papier passé.",
                { position: 'top-right' }
            );
            return;
        }

        const plafondResteBrut = Number(data?.solde_restant ?? compte.solde_restant ?? 0);
        const plafondReste = Math.max(0, plafondResteBrut - reductionAnticipeeEstimee);

        if (plafondResteBrut <= 0) {
            toast.error(
                "Ce compte élève est déjà totalement soldé (Reste à payer : 0 FCFA). Si un frais optionnel le concerne, cochez-le d'abord ci-dessus.",
                { position: 'top-right' }
            );
            return;
        }

        if (montantSaisiBrut > plafondReste + 0.01) {
            toast.error(
                `Le montant saisi (${montantSaisiBrut.toLocaleString('fr-FR')} FCFA) ne peut pas dépasser le reste à payer (${plafondReste.toLocaleString('fr-FR')} FCFA).`,
                { position: 'top-right' }
            );
            return;
        }

        if (montantTotalEffectif <= 0 || ventilationsPrevisionnelles.length === 0) {
            toast.error('Veuillez saisir ou sélectionner un montant valide à encaisser.', {
                position: 'top-right',
            });
            return;
        }

        setIsSubmittingPayment(true);
        router.post(
            '/administration/finances/versements',
            {
                compte_financier_id: compte.id,
                montant_total: montantTotalEffectif,
                date_versement: dateVersement,
                appliquer_remise_anticipee: appliquerRemiseAnticipee && estEligibleRemiseAnticipee,
                bourse_remise_anticipee_id:
                    appliquerRemiseAnticipee && estEligibleRemiseAnticipee
                        ? remiseAnticipeeModele?.id ?? null
                        : null,
                mode_paiement: modePaiement,
                reference_paiement: modePaiement !== 'especes' ? (referencePaiement.trim() || null) : null,
                payeur_nom: payeurNom.trim() || null,
                payeur_telephone: payeurTelephone.trim() || null,
                ventilations: ventilationsPrevisionnelles,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    toast.success(
                        `Versement de ${montantTotalEffectif.toLocaleString('fr-FR')} FCFA encaissé avec succès !`,
                        {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        }
                    );
                    setMontantEncaissement('');
                    setCiblagesManuels({});
                    setModeAllocation('auto');
                    setAppliquerRemiseAnticipee(false);
                    setReferencePaiement('');
                    fetchDossier(compte.id);

                    const flash = (page.props as Record<string, unknown>)?.flash as
                        | { dernier_recu_id?: number }
                        | undefined;
                    if (flash?.dernier_recu_id) {
                        window.open(
                            buildReceiptPrintUrl(flash.dernier_recu_id, receiptFormat),
                            '_blank'
                        );
                    }
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Erreur lors de l'encaissement.", { position: 'top-right' });
                },
                onFinish: () => setIsSubmittingPayment(false),
            }
        );
    };

    /**
     * Ouvre le formulaire d'édition directe d'un versement valide en pré-remplissant ses valeurs actuelles.
     *
     * @param {VersementHistoriqueItem} versement Reçu valide à modifier
     */
    const handleOuvrirEditionVersement = useCallback(
        (versement: VersementHistoriqueItem) => {
            if (!effectiveCanModifierVersement || versement.statut !== 'valide') return;

            setVersementEnEdition(versement);
            setEditMontantTotal(String(Number(versement.montant_total || 0)));
            setEditDateVersement(formatDateForInput(versement.date_versement) || aujourdhuiIso);
            setEditModePaiement((versement.mode_paiement as ModePaiement) || 'especes');
            setEditReferencePaiement(versement.reference_paiement || '');
            setEditPayeurNom(
                versement.payeur_nom ||
                    compte?.eleve?.nom_tuteur ||
                    compte?.eleve?.nom_pere ||
                    compte?.eleve?.nom_mere ||
                    ''
            );
            setEditPayeurTelephone(versement.payeur_telephone || compte?.eleve?.telephone_parent || '');
            setEditObservations(versement.observations || '');
        },
        [effectiveCanModifierVersement, aujourdhuiIso, compte]
    );

    /**
     * Soumet la modification d'un versement existant et déclenche le recalcul immédiat
     * de l'échéancier, du solde de l'élève et des états financiers.
     *
     * @param {React.FormEvent} e Événement de soumission du formulaire
     */
    const handleEnregistrerEditionVersement = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte || !versementEnEdition) return;

        const nouveauMontant = Math.round((parseFloat(editMontantTotal) || 0) * 100) / 100;
        const plafondAutorise =
            Math.round(
                (Number(data?.solde_restant ?? compte.solde_restant ?? 0) +
                    Number(versementEnEdition.montant_total || 0)) *
                    100
            ) / 100;

        if (nouveauMontant <= 0) {
            toast.error('Veuillez saisir un montant strictement supérieur à 0 FCFA.', {
                position: 'top-right',
            });
            return;
        }

        if (nouveauMontant > plafondAutorise + 0.01) {
            toast.error(
                `Le montant modifié (${nouveauMontant.toLocaleString('fr-FR')} FCFA) dépasse le plafond autorisé pour ce dossier (${plafondAutorise.toLocaleString('fr-FR')} FCFA).`,
                { position: 'top-right' }
            );
            return;
        }

        setIsSubmittingEditVersement(true);
        router.put(
            `/administration/finances/versements/${versementEnEdition.id}`,
            {
                montant_total: nouveauMontant,
                date_versement: editDateVersement || aujourdhuiIso,
                mode_paiement: editModePaiement,
                reference_paiement: editReferencePaiement.trim() || null,
                payeur_nom: editPayeurNom.trim() || null,
                payeur_telephone: editPayeurTelephone.trim() || null,
                observations: editObservations.trim() || null,
                mode_allocation: 'auto',
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success(
                        `Reçu ${versementEnEdition.reference_recu} modifié (${nouveauMontant.toLocaleString('fr-FR')} FCFA). Dossier et états mis à jour.`,
                        {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        }
                    );
                    setVersementEnEdition(null);
                    fetchDossier(compte.id);
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || 'Impossible de modifier ce versement.', {
                        position: 'top-right',
                    });
                },
                onFinish: () => setIsSubmittingEditVersement(false),
            }
        );
    };

    /**
     * Accorde une dispense financière (totale 100 % ou partielle) sur une tranche obligatoire.
     */
    const handleAccorderDispense = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte || !selectedDispenseFraisId) return;

        if (motifDispense.trim().length < 3) {
            toast.error('Veuillez indiquer le motif de la dispense (ex: Arrivée au 2ème trimestre).', {
                position: 'top-right',
            });
            return;
        }

        const montantPartiel =
            typeDispense === 'partielle' ? parseFloat(montantDispenseInput) || 0 : null;

        if (typeDispense === 'partielle' && (!montantPartiel || montantPartiel <= 0)) {
            toast.error('Veuillez saisir un montant de dispense partielle valide.', {
                position: 'top-right',
            });
            return;
        }

        setIsSubmittingDispense(true);
        router.post(
            '/administration/finances/dispenses',
            {
                compte_financier_id: compte.id,
                frais_scolaire_id: Number(selectedDispenseFraisId),
                type_dispense: typeDispense,
                montant_dispense: montantPartiel,
                motif: motifDispense.trim(),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Dispense financière enregistrée et échéancier mis à jour.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    setMotifDispense('');
                    setMontantDispenseInput('');
                    setShowInlineDispenseForm(false);
                    fetchDossier(compte.id);
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Impossible d'enregistrer la dispense.", { position: 'top-right' });
                },
                onFinish: () => setIsSubmittingDispense(false),
            }
        );
    };

    /**
     * Retire une dispense financière existante et rétablit l'exigibilité de la tranche.
     */
    const handleRetirerDispense = useCallback(
        (dispenseId: number) => {
            if (!compte) return;

            router.delete(`/administration/finances/dispenses/${dispenseId}`, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Dispense retirée et solde recalculé.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    fetchDossier(compte.id);
                },
            });
        },
        [compte, fetchDossier]
    );

    /**
     * Attribue un modèle de bourse ou remise à cet élève en respectant la tranche cible choisie.
     */
    const handleAttribuerBourse = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte || !selectedBourseId) return;

        let cibleImputationPayload: string | null = null;
        let fraisScolaireIdPayload: number | null = null;

        if (selectedBourseCible.startsWith('frais:')) {
            fraisScolaireIdPayload = Number(selectedBourseCible.replace('frais:', ''));
        } else if (selectedBourseCible !== 'modele_defaut') {
            cibleImputationPayload = selectedBourseCible;
        }

        setIsSubmittingBourse(true);
        router.post(
            '/administration/finances/bourses-remises/attribuer',
            {
                compte_financier_id: compte.id,
                bourse_remise_id: Number(selectedBourseId),
                cible_imputation: cibleImputationPayload,
                frais_scolaire_id: fraisScolaireIdPayload,
                motif: motifBourse.trim() || null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Remise / Bourse appliquée avec succès au dossier de l’élève.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    setMotifBourse('');
                    setSelectedBourseCible('modele_defaut');
                    fetchDossier(compte.id);
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Erreur lors de l'attribution.", { position: 'top-right' });
                },
                onFinish: () => setIsSubmittingBourse(false),
            }
        );
    };

    /**
     * Retire une bourse ou remise attribuée à cet élève.
     */
    const handleRetirerBourse = (attributionId: number) => {
        if (!compte) return;

        router.delete(`/administration/finances/bourses-remises/attributions/${attributionId}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Remise retirée et solde recalculé.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                fetchDossier(compte.id);
            },
        });
    };

    /**
     * Met à jour le montant des arriérés antérieurs de l'élève.
     */
    const handleSaveArrieres = () => {
        if (!compte) return;
        const montantArr = Math.max(0, parseFloat(arrieresInput) || 0);

        router.post(
            `/administration/finances/comptes/${compte.id}/ajuster`,
            { arrieres_anterieurs: montantArr },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Arriérés antérieurs mis à jour.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    fetchDossier(compte.id);
                },
            }
        );
    };

    /**
     * Souscrit l'élève à un frais optionnel (Bus, Cantine, Internat...).
     */
    const handleSouscrireOption = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte || !selectedOptionId) return;

        setIsSubmittingOption(true);
        router.post(
            '/administration/finances/souscriptions-optionnelles',
            {
                compte_financier_id: compte.id,
                frais_scolaire_id: Number(selectedOptionId),
                montant: montantOptionCustom ? parseFloat(montantOptionCustom) : null,
                observations: observationsOption.trim() || null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Option ajoutée au dossier de l’élève.', {
                        position: 'top-right',
                        className: 'bg-emerald-600 text-white border-none',
                    });
                    setSelectedOptionId('');
                    setMontantOptionCustom('');
                    setObservationsOption('');
                    fetchDossier(compte.id);
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.error(first || "Impossible d'ajouter cette option.", { position: 'top-right' });
                },
                onFinish: () => setIsSubmittingOption(false),
            }
        );
    };

    /**
     * Annule une option facultative souscrite par l'élève.
     */
    const handleResilierOption = (souscriptionId: number) => {
        if (!compte) return;

        router.delete(`/administration/finances/souscriptions-optionnelles/${souscriptionId}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Option résiliée et solde mis à jour.', {
                    position: 'top-right',
                    className: 'bg-emerald-600 text-white border-none',
                });
                fetchDossier(compte.id);
            },
            onError: (errors) => {
                const first = Object.values(errors)[0] as string;
                toast.error(first || 'Impossible de retirer ce frais optionnel.', { position: 'top-right' });
            },
        });
    };

    /**
     * Active ou désactive en 1 clic un frais optionnel du niveau directement depuis l'Onglet 1 (Échéancier & Encaisser)
     * afin que le montant total à verser par l'élève soit recalculé immédiatement avant tout encaissement.
     */
    const handleToggleOptionDirect = useCallback(
        (opt: FraisOptionnelNiveauItem) => {
            if (!compte || !canManageOptions) return;

            const fraisId = opt.frais_scolaire_id ?? opt.id;
            const montantStd = opt.montant_standard ?? opt.montant;
            setTogglingOptionFraisId(fraisId);

            if (opt.est_souscrit && opt.souscription_id) {
                router.delete(`/administration/finances/souscriptions-optionnelles/${opt.souscription_id}`, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        toast.success(`Frais optionnel « ${opt.libelle} » retiré du dossier de l'élève.`, {
                            position: 'top-right',
                            className: 'bg-emerald-600 text-white border-none',
                        });
                        fetchDossier(compte.id);
                    },
                    onError: (errors) => {
                        const first = Object.values(errors)[0] as string;
                        toast.error(first || 'Impossible de décocher ce frais optionnel.', {
                            position: 'top-right',
                        });
                    },
                    onFinish: () => setTogglingOptionFraisId(null),
                });
            } else {
                router.post(
                    '/administration/finances/souscriptions-optionnelles',
                    {
                        compte_financier_id: compte.id,
                        frais_scolaire_id: fraisId,
                        montant: montantStd,
                        observations: null,
                    },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            toast.success(
                                `Frais optionnel « ${opt.libelle} » (+${Number(montantStd).toLocaleString('fr-FR')} FCFA) appliqué à l'élève.`,
                                {
                                    position: 'top-right',
                                    className: 'bg-emerald-600 text-white border-none',
                                }
                            );
                            fetchDossier(compte.id);
                        },
                        onError: (errors) => {
                            const first = Object.values(errors)[0] as string;
                            toast.error(first || "Impossible d'appliquer ce frais optionnel.", {
                                position: 'top-right',
                            });
                        },
                        onFinish: () => setTogglingOptionFraisId(null),
                    }
                );
            }
        },
        [compte, canManageOptions, fetchDossier]
    );

    /**
     * Filtre les frais optionnels compatibles avec le niveau de l'élève (ou globaux).
     */
    const optionsApplicables = useMemo(() => {
        const niveauEleveId = compte?.inscription?.classe?.niveau_id;
        return fraisOptionnels.filter(
            (fo) => !fo.niveau_id || !niveauEleveId || Number(fo.niveau_id) === Number(niveauEleveId)
        );
    }, [fraisOptionnels, compte?.inscription?.classe?.niveau_id]);

    /**
     * Colonnes TanStack Table pour l'échéancier des tranches de l'élève.
     */
    const echeancierColumns = useMemo<ColumnDef<LigneEcheancier>[]>(
        () => [
            {
                id: 'rubrique',
                header: 'Priorité & Tranche',
                cell: ({ row }) => {
                    const l = row.original;
                    const dispense = Number(l.dispense_appliquee || 0);
                    const remise = Number(l.remise_appliquee || 0);

                    return (
                        <div>
                            <div className="font-semibold text-xs text-slate-800 dark:text-slate-100 flex flex-wrap items-center gap-1.5">
                                {l.type === 'obligatoire' && l.ordre_priorite !== undefined && (
                                    <span
                                        className="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-mono text-[10px] font-bold border border-slate-200 dark:border-slate-700"
                                        title="Ordre de priorité de prélèvement de l'établissement"
                                    >
                                        #{l.ordre_priorite}
                                    </span>
                                )}
                                <span>{l.libelle}</span>
                                {l.type === 'optionnel' && (
                                    <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 text-[10px] font-semibold">
                                        Optionnel
                                    </Badge>
                                )}
                                {dispense > 0 && (
                                    <Badge className="bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border-sky-200 text-[10px] font-semibold">
                                        -{dispense.toLocaleString('fr-FR')} FCFA dispensé
                                    </Badge>
                                )}
                                {remise > 0 && (
                                    <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 text-[10px] font-semibold">
                                        -{remise.toLocaleString('fr-FR')} FCFA remise
                                    </Badge>
                                )}
                            </div>
                            <div className="text-[10px] text-slate-500 flex flex-wrap items-center gap-x-1.5">
                                <span>{l.categorie}</span>
                                {(dispense > 0 || remise > 0) && l.montant_brut !== undefined && (
                                    <span>
                                        • Tarif initial : {Number(l.montant_brut).toLocaleString('fr-FR')} FCFA
                                    </span>
                                )}
                                {l.dispense_motif && (
                                    <span className="text-sky-700 dark:text-sky-400 font-medium">
                                        • Motif : {l.dispense_motif}
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'date_limite',
                header: 'Date Limite',
                cell: ({ row }) => {
                    const rawDate = row.original.date_limite_iso || row.original.date_limite;
                    return (
                        <div className="text-xs flex items-center gap-1 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            <Calendar className="w-3 h-3 text-slate-400 shrink-0" />
                            {rawDate ? formatDate(rawDate) : 'Dès inscription'}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'montant',
                header: () => <div className="text-right">Net Exigible</div>,
                cell: ({ row }) => (
                    <div className="text-right font-semibold text-xs tabular-nums">
                        {Number(row.original.montant).toLocaleString('fr-FR')} FCFA
                    </div>
                ),
            },
            {
                accessorKey: 'montant_paye',
                header: () => <div className="text-right">Réglé</div>,
                cell: ({ row }) => (
                    <div className="text-right text-xs text-emerald-600 font-semibold tabular-nums">
                        {Number(row.original.montant_paye).toLocaleString('fr-FR')} FCFA
                    </div>
                ),
            },
            {
                accessorKey: 'montant_restant',
                header: () => <div className="text-right">Reste Dû</div>,
                cell: ({ row }) => (
                    <div
                        className={`text-right text-xs font-bold tabular-nums ${
                            row.original.montant_restant > 0 ? 'text-red-600' : 'text-slate-400'
                        }`}
                    >
                        {Number(row.original.montant_restant).toLocaleString('fr-FR')} FCFA
                    </div>
                ),
            },
            {
                accessorKey: 'statut',
                header: () => <div className="text-center">Statut & Actions</div>,
                cell: ({ row }) => {
                    const l = row.original;

                    return (
                        <div className="flex flex-wrap items-center justify-center gap-1.5">
                            {l.statut === 'dispense' ? (
                                <Badge className="bg-sky-600 text-white text-[10px]">Dispensé</Badge>
                            ) : l.statut === 'solde' ? (
                                <Badge className="bg-emerald-600 text-white text-[10px]">Soldé</Badge>
                            ) : l.statut === 'en_retard' ? (
                                <Badge variant="destructive" className="text-[10px]">
                                    En retard
                                </Badge>
                            ) : (
                                <Badge
                                    variant="outline"
                                    className="text-[10px] text-amber-700 bg-amber-50 border-amber-200"
                                >
                                    À échoir
                                </Badge>
                            )}

                            {canEncaisser && !estCaisseCloturee && l.montant_restant > 0 && (
                                <button
                                    type="button"
                                    onClick={() => handleEncaisserLigneSpecifique(l)}
                                    className="text-[10px] font-bold text-emerald-700 hover:underline cursor-pointer"
                                    title="Encaisser cette ligne"
                                >
                                    Encaisser
                                </button>
                            )}

                            {effectiveCanDispenses && l.type === 'obligatoire' && l.id && (
                                l.dispense_id ? (
                                    <button
                                        type="button"
                                        onClick={() => handleRetirerDispense(Number(l.dispense_id))}
                                        className="text-[10px] font-semibold text-sky-700 hover:text-red-600 hover:underline cursor-pointer"
                                        title="Retirer la dispense accordée sur cette tranche"
                                    >
                                        Lever dispense
                                    </button>
                                ) : (
                                    l.montant_restant > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => handleOuvrirDispenseSurLigne(l)}
                                            className="text-[10px] font-semibold text-sky-700 hover:underline cursor-pointer"
                                            title="Accorder une dispense totale ou partielle (ex: élève arrivé au 2ème trimestre)"
                                        >
                                            Dispenser
                                        </button>
                                    )
                                )
                            )}
                        </div>
                    );
                },
            },
        ],
        [
            canEncaisser,
            effectiveCanDispenses,
            estCaisseCloturee,
            handleEncaisserLigneSpecifique,
            handleOuvrirDispenseSurLigne,
            handleRetirerDispense,
        ]
    );

    /**
     * Colonnes TanStack Table pour l'historique des reçus de l'élève.
     */
    const historiqueColumns = useMemo<ColumnDef<VersementHistoriqueItem>[]>(
        () => [
            {
                accessorKey: 'reference_recu',
                header: 'N° Reçu',
                cell: ({ row }) => (
                    <span className="font-mono font-bold text-xs text-slate-800 dark:text-slate-200">
                        {row.original.reference_recu}
                    </span>
                ),
            },
            {
                accessorKey: 'date_versement',
                header: 'Date',
                cell: ({ row }) => (
                    <span className="text-xs whitespace-nowrap">
                        {formatDate(row.original.date_versement)}
                    </span>
                ),
            },
            {
                id: 'details',
                header: 'Tranches réglées',
                cell: ({ row }) => (
                    <div className="flex flex-wrap gap-1">
                        {row.original.ventilations.map((vent) => (
                            <Badge key={vent.id} variant="outline" className="text-[10px] font-normal">
                                {vent.libelle} ({Number(vent.montant).toLocaleString('fr-FR')})
                            </Badge>
                        ))}
                    </div>
                ),
            },
            {
                accessorKey: 'montant_total',
                header: () => <div className="text-right">Montant</div>,
                cell: ({ row }) => (
                    <div className="text-right font-bold text-xs text-emerald-600 tabular-nums">
                        {Number(row.original.montant_total).toLocaleString('fr-FR')} FCFA
                    </div>
                ),
            },
            {
                accessorKey: 'statut',
                header: 'Statut',
                cell: ({ row }) => (
                    <Badge
                        variant={row.original.statut === 'valide' ? 'default' : 'destructive'}
                        className={
                            row.original.statut === 'valide'
                                ? 'bg-emerald-600 text-white text-[10px]'
                                : 'text-[10px]'
                        }
                    >
                        {row.original.statut === 'valide' ? 'Validé' : 'Annulé'}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: () => <div className="text-right">Actions & Reçus</div>,
                cell: ({ row }) => (
                    <div className="flex items-center justify-end gap-1">
                        {effectiveCanModifierVersement && row.original.statut === 'valide' && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 text-[11px] px-2 flex items-center gap-1 border-amber-300 text-amber-800 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                title="Modifier ce versement (montant, date, mode, payeur)"
                                onClick={() => handleOuvrirEditionVersement(row.original)}
                            >
                                <Pencil className="w-3 h-3" /> Modifier
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-7 text-[11px] px-2 flex items-center gap-1 border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                            title="Reçu officiel format A4 (2 exemplaires / page)"
                            onClick={() =>
                                window.open(
                                    buildReceiptPrintUrl(row.original.id, 'a4'),
                                    '_blank'
                                )
                            }
                        >
                            <Printer className="w-3 h-3" /> A4
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-7 text-[11px] px-2 flex items-center gap-1 border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200"
                            title="Ticket thermique 80mm (format caisse pharmacie)"
                            onClick={() =>
                                window.open(
                                    buildReceiptPrintUrl(row.original.id, 'ticket'),
                                    '_blank'
                                )
                            }
                        >
                            <Receipt className="w-3 h-3" /> Ticket 80mm
                        </Button>
                    </div>
                ),
            },
        ],
        [effectiveCanModifierVersement, handleOuvrirEditionVersement]
    );

    const echeancierLignes = useMemo(() => data?.lignes ?? [], [data?.lignes]);
    const versementsHistorique = useMemo(() => data?.versements ?? [], [data?.versements]);

    const echeancierTable = useReactTable({
        data: echeancierLignes,
        columns: echeancierColumns,
        getCoreRowModel: getCoreRowModel(),
    });

    const historiqueTable = useReactTable({
        data: versementsHistorique,
        columns: historiqueColumns,
        getCoreRowModel: getCoreRowModel(),
    });

    const totalExigible = Number(data?.total_exigible ?? activeCompte?.montant_net_exigible ?? 0);
    const totalDispense = Number(data?.total_dispense ?? activeCompte?.total_dispense ?? 0);
    const totalPaye = Number(data?.total_paye ?? activeCompte?.total_paye ?? 0);
    const soldeRestant = Number(data?.solde_restant ?? activeCompte?.solde_restant ?? 0);
    const plafondResteSaisie = Math.max(0, soldeRestant - reductionAnticipeeEstimee);
    const remiseTotale = Number(data?.remise_bourse ?? activeCompte?.remise_bourse ?? 0);
    const retardEchu = Number(data?.montant_echu_en_retard ?? 0);
    const boursesAttribuees = data?.bourses_attribuees || [];
    const dispensesAttribuees = data?.dispenses_attribuees || [];
    const souscriptionsActives = data?.souscriptions_actives || [];
    const versementsEleve = versementsHistorique;
    const versementsValidesEleve = useMemo(
        () => versementsEleve.filter((v) => v.statut === 'valide'),
        [versementsEleve]
    );

    const plafondEditionVersement = versementEnEdition
        ? Math.round((soldeRestant + Number(versementEnEdition.montant_total || 0)) * 100) / 100
        : 0;
    const montantEditionSaisi = Math.round((parseFloat(editMontantTotal) || 0) * 100) / 100;

    /**
     * Rend le panneau de modification directe d'un versement existant (montant, date, mode, payeur, référence)
     * avec contrôle en direct du plafond exigible et recalcul automatique des tranches.
     *
     * @returns {JSX.Element | null} Formulaire de modification du reçu sélectionné
     */
    const renderPanneauEditionVersement = () => {
        if (!versementEnEdition || !effectiveCanModifierVersement) {
            return null;
        }

        return (
            <form
                onSubmit={handleEnregistrerEditionVersement}
                className="p-4 rounded-xl bg-amber-50/80 dark:bg-amber-950/30 border-2 border-amber-300 dark:border-amber-700 space-y-3 text-xs shadow-xs"
            >
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Pencil className="w-4 h-4 text-amber-700 dark:text-amber-300 shrink-0" />
                        <div>
                            <h4 className="font-bold uppercase tracking-wider text-amber-950 dark:text-amber-100">
                                Modifier le Versement N° {versementEnEdition.reference_recu}
                            </h4>
                            <p className="text-[11px] text-amber-800 dark:text-amber-300">
                                Montant initial :{' '}
                                <strong>
                                    {Number(versementEnEdition.montant_total).toLocaleString('fr-FR')} FCFA
                                </strong>{' '}
                                • Le dossier de l'élève, son échéancier et les états financiers seront mis à jour
                                automatiquement.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={() => setVersementEnEdition(null)}
                        className="text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 p-1 cursor-pointer"
                        title="Annuler la modification"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Raccourcis rapides de montants pour corriger une erreur de saisie en 1 clic */}
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-[11px] font-semibold text-amber-900 dark:text-amber-200">
                        Ajustement rapide :
                    </span>
                    <button
                        type="button"
                        onClick={() =>
                            setEditMontantTotal(String(Number(versementEnEdition.montant_total)))
                        }
                        className="px-2 py-0.5 rounded-md bg-white dark:bg-slate-900 border border-amber-300 text-amber-900 dark:text-amber-200 text-[10px] font-semibold hover:bg-amber-100 cursor-pointer"
                    >
                        Montant actuel ({Number(versementEnEdition.montant_total).toLocaleString('fr-FR')} F)
                    </button>
                    {plafondEditionVersement > 0 && (
                        <button
                            type="button"
                            onClick={() => setEditMontantTotal(String(plafondEditionVersement))}
                            className="px-2 py-0.5 rounded-md bg-amber-600 text-white text-[10px] font-bold hover:bg-amber-700 cursor-pointer"
                        >
                            Solder le reste dû ({plafondEditionVersement.toLocaleString('fr-FR')} FCFA)
                        </button>
                    )}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                    <div className="space-y-1">
                        <Label htmlFor="edit_versement_montant" className="text-xs flex items-center justify-between">
                            <span>
                                Nouveau montant (FCFA) <span className="text-red-500">*</span>
                            </span>
                            <span className="text-[10px] font-normal text-slate-500">
                                Max : {plafondEditionVersement.toLocaleString('fr-FR')} F
                            </span>
                        </Label>
                        <Input
                            id="edit_versement_montant"
                            type="number"
                            min="1"
                            max={plafondEditionVersement}
                            value={editMontantTotal}
                            onChange={(e) => setEditMontantTotal(e.target.value)}
                            className={`h-9 bg-white dark:bg-slate-900 font-bold tabular-nums ${
                                montantEditionSaisi > plafondEditionVersement + 0.01
                                    ? 'border-red-500 text-red-600 focus-visible:ring-red-500'
                                    : 'text-emerald-700 dark:text-emerald-400'
                            }`}
                            required
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="edit_versement_date" className="text-xs">
                            Date du versement <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            id="edit_versement_date"
                            type="date"
                            min={dateMinVersement}
                            max={aujourdhuiIso}
                            value={formatDateForInput(editDateVersement)}
                            onChange={(e) => setEditDateVersement(e.target.value)}
                            className="h-9 bg-white dark:bg-slate-900 text-xs"
                            required
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="edit_versement_mode" className="text-xs">
                            Mode de règlement
                        </Label>
                        <Select
                            value={editModePaiement}
                            onValueChange={(v: ModePaiement) => setEditModePaiement(v)}
                        >
                            <SelectTrigger
                                id="edit_versement_mode"
                                className="h-9 bg-white dark:bg-slate-900 text-xs"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="especes">Espèces</SelectItem>
                                <SelectItem value="mobile_money_manuel">Orange / MTN Money</SelectItem>
                                <SelectItem value="virement">Virement bancaire</SelectItem>
                                <SelectItem value="cheque">Chèque</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="edit_versement_payeur" className="text-xs">
                            Payeur / Parent
                        </Label>
                        <Input
                            id="edit_versement_payeur"
                            placeholder="Nom du déposant"
                            value={editPayeurNom}
                            onChange={(e) => setEditPayeurNom(e.target.value)}
                            className="h-9 bg-white dark:bg-slate-900 text-xs"
                        />
                    </div>
                </div>

                {editModePaiement !== 'especes' && (
                    <div className="space-y-1">
                        <Label htmlFor="edit_versement_ref" className="text-xs">
                            Référence Mobile Money / Chèque / Bordereau
                        </Label>
                        <Input
                            id="edit_versement_ref"
                            placeholder="N° de transaction..."
                            value={editReferencePaiement}
                            onChange={(e) => setEditReferencePaiement(e.target.value)}
                            className="h-8 bg-white dark:bg-slate-900 text-xs"
                        />
                    </div>
                )}

                {montantEditionSaisi > plafondEditionVersement + 0.01 && (
                    <div className="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-300 dark:border-red-800 flex flex-wrap items-center justify-between gap-2 text-xs text-red-800 dark:text-red-200">
                        <div className="flex items-center gap-1.5 font-semibold">
                            <AlertTriangle className="w-4 h-4 text-red-600 shrink-0" />
                            <span>
                                Le montant saisi ({montantEditionSaisi.toLocaleString('fr-FR')} FCFA) dépasse le
                                plafond autorisé sur ce dossier ({plafondEditionVersement.toLocaleString('fr-FR')}{' '}
                                FCFA).
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setEditMontantTotal(String(plafondEditionVersement))}
                            className="px-2.5 py-1 rounded bg-red-600 text-white text-[11px] font-bold hover:bg-red-700 cursor-pointer shrink-0"
                        >
                            Ramener au plafond ({plafondEditionVersement.toLocaleString('fr-FR')} F)
                        </button>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-end gap-2 pt-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setVersementEnEdition(null)}
                        className="h-8 text-xs"
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={
                            isSubmittingEditVersement ||
                            montantEditionSaisi <= 0 ||
                            montantEditionSaisi > plafondEditionVersement + 0.01
                        }
                        className="h-8 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4"
                    >
                        <CheckCircle2 className="w-3.5 h-3.5 mr-1.5" />
                        {isSubmittingEditVersement
                            ? 'Mise à jour...'
                            : `Enregistrer la modification (${montantEditionSaisi.toLocaleString('fr-FR')} FCFA)`}
                    </Button>
                </div>
            </form>
        );
    };

    return (
        <Dialog open={Boolean(isOpen && compte)} onOpenChange={(open) => !open && onClose()}>
            {activeCompte && (
            <DialogContent className="max-w-4xl max-h-[92vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex flex-wrap items-center justify-between gap-2 text-slate-800 dark:text-slate-100 pr-6">
                        <span className="flex items-center gap-2">
                            <CreditCard className="w-5 h-5 text-emerald-600 shrink-0" />
                            <span>Dossier Financier de l'Élève</span>
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs flex items-center gap-1.5 text-sky-700 border-sky-200 hover:bg-sky-50"
                            onClick={() =>
                                window.open(`/administration/finances/comptes/${activeCompte.id}/fiche-pdf`, '_blank')
                            }
                        >
                            <Printer className="w-3.5 h-3.5" /> Relevé Parent (PDF)
                        </Button>
                    </DialogTitle>
                </DialogHeader>

                {/* Bandeau d'identité de l'élève */}
                <div className="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                    <div>
                        <div className="font-bold text-sm text-slate-900 dark:text-slate-100">
                            {activeCompte.eleve?.nom} {activeCompte.eleve?.prenom}
                        </div>
                        <div className="text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span>
                                Matricule :{' '}
                                <strong className="font-mono text-slate-800 dark:text-slate-200">
                                    {activeCompte.eleve?.matricule}
                                </strong>
                            </span>
                            <span>•</span>
                            <span>
                                Classe :{' '}
                                <strong className="text-emerald-700 dark:text-emerald-400">
                                    {activeCompte.inscription?.classe?.nom ?? 'N/A'}
                                </strong>
                            </span>
                            {data?.niveau_nom && (
                                <>
                                    <span>•</span>
                                    <span>Niveau : {data.niveau_nom}</span>
                                </>
                            )}
                            <span>•</span>
                            <span>
                                Parent : {activeCompte.eleve?.nom_tuteur || activeCompte.eleve?.nom_pere || 'Non renseigné'} (
                                {activeCompte.eleve?.telephone_parent || 'N/A'})
                            </span>
                        </div>
                    </div>
                    {soldeRestant <= 0 && (totalExigible > 0 || totalDispense > 0) && (
                        <Badge className="bg-emerald-600 text-white text-xs px-2.5 py-1 w-fit">
                            <CheckCircle2 className="w-3.5 h-3.5 mr-1" /> Scolarité Soldée
                        </Badge>
                    )}
                </div>

                {/* 4 Cartes KPIs synthétiques */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <div className="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                        <div className="flex items-center justify-between text-[11px] text-slate-500">
                            <span>Net à Payer</span>
                            <Wallet className="w-3.5 h-3.5 text-sky-600" />
                        </div>
                        <div className="text-sm font-extrabold text-slate-800 dark:text-slate-100 mt-1 tabular-nums">
                            {totalExigible.toLocaleString('fr-FR')} FCFA
                        </div>
                        <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5">
                            {totalDispense > 0 && (
                                <span className="text-[10px] font-semibold text-sky-600">
                                    Dispense : -{totalDispense.toLocaleString('fr-FR')} F
                                </span>
                            )}
                            {remiseTotale > 0 && (
                                <span className="text-[10px] font-semibold text-emerald-600">
                                    Remise : -{remiseTotale.toLocaleString('fr-FR')} F
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="p-2.5 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/20">
                        <div className="flex items-center justify-between text-[11px] text-emerald-700 dark:text-emerald-300">
                            <span>Déjà Versé</span>
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                        </div>
                        <div className="text-sm font-extrabold text-emerald-700 dark:text-emerald-300 mt-1 tabular-nums">
                            {totalPaye.toLocaleString('fr-FR')} FCFA
                        </div>
                    </div>

                    <div className="p-2.5 rounded-xl border border-red-200 dark:border-red-900/40 bg-red-50/50 dark:bg-red-950/20">
                        <div className="flex items-center justify-between text-[11px] text-red-700 dark:text-red-300">
                            <span>Reste à Payer</span>
                            <DollarSign className="w-3.5 h-3.5 text-red-600" />
                        </div>
                        <div className="text-sm font-extrabold text-red-700 dark:text-red-300 mt-1 tabular-nums">
                            {soldeRestant.toLocaleString('fr-FR')} FCFA
                        </div>
                    </div>

                    <div
                        className={`p-2.5 rounded-xl border ${
                            retardEchu > 0
                                ? 'border-red-300 bg-red-100/60 dark:bg-red-900/30'
                                : 'border-slate-200 bg-slate-50 dark:bg-slate-900'
                        }`}
                    >
                        <div className="flex items-center justify-between text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                            <span>Retard Échu</span>
                            <AlertTriangle
                                className={`w-3.5 h-3.5 ${retardEchu > 0 ? 'text-red-600' : 'text-slate-400'}`}
                            />
                        </div>
                        <div
                            className={`text-sm font-extrabold mt-1 tabular-nums ${
                                retardEchu > 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-600'
                            }`}
                        >
                            {retardEchu > 0 ? `${retardEchu.toLocaleString('fr-FR')} FCFA` : '0 FCFA (À jour)'}
                        </div>
                    </div>
                </div>

                {/* Sous-navigation du dossier Tout-en-Un */}
                <div className="flex items-center gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800 text-xs font-semibold pt-1 scrollbar-none">
                    <button
                        type="button"
                        onClick={() => setDossierTab('echeancier')}
                        className={`pb-2 px-3 flex items-center gap-1.5 border-b-2 whitespace-nowrap cursor-pointer transition-colors ${
                            dossierTab === 'echeancier'
                                ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Layers className="w-3.5 h-3.5" />
                        Échéancier & Encaisser
                    </button>
                    <button
                        type="button"
                        onClick={() => setDossierTab('bourses')}
                        className={`pb-2 px-3 flex items-center gap-1.5 border-b-2 whitespace-nowrap cursor-pointer transition-colors ${
                            dossierTab === 'bourses'
                                ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Award className="w-3.5 h-3.5" />
                        Bourses, Dispenses & Arriérés ({boursesAttribuees.length + dispensesAttribuees.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setDossierTab('options')}
                        className={`pb-2 px-3 flex items-center gap-1.5 border-b-2 whitespace-nowrap cursor-pointer transition-colors ${
                            dossierTab === 'options'
                                ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Bus className="w-3.5 h-3.5" />
                        Options Bus / Cantine ({souscriptionsActives.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setDossierTab('historique')}
                        className={`pb-2 px-3 flex items-center gap-1.5 border-b-2 whitespace-nowrap cursor-pointer transition-colors ${
                            dossierTab === 'historique'
                                ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <History className="w-3.5 h-3.5" />
                        Reçus & Historique ({versementsEleve.length})
                    </button>
                </div>

                {/* ONGLET 1 : ÉCHÉANCIER & ENCAISSEMENT DIRECT */}
                {dossierTab === 'echeancier' && (
                    <div className="space-y-3.5 pt-1">
                        {/* Alerte pédagogique si aucun tarif n'est configuré pour le niveau de l'élève */}
                        {!isLoading && data && (data.nb_tarifs_niveau ?? 0) === 0 && data.lignes.length === 0 && (
                            <div className="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                <div className="space-y-1 text-amber-900 dark:text-amber-200">
                                    <p className="font-bold flex items-center gap-1.5">
                                        <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
                                        Aucun tarif n'est encore configuré pour le niveau «{' '}
                                        {data.niveau_nom || 'de cette classe'} ».
                                    </p>
                                    <p className="text-amber-800/90 dark:text-amber-300/90">
                                        Ajoutez les tranches de ce niveau dans la <strong>Grille Tarifaire</strong> pour
                                        que l'échéancier de tous les élèves de ce niveau s'affiche automatiquement.
                                    </p>
                                </div>
                                {canConfigureFrais && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="border-amber-400 text-amber-900 hover:bg-amber-100 text-xs shrink-0"
                                        onClick={() => {
                                            onClose();
                                            router.visit('/administration/finances?tab=tarifs');
                                        }}
                                    >
                                        <ExternalLink className="w-3.5 h-3.5 mr-1.5" /> Configurer la Grille Tarifaire
                                    </Button>
                                )}
                            </div>
                        )}

                        {/* Panneau interactif des frais optionnels du niveau : permet de cocher ce qui concerne l'élève avant d'encaisser */}
                        {!isLoading && data && (data.frais_optionnels_niveau?.length ?? 0) > 0 && (
                            <div className="p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/25 border border-amber-200 dark:border-amber-800/70 space-y-2.5 text-xs">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <div className="font-bold text-amber-950 dark:text-amber-200 flex items-center gap-1.5">
                                            <Bus className="w-4 h-4 text-amber-600 shrink-0" />
                                            <span>
                                                Frais optionnels du niveau « {data.niveau_nom || 'de la classe'} » — Cochez
                                                ce qui concerne cet élève avant d'encaisser
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-amber-800/90 dark:text-amber-300/80 mt-0.5">
                                            Le montant net à payer et le reste à régler sont recalculés instantanément dès
                                            que vous cochez ou décochez un frais optionnel.
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    {(data.frais_optionnels_niveau || []).map((opt) => {
                                        const fraisId = opt.frais_scolaire_id ?? opt.id;
                                        const isBusy = togglingOptionFraisId === fraisId;
                                        const montantAffiche = Number(
                                            opt.montant_souscrit ?? opt.montant_standard ?? opt.montant
                                        );

                                        return (
                                            <label
                                                key={fraisId}
                                                className={`p-2.5 rounded-lg border flex items-center justify-between gap-2 transition-colors ${
                                                    canManageOptions && !isBusy
                                                        ? 'cursor-pointer'
                                                        : 'opacity-70 cursor-not-allowed'
                                                } ${
                                                    opt.est_souscrit
                                                        ? 'bg-white dark:bg-slate-900 border-amber-500 shadow-2xs'
                                                        : 'bg-white/70 dark:bg-slate-900/50 border-amber-200/80 dark:border-slate-800 hover:bg-white'
                                                }`}
                                            >
                                                <div className="flex items-center gap-2.5 min-w-0">
                                                    <input
                                                        type="checkbox"
                                                        checked={opt.est_souscrit}
                                                        disabled={!canManageOptions || isBusy}
                                                        onChange={() => handleToggleOptionDirect(opt)}
                                                        className="h-4 w-4 rounded border-amber-400 text-amber-600 focus:ring-amber-500 shrink-0"
                                                    />
                                                    <div className="min-w-0">
                                                        <div className="font-bold text-slate-900 dark:text-slate-100 truncate">
                                                            {opt.libelle}
                                                        </div>
                                                        <div className="text-[10px] text-slate-500 truncate">
                                                            {opt.categorie}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="text-right shrink-0">
                                                    <div className="font-extrabold text-xs text-amber-800 dark:text-amber-300 tabular-nums">
                                                        +{montantAffiche.toLocaleString('fr-FR')} FCFA
                                                    </div>
                                                    <span
                                                        className={`text-[10px] font-semibold ${
                                                            opt.est_souscrit
                                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                                : 'text-slate-400'
                                                        }`}
                                                    >
                                                        {isBusy
                                                            ? 'Mise à jour...'
                                                            : opt.est_souscrit
                                                              ? 'Inclus dans le total'
                                                              : 'Non inclus'}
                                                    </span>
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Tableau TanStack de l'échéancier trié par ordre de priorité */}
                        <div className="border border-slate-200 dark:border-slate-800 rounded-xl overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead className="bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                                    {echeancierTable.getHeaderGroups().map((headerGroup) => (
                                        <tr key={headerGroup.id}>
                                            {headerGroup.headers.map((header) => (
                                                <th key={header.id} className="p-2.5 font-semibold text-left">
                                                    {header.isPlaceholder
                                                        ? null
                                                        : flexRender(
                                                              header.column.columnDef.header,
                                                              header.getContext()
                                                          )}
                                                </th>
                                            ))}
                                        </tr>
                                    ))}
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {echeancierTable.getRowModel().rows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={echeancierColumns.length}
                                                className="p-4 text-center text-slate-500"
                                            >
                                                {isLoading
                                                    ? "Chargement de l'échéancier..."
                                                    : 'Aucune tranche exigible sur ce dossier.'}
                                            </td>
                                        </tr>
                                    ) : (
                                        echeancierTable.getRowModel().rows.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="hover:bg-slate-50 dark:hover:bg-slate-900/40"
                                            >
                                                {row.getVisibleCells().map((cell) => (
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

                        {/* Panneau rapide d'octroi de dispense (ouvert en cliquant sur « Dispenser » sur une tranche) */}
                        {effectiveCanDispenses && showInlineDispenseForm && (
                            <form
                                onSubmit={handleAccorderDispense}
                                className="p-3.5 rounded-xl bg-sky-50/70 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800 space-y-3 text-xs"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <div className="font-bold text-sky-900 dark:text-sky-200 flex items-center gap-1.5">
                                        <FileCheck2 className="w-4 h-4 text-sky-600" />
                                        <span>
                                            Accorder une dispense sur « {ligneDispenseCourante?.libelle || 'Tranche'} »
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setShowInlineDispenseForm(false)}
                                        className="text-slate-400 hover:text-slate-700 cursor-pointer"
                                        title="Fermer"
                                    >
                                        <X className="w-4 h-4" />
                                    </button>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Portée de la dispense</Label>
                                        <Select
                                            value={typeDispense}
                                            onValueChange={(v: 'totale' | 'partielle') => setTypeDispense(v)}
                                        >
                                            <SelectTrigger className="h-8 bg-white dark:bg-slate-900 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="totale">Dispense totale (100 %)</SelectItem>
                                                <SelectItem value="partielle">
                                                    Dispense partielle (montant libre)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {typeDispense === 'partielle' && (
                                        <div className="space-y-1">
                                            <Label className="text-xs">Montant dispensé (FCFA)</Label>
                                            <Input
                                                type="number"
                                                min="1"
                                                max={ligneDispenseCourante?.montant_brut ?? undefined}
                                                value={montantDispenseInput}
                                                onChange={(e) => setMontantDispenseInput(e.target.value)}
                                                placeholder="Ex: 15000"
                                                className="h-8 bg-white dark:bg-slate-900 text-xs font-bold tabular-nums"
                                            />
                                        </div>
                                    )}

                                    <div
                                        className={`space-y-1 ${
                                            typeDispense === 'partielle' ? '' : 'sm:col-span-2'
                                        }`}
                                    >
                                        <Label className="text-xs">
                                            Motif justificatif <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            value={motifDispense}
                                            onChange={(e) => setMotifDispense(e.target.value)}
                                            placeholder="Ex: Élève inscrit au 2ème trimestre..."
                                            className="h-8 bg-white dark:bg-slate-900 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex flex-wrap items-center gap-1">
                                        {MOTIFS_DISPENSE_RAPIDES.map((m) => (
                                            <button
                                                key={m}
                                                type="button"
                                                onClick={() => setMotifDispense(m)}
                                                className="px-2 py-0.5 rounded bg-white dark:bg-slate-900 border border-sky-200 text-sky-800 dark:text-sky-300 text-[10px] hover:bg-sky-100 cursor-pointer"
                                            >
                                                {m}
                                            </button>
                                        ))}
                                    </div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={isSubmittingDispense}
                                        className="h-8 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold"
                                    >
                                        {isSubmittingDispense ? 'Enregistrement...' : 'Valider la dispense'}
                                    </Button>
                                </div>
                            </form>
                        )}

                        {/* Bloc d'encaissement rapide intégré (Automatique selon priorité OU Ciblage manuel) */}
                        {canEncaisser && (
                            <form
                                onSubmit={handleEncaisserMaintenant}
                                className="p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/60 space-y-3"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <Receipt className="w-4 h-4 text-emerald-600" />
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-200">
                                            Encaisser un Versement Maintenant
                                        </h4>
                                    </div>

                                    {lignesImpayees.length > 0 && (
                                        <div className="inline-flex rounded-lg border border-emerald-300 dark:border-emerald-800 bg-white dark:bg-slate-900 p-0.5 text-[11px]">
                                            <button
                                                type="button"
                                                onClick={() => setModeAllocation('auto')}
                                                className={`px-2.5 py-1 rounded-md font-semibold transition-colors cursor-pointer ${
                                                    modeAllocation === 'auto'
                                                        ? 'bg-emerald-600 text-white'
                                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-300'
                                                }`}
                                            >
                                                Automatique (selon priorité)
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setModeAllocation('manuel')}
                                                className={`px-2.5 py-1 rounded-md font-semibold flex items-center gap-1 transition-colors cursor-pointer ${
                                                    modeAllocation === 'manuel'
                                                        ? 'bg-emerald-600 text-white'
                                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-300'
                                                }`}
                                            >
                                                <SlidersHorizontal className="w-3 h-3" />
                                                Cibler manuellement
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {soldeRestant <= 0 ? (
                                    <div className="p-3 rounded-lg bg-white dark:bg-slate-900 border border-emerald-200 dark:border-emerald-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                        <div className="text-slate-700 dark:text-slate-300">
                                            <strong className="text-emerald-700 dark:text-emerald-400">
                                                Reste à payer : 0 FCFA.
                                            </strong>{' '}
                                            Aucun versement supplémentaire ne peut être encaissé sur un solde nul.
                                            {(data?.frais_optionnels_niveau || []).some((o) => !o.est_souscrit) && (
                                                <span>
                                                    {' '}
                                                    Si un <strong>frais optionnel</strong> ci-dessus concerne cet élève,
                                                    cochez-le d'abord pour mettre à jour son montant total à verser.
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        {estCaisseCloturee && (
                                            <div className="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 font-medium">
                                                La caisse d'aujourd'hui ({formatDate(aujourdhuiIso)}) est clôturée. Pour
                                                enregistrer un reçu papier perçu antérieurement, sélectionnez sa date réelle
                                                ci-dessous.
                                            </div>
                                        )}

                                        {/* Date du versement (permet l'enregistrement des versements papier antérieurs) + Remise anticipée 4 % */}
                                        <div className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center p-2.5 rounded-lg bg-white/90 dark:bg-slate-900/90 border border-emerald-200/80 dark:border-emerald-900/60">
                                            <div className="sm:col-span-5 space-y-1">
                                                <Label
                                                    htmlFor="quick_date_versement"
                                                    className="text-xs flex items-center gap-1.5 font-semibold"
                                                >
                                                    <Calendar className="w-3.5 h-3.5 text-emerald-600" />
                                                    <span>
                                                        Date du versement <span className="text-red-500">*</span>
                                                    </span>
                                                </Label>
                                                <Input
                                                    id="quick_date_versement"
                                                    type="date"
                                                    min={dateMinVersement}
                                                    max={aujourdhuiIso}
                                                    value={formatDateForInput(dateVersement)}
                                                    onChange={(e) => {
                                                        const nouvelleDate = e.target.value;
                                                        setDateVersement(nouvelleDate);
                                                        if (
                                                            !nouvelleDate ||
                                                            !dateLimiteRentree ||
                                                            nouvelleDate > dateLimiteRentree
                                                        ) {
                                                            setAppliquerRemiseAnticipee(false);
                                                        }
                                                    }}
                                                    className="h-8 bg-white dark:bg-slate-900 text-xs"
                                                    required
                                                />
                                            </div>

                                            <div className="sm:col-span-7 text-[11px] text-slate-600 dark:text-slate-300">
                                                {remiseAnticipeeModele?.deja_attribuee ? (
                                                    <div className="p-2 rounded-md bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 font-medium">
                                                        {remiseAnticipeeModele.nom} déjà appliquée sur ce dossier.
                                                    </div>
                                                ) : estEligibleRemiseAnticipee && remiseAnticipeeModele ? (
                                                    <label className="flex items-start gap-2 p-2 rounded-md bg-amber-50/80 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            checked={appliquerRemiseAnticipee}
                                                            onChange={(e) =>
                                                                setAppliquerRemiseAnticipee(e.target.checked)
                                                            }
                                                            className="mt-0.5 rounded border-amber-400 text-emerald-600 focus:ring-emerald-500"
                                                        />
                                                        <div>
                                                            <div className="font-bold text-amber-900 dark:text-amber-200">
                                                                Appliquer « {remiseAnticipeeModele.nom} » (
                                                                -{Number(remiseAnticipeeModele.montant_estime).toLocaleString('fr-FR')}{' '}
                                                                FCFA)
                                                            </div>
                                                            <div className="text-[10px] text-amber-700 dark:text-amber-300">
                                                                Éligible car date de versement ≤{' '}
                                                                {formatDate(dateLimiteRentree)} (déduite sur :{' '}
                                                                {formatCibleImputationLabel(
                                                                    remiseAnticipeeModele.cible_imputation
                                                                )}
                                                                ).
                                                            </div>
                                                        </div>
                                                    </label>
                                                ) : (
                                                    <div className="text-slate-500 dark:text-slate-400">
                                                        Par défaut : date du jour ({formatDate(aujourdhuiIso)}). Modifiez
                                                        pour saisir un reçu papier antérieur (dates futures interdites).
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {modeAllocation === 'auto' ? (
                                            <>
                                                {/* Raccourcis rapides de montants en mode automatique */}
                                                {lignesImpayees.length > 0 && (
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        {lignesImpayees.slice(0, 3).map((l, idx) => (
                                                            <button
                                                                key={getLigneUniqueKey(l, idx)}
                                                                type="button"
                                                                onClick={() =>
                                                                    setMontantEncaissement(
                                                                        String(Number(l.montant_restant))
                                                                    )
                                                                }
                                                                className="px-2 py-0.5 rounded-md bg-white dark:bg-slate-900 border border-emerald-300 text-emerald-800 dark:text-emerald-300 text-[10px] font-semibold hover:bg-emerald-100 cursor-pointer"
                                                            >
                                                                {l.libelle} :{' '}
                                                                {Number(l.montant_restant).toLocaleString('fr-FR')} FCFA
                                                            </button>
                                                        ))}
                                                        {plafondResteSaisie > 0 && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setMontantEncaissement(String(plafondResteSaisie))
                                                                }
                                                                className="px-2 py-0.5 rounded-md bg-emerald-600 text-white text-[10px] font-bold hover:bg-emerald-700 cursor-pointer"
                                                            >
                                                                Tout solder ({plafondResteSaisie.toLocaleString('fr-FR')} FCFA)
                                                            </button>
                                                        )}
                                                    </div>
                                                )}

                                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                                    <div className="space-y-1">
                                                        <Label htmlFor="quick_montant" className="text-xs flex items-center justify-between">
                                                            <span>
                                                                Montant versé (FCFA) <span className="text-red-500">*</span>
                                                            </span>
                                                            <span className="text-[10px] font-normal text-slate-500">
                                                                Max : {plafondResteSaisie.toLocaleString('fr-FR')} F
                                                            </span>
                                                        </Label>
                                                        <Input
                                                            id="quick_montant"
                                                            type="number"
                                                            min="1"
                                                            max={plafondResteSaisie}
                                                            placeholder={`Max ${plafondResteSaisie.toLocaleString('fr-FR')}`}
                                                            value={montantEncaissement}
                                                            onChange={(e) => setMontantEncaissement(e.target.value)}
                                                            className={`h-9 bg-white dark:bg-slate-900 font-bold tabular-nums ${
                                                                montantSaisiBrut > plafondResteSaisie + 0.01
                                                                    ? 'border-red-500 text-red-600 focus-visible:ring-red-500'
                                                                    : 'text-emerald-700'
                                                            }`}
                                                        />
                                                    </div>

                                                    <div className="space-y-1">
                                                        <Label htmlFor="quick_mode" className="text-xs">
                                                            Mode de règlement
                                                        </Label>
                                                        <Select
                                                            value={modePaiement}
                                                            onValueChange={(v: ModePaiement) => setModePaiement(v)}
                                                        >
                                                            <SelectTrigger
                                                                id="quick_mode"
                                                                className="h-9 bg-white dark:bg-slate-900 text-xs"
                                                            >
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="especes">Espèces</SelectItem>
                                                                <SelectItem value="mobile_money_manuel">
                                                                    Orange / MTN Money
                                                                </SelectItem>
                                                                <SelectItem value="virement">
                                                                    Virement bancaire
                                                                </SelectItem>
                                                                <SelectItem value="cheque">Chèque</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-1">
                                                        <Label htmlFor="quick_payeur" className="text-xs">
                                                            Payeur / Parent
                                                        </Label>
                                                        <Input
                                                            id="quick_payeur"
                                                            placeholder="Nom du déposant"
                                                            value={payeurNom}
                                                            onChange={(e) => setPayeurNom(e.target.value)}
                                                            className="h-9 bg-white dark:bg-slate-900 text-xs"
                                                        />
                                                    </div>
                                                </div>
                                            </>
                                        ) : (
                                            <div className="space-y-2.5">
                                                <p className="text-[11px] text-slate-600 dark:text-slate-300">
                                                    Cochez la ou les rubriques précises que le parent souhaite régler
                                                    aujourd'hui (ex : payer uniquement le Bus ou une tranche précise) :
                                                </p>

                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    {lignesImpayees.map((l, idx) => {
                                                        const key = getLigneUniqueKey(l, idx);
                                                        const isChecked = ciblagesManuels[key] !== undefined;
                                                        return (
                                                            <div
                                                                key={key}
                                                                className={`p-2 rounded-lg border flex items-center justify-between gap-2 text-xs transition-colors ${
                                                                    isChecked
                                                                        ? 'bg-white dark:bg-slate-900 border-emerald-500 shadow-xs'
                                                                        : 'bg-white/60 dark:bg-slate-900/50 border-slate-200 dark:border-slate-800'
                                                                }`}
                                                            >
                                                                <label className="flex items-center gap-2 cursor-pointer min-w-0 flex-1">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={isChecked}
                                                                        onChange={() => toggleLigneManuelle(l, idx)}
                                                                        className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                                    />
                                                                    <div className="truncate">
                                                                        <div className="font-semibold text-slate-800 dark:text-slate-100 truncate">
                                                                            {l.libelle}
                                                                        </div>
                                                                        <div className="text-[10px] text-slate-500">
                                                                            Reste :{' '}
                                                                            {Number(l.montant_restant).toLocaleString(
                                                                                'fr-FR'
                                                                            )}{' '}
                                                                            FCFA
                                                                        </div>
                                                                    </div>
                                                                </label>
                                                                {isChecked && (
                                                                    <Input
                                                                        type="number"
                                                                        min="1"
                                                                        max={Number(l.montant_restant)}
                                                                        value={ciblagesManuels[key]}
                                                                        onChange={(e) =>
                                                                            updateMontantLigneManuelle(
                                                                                l,
                                                                                idx,
                                                                                e.target.value
                                                                            )
                                                                        }
                                                                        className="h-7 w-28 text-xs font-bold text-right tabular-nums bg-emerald-50/50 dark:bg-slate-950"
                                                                    />
                                                                )}
                                                            </div>
                                                        );
                                                    })}
                                                </div>

                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                    <div className="space-y-1">
                                                        <Label htmlFor="manual_mode" className="text-xs">
                                                            Mode de règlement
                                                        </Label>
                                                        <Select
                                                            value={modePaiement}
                                                            onValueChange={(v: ModePaiement) => setModePaiement(v)}
                                                        >
                                                            <SelectTrigger
                                                                id="manual_mode"
                                                                className="h-9 bg-white dark:bg-slate-900 text-xs"
                                                            >
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="especes">Espèces</SelectItem>
                                                                <SelectItem value="mobile_money_manuel">
                                                                    Orange / MTN Money
                                                                </SelectItem>
                                                                <SelectItem value="virement">
                                                                    Virement bancaire
                                                                </SelectItem>
                                                                <SelectItem value="cheque">Chèque</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-1">
                                                        <Label htmlFor="manual_payeur" className="text-xs">
                                                            Payeur / Parent
                                                        </Label>
                                                        <Input
                                                            id="manual_payeur"
                                                            placeholder="Nom du déposant"
                                                            value={payeurNom}
                                                            onChange={(e) => setPayeurNom(e.target.value)}
                                                            className="h-9 bg-white dark:bg-slate-900 text-xs"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {modePaiement !== 'especes' && (
                                            <div className="space-y-1">
                                                <Label htmlFor="quick_ref" className="text-xs">
                                                    Référence Mobile Money / Chèque / Bordereau
                                                </Label>
                                                <Input
                                                    id="quick_ref"
                                                    placeholder="N° de transaction..."
                                                    value={referencePaiement}
                                                    onChange={(e) => setReferencePaiement(e.target.value)}
                                                    className="h-8 bg-white dark:bg-slate-900 text-xs"
                                                />
                                            </div>
                                        )}

                                        {/* Alerte immédiate si le montant saisi dépasse le reste à payer */}
                                        {montantSaisiBrut > plafondResteSaisie + 0.01 && (
                                            <div className="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-300 dark:border-red-800 flex flex-wrap items-center justify-between gap-2 text-xs text-red-800 dark:text-red-200">
                                                <div className="flex items-center gap-1.5 font-semibold">
                                                    <AlertTriangle className="w-4 h-4 text-red-600 shrink-0" />
                                                    <span>
                                                        Le montant saisi ({montantSaisiBrut.toLocaleString('fr-FR')} FCFA)
                                                        dépasse le reste à payer ({plafondResteSaisie.toLocaleString('fr-FR')}{' '}
                                                        FCFA). Le versement doit obligatoirement être inférieur ou égal au
                                                        reste dû.
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => setMontantEncaissement(String(plafondResteSaisie))}
                                                    className="px-2.5 py-1 rounded bg-red-600 text-white text-[11px] font-bold hover:bg-red-700 cursor-pointer shrink-0"
                                                >
                                                    Ramener au reste exact ({plafondResteSaisie.toLocaleString('fr-FR')} F)
                                                </button>
                                            </div>
                                        )}

                                        {/* Aperçu clair de la ventilation et bouton de validation */}
                                        {ventilationsPrevisionnelles.length > 0 && (
                                            <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
                                                <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-600 dark:text-slate-300">
                                                    <span className="font-semibold">
                                                        {modeAllocation === 'manuel'
                                                            ? 'Affectation ciblée :'
                                                            : 'Imputation selon priorité :'}
                                                    </span>
                                                    {ventilationsPrevisionnelles.map((v, i) => (
                                                        <Badge
                                                            key={i}
                                                            variant="outline"
                                                            className="bg-white dark:bg-slate-900 text-[10px]"
                                                        >
                                                            {v.libelle} : {v.montant.toLocaleString('fr-FR')} FCFA
                                                        </Badge>
                                                    ))}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    {/* Sélecteur mémorisé : Reçu A4 (2 exemplaires/page) vs Ticket thermique 80mm */}
                                                    <div
                                                        className="inline-flex items-center rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-0.5 text-[11px]"
                                                        role="group"
                                                        aria-label="Format d'impression du reçu"
                                                    >
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setReceiptFormat('a4');
                                                                setPreferredReceiptFormat('a4');
                                                            }}
                                                            className={`px-2 py-1 rounded-sm font-semibold transition-colors cursor-pointer ${
                                                                receiptFormat === 'a4'
                                                                    ? 'bg-emerald-600 text-white'
                                                                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                                            }`}
                                                        >
                                                            Reçu A4
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setReceiptFormat('ticket');
                                                                setPreferredReceiptFormat('ticket');
                                                            }}
                                                            className={`px-2 py-1 rounded-sm font-semibold transition-colors cursor-pointer ${
                                                                receiptFormat === 'ticket'
                                                                    ? 'bg-emerald-600 text-white'
                                                                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                                            }`}
                                                        >
                                                            Ticket 80mm
                                                        </button>
                                                    </div>

                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={
                                                            isSubmittingPayment ||
                                                            estCaisseBloqueeSurDate ||
                                                            montantSaisiBrut > plafondResteSaisie + 0.01
                                                        }
                                                        className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold h-9 px-4"
                                                    >
                                                        <Wallet className="w-3.5 h-3.5 mr-1.5" />
                                                        {isSubmittingPayment
                                                            ? 'Validation...'
                                                            : `Valider (${montantTotalEffectif.toLocaleString('fr-FR')} FCFA) & ${receiptFormat === 'ticket' ? 'Ticket' : 'Reçu'}`}
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                    </>
                                )}
                            </form>
                        )}

                        {/* Panneau d'édition directe d'un versement (lorsque l'utilisateur clique sur « Modifier ») */}
                        {renderPanneauEditionVersement()}

                        {/* Accès rapide aux versements enregistrés de l'élève (permet de corriger une erreur de saisie sans changer d'onglet) */}
                        {versementsValidesEleve.length > 0 && (
                            <div className="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50 space-y-2 text-xs">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                        <History className="w-3.5 h-3.5 text-emerald-600" />
                                        Versements enregistrés sur ce dossier ({versementsValidesEleve.length})
                                    </span>
                                    {effectiveCanModifierVersement && (
                                        <span className="text-[11px] text-slate-500">
                                            Cliquez sur <strong>Modifier</strong> pour ajuster un montant saisi par erreur
                                        </span>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    {versementsValidesEleve.map((v) => (
                                        <div
                                            key={v.id}
                                            className={`p-2 rounded-lg border flex flex-wrap items-center justify-between gap-2 ${
                                                versementEnEdition?.id === v.id
                                                    ? 'bg-amber-50/90 dark:bg-amber-950/30 border-amber-400'
                                                    : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800'
                                            }`}
                                        >
                                            <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                                <span className="font-mono font-bold text-slate-800 dark:text-slate-100">
                                                    {v.reference_recu}
                                                </span>
                                                <span className="text-slate-500">
                                                    {formatDate(v.date_versement)}
                                                </span>
                                                <span className="font-extrabold text-emerald-700 dark:text-emerald-400 tabular-nums">
                                                    {Number(v.montant_total).toLocaleString('fr-FR')} FCFA
                                                </span>
                                                <div className="flex flex-wrap gap-1">
                                                    {v.ventilations.map((vent) => (
                                                        <Badge
                                                            key={vent.id}
                                                            variant="outline"
                                                            className="text-[10px] font-normal"
                                                        >
                                                            {vent.libelle} ({Number(vent.montant).toLocaleString('fr-FR')})
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-1">
                                                {effectiveCanModifierVersement && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7 text-[11px] px-2 flex items-center gap-1 border-amber-300 text-amber-800 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300"
                                                        onClick={() => handleOuvrirEditionVersement(v)}
                                                    >
                                                        <Pencil className="w-3 h-3" /> Modifier
                                                    </Button>
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 text-[11px] px-2 flex items-center gap-1 border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                                                    onClick={() =>
                                                        window.open(buildReceiptPrintUrl(v.id, 'a4'), '_blank')
                                                    }
                                                >
                                                    <Printer className="w-3 h-3" /> A4
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 text-[11px] px-2 flex items-center gap-1 border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200"
                                                    onClick={() =>
                                                        window.open(buildReceiptPrintUrl(v.id, 'ticket'), '_blank')
                                                    }
                                                >
                                                    <Receipt className="w-3 h-3" /> Ticket 80mm
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* ONGLET 2 : BOURSES, DISPENSES & ARRIÉRÉS */}
                {dossierTab === 'bourses' && (
                    <div className="space-y-5 pt-1 text-xs">
                        {/* SECTION A : DISPENSES FINANCIÈRES DE TRANCHE(S) */}
                        <div className="space-y-2.5">
                            <h4 className="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <FileCheck2 className="w-4 h-4 text-sky-600" />
                                Dispenses de tranches (élève inscrit en cours d'année, exonération...)
                            </h4>

                            {dispensesAttribuees.length === 0 ? (
                                <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500">
                                    Aucune dispense de tranche n'est accordée à cet élève (toutes les tranches du niveau
                                    lui sont exigibles).
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {dispensesAttribuees.map((d) => (
                                        <div
                                            key={d.id}
                                            className="p-3 rounded-lg bg-sky-50/70 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800 flex items-center justify-between gap-2"
                                        >
                                            <div>
                                                <div className="font-bold text-sky-950 dark:text-sky-200 flex flex-wrap items-center gap-2">
                                                    <span>{d.libelle_tranche}</span>
                                                    <Badge className="bg-sky-600 text-white text-[10px]">
                                                        {d.type_dispense === 'totale'
                                                            ? 'Dispense 100 %'
                                                            : 'Dispense partielle'}
                                                    </Badge>
                                                </div>
                                                <div className="text-[11px] text-slate-600 dark:text-slate-400">
                                                    Montant exonéré :{' '}
                                                    <strong className="text-sky-700 dark:text-sky-300">
                                                        -{Number(d.montant_dispense).toLocaleString('fr-FR')} FCFA
                                                    </strong>
                                                    {' • '}Motif : <strong>{d.motif}</strong>
                                                    {d.date_dispense && ` • Le ${formatDate(d.date_dispense)}`}
                                                </div>
                                            </div>
                                            {effectiveCanDispenses && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleRetirerDispense(d.id)}
                                                    className="text-red-600 hover:text-red-700 hover:bg-red-50 h-7 text-xs"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5 mr-1" /> Retirer
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {effectiveCanDispenses && lignesObligatoires.length > 0 && (
                                <form
                                    onSubmit={handleAccorderDispense}
                                    className="p-4 rounded-xl border border-sky-200/80 dark:border-sky-900/60 bg-sky-50/30 dark:bg-slate-900 space-y-3"
                                >
                                    <span className="font-bold text-slate-800 dark:text-slate-100 block">
                                        Accorder une dispense sur une tranche obligatoire
                                    </span>

                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                        <div className="space-y-1">
                                            <Label className="text-xs">Tranche à dispenser</Label>
                                            <Select
                                                value={selectedDispenseFraisId}
                                                onValueChange={(val) => {
                                                    setSelectedDispenseFraisId(val);
                                                    const found = lignesObligatoires.find(
                                                        (l) => String(l.id) === val
                                                    );
                                                    if (found && typeDispense === 'partielle') {
                                                        setMontantDispenseInput(
                                                            String(Number(found.montant_brut ?? found.montant))
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger className="h-9 bg-white dark:bg-slate-900 text-xs">
                                                    <SelectValue placeholder="Choisir la tranche..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {lignesObligatoires.map((l) => (
                                                        <SelectItem key={String(l.id)} value={String(l.id)}>
                                                            {l.libelle} (
                                                            {Number(l.montant_brut ?? l.montant).toLocaleString(
                                                                'fr-FR'
                                                            )}{' '}
                                                            FCFA)
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1">
                                            <Label className="text-xs">Type de dispense</Label>
                                            <Select
                                                value={typeDispense}
                                                onValueChange={(v: 'totale' | 'partielle') => setTypeDispense(v)}
                                            >
                                                <SelectTrigger className="h-9 bg-white dark:bg-slate-900 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="totale">Dispense totale (100 %)</SelectItem>
                                                    <SelectItem value="partielle">
                                                        Dispense partielle (montant libre)
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {typeDispense === 'partielle' ? (
                                            <div className="space-y-1">
                                                <Label className="text-xs">Montant dispensé (FCFA)</Label>
                                                <Input
                                                    type="number"
                                                    min="1"
                                                    max={ligneDispenseCourante?.montant_brut ?? undefined}
                                                    value={montantDispenseInput}
                                                    onChange={(e) => setMontantDispenseInput(e.target.value)}
                                                    placeholder="Ex: 15000"
                                                    className="h-9 bg-white dark:bg-slate-900 text-xs font-bold tabular-nums"
                                                />
                                            </div>
                                        ) : (
                                            <div className="space-y-1">
                                                <Label className="text-xs">
                                                    Motif <span className="text-red-500">*</span>
                                                </Label>
                                                <Input
                                                    value={motifDispense}
                                                    onChange={(e) => setMotifDispense(e.target.value)}
                                                    placeholder="Ex: Arrivée au 2ème trimestre"
                                                    className="h-9 bg-white dark:bg-slate-900 text-xs"
                                                />
                                            </div>
                                        )}
                                    </div>

                                    {typeDispense === 'partielle' && (
                                        <div className="space-y-1">
                                            <Label className="text-xs">
                                                Motif justificatif <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                value={motifDispense}
                                                onChange={(e) => setMotifDispense(e.target.value)}
                                                placeholder="Ex: Arrivée en cours de trimestre..."
                                                className="h-9 bg-white dark:bg-slate-900 text-xs"
                                            />
                                        </div>
                                    )}

                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex flex-wrap items-center gap-1">
                                            {MOTIFS_DISPENSE_RAPIDES.map((m) => (
                                                <button
                                                    key={m}
                                                    type="button"
                                                    onClick={() => setMotifDispense(m)}
                                                    className="px-2 py-0.5 rounded bg-white dark:bg-slate-900 border border-sky-200 text-sky-800 dark:text-sky-300 text-[10px] hover:bg-sky-100 cursor-pointer"
                                                >
                                                    {m}
                                                </button>
                                            ))}
                                        </div>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={isSubmittingDispense || !selectedDispenseFraisId}
                                            className="h-8 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold"
                                        >
                                            <FileCheck2 className="w-3.5 h-3.5 mr-1.5" />
                                            {isSubmittingDispense ? 'Validation...' : 'Accorder la dispense'}
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </div>

                        {/* SECTION B : BOURSES, BONUS & REMISES AVEC CHOIX DE LA TRANCHE CIBLE */}
                        <div className="space-y-2.5 pt-2 border-t border-slate-200 dark:border-slate-800">
                            <h4 className="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <Sparkles className="w-4 h-4 text-emerald-600" />
                                Réductions, Bourses & Bonus commerciaux accordés à cet élève
                            </h4>

                            {boursesAttribuees.length === 0 ? (
                                <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500">
                                    Aucune bourse ou remise (ex : paiement en avance) n'est appliquée à cet élève.
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {boursesAttribuees.map((b) => (
                                        <div
                                            key={b.id}
                                            className="p-3 rounded-lg bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between gap-2"
                                        >
                                            <div>
                                                <div className="font-bold text-emerald-950 dark:text-emerald-200 flex flex-wrap items-center gap-2">
                                                    <span>{b.nom}</span>
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {b.categorie}
                                                    </Badge>
                                                    {b.mode_calcul === 'pourcentage' && b.valeur !== undefined && (
                                                        <span className="text-[10px] font-medium text-emerald-700 dark:text-emerald-300">
                                                            ({b.valeur}% • {formatBaseCalculRemiseLabel(b.base_calcul)})
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-[11px] text-slate-600 dark:text-slate-400">
                                                    Imputation :{' '}
                                                    <strong>
                                                        {b.frais_cible_libelle
                                                            ? `Ligne précise (${b.frais_cible_libelle})`
                                                            : formatCibleImputationLabel(b.cible_imputation)}
                                                    </strong>{' '}
                                                    •{' '}
                                                    <strong className="text-emerald-700 dark:text-emerald-300">
                                                        -{b.montant_calcule.toLocaleString('fr-FR')} FCFA
                                                    </strong>
                                                    {b.date_attribution && ` • Le ${formatDate(b.date_attribution)}`}
                                                </div>
                                            </div>
                                            {canManageBourses && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleRetirerBourse(b.id)}
                                                    className="text-red-600 hover:text-red-700 hover:bg-red-50 h-7 text-xs"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5 mr-1" /> Retirer
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {canManageBourses && (
                                <form
                                    onSubmit={handleAttribuerBourse}
                                    className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-bold text-slate-800 dark:text-slate-100">
                                            Appliquer un Bonus / Bourse / Remise à cet élève
                                        </span>
                                        {onOpenCreateModeleBourse && (
                                            <button
                                                type="button"
                                                onClick={onOpenCreateModeleBourse}
                                                className="text-xs font-semibold text-emerald-600 hover:underline flex items-center gap-1 cursor-pointer"
                                            >
                                                <Plus className="w-3.5 h-3.5" /> Créer un nouveau modèle
                                            </button>
                                        )}
                                    </div>

                                    {boursesRemises.length === 0 ? (
                                        <div className="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 flex items-center justify-between gap-2">
                                            <span>
                                                Aucun modèle de remise (ex : Paiement en avance -3 000 FCFA) n'a encore
                                                été créé.
                                            </span>
                                            {onOpenCreateModeleBourse && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={onOpenCreateModeleBourse}
                                                    className="bg-emerald-600 hover:bg-emerald-700 text-white h-7 text-xs shrink-0"
                                                >
                                                    Créer maintenant
                                                </Button>
                                            )}
                                        </div>
                                    ) : (
                                        <>
                                            <div className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                                            <div className="sm:col-span-5 space-y-1">
                                                <Label className="text-xs">Choisir la remise ou bourse</Label>
                                                <Select value={selectedBourseId} onValueChange={setSelectedBourseId}>
                                                    <SelectTrigger className="h-9 text-xs">
                                                        <SelectValue placeholder="Sélectionner..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {boursesRemises.map((br) => (
                                                            <SelectItem key={br.id} value={String(br.id)}>
                                                                {br.nom} —{' '}
                                                                {br.mode_calcul === 'pourcentage'
                                                                    ? `${br.valeur}%`
                                                                    : `-${Number(br.valeur).toLocaleString(
                                                                          'fr-FR'
                                                                      )} FCFA`}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="sm:col-span-4 space-y-1">
                                                <Label className="text-xs">Déduire sur quelle tranche ?</Label>
                                                <Select
                                                    value={selectedBourseCible}
                                                    onValueChange={setSelectedBourseCible}
                                                >
                                                    <SelectTrigger className="h-9 text-xs">
                                                        <SelectValue placeholder="Choisir la tranche..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="modele_defaut">
                                                            Cible par défaut du modèle
                                                            {bourseModeleCourant
                                                                ? ` (${formatCibleImputationLabel(bourseModeleCourant.cible_imputation)})`
                                                                : ''}
                                                        </SelectItem>
                                                        {lignesObligatoires.map((l) => (
                                                            <SelectItem
                                                                key={`frais-${l.id}`}
                                                                value={`frais:${l.id}`}
                                                            >
                                                                {l.libelle} (
                                                                {Number(l.montant_brut ?? l.montant).toLocaleString(
                                                                    'fr-FR'
                                                                )}{' '}
                                                                FCFA)
                                                            </SelectItem>
                                                        ))}
                                                        <SelectItem value="derniere_tranche">
                                                            Dernière tranche obligatoire
                                                        </SelectItem>
                                                        <SelectItem value="proportionnelle">
                                                            Répartition proportionnelle
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="sm:col-span-3">
                                                <Button
                                                    type="submit"
                                                    disabled={isSubmittingBourse || !selectedBourseId}
                                                    className="w-full h-9 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold"
                                                >
                                                    <Award className="w-3.5 h-3.5 mr-1.5" />
                                                    {isSubmittingBourse ? 'Application...' : 'Appliquer'}
                                                </Button>
                                            </div>
                                        </div>
                                        {bourseModeleCourant && apercuAttributionBourse && (
                                            <div className="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/30 px-3 py-2 text-[11px] text-emerald-900 dark:text-emerald-200 flex flex-wrap items-center justify-between gap-2">
                                                <span>
                                                    Aperçu :{' '}
                                                    {bourseModeleCourant.mode_calcul === 'pourcentage'
                                                        ? `${bourseModeleCourant.valeur}% calculé sur ${formatBaseCalculRemiseLabel(bourseModeleCourant.base_calcul).toLowerCase()} (${apercuAttributionBourse.assietteCalcul.toLocaleString('fr-FR')} FCFA)`
                                                        : 'Montant fixe'}{' '}
                                                    → déduit sur <strong>{apercuAttributionBourse.libelleCible}</strong>
                                                </span>
                                                <strong className="text-emerald-700 dark:text-emerald-300 tabular-nums">
                                                    -{apercuAttributionBourse.montantCalcule.toLocaleString('fr-FR')} FCFA
                                                </strong>
                                            </div>
                                        )}
                                        </>
                                    )}
                                </form>
                            )}
                        </div>

                        {/* SECTION C : AJUSTEMENT DES ARRIÉRÉS N-1 */}
                        {canManageArrieres && (
                            <div className="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                                <div className="space-y-1 flex-1">
                                    <Label htmlFor="arrieres_input" className="text-xs font-semibold">
                                        Arriérés reportés des années précédentes (FCFA)
                                    </Label>
                                    <Input
                                        id="arrieres_input"
                                        type="number"
                                        min="0"
                                        value={arrieresInput}
                                        onChange={(e) => setArrieresInput(e.target.value)}
                                        className="h-8 text-xs bg-white dark:bg-slate-900 max-w-xs tabular-nums"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleSaveArrieres}
                                    className="h-8 text-xs"
                                >
                                    Enregistrer l'arriéré
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {/* ONGLET 3 : OPTIONS (BUS, CANTINE, ACTIVITÉS...) */}
                {dossierTab === 'options' && (
                    <div className="space-y-4 pt-1 text-xs">
                        <div className="space-y-2">
                            <h4 className="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <Bus className="w-4 h-4 text-amber-600" />
                                Services optionnels souscrits par cet élève
                            </h4>

                            {souscriptionsActives.length === 0 ? (
                                <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500">
                                    Cet élève n'est inscrit à aucun frais optionnel (Bus, Cantine, Internat...).
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {souscriptionsActives.map((s) => (
                                        <div
                                            key={s.id}
                                            className="p-3 rounded-lg bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 flex items-center justify-between gap-2"
                                        >
                                            <div>
                                                <div className="font-bold text-amber-950 dark:text-amber-200">
                                                    {s.libelle}
                                                </div>
                                                <div className="text-[11px] text-slate-600 dark:text-slate-400">
                                                    Montant ajouté au dossier :{' '}
                                                    <strong>{s.montant.toLocaleString('fr-FR')} FCFA</strong>
                                                    {s.date_souscription &&
                                                        ` • Souscrit le ${formatDate(s.date_souscription)}`}
                                                </div>
                                            </div>
                                            {canManageOptions && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleResilierOption(s.id)}
                                                    className="text-red-600 hover:text-red-700 hover:bg-red-50 h-7 text-xs"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5 mr-1" /> Résilier
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {canManageOptions && (
                            <form
                                onSubmit={handleSouscrireOption}
                                className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3"
                            >
                                <span className="font-bold text-slate-800 dark:text-slate-100 block">
                                    Inscrire cet élève à un service optionnel
                                </span>

                                {optionsApplicables.length === 0 ? (
                                    <p className="text-slate-500">
                                        Aucun tarif optionnel (ex : Transport / Bus, Cantine) n'est encore paramétré
                                        dans la Grille Tarifaire pour ce niveau.
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 items-end">
                                        <div className="space-y-1">
                                            <Label className="text-xs">Service optionnel</Label>
                                            <Select
                                                value={selectedOptionId}
                                                onValueChange={(val) => {
                                                    setSelectedOptionId(val);
                                                    const opt = optionsApplicables.find((o) => String(o.id) === val);
                                                    if (opt) setMontantOptionCustom(String(Number(opt.montant)));
                                                }}
                                            >
                                                <SelectTrigger className="h-9 text-xs">
                                                    <SelectValue placeholder="Choisir une option..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {optionsApplicables.map((opt) => (
                                                        <SelectItem key={opt.id} value={String(opt.id)}>
                                                            {opt.type_frais?.libelle}{' '}
                                                            {opt.libelle_tranche ? `(${opt.libelle_tranche})` : ''} —{' '}
                                                            {Number(opt.montant).toLocaleString('fr-FR')} FCFA
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1">
                                            <Label className="text-xs">Montant appliqué (FCFA)</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                placeholder="Montant standard"
                                                value={montantOptionCustom}
                                                onChange={(e) => setMontantOptionCustom(e.target.value)}
                                                className="h-9 text-xs tabular-nums"
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={isSubmittingOption || !selectedOptionId}
                                            className="h-9 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold"
                                        >
                                            <Plus className="w-3.5 h-3.5 mr-1" /> Activer l'option
                                        </Button>
                                    </div>
                                )}
                            </form>
                        )}
                    </div>
                )}

                {/* ONGLET 4 : HISTORIQUE DES REÇUS */}
                {dossierTab === 'historique' && (
                    <div className="pt-1 space-y-3">
                        {renderPanneauEditionVersement()}
                        <div className="border border-slate-200 dark:border-slate-800 rounded-xl overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead className="bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                                    {historiqueTable.getHeaderGroups().map((hg) => (
                                        <tr key={hg.id}>
                                            {hg.headers.map((header) => (
                                                <th key={header.id} className="p-2.5 font-semibold text-left">
                                                    {header.isPlaceholder
                                                        ? null
                                                        : flexRender(
                                                              header.column.columnDef.header,
                                                              header.getContext()
                                                          )}
                                                </th>
                                            ))}
                                        </tr>
                                    ))}
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {historiqueTable.getRowModel().rows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={historiqueColumns.length}
                                                className="p-4 text-center text-slate-500"
                                            >
                                                Aucun versement enregistré pour cet élève.
                                            </td>
                                        </tr>
                                    ) : (
                                        historiqueTable.getRowModel().rows.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="hover:bg-slate-50 dark:hover:bg-slate-900/40"
                                            >
                                                {row.getVisibleCells().map((cell) => (
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
                )}

                <DialogFooter className="mt-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <Button type="button" variant="outline" size="sm" onClick={onClose}>
                        Fermer le dossier
                    </Button>
                </DialogFooter>
            </DialogContent>
            )}
        </Dialog>
    );
}

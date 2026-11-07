import React, { useCallback, useEffect, useMemo, useState } from 'react';
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
    BourseRemise,
    CibleImputationRemise,
    CompteFinancierEleve,
    FraisScolaire,
    ModePaiement,
    formatCibleImputationLabel,
} from '@/types/finance';
import { formatDate } from '@/lib/utils';
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
    recu_par: string | null;
    statut: 'valide' | 'annule';
    motif_annulation: string | null;
    ventilations: {
        id: number;
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
    lignes: LigneEcheancier[];
    bourses_attribuees?: BourseAttribueeItem[];
    dispenses_attribuees?: DispenseAttribueeItem[];
    souscriptions_actives?: SouscriptionActiveItem[];
    versements?: VersementHistoriqueItem[];
    total_exigible: number;
    total_dispense?: number;
    total_paye: number;
    solde_restant: number;
    remise_bourse?: number;
    arrieres_anterieurs?: number;
    montant_echu_en_retard: number;
    statut_financier: string;
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
    canManageBourses = true,
    canManageDispenses,
    canManageOptions = true,
    canConfigureFrais = false,
    canManageArrieres = false,
    onOpenCreateModeleBourse,
}: DossierFinancierModalProps) {
    const effectiveCanDispenses = canManageDispenses ?? canManageBourses;
    const [dossierTab, setDossierTab] = useState<'echeancier' | 'bourses' | 'options' | 'historique'>('echeancier');
    const [data, setData] = useState<DossierData | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    // États d'encaissement rapide intégré (mode automatique par priorité vs ciblage manuel)
    const [modeAllocation, setModeAllocation] = useState<'auto' | 'manuel'>('auto');
    const [montantEncaissement, setMontantEncaissement] = useState<string>('');
    const [ciblagesManuels, setCiblagesManuels] = useState<Record<string, string>>({});
    const [modePaiement, setModePaiement] = useState<ModePaiement>('especes');
    const [referencePaiement, setReferencePaiement] = useState<string>('');
    const [payeurNom, setPayeurNom] = useState<string>('');
    const [payeurTelephone, setPayeurTelephone] = useState<string>('');
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

    /**
     * Recharge les données fraîches du dossier financier de l'élève depuis le backend.
     */
    const fetchDossier = useCallback((compteId: number) => {
        setIsLoading(true);
        fetch(`/administration/finances/comptes/${compteId}/dossier`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => res.json())
            .then((resData: DossierData) => {
                setData(resData);
                setArrieresInput(String(Number(resData.arrieres_anterieurs ?? 0)));
                setIsLoading(false);
            })
            .catch(() => {
                setIsLoading(false);
            });
    }, []);

    /**
     * Initialisation à l'ouverture du dossier d'un élève.
     */
    useEffect(() => {
        if (!isOpen || !compte) {
            setData(null);
            return;
        }

        setDossierTab('echeancier');
        setModeAllocation('auto');
        setMontantEncaissement('');
        setCiblagesManuels({});
        setModePaiement('especes');
        setReferencePaiement('');
        setPayeurNom(
            compte.eleve?.nom_tuteur ||
                compte.eleve?.nom_pere ||
                compte.eleve?.nom_mere ||
                ''
        );
        setPayeurTelephone(compte.eleve?.telephone_parent || '');
        setShowInlineDispenseForm(false);
        setSelectedDispenseFraisId('');
        setTypeDispense('totale');
        setMontantDispenseInput('');
        setMotifDispense('');
        setSelectedBourseId(boursesRemises[0] ? String(boursesRemises[0].id) : '');
        setSelectedBourseCible('modele_defaut');
        setMotifBourse('');
        setSelectedOptionId('');
        setMontantOptionCustom('');
        setObservationsOption('');

        fetchDossier(compte.id);
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

        if (reliquat > 0) {
            vents.push({
                libelle: vents.length > 0 ? 'Avance / Complément scolarité' : 'Versement scolarité',
                montant: reliquat,
                type_frais_id: null,
                frais_scolaire_id: null,
            });
        }

        return vents;
    }, [modeAllocation, montantEncaissement, ciblagesManuels, lignesImpayees]);

    /**
     * Montant total effectif du versement (saisi en mode auto, ou somme des lignes cochées en mode manuel).
     */
    const montantTotalEffectif = useMemo(() => {
        if (modeAllocation === 'manuel') {
            return ventilationsPrevisionnelles.reduce((acc, v) => acc + v.montant, 0);
        }
        const parsed = parseFloat(montantEncaissement);
        return isNaN(parsed) || parsed <= 0 ? 0 : parsed;
    }, [modeAllocation, montantEncaissement, ventilationsPrevisionnelles]);

    /**
     * Enregistre le versement directement depuis le dossier de l'élève et actualise l'échéancier.
     */
    const handleEncaisserMaintenant = (e: React.FormEvent) => {
        e.preventDefault();
        if (!compte) return;

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
                    setReferencePaiement('');
                    fetchDossier(compte.id);

                    const flash = (page.props as Record<string, unknown>)?.flash as
                        | { dernier_recu_id?: number }
                        | undefined;
                    if (flash?.dernier_recu_id) {
                        window.open(`/administration/finances/versements/${flash.dernier_recu_id}/recu`, '_blank');
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
        });
    };

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
                header: () => <div className="text-right">Reçu</div>,
                cell: ({ row }) => (
                    <div className="text-right">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-7 text-xs flex items-center gap-1 ml-auto border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                            onClick={() =>
                                window.open(
                                    `/administration/finances/versements/${row.original.id}/recu`,
                                    '_blank'
                                )
                            }
                        >
                            <Printer className="w-3.5 h-3.5" /> Reçu PDF
                        </Button>
                    </div>
                ),
            },
        ],
        []
    );

    const echeancierTable = useReactTable({
        data: data?.lignes || [],
        columns: echeancierColumns,
        getCoreRowModel: getCoreRowModel(),
    });

    const historiqueTable = useReactTable({
        data: data?.versements || [],
        columns: historiqueColumns,
        getCoreRowModel: getCoreRowModel(),
    });

    if (!compte) return null;

    const totalExigible = Number(data?.total_exigible ?? compte.montant_net_exigible);
    const totalDispense = Number(data?.total_dispense ?? compte.total_dispense ?? 0);
    const totalPaye = Number(data?.total_paye ?? compte.total_paye);
    const soldeRestant = Number(data?.solde_restant ?? compte.solde_restant);
    const remiseTotale = Number(data?.remise_bourse ?? compte.remise_bourse ?? 0);
    const retardEchu = Number(data?.montant_echu_en_retard ?? 0);
    const boursesAttribuees = data?.bourses_attribuees || [];
    const dispensesAttribuees = data?.dispenses_attribuees || [];
    const souscriptionsActives = data?.souscriptions_actives || [];
    const versementsEleve = data?.versements || [];

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
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
                                window.open(`/administration/finances/comptes/${compte.id}/fiche-pdf`, '_blank')
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
                            {compte.eleve?.nom} {compte.eleve?.prenom}
                        </div>
                        <div className="text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span>
                                Matricule :{' '}
                                <strong className="font-mono text-slate-800 dark:text-slate-200">
                                    {compte.eleve?.matricule}
                                </strong>
                            </span>
                            <span>•</span>
                            <span>
                                Classe :{' '}
                                <strong className="text-emerald-700 dark:text-emerald-400">
                                    {compte.inscription?.classe?.nom ?? 'N/A'}
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
                                Parent : {compte.eleve?.nom_tuteur || compte.eleve?.nom_pere || 'Non renseigné'} (
                                {compte.eleve?.telephone_parent || 'N/A'})
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

                                    {!estCaisseCloturee && lignesImpayees.length > 0 && (
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

                                {estCaisseCloturee ? (
                                    <p className="text-xs text-amber-800 font-medium">
                                        La caisse de ce jour a déjà été clôturée. Aucun nouvel encaissement ne peut
                                        être saisi aujourd'hui.
                                    </p>
                                ) : (
                                    <>
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
                                                        {soldeRestant > 0 && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setMontantEncaissement(String(soldeRestant))
                                                                }
                                                                className="px-2 py-0.5 rounded-md bg-emerald-600 text-white text-[10px] font-bold hover:bg-emerald-700 cursor-pointer"
                                                            >
                                                                Tout solder ({soldeRestant.toLocaleString('fr-FR')} FCFA)
                                                            </button>
                                                        )}
                                                    </div>
                                                )}

                                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                                    <div className="space-y-1">
                                                        <Label htmlFor="quick_montant" className="text-xs">
                                                            Montant versé (FCFA) <span className="text-red-500">*</span>
                                                        </Label>
                                                        <Input
                                                            id="quick_montant"
                                                            type="number"
                                                            min="1"
                                                            placeholder="Ex: 25000"
                                                            value={montantEncaissement}
                                                            onChange={(e) => setMontantEncaissement(e.target.value)}
                                                            className="h-9 bg-white dark:bg-slate-900 font-bold text-emerald-700 tabular-nums"
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
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={isSubmittingPayment}
                                                    className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold h-9 px-4"
                                                >
                                                    <Wallet className="w-3.5 h-3.5 mr-1.5" />
                                                    {isSubmittingPayment
                                                        ? 'Validation...'
                                                        : `Valider (${montantTotalEffectif.toLocaleString('fr-FR')} FCFA) & Reçu`}
                                                </Button>
                                            </div>
                                        )}
                                    </>
                                )}
                            </form>
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
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="modele_defaut">
                                                            Selon modèle (
                                                            {formatCibleImputationLabel(
                                                                bourseModeleCourant?.cible_imputation
                                                            )}
                                                            )
                                                        </SelectItem>
                                                        <SelectItem value="derniere_tranche">
                                                            Dernière tranche obligatoire
                                                        </SelectItem>
                                                        <SelectItem value="tranche_1">1ère tranche</SelectItem>
                                                        <SelectItem value="tranche_2">2ème tranche</SelectItem>
                                                        <SelectItem value="tranche_3">3ème tranche</SelectItem>
                                                        <SelectItem value="proportionnelle">
                                                            Répartition proportionnelle
                                                        </SelectItem>
                                                        {lignesObligatoires.map((l) => (
                                                            <SelectItem
                                                                key={`frais-${l.id}`}
                                                                value={`frais:${l.id}`}
                                                            >
                                                                Ligne précise : {l.libelle}
                                                            </SelectItem>
                                                        ))}
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
                    <div className="pt-1">
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
        </Dialog>
    );
}

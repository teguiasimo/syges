import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    Clock,
    DollarSign,
    FileText,
    GraduationCap,
    Lock,
    Save,
    Shield,
    ShieldAlert,
    Sliders,
    Sparkles,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

/**
 * Interface représentant les données complètes d'un Établissement scolaire.
 */
interface EtablissementData {
    id: number;
    institution_id: number;
    code: string;
    nom: string;
    nom_en?: string | null;
    statut_juridique: 'Public' | 'Prive_Laic' | 'Prive_Confessionnel';
    ordre_enseignement: 'Maternelle' | 'Primaire' | 'Secondaire_General' | 'Secondaire_Technique' | 'Polyvalent';
    decret_creation?: string | null;
    nom_chef_etablissement?: string | null;
    email?: string | null;
    telephone_1?: string | null;
    telephone_2?: string | null;
    adresse?: string | null;
    logo?: string | null;
    mode_bulletin_defaut: 'sans_competences' | 'avec_competences';
    type_calcul_trimestre: 'arithmetique' | 'pondere';
    seuil_absences_decheance_mentions: number;
    verrou_solvabilite_actif: boolean;
    bonus_paiement_anticipe_actif: boolean;
    montant_bonus_anticipe?: number | null;
    date_limite_bonus?: string | null;
    institution?: {
        id: number;
        nom: string;
        code: string;
    } | null;
}

/**
 * Propriétés transmises à la page de configuration de l'établissement.
 */
interface EtablissementSettingsProps {
    etablissement: EtablissementData;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

/**
 * Page dédiée de configuration et de paramétrage des règles de l'établissement scolaire (Option B).
 *
 * Permet de définir :
 * 1. Le format de bulletin par défaut (Standard sans compétences par défaut / APC avec compétences).
 * 2. Le type de calcul des moyennes trimestrielles (arithmétique ou pondéré).
 * 3. Les règles disciplinaires (seuil d'absences pour déchéance des mentions).
 * 4. Les contraintes financières (verrou de solvabilité et bonus paiement anticipé).
 * 5. L'identité et les coordonnées administratives du campus.
 *
 * @param {EtablissementSettingsProps} props Données de l'établissement et messages flash.
 * @returns {JSX.Element}
 */
export default function EtablissementSettings({ etablissement, flash }: EtablissementSettingsProps) {
    // Référence de déduplication pour éviter les doubles déclenchements de toast
    const lastToastRef = React.useRef<string | null>(null);

    // Onglet actif
    const [activeTab, setActiveTab] = useState<string>('pedagogie');

    // Gestion des toasts de notification en haut à droite (fond vert pour le succès)
    useEffect(() => {
        if (flash?.success && lastToastRef.current !== flash.success) {
            lastToastRef.current = flash.success;
            toast.success(flash.success, {
                id: 'etablissement-settings-success',
                style: {
                    backgroundColor: '#10b981',
                    color: '#ffffff',
                    border: '1px solid #059669',
                },
            });
        }
        if (flash?.error && lastToastRef.current !== flash.error) {
            lastToastRef.current = flash.error;
            toast.error(flash.error, {
                id: 'etablissement-settings-error',
            });
        }
    }, [flash]);

    // Initialisation du formulaire Inertia avec toutes les options paramétrables
    const { data, setData, put, processing, errors, isDirty } = useForm({
        // Identité & Coordonnées
        code: etablissement.code || '',
        nom: etablissement.nom || '',
        nom_en: etablissement.nom_en || '',
        statut_juridique: etablissement.statut_juridique || 'Prive_Confessionnel',
        ordre_enseignement: etablissement.ordre_enseignement || 'Secondaire_General',
        decret_creation: etablissement.decret_creation || '',
        nom_chef_etablissement: etablissement.nom_chef_etablissement || '',
        email: etablissement.email || '',
        telephone_1: etablissement.telephone_1 || '',
        telephone_2: etablissement.telephone_2 || '',
        adresse: etablissement.adresse || '',

        // Pédagogie & Format des Bulletins (Module 10)
        mode_bulletin_defaut: etablissement.mode_bulletin_defaut || 'sans_competences',
        type_calcul_trimestre: etablissement.type_calcul_trimestre || 'arithmetique',

        // Discipline & Assiduité (Module 12 - Seuil MINESEC Cameroun par défaut : 40h)
        seuil_absences_decheance_mentions: etablissement.seuil_absences_decheance_mentions ?? 40,

        // Finances & Solvabilité (Module 11)
        verrou_solvabilite_actif: Boolean(etablissement.verrou_solvabilite_actif),
        bonus_paiement_anticipe_actif: Boolean(etablissement.bonus_paiement_anticipe_actif),
        montant_bonus_anticipe: etablissement.montant_bonus_anticipe ?? 3000,
        date_limite_bonus: etablissement.date_limite_bonus ? String(etablissement.date_limite_bonus).substring(0, 10) : '',
    });

    /**
     * Soumet les modifications pour enregistrement.
     *
     * @param {React.FormEvent} e Événement de soumission.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/administration/etablissements/${etablissement.id}/settings`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`Paramètres & Règles - ${etablissement.nom}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 sm:p-8 max-w-6xl mx-auto w-full">
                {/* En-tête avec navigation de retour et boutons d'action */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border/70 pb-5">
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Link
                                href="/administration/institution"
                                className="inline-flex items-center gap-1 hover:text-foreground transition-colors font-medium cursor-pointer"
                            >
                                <ArrowLeft className="size-3.5" />
                                <span>Retour à l'arborescence</span>
                            </Link>
                            <span>/</span>
                            <span className="font-semibold text-foreground">{etablissement.nom}</span>
                        </div>

                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                                <Sliders className="size-7 text-indigo-600 dark:text-indigo-400" />
                                Configuration & Règles de Gestion
                            </h1>
                            <Badge variant="outline" className="font-mono text-xs px-2.5 py-0.5 border-indigo-200 text-indigo-700 bg-indigo-50 dark:bg-indigo-950/50 dark:text-indigo-300">
                                {etablissement.code}
                            </Badge>
                        </div>
                        <p className="text-xs sm:text-sm text-muted-foreground">
                            Ajustez les formats de bulletins scolaires, règles de calcul, seuils disciplinaires et verrous financiers de ce campus.
                        </p>
                    </div>

                    {/* Actions de sauvegarde */}
                    <div className="flex items-center gap-2.5 shrink-0">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="cursor-pointer text-xs h-9"
                        >
                            <Link href="/administration/institution">
                                Annuler
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            onClick={handleSubmit}
                            disabled={processing || !isDirty}
                            className="gap-2 cursor-pointer text-xs h-9 bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm"
                        >
                            <Save className="size-4" />
                            <span>{processing ? 'Enregistrement...' : 'Enregistrer les paramètres'}</span>
                        </Button>
                    </div>
                </div>

                {/* Formulaire complet articulé par onglets */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                        <TabsList className="grid grid-cols-2 md:grid-cols-4 h-11 w-full bg-muted/60 p-1 rounded-xl">
                            <TabsTrigger value="pedagogie" className="gap-2 text-xs font-semibold">
                                <FileText className="size-4 text-emerald-600" />
                                <span>Bulletins & Notes</span>
                            </TabsTrigger>
                            <TabsTrigger value="discipline" className="gap-2 text-xs font-semibold">
                                <ShieldAlert className="size-4 text-amber-600" />
                                <span>Discipline & Assiduité</span>
                            </TabsTrigger>
                            <TabsTrigger value="finances" className="gap-2 text-xs font-semibold">
                                <DollarSign className="size-4 text-blue-600" />
                                <span>Caisse & Solvabilité</span>
                            </TabsTrigger>
                            <TabsTrigger value="identite" className="gap-2 text-xs font-semibold">
                                <Building2 className="size-4 text-indigo-600" />
                                <span>Identité du Campus</span>
                            </TabsTrigger>
                        </TabsList>

                        {/* =========================================================================
                         * ONGLET 1 : PÉDAGOGIE & BULLETINS (MODULE 10)
                         * ========================================================================= */}
                        <TabsContent value="pedagogie" className="space-y-6 pt-4">
                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                            <GraduationCap className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base sm:text-lg">Format Officiel des Bulletins Scolaires</CardTitle>
                                            <CardDescription className="text-xs sm:text-sm">
                                                Choisissez le gabarit de mise en page par défaut appliqué lors de la génération des bulletins PDF.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Option 1 : Standard sans compétences (Par défaut) */}
                                        <div
                                            onClick={() => setData('mode_bulletin_defaut', 'sans_competences')}
                                            className={`relative p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                data.mode_bulletin_defaut === 'sans_competences'
                                                    ? 'border-emerald-600 bg-emerald-50/40 dark:bg-emerald-950/20 dark:border-emerald-500 shadow-xs'
                                                    : 'border-border/70 hover:border-border bg-card'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-sm text-foreground">Standard sans compétences</span>
                                                        <Badge className="bg-emerald-600 text-white text-[10px] px-1.5 py-0 font-medium">
                                                            Recommandé
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground leading-relaxed">
                                                        Modèle classique standard MINESEC / Cameroun. Présente les matières groupées, coefficients, notes séquentielles sur 20, totaux, moyennes trimestrielles, rangs et appréciations générales.
                                                    </p>
                                                </div>
                                                <div className={`size-5 rounded-full border flex items-center justify-center shrink-0 ${
                                                    data.mode_bulletin_defaut === 'sans_competences'
                                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                                        : 'border-muted-foreground/40'
                                                }`}>
                                                    {data.mode_bulletin_defaut === 'sans_competences' && <CheckCircle2 className="size-3.5" />}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Option 2 : Avec compétences (APC) */}
                                        <div
                                            onClick={() => setData('mode_bulletin_defaut', 'avec_competences')}
                                            className={`relative p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                data.mode_bulletin_defaut === 'avec_competences'
                                                    ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/20 dark:border-indigo-500 shadow-xs'
                                                    : 'border-border/70 hover:border-border bg-card'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-sm text-foreground">Approche Par Compétences (APC)</span>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground leading-relaxed">
                                                        Modèle axé sur l'évaluation par compétences professionnelles et académiques. Intègre les compétences visées, niveaux d'acquisition (Expert, Acquis, En cours, Non acquis) et appréciations modulaires.
                                                    </p>
                                                </div>
                                                <div className={`size-5 rounded-full border flex items-center justify-center shrink-0 ${
                                                    data.mode_bulletin_defaut === 'avec_competences'
                                                        ? 'border-indigo-600 bg-indigo-600 text-white'
                                                        : 'border-muted-foreground/40'
                                                }`}>
                                                    {data.mode_bulletin_defaut === 'avec_competences' && <CheckCircle2 className="size-3.5" />}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    {errors.mode_bulletin_defaut && (
                                        <p className="text-xs text-rose-500 font-medium">{errors.mode_bulletin_defaut}</p>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                                            <Sliders className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base sm:text-lg">Règle de Calcul des Moyennes Trimestrielles</CardTitle>
                                            <CardDescription className="text-xs sm:text-sm">
                                                Détermine comment les notes des différentes séquences sont consolidées pour chaque trimestre.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div
                                            onClick={() => setData('type_calcul_trimestre', 'arithmetique')}
                                            className={`p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                data.type_calcul_trimestre === 'arithmetique'
                                                    ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/20 dark:border-blue-500 shadow-xs'
                                                    : 'border-border/70 hover:border-border bg-card'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="space-y-1">
                                                    <span className="font-bold text-sm text-foreground">Moyenne Arithmétique Simple</span>
                                                    <p className="text-xs text-muted-foreground leading-relaxed">
                                                        Chaque séquence a le même poids. La note du trimestre est la moyenne directe : (Séquence 1 + Séquence 2) ÷ 2.
                                                    </p>
                                                </div>
                                                <div className={`size-5 rounded-full border flex items-center justify-center shrink-0 ${
                                                    data.type_calcul_trimestre === 'arithmetique'
                                                        ? 'border-blue-600 bg-blue-600 text-white'
                                                        : 'border-muted-foreground/40'
                                                }`}>
                                                    {data.type_calcul_trimestre === 'arithmetique' && <CheckCircle2 className="size-3.5" />}
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            onClick={() => setData('type_calcul_trimestre', 'pondere')}
                                            className={`p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                data.type_calcul_trimestre === 'pondere'
                                                    ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/20 dark:border-blue-500 shadow-xs'
                                                    : 'border-border/70 hover:border-border bg-card'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="space-y-1">
                                                    <span className="font-bold text-sm text-foreground">Moyenne Pondérée par Pourcentage</span>
                                                    <p className="text-xs text-muted-foreground leading-relaxed">
                                                        Permet d'appliquer des coefficients distincts selon la séquence (ex: 40% pour l'évaluation formative, 60% pour la synthèse trimestrielle).
                                                    </p>
                                                </div>
                                                <div className={`size-5 rounded-full border flex items-center justify-center shrink-0 ${
                                                    data.type_calcul_trimestre === 'pondere'
                                                        ? 'border-blue-600 bg-blue-600 text-white'
                                                        : 'border-muted-foreground/40'
                                                }`}>
                                                    {data.type_calcul_trimestre === 'pondere' && <CheckCircle2 className="size-3.5" />}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    {errors.type_calcul_trimestre && (
                                        <p className="text-xs text-rose-500 font-medium">{errors.type_calcul_trimestre}</p>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* =========================================================================
                         * ONGLET 2 : DISCIPLINE & ASSIDUITÉ (MODULE 12)
                         * ========================================================================= */}
                        <TabsContent value="discipline" className="space-y-6 pt-4">
                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                            <ShieldAlert className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base sm:text-lg">Déchéance des Mentions Honorifiques pour Absentéisme</CardTitle>
                                            <CardDescription className="text-xs sm:text-sm">
                                                Règle officielle de neutralisation des distinctions scolaires en cas d'absences répétées non justifiées.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="max-w-md space-y-2">
                                        <Label htmlFor="seuil_absences" className="text-xs font-semibold text-foreground">
                                            Seuil critique d'absences non justifiées (en heures / trimestre)
                                        </Label>
                                        <div className="flex items-center gap-3">
                                            <Input
                                                id="seuil_absences"
                                                type="number"
                                                min={0}
                                                max={200}
                                                value={data.seuil_absences_decheance_mentions}
                                                onChange={(e) => setData('seuil_absences_decheance_mentions', parseInt(e.target.value) || 0)}
                                                className="w-32 font-mono text-center text-base font-bold"
                                            />
                                            <span className="text-xs font-medium text-muted-foreground">heures par trimestre</span>
                                        </div>
                                        {errors.seuil_absences_decheance_mentions && (
                                            <p className="text-xs text-rose-500 font-medium">{errors.seuil_absences_decheance_mentions}</p>
                                        )}
                                    </div>

                                    <div className="p-4 rounded-xl bg-amber-50/70 border border-amber-200 text-amber-900 dark:bg-amber-950/30 dark:border-amber-800/60 dark:text-amber-200 text-xs space-y-2">
                                        <div className="font-bold flex items-center gap-1.5 text-sm">
                                            <Clock className="size-4 text-amber-600" />
                                            Impact sur le procès-verbal et les bulletins de notes :
                                        </div>
                                        <p className="leading-relaxed">
                                            Lorsqu'un élève accumule un nombre d'heures d'absences non justifiées strictement supérieur à ce seuil (défaut : <strong>40 heures</strong>, conforme aux dispositions disciplinaires MINESEC Cameroun), le système :
                                        </p>
                                        <ul className="list-disc pl-5 space-y-1">
                                            <li>Annule automatiquement l'attribution du <strong>Tableau d'Honneur (TH)</strong>, des <strong>Encouragements</strong> et des <strong>Félicitations</strong>.</li>
                                            <li>Appose une alerte réglementaire sur le bulletin officiel et le Procès-Verbal du Conseil de Classe (Module 15).</li>
                                        </ul>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* =========================================================================
                         * ONGLET 3 : CAISSE & SOLVABILITÉ FINANCIÈRE (MODULE 11)
                         * ========================================================================= */}
                        <TabsContent value="finances" className="space-y-6 pt-4">
                            {/* Verrou de Solvabilité */}
                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400">
                                            <Lock className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base sm:text-lg">Verrou de Solvabilité sur les Bulletins</CardTitle>
                                            <CardDescription className="text-xs sm:text-sm">
                                                Interdit le téléchargement et l'impression des bulletins pour les élèves ayant des arriérés de paiement.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div
                                        onClick={() => setData('verrou_solvabilite_actif', !data.verrou_solvabilite_actif)}
                                        className={`p-4 rounded-xl border-2 cursor-pointer transition-all flex items-center justify-between gap-4 ${
                                            data.verrou_solvabilite_actif
                                                ? 'border-rose-600 bg-rose-50/40 dark:bg-rose-950/20 dark:border-rose-500'
                                                : 'border-border/70 hover:border-border bg-card'
                                        }`}
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-sm text-foreground">Activer le verrou de solvabilité</span>
                                                {data.verrou_solvabilite_actif ? (
                                                    <Badge className="bg-rose-600 text-white text-[10px]">Actif</Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-[10px]">Désactivé</Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground leading-relaxed">
                                                Si activé, le bouton de téléchargement du bulletin scolaire sera verrouillé tant que le compte financier de l'élève présente une dette sur les frais exigibles.
                                            </p>
                                        </div>
                                        <div className={`w-11 h-6 rounded-full transition-colors relative shrink-0 ${
                                            data.verrou_solvabilite_actif ? 'bg-rose-600' : 'bg-muted-foreground/30'
                                        }`}>
                                            <div className={`w-5 h-5 rounded-full bg-white transition-transform absolute top-0.5 ${
                                                data.verrou_solvabilite_actif ? 'left-5.5' : 'left-0.5'
                                            }`} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Bonus Paiement Anticipé */}
                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                            <Sparkles className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base sm:text-lg">Bonus / Remise pour Paiement Anticipé</CardTitle>
                                            <CardDescription className="text-xs sm:text-sm">
                                                Encouragez les familles à régler la totalité des frais scolaires dès le début d'année.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div
                                        onClick={() => setData('bonus_paiement_anticipe_actif', !data.bonus_paiement_anticipe_actif)}
                                        className={`p-4 rounded-xl border-2 cursor-pointer transition-all flex items-center justify-between gap-4 ${
                                            data.bonus_paiement_anticipe_actif
                                                ? 'border-emerald-600 bg-emerald-50/40 dark:bg-emerald-950/20 dark:border-emerald-500'
                                                : 'border-border/70 hover:border-border bg-card'
                                        }`}
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-sm text-foreground">Activer le programme de bonus anticipé</span>
                                                {data.bonus_paiement_anticipe_actif ? (
                                                    <Badge className="bg-emerald-600 text-white text-[10px]">Actif</Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-[10px]">Désactivé</Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground leading-relaxed">
                                                Applique automatiquement une déduction financière si l'intégralité de la pension scolaire est acquittée avant la date limite.
                                            </p>
                                        </div>
                                        <div className={`w-11 h-6 rounded-full transition-colors relative shrink-0 ${
                                            data.bonus_paiement_anticipe_actif ? 'bg-emerald-600' : 'bg-muted-foreground/30'
                                        }`}>
                                            <div className={`w-5 h-5 rounded-full bg-white transition-transform absolute top-0.5 ${
                                                data.bonus_paiement_anticipe_actif ? 'left-5.5' : 'left-0.5'
                                            }`} />
                                        </div>
                                    </div>

                                    {data.bonus_paiement_anticipe_actif && (
                                        <div className="space-y-4 pt-2">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div className="space-y-2">
                                                    <Label htmlFor="montant_bonus" className="text-xs font-semibold text-foreground">
                                                        Montant de la remise (FCFA)
                                                    </Label>
                                                    <Input
                                                        id="montant_bonus"
                                                        type="number"
                                                        min={0}
                                                        step={500}
                                                        value={data.montant_bonus_anticipe}
                                                        onChange={(e) => setData('montant_bonus_anticipe', parseFloat(e.target.value) || 0)}
                                                        placeholder="Ex: 3000"
                                                        className="font-mono text-sm"
                                                    />
                                                    {errors.montant_bonus_anticipe && (
                                                        <p className="text-xs text-rose-500 font-medium">{errors.montant_bonus_anticipe}</p>
                                                    )}
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="date_limite_bonus" className="text-xs font-semibold text-foreground">
                                                        Date limite de règlement
                                                    </Label>
                                                    <Input
                                                        id="date_limite_bonus"
                                                        type="date"
                                                        value={data.date_limite_bonus}
                                                        onChange={(e) => setData('date_limite_bonus', e.target.value)}
                                                        className="text-sm"
                                                    />
                                                    {errors.date_limite_bonus && (
                                                        <p className="text-xs text-rose-500 font-medium">{errors.date_limite_bonus}</p>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Règle de déduction ciblée : Dernière tranche pour motiver le paiement intégral */}
                                            <div className="p-3.5 rounded-lg bg-emerald-50/70 border border-emerald-200 text-emerald-900 dark:bg-emerald-950/30 dark:border-emerald-800/60 dark:text-emerald-200 text-xs space-y-1.5">
                                                <div className="font-semibold flex items-center gap-1.5">
                                                    <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                                    <span>Cible d'imputation recommandée : <strong>Dernière tranche de pension</strong></span>
                                                </div>
                                                <p className="text-[11px] leading-relaxed text-muted-foreground dark:text-emerald-300/80">
                                                    Le bonus accordé (montant suggéré : <strong>3 000 FCFA</strong>) est appliqué en déduction sur la <strong>dernière tranche de pension</strong> pour les parents ayant soldé leur engagement avant la date limite. Cela motive fortement les familles à finaliser la totalité des règlements.
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* =========================================================================
                         * ONGLET 4 : IDENTITÉ ADMINISTRATIVE DU CAMPUS
                         * ========================================================================= */}
                        <TabsContent value="identite" className="space-y-6 pt-4">
                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                            <Building2 className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base sm:text-lg">Informations Légales & Coordonnées</CardTitle>
                                            <CardDescription className="text-xs sm:text-sm">
                                                Données officielles de l'établissement déclarées aux ministères de tutelle.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="code" className="text-xs font-semibold text-foreground">
                                                Code Établissement <span className="text-rose-500">*</span>
                                            </Label>
                                            <Input
                                                id="code"
                                                value={data.code}
                                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                                placeholder="Ex: LYC-001"
                                                className="font-mono text-sm uppercase"
                                                required
                                            />
                                            {errors.code && <p className="text-xs text-rose-500 font-medium">{errors.code}</p>}
                                        </div>

                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor="nom" className="text-xs font-semibold text-foreground">
                                                Nom de l'établissement (Français) <span className="text-rose-500">*</span>
                                            </Label>
                                            <Input
                                                id="nom"
                                                value={data.nom}
                                                onChange={(e) => setData('nom', e.target.value)}
                                                placeholder="Ex: Lycée Bilingue d'Application"
                                                className="text-sm"
                                                required
                                            />
                                            {errors.nom && <p className="text-xs text-rose-500 font-medium">{errors.nom}</p>}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor="nom_en" className="text-xs font-semibold text-foreground">
                                                Nom officiel en anglais
                                            </Label>
                                            <Input
                                                id="nom_en"
                                                value={data.nom_en}
                                                onChange={(e) => setData('nom_en', e.target.value)}
                                                placeholder="Ex: Government Bilingual High School"
                                                className="text-sm"
                                            />
                                            {errors.nom_en && <p className="text-xs text-rose-500 font-medium">{errors.nom_en}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="statut_juridique" className="text-xs font-semibold text-foreground">
                                                Statut Juridique <span className="text-rose-500">*</span>
                                            </Label>
                                            <select
                                                id="statut_juridique"
                                                value={data.statut_juridique}
                                                onChange={(e) => setData('statut_juridique', e.target.value as any)}
                                                className="w-full h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:ring-2 focus:ring-primary outline-none"
                                                required
                                            >
                                                <option value="Public">Public (Étatique)</option>
                                                <option value="Prive_Laic">Privé Laïc</option>
                                                <option value="Prive_Confessionnel">Privé Confessionnel</option>
                                            </select>
                                            {errors.statut_juridique && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.statut_juridique}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="ordre_enseignement" className="text-xs font-semibold text-foreground">
                                                Ordre d'Enseignement <span className="text-rose-500">*</span>
                                            </Label>
                                            <select
                                                id="ordre_enseignement"
                                                value={data.ordre_enseignement}
                                                onChange={(e) => setData('ordre_enseignement', e.target.value as any)}
                                                className="w-full h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:ring-2 focus:ring-primary outline-none"
                                                required
                                            >
                                                <option value="Secondaire_General">Secondaire Général</option>
                                                <option value="Secondaire_Technique">Secondaire Technique</option>
                                                <option value="Primaire">Primaire</option>
                                                <option value="Maternelle">Maternelle</option>
                                                <option value="Polyvalent">Polyvalent (Général & Technique)</option>
                                            </select>
                                            {errors.ordre_enseignement && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.ordre_enseignement}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="chef_etablissement" className="text-xs font-semibold text-foreground">
                                                Nom du Chef d'Établissement
                                            </Label>
                                            <Input
                                                id="chef_etablissement"
                                                value={data.nom_chef_etablissement}
                                                onChange={(e) => setData('nom_chef_etablissement', e.target.value)}
                                                placeholder="Ex: M. Paul Atangana"
                                                className="text-sm"
                                            />
                                            {errors.nom_chef_etablissement && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.nom_chef_etablissement}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="email" className="text-xs font-semibold text-foreground">
                                                Email de contact
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                placeholder="campus@ecole.org"
                                                className="text-sm"
                                            />
                                            {errors.email && <p className="text-xs text-rose-500 font-medium">{errors.email}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="tel1" className="text-xs font-semibold text-foreground">
                                                Téléphone 1
                                            </Label>
                                            <Input
                                                id="tel1"
                                                value={data.telephone_1}
                                                onChange={(e) => setData('telephone_1', e.target.value)}
                                                placeholder="+237 600 000 000"
                                                className="text-sm font-mono"
                                            />
                                            {errors.telephone_1 && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.telephone_1}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="tel2" className="text-xs font-semibold text-foreground">
                                                Téléphone 2
                                            </Label>
                                            <Input
                                                id="tel2"
                                                value={data.telephone_2}
                                                onChange={(e) => setData('telephone_2', e.target.value)}
                                                placeholder="+237 677 000 000"
                                                className="text-sm font-mono"
                                            />
                                            {errors.telephone_2 && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.telephone_2}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="adresse" className="text-xs font-semibold text-foreground">
                                                Adresse physique / Quartier
                                            </Label>
                                            <Input
                                                id="adresse"
                                                value={data.adresse}
                                                onChange={(e) => setData('adresse', e.target.value)}
                                                placeholder="Ex: Quartier Omnisports, Yaoundé"
                                                className="text-sm"
                                            />
                                            {errors.adresse && <p className="text-xs text-rose-500 font-medium">{errors.adresse}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="decret" className="text-xs font-semibold text-foreground">
                                                Décret / Arrêté ministériel de création
                                            </Label>
                                            <Input
                                                id="decret"
                                                value={data.decret_creation}
                                                onChange={(e) => setData('decret_creation', e.target.value)}
                                                placeholder="Ex: Arrêté N° 045/B1/1464/MINESEC du 12/08/2015"
                                                className="text-sm"
                                            />
                                            {errors.decret_creation && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.decret_creation}</p>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>

                    {/* Barre d'action inférieure de validation */}
                    <div className="flex items-center justify-end gap-3 pt-4 border-t border-border/70">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="cursor-pointer text-xs"
                        >
                            <Link href="/administration/institution">
                                Annuler
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !isDirty}
                            className="gap-2 cursor-pointer text-xs bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm"
                        >
                            <Save className="size-4" />
                            <span>{processing ? 'Enregistrement en cours...' : 'Enregistrer les modifications'}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

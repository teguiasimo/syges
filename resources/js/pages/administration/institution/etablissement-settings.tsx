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
    Scale,
    ShieldAlert,
    Sliders,
    Trash2,
    UploadCloud,
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
    titre_chef?: string | null;
    titre_chef_en?: string | null;
    titre_responsable_classe?: string | null;
    titre_responsable_classe_en?: string | null;
    email?: string | null;
    telephone_1?: string | null;
    telephone_2?: string | null;
    adresse?: string | null;
    logo?: string | null;
    logo_filigrane?: string | null;
    mode_bulletin_defaut: 'sans_competences' | 'avec_competences';
    type_calcul_trimestre: 'arithmetique' | 'pondere';
    seuil_classement_actif?: boolean;
    mode_seuil_classement?: 'pourcentage' | 'valeur_fixe';
    seuil_classement_valeur?: number;
    seuil_absences_decheance_mentions: number;
    verrou_solvabilite_actif: boolean;
    effective_logo?: string | null;
    effective_filigrane?: string | null;
    institution?: {
        id: number;
        nom: string;
        code: string;
        logo?: string | null;
        logo_filigrane?: string | null;
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

    // Initialisation du formulaire Inertia avec toutes les options paramétrables (incluant logo propre au campus)
    const { data, setData, post, processing, errors, isDirty } = useForm<{
        code: string;
        nom: string;
        nom_en: string;
        statut_juridique: string;
        ordre_enseignement: string;
        decret_creation: string;
        nom_chef_etablissement: string;
        titre_chef: string;
        titre_chef_en: string;
        titre_responsable_classe: string;
        titre_responsable_classe_en: string;
        email: string;
        telephone_1: string;
        telephone_2: string;
        adresse: string;
        logo: string;
        logo_file: File | null;
        logo_filigrane: string;
        logo_filigrane_file: File | null;
        _method: string;
        mode_bulletin_defaut: 'sans_competences' | 'avec_competences';
        type_calcul_trimestre: 'arithmetique' | 'pondere';
        seuil_classement_actif: boolean;
        mode_seuil_classement: 'pourcentage' | 'valeur_fixe';
        seuil_classement_valeur: number;
        seuil_absences_decheance_mentions: number;
        verrou_solvabilite_actif: boolean;
    }>({
        // Identité & Coordonnées
        code: etablissement.code || '',
        nom: etablissement.nom || '',
        nom_en: etablissement.nom_en || '',
        statut_juridique: etablissement.statut_juridique || 'Prive_Confessionnel',
        ordre_enseignement: etablissement.ordre_enseignement || 'Secondaire_General',
        decret_creation: etablissement.decret_creation || '',
        nom_chef_etablissement: etablissement.nom_chef_etablissement || '',
        titre_chef: etablissement.titre_chef || '',
        titre_chef_en: etablissement.titre_chef_en || '',
        titre_responsable_classe: etablissement.titre_responsable_classe || '',
        titre_responsable_classe_en: etablissement.titre_responsable_classe_en || '',
        email: etablissement.email || '',
        telephone_1: etablissement.telephone_1 || '',
        telephone_2: etablissement.telephone_2 || '',
        adresse: etablissement.adresse || '',
        logo: etablissement.logo || '',
        logo_file: null,
        logo_filigrane: etablissement.logo_filigrane || '',
        logo_filigrane_file: null,
        _method: 'put',

        // Pédagogie & Format des Bulletins (Module 10)
        mode_bulletin_defaut: etablissement.mode_bulletin_defaut || 'avec_competences',
        type_calcul_trimestre: etablissement.type_calcul_trimestre || 'arithmetique',
        seuil_classement_actif: etablissement.seuil_classement_actif !== undefined ? Boolean(etablissement.seuil_classement_actif) : true,
        mode_seuil_classement: etablissement.mode_seuil_classement || 'pourcentage',
        seuil_classement_valeur: etablissement.seuil_classement_valeur ?? 60,

        // Discipline & Assiduité (Module 12 - Seuil MINESEC Cameroun par défaut : 40h)
        seuil_absences_decheance_mentions: etablissement.seuil_absences_decheance_mentions ?? 40,

        // Finances & Solvabilité (Module 11)
        verrou_solvabilite_actif: Boolean(etablissement.verrou_solvabilite_actif),
    });

    // Prévisualisation des logos (principal + filigrane) et références des inputs fichier
    const [logoPreview, setLogoPreview] = useState<string | null>(etablissement.logo || null);
    const [filigranePreview, setFiligranePreview] = useState<string | null>(etablissement.logo_filigrane || null);
    const fileInputRef = React.useRef<HTMLInputElement | null>(null);
    const filigraneInputRef = React.useRef<HTMLInputElement | null>(null);

    /**
     * Valide la taille d'une image (<= 2 Mo) et met à jour le champ et son aperçu.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} e Événement de sélection de fichier.
     * @param {'logo_file' | 'logo_filigrane_file'} field Champ fichier Inertia cible.
     * @param {React.Dispatch<React.SetStateAction<string | null>>} setPreview Mutateur d'aperçu.
     */
    const handleImageFileChange = (
        e: React.ChangeEvent<HTMLInputElement>,
        field: 'logo_file' | 'logo_filigrane_file',
        setPreview: React.Dispatch<React.SetStateAction<string | null>>
    ) => {
        const file = e.target.files?.[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            toast.error('Le fichier est trop volumineux. Taille maximale : 2 Mo.');
            return;
        }
        setData(field, file);
        setPreview(URL.createObjectURL(file));
    };

    /**
     * Supprime un visuel du campus (retour automatique au visuel faîtier de l'institution).
     *
     * @param {'logo' | 'logo_filigrane'} pathField Champ chemin texte.
     * @param {'logo_file' | 'logo_filigrane_file'} fileField Champ fichier multipart.
     * @param {React.Dispatch<React.SetStateAction<string | null>>} setPreview Mutateur d'aperçu.
     * @param {React.RefObject<HTMLInputElement | null>} inputRef Référence vers l'input fichier.
     */
    const handleRemoveImage = (
        pathField: 'logo' | 'logo_filigrane',
        fileField: 'logo_file' | 'logo_filigrane_file',
        setPreview: React.Dispatch<React.SetStateAction<string | null>>,
        inputRef: React.RefObject<HTMLInputElement | null>
    ) => {
        setData(pathField, '');
        setData(fileField, null);
        setPreview(null);
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    /**
     * Soumet les modifications pour enregistrement avec support multipart.
     *
     * @param {React.FormEvent} e Événement de soumission.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/administration/etablissements/${etablissement.id}/settings`, {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title={`Paramètres & Règles - ${etablissement.nom}`} />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête avec navigation de retour et boutons d'action */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-border/70 pb-5 min-w-0">
                    <div className="space-y-1.5 min-w-0">
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Link
                                href="/administration/institution"
                                className="inline-flex items-center gap-1 hover:text-foreground transition-colors font-medium cursor-pointer shrink-0"
                            >
                                <ArrowLeft className="size-3.5 shrink-0" />
                                <span>Retour à l'arborescence</span>
                            </Link>
                            <span>/</span>
                            <span className="font-semibold text-foreground truncate max-w-full">{etablissement.nom}</span>
                        </div>

                        <div className="flex items-start sm:items-center gap-3.5 min-w-0">
                            <div
                                className="size-12 rounded-xl bg-slate-50 border border-slate-300 p-1 flex items-center justify-center shadow-xs shrink-0 overflow-hidden relative"
                                title={logoPreview ? "Logo propre à cet établissement" : "Logo hérité de l'institution (repli automatique)"}
                            >
                                {(logoPreview || etablissement.effective_logo || etablissement.institution?.logo) ? (
                                    <img
                                        src={logoPreview || etablissement.effective_logo || etablissement.institution?.logo || ''}
                                        alt={`Logo ${etablissement.nom}`}
                                        className="max-h-full max-w-full object-contain"
                                    />
                                ) : (
                                    <Building2 className="size-6 text-indigo-600" />
                                )}
                                {logoPreview && (
                                    <span
                                        title="Logo propre à l'établissement"
                                        className="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-emerald-500 ring-2 ring-background"
                                    />
                                )}
                            </div>

                            <div className="space-y-0.5">
                                <div className="flex flex-wrap items-center gap-2.5">
                                    <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                                        <Sliders className="size-6 text-indigo-600" />
                                        Configuration & Règles de Gestion
                                    </h1>
                                    <Badge variant="outline" className="font-mono text-xs px-2.5 py-0.5 border-indigo-200 text-indigo-700 bg-indigo-50">
                                        {etablissement.code}
                                    </Badge>
                                    {logoPreview ? (
                                        <Badge variant="outline" className="text-[10px] px-1.5 py-0 font-medium text-emerald-700 bg-emerald-50 border-emerald-200">
                                            Logo propre
                                        </Badge>
                                    ) : etablissement.institution?.logo ? (
                                        <Badge variant="outline" className="text-[10px] px-1.5 py-0 font-normal text-muted-foreground bg-muted/40 border-border/60" title="En l'absence de logo propre, le logo de l'institution faîtière s'applique automatiquement">
                                            Logo faîtier (repli)
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    Ajustez les formats de bulletins scolaires, règles de calcul, seuils disciplinaires et verrous financiers de ce campus.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Actions de sauvegarde adaptées mobile */}
                    <div className="flex flex-wrap items-center gap-2.5 shrink-0">
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
                            <Save className="size-4 shrink-0" />
                            <span>{processing ? 'Enregistrement...' : 'Enregistrer les paramètres'}</span>
                        </Button>
                    </div>
                </div>

                {/* Formulaire complet articulé par onglets responsive (1 col mobile, 2 cols sm/md, 4 cols xl) */}
                <form onSubmit={handleSubmit} className="space-y-6 min-w-0">
                    <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full min-w-0">
                        <TabsList className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 h-auto gap-1 w-full bg-muted/60 p-1 rounded-xl">
                            <TabsTrigger value="pedagogie" className="gap-2 py-2 text-xs font-semibold min-w-0">
                                <FileText className="size-4 text-emerald-600 shrink-0" />
                                <span className="truncate">Bulletins & Notes</span>
                            </TabsTrigger>
                            <TabsTrigger value="discipline" className="gap-2 py-2 text-xs font-semibold min-w-0">
                                <ShieldAlert className="size-4 text-amber-600 shrink-0" />
                                <span className="truncate">Discipline & Assiduité</span>
                            </TabsTrigger>
                            <TabsTrigger value="finances" className="gap-2 py-2 text-xs font-semibold min-w-0">
                                <DollarSign className="size-4 text-blue-600 shrink-0" />
                                <span className="truncate">Caisse & Solvabilité</span>
                            </TabsTrigger>
                            <TabsTrigger value="identite" className="gap-2 py-2 text-xs font-semibold min-w-0">
                                <Building2 className="size-4 text-teal-600 shrink-0" />
                                <span className="truncate">Identité du Campus</span>
                            </TabsTrigger>
                        </TabsList>

                        {/* =========================================================================
                         * ONGLET 1 : PÉDAGOGIE & BULLETINS (MODULE 10)
                         * ========================================================================= */}
                        <TabsContent value="pedagogie" className="space-y-6 pt-4">
                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-emerald-50 text-emerald-600">
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
                                        {/* Option 1 : Avec compétences (APC - Recommandé MINESEC) */}
                                        <div
                                            onClick={() => setData('mode_bulletin_defaut', 'avec_competences')}
                                            className={`relative p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                data.mode_bulletin_defaut === 'avec_competences'
                                                    ? 'border-emerald-600 bg-emerald-50/40 shadow-xs'
                                                    : 'border-border/70 hover:border-border bg-card'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-sm text-foreground">Approche Par Compétences (APC)</span>
                                                        <Badge className="bg-emerald-600 text-white text-[10px] px-1.5 py-0 font-medium">
                                                            Recommandé (Norme MINESEC)
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground leading-relaxed">
                                                        Modèle officiel camerounais avec compétences évaluées par matière, cotes d'acquisition APC (Expert, Acquis, En cours, Non acquis), sous-totaux par bloc pédagogique et profil disciplinaire complet.
                                                    </p>
                                                </div>
                                                <div className={`size-5 rounded-full border flex items-center justify-center shrink-0 ${
                                                    data.mode_bulletin_defaut === 'avec_competences'
                                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                                        : 'border-muted-foreground/40'
                                                }`}>
                                                    {data.mode_bulletin_defaut === 'avec_competences' && <CheckCircle2 className="size-3.5" />}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Option 2 : Standard sans compétences (Modèle classique) */}
                                        <div
                                            onClick={() => setData('mode_bulletin_defaut', 'sans_competences')}
                                            className={`relative p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                data.mode_bulletin_defaut === 'sans_competences'
                                                    ? 'border-slate-600 bg-slate-50/40 shadow-xs'
                                                    : 'border-border/70 hover:border-border bg-card'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-sm text-foreground">Standard sans compétences</span>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground leading-relaxed">
                                                        Modèle classique condensé. Présente les matières groupées, coefficients, notes séquentielles sur 20, totaux, moyennes trimestrielles, rangs et appréciations générales sans libellé de compétence.
                                                    </p>
                                                </div>
                                                <div className={`size-5 rounded-full border flex items-center justify-center shrink-0 ${
                                                    data.mode_bulletin_defaut === 'sans_competences'
                                                        ? 'border-slate-600 bg-slate-600 text-white'
                                                        : 'border-muted-foreground/40'
                                                }`}>
                                                    {data.mode_bulletin_defaut === 'sans_competences' && <CheckCircle2 className="size-3.5" />}
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
                                        <div className="p-2 rounded-lg bg-blue-50 text-blue-600">
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
                                                    ? 'border-blue-600 bg-blue-50/40 shadow-xs'
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
                                                    ? 'border-blue-600 bg-blue-50/40 shadow-xs'
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

                            {/* Règle de Quota Minimal de Coefficients pour le Classement (MINESEC / Gesco) */}
                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-indigo-50 text-indigo-600">
                                            <Scale className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base sm:text-lg">Éligibilité au Classement & Quota de Coefficients</CardTitle>
                                            <CardDescription className="text-xs sm:text-sm">
                                                Empêche qu'un élève absent ayant composé dans un nombre marginal de matières ne soit artificiellement classé 1er.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div
                                        onClick={() => setData('seuil_classement_actif', !data.seuil_classement_actif)}
                                        className={`p-4 rounded-xl border-2 cursor-pointer transition-all flex items-center justify-between gap-4 ${
                                            data.seuil_classement_actif
                                                ? 'border-indigo-600 bg-indigo-50/40'
                                                : 'border-border/70 hover:border-border bg-card'
                                        }`}
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-sm text-foreground">Activer le seuil minimal de classement</span>
                                                {data.seuil_classement_actif ? (
                                                    <Badge className="bg-indigo-600 text-white text-[10px]">Actif</Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-[10px]">Désactivé (Tous classés)</Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground leading-relaxed">
                                                Si activé, les élèves n'ayant pas atteint le volume minimal de coefficients requis porteront la mention <strong>NC (Non Classé)</strong> sans décaler le rang des élèves assidus.
                                            </p>
                                        </div>
                                        <div className={`w-11 h-6 rounded-full transition-colors relative shrink-0 ${
                                            data.seuil_classement_actif ? 'bg-indigo-600' : 'bg-muted-foreground/30'
                                        }`}>
                                            <div className={`w-5 h-5 rounded-full bg-white transition-transform absolute top-0.5 ${
                                                data.seuil_classement_actif ? 'left-5.5' : 'left-0.5'
                                            }`} />
                                        </div>
                                    </div>

                                    {data.seuil_classement_actif && (
                                        <div className="space-y-4 pt-2 border-t border-border/60">
                                            <div className="space-y-2">
                                                <Label className="text-xs font-semibold text-foreground">
                                                    Mode de définition du seuil réglementaire
                                                </Label>
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <div
                                                        onClick={() => setData('mode_seuil_classement', 'pourcentage')}
                                                        className={`p-3 rounded-lg border-2 cursor-pointer transition-all ${
                                                            data.mode_seuil_classement === 'pourcentage'
                                                                ? 'border-indigo-600 bg-indigo-50/30'
                                                                : 'border-border/60 hover:border-border bg-card'
                                                        }`}
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-xs font-bold text-foreground">Pourcentage de la classe (Recommandé)</span>
                                                            {data.mode_seuil_classement === 'pourcentage' && <CheckCircle2 className="size-4 text-indigo-600 shrink-0" />}
                                                        </div>
                                                        <p className="text-[11px] text-muted-foreground mt-1">
                                                            S'adapte dynamiquement à chaque classe (ex: 60% des coefficients totaux).
                                                        </p>
                                                    </div>

                                                    <div
                                                        onClick={() => setData('mode_seuil_classement', 'valeur_fixe')}
                                                        className={`p-3 rounded-lg border-2 cursor-pointer transition-all ${
                                                            data.mode_seuil_classement === 'valeur_fixe'
                                                                ? 'border-indigo-600 bg-indigo-50/30'
                                                                : 'border-border/60 hover:border-border bg-card'
                                                        }`}
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-xs font-bold text-foreground">Nombre fixe de coefficients</span>
                                                            {data.mode_seuil_classement === 'valeur_fixe' && <CheckCircle2 className="size-4 text-indigo-600 shrink-0" />}
                                                        </div>
                                                        <p className="text-[11px] text-muted-foreground mt-1">
                                                            Impose un plancher absolu uniforme (ex: au moins 15 coefficients évalués).
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="max-w-xs space-y-2">
                                                <Label htmlFor="seuil_valeur" className="text-xs font-semibold text-foreground">
                                                    {data.mode_seuil_classement === 'pourcentage'
                                                        ? 'Pourcentage minimal requis (%)'
                                                        : 'Nombre minimal de coefficients requis'}
                                                </Label>
                                                <div className="flex items-center gap-3">
                                                    <Input
                                                        id="seuil_valeur"
                                                        type="number"
                                                        min={1}
                                                        max={data.mode_seuil_classement === 'pourcentage' ? 100 : 50}
                                                        step={1}
                                                        value={data.seuil_classement_valeur}
                                                        onChange={(e) => setData('seuil_classement_valeur', parseFloat(e.target.value) || 0)}
                                                        className="w-28 font-mono text-center text-base font-bold"
                                                    />
                                                    <span className="text-xs font-medium text-muted-foreground">
                                                        {data.mode_seuil_classement === 'pourcentage' ? '% des coefficients de la classe' : 'coefficients'}
                                                    </span>
                                                </div>
                                                {errors.seuil_classement_valeur && (
                                                    <p className="text-xs text-rose-500 font-medium">{errors.seuil_classement_valeur}</p>
                                                )}
                                            </div>

                                            <div className="p-3.5 rounded-lg bg-indigo-50/70 border border-indigo-200 text-indigo-900 text-xs space-y-1.5">
                                                <div className="font-bold flex items-center gap-1.5 text-xs">
                                                    <Scale className="size-3.5 text-indigo-600" />
                                                    Règle appliquée lors du tirage des bulletins :
                                                </div>
                                                <p className="leading-relaxed">
                                                    Tout élève cumulant moins que ce quota (par exemple moins de {data.seuil_classement_valeur}{data.mode_seuil_classement === 'pourcentage' ? '%' : ' coefficients'}) verra son bulletin généré avec sa moyenne et ses notes réelles, mais aura un rang général marqué <strong>« NC »</strong>. Il ne sera pas compté dans le palmarès des meilleurs élèves.
                                                </p>
                                            </div>
                                        </div>
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
                                        <div className="p-2 rounded-lg bg-amber-50 text-amber-600">
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

                                    <div className="p-4 rounded-xl bg-amber-50/70 border border-amber-200 text-amber-900 text-xs space-y-2">
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
                                        <div className="p-2 rounded-lg bg-rose-50 text-rose-600">
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
                                                ? 'border-rose-600 bg-rose-50/40'
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
                        </TabsContent>

                        {/* =========================================================================
                         * ONGLET 4 : IDENTITÉ ADMINISTRATIVE DU CAMPUS
                         * ========================================================================= */}
                        <TabsContent value="identite" className="space-y-6 pt-4">
                            <Card className="border-border/80 shadow-xs">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <div className="p-2 rounded-lg bg-indigo-50 text-indigo-600">
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

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="titre_chef" className="text-xs font-semibold text-foreground">
                                                Titre officiel du Chef d'Établissement (Français)
                                            </Label>
                                            <Input
                                                id="titre_chef"
                                                value={data.titre_chef}
                                                onChange={(e) => setData('titre_chef', e.target.value)}
                                                placeholder="Ex: Le Directeur, Le Proviseur, Le Principal"
                                                className="text-sm"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Titre imprimé sur les bulletins et états officiels (ex: « Le Directeur », « Le Proviseur »).
                                            </p>
                                            {errors.titre_chef && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.titre_chef}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="titre_chef_en" className="text-xs font-semibold text-foreground">
                                                Titre officiel en anglais (English Title)
                                            </Label>
                                            <Input
                                                id="titre_chef_en"
                                                value={data.titre_chef_en}
                                                onChange={(e) => setData('titre_chef_en', e.target.value)}
                                                placeholder="Ex: The Director, The Principal, The Headmaster"
                                                className="text-sm"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Titre pour les sous-systèmes ou mentions anglophones (ex: « The Director »).
                                            </p>
                                            {errors.titre_chef_en && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.titre_chef_en}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Titre officiel du responsable de classe (Professeur Principal / Maître / Maîtresse) */}
                                    <div className="p-3.5 rounded-xl bg-amber-50/40 border border-amber-200/70 space-y-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="text-xs font-bold text-foreground">
                                                    Appellation du Titulaire de Classe (Bulletins & Pédagogie)
                                                </p>
                                                <p className="text-[11px] text-muted-foreground">
                                                    Personnalise le libellé imprimé au bas des bulletins et dans les attributions selon l'ordre d'enseignement.
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setData('titre_responsable_classe', 'Le Maître / La Maîtresse');
                                                        setData('titre_responsable_classe_en', 'Class Teacher');
                                                    }}
                                                    className="text-[10px] px-2 py-0.5 rounded-md border border-amber-300 bg-white hover:bg-amber-100 text-amber-800 font-medium cursor-pointer"
                                                >
                                                    Base (Maître / Maîtresse)
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setData('titre_responsable_classe', 'Le Professeur Principal');
                                                        setData('titre_responsable_classe_en', 'Head Teacher');
                                                    }}
                                                    className="text-[10px] px-2 py-0.5 rounded-md border border-border bg-white hover:bg-muted font-medium cursor-pointer"
                                                >
                                                    Secondaire (Professeur Principal)
                                                </button>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="space-y-1.5">
                                                <Label htmlFor="titre_responsable_classe" className="text-xs font-semibold text-foreground">
                                                    Appellation (Français)
                                                </Label>
                                                <Input
                                                    id="titre_responsable_classe"
                                                    value={data.titre_responsable_classe}
                                                    onChange={(e) => setData('titre_responsable_classe', e.target.value)}
                                                    placeholder="Ex: Le Maître / La Maîtresse, Le Professeur Principal"
                                                    className="text-sm bg-background"
                                                />
                                                {errors.titre_responsable_classe && (
                                                    <p className="text-xs text-rose-500 font-medium">{errors.titre_responsable_classe}</p>
                                                )}
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="titre_responsable_classe_en" className="text-xs font-semibold text-foreground">
                                                    Appellation en anglais (English)
                                                </Label>
                                                <Input
                                                    id="titre_responsable_classe_en"
                                                    value={data.titre_responsable_classe_en}
                                                    onChange={(e) => setData('titre_responsable_classe_en', e.target.value)}
                                                    placeholder="Ex: Class Teacher, Head Teacher"
                                                    className="text-sm bg-background"
                                                />
                                                {errors.titre_responsable_classe_en && (
                                                    <p className="text-xs text-rose-500 font-medium">{errors.titre_responsable_classe_en}</p>
                                                )}
                                            </div>
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

                                    {/* Section Logo / Blason Officiel du Campus */}
                                    <div className="pt-4 border-t border-border/60">
                                        <div className="p-4 rounded-xl bg-slate-50/70 border border-slate-200 space-y-3">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <Label className="text-xs font-bold text-foreground">
                                                        Logo / Blason Officiel du Campus
                                                    </Label>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        Chaque établissement est autonome et possède son propre logo sur tous ses états imprimables (bulletins, certificats, reçus).
                                                    </p>
                                                </div>
                                                {logoPreview ? (
                                                    <Badge variant="outline" className="text-[10px] text-emerald-700 bg-emerald-50 border-emerald-200">
                                                        Logo propre actif
                                                    </Badge>
                                                ) : etablissement.institution?.logo ? (
                                                    <Badge variant="outline" className="text-[10px] text-muted-foreground bg-muted/40 border-border/60">
                                                        Repli sur logo faîtier
                                                    </Badge>
                                                ) : null}
                                            </div>

                                            <input
                                                type="file"
                                                ref={fileInputRef}
                                                onChange={(e) => handleImageFileChange(e, 'logo_file', setLogoPreview)}
                                                accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
                                                className="hidden"
                                            />

                                            <div className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 pt-1">
                                                {logoPreview ? (
                                                    <div className="relative group size-16 shrink-0 rounded-2xl border border-border bg-white p-1 flex items-center justify-center overflow-hidden shadow-xs">
                                                        <img
                                                            src={logoPreview}
                                                            alt="Logo campus"
                                                            className="max-h-full max-w-full object-contain"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemoveImage('logo', 'logo_file', setLogoPreview, fileInputRef)}
                                                            className="absolute inset-0 bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl cursor-pointer"
                                                            title="Supprimer le logo (revenir au logo de l'institution)"
                                                        >
                                                            <Trash2 className="size-5" />
                                                        </button>
                                                    </div>
                                                ) : etablissement.institution?.logo ? (
                                                    <div
                                                        onClick={() => fileInputRef.current?.click()}
                                                        className="relative group size-16 shrink-0 rounded-2xl border-2 border-dashed border-border/80 hover:border-primary/60 bg-muted/20 p-1 flex items-center justify-center cursor-pointer transition-colors"
                                                        title="Logo hérité de l'institution faîtière (cliquer pour téléverser un logo propre)"
                                                    >
                                                        <img
                                                            src={etablissement.institution.logo}
                                                            alt="Logo institution (repli)"
                                                            className="max-h-full max-w-full object-contain opacity-60 group-hover:opacity-40 transition-opacity"
                                                        />
                                                        <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/30 rounded-2xl text-white">
                                                            <UploadCloud className="size-5" />
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <div
                                                        onClick={() => fileInputRef.current?.click()}
                                                        className="size-16 shrink-0 rounded-2xl border-2 border-dashed border-border hover:border-primary/60 bg-muted/20 flex flex-col items-center justify-center cursor-pointer transition-colors"
                                                        title="Cliquer pour importer un logo"
                                                    >
                                                        <UploadCloud className="size-6 text-muted-foreground" />
                                                    </div>
                                                )}

                                                <div className="flex flex-col gap-1.5 min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => fileInputRef.current?.click()}
                                                            className="cursor-pointer text-xs h-8 gap-1.5 justify-start w-fit"
                                                        >
                                                            <UploadCloud className="size-3.5 shrink-0" />
                                                            <span>{logoPreview ? 'Changer le logo' : 'Téléverser un logo spécifique'}</span>
                                                        </Button>
                                                        {logoPreview && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleRemoveImage('logo', 'logo_file', setLogoPreview, fileInputRef)}
                                                                className="cursor-pointer text-xs h-8 gap-1 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                                                            >
                                                                <Trash2 className="size-3.5 shrink-0" />
                                                                <span>Supprimer</span>
                                                            </Button>
                                                        )}
                                                    </div>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        {logoPreview
                                                            ? 'Ce logo personnalisé sera utilisé en en-tête des bulletins, relevés et certificats de ce campus.'
                                                            : etablissement.institution?.logo
                                                            ? 'En l’absence de logo spécifique téléversé, le logo officiel de l’institution sera automatiquement appliqué lors de l’impression.'
                                                            : 'Formats acceptés : PNG, JPG, SVG, WEBP (Taille maximale : 2 Mo).'}
                                                    </p>
                                                </div>
                                            </div>
                                            {errors.logo && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.logo}</p>
                                            )}
                                        </div>

                                        {/* Section Filigrane Officiel du Campus */}
                                        <div className="mt-4 p-4 rounded-xl bg-slate-50/70 border border-slate-200 space-y-3">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <Label className="text-xs font-bold text-foreground">
                                                        Logo Filigrane Officiel (Arrière-plan PDF du Campus)
                                                    </Label>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        Image apposée en filigrane centré et discret sur tous les états officiels (recommandé : PNG sans fond).
                                                    </p>
                                                </div>
                                                {filigranePreview ? (
                                                    <Badge variant="outline" className="text-[10px] text-emerald-700 bg-emerald-50 border-emerald-200">
                                                        Filigrane propre actif
                                                    </Badge>
                                                ) : etablissement.institution?.logo_filigrane ? (
                                                    <Badge variant="outline" className="text-[10px] text-muted-foreground bg-muted/40 border-border/60">
                                                        Repli sur filigrane faîtier
                                                    </Badge>
                                                ) : null}
                                            </div>

                                            <input
                                                type="file"
                                                ref={filigraneInputRef}
                                                onChange={(e) => handleImageFileChange(e, 'logo_filigrane_file', setFiligranePreview)}
                                                accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
                                                className="hidden"
                                            />

                                            <div className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 pt-1">
                                                {filigranePreview ? (
                                                    <div className="relative group size-16 shrink-0 rounded-2xl border border-border bg-white p-1 flex items-center justify-center overflow-hidden shadow-xs">
                                                        <img
                                                            src={filigranePreview}
                                                            alt="Filigrane campus"
                                                            className="max-h-full max-w-full object-contain opacity-65"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemoveImage('logo_filigrane', 'logo_filigrane_file', setFiligranePreview, filigraneInputRef)}
                                                            className="absolute inset-0 bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl cursor-pointer"
                                                            title="Supprimer le filigrane du campus"
                                                        >
                                                            <Trash2 className="size-5" />
                                                        </button>
                                                    </div>
                                                ) : etablissement.institution?.logo_filigrane ? (
                                                    <div
                                                        onClick={() => filigraneInputRef.current?.click()}
                                                        className="relative group size-16 shrink-0 rounded-2xl border-2 border-dashed border-border/80 hover:border-primary/60 bg-muted/20 p-1 flex items-center justify-center cursor-pointer transition-colors"
                                                        title="Filigrane hérité de l'institution faîtière"
                                                    >
                                                        <img
                                                            src={etablissement.institution.logo_filigrane}
                                                            alt="Filigrane institution (repli)"
                                                            className="max-h-full max-w-full object-contain opacity-45 group-hover:opacity-30 transition-opacity"
                                                        />
                                                        <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/30 rounded-2xl text-white">
                                                            <UploadCloud className="size-5" />
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <div
                                                        onClick={() => filigraneInputRef.current?.click()}
                                                        className="size-16 shrink-0 rounded-2xl border-2 border-dashed border-border hover:border-primary/60 bg-muted/20 flex flex-col items-center justify-center cursor-pointer transition-colors"
                                                        title="Cliquer pour importer un filigrane"
                                                    >
                                                        <UploadCloud className="size-6 text-muted-foreground" />
                                                    </div>
                                                )}

                                                <div className="flex flex-col gap-1.5 min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => filigraneInputRef.current?.click()}
                                                            className="cursor-pointer text-xs h-8 gap-1.5 justify-start w-fit"
                                                        >
                                                            <UploadCloud className="size-3.5 shrink-0" />
                                                            <span>{filigranePreview ? 'Changer le filigrane' : 'Téléverser un filigrane spécifique'}</span>
                                                        </Button>
                                                        {filigranePreview && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleRemoveImage('logo_filigrane', 'logo_filigrane_file', setFiligranePreview, filigraneInputRef)}
                                                                className="cursor-pointer text-xs h-8 gap-1 text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                                                            >
                                                                <Trash2 className="size-3.5 shrink-0" />
                                                                <span>Supprimer</span>
                                                            </Button>
                                                        )}
                                                    </div>
                                                    <p className="text-[11px] text-muted-foreground">
                                                        {filigranePreview
                                                            ? 'Ce filigrane personnalisé est appliqué sur l’ensemble des documents PDF de ce campus.'
                                                            : etablissement.institution?.logo_filigrane
                                                            ? 'En l’absence de filigrane propre, celui de l’institution faîtière est automatiquement hérité.'
                                                            : 'Formats acceptés : PNG transparent recommandé, JPG, SVG (max 2 Mo).'}
                                                    </p>
                                                </div>
                                            </div>
                                            {errors.logo_filigrane && (
                                                <p className="text-xs text-rose-500 font-medium">{errors.logo_filigrane}</p>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>

                    {/* Barre d'action inférieure de validation empilée sur mobile */}
                    <div className="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 sm:gap-3 pt-4 border-t border-border/70">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="w-full sm:w-auto cursor-pointer text-xs"
                        >
                            <Link href="/administration/institution">
                                Annuler
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !isDirty}
                            className="w-full sm:w-auto gap-2 cursor-pointer text-xs bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm"
                        >
                            <Save className="size-4 shrink-0" />
                            <span>{processing ? 'Enregistrement en cours...' : 'Enregistrer les modifications'}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

/**
 * Fil d'Ariane (Breadcrumbs) pour la page de configuration des paramètres d'un établissement / campus.
 */
EtablissementSettings.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
        {
            title: 'Institution & Campus',
            href: '/administration/institution',
        },
        {
            title: 'Paramètres du Campus',
            href: '/administration/institution',
        },
    ],
};

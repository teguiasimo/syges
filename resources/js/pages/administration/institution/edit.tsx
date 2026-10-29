import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    School,
    ArrowLeft,
    Building2,
    Save,
    Shield,
    MapPin,
    Phone,
    Mail,
    Globe,
    FileText,
    CheckCircle2,
    AlertCircle,
    RotateCcw,
    Sparkles,
    Trash2,
    UploadCloud,
} from 'lucide-react';
import React, { useEffect } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Interface représentant les données de l'Institution scolaire faîtière.
 */
interface InstitutionData {
    id?: number;
    code: string;
    sigle?: string | null;
    nom: string;
    nom_en?: string | null;
    devise?: string | null;
    devise_en?: string | null;
    logo?: string | null;
    email?: string | null;
    telephone_1?: string | null;
    telephone_2?: string | null;
    boite_postale?: string | null;
    site_web?: string | null;
    region?: string | null;
    region_en?: string | null;
    departement?: string | null;
    departement_en?: string | null;
    arrondissement?: string | null;
    arrondissement_en?: string | null;
    ville?: string | null;
    delegation_regionale?: string | null;
    delegation_regionale_en?: string | null;
    delegation_departementale?: string | null;
    delegation_departementale_en?: string | null;
}

/**
 * Propriétés attendues par la page dédiée d'édition de l'institution.
 */
interface EditInstitutionProps {
    institution: InstitutionData;
    flash?: {
        success?: string;
        error?: string;
    };
}

/**
 * Page dédiée à la création et modification de l'Institution faîtière.
 *
 * Conçue pour offrir un espace de travail confortable et aéré permettant de renseigner
 * facilement l'ensemble des données juridiques, bilingues, territoriales et administratives.
 *
 * @param {EditInstitutionProps} props Les propriétés transmises par le contrôleur Inertia.
 * @returns {JSX.Element} La vue du formulaire complet de l'institution.
 */
export default function EditInstitution({ institution, flash }: EditInstitutionProps) {
    // Référence de mémorisation pour éviter les doubles déclenchements de toast
    const lastToastRef = React.useRef<string | null>(null);

    // Gestion des toasts de notification en haut à droite avec style vert pour le succès et déduplication
    useEffect(() => {
        if (flash?.success && lastToastRef.current !== flash.success) {
            lastToastRef.current = flash.success;
            toast.success(flash.success, {
                id: 'institution-edit-success',
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
                id: 'institution-edit-error',
            });
        }
    }, [flash]);

    // Gestion de l'état de prévisualisation du logo et référence de l'input fichier
    const [logoPreview, setLogoPreview] = React.useState<string | null>(institution.logo || null);
    const fileInputRef = React.useRef<HTMLInputElement | null>(null);

    // Initialisation du formulaire Inertia avec l'ensemble des champs
    const { data, setData, post, processing, errors, isDirty, reset } = useForm<{
        code: string;
        sigle: string;
        nom: string;
        nom_en: string;
        devise: string;
        devise_en: string;
        logo: string;
        logo_file: File | null;
        _method: string;
        email: string;
        telephone_1: string;
        telephone_2: string;
        boite_postale: string;
        site_web: string;
        region: string;
        region_en: string;
        departement: string;
        departement_en: string;
        arrondissement: string;
        arrondissement_en: string;
        ville: string;
        delegation_regionale: string;
        delegation_regionale_en: string;
        delegation_departementale: string;
        delegation_departementale_en: string;
    }>({
        code: institution.code || '',
        sigle: institution.sigle || '',
        nom: institution.nom || '',
        nom_en: institution.nom_en || '',
        devise: institution.devise || '',
        devise_en: institution.devise_en || '',
        logo: institution.logo || '',
        logo_file: null,
        _method: 'put',
        email: institution.email || '',
        telephone_1: institution.telephone_1 || '',
        telephone_2: institution.telephone_2 || '',
        boite_postale: institution.boite_postale || '',
        site_web: institution.site_web || '',
        region: institution.region || '',
        region_en: institution.region_en || '',
        departement: institution.departement || '',
        departement_en: institution.departement_en || '',
        arrondissement: institution.arrondissement || '',
        arrondissement_en: institution.arrondissement_en || '',
        ville: institution.ville || '',
        delegation_regionale: institution.delegation_regionale || '',
        delegation_regionale_en: institution.delegation_regionale_en || '',
        delegation_departementale: institution.delegation_departementale || '',
        delegation_departementale_en: institution.delegation_departementale_en || '',
    });


    /**
     * Gestion de la sélection d'un fichier logo avec prévisualisation immédiate.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} e Événement de changement de fichier.
     */
    const handleLogoFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                toast.error('Le fichier est trop volumineux. Taille maximale : 2 Mo.');
                return;
            }
            setData('logo_file', file);
            const previewUrl = URL.createObjectURL(file);
            setLogoPreview(previewUrl);
        }
    };

    /**
     * Suppression du logo sélectionné.
     */
    const handleRemoveLogo = () => {
        setData('logo', '');
        setData('logo_file', null);
        setLogoPreview(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    /**
     * Soumission du formulaire pour enregistrement des données avec support multipart.
     *
     * @param {React.FormEvent} e Événement de soumission.
     */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/administration/institution', {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title="Configuration de l'Institution - Administration" />

            {/* Conteneur principal plein écran fluide et responsive sans contrainte artificielle de largeur */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête avec bouton retour et actions rapides adaptées mobile */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border/70 pb-5 min-w-0">
                    <div className="space-y-1 min-w-0">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                            <Link
                                href="/administration/institution"
                                className="inline-flex items-center gap-1 hover:text-foreground transition-colors font-medium cursor-pointer"
                            >
                                <ArrowLeft className="size-3.5 shrink-0" />
                                <span>Retour à l'arborescence académique</span>
                            </Link>
                        </div>
                        <h1 className="text-xl sm:text-2xl lg:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <School className="size-6 sm:size-7 text-teal-600 dark:text-teal-400 shrink-0" />
                            <span>Configuration de l'Institution Faîtière</span>
                        </h1>
                        <p className="text-xs sm:text-sm text-muted-foreground">
                            Définissez l'identité bilingue, les tutelles ministérielles et les coordonnées officielles de votre groupe scolaire.
                        </p>
                    </div>

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
                            className="gap-2 cursor-pointer text-xs h-9 bg-primary text-primary-foreground hover:bg-primary/90"
                        >
                            <Save className="size-4 shrink-0" />
                            <span>{processing ? 'Enregistrement...' : 'Enregistrer les modifications'}</span>
                        </Button>
                    </div>
                </div>

                {/* Formulaire principal découpé en cartes spacieuses */}
                <form onSubmit={handleSubmit} className="space-y-8">
                    {/* CARTE 1 : IDENTITÉ OFFICIELLE & BILINGUISME */}
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        <div className="bg-gradient-to-r from-slate-200/95 via-indigo-100/70 to-slate-200/80 dark:from-slate-800 dark:via-indigo-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white font-bold shadow-xs shrink-0">
                                <Building2 className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">Identité & Bilinguisme Officiel</h2>
                                <p className="text-xs text-slate-600 dark:text-slate-400">
                                    Informations juridiques utilisées sur l'ensemble des entêtes de documents (Bulletins, Relevés, Attestations).
                                </p>
                            </div>
                        </div>

                        <div className="p-6 bg-card space-y-6">
                            {/* LIGNE 1 : Nom bilingue FR et EN en colonnes miroir (Priorité 1 de lecture) */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div className="space-y-1.5 p-4 rounded-xl bg-slate-50/60 dark:bg-slate-900/40 border border-slate-200/60 dark:border-slate-800/60">
                                    <Label htmlFor="nom" className="text-xs font-semibold flex items-center gap-1 text-foreground">
                                        <span>🇫🇷 Nom Officiel (Français)</span> <span className="text-rose-500">*</span>
                                    </Label>
                                <Input
                                    id="nom"
                                    value={data.nom}
                                    onChange={(e) => setData('nom', e.target.value)}
                                    placeholder="GROUPE SCOLAIRE BILINGUE SAINT JOSEPH"
                                    className="font-semibold text-sm"
                                    required
                                />
                                {errors.nom && <p className="text-[11px] text-rose-500">{errors.nom}</p>}
                                <p className="text-[11px] text-muted-foreground">Apparaît sur l'en-tête gauche des documents officiels.</p>
                            </div>

                            <div className="space-y-1.5 p-4 rounded-xl bg-slate-50/60 dark:bg-slate-900/40 border border-slate-200/60 dark:border-slate-800/60">
                                <Label htmlFor="nom_en" className="text-xs font-semibold text-foreground">
                                    🇬🇧 Official Name (English)
                                </Label>
                                <Input
                                    id="nom_en"
                                    value={data.nom_en || ''}
                                    onChange={(e) => setData('nom_en', e.target.value)}
                                    placeholder="SAINT JOSEPH BILINGUAL SCHOOL GROUP"
                                    className="font-semibold text-sm"
                                />
                                {errors.nom_en && <p className="text-[11px] text-rose-500">{errors.nom_en}</p>}
                                <p className="text-[11px] text-muted-foreground">Apparaît sur l'en-tête droit des documents officiels.</p>
                            </div>
                        </div>

                        {/* LIGNE 2 : Devise bilingue FR et EN */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="devise" className="text-xs font-semibold text-foreground">
                                    🇫🇷 Devise Officielle (Français)
                                </Label>
                                <Input
                                    id="devise"
                                    value={data.devise || ''}
                                    onChange={(e) => setData('devise', e.target.value)}
                                    placeholder="ex: Discipline - Travail - Succès"
                                    className="text-sm"
                                />
                                {errors.devise && <p className="text-[11px] text-rose-500">{errors.devise}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="devise_en" className="text-xs font-semibold text-foreground">
                                    🇬🇧 Official Motto (English)
                                </Label>
                                <Input
                                    id="devise_en"
                                    value={data.devise_en || ''}
                                    onChange={(e) => setData('devise_en', e.target.value)}
                                    placeholder="ex: Discipline - Hard Work - Success"
                                    className="text-sm"
                                />
                                {errors.devise_en && <p className="text-[11px] text-rose-500">{errors.devise_en}</p>}
                            </div>
                        </div>

                        {/* LIGNE 3 : Sigle / Acronyme du Groupe & Logo avec Prévisualisation */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-3 border-t border-border/60">
                            {/* Sigle / Acronyme */}
                            <div className="space-y-1.5">
                                <Label htmlFor="sigle" className="text-xs font-semibold text-foreground">
                                    Sigle / Acronyme du Groupe Scolaire
                                </Label>
                                <Input
                                    id="sigle"
                                    value={data.sigle || ''}
                                    onChange={(e) => setData('sigle', e.target.value)}
                                    placeholder="ex: GSB-STP, CS-NDN"
                                    className="font-mono text-sm uppercase"
                                />
                                {errors.sigle && <p className="text-[11px] text-rose-500">{errors.sigle}</p>}
                                <p className="text-[11px] text-muted-foreground">
                                    Appellation abrégée de l'institution (affichée en en-tête des documents et bulletins).
                                </p>
                            </div>

                            {/* Logo Officiel avec Prévisualisation et Sélecteur de fichier */}
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold text-foreground">
                                    Logo / Blason Officiel
                                </Label>
                                <input
                                    type="file"
                                    ref={fileInputRef}
                                    onChange={handleLogoFileChange}
                                    accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
                                    className="hidden"
                                />
                                <div className="flex items-center gap-3">
                                    {logoPreview ? (
                                        <div className="relative group size-14 shrink-0 rounded-xl border border-border bg-slate-50 dark:bg-slate-900 p-1 flex items-center justify-center overflow-hidden shadow-xs">
                                            <img
                                                src={logoPreview}
                                                alt="Logo officiel"
                                                className="max-h-full max-w-full object-contain"
                                            />
                                            <button
                                                type="button"
                                                onClick={handleRemoveLogo}
                                                className="absolute inset-0 bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-xl cursor-pointer"
                                                title="Supprimer le logo"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    ) : (
                                        <div
                                            onClick={() => fileInputRef.current?.click()}
                                            className="size-14 shrink-0 rounded-xl border-2 border-dashed border-border hover:border-primary/60 bg-muted/20 flex flex-col items-center justify-center cursor-pointer transition-colors"
                                            title="Cliquer pour importer une image"
                                        >
                                            <UploadCloud className="size-5 text-muted-foreground" />
                                        </div>
                                    )}

                                    <div className="flex flex-col gap-1 min-w-0">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => fileInputRef.current?.click()}
                                            className="cursor-pointer text-xs h-8 gap-1.5 justify-start w-fit"
                                        >
                                            <UploadCloud className="size-3.5" />
                                            <span>{logoPreview ? 'Changer' : 'Sélectionner'}</span>
                                        </Button>
                                        <p className="text-[10px] text-muted-foreground truncate">PNG, JPG, SVG (max 2Mo)</p>
                                    </div>
                                </div>
                                {errors.logo && <p className="text-[11px] text-rose-500">{errors.logo}</p>}
                            </div>
                            </div>
                        </div>
                    </div>

                    {/* CARTE 2 : TUTELLE MINISTÉRIELLE & DÉCOUPAGE TERRITORIAL */}
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        <div className="bg-gradient-to-r from-slate-200/95 via-emerald-100/70 to-slate-200/80 dark:from-slate-800 dark:via-emerald-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white font-bold shadow-xs shrink-0">
                                <Shield className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">Tutelle Administrative & Localisation Géographique</h2>
                                <p className="text-xs text-slate-600 dark:text-slate-400">
                                    Spécificités réglementaires camerounaises (Délégations MINESEC / MINEDUB, Région, Département).
                                </p>
                            </div>
                        </div>

                        <div className="p-6 bg-card space-y-5">

                        {/* Délégations Régionales & Départementales */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="delegation_regionale" className="text-xs font-semibold text-foreground">
                                    🇫🇷 Délégation Régionale de Tutelle (FR)
                                </Label>
                                <Input
                                    id="delegation_regionale"
                                    value={data.delegation_regionale || ''}
                                    onChange={(e) => setData('delegation_regionale', e.target.value)}
                                    placeholder="ex: Délégation Régionale des Enseignements Secondaires du Littoral"
                                    className="text-sm"
                                />
                                {errors.delegation_regionale && <p className="text-[11px] text-rose-500">{errors.delegation_regionale}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="delegation_regionale_en" className="text-xs font-semibold text-foreground">
                                    🇬🇧 Regional Delegation of Tutelage (EN)
                                </Label>
                                <Input
                                    id="delegation_regionale_en"
                                    value={data.delegation_regionale_en || ''}
                                    onChange={(e) => setData('delegation_regionale_en', e.target.value)}
                                    placeholder="ex: Regional Delegation of Secondary Education for the Littoral"
                                    className="text-sm"
                                />
                                {errors.delegation_regionale_en && <p className="text-[11px] text-rose-500">{errors.delegation_regionale_en}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="delegation_departementale" className="text-xs font-semibold text-foreground">
                                    🇫🇷 Délégation Départementale (FR)
                                </Label>
                                <Input
                                    id="delegation_departementale"
                                    value={data.delegation_departementale || ''}
                                    onChange={(e) => setData('delegation_departementale', e.target.value)}
                                    placeholder="ex: Délégation Départementale du Wouri"
                                    className="text-sm"
                                />
                                {errors.delegation_departementale && <p className="text-[11px] text-rose-500">{errors.delegation_departementale}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="delegation_departementale_en" className="text-xs font-semibold text-foreground">
                                    🇬🇧 Divisional Delegation (EN)
                                </Label>
                                <Input
                                    id="delegation_departementale_en"
                                    value={data.delegation_departementale_en || ''}
                                    onChange={(e) => setData('delegation_departementale_en', e.target.value)}
                                    placeholder="ex: Divisional Delegation of Wouri"
                                    className="text-sm"
                                />
                                {errors.delegation_departementale_en && <p className="text-[11px] text-rose-500">{errors.delegation_departementale_en}</p>}
                            </div>
                        </div>

                        {/* Découpage territorial */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5 pt-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="region" className="text-xs font-semibold text-foreground">
                                    Région Administrative (FR)
                                </Label>
                                <Input
                                    id="region"
                                    value={data.region || ''}
                                    onChange={(e) => setData('region', e.target.value)}
                                    placeholder="ex: Littoral"
                                    className="text-sm"
                                />
                                {errors.region && <p className="text-[11px] text-rose-500">{errors.region}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="region_en" className="text-xs font-semibold text-foreground">
                                    Region (EN)
                                </Label>
                                <Input
                                    id="region_en"
                                    value={data.region_en || ''}
                                    onChange={(e) => setData('region_en', e.target.value)}
                                    placeholder="ex: Littoral Region"
                                    className="text-sm"
                                />
                                {errors.region_en && <p className="text-[11px] text-rose-500">{errors.region_en}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="ville" className="text-xs font-semibold text-foreground">
                                    Ville / Siège
                                </Label>
                                <Input
                                    id="ville"
                                    value={data.ville || ''}
                                    onChange={(e) => setData('ville', e.target.value)}
                                    placeholder="ex: Douala"
                                    className="text-sm"
                                />
                                {errors.ville && <p className="text-[11px] text-rose-500">{errors.ville}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="departement" className="text-xs font-semibold text-foreground">
                                    Département (FR)
                                </Label>
                                <Input
                                    id="departement"
                                    value={data.departement || ''}
                                    onChange={(e) => setData('departement', e.target.value)}
                                    placeholder="ex: Wouri"
                                    className="text-sm"
                                />
                                {errors.departement && <p className="text-[11px] text-rose-500">{errors.departement}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="departement_en" className="text-xs font-semibold text-foreground">
                                    Division (EN)
                                </Label>
                                <Input
                                    id="departement_en"
                                    value={data.departement_en || ''}
                                    onChange={(e) => setData('departement_en', e.target.value)}
                                    placeholder="ex: Wouri Division"
                                    className="text-sm"
                                />
                                {errors.departement_en && <p className="text-[11px] text-rose-500">{errors.departement_en}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="arrondissement" className="text-xs font-semibold text-foreground">
                                    Arrondissement (FR)
                                </Label>
                                <Input
                                    id="arrondissement"
                                    value={data.arrondissement || ''}
                                    onChange={(e) => setData('arrondissement', e.target.value)}
                                    placeholder="ex: Douala 5ème"
                                    className="text-sm"
                                />
                                {errors.arrondissement && <p className="text-[11px] text-rose-500">{errors.arrondissement}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="arrondissement_en" className="text-xs font-semibold text-foreground">
                                    Sub-Division (EN)
                                </Label>
                                <Input
                                    id="arrondissement_en"
                                    value={data.arrondissement_en || ''}
                                    onChange={(e) => setData('arrondissement_en', e.target.value)}
                                    placeholder="ex: Douala V"
                                    className="text-sm"
                                />
                                {errors.arrondissement_en && <p className="text-[11px] text-rose-500">{errors.arrondissement_en}</p>}
                            </div>
                        </div>
                        </div>
                    </div>

                    {/* CARTE 3 : COORDONNÉES OFFICIELLES & CONTACTS */}
                    <div className="rounded-2xl border-2 border-slate-300/90 dark:border-slate-700 bg-card shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-400 dark:hover:border-slate-600">
                        <div className="bg-gradient-to-r from-slate-200/95 via-amber-100/70 to-slate-200/80 dark:from-slate-800 dark:via-amber-950/40 dark:to-slate-800/90 px-6 py-4 border-b-2 border-slate-300 dark:border-slate-700 flex flex-wrap items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 text-white font-bold shadow-xs shrink-0">
                                <Phone className="size-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white tracking-tight">Coordonnées Officielles & Contact</h2>
                                <p className="text-xs text-slate-600 dark:text-slate-400">
                                    Canaux de communication officiels de l'institution avec les parents et l'administration.
                                </p>
                            </div>
                        </div>

                        <div className="p-6 bg-card space-y-5">
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                                <div className="space-y-1.5">
                                <Label htmlFor="email" className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <Mail className="size-3.5 text-muted-foreground" /> Email Officiel
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email || ''}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="contact@institution.org"
                                    className="text-sm"
                                />
                                {errors.email && <p className="text-[11px] text-rose-500">{errors.email}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="telephone_1" className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <Phone className="size-3.5 text-muted-foreground" /> Téléphone Principal
                                </Label>
                                <Input
                                    id="telephone_1"
                                    value={data.telephone_1 || ''}
                                    onChange={(e) => setData('telephone_1', e.target.value)}
                                    placeholder="+237 699 00 00 00"
                                    className="text-sm"
                                />
                                {errors.telephone_1 && <p className="text-[11px] text-rose-500">{errors.telephone_1}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="telephone_2" className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <Phone className="size-3.5 text-muted-foreground" /> Téléphone Secondaire
                                </Label>
                                <Input
                                    id="telephone_2"
                                    value={data.telephone_2 || ''}
                                    onChange={(e) => setData('telephone_2', e.target.value)}
                                    placeholder="+237 677 00 00 00"
                                    className="text-sm"
                                />
                                {errors.telephone_2 && <p className="text-[11px] text-rose-500">{errors.telephone_2}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="boite_postale" className="text-xs font-semibold text-foreground">
                                    Boîte Postale (BP / P.O. Box)
                                </Label>
                                <Input
                                    id="boite_postale"
                                    value={data.boite_postale || ''}
                                    onChange={(e) => setData('boite_postale', e.target.value)}
                                    placeholder="ex: B.P. 1245 Douala"
                                    className="text-sm"
                                />
                                {errors.boite_postale && <p className="text-[11px] text-rose-500">{errors.boite_postale}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="site_web" className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <Globe className="size-3.5 text-muted-foreground" /> Site Web Officiel
                                </Label>
                                <Input
                                    id="site_web"
                                    value={data.site_web || ''}
                                    onChange={(e) => setData('site_web', e.target.value)}
                                    placeholder="https://www.institution.org"
                                    className="text-sm"
                                />
                                {errors.site_web && <p className="text-[11px] text-rose-500">{errors.site_web}</p>}
                            </div>
                        </div>
                        </div>
                    </div>

                    {/* BARRE D'ACTIONS INFÉRIEURE EMPILABLE SUR MOBILE */}
                    <div className="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-border/60">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => reset()}
                            disabled={!isDirty || processing}
                            className="w-full sm:w-auto text-xs gap-1.5 cursor-pointer text-muted-foreground hover:text-foreground"
                        >
                            <RotateCcw className="size-3.5 shrink-0" />
                            <span>Réinitialiser les modifications</span>
                        </Button>

                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3">
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
                                className="w-full sm:w-auto gap-2 cursor-pointer text-xs bg-primary text-primary-foreground hover:bg-primary/90"
                            >
                                <Save className="size-4 shrink-0" />
                                <span>{processing ? 'Enregistrement...' : 'Enregistrer les modifications'}</span>
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

/**
 * Fil d'Ariane du Layout racine pour la page de configuration de l'institution.
 */
EditInstitution.layout = {
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
            title: 'Configuration de l\'Institution',
            href: '/administration/institution/edit',
        },
    ],
};

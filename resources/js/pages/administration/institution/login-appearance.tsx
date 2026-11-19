import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    EyeOff,
    Globe,
    Image as ImageIcon,
    Info,
    Layers,
    Loader2,
    Mail,
    PanelLeft,
    Pause,
    Pencil,
    Phone,
    Play,
    Plus,
    RotateCcw,
    Sparkles,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useConfirmDelete } from '@/components/confirm-delete-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import type {
    BreadcrumbItem,
    InstitutionLoginBrief,
    LoginAppearancePayload,
    LoginBrandingConfig,
    LoginSettingRecord,
    LoginSlideItem,
    LoginSlideRecord,
} from '@/types';
import { LoginSlideModal } from './components/login-slide-modal';

/**
 * Style obligatoire des toasts de succès SYGES (haut-droite, couleur verte).
 */
const SUCCESS_TOAST_STYLE = {
    backgroundColor: '#10b981',
    color: '#ffffff',
    border: '1px solid #059669',
} as const;

/**
 * Propriétés Inertia transmises par `LoginAppearanceController::index()`.
 */
interface LoginAppearancePageProps {
    /** Réglages globaux enregistrés en base */
    settings: LoginSettingRecord;
    /** Liste ordonnée des slides personnalisés */
    slides: LoginSlideRecord[];
    /** Les 10 scènes par défaut intégrées à SYGES */
    defaultScenes: LoginSlideItem[];
    /** Identité visuelle par défaut SYGES */
    defaultBranding: LoginBrandingConfig;
    /** Fiche synthétique de l'établissement actif pour le pré-remplissage rapide */
    institution: InstitutionLoginBrief | null;
    /** Payload effectif actuellement résolu pour `/login` */
    effectivePreview: LoginAppearancePayload;
}

/**
 * Fil d'Ariane de la page de configuration du carrousel et du branding de connexion.
 */
const BREADCRUMBS: BreadcrumbItem[] = [
    { title: 'Administration', href: '/administration' },
    { title: 'Institution & Campus', href: '/administration/institution' },
    {
        title: 'Apparence Page Login',
        href: '/administration/institution/login-appearance',
    },
];

/**
 * Valeurs rapides suggérées pour la cadence de rotation du diaporama (en secondes).
 */
const INTERVAL_PRESETS = [3, 4, 6, 10] as const;

/**
 * Page d'administration dédiée à la personnalisation de l'écran de connexion (`/login`).
 *
 * Permet aux administrateurs habilités (`institutions.edit`) de :
 * - Choisir entre les 10 photos officielles SYGES et leur galerie personnalisée (N slides en boucle) ;
 * - Importer, modifier, activer/désactiver, réordonner et supprimer des slides sans code ;
 * - Ajuster la cadence de défilement (2 à 30 secondes) ;
 * - Personnaliser le logo, les intitulés et les contacts d'assistance du formulaire de connexion.
 *
 * @param {LoginAppearancePageProps} props Données initiales injectées par Inertia
 * @returns {JSX.Element} Interface complète de configuration avec aperçu interactif en direct
 */
export default function LoginAppearancePage({
    settings,
    slides,
    defaultScenes,
    defaultBranding,
    institution,
}: LoginAppearancePageProps) {
    const { confirmDelete, ConfirmDialog } = useConfirmDelete();
    const logoInputRef = useRef<HTMLInputElement | null>(null);
    const appLogoInputRef = useRef<HTMLInputElement | null>(null);

    // -----------------------------------------------------------------
    // État du formulaire des paramètres globaux (mode, cadence, branding login & app)
    // -----------------------------------------------------------------
    const [useCustomSlides, setUseCustomSlides] = useState<boolean>(settings.use_custom_slides);
    const [rotationSeconds, setRotationSeconds] = useState<number>(settings.rotation_interval_seconds);
    const [brandName, setBrandName] = useState<string>(settings.brand_name ?? defaultBranding.brand_name);
    const [brandSubtitle, setBrandSubtitle] = useState<string>(
        settings.brand_subtitle ?? defaultBranding.brand_subtitle,
    );
    const [logoPath, setLogoPath] = useState<string>(settings.logo_path ?? defaultBranding.logo_url);
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [logoObjectPreview, setLogoObjectPreview] = useState<string | null>(null);
    const [resetLogoToDefault, setResetLogoToDefault] = useState<boolean>(false);
    const [applyBrandingToApp, setApplyBrandingToApp] = useState<boolean>(
        Boolean(settings.apply_branding_to_app),
    );
    const [appBrandName, setAppBrandName] = useState<string>(settings.app_brand_name ?? '');
    const [appBrandSubtitle, setAppBrandSubtitle] = useState<string>(settings.app_brand_subtitle ?? '');
    const [appLogoPath, setAppLogoPath] = useState<string>(settings.app_logo_path ?? '');
    const [appLogoFile, setAppLogoFile] = useState<File | null>(null);
    const [appLogoObjectPreview, setAppLogoObjectPreview] = useState<string | null>(null);
    const [resetAppLogoToShared, setResetAppLogoToShared] = useState<boolean>(false);
    const [syncFavicon, setSyncFavicon] = useState<boolean>(settings.sync_favicon ?? true);
    const [footerLeft, setFooterLeft] = useState<string>(settings.footer_left ?? defaultBranding.footer_left);
    const [footerRight, setFooterRight] = useState<string>(
        settings.footer_right ?? defaultBranding.footer_right,
    );
    const [supportLabel, setSupportLabel] = useState<string>(
        settings.support_label ?? defaultBranding.support_label,
    );
    const [supportPhone, setSupportPhone] = useState<string>(settings.support_phone ?? '');
    const [supportEmail, setSupportEmail] = useState<string>(settings.support_email ?? '');
    const [savingSettings, setSavingSettings] = useState<boolean>(false);
    const [settingsErrors, setSettingsErrors] = useState<Record<string, string>>({});

    // -----------------------------------------------------------------
    // État des modales et de l'aperçu interactif du carrousel
    // -----------------------------------------------------------------
    const [modalOpen, setModalOpen] = useState<boolean>(false);
    const [editingSlide, setEditingSlide] = useState<LoginSlideRecord | null>(null);
    const [previewIdx, setPreviewIdx] = useState<number>(0);
    const [previewPaused, setPreviewPaused] = useState<boolean>(false);

    // Resynchronise l'état local lorsque les props serveur sont mises à jour par Inertia
    useEffect(() => {
        setUseCustomSlides(settings.use_custom_slides);
        setRotationSeconds(settings.rotation_interval_seconds);
        setBrandName(settings.brand_name ?? defaultBranding.brand_name);
        setBrandSubtitle(settings.brand_subtitle ?? defaultBranding.brand_subtitle);
        setLogoPath(settings.logo_path ?? defaultBranding.logo_url);
        setApplyBrandingToApp(Boolean(settings.apply_branding_to_app));
        setAppBrandName(settings.app_brand_name ?? '');
        setAppBrandSubtitle(settings.app_brand_subtitle ?? '');
        setAppLogoPath(settings.app_logo_path ?? '');
        setSyncFavicon(settings.sync_favicon ?? true);
        setFooterLeft(settings.footer_left ?? defaultBranding.footer_left);
        setFooterRight(settings.footer_right ?? defaultBranding.footer_right);
        setSupportLabel(settings.support_label ?? defaultBranding.support_label);
        setSupportPhone(settings.support_phone ?? '');
        setSupportEmail(settings.support_email ?? '');
        setLogoFile(null);
        setAppLogoFile(null);
        setResetLogoToDefault(false);
        setResetAppLogoToShared(false);
        setSettingsErrors({});

        setLogoObjectPreview((prev) => {
            if (prev) {
                URL.revokeObjectURL(prev);
            }
            return null;
        });
        setAppLogoObjectPreview((prev) => {
            if (prev) {
                URL.revokeObjectURL(prev);
            }
            return null;
        });

        if (logoInputRef.current) {
            logoInputRef.current.value = '';
        }
        if (appLogoInputRef.current) {
            appLogoInputRef.current.value = '';
        }
    }, [settings, defaultBranding]);

    useEffect(() => {
        return () => {
            if (logoObjectPreview) {
                URL.revokeObjectURL(logoObjectPreview);
            }
        };
    }, [logoObjectPreview]);

    useEffect(() => {
        return () => {
            if (appLogoObjectPreview) {
                URL.revokeObjectURL(appLogoObjectPreview);
            }
        };
    }, [appLogoObjectPreview]);

    const activeCustomSlides = useMemo(
        () => slides.filter((item) => item.is_active),
        [slides],
    );

    const willDisplayCustomOnLogin = useCustomSlides && activeCustomSlides.length > 0;

    /**
     * Liste exacte des diapositives affichées dans l'aperçu en direct (N slides actifs ou 10 scènes SYGES).
     */
    const previewSlides: LoginSlideItem[] = useMemo(() => {
        if (willDisplayCustomOnLogin) {
            return activeCustomSlides.map((slide) => ({
                id: `custom-${slide.id}`,
                image: slide.image_path,
                title: slide.titre,
                subtitle: slide.message ?? '',
            }));
        }

        return defaultScenes;
    }, [willDisplayCustomOnLogin, activeCustomSlides, defaultScenes]);

    const safePreviewIdx = previewSlides.length > 0 ? previewIdx % previewSlides.length : 0;
    const currentPreviewSlide = previewSlides[safePreviewIdx] ?? previewSlides[0];
    const effectiveLogoUrl = logoObjectPreview || logoPath || defaultBranding.logo_url;
    const effectiveSidebarLogoUrl =
        appLogoObjectPreview || appLogoPath.trim() || effectiveLogoUrl || defaultBranding.logo_url;
    const effectiveSidebarTitle =
        appBrandName.trim() || brandName.trim() || defaultBranding.brand_name;
    const effectiveSidebarSubtitle =
        appBrandSubtitle.trim() || brandSubtitle.trim() || 'Gestion Scolaire';

    // Anime le mini-aperçu interactif au rythme exact de la cadence configurée
    useEffect(() => {
        if (previewPaused || previewSlides.length <= 1) {
            return;
        }

        const safeSeconds = Math.max(2, Math.min(30, Number(rotationSeconds) || 4));
        const timer = window.setInterval(() => {
            setPreviewIdx((prev) => (prev + 1) % previewSlides.length);
        }, safeSeconds * 1000);

        return () => window.clearInterval(timer);
    }, [previewPaused, previewSlides.length, rotationSeconds]);

    /**
     * Enregistre les paramètres globaux (mode du carrousel, cadence et branding) côté serveur.
     *
     * @param {boolean} [overrideUseCustom] Force optionnelle du mode personnalisé lors d'un clic direct
     */
    const saveSettings = (overrideUseCustom?: boolean) => {
        const nextUseCustom =
            typeof overrideUseCustom === 'boolean' ? overrideUseCustom : useCustomSlides;
        const clampedSeconds = Math.max(2, Math.min(30, Number(rotationSeconds) || 4));

        setSavingSettings(true);
        setSettingsErrors({});

        const payload: {
            use_custom_slides: number;
            rotation_interval_seconds: number;
            brand_name: string;
            brand_subtitle: string;
            logo_path: string;
            reset_logo_to_default: number;
            footer_left: string;
            footer_right: string;
            support_label: string;
            support_phone: string;
            support_email: string;
            apply_branding_to_app: number;
            app_brand_name: string;
            app_brand_subtitle: string;
            app_logo_path: string;
            reset_app_logo_to_shared: number;
            sync_favicon: number;
            logo_file?: File;
            app_logo_file?: File;
        } = {
            use_custom_slides: nextUseCustom ? 1 : 0,
            rotation_interval_seconds: clampedSeconds,
            brand_name: brandName.trim(),
            brand_subtitle: brandSubtitle.trim(),
            logo_path: logoPath.trim() || defaultBranding.logo_url,
            reset_logo_to_default: resetLogoToDefault ? 1 : 0,
            footer_left: footerLeft.trim(),
            footer_right: footerRight.trim(),
            support_label: supportLabel.trim(),
            support_phone: supportPhone.trim(),
            support_email: supportEmail.trim(),
            apply_branding_to_app: applyBrandingToApp ? 1 : 0,
            app_brand_name: appBrandName.trim(),
            app_brand_subtitle: appBrandSubtitle.trim(),
            app_logo_path: appLogoPath.trim(),
            reset_app_logo_to_shared: resetAppLogoToShared ? 1 : 0,
            sync_favicon: syncFavicon ? 1 : 0,
        };

        if (logoFile) {
            payload.logo_file = logoFile;
        }
        if (appLogoFile) {
            payload.app_logo_file = appLogoFile;
        }

        router.post('/administration/institution/login-appearance/settings', payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Paramètres de la page de connexion mis à jour avec succès.', {
                    position: 'top-right',
                    style: SUCCESS_TOAST_STYLE,
                });
            },
            onError: (errors) => {
                setSettingsErrors(errors);
                toast.error(
                    'Erreur lors de l’enregistrement des réglages. Vérifiez les champs.',
                    { position: 'top-right' },
                );
            },
            onFinish: () => {
                setSavingSettings(false);
            },
        });
    };

    /**
     * Déclenche une confirmation puis rétablit toute la page de connexion aux réglages par défaut SYGES.
     */
    const handleResetDefaults = () => {
        confirmDelete({
            title: 'Réinitialiser la page de connexion',
            itemType: 'la configuration visuelle',
            itemName: 'Valeurs SYGES par défaut',
            promptText:
                'Souhaitez-vous réactiver les 10 scènes scolaires SYGES et rétablir les textes/logo par défaut ?',
            description:
                'Vos slides personnalisés déjà importés seront conservés en base de données mais désactivés du carrousel jusqu’à réactivation.',
            confirmLabel: 'Réinitialiser par défaut',
            variant: 'warning',
            onConfirm: () => {
                router.post(
                    '/administration/institution/login-appearance/settings/reset',
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            toast.success(
                                'Apparence de connexion rétablie aux valeurs SYGES par défaut.',
                                {
                                    position: 'top-right',
                                    style: SUCCESS_TOAST_STYLE,
                                },
                            );
                        },
                    },
                );
            },
        });
    };

    /**
     * Bascule l'état actif/inactif d'un slide personnalisé en un clic.
     *
     * @param {LoginSlideRecord} slide Diapositive à activer ou mettre en pause
     */
    const handleToggleSlideActive = (slide: LoginSlideRecord) => {
        const nextActive = !slide.is_active;

        router.post(
            `/administration/institution/login-appearance/slides/${slide.id}`,
            {
                titre: slide.titre,
                message: slide.message ?? '',
                is_active: nextActive ? 1 : 0,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        nextActive
                            ? `Slide « ${slide.titre} » activé dans le carrousel.`
                            : `Slide « ${slide.titre} » masqué du carrousel.`,
                        {
                            position: 'top-right',
                            style: SUCCESS_TOAST_STYLE,
                        },
                    );
                },
            },
        );
    };

    /**
     * Déplace un slide vers le haut ou vers le bas dans l'ordre du carrousel.
     *
     * @param {LoginSlideRecord} slide Diapositive concernée
     * @param {'up' | 'down'} direction Sens de déplacement souhaité
     */
    const handleReorderSlide = (slide: LoginSlideRecord, direction: 'up' | 'down') => {
        router.post(
            `/administration/institution/login-appearance/slides/${slide.id}/reorder`,
            { direction },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Ordre du carrousel mis à jour.', {
                        position: 'top-right',
                        style: SUCCESS_TOAST_STYLE,
                    });
                },
            },
        );
    };

    /**
     * Ouvre la confirmation avant suppression définitive d'un slide personnalisé et de son fichier image.
     *
     * @param {LoginSlideRecord} slide Diapositive à supprimer
     */
    const handleDeleteSlide = (slide: LoginSlideRecord) => {
        confirmDelete({
            title: 'Supprimer ce slide',
            itemType: 'le slide',
            itemName: slide.titre,
            description:
                'La photo et son texte seront supprimés définitivement. Si aucun autre slide actif ne subsiste, SYGES réaffichera automatiquement les 10 photos par défaut.',
            onConfirm: () => {
                router.delete(
                    `/administration/institution/login-appearance/slides/${slide.id}`,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            toast.success('Slide supprimé avec succès.', {
                                position: 'top-right',
                                style: SUCCESS_TOAST_STYLE,
                            });
                        },
                    },
                );
            },
        });
    };

    /**
     * Pré-remplit les champs d'identité visuelle (connexion et barre latérale) et d'assistance à partir de l'établissement enregistré.
     */
    const handlePrefillFromInstitution = () => {
        if (!institution) {
            return;
        }

        setBrandName(institution.sigle?.trim() || institution.nom);
        setBrandSubtitle(institution.devise?.trim() || institution.nom);
        setFooterLeft(`${institution.nom} • Portail SYGES`);
        if (institution.sigle?.trim()) {
            setAppBrandName(institution.sigle.trim());
        }
        if (institution.telephone_1) {
            setSupportPhone(institution.telephone_1);
        }
        if (institution.email) {
            setSupportEmail(institution.email);
        }

        toast.success(
            'Champs pré-remplis depuis votre établissement. Cliquez sur « Enregistrer » pour valider.',
            {
                position: 'top-right',
                style: SUCCESS_TOAST_STYLE,
            },
        );
    };

    /**
     * Valide la taille maximale (5 Mo) d'un logo importé, nettoie son aperçu précédent et applique la sélection.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} event Événement de sélection de fichier
     * @param {(file: File) => void} applyFile Callback assignant le fichier retenu
     * @param {string | null} currentPreview URL d'aperçu précédente à libérer
     * @param {(url: string) => void} applyPreview Callback enregistrant la nouvelle URL d'aperçu
     */
    const handleBrandLogoSelect = (
        event: React.ChangeEvent<HTMLInputElement>,
        applyFile: (file: File) => void,
        currentPreview: string | null,
        applyPreview: (url: string) => void,
    ) => {
        const selected = event.target.files?.[0] ?? null;
        if (!selected) {
            return;
        }

        if (selected.size > 5 * 1024 * 1024) {
            toast.error('Le logo ne doit pas dépasser 5 Mo.', { position: 'top-right' });
            event.target.value = '';
            return;
        }

        if (currentPreview) {
            URL.revokeObjectURL(currentPreview);
        }

        applyFile(selected);
        applyPreview(URL.createObjectURL(selected));
    };

    /**
     * Prépare l'aperçu local d'un nouveau logo importé pour la page de connexion.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} event Événement de sélection de fichier
     */
    const handleLogoFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        handleBrandLogoSelect(
            event,
            (file) => {
                setLogoFile(file);
                setResetLogoToDefault(false);
            },
            logoObjectPreview,
            setLogoObjectPreview,
        );
    };

    /**
     * Prépare l'aperçu local d'un logo spécifique à la barre latérale après connexion.
     *
     * @param {React.ChangeEvent<HTMLInputElement>} event Événement de sélection de fichier
     */
    const handleAppLogoFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        handleBrandLogoSelect(
            event,
            (file) => {
                setAppLogoFile(file);
                setResetAppLogoToShared(false);
            },
            appLogoObjectPreview,
            setAppLogoObjectPreview,
        );
    };

    return (
        <>
            <Head title="Apparence de la page de connexion" />

            {/* Conteneur principal pleine largeur sans contrainte max-w afin d'occuper tout l'espace sur grands écrans */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 w-full min-w-0">
                {/* En-tête de la page */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <Button
                                variant="ghost"
                                size="sm"
                                asChild
                                className="-ml-2 h-8 px-2 text-muted-foreground hover:text-foreground"
                            >
                                <Link href="/administration/institution">
                                    <ArrowLeft className="size-4 mr-1" />
                                    Institution
                                </Link>
                            </Button>
                            <Badge
                                variant={willDisplayCustomOnLogin ? 'default' : 'secondary'}
                                className={
                                    willDisplayCustomOnLogin
                                        ? 'bg-emerald-600 hover:bg-emerald-600 text-white'
                                        : ''
                                }
                            >
                                {willDisplayCustomOnLogin
                                    ? `Mode personnalisé (${activeCustomSlides.length} ${
                                          activeCustomSlides.length > 1 ? 'slides' : 'slide'
                                      } en boucle)`
                                    : 'Mode SYGES par défaut (10 scènes)'}
                            </Badge>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Apparence, Carrousel &amp; Identité
                        </h1>
                        <p className="text-sm text-muted-foreground max-w-3xl">
                            Personnalisez les photos qui défilent sur l&apos;écran de connexion, leurs messages,
                            la cadence de rotation ainsi que l&apos;identité visuelle (écran de login, menu latéral et icône d&apos;onglet).
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleResetDefaults}
                            className="gap-2"
                        >
                            <RotateCcw className="size-4" />
                            Réinitialiser par défaut
                        </Button>
                    </div>
                </div>

                {/* Aperçu interactif en direct du rendu sur /login */}
                <Card className="overflow-hidden border-slate-200 dark:border-slate-800 shadow-sm">
                    <CardHeader className="py-3.5 px-5 bg-muted/40 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <Sparkles className="size-4 text-primary" />
                            <div>
                                <CardTitle className="text-sm font-semibold">
                                    Aperçu en direct de l&apos;écran de connexion
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    {willDisplayCustomOnLogin
                                        ? `Affiche exactement vos ${activeCustomSlides.length} photos personnalisées actives en boucle.`
                                        : 'Affiche les 10 scènes SYGES officielles par défaut.'}
                                </CardDescription>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Badge variant="outline" className="text-xs tabular-nums">
                                Slide {safePreviewIdx + 1} / {previewSlides.length} • {rotationSeconds}s
                            </Badge>
                            {previewSlides.length > 1 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 px-2 text-xs gap-1"
                                    onClick={() => setPreviewPaused((prev) => !prev)}
                                >
                                    {previewPaused ? (
                                        <>
                                            <Play className="size-3.5" />
                                            Reprendre
                                        </>
                                    ) : (
                                        <>
                                            <Pause className="size-3.5" />
                                            Pause
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </CardHeader>

                    <div className="grid grid-cols-1 lg:grid-cols-12 bg-slate-950 text-white">
                        {/* Mini panneau gauche (carrousel + branding) */}
                        <div className="relative lg:col-span-7 min-h-[240px] sm:min-h-[300px] xl:min-h-[340px] 2xl:min-h-[380px] overflow-hidden flex flex-col justify-between p-5">
                            {currentPreviewSlide && (
                                <img
                                    src={currentPreviewSlide.image}
                                    alt={currentPreviewSlide.title}
                                    className="absolute inset-0 h-full w-full object-cover transition-opacity duration-500"
                                />
                            )}
                            <div className="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/45 to-slate-950/55" />

                            {/* En-tête branding */}
                            <div className="relative z-10 flex items-center gap-2.5">
                                <div className="flex size-9 items-center justify-center rounded-lg bg-white/10 backdrop-blur-md border border-white/20 p-1">
                                    <img
                                        src={effectiveLogoUrl}
                                        alt={brandName || 'Logo'}
                                        className="h-full w-full object-contain"
                                    />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-sm font-bold leading-tight truncate">
                                        {brandName.trim() || defaultBranding.brand_name}
                                    </p>
                                    <p className="text-[11px] text-slate-300 truncate">
                                        {brandSubtitle.trim() || defaultBranding.brand_subtitle}
                                    </p>
                                </div>
                            </div>

                            {/* Légende du slide + contrôles */}
                            <div className="relative z-10 space-y-3 mt-12">
                                {currentPreviewSlide && (
                                    <div className="space-y-1 max-w-2xl">
                                        <h3 className="text-base sm:text-lg font-semibold leading-snug">
                                            {currentPreviewSlide.title}
                                        </h3>
                                        {currentPreviewSlide.subtitle && (
                                            <p className="text-xs sm:text-sm text-slate-200/90 line-clamp-2">
                                                {currentPreviewSlide.subtitle}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div className="flex items-center justify-between gap-3 pt-1">
                                    <div className="flex items-center gap-1.5 flex-wrap">
                                        {previewSlides.map((slideItem, idx) => (
                                            <button
                                                key={slideItem.id}
                                                type="button"
                                                aria-label={`Aller au slide ${idx + 1}`}
                                                onClick={() => setPreviewIdx(idx)}
                                                className={`h-1.5 rounded-full transition-all ${
                                                    idx === safePreviewIdx
                                                        ? 'w-6 bg-emerald-400'
                                                        : 'w-2 bg-white/35 hover:bg-white/60'
                                                }`}
                                            />
                                        ))}
                                    </div>

                                    {previewSlides.length > 1 && (
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="size-7 text-white hover:bg-white/15 hover:text-white"
                                                onClick={() =>
                                                    setPreviewIdx(
                                                        (prev) =>
                                                            (prev - 1 + previewSlides.length) %
                                                            previewSlides.length,
                                                    )
                                                }
                                            >
                                                <ChevronLeft className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="size-7 text-white hover:bg-white/15 hover:text-white"
                                                onClick={() =>
                                                    setPreviewIdx(
                                                        (prev) => (prev + 1) % previewSlides.length,
                                                    )
                                                }
                                            >
                                                <ChevronRight className="size-4" />
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center justify-between text-[11px] text-slate-300/80 border-t border-white/10 pt-2">
                                    <span className="truncate">
                                        {footerLeft.trim() || defaultBranding.footer_left}
                                    </span>
                                    <span className="shrink-0">
                                        {footerRight.trim() || defaultBranding.footer_right}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Mini panneau droit (rappel support & connexion) */}
                        <div className="lg:col-span-5 bg-card text-card-foreground p-5 flex flex-col justify-between border-t lg:border-t-0 lg:border-l border-border">
                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Panneau de connexion
                                </p>
                                <p className="text-sm font-medium">
                                    Connexion sécurisée • {brandName.trim() || defaultBranding.brand_name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Le formulaire identifiant / mot de passe reste inchangé et centré à droite sur grand écran.
                                </p>
                            </div>

                            <div className="mt-4 rounded-lg border bg-muted/40 p-3 space-y-1.5">
                                <p className="text-xs font-semibold text-foreground">
                                    {supportLabel.trim() || defaultBranding.support_label}
                                </p>
                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    {supportPhone.trim() ? (
                                        <span className="inline-flex items-center gap-1.5">
                                            <Phone className="size-3.5 text-emerald-600" />
                                            {supportPhone.trim()}
                                        </span>
                                    ) : (
                                        <span className="italic text-[11px]">Téléphone masqué</span>
                                    )}
                                    {supportEmail.trim() ? (
                                        <span className="inline-flex items-center gap-1.5">
                                            <Mail className="size-3.5 text-sky-600" />
                                            {supportEmail.trim()}
                                        </span>
                                    ) : (
                                        <span className="italic text-[11px]">Email masqué</span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Onglets principaux de configuration */}
                <Tabs defaultValue="carousel" className="w-full space-y-4">
                    <TabsList className="w-full sm:w-auto">
                        <TabsTrigger value="carousel" className="gap-2">
                            <ImageIcon className="size-4" />
                            Carrousel &amp; Photos ({slides.length})
                        </TabsTrigger>
                        <TabsTrigger value="branding" className="gap-2">
                            <Building2 className="size-4" />
                            Identité, Menu &amp; Assistance
                        </TabsTrigger>
                    </TabsList>

                    {/* =========================================================
                        ONGLET 1 : MODE DU CARROUSEL + CADENCE + LISTE DES SLIDES
                       ========================================================= */}
                    <TabsContent value="carousel" className="space-y-6">
                        {/* Alerte contextuelle si des slides actifs existent mais que le mode personnalisé est désactivé */}
                        {activeCustomSlides.length > 0 && !useCustomSlides && (
                            <Alert className="border-amber-300 bg-amber-50/70 dark:bg-amber-950/30 dark:border-amber-900">
                                <Info className="size-4 text-amber-600" />
                                <AlertTitle>Photos personnalisées prêtes mais non activées</AlertTitle>
                                <AlertDescription className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 w-full">
                                    <span>
                                        Vous avez {activeCustomSlides.length} slide(s) actif(s), mais la page de
                                        connexion affiche encore les 10 photos SYGES par défaut.
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() => {
                                            setUseCustomSlides(true);
                                            saveSettings(true);
                                        }}
                                        disabled={savingSettings}
                                        className="shrink-0"
                                    >
                                        Activer ma galerie maintenant
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Carte Mode du carrousel & Vitesse de défilement */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Layers className="size-4 text-primary" />
                                    Source du diaporama &amp; Cadence de défilement
                                </CardTitle>
                                <CardDescription>
                                    Choisissez d&apos;afficher les photos par défaut SYGES ou uniquement vos slides
                                    personnalisés actifs (ex. : 3 photos = boucle stricte de 3 slides).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    {/* Option A : Par défaut SYGES */}
                                    <button
                                        type="button"
                                        onClick={() => setUseCustomSlides(false)}
                                        className={`text-left rounded-xl border p-4 transition-all ${
                                            !useCustomSlides
                                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                : 'border-border hover:bg-muted/40'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold text-sm">
                                                Galerie officielle SYGES (par défaut)
                                            </span>
                                            {!useCustomSlides && (
                                                <CheckCircle2 className="size-4 text-primary shrink-0" />
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-1">
                                            Conserve les 10 scènes scolaires nationales intégrées en boucle.
                                        </p>
                                    </button>

                                    {/* Option B : Galerie personnalisée */}
                                    <button
                                        type="button"
                                        onClick={() => setUseCustomSlides(true)}
                                        className={`text-left rounded-xl border p-4 transition-all ${
                                            useCustomSlides
                                                ? 'border-emerald-600 bg-emerald-50/50 dark:bg-emerald-950/25 ring-1 ring-emerald-600'
                                                : 'border-border hover:bg-muted/40'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold text-sm">
                                                Galerie personnalisée de l&apos;établissement
                                            </span>
                                            {useCustomSlides && (
                                                <CheckCircle2 className="size-4 text-emerald-600 shrink-0" />
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-1">
                                            {activeCustomSlides.length > 0
                                                ? `Fait défiler exactement vos ${activeCustomSlides.length} slide(s) actif(s) en boucle.`
                                                : 'Aucun slide actif pour le moment — ajoutez des photos ci-dessous.'}
                                        </p>
                                    </button>
                                </div>

                                <Separator />

                                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="rotation-seconds" className="flex items-center gap-1.5">
                                            <Clock className="size-3.5 text-muted-foreground" />
                                            Durée d&apos;affichage par photo (secondes)
                                        </Label>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Input
                                                id="rotation-seconds"
                                                type="number"
                                                min={2}
                                                max={30}
                                                value={rotationSeconds}
                                                onChange={(e) =>
                                                    setRotationSeconds(Number(e.target.value) || 4)
                                                }
                                                className="w-24 tabular-nums"
                                            />
                                            <div className="flex items-center gap-1">
                                                {INTERVAL_PRESETS.map((sec) => (
                                                    <Button
                                                        key={sec}
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            rotationSeconds === sec ? 'default' : 'outline'
                                                        }
                                                        onClick={() => setRotationSeconds(sec)}
                                                        className="h-8 px-2.5 text-xs"
                                                    >
                                                        {sec}s
                                                    </Button>
                                                ))}
                                            </div>
                                        </div>
                                        {settingsErrors.rotation_interval_seconds && (
                                            <p className="text-xs text-destructive">
                                                {settingsErrors.rotation_interval_seconds}
                                            </p>
                                        )}
                                    </div>

                                    <Button
                                        type="button"
                                        onClick={() => saveSettings()}
                                        disabled={savingSettings}
                                        className="gap-2"
                                    >
                                        {savingSettings && <Loader2 className="size-4 animate-spin" />}
                                        <span>Enregistrer le mode et la cadence</span>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Gestionnaire des diapositives personnalisées */}
                        <div className="space-y-4">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-lg font-semibold tracking-tight">
                                        Vos diapositives personnalisées ({slides.length})
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Importez vos photos (ex. depuis un dossier appareil photo) : si vous
                                        importez 3 photos actives, la page de connexion n&apos;affichera que ces 3
                                        photos en boucle.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    onClick={() => {
                                        setEditingSlide(null);
                                        setModalOpen(true);
                                    }}
                                    className="gap-2 shrink-0"
                                >
                                    <Plus className="size-4" />
                                    Ajouter un slide
                                </Button>
                            </div>

                            {slides.length === 0 ? (
                                <Card className="border-dashed">
                                    <CardContent className="flex flex-col items-center justify-center py-12 px-4 text-center">
                                        <div className="rounded-full bg-primary/10 p-3.5 text-primary mb-3">
                                            <ImageIcon className="size-7" />
                                        </div>
                                        <h3 className="text-base font-semibold">
                                            Aucune photo personnalisée importée
                                        </h3>
                                        <p className="text-sm text-muted-foreground max-w-md mt-1 mb-5">
                                            Par défaut, SYGES affiche ses 10 scènes scolaires. Cliquez ci-dessous
                                            pour importer les photos propres à votre établissement (JPG, PNG ou
                                            WebP jusqu&apos;à 10 Mo).
                                        </p>
                                        <Button
                                            type="button"
                                            onClick={() => {
                                                setEditingSlide(null);
                                                setModalOpen(true);
                                            }}
                                            className="gap-2"
                                        >
                                            <Plus className="size-4" />
                                            Importer ma première photo
                                        </Button>
                                    </CardContent>
                                </Card>
                            ) : (
                                <div className="grid grid-cols-1 gap-3">
                                    {slides.map((slide, index) => (
                                        <Card
                                            key={slide.id}
                                            className={`transition-all ${
                                                !slide.is_active ? 'opacity-70 bg-muted/20' : ''
                                            }`}
                                        >
                                            <CardContent className="p-4 flex flex-col sm:flex-row sm:items-center gap-4">
                                                {/* Contrôles d'ordre + numéro */}
                                                <div className="flex sm:flex-col items-center justify-center gap-1 shrink-0">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-7"
                                                        disabled={index === 0}
                                                        title="Monter ce slide"
                                                        onClick={() => handleReorderSlide(slide, 'up')}
                                                    >
                                                        <ArrowUp className="size-3.5" />
                                                    </Button>
                                                    <Badge variant="outline" className="tabular-nums text-xs">
                                                        #{index + 1}
                                                    </Badge>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-7"
                                                        disabled={index === slides.length - 1}
                                                        title="Descendre ce slide"
                                                        onClick={() => handleReorderSlide(slide, 'down')}
                                                    >
                                                        <ArrowDown className="size-3.5" />
                                                    </Button>
                                                </div>

                                                {/* Miniature photo */}
                                                <div className="relative aspect-video w-full sm:w-44 shrink-0 overflow-hidden rounded-lg bg-slate-900 border">
                                                    <img
                                                        src={slide.image_path}
                                                        alt={slide.titre}
                                                        className="h-full w-full object-cover"
                                                    />
                                                </div>

                                                {/* Contenu texte & statut */}
                                                <div className="flex-1 min-w-0 space-y-1.5">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h3 className="font-semibold text-sm sm:text-base truncate">
                                                            {slide.titre}
                                                        </h3>
                                                        <Badge
                                                            variant={slide.is_active ? 'default' : 'secondary'}
                                                            className={
                                                                slide.is_active
                                                                    ? 'bg-emerald-600 hover:bg-emerald-600 text-white'
                                                                    : ''
                                                            }
                                                        >
                                                            {slide.is_active ? 'Actif' : 'Masqué'}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs sm:text-sm text-muted-foreground line-clamp-2">
                                                        {slide.message?.trim() || (
                                                            <span className="italic">
                                                                Aucun message d&apos;accompagnement
                                                            </span>
                                                        )}
                                                    </p>
                                                </div>

                                                {/* Actions */}
                                                <div className="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleToggleSlideActive(slide)}
                                                        className="gap-1.5 text-xs"
                                                    >
                                                        {slide.is_active ? (
                                                            <>
                                                                <EyeOff className="size-3.5" />
                                                                Masquer
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Eye className="size-3.5" />
                                                                Activer
                                                            </>
                                                        )}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => {
                                                            setEditingSlide(slide);
                                                            setModalOpen(true);
                                                        }}
                                                        className="gap-1.5 text-xs"
                                                    >
                                                        <Pencil className="size-3.5" />
                                                        Modifier
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => handleDeleteSlide(slide)}
                                                        className="gap-1.5 text-xs"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                        Supprimer
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Référence visuelle des 10 scènes par défaut */}
                        <Card className="bg-muted/20">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm font-semibold">
                                    Galerie de repli SYGES ({defaultScenes.length} scènes par défaut)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Ces images restent toujours disponibles en sécurité si vous repassez en mode
                                    par défaut ou si tous vos slides personnalisés sont masqués/supprimés.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                                    {defaultScenes.map((scene, idx) => (
                                        <div
                                            key={scene.id}
                                            className="group relative aspect-video overflow-hidden rounded-lg border bg-slate-900"
                                        >
                                            <img
                                                src={scene.image}
                                                alt={scene.title}
                                                className="h-full w-full object-cover opacity-90"
                                            />
                                            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/90 to-transparent p-1.5">
                                                <p className="text-[10px] font-medium text-white truncate">
                                                    {idx + 1}. {scene.title}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* =========================================================
                        ONGLET 2 : BRANDING, LOGO & CONTACTS D'ASSISTANCE
                       ========================================================= */}
                    <TabsContent value="branding">
                        <Card>
                            <CardHeader className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <CardTitle className="text-base">
                                        Identité visuelle &amp; Contacts d&apos;assistance
                                    </CardTitle>
                                    <CardDescription>
                                        Adaptez le nom affiché, le logo, le pied de page et les coordonnées
                                        d&apos;aide sur la page de connexion.
                                    </CardDescription>
                                </div>

                                {institution && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handlePrefillFromInstitution}
                                        className="gap-2 shrink-0"
                                    >
                                        <Building2 className="size-4 text-emerald-600" />
                                        Pré-remplir depuis l&apos;Institution
                                    </Button>
                                )}
                            </CardHeader>

                            <CardContent className="space-y-6">
                                {/* Logo de la page de connexion */}
                                <div className="flex flex-col sm:flex-row sm:items-center gap-4 rounded-xl border p-4 bg-muted/20">
                                    <div className="flex size-16 items-center justify-center rounded-xl border bg-slate-950 p-2 shrink-0">
                                        <img
                                            src={effectiveLogoUrl}
                                            alt="Logo de la page de connexion"
                                            className="h-full w-full object-contain"
                                        />
                                    </div>
                                    <div className="flex-1 space-y-1">
                                        <p className="text-sm font-medium">Logo affiché en haut à gauche</p>
                                        <p className="text-xs text-muted-foreground">
                                            PNG, JPG, SVG ou WebP (max 5 Mo). Laissez vide pour conserver le logo actuel.
                                        </p>
                                        <div className="flex flex-wrap items-center gap-2 pt-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => logoInputRef.current?.click()}
                                                className="gap-1.5 text-xs"
                                            >
                                                <Upload className="size-3.5" />
                                                Importer un logo
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    setLogoFile(null);
                                                    setLogoPath(defaultBranding.logo_url);
                                                    setResetLogoToDefault(true);
                                                    if (logoObjectPreview) {
                                                        URL.revokeObjectURL(logoObjectPreview);
                                                        setLogoObjectPreview(null);
                                                    }
                                                }}
                                                className="text-xs text-muted-foreground"
                                            >
                                                Revenir au logo SYGES
                                            </Button>
                                            <input
                                                ref={logoInputRef}
                                                type="file"
                                                accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp"
                                                onChange={handleLogoFileChange}
                                                className="hidden"
                                            />
                                        </div>
                                        {settingsErrors.logo_file && (
                                            <p className="text-xs text-destructive">
                                                {settingsErrors.logo_file}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="brand-name">Nom principal / Sigle</Label>
                                        <Input
                                            id="brand-name"
                                            value={brandName}
                                            onChange={(e) => setBrandName(e.target.value)}
                                            maxLength={120}
                                            placeholder={defaultBranding.brand_name}
                                        />
                                        {settingsErrors.brand_name && (
                                            <p className="text-xs text-destructive">
                                                {settingsErrors.brand_name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="brand-subtitle">Sous-titre / Devise</Label>
                                        <Input
                                            id="brand-subtitle"
                                            value={brandSubtitle}
                                            onChange={(e) => setBrandSubtitle(e.target.value)}
                                            maxLength={160}
                                            placeholder={defaultBranding.brand_subtitle}
                                        />
                                        {settingsErrors.brand_subtitle && (
                                            <p className="text-xs text-destructive">
                                                {settingsErrors.brand_subtitle}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="footer-left">Mention pied de page (gauche)</Label>
                                        <Input
                                            id="footer-left"
                                            value={footerLeft}
                                            onChange={(e) => setFooterLeft(e.target.value)}
                                            maxLength={160}
                                            placeholder={defaultBranding.footer_left}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="footer-right">Mention pied de page (droite)</Label>
                                        <Input
                                            id="footer-right"
                                            value={footerRight}
                                            onChange={(e) => setFooterRight(e.target.value)}
                                            maxLength={80}
                                            placeholder={defaultBranding.footer_right}
                                        />
                                    </div>
                                </div>

                                <Separator />

                                {/* Identité de l'espace connecté (Barre latérale) & Favicon onglet navigateur */}
                                <div className="space-y-4">
                                    <div>
                                        <h3 className="text-sm font-semibold flex items-center gap-2">
                                            <PanelLeft className="size-4 text-primary" />
                                            Barre latérale (après connexion) &amp; Onglet navigateur
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Choisissez si l&apos;espace connecté conserve le logo SYGES par défaut ou
                                            reprend l&apos;identité de votre établissement.
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <label
                                            htmlFor="apply-branding-to-app"
                                            className={`flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition-colors ${
                                                applyBrandingToApp
                                                    ? 'border-emerald-600 bg-emerald-50/50 dark:bg-emerald-950/20'
                                                    : 'bg-muted/20 hover:bg-muted/40'
                                            }`}
                                        >
                                            <Checkbox
                                                id="apply-branding-to-app"
                                                checked={applyBrandingToApp}
                                                onCheckedChange={(checked) =>
                                                    setApplyBrandingToApp(checked === true)
                                                }
                                                className="mt-0.5"
                                            />
                                            <div className="space-y-1">
                                                <span className="text-sm font-medium block">
                                                    Appliquer dans le menu latéral après connexion
                                                </span>
                                                <p className="text-xs text-muted-foreground leading-relaxed">
                                                    Remplace le logo « SYGES / Gestion Scolaire » en haut à gauche une
                                                    fois connecté par votre identité configurée.
                                                </p>
                                            </div>
                                        </label>

                                        <label
                                            htmlFor="sync-favicon"
                                            className={`flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition-colors ${
                                                syncFavicon
                                                    ? 'border-sky-600 bg-sky-50/50 dark:bg-sky-950/20'
                                                    : 'bg-muted/20 hover:bg-muted/40'
                                            }`}
                                        >
                                            <Checkbox
                                                id="sync-favicon"
                                                checked={syncFavicon}
                                                onCheckedChange={(checked) =>
                                                    setSyncFavicon(checked === true)
                                                }
                                                className="mt-0.5"
                                            />
                                            <div className="space-y-1">
                                                <span className="text-sm font-medium flex items-center gap-1.5">
                                                    <Globe className="size-3.5 text-sky-600" />
                                                    Mettre à jour l&apos;icône d&apos;onglet (Favicon)
                                                </span>
                                                <p className="text-xs text-muted-foreground leading-relaxed">
                                                    Affiche votre logo personnalisé dans l&apos;onglet du navigateur dès
                                                    qu&apos;un logo autre que SYGES est défini.
                                                </p>
                                            </div>
                                        </label>
                                    </div>

                                    {applyBrandingToApp && (
                                        <div className="rounded-xl border bg-muted/25 p-4 space-y-4">
                                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b">
                                                <div className="space-y-1">
                                                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                                        Aperçu réel en haut du menu latéral
                                                    </p>
                                                    <div className="inline-flex items-center rounded-lg border bg-background px-3 py-2 shadow-xs max-w-[260px]">
                                                        <div className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-muted/30">
                                                            <img
                                                                src={effectiveSidebarLogoUrl}
                                                                alt="Aperçu logo barre latérale"
                                                                className="size-9 object-contain"
                                                            />
                                                        </div>
                                                        <div className="ml-2 grid min-w-0 text-left text-sm leading-tight">
                                                            <span className="truncate font-bold tracking-tight text-foreground text-base">
                                                                {effectiveSidebarTitle}
                                                            </span>
                                                            <span className="truncate text-xs text-muted-foreground">
                                                                {effectiveSidebarSubtitle}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                {institution?.sigle?.trim() && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => {
                                                            setAppBrandName(institution.sigle?.trim() || '');
                                                            setAppBrandSubtitle('Gestion Scolaire');
                                                        }}
                                                        className="text-xs shrink-0 gap-1.5"
                                                    >
                                                        <Building2 className="size-3.5 text-emerald-600" />
                                                        Utiliser le sigle ({institution.sigle.trim()})
                                                    </Button>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div className="space-y-2">
                                                    <Label htmlFor="app-brand-name">
                                                        Nom court dans le menu latéral
                                                    </Label>
                                                    <Input
                                                        id="app-brand-name"
                                                        value={appBrandName}
                                                        onChange={(e) => setAppBrandName(e.target.value)}
                                                        maxLength={80}
                                                        placeholder={`Laisser vide pour réutiliser « ${brandName.trim() || defaultBranding.brand_name} »`}
                                                    />
                                                    <p className="text-[11px] text-muted-foreground">
                                                        Conseillé : un sigle court (ex. G.S.B.L ESPOIR) pour éviter toute coupure dans le menu.
                                                    </p>
                                                    {settingsErrors.app_brand_name && (
                                                        <p className="text-xs text-destructive">
                                                            {settingsErrors.app_brand_name}
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="app-brand-subtitle">
                                                        Sous-titre dans le menu latéral
                                                    </Label>
                                                    <Input
                                                        id="app-brand-subtitle"
                                                        value={appBrandSubtitle}
                                                        onChange={(e) => setAppBrandSubtitle(e.target.value)}
                                                        maxLength={120}
                                                        placeholder="Laisser vide pour réutiliser la devise / sous-titre"
                                                    />
                                                    {settingsErrors.app_brand_subtitle && (
                                                        <p className="text-xs text-destructive">
                                                            {settingsErrors.app_brand_subtitle}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                                                <div className="space-y-0.5">
                                                    <p className="text-xs font-medium">
                                                        Logo du menu latéral :{' '}
                                                        <span className="text-muted-foreground font-normal">
                                                            {appLogoFile
                                                                ? 'Nouveau logo spécifique sélectionné'
                                                                : appLogoPath.trim() && !resetAppLogoToShared
                                                                  ? 'Logo spécifique configuré'
                                                                  : 'Partagé avec le logo de la page de connexion'}
                                                        </span>
                                                    </p>
                                                    {settingsErrors.app_logo_file && (
                                                        <p className="text-xs text-destructive">
                                                            {settingsErrors.app_logo_file}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2 shrink-0">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => appLogoInputRef.current?.click()}
                                                        className="gap-1.5 text-xs"
                                                    >
                                                        <Upload className="size-3.5" />
                                                        Logo spécifique
                                                    </Button>
                                                    {(appLogoFile || (appLogoPath.trim() && !resetAppLogoToShared)) && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setAppLogoFile(null);
                                                                setAppLogoPath('');
                                                                setResetAppLogoToShared(true);
                                                                if (appLogoObjectPreview) {
                                                                    URL.revokeObjectURL(appLogoObjectPreview);
                                                                    setAppLogoObjectPreview(null);
                                                                }
                                                            }}
                                                            className="text-xs text-muted-foreground"
                                                        >
                                                            Partager le logo login
                                                        </Button>
                                                    )}
                                                    <input
                                                        ref={appLogoInputRef}
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp"
                                                        onChange={handleAppLogoFileChange}
                                                        className="hidden"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <Separator />

                                <div className="space-y-3">
                                    <div>
                                        <h3 className="text-sm font-semibold">
                                            Bloc d&apos;assistance sous le formulaire de connexion
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Laissez le téléphone ou l&apos;email vide pour masquer le bouton correspondant.
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="support-label">Libellé du bloc aide</Label>
                                            <Input
                                                id="support-label"
                                                value={supportLabel}
                                                onChange={(e) => setSupportLabel(e.target.value)}
                                                maxLength={80}
                                                placeholder={defaultBranding.support_label}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="support-phone">Téléphone d&apos;assistance</Label>
                                            <Input
                                                id="support-phone"
                                                value={supportPhone}
                                                onChange={(e) => setSupportPhone(e.target.value)}
                                                maxLength={40}
                                                placeholder="(+237) 6xx xx xx xx"
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="support-email">Email de support</Label>
                                            <Input
                                                id="support-email"
                                                type="email"
                                                value={supportEmail}
                                                onChange={(e) => setSupportEmail(e.target.value)}
                                                maxLength={120}
                                                placeholder="contact@ecole.cm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end pt-2">
                                    <Button
                                        type="button"
                                        onClick={() => saveSettings()}
                                        disabled={savingSettings}
                                        className="gap-2"
                                    >
                                        {savingSettings && <Loader2 className="size-4 animate-spin" />}
                                        <span>Enregistrer l&apos;identité &amp; les contacts</span>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Modale ajout / édition d'un slide */}
            <LoginSlideModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                slide={editingSlide}
            />

            {/* Dialogue de confirmation universel */}
            <ConfirmDialog />
        </>
    );
}

/**
 * Fil d'Ariane transmis au Layout racine (`AppLayout` géré globalement par `app.tsx`).
 */
LoginAppearancePage.layout = {
    breadcrumbs: BREADCRUMBS,
};

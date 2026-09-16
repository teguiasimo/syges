import { Head, Link, usePage } from '@inertiajs/react';
import {
    School,
    Users,
    ShieldCheck,
    GraduationCap,
    BookOpen,
    CalendarRange,
    Briefcase,
    Layers,
    KeyRound,
    FileSpreadsheet,
    Database,
    ChevronRight,
    Lock,
    Edit3,
    FileText,
    Wallet,
    ShieldAlert,
    CalendarDays,
    MessageSquare,
} from 'lucide-react';
import { useTranslation } from '@/contexts/language-context';
import type { User } from '@/types';
import { dashboard } from '@/routes';

/**
 * Type définissant un module administratif affiché dans la grille.
 *
 * @property {string} titleKey - Clé de traduction du titre principal du module.
 * @property {string} descKey - Clé de traduction de la description du module.
 * @property {React.ElementType} icon - Icône Lucide représentative.
 * @property {string} color - Classes CSS de couleur de l'icône.
 * @property {string} bgColor - Classes CSS de fond du conteneur de l'icône.
 * @property {string} [href] - Lien de navigation optionnel.
 * @property {string} [permission] - Permission Spatie requise pour l'affichage.
 */
type AdminModule = {
    titleKey: string;
    descKey: string;
    icon: React.ElementType;
    color: string;
    bgColor: string;
    href?: string;
    permission?: string;
};

/**
 * Modules administratifs actuellement actifs dans SYGES.
 * Les libellés utilisent des clés d'internationalisation réactives.
 */
const adminModules: AdminModule[] = [
    {
        titleKey: 'admin.institution_title',
        descKey: 'admin.institution_desc',
        icon: School,
        color: 'text-indigo-600 dark:text-indigo-400',
        bgColor: 'bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900/40',
        href: '/administration/institution',
        permission: 'institutions.view',
    },
    {
        titleKey: 'admin.periodes_title',
        descKey: 'admin.periodes_desc',
        icon: CalendarRange,
        color: 'text-sky-600 dark:text-sky-400',
        bgColor: 'bg-sky-50 dark:bg-sky-950/50 border border-sky-100 dark:border-sky-900/40',
        href: '/administration/periodes',
        permission: 'periodes.view',
    },
    {
        titleKey: 'admin.matieres_title',
        descKey: 'admin.matieres_desc',
        icon: BookOpen,
        color: 'text-violet-600 dark:text-violet-400',
        bgColor: 'bg-violet-50 dark:bg-violet-950/50 border border-violet-100 dark:border-violet-900/40',
        href: '/administration/matieres',
        permission: 'matieres.view',
    },
    {
        titleKey: 'admin.eleves_title',
        descKey: 'admin.eleves_desc',
        icon: GraduationCap,
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-50 dark:bg-blue-950/50 border border-blue-100 dark:border-blue-900/40',
        href: '/administration/eleves',
        permission: 'eleves.view',
    },
    {
        titleKey: 'admin.personnels_title',
        descKey: 'admin.personnels_desc',
        icon: Briefcase,
        color: 'text-teal-600 dark:text-teal-400',
        bgColor: 'bg-teal-50 dark:bg-teal-950/50 border border-teal-100 dark:border-teal-900/40',
        href: '/administration/personnels',
        permission: 'personnels.view',
    },
    {
        titleKey: 'admin.attributions_title',
        descKey: 'admin.attributions_desc',
        icon: Layers,
        color: 'text-indigo-600 dark:text-indigo-400',
        bgColor: 'bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900/40',
        href: '/administration/attributions',
        permission: 'attributions.view',
    },
    {
        titleKey: 'admin.notes_title',
        descKey: 'admin.notes_desc',
        icon: Edit3,
        color: 'text-rose-600 dark:text-rose-400',
        bgColor: 'bg-rose-50 dark:bg-rose-950/50 border border-rose-100 dark:border-rose-900/40',
        href: '/administration/notes',
        permission: 'notes.view',
    },
    {
        titleKey: 'admin.bulletins_title',
        descKey: 'admin.bulletins_desc',
        icon: FileText,
        color: 'text-sky-600 dark:text-sky-400',
        bgColor: 'bg-sky-50 dark:bg-sky-950/50 border border-sky-100 dark:border-sky-900/40',
        href: '/administration/bulletins',
        permission: 'bulletins.view',
    },
    {
        titleKey: 'admin.finances_title',
        descKey: 'admin.finances_desc',
        icon: Wallet,
        color: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900/40',
        href: '/administration/finances',
        permission: 'finances.view',
    },
    {
        titleKey: 'admin.discipline_title',
        descKey: 'admin.discipline_desc',
        icon: ShieldAlert,
        color: 'text-rose-600 dark:text-rose-400',
        bgColor: 'bg-rose-50 dark:bg-rose-950/50 border border-rose-100 dark:border-rose-900/40',
        href: '/administration/discipline',
        permission: 'discipline.view',
    },
    {
        titleKey: 'admin.emplois_du_temps_title',
        descKey: 'admin.emplois_du_temps_desc',
        icon: CalendarDays,
        color: 'text-indigo-600 dark:text-indigo-400',
        bgColor: 'bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900/40',
        href: '/administration/emplois-du-temps',
        permission: 'emplois_du_temps.view',
    },
    {
        titleKey: 'admin.communication_title',
        descKey: 'admin.communication_desc',
        icon: MessageSquare,
        color: 'text-sky-600 dark:text-sky-400',
        bgColor: 'bg-sky-50 dark:bg-sky-950/50 border border-sky-100 dark:border-sky-900/40',
        href: '/administration/communication',
        permission: 'communication.view',
    },
    {
        titleKey: 'admin.users_title',
        descKey: 'admin.users_desc',
        icon: Users,
        color: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900/40',
        href: '/administration/users',
        permission: 'users.view',
    },
    {
        titleKey: 'admin.roles_title',
        descKey: 'admin.roles_desc',
        icon: ShieldCheck,
        color: 'text-amber-600 dark:text-amber-400',
        bgColor: 'bg-amber-50 dark:bg-amber-950/50 border border-amber-100 dark:border-amber-900/40',
        href: '/administration/roles',
        permission: 'roles.view',
    },
];

/**
 * Composant de la page Administration de SYGES.
 * Seules les cartes des modules pour lesquels l'utilisateur possède une permission
 * sont affichées à l'écran. Toute action ou module non autorisé est complètement masqué.
 * Les libellés sont entièrement bilingues et commutent instantanément.
 *
 * @returns {JSX.Element} La vue de l'administration.
 */
export default function Administration() {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth?: { user?: User } }>().props;
    const permissions = auth?.user?.permissions ?? [];
    const isSuperAdmin = auth?.user?.is_super_admin ?? false;

    // Filtre strict : un module n'est affiché que si l'utilisateur est Super Admin ou possède la permission requise
    const visibleModules = adminModules.filter((module) => {
        if (isSuperAdmin) return true;
        if (!module.permission) return false;
        return permissions.includes(module.permission);
    });

    return (
        <>
            <Head title={t('admin.title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-8">
                {/* Titre et sous-titre de la page */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        {t('admin.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm mt-1.5">
                        {t('admin.subtitle')}
                    </p>
                </div>

                {visibleModules.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-border bg-card/40">
                        <ShieldCheck className="size-12 text-muted-foreground/40 mb-3" />
                        <h3 className="text-base font-semibold text-foreground">{t('admin.no_modules')}</h3>
                        <p className="text-xs text-muted-foreground mt-1 max-w-sm">
                            {t('admin.no_modules_desc')}
                        </p>
                    </div>
                ) : (
                    /* Grille de cartes : exactement 3 par ligne sur desktop */
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        {visibleModules.map((module) => {
                            const IconComponent = module.icon;
                            const isClickable = module.href && module.href !== '#';
                            const CardWrapper = isClickable ? Link : 'div';
                            const title = t(module.titleKey);
                            const description = t(module.descKey);

                            return (
                                <CardWrapper
                                    key={module.titleKey}
                                    href={isClickable ? module.href : undefined}
                                    className={`group flex items-center justify-between rounded-2xl border border-border/80 bg-card p-5 transition-all duration-200 ${
                                        isClickable
                                            ? 'hover:border-slate-300 hover:shadow-sm dark:hover:border-slate-700 cursor-pointer'
                                            : 'cursor-default'
                                    }`}
                                >
                                    {/* Côté gauche : Icône badge + Titre et Description */}
                                    <div className="flex items-center gap-4 min-w-0">
                                        <div
                                            className={`flex size-12 shrink-0 items-center justify-center rounded-xl ${module.bgColor} ${module.color}`}
                                        >
                                            <IconComponent className="size-6" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <h3 className={`text-sm sm:text-base font-bold truncate ${
                                                isClickable ? 'text-foreground group-hover:text-primary transition-colors' : 'text-foreground'
                                            }`}>
                                                {title}
                                            </h3>
                                            <p className="text-xs text-muted-foreground mt-0.5 truncate">
                                                {description}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Côté droit : Flèche de navigation */}
                                    {isClickable && (
                                        <ChevronRight className="size-4 text-muted-foreground/50 group-hover:text-foreground group-hover:translate-x-0.5 transition-all shrink-0 ml-3" />
                                    )}
                                </CardWrapper>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Configuration du layout et des fils d'Ariane pour la page Administration.
 */
Administration.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: '/administration',
        },
    ],
};

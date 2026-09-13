import { Head } from '@inertiajs/react';
import {
    School,
    Users,
    ShieldCheck,
    GraduationCap,
    BookOpen,
    CalendarRange,
    KeyRound,
    FileSpreadsheet,
    Database,
    ChevronRight,
} from 'lucide-react';
import { dashboard } from '@/routes';

/**
 * Type définissant un module administratif affiché dans la grille.
 *
 * @property {string} title - Titre principal du module.
 * @property {string} description - Description courte du module.
 * @property {React.ElementType} icon - Icône Lucide représentative.
 * @property {string} color - Classes CSS de couleur de l'icône.
 * @property {string} bgColor - Classes CSS de fond du conteneur de l'icône.
 * @property {string} [href] - Lien de navigation optionnel.
 */
type AdminModule = {
    title: string;
    description: string;
    icon: React.ElementType;
    color: string;
    bgColor: string;
    href?: string;
};

/**
 * Liste des 9 modules d'administration structurés en 3 lignes de 3 colonnes,
 * inspirés de l'interface GesPar adaptée au contexte scolaire SYGES.
 */
const adminModules: AdminModule[] = [
    {
        title: 'Configuration Établissement',
        description: 'Gérez les propriétés et paramètres de l\'école...',
        icon: School,
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-50 dark:bg-blue-950/50 border border-blue-100 dark:border-blue-900/40',
        href: '#',
    },
    {
        title: 'Gestion des Utilisateurs',
        description: 'Gérer les comptes, personnels et statuts d\'accès.',
        icon: Users,
        color: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900/40',
        href: '#',
    },
    {
        title: 'Rôles & Permissions',
        description: 'Définir les profils et niveaux d\'accès au système.',
        icon: ShieldCheck,
        color: 'text-amber-600 dark:text-amber-400',
        bgColor: 'bg-amber-50 dark:bg-amber-950/50 border border-amber-100 dark:border-amber-900/40',
        href: '#',
    },
    {
        title: 'Classes & Niveaux',
        description: 'Gestion des sections, filières et promotions...',
        icon: GraduationCap,
        color: 'text-sky-600 dark:text-sky-400',
        bgColor: 'bg-sky-50 dark:bg-sky-950/50 border border-sky-100 dark:border-sky-900/40',
        href: '#',
    },
    {
        title: 'Matières & Coefficients',
        description: 'Organisation des cours, barèmes et coefficients...',
        icon: BookOpen,
        color: 'text-teal-600 dark:text-teal-400',
        bgColor: 'bg-teal-50 dark:bg-teal-950/50 border border-teal-100 dark:border-teal-900/40',
        href: '#',
    },
    {
        title: 'Année Scolaire & Périodes',
        description: 'Trimestres, semestres et calendrier académique...',
        icon: CalendarRange,
        color: 'text-indigo-600 dark:text-indigo-400',
        bgColor: 'bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900/40',
        href: '#',
    },
    {
        title: 'Sécurité & Authentification',
        description: 'Contrôle des sessions et protection des accès...',
        icon: KeyRound,
        color: 'text-rose-600 dark:text-rose-400',
        bgColor: 'bg-rose-50 dark:bg-rose-950/50 border border-rose-100 dark:border-rose-900/40',
        href: '#',
    },
    {
        title: 'Évaluations & Examens',
        description: 'Paramétrage des notes, moyennes et bulletins...',
        icon: FileSpreadsheet,
        color: 'text-purple-600 dark:text-purple-400',
        bgColor: 'bg-purple-50 dark:bg-purple-950/50 border border-purple-100 dark:border-purple-900/40',
        href: '#',
    },
    {
        title: 'Sauvegardes & Données',
        description: 'Exportations des données et maintenance système...',
        icon: Database,
        color: 'text-orange-600 dark:text-orange-400',
        bgColor: 'bg-orange-50 dark:bg-orange-950/50 border border-orange-100 dark:border-orange-900/40',
        href: '#',
    },
];

/**
 * Composant de la page Administration de SYGES.
 * Présente une disposition moderne avec 3 cartes horizontales par ligne,
 * badges colorés pastel et flèche d'action à droite, conforme au modèle de capture.
 *
 * @returns {JSX.Element} La vue de l'administration.
 */
export default function Administration() {
    return (
        <>
            <Head title="Administration" />
            <div className="flex h-full flex-1 flex-col gap-6 p-8">
                {/* Titre et sous-titre de la page */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        Administration
                    </h1>
                    <p className="text-muted-foreground text-sm mt-1.5">
                        Panneau de contrôle centralisé pour la gestion du système.
                    </p>
                </div>

                {/* Grille de cartes : exactement 3 par ligne sur desktop (lg:grid-cols-3) */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {adminModules.map((module) => {
                        const IconComponent = module.icon;
                        return (
                            <div
                                key={module.title}
                                className="group flex items-center justify-between rounded-2xl border border-border/80 bg-card p-5 transition-all duration-200 hover:border-slate-300 hover:shadow-sm dark:hover:border-slate-700 cursor-pointer"
                            >
                                {/* Côté gauche : Icône badge + Titre et Description */}
                                <div className="flex items-center gap-4 min-w-0">
                                    <div
                                        className={`flex size-12 shrink-0 items-center justify-center rounded-xl ${module.bgColor} ${module.color}`}
                                    >
                                        <IconComponent className="size-6" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <h3 className="text-sm sm:text-base font-bold text-foreground truncate group-hover:text-primary transition-colors">
                                            {module.title}
                                        </h3>
                                        <p className="text-xs text-muted-foreground mt-0.5 truncate">
                                            {module.description}
                                        </p>
                                    </div>
                                </div>

                                {/* Côté droit : Flèche discrète */}
                                <ChevronRight className="size-4 text-muted-foreground/50 group-hover:text-foreground group-hover:translate-x-0.5 transition-all shrink-0 ml-3" />
                            </div>
                        );
                    })}
                </div>
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

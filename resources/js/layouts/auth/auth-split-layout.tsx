import { Link, usePage } from '@inertiajs/react';
import { GraduationCap, Users, ShieldCheck, Sparkles } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Layout d'authentification "Split" à deux colonnes pour l'application SYGES :
 * - Colonne gauche (écrans larges) : vitrine visuelle présentant SYGES avec dégradés,
 *   effets de flou ambient, logo académique et mise en avant des fonctionnalités clés.
 * - Colonne droite : conteneur de formulaire centré (connexion, réinitialisation, etc.).
 *
 * @param {AuthLayoutProps} props - Propriétés contenant les enfants, le titre et la description.
 * @returns {JSX.Element} Le composant de mise en page d'authentification scindé.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative grid min-h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            {/* Section 1 (Gauche) : Vitrine visuelle et identité SYGES (Desktop uniquement) */}
            <div className="relative hidden h-full flex-col justify-between overflow-hidden bg-slate-950 p-12 text-white lg:flex dark:border-r dark:border-slate-800">
                {/* Arrière-plan avec dégradés riches et effets de lumière ambiants */}
                <div className="absolute inset-0 bg-linear-to-br from-slate-950 via-slate-900 to-indigo-950" />
                <div className="absolute -top-24 -left-24 size-96 rounded-full bg-indigo-600/15 blur-3xl pointer-events-none" />
                <div className="absolute -bottom-24 -right-24 size-96 rounded-full bg-blue-500/15 blur-3xl pointer-events-none" />
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 size-125 rounded-full bg-indigo-500/5 blur-3xl pointer-events-none" />

                {/* En-tête : Logo et Nom de la solution */}
                <div className="relative z-20 flex items-center justify-between">
                    <Link
                        href={home()}
                        className="flex items-center gap-3 text-lg font-semibold tracking-tight transition-opacity hover:opacity-90"
                    >
                        <div className="flex size-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/30">
                            <GraduationCap className="size-6" />
                        </div>
                        <div className="flex flex-col">
                            <span className="text-xl font-bold tracking-tight text-white">
                                {name ?? 'SYGES'}
                            </span>
                            <span className="text-xs font-normal text-indigo-200/70">
                                Gestion Académique & Scolaire
                            </span>
                        </div>
                    </Link>

                    <div className="flex items-center gap-1.5 rounded-full border border-indigo-500/20 bg-indigo-500/10 px-3 py-1 text-xs font-medium text-indigo-300 backdrop-blur-sm">
                        <Sparkles className="size-3.5 text-indigo-400" />
                        Portail Sécurisé
                    </div>
                </div>

                {/* Contenu central : Message d'accueil et modules phares */}
                <div className="relative z-20 my-auto py-10">
                    <div className="space-y-4 max-w-lg">
                        <h2 className="text-3xl font-extrabold tracking-tight text-white sm:text-4xl leading-tight">
                            L'excellence au service du pilotage scolaire.
                        </h2>
                        <p className="text-base text-slate-300 leading-relaxed">
                            Simplifiez la vie de votre établissement : suivi pédagogique des apprenants, gestion des évaluations, relevés de notes et communication centralisée.
                        </p>
                    </div>

                    {/* Liste des modules sous forme de cartes élégantes */}
                    <div className="mt-8 space-y-3 max-w-md">
                        <div className="flex items-start gap-4 rounded-xl border border-white/5 bg-white/3 p-3.5 backdrop-blur-sm transition-colors hover:bg-white/6">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-400">
                                <GraduationCap className="size-5" />
                            </div>
                            <div className="text-sm">
                                <div className="font-semibold text-white">Suivi Pédagogique & Notes</div>
                                <div className="text-xs text-slate-400 mt-0.5">Calcul automatique des moyennes, bulletins scolaires et statistiques.</div>
                            </div>
                        </div>

                        <div className="flex items-start gap-4 rounded-xl border border-white/5 bg-white/3 p-3.5 backdrop-blur-sm transition-colors hover:bg-white/6">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/20 text-blue-400">
                                <Users className="size-5" />
                            </div>
                            <div className="text-sm">
                                <div className="font-semibold text-white">Gestion des Élèves & Classes</div>
                                <div className="text-xs text-slate-400 mt-0.5">Dossiers administratifs, inscriptions et contrôle des présences.</div>
                            </div>
                        </div>

                        <div className="flex items-start gap-4 rounded-xl border border-white/5 bg-white/3 p-3.5 backdrop-blur-sm transition-colors hover:bg-white/6">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                                <ShieldCheck className="size-5" />
                            </div>
                            <div className="text-sm">
                                <div className="font-semibold text-white">Sécurité & Confidentialité</div>
                                <div className="text-xs text-slate-400 mt-0.5">Accès par profil : administration, enseignants, élèves et parents.</div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Pied de page de la colonne gauche : Citation & Crédit */}
                <div className="relative z-20 border-t border-white/10 pt-6">
                    <blockquote className="space-y-1">
                        <p className="text-sm italic text-slate-300">
                            « Une interface unifiée et moderne pour digitaliser l’ensemble des opérations scolaires avec fluidité. »
                        </p>
                        <footer className="text-xs font-medium text-indigo-400">
                            SYGES • Plateforme de Gestion Scolaire
                        </footer>
                    </blockquote>
                </div>
            </div>

            {/* Section 2 (Droite) : Formulaire d'authentification */}
            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-95">
                    {/* Logo pour affichage mobile lorsque la colonne de gauche est masquée */}
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center gap-2 lg:hidden"
                    >
                        <div className="flex size-10 items-center justify-center rounded-xl bg-indigo-600 text-white">
                            <GraduationCap className="size-6" />
                        </div>
                        <span className="text-xl font-bold tracking-tight text-foreground">
                            {name ?? 'SYGES'}
                        </span>
                    </Link>

                    {/* En-tête de la page (titre + description) */}
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground text-sm text-balance">
                            {description}
                        </p>
                    </div>

                    {/* Formulaire injecté (ex: formulaire de login) */}
                    {children}
                </div>
            </div>
        </div>
    );
}

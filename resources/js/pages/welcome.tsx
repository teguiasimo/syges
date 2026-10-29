import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, GraduationCap, ShieldCheck, Users } from 'lucide-react';
import { dashboard, login, register } from '@/routes';

/**
 * Page d'accueil officielle de la plateforme SYGES.
 * Présente l'écosystème de gestion d'établissement scolaire avec l'identité visuelle officielle.
 *
 * @returns {JSX.Element} Page d'accueil SYGES
 */
export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Bienvenue - SYGES Plateforme Scolaire" />
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
                <header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
                    <nav className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-2">
                            <img
                                src="/images/syges-icon.png"
                                alt="SYGES"
                                className="size-8 object-contain rounded-lg"
                            />
                            <span className="font-bold text-base tracking-tight text-foreground">
                                SYGES
                            </span>
                        </div>
                        <div className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-block rounded-lg bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700"
                                >
                                    Accéder au Tableau de Bord
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-block rounded-lg border border-input px-4 py-2 text-xs font-medium text-foreground hover:bg-accent"
                                    >
                                        Connexion
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="inline-block rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700"
                                    >
                                        Créer un compte
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                </header>
                <div className="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow">
                    <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row shadow-xl rounded-2xl overflow-hidden border border-border/60">
                        {/* Volet gauche : Présentation des modules clés */}
                        <div className="flex-1 bg-card p-6 pb-10 text-[13px] leading-[20px] lg:p-14">
                            <div className="flex items-center gap-2 mb-3">
                                <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 px-2.5 py-0.5 text-[11px] font-semibold">
                                    Système Intégré SaaS
                                </span>
                            </div>
                            <h1 className="mb-2 text-2xl font-bold tracking-tight text-foreground">
                                SYGES • Gestion Scolaire
                            </h1>
                            <p className="mb-6 text-sm text-muted-foreground leading-relaxed">
                                Plateforme unifiée de gestion d'établissement scolaire : scolarité, attributions pédagogiques, bulletins conformes APC, finances et discipline.
                            </p>
                            <div className="space-y-4 mb-8">
                                <div className="flex items-start gap-3">
                                    <div className="size-8 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 flex items-center justify-center shrink-0">
                                        <GraduationCap className="size-4" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-xs text-foreground">Pédagogie & Bulletins APC</h3>
                                        <p className="text-xs text-muted-foreground">Gestion des évaluations, compétences ministérielles et génération des bulletins PDF.</p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <div className="size-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <BookOpen className="size-4" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-xs text-foreground">Attribution & Emplois du Temps</h3>
                                        <p className="text-xs text-muted-foreground">Affectation fluide des matières, enseignants et planification des séances de cours.</p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <div className="size-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 flex items-center justify-center shrink-0">
                                        <ShieldCheck className="size-4" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-xs text-foreground">Sécurité & Multi-Tenancy</h3>
                                        <p className="text-xs text-muted-foreground">Contrôle d'accès granulaire RBAC et étanchéité stricte des données de l'établissement.</p>
                                    </div>
                                </div>
                            </div>
                            <div className="pt-4 border-t border-border/50">
                                <Link
                                    href={login()}
                                    className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700 transition-colors"
                                >
                                    Accéder au portail
                                </Link>
                            </div>
                        </div>

                        {/* Volet droit : Logo officiel SYGES */}
                        <div className="relative aspect-[335/240] w-full shrink-0 overflow-hidden bg-linear-to-br from-slate-950 via-slate-900 to-indigo-950 lg:aspect-auto lg:w-[420px] flex flex-col items-center justify-center p-8 text-center">
                            <div className="relative z-10 flex flex-col items-center gap-4">
                                <div className="p-4 bg-white/10 rounded-3xl backdrop-blur-md border border-white/20 shadow-2xl">
                                    <img
                                        src="/images/syges-icon.png"
                                        alt="Logo Officiel SYGES"
                                        className="size-28 sm:size-36 object-contain"
                                    />
                                </div>
                                <div className="space-y-1 mt-2">
                                    <h2 className="text-2xl font-bold tracking-tight text-white">SYGES</h2>
                                    <p className="text-xs text-indigo-200/80 max-w-xs font-medium">
                                        Système de Gestion d'Établissement Scolaire
                                    </p>
                                </div>
                            </div>
                            <div className="absolute inset-0 bg-radial from-indigo-500/15 via-transparent to-transparent pointer-events-none" />
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}

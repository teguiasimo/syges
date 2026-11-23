import React from 'react';
import { Form, Head, usePage } from '@inertiajs/react';
import { User, Lock, AlertCircle, ArrowRight, Phone, Mail } from 'lucide-react';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { store } from '@/routes/login';
import {
    DEFAULT_LOGIN_BRANDING,
    type LoginAppearancePayload,
} from '@/types/login-appearance';

/**
 * Propriétés transmises par Inertia.js à la page de connexion.
 *
 * @property {string} [status] - Message d'état optionnel de la session (ex: confirmation post réinitialisation).
 * @property {boolean} [canResetPassword] - Indique si la fonctionnalité de réinitialisation de mot de passe est activée.
 */
type Props = {
    status?: string;
    canResetPassword?: boolean;
};

/**
 * Composant de la page de connexion professionnelle SYGES.
 *
 * Permet l'authentification unifiée par identifiant (login) ou adresse email et mot de passe.
 * Propose une détection et un affichage clair et immédiat en cas d'identifiants incorrects
 * (bannière d'alerte rouge avec icône et libellé explicite en français/anglais).
 *
 * S'intègre dans le layout scindé (AuthSplitLayout) avec support complet du bilinguisme
 * et adaptation automatique aux écrans larges (écrans 22 pouces et supérieurs).
 *
 * @param {Props} props Les propriétés transmises par le contrôleur Fortify / Laravel.
 * @returns {JSX.Element} Le formulaire d'authentification utilisateur enrichi.
 */
export default function Login({ status }: Props) {
    // Récupération des éventuels messages flash d'erreur et de la configuration d'apparence/contacts
    const { props: pageProps } = usePage<{
        flash?: { error?: string };
        loginAppearance?: LoginAppearancePayload | null;
    }>();
    const flashError = pageProps.flash?.error;
    const branding = pageProps.loginAppearance?.branding ?? DEFAULT_LOGIN_BRANDING;
    const supportLabel = branding.support_label || DEFAULT_LOGIN_BRANDING.support_label;
    const supportPhone = branding.support_phone?.trim() ?? '';
    const supportEmail = branding.support_email?.trim() ?? '';
    const supportPhoneHref = supportPhone ? `tel:${supportPhone.replace(/[^+\d]/g, '')}` : '';

    return (
        <>
            <Head title="Connexion à votre espace" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => {
                    // Vérification de la présence d'une erreur d'authentification ou de session
                    const hasAuthError = Boolean(errors.email || errors.password);
                    const hasSessionBanner = Boolean(! hasAuthError && flashError);

                    return (
                        <>
                            {/* Bannière d'alerte proéminente en cas d'identifiants erronés */}
                            {hasAuthError && (
                                <Alert
                                    variant="destructive"
                                    className="border-red-500/30 bg-red-500/10 text-red-700 py-3 shadow-xs"
                                >
                                    <AlertCircle className="size-4.5 text-red-600 mt-0.5" />
                                    <div className="flex flex-col gap-0.5">
                                        <AlertTitle className="font-bold text-sm">
                                            Identifiants incorrects
                                        </AlertTitle>
                                        <AlertDescription className="text-xs leading-relaxed text-red-600">
                                            {errors.email || errors.password || "Identifiant ou mot de passe incorrect. Veuillez vérifier vos informations de connexion."}
                                        </AlertDescription>
                                    </div>
                                </Alert>
                            )}

                            {/* Bannière d'avertissement lorsque la session de la page a expiré et été renouvelée */}
                            {hasSessionBanner && (
                                <Alert
                                    variant="destructive"
                                    className="border-amber-500/40 bg-amber-500/10 text-amber-800 py-3 shadow-xs"
                                >
                                    <AlertCircle className="size-4.5 text-amber-600 mt-0.5" />
                                    <div className="flex flex-col gap-0.5">
                                        <AlertTitle className="font-bold text-sm">
                                            Session actualisée
                                        </AlertTitle>
                                        <AlertDescription className="text-xs leading-relaxed text-amber-700">
                                            {flashError}
                                        </AlertDescription>
                                    </div>
                                </Alert>
                            )}

                            {/* Message de statut optionnel de session */}
                            {status && (
                                <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-center text-xs font-semibold text-emerald-700">
                                    {status}
                                </div>
                            )}

                            <div className="grid gap-5">
                                {/* Champ 1 : Identifiant (login) ou Email avec icône */}
                                <div className="grid gap-2">
                                    <Label htmlFor="email" className="text-xs font-semibold text-foreground">
                                        Identifiant ou Email <span className="text-red-500">*</span>
                                    </Label>

                                    <div className="relative">
                                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted-foreground">
                                            <User className="size-4" />
                                        </div>
                                        <Input
                                            id="email"
                                            type="text"
                                            name="email"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="username"
                                            placeholder="ex: superadmin ou email@domaine.com"
                                            className={`pl-10 h-11 transition-all ${
                                                errors.email
                                                    ? 'border-red-500 focus-visible:ring-red-500/30'
                                                    : 'focus-visible:ring-indigo-500/20'
                                            }`}
                                        />
                                    </div>
                                    {/* Pas de message en double sous le champ : l'erreur est déjà affichée de manière claire et unique dans l'alerte ci-dessus */}
                                </div>

                                {/* Champ 2 : Mot de passe avec icône et toggle visibilité */}
                                <div className="grid gap-2">
                                    <Label htmlFor="password" className="text-xs font-semibold text-foreground">
                                        Mot de passe <span className="text-red-500">*</span>
                                    </Label>

                                    <div className="relative">
                                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted-foreground z-10">
                                            <Lock className="size-4" />
                                        </div>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="••••••••"
                                            className={`pl-10 h-11 transition-all ${
                                                errors.password
                                                    ? 'border-red-500 focus-visible:ring-red-500/30'
                                                    : 'focus-visible:ring-indigo-500/20'
                                            }`}
                                        />
                                    </div>
                                    {/* Pas de message en double sous le champ : l'erreur est déjà affichée de manière claire et unique dans l'alerte ci-dessus */}
                                </div>

                                {/* Ligne des options : Se souvenir de moi */}
                                <div className="flex items-center justify-between pt-0.5">
                                    <div className="flex items-center space-x-2.5">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                            className="cursor-pointer"
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="cursor-pointer text-xs font-medium text-muted-foreground select-none"
                                        >
                                            Se souvenir de moi
                                        </Label>
                                    </div>
                                </div>

                                {/* Bouton principal de soumission */}
                                <Button
                                    type="submit"
                                    className="mt-2 h-11 w-full cursor-pointer bg-linear-to-r from-blue-700 via-indigo-600 to-indigo-700 hover:from-blue-800 hover:via-indigo-700 hover:to-indigo-800 text-white font-semibold shadow-md shadow-indigo-600/20 transition-all hover:scale-[1.01] active:scale-[0.99]"
                                    tabIndex={4}
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing ? (
                                        <>
                                            <Spinner />
                                            <span>Connexion en cours...</span>
                                        </>
                                    ) : (
                                        <div className="flex items-center justify-center gap-2">
                                            <span>Se connecter</span>
                                            <ArrowRight className="size-4" />
                                        </div>
                                    )}
                                </Button>
                            </div>

                            {/* Ligne d'assistance rapide & contact direct configurables */}
                            {(supportPhone !== '' || supportEmail !== '') && (
                                <div className="mt-5 pt-4 border-t border-border/50 text-center text-xs text-muted-foreground">
                                    <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                                        <span className="text-muted-foreground/80">{supportLabel}</span>
                                        {supportPhone !== '' && (
                                            <a
                                                href={supportPhoneHref}
                                                className="inline-flex items-center gap-1 font-semibold text-foreground hover:text-indigo-600 transition-colors"
                                            >
                                                <Phone className="size-3 text-emerald-600" />
                                                <span>{supportPhone}</span>
                                            </a>
                                        )}
                                        {supportPhone !== '' && supportEmail !== '' && (
                                            <span className="text-muted-foreground/40">•</span>
                                        )}
                                        {supportEmail !== '' && (
                                            <a
                                                href={`mailto:${supportEmail}`}
                                                className="inline-flex items-center gap-1 font-semibold text-foreground hover:text-indigo-600 transition-colors"
                                            >
                                                <Mail className="size-3 text-indigo-500" />
                                                <span>{supportEmail}</span>
                                            </a>
                                        )}
                                    </div>
                                </div>
                            )}
                        </>
                    );
                }}
            </Form>
        </>
    );
}

/**
 * Configuration des métadonnées du layout pour la page de connexion.
 */
Login.layout = {
    title: 'Connexion',
    description: 'Entrez vos identifiants pour continuer',
};

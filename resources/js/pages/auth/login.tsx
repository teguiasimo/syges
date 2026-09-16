import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/contexts/language-context';
import { store } from '@/routes/login';

/**
 * Propriétés attendues par la page de connexion.
 *
 * @property {string} [status] - Message optionnel de statut de session (ex: confirmation après réinitialisation).
 * @property {boolean} [canResetPassword] - Indique si la réinitialisation de mot de passe est activée côté serveur.
 */
type Props = {
    status?: string;
    canResetPassword?: boolean;
};

/**
 * Composant de la page de connexion par identifiant (login) ou email et mot de passe.
 * Le champ d'identification est de type "text" pour permettre la saisie indifférenciée
 * d'un nom d'utilisateur (login) ou d'une adresse email.
 * Les libellés s'adaptent instantanément à la langue sélectionnée (fr/en).
 *
 * @param {Props} props - Les propriétés transmises par Inertia.js.
 * @returns {JSX.Element} Le formulaire d'authentification utilisateur.
 */
export default function Login({ status }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('auth.login_title')} />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            {/* Champ de saisie de l'identifiant (login) ou de l'adresse email */}
                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('auth.login_input_label')}</Label>
                                <Input
                                    id="email"
                                    type="text"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    placeholder={t('auth.login_input_placeholder')}
                                />
                                <InputError message={errors.email} />
                            </div>

                            {/* Champ de saisie du mot de passe */}
                            <div className="grid gap-2">
                                <Label htmlFor="password">{t('auth.password_label')}</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder={t('auth.password_placeholder')}
                                />
                                <InputError message={errors.password} />
                            </div>

                            {/* Case à cocher pour se souvenir de la session */}
                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className="cursor-pointer"
                                />
                                <Label htmlFor="remember" className="cursor-pointer">
                                    {t('auth.remember_me')}
                                </Label>
                            </div>

                            {/* Bouton de validation de la connexion */}
                            <Button
                                type="submit"
                                className="mt-4 w-full cursor-pointer"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {processing ? t('auth.signing_in_button') : t('auth.sign_in_button')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {/* Message de statut optionnel */}
            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

/**
 * Configuration du layout d'authentification pour la page de connexion.
 */
Login.layout = {
    title: 'Connexion à votre compte',
    description: 'Entrez votre identifiant ou votre adresse email ainsi que votre mot de passe pour vous connecter',
};

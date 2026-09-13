import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
 * Composant de la page de connexion standard par email et mot de passe.
 * Les options de connexion par Passkey, lien de mot de passe oublié et inscription ont été retirées.
 *
 * @param {Props} props - Les propriétés transmises par Inertia.js.
 * @returns {JSX.Element} Le formulaire d'authentification utilisateur.
 */
export default function Login({ status }: Props) {
    return (
        <>
            <Head title="Log in" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            {/* Champ de saisie de l'adresse email */}
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            {/* Champ de saisie du mot de passe */}
                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            {/* Case à cocher pour se souvenir de la session */}
                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            {/* Bouton de validation de la connexion */}
                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
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
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};

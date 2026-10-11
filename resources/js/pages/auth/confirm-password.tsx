import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

/**
 * Page de confirmation du mot de passe pour les actions sécurisées.
 *
 * @returns {JSX.Element}
 */
export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmer le mot de passe" />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Mot de passe</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Entrez votre mot de passe"
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full cursor-pointer"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                Confirmer le mot de passe
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirmer le mot de passe',
    description:
        'Ceci est une zone sécurisée de l\'application. Veuillez confirmer votre mot de passe pour continuer.',
};

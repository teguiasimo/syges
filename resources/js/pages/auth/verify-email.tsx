// Components
import { Form, Head, router } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

/**
 * Page de vérification d'adresse email post-inscription.
 *
 * @param {{ status?: string }} props - Message d'état optionnel après envoi du lien.
 * @returns {JSX.Element} Formulaire de renvoi de lien et action de déconnexion.
 */
export default function VerifyEmail({ status }: { status?: string }) {
    /**
     * Annule les requêtes Inertia actives avant d'envoyer la déconnexion.
     */
    const handleLogout = () => {
        router.cancelAll();
        router.flushAll();
    };

    return (
        <>
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            Resend verification email
                        </Button>

                        <TextLink
                            href={logout()}
                            onClick={handleLogout}
                            className="mx-auto block text-sm"
                        >
                            Log out
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Email verification',
    description:
        'Please verify your email address by clicking on the link we just emailed to you.',
};

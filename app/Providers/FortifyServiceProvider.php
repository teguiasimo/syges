<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureResponses();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
    }

    /**
     * Configure les réponses HTTP personnalisées de Fortify pour Inertia.js.
     *
     * Lors d'une déconnexion via Inertia (`X-Inertia`), force une redirection
     * navigateur complète (`Inertia::location`) vers `/login` afin d'annuler les
     * requêtes/prefetches en cours et d'obtenir un couple session/XSRF-TOKEN neuf.
     */
    private function configureResponses(): void
    {
        $this->app->singleton(LogoutResponseContract::class, function () {
            return new class implements LogoutResponseContract
            {
                /**
                 * Génère la réponse de déconnexion adaptée au type de requête.
                 *
                 * @param  \Illuminate\Http\Request  $request  Requête HTTP entrante.
                 * @return \Symfony\Component\HttpFoundation\Response
                 */
                public function toResponse($request)
                {
                    if ($request->header('X-Inertia')) {
                        return Inertia::location(route('login'));
                    }

                    return $request->wantsJson()
                        ? new JsonResponse('', 204)
                        : redirect(Fortify::redirects('logout', '/'));
                }
            };
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        /*
         * Empêche la mise en cache bfcache du formulaire de connexion par le navigateur
         * pour éviter la soumission d'un ancien jeton CSRF via le bouton « Précédent ».
         */
        Fortify::loginView(function (Request $request) {
            $response = Inertia::render('auth/login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'status' => $request->session()->get('status'),
            ])->toResponse($request);

            // Passe par ResponseHeaderBag (typage Symfony Response) plutôt que withHeaders()
            $response->headers->add([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ]);

            return $response;
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Configure la logique d'authentification personnalisée :
     * - Autorise la connexion insensible à la casse par adresse email ou par login (ex: `superadmin`).
     * - Vérifie que le compte n'est pas bloqué ou suspendu (`is_active = true`).
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $identifier = Str::lower(trim((string) $request->input('email', '')));

            $user = User::whereRaw('LOWER(email) = ?', [$identifier])
                ->orWhereRaw('LOWER(login) = ?', [$identifier])
                ->first();

            if ($user && Hash::check($request->password, $user->password)) {
                if (! $user->is_active) {
                    throw ValidationException::withMessages([
                        Fortify::username() => ['Ce compte utilisateur est actuellement suspendu ou désactivé. Veuillez contacter l\'administration.'],
                    ]);
                }

                return $user;
            }

            return null;
        });
    }
}

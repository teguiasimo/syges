<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| Prévention de la condition de concurrence putenv/getenv sous Apache mpm_winnt
|--------------------------------------------------------------------------
| Sous Windows (Laragon Apache mod_php ZTS avec 64 worker threads), l'adaptateur
| PutenvAdapter appelle putenv()/getenv() sur l'environnement global du processus.
| En désactivant putenv(), Dotenv utilise exclusivement $_ENV et $_SERVER isolés
| par thread, éliminant les pertes aléatoires d'APP_KEY/APP_ENV sous charge.
*/
Env::disablePutenv();

/**
 * Construit un cookie XSRF-TOKEN non chiffré prêt à être chiffré par EncryptCookies.
 * Garantit que le client reçoit immédiatement un jeton synchronisé avec la session
 * active lors de la récupération d'une erreur 419 (TokenMismatchException).
 *
 * @param  Request  $request  Requête HTTP disposant d'une session démarrée.
 * @return Cookie Cookie XSRF-TOKEN prêt à attacher à la réponse.
 */
$buildFreshXsrfCookie = static function (Request $request): SymfonyCookie {
    $sessionConfig = config('session');
    $lifetimeMinutes = (int) ($sessionConfig['lifetime'] ?? 120);

    return new SymfonyCookie(
        'XSRF-TOKEN',
        $request->session()->token(),
        time() + (60 * $lifetimeMinutes),
        $sessionConfig['path'] ?? '/',
        $sessionConfig['domain'] ?? null,
        $sessionConfig['secure'] ?? null,
        false,
        false,
        $sessionConfig['same_site'] ?? 'lax',
        $sessionConfig['partitioned'] ?? false
    );
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state', 'locale']);

        $middleware->validateCsrfTokens(except: [
            'administration/eleves/cartes-scolaires/imprimer-planche',
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($buildFreshXsrfCookie): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
        |--------------------------------------------------------------------------
        | Récupération gracieuse des expirations CSRF / Session (HTTP 419)
        |--------------------------------------------------------------------------
        | Évite la boîte modale brute « 419 | Page Expired » d'Inertia et les boucles
        | bloquées lorsque le jeton XSRF a expiré (inactivité > 120 min, onglet
        | en veille, multi-onglets ou retour arrière navigateur).
        */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) use ($buildFreshXsrfCookie) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            $freshXsrfCookie = $request->hasSession() ? $buildFreshXsrfCookie($request) : null;

            // Pour les appels fetch/API JSON purs (hors Inertia), conserver le statut 419 en JSON avec cookie rafraîchi
            if (! $request->header('X-Inertia') && ($request->is('api/*') || $request->expectsJson())) {
                $jsonResponse = response()->json([
                    'message' => 'Votre session a expiré. Le jeton de sécurité vient d\'être actualisé, veuillez réessayer.',
                    'csrf_token' => $request->hasSession() ? $request->session()->token() : null,
                ], 419);

                return $freshXsrfCookie ? $jsonResponse->withCookie($freshXsrfCookie) : $jsonResponse;
            }

            // Déconnexion demandée sur une session déjà expirée : retour direct vers la page de connexion
            if (($request->routeIs('logout') || $request->is('logout')) && ! $request->user()) {
                $logoutRedirect = $request->header('X-Inertia')
                    ? Inertia::location(route('login'))
                    : redirect()->route('login');

                return $freshXsrfCookie ? $logoutRedirect->withCookie($freshXsrfCookie) : $logoutRedirect;
            }

            // Soumission du formulaire de connexion avec jeton périmé ou multi-onglets
            if ($request->routeIs('login.store') || $request->is('login')) {
                if ($request->user()) {
                    $alreadyAuthMessage = 'Une session utilisateur est déjà active. Veuillez vous déconnecter avant de changer de compte.';
                    Inertia::flash('toast', [
                        'type' => 'error',
                        'message' => $alreadyAuthMessage,
                    ]);

                    $redirectDashboard = redirect()->route('dashboard')->with('error', $alreadyAuthMessage);

                    return $freshXsrfCookie ? $redirectDashboard->withCookie($freshXsrfCookie) : $redirectDashboard;
                }

                $expiredLoginMessage = 'Votre session de connexion a expiré et vient d\'être actualisée. Veuillez vous reconnecter.';
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => $expiredLoginMessage,
                ]);

                $redirectLogin = redirect()->route('login')->with('error', $expiredLoginMessage);

                return $freshXsrfCookie ? $redirectLogin->withCookie($freshXsrfCookie) : $redirectLogin;
            }

            // Autres requêtes web/Inertia expirées : rafraîchissement du jeton et retour sur la page précédente
            $expiredMessage = 'Votre session a expiré. La page a été actualisée, veuillez réessayer.';
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $expiredMessage,
            ]);

            $redirectBack = redirect()->back(fallback: route('home'))->with('error', $expiredMessage);

            return $freshXsrfCookie ? $redirectBack->withCookie($freshXsrfCookie) : $redirectBack;
        });
    })->create();

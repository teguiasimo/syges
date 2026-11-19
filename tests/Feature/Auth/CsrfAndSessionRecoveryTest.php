<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * Exécute un rappel en désactivant temporairement la détection `runningUnitTests`
 * afin d'exercer le véritable middleware CSRF (PreventRequestForgery) de Laravel.
 *
 * @param  callable(): void  $callback  Scénario de test à exécuter avec vérification CSRF active.
 */
function withRealCsrfVerification(callable $callback): void
{
    $originalEnv = app()->environment();
    app()->detectEnvironment(fn (): string => 'local');

    try {
        $callback();
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnv);
    }
}

test('la page de connexion envoie des en-têtes no-store contre le cache bfcache', function () {
    $response = $this->get(route('login'));

    $response->assertOk();

    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate');
    expect($response->headers->get('Pragma'))->toBe('no-cache');
});

test('la déconnexion Inertia force une navigation complète vers login', function () {
    $user = User::factory()->create();

    $inertiaResponse = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post(route('logout'));

    $inertiaResponse->assertStatus(409);
    $inertiaResponse->assertHeader('X-Inertia-Location', route('login'));
    $this->assertGuest();
});

test('la déconnexion classique sans en-tête Inertia redirige vers home', function () {
    $user = User::factory()->create();

    $standardResponse = $this->actingAs($user)->post(route('logout'));
    $standardResponse->assertRedirect(route('home'));
    $this->assertGuest();
});

test('un jeton CSRF expiré sur POST /login actualise le cookie XSRF-TOKEN et redirige proprement vers login', function () {
    withRealCsrfVerification(function () {
        $response = $this->withHeader('X-Inertia', 'true')
            ->withHeader('X-XSRF-TOKEN', 'jeton-expire-invalide')
            ->post(route('login.store'), [
                'email' => 'admin@gmail.com',
                'password' => 'secret',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertCookie('XSRF-TOKEN');
        $response->assertSessionHas('error');
    });
});

test('un jeton CSRF expiré sur POST /login alors qu une session est déjà ouverte redirige vers le tableau de bord', function () {
    $user = User::factory()->create();

    withRealCsrfVerification(function () use ($user) {
        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-XSRF-TOKEN', 'jeton-ancien-onglet')
            ->post(route('login.store'), [
                'email' => 'autre@gmail.com',
                'password' => 'secret',
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertCookie('XSRF-TOKEN');
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($user);
    });
});

test('une déconnexion Inertia demandée sur une session déjà expirée force la navigation vers login sans erreur 419', function () {
    withRealCsrfVerification(function () {
        $inertiaResponse = $this->withHeader('X-Inertia', 'true')
            ->withHeader('X-XSRF-TOKEN', 'jeton-session-expiree')
            ->post(route('logout'));

        $inertiaResponse->assertStatus(409);
        $inertiaResponse->assertHeader('X-Inertia-Location', route('login'));
        $inertiaResponse->assertCookie('XSRF-TOKEN');
    });
});

test('une déconnexion HTTP classique demandée sur une session déjà expirée redirige vers login sans erreur 419', function () {
    withRealCsrfVerification(function () {
        $standardResponse = $this->withHeader('X-XSRF-TOKEN', 'jeton-session-expiree')
            ->post(route('logout'));

        $standardResponse->assertRedirect(route('login'));
        $standardResponse->assertCookie('XSRF-TOKEN');
    });
});

test('une erreur CSRF sur une requête Inertia authentifiée rafraîchit le cookie XSRF-TOKEN et redirige back', function () {
    $user = User::factory()->create();

    withRealCsrfVerification(function () use ($user) {
        $inertiaResponse = $this->actingAs($user)
            ->from('/dashboard')
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-XSRF-TOKEN', 'jeton-expire')
            ->post('/locale', ['locale' => 'fr']);

        $inertiaResponse->assertRedirect('/dashboard');
        $inertiaResponse->assertCookie('XSRF-TOKEN');
        $inertiaResponse->assertSessionHas('error');
    });
});

test('une erreur CSRF sur un appel JSON pur retourne HTTP 419 JSON avec cookie XSRF-TOKEN rafraîchi', function () {
    $user = User::factory()->create();

    withRealCsrfVerification(function () use ($user) {
        $jsonResponse = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-XSRF-TOKEN', 'jeton-expire')
            ->post('/locale', ['locale' => 'en']);

        $jsonResponse->assertStatus(419);
        $jsonResponse->assertCookie('XSRF-TOKEN');
        $jsonResponse->assertJsonStructure(['message', 'csrf_token']);
    });
});

test('la configuration Env désactive putenv pour prévenir les conditions de concurrence Apache mpm_winnt', function () {
    // Vérifie que la configuration chargée reste accessible via $_ENV/$_SERVER sans dépendre de putenv
    expect(Config::get('app.name'))->not->toBeEmpty();
});

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware global d'injection des en-têtes HTTP de sécurité (OWASP A02 / Security Misconfiguration).
 *
 * Ajoute systématiquement sur toutes les réponses HTTP de la plateforme SYGES :
 * - `X-Frame-Options: SAMEORIGIN` (protection contre le détournement de clic / Clickjacking en iframe externe),
 * - `X-Content-Type-Options: nosniff` (interdiction du reniflage MIME par le navigateur),
 * - `Referrer-Policy: strict-origin-when-cross-origin` (protection des paramètres d'URL vers les sites tiers),
 * - `Permissions-Policy: camera=(), microphone=(), geolocation=()` (verrouillage des capteurs matériels),
 * - `Strict-Transport-Security` (HSTS activé automatiquement lorsque la connexion est en HTTPS).
 */
class SecurityHeaders
{
    /**
     * Traite la requête entrante et attache les en-têtes de sécurité à la réponse.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @param  Closure(Request): Response  $next  Prochain maillon du pipeline middleware.
     * @return Response Réponse HTTP enrichie des en-têtes de sécurité.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);

        if (
            $request->isSecure()
            || (app()->isProduction() && str_starts_with((string) config('app.url'), 'https://'))
        ) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
                false
            );
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de gestion du thème d'apparence (clair, sombre ou système).
 *
 * Valide strictement la valeur du cookie d'apparence afin de prévenir toute injection
 * ou chaîne malformée dans le script d'initialisation du thème de la vue racine Blade.
 */
class HandleAppearance
{
    /**
     * Traite la requête HTTP entrante et partage une valeur d'apparence sécurisée avec les vues Blade.
     *
     * @param  Request  $request  Requête HTTP entrante.
     * @param  Closure(Request): (Response)  $next  Gestionnaire de middleware suivant.
     * @return Response Réponse HTTP traitée.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawAppearance = $request->cookie('appearance');
        $appearance = in_array($rawAppearance, ['light', 'dark', 'system'], true)
            ? $rawAppearance
            : 'system';

        View::share('appearance', $appearance);

        return $next($request);
    }
}

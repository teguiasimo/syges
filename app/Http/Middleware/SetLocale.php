<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware chargé de définir la langue active (locale) de l'application.
 * 
 * Lit la langue choisie depuis la session utilisateur, ou le cookie persistant 'locale'.
 * Si aucune préférence n'est détectée, utilise la valeur par défaut configurée ('fr').
 * Valide strictement que la locale fait partie des langues supportées ('fr', 'en').
 */
class SetLocale
{
    /**
     * Liste des langues officiellement supportées par SYGES.
     *
     * @var array<string>
     */
    protected array $supportedLocales = ['fr', 'en'];

    /**
     * Intercepte et traite la requête entrante pour configurer la locale de l'application.
     *
     * @param  \Illuminate\Http\Request  $request Requête HTTP entrante.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next Callback vers le middleware suivant.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Récupération de la locale : priorité à la session, puis au cookie, puis à la configuration par défaut
        $locale = $request->session()->get('locale')
            ?? $request->cookie('locale')
            ?? config('app.locale', 'fr');

        // 2. Validation stricte de la langue pour éviter toute injection
        if (! in_array($locale, $this->supportedLocales, true)) {
            $locale = 'fr';
        }

        // 3. Application de la locale au niveau du framework Laravel et de Carbon
        App::setLocale($locale);

        return $next($request);
    }
}

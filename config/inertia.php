<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | Désactivé par défaut tant qu'aucun daemon Node SSR n'écoute sur le port 13714 :
    | évite un délai TCP mort (~415 ms) à chaque chargement complet de page.
    | Activable à la demande via `INERTIA_SSR_ENABLED=true` dans `.env`.
    |
    */

    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
        'url' => (string) env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Inertia DevTools (v3)
    |--------------------------------------------------------------------------
    |
    | Par défaut, Inertia v3 active l'enregistreur DevTools en environnement `local`,
    | ce qui parcourt récursivement toutes les clés de props et ajoute plusieurs secondes
    | sur les pages volumineuses. Désactivé par défaut pour des performances représentatives ;
    | activable ponctuellement via `INERTIA_DEVTOOLS_ENABLED=true`.
    |
    */

    'devtools' => [
        'enabled' => (bool) env('INERTIA_DEVTOOLS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];

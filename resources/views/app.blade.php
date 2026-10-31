<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Script en ligne sécurisé pour détecter la préférence du mode sombre et l'appliquer immédiatement sans blocage JS --}}
        <script>
            (function() {
                const appearance = {{ json_encode(in_array($appearance ?? 'system', ['light', 'dark', 'system'], true) ? $appearance : 'system') }};

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{-- 
            Identité visuelle & Favicons personnalisés SYGES
            Configuration multi-formats (PNG 32x32, PNG 16x16, ICO universel, Apple Touch Icon)
            Utilisation d'un paramètre de version ?v=syges2 pour forcer l'invalidation du cache navigateur (Favicons cache)
        --}}
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=syges2">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=syges2">
        <link rel="shortcut icon" href="/favicon.ico?v=syges2">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=syges2">
        <link rel="manifest" href="/site.webmanifest?v=syges2">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'SYGES') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

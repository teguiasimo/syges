<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light">

        {{-- Verrouillage permanent du thème clair pour l'ensemble de l'application SYGES --}}
        <style>
            html {
                background-color: oklch(1 0 0);
                color-scheme: light;
            }
        </style>

        @php
            $sygesAppBranding = app(\App\Services\LoginAppearanceService::class)->getAppBranding();
            $customFaviconUrl = $sygesAppBranding['favicon_url'] ?? null;
            $customAppTitle = ! empty($sygesAppBranding['use_custom_app_branding'])
                ? ($sygesAppBranding['brand_name'] ?? config('app.name', 'SYGES'))
                : config('app.name', 'SYGES');
        @endphp

        {{-- 
            Identité visuelle & Favicons : utilise le logo personnalisé lorsque l'administrateur
            l'a activé pour l'onglet du navigateur, ou les favicons vectoriels SYGES par défaut.
        --}}
        @if ($customFaviconUrl)
            <link rel="icon" data-syges-favicon="custom" href="{{ $customFaviconUrl }}">
            <link rel="shortcut icon" data-syges-favicon="custom" href="{{ $customFaviconUrl }}">
            <link rel="apple-touch-icon" data-syges-favicon="custom" href="{{ $customFaviconUrl }}">
        @else
            <link rel="icon" type="image/svg+xml" data-syges-favicon="default" href="/favicon.svg?v=syges3">
            <link rel="icon" type="image/png" sizes="32x32" data-syges-favicon="default" href="/favicon-32x32.png?v=syges3">
            <link rel="icon" type="image/png" sizes="16x16" data-syges-favicon="default" href="/favicon-16x16.png?v=syges3">
            <link rel="shortcut icon" data-syges-favicon="default" href="/favicon.ico?v=syges3">
            <link rel="apple-touch-icon" sizes="180x180" data-syges-favicon="default" href="/apple-touch-icon.png?v=syges3">
        @endif
        <link rel="manifest" href="/site.webmanifest?v=syges3">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $customAppTitle }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

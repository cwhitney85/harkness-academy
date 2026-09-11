<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Light-first is a standing constraint (D21); dark mode is v1.1 (spec 8.5.5). --}}
        <style>
            html {
                background-color: oklch(0.962 0.01 95);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Spec 7.1. Install is found in the browser's own menu (7.3, 7.4);
             this is what the browser reads once the student picks it. The two
             apple tags are for iOS, which honours the manifest's `display` and
             `name` only since 16.4 and would otherwise open with browser
             chrome and title the home-screen icon after whichever page was
             open when it was added — the page titles here are per screen. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Harkness Academy">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="antialiased">
        <x-inertia::app />
    </body>
</html>

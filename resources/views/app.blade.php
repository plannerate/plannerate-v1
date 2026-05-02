<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

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

        <link rel="icon" href="/img/logo.jpg" sizes="any">
        <link rel="icon" href="/img/logo.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/img/logo.jpg">
        <meta name="plannerate-reverb-key" content="{{ env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY')) }}">
        <meta name="plannerate-reverb-host" content="{{ env('VITE_REVERB_HOST', env('REVERB_HOST')) }}">
        <meta name="plannerate-reverb-port" content="{{ env('VITE_REVERB_PORT', env('REVERB_PORT', 8080)) }}">
        <meta name="plannerate-reverb-scheme" content="{{ env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'http')) }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

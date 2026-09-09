<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#f5f3ee">

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
                background-color: #f3f0ea;
            }

            html.dark {
                background-color: #0e171a;
            }

            #app-loading {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: grid;
                place-items: center;
                background: #f3f0ea;
                color: #17262b;
                font: 500 0.95rem/1.5 system-ui, sans-serif;
            }

            html.dark #app-loading {
                background: #0e171a;
                color: #f3f0ea;
            }

            #app-loading::before {
                width: 1.25rem;
                height: 1.25rem;
                margin-right: 0.6rem;
                border: 2px solid currentColor;
                border-right-color: transparent;
                border-radius: 9999px;
                animation: app-loading-spin 0.75s linear infinite;
                content: '';
            }

            @keyframes app-loading-spin {
                to { transform: rotate(360deg); }
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Caldas Gestão') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div id="app-loading" role="status" aria-live="polite">Carregando…</div>
        <x-inertia::app />
    </body>
</html>

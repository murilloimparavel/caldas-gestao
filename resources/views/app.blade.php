@php
    $publicUnit = ($page['component'] ?? null) === 'public-booking/show' ? ($page['props']['unit'] ?? []) : [];
    $publicMeta = $publicUnit['public_meta'] ?? null;
    $isAtelierBooking = ($publicUnit['template_key'] ?? null) === 'atelier-barber';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['atelier-booking' => $isAtelierBooking, 'dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ $isAtelierBooking ? '#0e0e0e' : '#f5f3ee' }}">

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

            html.atelier-booking,
            html.atelier-booking.dark,
            html.atelier-booking body,
            html.atelier-booking #app-loading,
            html.atelier-booking.dark #app-loading {
                background-color: #0e0e0e;
                color: #eef5f2;
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

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            @if($publicMeta)
                <title>{{ $publicMeta['title'] }}</title>
                <meta data-inertia="description" name="description" content="{{ $publicMeta['description'] }}">
                <meta data-inertia="robots" name="robots" content="{{ $publicMeta['robots'] }}">
                <meta data-inertia="og:title" property="og:title" content="{{ $publicMeta['title'] }}">
                <meta data-inertia="og:description" property="og:description" content="{{ $publicMeta['description'] }}">
                <meta data-inertia="og:type" property="og:type" content="website">
                <meta data-inertia="og:locale" property="og:locale" content="pt_BR">
                <meta data-inertia="twitter:card" name="twitter:card" content="{{ $publicMeta['image'] ? 'summary_large_image' : 'summary' }}">
                <meta data-inertia="twitter:title" name="twitter:title" content="{{ $publicMeta['title'] }}">
                <meta data-inertia="twitter:description" name="twitter:description" content="{{ $publicMeta['description'] }}">
                @if($publicMeta['canonical_url'])
                    <link data-inertia="canonical" rel="canonical" href="{{ $publicMeta['canonical_url'] }}">
                    <meta data-inertia="og:url" property="og:url" content="{{ $publicMeta['canonical_url'] }}">
                @endif
                @if($publicMeta['image'])
                    <meta data-inertia="og:image" property="og:image" content="{{ $publicMeta['image'] }}">
                    <meta data-inertia="twitter:image" name="twitter:image" content="{{ $publicMeta['image'] }}">
                @endif
                <script data-inertia="business-schema" type="application/ld+json">{!! json_encode($publicMeta['structured_data'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
            @else
                <title>{{ config('app.name', 'Caldas Gestão') }}</title>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div id="app-loading" role="status" aria-live="polite">Carregando…</div>
        <x-inertia::app />
    </body>
</html>

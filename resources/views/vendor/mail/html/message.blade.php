<x-mail::layout>
<x-slot:head>
<style>
:root {
    --caldas-primary-color: {{ config('branding.primary_color', '#3167d8') }};
}
</style>
</x-slot:head>

<x-slot:header>
<x-mail::header :url="config('app.url')">
@if (config('branding.logo_url'))
<img src="{{ config('branding.logo_url') }}" class="logo" alt="{{ config('branding.name') }}">
@else
{{ config('branding.name') }}
@endif
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('branding.name') }}. Todos os direitos reservados.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

<x-mail::layout>
<x-slot:header>
{{ config('branding.name') }} — {{ config('app.url') }}
</x-slot:header>

{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
© {{ date('Y') }} {{ config('branding.name') }}. Todos os direitos reservados.
</x-slot:footer>
</x-mail::layout>

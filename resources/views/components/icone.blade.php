@props(['nom'])
<svg {{ $attributes->merge(['class' => 'icone']) }} aria-hidden="true" focusable="false"><use href="{{ \App\Portail\Liens::icones() }}#{{ $nom }}"></use></svg>

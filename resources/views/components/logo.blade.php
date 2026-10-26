@props(['hauteur' => 56, 'alt' => ''])
{{--
    Logo de la Commune (public/logo.jpg, versions web produites par « php outils-dev/deriver-logo.php »).
    Décoratif par défaut (alt vide) : il accompagne toujours le nom « Commune de Makary » écrit en toutes lettres.
--}}
@php($base = $hauteur <= 64 ? [64, 128] : [128, 256])
<img src="{{ asset("images/logo-makary-{$base[0]}.webp") }}"
     srcset="{{ asset("images/logo-makary-{$base[0]}.webp") }} 1x, {{ asset("images/logo-makary-{$base[1]}.webp") }} 2x"
     width="{{ (int) round($hauteur * 240 / 256) }}" height="{{ $hauteur }}" alt="{{ $alt }}" decoding="async"
     {{ $attributes->merge(['class' => 'flex-none']) }}>

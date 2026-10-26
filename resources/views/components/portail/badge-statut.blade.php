@props(['statut'])
<span {{ $attributes->merge(['class' => 'badge']) }} data-statut="{{ $statut['code'] }}">{{ $statut['libelle'] }}</span>

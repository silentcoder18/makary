@props(['etat'])
@php($etat = $etat instanceof \App\Domaine\Enums\EtatEditorial ? $etat : \App\Domaine\Enums\EtatEditorial::from($etat))
<span {{ $attributes->merge(['class' => 'badge']) }} data-etat="{{ $etat->value }}">{{ $etat->libelle() }}</span>

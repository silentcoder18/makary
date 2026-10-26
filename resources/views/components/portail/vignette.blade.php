@props(['media' => null, 'icone' => 'chantier', 'tailles' => '(min-width: 64rem) 24rem, 100vw', 'prioritaire' => false])
{{-- Photo publiée (droit d'usage vérifié), sinon illustration du secteur en CSS, sans image à télécharger --}}
<div {{ $attributes->merge(['class' => 'vignette']) }}>
    @if ($media)
        <img src="{{ \App\Portail\Liens::fichier($media['fichier'], $media['titre']) }}" alt="{{ $media['texte_alternatif'] }}"
             width="1200" height="800" sizes="{{ $tailles }}" decoding="async" @if (! $prioritaire) loading="lazy" @endif>
    @else
        <x-icone nom="{{ $icone }}" class="icone size-16 opacity-90 [stroke-width:1.3]" />
    @endif
    {{ $slot }}
</div>

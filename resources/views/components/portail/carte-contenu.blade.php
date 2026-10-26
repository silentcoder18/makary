@props(['contenu', 'titreNiveau' => 'h3', 'icone' => 'journal'])
<article class="carte h-full">
    <x-portail.vignette :media="$contenu['image']" :icone="$icone" />
    <div class="flex flex-1 flex-col p-6">
        <p class="text-sm font-semibold text-lagune">
            <time datetime="{{ $contenu->premierePublication->toDateString() }}">{{ $format->date($contenu->premierePublication) }}</time>
        </p>
        <{{ $titreNiveau }} class="mt-2 text-xl">
            <a href="{{ \App\Portail\Liens::contenu($contenu) }}" class="lien-etendu">{{ $contenu['titre'] }}</a>
        </{{ $titreNiveau }}>
        @if ($contenu['resume'])
            <p class="mt-3 line-clamp-3 text-ardoise">{{ $contenu['resume'] }}</p>
        @endif
        <span class="lien-fleche mt-auto pt-5 text-lac">Lire <x-icone nom="fleche" class="icone size-5" /></span>
    </div>
</article>

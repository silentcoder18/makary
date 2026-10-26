@props(['evenement', 'titreNiveau' => 'h3'])
@php($debut = \Illuminate\Support\Carbon::parse($evenement->get('attributs.date_debut')))
<article class="carte flex-row items-start gap-4 p-5">
    <p class="pave-date" aria-hidden="true"><strong>{{ $debut->format('j') }}</strong><span>{{ $debut->translatedFormat('M') }}</span></p>
    <div class="min-w-0">
        <{{ $titreNiveau }} class="text-lg">
            <a href="{{ route('evenements.show', $evenement['slug']) }}" class="lien-etendu">{{ $evenement['titre'] }}</a>
        </{{ $titreNiveau }}>
        <p class="mt-1 text-sm text-ardoise">
            <time datetime="{{ $debut->toIso8601String() }}">{{ $format->date($debut, 'l j F Y \à H\hi') }}</time>
            @if ($evenement->get('attributs.lieu'))
                <span class="mt-1 flex items-center gap-1.5"><x-icone nom="lieu" class="icone size-4" /> {{ $evenement->get('attributs.lieu') }}</span>
            @endif
        </p>
    </div>
</article>

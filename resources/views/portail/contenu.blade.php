{{-- Fiche d'un contenu publié : actualité, événement, site du patrimoine, service --}}
@php($type = $contenu['type_contenu'])
{{-- La rubrique vient de la route (fil d'Ariane) ; seule l'étape intermédiaire est ajoutée. --}}
@php($etape = match ($type) {
    'evenement' => ['Événements' => route('evenements')],
    'service' => ['Services municipaux' => route('mairie.services')],
    default => [],
})
<x-portail.gabarit :titre="$contenu['titre']" :description="$contenu['resume'] ?? $contenu['titre']">
    <x-portail.entete-page :titre="$contenu['titre']" :chapeau="$contenu['resume']" :surtitre="$surtitre" :ariane="$etape" :sous-navigation="[]">
        <p class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-ardoise">
            @if ($type === 'evenement' && $contenu->get('attributs.date_debut'))
                <span class="flex items-center gap-2"><x-icone nom="calendrier" class="icone size-5 text-lagune" />
                    <time datetime="{{ $contenu->get('attributs.date_debut') }}">{{ $format->date($contenu->get('attributs.date_debut'), 'l j F Y \à H\hi') }}</time></span>
                @if ($contenu->get('attributs.lieu'))
                    <span class="flex items-center gap-2"><x-icone nom="lieu" class="icone size-5 text-lagune" />{{ $contenu->get('attributs.lieu') }}</span>
                @endif
            @else
                <span>Publié le <time datetime="{{ $contenu->premierePublication->toDateString() }}">{{ $format->date($contenu->premierePublication) }}</time></span>
                @if ($contenu->version > 1)
                    <span>Mis à jour le {{ $format->date($contenu->publieLe) }}</span>
                @endif
            @endif
            @if ($contenu['localite'])
                <a href="{{ route('decouvrir.localite', $contenu['localite']['slug']) }}" class="flex items-center gap-2"><x-icone nom="lieu" class="icone size-5" />{{ $contenu['localite']['nom'] }}</a>
            @endif
        </p>
    </x-portail.entete-page>

    <div class="conteneur py-12 lg:py-16">
        @if ($contenu['image'])
            <figure class="mb-10 max-w-4xl">
                <x-portail.vignette :media="$contenu['image']" class="rounded-carte" tailles="(min-width: 64rem) 56rem, 100vw" prioritaire />
                <figcaption class="mt-2 text-sm text-ardoise">{{ $contenu['image']['titre'] }} — {{ $contenu['image']['auteur'] }}</figcaption>
            </figure>
        @endif
        <div class="prose-makary">{!! $format->markdown($contenu['corps']) !!}</div>

        @if ($contenu->get('attributs.acces'))
            <p class="mt-8 max-w-3xl rounded-carte bg-sable p-5"><strong>Accès :</strong> {{ $contenu->get('attributs.acces') }}</p>
        @endif
    </div>
</x-portail.gabarit>

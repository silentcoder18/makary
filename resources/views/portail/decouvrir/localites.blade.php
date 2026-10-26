<x-portail.gabarit titre="Localités">
    <x-portail.entete-page titre="Les localités de Makary" chapeau="Villages, quartiers et espace urbain de la commune, d’après la liste officielle et ses sources." />

    <div class="conteneur py-12 lg:py-16">
        @if ($localites->isEmpty())
            <x-portail.a-fournir quoi="La liste officielle des localités" detail="C-013" />
        @else
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($localites as $localite)
                    <li class="carte p-6">
                        <p class="text-sm font-semibold text-lagune">{{ ['village' => 'Village', 'quartier' => 'Quartier', 'espace_urbain' => 'Espace urbain'][$localite['categorie']] ?? 'Localité' }}</p>
                        <h2 class="mt-1 text-xl"><a href="{{ route('decouvrir.localite', $localite['slug']) }}" class="lien-etendu">{{ $localite['nom'] }}</a></h2>
                        <p class="mt-3 text-sm text-ardoise">{{ $format->source($localite['source']) }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

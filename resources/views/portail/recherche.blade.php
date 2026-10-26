@php($libelles = ['contenu' => 'Page', 'projet' => 'Projet', 'document' => 'Document', 'localite' => 'Localité', 'elu' => 'Élu'])
<x-portail.gabarit titre="Recherche">
    <x-portail.entete-page titre="Rechercher sur le site" :sous-navigation="[]">
        <form action="{{ route('recherche') }}" method="get" role="search" class="mt-8 flex max-w-2xl gap-2">
            <label for="champ-recherche" class="sr-only">Que cherchez-vous ?</label>
            <input id="champ-recherche" name="q" type="search" value="{{ $terme }}" class="champ" placeholder="Que cherchez-vous ?" maxlength="100">
            <button type="submit" class="bouton bouton-principal"><x-icone nom="recherche" class="icone size-5" /> Rechercher</button>
        </form>
    </x-portail.entete-page>

    <div class="conteneur py-12 lg:py-16">
        @if ($terme === '')
            <p class="text-ardoise">Saisissez au moins deux caractères.</p>
        @else
            <p class="font-semibold" role="status">{{ $resultats->count() }} {{ $resultats->count() > 1 ? 'résultats' : 'résultat' }} pour « {{ $terme }} »</p>
            @if ($resultats->isEmpty())
                <p class="mt-4 text-ardoise">Essayez un autre mot, ou consultez les <a href="{{ route('demarches') }}">démarches</a>, les <a href="{{ route('projets') }}">projets</a> et les <a href="{{ route('documents') }}">documents</a>.</p>
            @else
                <ul class="mt-6 max-w-3xl divide-y divide-argile">
                    @foreach ($resultats as $resultat)
                        @php($fiche = $resultat['fiche'])
                        @php($lien = \App\Portail\Liens::vers($resultat['type'], $fiche))
                        @if ($lien)
                            <li class="py-4">
                                <p class="text-sm font-semibold text-lagune">{{ $libelles[$resultat['type']->value] }}</p>
                                <h2 class="text-lg"><a href="{{ $lien }}">{{ $fiche['titre'] ?? $fiche['nom'] ?? $fiche['nom_affiche'] }}</a></h2>
                                @if ($fiche['resume'])
                                    <p class="mt-1 text-ardoise">{{ \Illuminate\Support\Str::limit($fiche['resume'], 200) }}</p>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</x-portail.gabarit>

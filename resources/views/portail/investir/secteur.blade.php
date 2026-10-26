<x-portail.gabarit :titre="$libelle">
    <x-portail.entete-page :titre="$libelle" :ariane="['Secteurs' => route('investir.secteurs')]" chapeau="Opportunités et projets publiés dans ce secteur." :sous-navigation="[]" />

    <div class="conteneur py-12 lg:py-16">
        @if ($opportunites->isNotEmpty())
            <h2 class="titre-section">Opportunités</h2>
            <ul class="mt-6 grid gap-6 md:grid-cols-2">
                @foreach ($opportunites as $opportunite)
                    <li class="carte p-6"><h3 class="text-xl">{{ $opportunite['titre'] }}</h3><p class="mt-2 text-ardoise">{{ $opportunite['resume'] }}</p></li>
                @endforeach
            </ul>
        @endif
        <h2 class="titre-section mt-12">Projets</h2>
        @if ($projets->isEmpty())
            <p class="mt-4 text-ardoise">Aucun projet publié dans ce secteur.</p>
        @else
            <ul class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($projets as $projet)
                    <li><x-portail.carte-projet :projet="$projet" /></li>
                @endforeach
            </ul>
        @endif
        <a href="{{ route('investir.interet') }}" class="bouton bouton-principal mt-12">Manifester mon intérêt</a>
    </div>
</x-portail.gabarit>

<x-portail.gabarit titre="Plan du site">
    <x-portail.entete-page titre="Plan du site" :sous-navigation="[]" />

    <div class="conteneur grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-3 lg:py-16">
        @foreach (\App\Portail\Navigation::PRINCIPALE as $rubrique)
            <section>
                <h2 class="text-xl"><a href="{{ route($rubrique['route']) }}">{{ $rubrique['libelle'] }}</a></h2>
                <ul class="mt-3 space-y-1.5">
                    @foreach ($rubrique['sous'] as $route => $libelle)
                        <li><a href="{{ route($route) }}">{{ $libelle }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endforeach
        <section>
            <h2 class="text-xl">Services</h2>
            <ul class="mt-3 space-y-1.5">
                @foreach (\App\Portail\Navigation::SERVICE as $lien)
                    <li><a href="{{ route($lien['route']) }}">{{ $lien['libelle'] }}</a></li>
                @endforeach
                <li><a href="{{ route('recherche') }}">Recherche</a></li>
            </ul>
        </section>
        <section>
            <h2 class="text-xl">Informations</h2>
            <ul class="mt-3 space-y-1.5">
                @foreach (\App\Portail\Navigation::INFORMATIONS as $route => $libelle)
                    <li><a href="{{ route($route) }}">{{ $libelle }}</a></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-portail.gabarit>

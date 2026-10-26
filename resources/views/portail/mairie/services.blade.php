<x-portail.gabarit titre="Services municipaux">
    <x-portail.entete-page titre="Les services municipaux" chapeau="Ce que fait chaque service de la mairie, et comment le joindre." />

    <div class="conteneur py-12 lg:py-16">
        @if ($services->isEmpty())
            <x-portail.a-fournir quoi="La liste des services municipaux" detail="missions, responsables et coordonnées" />
        @else
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <li class="carte p-6">
                        <x-icone nom="administration" class="icone size-8 text-terre" />
                        <h2 class="mt-4 text-xl"><a href="{{ route('mairie.service', $service['slug']) }}" class="lien-etendu">{{ $service['titre'] }}</a></h2>
                        <p class="mt-2 text-ardoise">{{ $service['resume'] }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

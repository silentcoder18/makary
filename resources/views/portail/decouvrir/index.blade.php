<x-portail.gabarit titre="Découvrir Makary">
    <x-portail.entete-page titre="Découvrir Makary" chapeau="Histoire, territoire, localités, économie et équipements : la commune en un coup d’œil." />

    <div class="conteneur grid gap-12 py-12 lg:grid-cols-[1fr_20rem] lg:py-16">
        <div>
            @if ($presentation)
                <h2 class="titre-section">{{ $presentation['titre'] }}</h2>
                <div class="prose-makary mt-6">{!! $format->markdown($presentation['corps']) !!}</div>
            @else
                <x-portail.a-fournir quoi="La présentation de la commune" />
            @endif

            <h2 class="titre-section mt-16">Explorer</h2>
            <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['decouvrir.histoire', 'journal', 'Histoire', 'Les grandes étapes de la commune.'],
                    ['decouvrir.territoire', 'boussole', 'Territoire', 'Situation, géographie, chiffres clés sourcés.'],
                    ['decouvrir.localites', 'lieu', 'Localités', $localites->count().' localités référencées.'],
                    ['decouvrir.economie', 'graphique', 'Économie', 'Activités, filières et atouts.'],
                    ['decouvrir.equipements', 'administration', 'Équipements', $equipements->count().' écoles, centres de santé, marchés et forages.'],
                    ['carte', 'lieu', 'Carte', 'Localités, équipements et projets.'],
                ] as [$route, $icone, $libelle, $detail])
                    <li class="carte flex-row items-center gap-4 p-5">
                        <span class="grid size-12 flex-none place-items-center rounded-xl bg-sable text-lac"><x-icone nom="{{ $icone }}" /></span>
                        <span><a href="{{ route($route) }}" class="lien-etendu text-lg font-extrabold">{{ $libelle }}</a><span class="block text-sm text-ardoise">{{ $detail }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
        <aside aria-labelledby="titre-localites">
            <h2 id="titre-localites" class="text-xl">Nos localités</h2>
            <ul class="mt-4 divide-y divide-argile rounded-carte border border-argile bg-white">
                @forelse ($localites as $localite)
                    <li><a href="{{ route('decouvrir.localite', $localite['slug']) }}" class="flex items-center justify-between gap-3 px-4 py-3 font-semibold no-underline hover:bg-sable">
                        {{ $localite['nom'] }} <x-icone nom="fleche" class="icone size-4 text-ardoise" /></a></li>
                @empty
                    <li class="p-4 text-ardoise">{{ $format->aFournir('liste officielle des localités (C-013)') }}</li>
                @endforelse
            </ul>
        </aside>
    </div>
</x-portail.gabarit>

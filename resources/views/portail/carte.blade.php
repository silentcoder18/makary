<x-portail.gabarit titre="Carte">
    <x-portail.entete-page titre="Carte de la commune" chapeau="Localités, équipements et projets. Une liste équivalente à la carte est toujours disponible ci-dessous." />

    <div class="conteneur py-12 lg:py-16">
        @if ($positionnes === 0)
            <div class="a-fournir flex gap-4 p-6">
                <x-icone nom="lieu" class="icone size-7 text-terre" />
                <div>
                    <p class="font-bold">Carte en attente de coordonnées vérifiées</p>
                    <p class="mt-1 text-ardoise">Seules des positions vérifiées et sourcées seront affichées ; aucune n’est estimée ni inventée. La carte s’affichera dès que la Commune aura fourni et validé les coordonnées des localités, des équipements et des projets (C-013).</p>
                </div>
            </div>
        @endif

        <h2 class="titre-section mt-12">Liste des lieux</h2>
        <div class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($localites as $localite)
                <section class="rounded-carte border border-argile p-5" aria-labelledby="lieu-{{ $localite['slug'] }}">
                    <h3 id="lieu-{{ $localite['slug'] }}" class="text-lg"><a href="{{ route('decouvrir.localite', $localite['slug']) }}">{{ $localite['nom'] }}</a></h3>
                    @php($ici = $equipements->get($localite['slug'], collect()))
                    @php($projetsIci = $projets->filter(fn ($p) => collect($p['localites'])->contains('slug', $localite['slug'])))
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach ($ici as $equipement)
                            <li class="flex gap-2"><x-icone nom="administration" class="icone size-4 flex-none text-lagune" />{{ $equipement['nom'] }}</li>
                        @endforeach
                        @foreach ($projetsIci as $projet)
                            <li class="flex gap-2"><x-icone nom="chantier" class="icone size-4 flex-none text-terre" /><a href="{{ route('projets.show', $projet['slug']) }}">{{ $projet['titre'] }}</a></li>
                        @endforeach
                        @if ($ici->isEmpty() && $projetsIci->isEmpty())
                            <li class="text-ardoise">Aucun équipement ni projet publié.</li>
                        @endif
                    </ul>
                </section>
            @empty
                <x-portail.a-fournir quoi="La liste officielle des localités" />
            @endforelse
        </div>
    </div>
</x-portail.gabarit>

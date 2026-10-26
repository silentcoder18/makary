@php($types = ['ecole' => 'Écoles', 'centre_sante' => 'Centres de santé', 'marche' => 'Marchés', 'forage' => 'Forages', 'puits' => 'Puits', 'route' => 'Routes', 'batiment_public' => 'Bâtiments publics', 'autre' => 'Autres équipements'])
<x-portail.gabarit titre="Équipements">
    <x-portail.entete-page titre="Les équipements publics" chapeau="Écoles, centres de santé, marchés, forages et services publics, localité par localité." />

    <div class="conteneur py-12 lg:py-16">
        @forelse ($equipements->groupBy('type_equipement') as $type => $liste)
            <section class="mb-12" aria-labelledby="type-{{ $type }}">
                <h2 id="type-{{ $type }}" class="text-2xl">{{ $types[$type] ?? $type }} <span class="text-ardoise">({{ $liste->count() }})</span></h2>
                <div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="{{ $types[$type] ?? $type }}">
                    <table class="tableau">
                        <thead><tr><th scope="col">Nom</th><th scope="col">Localité</th><th scope="col">Source</th></tr></thead>
                        <tbody>
                            @foreach ($liste as $equipement)
                                <tr>
                                    <td class="font-semibold">{{ $equipement['nom'] }}</td>
                                    <td><a href="{{ route('decouvrir.localite', $equipement['localite']['slug']) }}">{{ $equipement['localite']['nom'] }}</a></td>
                                    <td class="text-ardoise">{{ $equipement->get('source.intitule') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <x-portail.a-fournir quoi="L’inventaire des équipements publics" />
        @endforelse
    </div>
</x-portail.gabarit>

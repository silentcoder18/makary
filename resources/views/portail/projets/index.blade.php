<x-portail.gabarit titre="Projets et réalisations">
    <x-portail.entete-page titre="Projets et réalisations" chapeau="Chaque projet de la commune avec son statut, son coût estimatif, son financement et ses sources. Un seul catalogue : les réalisations en sont les projets achevés." />

    <div class="conteneur py-12 lg:py-16">
        <form method="get" action="{{ route('projets') }}" class="grid gap-4 rounded-carte bg-sable p-5 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end" aria-label="Filtrer les projets">
            <div>
                <label for="filtre-statut" class="etiquette">Statut</label>
                <select id="filtre-statut" name="statut" class="champ">
                    <option value="">Tous les statuts</option>
                    @foreach ($statuts as $statut)
                        <option value="{{ str_replace('_', '-', $statut->value) }}" @selected($filtres['statut'] === $statut)>{{ $statut->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtre-secteur" class="etiquette">Secteur</label>
                <select id="filtre-secteur" name="secteur" class="champ">
                    <option value="">Tous les secteurs</option>
                    @foreach ($secteurs as $code => $libelle)
                        <option value="{{ $code }}" @selected($filtres['secteur'] === $code)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtre-localite" class="etiquette">Localité</label>
                <select id="filtre-localite" name="localite" class="champ">
                    <option value="">Toutes les localités</option>
                    @foreach ($localites as $localite)
                        <option value="{{ $localite['slug'] }}" @selected($filtres['localite'] === $localite['slug'])>{{ $localite['nom'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                <button type="submit" class="bouton bouton-principal">Filtrer</button>
                @if (array_filter($filtres))
                    <a href="{{ route('projets') }}" class="bouton bouton-secondaire">Effacer</a>
                @endif
            </div>
        </form>

        <p class="mt-8 font-semibold" role="status">{{ $projets->count() }} {{ $projets->count() > 1 ? 'projets' : 'projet' }}</p>
        @if ($projets->isEmpty())
            <p class="mt-4 text-ardoise">Aucun projet publié ne correspond à ces critères.</p>
        @else
            <ul class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($projets as $projet)
                    <li><x-portail.carte-projet :projet="$projet" titre-niveau="h2" /></li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

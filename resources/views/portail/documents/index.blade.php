<x-portail.gabarit titre="Documents">
    <x-portail.entete-page titre="Centre documentaire" chapeau="Budgets, délibérations, rapports et études de la commune, en libre téléchargement, sans inscription." />

    <div class="conteneur py-12 lg:py-16">
        <form method="get" action="{{ route('documents') }}" class="grid gap-4 rounded-carte bg-sable p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end" aria-label="Filtrer les documents">
            <div>
                <label for="filtre-categorie" class="etiquette">Catégorie</label>
                <select id="filtre-categorie" name="categorie" class="champ">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $code => $libelle)
                        <option value="{{ $code }}" @selected($filtres['categorie'] === $code)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtre-annee" class="etiquette">Année</label>
                <select id="filtre-annee" name="annee" class="champ">
                    <option value="">Toutes les années</option>
                    @foreach ($annees as $a)
                        <option value="{{ $a }}" @selected($filtres['annee'] === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bouton bouton-principal">Filtrer</button>
        </form>

        <p class="mt-8 font-semibold" role="status">{{ $documents->count() }} {{ $documents->count() > 1 ? 'documents' : 'document' }}</p>
        @if ($documents->isEmpty())
            <p class="mt-4 text-ardoise">Aucun document publié ne correspond à ces critères.</p>
        @else
            <ul class="mt-6 divide-y divide-argile rounded-carte border border-argile bg-white">
                @foreach ($documents as $document)
                    <li class="relative flex flex-col gap-3 p-5 hover:bg-sable sm:flex-row sm:items-center">
                        <span class="grid size-12 flex-none place-items-center rounded-xl bg-sable text-lac"><x-icone nom="document" /></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg"><a href="{{ route('documents.show', $document['slug']) }}" class="lien-etendu">{{ $document['titre'] }}</a></h2>
                            <p class="text-sm text-ardoise">{{ $document->get('categorie.libelle') }} · {{ $format->date($document['date_document']) }}
                                @if ($document['version']) · {{ strtoupper(pathinfo($document->get('version.fichier.nom'), PATHINFO_EXTENSION)) }}, {{ $format->nombre(round($document->get('version.fichier.taille_octets') / 1024)) }} Ko @endif</p>
                        </div>
                        <span class="lien-fleche text-sm text-lac">Consulter <x-icone nom="fleche" class="icone size-4" /></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

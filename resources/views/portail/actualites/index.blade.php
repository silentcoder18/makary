<x-portail.gabarit :titre="$annee ? 'Actualités '.$annee : 'Actualités'">
    <x-portail.entete-page :titre="$annee ? 'Actualités '.$annee : 'Actualités'" chapeau="La vie de la commune : annonces, comptes rendus et informations pratiques."
        :sous-navigation="['actualites' => 'Toutes les actualités', 'evenements' => 'Agenda']" />

    <div class="conteneur grid gap-12 py-12 lg:grid-cols-[1fr_18rem] lg:py-16">
        <div>
            @if ($actualites->isEmpty())
                <p class="text-ardoise">Aucune actualité publiée.</p>
            @else
                <ul class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($actualites as $actualite)
                        <li><x-portail.carte-contenu :contenu="$actualite" titre-niveau="h2" /></li>
                    @endforeach
                </ul>
                @if ($actualites->hasPages())
                    <nav class="mt-10 flex items-center justify-between gap-4" aria-label="Pages d’actualités">
                        @if ($actualites->onFirstPage())<span></span>@else<a class="bouton bouton-secondaire" href="{{ $actualites->previousPageUrl() }}" rel="prev">Plus récentes</a>@endif
                        <span class="text-sm text-ardoise">Page {{ $actualites->currentPage() }} sur {{ $actualites->lastPage() }}</span>
                        @if ($actualites->hasMorePages())<a class="bouton bouton-secondaire" href="{{ $actualites->nextPageUrl() }}" rel="next">Plus anciennes</a>@else<span></span>@endif
                    </nav>
                @endif
            @endif
        </div>
        <aside class="space-y-10">
            @if ($annees)
                <nav aria-labelledby="titre-archives">
                    <h2 id="titre-archives" class="text-xl">Archives</h2>
                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach ($annees as $a)
                            <li><a href="{{ route('actualites.annee', $a) }}" class="puce" @if ($annee === $a) aria-current="page" @endif>{{ $a }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
            @if ($evenements->isNotEmpty())
                <section aria-labelledby="titre-agenda-actu">
                    <h2 id="titre-agenda-actu" class="text-xl">À venir</h2>
                    <ul class="mt-4 space-y-4">
                        @foreach ($evenements as $evenement)
                            <li><x-portail.carte-evenement :evenement="$evenement" /></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</x-portail.gabarit>

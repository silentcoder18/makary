<x-portail.gabarit titre="Patrimoine et tourisme">
    <x-portail.entete-page titre="Patrimoine et tourisme" chapeau="Sites remarquables, culture et informations pratiques pour découvrir Makary." />

    <div class="conteneur py-12 lg:py-16">
        <section aria-labelledby="titre-sites">
            <h2 id="titre-sites" class="titre-section">Sites à découvrir</h2>
            @if ($sites->isEmpty())
                <x-portail.a-fournir quoi="Les fiches des sites patrimoniaux et touristiques" class="mt-6" />
            @else
                <ul class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($sites as $site)
                        <li><x-portail.carte-contenu :contenu="$site" icone="appareil" /></li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($medias->isNotEmpty())
            <section class="mt-16" aria-labelledby="titre-galerie">
                <h2 id="titre-galerie" class="titre-section">Galerie</h2>
                <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($medias as $media)
                        <li><figure>
                            <x-portail.vignette :media="$media->donnees" class="rounded-carte" />
                            <figcaption class="mt-2 text-sm text-ardoise">{{ $media['titre'] }} — {{ $media['auteur'] }}{{ $media['lieu'] ? ', '.$media['lieu'] : '' }}</figcaption>
                        </figure></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-16 grid gap-6 md:grid-cols-2" aria-label="Préparer sa visite">
            <a href="{{ route('patrimoine.culture') }}" class="carte p-6 no-underline"><x-icone nom="journal" class="icone size-8 text-terre" /><span class="mt-4 text-xl font-extrabold text-encre">Culture et histoire</span><span class="mt-1 text-ardoise">Traditions, savoir-faire et mémoire.</span></a>
            <a href="{{ route('patrimoine.pratique') }}" class="carte p-6 no-underline"><x-icone nom="boussole" class="icone size-8 text-terre" /><span class="mt-4 text-xl font-extrabold text-encre">Informations pratiques</span><span class="mt-1 text-ardoise">Venir, se loger, se déplacer.</span></a>
        </section>
    </div>
</x-portail.gabarit>

<x-portail.gabarit titre="Le Maire">
    <x-portail.entete-page titre="Le Maire et l’action municipale" chapeau="Le mot du Maire, la vision du mandat et le bilan des réalisations validées." />

    <div class="conteneur py-12 lg:py-16">
        <section class="grid items-start gap-10 lg:grid-cols-[18rem_1fr] lg:gap-16" aria-labelledby="titre-mot">
            <figure>
                <div class="aspect-[4/5] overflow-hidden rounded-t-full border-8 border-sable bg-argile">
                    @if ($maire && $maire['photo'])
                        <img src="{{ \App\Portail\Liens::fichier($maire['photo']['fichier'], $maire['photo']['titre']) }}" alt="{{ $maire['photo']['texte_alternatif'] }}" width="800" height="1000" class="size-full object-cover" decoding="async">
                    @else
                        <div class="grid size-full place-items-center text-ardoise"><x-icone nom="personne" class="icone size-20 [stroke-width:1.2]" /></div>
                    @endif
                </div>
                @if ($maire)
                    <figcaption class="mt-4"><strong class="block text-lg">{{ $maire['nom_affiche'] }}</strong>
                        <span class="text-sm text-ardoise">{{ $maire['fonction'] }} depuis le {{ $format->date($maire['mandat_debut']) }}</span></figcaption>
                @endif
            </figure>
            <div>
                <h2 id="titre-mot" class="titre-section">Le mot du Maire</h2>
                @if ($motDuMaire)
                    <div class="prose-makary mt-6">{!! $format->markdown($motDuMaire['corps']) !!}</div>
                @else
                    <x-portail.a-fournir quoi="Le mot du Maire" class="mt-6" />
                @endif
                @if ($maire && $maire['biographie'])
                    <h3 class="mt-10 text-xl">Parcours</h3>
                    <p class="mt-3 max-w-3xl text-ardoise">{{ $maire['biographie'] }}</p>
                    <p class="mt-2 text-sm text-ardoise">{{ $format->source($maire['source']) }}</p>
                @endif
            </div>
        </section>

        <section class="mt-20" aria-labelledby="titre-vision">
            <h2 id="titre-vision" class="titre-section">Vision et priorités</h2>
            @if ($vision)
                <p class="mt-4 max-w-3xl text-lg text-ardoise">{{ $vision['resume'] }}</p>
                <a href="{{ route('maire.vision') }}" class="lien-fleche mt-4">Lire la vision du mandat <x-icone nom="fleche" class="icone size-5" /></a>
            @else
                <x-portail.a-fournir quoi="La vision et les priorités du mandat" class="mt-6" />
            @endif
        </section>

        <section class="mt-20" aria-labelledby="titre-bilan">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="titre-bilan" class="titre-section">Réalisations du mandat</h2>
                <a href="{{ route('maire.bilans') }}" class="lien-fleche">Tout le bilan <x-icone nom="fleche" class="icone size-5" /></a>
            </div>
            <p class="mt-3 max-w-3xl text-ardoise">Seules figurent ici les réalisations dont l’achèvement a été validé sur pièce par le référent des réalisations.</p>
            @if ($realisations->isEmpty())
                <p class="mt-6 text-ardoise">Aucune réalisation achevée n’est encore publiée.</p>
            @else
                <ul class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($realisations as $projet)
                        <li><x-portail.carte-projet :projet="$projet" /></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-portail.gabarit>

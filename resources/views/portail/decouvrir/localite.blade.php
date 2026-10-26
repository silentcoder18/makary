<x-portail.gabarit :titre="$localite['nom']">
    <x-portail.entete-page :titre="$localite['nom']" :ariane="['Localités' => route('decouvrir.localites')]"
        :chapeau="(['village' => 'Village', 'quartier' => 'Quartier', 'espace_urbain' => 'Espace urbain'][$localite['categorie']] ?? 'Localité').' de la commune de Makary.'" :sous-navigation="[]">
        <p class="mt-4 text-sm text-ardoise">{{ $format->source($localite['source']) }}</p>
    </x-portail.entete-page>

    <div class="conteneur grid gap-12 py-12 lg:grid-cols-2 lg:py-16">
        <section aria-labelledby="titre-equipements">
            <h2 id="titre-equipements" class="titre-section">Équipements</h2>
            @if ($equipements->isEmpty())
                <p class="mt-4 text-ardoise">Aucun équipement publié pour cette localité.</p>
            @else
                <ul class="mt-6 space-y-3">
                    @foreach ($equipements as $equipement)
                        <li class="flex items-start gap-3 rounded-carte bg-sable p-4">
                            <x-icone nom="administration" class="icone mt-0.5 text-lagune" />
                            <span><strong class="block">{{ $equipement['nom'] }}</strong><span class="text-sm text-ardoise">{{ $format->source($equipement['source']) }}</span></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        <section aria-labelledby="titre-projets-localite">
            <h2 id="titre-projets-localite" class="titre-section">Projets dans cette localité</h2>
            @if ($projets->isEmpty())
                <p class="mt-4 text-ardoise">Aucun projet publié pour cette localité.</p>
            @else
                <ul class="mt-6 grid gap-6">
                    @foreach ($projets as $projet)
                        <li><x-portail.carte-projet :projet="$projet" /></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-portail.gabarit>

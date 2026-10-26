<x-portail.gabarit titre="Diaspora">
    <x-portail.entete-page titre="Espace diaspora" chapeau="Où que vous soyez, contribuez au développement de Makary : expertise, parrainage d’un projet, investissement.">
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('diaspora.interet') }}" class="bouton bouton-principal">Déclarer mon intérêt</a>
            <a href="{{ route('demande.suivi') }}" class="bouton bouton-secondaire">Suivre ma demande</a>
        </div>
    </x-portail.entete-page>

    <div class="conteneur py-12 lg:py-16">
        @if ($presentation)
            <div class="prose-makary">{!! $format->markdown($presentation['corps']) !!}</div>
        @endif

        <section class="mt-12 grid gap-6 md:grid-cols-3" aria-labelledby="titre-contribuer">
            <h2 id="titre-contribuer" class="titre-section md:col-span-3">Trois façons de contribuer</h2>
            @foreach ([
                ['personne', 'Votre expertise', 'Ingénierie, santé, éducation, gestion : mettez vos compétences au service d’un projet.'],
                ['chantier', 'Parrainer un projet', 'Choisissez un projet publié et mobilisez votre réseau autour de son financement.'],
                ['graphique', 'Investir', 'Découvrez les secteurs porteurs et les démarches pour vous implanter.'],
            ] as [$icone, $titre, $texte])
                <div class="rounded-carte bg-sable p-6"><x-icone nom="{{ $icone }}" class="icone size-8 text-terre" /><h3 class="mt-4 text-xl">{{ $titre }}</h3><p class="mt-2 text-ardoise">{{ $texte }}</p></div>
            @endforeach
        </section>

        @if ($projets->isNotEmpty())
            <section class="mt-16" aria-labelledby="titre-projets-diaspora">
                <h2 id="titre-projets-diaspora" class="titre-section">Des projets à soutenir</h2>
                <ul class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($projets as $projet)
                        <li><x-portail.carte-projet :projet="$projet" /></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="mt-12 max-w-3xl rounded-carte bg-sable p-5"><strong>Sans compte à créer.</strong> Votre demande reçoit un numéro de suivi ; avec ce numéro et votre adresse électronique, vous suivez son traitement.</p>
    </div>
</x-portail.gabarit>

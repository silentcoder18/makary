<x-portail.gabarit titre="Investir à Makary">
    <x-portail.entete-page titre="Investir à Makary" chapeau="Des secteurs porteurs, des opportunités identifiées et un interlocuteur unique pour vous accompagner.">
        <a href="{{ route('investir.interet') }}" class="bouton bouton-principal mt-8">Manifester mon intérêt <x-icone nom="fleche" class="icone size-5" /></a>
    </x-portail.entete-page>

    <div class="conteneur py-12 lg:py-16">
        <section aria-labelledby="titre-opportunites">
            <h2 id="titre-opportunites" class="titre-section">Opportunités</h2>
            @if ($opportunites->isEmpty())
                <x-portail.a-fournir quoi="Les opportunités d’investissement validées" class="mt-6" />
            @else
                <ul class="mt-6 grid gap-6 md:grid-cols-2">
                    @foreach ($opportunites as $opportunite)
                        <li id="{{ $opportunite['slug'] }}" class="carte p-6">
                            <p class="text-sm font-semibold text-lagune">{{ $secteurs[$opportunite->get('attributs.secteur')] ?? 'Tous secteurs' }}</p>
                            <h3 class="mt-2 text-xl">{{ $opportunite['titre'] }}</h3>
                            <p class="mt-2 text-ardoise">{{ $opportunite['resume'] }}</p>
                            <div class="prose-makary mt-4 text-base">{!! $format->markdown($opportunite['corps']) !!}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mt-16" aria-labelledby="titre-parcours">
            <h2 id="titre-parcours" class="titre-section">Votre parcours d’investisseur</h2>
            <ol class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['Repérer', 'Consultez les secteurs porteurs et les projets ouverts au financement.', route('investir.secteurs'), 'Voir les secteurs'],
                    ['Préparer', 'Prenez connaissance des démarches pour vous implanter.', route('investir.implanter'), 'S’implanter'],
                    ['Échanger', 'Décrivez votre projet : un interlocuteur vous recontacte, avec un numéro de suivi.', route('investir.interet'), 'Manifester mon intérêt'],
                ] as $i => [$etape, $texte, $adresse, $lien])
                    <li class="rounded-carte bg-sable p-6">
                        <span class="grid size-10 place-items-center rounded-full bg-lac text-lg font-extrabold text-white">{{ $i + 1 }}</span>
                        <h3 class="mt-4 text-xl">{{ $etape }}</h3>
                        <p class="mt-2 text-ardoise">{{ $texte }}</p>
                        <a href="{{ $adresse }}" class="lien-fleche mt-4">{{ $lien }} <x-icone nom="fleche" class="icone size-5" /></a>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</x-portail.gabarit>

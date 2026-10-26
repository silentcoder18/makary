<x-portail.gabarit titre="Partenaires et bailleurs">
    <x-portail.entete-page titre="Partenaires et bailleurs" chapeau="Des besoins de financement chiffrés, sourcés et datés ; une fiche téléchargeable par projet ; un interlocuteur et un numéro de suivi pour chaque échange.">
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('partenaires.besoins') }}" class="bouton bouton-principal">Voir les besoins de financement</a>
            <a href="{{ route('partenaires.devenir') }}" class="bouton bouton-secondaire">Devenir partenaire</a>
        </div>
    </x-portail.entete-page>

    <div class="conteneur py-12 lg:py-16">
        <section aria-labelledby="titre-besoins">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="titre-besoins" class="titre-section">Projets ouverts au financement</h2>
                <a href="{{ route('partenaires.besoins') }}" class="lien-fleche">Tous les besoins <x-icone nom="fleche" class="icone size-5" /></a>
            </div>
            @if ($besoins->isEmpty())
                <p class="mt-4 text-ardoise">Aucun projet n’est actuellement ouvert au financement.</p>
            @else
                <ul class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($besoins as $projet)
                        <li><x-portail.carte-projet :projet="$projet" /></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mt-16 grid gap-6 md:grid-cols-3" aria-label="Nos engagements">
            @foreach ([
                ['verifie', 'Des chiffres sourcés', 'Chaque montant porte sa devise, sa date et sa source. Une estimation est présentée comme telle.'],
                ['document', 'Une fiche par projet', 'La fiche de financement est générée depuis la fiche publiée, jamais saisie à la main.'],
                ['poignee', 'Un acquis réellement acquis', 'Seuls les engagements signés et validés sur pièce sont comptés comme acquis.'],
            ] as [$icone, $titre, $texte])
                <div class="rounded-carte bg-sable p-6"><x-icone nom="{{ $icone }}" class="icone size-8 text-terre" /><h2 class="mt-4 text-xl">{{ $titre }}</h2><p class="mt-2 text-ardoise">{{ $texte }}</p></div>
            @endforeach
        </section>

        <section class="mt-16" aria-labelledby="titre-partenaires">
            <h2 id="titre-partenaires" class="titre-section">Ils accompagnent la commune</h2>
            @if ($partenaires->isEmpty())
                <p class="mt-4 text-ardoise">Les partenaires sont cités ici avec leur accord écrit.</p>
            @else
                <ul class="mt-6 flex flex-wrap gap-3">
                    @foreach ($partenaires as $partenaire)
                        <li class="rounded-full bg-sable px-5 py-2.5 font-bold">{{ $partenaire['nom'] }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('partenaires.liste') }}" class="lien-fleche mt-6">Nos partenaires <x-icone nom="fleche" class="icone size-5" /></a>
            @endif
        </section>
    </div>
</x-portail.gabarit>

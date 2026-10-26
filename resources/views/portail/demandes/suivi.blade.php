@php($etapes = ['recue' => 'Reçue', 'qualifiee' => 'Qualifiée', 'affectee' => 'Confiée à un agent', 'en_traitement' => 'En traitement', 'repondue' => 'Réponse envoyée', 'cloturee' => 'Clôturée'])
<x-portail.gabarit titre="Suivre ma demande">
    <x-portail.entete-page titre="Suivre ma demande" chapeau="Saisissez le numéro de suivi reçu à l’envoi et l’adresse électronique indiquée dans votre demande." />

    <div class="conteneur grid gap-12 py-12 lg:grid-cols-2 lg:py-16">
        <form method="post" action="{{ route('demande.suivi') }}" class="max-w-xl space-y-6" novalidate>
            @csrf
            <x-portail.champ nom="numero" libelle="Numéro de suivi" aide="Par exemple : DEM-2026-AB12CD" max="20" requis :valeur="$resultat?->numero_suivi" />
            <x-portail.champ nom="courriel" libelle="Adresse électronique" type="email" autocomplete="email" max="254" requis />
            <button type="submit" class="bouton bouton-principal">Consulter</button>
        </form>

        @if ($resultat)
            <section class="rounded-carte bg-white p-8 shadow-[var(--shadow-carte)]" aria-labelledby="titre-etat" role="status">
                <h2 id="titre-etat" class="text-2xl">Demande {{ $resultat->numero_suivi }}</h2>
                <p class="mt-2 text-ardoise">Reçue le {{ $format->date($resultat->recue_le) }}</p>
                @if ($resultat->statut->value === 'rejetee')
                    <p class="mt-6 font-bold text-terre-fonce">Cette demande n’a pas pu être traitée. La mairie vous a écrit pour en préciser le motif.</p>
                @else
                    <ol class="mt-6 space-y-3">
                        @foreach ($etapes as $code => $libelle)
                            @php($atteinte = array_search($code, array_keys($etapes)) <= array_search($resultat->statut->value, array_keys($etapes)))
                            <li class="flex items-center gap-3 {{ $atteinte ? 'font-bold text-encre' : 'text-ardoise' }}">
                                <span class="grid size-7 flex-none place-items-center rounded-full {{ $atteinte ? 'bg-palmeraie text-white' : 'bg-argile' }}">
                                    @if ($atteinte)<x-icone nom="verifie" class="icone size-4" />@endif
                                </span>
                                {{ $libelle }} @if ($resultat->statut->value === $code)<span class="sr-only">(étape actuelle)</span>@endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endif
    </div>
</x-portail.gabarit>

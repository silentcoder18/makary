<x-portail.gabarit :titre="$titre">
    <x-portail.entete-page :titre="$titre" :chapeau="$chapeau" />

    <div class="conteneur py-12 lg:py-16">
        @if ($elus->isEmpty())
            <x-portail.a-fournir :quoi="$titre" detail="noms, fonctions et actes de désignation" />
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($elus as $elu)
                    <li class="carte items-center p-6 text-center">
                        <div class="size-32 overflow-hidden rounded-full border-4 border-sable bg-argile">
                            @if ($elu['photo'])
                                <img src="{{ \App\Portail\Liens::fichier($elu['photo']['fichier'], $elu['photo']['titre']) }}" alt="{{ $elu['photo']['texte_alternatif'] }}" width="800" height="1000" class="size-full object-cover" loading="lazy" decoding="async">
                            @else
                                <div class="grid size-full place-items-center text-ardoise"><x-icone nom="personne" class="icone size-12 [stroke-width:1.4]" /></div>
                            @endif
                        </div>
                        <h2 class="mt-4 text-lg">{{ $elu['nom_affiche'] }}</h2>
                        <p class="font-semibold text-lagune">{{ $elu['fonction'] }}</p>
                        <p class="mt-1 text-sm text-ardoise">
                            @if ($elu['mandat_fin'])
                                Du {{ $format->date($elu['mandat_debut']) }} au {{ $format->date($elu['mandat_fin']) }}
                            @else
                                Depuis le {{ $format->date($elu['mandat_debut']) }}
                            @endif
                        </p>
                        <p class="mt-3 text-xs text-ardoise">{{ $format->source($elu['source']) }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

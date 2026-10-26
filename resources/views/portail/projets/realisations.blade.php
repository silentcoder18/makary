<x-portail.gabarit titre="Réalisations">
    <x-portail.entete-page titre="Réalisations" chapeau="Les projets achevés, dont la réception a été validée sur pièce par le référent des réalisations." />

    <div class="conteneur py-12 lg:py-16">
        @if ($realisations->isEmpty())
            <p class="text-ardoise">Aucune réalisation achevée n’est encore publiée.</p>
        @else
            <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($realisations as $projet)
                    <li><x-portail.carte-projet :projet="$projet" titre-niveau="h2" /></li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

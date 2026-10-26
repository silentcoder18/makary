<x-portail.gabarit titre="Bilans">
    <x-portail.entete-page titre="Le bilan des réalisations" chapeau="Les réalisations achevées et validées sur pièce : procès-verbal de réception, coût final et photographies datées." />

    <div class="conteneur py-12 lg:py-16">
        @if ($realisations->isEmpty())
            <p class="text-ardoise">Aucune réalisation achevée n’est encore publiée. Une réalisation n’apparaît ici qu’après la validation de son achèvement par le référent des réalisations.</p>
        @else
            <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($realisations as $projet)
                    <li><x-portail.carte-projet :projet="$projet" titre-niveau="h2" /></li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

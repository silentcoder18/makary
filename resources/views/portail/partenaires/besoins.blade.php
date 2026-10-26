<x-portail.gabarit titre="Besoins de financement">
    <x-portail.entete-page titre="Besoins de financement" chapeau="Les projets prêts à être soumis ou en recherche de financement, avec leur coût, l’acquis et le besoin restant." />

    <div class="conteneur py-12 lg:py-16">
        @if ($projets->isEmpty())
            <p class="text-ardoise">Aucun projet n’est actuellement ouvert au financement.</p>
        @else
            <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($projets as $projet)
                    <li><x-portail.carte-projet :projet="$projet" titre-niveau="h2" /></li>
                @endforeach
            </ul>
        @endif
        <p class="mt-10 max-w-3xl rounded-carte bg-sable p-5">Un projet vous intéresse ? <a href="{{ route('partenaires.devenir') }}">Proposez votre concours</a> ; un gestionnaire des partenaires vous répondra.</p>
    </div>
</x-portail.gabarit>

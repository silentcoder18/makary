<x-portail.gabarit titre="Agenda">
    <x-portail.entete-page titre="Agenda" chapeau="Réunions publiques, manifestations, campagnes : ce qui se passe à Makary."
        :sous-navigation="['actualites' => 'Toutes les actualités', 'evenements' => 'Agenda']" />

    <div class="conteneur py-12 lg:py-16">
        <nav aria-label="Période">
            <ul class="flex flex-wrap gap-2">
                @foreach ($periodes as $code => $libelle)
                    <li><a href="{{ route('evenements', ['periode' => $code]) }}" class="puce" @if ($periode === $code) aria-current="true" @endif>{{ $libelle }}</a></li>
                @endforeach
            </ul>
        </nav>
        <h2 class="sr-only">{{ $periodes[$periode] }}</h2>
        @if ($evenements->isEmpty())
            <p class="mt-8 text-ardoise">Aucun événement pour cette période.</p>
        @else
            <ul class="mt-8 grid gap-4 md:grid-cols-2">
                @foreach ($evenements as $evenement)
                    <li><x-portail.carte-evenement :evenement="$evenement" /></li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

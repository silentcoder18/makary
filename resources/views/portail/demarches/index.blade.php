<x-portail.gabarit titre="Démarches">
    <x-portail.entete-page titre="Vos démarches" chapeau="Actes d’état civil, certificats, urbanisme : les pièces à fournir, les délais et le service compétent." />

    <div class="conteneur py-12 lg:py-16">
        @if ($demarches->isEmpty())
            <x-portail.a-fournir quoi="Les fiches des démarches administratives" />
        @else
            <ul class="grid gap-4 md:grid-cols-2">
                @foreach ($demarches as $demarche)
                    <li class="carte flex-row items-start gap-4 p-6">
                        <span class="grid size-12 flex-none place-items-center rounded-xl bg-sable text-terre"><x-icone nom="acte" /></span>
                        <div class="min-w-0">
                            <h2 class="text-xl"><a href="{{ route('demarches.show', $demarche['slug']) }}" class="lien-etendu">{{ $demarche['titre'] }}</a></h2>
                            <p class="mt-1 text-ardoise">{{ $demarche['resume'] }}</p>
                            <span class="lien-fleche mt-3 text-sm text-lac">Préparer ma démarche <x-icone nom="fleche" class="icone size-4" /></span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="mt-10 max-w-3xl rounded-carte bg-sable p-5">Votre démarche n’est pas dans la liste ? <a href="{{ route('contact') }}">Écrivez à la mairie</a> : votre demande recevra un numéro de suivi.</p>
    </div>
</x-portail.gabarit>

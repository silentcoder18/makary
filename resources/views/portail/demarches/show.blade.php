<x-portail.gabarit :titre="$demarche['titre']" :description="$demarche['resume']">
    <x-portail.entete-page :titre="$demarche['titre']" :chapeau="$demarche['resume']" surtitre="Démarches" />

    <div class="conteneur grid gap-12 py-12 lg:grid-cols-[1fr_22rem] lg:py-16">
        <div>
            <h2 class="text-2xl">Pièces à fournir</h2>
            @if ($demarche->get('attributs.pieces_a_fournir'))
                <ul class="mt-4 space-y-2">
                    @foreach ($demarche->get('attributs.pieces_a_fournir') as $piece)
                        <li class="flex items-start gap-3"><x-icone nom="verifie" class="icone mt-0.5 text-palmeraie" />{{ $piece }}</li>
                    @endforeach
                </ul>
            @else
                <x-portail.a-fournir quoi="La liste des pièces à fournir" class="mt-4" />
            @endif
            @if ($demarche['corps'])
                <div class="prose-makary mt-10">{!! $format->markdown($demarche['corps']) !!}</div>
            @endif
        </div>
        <aside class="h-fit rounded-carte bg-sable p-6" aria-labelledby="titre-pratique">
            <h2 id="titre-pratique" class="text-xl">En pratique</h2>
            <dl class="mt-4 space-y-4">
                <div><dt class="text-sm font-bold text-ardoise">Service</dt><dd>{{ $demarche->get('attributs.service') ?? $format->aFournir('service compétent') }}</dd></div>
                <div><dt class="text-sm font-bold text-ardoise">Délai</dt><dd>{{ $demarche->get('attributs.delai') ?? $format->aFournir('délai') }}</dd></div>
                <div><dt class="text-sm font-bold text-ardoise">Coût</dt><dd>{{ $demarche->get('attributs.cout') ?? $format->aFournir('coût') }}</dd></div>
            </dl>
            <a href="{{ route('contact') }}" class="bouton bouton-principal mt-6 w-full">Poser une question</a>
        </aside>
    </div>
</x-portail.gabarit>

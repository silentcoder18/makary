<x-portail.gabarit :titre="$document['titre']" :description="$document['resume'] ?? $document['titre']">
    <x-portail.entete-page :titre="$document['titre']" :chapeau="$document['resume']" :surtitre="$document->get('categorie.libelle')" :sous-navigation="[]" />

    <div class="conteneur grid gap-12 py-12 lg:grid-cols-[1fr_22rem] lg:py-16">
        <dl class="grid max-w-2xl gap-4 sm:grid-cols-2">
            <div class="rounded-carte bg-sable p-5"><dt class="text-sm font-bold text-ardoise">Date du document</dt><dd class="mt-1 text-lg font-semibold">{{ $format->date($document['date_document']) }}</dd></div>
            <div class="rounded-carte bg-sable p-5"><dt class="text-sm font-bold text-ardoise">Catégorie</dt><dd class="mt-1 text-lg font-semibold">
                <a href="{{ route('documents.show', str_replace('_', '-', $document->get('categorie.code'))) }}">{{ $document->get('categorie.libelle') }}</a></dd></div>
            @if ($document['reference_officielle'])
                <div class="rounded-carte bg-sable p-5"><dt class="text-sm font-bold text-ardoise">Référence officielle</dt><dd class="mt-1 text-lg font-semibold">{{ $document['reference_officielle'] }}</dd></div>
            @endif
            <div class="rounded-carte bg-sable p-5"><dt class="text-sm font-bold text-ardoise">Version</dt><dd class="mt-1 text-lg font-semibold">{{ $document->get('version.numero') ?? '—' }}, publiée le {{ $format->date($document->publieLe) }}</dd></div>
        </dl>

        @if ($document['version'])
            <aside class="h-fit rounded-carte bg-white p-6 shadow-[var(--shadow-carte)]" aria-labelledby="titre-telechargement">
                <h2 id="titre-telechargement" class="text-xl">Télécharger</h2>
                <p class="mt-2 text-sm text-ardoise">{{ $document->get('version.fichier.nom') }} · {{ $format->nombre(round($document->get('version.fichier.taille_octets') / 1024)) }} Ko</p>
                <a href="{{ route('documents.telecharger', $document['slug']) }}" class="bouton bouton-principal mt-5 w-full"><x-icone nom="telechargement" class="icone size-5" /> Télécharger le document</a>
                <p class="mt-4 break-all text-xs text-ardoise">Empreinte SHA-256 : {{ $document->get('version.fichier.empreinte_sha256') }}</p>
            </aside>
        @endif
    </div>
</x-portail.gabarit>

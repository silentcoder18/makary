<footer class="sur-sombre mt-20 bg-nuit text-sable">
    <div class="frise" aria-hidden="true"></div>
    <div class="conteneur grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <span class="mb-4 inline-block rounded-xl bg-white p-1.5"><x-logo :hauteur="64" class="h-16 w-auto" /></span>
            <h2 class="text-lg text-white">Mairie de Makary</h2>
            <dl class="mt-4 space-y-3 text-sm">
                {{-- Coordonnées officielles saisies dans le back-office (« Coordonnées »), sinon la mention « à fournir » --}}
                <div class="flex gap-3"><dt><x-icone nom="lieu" class="icone size-5 text-soleil" /><span class="sr-only">Adresse</span></dt>
                    <dd>{{ $coordonneesPied['adresse'] ?? $format->aFournir('adresse officielle (C-003)') }}@if (! empty($coordonneesPied['boite_postale']))<br>{{ $coordonneesPied['boite_postale'] }}@endif</dd></div>
                <div class="flex gap-3"><dt><x-icone nom="telephone" class="icone size-5 text-soleil" /><span class="sr-only">Téléphone</span></dt>
                    <dd>@if (! empty($coordonneesPied['telephone']))<a class="text-sable hover:text-white" href="tel:{{ preg_replace('/[^0-9+]/', '', $coordonneesPied['telephone']) }}">{{ $coordonneesPied['telephone'] }}</a>@else{{ $format->aFournir('téléphone officiel') }}@endif</dd></div>
                @if (! empty($coordonneesPied['courriel']))
                    <div class="flex gap-3"><dt><x-icone nom="enveloppe" class="icone size-5 text-soleil" /><span class="sr-only">Courriel</span></dt>
                        <dd><a class="text-sable hover:text-white" href="mailto:{{ $coordonneesPied['courriel'] }}">{{ $coordonneesPied['courriel'] }}</a></dd></div>
                @endif
                <div class="flex gap-3"><dt><x-icone nom="horloge" class="icone size-5 text-soleil" /><span class="sr-only">Horaires</span></dt><dd>{{ $coordonneesPied['horaires'] ?? $format->aFournir('horaires d’ouverture') }}</dd></div>
            </dl>
            <a href="{{ route('contact') }}" class="bouton bouton-clair mt-6">Écrire à la mairie</a>
        </div>
        <div>
            <h2 class="text-lg text-white">Accès rapides</h2>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a class="text-sable hover:text-white" href="{{ route('demarches') }}">Démarches administratives</a></li>
                <li><a class="text-sable hover:text-white" href="{{ route('documents') }}">Documents officiels</a></li>
                <li><a class="text-sable hover:text-white" href="{{ route('evenements') }}">Agenda</a></li>
                <li><a class="text-sable hover:text-white" href="{{ route('demande.suivi') }}">Suivre ma demande</a></li>
                <li><a class="text-sable hover:text-white" href="{{ route('projets.realisations') }}">Réalisations</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-lg text-white">La commune</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach (\App\Portail\Navigation::PRINCIPALE as $lien)
                    <li><a class="text-sable hover:text-white" href="{{ route($lien['route']) }}">{{ $lien['libelle'] }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="text-lg text-white">Nos localités</h2>
            @if ($localitesPied->isEmpty())
                <p class="mt-4 text-sm">{{ $format->aFournir('liste officielle des localités (C-013)') }}</p>
            @else
                <ul class="mt-4 flex flex-wrap gap-2 text-sm">
                    @foreach ($localitesPied as $localite)
                        <li><a class="inline-block rounded-full bg-nuit-clair px-3 py-1 text-sable no-underline hover:text-white hover:underline" href="{{ route('decouvrir.localite', $localite['slug']) }}">{{ $localite['nom'] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <div class="border-t border-nuit-clair">
        <div class="conteneur flex flex-col gap-4 py-6 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ now()->year }} Commune de Makary @if (config('makary.donnees_fictives')) · <strong class="text-soleil">données fictives de démonstration</strong> @endif</p>
            <ul class="flex flex-wrap gap-x-5 gap-y-1">
                @foreach (\App\Portail\Navigation::INFORMATIONS as $route => $libelle)
                    <li><a class="text-sable hover:text-white" href="{{ route($route) }}">{{ $libelle }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</footer>

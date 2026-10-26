@php($types = ['bailleur_bilateral' => 'Bailleur bilatéral', 'bailleur_multilateral' => 'Bailleur multilatéral', 'programme_national' => 'Programme national', 'ong' => 'ONG',
    'fondation' => 'Fondation', 'entreprise' => 'Entreprise', 'collectivite' => 'Collectivité', 'association_diaspora' => 'Association de la diaspora', 'autre' => 'Partenaire'])
<x-portail.gabarit titre="Nos partenaires">
    <x-portail.entete-page titre="Nos partenaires" chapeau="Les partenaires de la commune, cités avec leur accord écrit." />

    <div class="conteneur py-12 lg:py-16">
        @if ($partenaires->isEmpty())
            <p class="text-ardoise">Aucun partenaire n’est encore cité.</p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($partenaires as $partenaire)
                    <li class="carte items-start p-6">
                        @if ($partenaire['logo'])
                            <img src="{{ \App\Portail\Liens::fichier($partenaire['logo']['fichier'], $partenaire['logo']['titre']) }}" alt="{{ $partenaire['logo']['texte_alternatif'] }}" width="120" height="80" class="h-16 w-auto" loading="lazy" decoding="async">
                        @else
                            <x-icone nom="poignee" class="icone size-10 text-terre" />
                        @endif
                        <h2 class="mt-4 text-xl">{{ $partenaire['nom'] }}</h2>
                        <p class="text-sm font-semibold text-lagune">{{ $types[$partenaire['type_partenaire']] ?? 'Partenaire' }}</p>
                        {{-- Seules les adresses web sont affichées comme liens (jamais « javascript: » ni autre schéma) --}}
                        @if ($partenaire['site_web'] && preg_match('#^https?://#i', $partenaire['site_web']))
                            <a href="{{ $partenaire['site_web'] }}" class="mt-3 text-sm" rel="noopener external">Site du partenaire</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-portail.gabarit>

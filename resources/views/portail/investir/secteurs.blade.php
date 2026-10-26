@php($icones = ['eau' => 'eau', 'education' => 'education', 'sante' => 'sante', 'voirie' => 'voirie', 'hygiene' => 'feuille', 'agriculture' => 'agriculture',
    'elevage_peche' => 'peche', 'commerce' => 'commerce', 'culture_tourisme' => 'appareil', 'environnement' => 'feuille', 'administration' => 'administration'])
<x-portail.gabarit titre="Secteurs">
    <x-portail.entete-page titre="Les secteurs" chapeau="Les domaines d’action de la commune, avec leurs projets et leurs opportunités." />

    <div class="conteneur py-12 lg:py-16">
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($secteurs as $secteur)
                <li class="carte flex-row items-center gap-4 p-5">
                    <span class="grid size-12 flex-none place-items-center rounded-xl bg-sable text-terre"><x-icone nom="{{ $icones[$secteur['code']] ?? 'chantier' }}" /></span>
                    <span><a href="{{ route('investir.secteur', str_replace('_', '-', $secteur['code'])) }}" class="lien-etendu text-lg font-extrabold">{{ $secteur['libelle'] }}</a>
                        <span class="block text-sm text-ardoise">{{ $secteur['projets'] }} {{ $secteur['projets'] > 1 ? 'projets publiés' : 'projet publié' }}</span></span>
                </li>
            @endforeach
        </ul>
    </div>
</x-portail.gabarit>

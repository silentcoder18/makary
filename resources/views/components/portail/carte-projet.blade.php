@props(['projet', 'titreNiveau' => 'h3'])
@php
    $icones = ['eau' => 'eau', 'education' => 'education', 'sante' => 'sante', 'voirie' => 'voirie', 'hygiene' => 'feuille', 'agriculture' => 'agriculture',
        'elevage_peche' => 'peche', 'commerce' => 'commerce', 'culture_tourisme' => 'appareil', 'environnement' => 'feuille', 'administration' => 'administration'];
    $financement = $projet['financement'];
    $part = $format->part($financement['acquis'], data_get($projet, 'cout.montant'));
    $ouvert = in_array($projet->get('statut.code'), ['financement_recherche', 'pret_a_etre_soumis'], true);
@endphp
<article class="carte h-full">
    <x-portail.vignette :media="$projet['medias'][0] ?? null" :icone="$icones[$projet->get('secteur.code')] ?? 'chantier'">
        <x-portail.badge-statut :statut="$projet['statut']" class="absolute left-4 top-4 z-[2] shadow" />
    </x-portail.vignette>
    <div class="flex flex-1 flex-col p-6">
        <p class="text-sm font-semibold text-lagune">{{ $projet->get('secteur.libelle') }} · {{ collect($projet['localites'])->pluck('nom')->implode(', ') }}</p>
        <{{ $titreNiveau }} class="mt-2 text-xl">
            <a href="{{ route('projets.show', $projet['slug']) }}" class="lien-etendu">{{ $projet['titre'] }}</a>
        </{{ $titreNiveau }}>
        @if ($projet['resume'])
            <p class="mt-3 line-clamp-3 text-ardoise">{{ $projet['resume'] }}</p>
        @endif

        @if ($ouvert && $part !== null)
            <div class="mt-5">
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="font-bold text-palmeraie">{{ $format->montant($financement['acquis'], $financement['devise']) }} acquis</span>
                    <span class="text-ardoise">sur {{ $format->montant(data_get($projet, 'cout.montant'), data_get($projet, 'cout.devise')) }}</span>
                </div>
                <progress class="financement mt-2" max="100" value="{{ $part }}">{{ $part }} %</progress>
                <p class="mt-1.5 text-xs text-ardoise">Recherché : <strong class="text-encre">{{ $format->montant($financement['recherche'], $financement['devise']) }}</strong>
                    · {{ $format->nature(data_get($projet, 'cout.nature')) }} au {{ $format->date(data_get($projet, 'cout.date_estimation')) }}</p>
            </div>
        @endif

        <p class="mt-auto pt-5 text-sm text-ardoise">Statut mis à jour le {{ $format->date($projet->get('statut.mis_a_jour_le')) }}</p>
    </div>
</article>

@props(['projet'])
@php
    $financement = $projet['financement'];
    $cout = $projet['cout'];
    $part = $format->part($financement['acquis'], $cout['montant'] ?? null);
@endphp
{{-- Bloc financier à trois cases (charte P1) : coût, acquis, recherché. Le sollicité n'est jamais affiché ni déduit. --}}
<section {{ $attributes->merge(['class' => 'rounded-carte bg-white p-6 shadow-[var(--shadow-carte)]']) }} aria-labelledby="titre-financement">
    <h2 id="titre-financement" class="text-xl">Financement</h2>
    <dl class="mt-5 grid gap-4">
        <div class="rounded-xl bg-sable p-4">
            <dt class="text-sm font-bold text-ardoise">Coût estimatif</dt>
            <dd class="mt-1 text-2xl font-extrabold">{{ $cout ? $format->montant($cout['montant'], $cout['devise']) : 'Non encore estimé' }}</dd>
            @if ($cout)
                <dd class="mt-1 text-sm text-ardoise">{{ ucfirst($format->natureCout($cout['nature_cout'])) }} · {{ $format->nature($cout['nature']) }} au {{ $format->date($cout['date_estimation']) }}</dd>
                <dd class="text-sm text-ardoise">{{ $format->source($cout['source']) }}</dd>
            @endif
        </div>
        <div class="rounded-xl bg-sable p-4">
            <dt class="text-sm font-bold text-ardoise">Financement acquis</dt>
            <dd class="mt-1 text-2xl font-extrabold text-palmeraie">{{ $format->montant($financement['acquis'], $financement['devise']) }}</dd>
            <dd class="text-sm text-ardoise">Engagements signés, validés sur pièce</dd>
        </div>
        <div class="rounded-xl bg-sable p-4">
            <dt class="text-sm font-bold text-ardoise">Financement recherché</dt>
            <dd class="mt-1 text-2xl font-extrabold text-terre">{{ $financement['recherche'] === null ? '—' : $format->montant($financement['recherche'], $financement['devise']) }}</dd>
            <dd class="text-sm text-ardoise">Besoin restant, calculé le {{ $format->date($financement['calcule_le']) }}</dd>
        </div>
    </dl>
    @if ($part !== null)
        <label for="progression-financement" class="mt-5 block text-sm font-bold">Part du coût déjà financée : {{ $part }} %</label>
        <progress id="progression-financement" class="financement mt-2" max="100" value="{{ $part }}">{{ $part }} %</progress>
    @endif

    @if ($financement['lignes_acquises'])
        <h3 class="mt-6 text-base">Financements acquis</h3>
        <ul class="mt-2 space-y-2 text-sm">
            @foreach ($financement['lignes_acquises'] as $ligne)
                <li class="flex justify-between gap-3 border-b border-argile pb-2">
                    <span>{{ $ligne['financeur'] ?? 'Financeur non cité à sa demande' }}<span class="block text-ardoise">le {{ $format->date($ligne['date']) }}</span></span>
                    <strong class="whitespace-nowrap">{{ $format->montant($ligne['montant'], $ligne['devise']) }}</strong>
                </li>
            @endforeach
        </ul>
    @endif
    <p class="mt-5 text-xs text-ardoise">Les montants indiqués sont des estimations à la date mentionnée. Ils ne valent pas engagement de la Commune.</p>
</section>

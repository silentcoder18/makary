{{-- Rubriques du portail (MKY-P3-08) : ce qui alimente chacune des 12 rubriques, et son état --}}
@php
    $libellesEtats = [
        'brouillon' => ['brouillon', 'brouillons'],
        'en_revue' => ['en revue', 'en revue'],
        'approuve' => ['approuvé', 'approuvés'],
        'publie' => ['publié', 'publiés'],
        'retire' => ['retiré', 'retirés'],
    ];
@endphp
<x-admin.gabarit titre="Rubriques du portail citoyen" chapeau="Vue d’ensemble des 12 rubriques du portail officiel de Makary : suivez l’état de rédaction des pages et la saisie des fiches correspondantes.">
    <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
        @foreach ($rubriques as $i => $rubrique)
            <section class="panneau panneau-hover flex flex-col justify-between space-y-5 border border-slate-200/80" aria-labelledby="rubrique-{{ $i }}">
                <div>
                    {{-- En-tête de carte rubrique --}}
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-black text-slate-700">
                                {{ $i + 1 }}
                            </span>
                            <h2 id="rubrique-{{ $i }}" class="text-base font-bold text-slate-900 tracking-tight">
                                {{ $rubrique['titre'] }}
                            </h2>
                        </div>
                        <a href="{{ $rubrique['portail'] }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-bold text-lac hover:text-lac/80 shrink-0">
                            <span>Portail</span>
                            <span class="text-xs">↗</span>
                        </a>
                    </div>

                    {{-- Pages associées --}}
                    @if ($rubrique['pages'])
                        <div class="mt-4">
                            <h3 class="text-[0.7rem] font-bold uppercase tracking-wider text-slate-400">Pages éditoriales</h3>
                            <ul class="mt-2 space-y-2">
                                @foreach ($rubrique['pages'] as $page)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50/70 p-2.5 text-xs border border-slate-100">
                                        <span class="font-medium text-slate-800">{{ $page['titre'] }}</span>
                                        <div class="flex items-center gap-2">
                                            @if ($page['etat'])
                                                <x-admin.etat :etat="$page['etat']" />
                                                @if ($page['lien'])
                                                    <a href="{{ $page['lien'] }}" class="font-bold text-lac hover:underline">Modifier</a>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[0.68rem] font-bold bg-slate-200 text-slate-700">À rédiger</span>
                                                @if ($page['lien'])
                                                    <a href="{{ $page['lien'] }}" class="font-bold text-terre hover:underline">+ Rédiger</a>
                                                @endif
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Fiches & Éléments --}}
                    @if ($rubrique['elements'])
                        <div class="mt-4">
                            <h3 class="text-[0.7rem] font-bold uppercase tracking-wider text-slate-400">Fiches & Contenus associés</h3>
                            <ul class="mt-2 space-y-2.5">
                                @foreach ($rubrique['elements'] as $element)
                                    <li class="rounded-lg border border-slate-100 bg-white p-3 shadow-2xs text-xs">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <a href="{{ $element['lien'] }}" class="font-bold text-slate-900 hover:text-lac">
                                                {{ $element['libelle'] }}
                                            </a>
                                            @if (! empty($element['nouveau']))
                                                <a href="{{ $element['nouveau'] }}" class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[0.7rem] font-bold text-emerald-700 border border-emerald-200 hover:bg-emerald-100">
                                                    + Ajouter
                                                </a>
                                            @endif
                                        </div>
                                        <p class="text-slate-500 mt-1">
                                            @if (isset($element['etats']))
                                                @if ($element['etats'])
                                                    <span class="inline-flex items-center gap-1">
                                                        {{ collect($element['etats'])->map(fn ($n, $etat) => $n.' '.($libellesEtats[$etat][$n > 1 ? 1 : 0] ?? $etat))->implode(' · ') }}
                                                    </span>
                                                @else
                                                    <span class="italic text-slate-400">Aucune fiche enregistrée</span>
                                                @endif
                                            @else
                                                {{ $element['note'] }}
                                            @endif
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! $rubrique['pages'] && ! $rubrique['elements'])
                        <p class="mt-4 text-xs text-slate-400 italic">Aucune page ou fiche spécifique requise pour cette rubrique.</p>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</x-admin.gabarit>

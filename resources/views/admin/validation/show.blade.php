@php
    $instantane = $validation->instantane;
    $titre = $instantane['titre'] ?? $instantane['nom'] ?? $instantane['nom_affiche'] ?? 'Élément '.$validation->objet_id;
    $soi = $validation->soumis_par === auth()->id();
    // Aperçu lisible de l'instantané : valeurs simples en clair, éléments composés résumés.
    $apercu = fn ($valeur) => match (true) {
        $valeur === null || $valeur === '' || $valeur === [] => '—',
        is_bool($valeur) => $valeur ? 'oui' : 'non',
        is_array($valeur) => array_is_list($valeur) ? count($valeur).' élément(s)' : collect($valeur)->filter(fn ($v) => is_scalar($v))->map(fn ($v, $k) => "$k : $v")->implode(' · '),
        default => \Illuminate\Support\Str::limit((string) $valeur, 400),
    };
@endphp
<x-admin.gabarit :titre="'Examen : '.$titre" chapeau="Examen contradictoire de la version soumise. Validation souveraine par le Super Administrateur ou le Secrétariat Général.">
    <p class="-mt-4 mb-6 text-sm">
        <a href="{{ route('admin.validation.index') }}" class="text-lac font-semibold hover:underline">← Retour à la file de validation</a>
    </p>

    <div class="grid gap-8 xl:grid-cols-[1fr_22rem]">
        {{-- Détails de la version soumise --}}
        <section class="panneau space-y-4" aria-labelledby="titre-instantane">
            <div class="border-b border-slate-100 pb-3">
                <h2 id="titre-instantane" class="text-lg font-bold text-slate-900 tracking-tight">Version soumise pour examen</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Soumise par <strong class="text-slate-800">{{ $validation->soumisPar?->nom_affiche ?? 'Administrateur' }}</strong> le {{ $format->date($validation->soumis_le, 'j F Y à H\hi') }}.
                    @if ($publiee)
                        · Comparée à la version {{ $publiee->version }} actuellement en ligne.
                    @else
                        · Première soumission (aucune version antérieure).
                    @endif
                </p>
            </div>

            <dl class="divide-y divide-slate-100 text-xs">
                @foreach ($instantane as $cle => $valeur)
                    <div class="grid gap-1 py-3 sm:grid-cols-[12rem_1fr] items-baseline">
                        <dt class="font-bold text-slate-700">
                            {{ str_replace('_', ' ', ucfirst($cle)) }}
                            @if ($modifies !== null && in_array($cle, $modifies, true))
                                <span class="badge ml-1" data-etat="en_revue">modifié</span>
                            @endif
                        </dt>
                        <dd class="whitespace-pre-line break-words text-slate-800 font-medium">{{ $apercu($valeur) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Panneau latéral de décision --}}
        <aside class="space-y-4">
            <div class="panneau space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-base font-bold text-slate-900 tracking-tight">Décision éditoriale</h2>
                    <div class="mt-2 flex items-center justify-between">
                        <x-admin.etat :etat="match ($validation->decision->value) { 'approuve' => 'approuve', 'renvoye' => 'brouillon', default => 'en_revue' }" />
                        @if ($validation->decidePar)
                            <span class="text-xs text-slate-500">par {{ $validation->decidePar->nom_affiche }}</span>
                        @endif
                    </div>
                </div>

                @if ($validation->motif)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                        <strong>Motif de la décision :</strong> {{ $validation->motif }}
                    </div>
                @endif

                @if ($empechement)
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 flex items-start gap-2">
                        <x-icone nom="alerte" class="icone size-4 flex-none text-rose-600 mt-0.5" />
                        <div>
                            <p class="font-bold">Point bloquant pour la publication :</p>
                            <p class="mt-0.5">{{ $empechement }}</p>
                        </div>
                    </div>
                @endif

                @if ($validation->decision->value === 'en_attente')
                    @php($peutPublier = auth()->user()->can('contenu.publier', $objet))
                    
                    @if ($peutPublier && ! $empechement)
                        <form method="post" action="{{ route('admin.validation.approuver-publier', $validation) }}">
                            @csrf
                            <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 w-full font-bold shadow-sm flex items-center justify-center gap-2 py-2.5 text-xs">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Approuver et publier en direct
                            </button>
                        </form>
                    @endif

                    <form method="post" action="{{ route('admin.validation.approuver', $validation) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="bouton bouton-secondaire w-full text-xs py-2 font-bold">
                            Approuver sans publier immédiatement
                        </button>
                    </form>

                    <form method="post" action="{{ route('admin.validation.renvoyer', $validation) }}" class="mt-4 space-y-3 border-t border-slate-100 pt-3">
                        @csrf
                        <x-portail.champ nom="motif" libelle="Motif du renvoi à l’auteur" type="textarea" max="2000" requis aide="Explication transmise pour correction avant nouvelle soumission." />
                        <button type="submit" class="bouton bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 w-full text-xs py-2 font-bold">
                            Renvoyer pour modifications
                        </button>
                    </form>
                @elseif ($validation->decision->value === 'approuve' && ! $validation->publication)
                    @can('contenu.publier', $objet)
                        <form method="post" action="{{ route('admin.validation.publier', $validation) }}" class="space-y-3">
                            @csrf
                            <x-portail.champ nom="publier_le" libelle="Date de publication programmée" type="datetime-local" aide="Laisser vide pour mettre en ligne immédiatement." />
                            <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 w-full font-bold text-xs py-2.5 shadow-sm">
                                Mettre en ligne
                            </button>
                        </form>
                    @endcan
                @elseif ($validation->publication)
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-xs text-emerald-900">
                        <p class="font-bold flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Version {{ $validation->publication->version }} publiée
                        </p>
                        <p class="text-[0.7rem] text-emerald-700 mt-1">Mise en ligne le {{ $format->date($validation->publication->publie_le, 'j F Y à H\hi') }}.</p>
                    </div>
                @endif
            </div>

            @if ($publiee)
                @can('contenu.retirer', $objet)
                    <form method="post" action="{{ route('admin.validation.retirer', $validation) }}" class="panneau space-y-3">
                        @csrf
                        <input type="hidden" name="_formulaire" value="retrait">
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Retrait du portail</h2>
                        <x-portail.champ nom="motif" libelle="Motif du retrait" type="textarea" max="2000" requis formulaire="retrait" aide="Justification archivée dans le journal d’audit." />
                        <button type="submit" class="bouton bg-amber-500 text-white hover:bg-amber-600 w-full text-xs font-bold py-2 shadow-2xs">
                            Retirer de la ligne publique
                        </button>
                    </form>
                @endcan
            @endif
        </aside>
    </div>
</x-admin.gabarit>

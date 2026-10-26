@props(['objet', 'section', 'validation' => null, 'publication' => null, 'lienPublic' => null])
{{-- Circuit éditorial d'un objet publiable (MKY-P3-08) : état, soumission à la revue, publication directe, retrait du portail --}}
<section class="panneau space-y-4" aria-labelledby="titre-circuit">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <h2 id="titre-circuit" class="text-base font-bold text-slate-900 tracking-tight">Cycle de publication</h2>
        @if (isset($objet->etat))
            <x-admin.etat :etat="$objet->etat" />
        @else
            <span class="badge" data-etat="{{ $publication ? 'publie' : 'brouillon' }}">{{ $publication ? 'Publié' : 'Non publié' }}</span>
        @endif
    </div>

    @if ($publication)
        <div class="rounded-xl bg-emerald-50/70 border border-emerald-200 p-3 text-xs text-emerald-900">
            <div class="flex items-center justify-between gap-2">
                <span class="font-bold flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Version {{ $publication->version }} en ligne
                </span>
                @if ($lienPublic)
                    <a href="{{ $lienPublic }}" target="_blank" class="font-bold text-lac hover:underline inline-flex items-center gap-1">
                        <span>Voir</span>
                        <span class="text-xs">↗</span>
                    </a>
                @endif
            </div>
            <p class="text-[0.7rem] text-emerald-700 mt-1">Publié le {{ $format->date($publication->publie_le, 'j F Y à H\hi') }}.</p>
        </div>
    @endif

    @if ($validation && $validation->decision->value === 'renvoye')
        <div class="rounded-xl border border-amber-300 bg-amber-50/70 p-3 text-xs text-amber-950">
            <p class="font-bold text-amber-900">Renvoyé par {{ $validation->decidePar?->nom_affiche ?? 'l’administrateur' }} :</p>
            <p class="mt-1 text-slate-700 italic">« {{ $validation->motif }} »</p>
        </div>
    @elseif ($validation && $validation->decision->value === 'en_attente')
        <div class="rounded-xl border-2 border-amber-300 bg-amber-50/70 p-3.5 text-xs">
            <p class="font-bold text-amber-950 flex items-center gap-1.5">
                <svg class="size-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>En attente de validation depuis le {{ $format->date($validation->soumis_le) }}</span>
            </p>
            <p class="text-[0.7rem] text-slate-600 mt-1">Soumis par {{ $validation->soumisPar?->nom_affiche ?? 'un agent' }}.</p>
            @can('contenu.publier', $objet)
                @if (! $empechement)
                    <form method="post" action="{{ route('admin.validation.approuver-publier', $validation) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="bouton bouton-publier w-full text-xs py-2 font-bold flex items-center justify-center gap-1.5 shadow-sm">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Approuver et publier en direct
                        </button>
                    </form>
                @endif
            @endcan
            <div class="mt-2 text-right">
                <a href="{{ route('admin.validation.show', $validation) }}" class="text-xs text-lac underline hover:text-lac/80 font-bold">Détails de l’examen →</a>
            </div>
        </div>
    @elseif ($validation && $validation->decision->value === 'approuve' && ! $validation->publication()->exists())
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3.5 text-xs">
            <p class="font-bold text-emerald-950 flex items-center gap-1.5">
                <svg class="size-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Approuvé : prêt pour la publication</span>
            </p>
            @can('contenu.publier', $objet)
                <form method="post" action="{{ route('admin.validation.publier', $validation) }}" class="mt-2.5">
                    @csrf
                    <button type="submit" class="bouton bouton-publier w-full text-xs py-2 font-bold shadow-sm">
                        Mettre en ligne maintenant
                    </button>
                </form>
            @endcan
        </div>
    @endif

    @if ($empechement = $objet->empechementPublication())
        <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 text-xs text-rose-800 flex items-start gap-2">
            <x-icone nom="alerte" class="icone size-4 flex-none text-rose-600 mt-0.5" />
            <span>{{ $empechement }}</span>
        </div>
    @endif

    {{-- Publication Directe (Superadmin & SG) --}}
    @can('contenu.publier', $objet)
        @if (! $empechement && (! $validation || $validation->decision->value !== 'en_attente'))
            <form method="post" action="{{ route("admin.$section.publier", $objet) }}" class="pt-1">
                @csrf
                <button type="submit" class="bouton bouton-publier w-full font-bold text-xs py-2.5 flex items-center justify-center gap-1.5 shadow-sm">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Mettre à jour et publier en direct</span>
                </button>
            </form>
        @endif
    @endcan

    {{-- Soumission pour validation --}}
    @can('contenu.soumettre', $objet)
        @if (! $validation || $validation->decision->value !== 'en_attente')
            <form method="post" action="{{ route("admin.$section.soumettre", $objet) }}" class="space-y-2 pt-1">
                @csrf
                <p class="text-xs text-slate-500">Fige l’instantané actuel pour revue et approbation par le Superadmin ou le SG.</p>
                <button type="submit" class="bouton bouton-secondaire w-full text-xs font-bold py-2 flex items-center justify-center gap-1.5">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Soumettre pour validation</span>
                </button>
            </form>
        @endif
    @endcan

    {{-- Retrait archivé --}}
    @if ($publication)
        @can('contenu.retirer', $objet)
            <div class="border-t border-slate-100 pt-3">
                <details class="text-xs text-slate-500">
                    <summary class="cursor-pointer hover:underline text-[0.72rem] font-medium text-slate-400">Retrait formel avec motif d’archivage…</summary>
                    <form method="post" action="{{ route("admin.$section.retirer", $objet) }}" class="space-y-3 pt-2">
                        @csrf
                        <x-portail.champ nom="motif" libelle="Motif du retrait" type="textarea" max="2000" requis formulaire="retrait" />
                        <button type="submit" class="bouton bouton-secondaire w-full text-xs">Confirmer le retrait archivé</button>
                    </form>
                </details>
            </div>
        @endcan
    @endif
</section>

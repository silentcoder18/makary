@php
    $peutTraiter = auth()->user()->can('demande.traiter');
    $possible = fn (string $statut) => collect($suivants)->contains(fn ($s) => $s->value === $statut);
@endphp
<x-admin.gabarit :titre="'Demande '.$demande->numero_suivi" chapeau="Consultation journalisée pour le suivi et la réponse aux requêtes citoyennes. Conservation jusqu’au {{ $format->date($demande->conserver_jusqu_au) }}.">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.demandes.index') }}" class="bouton bouton-secondaire text-xs">← Liste des demandes</a>
            <span class="font-mono font-bold text-xs px-2.5 py-1 bg-slate-100 rounded-lg text-slate-800 border border-slate-200">{{ $demande->numero_suivi }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ match($demande->statut->value) {
                'recue' => 'bg-amber-100 text-amber-800 border border-amber-200',
                'en_cours', 'qualifiee', 'affectee', 'en_traitement' => 'bg-sky-100 text-sky-800 border border-sky-200',
                'repondue', 'cloturee' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                default => 'bg-slate-100 text-slate-700 border border-slate-200'
            } }}">
                Statut : {{ $demande->statut->libelle() }}
            </span>
        </div>
    </div>

    <div class="grid gap-8 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            {{-- Contenu de la demande --}}
            <section class="panneau space-y-5" aria-labelledby="titre-demande">
                <div class="border-b border-slate-100 pb-3">
                    <h2 id="titre-demande" class="text-xl font-bold text-slate-900 tracking-tight">{{ $demande->objet }}</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Reçue le {{ $format->date($demande->recue_le, 'j F Y à H\hi') }}
                        @if ($demande->priorite) · <span class="font-bold text-slate-700">Priorité niveau {{ $demande->priorite }}</span>@endif
                        @if ($demande->affecteeA) · <span class="font-bold text-slate-700">Confiée à {{ $demande->affecteeA->nom_affiche }}</span>@endif
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 text-sm text-slate-800 whitespace-pre-line leading-relaxed">
                    {{ $demande->message }}
                </div>

                <dl class="grid gap-4 border-t border-slate-100 pt-4 text-xs sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-2.5 border border-slate-100">
                        <dt class="font-bold text-slate-500 uppercase tracking-wider text-[0.68rem]">Demandeur / Contact</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">{{ $demande->nom_contact ?? 'Anonyme' }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-2.5 border border-slate-100">
                        <dt class="font-bold text-slate-500 uppercase tracking-wider text-[0.68rem]">Adresse électronique</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">{{ $demande->courriel ?? '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-2.5 border border-slate-100">
                        <dt class="font-bold text-slate-500 uppercase tracking-wider text-[0.68rem]">Téléphone</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">{{ $demande->telephone ?? '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-2.5 border border-slate-100">
                        <dt class="font-bold text-slate-500 uppercase tracking-wider text-[0.68rem]">Organisme &amp; Pays</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">{{ collect([$demande->organisme, $demande->pays])->filter()->implode(', ') ?: '—' }}</dd>
                    </div>
                    @if ($demande->projet)
                        <div class="sm:col-span-2 rounded-lg bg-slate-50 p-2.5 border border-slate-100">
                            <dt class="font-bold text-slate-500 uppercase tracking-wider text-[0.68rem]">Projet communal concerné</dt>
                            <dd class="mt-0.5 font-semibold text-slate-900">
                                <a href="{{ route('admin.projets.show', $demande->projet) }}" class="text-lac hover:underline">
                                    {{ $demande->projet->titre }} ({{ $demande->projet->code }}) →
                                </a>
                            </dd>
                        </div>
                    @endif
                    <div class="sm:col-span-2 text-slate-500 pt-1">
                        Base légale de traitement : <span class="font-medium text-slate-700">{{ $demande->base->libelle() }}</span>
                        @if ($demande->consentement_donne)
                            · Consentement recueilli (mention d'information v. {{ $demande->texte_information_version }})
                        @endif
                    </div>
                </dl>
            </section>

            {{-- Historique du suivi --}}
            <section class="panneau space-y-4" aria-labelledby="titre-suivi">
                <div class="border-b border-slate-100 pb-2">
                    <h2 id="titre-suivi" class="text-base font-bold text-slate-900 tracking-tight">Historique de traitement</h2>
                </div>
                <ol class="space-y-3">
                    @forelse ($demande->evenements as $evenement)
                        <li class="border-l-2 border-slate-300 pl-3 py-1">
                            <p class="text-xs font-bold text-slate-800">
                                <span>{{ $evenement->type }}</span>
                                <span class="text-slate-400 font-normal">· {{ $format->date($evenement->le, 'j M Y à H\hi') }}</span>
                            </p>
                            <p class="text-xs text-slate-600 mt-0.5 whitespace-pre-line">{{ $evenement->contenu }}</p>
                        </li>
                    @empty
                        <li class="text-slate-500 text-xs py-2 italic">Aucune étape de traitement enregistrée pour le moment.</li>
                    @endforelse
                </ol>
            </section>
        </div>

        {{-- Panneau d'actions de traitement --}}
        @if ($peutTraiter && $suivants)
            <aside class="space-y-4">
                @if ($possible('qualifiee'))
                    <form method="post" action="{{ route('admin.demandes.agir', [$demande, 'qualifier']) }}" class="panneau space-y-3">
                        @csrf
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Qualifier la demande</h2>
                        <x-portail.champ nom="priorite" libelle="Niveau de priorité" :options="[1 => '1 — Haute', 2 => '2 — Normale', 3 => '3 — Basse']" requis />
                        <button type="submit" class="bouton bouton-principal w-full text-xs py-2 font-bold shadow-sm">Enregistrer la priorité</button>
                    </form>
                @endif

                @if ($possible('affectee'))
                    <form method="post" action="{{ route('admin.demandes.agir', [$demande, 'affecter']) }}" class="panneau space-y-3">
                        @csrf
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Assigner à un agent</h2>
                        <x-portail.champ nom="agent" libelle="Agent responsable" :options="$agents->pluck('nom_affiche', 'id')->all()" requis />
                        <button type="submit" class="bouton bouton-principal w-full text-xs py-2 font-bold shadow-sm">Assigner la demande</button>
                    </form>
                @endif

                @if ($possible('en_traitement'))
                    <form method="post" action="{{ route('admin.demandes.agir', [$demande, 'commencer']) }}" class="panneau space-y-2">
                        @csrf
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Traitement</h2>
                        <p class="text-xs text-slate-500">Passe la demande au statut « en cours d'instruction ».</p>
                        <button type="submit" class="bouton bouton-secondaire w-full text-xs py-2 font-bold">Marquer « En traitement »</button>
                    </form>
                @endif

                @if ($possible('repondue'))
                    <form method="post" action="{{ route('admin.demandes.agir', [$demande, 'repondre']) }}" class="panneau space-y-3">
                        @csrf
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Rédiger la réponse</h2>
                        <x-portail.champ nom="reponse" libelle="Texte officiel de réponse" type="textarea" max="4000" requis aide="Sera consigné dans le suivi officiel." />
                        <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 w-full text-xs py-2.5 font-bold shadow-sm">Enregistrer et notifier</button>
                    </form>
                @endif

                @if ($possible('cloturee'))
                    <form method="post" action="{{ route('admin.demandes.agir', [$demande, 'cloturer']) }}" class="panneau space-y-2">
                        @csrf
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Clôture</h2>
                        <button type="submit" class="bouton bouton-secondaire w-full text-xs py-2 font-bold">Clôturer définitivement la demande</button>
                    </form>
                @endif

                @if ($possible('rejetee'))
                    <form method="post" action="{{ route('admin.demandes.agir', [$demande, 'rejeter']) }}" class="panneau space-y-3 border border-rose-200 bg-rose-50/50">
                        @csrf
                        <h2 class="text-base font-bold text-rose-950 tracking-tight">Rejet motivé</h2>
                        <x-portail.champ nom="motif" libelle="Motif explicite du rejet" type="textarea" max="2000" requis aide="Transmis au citoyen en explication du refus." />
                        <button type="submit" class="bouton bg-rose-600 text-white hover:bg-rose-700 w-full text-xs py-2 font-bold shadow-sm">Confirmer le rejet</button>
                    </form>
                @endif
            </aside>
        @endif
    </div>
</x-admin.gabarit>

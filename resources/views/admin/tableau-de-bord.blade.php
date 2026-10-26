<x-admin.gabarit titre="Tableau de bord" chapeau="Espace de pilotage stratégique et indicateurs opérationnels de la Commune de Makary.">
    @php
        $agent = auth()->user();
        $taches = collect([
            ['validations', 'Validation en attente', 'admin.validation.index', 'contenu.approuver', 'verifie', 'amber', 'éléments à approuver et publier'],
            ['transitions', 'Transitions de statut', 'admin.projets.index', 'projet.valider_transition', 'chantier', 'sky', 'statuts de projets à examiner'],
            ['demandes_nouvelles', 'Demandes citoyennes', 'admin.demandes.index', 'demande.traiter', 'enveloppe', 'emerald', 'nouvelles demandes à qualifier'],
            ['mes_brouillons', 'Mes brouillons', 'admin.contenus.index', 'contenu.lire_brouillons', 'journal', 'purple', 'contenus en cours de rédaction'],
        ])->filter(fn ($t) => $agent->can($t[3]));

        $actionLibelles = [
            'contenu.creer' => ['Création d’un contenu', 'bg-blue-100 text-blue-800 border-blue-200'],
            'contenu.modifier' => ['Mise à jour de contenu', 'bg-slate-100 text-slate-800 border-slate-200'],
            'contenu.publier' => ['Publication en direct', 'bg-emerald-100 text-emerald-800 border-emerald-200'],
            'contenu.depublier' => ['Dépublication (remis en brouillon)', 'bg-amber-100 text-amber-800 border-amber-200'],
            'contenu.soumettre' => ['Soumission pour validation', 'bg-sky-100 text-sky-800 border-sky-200'],
            'validation.approuver' => ['Validation & publication', 'bg-emerald-100 text-emerald-800 border-emerald-200'],
            'media.televerser' => ['Téléversement photo/média', 'bg-purple-100 text-purple-800 border-purple-200'],
            'utilisateur.connexion' => ['Connexion à la plateforme', 'bg-slate-100 text-slate-700 border-slate-200'],
            'projet.modifier' => ['Mise à jour projet', 'bg-indigo-100 text-indigo-800 border-indigo-200'],
        ];
    @endphp

    {{-- Bannière d'accueil & Raccourcis d'action rapide --}}
    <div class="mb-8 rounded-2xl bg-gradient-to-r from-[#1a1439] via-[#2a2254] to-[#1a1439] p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 size-60 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-soleil text-xs font-bold tracking-wide uppercase mb-3">
                    <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Plateforme Municipale Active</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                    Bonjour, {{ $agent->nom_affiche }}
                </h2>
                <p class="mt-1 text-sm text-sable/80 max-w-xl">
                    Bienvenue sur l’espace d’administration officielle de la Commune de Makary. Gérez vos publications, les médias et les utilisateurs en toute simplicité.
                </p>
            </div>

            {{-- Actions Rapides en 1 Clic --}}
            <div class="flex flex-wrap items-center gap-2.5">
                @can('contenu.creer')
                    <a href="{{ route('admin.contenus.creer') }}" class="inline-flex items-center gap-2 rounded-xl bg-soleil text-encre px-4 py-2.5 text-xs font-bold shadow-md hover:bg-yellow-300 transition transform hover:-translate-y-0.5">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Rédiger un article</span>
                    </a>
                @endcan
                @can('media.televerser')
                    <a href="{{ route('admin.medias.creer') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 text-white border border-white/20 px-4 py-2.5 text-xs font-bold shadow-sm hover:bg-white/25 transition">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Téléverser une photo</span>
                    </a>
                @endcan
                @can('utilisateur.gerer')
                    <a href="{{ route('admin.utilisateurs.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/10 text-white/90 border border-white/15 px-3.5 py-2.5 text-xs font-bold hover:bg-white/20 transition">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Utilisateurs</span>
                    </a>
                @endcan
            </div>
        </div>
    </div>

    {{-- Section : Éléments prioritaires & Tâches à traiter --}}
    <section aria-labelledby="titre-a-traiter" class="mb-10">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 id="titre-a-traiter" class="text-lg font-bold text-slate-900 tracking-tight">À traiter en priorité</h2>
                <p class="text-xs text-slate-500">Actions et validations requises pour maintenir le portail citoyen à jour.</p>
            </div>
            @if ($aTraiter['validations'] > 0)
                <a href="{{ route('admin.validation.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200 hover:bg-amber-100 transition">
                    <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>Ouvrir la file de validation ({{ $aTraiter['validations'] }})</span>
                </a>
            @endif
        </div>

        @if ($taches->isEmpty())
            <div class="panneau text-center py-8 text-slate-500">
                <p class="text-sm">Aucune tâche en attente pour votre rôle actuellement.</p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($taches as [$cle, $titre, $route, $permission, $icone, $couleur, $details])
                    @php($nombre = $aTraiter[$cle] ?? 0)
                    <div class="kpi-stat group flex flex-col justify-between {{ $nombre > 0 && $cle === 'validations' ? 'ring-2 ring-amber-400 bg-amber-50/20' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex size-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700 group-hover:scale-105 transition-transform {{ $cle === 'validations' && $nombre > 0 ? 'bg-amber-100 text-amber-800' : '' }}">
                                <x-icone nom="{{ $icone }}" class="icone size-6" />
                            </span>
                            @if ($nombre > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[0.7rem] font-black {{ $cle === 'validations' ? 'bg-amber-500 text-white animate-pulse' : 'bg-slate-200 text-slate-800' }}">
                                    Action requise
                                </span>
                            @endif
                        </div>
                        <div class="mt-4">
                            <p class="text-3xl font-black tracking-tight text-slate-900">{{ $nombre }}</p>
                            <h3 class="font-bold text-sm text-slate-800 mt-1">{{ $titre }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $details }}</p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100">
                            <a href="{{ route($route) }}" class="text-xs font-bold text-lac hover:text-lac/80 flex items-center justify-between group-hover:translate-x-1 transition-transform">
                                <span>Accéder</span>
                                <span>→</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Section : Indicateurs clés du mois (§12) --}}
    @if ($indicateurs)
        <section class="mb-10" aria-labelledby="titre-indicateurs">
            <div class="mb-4">
                <h2 id="titre-indicateurs" class="text-lg font-bold text-slate-900 tracking-tight">Indicateurs de performance</h2>
                <p class="text-xs text-slate-500">Données calculées en temps réel selon les règles de gouvernance municipale.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="panneau flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Fiches Projets</span>
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                        </div>
                        <p class="mt-3 text-3xl font-black text-slate-900">
                            {{ $indicateurs['fiches_a_jour'] }} <span class="text-lg font-semibold text-slate-400">/ {{ $indicateurs['fiches_publiees'] }}</span>
                        </p>
                        <p class="text-xs text-slate-500 mt-1">Fiches publiées avec statut à jour de moins de 6 mois.</p>
                    </div>
                    @if ($indicateurs['fiches_publiees'] > 0)
                        <div class="mt-4 pt-3 border-t border-slate-100">
                            @php($pourcentage = round(($indicateurs['fiches_a_jour'] / max(1, $indicateurs['fiches_publiees'])) * 100))
                            <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                <span>Taux d’actualité</span>
                                <span>{{ $pourcentage }}%</span>
                            <progress max="100" value="{{ $pourcentage }}" class="h-2 w-full rounded-full overflow-hidden bg-slate-100 accent-emerald-500"></progress>
                        </div>
                    @endif
                </div>

                <div class="panneau flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Partenaires & Investisseurs</span>
                            <span class="size-2 rounded-full bg-blue-500"></span>
                        </div>
                        <p class="mt-3 text-3xl font-black text-slate-900">
                            {{ $indicateurs['contacts_recus'] }}
                            <span class="text-sm font-semibold text-slate-500">contacts reçus</span>
                        </p>
                        <p class="text-xs text-slate-500 mt-1">Dont <strong>{{ $indicateurs['contacts_qualifies'] }}</strong> qualifiés par les services municipaux.</p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600 flex items-center justify-between">
                        <span>Téléchargements de fiches</span>
                        <span class="font-bold text-slate-900">{{ $indicateurs['telechargements_fiches'] }}</span>
                    </div>
                </div>

                <div class="panneau flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Délai de première réponse</span>
                            <span class="size-2 rounded-full bg-purple-500"></span>
                        </div>
                        <p class="mt-3 text-3xl font-black text-slate-900">
                            {{ $indicateurs['delai_moyen_jours'] === null ? '—' : $format->nombre($indicateurs['delai_moyen_jours']).' j' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">Délai moyen de réponse aux requêtes investisseurs & bailleurs.</p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600 flex items-center justify-between">
                        <span>Médiane de réponse</span>
                        <span class="font-bold text-slate-900">{{ $indicateurs['delai_median_jours'] === null ? '—' : $format->nombre($indicateurs['delai_median_jours']).' jours' }}</span>
                    </div>
                </div>
            </div>

            @if ($indicateurs['acquis_par_devise'])
                <div class="mt-4 rounded-xl border border-slate-200/80 bg-white p-4 text-xs text-slate-600 flex flex-wrap items-center gap-2 shadow-xs">
                    <span class="font-bold text-slate-800">Financements acquis cette année :</span>
                    @foreach ($indicateurs['acquis_par_devise'] as $devise => $montant)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-900 font-bold border border-slate-200">
                            {{ $format->montant($montant, $devise) }}
                        </span>
                        @if (! $loop->last) · @endif
                    @endforeach
                    <span class="text-slate-400 italic">(selon règle de non-addition inter-devises)</span>
                </div>
            @endif
        </section>
    @endif

    {{-- Section : Activité récente --}}
    @if ($activite->isNotEmpty())
        <section aria-labelledby="titre-activite">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 id="titre-activite" class="text-lg font-bold text-slate-900 tracking-tight">Activité récente du back-office</h2>
                    <p class="text-xs text-slate-500">Traçabilité complète des dernières actions administratives.</p>
                </div>
                @can('journal.consulter')
                    <a href="{{ route('admin.journal.index') }}" class="text-xs font-bold text-lac hover:underline">Consulter le journal d’audit complet →</a>
                @endcan
            </div>

            <div class="panneau p-0 overflow-hidden shadow-xs">
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tableau de l’activité récente">
                    <table class="admin-tableau">
                        <thead>
                            <tr>
                                <th scope="col">Date & Heure</th>
                                <th scope="col">Utilisateur</th>
                                <th scope="col">Action effectuée</th>
                                <th scope="col">Élément concerné</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activite as $ligne)
                                @php($badgeAction = $actionLibelles[$ligne->action] ?? [ucfirst(str_replace(['.', '_'], ' ', $ligne->action)), 'bg-slate-100 text-slate-800 border-slate-200'])
                                <tr>
                                    <td class="whitespace-nowrap font-mono text-xs text-slate-600">
                                        {{ $format->date($ligne->horodatage, 'j M Y, H\hi') }}
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="size-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-[0.65rem] font-bold">
                                                {{ mb_substr($ligne->nom_affiche ?? 'S', 0, 1) }}
                                            </span>
                                            <span class="font-semibold text-slate-800">{{ $ligne->nom_affiche ?? 'Système' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border {{ $badgeAction[1] }}">
                                            {{ $badgeAction[0] }}
                                        </span>
                                    </td>
                                    <td class="text-xs text-slate-600 font-mono">
                                        <span class="font-bold text-slate-800">{{ ucfirst($ligne->objet_type) }}</span> #{{ $ligne->objet_id }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
</x-admin.gabarit>

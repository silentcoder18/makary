<x-admin.gabarit titre="Gestion des utilisateurs" chapeau="Création, attribution des rôles et contrôle des accès à la plateforme municipale de Makary.">
    {{-- Barre d'actions & Filtres --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <form method="get" action="{{ route('admin.utilisateurs.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <input type="search" name="q" value="{{ $recherche }}" placeholder="Rechercher par nom, courriel..."
                       class="champ text-xs py-2 px-3.5 min-w-[15rem] rounded-xl border-slate-200" />
            </div>
            <div>
                <select name="role" class="champ text-xs py-2 px-3 rounded-xl border-slate-200">
                    <option value="">Tous les rôles</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->code }}" @selected($roleFiltre === $r->code)>{{ $r->libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="statut" class="champ text-xs py-2 px-3 rounded-xl border-slate-200">
                    <option value="">Tous les statuts</option>
                    <option value="1" @selected($statutFiltre === '1')>Actifs uniquement</option>
                    <option value="0" @selected($statutFiltre === '0')>Désactivés</option>
                </select>
            </div>
            <button type="submit" class="bouton bouton-secondaire text-xs font-bold py-2 px-4 shadow-2xs">Filtrer</button>
            @if ($recherche || $roleFiltre || $statutFiltre !== null && $statutFiltre !== '')
                <a href="{{ route('admin.utilisateurs.index') }}" class="text-xs text-lac font-semibold hover:underline">Réinitialiser</a>
            @endif
        </form>

        <a href="{{ route('admin.utilisateurs.creer') }}" class="bouton bouton-principal shadow-sm flex items-center gap-2">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            <span>Nouvel utilisateur</span>
        </a>
    </div>

    {{-- Tableau des utilisateurs --}}
    <div class="panneau p-0 overflow-hidden shadow-xs">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Liste des utilisateurs">
            <table class="admin-tableau">
                <thead>
                    <tr>
                        <th scope="col">Utilisateur</th>
                        <th scope="col">Fonction</th>
                        <th scope="col">Rôle attribué</th>
                        <th scope="col">État du compte</th>
                        <th scope="col">Dernière activité</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($utilisateurs as $u)
                        @php($initialesU = collect(explode(' ', $u->nom_affiche))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''))
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="avatar-admin text-[0.7rem] size-9 shrink-0">
                                        {{ $initialesU ?: 'U' }}
                                    </span>
                                    <div>
                                        <div class="font-bold text-slate-900 text-sm">{{ $u->nom_affiche }}</div>
                                        <div class="text-xs text-slate-500 font-mono">{{ $u->courriel }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-xs text-slate-700">
                                {{ $u->fonction ?: '—' }}
                            </td>
                            <td>
                                @forelse ($u->roles as $role)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border {{ $role->code === 'superadmin' ? 'bg-purple-50 text-purple-800 border-purple-200' : 'bg-blue-50 text-blue-800 border-blue-200' }}">
                                        {{ $role->libelle }}
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400 italic">Aucun rôle</span>
                                @endforelse
                            </td>
                            <td>
                                @if ($u->actif)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span class="size-2 rounded-full bg-emerald-500"></span> Actif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                        <span class="size-2 rounded-full bg-rose-500"></span> Désactivé
                                    </span>
                                @endif
                            </td>
                            <td class="text-xs text-slate-500 font-mono whitespace-nowrap">
                                {{ $u->derniere_connexion ? $format->date($u->derniere_connexion, 'j M Y, H\hi') : 'Jamais connecté' }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <a href="{{ route('admin.utilisateurs.modifier', $u) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        Modifier
                                    </a>

                                    @if ($u->id !== auth()->id())
                                        <form method="post" action="{{ route('admin.utilisateurs.statut', $u) }}" class="inline"
                                              onsubmit="return confirm('Confirmer le changement de statut de cet utilisateur ?');">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold transition {{ $u->actif ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }}">
                                                {{ $u->actif ? 'Désactiver' : 'Activer' }}
                                            </button>
                                        </form>

                                        <form method="post" action="{{ route('admin.utilisateurs.detruire', $u) }}" class="inline"
                                              onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cet utilisateur ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center size-7 rounded-lg text-rose-600 hover:bg-rose-50 hover:text-rose-800 transition" title="Supprimer">
                                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-slate-500">
                                Aucun utilisateur ne correspond aux critères de recherche.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin.gabarit>

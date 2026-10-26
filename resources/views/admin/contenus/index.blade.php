@use('App\Http\Controllers\Admin\ContenusController', 'C')
<x-admin.gabarit titre="Pages et articles" chapeau="Gestion éditoriale des actualités, démarches, événements et pages du portail citoyen.">
    {{-- Barre d'actions & Filtres --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <nav aria-label="Types de contenus">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <a href="{{ route('admin.contenus.index') }}" class="puce {{ ! $type ? 'aria-current' : '' }}" @if (! $type) aria-current="true" @endif>
                        Tous
                    </a>
                </li>
                @foreach (C::TYPES as $code => $libelle)
                    <li>
                        <a href="{{ route('admin.contenus.index', ['type' => $code]) }}" class="puce {{ $type === $code ? 'aria-current' : '' }}" @if ($type === $code) aria-current="true" @endif>
                            {{ $libelle }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @can('contenu.creer')
            <a href="{{ route('admin.contenus.creer') }}" class="bouton bouton-principal shadow-sm flex items-center gap-2">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nouveau contenu</span>
            </a>
        @endcan
    </div>

    {{-- Tableau des contenus --}}
    <div class="panneau p-0 overflow-hidden shadow-xs">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Liste des contenus">
            <table class="admin-tableau">
                <thead>
                    <tr>
                        <th scope="col">Titre du contenu</th>
                        <th scope="col">Type</th>
                        <th scope="col">Module</th>
                        <th scope="col">Auteur</th>
                        <th scope="col">État</th>
                        <th scope="col">Dernière mise à jour</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contenus as $contenu)
                        @php($estPublie = ($contenu->etat?->value ?? $contenu->etat) === 'publie')
                        <tr>
                            <td class="font-semibold text-slate-900">
                                <a href="{{ route('admin.contenus.modifier', $contenu) }}" class="hover:text-lac hover:underline block max-w-md truncate">
                                    {{ $contenu->titre }}
                                </a>
                                @if ($contenu->resume)
                                    <p class="text-xs text-slate-500 font-normal line-clamp-1 mt-0.5">{{ $contenu->resume }}</p>
                                @endif
                            </td>
                            <td>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ C::TYPES[$contenu->type_contenu] ?? $contenu->type_contenu }}
                                </span>
                            </td>
                            <td class="text-xs font-mono text-slate-600">{{ $contenu->module }}</td>
                            <td class="text-xs text-slate-700">{{ $contenu->auteur?->nom_affiche ?? '—' }}</td>
                            <td><x-admin.etat :etat="$contenu->etat" /></td>
                            <td class="whitespace-nowrap text-xs text-slate-500 font-mono">{{ $format->date($contenu->modifie_le) }}</td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <a href="{{ route('admin.contenus.modifier', $contenu) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        Modifier
                                    </a>

                                    @if (! $estPublie)
                                        @can('contenu.publier', $contenu)
                                            <form method="post" action="{{ route('admin.contenus.publier', $contenu) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 px-2.5 py-1 text-xs font-bold shadow-2xs">
                                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Publier
                                                </button>
                                            </form>
                                    @endif

                                    @can('contenu.retirer', $contenu)
                                        <form method="post" action="{{ route('admin.contenus.detruire', $contenu) }}" onsubmit="return confirm('Supprimer définitivement cet article / contenu ?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center size-7 rounded-lg text-rose-600 hover:bg-rose-50 hover:text-rose-800 transition" title="Supprimer">
                                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-12 text-slate-500">
                                Aucun contenu trouvé dans cette catégorie.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        <x-admin.pagination :pages="$contenus" libelle="Pages de contenus" />
    </div>
</x-admin.gabarit>

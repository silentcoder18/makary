{{-- Liste commune des objets publiables du portail (MKY-P3-08) --}}
<x-admin.gabarit :titre="$textes['liste']" :chapeau="$textes['chapeau']">
    {{-- Barre d'actions & Filtres --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        @if ($filtres)
            <form method="get" class="flex flex-wrap items-center gap-3" aria-label="Filtrer la liste">
                @foreach ($filtres as $nom => [$libelle, $options])
                    <div class="min-w-[12rem]">
                        <x-portail.champ :nom="$nom" :libelle="$libelle" :options="$options" :valeur="request($nom)" vide-libelle="Tous" />
                    </div>
                @endforeach
                <button type="submit" class="bouton bouton-secondaire text-xs font-bold py-2 px-4 shadow-2xs">Filtrer</button>
                @if (request()->query())
                    <a href="{{ route("admin.$section.index") }}" class="text-xs text-lac font-semibold hover:underline">Réinitialiser</a>
                @endif
            </form>
        @else
            <div></div>
        @endif

        @if ($peutCreer)
            <a href="{{ route("admin.$section.creer") }}" class="bouton bouton-principal shadow-sm flex items-center gap-2">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>{{ $textes['nouveau'] }}</span>
            </a>
        @endif
    </div>

    {{-- Tableau des objets --}}
    <div class="panneau p-0 overflow-hidden shadow-xs">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ $textes['liste'] }}">
            <table class="admin-tableau">
                <thead>
                    <tr>
                        @foreach (array_keys($colonnes) as $entete)
                            <th scope="col">{{ $entete }}</th>
                        @endforeach
                        <th scope="col">État de publication</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($objets as $objet)
                        <tr>
                            @foreach (array_values($colonnes) as $i => $valeur)
                                @if ($i === 0)
                                    <td class="font-semibold text-slate-900">
                                        <div class="flex items-center gap-3">
                                            @if ($objet instanceof \App\Models\Media && $objet->fichier)
                                                <img src="{{ route('admin.fichiers.show', $objet->fichier) }}" alt="" class="size-12 rounded-lg object-cover border border-slate-200 shadow-2xs shrink-0" loading="lazy">
                                            @endif
                                            <a href="{{ route("admin.$section.modifier", $objet) }}" class="hover:text-lac hover:underline block max-w-sm sm:max-w-md truncate">
                                                {{ $valeur($objet) }}
                                            </a>
                                        </div>
                                    </td>
                                @else
                                    <td class="text-slate-700 text-xs">{{ $valeur($objet) }}</td>
                                @endif
                            @endforeach
                            <td>
                                @if (isset($objet->etat))
                                    <x-admin.etat :etat="$objet->etat" />
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $objet->publicationCourante() ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                                        {{ $objet->publicationCourante() ? 'Publié' : 'Non publié' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <a href="{{ route("admin.$section.modifier", $objet) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        Modifier
                                    </a>

                                    @if (! $objet->publicationCourante() && ! $objet->empechementPublication())
                                        @can('contenu.publier', $objet)
                                            <form method="post" action="{{ route("admin.$section.publier", $objet) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 px-2.5 py-1 text-xs font-bold shadow-2xs">
                                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Publier
                                                </button>
                                            </form>
                                    @endif

                                    @can('contenu.retirer', $objet)
                                        <form method="post" action="{{ route("admin.$section.detruire", $objet) }}" onsubmit="return confirm('Supprimer définitivement cet élément ?');" class="inline">
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
                            <td colspan="{{ count($colonnes) + 2 }}" class="text-center py-12 text-slate-500">
                                {{ $textes['vide'] }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        <x-admin.pagination :pages="$objets" :libelle="'Pages : '.$textes['liste']" />
    </div>
</x-admin.gabarit>

<x-admin.gabarit titre="Fiches Projets et Réalisations" chapeau="Suivi des chantiers municipaux, fiches publiques, estimations de coûts et financements acquis. Accessible au Super Administrateur et au Secrétariat Général.">
    {{-- Barre d'actions & Filtres --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <nav aria-label="Statuts des projets">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <a href="{{ route('admin.projets.index') }}" class="puce" @if (! $statut) aria-current="true" @endif>
                        Tous
                    </a>
                </li>
                @foreach (\App\Domaine\Enums\StatutProjet::cases() as $s)
                    <li>
                        <a href="{{ route('admin.projets.index', ['statut' => $s->value]) }}" class="puce" @if ($statut === $s) aria-current="true" @endif>
                            {{ $s->libelle() }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
        @can('projet.creer')
            <a href="{{ route('admin.projets.creer') }}" class="bouton bouton-principal shadow-sm flex items-center gap-2">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nouvelle fiche projet</span>
            </a>
        @endcan
    </div>

    {{-- Tableau des projets --}}
    <div class="panneau p-0 overflow-hidden shadow-xs">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Liste des projets">
            <table class="admin-tableau">
                <thead>
                    <tr>
                        <th scope="col">Fiche projet</th>
                        <th scope="col">Statut opérationnel</th>
                        <th scope="col">Publication</th>
                        <th scope="col">Catalogue public</th>
                        <th scope="col">Dernière mise à jour</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projets as $projet)
                        <tr>
                            <td class="font-semibold text-slate-900">
                                <div>
                                    <a href="{{ route('admin.projets.show', $projet) }}" class="hover:text-lac hover:underline block font-bold text-slate-900">
                                        {{ $projet->titre }}
                                    </a>
                                    <span class="font-mono text-xs text-slate-500 font-normal">{{ $projet->code }}</span>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <x-portail.badge-statut :statut="['code' => $projet->statut->value, 'libelle' => $projet->statut->libelle()]" />
                                    @if ($projet->transitions->contains(fn ($t) => $t->decision->value === 'en_attente'))
                                        <span class="mt-1 inline-flex items-center gap-1 text-[0.68rem] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                            <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            Transition en attente
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <x-admin.etat :etat="$projet->etat" />
                            </td>
                            <td>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $projet->visible_catalogue ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                    {{ $projet->visible_catalogue ? 'Visible' : 'Retirée' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-xs font-mono text-slate-600">
                                {{ $format->date($projet->statut_mis_a_jour_le, 'j M Y') }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.projets.show', $projet) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Consulter / Gérer
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-slate-500">
                                Aucune fiche projet enregistrée pour le moment.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin.gabarit>

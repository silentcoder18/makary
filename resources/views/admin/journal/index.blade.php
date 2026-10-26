<x-admin.gabarit titre="Journal d’audit et traçabilité" chapeau="Registre immuable des connexions, publications, validations et consultations. Conforme aux exigences de transparence et de sécurité légale.">
    {{-- Filtre d'actions --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <form method="get" action="{{ route('admin.journal.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="min-w-[14rem]">
                <x-portail.champ nom="action" libelle="Filtrer par action" :options="$actions->mapWithKeys(fn ($a) => [$a => $a])->all()" :valeur="$action" vide-libelle="Toutes les actions" />
            </div>
            <button type="submit" class="bouton bouton-secondaire text-xs font-bold py-2 px-4 shadow-2xs">Filtrer</button>
            @if ($action)
                <a href="{{ route('admin.journal.index') }}" class="text-xs text-lac font-semibold hover:underline">Réinitialiser</a>
            @endif
        </form>

        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-semibold">
            <svg class="size-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span>Journal protégé en ajout seul</span>
        </div>
    </div>

    {{-- Tableau d'audit --}}
    <div class="panneau p-0 overflow-hidden shadow-xs">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Lignes du journal">
            <table class="admin-tableau">
                <thead>
                    <tr>
                        <th scope="col">Horodatage</th>
                        <th scope="col">Agent / Utilisateur</th>
                        <th scope="col">Action</th>
                        <th scope="col">Objet concerné</th>
                        <th scope="col">Détails de l’opération</th>
                        <th scope="col">Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lignes as $ligne)
                        <tr>
                            <td class="whitespace-nowrap font-mono text-xs text-slate-600">
                                {{ $format->date($ligne->horodatage, 'j M Y, H\hi') }}
                            </td>
                            <td>
                                <div class="font-bold text-slate-800 text-xs">
                                    {{ $ligne->nom_affiche ?? 'Système' }}
                                </div>
                            </td>
                            <td>
                                <code class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 text-xs font-mono border border-slate-200">
                                    {{ $ligne->action }}
                                </code>
                            </td>
                            <td class="text-xs text-slate-700 font-mono">
                                {{ $ligne->objet_type ? ucfirst($ligne->objet_type).' #'.$ligne->objet_id : '—' }}
                            </td>
                            <td class="max-w-xs sm:max-w-md truncate text-xs text-slate-500 font-mono">
                                {{ $ligne->details === '{}' ? '—' : $ligne->details }}
                            </td>
                            <td class="text-xs font-mono text-slate-600">
                                {{ $ligne->adresse_ip }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-slate-500">
                                Aucune entrée trouvée dans le journal pour ces critères.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        <x-admin.pagination :pages="$lignes" libelle="Pages du journal" />
    </div>
</x-admin.gabarit>

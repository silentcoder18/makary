@php
    $types = ['contact' => 'Contact citoyen', 'investisseur' => 'Investisseur', 'partenaire' => 'Partenaire institutionnel', 'diaspora' => 'Diaspora', 'autre' => 'Autre'];
@endphp
<x-admin.gabarit titre="Demandes citoyennes & partenariats" chapeau="Suivi des requêtes, propositions de partenariat et messages citoyens. La consultation nominative fait l’objet d’une traçabilité dans le journal d’audit.">
    {{-- Filtre par statut --}}
    <div class="mb-6">
        <nav aria-label="Statuts des demandes">
            <ul class="flex flex-wrap gap-2">
                <li>
                    <a href="{{ route('admin.demandes.index') }}" class="puce {{ ! $statut ? 'aria-current' : '' }}" @if (! $statut) aria-current="true" @endif>
                        Toutes les demandes
                    </a>
                </li>
                @foreach (\App\Domaine\Enums\StatutDemande::cases() as $s)
                    <li>
                        <a href="{{ route('admin.demandes.index', ['statut' => $s->value]) }}" class="puce {{ $statut === $s ? 'aria-current' : '' }}" @if ($statut === $s) aria-current="true" @endif>
                            {{ $s->libelle() }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>

    {{-- Tableau des demandes --}}
    <div class="panneau p-0 overflow-hidden shadow-xs">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Liste des demandes">
            <table class="admin-tableau">
                <thead>
                    <tr>
                        <th scope="col">N° de suivi</th>
                        <th scope="col">Catégorie</th>
                        <th scope="col">Objet de la demande</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Priorité</th>
                        <th scope="col">Date de réception</th>
                        <th scope="col" class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($demandes as $demande)
                        <tr>
                            <td class="whitespace-nowrap font-mono text-xs font-bold text-slate-800">
                                <a href="{{ route('admin.demandes.show', $demande) }}" class="text-lac hover:underline">
                                    {{ $demande->numero_suivi }}
                                </a>
                            </td>
                            <td>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $types[$demande->type_demande->value] ?? $demande->type_demande->value }}
                                </span>
                            </td>
                            <td class="text-xs text-slate-800 font-medium max-w-xs sm:max-w-md truncate">
                                {{ \Illuminate\Support\Str::limit($demande->objet, 80) }}
                            </td>
                            <td>
                                @php($statutVal = $demande->statut->value)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ match($statutVal) {
                                    'recue' => 'bg-amber-50 text-amber-800 border-amber-200',
                                    'en_cours' => 'bg-sky-50 text-sky-800 border-sky-200',
                                    'repondue' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    'classee' => 'bg-slate-100 text-slate-700 border-slate-200',
                                    default => 'bg-slate-100 text-slate-700 border-slate-200'
                                } }}">
                                    {{ $demande->statut->libelle() }}
                                </span>
                            </td>
                            <td class="text-xs text-slate-600">
                                {{ $demande->priorite ? ucfirst($demande->priorite) : 'Normale' }}
                            </td>
                            <td class="whitespace-nowrap text-xs text-slate-500 font-mono">
                                {{ $format->date($demande->recue_le, 'j M Y, H\hi') }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.demandes.show', $demande) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                    Consulter →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-12 text-slate-500">
                                Aucune demande dans cette catégorie.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        <x-admin.pagination :pages="$demandes" libelle="Pages de demandes" />
    </div>
</x-admin.gabarit>

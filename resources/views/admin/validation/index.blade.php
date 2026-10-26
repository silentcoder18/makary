@php($types = ['contenu' => 'Contenu / Article', 'projet' => 'Fiche projet', 'document' => 'Document', 'elu' => 'Élu', 'partenaire' => 'Partenaire', 'localite' => 'Localité', 'equipement' => 'Équipement', 'media' => 'Photo / Média'])
<x-admin.gabarit titre="File de validation" chapeau="Examinez, validez et publiez les articles et médias soumis sur la plateforme. Accessible au Super Administrateur et au Secrétariat Général.">
    {{-- Filtres de type --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <nav aria-label="Filtrer les éléments à valider">
            <ul class="flex flex-wrap gap-2">
                <li><a href="{{ route('admin.validation.index') }}" class="puce" @if (! $typeFiltre) aria-current="true" @endif>Tous ({{ $compteurs['tous'] }})</a></li>
                <li><a href="{{ route('admin.validation.index', ['type' => 'contenu']) }}" class="puce" @if ($typeFiltre === 'contenu') aria-current="true" @endif>Articles &amp; Pages ({{ $compteurs['contenu'] }})</a></li>
                <li><a href="{{ route('admin.validation.index', ['type' => 'media']) }}" class="puce" @if ($typeFiltre === 'media') aria-current="true" @endif>Photos &amp; Médias ({{ $compteurs['media'] }})</a></li>
                @if ($compteurs['autres'] > 0)
                    <li><a href="{{ route('admin.validation.index', ['type' => 'autres']) }}" class="puce" @if ($typeFiltre === 'autres') aria-current="true" @endif>Autres éléments ({{ $compteurs['autres'] }})</a></li>
                @endif
            </ul>
        </nav>
    </div>

    {{-- Section : En attente de validation --}}
    <section aria-labelledby="titre-attente" class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 id="titre-attente" class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <span>Éléments en attente de décision</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black {{ $enAttente->count() > 0 ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700' }}">{{ $enAttente->count() }}</span>
            </h2>
            <span class="text-xs text-slate-500">Validation par Superadmin ou Secrétaire Général</span>
        </div>

        <div class="panneau p-0 overflow-hidden shadow-xs">
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Éléments à examiner">
                <table class="admin-tableau">
                    <thead>
                        <tr>
                            <th scope="col">Élément soumis</th>
                            <th scope="col">Type</th>
                            <th scope="col">Auteur de la soumission</th>
                            <th scope="col">Date & Heure</th>
                            <th scope="col" class="text-right">Décision / Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($enAttente as $v)
                            <tr>
                                <td class="font-semibold">
                                    <div class="flex items-center gap-3">
                                        @if ($v->objet_type->value === 'media' && isset($v->instantane['fichier']['id']))
                                            <img src="{{ route('admin.fichiers.show', $v->instantane['fichier']['id']) }}" alt="" class="size-11 rounded-lg object-cover border border-slate-200 shadow-2xs shrink-0" loading="lazy">
                                        @endif
                                        <div>
                                            <a href="{{ route('admin.validation.show', $v) }}" class="font-bold text-slate-900 hover:text-lac hover:underline block max-w-sm sm:max-w-md truncate">
                                                {{ $v->instantane['titre'] ?? $v->instantane['nom'] ?? $v->instantane['nom_affiche'] ?? 'Élément #'.$v->objet_id }}
                                            </a>
                                            @if (! empty($v->instantane['resume']))
                                                <p class="text-xs text-slate-500 font-normal line-clamp-1 mt-0.5">{{ $v->instantane['resume'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" data-etat="en_revue">{{ $types[$v->objet_type->value] ?? $v->objet_type->value }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="size-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-[0.65rem] font-bold">
                                            {{ mb_substr($v->soumisPar?->nom_affiche ?? 'A', 0, 1) }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-800">{{ $v->soumisPar?->nom_affiche ?? 'Auteur inconnu' }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap font-mono text-xs text-slate-600">
                                    {{ $format->date($v->soumis_le, 'j M Y à H\hi') }}
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2 justify-end">
                                        @if (auth()->user()->can('contenu.publier', $v->objet()) && ! $v->objet()->empechementPublication())
                                            <form method="post" action="{{ route('admin.validation.approuver-publier', $v) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 px-3 py-1.5 text-xs font-bold shadow-2xs">
                                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Valider & Publier
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.validation.show', $v) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                            Examiner
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-slate-500 py-10 text-center">
                                    Aucun élément en attente de validation actuellement.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Section : Approuvés prêts à publier --}}
    @if ($aPublier->count() > 0)
        <section class="mt-10 space-y-3" aria-labelledby="titre-publier">
            <div class="flex items-center justify-between gap-2">
                <h2 id="titre-publier" class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Approuvés, prêts pour la mise en ligne</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-900">{{ $aPublier->count() }}</span>
                </h2>
                <span class="text-xs text-slate-500">Mise en ligne immédiate par un administrateur</span>
            </div>

            <div class="panneau p-0 overflow-hidden shadow-xs">
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Éléments à publier">
                    <table class="admin-tableau">
                        <thead>
                            <tr>
                                <th scope="col">Élément</th>
                                <th scope="col">Type</th>
                                <th scope="col">Approuvé par</th>
                                <th scope="col">Date d'approbation</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aPublier as $v)
                                <tr>
                                    <td class="font-semibold">
                                        <div class="flex items-center gap-3">
                                            @if ($v->objet_type->value === 'media' && isset($v->instantane['fichier']['id']))
                                                <img src="{{ route('admin.fichiers.show', $v->instantane['fichier']['id']) }}" alt="" class="size-11 rounded-lg object-cover border border-slate-200 shadow-2xs shrink-0" loading="lazy">
                                            @endif
                                            <a href="{{ route('admin.validation.show', $v) }}" class="font-bold text-slate-900 hover:text-lac hover:underline block max-w-sm truncate">
                                                {{ $v->instantane['titre'] ?? $v->instantane['nom'] ?? $v->instantane['nom_affiche'] ?? 'Élément #'.$v->objet_id }}
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" data-etat="approuve">{{ $types[$v->objet_type->value] ?? $v->objet_type->value }}</span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="size-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-[0.65rem] font-bold">
                                                {{ mb_substr($v->decidePar?->nom_affiche ?? 'A', 0, 1) }}
                                            </span>
                                            <span class="text-xs font-semibold text-slate-800">{{ $v->decidePar?->nom_affiche }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap font-mono text-xs text-slate-600">
                                        {{ $format->date($v->decide_le, 'j M Y à H\hi') }}
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2 justify-end">
                                            @can('contenu.publier', $v->objet())
                                                <form method="post" action="{{ route('admin.validation.publier', $v) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 px-3 py-1.5 text-xs font-bold shadow-2xs">
                                                        Mettre en ligne
                                                    </button>
                                                </form>
                                            @endcan
                                            <a href="{{ route('admin.validation.show', $v) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                                                Détails
                                            </a>
                                        </div>
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

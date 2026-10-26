@use('App\Http\Controllers\Admin\ProjetsController', 'P')
@php
    $agent = auth()->user();
    $modifiable = $agent->can('projet.modifier', $projet);
    $natures = ['verifie' => 'Vérifié', 'estimation' => 'Estimation', 'a_confirmer' => 'À confirmer (jamais publié)'];
    $statuts = ['recherche' => 'Recherché', 'sollicite' => 'Sollicité (interne)', 'acquis' => 'Acquis'];
    $peutValiderAcquis = $agent->can('financement.valider_acquis', new \App\Securite\Cible(['projets']));
    $piecesOptions = $projet->pieces->mapWithKeys(fn ($p) => [$p->id => (P::TYPES_PIECE[$p->type_piece] ?? $p->type_piece).' — '.$p->source->intitule])->all();
@endphp
<x-admin.gabarit :titre="$projet->titre" chapeau="Fiche technique et financière du projet communal. Code : {{ $projet->code }}.">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 text-xs">
            <span class="font-mono font-bold px-2 py-1 bg-slate-100 rounded text-slate-700 border border-slate-200">{{ $projet->code }}</span>
            <x-portail.badge-statut :statut="['code' => $projet->statut->value, 'libelle' => $projet->statut->libelle()]" />
            <x-admin.etat :etat="$projet->etat" />
            <span class="text-slate-500">Statut mis à jour le {{ $format->date($projet->statut_mis_a_jour_le, 'j F Y') }}</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.projets.index') }}" class="bouton bouton-secondaire text-xs">← Liste des projets</a>
            @if ($publication)
                <a href="{{ route('projets.show', $projet->slug) }}" target="_blank" class="bouton bouton-secondaire text-xs text-lac font-bold flex items-center gap-1">
                    <span>Voir sur le portail</span>
                    <span>↗</span>
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-8 2xl:grid-cols-[1fr_24rem]">
        <div class="min-w-0 space-y-8">
            {{-- Formulaire des champs de la fiche --}}
            <form method="post" action="{{ route('admin.projets.mettre-a-jour', $projet) }}" class="panneau space-y-6" novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="_formulaire" value="fiche">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-slate-900 tracking-tight">Caractéristiques du projet</h2>
                    <p class="text-xs text-slate-500">Informations générales, descriptif technique et localisation.</p>
                </div>
                <fieldset class="space-y-5" @disabled(! $modifiable)>
                    <legend class="sr-only">Champs de la fiche</legend>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-portail.champ nom="titre" libelle="Titre du projet" max="120" :valeur="$projet->titre" formulaire="fiche" requis />
                        <x-portail.champ nom="secteur" libelle="Secteur d’activité" :options="$secteurs" :valeur="$projet->secteur" formulaire="fiche" requis />
                    </div>
                    <x-portail.champ nom="resume" libelle="Résumé public" type="textarea" max="500" :valeur="$projet->resume" formulaire="fiche" aide="Synthèse affichée sur les listes et cartes du portail." />
                    <x-portail.champ nom="besoin" libelle="Besoin auquel le projet répond" type="textarea" max="4000" :valeur="$projet->besoin" formulaire="fiche" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-portail.champ nom="besoin_source" libelle="Source du besoin (délibération, étude, plainte…)" max="300" :valeur="$projet->besoinSource?->intitule" formulaire="fiche" />
                        <x-portail.champ nom="besoin_source_date" libelle="Date de la source" type="date" :valeur="$projet->besoinSource?->date_source?->toDateString()" formulaire="fiche" />
                    </div>
                    <x-portail.champ nom="description_technique" libelle="Description technique détaillée" type="textarea" max="20000" :valeur="$projet->description_technique" formulaire="fiche" />
                    <div class="flex items-center gap-2 pt-1">
                        <input type="hidden" name="description_publique" value="0">
                        <input id="champ-description_publique" type="checkbox" name="description_publique" value="1" class="case" @checked($projet->description_publique)>
                        <label for="champ-description_publique" class="text-xs font-semibold text-slate-800 cursor-pointer">Rendre la description technique visible au public sur le portail</label>
                    </div>
                    <x-portail.champ nom="service_responsable" libelle="Service communal responsable du suivi" max="160" :valeur="$projet->service_responsable" formulaire="fiche" />
                    <fieldset class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                        <legend class="etiquette font-bold text-slate-800">Localités bénéficiaires</legend>
                        <div class="grid gap-2 sm:grid-cols-3 mt-2">
                            @foreach ($localites as $id => $nom)
                                <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer hover:text-lac">
                                    <input type="checkbox" name="localites[]" value="{{ $id }}" class="case" @checked($projet->localites->contains('id', $id))>
                                    <span>{{ $nom }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @if ($modifiable)
                        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-end gap-3">
                            @can('contenu.publier', $projet)
                                <button type="submit" name="action" value="publier" class="bouton bouton-publier font-semibold shadow-sm flex items-center gap-1.5">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Mettre à jour et publier en direct
                                </button>
                            @endcan
                            <button type="submit" name="action" value="sauvegarder" class="bouton bouton-secondaire">
                                Enregistrer les modifications
                            </button>
                        </div>
                    @endif
                </fieldset>
            </form>

            {{-- Financement --}}
            <section class="panneau space-y-6" aria-labelledby="titre-financement">
                <div class="border-b border-slate-100 pb-3">
                    <h2 id="titre-financement" class="text-lg font-bold text-slate-900 tracking-tight">Financement et bailleurs</h2>
                    <p class="text-xs text-slate-500">Synthèse budgétaire et lignes de financement associées.</p>
                </div>
                <dl class="grid gap-4 sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <dt class="text-xs font-bold text-slate-500 uppercase tracking-wider">Coût total</dt>
                        <dd class="mt-1 text-base font-black text-slate-900">{{ $format->montant($synthese['cout'], $synthese['devise']) }}</dd>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                        <dt class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Acquis</dt>
                        <dd class="mt-1 text-base font-black text-emerald-800">{{ $format->montant($synthese['acquis'], $synthese['devise']) }}</dd>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <dt class="text-xs font-bold text-amber-700 uppercase tracking-wider">Recherché</dt>
                        <dd class="mt-1 text-base font-black text-amber-800">{{ $format->montant($synthese['recherche'], $synthese['devise']) }}</dd>
                    </div>
                    <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                        <dt class="text-xs font-bold text-sky-700 uppercase tracking-wider">Sollicité</dt>
                        <dd class="mt-1 text-base font-black text-sky-800">{{ $format->montant($synthese['sollicite'], $synthese['devise']) }}</dd>
                    </div>
                </dl>

                <div class="p-0 overflow-hidden rounded-xl border border-slate-200 shadow-2xs">
                    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Lignes de financement">
                        <table class="admin-tableau">
                            <thead>
                                <tr>
                                    <th scope="col">Statut</th>
                                    <th scope="col">Financeur</th>
                                    <th scope="col">Montant</th>
                                    <th scope="col">Pièce / Source</th>
                                    <th scope="col">Validation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($projet->financements as $ligne)
                                    <tr>
                                        <td>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $ligne->statut_ligne->value === 'acquis' ? 'bg-emerald-100 text-emerald-800' : ($ligne->statut_ligne->value === 'sollicite' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800') }}">
                                                {{ $statuts[$ligne->statut_ligne->value] ?? $ligne->statut_ligne->value }}
                                            </span>
                                        </td>
                                        <td class="font-semibold text-slate-800">{{ $ligne->partenaire?->nom ?? 'Budget communal' }}</td>
                                        <td class="whitespace-nowrap font-bold text-slate-900">{{ $format->montant($ligne->montant, $ligne->devise) }}</td>
                                        <td class="text-xs text-slate-600">{{ $ligne->source->intitule }}, {{ $format->date($ligne->date_information) }}</td>
                                        <td>
                                            @if ($ligne->statut_ligne->value === 'acquis')
                                                <span class="text-xs font-semibold text-emerald-700 flex items-center gap-1">
                                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Validée le {{ $format->date($ligne->valide_le, 'j M Y') }}
                                                </span>
                                            @elseif ($ligne->statut_ligne->value === 'sollicite' && $peutValiderAcquis)
                                                <details class="text-xs">
                                                    <summary class="cursor-pointer font-bold text-lac hover:underline">Valider comme acquise…</summary>
                                                    <form method="post" action="{{ route('admin.projets.acquis', $ligne) }}" class="mt-3 space-y-3 p-3 rounded-xl border border-slate-200 bg-slate-50">
                                                        @csrf
                                                        <input type="hidden" name="_formulaire" value="acquis-{{ $ligne->id }}">
                                                        <x-admin.source formulaire="acquis-{{ $ligne->id }}" legende="Pièce : convention ou accord officiel signé" :types="['convention' => 'Convention', 'piece_officielle' => 'Pièce officielle']" />
                                                        <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 text-xs py-1.5 font-bold shadow-sm w-full">Valider sur pièce</button>
                                                    </form>
                                                </details>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-slate-500 text-center py-6">Aucune ligne de financement enregistrée.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @can('financement.modifier', $projet)
                    <details class="pt-2">
                        <summary class="cursor-pointer font-bold text-xs text-lac hover:underline">+ Ajouter une ligne de financement (recherchée ou sollicitée)</summary>
                        <form method="post" action="{{ route('admin.projets.ligne', $projet) }}" class="mt-4 space-y-4 p-4 rounded-xl border border-slate-200 bg-slate-50/70" novalidate>
                            @csrf
                            <input type="hidden" name="_formulaire" value="financement">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-portail.champ nom="statut_ligne" libelle="Statut de la ligne" :options="['recherche' => 'Recherché', 'sollicite' => 'Sollicité']" formulaire="financement" requis />
                                <x-portail.champ nom="partenaire_id" libelle="Partenaire / Billeur sollicité" :options="$partenaires" formulaire="financement" aide="Obligatoire pour une ligne sollicitée." />
                                <x-portail.champ nom="montant" libelle="Montant (chiffres)" type="number" formulaire="financement" requis />
                                <x-portail.champ nom="devise" libelle="Devise" max="3" valeur="XAF" formulaire="financement" requis />
                                <x-portail.champ nom="date_information" libelle="Date de l’information" type="date" formulaire="financement" requis />
                            </div>
                            <x-admin.source formulaire="financement" />
                            <p class="text-xs text-slate-500">Une ligne sollicitée devient « acquise » dès confirmation sur pièce (convention ou accord officiel).</p>
                            <button type="submit" class="bouton bouton-principal text-xs py-2">Enregistrer la ligne</button>
                        </form>
                    </details>
                @endcan
            </section>

            {{-- Éléments sourcés complémentaires --}}
            @foreach ([
                'estimations' => ['Coûts estimatifs & Devis', $projet->estimations, fn ($e) => $format->montant($e->montant, $e->devise).' — '.$format->natureCout($e->nature_cout).', '.$natures[$e->nature->value].' au '.$format->date($e->date_estimation).' · '.$e->source->intitule],
                'beneficiaires' => ['Bénéficiaires ciblés', $projet->beneficiaires, fn ($b) => $format->nombre($b->nombre).' '.$b->categorie.' — '.$b->methode_estimation.' ('.$natures[$b->nature->value].', '.$format->date($b->date_estimation).')'],
                'indicateurs' => ['Indicateurs de performance', $projet->indicateurs, fn ($i) => $i->libelle.' : cible '.$format->nombre($i->cible).' '.$i->unite.' — '.$i->methode_mesure],
                'pieces' => ['Pièces justificatives & Marchés', $projet->pieces, fn ($p) => (P::TYPES_PIECE[$p->type_piece] ?? $p->type_piece).' — '.$p->source->intitule.' ('.$p->diffusion->libelle().')'],
            ] as $relation => [$titre, $elements, $ligne])
                <section class="panneau space-y-4" aria-labelledby="titre-{{ $relation }}">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h2 id="titre-{{ $relation }}" class="text-base font-bold text-slate-900 tracking-tight">{{ $titre }}</h2>
                        <span class="text-xs text-slate-500 font-semibold">{{ count($elements) }} élément(s)</span>
                    </div>
                    <ul class="space-y-2">
                        @forelse ($elements as $element)
                            <li class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-2.5 text-xs text-slate-800 font-medium">{{ $ligne($element) }}</li>
                        @empty
                            <li class="text-slate-500 text-xs py-1 italic">Aucun élément renseigné pour l'instant.</li>
                        @endforelse
                    </ul>
                    @if ($modifiable)
                        <details class="pt-1">
                            <summary class="cursor-pointer font-bold text-xs text-lac hover:underline">+ Ajouter dans {{ strtolower($titre) }}</summary>
                            <form method="post" action="{{ route('admin.projets.element', [$projet, $relation]) }}" class="mt-3 space-y-4 p-4 rounded-xl border border-slate-200 bg-slate-50/70" novalidate>
                                @csrf
                                <input type="hidden" name="_formulaire" value="{{ $relation }}">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @switch($relation)
                                        @case('estimations')
                                            <x-portail.champ nom="montant" libelle="Montant" type="number" formulaire="estimations" requis />
                                            <x-portail.champ nom="devise" libelle="Devise" max="3" valeur="XAF" formulaire="estimations" requis />
                                            <x-portail.champ nom="date_estimation" libelle="Date de l’estimation" type="date" formulaire="estimations" requis />
                                            <x-portail.champ nom="nature_cout" libelle="Nature du montant" :options="['estimation' => 'Estimation', 'devis' => 'Devis', 'marche' => 'Marché', 'cout_final' => 'Coût final']" formulaire="estimations" requis />
                                            <x-portail.champ nom="nature" libelle="Fiabilité de l’information" :options="$natures" formulaire="estimations" requis />
                                            @break
                                        @case('beneficiaires')
                                            <x-portail.champ nom="nombre" libelle="Nombre" type="number" formulaire="beneficiaires" requis />
                                            <x-portail.champ nom="categorie" libelle="Catégorie (ex: Habitants, élèves)" max="80" formulaire="beneficiaires" requis />
                                            <x-portail.champ nom="methode_estimation" libelle="Méthode d’estimation" max="500" formulaire="beneficiaires" requis class="sm:col-span-2" />
                                            <x-portail.champ nom="date_estimation" libelle="Date" type="date" formulaire="beneficiaires" requis />
                                            <x-portail.champ nom="nature" libelle="Fiabilité" :options="$natures" formulaire="beneficiaires" requis />
                                            @break
                                        @case('indicateurs')
                                            <x-portail.champ nom="libelle" libelle="Libellé de l’indicateur" max="160" formulaire="indicateurs" requis />
                                            <x-portail.champ nom="unite" libelle="Unité de mesure" max="40" formulaire="indicateurs" requis />
                                            <x-portail.champ nom="valeur_reference" libelle="Valeur de référence (point de départ)" type="number" formulaire="indicateurs" />
                                            <x-portail.champ nom="cible" libelle="Objectif cible" type="number" formulaire="indicateurs" requis />
                                            <x-portail.champ nom="methode_mesure" libelle="Méthode de mesure" max="500" formulaire="indicateurs" requis class="sm:col-span-2" />
                                            @break
                                        @case('pieces')
                                            <x-portail.champ nom="type_piece" libelle="Type de pièce" :options="P::TYPES_PIECE" formulaire="pieces" requis />
                                            <x-portail.champ nom="diffusion" libelle="Diffusion" :options="['interne' => 'Interne', 'sur_demande' => 'Sur demande', 'public' => 'Public']" formulaire="pieces" requis />
                                            @break
                                    @endswitch
                                </div>
                                @if ($relation !== 'indicateurs')<x-admin.source :formulaire="$relation" />@endif
                                <button type="submit" class="bouton bouton-principal text-xs py-2">Ajouter cet élément</button>
                            </form>
                        </details>
                    @endif
                </section>
            @endforeach
        </div>

        <aside class="space-y-6">
            {{-- Transitions de statut du projet --}}
            <section class="panneau space-y-4" aria-labelledby="titre-transitions">
                <div class="border-b border-slate-100 pb-2">
                    <h2 id="titre-transitions" class="text-base font-bold text-slate-900 tracking-tight">Statut opérationnel</h2>
                    <p class="text-xs text-slate-500">Avancement des étapes du cycle de vie du projet.</p>
                </div>
                
                @if ($enAttente)
                    <div class="rounded-xl border-2 border-amber-300 bg-amber-50/70 p-4 text-xs">
                        <p class="font-bold text-amber-950 flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                            Transition en attente : {{ $enAttente->de_statut->libelle() }} → {{ $enAttente->vers_statut->libelle() }}
                        </p>
                        <p class="text-slate-600 mt-1">Demandée par {{ $enAttente->demandePar->nom_affiche }} le {{ $format->date($enAttente->demande_le) }}@if ($enAttente->motif) · « {{ $enAttente->motif }} »@endif</p>
                        @if ($agent->can('projet.valider_transition', $enAttente))
                            <form method="post" action="{{ route('admin.projets.transition.approuver', $enAttente) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 w-full font-bold text-xs py-2 shadow-sm">
                                    Approuver la transition
                                </button>
                            </form>
                            <form method="post" action="{{ route('admin.projets.transition.refuser', $enAttente) }}" class="mt-2 space-y-2">
                                @csrf
                                <input type="hidden" name="_formulaire" value="refus">
                                <x-portail.champ nom="motif_refus" libelle="Motif du refus" type="textarea" max="2000" formulaire="refus" requis />
                                <button type="submit" class="bouton bouton-secondaire w-full text-xs py-1.5">Refuser la transition</button>
                            </form>
                        @endif
                    </div>
                @elseif ($agent->can('projet.demander_transition', $projet) && $suivants->isNotEmpty())
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Prochaines étapes possibles</h3>
                    <ul class="space-y-2.5 text-xs">
                        @foreach ($suivants as $s)
                            <li class="rounded-xl border border-slate-200 bg-slate-50/50 p-3">
                                <p class="font-bold text-slate-900">{{ $s['vers']->libelle() }}</p>
                                @if ($s['manque'])
                                    <p class="mt-1 font-semibold text-rose-700">Conditions à remplir :</p>
                                    <ul class="list-disc pl-4 text-slate-600 space-y-0.5 mt-0.5">@foreach ($s['manque'] as $m)<li>{{ $m }}</li>@endforeach</ul>
                                @else
                                    <p class="mt-1 flex items-center gap-1 font-bold text-emerald-700"><x-icone nom="verifie" class="icone size-3.5" /> Conditions satisfaites</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <form method="post" action="{{ route('admin.projets.transition', $projet) }}" class="mt-3 space-y-3" novalidate>
                        @csrf
                        <input type="hidden" name="_formulaire" value="transition">
                        <x-portail.champ nom="vers" libelle="Demander le passage à" :options="$suivants->mapWithKeys(fn ($s) => [$s['vers']->value => $s['vers']->libelle()])->all()" formulaire="transition" requis />
                        <x-portail.champ nom="motif" libelle="Motif de la transition" type="textarea" max="2000" formulaire="transition" aide="Obligatoire pour une suspension, reprise ou révision." />
                        <x-portail.champ nom="piece_id" libelle="Pièce jointe (facultative)" :options="$piecesOptions" formulaire="transition" vide-libelle="Aucune" />
                        <button type="submit" class="bouton bouton-principal w-full text-xs py-2 shadow-sm">Valider la transition</button>
                    </form>
                @endif

                @if ($projet->transitions->isNotEmpty())
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 pt-2 border-t border-slate-100">Historique des étapes</h3>
                    <ol class="space-y-2 text-xs">
                        @foreach ($projet->transitions->reverse() as $t)
                            <li class="border-l-2 border-slate-300 pl-2.5 py-0.5">
                                <span class="font-bold text-slate-800">{{ $t->de_statut->libelle() }} → {{ $t->vers_statut->libelle() }}</span>
                                <span class="block text-slate-500 text-[0.7rem]">{{ $t->decision->libelle() }} par {{ $t->validePar?->nom_affiche ?? 'Administrateur' }} le {{ $format->date($t->demande_le, 'j M Y') }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Photos associées au projet --}}
            <section class="panneau space-y-4" aria-labelledby="titre-photos">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h2 id="titre-photos" class="text-base font-bold text-slate-900 tracking-tight">Photos du chantier</h2>
                    <span class="text-xs text-slate-500 font-semibold">{{ $projet->medias->count() }} photo(s)</span>
                </div>
                @forelse ($projet->medias as $photo)
                    <div class="flex items-center justify-between gap-3 text-xs rounded-xl border border-slate-200 p-2.5 bg-white">
                        <div class="flex items-center gap-2.5 min-w-0">
                            @if ($photo->fichier)
                                <img src="{{ route('admin.fichiers.show', $photo->fichier) }}" alt="" class="size-10 rounded-lg object-cover border border-slate-200 shrink-0" loading="lazy">
                            @endif
                            <div class="min-w-0">
                                <a href="{{ route('admin.medias.modifier', $photo) }}" class="font-bold text-slate-800 hover:text-lac truncate block">{{ $photo->titre }}</a>
                                <p class="text-[0.7rem] text-slate-500">{{ $photo->droit->libelle() }}</p>
                            </div>
                        </div>
                        <span class="flex items-center gap-2 shrink-0">
                            @can('projet.modifier', $projet)
                                <form method="post" action="{{ route('admin.projets.photo.retirer', [$projet, $photo]) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-bold text-rose-600 hover:underline">Retirer</button>
                                </form>
                            @endcan
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-slate-500 italic">Aucune photo liée. Ajoutez une photo issue de la médiathèque.</p>
                @endforelse
                @can('projet.modifier', $projet)
                    <form method="post" action="{{ route('admin.projets.photo', $projet) }}" class="space-y-3 border-t border-slate-100 pt-3">
                        @csrf
                        <input type="hidden" name="_formulaire" value="photos">
                        <x-portail.champ nom="media_id" libelle="Sélectionner une photo de la médiathèque" :options="$mediatheque" formulaire="photos" requis />
                        <button type="submit" class="bouton bouton-secondaire w-full text-xs py-1.5 font-bold">+ Associer la photo</button>
                    </form>
                @endcan
            </section>

            {{-- Circuit éditorial & publication directe --}}
            <x-admin.circuit :objet="$projet" section="projets" :validation="$validation" :publication="$publication" :lien-public="$lienPublic" />

            {{-- Visibilité au catalogue public --}}
            @can('projet.retirer_catalogue', $projet)
                <section class="panneau space-y-3" aria-labelledby="titre-catalogue">
                    <h2 id="titre-catalogue" class="text-base font-bold text-slate-900 tracking-tight">Catalogue des projets</h2>
                    <p class="text-xs text-slate-500">Contrôlez la visibilité publique de la fiche dans le catalogue officiel.</p>
                    <form method="post" action="{{ route('admin.projets.catalogue', $projet) }}" class="space-y-3 pt-1">
                        @csrf
                        <input type="hidden" name="_formulaire" value="catalogue">
                        @if ($projet->visible_catalogue)
                            <x-portail.champ nom="motif_retrait" libelle="Motif du retrait" max="500" formulaire="catalogue" requis aide="Ex : Données incomplètes, mise en révision…" />
                            <button type="submit" class="bouton bg-amber-500 text-white hover:bg-amber-600 w-full text-xs font-bold py-2 shadow-2xs">Retirer du catalogue public</button>
                        @else
                            <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 w-full text-xs font-bold py-2 shadow-2xs">Rétablir dans le catalogue</button>
                        @endif
                    </form>
                </section>
            @endcan
        </aside>
    </div>
</x-admin.gabarit>

{{-- Fiche commune des objets publiables du portail (MKY-P3-08) : champs décrits par le contrôleur, circuit éditorial à droite --}}
@php($existe = $objet->exists)
<x-admin.gabarit :titre="$titre" :chapeau="$existe ? null : 'Rédigez la fiche et publiez-la directement sur le portail, ou enregistrez-la en brouillon.'">
    <p class="-mt-4 mb-6 text-sm"><a href="{{ route("admin.$section.index") }}">← {{ $textes['liste'] }}</a></p>

    @if ($validation && $validation->decision->value === 'en_attente')
        <div class="mb-6 rounded-xl border-2 border-amber-300 bg-amber-50 p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-200 text-amber-800">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <p class="font-bold text-amber-950">En attente de validation éditoriale par le Superadmin ou le Secrétariat Général</p>
                    <p class="text-xs text-amber-800 mt-0.5">Soumis par {{ $validation->soumisPar?->nom_affiche ?? 'un rédacteur' }} le {{ $format->date($validation->cree_le, 'j F Y à H\hi') }}.</p>
                </div>
            </div>
            @can('contenu.publier')
                <form method="post" action="{{ route('admin.validation.approuver-publier', $validation) }}" class="inline">
                    @csrf
                    <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 font-semibold shadow-sm flex items-center gap-1.5 text-xs py-2 px-3">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Approuver et publier en direct
                    </button>
                </form>
            @endcan
        </div>
    @endif
    @if ($validation && $validation->decision->value === 'renvoye')
        <p class="mb-6 rounded-xl border-2 border-amber-300 bg-white p-4 text-sm"><strong>Renvoyé par {{ $validation->decidePar?->nom_affiche }} :</strong> {{ $validation->motif }}</p>
    @endif

    <div class="grid gap-8 xl:grid-cols-[1fr_22rem]">
        <form method="post" action="{{ $existe ? route("admin.$section.mettre-a-jour", $objet) : route("admin.$section.enregistrer") }}"
              class="panneau" novalidate @if ($formulaireMultipart) enctype="multipart/form-data" @endif>
            @csrf
            @if ($existe) @method('PUT') @endif
            <fieldset class="grid gap-5 sm:grid-cols-2" @disabled(! $modifiable)>
                <legend class="sr-only">{{ $titre }}</legend>
                @foreach ($champs as $c)
                    @switch($c['type'])
                        @case('intertitre')
                            <h2 class="text-lg sm:col-span-2">{{ $c['libelle'] }}</h2>
                            @break
                        @case('source')
                            <fieldset class="rounded-xl border border-argile p-4 sm:col-span-2">
                                <legend class="px-1 text-sm font-bold">{{ $c['legende'] }}</legend>
                                @if ($c['actuelle'])
                                    <p class="mb-3 text-sm">Source actuelle : <strong>{{ $c['actuelle']->intitule }}</strong>
                                        ({{ \App\Http\Controllers\Admin\ProjetsController::TYPES_SOURCE[$c['actuelle']->type_source] ?? $c['actuelle']->type_source }},
                                        {{ $format->date($c['actuelle']->date_source) }}@if ($c['actuelle']->reference), réf. {{ $c['actuelle']->reference }}@endif).
                                        <span class="text-ardoise">Remplissez les champs ci-dessous seulement pour la remplacer.</span></p>
                                @endif
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-portail.champ :nom="$c['prefixe'].'_type'" libelle="Type de source" :options="\App\Http\Controllers\Admin\ProjetsController::TYPES_SOURCE" :requis="$c['requise']" />
                                    <x-portail.champ :nom="$c['prefixe'].'_date'" libelle="Date de la source" type="date" :requis="$c['requise']" />
                                    <x-portail.champ :nom="$c['prefixe'].'_intitule'" libelle="Intitulé (titre de la pièce)" max="300" :requis="$c['requise']" class="sm:col-span-2" />
                                    <x-portail.champ :nom="$c['prefixe'].'_reference'" libelle="Référence (numéro, page)" max="160" class="sm:col-span-2" />
                                </div>
                            </fieldset>
                            @break
                        @case('case')
                            <div class="sm:col-span-2">
                                <div class="flex gap-3">
                                    <input type="hidden" name="{{ $c['nom'] }}" value="0">
                                    <input id="champ-{{ $c['nom'] }}" name="{{ $c['nom'] }}" type="checkbox" value="1" class="case" @checked(old($c['nom'], $c['valeur']))
                                           @if (! empty($c['aide'])) aria-describedby="champ-{{ $c['nom'] }}-aide" @endif>
                                    <label for="champ-{{ $c['nom'] }}" class="font-semibold">{{ $c['libelle'] }}</label>
                                </div>
                                @if (! empty($c['aide']))<span id="champ-{{ $c['nom'] }}-aide" class="aide">{{ $c['aide'] }}</span>@endif
                                @error($c['nom'])<p class="erreur"><x-icone nom="alerte" class="icone size-5 flex-none" />{{ $message }}</p>@enderror
                            </div>
                            @break
                        @case('fichier')
                            <div class="sm:col-span-2">
                                <label for="champ-{{ $c['nom'] }}" class="etiquette">{{ $c['libelle'] }} @if (! empty($c['requis']))<span class="font-normal text-ardoise">(obligatoire)</span>@endif</label>
                                @if (! empty($c['aide']))<span id="champ-{{ $c['nom'] }}-aide" class="aide">{{ $c['aide'] }}</span>@endif
                                <input id="champ-{{ $c['nom'] }}" name="{{ $c['nom'] }}" type="file" class="champ" accept="{{ $c['accept'] }}" @required(! empty($c['requis']))
                                       @if (! empty($c['aide'])) aria-describedby="champ-{{ $c['nom'] }}-aide" @endif>
                                @error($c['nom'])<p class="erreur"><x-icone nom="alerte" class="icone size-5 flex-none" />{{ $message }}</p>@enderror
                            </div>
                            @break
                        @default
                            <x-portail.champ :nom="$c['nom']" :libelle="$c['libelle']" :type="$c['type']" :requis="$c['requis'] ?? false" :max="$c['max'] ?? null"
                                :aide="$c['aide'] ?? null" :options="$c['options'] ?? null" :valeur="$c['valeur'] ?? null" :vide-libelle="$c['vide'] ?? 'Choisir…'"
                                @class(['sm:col-span-2' => ($c['pleine'] ?? false) || $c['type'] === 'textarea']) />
                    @endswitch
                @endforeach
                <div class="sm:col-span-2">
                    @if ($modifiable)
                        @if (! $existe)
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="submit" name="action" value="publier" class="bouton bouton-publier font-semibold shadow-sm flex items-center gap-1.5">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Publier immédiatement
                                </button>
                                <button type="submit" name="action" value="soumettre" class="bouton bouton-soumettre font-semibold shadow-sm flex items-center gap-1.5">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Soumettre à validation (Superadmin / SG)
                                </button>
                                <button type="submit" name="action" value="brouillon" class="bouton bouton-secondaire">Enregistrer en brouillon</button>
                            </div>
                        @else
                            @php($estPublie = ($objet->etat?->value ?? $objet->etat) === 'publie' || $publication !== null)
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($estPublie)
                                    <button type="submit" name="action" value="publier" class="bouton bouton-publier font-semibold shadow-sm flex items-center gap-1.5">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Mettre à jour et publier en direct
                                    </button>
                                    <button type="submit" name="action" value="sauvegarder" class="bouton bouton-secondaire">Enregistrer les modifications</button>
                                @else
                                    <button type="submit" name="action" value="publier" class="bouton bouton-publier font-semibold shadow-sm flex items-center gap-1.5">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Mettre à jour et publier en direct
                                    </button>
                                    <button type="submit" name="action" value="soumettre" class="bouton bouton-soumettre font-semibold shadow-sm flex items-center gap-1.5">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Soumettre à validation (Superadmin / SG)
                                    </button>
                                    <button type="submit" name="action" value="brouillon" class="bouton bouton-secondaire">Enregistrer le brouillon</button>
                                @endif
                            </div>
                        @endif
                    @else
                        <p class="text-ardoise">Vous pouvez consulter cette fiche, mais pas la modifier.</p>
                    @endif
                </div>

            </fieldset>
        </form>

        @if ($existe)
            <aside class="space-y-4">
                <x-admin.circuit :objet="$objet" :section="$section" :validation="$validation" :publication="$publication" :lien-public="$lienPublic" />
                @foreach ($panneaux as $vue => $donnees)
                    @include($vue, $donnees)
                @endforeach

                @can('contenu.retirer', $objet)
                    <div class="panneau border border-rose-200 bg-rose-50/50">
                        <h2 class="text-lg font-bold text-rose-950">Zone de danger</h2>
                        <p class="mt-2 text-sm text-rose-800">Supprimer définitivement cet élément et son historique.</p>
                        <form method="post" action="{{ route("admin.$section.detruire", $objet) }}" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cet élément ? Cette action est irréversible.');" class="mt-4">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bouton bg-rose-600 text-white hover:bg-rose-700 w-full">Supprimer définitivement</button>
                        </form>
                    </div>
                @endcan
            </aside>
        @endif
    </div>
</x-admin.gabarit>

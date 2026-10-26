@use('App\Http\Controllers\Admin\ContenusController', 'C')
@php($existe = $contenu->exists)
@php($modifiable = $existe ? auth()->user()->can('contenu.modifier', $contenu) : auth()->user()->can('contenu.creer'))
@php($estPublie = $existe && (($contenu->etat?->value ?? $contenu->etat) === 'publie' || $publication !== null))
<x-admin.gabarit :titre="$existe ? $contenu->titre : 'Nouveau contenu'" :chapeau="$existe ? null : 'Rédigez votre contenu et publiez-le directement sur le portail citoyen, ou enregistrez-le en brouillon.'">
    @if ($existe)
        <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
            <x-admin.etat :etat="$contenu->etat" />
            <span class="text-ardoise">Auteur : {{ $contenu->auteur?->nom_affiche }} · modifié le {{ $format->date($contenu->modifie_le, 'j F Y à H\hi') }}</span>
            @if ($publication)
                <a href="{{ $lienPublic ?? \App\Portail\Liens::contenu(\App\Portail\Fiche::depuis($publication)) }}" target="_blank" class="font-semibold text-emerald-700 underline flex items-center gap-1">
                    <span>Version {{ $publication->version }} en ligne</span>
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            @endif
        </div>
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
            <p class="mb-6 rounded-carte border-2 border-soleil bg-white p-4"><strong>Renvoyé par {{ $validation->decidePar?->nom_affiche }} :</strong> {{ $validation->motif }}</p>
        @endif
    @endif

    <div class="grid gap-8 xl:grid-cols-[1fr_20rem]">
        <form method="post" action="{{ $existe ? route('admin.contenus.mettre-a-jour', $contenu) : route('admin.contenus.enregistrer') }}" class="panneau space-y-5" novalidate>
            @csrf
            @if ($existe) @method('PUT') @endif
            <fieldset class="space-y-5" @disabled(! $modifiable)>
                <legend class="sr-only">Contenu</legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-portail.champ nom="type_contenu" libelle="Type" :options="C::TYPES" :valeur="$contenu->type_contenu" requis />
                    <x-portail.champ nom="module" libelle="Module" :options="C::MODULES" :valeur="$contenu->module" requis />
                </div>
                <x-portail.champ nom="titre" libelle="Titre" max="160" :valeur="$contenu->titre" requis />
                <x-portail.champ nom="slug" libelle="Adresse de la page" max="120" :valeur="$contenu->slug" requis aide="Minuscules sans accents, chiffres et tirets : elle ne change plus après publication." />
                <x-portail.champ nom="resume" libelle="Résumé" type="textarea" max="500" :valeur="$contenu->resume" aide="500 caractères au plus ; il apparaît dans les listes." />
                <x-portail.champ nom="corps" libelle="Texte" type="textarea" :valeur="$contenu->corps" aide="Mise en forme simple (Markdown) : ## intertitre, - liste, **gras**. Le HTML n’est pas interprété." />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-portail.champ nom="localite_id" libelle="Localité" :options="$localites" :valeur="$contenu->localite_id" vide-libelle="Aucune" />
                    <x-portail.champ nom="publication_programmee" libelle="Publication programmée" type="datetime-local" :valeur="$contenu->publication_programmee?->format('Y-m-d\TH:i')" />
                </div>
                <div>
                    <x-portail.champ nom="image_id" libelle="Image (médiathèque)" :options="$medias" :valeur="$contenu->image_id" vide-libelle="Aucune"
                        aide="Téléversez d’abord la photo dans « Médias », avec son auteur et son droit d’usage." />
                    @if ($contenu->image && $contenu->image->fichier)
                        <div class="mt-3 flex items-center gap-4 rounded-xl border border-argile bg-argile/20 p-3">
                            <img src="{{ route('admin.fichiers.show', $contenu->image->fichier) }}" alt="{{ $contenu->image->texte_alternatif }}" class="h-16 w-24 rounded-lg object-cover border border-argile shrink-0">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold truncate">{{ $contenu->image->titre }}</p>
                                <p class="text-xs text-ardoise mt-0.5">{{ $contenu->image->droit->libelle() }} · Auteur : {{ $contenu->image->auteur }}</p>
                                <a href="{{ route('admin.medias.modifier', $contenu->image) }}" class="text-xs text-terre-fonce font-semibold underline mt-1 inline-block">Voir la fiche média</a>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Champs propres au type : pour un contenu existant, seul le bloc de son type est affiché --}}
                @php($a = $contenu->attributs ?? [])
                @php($bloc = fn (string $type) => ! $existe || $contenu->type_contenu === $type)
                @php($date = fn (?string $valeur) => $valeur ? \Illuminate\Support\Carbon::parse($valeur)->format('Y-m-d\TH:i') : null)
                @if ($existe)
                    <p class="aide">Après un changement de type, enregistrez pour afficher les champs propres au nouveau type.</p>
                @endif
                @if ($bloc('evenement'))
                    <fieldset class="rounded-xl border border-argile p-4">
                        <legend class="px-1 text-sm font-bold">Événement</legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-portail.champ nom="date_debut" libelle="Début (date et heure)" type="datetime-local" :valeur="$date($a['date_debut'] ?? null)" />
                            <x-portail.champ nom="date_fin" libelle="Fin (facultatif)" type="datetime-local" :valeur="$date($a['date_fin'] ?? null)" />
                            <x-portail.champ nom="lieu" libelle="Lieu" max="160" :valeur="$a['lieu'] ?? null" class="sm:col-span-2" />
                        </div>
                    </fieldset>
                @endif
                @if ($bloc('actualite'))
                    <fieldset class="rounded-xl border border-argile p-4">
                        <legend class="px-1 text-sm font-bold">Actualité : alerte en tête de la page d’accueil (facultatif)</legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-portail.champ nom="alerte_niveau" libelle="Niveau d’alerte" :options="C::NIVEAUX_ALERTE" :valeur="$a['alerte']['niveau'] ?? null" vide-libelle="Aucune alerte" />
                            <x-portail.champ nom="alerte_jusqu_au" libelle="Affichée jusqu’au" type="date" :valeur="$a['alerte']['jusqu_au'] ?? null" />
                        </div>
                    </fieldset>
                @endif
                @if ($bloc('demarche'))
                    <fieldset class="rounded-xl border border-argile p-4">
                        <legend class="px-1 text-sm font-bold">Démarche</legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-portail.champ nom="pieces_a_fournir" libelle="Pièces à fournir" type="textarea" max="3000" :valeur="implode(PHP_EOL, $a['pieces_a_fournir'] ?? [])" aide="Une pièce par ligne." class="sm:col-span-2" />
                            <x-portail.champ nom="service" libelle="Service compétent" max="160" :valeur="$a['service'] ?? null" />
                            <x-portail.champ nom="delai" libelle="Délai habituel" max="160" :valeur="$a['delai'] ?? null" />
                            <x-portail.champ nom="cout" libelle="Coût (et texte qui le fixe)" max="160" :valeur="$a['cout'] ?? null" aide="Par exemple : 1 000 FCFA (délibération n° … du …) ; ou « gratuit »." class="sm:col-span-2" />
                        </div>
                    </fieldset>
                @endif
                @if ($bloc('service'))
                    <fieldset class="rounded-xl border border-argile p-4">
                        <legend class="px-1 text-sm font-bold">Service municipal</legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-portail.champ nom="missions" libelle="Missions" type="textarea" max="3000" :valeur="implode(PHP_EOL, $a['missions'] ?? [])" aide="Une mission par ligne." class="sm:col-span-2" />
                            <x-portail.champ nom="horaires" libelle="Horaires" max="300" :valeur="$a['horaires'] ?? null" />
                            <x-portail.champ nom="contact_generique" libelle="Contact générique du service" max="300" :valeur="$a['contact_generique'] ?? null" aide="Jamais un numéro personnel." />
                        </div>
                    </fieldset>
                @endif
                @if ($bloc('site_patrimoine'))
                    <fieldset class="rounded-xl border border-argile p-4">
                        <legend class="px-1 text-sm font-bold">Site du patrimoine</legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-portail.champ nom="acces" libelle="Accès" type="textarea" max="1000" :valeur="$a['acces'] ?? null" class="sm:col-span-2" />
                            <x-portail.champ nom="periode_conseillee" libelle="Période conseillée" max="300" :valeur="$a['periode_conseillee'] ?? null" />
                            <x-portail.champ nom="informations_pratiques" libelle="Informations pratiques" type="textarea" max="2000" :valeur="$a['informations_pratiques'] ?? null" class="sm:col-span-2" />
                        </div>
                    </fieldset>
                @endif
                @if ($bloc('opportunite'))
                    <fieldset class="rounded-xl border border-argile p-4">
                        <legend class="px-1 text-sm font-bold">Opportunité d’investissement</legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-portail.champ nom="secteur" libelle="Secteur" :options="$secteurs" :valeur="$a['secteur'] ?? null" vide-libelle="Tous secteurs" />
                            <x-portail.champ nom="interlocuteur" libelle="Interlocuteur (service, fonction)" max="300" :valeur="$a['interlocuteur'] ?? null" />
                            <x-portail.champ nom="conditions" libelle="Conditions (foncier, autorisations, contreparties)" type="textarea" max="2000" :valeur="$a['conditions'] ?? null" class="sm:col-span-2" />
                        </div>
                    </fieldset>
                @endif

                @if ($modifiable)
                    @if (! $existe)
                        <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-argile">
                            <button type="submit" name="action" value="publier" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 font-semibold shadow-sm flex items-center gap-2">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Publier immédiatement
                            </button>
                            <button type="submit" name="action" value="soumettre" class="bouton bg-sky-700 text-white hover:bg-sky-800 font-semibold shadow-sm flex items-center gap-2">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Soumettre à validation (Superadmin / SG)
                            </button>
                            <button type="submit" name="action" value="brouillon" class="bouton bouton-secondaire">
                                Enregistrer en brouillon
                            </button>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-argile">
                            @if ($estPublie)
                                <button type="submit" name="action" value="publier" class="bouton bouton-publier font-semibold shadow-sm flex items-center gap-2">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Mettre à jour et publier en direct
                                </button>
                                <button type="submit" name="action" value="sauvegarder" class="bouton bouton-secondaire">
                                    Enregistrer sans republier
                                </button>
                            @else
                                <button type="submit" name="action" value="publier" class="bouton bouton-publier font-semibold shadow-sm flex items-center gap-2">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Mettre à jour et publier en direct
                                </button>
                                <button type="submit" name="action" value="soumettre" class="bouton bouton-soumettre font-semibold shadow-sm flex items-center gap-2">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Soumettre à validation (Superadmin / SG)
                                </button>
                                <button type="submit" name="action" value="brouillon" class="bouton bouton-secondaire">
                                    Enregistrer le brouillon
                                </button>
                            @endif
                        </div>
                    @endif
                @else
                    <p class="text-ardoise">Vous pouvez consulter ce contenu, mais pas le modifier.</p>
                @endif
            </fieldset>
        </form>

        @if ($existe)
            <aside class="space-y-4">
                {{-- Carte d'état et actions rapides de publication --}}
                <div class="panneau {{ $estPublie ? 'bg-emerald-50/50 border border-emerald-200' : 'bg-slate-50 border border-slate-200' }}">
                    <h2 class="text-base font-bold {{ $estPublie ? 'text-emerald-950' : 'text-slate-800' }}">État de publication</h2>
                    <div class="mt-2 text-sm">
                        @if ($estPublie && $publication)
                            <div class="flex items-center gap-2 text-emerald-800 font-semibold mb-2">
                                <span class="size-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>En ligne (version {{ $publication->version }})</span>
                            </div>
                            <p class="text-xs text-emerald-700 mb-3">Publié le {{ $format->date($publication->publie_le, 'j F Y à H\hi') }}.</p>
                            @if ($lienPublic)
                                <a href="{{ $lienPublic }}" target="_blank" class="bouton bouton-secondaire text-xs w-full flex items-center justify-center gap-1.5 mb-3 bg-white">
                                    <span>Voir sur le portail citoyen</span>
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            @endif
                        @else
                            @if ($validation && $validation->decision->value === 'en_attente')
                                <div class="flex items-center gap-2 text-amber-700 font-semibold mb-2">
                                    <span class="size-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>En attente de validation (Superadmin / SG)</span>
                                </div>
                                <p class="text-xs text-amber-800 mb-3">Soumis par {{ $validation->soumisPar?->nom_affiche ?? 'un rédacteur' }} le {{ $format->date($validation->cree_le, 'j F Y à H\hi') }}.</p>
                                @can('contenu.publier', $contenu)
                                    <form method="post" action="{{ route('admin.validation.approuver-publier', $validation) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 text-xs w-full flex items-center justify-center gap-1.5 font-bold py-2 shadow-sm">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Approuver et publier en direct
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.validation.show', $validation) }}" class="bouton bouton-secondaire text-xs w-full flex items-center justify-center gap-1.5 bg-white">
                                        Voir dans le centre de validation
                                    </a>
                                @endcan
                            @else
                                <div class="flex items-center gap-2 text-slate-700 font-semibold mb-2">
                                    <span class="size-2.5 rounded-full bg-slate-400"></span>
                                    <span>Brouillon (non visible des citoyens)</span>
                                </div>
                                <p class="text-xs text-slate-600 mb-3">Cet article n'est actuellement pas visible sur le portail public.</p>
                                @can('contenu.publier', $contenu)
                                    <form method="post" action="{{ route('admin.contenus.publier', $contenu) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="bouton bg-emerald-600 text-white hover:bg-emerald-700 text-xs w-full flex items-center justify-center gap-1.5 font-bold py-2 shadow-sm">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Publier sur le portail
                                        </button>
                                    </form>
                                @endcan
                                @can('contenu.soumettre', $contenu)
                                    <form method="post" action="{{ route('admin.contenus.soumettre', $contenu) }}">
                                        @csrf
                                        <button type="submit" class="bouton bg-sky-700 text-white hover:bg-sky-800 text-xs w-full flex items-center justify-center gap-1.5 font-semibold py-2">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Soumettre à validation (Superadmin / SG)
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        @endif
                    </div>
                </div>

                @can('contenu.retirer', $contenu)
                    <div class="panneau border border-rose-200 bg-rose-50/50">
                        <h2 class="text-base font-bold text-rose-950">Zone de danger</h2>
                        <p class="mt-1 text-xs text-rose-800">Supprimer définitivement cet article et son historique.</p>
                        <form method="post" action="{{ route('admin.contenus.detruire', $contenu) }}" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cet article ? Cette action est irréversible.');" class="mt-3">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bouton bg-rose-600 text-white hover:bg-rose-700 text-xs w-full py-2 font-semibold">Supprimer l’article</button>
                        </form>
                    </div>
                @endcan
            </aside>
        @endif
    </div>
</x-admin.gabarit>


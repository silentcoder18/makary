{{-- Coordonnées officielles de la mairie (C-003, C-110) --}}
@use('App\Http\Controllers\Admin\CoordonneesController', 'C')
<x-admin.gabarit titre="Coordonnées officielles" chapeau="Adresse, standard téléphonique, horaires et contacts des services municipaux affichés sur le portail citoyen.">
    @if ($parametre)
        <p class="-mt-4 mb-6 text-xs text-slate-500">Dernière mise à jour le {{ $format->date($parametre->modifie_le, 'j F Y à H\hi') }}.</p>
    @endif

    <div class="max-w-4xl">
        <form method="post" action="{{ route('admin.coordonnees.enregistrer') }}" class="panneau space-y-8" novalidate>
            @csrf
            @method('PUT')
            
            <fieldset class="space-y-4">
                <div class="border-b border-slate-100 pb-2">
                    <h2 class="text-base font-bold text-slate-900 tracking-tight">Hôtel de Ville &amp; Standard</h2>
                    <p class="text-xs text-slate-500">Coordonnées principales de la mairie de Makary.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-portail.champ nom="adresse" libelle="Adresse (quartier, rue, repère)" max="300" :valeur="$valeur['adresse'] ?? null" class="sm:col-span-2" requis />
                    <x-portail.champ nom="boite_postale" libelle="Boîte postale (B.P.)" max="60" :valeur="$valeur['boite_postale'] ?? null" />
                    <x-portail.champ nom="telephone" libelle="Téléphone du standard" type="tel" max="40" :valeur="$valeur['telephone'] ?? null" requis />
                    <x-portail.champ nom="courriel" libelle="Courriel officiel" type="email" max="254" :valeur="$valeur['courriel'] ?? null" requis />
                    <x-portail.champ nom="horaires" libelle="Horaires d’ouverture au public" max="300" :valeur="$valeur['horaires'] ?? null" aide="Exemple : Du lundi au vendredi, de 7 h 30 à 15 h 30." class="sm:col-span-2" />
                </div>
            </fieldset>

            <fieldset class="space-y-4 pt-4 border-t border-slate-100">
                <div class="border-b border-slate-100 pb-2">
                    <h2 class="text-base font-bold text-slate-900 tracking-tight">Contacts directs des services</h2>
                    <p class="text-xs text-slate-500">Adresses et numéros génériques professionnels par département communal.</p>
                </div>
                <ol class="space-y-3">
                    @for ($i = 0; $i < C::NOMBRE_SERVICES; $i++)
                        @php($service = $valeur['services'][$i] ?? [])
                        <li class="grid gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 sm:grid-cols-[1.2fr_1fr_1.3fr] items-end">
                            <div>
                                <label class="etiquette font-bold text-slate-800" for="service-{{ $i }}-nom">Intitulé du service {{ $i + 1 }}</label>
                                <input id="service-{{ $i }}-nom" class="champ" name="services[{{ $i }}][service]" maxlength="120" placeholder="Ex : État civil, Voirie…" value="{{ old("services.$i.service", $service['service'] ?? '') }}">
                            </div>
                            <div>
                                <label class="etiquette font-bold text-slate-800" for="service-{{ $i }}-telephone">Téléphone</label>
                                <input id="service-{{ $i }}-telephone" class="champ" type="tel" name="services[{{ $i }}][telephone]" maxlength="40" placeholder="+237 …" value="{{ old("services.$i.telephone", $service['telephone'] ?? '') }}">
                            </div>
                            <div>
                                <label class="etiquette font-bold text-slate-800" for="service-{{ $i }}-courriel">Courriel générique</label>
                                <input id="service-{{ $i }}-courriel" class="champ" type="email" name="services[{{ $i }}][courriel]" maxlength="254" placeholder="service@makary.cm" value="{{ old("services.$i.courriel", $service['courriel'] ?? '') }}">
                            </div>
                        </li>
                    @endfor
                </ol>
            </fieldset>

            <div class="pt-4 border-t border-slate-100 flex justify-end">
                <button type="submit" class="bouton bouton-publier font-semibold shadow-sm flex items-center gap-2">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Mettre à jour et publier en direct
                </button>
            </div>
        </form>
    </div>
</x-admin.gabarit>

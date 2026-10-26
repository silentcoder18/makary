@use('App\Http\Controllers\Portail\DemandesController', 'D')
@php
    $liste = fn (array $valeurs) => array_combine($valeurs, $valeurs);
    $projetsOptions = $projets->mapWithKeys(fn ($p) => [$p['slug'] => $p['titre'].' ('.$p->get('statut.libelle').')'])->all();
@endphp
<x-portail.gabarit :titre="$formulaire['titre']" :description="$formulaire['chapeau']">
    <x-portail.entete-page :titre="$formulaire['titre']" :chapeau="$formulaire['chapeau']" />

    <div class="conteneur grid gap-12 py-12 lg:grid-cols-[1fr_22rem] lg:py-16">
        <form method="post" action="{{ url($formulaire['adresse']) }}" class="max-w-2xl space-y-6" novalidate>
            @csrf
            @if ($errors->any())
                <div class="rounded-carte border-2 border-terre bg-white p-5" tabindex="-1" data-erreurs aria-labelledby="titre-erreurs">
                    <h2 id="titre-erreurs" class="text-lg text-terre-fonce">Le formulaire contient {{ $errors->count() }} {{ $errors->count() > 1 ? 'erreurs' : 'erreur' }}</h2>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($errors->keys() as $champ)
                            <li><a href="#champ-{{ $champ }}">{{ $errors->first($champ) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <p class="text-ardoise">Les champs marqués « obligatoire » doivent être remplis.</p>

            @switch($cle)
                @case('contact')
                    <x-portail.champ nom="objet" libelle="Objet de votre demande" :options="$liste(D::OBJETS_CONTACT)" requis />
                    <x-portail.champ nom="nom_contact" libelle="Nom" autocomplete="name" max="120" />
                    @break
                @case('investisseur')
                    <x-portail.champ nom="organisme" libelle="Entreprise ou organisme" autocomplete="organization" max="160" requis />
                    <x-portail.champ nom="nom_contact" libelle="Nom et fonction" autocomplete="name" max="120" requis />
                    <x-portail.champ nom="pays" libelle="Pays" autocomplete="country-name" max="80" requis />
                    <x-portail.champ nom="secteur" libelle="Secteur d’intérêt" :options="$secteurs->all()" requis />
                    @break
                @case('partenaire')
                    <x-portail.champ nom="organisme" libelle="Organisme" autocomplete="organization" max="160" requis />
                    <x-portail.champ nom="type_organisme" libelle="Type d’organisme" :options="$liste(D::TYPES_ORGANISME)" requis />
                    <x-portail.champ nom="nom_contact" libelle="Nom et fonction du contact" autocomplete="name" max="120" requis />
                    <x-portail.champ nom="pays" libelle="Pays" autocomplete="country-name" max="80" requis />
                    <x-portail.champ nom="nature" libelle="Nature de l’intérêt" :options="$liste(D::NATURES_INTERET['partenaire'])" requis />
                    @break
                @case('diaspora')
                    <x-portail.champ nom="nom_contact" libelle="Nom" autocomplete="name" max="120" requis />
                    <x-portail.champ nom="pays" libelle="Pays de résidence" autocomplete="country-name" max="80" requis />
                    <x-portail.champ nom="nature" libelle="Comment souhaitez-vous contribuer ?" :options="$liste(D::NATURES_INTERET['diaspora'])" requis />
                    @break
            @endswitch

            <x-portail.champ nom="courriel" libelle="Adresse électronique" type="email" autocomplete="email" max="254" requis aide="Pour vous répondre et suivre votre demande." />
            <x-portail.champ nom="telephone" libelle="Téléphone" type="tel" autocomplete="tel" max="30" />
            @if ($cle !== 'contact')
                <x-portail.champ nom="projet" libelle="Projet concerné" :options="$projetsOptions" :valeur="request('projet')" vide-libelle="Aucun projet en particulier" />
            @endif
            <x-portail.champ nom="message" libelle="Votre message" type="textarea" max="2000" requis aide="2 000 caractères au plus." />

            {{-- Champ piège contre les envois automatisés (E-06) : invisible, à laisser vide --}}
            <div class="champ-piege" aria-hidden="true">
                <label for="champ-site_web">Ne pas remplir ce champ</label>
                <input id="champ-site_web" name="site_web" type="text" tabindex="-1" autocomplete="off">
            </div>

            {{-- Mention d'information (E-01) et consentement non précoché --}}
            <div class="rounded-carte bg-sable p-5 text-sm">
                <h2 class="text-base">Vos données personnelles</h2>
                <p class="mt-2"><strong>Responsable :</strong> {{ $mention['responsable'] }}. <strong>Finalité :</strong> {{ $mention['finalite'] }}.
                    <strong>Destinataires :</strong> {{ $mention['destinataires'] }}. <strong>Conservation :</strong> {{ $conservationMois }} mois, puis anonymisation.
                    <strong>Vos droits :</strong> accès, rectification, opposition et effacement, en écrivant à {{ $mention['contact'] }}.</p>
                <p class="mt-2 text-ardoise">Mention proposée, à valider par le référent juridique (version {{ $mention['version'] }}).</p>
                <div class="mt-4 flex gap-3">
                    <input id="champ-consentement" name="consentement" type="checkbox" value="1" class="case" @checked(old('consentement'))
                           @error('consentement') aria-invalid="true" aria-describedby="champ-consentement-erreur" @enderror>
                    <label for="champ-consentement" class="font-semibold">J’accepte que la Commune de Makary utilise ces informations pour traiter ma demande. <span class="font-normal text-ardoise">(obligatoire)</span></label>
                </div>
                @error('consentement')
                    <p id="champ-consentement-erreur" class="erreur"><x-icone nom="alerte" class="icone size-5 flex-none" />{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="bouton bouton-principal">Envoyer ma demande <x-icone nom="fleche" class="icone size-5" /></button>
        </form>

        <aside class="h-fit space-y-4 rounded-carte bg-sable p-6" aria-labelledby="titre-suite">
            <h2 id="titre-suite" class="text-xl">Et ensuite ?</h2>
            <ol class="space-y-3 text-sm">
                <li class="flex gap-3"><span class="grid size-7 flex-none place-items-center rounded-full bg-lac font-bold text-white">1</span>Vous recevez immédiatement un numéro de suivi.</li>
                <li class="flex gap-3"><span class="grid size-7 flex-none place-items-center rounded-full bg-lac font-bold text-white">2</span>Votre demande est qualifiée puis confiée à l’agent compétent.</li>
                <li class="flex gap-3"><span class="grid size-7 flex-none place-items-center rounded-full bg-lac font-bold text-white">3</span>Vous suivez son traitement avec votre numéro et votre adresse électronique.</li>
            </ol>
            <a href="{{ route('demande.suivi') }}" class="lien-fleche text-sm">Suivre une demande <x-icone nom="fleche" class="icone size-4" /></a>
        </aside>
    </div>
</x-portail.gabarit>

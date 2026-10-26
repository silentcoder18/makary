<x-portail.gabarit titre="Accueil">
    {{-- Avis et alertes (P6) : compacts, datés, niveau écrit en toutes lettres --}}
    @if ($alertes->isNotEmpty())
        <section class="border-b border-argile bg-sable" aria-labelledby="titre-alertes">
            <div class="conteneur py-3">
                <h2 id="titre-alertes" class="sr-only">Avis et alertes</h2>
                <ul class="space-y-1.5">
                    @foreach ($alertes as $alerte)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-terre px-2.5 py-0.5 font-bold text-white"><x-icone nom="alerte" class="icone size-4" />{{ $alerte->get('attributs.alerte.niveau') }}</span>
                            <a href="{{ \App\Portail\Liens::contenu($alerte) }}" class="font-semibold text-encre">{{ $alerte['titre'] }}</a>
                            <span class="text-ardoise">publié le {{ $format->date($alerte->premierePublication) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Hero : promesse, recherche, raccourcis. Aucun carrousel. --}}
    <section class="sur-sombre relative isolate overflow-hidden bg-nuit text-white" aria-labelledby="titre-accueil">
        {{-- Arrière-plan : photo hero (public/images/hero.jpg) --}}
        <img src="{{ asset('images/hero.jpg') }}"
             srcset="{{ asset('images/hero-640.webp') }} 640w, {{ asset('images/hero-1024.webp') }} 1024w, {{ asset('images/hero-1536.webp') }} 1536w, {{ asset('images/hero.jpg') }} 1536w" sizes="100vw"
             width="1536" height="1024" alt="" fetchpriority="high" decoding="async" class="absolute inset-0 -z-20 size-full object-cover object-center">
        {{-- Voile sombre sous les textes (contraste AA) : uniforme sur mobile ; sur grand écran, dense sous le texte,
             puis éclairci vers la droite pour laisser voir le bâtiment. --}}
        <div class="absolute inset-0 -z-10 bg-nuit/80 lg:bg-transparent lg:bg-[linear-gradient(90deg,rgb(26_20_57/0.92)_0%,rgb(26_20_57/0.84)_52%,rgb(26_20_57/0.3)_78%,rgb(26_20_57/0.1)_100%)]"></div>

        <div class="conteneur py-16 sm:py-20 lg:py-28">
            <div class="max-w-2xl">
                <p class="surtitre">Site officiel de la Commune de Makary</p>
                <h1 id="titre-accueil" class="mt-4 text-[clamp(2.5rem,1.6rem+3.6vw,4rem)] text-white">Makary, <span class="text-soleil">Héritière du Royaume Kotoko.</span></h1>
                <p class="mt-5 max-w-xl text-lg text-sable lg:text-xl">Les services de la mairie, les projets de la commune et les moyens d’y contribuer, au même endroit.</p>

                <form action="{{ route('recherche') }}" method="get" role="search" class="mt-8">
                    <label for="recherche-accueil" class="mb-2 block text-lg font-bold">Que cherchez-vous ?</label>
                    <div class="flex max-w-xl overflow-hidden rounded-full bg-white p-1.5 shadow-[0_18px_40px_-18px_rgb(0_0_0/0.6)] focus-within:outline focus-within:outline-3 focus-within:outline-soleil">
                        <input id="recherche-accueil" name="q" type="search" placeholder="Acte de naissance, forage, budget…" autocomplete="off"
                               class="min-w-0 flex-1 bg-transparent px-4 text-encre placeholder:text-ardoise focus:outline-none">
                        <button type="submit" class="bouton bouton-principal min-h-11 px-5"><x-icone nom="recherche" class="icone size-5" /><span class="sr-only sm:not-sr-only">Rechercher</span></button>
                    </div>
                </form>
                <p class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-sable">Souvent demandé :</span>
                    <a class="rounded-full bg-white/10 px-3 py-1 font-semibold text-white no-underline hover:bg-white/20" href="{{ route('demarches') }}">Démarches</a>
                    <a class="rounded-full bg-white/10 px-3 py-1 font-semibold text-white no-underline hover:bg-white/20" href="{{ route('projets') }}">Projets</a>
                    <a class="rounded-full bg-white/10 px-3 py-1 font-semibold text-white no-underline hover:bg-white/20" href="{{ route('documents') }}">Documents</a>
                    <a class="rounded-full bg-white/10 px-3 py-1 font-semibold text-white no-underline hover:bg-white/20" href="{{ route('demande.suivi') }}">Suivre ma demande</a>
                </p>
            </div>
        </div>
        <div class="frise" aria-hidden="true"></div>
    </section>

    {{-- En un clic (P2) --}}
    <section class="conteneur py-16 lg:py-20" aria-labelledby="titre-un-clic">
        <p class="surtitre">Services</p>
        <h2 id="titre-un-clic" class="titre-section mt-3">En un clic</h2>
        <ul class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach ([
                ['demarches', 'acte', 'Démarches', 'État civil, certificats, urbanisme'],
                ['projets', 'chantier', 'Projets', 'Statut, coût et financement'],
                ['documents', 'document', 'Documents', 'Budgets, délibérations, rapports'],
                ['evenements', 'calendrier', 'Agenda', 'Ce qui se passe à Makary'],
                ['carte', 'lieu', 'Carte', 'Localités et équipements'],
                ['contact', 'enveloppe', 'Contact', 'Écrire à la mairie'],
            ] as [$route, $icone, $libelle, $detail])
                <li class="carte group p-5">
                    <span class="grid size-12 place-items-center rounded-xl bg-sable text-lac transition group-hover:bg-lac group-hover:text-white"><x-icone nom="{{ $icone }}" /></span>
                    <a href="{{ route($route) }}" class="lien-etendu mt-4 text-lg font-extrabold">{{ $libelle }}</a>
                    <span class="mt-1 text-sm text-ardoise">{{ $detail }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Le mot du Maire (P4) --}}
    <section class="fond-motif py-16 lg:py-24" aria-labelledby="titre-maire">
        <div class="conteneur grid items-center gap-10 lg:grid-cols-[minmax(0,22rem)_1fr] lg:gap-16">
            <figure class="mx-auto w-full max-w-xs lg:max-w-none">
                <div class="aspect-[4/5] overflow-hidden rounded-t-full border-8 border-white bg-argile shadow-[var(--shadow-carte)]">
                    @if ($maire && $maire['photo'])
                        <img src="{{ \App\Portail\Liens::fichier($maire['photo']['fichier'], $maire['photo']['titre']) }}" alt="{{ $maire['photo']['texte_alternatif'] }}"
                             width="800" height="1000" loading="lazy" decoding="async" class="size-full object-cover">
                    @else
                        <div class="grid size-full place-items-center text-ardoise"><x-icone nom="personne" class="icone size-20 [stroke-width:1.2]" /></div>
                    @endif
                </div>
                @if ($maire)
                    <figcaption class="mt-4 text-center"><strong class="block">{{ $maire['nom_affiche'] }}</strong><span class="text-sm text-ardoise">{{ $maire['fonction'] }} de la Commune de Makary</span></figcaption>
                @endif
            </figure>
            <div>
                <p class="surtitre">Le mot du Maire</p>
                <h2 id="titre-maire" class="titre-section mt-3">Une commune qui rend compte</h2>
                @if ($motDuMaire)
                    <blockquote class="mt-6 border-l-4 border-soleil pl-6 text-[clamp(1.25rem,1.05rem+0.8vw,1.625rem)] font-semibold leading-snug text-encre">
                        « {{ $motDuMaire['resume'] }} »
                    </blockquote>
                @else
                    <x-portail.a-fournir quoi="Le mot du Maire" class="mt-6" />
                @endif
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('maire.vision') }}" class="bouton bouton-principal">Vision et priorités</a>
                    <a href="{{ route('maire.bilans') }}" class="bouton bouton-secondaire">Les réalisations du mandat</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Vous êtes… (P3) --}}
    <section class="bg-sable py-16 lg:py-20" aria-labelledby="titre-publics">
        <div class="conteneur">
            <p class="surtitre">Pour chacun</p>
            <h2 id="titre-publics" class="titre-section mt-3">Vous êtes…</h2>
            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['demarches', 'maison', 'Habitant', 'Vos démarches, les services et la vie de votre commune.'],
                    ['investir', 'graphique', 'Investisseur', 'Les secteurs porteurs et l’accompagnement pour vous implanter.'],
                    ['partenaires', 'poignee', 'Bailleur ou partenaire', 'Les besoins de financement, chiffrés et sourcés.'],
                    ['diaspora', 'globe', 'Membre de la diaspora', 'Contribuer par votre expertise, vos projets, vos investissements.'],
                    ['patrimoine', 'appareil', 'Visiteur', 'Le patrimoine, la culture et les informations pratiques.'],
                ] as [$route, $icone, $public, $phrase])
                    <li class="carte p-6">
                        <x-icone nom="{{ $icone }}" class="icone size-8 text-terre" />
                        <a href="{{ route($route) }}" class="lien-etendu mt-4 text-lg font-extrabold">{{ $public }}</a>
                        <p class="mt-2 text-sm text-ardoise">{{ $phrase }}</p>
                        <span class="lien-fleche mt-auto pt-4 text-sm text-lac">Accéder <x-icone nom="fleche" class="icone size-4" /></span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Projets mis en avant --}}
    <section class="conteneur py-16 lg:py-20" aria-labelledby="titre-projets">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="surtitre">Projets et réalisations</p>
                <h2 id="titre-projets" class="titre-section mt-3">Ce que la commune construit</h2>
            </div>
            <a href="{{ route('projets') }}" class="lien-fleche">Tous les projets <x-icone nom="fleche" class="icone size-5" /></a>
        </div>
        @if ($projets->isEmpty())
            <x-portail.a-fournir quoi="Les fiches des projets de la commune" class="mt-8" />
        @else
            <ul class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($projets as $projet)
                    <li><x-portail.carte-projet :projet="$projet" /></li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Makary en chiffres (P8) : seulement des chiffres sourcés --}}
    @if ($chiffres)
        <section class="sur-sombre bg-nuit py-16 text-sable lg:py-20" aria-labelledby="titre-chiffres">
            <div class="conteneur">
                <p class="surtitre">Transparence</p>
                <h2 id="titre-chiffres" class="titre-section mt-3 text-white">Makary en chiffres</h2>
                <dl class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($chiffres as $chiffre)
                        <div class="flex flex-col-reverse border-l-2 border-nuit-clair pl-5">
                            <dt class="mt-2 font-semibold text-white">{{ $chiffre['libelle'] }}</dt>
                            <dd class="chiffre">{{ $format->nombre($chiffre['valeur']) }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-8 text-sm">Source : {{ $chiffres[0]['source'] }}. Les chiffres du territoire seront publiés avec leur source officielle.</p>
            </div>
        </section>
    @endif

    {{-- Actualités et agenda (P9) --}}
    <section class="conteneur grid gap-12 py-16 lg:grid-cols-[1fr_22rem] lg:py-20">
        <div aria-labelledby="titre-actualites">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="surtitre">Vie de la commune</p>
                    <h2 id="titre-actualites" class="titre-section mt-3">Dernières actualités</h2>
                </div>
                <a href="{{ route('actualites') }}" class="lien-fleche">Toutes les actualités <x-icone nom="fleche" class="icone size-5" /></a>
            </div>
            <ul class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($actualites as $actualite)
                    <li><x-portail.carte-contenu :contenu="$actualite" /></li>
                @empty
                    <li class="text-ardoise">Aucune actualité publiée pour le moment.</li>
                @endforelse
            </ul>
        </div>
        <div aria-labelledby="titre-agenda">
            <p class="surtitre">Agenda</p>
            <h2 id="titre-agenda" class="titre-section mt-3">À venir</h2>
            <ul class="mt-8 space-y-4">
                @forelse ($evenements as $evenement)
                    <li><x-portail.carte-evenement :evenement="$evenement" /></li>
                @empty
                    <li class="text-ardoise">Aucun événement annoncé.</li>
                @endforelse
            </ul>
            <a href="{{ route('evenements') }}" class="lien-fleche mt-6">Tout l’agenda <x-icone nom="fleche" class="icone size-5" /></a>
        </div>
    </section>

    {{-- Appel aux investisseurs, aux bailleurs et à la diaspora --}}
    <section class="conteneur" aria-labelledby="titre-appel">
        <div class="sur-sombre relative overflow-hidden rounded-[1.5rem] bg-lac px-6 py-12 text-white sm:px-12 lg:py-16">
            <div class="absolute inset-x-0 bottom-0 frise frise-claire" aria-hidden="true"></div>
            <h2 id="titre-appel" class="max-w-2xl text-[clamp(1.75rem,1.3rem+1.6vw,2.5rem)] text-white">Investir, financer, contribuer : construisons Makary ensemble.</h2>
            <p class="mt-4 max-w-2xl text-lg text-sable">Chaque besoin de financement est publié avec son coût, sa source et sa date. Chaque demande reçoit un numéro de suivi.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('investir') }}" class="bouton bouton-clair">Investir à Makary</a>
                <a href="{{ route('partenaires.besoins') }}" class="bouton bouton-clair">Besoins de financement</a>
                <a href="{{ route('diaspora') }}" class="bouton bouton-clair">Espace diaspora</a>
            </div>
        </div>
    </section>
</x-portail.gabarit>

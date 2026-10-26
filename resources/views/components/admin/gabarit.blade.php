@props(['titre', 'chapeau' => null])
@php
    $agent = auth()->user();
    $route = request()->route()?->getName() ?? '';
    // Chaque entrée n'apparaît qu'aux comptes qui détiennent l'une de ses permissions (contrôlées de nouveau côté serveur).
    // Le groupe « Contenus du portail » suit les rubriques du portail public (MKY-P3-08).
    $groupes = collect([
        '' => [
            ['admin.tableau-de-bord', 'Tableau de bord', 'graphique', []],
            ['admin.rubriques', 'Rubriques du portail', 'maison', ['contenu.lire_brouillons', 'projet.lire_interne', 'partenaire.modifier', 'document.gerer', 'media.televerser', 'referentiel.modifier', 'parametre.modifier_editorial']],
            ['admin.validation.index', 'Validation', 'verifie', ['contenu.approuver']],
        ],
        'Contenus du portail' => [
            ['admin.contenus.index', 'Pages et articles', 'journal', ['contenu.lire_brouillons']],
            ['admin.elus.index', 'Élus', 'personne', ['contenu.lire_brouillons']],
            ['admin.projets.index', 'Projets', 'chantier', ['projet.lire_interne']],
            ['admin.partenaires.index', 'Partenaires', 'poignee', ['contenu.lire_brouillons', 'partenaire.modifier']],
            ['admin.documents.index', 'Documents', 'document', ['contenu.lire_brouillons', 'document.gerer']],
            ['admin.medias.index', 'Médias', 'appareil', ['contenu.lire_brouillons', 'media.televerser']],
            ['admin.localites.index', 'Localités', 'lieu', ['contenu.lire_brouillons', 'referentiel.modifier']],
            ['admin.equipements.index', 'Équipements', 'boussole', ['contenu.lire_brouillons', 'referentiel.modifier']],
            ['admin.coordonnees.modifier', 'Coordonnées', 'telephone', ['parametre.modifier_editorial']],
        ],
        'Administration & Suivi' => [
            ['admin.utilisateurs.index', 'Utilisateurs', 'personne', ['utilisateur.gerer']],
            ['admin.demandes.index', 'Demandes', 'enveloppe', ['demande.lire']],
            ['admin.journal.index', 'Journal', 'acte', ['journal.consulter']],
        ],
    ])->map(fn ($entrees) => array_values(array_filter($entrees, fn ($e) => $e[3] === [] || $agent->canAny($e[3]))))->filter();
    $actif = fn (string $nom) => str_starts_with($route, preg_replace('/\.(index|modifier)$/', '', $nom));
    $nbEnAttente = \App\Models\ValidationDemande::where('decision', \App\Domaine\Enums\DecisionValidation::EnAttente->value)->count();
    $initiales = collect(explode(' ', $agent->nom_affiche ?? 'Admin'))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $rolePrincipal = $agent->roles->first();
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $titre }} — Back-office · Commune de Makary</title>
    <link rel="icon" href="{{ asset('images/icone-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/icone-180.png') }}">
    @vite(['resources/css/portail.css', 'resources/js/portail.js'])
</head>
<body class="min-h-screen bg-[#f8f9fa] text-encre antialiased">
    <a class="lien-evitement" href="#contenu">Aller au contenu</a>
    <div class="lg:grid lg:min-h-screen lg:grid-cols-[17.5rem_1fr]">
        {{-- Barre latérale (Sidebar) --}}
        <header class="sur-sombre bg-gradient-to-b from-[#161131] via-[#1a1439] to-[#120e29] text-sable lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto lg:flex lg:flex-col lg:justify-between border-r border-white/5 shadow-xl">
            <div>
                @if (config('makary.donnees_fictives'))
                    <p class="bandeau-demo text-[0.7rem] uppercase tracking-wider py-1 font-bold bg-amber-400 text-amber-950" role="note">Environnement de Démonstration</p>
                @endif
                <x-admin.dispense-mfa />

                {{-- En-tête de marque --}}
                <div class="flex items-center justify-between gap-3 px-6 py-5 border-b border-white/10">
                    <a href="{{ route('admin.tableau-de-bord') }}" class="flex items-center gap-3.5 leading-tight text-white no-underline group">
                        <span class="rounded-xl bg-white p-1.5 shadow-md ring-1 ring-white/20 transition-transform group-hover:scale-105">
                            <x-logo :hauteur="36" class="h-9 w-auto" />
                        </span>
                        <div>
                            <span class="block text-sm font-black tracking-tight text-white">Commune de Makary</span>
                            <span class="block text-[0.65rem] font-bold uppercase tracking-[0.16em] text-soleil">Portail d’administration</span>
                        </div>
                    </a>

                    {{-- Bouton Menu Mobile --}}
                    <details class="menu-mobile lg:hidden">
                        <summary class="inline-flex size-10 cursor-pointer items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/15 transition">
                            <x-icone nom="menu" class="icone icone-menu size-5" /><x-icone nom="fermer" class="icone icone-fermer size-5" />
                        </summary>
                        <nav class="absolute inset-x-0 z-40 max-h-[85vh] overflow-y-auto bg-[#1a1439] px-6 pb-6 pt-4 border-b border-white/10 shadow-2xl" aria-label="Back-office (mobile)">
                            @foreach ($groupes as $groupe => $entrees)
                                @if ($groupe !== '')
                                    <p class="mt-4 mb-2 text-[0.7rem] font-extrabold uppercase tracking-[0.15em] text-soleil/90">{{ $groupe }}</p>
                                @endif
                                <ul class="grid gap-1">
                                    @foreach ($entrees as [$nom, $libelle, $icone])
                                        <li>
                                            <a href="{{ route($nom) }}" class="nav-item-admin {{ $actif($nom) ? 'actif' : '' }}"
                                               @if ($actif($nom)) aria-current="page" @endif>
                                                <x-icone nom="{{ $icone }}" class="icone size-5 opacity-90" />
                                                <span class="flex-1">{{ $libelle }}</span>
                                                @if ($nom === 'admin.validation.index' && $nbEnAttente > 0)
                                                    <span class="inline-flex items-center justify-center rounded-full bg-amber-500 px-2 py-0.5 text-xs font-black text-white shadow-sm animate-pulse">{{ $nbEnAttente }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endforeach
                        </nav>
                    </details>
                </div>

                {{-- Navigation Desktop --}}
                <nav class="hidden px-4 py-5 lg:block space-y-6" aria-label="Navigation principale du back-office">
                    @foreach ($groupes as $groupe => $entrees)
                        <div>
                            @if ($groupe !== '')
                                <p class="px-3 mb-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-soleil/90">{{ $groupe }}</p>
                            @endif
                            <ul class="grid gap-1">
                                @foreach ($entrees as [$nom, $libelle, $icone])
                                    <li>
                                        <a href="{{ route($nom) }}" @if ($actif($nom)) aria-current="page" @endif
                                           class="nav-item-admin {{ $actif($nom) ? 'actif' : '' }}">
                                            <x-icone nom="{{ $icone }}" class="icone size-5 shrink-0" />
                                            <span class="flex-1 truncate">{{ $libelle }}</span>
                                            @if ($nom === 'admin.validation.index' && $nbEnAttente > 0)
                                                <span class="inline-flex items-center justify-center rounded-full bg-amber-500 px-2 py-0.5 text-[0.7rem] font-black text-white shadow-sm animate-pulse">{{ $nbEnAttente }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>
            </div>

            {{-- Widget Bas de Sidebar --}}
            <div class="hidden lg:block px-4 pb-5 pt-3 border-t border-white/10 bg-black/20">
                <a href="{{ route('accueil') }}" target="_blank" class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-semibold text-sable transition no-underline group">
                    <span class="flex items-center gap-2">
                        <svg class="size-4 text-soleil group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        <span>Voir le portail citoyen</span>
                    </span>
                    <span class="text-xs text-white/50">↗</span>
                </a>
            </div>
        </header>

        {{-- Contenu Principal --}}
        <div class="min-w-0 flex flex-col min-h-screen">
            {{-- Barre de session supérieure (Header) --}}
            <header class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200/80 bg-white/95 backdrop-blur-md px-6 py-3.5 lg:px-10 shadow-xs" aria-label="Session utilisateur">
                <div class="flex flex-wrap items-center gap-3.5">
                    {{-- Avatar Utilisateur --}}
                    <div class="avatar-admin text-xs">
                        {{ $initiales ?: 'AD' }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-sm text-slate-900">{{ $agent->nom_affiche }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[0.68rem] font-bold border {{ $rolePrincipal?->code === 'superadmin' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                                {{ $rolePrincipal?->libelle ?? 'Administrateur' }}
                            </span>
                        </div>
                        <p class="text-[0.75rem] text-slate-500 font-mono">{{ $agent->courriel }}</p>
                    </div>

                    @if ($nbEnAttente > 0)
                        <a href="{{ route('admin.validation.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-300 px-3 py-1 text-xs font-bold text-amber-900 hover:bg-amber-100 transition shadow-xs">
                            <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <span>{{ $nbEnAttente }} à valider (Superadmin / SG)</span>
                        </a>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('accueil') }}" target="_blank" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 transition">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Portail citoyen
                    </a>
                    <form method="post" action="{{ route('admin.deconnexion') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50 border border-rose-200 transition">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Déconnexion
                        </button>
                    </form>
                </div>
            </header>

            {{-- Corps de page --}}
            <main id="contenu" tabindex="-1" class="flex-1 px-6 py-8 lg:px-10 max-w-7xl w-full">
                <div class="mb-6">
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">{{ $titre }}</h1>
                    @if ($chapeau)
                        <p class="mt-1.5 max-w-3xl text-sm text-slate-600 leading-relaxed">{{ $chapeau }}</p>
                    @endif
                </div>

                {{-- Messages Flash & Alertes --}}
                @if (session('statut'))
                    <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 shadow-xs text-sm text-emerald-900" role="status">
                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                            <x-icone nom="verifie" class="icone size-5" />
                        </span>
                        <div class="font-medium">{{ session('statut') }}</div>
                    </div>
                @endif
                @if (session('erreur'))
                    <div class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs text-sm text-rose-900" role="alert">
                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700 mt-0.5">
                            <x-icone nom="alerte" class="icone size-5" />
                        </span>
                        <div class="font-medium">{{ session('erreur') }}</div>
                    </div>
                @endif
                @php($messages = collect($errors->getBags())->flatMap(fn ($sac) => $sac->all()))
                @if ($messages->isNotEmpty())
                    <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs text-sm" role="alert">
                        <div class="flex items-center gap-2 font-bold text-rose-950">
                            <x-icone nom="alerte" class="icone size-5 text-rose-600" />
                            <span>Veuillez corriger {{ $messages->count() }} {{ $messages->count() > 1 ? 'erreurs' : 'erreur' }} :</span>
                        </div>
                        <ul class="mt-2 list-disc pl-8 space-y-1 text-rose-900 font-medium">
                            @foreach ($messages as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div>{{ $slot }}</div>
            </main>
        </div>
    </div>
</body>
</html>

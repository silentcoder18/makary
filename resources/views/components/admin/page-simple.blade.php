@props(['titre'])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $titre }} — Administration · Commune de Makary</title>
    <link rel="icon" href="{{ asset('images/icone-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/icone-180.png') }}">
    @vite(['resources/css/portail.css', 'resources/js/portail.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-[#120e29] via-[#1a1439] to-[#251e4f] text-slate-100 flex flex-col justify-between selection:bg-soleil selection:text-encre relative overflow-x-hidden">
    {{-- Halos d'ambiance décoratifs --}}
    <div class="absolute -top-32 -left-32 size-96 rounded-full bg-purple-600/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 size-96 rounded-full bg-amber-500/10 blur-3xl pointer-events-none"></div>

    <header class="relative z-10">
        @if (config('makary.donnees_fictives'))
            <p class="bandeau-demo text-center text-xs font-bold py-1 bg-amber-400 text-amber-950 uppercase tracking-widest shadow-sm" role="note">Environnement de Démonstration</p>
        @endif
        <x-admin.dispense-mfa />
    </header>

    <main id="contenu" class="relative z-10 w-full max-w-lg mx-auto px-5 py-12 flex-1 flex flex-col justify-center">
        <div class="rounded-3xl bg-white/95 backdrop-blur-xl p-8 sm:p-10 shadow-2xl border border-white/20 text-encre">
            <div class="flex items-center gap-3.5 mb-6">
                <span class="rounded-2xl bg-[#1a1439] p-2 shadow-md">
                    <x-logo :hauteur="44" class="h-11 w-auto" />
                </span>
                <div>
                    <span class="block text-xs font-extrabold uppercase tracking-[0.16em] text-lac">Commune de Makary</span>
                    <span class="block text-xs text-slate-500 font-medium">République du Cameroun</span>
                </div>
            </div>

            <h1 class="text-2xl font-black tracking-tight text-slate-900 mb-2">{{ $titre }}</h1>
            <p class="text-xs text-slate-600 leading-relaxed mb-6">Connectez-vous avec vos identifiants pour administrer la plateforme et valider les contenus.</p>

            @if (session('statut'))
                <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-3.5 text-xs font-bold text-emerald-800 flex items-center gap-2" role="status">
                    <x-icone nom="verifie" class="icone size-4 text-emerald-600" />
                    <span>{{ session('statut') }}</span>
                </div>
            @endif

            @if (session('erreur'))
                <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 p-3.5 text-xs font-bold text-rose-800 flex items-center gap-2" role="alert">
                    <x-icone nom="alerte" class="icone size-4 text-rose-600" />
                    <span>{{ session('erreur') }}</span>
                </div>
            @endif

            {{ $slot }}
        </div>

        <p class="mt-6 text-center text-xs text-sable/60">
            <a href="{{ route('accueil') }}" class="text-sable hover:text-white underline font-medium">← Retourner au portail citoyen</a>
        </p>
    </main>

    <footer class="relative z-10 py-4 text-center text-[0.7rem] text-sable/50">
        Plateforme officielle de la Commune de Makary · Tous droits réservés
    </footer>
</body>
</html>

@props(['titre', 'description' => 'Site officiel de la Commune de Makary : services de la mairie, projets, investissement, partenariats, diaspora.'])
@php($routeCourante = request()->route()?->getName())
@php($rubrique = \App\Portail\Navigation::rubrique($routeCourante))
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} — Commune de Makary</title>
    <meta name="description" content="{{ $description }}">
    @if (config('makary.donnees_fictives'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="#1a1439">
    <link rel="icon" href="{{ asset('images/icone-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/icone-180.png') }}">
    @vite(['resources/css/portail.css', 'resources/js/portail.js'])
</head>
<body class="flex min-h-screen flex-col bg-white">
    <a class="lien-evitement" href="#contenu">Aller au contenu</a>

    <header class="relative z-30 bg-white shadow-[0_1px_0_var(--color-argile)]">
        @if (config('makary.donnees_fictives'))
            <p class="bandeau-demo" role="note">DONNÉES FICTIVES — DÉMONSTRATION · Environnement de démonstration, non validé par la Commune</p>
        @endif

        {{-- Barre de service --}}
        <div class="sur-sombre hidden bg-nuit text-sm lg:block">
            <nav class="conteneur flex items-center justify-between gap-6" aria-label="Accès de service">
                <ul class="flex flex-wrap items-center gap-x-6">
                    @foreach (\App\Portail\Navigation::SERVICE as $cle => $lien)
                        <li><a href="{{ route($lien['route']) }}" class="inline-block py-2.5 font-semibold text-sable no-underline hover:text-white hover:underline"
                               @if ($rubrique && $rubrique['cle'] === $cle) aria-current="page" @endif>{{ $lien['libelle'] }}</a></li>
                    @endforeach
                </ul>
                <a href="{{ route('demande.suivi') }}" class="inline-flex items-center gap-1.5 py-2.5 font-semibold text-soleil no-underline hover:underline">
                    <x-icone nom="suivi" class="icone size-4" /> Suivre ma demande
                </a>
            </nav>
        </div>

        <div class="conteneur flex items-center justify-between gap-4 py-3 lg:py-4">
            <a href="{{ route('accueil') }}" class="flex items-center gap-3 text-encre no-underline" @if ($routeCourante === 'accueil') aria-current="page" @endif>
                <x-logo :hauteur="56" class="h-12 w-auto lg:h-14" />
                <span class="leading-tight">
                    <span class="block text-lg font-extrabold tracking-tight">Commune de Makary</span>
                    <span class="block text-xs font-semibold uppercase tracking-[0.14em] text-ardoise">Site officiel</span>
                </span>
            </a>

            <div class="flex items-center gap-2">
                <a href="{{ route('recherche') }}" class="inline-flex min-h-11 items-center gap-2 rounded-full px-3 font-bold text-lac no-underline hover:bg-sable">
                    <x-icone nom="recherche" /> <span class="sr-only sm:not-sr-only">Rechercher</span>
                </a>

                {{-- Menu mobile : élément <details>, utilisable sans JavaScript --}}
                <details class="menu-mobile lg:hidden">
                    <summary class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full bg-lac px-4 font-bold text-white">
                        <x-icone nom="menu" class="icone icone-menu" /><x-icone nom="fermer" class="icone icone-fermer" /> Menu
                    </summary>
                    <div class="absolute inset-x-0 top-full max-h-[80vh] overflow-y-auto border-t border-argile bg-white shadow-[0_24px_40px_-24px_rgb(11_31_46/0.45)]">
                        <nav class="conteneur py-4" aria-label="Navigation principale">
                            <ul class="grid gap-1">
                                @foreach (\App\Portail\Navigation::PRINCIPALE as $cle => $lien)
                                    <li><a href="{{ route($lien['route']) }}" class="flex items-center gap-3 rounded-lg px-2 py-3 text-lg font-bold text-encre no-underline hover:bg-sable"
                                           @if ($rubrique && $rubrique['cle'] === $cle) aria-current="page" @endif><x-icone nom="{{ $lien['icone'] }}" class="icone text-lagune" />{{ $lien['libelle'] }}</a></li>
                                @endforeach
                            </ul>
                            <p class="mt-4 border-t border-argile pt-4 text-xs font-extrabold uppercase tracking-[0.14em] text-ardoise">Services</p>
                            <ul class="mt-2 grid grid-cols-2 gap-1">
                                @foreach (\App\Portail\Navigation::SERVICE as $cle => $lien)
                                    <li><a href="{{ route($lien['route']) }}" class="block rounded-lg px-2 py-2.5 font-semibold no-underline hover:bg-sable"
                                           @if ($rubrique && $rubrique['cle'] === $cle) aria-current="page" @endif>{{ $lien['libelle'] }}</a></li>
                                @endforeach
                                <li><a href="{{ route('demande.suivi') }}" class="block rounded-lg px-2 py-2.5 font-semibold no-underline hover:bg-sable">Suivre ma demande</a></li>
                            </ul>
                        </nav>
                    </div>
                </details>
            </div>
        </div>

        {{-- Navigation principale (écrans larges) : les rubriques de la mission --}}
        <nav class="hidden border-t border-argile lg:block" aria-label="Navigation principale">
            <ul class="conteneur flex items-center gap-x-7">
                @foreach (\App\Portail\Navigation::PRINCIPALE as $cle => $lien)
                    <li><a href="{{ route($lien['route']) }}" class="nav-lien py-3.5" @if ($rubrique && $rubrique['cle'] === $cle) aria-current="page" @endif>{{ $lien['libelle'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    </header>

    <main id="contenu" class="flex-1" tabindex="-1">
        {{ $slot }}
    </main>

    @include('portail.partiels.pied')
</body>
</html>

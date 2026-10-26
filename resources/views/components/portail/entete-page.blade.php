@props(['titre', 'chapeau' => null, 'surtitre' => null, 'ariane' => [], 'sousNavigation' => null])
@php($rubrique = \App\Portail\Navigation::rubrique(request()->route()?->getName()))
@php($sousNavigation ??= $rubrique['sous'] ?? [])
@php($routeCourante = request()->route()?->getName())
<div class="fond-motif border-b border-argile">
    <div class="conteneur pb-10 pt-6 lg:pb-14">
        <nav aria-label="Fil d’Ariane">
            <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ardoise">
                <li><a href="{{ route('accueil') }}">Accueil</a></li>
                @if ($rubrique && $routeCourante !== $rubrique['route'])
                    <li class="flex items-center gap-2"><span aria-hidden="true">›</span><a href="{{ route($rubrique['route']) }}">{{ $rubrique['libelle'] }}</a></li>
                @endif
                @foreach ($ariane as $libelle => $adresse)
                    <li class="flex items-center gap-2"><span aria-hidden="true">›</span><a href="{{ $adresse }}">{{ $libelle }}</a></li>
                @endforeach
                <li class="flex items-center gap-2"><span aria-hidden="true">›</span><span aria-current="page" class="font-semibold text-encre">{{ $titre }}</span></li>
            </ol>
        </nav>

        <div class="mt-8 max-w-3xl">
            @if ($surtitre ?? $rubrique['libelle'] ?? null)
                <p class="surtitre">{{ $surtitre ?? $rubrique['libelle'] }}</p>
            @endif
            <h1 class="mt-3 text-[clamp(2rem,1.4rem+2.6vw,3.25rem)]">{{ $titre }}</h1>
            @if ($chapeau)
                <p class="mt-4 text-lg text-ardoise lg:text-xl">{{ $chapeau }}</p>
            @endif
            {{ $slot }}
        </div>

        @if ($sousNavigation)
            <nav class="mt-8" aria-label="Dans cette rubrique">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($sousNavigation as $route => $libelle)
                        <li><a href="{{ route($route) }}" class="puce" @if ($routeCourante === $route) aria-current="page" @endif>{{ $libelle }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</div>

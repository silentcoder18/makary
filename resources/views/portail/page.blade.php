{{-- Page de rubrique éditoriale : version publiée, ou emplacement « Contenu à fournir par la Commune » --}}
<x-portail.gabarit :titre="$page['titre'] ?? $titre" :description="$page['resume'] ?? $chapeau">
    <x-portail.entete-page :titre="$titre" :chapeau="$page['resume'] ?? $chapeau" />

    <div class="conteneur py-12 lg:py-16">
        @if ($page)
            <div class="prose-makary">{!! $format->markdown($page['corps']) !!}</div>
            <p class="mt-10 text-sm text-ardoise">Mis à jour le {{ $format->date($page->publieLe) }} · version {{ $page->version }}</p>
        @else
            <x-portail.a-fournir :quoi="$titre" :detail="$chapeau" class="max-w-3xl" />
        @endif

        @isset($action)
            <a href="{{ route($action['route']) }}" class="bouton bouton-principal mt-10">{{ $action['libelle'] }} <x-icone nom="fleche" class="icone size-5" /></a>
        @endisset
    </div>
</x-portail.gabarit>

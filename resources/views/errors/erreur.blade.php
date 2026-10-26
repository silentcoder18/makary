{{-- Page d'erreur commune du portail : message clair et chemins pour repartir --}}
<x-portail.gabarit :titre="$titre">
    <div class="fond-motif">
        <div class="conteneur py-20 lg:py-28">
            <p class="surtitre">Erreur {{ $code }}</p>
            <h1 class="mt-3 text-[clamp(2rem,1.4rem+2.6vw,3.25rem)]">{{ $titre }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-ardoise">{{ $message }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('accueil') }}" class="bouton bouton-principal">Retour à l’accueil</a>
                <a href="{{ route('recherche') }}" class="bouton bouton-secondaire">Rechercher</a>
                <a href="{{ route('plan-du-site') }}" class="bouton bouton-secondaire">Plan du site</a>
            </div>
        </div>
    </div>
</x-portail.gabarit>

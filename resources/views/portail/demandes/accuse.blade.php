<x-portail.gabarit titre="Demande envoyée">
    <x-portail.entete-page titre="Votre demande a bien été envoyée" :chapeau="'Merci. Votre demande « '.$demande['titre'].' » a été enregistrée.'" :sous-navigation="[]" />

    <div class="conteneur py-12 lg:py-16">
        <div class="max-w-2xl rounded-carte bg-white p-8 shadow-[var(--shadow-carte)]">
            <div class="flex items-center gap-3 text-palmeraie"><x-icone nom="verifie" class="icone size-8" /><p class="text-lg font-bold">Accusé de réception</p></div>
            <p class="mt-6 text-ardoise">Votre numéro de suivi :</p>
            <p class="mt-1 font-mono text-3xl font-extrabold tracking-wider text-lac">{{ $demande['numero'] }}</p>
            <button type="button" class="bouton bouton-secondaire mt-4" data-copier="{{ $demande['numero'] }}" aria-describedby="etat-copie" hidden>Copier le numéro</button>
            <p id="etat-copie" class="mt-2 text-sm text-ardoise" role="status"></p>
            <p class="mt-6">Conservez ce numéro. Avec votre adresse <strong>{{ $demande['courriel'] }}</strong>, il vous permet de suivre le traitement de votre demande.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('demande.suivi') }}" class="bouton bouton-principal">Suivre ma demande</a>
                <a href="{{ route('accueil') }}" class="bouton bouton-secondaire">Retour à l’accueil</a>
            </div>
        </div>
    </div>
</x-portail.gabarit>

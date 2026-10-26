<x-admin.page-simple titre="Double authentification">
    @if ($configuration)
        <p>Votre rôle exige un second facteur. Ajoutez ce compte dans une application d’authentification
            (par exemple FreeOTP ou Aegis), puis saisissez le code à six chiffres qu’elle affiche.</p>
        <p class="mt-4 text-sm font-bold">Clé à saisir</p>
        <p class="mt-1 break-all rounded-lg bg-sable p-3 font-mono text-lg tracking-wider">{{ $configuration['secret'] }}</p>
        <details class="mt-3 text-sm"><summary class="cursor-pointer font-semibold text-lac">Adresse de configuration</summary>
            <p class="mt-2 break-all rounded-lg bg-sable p-3 font-mono">{{ $configuration['uri'] }}</p></details>
    @else
        <p>Saisissez le code à six chiffres affiché par votre application d’authentification.</p>
    @endif
    <form method="post" action="{{ route('admin.mfa') }}" class="mt-6 space-y-5" novalidate>
        @csrf
        <x-portail.champ nom="code" libelle="Code à six chiffres" autocomplete="one-time-code" max="7" requis />
        <button type="submit" class="bouton bouton-principal w-full">Valider</button>
    </form>
</x-admin.page-simple>

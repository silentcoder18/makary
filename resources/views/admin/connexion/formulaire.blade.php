<x-admin.page-simple titre="Connexion">
    <form method="post" action="{{ route('admin.connexion') }}" class="space-y-4" novalidate>
        @csrf
        <div>
            <x-portail.champ nom="courriel" libelle="Adresse électronique professionnelle" type="email" autocomplete="username" requis />
        </div>
        <div>
            <x-portail.champ nom="mot_de_passe" libelle="Mot de passe" type="password" autocomplete="current-password" requis />
        </div>

        <button type="submit" class="bouton w-full bg-[#1a1439] text-white hover:bg-[#282054] font-bold shadow-md text-sm py-3 flex items-center justify-center gap-2 mt-2">
            <span>Accéder au back-office</span>
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>
    </form>

    <div class="mt-6 pt-5 border-t border-slate-100 flex items-center gap-2 text-[0.72rem] text-slate-500">
        <svg class="size-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <span>Accès sécurisé réservé au Super Administrateur et au Secrétariat Général.</span>
    </div>
</x-admin.page-simple>

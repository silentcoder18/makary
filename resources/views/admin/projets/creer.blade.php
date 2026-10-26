<x-admin.gabarit titre="Nouvelle fiche projet" chapeau="La fiche est initialisée au statut « idée identifiée », avec un code officiel unique attribué par le système.">
    <p class="-mt-4 mb-6 text-sm">
        <a href="{{ route('admin.projets.index') }}" class="text-lac font-semibold hover:underline">← Retour à la liste des projets</a>
    </p>

    <div class="max-w-3xl">
        <form method="post" action="{{ route('admin.projets.enregistrer') }}" class="panneau space-y-6" novalidate>
            @csrf
            
            <div class="space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">Identification du projet</h2>
                <x-portail.champ nom="titre" libelle="Titre du projet" max="120" requis placeholder="Ex : Réhabilitation du réseau hydraulique" />
                <x-portail.champ nom="slug" libelle="Identifiant web (adresse de la page)" max="120" requis aide="Lettres minuscules, chiffres et tirets. Ex : rehabilitation-reseau-hydraulique" />
                <x-portail.champ nom="secteur" libelle="Secteur d’intervention" :options="$secteurs" requis />
            </div>

            <div class="space-y-4 pt-4 border-t border-slate-100">
                <fieldset>
                    <legend class="etiquette font-bold text-slate-900">Localités bénéficiaires <span class="font-normal text-slate-500">(au moins une localité)</span></legend>
                    <div class="grid gap-2.5 sm:grid-cols-2 mt-2 p-3 rounded-xl border border-slate-200 bg-slate-50/50">
                        @foreach ($localites as $id => $nom)
                            <label class="flex items-center gap-2.5 text-xs font-medium text-slate-800 cursor-pointer hover:text-lac">
                                <input type="checkbox" name="localites[]" value="{{ $id }}" class="case rounded" @checked(in_array($id, old('localites', [])))>
                                <span>{{ $nom }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('localites')
                        <p class="erreur mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </fieldset>
            </div>

            <div class="flex items-center justify-between gap-4 pt-6 border-t border-slate-100">
                <a href="{{ route('admin.projets.index') }}" class="bouton bouton-secondaire">
                    Annuler
                </a>
                <button type="submit" class="bouton bouton-principal shadow-sm">
                    Initialiser la fiche projet
                </button>
            </div>
        </form>
    </div>
</x-admin.gabarit>

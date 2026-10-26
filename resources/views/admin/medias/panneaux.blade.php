{{-- Média : aperçu et confirmation du droit d'usage (E-12) --}}
@use('App\Http\Controllers\Admin\MediasController', 'M')
<section class="panneau space-y-3" aria-labelledby="titre-apercu">
    <div class="border-b border-slate-100 pb-2">
        <h2 id="titre-apercu" class="text-base font-bold text-slate-900 tracking-tight">Aperçu du média</h2>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
        <img src="{{ route('admin.fichiers.show', $media->fichier) }}" alt="{{ $media->texte_alternatif }}" class="w-full h-auto object-cover max-h-60" loading="lazy">
    </div>
    <p class="text-xs text-slate-500 font-mono">
        {{ $media->fichier->nom_original }} · {{ number_format($media->fichier->taille_octets / 1024, 0, ',', ' ') }} Ko
        <br>Analyse antivirus : <span class="font-bold {{ $media->fichier->analyse_ok ? 'text-emerald-700' : 'text-slate-700' }}">{{ match ($media->fichier->analyse_ok) { true => 'Fichier sain', false => 'Fichier refusé', null => 'En attente' } }}</span>
    </p>
</section>

<section class="panneau space-y-4" aria-labelledby="titre-droit">
    <div class="border-b border-slate-100 pb-2">
        <h2 id="titre-droit" class="text-base font-bold text-slate-900 tracking-tight">Droit d’usage &amp; Propriété</h2>
        <p class="text-xs text-slate-500">
            Actuellement : <strong class="text-slate-900">{{ $media->droit->libelle() }}</strong>
            @if ($media->justificatif) · Pièce : « {{ $media->justificatif->intitule }} »@endif
        </p>
    </div>

    @can('media.valider_droits', $media)
        <form method="post" action="{{ route('admin.medias.droit', $media) }}" class="space-y-3 pt-1">
            @csrf
            <input type="hidden" name="_formulaire" value="droit">
            <x-portail.champ nom="droit" libelle="Droit d’usage confirmé" :options="M::DROITS" requis formulaire="droit" />
            <fieldset class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                <legend class="px-1 text-xs font-bold text-slate-800">Pièce justificative (autorisation écrite ou licence)</legend>
                <div class="grid gap-2.5 mt-2">
                    <x-portail.champ nom="justificatif_type" libelle="Type" :options="\App\Http\Controllers\Admin\ProjetsController::TYPES_SOURCE" formulaire="droit" />
                    <x-portail.champ nom="justificatif_date" libelle="Date" type="date" formulaire="droit" />
                    <x-portail.champ nom="justificatif_intitule" libelle="Intitulé" max="300" formulaire="droit" />
                    <x-portail.champ nom="justificatif_reference" libelle="Référence" max="160" formulaire="droit" />
                </div>
            </fieldset>
            <button type="submit" class="bouton bouton-principal w-full text-xs py-2 font-bold shadow-sm">Confirmer le droit d’usage</button>
        </form>
    @else
        <p class="text-xs text-slate-500">Confirmation du droit d’usage par le Super Administrateur ou le Secrétaire Général.</p>
    @endcan
</section>

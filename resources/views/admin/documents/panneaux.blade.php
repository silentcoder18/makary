{{-- Document : versions, diffusion et archivage (E-14) --}}
@use('App\Http\Controllers\Admin\DocumentsController', 'D')
<section class="panneau space-y-4" aria-labelledby="titre-versions">
    <div class="border-b border-slate-100 pb-2">
        <h2 id="titre-versions" class="text-base font-bold text-slate-900 tracking-tight">Fichiers &amp; Versions</h2>
        <p class="text-xs text-slate-500">Historique des versions PDF déposées.</p>
    </div>
    
    <div class="space-y-2">
        @forelse ($document->versions->sortByDesc('numero') as $version)
            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 text-xs space-y-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-slate-900">Version {{ $version->numero }}</span>
                    <span class="text-[0.7rem] font-mono text-slate-500">{{ $format->date($version->cree_le, 'j M Y') }}</span>
                </div>
                <div class="flex items-center justify-between gap-2 pt-0.5">
                    <span class="font-mono truncate text-slate-700">
                        @if ($version->fichier->analyse_ok === true)
                            <a href="{{ route('admin.fichiers.show', $version->fichier) }}" class="text-lac hover:underline font-bold">{{ $version->fichier->nom_original }}</a>
                        @else
                            {{ $version->fichier->nom_original }}
                        @endif
                    </span>
                    <span class="text-slate-400 font-mono shrink-0">{{ number_format($version->fichier->taille_octets / 1024, 0, ',', ' ') }} Ko</span>
                </div>
                @if ($version->note)<p class="text-slate-600 italic">« {{ $version->note }} »</p>@endif
                @if ($version->auteur)<p class="text-[0.7rem] text-slate-400">Déposée par {{ $version->auteur->nom_affiche }}</p>@endif
            </div>
        @empty
            <p class="text-xs text-slate-500 italic py-1">Aucune version : déposez le premier fichier PDF.</p>
        @endforelse
    </div>

    @can('document.gerer', $document)
        @unless ($document->archive)
            <form method="post" action="{{ route('admin.documents.version', $document) }}" enctype="multipart/form-data" class="space-y-3 border-t border-slate-100 pt-3">
                @csrf
                <input type="hidden" name="_formulaire" value="version">
                <div>
                    <label for="champ-version-fichier" class="etiquette font-bold text-slate-800">Nouvelle version (PDF) <span class="font-normal text-rose-600">*</span></label>
                    <input id="champ-version-fichier" name="fichier" type="file" accept="application/pdf" class="champ mt-1" required>
                    @error('fichier', 'version')<p class="erreur mt-1 text-xs text-rose-600"><x-icone nom="alerte" class="icone size-4 inline" />{{ $message }}</p>@enderror
                </div>
                <x-portail.champ nom="note" libelle="Note d’évolution (facultatif)" max="500" formulaire="version" />
                <button type="submit" class="bouton bouton-secondaire w-full text-xs py-2 font-bold">+ Ajouter cette version</button>
            </form>
        @endunless
    @endcan
</section>

<section class="panneau space-y-4" aria-labelledby="titre-diffusion">
    <div class="border-b border-slate-100 pb-2">
        <h2 id="titre-diffusion" class="text-base font-bold text-slate-900 tracking-tight">Niveau de diffusion</h2>
        <p class="text-xs text-slate-500">Actuellement : <strong class="text-slate-900">{{ $document->diffusion->libelle() }}</strong></p>
    </div>

    @if ($document->diffusion->value !== 'public')
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-800">
            La diffusion publique est requise avant toute mise en ligne sur le portail citoyen.
        </div>
    @endif

    @can('document.valider_diffusion', new \App\Securite\Cible(['juridique']))
        <form method="post" action="{{ route('admin.documents.diffusion', $document) }}" class="space-y-3 pt-1">
            @csrf
            <input type="hidden" name="_formulaire" value="diffusion">
            <x-portail.champ nom="diffusion" libelle="Accorder la diffusion" :options="D::DIFFUSIONS" :valeur="$document->diffusion->value" requis formulaire="diffusion" />
            <fieldset class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                <legend class="px-1 text-xs font-bold text-slate-800">Pièce de l’accord (obligatoire pour « Public »)</legend>
                <div class="grid gap-2.5 mt-2">
                    <x-portail.champ nom="accord_type" libelle="Type" :options="\App\Http\Controllers\Admin\ProjetsController::TYPES_SOURCE" formulaire="diffusion" />
                    <x-portail.champ nom="accord_date" libelle="Date" type="date" formulaire="diffusion" />
                    <x-portail.champ nom="accord_intitule" libelle="Intitulé" max="300" formulaire="diffusion" />
                    <x-portail.champ nom="accord_reference" libelle="Référence" max="160" formulaire="diffusion" />
                </div>
            </fieldset>
            <button type="submit" class="bouton bouton-principal w-full text-xs py-2 font-bold shadow-sm">Valider la diffusion</button>
        </form>
    @else
        <p class="text-xs text-slate-500">Validation accordée par le Super Administrateur ou le Secrétaire Général.</p>
    @endcan
</section>

@can('document.archiver', $document)
    @unless ($document->archive)
        <section class="panneau space-y-3 border border-slate-200 bg-slate-50/60" aria-labelledby="titre-archive">
            <h2 id="titre-archive" class="text-base font-bold text-slate-900 tracking-tight">Archivage officiel</h2>
            <p class="text-xs text-slate-500">Le document quitte le portail public mais reste conservé avec toutes ses versions.</p>
            <form method="post" action="{{ route('admin.documents.archiver', $document) }}" class="space-y-3 pt-1">
                @csrf
                <input type="hidden" name="_formulaire" value="archivage">
                <x-portail.champ nom="motif_archivage" libelle="Motif de l’archivage" type="textarea" max="2000" requis formulaire="archivage" />
                <button type="submit" class="bouton bouton-secondaire w-full text-xs py-2 font-bold">Archiver le document</button>
            </form>
        </section>
    @endunless
@endcan

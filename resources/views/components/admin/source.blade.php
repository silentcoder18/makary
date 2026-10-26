@props(['formulaire', 'types' => \App\Http\Controllers\Admin\ProjetsController::TYPES_SOURCE, 'legende' => 'Source (obligatoire, §8)'])
{{-- Sous-formulaire de source : aucun chiffre ni aucune pièce sans source datée --}}
<fieldset {{ $attributes->merge(['class' => 'rounded-xl border border-argile p-4']) }}>
    <legend class="px-1 text-sm font-bold">{{ $legende }}</legend>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-portail.champ nom="source_type" libelle="Type" :options="$types" :formulaire="$formulaire" requis />
        <x-portail.champ nom="source_date" libelle="Date" type="date" :formulaire="$formulaire" requis />
        <x-portail.champ nom="source_intitule" libelle="Intitulé" max="300" :formulaire="$formulaire" requis class="sm:col-span-2" />
        <x-portail.champ nom="source_reference" libelle="Référence" max="160" :formulaire="$formulaire" class="sm:col-span-2" />
    </div>
</fieldset>

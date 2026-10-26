@props(['quoi', 'detail' => null])
<div {{ $attributes->merge(['class' => 'a-fournir flex gap-4 p-6']) }}>
    <x-icone nom="info" class="icone size-7 text-terre" />
    <div>
        <p class="font-bold">Contenu à fournir par la Commune</p>
        <p class="mt-1 text-ardoise">{{ $quoi }}{{ $detail ? ' — '.$detail : '' }}. Cet emplacement sera rempli après validation par le responsable désigné ; aucun texte provisoire n’est inventé.</p>
    </div>
</div>

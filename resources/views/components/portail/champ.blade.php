@props(['nom', 'libelle', 'type' => 'text', 'requis' => false, 'aide' => null, 'autocomplete' => null, 'options' => null, 'valeur' => null, 'max' => null,
    'videLibelle' => 'Choisir…', 'formulaire' => null])
@php
    // Plusieurs formulaires sur une même page : identifiants préfixés, erreurs et saisies propres à chaque formulaire.
    $id = 'champ-'.($formulaire ? "$formulaire-" : '').$nom;
    $sac = $errors->getBag($formulaire ?? 'default');
    $erreur = $sac->first($nom);
    $decrit = trim(($aide ? "$id-aide " : '').($erreur ? "$id-erreur" : ''));
    $actuel = ! $formulaire || old('_formulaire') === $formulaire ? old($nom, $valeur) : $valeur;
@endphp
<div {{ $attributes }}>
    <label for="{{ $id }}" class="etiquette">{{ $libelle }} @if ($requis)<span class="font-normal text-ardoise">(obligatoire)</span>@endif</label>
    @if ($aide)
        <span id="{{ $id }}-aide" class="aide">{{ $aide }}</span>
    @endif
    @if ($options !== null)
        <select id="{{ $id }}" name="{{ $nom }}" class="champ" @required($requis) @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>
            <option value="">{{ $videLibelle }}</option>
            @foreach ($options as $valeurOption => $libelleOption)
                <option value="{{ $valeurOption }}" @selected((string) $actuel === (string) $valeurOption)>{{ $libelleOption }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $nom }}" class="champ" rows="6" @required($requis) @if ($max) maxlength="{{ $max }}" @endif
            @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>{{ $actuel }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $nom }}" type="{{ $type }}" value="{{ $actuel }}" class="champ" @required($requis) @if ($max) maxlength="{{ $max }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if ($decrit) aria-describedby="{{ $decrit }}" @endif @if ($erreur) aria-invalid="true" @endif>
    @endif
    @if ($erreur)
        <p id="{{ $id }}-erreur" class="erreur"><x-icone nom="alerte" class="icone size-5 flex-none" />{{ $erreur }}</p>
    @endif
</div>

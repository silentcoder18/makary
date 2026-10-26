<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Enums\TypeObjet;
use App\Models\Localite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Liste officielle des localités (C-013) : rubriques « Découvrir Makary » et « Cartographie ». */
class LocalitesController extends ObjetsController
{
    public const CATEGORIES = ['espace_urbain' => 'Espace urbain', 'village' => 'Village', 'quartier' => 'Quartier', 'autre' => 'Autre'];

    protected function type(): TypeObjet
    {
        return TypeObjet::Localite;
    }

    protected function section(): string
    {
        return 'localites';
    }

    protected function textes(): array
    {
        return ['liste' => 'Localités', 'nouveau' => 'Nouvelle localité', 'vide' => 'Aucune localité : la liste officielle reste à saisir.',
            'chapeau' => 'Liste officielle des villages, quartiers et espaces urbains (rubriques « Découvrir Makary » et « Cartographie »). Une position n’est affichée sur la carte que si elle a été relevée et sourcée.'];
    }

    protected function filtres(): array
    {
        return ['categorie' => ['Catégorie', self::CATEGORIES]];
    }

    protected function requete(Request $request): Builder
    {
        return Localite::query()->when(array_key_exists((string) $request->query('categorie'), self::CATEGORIES), fn ($q) => $q->where('categorie', $request->query('categorie')))
            ->orderBy('nom');
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Localite $l) => $l->nom,
            'Catégorie' => fn (Localite $l) => self::CATEGORIES[$l->categorie] ?? $l->categorie,
            'Position' => fn (Localite $l) => $l->latitude !== null ? "{$l->latitude}, {$l->longitude}" : 'non relevée',
        ];
    }

    protected function intitule(Model $objet): string
    {
        return $objet->nom;
    }

    protected function lienPublic(Model $objet): ?string
    {
        return route('decouvrir.localite', $objet->slug);
    }

    protected function champs(Model $objet): array
    {
        return [
            ['type' => 'text', 'nom' => 'nom', 'libelle' => 'Nom officiel', 'requis' => true, 'max' => 160, 'valeur' => $objet->nom],
            ['type' => 'text', 'nom' => 'slug', 'libelle' => 'Adresse de la page', 'requis' => true, 'max' => 160, 'valeur' => $objet->slug,
                'aide' => 'Minuscules sans accents, chiffres et tirets ; elle ne change plus après publication.'],
            ['type' => 'select', 'nom' => 'categorie', 'libelle' => 'Catégorie', 'requis' => true, 'options' => self::CATEGORIES, 'valeur' => $objet->categorie],
            $this->champSource('source', 'Source de l’existence et du nom (obligatoire) : liste officielle, arrêté, recensement…', $objet->source, ! $objet->exists),
            ['type' => 'intertitre', 'libelle' => 'Position sur la carte (seulement si elle a été relevée sur place)'],
            ['type' => 'text', 'nom' => 'latitude', 'libelle' => 'Latitude (degrés décimaux)', 'valeur' => $objet->latitude, 'aide' => 'Six chiffres après la virgule, par exemple 12.345678.'],
            ['type' => 'text', 'nom' => 'longitude', 'libelle' => 'Longitude (degrés décimaux)', 'valeur' => $objet->longitude],
            $this->champSource('releve', 'Source de la position (obligatoire si une position est saisie) : relevé GPS, carte officielle…', $objet->coordonneesSource, false),
        ];
    }

    protected function regles(Request $request, ?Model $objet): array
    {
        $sansSourceDePosition = ! $objet?->coordonnees_source;

        return [
            'nom' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('localite', 'slug')->ignore($objet?->id)],
            'categorie' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            ...$this->reglesSource('source', $objet === null),
            ...$this->reglesSource('releve', false),
            // Une position exige sa source : celle de la fiche, ou une nouvelle.
            'releve_type' => [$sansSourceDePosition ? 'required_with:latitude' : 'nullable', 'nullable', Rule::in(array_keys(ProjetsController::TYPES_SOURCE))],
        ];
    }

    protected function attributs(array $valide, Request $request, ?Model $objet): array
    {
        $source = $this->creerSource($valide, 'source', $request->user());
        $releve = $this->creerSource($valide, 'releve', $request->user());
        $avecPosition = isset($valide['latitude']) && $valide['latitude'] !== null;

        return [
            ...collect($valide)->only(['nom', 'slug', 'categorie'])->all(),
            'latitude' => $avecPosition ? $valide['latitude'] : null,
            'longitude' => $avecPosition ? $valide['longitude'] : null,
            'coordonnees_source' => $avecPosition ? ($releve?->id ?? $objet?->coordonnees_source) : null,
            ...($source ? ['source_id' => $source->id] : []),
        ];
    }

    protected function messages(): array
    {
        return [...parent::messages(), 'releve_type.required_with' => 'Indiquez la source de la position (relevé GPS, carte officielle…).'];
    }
}

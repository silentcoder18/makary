<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Enums\TypeObjet;
use App\Models\Equipement;
use App\Models\Localite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Équipements publics (C-101) : écoles, centres de santé, marchés, points d'eau, routes, bâtiments publics. */
class EquipementsController extends ObjetsController
{
    public const TYPES = ['ecole' => 'École', 'centre_sante' => 'Centre de santé', 'marche' => 'Marché', 'forage' => 'Forage', 'puits' => 'Puits',
        'route' => 'Route ou piste', 'batiment_public' => 'Bâtiment public', 'autre' => 'Autre équipement'];

    protected function type(): TypeObjet
    {
        return TypeObjet::Equipement;
    }

    protected function section(): string
    {
        return 'equipements';
    }

    protected function textes(): array
    {
        return ['liste' => 'Équipements', 'nouveau' => 'Nouvel équipement', 'vide' => 'Aucun équipement : l’inventaire reste à saisir.',
            'chapeau' => 'Écoles, centres de santé, marchés, points d’eau, routes et bâtiments publics (rubriques « Découvrir Makary » et « Cartographie »).'];
    }

    protected function filtres(): array
    {
        return ['type' => ['Type', self::TYPES]];
    }

    protected function requete(Request $request): Builder
    {
        return Equipement::with('localite')->when(array_key_exists((string) $request->query('type'), self::TYPES), fn ($q) => $q->where('type_equipement', $request->query('type')))
            ->orderBy('type_equipement')->orderBy('nom');
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Equipement $e) => $e->nom,
            'Type' => fn (Equipement $e) => self::TYPES[$e->type_equipement] ?? $e->type_equipement,
            'Localité' => fn (Equipement $e) => $e->localite?->nom,
            'Position' => fn (Equipement $e) => $e->latitude !== null ? "{$e->latitude}, {$e->longitude}" : 'non relevée',
        ];
    }

    protected function intitule(Model $objet): string
    {
        return $objet->nom;
    }

    protected function lienPublic(Model $objet): ?string
    {
        return route('decouvrir.equipements');
    }

    protected function champs(Model $objet): array
    {
        return [
            ['type' => 'text', 'nom' => 'nom', 'libelle' => 'Nom', 'requis' => true, 'max' => 160, 'valeur' => $objet->nom],
            ['type' => 'select', 'nom' => 'type_equipement', 'libelle' => 'Type', 'requis' => true, 'options' => self::TYPES, 'valeur' => $objet->type_equipement],
            ['type' => 'select', 'nom' => 'localite_id', 'libelle' => 'Localité', 'requis' => true, 'options' => Localite::orderBy('nom')->pluck('nom', 'id')->all(),
                'valeur' => $objet->localite_id, 'aide' => 'La localité doit d’abord exister dans « Localités ».'],
            ['type' => 'text', 'nom' => 'latitude', 'libelle' => 'Latitude (degrés décimaux)', 'valeur' => $objet->latitude, 'aide' => 'Seulement si elle a été relevée sur place.'],
            ['type' => 'text', 'nom' => 'longitude', 'libelle' => 'Longitude (degrés décimaux)', 'valeur' => $objet->longitude],
            $this->champSource('source', 'Source (obligatoire) : carte scolaire ou sanitaire, inventaire du service technique, relevé…', $objet->source, ! $objet->exists),
        ];
    }

    protected function regles(Request $request, ?Model $objet): array
    {
        return [
            'nom' => ['required', 'string', 'max:160'],
            'type_equipement' => ['required', Rule::in(array_keys(self::TYPES))],
            'localite_id' => ['required', 'integer', 'exists:localite,id'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            ...$this->reglesSource('source', $objet === null),
        ];
    }

    protected function attributs(array $valide, Request $request, ?Model $objet): array
    {
        $source = $this->creerSource($valide, 'source', $request->user());

        return [
            ...collect($valide)->only(['nom', 'type_equipement', 'localite_id', 'latitude', 'longitude'])->all(),
            ...($source ? ['source_id' => $source->id] : []),
        ];
    }
}

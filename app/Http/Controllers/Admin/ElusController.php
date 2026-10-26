<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Enums\TypeObjet;
use App\Models\Elu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Le Maire, l'exécutif, le conseil municipal et les anciens élus (M02) : chaque mandat appuyé sur son acte. */
class ElusController extends ObjetsController
{
    public const ORGANES = ['maire' => 'Maire', 'executif' => 'Exécutif municipal', 'conseil_municipal' => 'Conseil municipal',
        'ancien_maire' => 'Ancien maire', 'ancien_executif' => 'Ancien membre de l’exécutif'];

    protected function type(): TypeObjet
    {
        return TypeObjet::Elu;
    }

    protected function section(): string
    {
        return 'elus';
    }

    protected function textes(): array
    {
        return ['liste' => 'Élus', 'nouveau' => 'Nouvel élu', 'vide' => 'Aucun élu enregistré.',
            'chapeau' => 'Le Maire, l’exécutif, le conseil municipal et les anciens élus (rubrique « Le Maire et l’action municipale »). Chaque mandat s’appuie sur son acte de désignation.'];
    }

    protected function filtres(): array
    {
        return ['organe' => ['Organe', self::ORGANES]];
    }

    protected function requete(Request $request): Builder
    {
        return Elu::query()->when(array_key_exists((string) $request->query('organe'), self::ORGANES), fn ($q) => $q->where('organe', $request->query('organe')))
            ->orderByRaw("FIELD(organe, 'maire', 'executif', 'conseil_municipal', 'ancien_maire', 'ancien_executif')")->orderBy('nom_affiche');
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Elu $e) => $e->nom_affiche,
            'Fonction' => fn (Elu $e) => $e->fonction,
            'Organe' => fn (Elu $e) => self::ORGANES[$e->organe] ?? $e->organe,
            'Mandat' => fn (Elu $e) => $e->mandat_debut->format('Y').($e->mandat_fin ? ' – '.$e->mandat_fin->format('Y') : ' – en cours'),
        ];
    }

    protected function intitule(Model $objet): string
    {
        return $objet->nom_affiche;
    }

    protected function lienPublic(Model $objet): ?string
    {
        return route(match ($objet->organe) {
            'maire' => 'maire', 'executif' => 'mairie.executif', 'conseil_municipal' => 'mairie.conseil', default => 'mairie.anciens',
        });
    }

    protected function champs(Model $objet): array
    {
        return [
            ['type' => 'text', 'nom' => 'nom_affiche', 'libelle' => 'Nom, tel qu’il doit apparaître', 'requis' => true, 'max' => 160, 'valeur' => $objet->nom_affiche],
            ['type' => 'text', 'nom' => 'fonction', 'libelle' => 'Fonction', 'requis' => true, 'max' => 160, 'valeur' => $objet->fonction, 'aide' => 'Par exemple : Premier adjoint au Maire, chargé de…'],
            ['type' => 'select', 'nom' => 'organe', 'libelle' => 'Organe', 'requis' => true, 'options' => self::ORGANES, 'valeur' => $objet->organe],
            ['type' => 'select', 'nom' => 'photo_id', 'libelle' => 'Photo (médiathèque)', 'options' => $this->optionsMedias(), 'valeur' => $objet->photo_id, 'vide' => 'Aucune',
                'aide' => 'Téléversez d’abord la photo dans « Médias », avec son auteur et son droit d’usage.'],
            ['type' => 'date', 'nom' => 'mandat_debut', 'libelle' => 'Début du mandat', 'requis' => true, 'valeur' => $objet->mandat_debut?->toDateString()],
            ['type' => 'date', 'nom' => 'mandat_fin', 'libelle' => 'Fin du mandat', 'valeur' => $objet->mandat_fin?->toDateString(), 'aide' => 'Vide pour un mandat en cours.'],
            ['type' => 'textarea', 'nom' => 'biographie', 'libelle' => 'Biographie', 'max' => 1500, 'valeur' => $objet->biographie,
                'aide' => 'Seulement ce que l’élu accepte de rendre public ; aucune donnée sensible.'],
            $this->champSource('acte', 'Acte de désignation ou d’élection (obligatoire)', $objet->source, ! $objet->exists),
        ];
    }

    protected function regles(Request $request, ?Model $objet): array
    {
        return [
            'nom_affiche' => ['required', 'string', 'max:160'],
            'fonction' => ['required', 'string', 'max:160'],
            'organe' => ['required', Rule::in(array_keys(self::ORGANES))],
            'photo_id' => ['nullable', 'integer', 'exists:media,id'],
            'mandat_debut' => ['required', 'date'],
            'mandat_fin' => ['nullable', 'date', 'after_or_equal:mandat_debut'],
            'biographie' => ['nullable', 'string', 'max:1500'],
            ...$this->reglesSource('acte', $objet === null),
        ];
    }

    protected function attributs(array $valide, Request $request, ?Model $objet): array
    {
        $source = $this->creerSource($valide, 'acte', $request->user());

        return [
            ...collect($valide)->only(['nom_affiche', 'fonction', 'organe', 'photo_id', 'mandat_debut', 'mandat_fin', 'biographie'])->all(),
            ...($source ? ['source_id' => $source->id] : []),
        ];
    }
}

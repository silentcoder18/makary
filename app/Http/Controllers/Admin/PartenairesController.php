<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Enums\TypeObjet;
use App\Models\Partenaire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Partenaires techniques et financiers (M05, C-050) : cités sur le portail seulement avec leur accord écrit. */
class PartenairesController extends ObjetsController
{
    public const TYPES = ['bailleur_bilateral' => 'Bailleur bilatéral', 'bailleur_multilateral' => 'Bailleur multilatéral', 'programme_national' => 'Programme national',
        'ong' => 'ONG', 'fondation' => 'Fondation', 'entreprise' => 'Entreprise', 'collectivite' => 'Collectivité',
        'association_diaspora' => 'Association de la diaspora', 'autre' => 'Autre'];

    protected function type(): TypeObjet
    {
        return TypeObjet::Partenaire;
    }

    protected function section(): string
    {
        return 'partenaires';
    }

    protected function textes(): array
    {
        return ['liste' => 'Partenaires', 'nouveau' => 'Nouveau partenaire', 'vide' => 'Aucun partenaire enregistré.',
            'chapeau' => 'Partenaires techniques et financiers (rubrique « Partenariats et bailleurs »). Un partenaire n’est cité sur le portail qu’avec son accord écrit, joint en pièce.'];
    }

    protected function requete(Request $request): Builder
    {
        return Partenaire::query()->orderBy('nom');
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Partenaire $p) => $p->nom,
            'Type' => fn (Partenaire $p) => self::TYPES[$p->type_partenaire] ?? $p->type_partenaire,
            'Accord pour être cité' => fn (Partenaire $p) => $p->accepte_citation ? 'oui' : 'non',
        ];
    }

    protected function intitule(Model $objet): string
    {
        return $objet->nom;
    }

    protected function lienPublic(Model $objet): ?string
    {
        return route('partenaires.liste');
    }

    protected function champs(Model $objet): array
    {
        return [
            ['type' => 'text', 'nom' => 'nom', 'libelle' => 'Nom', 'requis' => true, 'max' => 255, 'valeur' => $objet->nom, 'pleine' => true],
            ['type' => 'select', 'nom' => 'type_partenaire', 'libelle' => 'Type', 'requis' => true, 'options' => self::TYPES, 'valeur' => $objet->type_partenaire],
            ['type' => 'text', 'nom' => 'pays', 'libelle' => 'Pays', 'max' => 80, 'valeur' => $objet->pays],
            ['type' => 'url', 'nom' => 'site_web', 'libelle' => 'Site web', 'max' => 500, 'valeur' => $objet->site_web],
            ['type' => 'select', 'nom' => 'logo_id', 'libelle' => 'Logo (médiathèque)', 'options' => $this->optionsMedias(), 'valeur' => $objet->logo_id, 'vide' => 'Aucun'],
            ['type' => 'case', 'nom' => 'accepte_citation', 'libelle' => 'Le partenaire a donné son accord écrit pour être cité sur le portail', 'valeur' => $objet->accepte_citation,
                'aide' => 'Sans accord écrit, la fiche ne peut pas être soumise à la revue.'],
            $this->champSource('accord', 'Pièce de l’accord écrit (obligatoire si l’accord est coché)', $objet->accordCitation, false),
        ];
    }

    protected function regles(Request $request, ?Model $objet): array
    {
        $sansAccord = ! $objet?->accord_citation_id;

        return [
            'nom' => ['required', 'string', 'max:255', Rule::unique('partenaire', 'nom')->ignore($objet?->id)],
            'type_partenaire' => ['required', Rule::in(array_keys(self::TYPES))],
            'pays' => ['nullable', 'string', 'max:80'],
            'site_web' => ['nullable', 'url:https,http', 'max:500'],
            'logo_id' => ['nullable', 'integer', 'exists:media,id'],
            'accepte_citation' => ['required', 'boolean'],
            ...$this->reglesSource('accord', false),
            'accord_type' => [$sansAccord ? 'required_if_accepted:accepte_citation' : 'nullable', 'nullable', Rule::in(array_keys(ProjetsController::TYPES_SOURCE))],
        ];
    }

    protected function attributs(array $valide, Request $request, ?Model $objet): array
    {
        $accord = $this->creerSource($valide, 'accord', $request->user());

        return [
            ...collect($valide)->only(['nom', 'type_partenaire', 'pays', 'site_web', 'logo_id'])->all(),
            'accepte_citation' => (bool) $valide['accepte_citation'],
            ...($accord ? ['accord_citation_id' => $accord->id] : []),
        ];
    }

    protected function messages(): array
    {
        return [...parent::messages(), 'accord_type.required_if_accepted' => 'Joignez la pièce de l’accord écrit du partenaire.'];
    }
}

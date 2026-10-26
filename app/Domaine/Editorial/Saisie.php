<?php

namespace App\Domaine\Editorial;

use App\Domaine\Contexte;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\Publiable;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Saisie des objets publiables gérés depuis le back-office (élus, référentiels, partenaires, documents, médias),
 * au nom de l'agent : l'historique de la base enregistre l'auteur de chaque modification (§8).
 * Les contenus et les projets ont leurs propres services (Redaction, Fiches).
 */
class Saisie
{
    /** Permissions de création et de modification par type d'objet (specs/securite/permissions.yaml). */
    public const PERMISSIONS = [
        'elu' => ['creer' => 'contenu.creer', 'modifier' => 'contenu.modifier'],
        'localite' => ['creer' => 'referentiel.modifier', 'modifier' => 'referentiel.modifier'],
        'equipement' => ['creer' => 'referentiel.modifier', 'modifier' => 'referentiel.modifier'],
        'partenaire' => ['creer' => 'partenaire.modifier', 'modifier' => 'partenaire.modifier'],
        'document' => ['creer' => 'document.gerer', 'modifier' => 'document.gerer'],
        'media' => ['creer' => 'media.televerser', 'modifier' => 'media.televerser'],
    ];

    public static function peutCreer(Utilisateur $agent, TypeObjet $type): bool
    {
        return Gate::forUser($agent)->allows(self::PERMISSIONS[$type->value]['creer']);
    }

    public static function peutModifier(Utilisateur $agent, Model&Publiable $objet): bool
    {
        return Gate::forUser($agent)->allows(self::PERMISSIONS[$objet->typeObjet()->value]['modifier'], $objet);
    }

    /**
     * @template T of Model&Publiable
     *
     * @param  T  $objet  objet neuf, non enregistré
     * @param  array<string, mixed>  $donnees
     * @return T
     */
    public function creer(Model&Publiable $objet, Utilisateur $par, array $donnees): Model
    {
        Gate::forUser($par)->authorize(self::PERMISSIONS[$objet->typeObjet()->value]['creer']);

        return Contexte::enTantQue($par, function () use ($objet, $donnees) {
            $objet->fill($donnees)->save();

            return $objet->refresh();
        });
    }

    /**
     * Une modification après soumission ne change pas ce qui a été soumis : l'instantané est figé (migration 0002).
     *
     * @param  array<string, mixed>  $donnees
     */
    public function modifier(Model&Publiable $objet, Utilisateur $par, array $donnees): Model
    {
        Gate::forUser($par)->authorize(self::PERMISSIONS[$objet->typeObjet()->value]['modifier'], $objet);

        return Contexte::enTantQue($par, function () use ($objet, $donnees) {
            $objet->fill($donnees)->save();

            return $objet;
        });
    }
}

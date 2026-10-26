<?php

namespace App\Domaine\Projets;

use App\Domaine\Contexte;
use App\Domaine\RegleMetierException;
use App\Models\Projet;
use App\Models\Source;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Saisie d'une fiche projet (fiche projet §2) : champs, localités, et éléments sourcés (coût, bénéficiaires,
 * indicateurs, pièces). Chaque chiffre est enregistré avec sa source ; le statut ne change que par une transition.
 */
class Fiches
{
    /**
     * @param  array<string, mixed>  $donnees
     * @param  list<int>  $localites
     */
    public function creer(Utilisateur $par, array $donnees, array $localites): Projet
    {
        Gate::forUser($par)->authorize('projet.creer');
        if ($localites === []) {
            throw new RegleMetierException('Une fiche projet est rattachée à au moins une localité.');
        }

        return Contexte::enTantQue($par, function () use ($par, $donnees, $localites) {
            $projet = Projet::create([...$donnees, 'responsable_id' => $donnees['responsable_id'] ?? $par->id]);
            $projet->localites()->sync($localites);

            return $projet->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @param  list<int>  $localites
     */
    public function modifier(Projet $projet, Utilisateur $par, array $donnees, array $localites): Projet
    {
        Gate::forUser($par)->authorize('projet.modifier', $projet);
        if ($localites === []) {
            throw new RegleMetierException('Une fiche projet est rattachée à au moins une localité.');
        }

        return Contexte::enTantQue($par, function () use ($projet, $donnees, $localites) {
            $projet->fill($donnees)->save();
            $projet->localites()->sync($localites);

            return $projet->refresh();
        });
    }

    /**
     * Ajoute un élément sourcé à la fiche (estimation de coût, bénéficiaires, indicateur, pièce).
     * La source est créée avec l'élément : aucun chiffre sans source (§8).
     *
     * @param  'estimations'|'beneficiaires'|'indicateurs'|'pieces'  $relation
     * @param  array<string, mixed>  $donnees
     * @param  array{type_source: string, intitule: string, reference?: ?string, date_source: string}|null  $source
     */
    public function ajouter(Projet $projet, Utilisateur $par, string $relation, array $donnees, ?array $source): Model
    {
        Gate::forUser($par)->authorize('projet.modifier', $projet);

        return Contexte::enTantQue($par, function () use ($projet, $par, $relation, $donnees, $source) {
            if ($source) {
                $donnees['source_id'] = Source::create([...$source, 'cree_par' => $par->id])->id;
            }

            return $projet->{$relation}()->create($donnees);
        });
    }
}

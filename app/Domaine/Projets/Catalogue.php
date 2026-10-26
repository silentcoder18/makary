<?php

namespace App\Domaine\Projets;

use App\Domaine\Contexte;
use App\Domaine\RegleMetierException;
use App\Models\Projet;
use App\Models\Utilisateur;
use App\Securite\Journal;
use Illuminate\Support\Facades\Gate;

/**
 * Visibilité d'une fiche dans le catalogue public (§8 : « les projets doivent pouvoir être retirés du catalogue
 * public »). La fiche n'est jamais supprimée : elle reste dans le back-office avec son historique.
 */
class Catalogue
{
    public function retirer(Projet $projet, Utilisateur $par, string $motif): Projet
    {
        Gate::forUser($par)->authorize('projet.retirer_catalogue', $projet);
        if (trim($motif) === '') {
            throw new RegleMetierException('Le retrait du catalogue doit être motivé.');
        }

        return $this->changer($projet, $par, false, $motif);
    }

    public function retablir(Projet $projet, Utilisateur $par): Projet
    {
        Gate::forUser($par)->authorize('projet.retirer_catalogue', $projet);

        return $this->changer($projet, $par, true, null);
    }

    private function changer(Projet $projet, Utilisateur $par, bool $visible, ?string $motif): Projet
    {
        return Contexte::enTantQue($par, function () use ($projet, $par, $visible, $motif) {
            $projet->forceFill(['visible_catalogue' => $visible, 'motif_retrait' => $motif])->save();
            Journal::consigner($visible ? 'projet.retablir_catalogue' : 'projet.retirer_catalogue', $projet, ['motif' => $motif], $par->id);

            return $projet;
        });
    }
}

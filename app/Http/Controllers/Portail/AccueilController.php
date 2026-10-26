<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/** M01 — Accueil : recherche, démarches en un clic, entrées par public, mot du Maire, projets, chiffres, agenda. */
class AccueilController extends PortailController
{
    public function __invoke(): View
    {
        $projets = $this->lecture->projets();

        return view('portail.accueil', [
            'alertes' => $this->lecture->alertes(),
            'motDuMaire' => $this->lecture->page('mot-du-maire'),
            'maire' => $this->lecture->maire(),
            // Mis en avant : d'abord les projets ouverts au financement, puis les plus récemment mis à jour.
            'projets' => $projets->sortBy(fn ($p) => $p->get('statut.code') === 'financement_recherche' ? 0 : 1)->take(3)->values(),
            'chiffres' => $this->lecture->chiffresDuCatalogue(),
            'actualites' => $this->lecture->contenus('actualite', limite: 3),
            'evenements' => $this->lecture->evenements('a-venir', 3),
            'localites' => $this->lecture->localites(),
        ]);
    }
}

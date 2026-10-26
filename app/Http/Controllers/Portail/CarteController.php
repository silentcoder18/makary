<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/**
 * M09 — Cartographie. Seules des coordonnées vérifiées et sourcées sont affichées ; aucune position n'est inventée.
 * La liste accessible, équivalente à la carte, est toujours présente.
 */
class CarteController extends PortailController
{
    public function __invoke(): View
    {
        $localites = $this->lecture->localites();
        $projets = $this->lecture->projets();

        return view('portail.carte', [
            'localites' => $localites,
            'equipements' => $this->lecture->equipements()->groupBy(fn ($e) => $e->get('localite.slug')),
            'projets' => $projets,
            'positionnes' => $localites->filter(fn ($l) => $l['latitude'] !== null)->count()
                + $projets->filter(fn ($p) => ! empty($p['geometries']))->count(),
        ]);
    }
}

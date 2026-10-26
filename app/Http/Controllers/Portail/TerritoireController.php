<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/** M01 — Découvrir Makary : histoire, territoire, localités, économie, équipements. */
class TerritoireController extends PortailController
{
    public function index(): View
    {
        return view('portail.decouvrir.index', [
            'presentation' => $this->lecture->page('presentation'),
            'localites' => $this->lecture->localites(),
            'equipements' => $this->lecture->equipements(),
        ]);
    }

    public function histoire(): View
    {
        return $this->pageEditoriale('histoire', 'Histoire de Makary', 'Les grandes étapes de l’histoire de la commune.');
    }

    public function territoire(): View
    {
        return $this->pageEditoriale('territoire', 'Le territoire', 'Situation, géographie et chiffres clés, chacun avec sa source et sa date.');
    }

    public function economie(): View
    {
        return $this->pageEditoriale('economie', 'Économie locale', 'Activités, filières et atouts économiques de la commune.');
    }

    public function localites(): View
    {
        return view('portail.decouvrir.localites', ['localites' => $this->lecture->localites()]);
    }

    public function localite(string $localite): View
    {
        $fiche = $this->introuvable($this->lecture->localite($localite));

        return view('portail.decouvrir.localite', [
            'localite' => $fiche,
            'equipements' => $this->lecture->equipements($localite),
            'projets' => $this->lecture->projets(['localite' => $localite]),
        ]);
    }

    public function equipements(): View
    {
        return view('portail.decouvrir.equipements', ['equipements' => $this->lecture->equipements()]);
    }
}

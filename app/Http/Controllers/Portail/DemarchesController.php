<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/** Démarches administratives (M02, barre de service) : pièces, délai, coût, service compétent. */
class DemarchesController extends PortailController
{
    public function index(): View
    {
        return view('portail.demarches.index', ['demarches' => $this->lecture->contenus('demarche')->sortBy('titre')->values()]);
    }

    public function show(string $demarche): View
    {
        return view('portail.demarches.show', ['demarche' => $this->introuvable($this->lecture->contenu('demarche', $demarche))]);
    }
}

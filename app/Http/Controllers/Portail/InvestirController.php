<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/** M04 — Investir à Makary : secteurs porteurs, opportunités, démarches d'implantation. */
class InvestirController extends PortailController
{
    public function index(): View
    {
        return view('portail.investir.index', [
            'opportunites' => $this->lecture->contenus('opportunite'),
            'secteurs' => $this->lecture->secteurs(),
        ]);
    }

    public function secteurs(): View
    {
        $projets = $this->lecture->projets();

        return view('portail.investir.secteurs', [
            'secteurs' => $this->lecture->secteurs()->map(fn (string $libelle, string $code) => [
                'code' => $code, 'libelle' => $libelle,
                'projets' => $projets->where(fn ($p) => $p->get('secteur.code') === $code)->count(),
            ]),
        ]);
    }

    public function secteur(string $secteur): View
    {
        $libelle = $this->lecture->secteurs()->get(str_replace('-', '_', $secteur));
        abort_if($libelle === null, 404);
        $code = str_replace('-', '_', $secteur);

        return view('portail.investir.secteur', [
            'code' => $code,
            'libelle' => $libelle,
            'opportunites' => $this->lecture->contenus('opportunite')->filter(fn ($o) => $o->get('attributs.secteur') === $code)->values(),
            'projets' => $this->lecture->projets(['secteur' => $code]),
        ]);
    }

    public function implanter(): View
    {
        return $this->pageEditoriale('s-implanter', 'S’implanter à Makary', 'Procédures, foncier, fiscalité locale et interlocuteur unique pour les investisseurs.', [
            'action' => ['route' => 'investir.interet', 'libelle' => 'Manifester mon intérêt'],
        ]);
    }
}

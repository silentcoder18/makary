<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/** M02 — Le Maire et l'action municipale ; la mairie (exécutif, conseil, anciens maires, services). */
class MairieController extends PortailController
{
    public function leMaire(): View
    {
        return view('portail.mairie.le-maire', [
            'maire' => $this->lecture->maire(),
            'motDuMaire' => $this->lecture->page('mot-du-maire'),
            'vision' => $this->lecture->page('vision-et-priorites'),
            'realisations' => $this->lecture->projets(['statut' => 'acheve'])->take(3),
        ]);
    }

    public function vision(): View
    {
        return $this->pageEditoriale('vision-et-priorites', 'Vision et priorités', 'Les orientations du mandat et leurs priorités.');
    }

    /** Bilans : uniquement les réalisations validées « achevé » du catalogue, jamais une liste tenue à la main. */
    public function bilans(): View
    {
        return view('portail.mairie.bilans', ['realisations' => $this->lecture->projets(['statut' => 'acheve'])]);
    }

    public function executif(): View
    {
        return view('portail.mairie.elus', [
            'titre' => 'L’exécutif municipal', 'chapeau' => 'Le Maire et ses adjoints.', 'elus' => $this->lecture->elus(['maire', 'executif']),
        ]);
    }

    public function conseil(): View
    {
        return view('portail.mairie.elus', [
            'titre' => 'Le conseil municipal', 'chapeau' => 'Les conseillers municipaux élus.', 'elus' => $this->lecture->elus(['conseil_municipal']),
        ]);
    }

    public function anciens(): View
    {
        return view('portail.mairie.elus', [
            'titre' => 'Les anciens maires', 'chapeau' => 'Celles et ceux qui ont dirigé la commune.', 'elus' => $this->lecture->elus(['ancien_maire', 'ancien_executif']),
        ]);
    }

    public function organigramme(): View
    {
        return $this->pageEditoriale('organigramme', 'Organigramme', 'L’organisation des services de la mairie.');
    }

    public function services(): View
    {
        return view('portail.mairie.services', ['services' => $this->lecture->contenus('service')]);
    }

    public function service(string $service): View
    {
        return view('portail.contenu', [
            'contenu' => $this->introuvable($this->lecture->contenu('service', $service)),
            'surtitre' => 'Services municipaux',
        ]);
    }
}

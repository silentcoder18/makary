<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/** M08 — Patrimoine et tourisme : sites, galerie, culture et informations pratiques. */
class PatrimoineController extends PortailController
{
    public function index(): View
    {
        return view('portail.patrimoine.index', [
            'sites' => $this->lecture->contenus('site_patrimoine'),
            'medias' => $this->lecture->medias(),
        ]);
    }

    public function site(string $site): View
    {
        return view('portail.contenu', [
            'contenu' => $this->introuvable($this->lecture->contenu('site_patrimoine', $site)),
            'surtitre' => 'Patrimoine et tourisme',
        ]);
    }

    public function culture(): View
    {
        return $this->pageEditoriale('culture-et-histoire', 'Culture et histoire', 'Traditions, savoir-faire et mémoire de la commune.');
    }

    public function pratique(): View
    {
        return $this->pageEditoriale('informations-pratiques', 'Informations pratiques', 'Venir à Makary, se loger, se déplacer, les contacts utiles.');
    }
}

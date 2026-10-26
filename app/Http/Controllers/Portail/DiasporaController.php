<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/** M06 — Diaspora : contribuer par l'expertise, les projets ou l'investissement ; suivi par numéro, sans compte. */
class DiasporaController extends PortailController
{
    public function index(): View
    {
        return view('portail.diaspora.index', [
            'presentation' => $this->lecture->page('diaspora'),
            'projets' => $this->lecture->besoinsDeFinancement()->take(3),
        ]);
    }

    public function contribuer(): View
    {
        return $this->pageEditoriale('contribuer', 'Contribuer depuis la diaspora', 'Expertise, parrainage de projets, investissement : les façons de contribuer.', [
            'action' => ['route' => 'diaspora.interet', 'libelle' => 'Déclarer mon intérêt'],
        ]);
    }
}

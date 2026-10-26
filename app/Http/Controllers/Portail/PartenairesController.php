<?php

namespace App\Http\Controllers\Portail;

use Illuminate\View\View;

/** M05 — Partenariats et bailleurs : besoins de financement et partenaires (cités avec leur accord). */
class PartenairesController extends PortailController
{
    public function index(): View
    {
        return view('portail.partenaires.index', [
            'besoins' => $this->lecture->besoinsDeFinancement()->take(3),
            'partenaires' => $this->lecture->partenaires(),
        ]);
    }

    public function besoins(): View
    {
        return view('portail.partenaires.besoins', ['projets' => $this->lecture->besoinsDeFinancement()]);
    }

    public function liste(): View
    {
        return view('portail.partenaires.liste', ['partenaires' => $this->lecture->partenaires()]);
    }
}

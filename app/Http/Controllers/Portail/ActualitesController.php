<?php

namespace App\Http\Controllers\Portail;

use Illuminate\Http\Request;
use Illuminate\View\View;

/** M10 — Actualités (adresses par année de première publication) et événements (agenda filtrable). */
class ActualitesController extends PortailController
{
    public const PERIODES = ['a-venir' => 'À venir', 'cette-semaine' => 'Cette semaine', 'ce-mois-ci' => 'Ce mois-ci', 'passes' => 'Passés'];

    public function index(): View
    {
        return view('portail.actualites.index', [
            'actualites' => $this->lecture->actualites(),
            'annees' => $this->lecture->anneesActualites(),
            'annee' => null,
            'evenements' => $this->lecture->evenements('a-venir', 3),
        ]);
    }

    public function annee(int $annee): View
    {
        $actualites = $this->lecture->actualites($annee);
        abort_if($actualites->total() === 0, 404);

        return view('portail.actualites.index', [
            'actualites' => $actualites, 'annees' => $this->lecture->anneesActualites(), 'annee' => $annee, 'evenements' => collect(),
        ]);
    }

    public function article(int $annee, string $article): View
    {
        return view('portail.contenu', [
            'contenu' => $this->introuvable($this->lecture->actualite($annee, $article)),
            'surtitre' => 'Actualités',
        ]);
    }

    public function evenements(Request $request): View
    {
        $periode = array_key_exists($request->query('periode'), self::PERIODES) ? $request->query('periode') : 'a-venir';

        return view('portail.actualites.evenements', [
            'evenements' => $this->lecture->evenements($periode),
            'periode' => $periode,
            'periodes' => self::PERIODES,
        ]);
    }

    public function evenement(string $evenement): View
    {
        return view('portail.contenu', [
            'contenu' => $this->introuvable($this->lecture->contenu('evenement', $evenement)),
            'surtitre' => 'Événements',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Portail;

use App\Domaine\Enums\StatutProjet;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\Statistiques\Compteur;
use App\Portail\FichePdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** M03 — Un seul catalogue de projets ; les réalisations en sont la vue « achevé » (arborescence, principe 3). */
class ProjetsController extends PortailController
{
    public function index(Request $request): View
    {
        // Les valeurs d'URL s'écrivent avec des tirets (?statut=en-execution) ; les codes, avec des soulignés.
        $statut = StatutProjet::tryFrom(str_replace('-', '_', (string) $request->query('statut')));
        $secteur = $this->lecture->secteurs()->has($request->query('secteur')) ? $request->query('secteur') : null;
        $localite = $this->lecture->localites()->firstWhere('slug', $request->query('localite'))?->get('slug');

        return view('portail.projets.index', [
            'projets' => $this->lecture->projets(['statut' => $statut?->value, 'secteur' => $secteur, 'localite' => $localite]),
            'filtres' => ['statut' => $statut, 'secteur' => $secteur, 'localite' => $localite],
            'statuts' => StatutProjet::cases(),
            'secteurs' => $this->lecture->secteurs(),
            'localites' => $this->lecture->localites(),
        ]);
    }

    public function realisations(): View
    {
        return view('portail.projets.realisations', ['realisations' => $this->lecture->projets(['statut' => StatutProjet::Acheve->value])]);
    }

    public function show(string $projet): View
    {
        return view('portail.projets.show', ['projet' => $this->introuvable($this->lecture->projet($projet))]);
    }

    /** Fiche de financement (fiche projet §4), générée depuis la fiche publiée, jamais saisie à la main. */
    public function fichePdf(Request $request, string $projet, FichePdf $pdf, Compteur $compteur): Response
    {
        $fiche = $this->introuvable($this->lecture->projet($projet));
        // Indicateur du §12 : téléchargements de fiches, comptés par jour, sans donnée personnelle.
        $compteur->incrementer($request, 'telechargement_fiche', TypeObjet::Projet, $fiche->objetId);

        return response($pdf->generer($fiche, route('projets.show', $projet)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="fiche-'.$fiche['code'].'.pdf"',
        ]);
    }
}

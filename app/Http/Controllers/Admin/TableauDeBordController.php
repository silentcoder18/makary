<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Statistiques\Indicateurs;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tableau de bord : ce qui attend l'agent, et les indicateurs du §12, calculés et jamais saisis. */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request, Indicateurs $indicateurs): View
    {
        $agent = $request->user();

        return view('admin.tableau-de-bord', [
            'aTraiter' => $indicateurs->aTraiter($agent),
            'indicateurs' => $agent->can('statistiques.consulter') ? $indicateurs->mois() : null,
            'activite' => $agent->can('journal.consulter') || $agent->can('statistiques.consulter') ? $indicateurs->activite() : collect(),
        ]);
    }
}

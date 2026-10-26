<?php

namespace App\Http\Controllers\Portail;

use App\Portail\Navigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pages transversales : recherche, plan du site, informations légales. */
class PagesController extends PortailController
{
    public function recherche(Request $request): View
    {
        $terme = trim(mb_substr((string) $request->query('q'), 0, 100));

        return view('portail.recherche', [
            'terme' => $terme,
            'resultats' => mb_strlen($terme) >= 2 ? $this->lecture->rechercher($terme) : collect(),
        ]);
    }

    public function planDuSite(): View
    {
        return view('portail.plan-du-site');
    }

    public function information(string $page): View
    {
        $chapeaux = [
            'mentions-legales' => 'Éditeur du site, directeur de la publication, hébergeur.',
            'confidentialite' => 'Données personnelles collectées par ce site, finalités, durées de conservation et droits.',
            'accessibilite' => 'Niveau d’accessibilité du site et moyens de signaler une difficulté.',
            'credits' => 'Auteurs des photographies, illustrations et contenus, et licences des logiciels utilisés.',
        ];

        return $this->pageEditoriale($page, Navigation::INFORMATIONS[$page], $chapeaux[$page]);
    }
}

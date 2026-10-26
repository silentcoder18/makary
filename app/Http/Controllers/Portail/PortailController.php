<?php

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Portail\Fiche;
use App\Portail\Lecture;
use Illuminate\View\View;

/** Base des contrôleurs du portail : lecture des seules publications en ligne. */
abstract class PortailController extends Controller
{
    public function __construct(protected readonly Lecture $lecture) {}

    /**
     * Page de rubrique éditoriale (type « page ») : la version publiée, ou l'emplacement « Contenu à fournir par la Commune ».
     *
     * @param  array<string, mixed>  $donnees
     */
    protected function pageEditoriale(string $slug, string $titre, string $chapeau, array $donnees = []): View
    {
        return view('portail.page', ['page' => $this->lecture->page($slug), 'titre' => $titre, 'chapeau' => $chapeau, ...$donnees]);
    }

    protected function introuvable(?Fiche $fiche): Fiche
    {
        abort_if($fiche === null, 404);

        return $fiche;
    }
}

<?php

namespace App\Http\Controllers\Portail;

use App\Domaine\Enums\TypeObjet;
use App\Domaine\Statistiques\Compteur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** M07 — Centre documentaire : filtres par catégorie et par année, notice et téléchargement. Aucune saisie de coordonnées exigée. */
class DocumentsController extends PortailController
{
    public function index(Request $request): View
    {
        $categories = $this->lecture->categoriesDocuments();
        $categorie = $categories->has($request->query('categorie')) ? $request->query('categorie') : null;
        $annee = preg_match('/^\d{4}$/', (string) $request->query('annee')) ? (int) $request->query('annee') : null;

        return $this->liste($categorie, $annee);
    }

    /** /documents/{categorie} (catégorie, en tirets) ou /documents/{document} (notice). */
    public function show(string $element): View
    {
        $code = str_replace('-', '_', $element);
        if ($this->lecture->categoriesDocuments()->has($code)) {
            return $this->liste($code, null);
        }

        return view('portail.documents.show', ['document' => $this->introuvable($this->lecture->document($element))]);
    }

    public function telecharger(Request $request, string $document, Compteur $compteur): RedirectResponse
    {
        $fiche = $this->introuvable($this->lecture->document($document));
        $fichier = $fiche->get('version.fichier');
        abort_if(! $fichier, 404);
        $compteur->incrementer($request, 'telechargement_document', TypeObjet::Document, $fiche->objetId);

        return redirect()->route('fichier', ['fichier' => $fichier['id'], 'nom' => $fichier['nom']]);
    }

    private function liste(?string $categorie, ?int $annee): View
    {
        $tous = $this->lecture->documents();

        return view('portail.documents.index', [
            'documents' => $this->lecture->documents(['categorie' => $categorie, 'annee' => $annee]),
            'categories' => $this->lecture->categoriesDocuments(),
            'annees' => $tous->map(fn ($d) => (int) substr((string) $d['date_document'], 0, 4))->unique()->sortDesc()->values(),
            'filtres' => ['categorie' => $categorie, 'annee' => $annee],
        ]);
    }
}

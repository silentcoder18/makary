<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fichier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aperçu d'un fichier téléversé, réservé aux agents qui saisissent ou valident les contenus (MKY-P3-08).
 * Images : toujours (recompressées au téléversement, elles ne contiennent que des pixels). Autres fichiers : seulement
 * s'ils ont été analysés par l'antivirus. Aucun script ne peut s'exécuter dans le fichier ouvert seul.
 */
class FichiersController extends Controller
{
    public function __invoke(Request $request, Fichier $fichier): StreamedResponse
    {
        abort_unless(Gate::any(['contenu.lire_brouillons', 'media.televerser', 'document.gerer', 'partenaire.modifier', 'referentiel.modifier']), 403);
        $image = str_starts_with($fichier->type_mime, 'image/');
        // Image matricielle recompressée : visible avant l'analyse ; tout autre fichier (PDF, SVG) seulement une fois déclaré sain (ou si antivirus non requis).
        $recompressee = in_array($fichier->type_mime, ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true)
            && ($fichier->analyse_ok === null || ! config('makary.antivirus.requis', true));
        $analyseOk = $fichier->analyse_ok === true || $recompressee || (! config('makary.antivirus.requis', true) && $fichier->type_mime === 'application/pdf');
        abort_unless($analyseOk && Storage::disk('local')->exists($fichier->chemin_stockage), 404);

        return Storage::disk('local')->response($fichier->chemin_stockage, $fichier->nom_original, [
            'Content-Type' => $fichier->type_mime,
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $image ? 'inline' : 'attachment');
    }
}

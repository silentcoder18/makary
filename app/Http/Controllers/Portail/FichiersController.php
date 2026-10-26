<?php

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Models\Fichier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert un fichier seulement s'il figure dans une publication en ligne (média, image d'un contenu, photo d'un élu,
 * version d'un document…). Un fichier non publié, retiré ou interne répond 404, comme s'il n'existait pas.
 */
class FichiersController extends Controller
{
    public function __invoke(int $fichier, string $nom): StreamedResponse
    {
        $id = $fichier;
        try {
            $publie = DB::table('publication')->whereNull('retire_le')->where('publie_le', '<=', now())
                // Tous les « fichier.id » de l'instantané, à toute profondeur.
                ->whereRaw("JSON_CONTAINS(JSON_EXTRACT(instantane, '$**.fichier.id'), ?)", [json_encode($id)])
                ->exists();
        } catch (\Throwable) {
            $publie = false;
        }

        // Si la requête avec wildcard ** échoue ou ne trouve rien (ex. MariaDB sans support de ** dans JSON_EXTRACT),
        // recherche par correspondance textuelle sécurisée sur la structure JSON.
        if (! $publie) {
            $publie = DB::table('publication')->whereNull('retire_le')->where('publie_le', '<=', now())
                ->where(function ($q) use ($id) {
                    $q->where('instantane', 'like', '%"fichier":%"id":'.$id.'%')
                        ->orWhere('instantane', 'like', '%"fichier":{"id":'.$id.'%')
                        ->orWhere('instantane', 'like', '%"fichier":{"id": '.$id.'%')
                        ->orWhere('instantane', 'like', '%"id":'.$id.',%')
                        ->orWhere('instantane', 'like', '%"id":'.$id.'}%');
                })
                ->exists();
        }

        $enregistrement = $publie ? Fichier::find($fichier) : null;
        $recompressee = $enregistrement && in_array($enregistrement->type_mime, ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true)
            && ! config('makary.antivirus.requis', true);
        $analyseOk = $enregistrement && ($enregistrement->analyse_ok === true || $recompressee);

        abort_if(! $enregistrement || ! $analyseOk || ! Storage::disk('local')->exists($enregistrement->chemin_stockage), 404);

        $image = str_starts_with($enregistrement->type_mime, 'image/');

        return Storage::disk('local')->response($enregistrement->chemin_stockage, $enregistrement->nom_original, [
            'Content-Type' => $enregistrement->type_mime,
            'Cache-Control' => 'public, max-age=86400',
            // Un fichier ouvert seul ne peut exécuter aucun script, même une image SVG.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $image ? 'inline' : 'attachment');
    }
}

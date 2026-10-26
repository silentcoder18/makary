<?php

use App\Http\Controllers\Api\PubliqueController;
use Illuminate\Support\Facades\Route;

/*
| API publique v1 (MKY-P3-03) : opérations /public/… du contrat specs/api/openapi.yaml, sous /api/v1.
| Lecture des seules publications approuvées ; dépôt et suivi des demandes, au débit limité.
*/
Route::prefix('public')->controller(PubliqueController::class)->group(function () {
    // Lectures : mises en cache cinq minutes, avec ETag (réponse 304 si rien n'a changé).
    Route::middleware('cache.headers:public;max_age=300;etag')->group(function () {
        Route::get('projets', 'projets');
        Route::get('projets/{slug}', 'projet');
        Route::get('contenus', 'contenus');
        Route::get('contenus/{type}/{slug}', 'contenu');
        Route::get('elus', 'elus');
        Route::get('documents', 'documents');
        Route::get('documents/{slug}', 'document');
        Route::get('localites', 'localites');
        Route::get('carte', 'carte');
        Route::get('partenaires', 'partenaires');
        Route::get('coordonnees', 'coordonnees');
        Route::get('recherche', 'recherche');
    });
    // Fiche PDF hors cache : chaque téléchargement est compté (indicateur du §12).
    Route::get('projets/{slug}/fiche.pdf', 'fichePdf');
    Route::post('demandes', 'deposerDemande')->middleware('throttle:formulaires');
    Route::post('demandes/suivi', 'suivreDemande')->middleware('throttle:suivi');
});

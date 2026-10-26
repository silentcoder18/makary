<?php

namespace App\Domaine\Statistiques;

use App\Domaine\Enums\TypeObjet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Comptages agrégés par jour (§12), sans aucune donnée personnelle : ni adresse IP, ni identifiant de visiteur.
 * Les robots identifiés par leur agent utilisateur ne sont pas comptés.
 */
class Compteur
{
    public function incrementer(Request $request, string $mesure, TypeObjet $type, int $objetId): void
    {
        if (preg_match('/bot|crawl|spider|slurp|preview|headless/i', (string) $request->userAgent())) {
            return;
        }
        DB::table('statistique_jour')->upsert(
            [['jour' => now()->toDateString(), 'mesure' => $mesure, 'objet_type' => $type->value, 'objet_id' => $objetId, 'chemin' => null, 'nombre' => 1]],
            ['jour', 'mesure', 'objet_type', 'objet_id', 'chemin'],
            ['nombre' => DB::raw('statistique_jour.nombre + 1')],
        );
    }
}

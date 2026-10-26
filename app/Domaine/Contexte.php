<?php

namespace App\Domaine;

use App\Models\Utilisateur;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Exécute une opération métier dans une transaction, au nom d'un agent.
 * L'agent est transmis à la base (variable de session @app_utilisateur_id) pour l'historique des modifications (§8).
 */
class Contexte
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public static function enTantQue(Utilisateur $agent, Closure $operation): mixed
    {
        return DB::transaction(function () use ($agent, $operation) {
            $precedent = DB::selectOne('SELECT @app_utilisateur_id AS id')->id;
            DB::statement('SET @app_utilisateur_id = ?', [$agent->getKey()]);
            try {
                return $operation();
            } finally {
                // Une variable de session ne suit pas la transaction : on rend toujours la main à l'agent englobant
                // (appel imbriqué) ou à personne, y compris en cas d'erreur.
                DB::statement('SET @app_utilisateur_id = ?', [$precedent]);
            }
        });
    }
}

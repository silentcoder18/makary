<?php

namespace App\Securite;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Journal d'audit en ajout seul (M12, §9). La base refuse toute modification (déclencheurs MySQL/MariaDB).
 */
class Journal
{
    /** @param  array<string, mixed>  $details */
    public static function consigner(string $action, ?Model $objet = null, array $details = [], ?int $utilisateurId = null): void
    {
        DB::table('journal_audit')->insert([
            'utilisateur_id' => $utilisateurId ?? Auth::id(),
            'action' => $action,
            'objet_type' => $objet ? $objet->getTable() : null,
            'objet_id' => $objet?->getKey(),
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'adresse_ip' => Request::ip(),
            'conserver_jusqu_au' => now()->addDays((int) config('makary.conservation.journal_jours'))->toDateString(),
        ]);
    }
}

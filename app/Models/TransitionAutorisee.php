<?php

namespace App\Models;

use App\Domaine\Enums\StatutProjet;
use Illuminate\Database\Eloquent\Model;

/** Transition de statut autorisée et périmètre du validateur (fiche projet §3). Référentiel figé. */
class TransitionAutorisee extends Model
{
    protected $table = 'transition_autorisee';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'de_statut' => StatutProjet::class,
            'vers_statut' => StatutProjet::class,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Historique des modifications, tenu par la base (§8). Lecture seule. */
class HistoriqueModification extends Model
{
    protected $table = 'historique_modification';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ancien' => 'array',
            'nouveau' => 'array',
            'horodatage' => 'datetime',
        ];
    }
}

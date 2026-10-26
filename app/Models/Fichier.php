<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Fichier stocké (privé par défaut), avec son empreinte SHA-256. */
class Fichier extends Model
{
    protected $table = 'fichier';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'taille_octets' => 'integer',
            'prive' => 'boolean',
            'analyse_ok' => 'boolean',
            'televerse_le' => 'datetime',
        ];
    }
}

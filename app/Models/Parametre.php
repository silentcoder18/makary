<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Paramètre de configuration éditoriale ou technique. */
class Parametre extends Model
{
    protected $table = 'parametre';

    public $timestamps = false;

    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valeur' => 'array',
            'modifie_le' => 'datetime',
        ];
    }
}

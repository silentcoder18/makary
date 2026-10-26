<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Secteur d'un projet (liste fermée, fiche projet champ 3). */
class Secteur extends Model
{
    protected $table = 'secteur';

    public $timestamps = false;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}

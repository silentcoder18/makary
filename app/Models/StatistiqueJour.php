<?php

namespace App\Models;

use App\Domaine\Enums\TypeObjet;
use Illuminate\Database\Eloquent\Model;

/** Comptage agrégé par jour, sans donnée personnelle (§12). */
class StatistiqueJour extends Model
{
    protected $table = 'statistique_jour';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jour' => 'date',
            'nombre' => 'integer',
            'objet_type' => TypeObjet::class,
        ];
    }
}

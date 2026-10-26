<?php

namespace App\Models;

use App\Domaine\Enums\BaseLegale;
use Illuminate\Database\Eloquent\Model;

/** Registre des traitements de données personnelles (E-02). */
class Traitement extends Model
{
    protected $table = 'traitement';

    public $timestamps = false;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base' => BaseLegale::class,
            'duree_conservation_jours' => 'integer',
            'transfert_hors_cameroun' => 'boolean',
            'valide_par_rj' => 'boolean',
        ];
    }
}

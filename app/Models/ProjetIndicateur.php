<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Indicateur de résultat et méthode de mesure (fiche projet champ 13). */
class ProjetIndicateur extends Model
{
    protected $table = 'projet_indicateur';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valeur_reference' => 'float',
            'cible' => 'float',
            'valeur_atteinte' => 'float',
            'date_mesure' => 'date',
        ];
    }

    /** @return BelongsTo<Projet, $this> */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }
}

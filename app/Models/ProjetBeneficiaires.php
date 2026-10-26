<?php

namespace App\Models;

use App\Domaine\Enums\NatureInformation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bénéficiaires ciblés, avec la méthode d'estimation (fiche projet champ 6). */
class ProjetBeneficiaires extends Model
{
    protected $table = 'projet_beneficiaires';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nombre' => 'integer',
            'date_estimation' => 'date',
            'nature' => NatureInformation::class,
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

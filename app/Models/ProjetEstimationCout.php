<?php

namespace App\Models;

use App\Domaine\Enums\NatureInformation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Estimation du coût : montant, devise, date et source obligatoires (§8). */
class ProjetEstimationCout extends Model
{
    protected $table = 'projet_estimation_cout';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
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

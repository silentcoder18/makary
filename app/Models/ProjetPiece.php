<?php

namespace App\Models;

use App\Domaine\Enums\DroitDiffusion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pièce d'un projet (étude, autorisation, convention, procès-verbal…). */
class ProjetPiece extends Model
{
    protected $table = 'projet_piece';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'diffusion' => DroitDiffusion::class,
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

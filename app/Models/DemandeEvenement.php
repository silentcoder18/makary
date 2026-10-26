<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Événement du suivi d'une demande (qualification, affectation, réponse…). */
class DemandeEvenement extends Model
{
    protected $table = 'demande_evenement';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Demande, $this> */
    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class, 'demande_id');
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'par');
    }
}

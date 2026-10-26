<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Source d'une information chiffrée ou factuelle (§8 : « source des chiffres »). */
class Source extends Model
{
    protected $table = 'source';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_source' => 'date',
            'cree_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Fichier, $this> */
    public function fichier(): BelongsTo
    {
        return $this->belongsTo(Fichier::class, 'fichier_id');
    }
}

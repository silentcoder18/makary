<?php

namespace App\Models;

use App\Domaine\Enums\DecisionValidation;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\ObjetsPubliables;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Demande de validation d'un objet publiable, avec l'instantané soumis (migration 0002).
 * Une décision rendue est définitive ; nul n'approuve sa propre soumission (contraintes de la base).
 */
class ValidationDemande extends Model
{
    protected $table = 'validation_demande';

    public $timestamps = false;

    protected $guarded = ['id', 'decision', 'decide_par', 'decide_le'];

    protected function casts(): array
    {
        return [
            'objet_type' => TypeObjet::class,
            'decision' => DecisionValidation::class,
            'instantane' => 'array',
            'soumis_le' => 'datetime',
            'decide_le' => 'datetime',
        ];
    }

    /** @return Model&Publiable */
    public function objet(): Model
    {
        return ObjetsPubliables::trouver($this->objet_type, $this->objet_id);
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function soumisPar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'soumis_par');
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function decidePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'decide_par');
    }

    /** @return HasOne<Publication, $this> */
    public function publication(): HasOne
    {
        return $this->hasOne(Publication::class, 'validation_id');
    }
}

<?php

namespace App\Models;

use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\EstPubliable;
use App\Domaine\ObjetsPubliables;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Équipement public (école, centre de santé, marché, forage…) rattaché à une localité (M09). */
class Equipement extends Model implements Publiable
{
    use EstPubliable;

    public const TYPE_OBJET = TypeObjet::Equipement;

    protected $table = 'equipement';

    public $timestamps = false;

    protected $guarded = ['id', 'etat'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'etat' => EtatEditorial::class,
        ];
    }

    /** @return BelongsTo<Localite, $this> */
    public function localite(): BelongsTo
    {
        return $this->belongsTo(Localite::class, 'localite_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function perimetres(): array
    {
        return ['M09', 'M01'];
    }

    public function instantanePublic(): array
    {
        return [
            'type_equipement' => $this->type_equipement,
            'nom' => $this->nom,
            'localite' => $this->localite->reference(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'source' => ObjetsPubliables::source($this->source),
        ];
    }
}

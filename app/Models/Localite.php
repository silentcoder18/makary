<?php

namespace App\Models;

use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\EstPubliable;
use App\Domaine\ObjetsPubliables;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Localité de la liste officielle (C-013), chacune avec sa source ; coordonnées seulement si sourcées. */
class Localite extends Model implements Publiable
{
    use EstPubliable;

    public const TYPE_OBJET = TypeObjet::Localite;

    protected $table = 'localite';

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

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    /** Source de la position (relevé GPS, carte officielle), obligatoire dès qu'une position est saisie. @return BelongsTo<Source, $this> */
    public function coordonneesSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'coordonnees_source');
    }

    /** @return HasMany<Equipement, $this> */
    public function equipements(): HasMany
    {
        return $this->hasMany(Equipement::class, 'localite_id');
    }

    public function perimetres(): array
    {
        return ['M01', 'M09'];
    }

    public function instantanePublic(): array
    {
        return [
            'nom' => $this->nom,
            'slug' => $this->slug,
            'categorie' => $this->categorie,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'source' => ObjetsPubliables::source($this->source),
        ];
    }

    /** Référence publique d'une localité citée dans un autre instantané. */
    public function reference(): array
    {
        return ['nom' => $this->nom, 'slug' => $this->slug, 'categorie' => $this->categorie];
    }
}

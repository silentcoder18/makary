<?php

namespace App\Models;

use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\EstPubliable;
use App\Domaine\ObjetsPubliables;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Élu ou ancien élu (M02) : publié dans sa fonction, avec l'acte de désignation en source.
 * Les champs de conservation (E-14) restent internes.
 */
class Elu extends Model implements Publiable
{
    use EstPubliable;

    public const TYPE_OBJET = TypeObjet::Elu;

    protected $table = 'elu';

    public $timestamps = false;

    protected $guarded = ['id', 'etat'];

    protected function casts(): array
    {
        return [
            'mandat_debut' => 'date',
            'mandat_fin' => 'date',
            'conserver_jusqu_au' => 'date',
            'etat' => EtatEditorial::class,
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function perimetres(): array
    {
        return ['M02'];
    }

    public function instantanePublic(): array
    {
        return [
            'nom_affiche' => $this->nom_affiche,
            'fonction' => $this->fonction,
            'organe' => $this->organe,
            'mandat_debut' => $this->mandat_debut->toDateString(),
            'mandat_fin' => $this->mandat_fin?->toDateString(),
            'biographie' => $this->biographie,
            'photo' => Media::versionPublique($this->photo),
            'source' => ObjetsPubliables::source($this->source),
        ];
    }

    public function empechementPublication(): ?string
    {
        return $this->photo?->empechementPublication();
    }
}

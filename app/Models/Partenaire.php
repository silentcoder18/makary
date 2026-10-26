<?php

namespace App\Models;

use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\EstPubliable;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Partenaire ou financeur (M05) : cité publiquement seulement avec son accord écrit. */
class Partenaire extends Model implements Publiable
{
    use EstPubliable;

    public const TYPE_OBJET = TypeObjet::Partenaire;

    protected $table = 'partenaire';

    public $timestamps = false;

    protected $guarded = ['id', 'etat'];

    protected function casts(): array
    {
        return [
            'accepte_citation' => 'boolean',
            'etat' => EtatEditorial::class,
        ];
    }

    /** @return BelongsTo<Source, $this> */
    public function accordCitation(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'accord_citation_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_id');
    }

    public function perimetres(): array
    {
        return ['M05'];
    }

    public function instantanePublic(): array
    {
        return [
            'nom' => $this->nom,
            'type_partenaire' => $this->type_partenaire,
            'pays' => $this->pays,
            'site_web' => $this->site_web,
            'logo' => Media::versionPublique($this->logo),
        ];
    }

    public function empechementPublication(): ?string
    {
        return $this->accepte_citation
            ? $this->logo?->empechementPublication()
            : "Le partenaire n'a pas donné son accord écrit pour être cité.";
    }

    /** Nom affichable dans une fiche publique : seulement si le partenaire accepte d'être cité. */
    public function nomCitable(): ?string
    {
        return $this->accepte_citation ? $this->nom : null;
    }
}

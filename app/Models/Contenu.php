<?php

namespace App\Models;

use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\EstPubliable;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contenu éditorial : page, actualité, événement, site du patrimoine, service, démarche, opportunité.
 * Le corps est en Markdown ; son rendu HTML est assaini à l'affichage.
 */
class Contenu extends Model implements Publiable
{
    use EstPubliable;

    public const TYPE_OBJET = TypeObjet::Contenu;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'modifie_le';

    protected $table = 'contenu';

    protected $guarded = ['id', 'etat', 'auteur_id'];

    protected function casts(): array
    {
        return [
            'attributs' => 'array',
            'etat' => EtatEditorial::class,
            'publication_programmee' => 'datetime',
        ];
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'auteur_id');
    }

    /** @return BelongsTo<Localite, $this> */
    public function localite(): BelongsTo
    {
        return $this->belongsTo(Localite::class, 'localite_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function auteurId(): ?int
    {
        return $this->auteur_id;
    }

    public function perimetres(): array
    {
        return [$this->module];
    }

    public function instantanePublic(): array
    {
        return [
            'type_contenu' => $this->type_contenu,
            'module' => $this->module,
            'slug' => $this->slug,
            'titre' => $this->titre,
            'resume' => $this->resume,
            'corps' => $this->corps,
            'attributs' => $this->attributs ?? [],
            'localite' => $this->localite?->reference(),
            'image' => Media::versionPublique($this->image),
            'mis_a_jour_le' => $this->modifie_le?->toIso8601String(),
        ];
    }

    public function empechementPublication(): ?string
    {
        return $this->image && $this->image->empechementPublication() !== null
            ? $this->image->empechementPublication()
            : null;
    }
}

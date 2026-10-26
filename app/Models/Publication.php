<?php

namespace App\Models;

use App\Domaine\Enums\TypeObjet;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instantané public d'un objet, issu d'une validation approuvée. Seule source du portail public.
 * La base refuse toute modification autre que le retrait (migration 0002).
 */
class Publication extends Model
{
    protected $table = 'publication';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'objet_type' => TypeObjet::class,
            'version' => 'integer',
            'instantane' => 'array',
            'publie_le' => 'datetime',
            'retire_le' => 'datetime',
        ];
    }

    /** Versions en ligne : non retirées, dont la date de publication (éventuellement programmée) est atteinte. */
    #[Scope]
    protected function enLigne(Builder $requete): void
    {
        $requete->whereNull('retire_le')->where('publie_le', '<=', now());
    }

    /** Version courante de chaque objet d'un type, telle que le portail l'affiche. */
    #[Scope]
    protected function courantes(Builder $requete, TypeObjet $type): void
    {
        // L'heure de référence est celle de l'application, comme pour enLigne() : now() côté base serait l'heure de début de la transaction.
        $maintenant = now();
        $requete->enLigne()->where('objet_type', $type->value)->whereRaw(
            'version = (SELECT max(x.version) FROM publication x WHERE x.objet_type = publication.objet_type
                AND x.objet_id = publication.objet_id AND x.retire_le IS NULL AND x.publie_le <= ?)', [$maintenant]
        );
    }

    /** @return BelongsTo<ValidationDemande, $this> */
    public function validation(): BelongsTo
    {
        return $this->belongsTo(ValidationDemande::class, 'validation_id');
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'publie_par');
    }
}

<?php

namespace App\Models;

use App\Domaine\Enums\StatutLigneFinancement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de financement d'un projet : « recherché », « sollicité » ou « acquis ».
 * Une ligne ne devient « acquise » que par le service de financement, avec sa pièce et la validation d'un référent (§8).
 */
class ProjetFinancement extends Model
{
    protected $table = 'projet_financement';

    public $timestamps = false;

    protected $guarded = ['id', 'statut_ligne', 'piece_id', 'valide_par', 'valide_le'];

    protected function casts(): array
    {
        return [
            'statut_ligne' => StatutLigneFinancement::class,
            'montant' => 'decimal:2',
            'date_information' => 'date',
            'valide_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Projet, $this> */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }

    /** @return BelongsTo<Partenaire, $this> */
    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(Partenaire::class, 'partenaire_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'piece_id');
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'valide_par');
    }
}

<?php

namespace App\Models;

use App\Domaine\Enums\DecisionValidation;
use App\Domaine\Enums\StatutProjet;
use App\Securite\ObjetAPerimetre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de changement de statut d'un projet. Le périmètre du validateur est celui de la transition :
 * « projets » (référent des projets et financements) ou « realisations » (référent des réalisations).
 */
class ProjetTransition extends Model implements ObjetAPerimetre
{
    protected $table = 'projet_transition';

    public $timestamps = false;

    protected $guarded = ['id', 'decision', 'valide_par', 'valide_le', 'applique_le'];

    protected function casts(): array
    {
        return [
            'de_statut' => StatutProjet::class,
            'vers_statut' => StatutProjet::class,
            'decision' => DecisionValidation::class,
            'demande_le' => 'datetime',
            'valide_le' => 'datetime',
            'applique_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Projet, $this> */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }

    /** @return BelongsTo<ProjetPiece, $this> */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(ProjetPiece::class, 'piece_id');
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function demandePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'demande_par');
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'valide_par');
    }

    public function regle(): TransitionAutorisee
    {
        return TransitionAutorisee::where('de_statut', $this->de_statut->value)->where('vers_statut', $this->vers_statut->value)->firstOrFail();
    }

    public function perimetres(): array
    {
        return [$this->regle()->perimetre];
    }

    public function auteurId(): ?int
    {
        return $this->demande_par;
    }
}

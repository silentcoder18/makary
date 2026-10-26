<?php

namespace App\Models;

use App\Domaine\Enums\BaseLegale;
use App\Domaine\Enums\StatutDemande;
use App\Domaine\Enums\TypeDemande;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Demande reçue du public (contact, investisseur, partenaire, diaspora), suivie par numéro, sans compte.
 * Données personnelles minimales, anonymisées à la fin de conservation (E-04).
 */
#[Hidden(['nom_contact', 'courriel', 'telephone'])]
class Demande extends Model
{
    protected $table = 'demande';

    public $timestamps = false;

    protected $guarded = ['id', 'numero_suivi', 'statut', 'priorite', 'affectee_a', 'recue_le', 'premiere_reponse_le',
        'cloturee_le', 'conserver_jusqu_au', 'anonymisee_le'];

    protected function casts(): array
    {
        return [
            'type_demande' => TypeDemande::class,
            'base' => BaseLegale::class,
            'statut' => StatutDemande::class,
            'consentement_donne' => 'boolean',
            'priorite' => 'integer',
            'recue_le' => 'datetime',
            'premiere_reponse_le' => 'datetime',
            'cloturee_le' => 'datetime',
            'conserver_jusqu_au' => 'date',
            'anonymisee_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Projet, $this> */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }

    /** @return BelongsTo<Utilisateur, $this> */
    public function affecteeA(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'affectee_a');
    }

    /** @return HasMany<DemandeEvenement, $this> */
    public function evenements(): HasMany
    {
        return $this->hasMany(DemandeEvenement::class, 'demande_id')->orderBy('le');
    }
}

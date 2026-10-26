<?php

namespace App\Domaine;

use App\Models\HistoriqueModification;
use App\Models\Publication;
use App\Models\ValidationDemande;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Implémentation commune aux modèles publiables. Le modèle déclare la constante TYPE_OBJET.
 *
 * @mixin Model
 */
trait EstPubliable
{
    public function typeObjet(): Enums\TypeObjet
    {
        return self::TYPE_OBJET;
    }

    /** @return HasMany<ValidationDemande, $this> */
    public function validations(): HasMany
    {
        return $this->hasMany(ValidationDemande::class, 'objet_id')->where('objet_type', self::TYPE_OBJET->value);
    }

    /** @return HasMany<Publication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'objet_id')->where('objet_type', self::TYPE_OBJET->value);
    }

    /** Version en ligne : la plus récente des versions non retirées dont la date de publication est atteinte. */
    public function publicationCourante(): ?Publication
    {
        return Publication::enLigne()->where('objet_type', self::TYPE_OBJET->value)->where('objet_id', $this->getKey())
            ->orderByDesc('version')->first();
    }

    /** Demande de validation en attente, s'il y en a une (une seule possible). */
    public function validationEnAttente(): ?ValidationDemande
    {
        return $this->validations()->where('decision', Enums\DecisionValidation::EnAttente->value)->first();
    }

    /**
     * Auteur de l'objet : le compte qui l'a créé, d'après l'historique tenu par la base.
     * Les modèles qui ont une colonne d'auteur (contenu) redéfinissent cette méthode.
     */
    public function auteurId(): ?int
    {
        return HistoriqueModification::query()
            ->where('nom_table', $this->getTable())->where('ligne_id', $this->getKey())->where('operation', 'INSERT')
            ->value('utilisateur_id');
    }

    /** Met à jour l'état éditorial (les médias n'en ont pas : leur publication suffit). */
    public function marquerEtat(Enums\EtatEditorial $etat): void
    {
        if ($this->suitUnEtatEditorial()) {
            $this->forceFill(['etat' => $etat])->save();
        }
    }

    protected function suitUnEtatEditorial(): bool
    {
        return true;
    }

    public function empechementPublication(): ?string
    {
        return null;
    }
}

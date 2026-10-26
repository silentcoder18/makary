<?php

namespace App\Domaine;

use App\Domaine\Enums\TypeObjet;
use App\Models\Contenu;
use App\Models\Document;
use App\Models\Elu;
use App\Models\Equipement;
use App\Models\Localite;
use App\Models\Media;
use App\Models\Partenaire;
use App\Models\Projet;
use App\Models\Source;
use Illuminate\Database\Eloquent\Model;

/** Correspondance entre les types d'objets publiables (énumération « type_objet ») et leurs modèles. */
final class ObjetsPubliables
{
    public const MODELES = [
        'contenu' => Contenu::class,
        'projet' => Projet::class,
        'document' => Document::class,
        'elu' => Elu::class,
        'partenaire' => Partenaire::class,
        'localite' => Localite::class,
        'equipement' => Equipement::class,
        'media' => Media::class,
    ];

    /** @return Model&Publiable */
    public static function trouver(TypeObjet $type, int $id): Model
    {
        return (self::MODELES[$type->value])::findOrFail($id);
    }

    /** Champs publics d'une source citée dans un instantané : jamais le fichier ni l'auteur de la saisie. */
    public static function source(?Source $source): ?array
    {
        return $source ? ['intitule' => $source->intitule, 'reference' => $source->reference, 'date' => $source->date_source?->toDateString()] : null;
    }
}

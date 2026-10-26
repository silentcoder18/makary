<?php

namespace App\Portail;

use App\Models\Publication;
use ArrayAccess;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Ce que le portail affiche d'un objet : l'instantané public de sa publication en ligne, en lecture seule.
 * Accès par clé : $fiche['titre'], data_get($fiche, 'statut.libelle').
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class Fiche implements ArrayAccess
{
    /** @param  array<string, mixed>  $donnees */
    public function __construct(
        public array $donnees,
        public int $objetId,
        public int $version,
        public CarbonInterface $publieLe,
        public CarbonInterface $premierePublication,
    ) {}

    public static function depuis(Publication $publication): self
    {
        $premiere = $publication->getAttribute('premiere_publication');

        return new self(
            $publication->instantane,
            $publication->objet_id,
            $publication->version,
            $publication->publie_le,
            $premiere ? Carbon::parse($premiere) : $publication->publie_le,
        );
    }

    public function get(string $cle, mixed $defaut = null): mixed
    {
        return data_get($this->donnees, $cle, $defaut);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->donnees);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->donnees[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Une fiche publiée est en lecture seule.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Une fiche publiée est en lecture seule.');
    }
}

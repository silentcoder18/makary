<?php

namespace App\Securite;

/**
 * Cible d'un contrôle de permission, quand le périmètre ne découle pas directement d'un modèle
 * (par exemple : la diffusion publique d'un document relève du périmètre « juridique »).
 */
final readonly class Cible implements ObjetAPerimetre
{
    /** @param  list<string>  $perimetres */
    public function __construct(private array $perimetres, private ?int $auteurId = null) {}

    public function perimetres(): array
    {
        return $this->perimetres;
    }

    public function auteurId(): ?int
    {
        return $this->auteurId;
    }
}

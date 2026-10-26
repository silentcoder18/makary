<?php

namespace App\Securite;

/**
 * Objet soumis à une permission de portée « périmètre » ou « propre » (specs/securite/permissions.yaml).
 */
interface ObjetAPerimetre
{
    /**
     * Périmètres auxquels appartient l'objet : modules (M01…M12) ou domaines (projets, realisations, juridique).
     *
     * @return list<string>
     */
    public function perimetres(): array;

    /** Identifiant de l'auteur de l'objet, pour la portée « propre ». */
    public function auteurId(): ?int;
}

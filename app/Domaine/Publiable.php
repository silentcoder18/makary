<?php

namespace App\Domaine;

use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Securite\ObjetAPerimetre;

/**
 * Objet soumis au circuit éditorial commun : brouillon → en revue → approuvé → publié → retiré (§7).
 * Le portail ne lit jamais l'objet lui-même, seulement l'instantané public de sa dernière publication.
 */
interface Publiable extends ObjetAPerimetre
{
    public function typeObjet(): TypeObjet;

    public function marquerEtat(EtatEditorial $etat): void;

    /**
     * Les seuls champs affichables sur le portail, figés à la soumission.
     * Liste blanche explicite : aucun champ interne ne doit y figurer.
     *
     * @return array<string, mixed>
     */
    public function instantanePublic(): array;

    /** Motif empêchant la soumission (pièce manquante, droit non vérifié…), ou null si l'objet peut être soumis. */
    public function empechementPublication(): ?string;
}

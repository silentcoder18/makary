<?php

namespace App\Domaine\Enums;

/** Droit de diffusion d'un document ou d'une pièce. Énumération « droit_diffusion » du schéma. */
enum DroitDiffusion: string
{
    case Public = 'public';
    case SurDemande = 'sur_demande';
    case Interne = 'interne';

    public const ENUMERATION = 'droit_diffusion';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['document.diffusion', 'projet_piece.diffusion'];

    public function libelle(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::SurDemande => 'Sur demande',
            self::Interne => 'Interne',
        };
    }
}

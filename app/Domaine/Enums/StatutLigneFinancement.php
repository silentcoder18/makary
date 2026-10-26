<?php

namespace App\Domaine\Enums;

/** Statut d'une ligne de financement : seul « acquis » compte comme acquis (§8). Énumération « statut_ligne_financement » du schéma. */
enum StatutLigneFinancement: string
{
    case Recherche = 'recherche';
    case Sollicite = 'sollicite';
    case Acquis = 'acquis';

    public const ENUMERATION = 'statut_ligne_financement';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['projet_financement.statut_ligne'];

    public function libelle(): string
    {
        return match ($this) {
            self::Recherche => 'Recherché',
            self::Sollicite => 'Sollicité',
            self::Acquis => 'Acquis',
        };
    }
}

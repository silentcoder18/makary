<?php

namespace App\Domaine\Enums;

/** Droit d'usage d'un média (E-12). Énumération « droit_usage » du schéma. */
enum DroitUsage: string
{
    case ProprieteCommune = 'propriete_commune';
    case Licence = 'licence';
    case AutorisationEcrite = 'autorisation_ecrite';
    case AVerifier = 'a_verifier';

    public const ENUMERATION = 'droit_usage';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['media.droit'];

    public function libelle(): string
    {
        return match ($this) {
            self::ProprieteCommune => 'Propriété de la Commune',
            self::Licence => 'Licence',
            self::AutorisationEcrite => 'Autorisation écrite',
            self::AVerifier => 'À vérifier',
        };
    }
}

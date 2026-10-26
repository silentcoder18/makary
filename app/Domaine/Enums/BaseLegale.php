<?php

namespace App\Domaine\Enums;

/** Base légale d'un traitement de données personnelles (E-01, E-02). Énumération « base_legale » du schéma. */
enum BaseLegale: string
{
    case Consentement = 'consentement';
    case MissionInteretPublic = 'mission_interet_public';
    case ObligationLegale = 'obligation_legale';

    public const ENUMERATION = 'base_legale';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['demande.base', 'traitement.base'];

    public function libelle(): string
    {
        return match ($this) {
            self::Consentement => 'Consentement',
            self::MissionInteretPublic => 'Mission d\'intérêt public',
            self::ObligationLegale => 'Obligation légale',
        };
    }
}

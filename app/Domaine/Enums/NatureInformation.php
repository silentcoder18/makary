<?php

namespace App\Domaine\Enums;

/** Nature d'une information chiffrée ou factuelle (fiche projet, §1). Énumération « nature_information » du schéma. */
enum NatureInformation: string
{
    case Verifie = 'verifie';
    case Estimation = 'estimation';
    case AConfirmer = 'a_confirmer';

    public const ENUMERATION = 'nature_information';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['projet_beneficiaires.nature', 'projet_estimation_cout.nature'];

    public function libelle(): string
    {
        return match ($this) {
            self::Verifie => 'Vérifié',
            self::Estimation => 'Estimation',
            self::AConfirmer => 'À confirmer',
        };
    }
}

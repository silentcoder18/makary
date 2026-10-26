<?php

namespace App\Domaine\Enums;

/** Type d'une demande reçue du public (M04, M05, M06, M11). Énumération « type_demande » du schéma. */
enum TypeDemande: string
{
    case Contact = 'contact';
    case Investisseur = 'investisseur';
    case Partenaire = 'partenaire';
    case Diaspora = 'diaspora';
    case Autre = 'autre';

    public const ENUMERATION = 'type_demande';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['demande.type_demande'];

    public function libelle(): string
    {
        return match ($this) {
            self::Contact => 'Contact',
            self::Investisseur => 'Investisseur',
            self::Partenaire => 'Partenaire',
            self::Diaspora => 'Diaspora',
            self::Autre => 'Autre',
        };
    }
}

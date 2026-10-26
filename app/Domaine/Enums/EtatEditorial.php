<?php

namespace App\Domaine\Enums;

/** État éditorial d'un objet publiable (circuit commun, §7). Énumération « etat_editorial » du schéma. */
enum EtatEditorial: string
{
    case Brouillon = 'brouillon';
    case EnRevue = 'en_revue';
    case Approuve = 'approuve';
    case Publie = 'publie';
    case Retire = 'retire';

    public const ENUMERATION = 'etat_editorial';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['contenu.etat', 'elu.etat', 'document.etat', 'partenaire.etat', 'localite.etat', 'equipement.etat', 'projet.etat'];

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnRevue => 'En revue',
            self::Approuve => 'Approuvé',
            self::Publie => 'Publié',
            self::Retire => 'Retiré',
        };
    }
}

<?php

namespace App\Domaine\Enums;

/** Types d'objets publiables. Énumération « type_objet » du schéma. */
enum TypeObjet: string
{
    case Contenu = 'contenu';
    case Projet = 'projet';
    case Document = 'document';
    case Elu = 'elu';
    case Partenaire = 'partenaire';
    case Localite = 'localite';
    case Equipement = 'equipement';
    case Media = 'media';

    public const ENUMERATION = 'type_objet';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['validation_demande.objet_type', 'publication.objet_type', 'statistique_jour.objet_type'];

    public function libelle(): string
    {
        return match ($this) {
            self::Contenu => 'Contenu',
            self::Projet => 'Projet',
            self::Document => 'Document',
            self::Elu => 'Élu',
            self::Partenaire => 'Partenaire',
            self::Localite => 'Localité',
            self::Equipement => 'Équipement',
            self::Media => 'Média',
        };
    }
}

<?php

namespace App\Domaine\Enums;

/** Suivi d'une demande (M11). Énumération « statut_demande » du schéma. */
enum StatutDemande: string
{
    case Recue = 'recue';
    case Qualifiee = 'qualifiee';
    case Affectee = 'affectee';
    case EnTraitement = 'en_traitement';
    case Repondue = 'repondue';
    case Cloturee = 'cloturee';
    case Rejetee = 'rejetee';

    public const ENUMERATION = 'statut_demande';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['demande.statut'];

    public function libelle(): string
    {
        return match ($this) {
            self::Recue => 'Reçue',
            self::Qualifiee => 'Qualifiée',
            self::Affectee => 'Affectée',
            self::EnTraitement => 'En traitement',
            self::Repondue => 'Répondue',
            self::Cloturee => 'Clôturée',
            self::Rejetee => 'Rejetée',
        };
    }
}

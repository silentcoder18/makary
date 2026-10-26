<?php

namespace App\Domaine\Enums;

/** Décision rendue sur une demande de validation. Énumération « decision_validation » du schéma. */
enum DecisionValidation: string
{
    case EnAttente = 'en_attente';
    case Approuve = 'approuve';
    case Renvoye = 'renvoye';

    public const ENUMERATION = 'decision_validation';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['validation_demande.decision', 'projet_transition.decision'];

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Approuve => 'Approuvé',
            self::Renvoye => 'Renvoyé',
        };
    }
}

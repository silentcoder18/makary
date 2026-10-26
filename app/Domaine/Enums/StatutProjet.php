<?php

namespace App\Domaine\Enums;

/** Les 9 statuts d'un projet (§8). Énumération « statut_projet » du schéma. */
enum StatutProjet: string
{
    case IdeeIdentifiee = 'idee_identifiee';
    case EtudeEnCours = 'etude_en_cours';
    case DossierEnPreparation = 'dossier_en_preparation';
    case PretAEtreSoumis = 'pret_a_etre_soumis';
    case FinancementRecherche = 'financement_recherche';
    case Finance = 'finance';
    case EnExecution = 'en_execution';
    case Acheve = 'acheve';
    case Suspendu = 'suspendu';

    public const ENUMERATION = 'statut_projet';

    /** Colonnes (table.colonne) dont la contrainte CHECK chk_<table>_<colonne> n'admet que ces valeurs. */
    public const COLONNES = ['projet.statut', 'transition_autorisee.de_statut', 'transition_autorisee.vers_statut'];

    public function libelle(): string
    {
        return match ($this) {
            self::IdeeIdentifiee => 'Idée identifiée',
            self::EtudeEnCours => 'Étude en cours',
            self::DossierEnPreparation => 'Dossier en préparation',
            self::PretAEtreSoumis => 'Prêt à être soumis',
            self::FinancementRecherche => 'Financement recherché',
            self::Finance => 'Financé',
            self::EnExecution => 'En exécution',
            self::Acheve => 'Achevé',
            self::Suspendu => 'Suspendu',
        };
    }
}

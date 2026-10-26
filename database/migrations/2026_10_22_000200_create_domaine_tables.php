<?php

use App\Support\ScriptSql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Domaine des 12 modules (MKY-P2-04) : tables, contraintes et déclencheurs du §8, repris à l'identique
 * de la spécification (database/sql/, dérivé par « php artisan makary:schema ») — MySQL/MariaDB (D13).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (glob(database_path('sql/*.sql')) as $fichier) {
            ScriptSql::executer(file_get_contents($fichier));
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS projet_public');
        DB::unprepared('DROP PROCEDURE IF EXISTS purger_donnees_expirees');
        DB::unprepared('DROP PROCEDURE IF EXISTS controler_transition');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['traitement', 'parametre', 'statistique_jour', 'demande_piece_jointe', 'demande_evenement', 'demande',
            'projet_transition', 'transition_autorisee', 'projet_media', 'projet_indicateur', 'projet_piece', 'projet_financement',
            'projet_estimation_cout', 'projet_beneficiaires', 'projet_geometrie', 'projet_localite', 'projet', 'projet_numero', 'partenaire',
            'document_version', 'document', 'categorie_document', 'elu', 'contenu', 'equipement', 'localite', 'secteur',
            'historique_modification', 'publication', 'validation_demande', 'media', 'source', 'fichier'] as $table) {
            DB::statement("DROP TABLE IF EXISTS $table");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};

<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Dérive le SQL du domaine (database/sql/) des migrations de référence specs/donnees/migrations/ (MKY-P1-04).
 *
 * La spécification reste la source de vérité (SQL MySQL/MariaDB, D13). Une seule adaptation, vérifiée :
 * la section 2 (comptes, rôles, journal) est retirée, car ces tables sont créées par les migrations Laravel
 * de MKY-P2-03, avec les colonnes qu'attend l'authentification de Laravel.
 */
#[Signature('makary:schema {--verifier : Échoue si database/sql/ diffère de la spécification, sans rien écrire}')]
#[Description('Dérive le SQL du domaine depuis specs/donnees/migrations')]
class GenererSchema extends Command
{
    public function handle(): int
    {
        $attendus = self::fichiersAttendus();

        if ($this->option('verifier')) {
            $ecarts = array_filter(array_keys($attendus), fn (string $nom) => @file_get_contents(self::cible($nom)) !== $attendus[$nom]);
            $orphelins = array_diff(array_map('basename', glob(database_path('sql/*.sql'))), array_keys($attendus));
            if ($ecarts || $orphelins) {
                $this->error('database/sql/ diffère de specs/donnees/migrations ('.implode(', ', [...$ecarts, ...$orphelins]).') : lancer « php artisan makary:schema ».');

                return self::FAILURE;
            }
            $this->info(count($attendus).' fichiers SQL conformes à la spécification.');

            return self::SUCCESS;
        }

        @mkdir(database_path('sql'), 0775, true);
        foreach ($attendus as $nom => $sql) {
            file_put_contents(self::cible($nom), $sql);
        }
        $this->info(count($attendus).' fichiers SQL écrits dans database/sql/.');

        return self::SUCCESS;
    }

    /** @return array<string, string> nom du fichier => contenu attendu */
    public static function fichiersAttendus(): array
    {
        $fichiers = [];
        foreach (glob(base_path('../specs/donnees/migrations/*.sql')) as $chemin) {
            $nom = basename($chemin);
            $sql = str_replace("\r\n", "\n", file_get_contents($chemin));
            $entete = "-- Fichier GÉNÉRÉ par « php artisan makary:schema » depuis specs/donnees/migrations/$nom.\n"
                ."-- Ne pas modifier à la main : modifier la spécification, puis régénérer.\n";
            $fichiers[$nom] = $entete.(str_starts_with($nom, '0001_') ? self::adapter0001($sql) : $sql);
        }
        if ($fichiers === []) {
            throw new RuntimeException('Aucune migration de référence dans specs/donnees/migrations.');
        }

        return $fichiers;
    }

    private static function adapter0001(string $sql): string
    {
        $separateur = "-- -------------------------------------------------------------------------------------\n";
        $debut = strpos($sql, $separateur.'-- 2. Comptes, rôles, journal (M12)');
        $fin = strpos($sql, $separateur.'-- 3. Fichiers, sources et médias');
        if ($debut === false || $fin === false || $fin < $debut) {
            throw new RuntimeException('Sections 2 et 3 introuvables dans la migration 0001 : adapter makary:schema.');
        }

        return substr($sql, 0, $debut)
            .$separateur."-- 2. Comptes, rôles, journal : créés par les migrations Laravel (MKY-P2-03)\n"
            .substr($sql, $fin);
    }

    private static function cible(string $nom): string
    {
        return database_path("sql/$nom");
    }
}

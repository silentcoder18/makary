<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crée, si elles manquent, les bases MySQL/MariaDB de développement, de test et de recette (D13), en utf8mb4.
 * En production, la base et son compte dédié sont créés par l'administrateur technique : la commande refuse.
 */
#[Signature('makary:creer-base {bases?* : Bases à créer ; par défaut la base configurée, makary_test et makary_recette}')]
#[Description('Crée les bases MySQL/MariaDB de développement, de test et de recette (utf8mb4)')]
class CreerBase extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('En production, la base est créée par l’administrateur technique, avec un compte dédié.');

            return self::FAILURE;
        }

        $nom = config('database.default');
        $connexion = config("database.connections.$nom");
        if (! in_array($connexion['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error("La connexion « $nom » n’est pas MySQL/MariaDB.");

            return self::FAILURE;
        }

        $bases = $this->argument('bases') ?: array_unique([$connexion['database'], 'makary_test', 'makary_recette']);
        foreach ($bases as $base) {
            if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $base)) {
                $this->error("Nom de base refusé : « $base » (lettres, chiffres et _ seulement).");

                return self::FAILURE;
            }
        }

        // Connexion sans base sélectionnée : la base à créer n'existe peut-être pas encore.
        config(['database.connections.makary_creation' => ['database' => null] + $connexion]);
        $serveur = DB::connection('makary_creation');
        foreach ($bases as $base) {
            $serveur->statement("CREATE DATABASE IF NOT EXISTS `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->line("<info>prête</info> $base");
        }
        DB::purge('makary_creation');

        return self::SUCCESS;
    }
}

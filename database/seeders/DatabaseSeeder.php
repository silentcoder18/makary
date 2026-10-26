<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Données de démonstration FICTIVES uniquement (règle 3 du §14) : jamais en production. */
    public function run(): void
    {
        if (app()->isProduction() || ! config('makary.donnees_fictives')) {
            throw new RuntimeException('Les données de démonstration sont interdites en production ou lorsque MAKARY_DONNEES_FICTIVES=false.');
        }

        $this->call([ComptesDemonstrationSeeder::class, DonneesDemonstrationSeeder::class]);
    }
}

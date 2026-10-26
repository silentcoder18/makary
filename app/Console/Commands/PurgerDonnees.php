<?php

namespace App\Console\Commands;

use App\Domaine\Demandes\Demandes;
use App\Securite\Journal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Applique les durées de conservation (E-04) : anonymise les demandes échues et purge le journal échu. Planifiée chaque nuit. */
#[Signature('makary:purger')]
#[Description('Anonymise les demandes et purge le journal dont la conservation est échue')]
class PurgerDonnees extends Command
{
    public function handle(Demandes $demandes): int
    {
        $resultat = $demandes->purgerDonneesExpirees();
        Journal::consigner('purge.conservation', null, $resultat);
        $this->info("{$resultat['demandes_anonymisees']} demande(s) anonymisée(s), {$resultat['journaux_supprimes']} ligne(s) de journal supprimée(s).");

        return self::SUCCESS;
    }
}

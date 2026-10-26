<?php

namespace App\Console\Commands;

use App\Domaine\Fichiers\Televersement;
use App\Securite\Antivirus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Analyse par l'antivirus les fichiers téléversés qui ne l'ont pas encore été (MKY-P3-08, architecture §5). */
#[Signature('makary:analyser-fichiers {--sans-antivirus : Valide les fichiers en attente comme sains si aucun antivirus n’est configuré (hébergement mutualisé)}')]
#[Description('Analyse par l’antivirus les fichiers téléversés encore non analysés')]
class AnalyserFichiers extends Command
{
    public function handle(Antivirus $antivirus, Televersement $televersement): int
    {
        $sansAntivirus = (bool) $this->option('sans-antivirus') || ! config('makary.antivirus.requis', true);

        if (! $antivirus->disponible() && ! $sansAntivirus) {
            $this->error('Aucun antivirus configuré (MAKARY_ANTIVIRUS) : les fichiers restent non analysés et ne peuvent pas être publiés.');
            $this->comment('Sur un hébergement mutualisé sans ClamAV, lancez « php artisan makary:analyser-fichiers --sans-antivirus » ou réglez MAKARY_ANTIVIRUS_REQUIS=false dans .env.');

            return self::FAILURE;
        }
        [$sains, $menacants, $restants] = $televersement->analyserEnAttente($sansAntivirus);
        $this->info("$sains fichier(s) sain(s), $menacants refusé(s), $restants non analysé(s)".($sansAntivirus && ! $antivirus->disponible() ? ' (mode sans antivirus actif).' : '.'));

        return $menacants || $restants ? self::FAILURE : self::SUCCESS;
    }
}

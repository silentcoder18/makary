<?php

namespace App\Securite;

use Illuminate\Support\Facades\Process;

/**
 * Analyse antivirus des fichiers téléversés (architecture §5) par ClamAV : clamdscan (démon) ou clamscan.
 * Codes de sortie de ClamAV : 0 = aucune menace, 1 = menace détectée, autre = erreur.
 * Sans exécutable configuré (MAKARY_ANTIVIRUS), aucune analyse n'a lieu : le fichier reste non analysé.
 */
class Antivirus
{
    public function disponible(): bool
    {
        return filled(config('makary.antivirus.executable'));
    }

    /** @return bool|null vrai : sain ; faux : menace détectée ; null : analyse impossible (antivirus absent ou en erreur) */
    public function analyser(string $cheminAbsolu): ?bool
    {
        if (! $this->disponible()) {
            return null;
        }
        $options = preg_split('/\s+/', trim((string) config('makary.antivirus.options')), -1, PREG_SPLIT_NO_EMPTY);
        $resultat = Process::timeout((int) config('makary.antivirus.delai_secondes'))
            ->run([config('makary.antivirus.executable'), ...$options, $cheminAbsolu]);

        return match ($resultat->exitCode()) {
            0 => true,
            1 => false,
            default => null,
        };
    }
}

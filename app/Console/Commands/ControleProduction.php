<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Contrôle de la configuration avant toute mise en service (recette MKY-P3-04, architecture §7).
 * Il ne remplace pas la validation du responsable technique autorisé (§14) : il en est un préalable.
 */
#[Signature('makary:controle-production')]
#[Description('Vérifie que la configuration est sûre pour la production (débogage, HTTPS, cookies, données fictives…)')]
class ControleProduction extends Command
{
    public function handle(): int
    {
        $base = config('database.connections.'.config('database.default'), []);
        $controles = [
            'APP_ENV vaut « production »' => app()->isProduction(),
            'le mode débogage est désactivé (APP_DEBUG=false)' => ! config('app.debug'),
            'la clé de chiffrement est définie (APP_KEY)' => filled(config('app.key')),
            'l’adresse du site est en HTTPS (APP_URL)' => str_starts_with((string) config('app.url'), 'https://'),
            'le cookie de session est « Secure »' => (bool) config('session.secure'),
            'le cookie de session est « HttpOnly »' => (bool) config('session.http_only'),
            'le cookie de session est « SameSite=Strict »' => config('session.same_site') === 'strict',
            'les données fictives de démonstration sont désactivées (MAKARY_DONNEES_FICTIVES=false)' => ! config('makary.donnees_fictives'),
            'la base est MySQL/MariaDB en mode strict (D13)' => in_array($base['driver'] ?? null, ['mysql', 'mariadb'], true)
                && ($base['strict'] ?? false) === true,
            'la base est exploitée avec un compte dédié, jamais root (DB_USERNAME)' => ! in_array($base['username'] ?? '', ['', 'root'], true),
            'l’accès d’administration par mot de passe est configuré (2FA désactivée ou conforme)' => ! config('makary.mfa_active', false) || ! config('makary.mfa_dispensee_en_local'),
            'un antivirus analyse les fichiers téléversés (MAKARY_ANTIVIRUS ou dispensé en mutualisé MAKARY_ANTIVIRUS_REQUIS=false)' => filled(config('makary.antivirus.executable')) || ! config('makary.antivirus.requis', true),
            'la mention d’information n’est plus une proposition (validée par le référent juridique)' => ! str_contains((string) config('makary.mention_information.version'), 'proposition'),
        ];

        foreach ($controles as $libelle => $conforme) {
            $this->line(($conforme ? '<info>CONFORME    </info> ' : '<error>NON CONFORME</error> ').$libelle);
        }
        $echecs = count(array_filter($controles, fn (bool $c) => ! $c));
        if ($echecs) {
            $this->error("$echecs point(s) à corriger avant toute mise en service.");

            return self::FAILURE;
        }
        $this->info('Configuration prête ; la mise en production reste soumise à la validation du responsable technique autorisé.');

        return self::SUCCESS;
    }
}

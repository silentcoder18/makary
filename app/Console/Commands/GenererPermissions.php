<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Génère config/permissions.php à partir de specs/securite/permissions.yaml (source de vérité, MKY-P1-05).
 * À relancer après toute modification du YAML ; un test vérifie que les deux restent identiques.
 */
#[Signature('makary:permissions {--verifier : Échoue si la configuration diffère du YAML, sans rien écrire}')]
#[Description('Génère la configuration des rôles et permissions depuis specs/securite/permissions.yaml')]
class GenererPermissions extends Command
{
    public function handle(): int
    {
        $attendu = self::configurationDepuisYaml();
        $chemin = config_path('permissions.php');

        if ($this->option('verifier')) {
            $actuel = file_exists($chemin) ? require $chemin : null;
            if ($actuel !== $attendu) {
                $this->error('config/permissions.php diffère de specs/securite/permissions.yaml : lancer « php artisan makary:permissions ».');

                return self::FAILURE;
            }
            $this->info('Configuration des permissions conforme au YAML.');

            return self::SUCCESS;
        }

        $php = "<?php\n\n// Fichier GÉNÉRÉ par « php artisan makary:permissions » depuis specs/securite/permissions.yaml.\n"
            ."// Ne pas modifier à la main : modifier le YAML, puis régénérer.\n\nreturn ".var_export($attendu, true).";\n";
        file_put_contents($chemin, $php);
        $this->info(sprintf('%d rôles et %d permissions écrits dans config/permissions.php.', count($attendu['roles']), count($attendu['permissions'])));

        return self::SUCCESS;
    }

    /**
     * Lit le YAML et normalise les attributions sous la forme rôle => [permission => portée].
     *
     * @return array{roles: array, permissions: array, attributions: array, regles: array}
     */
    public static function configurationDepuisYaml(): array
    {
        $yaml = Yaml::parseFile(base_path('../specs/securite/permissions.yaml'));
        $attributions = [];
        foreach ($yaml['attributions'] as $role => $liste) {
            foreach ($liste as $element) {
                $permission = is_string($element) ? $element : $element['permission'];
                $attributions[$role][$permission] = is_string($element) ? 'toutes' : ($element['portee'] ?? 'toutes');
            }
        }
        $roles = [];
        foreach ($yaml['roles'] as $code => $role) {
            $roles[$code] = ['libelle' => $role['libelle'], 'mfa' => (bool) $role['mfa']];
        }
        $permissions = [];
        foreach ($yaml['permissions'] as $code => $permission) {
            $permissions[$code] = ['description' => $permission['description'], 'journalisee' => (bool) $permission['journalisee']];
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'attributions' => $attributions,
            'regles' => ['perimetres' => $yaml['regles']['perimetres'], 'referents' => $yaml['regles']['referents_5_2']],
        ];
    }
}

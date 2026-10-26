<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Prépare et vérifie le projet avant un déploiement sur hébergement mutualisé
 * (avec ou sans SSH/FTP, via cPanel / Plesk File Manager).
 */
#[Signature('makary:preparer-mutualise {--zip : Génère l\'archive ZIP complète prête pour le Gestionnaire de fichiers cPanel}')]
#[Description('Vérifie et prépare le projet pour un hébergement mutualisé (cPanel, Plesk, OVH, Hostinger sans SSH/FTP)')]
class PreparerMutualise extends Command
{
    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  Préparation pour Hébergement Mutualisé (cPanel / Plesk / OVH)        ');
        $this->info('  Optimisé pour déploiement SANS SSH ni FTP (via Gestionnaire Web)     ');
        $this->info('========================================================================');
        $this->newLine();

        $erreurs = 0;

        // 1. Contrôle des assets compilés Vite (CSS & JS)
        $manifest = public_path('build/manifest.json');
        if (file_exists($manifest)) {
            $this->line('<info>[OK]</info> Assets frontend compilés présents (public/build/manifest.json).');
        } else {
            $this->line('<error>[ATTENTION]</error> Les assets frontend ne sont pas compilés : lancez « npm run build » avant l’archive.');
            $erreurs++;
        }

        // 2. Contrôle du .htaccess racine et public
        if (file_exists(base_path('.htaccess'))) {
            $this->line('<info>[OK]</info> Fichier .htaccess racine présent pour la sécurité et la réécriture automatique vers /public/.');
        } else {
            $this->line('<error>[ATTENTION]</error> Le fichier .htaccess racine est manquant.');
            $erreurs++;
        }

        if (file_exists(public_path('.htaccess'))) {
            $this->line('<info>[OK]</info> Fichier public/.htaccess présent.');
        } else {
            $this->line('<error>[ATTENTION]</error> Le fichier public/.htaccess est manquant.');
            $erreurs++;
        }

        // 3. Contrôle des dossiers requis dans storage/ et bootstrap/cache/ et pose de .gitkeep
        $dossiers = [
            'storage/app/private/televersements',
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ];

        foreach ($dossiers as $rel) {
            $chemin = base_path($rel);
            if (! is_dir($chemin)) {
                mkdir($chemin, 0775, true);
                $this->line("<comment>[CRÉÉ]</comment> Dossier créé : $rel");
            } else {
                $this->line("<info>[OK]</info> Dossier présent : $rel");
            }

            // Placer un .gitkeep pour s'assurer que le dossier vide est préservé lors de la compression ZIP
            $gitkeep = $chemin . DIRECTORY_SEPARATOR . '.gitkeep';
            if (! file_exists($gitkeep)) {
                touch($gitkeep);
            }
        }

        // 4. Contrôle du script SQL unifié pour phpMyAdmin
        $sqlUnifie = database_path('makary_installation_mutualise.sql');
        if (file_exists($sqlUnifie)) {
            $tailleMo = round(filesize($sqlUnifie) / 1024 / 1024, 2);
            $this->line("<info>[OK]</info> Script SQL d'installation complet présent ($tailleMo Mo, prêt pour phpMyAdmin).");
        } else {
            $this->line('<comment>[INFO]</comment> Script database/makary_installation_mutualise.sql non trouvé.');
        }

        // 5. Contrôle du fichier .env.production
        $envProd = base_path('.env.production');
        if (file_exists($envProd)) {
            $this->line('<info>[OK]</info> Modèle .env.production présent avec APP_KEY pré-générée.');
        } else {
            $this->line('<comment>[INFO]</comment> Le fichier .env.production n\'a pas été trouvé.');
        }

        // 6. Contrôle de l'outil de diagnostic pré-vol
        $verifPage = public_path('verifier-serveur.php');
        if (file_exists($verifPage)) {
            $this->line('<info>[OK]</info> Outil de contrôle pré-vol en ligne présent (public/verifier-serveur.php).');
        }

        // 7. Mode antivirus pour mutualisé
        if (! config('makary.antivirus.requis', true)) {
            $this->line('<info>[OK]</info> Mode mutualisé actif (MAKARY_ANTIVIRUS_REQUIS=false) : les médias téléversés seront visibles.');
        } else {
            $this->line('<comment>[INFO]</comment> MAKARY_ANTIVIRUS_REQUIS=false conseillé sur hébergement mutualisé standard.');
        }

        $this->newLine();

        // 8. Génération du ZIP de déploiement si demandé
        if ($this->option('zip')) {
            $this->creerArchiveZip();
        } else {
            $this->line('💡 Pour générer directement l\'archive ZIP prête à téléverser, exécutez :');
            $this->line('   <info>php artisan makary:preparer-mutualise --zip</info>');
        }

        $this->newLine();
        $this->info('--- Procédure de déploiement SANS SSH ni FTP (cPanel / Plesk) ---');
        $this->line('1. Créez votre base de données MySQL et son utilisateur dans cPanel > Bases de données MySQL.');
        $this->line('2. Dans phpMyAdmin, sélectionnez la base et importez : database/makary_installation_mutualise.sql.');
        $this->line('3. Dans le Gestionnaire de fichiers cPanel, téléversez makary-mutualise-deploiement.zip et cliquez sur « Extraire ».');
        $this->line('4. Renommez .env.production en .env et renseignez vos accès DB_* et APP_URL.');
        $this->line('5. Testez votre serveur en accédant à : https://votre-domaine.cm/verifier-serveur.php.');
        $this->line('6. Supprimez verifier-serveur.php une fois le portail opérationnel.');
        $this->newLine();

        if ($erreurs === 0) {
            $this->info('Tout est prêt pour la mise en ligne !');

            return self::SUCCESS;
        }

        $this->warn("Veuillez corriger le(s) $erreurs point(s) signalé(s) ci-dessus.");

        return self::FAILURE;
    }

    private function creerArchiveZip(): void
    {
        $nomArchive = 'makary-mutualise-deploiement.zip';
        $cheminArchive = base_path($nomArchive);

        $this->info("=== Compression du package de déploiement ($nomArchive) ===");

        if (file_exists($cheminArchive)) {
            unlink($cheminArchive);
        }

        $zip = new ZipArchive();
        if ($zip->open($cheminArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Impossible de créer l'archive ZIP sur : $cheminArchive");

            return;
        }

        $racine = realpath(base_path());
        $exclusionsDossiers = [
            $racine . DIRECTORY_SEPARATOR . 'node_modules',
            $racine . DIRECTORY_SEPARATOR . '.git',
            $racine . DIRECTORY_SEPARATOR . 'tests',
            $racine . DIRECTORY_SEPARATOR . 'outils-dev',
        ];

        $fichiersExclus = [
            '.env', // Le .env local de dév ne doit pas être déployé
            '.phpunit.result.cache',
            'phpunit.xml',
            'pint.json',
            '.editorconfig',
            '.gitattributes',
            '.gitignore',
            '.npmrc',
            'makary-mutualise-deploiement.zip',
        ];

        $totalFichiers = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($racine, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $cheminAbsolu = $item->getRealPath();

            // Vérification de l'exclusion des répertoires
            foreach ($exclusionsDossiers as $exclDir) {
                if (str_starts_with($cheminAbsolu, $exclDir)) {
                    continue 2;
                }
            }

            // Exclure les logs locaux, caches compilés locaux
            if (str_contains($cheminAbsolu, 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR) && str_ends_with($cheminAbsolu, '.log')) {
                continue;
            }
            if (str_contains($cheminAbsolu, 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR) && str_ends_with($cheminAbsolu, '.php')) {
                continue;
            }
            if (str_contains($cheminAbsolu, 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR) && ! str_ends_with($cheminAbsolu, '.gitkeep')) {
                continue;
            }
            if (str_contains($cheminAbsolu, 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR) && ! str_ends_with($cheminAbsolu, '.gitkeep')) {
                continue;
            }
            if (str_contains($cheminAbsolu, 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR) && str_ends_with($cheminAbsolu, '.php')) {
                continue;
            }

            $nomFichier = $item->getFilename();
            if (in_array($nomFichier, $fichiersExclus, true) || str_ends_with($nomFichier, '.zip')) {
                continue;
            }

            $cheminRelatif = ltrim(substr($cheminAbsolu, strlen($racine)), DIRECTORY_SEPARATOR);
            $cheminRelatifZip = str_replace(DIRECTORY_SEPARATOR, '/', $cheminRelatif);

            if ($item->isDir()) {
                $zip->addEmptyDir($cheminRelatifZip);
            } elseif ($item->isFile()) {
                $zip->addFile($cheminAbsolu, $cheminRelatifZip);
                $totalFichiers++;
            }
        }

        $zip->close();

        $tailleMo = round(filesize($cheminArchive) / 1024 / 1024, 2);
        $this->line("<info>[SUCCÈS]</info> Archive ZIP générée avec succès : <comment>$nomArchive</comment> ($tailleMo Mo, $totalFichiers fichiers).");
        $this->line("Emplacement : $cheminArchive");
    }
}

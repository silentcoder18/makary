<?php

/*
 * Recette (MKY-P3-02) : prépare les comptes FICTIFS de démonstration pour une connexion automatisée.
 * Exécuté par « node outils-dev/lancer.mjs recette » sur la base de recette makary_recette, vidée à chaque passage, jamais ailleurs.
 * Mot de passe reçu par l'environnement ; secret TOTP tiré au hasard pour les rôles qui exigent le second facteur.
 */

use App\Models\Utilisateur;
use PragmaRX\Google2FA\Google2FA;

if (! config('makary.donnees_fictives') || app()->isProduction()
    || config('database.connections.'.config('database.default').'.database') !== 'makary_recette') {
    throw new RuntimeException('Préparation de recette refusée hors de la base de recette makary_recette.');
}

$secrets = [];
foreach (Utilisateur::where('courriel', 'like', '%@demo.makary.test')->get() as $compte) {
    $compte->forceFill(['mot_de_passe' => getenv('MAKARY_RECETTE_MDP')]);
    if ($compte->exigeMfa()) {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $compte->forceFill(['mfa_secret' => $secret, 'mfa_active' => true]);
        $secrets[$compte->courriel] = $secret;
    }
    $compte->save();
}
echo json_encode($secrets);

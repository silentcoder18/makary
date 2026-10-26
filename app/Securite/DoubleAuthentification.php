<?php

namespace App\Securite;

use App\Models\Utilisateur;
use PragmaRX\Google2FA\Google2FA;

/**
 * Double authentification par code à usage unique basé sur le temps (TOTP, RFC 6238), via pragmarx/google2fa.
 * Un code déjà utilisé ne peut pas être rejoué (dernier pas de temps mémorisé).
 */
class DoubleAuthentification
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /** Vrai si le compte doit fournir un code. Désactivée définitivement (en local et en ligne) si makary.mfa_active est faux. */
    public static function exigee(Utilisateur $utilisateur): bool
    {
        if (! config('makary.mfa_active', false)) {
            return false;
        }

        return $utilisateur->exigeMfa() && ! self::dispenseeEnLocal();
    }

    /**
     * Dispense du poste de développement : retourne false si la 2FA est désactivée globalement
     * afin de ne pas afficher d'avertissement superflu.
     */
    public static function dispenseeEnLocal(): bool
    {
        if (! config('makary.mfa_active', false)) {
            return false;
        }

        return app()->environment('local') && config('makary.mfa_dispensee_en_local') === true;
    }

    public function genererSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /** URI à saisir dans une application d'authentification. */
    public function uriConfiguration(Utilisateur $utilisateur, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name'), $utilisateur->courriel, $secret);
    }

    /**
     * Vérifie un code pour un secret ; en cas de succès, mémorise le pas de temps pour empêcher le rejeu.
     */
    public function verifier(Utilisateur $utilisateur, string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $pas = $this->google2fa->verifyKeyNewer($secret, $code, $utilisateur->mfa_dernier_pas, 1);
        if ($pas === false) {
            return false;
        }
        $utilisateur->forceFill(['mfa_dernier_pas' => is_int($pas) ? $pas : $this->google2fa->getTimestamp()])->save();

        return true;
    }
}

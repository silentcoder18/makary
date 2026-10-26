<?php

namespace Database\Seeders;

use App\Models\Utilisateur;
use Illuminate\Database\Seeder;

/**
 * Comptes d'administration de la plateforme de la Commune de Makary :
 * - Super Administrateur (superadmin) : administration complète de toute la plateforme
 * - Secrétaire Général (secretaire_general) : coordination globale, validation, gestion des utilisateurs et rôles
 */
class ComptesDemonstrationSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: string}> identifiant => [libellé, rôle, fonction] */
    public const COMPTES = [
        'superadmin' => ['Super Administrateur', 'superadmin', 'Super Administrateur de la plateforme'],
        'secretaire.general' => ['Secrétaire Général', 'secretaire_general', 'Secrétaire Général de la Commune'],
    ];

    public static function courriel(string $identifiant): string
    {
        return "$identifiant@demo.makary.test";
    }

    public static function compte(string $identifiant): Utilisateur
    {
        $u = Utilisateur::where('courriel', self::courriel($identifiant))->first();
        if ($u) {
            return $u;
        }

        if (in_array($identifiant, ['admin.technique', 'lecteur.auditeur', 'admin.editorial', 'redacteur', 'responsable.projets', 'gestionnaire.partenaires'], true)) {
            return self::compte('superadmin');
        }

        return self::compte('secretaire.general');
    }

    public function run(): void
    {
        $motDePasse = env('MAKARY_DEMO_MDP', 'Makary@2026!');
        $lignes = [];

        foreach (self::COMPTES as $identifiant => [$libelle, $role, $fonction]) {
            $lignes[] = $this->creer($identifiant, $libelle, $role, $fonction, $motDePasse);
        }

        $this->command?->table(['Adresse', 'Rôle', 'Fonction', 'Double authentification'], $lignes);
        $this->command?->info("Mot de passe commun de ces comptes : $motDePasse");
    }

    private function creer(string $identifiant, string $libelle, string $role, string $fonction, string $motDePasse): array
    {
        $utilisateur = Utilisateur::updateOrCreate(['courriel' => self::courriel($identifiant)], [
            'nom_affiche' => $libelle,
            'fonction' => $fonction,
            'mot_de_passe' => $motDePasse,
            'actif' => true,
            'mfa_active' => false,
            'mfa_secret' => null,
            'mfa_dernier_pas' => null,
        ]);
        $utilisateur->attribuerRole($role, config('permissions.regles.perimetres', []));

        return [self::courriel($identifiant), $role, $fonction, 'non'];
    }
}

<?php

namespace Database\Factories;

use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Comptes FICTIFS pour les tests et la démonstration (règle 3 du §14).
 *
 * @extends Factory<Utilisateur>
 */
class UtilisateurFactory extends Factory
{
    public static ?string $motDePasse = null;

    public function definition(): array
    {
        return [
            'nom_affiche' => '[Agent fictif '.fake()->unique()->numberBetween(1, 999999).']',
            'courriel' => 'agent.'.fake()->unique()->numberBetween(1, 999999).'@exemple.test',
            'fonction' => 'Agent (fictif)',
            'mot_de_passe' => static::$motDePasse ??= 'Mot-de-passe-fictif-2026',
        ];
    }

    /** Compte désactivé, conservé jusqu'à la date prévue (contrainte utilisateur_desactivation). */
    public function desactive(): static
    {
        return $this->state(fn () => [
            'actif' => false,
            'desactive_le' => now(),
            'conserver_jusqu_au' => now()->addYear()->toDateString(),
        ]);
    }

    /** @param  list<string>  $perimetre */
    public function avecRole(string $role, array $perimetre = []): static
    {
        return $this->afterCreating(fn (Utilisateur $utilisateur) => $utilisateur->attribuerRole($role, $perimetre));
    }
}

<?php

namespace Tests\Feature\BackOffice;

use App\Models\Utilisateur;
use Database\Seeders\ComptesDemonstrationSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Back-office sur le jeu de démonstration FICTIF : un compte par rôle et par référent du §5.2. */
abstract class BackOfficeTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    protected function compte(string $identifiant): Utilisateur
    {
        return ComptesDemonstrationSeeder::compte($identifiant);
    }

    /** Session ouverte comme après la connexion et, pour les rôles qui l'exigent, la double authentification. */
    protected function en(string|Utilisateur $agent): static
    {
        $agent = is_string($agent) ? $this->compte($agent) : $agent;
        $this->app['auth']->forgetGuards();

        return $this->actingAs($agent)->withSession(['mfa_verifie' => true, 'connecte_le' => now()->getTimestamp()]);
    }
}

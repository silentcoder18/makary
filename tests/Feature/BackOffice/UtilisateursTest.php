<?php

namespace Tests\Feature\BackOffice;

use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UtilisateursTest extends TestCase
{
    use RefreshDatabase;

    private Utilisateur $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (config('permissions.roles') as $code => $r) {
            Role::updateOrInsert(['code' => $code], ['libelle' => $r['libelle'], 'mfa_requise' => $r['mfa']]);
        }

        $this->superadmin = Utilisateur::create([
            'nom_affiche' => 'Super Admin',
            'courriel' => 'superadmin.test@makary.test',
            'fonction' => 'Super Administrateur',
            'mot_de_passe' => Hash::make('Secret123!'),
            'actif' => true,
        ]);
        $this->superadmin->attribuerRole('superadmin');
    }

    public function test_la_liste_des_utilisateurs_est_accessible_au_superadmin(): void
    {
        $this->actingAs($this->superadmin)
            ->withSession(['mfa_verifie' => true, 'connecte_le' => now()->getTimestamp()])
            ->get('/admin/utilisateurs')
            ->assertOk()
            ->assertSee('Gestion des utilisateurs')
            ->assertSee('Super Admin');
    }

    public function test_creation_d_un_utilisateur_avec_attribution_de_role(): void
    {
        $this->actingAs($this->superadmin)
            ->withSession(['mfa_verifie' => true, 'connecte_le' => now()->getTimestamp()])
            ->post('/admin/utilisateurs', [
                'nom_affiche' => 'Abakar Ali',
                'courriel' => 'abakar.ali@makary.cm',
                'fonction' => 'Secrétaire Général',
                'role_code' => 'secretaire_general',
                'mot_de_passe' => 'Makary@2026!',
                'mot_de_passe_confirmation' => 'Makary@2026!',
                'actif' => 1,
            ])
            ->assertRedirect('/admin/utilisateurs')
            ->assertSessionHas('statut');

        $cree = Utilisateur::where('courriel', 'abakar.ali@makary.cm')->first();
        $this->assertNotNull($cree);
        $this->assertSame('Abakar Ali', $cree->nom_affiche);
        $this->assertTrue(Hash::check('Makary@2026!', $cree->mot_de_passe));
        $this->assertTrue($cree->roles->contains('code', 'secretaire_general'));
    }

    public function test_modification_d_un_utilisateur_et_son_role(): void
    {
        $agent = Utilisateur::create([
            'nom_affiche' => 'Ousmanou',
            'courriel' => 'ousmanou@makary.cm',
            'fonction' => 'Agent',
            'mot_de_passe' => Hash::make('AncienMdp123!'),
            'actif' => true,
        ]);
        $agent->attribuerRole('secretaire_general');

        $this->actingAs($this->superadmin)
            ->withSession(['mfa_verifie' => true, 'connecte_le' => now()->getTimestamp()])
            ->put("/admin/utilisateurs/{$agent->id}", [
                'nom_affiche' => 'Ousmanou Modifié',
                'courriel' => 'ousmanou.nouveau@makary.cm',
                'fonction' => 'Chef de bureau',
                'role_code' => 'superadmin',
                'actif' => 1,
            ])
            ->assertRedirect('/admin/utilisateurs');

        $agent->refresh();
        $this->assertSame('Ousmanou Modifié', $agent->nom_affiche);
        $this->assertSame('ousmanou.nouveau@makary.cm', $agent->courriel);
        $this->assertTrue($agent->roles->contains('code', 'superadmin'));
        // Le mot de passe n'a pas changé
        $this->assertTrue(Hash::check('AncienMdp123!', $agent->mot_de_passe));
    }

    public function test_basculer_le_statut_d_un_utilisateur(): void
    {
        $agent = Utilisateur::create([
            'nom_affiche' => 'Moussa',
            'courriel' => 'moussa@makary.cm',
            'fonction' => 'Agent',
            'mot_de_passe' => Hash::make('Mdp123!'),
            'actif' => true,
        ]);
        $agent->attribuerRole('secretaire_general');

        $this->actingAs($this->superadmin)
            ->withSession(['mfa_verifie' => true, 'connecte_le' => now()->getTimestamp()])
            ->post("/admin/utilisateurs/{$agent->id}/statut")
            ->assertRedirect();

        $this->assertFalse($agent->fresh()->actif);
    }

    public function test_impossible_de_desactiver_son_propre_compte(): void
    {
        $this->actingAs($this->superadmin)
            ->withSession(['mfa_verifie' => true, 'connecte_le' => now()->getTimestamp()])
            ->post("/admin/utilisateurs/{$this->superadmin->id}/statut")
            ->assertRedirect()
            ->assertSessionHas('erreur');

        $this->assertTrue($this->superadmin->fresh()->actif);
    }
}

<?php

namespace Tests\Feature\Securite;

use App\Models\Utilisateur;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Connexion nominative, double authentification TOTP, limitation des tentatives, déconnexion (§7, §7.1). */
class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    private const MOT_DE_PASSE = 'Mot-de-passe-fictif-2026';

    private function journal(string $action): int
    {
        return DB::table('journal_audit')->where('action', $action)->count();
    }

    private function seConnecter(Utilisateur $utilisateur, string $motDePasse = self::MOT_DE_PASSE)
    {
        return $this->post('/admin/connexion', ['courriel' => $utilisateur->courriel, 'mot_de_passe' => $motDePasse]);
    }

    public function test_pages_publiques_et_en_tetes_de_securite(): void
    {
        $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $reponse = $this->get('/admin/connexion')->assertOk()->assertSee('Adresse électronique professionnelle')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString("frame-ancestors 'none'", $reponse->headers->get('Content-Security-Policy'));
    }

    public function test_back_office_reserve_aux_comptes_connectes(): void
    {
        $this->get('/admin')->assertRedirect('/admin/connexion');
    }

    public function test_connexion_sans_double_authentification_pour_un_secretaire_general(): void
    {
        $sg = Utilisateur::factory()->avecRole('secretaire_general')->create();

        $this->seConnecter($sg)->assertRedirect('/admin');
        $this->assertAuthenticatedAs($sg);
        $this->get('/admin')->assertOk()->assertSee($sg->nom_affiche);
        $this->assertSame(1, $this->journal('connexion.reussie'));
        $this->assertNotNull($sg->fresh()->derniere_connexion);
    }

    public function test_identifiants_incorrects_compte_inconnu_ou_desactive(): void
    {
        $agent = Utilisateur::factory()->avecRole('secretaire_general')->create();
        $ancien = Utilisateur::factory()->desactive()->avecRole('secretaire_general')->create();

        $this->seConnecter($agent, 'mauvais')->assertSessionHasErrors(['courriel' => 'Identifiants incorrects.']);
        $this->seConnecter($ancien)->assertSessionHasErrors(['courriel' => 'Identifiants incorrects.']);
        $this->post('/admin/connexion', ['courriel' => 'inconnu@exemple.test', 'mot_de_passe' => 'x'])
            ->assertSessionHasErrors(['courriel' => 'Identifiants incorrects.']);
        $this->assertGuest();
        $this->assertSame(3, $this->journal('connexion.echec'));
        $this->assertSame(0, DB::table('journal_audit')->where('details', 'like', '%inconnu@%')->count(), 'adresse non conservée en clair');
    }

    public function test_limitation_des_tentatives(): void
    {
        $agent = Utilisateur::factory()->avecRole('secretaire_general')->create();
        for ($i = 0; $i < 5; $i++) {
            $this->seConnecter($agent, 'mauvais')->assertRedirect();
        }
        $this->seConnecter($agent)->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_premiere_activation_puis_connexion_avec_double_authentification(): void
    {
        config(['makary.mfa_active' => true]);
        DB::table('role')->where('code', 'superadmin')->update(['mfa_requise' => true]);

        $google2fa = app(Google2FA::class);
        $validateur = Utilisateur::factory()->avecRole('superadmin')->create();

        // Étape 1 : mot de passe correct, mais la session n'est pas encore ouverte.
        $this->seConnecter($validateur)->assertRedirect('/admin/connexion/mfa');
        $this->assertGuest();

        // Étape 2 : configuration du second facteur, validée par un premier code.
        $this->get('/admin/connexion/mfa')->assertOk()->assertSee('Clé à saisir');
        $secret = session('mfa.secret_provisoire');
        $this->post('/admin/connexion/mfa', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = $google2fa->getCurrentOtp($secret);
        $this->post('/admin/connexion/mfa', ['code' => $code])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($validateur);
        $this->assertTrue($validateur->fresh()->mfa_active);
        $this->assertSame($secret, $validateur->fresh()->mfa_secret);
        $this->assertNotSame($secret, DB::table('utilisateur')->where('id', $validateur->id)->value('mfa_secret'), 'secret chiffré en base');
        $this->assertSame(1, $this->journal('mfa.activation'));

        // Déconnexion, puis nouvelle connexion : le même code ne peut pas être rejoué.
        $this->post('/admin/deconnexion')->assertRedirect('/admin/connexion');
        $this->assertGuest();
        $this->seConnecter($validateur)->assertRedirect('/admin/connexion/mfa');
        $this->get('/admin/connexion/mfa')->assertOk()->assertDontSee('Clé à saisir');
        $this->post('/admin/connexion/mfa', ['code' => $code])->assertSessionHasErrors(['code' => 'Code incorrect ou déjà utilisé.']);
        $this->assertGuest();
        $this->assertSame(1, $this->journal('deconnexion'));
        $this->assertSame(2, $this->journal('mfa.echec'));
    }

    /**
     * Simule le poste de développement. Hors de l'environnement « testing », Laravel vérifie le jeton CSRF :
     * ce contrôle est écarté ici seulement, il est éprouvé par ailleurs (SecuriteBaseTest, recette).
     */
    private function enLocal(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_dispense_de_double_authentification_en_developpement_local_seulement(): void
    {
        config(['makary.mfa_active' => true, 'makary.mfa_dispensee_en_local' => true]);
        DB::table('role')->where('code', 'superadmin')->update(['mfa_requise' => true]);

        $validateur = Utilisateur::factory()->avecRole('superadmin')->create();

        // Hors de l'environnement « local » (ici, les tests), la dispense est sans effet.
        $this->seConnecter($validateur)->assertRedirect('/admin/connexion/mfa');
        $this->assertGuest();

        // Sur le poste de développement : connexion par mot de passe seul, tracée.
        $this->enLocal();
        $this->seConnecter($validateur)->assertRedirect('/admin');
        $this->assertAuthenticatedAs($validateur);
        $this->get('/admin')->assertOk();
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'connexion.reussie')
            ->where('details', 'like', '%mfa_dispensee_en_local%')->count());

        // Dispense retirée en cours de session : le back-office exige de nouveau le second facteur.
        config(['makary.mfa_dispensee_en_local' => false]);
        $this->get('/admin')->assertRedirect('/admin/connexion');
        $this->assertGuest();
    }

    public function test_sans_dispense_la_double_authentification_reste_exigee_en_local(): void
    {
        config(['makary.mfa_active' => true]);
        DB::table('role')->where('code', 'superadmin')->update(['mfa_requise' => true]);

        $validateur = Utilisateur::factory()->avecRole('superadmin')->create();
        $this->enLocal();

        $this->get('/admin/connexion')->assertOk()->assertDontSee('DOUBLE AUTHENTIFICATION DÉSACTIVÉE');
        $this->seConnecter($validateur)->assertRedirect('/admin/connexion/mfa');
        $this->assertGuest();
    }

    public function test_code_suivant_accepte_apres_un_code_utilise(): void
    {
        config(['makary.mfa_active' => true]);
        DB::table('role')->where('code', 'superadmin')->update(['mfa_requise' => true]);

        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey(32);
        $admin = Utilisateur::factory()->avecRole('superadmin')->create();
        $admin->forceFill(['mfa_secret' => $secret, 'mfa_active' => true])->save();

        $this->seConnecter($admin);
        $this->post('/admin/connexion/mfa', ['code' => $google2fa->oathTotp($secret, $google2fa->getTimestamp())])->assertRedirect('/admin');
        $this->post('/admin/deconnexion');

        $this->seConnecter($admin);
        $this->post('/admin/connexion/mfa', ['code' => $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1)])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_un_role_avec_double_authentification_ne_peut_pas_la_contourner(): void
    {
        config(['makary.mfa_active' => true]);
        DB::table('role')->where('code', 'superadmin')->update(['mfa_requise' => true]);

        $validateur = Utilisateur::factory()->avecRole('superadmin')->create();
        $this->seConnecter($validateur);

        // Accès direct au back-office sans code : refusé.
        $this->get('/admin')->assertRedirect('/admin/connexion');
        // Session ouverte par un autre moyen sans second facteur : fermée par le middleware.
        $this->actingAs($validateur)->withSession(['connecte_le' => now()->getTimestamp()])
            ->get('/admin')->assertRedirect('/admin/connexion');
        $this->assertGuest();
    }

    public function test_duree_maximale_de_session(): void
    {
        $redacteur = Utilisateur::factory()->avecRole('secretaire_general')->create();
        $this->seConnecter($redacteur);
        $this->get('/admin')->assertOk();

        $this->travel(8)->hours();
        $this->get('/admin')->assertRedirect('/admin/connexion');
        $this->assertGuest();
        $this->assertSame(1, $this->journal('session.expiree'));
    }

    public function test_compte_desactive_pendant_la_session(): void
    {
        $redacteur = Utilisateur::factory()->avecRole('secretaire_general')->create();
        $this->seConnecter($redacteur);
        $redacteur->forceFill(['actif' => false, 'desactive_le' => now(), 'conserver_jusqu_au' => now()->addYear()])->save();
        // Comme à chaque nouvelle requête réelle, le compte est relu depuis la base.
        $this->app['auth']->forgetGuards();

        $this->get('/admin')->assertRedirect('/admin/connexion');
        $this->assertGuest();
    }
}

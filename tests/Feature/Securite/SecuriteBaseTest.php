<?php

namespace Tests\Feature\Securite;

use App\Domaine\Contexte;
use App\Domaine\Editorial\CircuitEditorial;
use App\Models\Contenu;
use App\Models\Demande;
use Database\Seeders\ComptesDemonstrationSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/** Recette MKY-P3-04 : sécurité de base (XSS, en-têtes, session, erreurs, injections, CSRF, configuration de production). */
class SecuriteBaseTest extends TestCase
{
    use RefreshDatabase;

    private const CHARGE = '<script>alert("xss")</script><img src=x onerror=alert(1)>';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    private function en(string $identifiant): static
    {
        return $this->actingAs(ComptesDemonstrationSeeder::compte($identifiant))->withSession(['mfa_verifie' => true, 'connecte_le' => now()->getTimestamp()]);
    }

    public function test_une_saisie_du_public_est_echappee_dans_le_back_office(): void
    {
        $this->post('/contact', ['objet' => 'Autre', 'nom_contact' => self::CHARGE, 'courriel' => 'xss@exemple.invalid',
            'message' => 'Message '.self::CHARGE, 'consentement' => '1'])->assertRedirect();
        $demande = Demande::where('courriel', 'xss@exemple.invalid')->sole();

        $html = $this->en('gestionnaire.partenaires')->get("/admin/demandes/{$demande->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_le_markdown_publie_est_assaini(): void
    {
        $redacteur = ComptesDemonstrationSeeder::compte('redacteur');
        $contenu = Contexte::enTantQue($redacteur, fn () => tap(new Contenu([
            'type_contenu' => 'actualite', 'module' => 'M10', 'slug' => 'actualite-fictive-assainie', 'titre' => 'Actualité fictive assainie',
            'corps' => "Texte.\n\n".self::CHARGE."\n\n[lien piégé](javascript:alert(1))",
        ]), fn (Contenu $c) => $c->forceFill(['auteur_id' => $redacteur->id])->save()));
        $circuit = new CircuitEditorial;
        $validation = $circuit->soumettre($contenu, $redacteur);
        $editorial = ComptesDemonstrationSeeder::compte('responsable.editorial');
        $circuit->approuver($validation, $editorial);
        $circuit->publier($validation->refresh(), $editorial);

        $html = $this->get('/actualites/'.now()->year.'/actualite-fictive-assainie')->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->getJson('/api/v1/public/contenus/actualite/actualite-fictive-assainie')->assertOk()
            ->assertJsonPath('corps_html', fn (string $h) => ! str_contains($h, '<script>') && ! str_contains($h, 'javascript:'));
    }

    public function test_en_tetes_de_securite_sur_tous_les_types_de_reponse(): void
    {
        $fichier = DB::table('document_version')->join('document', 'document.id', '=', 'document_version.document_id')
            ->where('document.slug', 'budget-primitif-fictif-2027')->value('fichier_id');
        foreach (['/', '/projets/forage-du-village-fictif-a', '/api/v1/public/projets', '/adresse-inconnue', "/fichiers/$fichier/budget.pdf", '/admin/connexion'] as $adresse) {
            $reponse = $this->get($adresse);
            $this->assertNotNull($reponse->headers->get('Content-Security-Policy'), "$adresse : CSP");
            $this->assertSame('nosniff', $reponse->headers->get('X-Content-Type-Options'), "$adresse : nosniff");
            $this->assertSame('strict-origin-when-cross-origin', $reponse->headers->get('Referrer-Policy'), "$adresse : Referrer-Policy");
            $this->assertStringContainsString("'none'", $reponse->headers->get('Content-Security-Policy'), "$adresse : frame-ancestors ou default-src 'none'");
        }
        $this->get('/admin/connexion')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_la_session_change_d_identifiant_a_la_connexion(): void
    {
        $this->get('/admin/connexion');
        $avant = session()->getId();
        $compte = ComptesDemonstrationSeeder::compte('redacteur');
        $compte->forceFill(['mot_de_passe' => 'Mot-de-passe-fictif-recette-2026'])->save();
        $this->post('/admin/connexion', ['courriel' => $compte->courriel, 'mot_de_passe' => 'Mot-de-passe-fictif-recette-2026'])->assertRedirect('/admin');

        $this->assertNotSame($avant, session()->getId(), 'fixation de session');
    }

    public function test_une_erreur_interne_ne_revele_rien_en_production(): void
    {
        config(['app.debug' => false]);
        Route::get('/essai-erreur-interne', fn () => throw new RuntimeException('secret interne de recette'))->middleware('web');
        Route::get('/api/v1/essai-erreur-interne', fn () => throw new RuntimeException('secret interne de recette'));

        $this->get('/essai-erreur-interne')->assertStatus(500)->assertSee('Une erreur est survenue')->assertDontSee('secret interne');
        $this->getJson('/api/v1/essai-erreur-interne')->assertStatus(500)->assertJsonMissingPath('detail')->assertDontSee('secret interne');
    }

    public function test_injections_dans_les_filtres_et_la_recherche(): void
    {
        foreach (["/recherche?q=' OR 1=1 --", '/recherche?q=%25%27%3B%20DROP%20TABLE%20projet%3B--', "/projets?secteur=eau'--", '/projets?localite=a%27%20or%20%271%27=%271',
            '/documents?categorie=budget%27%3B--&annee=2027%20or%201=1'] as $adresse) {
            $this->get($adresse)->assertOk();
        }
        $this->getJson("/api/v1/public/recherche?q=' OR 1=1 --")->assertOk()->assertJsonCount(0);
        $this->getJson("/api/v1/public/projets?localite=a' or '1'='1")->assertStatus(400);
        $this->assertSame(6, DB::table('projet')->count(), 'aucune table altérée');
    }

    public function test_chaque_formulaire_porte_un_jeton_csrf(): void
    {
        $pages = ['/contact', '/partenaires/devenir-partenaire', '/diaspora/suivre-ma-demande', '/admin/connexion'];
        foreach ($pages as $adresse) {
            $this->assertFormulairesProteges($this->get($adresse)->getContent(), $adresse);
        }
        $projet = DB::table('projet')->where('slug', 'forage-du-village-fictif-a')->value('id');
        $this->assertFormulairesProteges($this->en('responsable.projets')->get("/admin/projets/$projet")->getContent(), 'fiche projet du back-office');
        $demande = Demande::first();
        $this->assertFormulairesProteges($this->en('gestionnaire.partenaires')->get("/admin/demandes/{$demande->id}")->getContent(), 'demande du back-office');
    }

    private function assertFormulairesProteges(string $html, string $page): void
    {
        preg_match_all('/<form\b[^>]*method="post"[^>]*>(.*?)<\/form>/si', $html, $formulaires);
        $this->assertNotEmpty($formulaires[1], "$page : aucun formulaire POST");
        foreach ($formulaires[1] as $formulaire) {
            $this->assertStringContainsString('name="_token"', $formulaire, "$page : formulaire sans jeton CSRF");
        }
    }

    public function test_controle_de_la_configuration_de_production(): void
    {
        // Environnement de démonstration : le contrôle refuse la mise en service.
        $this->artisan('makary:controle-production')->assertFailed();

        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://makary.cm', 'session.secure' => true, 'makary.donnees_fictives' => false,
            'database.connections.'.config('database.default').'.username' => 'makary_app', 'makary.mention_information.version' => 'e01-v1',
            'makary.antivirus.executable' => 'clamdscan']);
        $this->artisan('makary:controle-production')->assertSuccessful();

        // La dispense de double authentification du poste de développement ne passe jamais en production (MKY-P3-07).
        config(['makary.mfa_dispensee_en_local' => true]);
        $this->artisan('makary:controle-production')->assertFailed();
    }
}

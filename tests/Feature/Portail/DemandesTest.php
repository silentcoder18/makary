<?php

namespace Tests\Feature\Portail;

use App\Models\Demande;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Formulaires (M04, M05, M06, M11) : enregistrement, accusé avec numéro, suivi par numéro et adresse (E-01, E-06). */
class DemandesTest extends TestCase
{
    use RefreshDatabase;

    private const PARTENAIRE = [
        'organisme' => 'Organisation fictive', 'type_organisme' => 'Fondation', 'nom_contact' => 'Contact fictif, directrice',
        'courriel' => 'Contact.Fictif@Exemple.invalid', 'pays' => 'Pays fictif', 'nature' => 'Financement',
        'projet' => 'forage-du-village-fictif-a', 'message' => 'Message fictif de démonstration pour un partenariat.', 'consentement' => '1',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_les_formulaires_affichent_la_mention_d_information_et_un_consentement_non_coche(): void
    {
        foreach (['/contact', '/investir/manifester-mon-interet', '/partenaires/devenir-partenaire', '/diaspora/declarer-mon-interet'] as $adresse) {
            $html = $this->get($adresse)->assertOk()->getContent();
            $this->assertStringContainsString('Vos données personnelles', $html, $adresse);
            $this->assertMatchesRegularExpression('/<input id="champ-consentement"[^>]*type="checkbox"(?![^>]*checked)[^>]*>/', $html, "$adresse : case non précochée");
            $this->assertStringContainsString('name="_token"', $html, "$adresse : jeton CSRF");
            $this->assertStringContainsString('name="site_web"', $html, "$adresse : champ piège");
        }
    }

    public function test_envoi_accuse_puis_suivi(): void
    {
        $reponse = $this->post('/partenaires/devenir-partenaire', self::PARTENAIRE);
        $reponse->assertRedirect(route('demande.accuse'));

        $demande = Demande::latest('id')->first();
        $this->assertSame('partenaire', $demande->type_demande->value);
        $this->assertSame('contact.fictif@exemple.invalid', $demande->courriel);
        $this->assertNotNull($demande->projet_id, 'projet concerné rattaché');
        $this->assertStringContainsString('Fondation', $demande->objet);
        $this->assertTrue($demande->consentement_donne);
        $this->assertSame(config('makary.mention_information.version'), $demande->texte_information_version);

        $this->get(route('demande.accuse'))->assertOk()->assertSee($demande->numero_suivi);

        // Suivi : numéro et adresse exigés ensemble.
        $this->post('/diaspora/suivre-ma-demande', ['numero' => strtolower($demande->numero_suivi), 'courriel' => 'contact.fictif@exemple.invalid'])
            ->assertOk()->assertSee('Demande '.$demande->numero_suivi)->assertSee('Reçue');
        $this->post('/diaspora/suivre-ma-demande', ['numero' => $demande->numero_suivi, 'courriel' => 'autre@exemple.invalid'])
            ->assertSessionHasErrors('numero');
    }

    public function test_validation_consentement_et_champ_piege(): void
    {
        $avant = Demande::count();
        $this->post('/partenaires/devenir-partenaire', ['consentement' => null] + self::PARTENAIRE)->assertSessionHasErrors('consentement');
        $this->post('/partenaires/devenir-partenaire', ['site_web' => 'https://robot.example'] + self::PARTENAIRE)->assertSessionHasErrors('site_web');
        $this->post('/contact', ['objet' => 'Inventé'] + self::PARTENAIRE)->assertSessionHasErrors('objet');
        $this->assertSame($avant, Demande::count());
    }

    public function test_erreurs_affichees_avec_recapitulatif_accessible(): void
    {
        $this->from('/contact')->post('/contact', ['message' => 'court'])->assertRedirect('/contact');
        $html = $this->get('/contact')->getContent();
        $this->assertStringContainsString('data-erreurs', $html);
        $this->assertStringContainsString('href="#champ-courriel"', $html);
        $this->assertMatchesRegularExpression('/id="champ-courriel"[^>]*aria-invalid="true"/', $html);
    }

    public function test_limitation_des_envois(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/partenaires/devenir-partenaire', self::PARTENAIRE)->assertRedirect();
        }
        $this->post('/partenaires/devenir-partenaire', self::PARTENAIRE)->assertTooManyRequests();
    }
}

<?php

namespace Tests\Feature\Portail;

use App\Models\Projet;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Les 12 rubriques du §3.1 et leurs pages de détail répondent, à l'adresse de l'arborescence P1. */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    /** Toutes les adresses du portail, y compris les pages de détail des données de démonstration. */
    private function adresses(): array
    {
        $annee = now()->year;

        return [
            '/', '/decouvrir', '/decouvrir/histoire', '/decouvrir/territoire', '/decouvrir/localites', '/decouvrir/localites/village-fictif-a',
            '/decouvrir/economie', '/decouvrir/equipements',
            '/le-maire', '/le-maire/vision-et-priorites', '/le-maire/bilans', '/mairie/executif', '/mairie/conseil-municipal', '/mairie/anciens-maires',
            '/mairie/organigramme', '/mairie/services', '/mairie/services/etat-civil',
            '/projets', '/projets?statut=en-execution', '/projets?secteur=eau', '/projets?localite=village-fictif-a', '/projets/realisations',
            '/projets/forage-du-village-fictif-a', '/projets/piste-rurale-du-village-fictif-d', '/projets/maternite-fictive-du-village-e',
            '/investir', '/investir/secteurs', '/investir/secteurs/eau', '/investir/s-implanter', '/investir/manifester-mon-interet',
            '/partenaires', '/partenaires/besoins-de-financement', '/partenaires/nos-partenaires', '/partenaires/devenir-partenaire',
            '/diaspora', '/diaspora/contribuer', '/diaspora/declarer-mon-interet', '/diaspora/suivre-ma-demande',
            '/patrimoine-tourisme', '/patrimoine-tourisme/site-patrimonial-fictif-a', '/patrimoine-tourisme/culture-et-histoire', '/patrimoine-tourisme/informations-pratiques',
            '/actualites', "/actualites/$annee", "/actualites/$annee/actualite-fictive-reunion-de-quartier",
            '/evenements', '/evenements?periode=ce-mois-ci', '/evenements/evenement-fictif-journee-portes-ouvertes',
            '/documents', '/documents?categorie=budget', '/documents/budget', '/documents/budget-primitif-fictif-2027',
            '/carte', '/contact', '/demarches', '/demarches/acte-de-naissance',
            '/recherche', '/recherche?q=forage', '/plan-du-site', '/mentions-legales', '/confidentialite', '/accessibilite', '/credits',
        ];
    }

    public function test_chaque_page_repond_avec_un_seul_titre_et_le_bandeau_de_demonstration(): void
    {
        foreach ($this->adresses() as $adresse) {
            $reponse = $this->get($adresse);
            $reponse->assertOk();
            $html = $reponse->getContent();
            $this->assertSame(1, substr_count($html, '<h1'), "$adresse : un seul titre de niveau 1");
            $this->assertStringContainsString('DONNÉES FICTIVES — DÉMONSTRATION', $html, "$adresse : bandeau de démonstration");
            $this->assertStringContainsString('<html lang="fr">', $html);
            $this->assertStringContainsString('href="#contenu"', $html, "$adresse : lien d’évitement");
            $this->assertStringNotContainsString('style="', $html, "$adresse : aucun style en ligne (CSP)");
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)/', $html, "$adresse : aucun script en ligne (CSP)");
        }
    }

    public function test_les_douze_rubriques_sont_a_un_clic_depuis_l_en_tete(): void
    {
        $html = $this->get('/')->getContent();
        foreach (['/decouvrir', '/le-maire', '/projets', '/investir', '/partenaires', '/diaspora',
            '/actualites', '/patrimoine-tourisme', '/documents', '/carte', '/demarches', '/contact'] as $rubrique) {
            $this->assertStringContainsString('href="'.url($rubrique).'"', $html, $rubrique);
        }
    }

    public function test_le_portail_ne_montre_que_les_donnees_publiques(): void
    {
        $html = $this->get('/projets/forage-du-village-fictif-a')->assertOk()->getContent();

        // Exemple de la fiche projet (§7) : acquis 4 M, recherché 6 M ; le sollicité (3 M, financeur C) n'apparaît pas.
        $this->assertStringContainsString("4\u{202F}000\u{202F}000", $html);
        $this->assertStringContainsString("6\u{202F}000\u{202F}000", $html);
        $this->assertStringNotContainsString("3\u{202F}000\u{202F}000", $html, 'montant sollicité affiché');
        $this->assertStringNotContainsString('Financeur fictif C', $html, 'financeur sollicité affiché');
        $this->assertStringNotContainsString('responsable.projets@', $html);
    }

    public function test_brouillon_et_fiche_retiree_du_catalogue_sont_introuvables(): void
    {
        // L'idée restée en brouillon n'a jamais été publiée.
        $this->get('/projets/amenagement-fictif-des-berges')->assertNotFound();

        $projet = Projet::where('slug', 'forage-du-village-fictif-a')->sole();
        DB::table('projet')->where('id', $projet->id)->update(['visible_catalogue' => false, 'motif_retrait' => 'Motif fictif']);
        $this->get('/projets/forage-du-village-fictif-a')->assertNotFound();
        $this->get('/projets')->assertDontSee('Forage du village fictif A');
    }

    public function test_filtres_des_projets(): void
    {
        $this->get('/projets?statut=acheve')->assertSee('Piste rurale du village fictif D')->assertDontSee('Forage du village fictif A');
        $this->get('/projets?secteur=eau')->assertSee('Forage du village fictif A')->assertDontSee('Piste rurale du village fictif D');
        $this->get('/projets/realisations')->assertSee('Piste rurale du village fictif D')->assertDontSee('Hangar du marché');
    }

    public function test_actualite_programmee_et_retiree_absentes(): void
    {
        $this->get('/actualites')->assertDontSee('Actualité fictive programmée')->assertDontSee('Actualité fictive retirée');
        $this->get('/actualites/'.now()->year.'/actualite-fictive-retiree')->assertNotFound();
        $this->get('/actualites/2001/actualite-fictive-reunion-de-quartier')->assertNotFound();
    }

    public function test_emplacements_a_fournir_sans_faux_texte(): void
    {
        $this->get('/decouvrir/histoire')->assertSee('Contenu à fournir par la Commune');
        $this->get('/carte')->assertSee('Carte en attente de coordonnées vérifiées');
    }

    public function test_recherche(): void
    {
        $this->get('/recherche?q=forage')->assertSee('Forage du village fictif A');
        $this->get('/recherche?q=%25')->assertOk()->assertSee('0 résultat');
    }

    public function test_fiche_de_financement_pdf(): void
    {
        $reponse = $this->get('/projets/forage-du-village-fictif-a/fiche.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $reponse->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);

        // Texte affiché : chaînes des opérateurs Tj, en WinAnsi, réunies ligne à ligne.
        preg_match_all('/\((.*?)(?<!\\\\)\) Tj/s', $pdf, $m);
        $texte = mb_convert_encoding(stripcslashes(implode(' ', $m[1])), 'UTF-8', 'Windows-1252');
        $this->assertStringContainsString('DONNÉES FICTIVES — DÉMONSTRATION', $texte);
        $this->assertStringContainsString('Ce document ne vaut pas engagement de la Commune.', $texte);
        $this->assertStringContainsString('Besoin restant : 6', $texte);
        $this->assertStringNotContainsString('Financeur fictif C', $texte);
    }

    public function test_redirections_de_l_ancien_site(): void
    {
        $this->get('/votre-mairie/')->assertStatus(301)->assertRedirect(route('maire'));
        $this->get('/actes-de-naissances')->assertStatus(301)->assertRedirect(route('demarches.show', 'acte-de-naissance'));
        $this->get('/projets-en-cours')->assertStatus(301)->assertRedirect(route('projets', ['statut' => 'en-execution']));
        $this->get('/formulaire-ecole/')->assertStatus(410)->assertSee('Cette page n’existe plus', false);
        $this->get('/shop/produit')->assertStatus(410);
        $this->get('/adresse-inconnue')->assertNotFound()->assertSee('Page introuvable');
    }
}

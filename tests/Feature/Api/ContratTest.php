<?php

namespace Tests\Feature\Api;

use App\Models\Demande;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\ValidateurContrat;
use Tests\TestCase;

/** API publique v1 : chaque opération du contrat est implémentée et ses réponses sont conformes (MKY-P3-03). */
class ContratTest extends TestCase
{
    use RefreshDatabase;

    /** Champs internes interdits dans toute réponse publique (repris de specs/verifications/verifier_api.mjs). */
    private const INTERDITS = ['lignes_financement', 'statut_ligne', 'partenaire_id', 'nom_contact', 'mot_de_passe', 'adresse_ip', 'demande_par',
        'valide_par', 'decide_par', 'soumis_par', 'auteur_id', 'responsable_id', 'utilisateur_id', 'motif_retrait', 'mfa_active', 'notes',
        'courriel', 'telephone', 'chemin_stockage', 'sollicite'];

    private ValidateurContrat $contrat;

    /** @var array<string, true> opérations exercées */
    private array $exercees = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->contrat = new ValidateurContrat;
    }

    /** Appelle une opération et valide la réponse contre le schéma du contrat pour son statut. */
    private function appeler(string $operation, string $adresse, int $statut, array $corps = []): TestResponse
    {
        [$methode] = explode(' ', $operation);
        $reponse = $methode === 'GET' ? $this->getJson('/api/v1'.$adresse) : $this->postJson('/api/v1'.$adresse, $corps);
        $this->assertSame($statut, $reponse->status(), "$operation ($adresse) : ".$reponse->getContent());
        $this->exercees[$operation] = true;

        $type = strtok((string) $reponse->headers->get('Content-Type'), ';');
        $schema = $this->contrat->schemaReponse($operation, $statut, $type);
        $this->assertNotNull($schema, "$operation : statut $statut et type $type non prévus par le contrat");
        if ($type !== 'application/pdf') {
            $donnee = json_decode($reponse->getContent());
            $this->assertSame([], $this->contrat->valider($donnee, $schema), "$operation ($adresse)");
            $this->assertSame([], array_values(array_intersect(self::INTERDITS, $this->cles($donnee))), "$operation : champ interne exposé");
        }

        return $reponse;
    }

    /** @return list<string> toutes les clés, à toute profondeur */
    private function cles(mixed $donnee): array
    {
        $cles = [];
        foreach (is_object($donnee) ? get_object_vars($donnee) : (is_array($donnee) ? $donnee : []) as $cle => $valeur) {
            if (is_string($cle)) {
                $cles[] = $cle;
            }
            $cles = [...$cles, ...$this->cles($valeur)];
        }

        return $cles;
    }

    public function test_chaque_operation_publique_respecte_le_contrat(): void
    {
        $annee = now()->year;
        $this->appeler('GET /public/projets', '/public/projets', 200);
        $this->appeler('GET /public/projets', '/public/projets?statut=acheve&par_page=1', 200)->assertJsonPath('pagination.total', 1);
        $forage = $this->appeler('GET /public/projets/{slug}', '/public/projets/forage-du-village-fictif-a', 200);
        $forage->assertJsonPath('financement.recherche.montants.0.montant', 6000000)->assertJsonPath('financement.total_acquis.0.montant', 4000000);
        $this->assertStringNotContainsString('Financeur fictif C', $forage->getContent());
        $this->appeler('GET /public/projets/{slug}', '/public/projets/piste-rurale-du-village-fictif-d', 200);
        $this->appeler('GET /public/projets/{slug}/fiche.pdf', '/public/projets/forage-du-village-fictif-a/fiche.pdf', 200);
        $this->appeler('GET /public/contenus', '/public/contenus?type=actualite', 200);
        $this->appeler('GET /public/contenus', '/public/contenus?type=evenement&module=M10', 200);
        $this->appeler('GET /public/contenus/{type}/{slug}', '/public/contenus/demarche/acte-de-naissance', 200);
        $this->appeler('GET /public/elus', '/public/elus', 200);
        $this->appeler('GET /public/elus', '/public/elus?organe=maire', 200)->assertJsonCount(1);
        $this->appeler('GET /public/documents', '/public/documents?annee='.$annee, 200);
        $this->appeler('GET /public/documents/{slug}', '/public/documents/budget-primitif-fictif-2027', 200);
        $this->appeler('GET /public/localites', '/public/localites', 200)->assertJsonCount(6);
        $this->appeler('GET /public/carte', '/public/carte?couches=localites,projets', 200)->assertJsonPath('type', 'FeatureCollection');
        $this->appeler('GET /public/partenaires', '/public/partenaires', 200)->assertJsonMissing(['nom' => 'Financeur fictif C']);
        $this->appeler('GET /public/coordonnees', '/public/coordonnees', 200);
        $this->appeler('GET /public/recherche', '/public/recherche?q=forage', 200)->assertJsonPath('0.nature', 'projet');

        $accuse = $this->appeler('POST /public/demandes', '/public/demandes', 201, [
            'type_demande' => 'diaspora', 'objet' => 'Objet fictif', 'message' => 'Message fictif.', 'courriel' => 'api@exemple.invalid',
            'projet_code' => 'PRJ-'.$annee.'-001', 'version_information' => config('makary.mention_information.version'), 'consentement' => true,
        ]);
        $numero = $accuse->json('numero_suivi');
        $this->assertNotNull(Demande::where('numero_suivi', $numero)->value('projet_id'), 'projet rattaché par son code');
        $this->appeler('POST /public/demandes/suivi', '/public/demandes/suivi', 200, ['numero_suivi' => $numero, 'courriel' => 'api@exemple.invalid'])
            ->assertJsonPath('etat', 'recue');

        $this->assertEqualsCanonicalizing($this->contrat->operationsPubliques(), array_keys($this->exercees), 'opération publique non couverte');
    }

    public function test_le_validateur_detecte_les_ecarts_au_contrat(): void
    {
        $schema = $this->contrat->schemaReponse('GET /public/projets/{slug}', 200, 'application/json');
        $projet = json_decode($this->getJson('/api/v1/public/projets/forage-du-village-fictif-a')->getContent());
        $this->assertSame([], $this->contrat->valider($projet, $schema));

        unset($projet->code);
        $projet->statut = 'termine';
        $projet->version = '3';
        $erreurs = implode("\n", $this->contrat->valider($projet, $schema));
        $this->assertStringContainsString('$.code : champ obligatoire absent', $erreurs);
        $this->assertStringContainsString('$.statut : "termine" hors énumération', $erreurs);
        $this->assertStringContainsString('$.version : type string au lieu de integer', $erreurs);
    }

    public function test_cache_et_etag_des_lectures(): void
    {
        $reponse = $this->getJson('/api/v1/public/localites')->assertOk()->assertHeader('ETag');
        $this->assertStringContainsString('max-age=300', $reponse->headers->get('Cache-Control'));
        $this->getJson('/api/v1/public/localites', ['If-None-Match' => $reponse->headers->get('ETag')])->assertStatus(304);
    }

    public function test_erreurs_au_format_problem_details(): void
    {
        $this->appeler('GET /public/projets', '/public/projets?statut=inconnu', 400)->assertJsonPath('erreurs.0.champ', 'statut');
        $this->appeler('GET /public/projets', '/public/projets?par_page=500', 400);
        $this->appeler('GET /public/contenus', '/public/contenus', 400)->assertJsonPath('erreurs.0.champ', 'type');
        $this->appeler('GET /public/projets/{slug}', '/public/projets/amenagement-fictif-des-berges', 404)->assertJsonPath('status', 404);
        $this->appeler('GET /public/documents/{slug}', '/public/documents/plan-communal-de-developpement-document-fictif', 404);
        $this->appeler('GET /public/carte', '/public/carte?couches=routes', 400);
        $this->appeler('GET /public/recherche', '/public/recherche?q=a', 400);
        $this->appeler('POST /public/demandes/suivi', '/public/demandes/suivi', 404, ['numero_suivi' => 'DEM-2026-ZZZZZZ', 'courriel' => 'x@exemple.invalid']);
    }

    public function test_depot_refuse_sans_consentement_ou_avec_une_mention_perimee(): void
    {
        $base = ['type_demande' => 'contact', 'objet' => 'Objet fictif', 'message' => 'Message fictif.', 'courriel' => 'api@exemple.invalid'];
        $this->appeler('POST /public/demandes', '/public/demandes', 400, [...$base, 'version_information' => config('makary.mention_information.version'), 'consentement' => false])
            ->assertJsonPath('erreurs.0.champ', 'consentement');
        $this->appeler('POST /public/demandes', '/public/demandes', 400, [...$base, 'version_information' => 'ancienne', 'consentement' => true])
            ->assertJsonPath('erreurs.0.champ', 'version_information');
        $this->assertSame(3, Demande::count(), 'aucune demande enregistrée en plus du jeu de démonstration');
    }

    public function test_limitation_du_debit_des_depots(): void
    {
        $corps = ['type_demande' => 'contact', 'objet' => 'Objet fictif', 'message' => 'Message fictif.', 'courriel' => 'api@exemple.invalid',
            'version_information' => config('makary.mention_information.version'), 'consentement' => true];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/public/demandes', $corps)->assertCreated();
        }
        $this->appeler('POST /public/demandes', '/public/demandes', 429, $corps)->assertHeader('Retry-After');
    }
}

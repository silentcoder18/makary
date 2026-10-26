<?php

namespace Tests\Feature\Domaine;

use App\Domaine\Contexte;
use App\Domaine\Editorial\CircuitEditorial;
use App\Domaine\Editorial\Droits;
use App\Domaine\Enums\DroitDiffusion;
use App\Domaine\Enums\DroitUsage;
use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\RegleMetierException;
use App\Models\Document;
use App\Models\Media;
use App\Models\Partenaire;
use App\Models\Publication;
use App\Models\Utilisateur;
use Database\Seeders\Demonstration\Fichiers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Circuit éditorial commun : instantané figé, séparation des tâches, publication et retrait (§7, §8). */
class CircuitEditorialTest extends TestCase
{
    use DonneesFictives, RefreshDatabase;

    private CircuitEditorial $circuit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerComptes();
        $this->circuit = new CircuitEditorial;
    }

    public function test_soumettre_approuver_publier(): void
    {
        $actualite = $this->actualite();

        $validation = $this->circuit->soumettre($actualite, $this->redacteur);
        $this->assertSame(EtatEditorial::EnRevue, $actualite->refresh()->etat);
        $this->assertSame('Actualité fictive', $validation->instantane['titre']);

        $this->circuit->approuver($validation, $this->editorial);
        $publication = $this->circuit->publier($validation->refresh(), $this->editorial);

        $this->assertSame(1, $publication->version);
        $this->assertEquals($validation->instantane, $publication->instantane);
        $this->assertSame(EtatEditorial::Publie, $actualite->refresh()->etat);
        $this->assertSame($publication->id, $actualite->publicationCourante()->id);
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'contenu.publier')->count());
    }

    public function test_le_public_voit_ce_qui_a_ete_approuve_et_non_les_modifications_ulterieures(): void
    {
        $actualite = $this->actualite();
        $validation = $this->circuit->soumettre($actualite, $this->redacteur);
        Contexte::enTantQue($this->redacteur, fn () => $actualite->update(['titre' => 'Titre modifié après la soumission']));

        $this->circuit->approuver($validation, $this->editorial);
        $publication = $this->circuit->publier($validation->refresh(), $this->editorial);

        $this->assertSame('Actualité fictive', $publication->instantane['titre']);
    }

    public function test_nul_ne_valide_sa_propre_soumission(): void
    {
        $agent = Utilisateur::factory()->avecRole('redacteur')->avecRole('validateur', ['M10'])->create();
        $validation = $this->circuit->soumettre($this->actualite($agent), $agent);

        $this->expectException(RegleMetierException::class);
        $this->circuit->approuver($validation, $agent);
    }

    public function test_droits_et_perimetres(): void
    {
        $validation = $this->circuit->soumettre($this->actualite(), $this->redacteur);

        // Le rédacteur ne peut ni approuver ni publier ; un validateur hors du module M10 non plus.
        foreach ([$this->redacteur, $this->rpf] as $agent) {
            try {
                $this->circuit->approuver($validation, $agent);
                $this->fail('Approbation acceptée hors des droits.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        // Un rédacteur ne soumet que ses propres contenus.
        $autre = Utilisateur::factory()->avecRole('redacteur')->create();
        $this->expectException(AuthorizationException::class);
        $this->circuit->soumettre($this->actualite($this->redacteur, 'Autre actualité fictive'), $autre);
    }

    public function test_renvoi_motive_puis_nouvelle_soumission(): void
    {
        $actualite = $this->actualite();
        $validation = $this->circuit->soumettre($actualite, $this->redacteur);

        try {
            $this->circuit->renvoyer($validation, $this->editorial, ' ');
            $this->fail('Renvoi sans motif accepté.');
        } catch (RegleMetierException) {
        }
        $this->circuit->renvoyer($validation, $this->editorial, 'Source à préciser (motif fictif).');
        $this->assertSame(EtatEditorial::Brouillon, $actualite->refresh()->etat);

        $this->expectException(RegleMetierException::class);
        $this->circuit->publier($validation->refresh(), $this->editorial);
    }

    public function test_une_seule_revue_a_la_fois_et_une_publication_par_approbation(): void
    {
        $actualite = $this->actualite();
        $validation = $this->circuit->soumettre($actualite, $this->redacteur);
        try {
            $this->circuit->soumettre($actualite, $this->redacteur);
            $this->fail('Seconde soumission acceptée.');
        } catch (RegleMetierException) {
        }
        $this->circuit->approuver($validation, $this->editorial);
        $this->circuit->publier($validation->refresh(), $this->editorial);

        $this->expectException(RegleMetierException::class);
        $this->circuit->publier($validation->refresh(), $this->editorial);
    }

    public function test_nouvelle_version_et_retrait(): void
    {
        $actualite = $this->actualite();
        $v1 = $this->circuit->soumettre($actualite, $this->redacteur);
        $this->circuit->approuver($v1, $this->editorial);
        $this->circuit->publier($v1->refresh(), $this->editorial);

        Contexte::enTantQue($this->redacteur, fn () => $actualite->update(['titre' => 'Actualité fictive corrigée']));
        $v2 = $this->circuit->soumettre($actualite->refresh(), $this->redacteur);
        $this->circuit->approuver($v2, $this->editorial);
        $this->circuit->publier($v2->refresh(), $this->editorial);

        $this->assertSame(2, $actualite->publicationCourante()->version);
        $this->assertSame('Actualité fictive corrigée', $actualite->publicationCourante()->instantane['titre']);
        $this->assertCount(1, Publication::courantes(TypeObjet::Contenu)->get());

        $this->assertSame(2, $this->circuit->retirer($actualite, $this->editorial, 'Information périmée (motif fictif).'));
        $this->assertNull($actualite->publicationCourante());
        $this->assertSame(EtatEditorial::Retire, $actualite->refresh()->etat);
    }

    public function test_publication_programmee(): void
    {
        $actualite = $this->actualite();
        $validation = $this->circuit->soumettre($actualite, $this->redacteur);
        $this->circuit->approuver($validation, $this->editorial);
        $this->circuit->publier($validation->refresh(), $this->editorial, now()->addDays(2));

        $this->assertNull($actualite->publicationCourante());
        $this->travel(3)->days();
        $this->assertNotNull($actualite->publicationCourante());
    }

    public function test_la_base_refuse_une_publication_qui_ne_reprend_pas_l_instantane_approuve(): void
    {
        $actualite = $this->actualite();
        $validation = $this->circuit->soumettre($actualite, $this->redacteur);
        $this->circuit->approuver($validation, $this->editorial);

        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('publication')->insert([
            'objet_type' => 'contenu', 'objet_id' => $actualite->id, 'version' => 1, 'validation_id' => $validation->id,
            'instantane' => json_encode(['titre' => 'Texte jamais approuvé']), 'publie_par' => $this->editorial->id,
        ]));
    }

    public function test_un_media_n_est_publie_qu_avec_un_droit_d_usage_verifie(): void
    {
        $media = Contexte::enTantQue($this->redacteur, fn () => Media::create([
            'fichier_id' => Fichiers::image('Photo fictive du marché')->id, 'titre' => 'Photo fictive du marché',
            'texte_alternatif' => 'Étals d’un marché fictif', 'auteur' => 'Photographe fictif',
        ]));
        $this->assertSame(DroitUsage::AVerifier, $media->refresh()->droit);

        try {
            $this->circuit->soumettre($media, $this->redacteur);
            $this->fail('Média à vérifier soumis.');
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString("droit d'usage", $e->getMessage());
        }

        $patrimoine = Utilisateur::factory()->avecRole('validateur', ['M08'])->create();
        try {
            (new Droits)->confirmerDroitMedia($media, $patrimoine, DroitUsage::Licence);
            $this->fail('Licence sans justificatif acceptée.');
        } catch (RegleMetierException) {
        }
        (new Droits)->confirmerDroitMedia($media, $patrimoine, DroitUsage::Licence, $this->source('autre', 'Licence fictive'));
        $validation = $this->circuit->soumettre($media->refresh(), $this->redacteur);
        $this->circuit->approuver($validation, $patrimoine);
        $this->assertSame('licence', $this->circuit->publier($validation->refresh(), $patrimoine)->instantane['droit']);
    }

    public function test_un_document_n_est_publie_qu_avec_l_accord_du_referent_juridique(): void
    {
        $juridique = Utilisateur::factory()->avecRole('validateur', ['juridique', 'M07'])->create();
        $document = Contexte::enTantQue($this->redacteur, function () {
            $document = Document::create(['slug' => 'budget-fictif-2027', 'titre' => 'Budget primitif fictif 2027', 'categorie' => 'budget', 'date_document' => '2027-01-10']);
            $document->versions()->create(['numero' => 1, 'fichier_id' => Fichiers::pdf('Budget primitif fictif 2027')->id, 'cree_par' => $this->redacteur->id]);

            return $document;
        });

        try {
            $this->circuit->soumettre($document, $this->redacteur);
            $this->fail('Document interne soumis à la publication.');
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString('référent juridique', $e->getMessage());
        }

        (new Droits)->accorderDiffusion($document, $juridique, DroitDiffusion::Public, $this->source('autre', 'Accord fictif du référent juridique'));
        $validation = $this->circuit->soumettre($document->refresh(), $this->redacteur);
        $this->circuit->approuver($validation, $juridique);
        $publication = $this->circuit->publier($validation->refresh(), $juridique);
        $this->assertSame('application/pdf', $publication->instantane['version']['fichier']['type_mime']);

        (new Droits)->archiverDocument($document, $juridique, 'Remplacé par une version fictive ultérieure.');
        $this->assertTrue($document->refresh()->archive);
        $this->assertNull($document->publicationCourante());
        $this->assertNotNull(Document::find($document->id), "un document archivé n'est jamais détruit");
    }

    public function test_un_partenaire_n_est_cite_qu_avec_son_accord_ecrit(): void
    {
        $gestionnaire = Utilisateur::factory()->avecRole('gestionnaire_partenaires')->create();
        $partenaire = Contexte::enTantQue($gestionnaire, fn () => Partenaire::create(['nom' => 'Financeur fictif B', 'type_partenaire' => 'fondation']));

        $this->expectExceptionMessage('accord écrit');
        $this->circuit->soumettre($partenaire, $gestionnaire);
    }
}

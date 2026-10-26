<?php

namespace Tests\Feature\Portail;

use App\Models\Fichier;
use App\Models\Media;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demonstration\Fichiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Un fichier n'est servi que s'il figure dans une publication en ligne ; il ne peut exécuter aucun script. */
class FichiersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_un_document_publie_est_telechargeable(): void
    {
        $reponse = $this->get('/documents/budget-primitif-fictif-2027/telecharger')->assertRedirect();
        $this->get($reponse->headers->get('Location'))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    }

    public function test_une_image_publiee_est_servie(): void
    {
        $mediaPublie = DB::table('publication')->where('objet_type', 'media')->whereNull('retire_le')->value('objet_id');
        $fichier = Media::findOrFail($mediaPublie)->fichier;

        $this->get(route('fichier', [$fichier->id, 'image.svg']))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_un_fichier_non_publie_ou_interne_repond_404(): void
    {
        // Le document resté interne (plan communal de développement) n'est pas publié.
        $interne = DB::table('document_version')->join('document', 'document.id', '=', 'document_version.document_id')
            ->where('document.slug', 'plan-communal-de-developpement-document-fictif')->value('fichier_id');
        $this->get(route('fichier', [$interne, 'document.pdf']))->assertNotFound();
        $this->get('/documents/plan-communal-de-developpement-document-fictif')->assertNotFound();

        $orphelin = Fichiers::image('Image fictive jamais publiée');
        $this->get(route('fichier', [$orphelin->id, 'image.svg']))->assertNotFound();
        $this->get(route('fichier', [999999, 'rien.pdf']))->assertNotFound();
    }

    public function test_un_fichier_non_analyse_n_est_pas_servi(): void
    {
        $fichierId = DB::table('document_version')->join('document', 'document.id', '=', 'document_version.document_id')
            ->where('document.slug', 'budget-primitif-fictif-2027')->value('fichier_id');
        Fichier::whereKey($fichierId)->update(['analyse_ok' => false]);

        $this->get(route('fichier', [$fichierId, 'budget.pdf']))->assertNotFound();
    }
}

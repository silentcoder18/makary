<?php

namespace Tests\Feature\BackOffice;

use App\Models\Contenu;
use App\Models\ValidationDemande;

/** Circuit éditorial : soumission, examen, approbation, renvoi et publication. */
class CircuitTest extends BackOfficeTestCase
{
    private const ACTUALITE = [
        'type_contenu' => 'actualite', 'module' => 'M10', 'titre' => 'Actualité circuit test back-office', 'slug' => 'actualite-circuit-test-back-office',
        'resume' => 'Résumé test.', 'corps' => "Premier paragraphe test.\n\n- point test",
    ];

    public function test_du_brouillon_a_la_publication_via_validation(): void
    {
        $sg = $this->compte('secretaire_general');
        $admin = $this->compte('superadmin');

        $this->en($sg)->post('/admin/contenus', self::ACTUALITE)->assertRedirect();
        $contenu = Contenu::where('slug', 'actualite-circuit-test-back-office')->sole();
        $this->assertSame('brouillon', $contenu->etat->value);

        $this->en($sg)->post("/admin/contenus/{$contenu->id}/soumettre")->assertRedirect()->assertSessionHas('statut');
        $validation = ValidationDemande::where('objet_type', 'contenu')->where('objet_id', $contenu->id)->sole();

        $this->get('/actualites')->assertDontSee('Actualité circuit test back-office');
        $this->en($admin)->post("/admin/validation/{$validation->id}/approuver")->assertSessionHas('statut');
        $this->en($admin)->post("/admin/validation/{$validation->id}/publier")->assertRedirect(route('admin.validation.index'));

        $this->app['auth']->forgetGuards();
        $this->get('/actualites/'.now()->year.'/actualite-circuit-test-back-office')->assertOk()->assertSee('Premier paragraphe test.');

        // Nettoyage
        $contenu->publications()->delete();
        $contenu->validations()->delete();
        $contenu->delete();
    }

    public function test_renvoi_motive_visible_par_l_auteur(): void
    {
        $sg = $this->compte('secretaire_general');
        $admin = $this->compte('superadmin');

        $this->en($sg)->post('/admin/contenus', self::ACTUALITE);
        $contenu = Contenu::where('slug', 'actualite-circuit-test-back-office')->sole();
        $this->en($sg)->post("/admin/contenus/{$contenu->id}/soumettre");
        $validation = ValidationDemande::where('objet_id', $contenu->id)->where('objet_type', 'contenu')->sole();

        $this->en($admin)->post("/admin/validation/{$validation->id}/renvoyer", [])->assertSessionHasErrors('motif');
        $this->en($admin)->post("/admin/validation/{$validation->id}/renvoyer", ['motif' => 'Préciser la source pour publication.']);
        $this->en($sg)->get("/admin/contenus/{$contenu->id}")->assertSee('Préciser la source pour publication.');

        // Nettoyage
        $contenu->publications()->delete();
        $contenu->validations()->delete();
        $contenu->delete();
    }

    public function test_approbation_et_publication_directe_en_un_clic(): void
    {
        $admin = $this->compte('superadmin');

        $this->en($admin)->post('/admin/contenus', self::ACTUALITE);
        $contenu = Contenu::where('slug', 'actualite-circuit-test-back-office')->sole();
        $this->en($admin)->post("/admin/contenus/{$contenu->id}/soumettre");
        $validation = ValidationDemande::where('objet_id', $contenu->id)->where('objet_type', 'contenu')->sole();

        // 1-clic approbation + publication
        $this->en($admin)->post("/admin/validation/{$validation->id}/approuver-et-publier")
            ->assertRedirect(route('admin.validation.index'))
            ->assertSessionHas('statut');

        $contenu->refresh();
        $this->assertSame('publie', $contenu->etat->value);
        $this->assertNotNull($contenu->publicationCourante());

        // Nettoyage
        $contenu->publications()->delete();
        $contenu->validations()->delete();
        $contenu->delete();
    }
}

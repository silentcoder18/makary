<?php

namespace Tests\Feature\BackOffice;

use App\Models\Contenu;
use App\Models\Demande;
use App\Models\Projet;
use App\Models\ValidationDemande;

/** Chaque section n'est accessible qu'aux rôles qui en détiennent la permission ; toutes les pages s'affichent. */
class AccesTest extends BackOfficeTestCase
{
    public function test_sans_session_le_back_office_est_ferme(): void
    {
        $this->get('/admin/contenus')->assertRedirect('/admin/connexion');
        $this->get('/admin/connexion')->assertOk()->assertSee('Connexion');
    }

    public function test_pages_par_role(): void
    {
        $contenu = Contenu::first();
        $projet = Projet::where('slug', 'forage-du-village-fictif-a')->sole();
        $demande = Demande::first();

        $attendus = [
            'redacteur' => ['/admin' => 200, '/admin/contenus' => 200, "/admin/contenus/{$contenu->id}" => 200, '/admin/contenus/nouveau' => 200,
                '/admin/validation' => 403, '/admin/projets' => 403, '/admin/demandes' => 403, '/admin/journal' => 403],
            'responsable.editorial' => ['/admin/validation' => 200, '/admin/projets' => 200, "/admin/projets/{$projet->id}" => 200, '/admin/contenus/nouveau' => 403],
            'responsable.projets' => ['/admin/projets' => 200, "/admin/projets/{$projet->id}" => 200, '/admin/projets/nouveau' => 200, '/admin/validation' => 403],
            'gestionnaire.partenaires' => ['/admin/demandes' => 200, "/admin/demandes/{$demande->id}" => 200, '/admin/contenus' => 403],
            'lecteur.auditeur' => ['/admin' => 200, '/admin/journal' => 200, '/admin/contenus' => 403, '/admin/demandes' => 403],
            'admin.technique' => ['/admin/journal' => 200, '/admin/contenus' => 403, '/admin/demandes' => 403],
        ];
        foreach ($attendus as $agent => $pages) {
            foreach ($pages as $adresse => $statut) {
                $reponse = $this->en($agent)->get($adresse);
                $this->assertSame($statut, $reponse->status(), "$agent : $adresse");
                if ($statut === 200) {
                    $html = $reponse->getContent();
                    $this->assertSame(1, substr_count($html, '<h1'), "$agent : $adresse — un seul titre");
                    $this->assertStringContainsString('DONNÉES FICTIVES — DÉMONSTRATION', $html, "$agent : $adresse — bandeau");
                    $this->assertStringNotContainsString('style="', $html, "$agent : $adresse — aucun style en ligne");
                }
            }
        }
    }

    public function test_le_menu_ne_montre_que_les_sections_permises(): void
    {
        $this->en('redacteur')->get('/admin')->assertSee('Contenus')->assertDontSee('href="'.route('admin.journal.index').'"', false);
        $this->en('lecteur.auditeur')->get('/admin')->assertSee('href="'.route('admin.journal.index').'"', false)->assertDontSee('href="'.route('admin.contenus.index').'"', false);
    }

    public function test_page_d_examen_d_une_validation(): void
    {
        $this->en('redacteur')->post('/admin/contenus/'.Contenu::where('etat', 'publie')->where('module', 'M10')->value('id').'/soumettre');
        $validation = ValidationDemande::where('decision', 'en_attente')->latest('id')->sole();

        $this->en('responsable.editorial')->get("/admin/validation/{$validation->id}")->assertOk()->assertSee('Version soumise')->assertSee('Approuver');
        $this->en('referent.juridique')->get("/admin/validation/{$validation->id}")->assertForbidden();
    }
}

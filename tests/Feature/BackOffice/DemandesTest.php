<?php

namespace Tests\Feature\BackOffice;

use App\Models\Demande;
use Illuminate\Support\Facades\DB;

/** Les demandes sont qualifiées, affectées, répondues et clôturées ; chaque consultation est journalisée. */
class DemandesTest extends BackOfficeTestCase
{
    public function test_parcours_complet_d_une_demande(): void
    {
        $demande = Demande::where('type_demande', 'investisseur')->sole();
        $gestionnaire = $this->compte('gestionnaire.partenaires');

        $this->en($gestionnaire)->get("/admin/demandes/{$demande->id}")->assertOk()->assertSee('contact2@exemple.invalid');
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'demande.lire')->where('objet_id', $demande->id)->count());

        $this->en($gestionnaire)->post("/admin/demandes/{$demande->id}/cloturer")->assertSessionHas('erreur', fn ($m) => str_contains($m, 'ne peut pas passer'));
        $this->en($gestionnaire)->post("/admin/demandes/{$demande->id}/qualifier", ['priorite' => 1])->assertSessionHas('statut');
        $this->en($gestionnaire)->post("/admin/demandes/{$demande->id}/affecter", ['agent' => $gestionnaire->id])->assertSessionHas('statut');
        $this->en($gestionnaire)->post("/admin/demandes/{$demande->id}/affecter", ['agent' => $this->compte('redacteur')->id]);
        $this->en($gestionnaire)->post("/admin/demandes/{$demande->id}/repondre", ['reponse' => 'Réponse fictive envoyée au demandeur.'])->assertSessionHas('statut');
        $this->en($gestionnaire)->post("/admin/demandes/{$demande->id}/cloturer")->assertSessionHas('statut');

        $demande->refresh();
        $this->assertSame('cloturee', $demande->statut->value);
        $this->assertSame(1, $demande->priorite);
        $this->assertSame($gestionnaire->id, $demande->affectee_a);
        $this->assertNotNull($demande->premiere_reponse_le);
        $this->assertSame(['qualification', 'affectation', 'reponse', 'cloture'], $demande->evenements()->pluck('type')->all());

        // Suivi côté public : la demande apparaît clôturée.
        $this->app['auth']->forgetGuards();
        $this->post('/diaspora/suivre-ma-demande', ['numero' => $demande->numero_suivi, 'courriel' => 'contact2@exemple.invalid'])->assertOk()->assertSee('Clôturée');
    }

    public function test_affectation_seulement_a_un_agent_habilite(): void
    {
        $demande = Demande::first();
        $gestionnaire = $this->compte('gestionnaire.partenaires');
        $this->en($gestionnaire)->post("/admin/demandes/{$demande->id}/qualifier", ['priorite' => 2]);

        $this->en($gestionnaire)->post("/admin/demandes/{$demande->id}/affecter", ['agent' => $this->compte('redacteur')->id])
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'habilité'));
        $this->assertSame('qualifiee', $demande->refresh()->statut->value);
    }

    public function test_les_autres_roles_n_ont_pas_acces_aux_donnees_personnelles(): void
    {
        $demande = Demande::first();
        foreach (['redacteur', 'responsable.projets', 'admin.technique', 'lecteur.auditeur'] as $agent) {
            $this->en($agent)->get("/admin/demandes/{$demande->id}")->assertForbidden();
            $this->en($agent)->post("/admin/demandes/{$demande->id}/qualifier", ['priorite' => 1])->assertForbidden();
        }
        $this->assertSame(0, DB::table('journal_audit')->where('action', 'demande.lire')->count());
    }

    public function test_tableau_de_bord_et_compteur_de_telechargements(): void
    {
        $this->get('/projets/forage-du-village-fictif-a/fiche.pdf')->assertOk();
        $this->get('/projets/forage-du-village-fictif-a/fiche.pdf')->assertOk();

        $html = $this->en('gestionnaire.partenaires')->get('/admin')->assertOk()->getContent();
        $this->assertStringContainsString('demandes reçues, à qualifier', $html);
        $this->assertMatchesRegularExpression('/2 téléchargements de fiches/', $html);
    }
}

<?php

namespace Tests\Feature\BackOffice;

use App\Models\Localite;
use App\Models\Partenaire;
use App\Models\Projet;
use App\Models\ProjetFinancement;
use App\Models\ProjetTransition;
use App\Models\Utilisateur;

/** Les transitions de projet suivent la fiche projet (P1-03) et chaque refus est expliqué. */
class ProjetsTest extends BackOfficeTestCase
{
    private function nouveauProjet(string|Utilisateur $agent = 'responsable.projets'): Projet
    {
        $localite = Localite::first();
        $this->en($agent)->post('/admin/projets', ['titre' => 'Puits fictif du back-office', 'slug' => 'puits-fictif-back-office', 'secteur' => 'eau', 'localites' => [$localite->id]])
            ->assertRedirect();

        return Projet::where('slug', 'puits-fictif-back-office')->sole();
    }

    public function test_creation_puis_transition_refusee_avec_explication(): void
    {
        $projet = $this->nouveauProjet();
        $this->assertSame('idee_identifiee', $projet->statut->value);
        $this->assertMatchesRegularExpression('/^PRJ-\d{4}-\d{3,}$/', $projet->code);

        $this->en('responsable.projets')->get("/admin/projets/{$projet->id}")->assertOk()->assertSee('À compléter')->assertSee('le besoin auquel le projet répond');
        $this->en('responsable.projets')->post("/admin/projets/{$projet->id}/transitions", ['vers' => 'etude_en_cours'])
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'Conditions non remplies') && str_contains($m, 'un résumé'));
        $this->assertSame(0, ProjetTransition::where('projet_id', $projet->id)->count());
    }

    public function test_fiche_completee_transition_validee_par_le_referent_competent(): void
    {
        $projet = $this->nouveauProjet();
        $this->en('responsable.projets')->put("/admin/projets/{$projet->id}", [
            'titre' => $projet->titre, 'secteur' => 'eau', 'resume' => 'Résumé fictif.', 'besoin' => 'Besoin fictif.',
            'besoin_source' => 'Compte rendu fictif', 'besoin_source_date' => '2026-10-01', 'service_responsable' => 'Service fictif',
            'localites' => $projet->localites->pluck('id')->all(),
        ])->assertSessionHas('statut');
        $this->assertNotNull($projet->refresh()->besoin_source_id, 'besoin sourcé');

        $this->en('responsable.projets')->post("/admin/projets/{$projet->id}/transitions", ['vers' => 'etude_en_cours'])->assertSessionHas('statut');
        $transition = ProjetTransition::where('projet_id', $projet->id)->sole();

        $this->en('referent.realisations')->post("/admin/transitions/{$transition->id}/approuver")->assertForbidden();
        $this->en('referent.projets')->post("/admin/transitions/{$transition->id}/refuser", [])->assertSessionHasErrors('motif_refus', null, 'refus');
        $this->en('referent.projets')->post("/admin/transitions/{$transition->id}/approuver")->assertSessionHas('statut');
        $this->assertSame('etude_en_cours', $projet->refresh()->statut->value);
    }

    public function test_un_element_chiffre_exige_sa_source(): void
    {
        $projet = $this->nouveauProjet();
        $this->en('responsable.projets')->post("/admin/projets/{$projet->id}/elements/estimations", [
            '_formulaire' => 'estimations', 'montant' => 5000000, 'devise' => 'XAF', 'date_estimation' => '2026-10-01', 'nature_cout' => 'devis', 'nature' => 'estimation',
        ])->assertSessionHasErrors(['source_intitule', 'source_type', 'source_date'], null, 'estimations');

        $this->en('responsable.projets')->post("/admin/projets/{$projet->id}/elements/estimations", [
            'montant' => 5000000, 'devise' => 'XAF', 'date_estimation' => '2026-10-01', 'nature_cout' => 'devis', 'nature' => 'estimation',
            'source_type' => 'devis', 'source_intitule' => 'Devis fictif', 'source_date' => '2026-10-01',
        ])->assertSessionHas('statut');
        $this->assertSame('5000000.00', $projet->estimations()->sole()->montant);
    }

    public function test_financement_sollicite_puis_acquis_sur_piece(): void
    {
        $projet = Projet::where('slug', 'forage-du-village-fictif-a')->sole();
        $financeur = Partenaire::where('nom', 'Financeur fictif C')->sole();

        $this->en('responsable.projets')->post("/admin/projets/{$projet->id}/financements", [
            'statut_ligne' => 'sollicite', 'partenaire_id' => $financeur->id, 'montant' => 1000000, 'devise' => 'XAF', 'date_information' => '2026-10-01',
            'source_type' => 'autre', 'source_intitule' => 'Dossier fictif', 'source_date' => '2026-10-01',
        ])->assertSessionHas('statut');
        $ligne = ProjetFinancement::where('projet_id', $projet->id)->where('montant', 1000000)->sole();

        $this->en('responsable.projets')->post("/admin/financements/{$ligne->id}/acquis", ['source_type' => 'convention', 'source_intitule' => 'Convention fictive', 'source_date' => '2026-10-02'])
            ->assertForbidden();
        $this->en('referent.projets')->post("/admin/financements/{$ligne->id}/acquis", ['source_type' => 'devis', 'source_intitule' => 'Devis fictif', 'source_date' => '2026-10-02'])
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'convention'));
        $this->en('referent.projets')->post("/admin/financements/{$ligne->id}/acquis", ['source_type' => 'convention', 'source_intitule' => 'Convention fictive', 'source_date' => '2026-10-02'])
            ->assertSessionHas('statut');
        $this->assertSame('acquis', $ligne->refresh()->statut_ligne->value);
    }

    public function test_retrait_du_catalogue_sans_suppression(): void
    {
        $projet = Projet::where('slug', 'forage-du-village-fictif-a')->sole();
        $this->en('responsable.projets')->post("/admin/projets/{$projet->id}/catalogue", ['motif_retrait' => 'Information sensible (motif fictif).'])->assertSessionHas('statut');

        $this->app['auth']->forgetGuards();
        $this->get('/projets/forage-du-village-fictif-a')->assertNotFound();
        $this->assertNotNull(Projet::find($projet->id));
    }
}

<?php

namespace Tests\Feature\Domaine;

use App\Domaine\Contexte;
use App\Domaine\Enums\DecisionValidation;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\Projets\Transitions;
use App\Domaine\RegleMetierException;
use App\Models\Projet;
use App\Models\Utilisateur;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Statuts et transitions d'un projet (fiche projet §3, §8, §14). */
class TransitionsTest extends TestCase
{
    use DonneesFictives, RefreshDatabase;

    private Transitions $transitions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerComptes();
        $this->transitions = new Transitions;
    }

    private function refuse(callable $operation, string $classe = RegleMetierException::class, ?string $message = null): void
    {
        try {
            $operation();
            $this->fail('Opération acceptée alors qu’elle devait être refusée.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($classe, $e, $e->getMessage());
            if ($message) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function test_un_projet_est_cree_au_statut_idee_identifiee_avec_un_code(): void
    {
        $projet = $this->projet();
        $this->assertSame(StatutProjet::IdeeIdentifiee, $projet->statut);
        $this->assertMatchesRegularExpression('/^PRJ-\d{4}-\d{3,}$/', $projet->code);
    }

    public function test_conditions_verifiees_avant_l_etude(): void
    {
        $projet = $this->projet();
        $this->refuse(fn () => $this->transitions->demander($projet, StatutProjet::EtudeEnCours, $this->responsableProjets), message: 'besoin');

        Contexte::enTantQue($this->responsableProjets, fn () => $projet->update([
            'resume' => 'Résumé fictif.', 'besoin' => 'Besoin fictif.', 'besoin_source_id' => $this->source()->id, 'service_responsable' => 'Service fictif',
        ]));
        $transition = $this->transitions->demander($projet->refresh(), StatutProjet::EtudeEnCours, $this->responsableProjets);
        $this->assertSame(DecisionValidation::EnAttente, $transition->decision);
        $this->assertSame(StatutProjet::IdeeIdentifiee, $projet->refresh()->statut, 'une demande ne change pas le statut');

        $projet = $this->transitions->approuver($transition, $this->rpf);
        $this->assertSame(StatutProjet::EtudeEnCours, $projet->statut);
        $this->assertNotNull($transition->refresh()->applique_le);
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'projet.valider_transition')->count());
    }

    public function test_transition_non_prevue_ou_deja_en_attente(): void
    {
        $projet = $this->projet();
        $this->refuse(fn () => $this->transitions->demander($projet, StatutProjet::Acheve, $this->responsableProjets), message: 'pas prévu');

        $this->transitions->demander($projet, StatutProjet::Suspendu, $this->responsableProjets, 'Motif fictif de suspension');
        $this->refuse(fn () => $this->transitions->demander($projet, StatutProjet::Suspendu, $this->responsableProjets, 'Autre motif'), message: 'déjà en attente');
    }

    public function test_separation_des_taches_et_perimetres(): void
    {
        $cumul = Utilisateur::factory()->avecRole('responsable_projets')->avecRole('validateur', ['projets'])->create();
        $projet = Contexte::enTantQue($cumul, fn () => Projet::create(['slug' => 'projet-fictif-cumul', 'titre' => 'Projet fictif', 'secteur' => 'sante']));
        $transition = $this->transitions->demander($projet->refresh(), StatutProjet::Suspendu, $cumul, 'Motif fictif');

        $this->refuse(fn () => $this->transitions->approuver($transition, $cumul), message: 'séparation des tâches');
        $this->refuse(fn () => $this->transitions->approuver($transition, $this->rr), AuthorizationException::class);
        $this->refuse(fn () => $this->transitions->approuver($transition, $this->redacteur), AuthorizationException::class);
        $this->refuse(fn () => $this->transitions->demander($projet, StatutProjet::EtudeEnCours, $this->redacteur), AuthorizationException::class);
        $this->transitions->approuver($transition, $this->rpf);
        $this->assertSame('Motif fictif', $projet->refresh()->motif_suspension);
    }

    public function test_la_base_refuse_un_changement_de_statut_direct(): void
    {
        $projet = $this->projet();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('projet')->where('id', $projet->id)->update(['statut' => 'acheve']));
    }

    public function test_parcours_complet_jusqu_a_acheve_avec_les_referents_competents(): void
    {
        $projet = $this->scenario()->avancerJusqua($this->projet(), StatutProjet::Acheve);

        $this->assertSame(StatutProjet::Acheve, $projet->statut);
        $validateurs = $projet->transitions()->get()->mapWithKeys(fn ($t) => [$t->vers_statut->value => $t->valide_par])->all();
        $this->assertSame($this->rpf->id, $validateurs['finance'], '« financé » : référent des projets et financements');
        $this->assertSame($this->rr->id, $validateurs['en_execution'], '« en exécution » : référent des réalisations');
        $this->assertSame($this->rr->id, $validateurs['acheve'], '« achevé » : référent des réalisations');
        $this->assertCount(7, $validateurs);
    }

    public function test_finance_exige_un_acquis_couvrant_le_cout_ou_un_motif(): void
    {
        $projet = $this->scenario()->avancerJusqua($this->projet(), StatutProjet::FinancementRecherche);
        $this->assertSame(['au moins une ligne de financement acquise et validée'],
            array_slice($this->transitions->conditionsManquantes($projet, $projet->statut, StatutProjet::Finance, null), 0, 1));

        $projet = $this->scenario()->avancerJusqua($projet, StatutProjet::Finance, ['cout' => 10_000_000, 'acquis' => 4_000_000]);
        $this->assertSame(StatutProjet::Finance, $projet->statut);
        $this->assertStringContainsString('financement partiel', $projet->transitions()->where('vers_statut', 'finance')->value('motif'));
    }

    public function test_en_execution_et_acheve_exigent_leurs_pieces(): void
    {
        $projet = $this->scenario()->avancerJusqua($this->projet(), StatutProjet::Finance);
        $this->refuse(fn () => $this->transitions->demander($projet, StatutProjet::EnExecution, $this->responsableProjets), message: 'ordre_service');

        $projet = $this->scenario()->avancerJusqua($projet, StatutProjet::EnExecution, ['avancement' => 40]);
        $this->assertSame('40.00', $projet->refresh()->avancement_pct);
        $piece = $projet->pieces()->where('type_piece', 'ordre_service')->first();
        $this->refuse(fn () => $this->transitions->demander($projet, StatutProjet::Acheve, $this->responsableProjets, null, $piece), message: 'pv_reception');
    }
}

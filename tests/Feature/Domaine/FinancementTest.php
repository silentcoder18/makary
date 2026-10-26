<?php

namespace Tests\Feature\Domaine;

use App\Domaine\Enums\StatutLigneFinancement;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\Projets\Financement;
use App\Domaine\RegleMetierException;
use App\Models\Partenaire;
use App\Models\Utilisateur;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Lignes de financement : seul l'« acquis » justifié et validé compte comme acquis (§8). */
class FinancementTest extends TestCase
{
    use DonneesFictives, RefreshDatabase;

    private Financement $financement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerComptes();
        $this->financement = new Financement;
    }

    private function ligneSollicitee(?Utilisateur $par = null)
    {
        $projet = $this->scenario()->avancerJusqua($this->projet(), StatutProjet::FinancementRecherche);
        $financeur = Partenaire::create(['nom' => 'Financeur fictif B', 'type_partenaire' => 'bailleur_multilateral']);

        return $this->financement->ajouterLigne($projet, $par ?? $this->responsableProjets, StatutLigneFinancement::Sollicite, [
            'partenaire_id' => $financeur->id, 'montant' => 4_000_000, 'devise' => 'XAF', 'date_information' => '2027-02-01',
            'source_id' => $this->source('autre', 'Dossier de demande fictif')->id,
        ]);
    }

    public function test_une_ligne_ne_peut_pas_etre_saisie_directement_comme_acquise(): void
    {
        $projet = $this->projet();
        $this->expectException(RegleMetierException::class);
        $this->financement->ajouterLigne($projet, $this->responsableProjets, StatutLigneFinancement::Acquis, [
            'montant' => 1, 'devise' => 'XAF', 'date_information' => '2027-02-01', 'source_id' => $this->source()->id,
        ]);
    }

    public function test_validation_d_une_ligne_acquise_sur_piece_par_le_referent(): void
    {
        $ligne = $this->ligneSollicitee();
        try {
            $this->financement->validerAcquis($ligne, $this->rpf, $this->source('devis', 'Devis fictif'));
            $this->fail('Un devis a été accepté comme pièce d’un financement acquis.');
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString('convention', $e->getMessage());
        }

        $ligne = $this->financement->validerAcquis($ligne->refresh(), $this->rpf, $this->source('convention', 'Convention fictive'));
        $this->assertSame(StatutLigneFinancement::Acquis, $ligne->statut_ligne);
        $this->assertSame($this->rpf->id, $ligne->valide_par);
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'financement.valider_acquis')->count());
    }

    public function test_le_referent_des_realisations_ne_valide_pas_un_financement(): void
    {
        $ligne = $this->ligneSollicitee();
        $this->expectException(AuthorizationException::class);
        $this->financement->validerAcquis($ligne, $this->rr, $this->source('convention'));
    }

    public function test_nul_ne_valide_sa_propre_saisie(): void
    {
        $cumul = Utilisateur::factory()->avecRole('responsable_projets')->avecRole('validateur', ['projets'])->create();
        $ligne = $this->ligneSollicitee($cumul);
        $this->expectExceptionMessage('séparation des tâches');
        $this->financement->validerAcquis($ligne, $cumul, $this->source('convention'));
    }

    public function test_synthese_de_l_exemple_de_la_fiche_projet(): void
    {
        // Fiche projet §7 : coût 10 000 000, acquis 4 000 000, sollicité 3 000 000 → affiché recherché 6 000 000.
        $projet = $this->scenario()->avancerJusqua($this->projet(), StatutProjet::Finance, ['cout' => 10_000_000, 'acquis' => 4_000_000, 'sollicite' => 3_000_000]);
        $synthese = $projet->syntheseFinanciere();

        $this->assertSame('XAF', $synthese['devise']);
        $this->assertSame('10000000.00', $synthese['cout']);
        $this->assertSame('4000000.00', $synthese['acquis']);
        $this->assertSame('3000000.00', $synthese['sollicite']);
        $this->assertSame('6000000.00', $synthese['recherche'], 'le sollicité n’est pas déduit du besoin');
    }
}

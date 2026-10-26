<?php

namespace Tests\Feature\Domaine;

use App\Domaine\Demandes\Demandes;
use App\Domaine\Enums\BaseLegale;
use App\Domaine\Enums\StatutDemande;
use App\Domaine\Enums\TypeDemande;
use App\Domaine\RegleMetierException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Demandes du public : numéro de suivi, consentement, fin de conservation et purge (E-01, E-04). */
class DemandesTest extends TestCase
{
    use RefreshDatabase;

    private function enregistrer(bool $consentement = true)
    {
        return (new Demandes)->enregistrer(TypeDemande::Diaspora, [
            'objet' => 'Objet fictif', 'message' => 'Message fictif.', 'nom_contact' => 'Contact fictif', 'courriel' => 'contact@exemple.invalid',
        ], BaseLegale::Consentement, $consentement, 'mention-e01-v1');
    }

    public function test_enregistrement_avec_numero_de_suivi_et_fin_de_conservation(): void
    {
        $demande = $this->enregistrer();

        $this->assertMatchesRegularExpression('/^DEM-\d{4}-[A-Z0-9]{6}$/', $demande->numero_suivi);
        $this->assertSame(StatutDemande::Recue, $demande->statut);
        $this->assertSame(now()->addDays(730)->toDateString(), $demande->conserver_jusqu_au->toDateString());
        $this->assertArrayNotHasKey('courriel', $demande->toArray(), 'données personnelles masquées à la sérialisation');
    }

    public function test_consentement_exige(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->enregistrer(false);
    }

    public function test_purge_des_demandes_echues(): void
    {
        $demande = $this->enregistrer();
        // La purge compare à la date du serveur de base de données : la demande est vieillie en base.
        DB::table('demande')->where('id', $demande->id)->update(['recue_le' => '2024-01-01 10:00:00', 'conserver_jusqu_au' => '2025-12-31']);
        $recente = $this->enregistrer();

        $this->artisan('makary:purger')->assertSuccessful();

        $ligne = DB::table('demande')->where('id', $demande->id)->first();
        $this->assertNull($ligne->courriel);
        $this->assertNull($ligne->nom_contact);
        $this->assertSame('[anonymisé]', $ligne->message);
        $this->assertSame('contact@exemple.invalid', DB::table('demande')->where('id', $recente->id)->value('courriel'), 'demande non échue conservée');
        $this->assertSame(1, DB::table('journal_audit')->where('action', 'purge.conservation')->count());
    }
}

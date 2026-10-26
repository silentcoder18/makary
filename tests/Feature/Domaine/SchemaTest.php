<?php

namespace Tests\Feature\Domaine;

use App\Domaine\Contexte;
use App\Domaine\Enums;
use App\Domaine\ObjetsPubliables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** Le schéma de l'application est celui de la spécification specs/donnees (MKY-P1-04, migration 0002), sur MySQL/MariaDB (D13). */
class SchemaTest extends TestCase
{
    use DonneesFictives, RefreshDatabase;

    public function test_le_sql_du_domaine_est_derive_de_la_specification(): void
    {
        $this->artisan('makary:schema', ['--verifier' => true])->assertSuccessful();
    }

    public function test_toutes_les_tables_et_vues_de_la_specification_existent(): void
    {
        $specification = '';
        foreach (glob(base_path('../specs/donnees/migrations/*.sql')) as $fichier) {
            $specification .= file_get_contents($fichier);
        }
        preg_match_all('/^CREATE (?:TABLE|VIEW) (\w+)/m', $specification, $m);
        $attendues = $m[1];
        $presentes = array_column(DB::select('SELECT table_name AS nom FROM information_schema.tables WHERE table_schema = DATABASE()'), 'nom');

        $this->assertGreaterThanOrEqual(36, count($attendues));
        $this->assertSame([], array_values(array_diff($attendues, $presentes)));
    }

    public function test_les_enumerations_php_sont_celles_de_la_base(): void
    {
        $specification = file_get_contents(base_path('../specs/donnees/migrations/0001_schema_initial.sql'));
        foreach (glob(app_path('Domaine/Enums/*.php')) as $fichier) {
            /** @var class-string<\BackedEnum> $enum */
            $enum = 'App\\Domaine\\Enums\\'.basename($fichier, '.php');
            $attendues = array_column($enum::cases(), 'value');

            // Valeurs de référence déclarées en tête de la spécification (section 1), lignes de suite comprises.
            $this->assertSame(1, preg_match('/^--\s+'.$enum::ENUMERATION.'\s+:(.+(?:\R--\s{10,}[^:\r\n]+)*)/m', $specification, $m), $enum);
            $this->assertSame($attendues, preg_split('/[\s,]+/', trim(str_replace('--', '', $m[1]))), "$enum : section 1 de la spécification");

            // Contraintes CHECK effectivement appliquées par la base.
            $this->assertNotEmpty($enum::COLONNES, $enum);
            foreach ($enum::COLONNES as $colonne) {
                $contrainte = 'chk_'.str_replace('.', '_', $colonne);
                $clause = DB::selectOne('SELECT check_clause AS clause FROM information_schema.check_constraints
                    WHERE constraint_schema = DATABASE() AND constraint_name = ?', [$contrainte])?->clause;
                $this->assertNotNull($clause, "$enum : contrainte $contrainte absente");
                preg_match_all("/'([^']*)'/", $clause, $valeurs);
                $this->assertSame($attendues, $valeurs[1], "$enum : $contrainte");
            }
        }
    }

    /** L'historique (§8) suit chaque colonne : une colonne ajoutée sans régénérer les déclencheurs serait perdue. */
    public function test_les_declencheurs_d_historique_couvrent_toutes_les_colonnes(): void
    {
        $declencheurs = DB::select("SELECT event_object_table AS nom_table, action_statement AS corps FROM information_schema.triggers
            WHERE trigger_schema = DATABASE() AND trigger_name LIKE 'historique\_%\_modification'");
        $this->assertCount(13, $declencheurs);
        foreach ($declencheurs as $declencheur) {
            $colonnes = array_column(DB::select("SELECT column_name AS nom FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND extra NOT LIKE '%GENERATED%'", [$declencheur->nom_table]), 'nom');
            foreach ($colonnes as $colonne) {
                foreach (['OLD', 'NEW'] as $ligne) {
                    $this->assertStringContainsString("'$colonne', $ligne.$colonne", $declencheur->corps, "historique de {$declencheur->nom_table}");
                }
            }
        }
    }

    /** Une variable de session ne suit pas l'annulation d'une transaction : l'agent transmis à la base est rétabli dans tous les cas. */
    public function test_l_agent_transmis_a_la_base_est_retabli_meme_apres_une_erreur(): void
    {
        $this->creerComptes();
        $agent = fn () => DB::selectOne('SELECT @app_utilisateur_id AS id')->id;
        $avant = $agent();

        Contexte::enTantQue($this->editorial, function () use ($agent) {
            $this->assertEquals($this->editorial->id, $agent());
            Contexte::enTantQue($this->redacteur, fn () => $this->assertEquals($this->redacteur->id, $agent()));
            $this->assertEquals($this->editorial->id, $agent());
        });
        $this->assertSame($avant, $agent());

        try {
            Contexte::enTantQue($this->redacteur, fn () => throw new RuntimeException('Erreur fictive'));
        } catch (RuntimeException) {
        }
        $this->assertSame($avant, $agent());
    }

    public function test_la_creation_des_bases_est_reservee_au_developpement(): void
    {
        $this->artisan('makary:creer-base', ['bases' => ['makary_test']])->assertSuccessful();   // déjà présente : inchangée
        $this->artisan('makary:creer-base', ['bases' => ['makary`; DROP DATABASE makary_test; --']])->assertFailed();

        $this->app['env'] = 'production';
        $this->artisan('makary:creer-base', ['bases' => ['makary_test']])->assertFailed();
    }

    public function test_chaque_type_publiable_a_son_modele(): void
    {
        $this->assertSame(array_column(Enums\TypeObjet::cases(), 'value'), array_keys(ObjetsPubliables::MODELES));
        foreach (ObjetsPubliables::MODELES as $type => $modele) {
            $this->assertSame($type, $modele::TYPE_OBJET->value);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Domaine\Enums\DecisionValidation;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\Enums\TypeObjet;
use App\Models\Projet;
use App\Models\Publication;
use App\Models\Utilisateur;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/** Les données de démonstration sont fictives, signalées et interdites en production (règle 3 du §14). */
class DonneesDemonstrationTest extends TestCase
{
    use RefreshDatabase;

    /** Colonnes portant un nom, un titre ou un intitulé : chacune doit se déclarer fictive. */
    private const LIBELLES = [
        'utilisateur' => ['nom_affiche'], 'localite' => ['nom'], 'equipement' => ['nom'], 'elu' => ['nom_affiche'],
        'partenaire' => ['nom'], 'projet' => ['titre'], 'contenu' => ['titre'], 'document' => ['titre'],
        'source' => ['intitule'], 'media' => ['titre', 'auteur'], 'demande' => ['nom_contact', 'organisme'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_un_compte_fictif_par_role_et_par_referent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(11, Utilisateur::count());
        $roles = DB::table('utilisateur_role')->distinct()->pluck('role_code')->sort()->values()->all();
        $this->assertSame(collect(array_keys(config('permissions.roles')))->sort()->values()->all(), $roles);
        $this->assertSame(['realisations'], Utilisateur::where('courriel', 'referent.realisations@demo.makary.test')->sole()->rolesAvecPerimetres()['validateur']);
    }

    public function test_aucun_nom_reel_chaque_libelle_est_signale_fictif(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (self::LIBELLES as $table => $colonnes) {
            foreach ($colonnes as $colonne) {
                $valeurs = DB::table($table)->whereNotNull($colonne)->pluck($colonne);
                $this->assertNotEmpty($valeurs, "$table.$colonne vide");
                foreach ($valeurs as $valeur) {
                    $this->assertMatchesRegularExpression('/fictif|fictive|démonstration/iu', $valeur, "$table.$colonne : « $valeur »");
                }
            }
        }
        foreach (DB::table('utilisateur')->pluck('courriel')->merge(DB::table('demande')->pluck('courriel')) as $courriel) {
            $this->assertMatchesRegularExpression('/@(demo\.makary\.test|exemple\.invalid)$/', $courriel);
        }
        $this->assertSame(0, DB::table('localite')->whereNotNull('latitude')->count(), 'aucune coordonnée inventée');
    }

    public function test_le_jeu_couvre_les_modules_et_passe_par_le_circuit(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['contenu', 'projet', 'document', 'elu', 'partenaire', 'localite', 'equipement', 'media'] as $type) {
            $this->assertNotEmpty(Publication::courantes(TypeObjet::from($type))->get(), "aucune publication en ligne : $type");
        }
        $modules = DB::table('contenu')->distinct()->pluck('module')->all();
        foreach (['M01', 'M02', 'M04', 'M06', 'M08', 'M10'] as $module) {
            $this->assertContains($module, $modules);
        }
        // Chaque publication provient d'une validation approuvée par une autre personne que l'auteur.
        $this->assertSame(0, DB::table('publication')->join('validation_demande as v', 'v.id', '=', 'publication.validation_id')
            ->where(fn ($q) => $q->where('v.decision', '<>', DecisionValidation::Approuve->value)->orWhereColumn('v.decide_par', 'v.soumis_par'))->count());

        $statuts = Projet::pluck('statut')->map->value->sort()->values()->all();
        $this->assertSame(['acheve', 'en_execution', 'etude_en_cours', 'financement_recherche', 'idee_identifiee', 'suspendu'], $statuts);

        // Exemple de la fiche projet (§7) : coût 10 M, acquis 4 M, sollicité 3 M → recherché affiché 6 M XAF.
        $forage = Projet::where('titre', 'Forage du village fictif A')->sole();
        $financement = $forage->publicationCourante()->instantane['financement'];
        $this->assertSame(['4000000.00', '6000000.00'], [$financement['acquis'], $financement['recherche']]);
        $this->assertSame(StatutProjet::FinancementRecherche, $forage->statut);
        $this->assertSame(3, DB::table('demande')->count());
    }

    public function test_refuse_en_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        // Appel direct : en production, la commande db:seed demanderait d'abord une confirmation.
        $this->app->make(DatabaseSeeder::class)->run();
    }

    public function test_refuse_si_les_donnees_ne_sont_plus_fictives(): void
    {
        config(['makary.donnees_fictives' => false]);
        $this->expectException(RuntimeException::class);
        $this->seed(DatabaseSeeder::class);
    }
}

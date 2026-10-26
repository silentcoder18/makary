<?php

namespace Tests\Feature\Domaine;

use App\Domaine\Contexte;
use App\Domaine\Editorial\CircuitEditorial;
use App\Domaine\Enums\StatutProjet;
use App\Models\Media;
use App\Models\Partenaire;
use App\Models\Source;
use Database\Seeders\Demonstration\Fichiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/** Une publication ne contient que des champs publics (fiche projet, colonne « Vis. »). */
class InstantanePublicTest extends TestCase
{
    use DonneesFictives, RefreshDatabase;

    /** Clés qui ne doivent jamais apparaître dans un instantané public, à aucun niveau. */
    private const CLES_INTERNES = ['responsable_id', 'auteur_id', 'etat', 'visible_catalogue', 'motif_retrait', 'sollicite',
        'mot_de_passe', 'courriel', 'cree_par', 'valide_par', 'televerse_par', 'chemin_stockage', 'conserver_jusqu_au', 'statut_ligne'];

    public function test_l_instantane_d_un_projet_publie_n_expose_que_les_champs_publics(): void
    {
        $this->creerComptes();
        $cite = Partenaire::create(['nom' => 'Financeur fictif B', 'type_partenaire' => 'bailleur_multilateral']);
        $cite->forceFill(['accepte_citation' => true, 'accord_citation_id' => $this->source('autre', 'Accord fictif de citation')->id])->save();
        $projet = $this->scenario()->avancerJusqua($this->projet(), StatutProjet::Finance, [
            'cout' => 10_000_000, 'acquis' => 4_000_000, 'sollicite' => 3_000_000, 'financeur' => $cite,
        ]);
        // Informations à ne pas publier : bénéficiaires « à confirmer », description interne, média non vérifié.
        Contexte::enTantQue($this->responsableProjets, function () use ($projet) {
            $projet->beneficiaires()->create(['nombre' => 99999, 'categorie' => 'élèves', 'methode_estimation' => 'Rumeur fictive',
                'date_estimation' => '2027-01-01', 'nature' => 'a_confirmer', 'source_id' => Source::first()->id]);
            $projet->update(['description_publique' => false]);
            $media = Media::create(['fichier_id' => Fichiers::image('Photo fictive non vérifiée')->id, 'titre' => 'Photo fictive non vérifiée',
                'texte_alternatif' => 'Illustration fictive', 'auteur' => 'Auteur fictif']);
            $projet->medias()->attach($media->id);
        });

        $circuit = new CircuitEditorial;
        $validation = $circuit->soumettre($projet->refresh(), $this->responsableProjets);
        $circuit->approuver($validation, $this->rpf);
        $instantane = $circuit->publier($validation->refresh(), $this->rpf)->instantane;

        $cles = array_map(fn ($cle) => last(explode('.', $cle)), array_keys(Arr::dot($instantane)));
        $this->assertSame([], array_values(array_intersect(self::CLES_INTERNES, $cles)));

        $this->assertNull($instantane['description_technique'], 'description technique non publique');
        $this->assertNotContains(99999, array_column($instantane['beneficiaires'], 'nombre'), 'information « à confirmer » publiée');
        $this->assertSame([], $instantane['medias'], 'média sans droit vérifié publié');
        $this->assertSame('4000000.00', $instantane['financement']['acquis']);
        $this->assertSame('6000000.00', $instantane['financement']['recherche']);
        $this->assertCount(1, $instantane['financement']['lignes_acquises'], 'lignes sollicitées publiées');
        $this->assertSame('Financeur fictif B', $instantane['financement']['lignes_acquises'][0]['financeur']);
        $this->assertSame('Service technique communal (fictif)', $instantane['service_responsable']);
        $this->assertSame('finance', $instantane['statut']['code']);
    }

    public function test_un_financeur_qui_n_accepte_pas_d_etre_cite_reste_anonyme(): void
    {
        $this->creerComptes();
        $projet = $this->scenario()->avancerJusqua($this->projet(), StatutProjet::Finance);

        $this->assertNull($projet->instantanePublic()['financement']['lignes_acquises'][0]['financeur']);
    }
}

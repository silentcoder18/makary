<?php

namespace Tests\Feature\Domaine;

use App\Domaine\Contexte;
use App\Models\Contenu;
use App\Models\Localite;
use App\Models\Projet;
use App\Models\Source;
use App\Models\Utilisateur;
use Database\Seeders\Demonstration\ScenarioProjet;
use Illuminate\Support\Facades\Storage;

/** Comptes et objets FICTIFS des tests du domaine (règle 3 du §14). */
trait DonneesFictives
{
    protected Utilisateur $redacteur;

    protected Utilisateur $responsableProjets;

    /** Référent des projets et financements (RPF, §5.2). */
    protected Utilisateur $rpf;

    /** Référent des réalisations (RR, §5.2). */
    protected Utilisateur $rr;

    /** Responsable éditorial (§5.2) : validateur des actualités et événements. */
    protected Utilisateur $editorial;

    protected function creerComptes(): void
    {
        Storage::fake('local');
        $this->redacteur = Utilisateur::factory()->avecRole('redacteur')->create(['nom_affiche' => '[Rédacteur fictif]']);
        $this->responsableProjets = Utilisateur::factory()->avecRole('responsable_projets')->create(['nom_affiche' => '[Responsable projets fictif]']);
        $this->rpf = Utilisateur::factory()->avecRole('validateur', ['projets', 'M03', 'M05'])->create(['nom_affiche' => '[Référent projets fictif]']);
        $this->rr = Utilisateur::factory()->avecRole('validateur', ['realisations'])->create(['nom_affiche' => '[Référent réalisations fictif]']);
        $this->editorial = Utilisateur::factory()->avecRole('validateur', ['M10'])->create(['nom_affiche' => '[Responsable éditorial fictif]']);
    }

    protected function source(string $type = 'estimation_interne', string $intitule = 'Source fictive'): Source
    {
        return Source::create(['type_source' => $type, 'intitule' => $intitule, 'date_source' => '2027-01-15']);
    }

    protected function localite(string $nom = 'Village fictif A'): Localite
    {
        return Localite::create(['nom' => $nom, 'slug' => str($nom)->slug(), 'categorie' => 'village', 'source_id' => $this->source('autre', 'Liste fictive des localités')->id]);
    }

    protected function scenario(): ScenarioProjet
    {
        return new ScenarioProjet($this->responsableProjets, $this->rpf, $this->rr);
    }

    protected function projet(string $titre = 'Forage du village fictif A'): Projet
    {
        return $this->scenario()->creer($titre, 'eau', [$this->localite()]);
    }

    protected function actualite(?Utilisateur $auteur = null, string $titre = 'Actualité fictive'): Contenu
    {
        $auteur ??= $this->redacteur;

        return Contexte::enTantQue($auteur, fn () => tap(new Contenu([
            'type_contenu' => 'actualite', 'module' => 'M10', 'slug' => str($titre)->slug(), 'titre' => $titre,
            'resume' => 'Résumé fictif.', 'corps' => 'Corps fictif.',
        ]), fn (Contenu $contenu) => $contenu->forceFill(['auteur_id' => $auteur->id])->save()));
    }
}

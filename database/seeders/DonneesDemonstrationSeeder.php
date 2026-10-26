<?php

namespace Database\Seeders;

use App\Domaine\Contexte;
use App\Domaine\Demandes\Demandes;
use App\Domaine\Editorial\CircuitEditorial;
use App\Domaine\Editorial\Droits;
use App\Domaine\Enums\BaseLegale;
use App\Domaine\Enums\DroitDiffusion;
use App\Domaine\Enums\DroitUsage;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\Enums\TypeDemande;
use App\Domaine\Projets\Transitions;
use App\Domaine\Publiable;
use App\Models\Contenu;
use App\Models\Document;
use App\Models\Elu;
use App\Models\Equipement;
use App\Models\Localite;
use App\Models\Media;
use App\Models\Partenaire;
use App\Models\Source;
use App\Models\Utilisateur;
use Database\Seeders\Demonstration\Fichiers;
use Database\Seeders\Demonstration\ScenarioProjet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Jeu de données FICTIVES de démonstration des 12 modules (règle 3 du §14). Aucun nom, chiffre, partenaire
 * ni financement réel : chaque libellé porte la mention « fictif », les informations que seule la Commune peut
 * fournir sont marquées « [À fournir par la Commune] ». Tout passe par les services métier : chaque publication
 * a été soumise, approuvée par une autre personne du bon périmètre, puis publiée.
 */
class DonneesDemonstrationSeeder extends Seeder
{
    private const A_FOURNIR = '[À fournir par la Commune]';

    private CircuitEditorial $circuit;

    private Droits $droits;

    /** @var array<string, Utilisateur> */
    private array $agents;

    /** @var array<string, Localite> */
    private array $localites = [];

    public function run(): void
    {
        $this->circuit = new CircuitEditorial;
        $this->droits = new Droits;
        $this->agents = collect(['admin.editorial', 'redacteur', 'responsable.projets', 'gestionnaire.partenaires', 'responsable.validation',
            'responsable.editorial', 'referent.realisations', 'referent.projets', 'referent.juridique'])
            ->mapWithKeys(fn (string $id) => [$id => ComptesDemonstrationSeeder::compte($id)])->all();

        $this->referentiels();
        $this->elus();
        $this->contenus();
        $partenaires = $this->partenaires();
        $this->projets($partenaires);
        $this->documents();
        $this->demandes();

        $this->command?->info('Données de démonstration fictives créées et publiées par le circuit éditorial.');
    }

    /** Soumission par l'auteur, approbation et publication par le validateur du périmètre. */
    private function publier(Model&Publiable $objet, string $auteur, string $validateur): void
    {
        $validation = $this->circuit->soumettre($objet->refresh(), $this->agents[$auteur]);
        $this->circuit->approuver($validation, $this->agents[$validateur]);
        $this->circuit->publier($validation->refresh(), $this->agents[$validateur]);
    }

    private function source(string $type, string $intitule, ?string $date = null): Source
    {
        return Source::create(['type_source' => $type, 'intitule' => $intitule, 'date_source' => $date ?? now()->toDateString()]);
    }

    private function image(string $titre, string $alternatif, bool $portrait = false): Media
    {
        $fichier = $portrait ? Fichiers::portrait($titre, $this->agents['admin.editorial']) : Fichiers::image($titre, $this->agents['admin.editorial']);
        $media = Contexte::enTantQue($this->agents['admin.editorial'], fn () => Media::create([
            'fichier_id' => $fichier->id, 'titre' => $titre,
            'texte_alternatif' => $alternatif, 'auteur' => 'Photographe fictif', 'date_prise' => now()->subMonth()->toDateString(), 'lieu' => 'Lieu fictif',
        ]));

        return $this->droits->confirmerDroitMedia($media, $this->agents['responsable.validation'], DroitUsage::ProprieteCommune);
    }

    /** M01, M09 : localités et équipements fictifs, sans coordonnées (aucune position inventée). */
    private function referentiels(): void
    {
        $liste = $this->source('autre', 'Liste fictive des localités (démonstration)');
        foreach ([['Village fictif A', 'village'], ['Village fictif B', 'village'], ['Quartier fictif C', 'quartier'],
            ['Village fictif D', 'village'], ['Village fictif E', 'village'], ['Espace urbain fictif', 'espace_urbain']] as [$nom, $categorie]) {
            $localite = Contexte::enTantQue($this->agents['admin.editorial'], fn () => Localite::create([
                'nom' => $nom, 'slug' => str($nom)->slug(), 'categorie' => $categorie, 'source_id' => $liste->id,
            ]));
            $this->publier($localite, 'admin.editorial', 'responsable.validation');
            $this->localites[$nom] = $localite;
        }

        $inventaire = $this->source('autre', 'Inventaire fictif des équipements (démonstration)');
        foreach ([['ecole', 'École publique fictive du village A', 'Village fictif A'], ['forage', 'Forage fictif du quartier C', 'Quartier fictif C'],
            ['marche', 'Marché fictif du village B', 'Village fictif B'], ['centre_sante', 'Centre de santé fictif', 'Espace urbain fictif']] as [$type, $nom, $localite]) {
            $equipement = Contexte::enTantQue($this->agents['admin.editorial'], fn () => Equipement::create([
                'type_equipement' => $type, 'nom' => $nom, 'localite_id' => $this->localites[$localite]->id, 'source_id' => $inventaire->id,
            ]));
            $this->publier($equipement, 'admin.editorial', 'responsable.validation');
        }
    }

    /** M02 : exécutif et conseil fictifs ; aucun nom de personne réelle. */
    private function elus(): void
    {
        $acte = $this->source('piece_officielle', 'Acte de désignation fictif', '2026-01-15');
        foreach ([
            ['[Maire fictif]', 'Maire', 'maire', '2026-01-15', null],
            ['[Premier adjoint fictif]', 'Premier adjoint au Maire', 'executif', '2026-01-15', null],
            ['[Deuxième adjoint fictif]', 'Deuxième adjoint au Maire', 'executif', '2026-01-15', null],
            ['[Conseiller municipal fictif 1]', 'Conseiller municipal', 'conseil_municipal', '2026-01-15', null],
            ['[Conseillère municipale fictive 2]', 'Conseillère municipale', 'conseil_municipal', '2026-01-15', null],
            ['[Ancien maire fictif]', 'Maire', 'ancien_maire', '2020-01-15', '2026-01-14'],
        ] as [$nom, $fonction, $organe, $debut, $fin]) {
            $photo = $organe === 'conseil_municipal' ? null : $this->image("Portrait fictif — $nom", "Silhouette d’illustration (portrait fictif) de $nom", portrait: true);
            $elu = Contexte::enTantQue($this->agents['admin.editorial'], fn () => Elu::create([
                'nom_affiche' => $nom, 'fonction' => $fonction, 'organe' => $organe, 'mandat_debut' => $debut, 'mandat_fin' => $fin,
                'biographie' => 'Biographie fictive de démonstration. '.self::A_FOURNIR, 'photo_id' => $photo?->id, 'source_id' => $acte->id,
            ]));
            $this->publier($elu, 'admin.editorial', 'responsable.validation');
        }
    }

    /** M01, M02, M04, M06, M08, M10 : pages, démarches, opportunité, patrimoine, actualités et événements fictifs. */
    private function contenus(): void
    {
        // [type, module, titre, résumé, attributs, validateur, adresse] ; l'adresse des pages est celle prévue par l'arborescence.
        $contenus = [
            ['page', 'M01', 'Présentation de la commune (texte fictif)', 'Texte fictif de présentation, en attendant le texte validé par la Commune.', [], 'responsable.validation', 'presentation'],
            ['page', 'M02', 'Le mot du Maire (texte fictif)', 'Message fictif du Maire, à remplacer par le message validé.', [], 'responsable.validation', 'mot-du-maire'],
            ['page', 'M02', 'Vision et priorités (texte fictif)', 'Texte fictif : la vision et les priorités du mandat seront fournies par la Commune.', [], 'responsable.validation', 'vision-et-priorites'],
            ['demarche', 'M02', 'Acte de naissance (démarche fictive)', 'Démarche fictive : les pièces, délais et coûts réels sont à fournir.',
                ['pieces_a_fournir' => ['Pièce fictive 1', 'Pièce fictive 2'], 'delai' => self::A_FOURNIR, 'cout' => self::A_FOURNIR, 'service' => 'Service de l’état civil (fictif)'], 'responsable.validation', 'acte-de-naissance'],
            ['demarche', 'M02', 'Certificat de résidence (démarche fictive)', 'Démarche fictive : les pièces, délais et coûts réels sont à fournir.',
                ['pieces_a_fournir' => ['Pièce fictive 1'], 'delai' => self::A_FOURNIR, 'cout' => self::A_FOURNIR, 'service' => 'Secrétariat général (fictif)'], 'responsable.validation', 'certificat-de-residence'],
            ['service', 'M02', 'Service de l’état civil (fictif)', 'Service fictif : naissances, mariages et décès. Missions et coordonnées à fournir.', [], 'responsable.validation', 'etat-civil'],
            ['service', 'M02', 'Service technique communal (fictif)', 'Service fictif : voirie, bâtiments et suivi des chantiers. Missions et coordonnées à fournir.', [], 'responsable.validation', 'service-technique'],
            ['opportunite', 'M04', 'Zone d’activités fictive', 'Opportunité fictive présentée pour la démonstration du module Investir.', ['secteur' => 'commerce'], 'responsable.validation', null],
            ['page', 'M06', 'La diaspora et la commune (texte fictif)', 'Texte fictif pour la démonstration du module Diaspora.', [], 'responsable.validation', 'diaspora'],
            ['page', 'M06', 'Contribuer depuis la diaspora (texte fictif)', 'Texte fictif : les modalités de contribution seront fournies par la Commune.', [], 'responsable.validation', 'contribuer'],
            ['site_patrimoine', 'M08', 'Site patrimonial fictif A', 'Site fictif décrit pour la démonstration du module Patrimoine.', ['acces' => self::A_FOURNIR], 'responsable.validation', null],
            ['actualite', 'M10', 'Actualité fictive : réunion de quartier', 'Compte rendu fictif d’une réunion de quartier.', [], 'responsable.editorial', null],
            ['actualite', 'M10', 'Actualité fictive : campagne de salubrité', 'Annonce fictive d’une campagne de salubrité.',
                ['alerte' => ['niveau' => 'Information', 'jusqu_au' => now()->addDays(14)->toDateString()]], 'responsable.editorial', null],
            ['evenement', 'M10', 'Événement fictif : journée portes ouvertes', 'Événement fictif de démonstration.',
                ['date_debut' => now()->addDays(10)->setTime(9, 0)->toIso8601String(), 'date_fin' => now()->addDays(10)->setTime(16, 0)->toIso8601String(), 'lieu' => 'Salle polyvalente fictive'], 'responsable.editorial', null],
            ['evenement', 'M10', 'Événement fictif : forum des partenaires', 'Événement fictif de démonstration.',
                ['date_debut' => now()->addDays(30)->setTime(10, 0)->toIso8601String(), 'lieu' => 'Hôtel de ville fictif'], 'responsable.editorial', null],
        ];
        foreach ($contenus as [$type, $module, $titre, $resume, $attributs, $validateur, $adresse]) {
            $image = in_array($type, ['actualite', 'site_patrimoine'], true) ? $this->image("Illustration fictive — $titre", "Illustration fictive : $titre") : null;
            $contenu = $this->contenu($type, $module, $titre, $resume, $attributs, $image, $adresse);
            $this->publier($contenu, 'redacteur', $validateur);
            if ($type === 'site_patrimoine') {
                // La galerie du patrimoine (M08) affiche aussi les médias publiés pour eux-mêmes.
                $this->publier($image, 'admin.editorial', 'responsable.validation');
            }
        }

        // Une actualité programmée (visible dans trois jours) et une actualité retirée, avec motif.
        $programmee = $this->contenu('actualite', 'M10', 'Actualité fictive programmée', 'Actualité fictive dont la publication est programmée.', []);
        Contexte::enTantQue($this->agents['redacteur'], fn () => $programmee->update(['publication_programmee' => now()->addDays(3)->setTime(8, 0)]));
        $this->publier($programmee, 'redacteur', 'responsable.editorial');
        $retiree = $this->contenu('actualite', 'M10', 'Actualité fictive retirée', 'Actualité fictive retirée du site pour la démonstration.', []);
        $this->publier($retiree, 'redacteur', 'responsable.editorial');
        $this->circuit->retirer($retiree->refresh(), $this->agents['responsable.editorial'], 'Information périmée (motif fictif).');
    }

    private function contenu(string $type, string $module, string $titre, string $resume, array $attributs, ?Media $image = null, ?string $adresse = null): Contenu
    {
        $redacteur = $this->agents['redacteur'];

        return Contexte::enTantQue($redacteur, fn () => tap(new Contenu([
            'type_contenu' => $type, 'module' => $module, 'slug' => $adresse ?? str($titre)->slug(), 'titre' => $titre, 'resume' => $resume,
            'corps' => "$resume\n\nCe texte est un exemple : il sera remplacé par le contenu validé par la Commune.\n\n*Données fictives — démonstration.*",
            'attributs' => $attributs, 'image_id' => $image?->id,
        ]), fn (Contenu $contenu) => $contenu->forceFill(['auteur_id' => $redacteur->id])->save()));
    }

    /** M05 : un financeur cité (accord écrit fictif), un financeur non cité, une association de la diaspora. */
    private function partenaires(): array
    {
        $gestionnaire = $this->agents['gestionnaire.partenaires'];
        $creer = fn (string $nom, string $type, bool $cite) => Contexte::enTantQue($gestionnaire, fn () => Partenaire::create([
            'nom' => $nom, 'type_partenaire' => $type, 'pays' => self::A_FOURNIR, 'accepte_citation' => $cite,
            'accord_citation_id' => $cite ? $this->source('autre', "Accord écrit fictif de citation — $nom")->id : null,
        ]));
        $b = $creer('Financeur fictif B', 'bailleur_multilateral', true);
        $c = $creer('Financeur fictif C', 'fondation', false);
        $diaspora = $creer('Association fictive de la diaspora', 'association_diaspora', true);
        $this->publier($b, 'gestionnaire.partenaires', 'referent.projets');
        $this->publier($diaspora, 'gestionnaire.partenaires', 'referent.projets');

        return ['B' => $b, 'C' => $c];
    }

    /**
     * M03 : six projets fictifs à différents statuts, amenés par de vraies transitions validées.
     * Le premier reprend l'exemple de la fiche projet (§7) : coût 10 M, acquis 4 M, sollicité 3 M, recherché affiché 6 M XAF.
     *
     * @param  array{B: Partenaire, C: Partenaire}  $partenaires
     */
    private function projets(array $partenaires): void
    {
        $scenario = new ScenarioProjet($this->agents['responsable.projets'], $this->agents['referent.projets'], $this->agents['referent.realisations']);
        $l = $this->localites;

        $forage = $scenario->creer('Forage du village fictif A', 'eau', [$l['Village fictif A']]);
        $forage = $scenario->avancerJusqua($forage, StatutProjet::FinancementRecherche, ['cout' => 10_000_000]);
        $scenario->ajouterAcquis($forage, 4_000_000, $partenaires['B']);
        $scenario->ajouterSollicite($forage, 3_000_000, $partenaires['C']);

        $classe = $scenario->creer('Salle de classe du quartier fictif C', 'education', [$l['Quartier fictif C']]);
        $classe = $scenario->avancerJusqua($classe, StatutProjet::EnExecution, ['cout' => 25_000_000, 'financeur' => $partenaires['B'], 'avancement' => 40]);

        $marche = $scenario->creer('Hangar du marché du village fictif B', 'commerce', [$l['Village fictif B']]);
        $marche = $scenario->avancerJusqua($marche, StatutProjet::EtudeEnCours);

        $piste = $scenario->creer('Piste rurale du village fictif D', 'voirie', [$l['Village fictif D'], $l['Village fictif E']]);
        $piste = $scenario->avancerJusqua($piste, StatutProjet::Acheve, ['cout' => 15_000_000, 'financeur' => $partenaires['B']]);

        $sante = $scenario->creer('Maternité fictive du village E', 'sante', [$l['Village fictif E']]);
        $sante = $scenario->avancerJusqua($sante, StatutProjet::DossierEnPreparation);
        $transitions = new Transitions;
        $suspension = $transitions->demander($sante, StatutProjet::Suspendu, $this->agents['responsable.projets'], 'Étude complémentaire demandée (motif fictif).');
        $sante = $transitions->approuver($suspension, $this->agents['referent.projets']);

        foreach ([$forage, $classe, $marche, $piste, $sante] as $projet) {
            $this->publier($projet, 'responsable.projets', 'referent.projets');
        }

        // Une idée restée en brouillon : visible dans le back-office seulement.
        $scenario->creer('Aménagement fictif des berges', 'environnement', [$l['Espace urbain fictif']]);
    }

    /** M07 : deux documents publiés avec l'accord du référent juridique, un document resté interne. */
    private function documents(): void
    {
        $redacteur = $this->agents['redacteur'];
        foreach ([['budget', 'Budget primitif fictif 2027', true], ['deliberation', 'Délibération fictive n° 001', true],
            ['pcd', 'Plan communal de développement (document fictif)', false]] as [$categorie, $titre, $public]) {
            $document = Contexte::enTantQue($redacteur, function () use ($categorie, $titre, $redacteur) {
                $document = Document::create([
                    'slug' => str($titre)->slug(), 'titre' => $titre, 'categorie' => $categorie, 'resume' => 'Document fictif de démonstration.',
                    'date_document' => now()->subMonths(2)->toDateString(), 'reference_officielle' => 'Référence fictive',
                ]);
                $document->versions()->create(['numero' => 1, 'fichier_id' => Fichiers::pdf($titre, $redacteur)->id, 'cree_par' => $redacteur->id]);

                return $document;
            });
            if ($public) {
                $this->droits->accorderDiffusion($document, $this->agents['referent.juridique'], DroitDiffusion::Public,
                    $this->source('autre', "Accord fictif du référent juridique — $titre"));
                $this->publier($document, 'redacteur', 'referent.juridique');
            }
        }
    }

    /** M04, M05, M06, M11 : demandes fictives reçues, à traiter dans le back-office. */
    private function demandes(): void
    {
        $demandes = new Demandes;
        foreach ([
            [TypeDemande::Diaspora, ['objet' => 'Question fictive d’un membre de la diaspora', 'nom_contact' => 'Contact fictif 1', 'courriel' => 'contact1@exemple.invalid', 'pays' => 'Pays fictif']],
            [TypeDemande::Investisseur, ['objet' => 'Intérêt fictif pour la zone d’activités', 'organisme' => 'Entreprise fictive', 'nom_contact' => 'Contact fictif 2', 'courriel' => 'contact2@exemple.invalid']],
            [TypeDemande::Partenaire, ['objet' => 'Proposition fictive de partenariat', 'organisme' => 'Organisation fictive', 'nom_contact' => 'Contact fictif 3', 'courriel' => 'contact3@exemple.invalid']],
        ] as [$type, $donnees]) {
            $demandes->enregistrer($type, [...$donnees, 'message' => 'Message fictif de démonstration.'], BaseLegale::Consentement, true, 'mention-e01-proposition');
        }
    }
}

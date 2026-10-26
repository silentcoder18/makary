<?php

namespace Database\Seeders\Demonstration;

use App\Domaine\Contexte;
use App\Domaine\Editorial\Droits;
use App\Domaine\Enums\DroitUsage;
use App\Domaine\Enums\StatutLigneFinancement;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\Projets\Financement;
use App\Domaine\Projets\Transitions;
use App\Models\Localite;
use App\Models\Media;
use App\Models\Partenaire;
use App\Models\Projet;
use App\Models\ProjetPiece;
use App\Models\Source;
use App\Models\Utilisateur;

/**
 * Fait avancer un projet FICTIF jusqu'à un statut, en passant par les vrais services : chaque étape est
 * demandée par le responsable projets et approuvée par le référent compétent, conditions remplies.
 * Sert au jeu de démonstration et aux tests ; toutes les valeurs sont fictives (règle 3 du §14).
 */
final class ScenarioProjet
{
    private const ETAPES = [
        StatutProjet::EtudeEnCours, StatutProjet::DossierEnPreparation, StatutProjet::PretAEtreSoumis,
        StatutProjet::FinancementRecherche, StatutProjet::Finance, StatutProjet::EnExecution, StatutProjet::Acheve,
    ];

    public function __construct(
        private readonly Utilisateur $responsable,
        private readonly Utilisateur $referentProjets,
        private readonly Utilisateur $referentRealisations,
        private readonly Transitions $transitions = new Transitions,
        private readonly Financement $financement = new Financement,
    ) {}

    /** @param  list<Localite>  $localites */
    public function creer(string $titre, string $secteur, array $localites): Projet
    {
        return Contexte::enTantQue($this->responsable, function () use ($titre, $secteur, $localites) {
            $projet = Projet::create(['slug' => str($titre)->slug(), 'titre' => $titre, 'secteur' => $secteur, 'responsable_id' => $this->responsable->id]);
            $projet->localites()->attach(collect($localites)->pluck('id'));

            return $projet->refresh();
        });
    }

    /**
     * Montants en XAF ; un acquis inférieur au coût est motivé comme un démarrage avec financement partiel.
     *
     * @param  array{cout?: int, acquis?: int, sollicite?: int, financeur?: Partenaire, financeur_sollicite?: Partenaire, avancement?: int}  $chiffres
     */
    public function avancerJusqua(Projet $projet, StatutProjet $cible, array $chiffres = []): Projet
    {
        $cout = $chiffres['cout'] ?? 10_000_000;
        $position = array_search($projet->statut, self::ETAPES, true);
        foreach (array_slice(self::ETAPES, $position === false ? 0 : $position + 1) as $etape) {
            if ($projet->statut === $cible) {
                break;
            }
            $this->preparer($projet, $etape, $cout, $chiffres);
            $piece = match ($etape) {
                StatutProjet::Finance => $this->piece($projet, 'convention', 'Convention de financement fictive'),
                StatutProjet::EnExecution => $this->piece($projet, 'ordre_service', 'Ordre de service fictif'),
                StatutProjet::Acheve => $this->piece($projet, 'pv_reception', 'Procès-verbal de réception fictif'),
                default => null,
            };
            $motif = $etape === StatutProjet::Finance && ($chiffres['acquis'] ?? $cout) < $cout
                ? 'Démarrage avec un financement partiel décidé par délibération fictive' : null;
            $transition = $this->transitions->demander($projet, $etape, $this->responsable, $motif, $piece);
            $referent = in_array($etape, [StatutProjet::EnExecution, StatutProjet::Acheve], true) ? $this->referentRealisations : $this->referentProjets;
            $projet = $this->transitions->approuver($transition, $referent);
        }
        // L'avancement n'est permis qu'à partir de « en exécution » (contrainte de la base).
        if (isset($chiffres['avancement']) && $projet->statut === StatutProjet::EnExecution) {
            Contexte::enTantQue($this->responsable, fn () => $projet->fill([
                'avancement_pct' => $chiffres['avancement'], 'avancement_date' => now()->toDateString(),
                'avancement_source_id' => $this->source('piece_officielle', 'Rapport d’avancement fictif')->id,
            ])->save());
        }

        return $projet;
    }

    /** Ajoute une ligne « acquise » validée par le référent, sans changer le statut (financement partiel en cours de recherche). */
    public function ajouterAcquis(Projet $projet, int $montant, Partenaire $financeur): void
    {
        $ligne = $this->financement->ajouterLigne($projet, $this->responsable, StatutLigneFinancement::Sollicite, [
            'partenaire_id' => $financeur->id, 'montant' => $montant, 'devise' => 'XAF', 'date_information' => now()->toDateString(),
            'source_id' => $this->source('autre', 'Dossier de demande fictif')->id,
        ]);
        $this->financement->validerAcquis($ligne, $this->referentProjets, $this->source('convention', 'Convention de financement fictive'));
    }

    /** Ajoute une ligne « sollicitée » (visible en interne seulement). */
    public function ajouterSollicite(Projet $projet, int $montant, Partenaire $financeur): void
    {
        $this->financement->ajouterLigne($projet, $this->responsable, StatutLigneFinancement::Sollicite, [
            'partenaire_id' => $financeur->id, 'montant' => $montant, 'devise' => 'XAF', 'date_information' => now()->toDateString(),
            'source_id' => $this->source('autre', "Dossier déposé auprès du {$financeur->nom}")->id,
        ]);
    }

    /** Remplit, avec des valeurs fictives, les conditions exigées pour atteindre l'étape (fiche projet §3). */
    private function preparer(Projet $projet, StatutProjet $etape, int $cout, array $chiffres): void
    {
        Contexte::enTantQue($this->responsable, function () use ($projet, $etape, $cout, $chiffres) {
            match ($etape) {
                StatutProjet::EtudeEnCours => $projet->fill([
                    'resume' => $projet->resume ?? "Résumé fictif du projet « {$projet->titre} ».",
                    'besoin' => $projet->besoin ?? 'Besoin fictif constaté lors d’une réunion de quartier fictive.',
                    'besoin_source_id' => $projet->besoin_source_id ?? $this->source('proces_verbal', 'Compte rendu de réunion fictif')->id,
                    'service_responsable' => $projet->service_responsable ?? 'Service technique communal (fictif)',
                ])->save(),
                StatutProjet::DossierEnPreparation => $projet->fill([
                    'description_technique' => $projet->description_technique ?? 'Description technique fictive : ouvrages et quantités de démonstration.',
                    'description_publique' => true,
                ])->save(),
                StatutProjet::PretAEtreSoumis => $this->preparerDossier($projet, $cout),
                StatutProjet::FinancementRecherche => $this->financement->ajouterLigne($projet, $this->responsable, StatutLigneFinancement::Recherche, [
                    'montant' => $cout, 'devise' => 'XAF', 'date_information' => now()->toDateString(),
                    'source_id' => $this->source('estimation_interne', 'Plan de financement fictif')->id,
                ]),
                StatutProjet::Finance => $this->obtenirFinancement($projet, $cout, $chiffres),
                StatutProjet::Acheve => $this->preparerAchevement($projet, $cout),
                default => null,
            };
        });
    }

    private function preparerDossier(Projet $projet, int $cout): void
    {
        $projet->beneficiaires()->create([
            'nombre' => 1200, 'categorie' => 'habitants', 'methode_estimation' => 'Recensement fictif des ménages desservis',
            'date_estimation' => now()->toDateString(), 'nature' => 'estimation', 'source_id' => $this->source('recensement', 'Recensement fictif')->id,
        ]);
        $projet->estimations()->create([
            'montant' => $cout, 'devise' => 'XAF', 'date_estimation' => now()->toDateString(), 'nature_cout' => 'devis', 'nature' => 'verifie',
            'source_id' => $this->source('devis', 'Devis fictif')->id,
        ]);
        $this->piece($projet, 'etude_faisabilite', 'Étude de faisabilité fictive', 'public');
        $projet->indicateurs()->create([
            'libelle' => 'Indicateur fictif', 'unite' => 'personnes', 'valeur_reference' => 0, 'cible' => 1200,
            'methode_mesure' => 'Comptage fictif à la réception',
        ]);
    }

    private function obtenirFinancement(Projet $projet, int $cout, array $chiffres): void
    {
        $financeur = $chiffres['financeur'] ?? Partenaire::firstOrCreate(['nom' => 'Financeur fictif B'], ['type_partenaire' => 'bailleur_multilateral']);
        $acquis = $chiffres['acquis'] ?? $cout;
        $ligne = $this->financement->ajouterLigne($projet, $this->responsable, StatutLigneFinancement::Sollicite, [
            'partenaire_id' => $financeur->id, 'montant' => $acquis, 'devise' => 'XAF', 'date_information' => now()->toDateString(),
            'source_id' => $this->source('autre', 'Dossier de demande fictif')->id,
        ]);
        $this->financement->validerAcquis($ligne, $this->referentProjets, $this->source('convention', 'Convention de financement fictive'));
        if (! empty($chiffres['sollicite'])) {
            $autre = $chiffres['financeur_sollicite'] ?? Partenaire::firstOrCreate(['nom' => 'Financeur fictif C'], ['type_partenaire' => 'fondation']);
            $this->financement->ajouterLigne($projet, $this->responsable, StatutLigneFinancement::Sollicite, [
                'partenaire_id' => $autre->id, 'montant' => $chiffres['sollicite'], 'devise' => 'XAF', 'date_information' => now()->toDateString(),
                'source_id' => $this->source('autre', 'Dossier déposé auprès du financeur fictif C')->id,
            ]);
        }
    }

    private function preparerAchevement(Projet $projet, int $cout): void
    {
        $projet->estimations()->create([
            'montant' => $cout, 'devise' => 'XAF', 'date_estimation' => now()->toDateString(), 'nature_cout' => 'cout_final', 'nature' => 'verifie',
            'source_id' => $this->source('piece_officielle', 'Décompte final fictif')->id,
        ]);
        $photo = Media::create([
            'fichier_id' => Fichiers::image("Photo fictive de réception — {$projet->titre}", $this->responsable)->id,
            'titre' => 'Réception de l’ouvrage (photo fictive)', 'texte_alternatif' => "Illustration fictive de l’ouvrage réceptionné : {$projet->titre}",
            'auteur' => 'Photographe fictif', 'date_prise' => now()->toDateString(), 'lieu' => 'Lieu fictif',
        ]);
        $projet->medias()->attach($photo->id, ['ordre' => 1]);
        (new Droits)->confirmerDroitMedia($photo, $this->referentProjets, DroitUsage::ProprieteCommune);
    }

    private function piece(Projet $projet, string $type, string $intitule, string $diffusion = 'interne'): ProjetPiece
    {
        return Contexte::enTantQue($this->responsable, fn () => $projet->pieces()->create([
            'type_piece' => $type, 'diffusion' => $diffusion, 'source_id' => $this->source('piece_officielle', $intitule)->id,
        ]));
    }

    private function source(string $type, string $intitule): Source
    {
        return Source::create(['type_source' => $type, 'intitule' => $intitule, 'date_source' => now()->toDateString(), 'cree_par' => $this->responsable->id]);
    }
}

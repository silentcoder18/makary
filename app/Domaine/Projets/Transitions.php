<?php

namespace App\Domaine\Projets;

use App\Domaine\Contexte;
use App\Domaine\Enums\DecisionValidation;
use App\Domaine\Enums\NatureInformation;
use App\Domaine\Enums\StatutLigneFinancement;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\RegleMetierException;
use App\Models\Projet;
use App\Models\ProjetPiece;
use App\Models\ProjetTransition;
use App\Models\TransitionAutorisee;
use App\Models\Utilisateur;
use App\Securite\Journal;
use Illuminate\Support\Facades\Gate;

/**
 * Changements de statut d'un projet (fiche projet §3, §8). Le responsable projets demande, un référent
 * du bon périmètre approuve ; nul ne valide sa propre demande. Les statuts qui engagent la Commune
 * (« financé », « en exécution », « achevé ») exigent une pièce et la validation d'un référent humain (§14).
 */
class Transitions
{
    /** Ordre d'avancement des statuts, hors « suspendu », pour distinguer progression et révision. */
    private const ORDRE = [
        'idee_identifiee', 'etude_en_cours', 'dossier_en_preparation', 'pret_a_etre_soumis',
        'financement_recherche', 'finance', 'en_execution', 'acheve',
    ];

    public function demander(Projet $projet, StatutProjet $vers, Utilisateur $par, ?string $motif = null, ?ProjetPiece $piece = null): ProjetTransition
    {
        Gate::forUser($par)->authorize('projet.demander_transition', $projet);
        $regle = TransitionAutorisee::where('de_statut', $projet->statut->value)->where('vers_statut', $vers->value)->first();
        if (! $regle) {
            throw new RegleMetierException("Le passage de « {$projet->statut->libelle()} » à « {$vers->libelle()} » n'est pas prévu.");
        }
        if ($projet->transitions()->where('decision', DecisionValidation::EnAttente->value)->exists()) {
            throw new RegleMetierException('Une demande de changement de statut est déjà en attente pour ce projet.');
        }
        if ($regle->piece_requise && ($piece?->projet_id !== $projet->id || $piece->type_piece !== $regle->piece_requise)) {
            throw new RegleMetierException("Ce changement de statut exige une pièce « {$regle->piece_requise} » du projet.");
        }
        $this->exigerConditions($projet, $projet->statut, $vers, $motif);

        return Contexte::enTantQue($par, function () use ($projet, $vers, $par, $motif, $piece) {
            $transition = new ProjetTransition([
                'projet_id' => $projet->id, 'de_statut' => $projet->statut, 'vers_statut' => $vers,
                'demande_par' => $par->id, 'motif' => $motif, 'piece_id' => $piece?->id,
            ]);
            $transition->save();
            Journal::consigner('projet.demander_transition', $projet, ['transition_id' => $transition->id, 'de' => $projet->statut->value, 'vers' => $vers->value], $par->id);

            return $transition->refresh();
        });
    }

    /** Approuve la demande et applique le nouveau statut ; les conditions sont vérifiées de nouveau. */
    public function approuver(ProjetTransition $transition, Utilisateur $validateur): Projet
    {
        $this->controlerDecision($transition, $validateur);
        $projet = $transition->projet;
        if ($projet->statut !== $transition->de_statut) {
            throw new RegleMetierException('Le statut du projet a changé depuis la demande.');
        }
        $this->exigerConditions($projet, $transition->de_statut, $transition->vers_statut, $transition->motif);

        return Contexte::enTantQue($validateur, function () use ($transition, $validateur, $projet) {
            $transition->forceFill(['decision' => DecisionValidation::Approuve, 'valide_par' => $validateur->id, 'valide_le' => now()])->save();
            $projet->forceFill([
                'statut' => $transition->vers_statut,
                'motif_suspension' => $transition->vers_statut === StatutProjet::Suspendu ? $transition->motif : $projet->motif_suspension,
            ])->save();
            Journal::consigner('projet.valider_transition', $projet, [
                'transition_id' => $transition->id, 'de' => $transition->de_statut->value, 'vers' => $transition->vers_statut->value,
            ], $validateur->id);

            return $projet->refresh();
        });
    }

    public function refuser(ProjetTransition $transition, Utilisateur $validateur, string $motif): ProjetTransition
    {
        if (trim($motif) === '') {
            throw new RegleMetierException('Le refus doit être motivé.');
        }
        $this->controlerDecision($transition, $validateur);

        return Contexte::enTantQue($validateur, function () use ($transition, $validateur, $motif) {
            $transition->forceFill(['decision' => DecisionValidation::Renvoye, 'valide_par' => $validateur->id, 'valide_le' => now()])->save();
            Journal::consigner('projet.refuser_transition', $transition->projet, ['transition_id' => $transition->id, 'motif' => $motif], $validateur->id);

            return $transition;
        });
    }

    /**
     * Conditions vérifiées par le système (tableau de la fiche projet, §3) ; liste vide si tout est rempli.
     *
     * @return list<string>
     */
    public function conditionsManquantes(Projet $projet, StatutProjet $de, StatutProjet $vers, ?string $motif): array
    {
        $sansMotif = trim((string) $motif) === '';
        $rang = fn (StatutProjet $s) => array_search($s->value, self::ORDRE, true);
        if ($vers === StatutProjet::Suspendu || $de === StatutProjet::Suspendu || $rang($vers) < $rang($de)) {
            return $sansMotif ? ['un motif (suspension, reprise ou révision)'] : [];
        }

        $confirme = fn ($requete) => $requete->where('nature', '<>', NatureInformation::AConfirmer->value);
        $synthese = $projet->syntheseFinanciere();
        $manque = match ($vers) {
            StatutProjet::EtudeEnCours => [
                'un résumé' => filled($projet->resume),
                'le besoin auquel le projet répond' => filled($projet->besoin),
                'le service responsable du suivi' => filled($projet->service_responsable) || $projet->responsable_id !== null,
            ],
            StatutProjet::DossierEnPreparation => ['la description technique' => filled($projet->description_technique)],
            StatutProjet::PretAEtreSoumis => [
                'les bénéficiaires et leur méthode d’estimation' => $projet->beneficiaires()->tap($confirme)->exists(),
                'un coût estimatif daté et sourcé' => $projet->estimationCourante() !== null,
                'au moins une pièce' => $projet->pieces()->exists(),
                'des indicateurs et leurs cibles' => $projet->indicateurs()->exists(),
            ],
            StatutProjet::FinancementRecherche => [
                'au moins une ligne de financement « recherché »' => $projet->financements()->where('statut_ligne', StatutLigneFinancement::Recherche->value)->exists(),
            ],
            StatutProjet::Finance => [
                'au moins une ligne de financement acquise et validée' => bccomp($synthese['acquis'], '0', 2) > 0,
                // Financement partiel : seulement sur décision écrite, motivée dans la demande.
                'un financement acquis couvrant le coût estimé, ou le motif d’un démarrage avec financement partiel' => ! $sansMotif
                    || ($synthese['cout'] !== null && bccomp($synthese['acquis'], $synthese['cout'], 2) >= 0),
            ],
            StatutProjet::Acheve => [
                'le coût final, avec sa source' => $projet->estimations()->where('nature_cout', 'cout_final')->tap($confirme)->exists(),
                'au moins une photo datée' => $projet->medias()->whereNotNull('date_prise')->exists(),
            ],
            default => [],
        };

        return array_keys(array_filter($manque, fn (bool $rempli) => ! $rempli));
    }

    private function exigerConditions(Projet $projet, StatutProjet $de, StatutProjet $vers, ?string $motif): void
    {
        if ($manque = $this->conditionsManquantes($projet, $de, $vers, $motif)) {
            throw new RegleMetierException("Conditions non remplies pour « {$vers->libelle()} » : ".implode(' ; ', $manque).'.');
        }
    }

    private function controlerDecision(ProjetTransition $transition, Utilisateur $validateur): void
    {
        // Le périmètre est celui de la transition : « projets » (RPF) ou « realisations » (RR).
        Gate::forUser($validateur)->authorize('projet.valider_transition', $transition);
        if ($transition->decision !== DecisionValidation::EnAttente) {
            throw new RegleMetierException('Une décision a déjà été rendue sur cette demande.');
        }
        if ($transition->demande_par === $validateur->id) {
            throw new RegleMetierException('Vous ne pouvez pas valider votre propre demande (séparation des tâches).');
        }
    }
}

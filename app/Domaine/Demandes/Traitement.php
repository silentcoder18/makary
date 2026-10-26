<?php

namespace App\Domaine\Demandes;

use App\Domaine\Contexte;
use App\Domaine\Enums\StatutDemande;
use App\Domaine\RegleMetierException;
use App\Models\Demande;
use App\Models\Utilisateur;
use App\Securite\Journal;
use Illuminate\Support\Facades\Gate;

/**
 * Traitement des demandes (M11) : qualification, affectation, réponse, clôture ou rejet.
 * Chaque étape est tracée dans le suivi de la demande ; la lecture des données personnelles est journalisée (§9).
 */
class Traitement
{
    /** Étapes permises depuis chaque statut. */
    private const SUIVANTS = [
        'recue' => ['qualifiee', 'rejetee'],
        'qualifiee' => ['affectee', 'repondue', 'rejetee'],
        'affectee' => ['en_traitement', 'repondue', 'rejetee'],
        'en_traitement' => ['repondue', 'rejetee'],
        'repondue' => ['repondue', 'cloturee'],
        'cloturee' => [],
        'rejetee' => [],
    ];

    /** Consultation d'une demande et de ses données personnelles : toujours journalisée. */
    public function consulter(Demande $demande, Utilisateur $par): Demande
    {
        Gate::forUser($par)->authorize('demande.lire');
        Journal::consigner('demande.lire', $demande, [], $par->id);

        return $demande;
    }

    public function qualifier(Demande $demande, Utilisateur $par, int $priorite, ?string $note = null): Demande
    {
        if ($priorite < 1 || $priorite > 3) {
            throw new RegleMetierException('La priorité va de 1 (haute) à 3 (basse).');
        }

        return $this->changer($demande, $par, StatutDemande::Qualifiee, 'qualification', $note ?? "Priorité $priorite", ['priorite' => $priorite]);
    }

    public function affecter(Demande $demande, Utilisateur $par, Utilisateur $agent): Demande
    {
        if (! $agent->actif || ! Gate::forUser($agent)->allows('demande.traiter')) {
            throw new RegleMetierException('La demande ne peut être confiée qu’à un agent habilité à traiter les demandes.');
        }

        return $this->changer($demande, $par, StatutDemande::Affectee, 'affectation', "Confiée à {$agent->nom_affiche}", ['affectee_a' => $agent->id]);
    }

    public function commencer(Demande $demande, Utilisateur $par): Demande
    {
        return $this->changer($demande, $par, StatutDemande::EnTraitement, 'note', 'Traitement commencé');
    }

    /** La réponse est consignée ; la date de première réponse alimente l'indicateur de délai (§12). */
    public function repondre(Demande $demande, Utilisateur $par, string $reponse): Demande
    {
        if (trim($reponse) === '') {
            throw new RegleMetierException('Le texte de la réponse est obligatoire.');
        }

        return $this->changer($demande, $par, StatutDemande::Repondue, 'reponse', $reponse,
            $demande->premiere_reponse_le ? [] : ['premiere_reponse_le' => now()]);
    }

    public function cloturer(Demande $demande, Utilisateur $par, ?string $note = null): Demande
    {
        return $this->changer($demande, $par, StatutDemande::Cloturee, 'cloture', $note ?? 'Demande clôturée', ['cloturee_le' => now()]);
    }

    public function rejeter(Demande $demande, Utilisateur $par, string $motif): Demande
    {
        if (trim($motif) === '') {
            throw new RegleMetierException('Le rejet doit être motivé.');
        }

        return $this->changer($demande, $par, StatutDemande::Rejetee, 'cloture', "Rejet : $motif", ['cloturee_le' => now()]);
    }

    /** @return list<StatutDemande> */
    public function suivants(Demande $demande): array
    {
        return array_map(fn (string $s) => StatutDemande::from($s), self::SUIVANTS[$demande->statut->value]);
    }

    /** @param  array<string, mixed>  $champs */
    private function changer(Demande $demande, Utilisateur $par, StatutDemande $vers, string $evenement, string $contenu, array $champs = []): Demande
    {
        Gate::forUser($par)->authorize('demande.traiter');
        if ($demande->anonymisee_le) {
            throw new RegleMetierException('Cette demande a été anonymisée : elle ne peut plus être traitée.');
        }
        if (! in_array($vers->value, self::SUIVANTS[$demande->statut->value], true)) {
            throw new RegleMetierException("Une demande « {$demande->statut->libelle()} » ne peut pas passer à « {$vers->libelle()} ».");
        }

        return Contexte::enTantQue($par, function () use ($demande, $par, $vers, $evenement, $contenu, $champs) {
            $demande->forceFill(['statut' => $vers, ...$champs])->save();
            $demande->evenements()->create(['type' => $evenement, 'contenu' => $contenu, 'par' => $par->id]);
            Journal::consigner('demande.'.$evenement, $demande, ['statut' => $vers->value], $par->id);

            return $demande->refresh();
        });
    }
}

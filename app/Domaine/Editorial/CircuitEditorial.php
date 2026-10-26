<?php

namespace App\Domaine\Editorial;

use App\Domaine\Contexte;
use App\Domaine\Enums\DecisionValidation;
use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Publiable;
use App\Domaine\RegleMetierException;
use App\Models\Contenu;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Models\ValidationDemande;
use App\Securite\Journal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Circuit éditorial commun à tous les objets publiables (§7, modèle de données §1) :
 * soumission (instantané figé) → approbation ou renvoi par une autre personne → publication → retrait.
 * Chaque règle est aussi imposée par la base (migrations 0001 et 0002).
 */
class CircuitEditorial
{
    public function soumettre(Model&Publiable $objet, Utilisateur $auteur): ValidationDemande
    {
        Gate::forUser($auteur)->authorize('contenu.soumettre', $objet);
        if ($objet->validationEnAttente()) {
            throw new RegleMetierException('Cet élément est déjà en revue.');
        }
        if ($empechement = $objet->empechementPublication()) {
            throw new RegleMetierException($empechement);
        }

        return Contexte::enTantQue($auteur, function () use ($objet, $auteur) {
            $validation = new ValidationDemande([
                'objet_type' => $objet->typeObjet(),
                'objet_id' => $objet->getKey(),
                'soumis_par' => $auteur->id,
                'instantane' => $objet->instantanePublic(),
            ]);
            $validation->save();
            $objet->marquerEtat(EtatEditorial::EnRevue);
            Journal::consigner('contenu.soumettre', $objet, ['validation_id' => $validation->id], $auteur->id);

            return $validation->refresh();
        });
    }

    public function approuver(ValidationDemande $validation, Utilisateur $validateur): ValidationDemande
    {
        $objet = $this->controlerDecision($validation, $validateur);

        return Contexte::enTantQue($validateur, function () use ($validation, $validateur, $objet) {
            $validation->forceFill([
                'decision' => DecisionValidation::Approuve, 'decide_par' => $validateur->id, 'decide_le' => now(),
            ])->save();
            $objet->marquerEtat(EtatEditorial::Approuve);
            Journal::consigner('contenu.approuver', $objet, ['validation_id' => $validation->id], $validateur->id);

            return $validation;
        });
    }

    public function renvoyer(ValidationDemande $validation, Utilisateur $validateur, string $motif): ValidationDemande
    {
        if (trim($motif) === '') {
            throw new RegleMetierException('Le renvoi doit être motivé.');
        }
        $objet = $this->controlerDecision($validation, $validateur);

        return Contexte::enTantQue($validateur, function () use ($validation, $validateur, $objet, $motif) {
            $validation->forceFill([
                'decision' => DecisionValidation::Renvoye, 'decide_par' => $validateur->id, 'decide_le' => now(), 'motif' => $motif,
            ])->save();
            $objet->marquerEtat(EtatEditorial::Brouillon);
            Journal::consigner('contenu.renvoyer', $objet, ['validation_id' => $validation->id, 'motif' => $motif], $validateur->id);

            return $validation;
        });
    }

    /**
     * Publie l'instantané approuvé, tel quel. Une date future programme la publication (M10) ;
     * à défaut, la date programmée d'un contenu est reprise.
     */
    public function publier(ValidationDemande $validation, Utilisateur $par, ?CarbonInterface $le = null): Publication
    {
        $objet = $validation->objet();
        Gate::forUser($par)->authorize('contenu.publier', $objet);
        if ($validation->decision !== DecisionValidation::Approuve) {
            throw new RegleMetierException('Seul un élément approuvé peut être publié.');
        }
        if ($validation->publication()->exists()) {
            throw new RegleMetierException('Cette approbation a déjà été publiée ; une nouvelle version doit être soumise.');
        }
        // Les droits peuvent avoir changé depuis la soumission (diffusion retirée, média contesté).
        if ($empechement = $objet->empechementPublication()) {
            throw new RegleMetierException($empechement);
        }
        $le ??= $objet instanceof Contenu && $objet->publication_programmee?->isFuture() ? $objet->publication_programmee : now();

        return Contexte::enTantQue($par, function () use ($validation, $par, $objet, $le) {
            $version = 1 + (int) Publication::where('objet_type', $validation->objet_type->value)->where('objet_id', $validation->objet_id)->max('version');
            // L'instantané est recopié tel qu'approuvé, octet pour octet (la base vérifie l'égalité).
            $id = DB::table('publication')->insertGetId([
                'objet_type' => $validation->objet_type->value,
                'objet_id' => $validation->objet_id,
                'version' => $version,
                'instantane' => $validation->getRawOriginal('instantane'),
                'validation_id' => $validation->id,
                'publie_par' => $par->id,
                'publie_le' => $le,
            ]);
            $objet->marquerEtat(EtatEditorial::Publie);
            Journal::consigner('contenu.publier', $objet, ['version' => $version, 'publie_le' => $le->toIso8601String()], $par->id);

            return Publication::findOrFail($id);
        });
    }

    /** Retire l'objet du site public, avec motif. Les versions restent dans le back-office. */
    public function retirer(Model&Publiable $objet, Utilisateur $par, string $motif): int
    {
        Gate::forUser($par)->authorize('contenu.retirer', $objet);
        if (trim($motif) === '') {
            throw new RegleMetierException('Le retrait doit être motivé.');
        }

        return Contexte::enTantQue($par, function () use ($objet, $par, $motif) {
            $retirees = $objet->publications()->whereNull('retire_le')->update(['retire_le' => now(), 'motif_retrait' => $motif]);
            $objet->marquerEtat(EtatEditorial::Retire);
            Journal::consigner('contenu.retirer', $objet, ['versions_retirees' => $retirees, 'motif' => $motif], $par->id);

            return $retirees;
        });
    }

    /**
     * Publie directement un objet sans étape intermédiaire pour les administrateurs autorisés.
     */
    public function publierDirectement(Model&Publiable $objet, Utilisateur $par, ?CarbonInterface $le = null): Publication
    {
        Gate::forUser($par)->authorize('contenu.publier', $objet);
        if ($empechement = $objet->empechementPublication()) {
            throw new RegleMetierException($empechement);
        }

        // Si l'objet a déjà des publications en ligne, les marquer comme remplacées
        $objet->publications()->whereNull('retire_le')->update([
            'retire_le' => now(),
            'motif_retrait' => 'Remplacé par la nouvelle version',
        ]);

        $instantane = $objet->instantanePublic();
        $enAttente = $objet->validationEnAttente();

        // Si une demande est déjà en attente avec le même instantané, l'approuver et la publier directement
        if ($enAttente && $enAttente->instantane == $instantane) {
            $enAttente->forceFill([
                'decision' => DecisionValidation::Approuve,
                'decide_par' => $par->id,
                'decide_le' => now(),
            ])->save();

            return $this->publier($enAttente, $par, $le);
        }

        // Si une demande était en attente avec un ancien instantané, la clore pour libérer l'index unique
        if ($enAttente) {
            $enAttente->forceFill([
                'decision' => DecisionValidation::Renvoye,
                'decide_par' => $par->id,
                'decide_le' => now(),
                'motif' => 'Remplacé par la publication directe d’une nouvelle version',
            ])->save();
        }

        // Vérifier que l'auteur existe toujours en base, sinon attribuer à l'administrateur
        $auteurId = $objet->auteurId();
        if (! $auteurId || ! Utilisateur::where('id', $auteurId)->exists()) {
            $auteurId = $par->id;
        }

        $validation = new ValidationDemande;
        $validation->objet_type = $objet->typeObjet();
        $validation->objet_id = $objet->getKey();
        $validation->soumis_par = $auteurId;
        $validation->instantane = $instantane;
        $validation->decision = DecisionValidation::Approuve;
        $validation->decide_par = $par->id;
        $validation->decide_le = now();
        $validation->save();

        return $this->publier($validation, $par, $le);
    }

    /**
     * Dépublie immédiatement un objet du site public et le repasse en brouillon (1 clic).
     */
    public function depublier(Model&Publiable $objet, Utilisateur $par, ?string $motif = null): int
    {
        Gate::forUser($par)->authorize('contenu.retirer', $objet);
        $motif = filled($motif) ? $motif : 'Dépublié par l’administrateur.';

        return Contexte::enTantQue($par, function () use ($objet, $par, $motif) {
            $retirees = $objet->publications()->whereNull('retire_le')->update([
                'retire_le' => now(),
                'motif_retrait' => $motif,
            ]);
            $objet->marquerEtat(EtatEditorial::Brouillon);
            Journal::consigner('contenu.retirer', $objet, ['versions_retirees' => $retirees, 'motif' => $motif], $par->id);

            return $retirees;
        });
    }

    /** Contrôles communs à l'approbation et au renvoi ; renvoie l'objet concerné. */
    private function controlerDecision(ValidationDemande $validation, Utilisateur $validateur): Model&Publiable
    {
        $objet = $validation->objet();
        Gate::forUser($validateur)->authorize('contenu.approuver', $objet);
        if ($validation->decision !== DecisionValidation::EnAttente) {
            throw new RegleMetierException('Une décision a déjà été rendue sur cette demande.');
        }
        if ($validation->soumis_par === $validateur->id && ! Gate::forUser($validateur)->allows('contenu.publier')) {
            throw new RegleMetierException('Vous ne pouvez pas valider votre propre soumission (séparation des tâches).');
        }

        return $objet;
    }
}

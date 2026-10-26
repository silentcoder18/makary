<?php

namespace App\Domaine\Projets;

use App\Domaine\Contexte;
use App\Domaine\Enums\StatutLigneFinancement;
use App\Domaine\RegleMetierException;
use App\Models\HistoriqueModification;
use App\Models\Projet;
use App\Models\ProjetFinancement;
use App\Models\Source;
use App\Models\Utilisateur;
use App\Securite\Cible;
use App\Securite\Journal;
use Illuminate\Support\Facades\Gate;

/**
 * Lignes de financement (§8) : le responsable projets saisit le « recherché » et le « sollicité » ;
 * seul un référent des projets et financements rend une ligne « acquise », sur pièce, sans valider sa propre saisie.
 */
class Financement
{
    /** Types de source acceptés comme pièce d'un financement acquis : convention ou engagement signé. */
    private const PIECES_ACQUIS = ['convention', 'piece_officielle'];

    /** @param  array{montant: string|int, devise: string, date_information: string, source_id: int, partenaire_id?: int|null}  $donnees */
    public function ajouterLigne(Projet $projet, Utilisateur $par, StatutLigneFinancement $statut, array $donnees): ProjetFinancement
    {
        Gate::forUser($par)->authorize('financement.modifier', $projet);
        if ($statut === StatutLigneFinancement::Acquis) {
            throw new RegleMetierException('Une ligne n’est « acquise » qu’après validation par un référent, avec sa pièce.');
        }

        return Contexte::enTantQue($par, function () use ($projet, $statut, $donnees) {
            $ligne = new ProjetFinancement(['projet_id' => $projet->id, ...$donnees]);
            $ligne->forceFill(['statut_ligne' => $statut])->save();

            return $ligne->refresh();
        });
    }

    public function validerAcquis(ProjetFinancement $ligne, Utilisateur $validateur, Source $piece): ProjetFinancement
    {
        Gate::forUser($validateur)->authorize('financement.valider_acquis', new Cible(['projets']));
        if ($ligne->statut_ligne !== StatutLigneFinancement::Sollicite) {
            throw new RegleMetierException('Seule une ligne « sollicitée » auprès d’un financeur peut devenir « acquise ».');
        }
        if (! in_array($piece->type_source, self::PIECES_ACQUIS, true)) {
            throw new RegleMetierException('La pièce d’un financement acquis est une convention ou un engagement signé.');
        }
        if ($this->saisiePar($ligne) === $validateur->id) {
            throw new RegleMetierException('Vous ne pouvez pas valider une ligne que vous avez saisie (séparation des tâches).');
        }

        return Contexte::enTantQue($validateur, function () use ($ligne, $validateur, $piece) {
            $ligne->forceFill([
                'statut_ligne' => StatutLigneFinancement::Acquis, 'piece_id' => $piece->id, 'valide_par' => $validateur->id, 'valide_le' => now(),
            ])->save();
            Journal::consigner('financement.valider_acquis', $ligne, [
                'projet_id' => $ligne->projet_id, 'montant' => $ligne->montant, 'devise' => $ligne->devise, 'piece_id' => $piece->id,
            ], $validateur->id);

            return $ligne;
        });
    }

    /** Auteur de la saisie de la ligne, d'après l'historique tenu par la base. */
    private function saisiePar(ProjetFinancement $ligne): ?int
    {
        return HistoriqueModification::where('nom_table', 'projet_financement')->where('ligne_id', $ligne->id)
            ->where('operation', 'INSERT')->value('utilisateur_id');
    }
}

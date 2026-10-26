<?php

namespace App\Domaine\Demandes;

use App\Domaine\Enums\BaseLegale;
use App\Domaine\Enums\TypeDemande;
use App\Domaine\RegleMetierException;
use App\Models\Demande;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demandes reçues du public (M04, M05, M06, M11) : numéro de suivi, base légale et fin de conservation
 * fixées dès la réception (E-01, E-04). Le suivi se fait par numéro, sans compte.
 */
class Demandes
{
    /**
     * @param  array{objet: string, message: string, nom_contact?: string, courriel?: string, telephone?: string,
     *               organisme?: string, pays?: string, projet_id?: int}  $donnees
     */
    public function enregistrer(TypeDemande $type, array $donnees, BaseLegale $base, bool $consentement, string $versionInformation): Demande
    {
        if ($base === BaseLegale::Consentement && ! $consentement) {
            throw new RegleMetierException('Le consentement est requis pour envoyer cette demande.');
        }

        $demande = new Demande([...$donnees, 'type_demande' => $type, 'base' => $base, 'consentement_donne' => $consentement,
            'texte_information_version' => $versionInformation]);
        $demande->forceFill([
            'numero_suivi' => $this->nouveauNumero(),
            'recue_le' => now(),
            'conserver_jusqu_au' => now()->addDays((int) config('makary.conservation.demande_jours'))->toDateString(),
        ])->save();

        return $demande->refresh();
    }

    /** Numéro de suivi non devinable : DEM-AAAA-XXXXXX (lettres et chiffres sans ambiguïté). */
    private function nouveauNumero(): string
    {
        do {
            $numero = 'DEM-'.now()->format('Y').'-'.Str::upper(Str::random(6));
            $numero = strtr($numero, ['O' => 'P', 'I' => 'J']);
        } while (! preg_match('/^DEM-\d{4}-[A-Z0-9]{6}$/', $numero) || Demande::where('numero_suivi', $numero)->exists());

        return $numero;
    }

    /**
     * Anonymise les demandes et supprime les lignes du journal dont la conservation est échue (procédure stockée de la base).
     *
     * @return array{demandes_anonymisees: int, journaux_supprimes: int}
     */
    public function purgerDonneesExpirees(): array
    {
        $resultat = DB::selectOne('CALL purger_donnees_expirees()');

        return ['demandes_anonymisees' => (int) $resultat->demandes_anonymisees, 'journaux_supprimes' => (int) $resultat->journaux_supprimes];
    }
}

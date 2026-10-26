<?php

namespace App\Domaine\Statistiques;

use App\Domaine\Enums\DecisionValidation;
use App\Domaine\Enums\StatutLigneFinancement;
use App\Domaine\Enums\TypeObjet;
use App\Models\Contenu;
use App\Models\Demande;
use App\Models\ProjetFinancement;
use App\Models\ProjetTransition;
use App\Models\Utilisateur;
use App\Models\ValidationDemande;
use App\Portail\Lecture;
use App\Securite\Autorisations;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du §12 et file « À traiter » du tableau de bord. Calculés par le système, jamais saisis à la main
 * (fiche projet §6) ; les montants ne sont jamais additionnés entre devises.
 */
class Indicateurs
{
    public function __construct(private readonly Lecture $lecture, private readonly Autorisations $autorisations) {}

    /** @return array{validations: int, transitions: int, demandes_nouvelles: int, mes_brouillons: int} */
    public function aTraiter(Utilisateur $agent): array
    {
        $perimetres = $this->autorisations->perimetresPour($agent, 'contenu.approuver');
        $estAdmin = $agent->roles->contains(fn ($r) => in_array($r->code, ['superadmin', 'secretaire_general'], true));
        $requeteValidations = ValidationDemande::where('decision', DecisionValidation::EnAttente->value);
        if (! $estAdmin) {
            $requeteValidations->where('soumis_par', '<>', $agent->id);
        }
        $validations = $requeteValidations->get()
            ->filter(fn (ValidationDemande $v) => $perimetres === null || array_intersect($v->objet()->perimetres(), $perimetres) !== []);

        return [
            'validations' => $perimetres === [] ? 0 : $validations->count(),
            'transitions' => $agent->can('projet.valider_transition')
                ? ProjetTransition::where('decision', DecisionValidation::EnAttente->value)->where('demande_par', '<>', $agent->id)->get()
                    ->filter(fn (ProjetTransition $t) => $agent->can('projet.valider_transition', $t))->count()
                : 0,
            'demandes_nouvelles' => $agent->can('demande.traiter') ? Demande::where('statut', 'recue')->whereNull('anonymisee_le')->count() : 0,
            'mes_brouillons' => Contenu::where('auteur_id', $agent->id)->where('etat', 'brouillon')->count(),
        ];
    }

    /**
     * @return array{fiches_publiees: int, fiches_a_jour: int, contacts_recus: int, contacts_qualifies: int,
     *               telechargements_fiches: int, delai_moyen_jours: ?float, delai_median_jours: ?float, acquis_par_devise: array<string, string>}
     */
    public function mois(?CarbonInterface $mois = null): array
    {
        $debut = ($mois ?? now())->copy()->startOfMonth();
        $fin = $debut->copy()->endOfMonth();
        $fiches = $this->lecture->projets();
        $contacts = Demande::whereIn('type_demande', ['investisseur', 'partenaire', 'diaspora'])->whereBetween('recue_le', [$debut, $fin]);
        $delais = Demande::whereIn('type_demande', ['investisseur', 'partenaire'])->whereNotNull('premiere_reponse_le')
            ->whereBetween('recue_le', [$debut, $fin])->get()
            ->map(fn (Demande $d) => round($d->recue_le->diffInMinutes($d->premiere_reponse_le) / 1440, 1))->sort()->values();

        return [
            'fiches_publiees' => $fiches->count(),
            // Fiche à jour : statut mis à jour depuis moins de six mois (fiche projet §3 et §6).
            'fiches_a_jour' => $fiches->filter(fn ($f) => now()->subMonths(6)->lte($f->get('statut.mis_a_jour_le')))->count(),
            'contacts_recus' => (clone $contacts)->count(),
            'contacts_qualifies' => (clone $contacts)->whereNotIn('statut', ['recue'])->count(),
            'telechargements_fiches' => (int) DB::table('statistique_jour')->where('mesure', 'telechargement_fiche')
                ->where('objet_type', TypeObjet::Projet->value)->whereBetween('jour', [$debut->toDateString(), $fin->toDateString()])->sum('nombre'),
            'delai_moyen_jours' => $delais->isEmpty() ? null : round($delais->avg(), 1),
            'delai_median_jours' => $delais->isEmpty() ? null : $delais->median(),
            'acquis_par_devise' => ProjetFinancement::where('statut_ligne', StatutLigneFinancement::Acquis->value)
                ->whereYear('valide_le', $debut->year)->get()->groupBy('devise')
                ->map(fn ($lignes) => $lignes->reduce(fn (string $total, ProjetFinancement $l) => bcadd($total, $l->montant, 2), '0'))->all(),
        ];
    }

    /** Dernières actions journalisées, avec le nom de l'agent. */
    public function activite(int $nombre = 8)
    {
        return DB::table('journal_audit')->leftJoin('utilisateur', 'utilisateur.id', '=', 'journal_audit.utilisateur_id')
            ->whereNotIn('action', ['connexion.reussie', 'connexion.echec', 'deconnexion', 'demande.lire'])
            ->orderByDesc('journal_audit.id')->limit($nombre)
            ->get(['journal_audit.horodatage', 'journal_audit.action', 'journal_audit.objet_type', 'journal_audit.objet_id', 'utilisateur.nom_affiche']);
    }
}

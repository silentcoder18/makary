<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Contexte;
use App\Domaine\Editorial\CircuitEditorial;
use App\Domaine\Enums\DecisionValidation;
use App\Domaine\Enums\StatutLigneFinancement;
use App\Domaine\Enums\StatutProjet;
use App\Domaine\Projets\Catalogue;
use App\Domaine\Projets\Fiches;
use App\Domaine\Projets\Financement;
use App\Domaine\Projets\Transitions;
use App\Http\Controllers\Controller;
use App\Models\Localite;
use App\Models\Media;
use App\Models\Partenaire;
use App\Models\Projet;
use App\Models\ProjetFinancement;
use App\Models\ProjetPiece;
use App\Models\ProjetTransition;
use App\Models\Secteur;
use App\Models\Source;
use App\Models\TransitionAutorisee;
use App\Securite\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fiches projet (§8) : champs, éléments sourcés, financement, transitions de statut et circuit éditorial.
 * Chaque refus d'une transition est expliqué par la liste des conditions manquantes (fiche projet §3).
 */
class ProjetsController extends Controller
{
    public const TYPES_SOURCE = ['piece_officielle' => 'Pièce officielle', 'convention' => 'Convention', 'devis' => 'Devis', 'proces_verbal' => 'Procès-verbal',
        'recensement' => 'Recensement', 'estimation_interne' => 'Estimation interne', 'etude' => 'Étude', 'presse' => 'Presse', 'autre' => 'Autre'];

    public const TYPES_PIECE = ['etude_faisabilite' => 'Étude de faisabilité', 'etude_impact' => 'Étude d’impact', 'autorisation' => 'Autorisation', 'permis' => 'Permis',
        'convention' => 'Convention', 'ordre_service' => 'Ordre de service', 'pv_reception' => 'Procès-verbal de réception',
        'attestation_achevement' => 'Attestation d’achèvement', 'rapport' => 'Rapport', 'autre' => 'Autre'];

    public function __construct(
        private readonly Fiches $fiches,
        private readonly Transitions $transitions,
        private readonly Financement $financement,
        private readonly CircuitEditorial $circuit,
        private readonly Catalogue $catalogue,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('projet.lire_interne');
        $statut = StatutProjet::tryFrom((string) $request->query('statut'));

        return view('admin.projets.index', [
            'projets' => Projet::with('transitions')->when($statut, fn ($q) => $q->where('statut', $statut->value))->orderByDesc('statut_mis_a_jour_le')->get(),
            'statut' => $statut,
        ]);
    }

    public function creer(): View
    {
        Gate::authorize('projet.creer');

        return view('admin.projets.creer', ['secteurs' => Secteur::orderBy('libelle')->pluck('libelle', 'code'), 'localites' => Localite::orderBy('nom')->pluck('nom', 'id')]);
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'unique:projet,slug'],
            'secteur' => ['required', 'exists:secteur,code'],
            'localites' => ['required', 'array', 'min:1'], 'localites.*' => ['integer', 'exists:localite,id'],
        ]);
        $projet = $this->fiches->creer($request->user(), collect($donnees)->except('localites')->all(), array_map('intval', $donnees['localites']));

        return redirect()->route('admin.projets.show', $projet)->with('statut', "Fiche {$projet->code} créée au statut « idée identifiée ».");
    }

    public function show(Request $request, Projet $projet): View
    {
        Gate::authorize('projet.lire_interne');
        $projet->load(['localites', 'medias', 'estimations.source', 'beneficiaires.source', 'indicateurs', 'pieces.source', 'financements.partenaire', 'financements.source', 'transitions.demandePar', 'transitions.validePar']);
        $suivants = TransitionAutorisee::where('de_statut', $projet->statut->value)->get()->map(fn (TransitionAutorisee $t) => [
            'vers' => $t->vers_statut,
            'perimetre' => $t->perimetre,
            'piece' => $t->piece_requise,
            // Sans motif : les conditions de motif (suspension, révision, financement partiel) sont aussi expliquées.
            'manque' => $this->transitions->conditionsManquantes($projet, $projet->statut, $t->vers_statut, null),
        ]);

        return view('admin.projets.show', [
            'projet' => $projet,
            'synthese' => $projet->syntheseFinanciere(),
            'suivants' => $suivants,
            'enAttente' => $projet->transitions->firstWhere('decision', DecisionValidation::EnAttente),
            'validation' => $projet->validations()->latest('id')->first(),
            'publication' => $projet->publicationCourante(),
            'secteurs' => Secteur::orderBy('libelle')->pluck('libelle', 'code'),
            'localites' => Localite::orderBy('nom')->pluck('nom', 'id'),
            'partenaires' => Partenaire::orderBy('nom')->pluck('nom', 'id'),
            'mediatheque' => Media::orderByDesc('id')->pluck('titre', 'id'),
            'lienPublic' => route('projets.show', $projet->slug),
        ]);
    }

    public function mettreAJour(Request $request, Projet $projet): RedirectResponse
    {
        $donnees = $request->validateWithBag('fiche', [
            'titre' => ['required', 'string', 'max:120'],
            'secteur' => ['required', 'exists:secteur,code'],
            'resume' => ['nullable', 'string', 'max:500'],
            'description_technique' => ['nullable', 'string', 'max:20000'],
            'description_publique' => ['boolean'],
            'besoin' => ['nullable', 'string', 'max:4000'],
            'besoin_source' => ['nullable', 'required_with:besoin', 'string', 'max:300'],
            'besoin_source_date' => ['nullable', 'required_with:besoin', 'date'],
            'service_responsable' => ['nullable', 'string', 'max:160'],
            'localites' => ['required', 'array', 'min:1'], 'localites.*' => ['integer', 'exists:localite,id'],
        ], ['besoin_source.required_with' => 'Le besoin doit être sourcé (§8).']);

        $champs = collect($donnees)->only(['titre', 'secteur', 'resume', 'description_technique', 'besoin', 'service_responsable'])->all();
        $champs['description_publique'] = $request->boolean('description_publique');
        if (filled($donnees['besoin'] ?? null) && filled($donnees['besoin_source'] ?? null)
            && $donnees['besoin_source'] !== $projet->besoinSource?->intitule) {
            $champs['besoin_source_id'] = Source::create(['type_source' => 'autre', 'intitule' => $donnees['besoin_source'],
                'date_source' => $donnees['besoin_source_date'], 'cree_par' => $request->user()->id])->id;
        }
        $this->fiches->modifier($projet, $request->user(), $champs, array_map('intval', $donnees['localites']));

        if ($request->input('action') === 'publier' && $request->user()->can('contenu.publier', $projet)) {
            $this->circuit->publierDirectement($projet, $request->user());

            return back()->with('statut', 'Fiche projet mise à jour et publiée en direct sur le portail.');
        }

        return back()->with('statut', 'Fiche enregistrée.');
    }

    /** Ajout d'un élément sourcé : estimation de coût, bénéficiaires, indicateur ou pièce. */
    public function ajouterElement(Request $request, Projet $projet, string $relation): RedirectResponse
    {
        $regles = match ($relation) {
            'estimations' => ['montant' => ['required', 'numeric', 'gt:0'], 'devise' => ['required', 'regex:/^[A-Z]{3}$/'], 'date_estimation' => ['required', 'date'],
                'nature_cout' => ['required', Rule::in(['estimation', 'devis', 'marche', 'cout_final'])], 'nature' => ['required', Rule::in(['verifie', 'estimation', 'a_confirmer'])]],
            'beneficiaires' => ['nombre' => ['required', 'integer', 'gt:0'], 'categorie' => ['required', 'string', 'max:80'], 'methode_estimation' => ['required', 'string', 'max:500'],
                'date_estimation' => ['required', 'date'], 'nature' => ['required', Rule::in(['verifie', 'estimation', 'a_confirmer'])]],
            'indicateurs' => ['libelle' => ['required', 'string', 'max:160'], 'unite' => ['required', 'string', 'max:40'], 'valeur_reference' => ['nullable', 'numeric'],
                'cible' => ['required', 'numeric'], 'methode_mesure' => ['required', 'string', 'max:500']],
            'pieces' => ['type_piece' => ['required', Rule::in(array_keys(self::TYPES_PIECE))], 'diffusion' => ['required', Rule::in(['public', 'sur_demande', 'interne'])]],
            default => abort(404),
        };
        $sourcee = $relation !== 'indicateurs';
        $donnees = $request->validateWithBag($relation, $regles + ($sourcee ? $this->reglesSource() : []));
        $source = $sourcee ? $this->source($donnees) : null;
        $this->fiches->ajouter($projet, $request->user(), $relation, collect($donnees)->except(['source_type', 'source_intitule', 'source_reference', 'source_date'])->all(), $source);

        return back()->with('statut', 'Élément ajouté à la fiche, avec sa source.');
    }

    public function ajouterLigne(Request $request, Projet $projet): RedirectResponse
    {
        $donnees = $request->validateWithBag('financement', [
            'statut_ligne' => ['required', Rule::in(['recherche', 'sollicite'])],
            'partenaire_id' => ['nullable', 'required_if:statut_ligne,sollicite', 'exists:partenaire,id'],
            'montant' => ['required', 'numeric', 'gt:0'], 'devise' => ['required', 'regex:/^[A-Z]{3}$/'], 'date_information' => ['required', 'date'],
        ] + $this->reglesSource(), ['partenaire_id.required_if' => 'Une ligne sollicitée indique le financeur sollicité.']);
        $source = Source::create([...$this->source($donnees), 'cree_par' => $request->user()->id]);
        $this->financement->ajouterLigne($projet, $request->user(), StatutLigneFinancement::from($donnees['statut_ligne']), [
            'partenaire_id' => $donnees['partenaire_id'] ?? null, 'montant' => $donnees['montant'], 'devise' => $donnees['devise'],
            'date_information' => $donnees['date_information'], 'source_id' => $source->id,
        ]);

        return back()->with('statut', 'Ligne de financement ajoutée.');
    }

    public function validerAcquis(Request $request, ProjetFinancement $ligne): RedirectResponse
    {
        $donnees = $request->validateWithBag('acquis-'.$ligne->id, $this->reglesSource());
        $piece = Source::create([...$this->source($donnees), 'cree_par' => $request->user()->id]);
        $this->financement->validerAcquis($ligne, $request->user(), $piece);

        return back()->with('statut', 'Ligne validée comme acquise, sur pièce.');
    }

    public function demanderTransition(Request $request, Projet $projet): RedirectResponse
    {
        $donnees = $request->validateWithBag('transition', [
            'vers' => ['required', Rule::enum(StatutProjet::class)],
            'motif' => ['nullable', 'string', 'max:2000'],
            'piece_id' => ['nullable', 'integer'],
        ]);
        $piece = isset($donnees['piece_id']) ? ProjetPiece::where('projet_id', $projet->id)->find($donnees['piece_id']) : null;
        $this->transitions->demander($projet, StatutProjet::from($donnees['vers']), $request->user(), $donnees['motif'] ?? null, $piece);

        return back()->with('statut', 'Demande de changement de statut transmise au référent compétent.');
    }

    public function approuverTransition(Request $request, ProjetTransition $transition): RedirectResponse
    {
        $projet = $this->transitions->approuver($transition, $request->user());

        return back()->with('statut', "Transition approuvée : le projet est maintenant « {$projet->statut->libelle()} ». La fiche publique suivra le circuit éditorial.");
    }

    public function refuserTransition(Request $request, ProjetTransition $transition): RedirectResponse
    {
        $motif = $request->validateWithBag('refus', ['motif_refus' => ['required', 'string', 'max:2000']], ['motif_refus.required' => 'Le motif du refus est obligatoire.'])['motif_refus'];
        $this->transitions->refuser($transition, $request->user(), $motif);

        return back()->with('statut', 'Transition refusée ; le motif est transmis au demandeur.');
    }

    public function soumettre(Request $request, Projet $projet): RedirectResponse
    {
        $this->circuit->soumettre($projet, $request->user());

        return back()->with('statut', 'Fiche soumise à la revue éditoriale : son instantané public est figé.');
    }

    public function publier(Request $request, Projet $projet): RedirectResponse
    {
        $this->circuit->publierDirectement($projet, $request->user());

        return back()->with('statut', 'Fiche projet publiée directement sur le portail.');
    }

    public function depublier(Request $request, Projet $projet): RedirectResponse
    {
        $this->circuit->depublier($projet, $request->user());

        return back()->with('statut', 'Fiche projet retirée du portail et remise en brouillon.');
    }

    public function catalogue(Request $request, Projet $projet): RedirectResponse
    {
        if ($projet->visible_catalogue) {
            $motif = $request->validateWithBag('catalogue', ['motif_retrait' => ['required', 'string', 'max:500']])['motif_retrait'];
            $this->catalogue->retirer($projet, $request->user(), $motif);

            return back()->with('statut', 'Fiche retirée du catalogue public. Elle reste dans le back-office avec son historique.');
        }
        $this->catalogue->retablir($projet, $request->user());

        return back()->with('statut', 'Fiche rétablie dans le catalogue public.');
    }

    /** Photos de la fiche (MKY-P3-08) : choisies dans la médiathèque ; chacune n'est publiée qu'avec son droit d'usage vérifié. */
    public function ajouterPhoto(Request $request, Projet $projet): RedirectResponse
    {
        Gate::authorize('projet.modifier', $projet);
        $media = $request->validateWithBag('photos', ['media_id' => ['required', 'integer', 'exists:media,id']],
            ['media_id.required' => 'Choisissez une photo de la médiathèque.'])['media_id'];
        Contexte::enTantQue($request->user(), function () use ($projet, $media, $request) {
            $ordre = 1 + (int) DB::table('projet_media')->where('projet_id', $projet->id)->max('ordre');
            $projet->medias()->syncWithoutDetaching([$media => ['ordre' => $ordre]]);
            Journal::consigner('projet.photo', $projet, ['media_id' => (int) $media, 'action' => 'ajout'], $request->user()->id);
        });

        return back()->with('statut', 'Photo ajoutée à la fiche. Soumettez la fiche à la revue pour la publier.');
    }

    public function retirerPhoto(Request $request, Projet $projet, Media $media): RedirectResponse
    {
        Gate::authorize('projet.modifier', $projet);
        Contexte::enTantQue($request->user(), function () use ($projet, $media, $request) {
            $projet->medias()->detach($media->id);
            Journal::consigner('projet.photo', $projet, ['media_id' => $media->id, 'action' => 'retrait'], $request->user()->id);
        });

        return back()->with('statut', 'Photo retirée de la fiche (elle reste dans la médiathèque).');
    }

    /** @return array<string, list<mixed>> */
    private function reglesSource(): array
    {
        return [
            'source_type' => ['required', Rule::in(array_keys(self::TYPES_SOURCE))],
            'source_intitule' => ['required', 'string', 'max:300'],
            'source_reference' => ['nullable', 'string', 'max:160'],
            'source_date' => ['required', 'date'],
        ];
    }

    /** @return array{type_source: string, intitule: string, reference: ?string, date_source: string} */
    private function source(array $donnees): array
    {
        return ['type_source' => $donnees['source_type'], 'intitule' => $donnees['source_intitule'],
            'reference' => $donnees['source_reference'] ?? null, 'date_source' => $donnees['source_date']];
    }
}

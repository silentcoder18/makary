<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Demandes\Traitement;
use App\Domaine\Enums\StatutDemande;
use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Demandes reçues (M11) : qualification, affectation, réponse, clôture. Chaque consultation est journalisée. */
class DemandesController extends Controller
{
    public function __construct(private readonly Traitement $traitement) {}

    public function index(Request $request): View
    {
        Gate::authorize('demande.lire');
        $statut = StatutDemande::tryFrom((string) $request->query('statut'));

        // La liste n'affiche ni nom ni adresse : les données personnelles ne sont visibles qu'en consultation journalisée.
        return view('admin.demandes.index', [
            'demandes' => Demande::whereNull('anonymisee_le')->when($statut, fn ($q) => $q->where('statut', $statut->value))
                ->orderByRaw("statut = 'recue' DESC")->orderByDesc('recue_le')->paginate(25)->withQueryString(),
            'statut' => $statut,
        ]);
    }

    public function show(Request $request, Demande $demande): View
    {
        $this->traitement->consulter($demande, $request->user());

        return view('admin.demandes.show', [
            'demande' => $demande->load('evenements', 'affecteeA', 'projet'),
            'suivants' => $this->traitement->suivants($demande),
            'agents' => Utilisateur::where('actif', true)->orderBy('nom_affiche')->get()->filter(fn (Utilisateur $u) => $u->can('demande.traiter')),
        ]);
    }

    public function agir(Request $request, Demande $demande, string $action): RedirectResponse
    {
        $agent = $request->user();
        match ($action) {
            'qualifier' => $this->traitement->qualifier($demande, $agent, (int) $request->validate(['priorite' => ['required', 'integer', 'between:1,3']])['priorite'],
                $request->input('note')),
            'affecter' => $this->traitement->affecter($demande, $agent, Utilisateur::findOrFail($request->validate(['agent' => ['required', 'integer']])['agent'])),
            'commencer' => $this->traitement->commencer($demande, $agent),
            'repondre' => $this->traitement->repondre($demande, $agent, $request->validate(['reponse' => ['required', 'string', 'max:4000']])['reponse']),
            'cloturer' => $this->traitement->cloturer($demande, $agent, $request->input('note')),
            'rejeter' => $this->traitement->rejeter($demande, $agent, $request->validate(['motif' => ['required', 'string', 'max:2000']])['motif']),
            default => abort(404),
        };

        return back()->with('statut', 'Demande mise à jour.');
    }
}

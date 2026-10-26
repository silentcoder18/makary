<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Editorial\CircuitEditorial;
use App\Domaine\Enums\DecisionValidation;
use App\Http\Controllers\Controller;
use App\Models\ValidationDemande;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * File de validation : éléments soumis dans le périmètre du validateur, examen de l'instantané soumis comparé
 * à la version publiée, décision (approbation ou renvoi motivé), publication immédiate ou programmée.
 */
class ValidationController extends Controller
{
    public function __construct(private readonly CircuitEditorial $circuit) {}

    public function index(Request $request): View
    {
        Gate::authorize('contenu.approuver');
        $type = $request->query('type');

        $filtreType = function ($q) use ($type) {
            if ($type === 'contenu') {
                $q->where('objet_type', 'contenu');
            } elseif ($type === 'media') {
                $q->where('objet_type', 'media');
            } elseif ($type === 'autres') {
                $q->whereNotIn('objet_type', ['contenu', 'media']);
            }
        };

        $enAttente = ValidationDemande::with('soumisPar')
            ->where('decision', DecisionValidation::EnAttente->value)
            ->where($filtreType)
            ->orderBy('soumis_le')
            ->get();

        $aPublier = ValidationDemande::with('decidePar')
            ->where('decision', DecisionValidation::Approuve->value)
            ->whereDoesntHave('publication')
            ->where($filtreType)
            ->orderBy('decide_le')
            ->get();

        $compteurs = [
            'tous' => ValidationDemande::where('decision', DecisionValidation::EnAttente->value)->count(),
            'contenu' => ValidationDemande::where('decision', DecisionValidation::EnAttente->value)->where('objet_type', 'contenu')->count(),
            'media' => ValidationDemande::where('decision', DecisionValidation::EnAttente->value)->where('objet_type', 'media')->count(),
            'autres' => ValidationDemande::where('decision', DecisionValidation::EnAttente->value)->whereNotIn('objet_type', ['contenu', 'media'])->count(),
        ];

        return view('admin.validation.index', [
            'enAttente' => $enAttente,
            'aPublier' => $aPublier,
            'typeFiltre' => $type,
            'compteurs' => $compteurs,
        ]);
    }

    public function show(Request $request, ValidationDemande $validation): View
    {
        Gate::authorize('contenu.approuver', $validation->objet());
        $objet = $validation->objet();
        $publiee = $objet->publicationCourante();

        return view('admin.validation.show', [
            'validation' => $validation->load('soumisPar', 'decidePar', 'publication'),
            'objet' => $objet,
            'publiee' => $publiee,
            'modifies' => $publiee ? array_keys(array_filter($validation->instantane, fn ($valeur, $cle) => ($publiee->instantane[$cle] ?? null) != $valeur, ARRAY_FILTER_USE_BOTH)) : null,
            'empechement' => $objet->empechementPublication(),
        ]);
    }

    public function approuver(Request $request, ValidationDemande $validation): RedirectResponse
    {
        $this->circuit->approuver($validation, $request->user());

        return back()->with('statut', 'Élément approuvé. Il peut maintenant être publié.');
    }

    public function approuverEtPublier(Request $request, ValidationDemande $validation): RedirectResponse
    {
        Gate::authorize('contenu.publier', $validation->objet());

        $this->circuit->approuver($validation, $request->user());
        $publication = $this->circuit->publier($validation, $request->user());

        return redirect()->route('admin.validation.index')->with('statut', "Élément approuvé et publié en direct sur le portail (version {$publication->version}).");
    }

    public function renvoyer(Request $request, ValidationDemande $validation): RedirectResponse
    {
        $motif = $request->validate(['motif' => ['required', 'string', 'max:2000']], ['motif.required' => 'Le motif du renvoi est obligatoire.'])['motif'];
        $this->circuit->renvoyer($validation, $request->user(), $motif);

        return redirect()->route('admin.validation.index')->with('statut', 'Élément renvoyé à son auteur, avec le motif.');
    }

    public function publier(Request $request, ValidationDemande $validation): RedirectResponse
    {
        $le = $request->validate(['publier_le' => ['nullable', 'date', 'after:now']])['publier_le'] ?? null;
        $publication = $this->circuit->publier($validation, $request->user(), $le ? Carbon::parse($le) : null);

        return redirect()->route('admin.validation.index')->with('statut', $publication->publie_le->isFuture()
            ? 'Publication programmée le '.$publication->publie_le->translatedFormat('j F Y à H\hi').'.'
            : "Publié : la version {$publication->version} est en ligne sur le portail.");
    }

    public function retirer(Request $request, ValidationDemande $validation): RedirectResponse
    {
        $motif = $request->validateWithBag('retrait', ['motif' => ['required', 'string', 'max:2000']], ['motif.required' => 'Le motif du retrait est obligatoire.'])['motif'];
        $retirees = $this->circuit->retirer($validation->objet(), $request->user(), $motif);

        return back()->with('statut', "Retiré du portail ($retirees version(s)).");
    }
}

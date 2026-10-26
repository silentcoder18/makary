<?php

namespace App\Http\Controllers\Admin;

use App\Domaine\Contexte;
use App\Http\Controllers\Controller;
use App\Models\Parametre;
use App\Securite\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Coordonnées officielles de la mairie (C-003, C-110), affichées en pied de page et par l'API (schéma Coordonnees du contrat).
 * Paramètre éditorial : modifié par l'administrateur éditorial (parametre.modifier_editorial), chaque changement journalisé.
 */
class CoordonneesController extends Controller
{
    public const NOMBRE_SERVICES = 12;

    public function modifier(): View
    {
        Gate::authorize('parametre.modifier_editorial');
        $parametre = Parametre::find('coordonnees');

        return view('admin.coordonnees', ['valeur' => $parametre?->valeur ?? [], 'parametre' => $parametre]);
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        Gate::authorize('parametre.modifier_editorial');
        $donnees = $request->validate([
            'adresse' => ['nullable', 'string', 'max:300'],
            'boite_postale' => ['nullable', 'string', 'max:60'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'courriel' => ['nullable', 'email:rfc', 'max:254'],
            'horaires' => ['nullable', 'string', 'max:300'],
            'services' => ['array', 'max:'.self::NOMBRE_SERVICES],
            'services.*.service' => ['nullable', 'string', 'max:120'],
            'services.*.telephone' => ['nullable', 'string', 'max:40'],
            'services.*.courriel' => ['nullable', 'email:rfc', 'max:254'],
        ], ['services.*.courriel.email' => 'Adresse électronique de service invalide.']);

        $valeur = [
            ...collect($donnees)->only(['adresse', 'boite_postale', 'telephone', 'courriel', 'horaires'])->map(fn ($v) => filled($v) ? trim($v) : null)->all(),
            // Seules les lignes dont le service est nommé sont conservées ; clés du contrat d'API (schéma Coordonnees).
            'services' => collect($donnees['services'] ?? [])->filter(fn ($s) => filled($s['service'] ?? null))
                ->map(fn ($s) => collect(['service' => null, 'telephone' => null, 'courriel' => null])->merge(collect($s)->only(['service', 'telephone', 'courriel']))
                    ->map(fn ($v) => filled($v) ? trim($v) : null)->all())->values()->all(),
        ];
        Contexte::enTantQue($request->user(), function () use ($valeur, $request) {
            Parametre::updateOrCreate(['cle' => 'coordonnees'], ['valeur' => $valeur, 'modifie_par' => $request->user()->id, 'modifie_le' => now()]);
            Journal::consigner('parametre.coordonnees', null, ['champs' => array_keys(array_filter($valeur))], $request->user()->id);
        });

        return back()->with('statut', 'Coordonnées enregistrées : elles s’affichent immédiatement sur le portail.');
    }
}

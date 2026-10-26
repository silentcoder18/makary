<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Securite\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestion des comptes utilisateurs et attribution des rôles (§7.1, §12).
 * Réservé aux comptes détenant la permission utilisateur.gerer et role.attribuer (Super Administrateur et Secrétaire Général).
 */
class UtilisateursController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('utilisateur.gerer');

        $recherche = trim((string) $request->query('q', ''));
        $roleFiltre = (string) $request->query('role', '');
        $statutFiltre = $request->query('statut');

        $utilisateurs = Utilisateur::with('roles')
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($sub) use ($recherche) {
                    $sub->where('nom_affiche', 'like', "%{$recherche}%")
                        ->orWhere('courriel', 'like', "%{$recherche}%")
                        ->orWhere('fonction', 'like', "%{$recherche}%");
                });
            })
            ->when($roleFiltre !== '', function ($q) use ($roleFiltre) {
                $q->whereHas('roles', fn ($r) => $r->where('code', $roleFiltre));
            })
            ->when($statutFiltre !== null && $statutFiltre !== '', function ($q) use ($statutFiltre) {
                $q->where('actif', (bool) $statutFiltre);
            })
            ->orderBy('nom_affiche')
            ->get();

        $roles = Role::orderBy('libelle')->get();

        return view('admin.utilisateurs.index', [
            'utilisateurs' => $utilisateurs,
            'roles' => $roles,
            'recherche' => $recherche,
            'roleFiltre' => $roleFiltre,
            'statutFiltre' => $statutFiltre,
        ]);
    }

    public function creer(): View
    {
        Gate::authorize('utilisateur.gerer');
        Gate::authorize('role.attribuer');

        return view('admin.utilisateurs.formulaire', [
            'utilisateur' => new Utilisateur(['actif' => true]),
            'roles' => Role::orderBy('libelle')->get(),
            'roleActuel' => null,
            'mode' => 'creation',
        ]);
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        Gate::authorize('utilisateur.gerer');
        Gate::authorize('role.attribuer');

        $valide = $request->validate([
            'nom_affiche' => ['required', 'string', 'max:120'],
            'courriel' => ['required', 'string', 'email:rfc', 'max:120', 'unique:utilisateur,courriel'],
            'fonction' => ['nullable', 'string', 'max:120'],
            'role_code' => ['required', 'string', 'exists:role,code'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'confirmed'],
            'actif' => ['nullable', 'boolean'],
        ]);

        $utilisateur = Utilisateur::create([
            'nom_affiche' => $valide['nom_affiche'],
            'courriel' => mb_strtolower(trim($valide['courriel'])),
            'fonction' => filled($valide['fonction'] ?? null) ? $valide['fonction'] : 'Agent communal',
            'mot_de_passe' => Hash::make($valide['mot_de_passe']),
            'actif' => $request->boolean('actif', true),
        ]);

        $utilisateur->attribuerRole($valide['role_code'], [], $request->user());

        Journal::consigner('utilisateur.creation', $utilisateur, [
            'role' => $valide['role_code'],
            'nom' => $utilisateur->nom_affiche,
            'courriel' => $utilisateur->courriel,
        ]);

        return redirect()->route('admin.utilisateurs.index')
            ->with('statut', "Le compte de {$utilisateur->nom_affiche} a été créé avec succès.");
    }

    public function modifier(Utilisateur $utilisateur): View
    {
        Gate::authorize('utilisateur.gerer');
        Gate::authorize('role.attribuer');

        return view('admin.utilisateurs.formulaire', [
            'utilisateur' => $utilisateur,
            'roles' => Role::orderBy('libelle')->get(),
            'roleActuel' => $utilisateur->roles->first()?->code,
            'mode' => 'edition',
        ]);
    }

    public function mettreAJour(Request $request, Utilisateur $utilisateur): RedirectResponse
    {
        Gate::authorize('utilisateur.gerer');
        Gate::authorize('role.attribuer');

        $valide = $request->validate([
            'nom_affiche' => ['required', 'string', 'max:120'],
            'courriel' => ['required', 'string', 'email:rfc', 'max:120', Rule::unique('utilisateur', 'courriel')->ignore($utilisateur->id)],
            'fonction' => ['nullable', 'string', 'max:120'],
            'role_code' => ['required', 'string', 'exists:role,code'],
            'mot_de_passe' => ['nullable', 'string', 'min:8', 'confirmed'],
            'actif' => ['nullable', 'boolean'],
        ]);

        $donnees = [
            'nom_affiche' => $valide['nom_affiche'],
            'courriel' => mb_strtolower(trim($valide['courriel'])),
            'fonction' => filled($valide['fonction'] ?? null) ? $valide['fonction'] : $utilisateur->fonction,
            'actif' => $request->boolean('actif', true),
        ];

        if (filled($valide['mot_de_passe'] ?? null)) {
            $donnees['mot_de_passe'] = Hash::make($valide['mot_de_passe']);
        }

        $utilisateur->update($donnees);

        $utilisateur->roles()->sync([
            $valide['role_code'] => [
                'perimetre' => '[]',
                'attribue_par' => $request->user()->id,
            ],
        ]);
        $utilisateur->unsetRelation('roles');

        Journal::consigner('utilisateur.modification', $utilisateur, [
            'role' => $valide['role_code'],
            'nom' => $utilisateur->nom_affiche,
            'courriel' => $utilisateur->courriel,
        ]);

        return redirect()->route('admin.utilisateurs.index')
            ->with('statut', "Le compte de {$utilisateur->nom_affiche} a été mis à jour.");
    }

    public function basculerStatut(Request $request, Utilisateur $utilisateur): RedirectResponse
    {
        Gate::authorize('utilisateur.gerer');

        if ($utilisateur->id === $request->user()->id) {
            return back()->with('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $nouveauStatut = ! $utilisateur->actif;
        $champs = ['actif' => $nouveauStatut];

        if (! $nouveauStatut) {
            $champs['desactive_le'] = now();
            $champs['conserver_jusqu_au'] = now()->addDays((int) config('makary.conservation.demande_jours', 730))->toDateString();
        } else {
            $champs['desactive_le'] = null;
            $champs['conserver_jusqu_au'] = null;
        }

        $utilisateur->forceFill($champs)->save();

        Journal::consigner('utilisateur.statut', $utilisateur, [
            'actif' => $nouveauStatut,
        ]);

        $etat = $nouveauStatut ? 'activé' : 'désactivé';

        return back()->with('statut', "Le compte de {$utilisateur->nom_affiche} est désormais {$etat}.");
    }

    public function detruire(Request $request, Utilisateur $utilisateur): RedirectResponse
    {
        Gate::authorize('utilisateur.gerer');

        if ($utilisateur->id === $request->user()->id) {
            return back()->with('erreur', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $nom = $utilisateur->nom_affiche;
        $courriel = $utilisateur->courriel;

        $utilisateur->roles()->detach();

        DB::table('contenu')->where('auteur_id', $utilisateur->id)->update(['auteur_id' => $request->user()->id]);
        DB::table('projet')->where('responsable_id', $utilisateur->id)->update(['responsable_id' => null]);
        DB::table('demande')->where('agent_id', $utilisateur->id)->update(['agent_id' => null]);
        DB::table('validation_demande')->where('soumis_par', $utilisateur->id)->update(['soumis_par' => $request->user()->id]);
        DB::table('validation_demande')->where('decide_par', $utilisateur->id)->update(['decide_par' => null]);
        DB::table('publication')->where('publie_par', $utilisateur->id)->update(['publie_par' => $request->user()->id]);

        $utilisateur->delete();

        Journal::consigner('utilisateur.suppression', null, [
            'nom' => $nom,
            'courriel' => $courriel,
        ]);

        return redirect()->route('admin.utilisateurs.index')
            ->with('statut', "Le compte de {$nom} a été définitivement supprimé.");
    }
}

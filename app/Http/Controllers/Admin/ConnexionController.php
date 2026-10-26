<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use App\Securite\DoubleAuthentification;
use App\Securite\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Connexion au back-office en deux étapes : mot de passe, puis code TOTP pour les rôles qui l'exigent (§7, §7.1).
 */
class ConnexionController extends Controller
{
    public function __construct(private readonly DoubleAuthentification $mfa) {}

    public function formulaire(): View
    {
        return view('admin.connexion.formulaire');
    }

    public function connecter(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'courriel' => ['required', 'email:rfc'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        $utilisateur = Utilisateur::where('courriel', mb_strtolower($donnees['courriel']))->first();
        // Même réponse pour un compte inconnu, désactivé ou un mauvais mot de passe : pas d'énumération des comptes.
        if (! $utilisateur || ! $utilisateur->actif || ! Hash::check($donnees['mot_de_passe'], $utilisateur->mot_de_passe)) {
            // L'adresse saisie n'est pas conservée en clair (minimisation des données) : seule son empreinte l'est.
            Journal::consigner('connexion.echec', null, ['empreinte_courriel' => hash('sha256', mb_strtolower($donnees['courriel']))], $utilisateur?->id);
            throw ValidationException::withMessages(['courriel' => 'Identifiants incorrects.']);
        }

        $request->session()->regenerate();
        if (DoubleAuthentification::exigee($utilisateur)) {
            $request->session()->put('mfa.utilisateur_id', $utilisateur->id);

            return redirect()->route('admin.mfa');
        }

        return $this->ouvrirSession($request, $utilisateur, false);
    }

    public function formulaireMfa(Request $request): View|RedirectResponse
    {
        $utilisateur = $this->utilisateurEnAttente($request);
        if (! $utilisateur) {
            return redirect()->route('admin.connexion');
        }
        $configuration = null;
        if (! $utilisateur->mfa_active) {
            // Premier usage : un secret est proposé ; il n'est enregistré qu'après la saisie d'un code valide.
            $secret = $request->session()->get('mfa.secret_provisoire') ?? $this->mfa->genererSecret();
            $request->session()->put('mfa.secret_provisoire', $secret);
            $configuration = ['secret' => trim(chunk_split($secret, 4, ' ')), 'uri' => $this->mfa->uriConfiguration($utilisateur, $secret)];
        }

        return view('admin.connexion.mfa', ['configuration' => $configuration]);
    }

    public function verifierMfa(Request $request): RedirectResponse
    {
        $utilisateur = $this->utilisateurEnAttente($request);
        if (! $utilisateur) {
            return redirect()->route('admin.connexion');
        }
        $code = preg_replace('/\s+/', '', (string) $request->validate(['code' => ['required', 'string']])['code']);
        $secret = $utilisateur->mfa_active ? $utilisateur->mfa_secret : $request->session()->get('mfa.secret_provisoire');

        if (! $secret || ! $this->mfa->verifier($utilisateur, $secret, $code)) {
            Journal::consigner('mfa.echec', $utilisateur, [], $utilisateur->id);
            throw ValidationException::withMessages(['code' => 'Code incorrect ou déjà utilisé.']);
        }

        if (! $utilisateur->mfa_active) {
            $utilisateur->forceFill(['mfa_secret' => $secret, 'mfa_active' => true])->save();
            Journal::consigner('mfa.activation', $utilisateur, [], $utilisateur->id);
        }
        $request->session()->forget(['mfa.utilisateur_id', 'mfa.secret_provisoire']);

        return $this->ouvrirSession($request, $utilisateur, true);
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        Journal::consigner('deconnexion', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.connexion')->with('statut', 'Vous êtes déconnecté.');
    }

    private function ouvrirSession(Request $request, Utilisateur $utilisateur, bool $mfaVerifie): RedirectResponse
    {
        Auth::guard('web')->login($utilisateur);
        $request->session()->regenerate();
        $request->session()->put(['mfa_verifie' => $mfaVerifie || ! $utilisateur->exigeMfa(), 'connecte_le' => now()->getTimestamp()]);
        $utilisateur->forceFill(['derniere_connexion' => now()])->save();
        // Une connexion sans second facteur pour un rôle qui l'exige ne peut venir que de la dispense locale : elle est tracée.
        $dispense = $utilisateur->exigeMfa() && ! $mfaVerifie ? ['mfa_dispensee_en_local' => true] : [];
        Journal::consigner('connexion.reussie', $utilisateur, ['mfa' => $mfaVerifie] + $dispense, $utilisateur->id);

        return redirect()->intended(route('admin.tableau-de-bord'));
    }

    private function utilisateurEnAttente(Request $request): ?Utilisateur
    {
        $id = $request->session()->get('mfa.utilisateur_id');
        $utilisateur = $id ? Utilisateur::find($id) : null;

        return $utilisateur && $utilisateur->actif ? $utilisateur : null;
    }
}

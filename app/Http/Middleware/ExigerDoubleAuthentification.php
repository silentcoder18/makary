<?php

namespace App\Http\Middleware;

use App\Securite\DoubleAuthentification;
use App\Securite\Journal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office : compte actif, double authentification validée pour les rôles qui l'exigent,
 * et durée maximale de session (8 h par défaut) quelle que soit l'activité.
 */
class ExigerDoubleAuthentification
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();
        $session = $request->session();
        $connecteLe = $session->get('connecte_le');
        $expiree = $connecteLe === null || now()->diffInHours(now()->createFromTimestamp($connecteLe), true) >= config('makary.session.duree_max_heures');

        if (! $utilisateur || ! $utilisateur->actif || $expiree
            || (DoubleAuthentification::exigee($utilisateur) && $session->get('mfa_verifie') !== true)) {
            if ($utilisateur && $expiree) {
                Journal::consigner('session.expiree', $utilisateur);
            }
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('admin.connexion')->with('statut', 'Votre session a pris fin. Veuillez vous reconnecter.');
        }

        return $next($request);
    }
}

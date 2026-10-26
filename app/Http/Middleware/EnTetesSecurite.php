<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité (AN-13, architecture §7). Le proxy inverse les pose aussi : l'application
 * les applique elle-même pour ne pas dépendre de sa configuration (défense en profondeur).
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        // Une réponse peut déclarer une politique plus stricte (fichiers servis) : elle est conservée.
        if (! $reponse->headers->has('Content-Security-Policy')) {
            $reponse->headers->set('Content-Security-Policy', $this->politique());
        }
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $reponse->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Le back-office n'est jamais indexé (architecture §5).
        if ($request->is('admin', 'admin/*')) {
            $reponse->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $reponse->headers->set('Cache-Control', 'no-store, private');
        }
        // Environnement de démonstration : rien n'est indexé tant que les données sont fictives (règle 3).
        if (config('makary.donnees_fictives')) {
            $reponse->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $reponse;
    }

    /** CSP stricte, sans script ni style en ligne. En développement seulement, le serveur Vite est autorisé. */
    private function politique(): string
    {
        $vite = '';
        if (app()->isLocal() && Vite::isRunningHot()) {
            $hote = rtrim((string) file_get_contents(Vite::hotFile()));
            $vite = ' '.$hote.' '.preg_replace('#^http#', 'ws', $hote);
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'".$vite,
            "style-src 'self'".$vite,
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'".$vite,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}

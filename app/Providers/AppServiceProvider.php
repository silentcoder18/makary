<?php

namespace App\Providers;

use App\Models\Parametre;
use App\Models\Utilisateur;
use App\Portail\Format;
use App\Portail\Lecture;
use App\Securite\Autorisations;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Autorisations::class);
    }

    public function boot(): void
    {
        // Chaque permission de specs/securite/permissions.yaml devient une « Gate » Laravel, contrôlée côté serveur (§7.1).
        foreach (array_keys(config('permissions.permissions')) as $permission) {
            Gate::define($permission, fn (Utilisateur $utilisateur, mixed $objet = null) => app(Autorisations::class)->peut($utilisateur, $permission, $objet));
        }

        // Limitation des tentatives de connexion et de double authentification (§7, architecture §7).
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(config('makary.connexion.tentatives_par_minute'))
            ->by(mb_strtolower((string) $request->input('courriel')).'|'.$request->ip()));
        RateLimiter::for('mfa', fn (Request $request) => Limit::perMinute(config('makary.connexion.tentatives_par_minute'))
            ->by((string) $request->session()->get('mfa.utilisateur_id').'|'.$request->ip()));
        // Formulaires publics : 5 envois par heure et par adresse IP (architecture §7).
        RateLimiter::for('formulaires', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        // API publique : 120 requêtes par minute et par adresse IP ; suivi des demandes : 10 par minute.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('suivi', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Mise en forme française commune à toutes les vues du portail.
        View::share('format', $this->app->make(Format::class));
        // Localités et coordonnées officielles du pied de page (P10, C-003). Une page d'erreur doit s'afficher même si la base ne répond pas.
        View::composer('portail.partiels.pied', function ($vue) {
            try {
                $vue->with('localitesPied', $this->app->make(Lecture::class)->localites());
                $vue->with('coordonneesPied', Parametre::find('coordonnees')?->valeur ?? []);
            } catch (Throwable) {
                $vue->with(['localitesPied' => collect(), 'coordonneesPied' => []]);
            }
        });
        Carbon::setLocale('fr');
    }
}

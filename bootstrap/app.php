<?php

use App\Api\Probleme;
use App\Domaine\RegleMetierException;
use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\ExigerDoubleAuthentification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(EnTetesSecurite::class);
        $middleware->throttleApi('api');
        $middleware->alias(['mfa' => ExigerDoubleAuthentification::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.connexion'));
        $middleware->redirectUsersTo(fn () => route('admin.tableau-de-bord'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API : toute erreur au format Problem Details (RFC 9457), sans détail technique.
        $exceptions->render(fn (Throwable $e, Request $request) => $request->is('api/*') ? Probleme::reponse($e, $request) : null);
        // Une règle métier refusée (§7, §8) ramène l'agent au formulaire, avec l'explication.
        $exceptions->render(fn (RegleMetierException $e) => back()->withInput()->with('erreur', $e->getMessage()));
        // Refus levé par un déclencheur de la base (SIGNAL SQLSTATE '45000', MySQL/MariaDB) : son message est destiné à l'agent.
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('admin/*') && ($e->errorInfo[0] ?? null) === '45000') {
                return back()->withInput()->with('erreur', 'Opération refusée par la base de données : '.trim($e->errorInfo[2] ?? 'règle du §8'));
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

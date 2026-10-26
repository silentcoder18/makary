<?php

namespace App\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Erreurs de l'API au format Problem Details (RFC 9457), comme le prévoit le contrat (schéma « Probleme »).
 * Une erreur interne ne révèle jamais de détail technique.
 */
final class Probleme
{
    private const TITRES = [
        400 => 'Requête invalide',
        404 => 'Ressource introuvable',
        405 => 'Méthode non permise',
        429 => 'Trop de requêtes',
        500 => 'Erreur interne',
    ];

    public static function reponse(Throwable $e, Request $request): JsonResponse
    {
        [$statut, $detail, $erreurs, $entetes] = match (true) {
            // Le contrat répond 400 (et non 422) à une requête invalide, avec le détail par champ.
            $e instanceof ValidationException => [400, 'Un ou plusieurs champs sont invalides.',
                collect($e->errors())->flatMap(fn (array $messages, string $champ) => array_map(fn ($m) => ['champ' => $champ, 'message' => $m], $messages))->values()->all(), []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getStatusCode() < 500 ? ($e->getMessage() ?: null) : null, null, $e->getHeaders()],
            default => [500, null, null, []],
        };
        $corps = array_filter([
            'type' => 'about:blank',
            'title' => self::TITRES[$statut] ?? 'Erreur',
            'status' => $statut,
            'detail' => $detail,
            'instance' => '/'.$request->path(),
            'erreurs' => $erreurs,
        ], fn ($valeur) => $valeur !== null);

        return response()->json($corps, $statut, ['Content-Type' => 'application/problem+json', ...$entetes]);
    }
}

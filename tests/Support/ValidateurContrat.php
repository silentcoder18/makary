<?php

namespace Tests\Support;

use stdClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Valide des réponses JSON contre les schémas du contrat specs/api/openapi.yaml (OpenAPI 3.1, JSON Schema).
 * Couvre les mots-clés employés par le contrat : $ref, allOf, oneOf, anyOf, type (y compris « null »), const, enum,
 * required, properties, additionalProperties, items, bornes numériques, longueurs, pattern et formats usuels.
 * Les objets JSON sont décodés en stdClass pour distinguer un objet vide d'une liste vide.
 */
final class ValidateurContrat
{
    private array $spec;

    public function __construct()
    {
        $this->spec = Yaml::parseFile(base_path('../specs/api/openapi.yaml'));
    }

    /** @return list<string> « GET /public/projets »… : toutes les opérations publiques du contrat */
    public function operationsPubliques(): array
    {
        $operations = [];
        foreach ($this->spec['paths'] as $chemin => $item) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $methode) {
                if (isset($item[$methode]) && str_starts_with($chemin, '/public/')) {
                    $operations[] = strtoupper($methode).' '.$chemin;
                }
            }
        }

        return $operations;
    }

    /** Schéma de réponse d'une opération pour un statut et un type de contenu ; null si le contrat n'en prévoit pas. */
    public function schemaReponse(string $operation, int $statut, string $type): ?array
    {
        [$methode, $chemin] = explode(' ', $operation, 2);
        $reponse = $this->spec['paths'][$chemin][strtolower($methode)]['responses'][(string) $statut] ?? null;
        if ($reponse === null) {
            return null;
        }
        $reponse = $this->resoudre($reponse);

        return $reponse['content'][$type]['schema'] ?? null;
    }

    /** @return list<string> erreurs ; liste vide si la donnée est conforme */
    public function valider(mixed $donnee, array $schema, string $ou = '$'): array
    {
        $schema = $this->resoudre($schema);
        $erreurs = [];
        foreach ($schema['allOf'] ?? [] as $sous) {
            $erreurs = [...$erreurs, ...$this->valider($donnee, $sous, $ou)];
        }
        if (isset($schema['oneOf'])) {
            $valides = count(array_filter($schema['oneOf'], fn ($s) => $this->valider($donnee, $s, $ou) === []));
            if ($valides !== 1) {
                $erreurs[] = "$ou : $valides alternatives « oneOf » satisfaites au lieu d'une";
            }
        }
        if (isset($schema['anyOf']) && ! array_filter($schema['anyOf'], fn ($s) => $this->valider($donnee, $s, $ou) === [])) {
            $erreurs[] = "$ou : aucune alternative « anyOf » satisfaite";
        }
        if (array_key_exists('const', $schema) && $donnee !== $schema['const']) {
            $erreurs[] = "$ou : ".json_encode($donnee)." au lieu de la constante {$schema['const']}";
        }
        if (isset($schema['enum']) && ! in_array($donnee, $schema['enum'], true)) {
            $erreurs[] = "$ou : ".json_encode($donnee).' hors énumération';
        }
        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            if (! array_filter($types, fn ($t) => $this->estDuType($donnee, $t))) {
                return [...$erreurs, "$ou : type ".get_debug_type($donnee).' au lieu de '.implode('|', $types)];
            }
        }

        if ($donnee instanceof stdClass) {
            $proprietes = get_object_vars($donnee);
            foreach ($schema['required'] ?? [] as $requis) {
                if (! array_key_exists($requis, $proprietes)) {
                    $erreurs[] = "$ou.$requis : champ obligatoire absent";
                }
            }
            foreach ($proprietes as $nom => $valeur) {
                if (isset($schema['properties'][$nom])) {
                    $erreurs = [...$erreurs, ...$this->valider($valeur, $schema['properties'][$nom], "$ou.$nom")];
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $erreurs[] = "$ou.$nom : champ non prévu par le contrat";
                }
            }
        }
        if (is_array($donnee) && isset($schema['items'])) {
            foreach ($donnee as $i => $element) {
                $erreurs = [...$erreurs, ...$this->valider($element, $schema['items'], "{$ou}[$i]")];
            }
        }
        if (is_int($donnee) || is_float($donnee)) {
            if (isset($schema['minimum']) && $donnee < $schema['minimum']) {
                $erreurs[] = "$ou : $donnee < minimum {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $donnee > $schema['maximum']) {
                $erreurs[] = "$ou : $donnee > maximum {$schema['maximum']}";
            }
            if (isset($schema['exclusiveMinimum']) && $donnee <= $schema['exclusiveMinimum']) {
                $erreurs[] = "$ou : $donnee ≤ {$schema['exclusiveMinimum']} (minimum exclusif)";
            }
        }
        if (is_string($donnee)) {
            if (isset($schema['minLength']) && mb_strlen($donnee) < $schema['minLength']) {
                $erreurs[] = "$ou : chaîne trop courte";
            }
            if (isset($schema['maxLength']) && mb_strlen($donnee) > $schema['maxLength']) {
                $erreurs[] = "$ou : chaîne trop longue";
            }
            if (isset($schema['pattern']) && ! preg_match('/'.str_replace('/', '\/', $schema['pattern']).'/u', $donnee)) {
                $erreurs[] = "$ou : « $donnee » ne respecte pas le motif {$schema['pattern']}";
            }
            if (isset($schema['format']) && ! $this->formatValide($donnee, $schema['format'])) {
                $erreurs[] = "$ou : « $donnee » n'est pas au format {$schema['format']}";
            }
        }

        return $erreurs;
    }

    private function estDuType(mixed $donnee, string $type): bool
    {
        return match ($type) {
            'null' => $donnee === null,
            'string' => is_string($donnee),
            'boolean' => is_bool($donnee),
            'integer' => is_int($donnee),
            'number' => is_int($donnee) || is_float($donnee),
            'array' => is_array($donnee),
            'object' => $donnee instanceof stdClass,
            default => false,
        };
    }

    private function formatValide(string $valeur, string $format): bool
    {
        return match ($format) {
            'date' => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur),
            'date-time' => (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $valeur),
            'uri' => filter_var($valeur, FILTER_VALIDATE_URL) !== false,
            'email' => filter_var($valeur, FILTER_VALIDATE_EMAIL) !== false,
            default => true,
        };
    }

    private function resoudre(array $schema): array
    {
        while (isset($schema['$ref'])) {
            $chemin = explode('/', substr($schema['$ref'], 2));
            $cible = $this->spec;
            foreach ($chemin as $segment) {
                $cible = $cible[$segment];
            }
            $schema = $cible;
        }

        return $schema;
    }
}

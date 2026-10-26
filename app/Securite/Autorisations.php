<?php

namespace App\Securite;

use App\Models\Utilisateur;

/**
 * Décide si un compte détient une permission, en appliquant la portée de l'attribution (MKY-P1-05) :
 *  - « toutes »    : accordée sans condition ;
 *  - « propre »    : seulement sur un objet dont le compte est l'auteur (ou sans objet, pour lister et créer) ;
 *  - « perimetre » : seulement si l'objet appartient à l'un des périmètres du compte pour ce rôle ;
 *                    sans objet, accordée si le compte a au moins un périmètre (le filtrage se fait à la requête).
 * Un compte désactivé ne détient aucune permission.
 */
class Autorisations
{
    public function peut(Utilisateur $utilisateur, string $permission, mixed $objet = null): bool
    {
        if (! $utilisateur->actif || ! array_key_exists($permission, config('permissions.permissions'))) {
            return false;
        }

        foreach ($utilisateur->rolesAvecPerimetres() as $role => $perimetres) {
            $portee = $this->portee($role, $permission);
            if ($portee === null) {
                continue;
            }
            if ($this->porteeSatisfaite($portee, $utilisateur, $perimetres, $objet)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Périmètres dans lesquels le compte détient la permission (pour filtrer les listes).
     *
     * @return list<string>|null null = sans restriction de périmètre
     */
    public function perimetresPour(Utilisateur $utilisateur, string $permission): ?array
    {
        $resultat = [];
        foreach ($utilisateur->rolesAvecPerimetres() as $role => $perimetres) {
            $portee = $this->portee($role, $permission);
            if ($portee === 'toutes' || $portee === 'propre') {
                return null;
            }
            if ($portee === 'perimetre') {
                $resultat = array_merge($resultat, $perimetres);
            }
        }

        return array_values(array_unique($resultat));
    }

    /** Portée de la permission pour le rôle ; les codes contiennent un point, d'où la lecture directe du tableau. */
    private function portee(string $role, string $permission): ?string
    {
        return config('permissions.attributions')[$role][$permission] ?? null;
    }

    /** @param  list<string>  $perimetres */
    private function porteeSatisfaite(string $portee, Utilisateur $utilisateur, array $perimetres, mixed $objet): bool
    {
        return match ($portee) {
            'toutes' => true,
            'propre' => ! $objet instanceof ObjetAPerimetre || $objet->auteurId() === $utilisateur->id,
            'perimetre' => $objet instanceof ObjetAPerimetre
                ? array_intersect($objet->perimetres(), $perimetres) !== []
                : $perimetres !== [],
            default => false,
        };
    }
}

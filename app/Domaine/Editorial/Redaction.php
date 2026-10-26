<?php

namespace App\Domaine\Editorial;

use App\Domaine\Contexte;
use App\Models\Contenu;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Gate;

/**
 * Rédaction des contenus éditoriaux. L'auteur est le compte qui crée ; un rédacteur ne modifie que ses contenus.
 * Une modification après soumission ne change pas ce qui a été soumis : l'instantané est figé (migration 0002).
 */
class Redaction
{
    /** @param  array<string, mixed>  $donnees */
    public function creer(Utilisateur $auteur, array $donnees): Contenu
    {
        Gate::forUser($auteur)->authorize('contenu.creer');

        return Contexte::enTantQue($auteur, function () use ($auteur, $donnees) {
            $contenu = new Contenu($donnees);
            $contenu->forceFill(['auteur_id' => $auteur->id])->save();

            return $contenu->refresh();
        });
    }

    /** @param  array<string, mixed>  $donnees */
    public function modifier(Contenu $contenu, Utilisateur $par, array $donnees): Contenu
    {
        Gate::forUser($par)->authorize('contenu.modifier', $contenu);

        return Contexte::enTantQue($par, function () use ($contenu, $donnees) {
            $contenu->fill($donnees)->save();

            return $contenu;
        });
    }
}

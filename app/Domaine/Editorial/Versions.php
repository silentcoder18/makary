<?php

namespace App\Domaine\Editorial;

use App\Domaine\Contexte;
use App\Domaine\RegleMetierException;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Fichier;
use App\Models\Utilisateur;
use App\Securite\Journal;
use Illuminate\Support\Facades\Gate;

/** Versions d'un document du centre documentaire (M07) : chaque version est conservée, la dernière est diffusée (E-14). */
class Versions
{
    public function ajouter(Document $document, Utilisateur $par, Fichier $fichier, ?string $note = null): DocumentVersion
    {
        Gate::forUser($par)->authorize('document.gerer', $document);
        if ($document->archive) {
            throw new RegleMetierException('Le document est archivé : aucune version ne peut lui être ajoutée.');
        }

        return Contexte::enTantQue($par, function () use ($document, $par, $fichier, $note) {
            $version = $document->versions()->create([
                // Sans le tri de la relation : un agrégat trié est refusé par le mode ONLY_FULL_GROUP_BY de MySQL.
                'numero' => 1 + (int) DocumentVersion::where('document_id', $document->id)->max('numero'),
                'fichier_id' => $fichier->id, 'note' => $note, 'cree_par' => $par->id,
            ]);
            Journal::consigner('document.version', $document, ['numero' => $version->numero, 'fichier_id' => $fichier->id], $par->id);

            return $version;
        });
    }
}

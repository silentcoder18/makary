<?php

namespace App\Domaine\Editorial;

use App\Domaine\Contexte;
use App\Domaine\Enums\DroitDiffusion;
use App\Domaine\Enums\DroitUsage;
use App\Domaine\Enums\EtatEditorial;
use App\Domaine\RegleMetierException;
use App\Models\Document;
use App\Models\Media;
use App\Models\Source;
use App\Models\Utilisateur;
use App\Securite\Cible;
use App\Securite\Journal;
use Illuminate\Support\Facades\Gate;

/**
 * Droits préalables à la publication : droit d'usage des médias (E-12), diffusion publique et archivage
 * des documents (référent juridique, E-14).
 */
class Droits
{
    public function confirmerDroitMedia(Media $media, Utilisateur $validateur, DroitUsage $droit, ?Source $justificatif = null): Media
    {
        Gate::forUser($validateur)->authorize('media.valider_droits', $media);
        if ($droit === DroitUsage::AVerifier) {
            throw new RegleMetierException('Indiquez le droit d’usage confirmé.');
        }
        if ($droit !== DroitUsage::ProprieteCommune && ! $justificatif) {
            throw new RegleMetierException('Une licence ou une autorisation écrite doit être jointe.');
        }

        return Contexte::enTantQue($validateur, function () use ($media, $validateur, $droit, $justificatif) {
            $media->forceFill(['droit' => $droit, 'justificatif_id' => $justificatif?->id])->save();
            Journal::consigner('media.valider_droits', $media, ['droit' => $droit->value], $validateur->id);

            return $media;
        });
    }

    /** Diffusion d'un document : « public » exige l'accord écrit du référent juridique (périmètre « juridique »). */
    public function accorderDiffusion(Document $document, Utilisateur $validateur, DroitDiffusion $diffusion, ?Source $accord = null): Document
    {
        Gate::forUser($validateur)->authorize('document.valider_diffusion', new Cible(['juridique']));
        if ($diffusion === DroitDiffusion::Public && ! $accord) {
            throw new RegleMetierException('La diffusion publique exige la pièce de l’accord du référent juridique.');
        }

        return Contexte::enTantQue($validateur, function () use ($document, $validateur, $diffusion, $accord) {
            $document->forceFill(['diffusion' => $diffusion, 'justificatif_diffusion' => $accord?->id])->save();
            Journal::consigner('document.valider_diffusion', $document, ['diffusion' => $diffusion->value], $validateur->id);

            return $document;
        });
    }

    /** Un document n'est jamais détruit : il est archivé et retiré du site public (E-14). */
    public function archiverDocument(Document $document, Utilisateur $par, string $motif): Document
    {
        Gate::forUser($par)->authorize('document.archiver', $document);
        if (trim($motif) === '') {
            throw new RegleMetierException('L’archivage doit être motivé.');
        }

        return Contexte::enTantQue($par, function () use ($document, $par, $motif) {
            $document->publications()->whereNull('retire_le')->update(['retire_le' => now(), 'motif_retrait' => "Archivé : $motif"]);
            $document->forceFill(['archive' => true, 'etat' => EtatEditorial::Retire])->save();
            Journal::consigner('document.archiver', $document, ['motif' => $motif], $par->id);

            return $document;
        });
    }
}

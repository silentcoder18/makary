<?php

namespace App\Domaine\Fichiers;

use App\Domaine\Contexte;
use App\Domaine\RegleMetierException;
use App\Models\Fichier;
use App\Models\Utilisateur;
use App\Securite\Antivirus;
use App\Securite\Journal;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Téléversement d'un fichier (architecture §5) :
 *  - type réel vérifié d'après le contenu, jamais d'après l'extension ; taille limitée ;
 *  - images recompressées : métadonnées supprimées (dont la position GPS), orientation appliquée, taille bornée ;
 *  - stockage privé, sous l'empreinte SHA-256 du contenu ;
 *  - analyse antivirus : un fichier menaçant est refusé, un fichier non analysé ne peut pas être publié.
 */
class Televersement
{
    /** Types acceptés : type réel => extension enregistrée. SVG exclu (il peut contenir du script). */
    public const IMAGES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public const DOCUMENTS = ['application/pdf' => 'pdf'];

    public function __construct(private readonly Antivirus $antivirus) {}

    /** @param  'image'|'document'  $nature */
    public function enregistrer(UploadedFile $televerse, Utilisateur $par, string $nature): Fichier
    {
        if (! $televerse->isValid()) {
            throw new RegleMetierException('Le fichier n’a pas été reçu correctement ; réessayez.');
        }
        $types = $nature === 'image' ? self::IMAGES : self::DOCUMENTS;
        // Type réel, lu dans le contenu du fichier (signatures de libmagic), indépendamment du nom et de ce qu'annonce le navigateur.
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($televerse->getRealPath()) ?: 'application/octet-stream';
        if (! isset($types[$type])) {
            $attendus = $nature === 'image' ? 'JPEG, PNG ou WebP' : 'PDF';
            throw new RegleMetierException("Type de fichier refusé : seuls les fichiers $attendus sont acceptés.");
        }
        $maximum = (int) config("makary.televersement.{$nature}_mo");
        if ($televerse->getSize() > $maximum * 1024 * 1024) {
            throw new RegleMetierException("Fichier trop lourd : $maximum Mo au plus.");
        }

        $contenu = $nature === 'image' ? $this->recompresser($televerse->getRealPath(), $type) : (string) file_get_contents($televerse->getRealPath());
        if ($nature === 'document' && ! str_starts_with($contenu, '%PDF-')) {
            throw new RegleMetierException('Ce fichier n’est pas un PDF valide.');
        }

        $empreinte = hash('sha256', $contenu);
        $chemin = 'televersements/'.now()->format('Y/m').'/'.$empreinte.'.'.$types[$type];
        $existant = Fichier::where('empreinte_sha256', $empreinte)->where('type_mime', $type)->first();
        if ($existant) {
            return $existant;   // même contenu déjà déposé : il n'est pas dupliqué
        }
        Storage::disk('local')->put($chemin, $contenu);

        $analyse = $this->antivirus->analyser(Storage::disk('local')->path($chemin));
        // En hébergement mutualisé sans ClamAV (MAKARY_ANTIVIRUS_REQUIS=false) : les images recompressées
        // et documents validés sont considérés comme sains sans bloquer la publication.
        if ($analyse === null && ! config('makary.antivirus.requis', true)) {
            $analyse = true;
        }
        if ($analyse === false) {
            Storage::disk('local')->delete($chemin);
            Journal::consigner('fichier.menace', null, ['empreinte_sha256' => $empreinte, 'nom' => $this->nomOriginal($televerse)], $par->id);
            throw new RegleMetierException('Le fichier a été refusé par l’analyse antivirus.');
        }

        return Contexte::enTantQue($par, fn () => Fichier::create([
            'chemin_stockage' => $chemin,
            'nom_original' => $this->nomOriginal($televerse),
            'type_mime' => $type,
            'taille_octets' => strlen($contenu),
            'empreinte_sha256' => $empreinte,
            'prive' => true,
            'analyse_ok' => $analyse,
            'televerse_par' => $par->id,
        ]));
    }

    /** Analyse les fichiers qui ne l'ont pas encore été ; renvoie [sains, menaçants, non analysés]. */
    public function analyserEnAttente(bool $forcerSansAntivirus = false): array
    {
        $bilan = [0, 0, 0];
        foreach (Fichier::whereNull('analyse_ok')->get() as $fichier) {
            $resultat = $this->antivirus->analyser(Storage::disk('local')->path($fichier->chemin_stockage));
            if ($resultat === null && ($forcerSansAntivirus || ! config('makary.antivirus.requis', true))) {
                $resultat = true;
            }
            if ($resultat !== null) {
                $fichier->forceFill(['analyse_ok' => $resultat])->save();
                if (! $resultat) {
                    Journal::consigner('fichier.menace', null, ['fichier_id' => $fichier->id]);
                }
            }
            $bilan[$resultat === true ? 0 : ($resultat === false ? 1 : 2)]++;
        }

        return $bilan;
    }

    /** Nom affiché au téléchargement : sans chemin ni caractère de contrôle. */
    private function nomOriginal(UploadedFile $televerse): string
    {
        $nom = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename((string) $televerse->getClientOriginalName())) ?: 'fichier';

        return mb_substr($nom, 0, 255);
    }

    /** Recompresse l'image : nouvelles données de pixels, sans métadonnées ; orientation EXIF appliquée ; côté le plus long borné. */
    private function recompresser(string $chemin, string $type): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($chemin));
        if (! $image instanceof GdImage) {
            throw new RegleMetierException('Image illisible ou endommagée.');
        }
        if ($type === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($chemin)['Orientation'] ?? 1);
            $image = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
        }
        $cote = (int) config('makary.televersement.image_cote_max');
        if (max(imagesx($image), imagesy($image)) > $cote) {
            $image = imagescale($image, imagesx($image) >= imagesy($image) ? $cote : (int) round(imagesx($image) * $cote / imagesy($image)));
        }
        imagesavealpha($image, true);

        ob_start();
        match ($type) {
            'image/jpeg' => imagejpeg($image, null, 85),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 85),
        };

        return (string) ob_get_clean();
    }
}

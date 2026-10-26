<?php

namespace App\Models;

use App\Domaine\Enums\DroitDiffusion;
use App\Domaine\Enums\EtatEditorial;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\EstPubliable;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Document du centre documentaire (M07). Publié seulement si sa diffusion publique est accordée
 * par le référent juridique ; un document retiré est archivé, jamais détruit (E-14).
 */
class Document extends Model implements Publiable
{
    use EstPubliable;

    public const TYPE_OBJET = TypeObjet::Document;

    protected $table = 'document';

    public $timestamps = false;

    /** Diffusion et archivage relèvent de services dédiés (permissions document.valider_diffusion et document.archiver). */
    protected $guarded = ['id', 'etat', 'diffusion', 'justificatif_diffusion', 'archive'];

    protected function casts(): array
    {
        return [
            'date_document' => 'date',
            'diffusion' => DroitDiffusion::class,
            'archive' => 'boolean',
            'etat' => EtatEditorial::class,
        ];
    }

    /** @return BelongsTo<CategorieDocument, $this> */
    public function categorieDocument(): BelongsTo
    {
        return $this->belongsTo(CategorieDocument::class, 'categorie');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderBy('numero');
    }

    /** @return HasOne<DocumentVersion, $this> */
    public function derniereVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'document_id')->latestOfMany('numero');
    }

    public function perimetres(): array
    {
        return ['M07'];
    }

    public function instantanePublic(): array
    {
        $version = $this->derniereVersion;

        return [
            'slug' => $this->slug,
            'titre' => $this->titre,
            'categorie' => ['code' => $this->categorie, 'libelle' => $this->categorieDocument->libelle],
            'resume' => $this->resume,
            'date_document' => $this->date_document->toDateString(),
            'reference_officielle' => $this->reference_officielle,
            'version' => $version ? [
                'numero' => $version->numero,
                'fichier' => [
                    'id' => $version->fichier->id,
                    'nom' => $version->fichier->nom_original,
                    'type_mime' => $version->fichier->type_mime,
                    'taille_octets' => $version->fichier->taille_octets,
                    'empreinte_sha256' => $version->fichier->empreinte_sha256,
                ],
            ] : null,
        ];
    }

    public function empechementPublication(): ?string
    {
        $version = $this->derniereVersion;
        $analyseOk = $version?->fichier?->analyse_ok === true
            || (! config('makary.antivirus.requis', true) && $version?->fichier?->type_mime === 'application/pdf');

        return match (true) {
            $this->archive => 'Le document est archivé.',
            $this->diffusion !== DroitDiffusion::Public => 'La diffusion publique du document doit être accordée par le référent juridique.',
            $version === null => "Le document n'a aucune version.",
            ! $analyseOk => "Le fichier de la dernière version n'a pas été analysé.",
            default => null,
        };
    }
}

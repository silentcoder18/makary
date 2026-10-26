<?php

namespace App\Models;

use App\Domaine\Enums\DroitUsage;
use App\Domaine\Enums\TypeObjet;
use App\Domaine\EstPubliable;
use App\Domaine\Publiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/** Photo ou illustration : auteur, date, lieu et droit d'usage obligatoires (E-12). */
class Media extends Model implements Publiable
{
    use EstPubliable;

    public const TYPE_OBJET = TypeObjet::Media;

    protected $table = 'media';

    public $timestamps = false;

    /** Le droit d'usage n'est confirmé que par le service des droits (permission media.valider_droits). */
    protected $guarded = ['id', 'droit', 'justificatif_id'];

    protected function casts(): array
    {
        return [
            'date_prise' => 'date',
            'droit' => DroitUsage::class,
        ];
    }

    /** @return BelongsTo<Fichier, $this> */
    public function fichier(): BelongsTo
    {
        return $this->belongsTo(Fichier::class, 'fichier_id');
    }

    /** @return BelongsTo<Source, $this> */
    public function justificatif(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'justificatif_id');
    }

    /** La table « media » n'a pas d'état éditorial : un média est publié ou non. */
    protected function suitUnEtatEditorial(): bool
    {
        return false;
    }

    public function droitVerifie(): bool
    {
        return $this->droit !== DroitUsage::AVerifier;
    }

    /**
     * Patrimoine (M08), et tout module où le média est employé : le validateur de ce module peut en confirmer les droits.
     */
    public function perimetres(): array
    {
        $modules = DB::table('contenu')->where('image_id', $this->id)->distinct()->pluck('module')->all();
        if (DB::table('projet_media')->where('media_id', $this->id)->exists()) {
            $modules[] = 'M03';
        }
        if (DB::table('elu')->where('photo_id', $this->id)->exists()) {
            $modules[] = 'M02';
        }
        if (DB::table('partenaire')->where('logo_id', $this->id)->exists()) {
            $modules[] = 'M05';
        }

        return array_values(array_unique(['M08', ...$modules]));
    }

    public function instantanePublic(): array
    {
        return [
            'titre' => $this->titre,
            'texte_alternatif' => $this->texte_alternatif,
            'auteur' => $this->auteur,
            'date_prise' => $this->date_prise?->toDateString(),
            'lieu' => $this->lieu,
            'droit' => $this->droit->value,
            'fichier' => ['id' => $this->fichier->id, 'type_mime' => $this->fichier->type_mime, 'empreinte_sha256' => $this->fichier->empreinte_sha256],
        ];
    }

    public function empechementPublication(): ?string
    {
        $analyseOk = $this->fichier?->analyse_ok === true
            || (! config('makary.antivirus.requis', true) && in_array($this->fichier?->type_mime, ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true));

        return match (true) {
            ! $this->droitVerifie() => "Le droit d'usage du média « {$this->titre} » n'est pas vérifié.",
            ! $analyseOk => "Le fichier du média « {$this->titre} » n'a pas été analysé.",
            default => null,
        };
    }

    /** Sous-ensemble public d'un média cité dans un autre instantané, ou null si ses droits ne sont pas vérifiés. */
    public static function versionPublique(?self $media): ?array
    {
        return $media && $media->empechementPublication() === null ? $media->instantanePublic() : null;
    }
}

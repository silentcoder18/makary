<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Version d'un document : chaque version garde son fichier. */
class DocumentVersion extends Model
{
    protected $table = 'document_version';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'cree_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** @return BelongsTo<Fichier, $this> */
    public function fichier(): BelongsTo
    {
        return $this->belongsTo(Fichier::class, 'fichier_id');
    }

    /** Agent qui a déposé la version. @return BelongsTo<Utilisateur, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'cree_par');
    }
}

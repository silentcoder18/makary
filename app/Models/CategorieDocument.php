<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catégorie du centre documentaire (M07). */
class CategorieDocument extends Model
{
    protected $table = 'categorie_document';

    public $timestamps = false;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}

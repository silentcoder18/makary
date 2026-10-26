<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Rôle du §7.1 (référentiel figé, généré depuis specs/securite/permissions.yaml). */
class Role extends Model
{
    protected $table = 'role';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['mfa_requise' => 'boolean'];
    }
}

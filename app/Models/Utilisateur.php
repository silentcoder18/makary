<?php

namespace App\Models;

use Database\Factories\UtilisateurFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Compte nominatif d'un agent de la Commune (§7.1). Aucun compte partagé.
 *
 * @property int $id
 * @property string $nom_affiche
 * @property string $courriel
 * @property bool $mfa_active
 * @property bool $actif
 */
#[Fillable(['nom_affiche', 'courriel', 'fonction', 'mot_de_passe', 'actif'])]
#[Hidden(['mot_de_passe', 'mfa_secret'])]
class Utilisateur extends Authenticatable
{
    /** @use HasFactory<UtilisateurFactory> */
    use HasFactory;

    protected $table = 'utilisateur';

    /** Le mot de passe est stocké dans la colonne « mot_de_passe ». */
    protected $authPasswordName = 'mot_de_passe';

    /** Pas de « se souvenir de moi » sur le back-office : aucun jeton persistant. */
    protected $rememberTokenName = '';

    /** Valeurs par défaut, identiques à celles de la base, disponibles dès la création. */
    protected $attributes = [
        'actif' => true,
        'mfa_active' => false,
    ];

    protected function casts(): array
    {
        return [
            'mot_de_passe' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_active' => 'boolean',
            'actif' => 'boolean',
            'desactive_le' => 'datetime',
            'derniere_connexion' => 'datetime',
            'conserver_jusqu_au' => 'date',
        ];
    }

    /** L'adresse est enregistrée en minuscules : la connexion ne dépend pas de la casse saisie. */
    protected function courriel(): Attribute
    {
        return Attribute::make(set: fn (string $valeur) => mb_strtolower(trim($valeur)));
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'utilisateur_role', 'utilisateur_id', 'role_code')
            ->withPivot(['perimetre', 'attribue_le']);
    }

    /** Vrai si l'un des rôles du compte exige la double authentification (§7). Désactivée si makary.mfa_active est faux. */
    public function exigeMfa(): bool
    {
        if (! config('makary.mfa_active', false)) {
            return false;
        }

        return $this->roles->contains(fn (Role $role) => $role->mfa_requise);
    }

    /**
     * Rôles et périmètres du compte, sous la forme [code du rôle => liste des périmètres].
     *
     * @return array<string, list<string>>
     */
    public function rolesAvecPerimetres(): array
    {
        return $this->roles->mapWithKeys(fn (Role $role) => [
            $role->code => json_decode($role->pivot->perimetre ?? '[]', true) ?: [],
        ])->all();
    }

    /**
     * Attribue un rôle avec son périmètre.
     *
     * @param  list<string>  $perimetre
     */
    public function attribuerRole(string $code, array $perimetre = [], ?Utilisateur $par = null): void
    {
        $this->roles()->syncWithoutDetaching([$code => [
            'perimetre' => json_encode(array_values($perimetre)),
            'attribue_par' => $par?->id,
        ]]);
        $this->unsetRelation('roles');
    }
}

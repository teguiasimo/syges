<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Scopes\EtablissementScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modèle utilisateur représentant les comptes d'accès à l'application SYGES.
 * Intègre la gestion des rôles et permissions (Spatie) et l'accès multi-établissements.
 *
 * @property int $id
 * @property int|null $etablissement_id
 * @property string|null $login
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Etablissement|null $etablissement
 * @property-read Personnel|null $personnel
 *
 * @mixin HasRoles
 *
 * @method bool hasRole(string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles, ?string $guard = null)
 * @method bool can(string $ability, array|mixed $arguments = [])
 */
#[Fillable(['name', 'email', 'password', 'login', 'is_active', 'etablissement_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Actions automatiques au cycle de vie du modèle User.
     * Garantit l'étanchéité multi-tenant : si un utilisateur n'est pas Super-Admin
     * et n'a pas d'établissement direct, synchronise son établissement depuis sa fiche Personnel.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->etablissement_id === null && ! $user->isSuperAdmin()) {
                $personnelEtabId = Personnel::withoutGlobalScope(EtablissementScope::class)
                    ->where('user_id', $user->id)
                    ->value('etablissement_id');

                if ($personnelEtabId) {
                    $user->etablissement_id = (int) $personnelEtabId;
                }
            }
        });
    }

    /**
     * Obtenir les attributs convertis dans des types natifs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'etablissement_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Vérifie si le compte utilisateur est actuellement actif.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Établissement / campus de rattachement principal du compte utilisateur.
     * Vaut null pour les comptes de type Super Administrateur (accès transversal).
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    /**
     * Fiche de personnel associée à cet utilisateur (si l'utilisateur est un employé de l'établissement).
     */
    public function personnel(): HasOne
    {
        return $this->hasOne(Personnel::class, 'user_id');
    }

    /**
     * Détermine si l'utilisateur possède les prérogatives de Super-Administrateur transversal.
     * Vérifie le rôle `super-admin`, le login racine `superadmin` ou l'email racine historique.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin')
            || $this->login === 'superadmin'
            || $this->email === 'admin@gmail.com';
    }

    /**
     * Accesseur magique pour accéder à $user->is_super_admin sous forme de propriété.
     */
    public function getIsSuperAdminAttribute(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Récupère l'identifiant d'établissement effectif du compte.
     * Donne priorité à `etablissement_id` direct sur le User, puis à sa fiche Personnel.
     * Utilise withoutGlobalScope pour éviter toute récursion infinie lors de l'application de EtablissementScope.
     */
    public function getEffectiveEtablissementId(): ?int
    {
        if ($this->etablissement_id !== null) {
            return $this->etablissement_id;
        }

        if ($this->relationLoaded('personnel')) {
            return $this->personnel?->etablissement_id;
        }

        return Personnel::withoutGlobalScope(EtablissementScope::class)
            ->where('user_id', $this->id)
            ->value('etablissement_id');
    }
}

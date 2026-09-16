<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant un Département Pédagogique (Module 8.2 Gesco).
 * 
 * Regroupe les enseignants d'une même discipline ou famille de matières
 * (ex: Département de Mathématiques, Département d'Informatique, etc.)
 * et peut être dirigé par un animateur pédagogique / chef de département.
 *
 * @property int $id
 * @property int $institution_id
 * @property string $nom
 * @property string|null $code
 * @property string|null $description
 * @property int|null $chef_departement_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Departement extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'institution_id',
        'nom',
        'code',
        'description',
        'chef_departement_id',
        'is_active',
    ];

    /**
     * Les attributs typés nativement.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Institution globale de rattachement.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    /**
     * Animateur pédagogique ou chef de département.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function chefDepartement(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'chef_departement_id');
    }

    /**
     * Liste de l'ensemble des membres du personnel rattachés à ce département.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class, 'departement_id');
    }

    /**
     * Liste des enseignants rattachés à ce département.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function enseignants(): HasMany
    {
        return $this->hasMany(Personnel::class, 'departement_id')->where('is_enseignant', true);
    }
}

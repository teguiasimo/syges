<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant un Niveau d'étude (palier pédagogique).
 *
 * Exemples : 6ème, 5ème, 4ème, 3ème, Seconde, Première, Terminale, Class 1, Form 5.
 * Porte le numéro d'ordre utilisé pour déterminer la progression scolaire et le passage de classe.
 *
 * @property int $id
 * @property int $cycle_id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property int $ordre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Cycle $cycle
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classe> $classes
 */
class Niveau extends Model
{
    use HasFactory;

    /**
     * Nom explicite de la table plurielle en français.
     *
     * @var string
     */
    protected $table = 'niveaux';

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cycle_id',
        'code',
        'nom',
        'nom_en',
        'ordre',
    ];

    /**
     * Les attributs à convertir en types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordre' => 'integer',
    ];

    /**
     * Relation plusieurs-à-un vers le Cycle parent.
     *
     * @return BelongsTo
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    /**
     * Relation un-à-plusieurs vers les Classes de ce niveau.
     *
     * @return HasMany
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    /**
     * Relation un-à-plusieurs vers les coefficients des matières configurées pour ce niveau.
     *
     * @return HasMany
     */
    public function matiereNiveaux(): HasMany
    {
        return $this->hasMany(MatiereNiveau::class, 'niveau_id');
    }
}

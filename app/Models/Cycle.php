<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant un Cycle d'enseignement.
 *
 * Découpe une section en grandes étapes d'apprentissage (ex: Premier Cycle, Second Cycle,
 * Cycle Primaire) et prépare un diplôme officiel (BEPC, BACC, GCE O-Level...).
 *
 * @property int $id
 * @property int $section_id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property string|null $diplome_fin_cycle
 * @property int $ordre
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $etablissement_id
 * @property-read \App\Models\Etablissement|null $etablissement
 * @property-read \App\Models\Section $section
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Niveau> $niveaux
 */
class Cycle extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'section_id',
        'code',
        'nom',
        'nom_en',
        'diplome_fin_cycle',
        'ordre',
    ];

    /**
     * Les attributs à convertir en types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'etablissement_id' => 'integer',
        'ordre' => 'integer',
    ];

    /**
     * Événements du cycle de vie du modèle.
     */
    protected static function booted(): void
    {
        static::creating(function (Cycle $cycle) {
            // Renseigne automatiquement l'établissement depuis la section si non spécifié
            if (empty($cycle->etablissement_id) && $cycle->section_id) {
                $cycle->etablissement_id = $cycle->section?->etablissement_id 
                    ?? Section::find($cycle->section_id)?->etablissement_id;
            }
        });
    }

    /**
     * Relation plusieurs-à-un vers l'Établissement scolaire parent.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation plusieurs-à-un vers la Section parente.
     *
     * @return BelongsTo
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Relation un-à-plusieurs vers les Niveaux d'étude.
     *
     * @return HasMany
     */
    public function niveaux(): HasMany
    {
        return $this->hasMany(Niveau::class)->orderBy('ordre');
    }
}

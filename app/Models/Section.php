<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Section éducative (sous-système linguistique ou technique).
 *
 * Exemples : Section Francophone (fr), Section Anglophone (en), Section Technique.
 *
 * @property int $id
 * @property int $etablissement_id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property string $langue
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement $etablissement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Cycle> $cycles
 */
class Section extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'code',
        'nom',
        'nom_en',
        'langue',
    ];

    /**
     * Relation plusieurs-à-un vers l'Établissement parent.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation un-à-plusieurs vers les cycles pédagogiques.
     *
     * @return HasMany
     */
    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class)->orderBy('ordre');
    }
}

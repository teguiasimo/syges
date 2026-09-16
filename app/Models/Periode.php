<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Période scolaire (Trimestre ou Semestre).
 *
 * Découpage de l'année scolaire en périodes d'évaluation intermédiaires (Module 3.2 Gesco).
 *
 * @property int $id
 * @property int $annee_scolaire_id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property int $ordre
 * @property \Illuminate\Support\Carbon|null $date_debut
 * @property \Illuminate\Support\Carbon|null $date_fin
 * @property string $statut
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AnneeScolaire $anneeScolaire
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Evaluation> $evaluations
 */
class Periode extends Model
{
    use HasFactory;

    /**
     * Nom explicite de la table.
     *
     * @var string
     */
    protected $table = 'periodes';

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'annee_scolaire_id',
        'code',
        'nom',
        'nom_en',
        'ordre',
        'date_debut',
        'date_fin',
        'statut',
    ];

    /**
     * Typages des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordre' => 'integer',
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    /**
     * Relation plusieurs-à-un vers l'Année Scolaire parente.
     *
     * @return BelongsTo
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    /**
     * Relation un-à-plusieurs vers les Évaluations (Séquences).
     *
     * @return HasMany
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'periode_id')->orderBy('ordre');
    }

    /**
     * Vérifie si la somme des pondérations des évaluations est égale à 100%.
     *
     * @return bool
     */
    public function isPonderationComplete(): bool
    {
        $sum = $this->evaluations()->sum('ponderation');
        return abs($sum - 100.00) < 0.01;
    }
}

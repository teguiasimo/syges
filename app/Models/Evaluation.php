<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Évaluation / Séquence d'un trimestre.
 *
 * Composante d'un trimestre destinée à la collecte des notes et au calcul des résultats (Module 3.3 Gesco).
 *
 * @property int $id
 * @property int $periode_id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property int $ordre
 * @property float $ponderation
 * @property \Illuminate\Support\Carbon|null $date_debut
 * @property \Illuminate\Support\Carbon|null $date_fin
 * @property \Illuminate\Support\Carbon|null $date_limite_saisie
 * @property bool $is_locked
 * @property string $statut
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Periode $periode
 */
class Evaluation extends Model
{
    use HasFactory;

    /**
     * Nom explicite de la table.
     *
     * @var string
     */
    protected $table = 'evaluations';

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'periode_id',
        'code',
        'nom',
        'nom_en',
        'ordre',
        'ponderation',
        'date_debut',
        'date_fin',
        'date_limite_saisie',
        'is_locked',
        'statut',
    ];

    /**
     * Typages des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordre' => 'integer',
        'ponderation' => 'float',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_limite_saisie' => 'date',
        'is_locked' => 'boolean',
    ];

    /**
     * Relation plusieurs-à-un vers la Période (Trimestre) parente.
     *
     * @return BelongsTo
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    /**
     * Indique si la saisie des notes pour cette évaluation est actuellement verrouillée.
     * Soit par verrou manuel, soit par dépassement de la date limite de saisie.
     *
     * @return bool
     */
    public function isLockedForGrading(): bool
    {
        if ($this->is_locked) {
            return true;
        }

        if ($this->date_limite_saisie && now()->endOfDay()->isAfter($this->date_limite_saisie->endOfDay())) {
            return true;
        }

        return false;
    }

    /**
     * Relation vers les notes enregistrées pour cette évaluation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Note>
     */
    public function notes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Note::class, 'evaluation_id');
    }
}

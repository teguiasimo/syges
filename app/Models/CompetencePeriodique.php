<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Compétence Périodique officielle du Programme (Phase 3 APC).
 *
 * Définit, par classe/niveau, matière et séquence ou trimestre, l'intitulé de la compétence
 * concrète évaluée selon le programme officiel (ex: « Résoudre un problème de fractions »).
 *
 * @property int $id
 * @property int $etablissement_id
 * @property int $classe_id
 * @property int $matiere_id
 * @property int|null $periode_id
 * @property int|null $evaluation_id
 * @property string $libelle_competence
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement $etablissement
 * @property-read \App\Models\Classe $classe
 * @property-read \App\Models\Matiere $matiere
 * @property-read \App\Models\Periode|null $periode
 * @property-read \App\Models\Evaluation|null $evaluation
 */
class CompetencePeriodique extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom explicite de la table.
     *
     * @var string
     */
    protected $table = 'competences_periodiques';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'classe_id',
        'matiere_id',
        'periode_id',
        'evaluation_id',
        'libelle_competence',
        'description',
        'is_active',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relation vers l'établissement de rattachement.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation vers la classe cible.
     *
     * @return BelongsTo
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Relation vers la matière évaluée.
     *
     * @return BelongsTo
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    /**
     * Relation vers la période (Trimestre).
     *
     * @return BelongsTo
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /**
     * Relation vers l'évaluation spécifique (Séquence).
     *
     * @return BelongsTo
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}

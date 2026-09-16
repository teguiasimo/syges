<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant la configuration d'un Coefficient par Niveau et Série.
 *
 * Association dynamique d'une matière du catalogue à un couple (Niveau + Série/Filière)
 * pour lui attribuer son poids académique, son bloc d'appartenance et ses règles de calcul (Module 4.3 Gesco).
 *
 * @property int $id
 * @property int $niveau_id
 * @property int $matiere_id
 * @property int $groupe_matiere_id
 * @property string|null $serie
 * @property float $coefficient
 * @property float $note_max
 * @property float|null $note_eliminatoire
 * @property bool $is_facultative
 * @property bool $is_comptabilisee
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Niveau $niveau
 * @property-read \App\Models\Matiere $matiere
 * @property-read \App\Models\GroupeMatiere $groupeMatiere
 */
class MatiereNiveau extends Model
{
    use HasFactory;

    /**
     * Nom explicite de la table.
     *
     * @var string
     */
    protected $table = 'matiere_niveaux';

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'niveau_id',
        'matiere_id',
        'groupe_matiere_id',
        'serie',
        'coefficient',
        'note_max',
        'note_eliminatoire',
        'is_facultative',
        'is_comptabilisee',
    ];

    /**
     * Typages des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'coefficient' => 'float',
        'note_max' => 'float',
        'note_eliminatoire' => 'float',
        'is_facultative' => 'boolean',
        'is_comptabilisee' => 'boolean',
    ];

    /**
     * Relation plusieurs-à-un vers le Niveau académique.
     *
     * @return BelongsTo
     */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }

    /**
     * Relation plusieurs-à-un vers la Matière du catalogue.
     *
     * @return BelongsTo
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class, 'matiere_id');
    }

    /**
     * Relation plusieurs-à-un vers le Groupe de matières (Bloc).
     *
     * @return BelongsTo
     */
    public function groupeMatiere(): BelongsTo
    {
        return $this->belongsTo(GroupeMatiere::class, 'groupe_matiere_id');
    }
}

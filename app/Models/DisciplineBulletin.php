<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant la synthèse disciplinaire d'un élève pour une période d'évaluation (Trimestre / Semestre).
 *
 * Imprimée sur le bulletin scolaire officiel selon les directives MINESEC/MINEDUB :
 * - Heures d'absences justifiées et non justifiées.
 * - Retards, consignes (heures de retenue).
 * - Sanctions de conduite : Avertissements, Blâmes, Jours d'exclusion.
 * - Décision / Avis du conseil de classe.
 *
 * @property int $id
 * @property int $inscription_id
 * @property int $periode_id
 * @property int $absences_justifiees
 * @property int $absences_non_justifiees
 * @property int $retards
 * @property int $consignes
 * @property int $avertissements_conduite
 * @property int $blames_conduite
 * @property int $exclusions_jours
 * @property string|null $decision_conseil
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DisciplineBulletin extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'disciplines_bulletins';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inscription_id',
        'periode_id',
        'absences_justifiees',
        'absences_non_justifiees',
        'retards',
        'consignes',
        'avertissements_conduite',
        'blames_conduite',
        'exclusions_jours',
        'decision_conseil',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'absences_justifiees' => 'integer',
        'absences_non_justifiees' => 'integer',
        'retards' => 'integer',
        'consignes' => 'integer',
        'avertissements_conduite' => 'integer',
        'blames_conduite' => 'integer',
        'exclusions_jours' => 'integer',
    ];

    /**
     * Relation vers l'inscription de l'élève.
     *
     * @return BelongsTo<Inscription, DisciplineBulletin>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    /**
     * Relation vers la période académique (Trimestre).
     *
     * @return BelongsTo<Periode, DisciplineBulletin>
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }
}

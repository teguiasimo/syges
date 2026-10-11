<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant la comparution et le dossier d'un élève devant le Conseil de Discipline (Phase 5).
 *
 * @property int $id
 * @property int $conseil_discipline_id
 * @property int $inscription_id
 * @property int $eleve_id
 * @property string $motifs_renvoi
 * @property string|null $plaidoirie_eleve
 * @property string $decision_finale
 * @property int|null $nombre_jours_exclusion
 * @property int|null $nombre_heures_consigne
 * @property \Illuminate\Support\Carbon|null $date_convocation
 * @property bool $parent_present
 * @property bool $is_convocation_envoyee
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ConseilDiscipline $conseilDiscipline
 * @property-read \App\Models\Inscription $inscription
 * @property-read \App\Models\Eleve $eleve
 */
class DossierConseilDiscipline extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'dossiers_conseil_discipline';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'conseil_discipline_id',
        'inscription_id',
        'eleve_id',
        'motifs_renvoi',
        'plaidoirie_eleve',
        'decision_finale',
        'nombre_jours_exclusion',
        'nombre_heures_consigne',
        'date_convocation',
        'parent_present',
        'is_convocation_envoyee',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_convocation' => 'date',
        'parent_present' => 'boolean',
        'is_convocation_envoyee' => 'boolean',
        'nombre_jours_exclusion' => 'integer',
        'nombre_heures_consigne' => 'integer',
    ];

    /**
     * Relation vers la session de Conseil de Discipline.
     *
     * @return BelongsTo
     */
    public function conseilDiscipline(): BelongsTo
    {
        return $this->belongsTo(ConseilDiscipline::class, 'conseil_discipline_id');
    }

    /**
     * Relation vers l'inscription de l'élève.
     *
     * @return BelongsTo
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /**
     * Relation directe vers le profil élève.
     *
     * @return BelongsTo
     */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}

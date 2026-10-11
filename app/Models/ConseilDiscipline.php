<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une session formelle de Conseil de Discipline (Phase 5).
 *
 * Conforme aux directives officielles MINESEC régissant les conseils d'établissement au Cameroun.
 *
 * @property int $id
 * @property int $etablissement_id
 * @property int $annee_scolaire_id
 * @property string $numero_session
 * @property \Illuminate\Support\Carbon $date_conseil
 * @property string $lieu
 * @property string $president_seance
 * @property string $rapporteur
 * @property string|null $membres_presents
 * @property string $statut
 * @property string|null $observations_generales
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement $etablissement
 * @property-read \App\Models\AnneeScolaire $anneeScolaire
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DossierConseilDiscipline> $dossiers
 */
class ConseilDiscipline extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'conseils_discipline';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'annee_scolaire_id',
        'numero_session',
        'date_conseil',
        'lieu',
        'president_seance',
        'rapporteur',
        'membres_presents',
        'statut',
        'observations_generales',
        'created_by',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_conseil' => 'datetime',
    ];

    /**
     * Relation vers l'établissement.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation vers l'année scolaire.
     *
     * @return BelongsTo
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /**
     * Relation vers les dossiers d'élèves traduits lors de cette session.
     *
     * @return HasMany
     */
    public function dossiers(): HasMany
    {
        return $this->hasMany(DossierConseilDiscipline::class, 'conseil_discipline_id');
    }
}

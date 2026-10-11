<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Dérogation ou Dispense Officielle pour un élève (Phase 4).
 *
 * Conforme aux directives MINESEC (Cameroun) pour l'inaptitude physique (EPS),
 * les dérogations de langues vivantes et les autorisations médicales.
 *
 * @property int $id
 * @property int $etablissement_id
 * @property int $annee_scolaire_id
 * @property int $inscription_id
 * @property int $eleve_id
 * @property int $matiere_id
 * @property int|null $periode_id
 * @property string $type_dispense
 * @property string $motif
 * @property string|null $reference_certificat
 * @property string|null $medecin_ou_autorite
 * @property \Illuminate\Support\Carbon|null $date_debut
 * @property \Illuminate\Support\Carbon|null $date_fin
 * @property bool $neutraliser_coefficient
 * @property string $statut
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement $etablissement
 * @property-read \App\Models\AnneeScolaire $anneeScolaire
 * @property-read \App\Models\Inscription $inscription
 * @property-read \App\Models\Eleve $eleve
 * @property-read \App\Models\Matiere $matiere
 * @property-read \App\Models\Periode|null $periode
 * @property-read \App\Models\User|null $auteur
 */
class DispenseEleve extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'dispenses_eleves';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'annee_scolaire_id',
        'inscription_id',
        'eleve_id',
        'matiere_id',
        'periode_id',
        'type_dispense',
        'motif',
        'reference_certificat',
        'medecin_ou_autorite',
        'date_debut',
        'date_fin',
        'neutraliser_coefficient',
        'statut',
        'created_by',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'neutraliser_coefficient' => 'boolean',
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

    /**
     * Relation vers la matière faisant l'objet de la dispense (ex: EPS).
     *
     * @return BelongsTo
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    /**
     * Relation vers la période trimestrielle concernée (null si annuelle).
     *
     * @return BelongsTo
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /**
     * Relation vers l'utilisateur ayant saisi la dispense.
     *
     * @return BelongsTo
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Portée pour filtrer les dispenses validées et actives.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValidees($query)
    {
        return $query->where('statut', 'validee');
    }
}

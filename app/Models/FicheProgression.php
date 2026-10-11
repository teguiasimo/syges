<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle FicheProgression - Cahier de Texte & Suivi de la Progression Pédagogique.
 * 
 * Conforme au Manuel Aurore (Section 5.1.8) et au Cahier des Charges Gesco V1.1.
 * Permet de consigner chaque séance de cours dispensée (date, semaine, chapitre, leçon,
 * heures prévues vs réalisées, devoirs et statut) et d'assurer le contrôle hiérarchique
 * (visa et appréciations du Censeur ou Directeur des Études).
 *
 * @property int $id
 * @property int $etablissement_id
 * @property int $annee_scolaire_id
 * @property int $attribution_id
 * @property int|null $periode_id
 * @property \Illuminate\Support\Carbon $date_seance
 * @property int|null $semaine_numero
 * @property string $chapitre
 * @property string $titre_lecon
 * @property string|null $resume_activite
 * @property string|null $travail_a_faire
 * @property float $heures_prevues
 * @property float $heures_effectuees
 * @property string $statut (fait, en_cours, reporte)
 * @property string|null $motif_report
 * @property bool $visa_censeur
 * @property \Illuminate\Support\Carbon|null $date_visa
 * @property int|null $vise_par_id
 * @property string|null $observations_censeur
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FicheProgression extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'fiches_progression';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'annee_scolaire_id',
        'attribution_id',
        'periode_id',
        'date_seance',
        'semaine_numero',
        'chapitre',
        'titre_lecon',
        'resume_activite',
        'travail_a_faire',
        'heures_prevues',
        'heures_effectuees',
        'statut',
        'motif_report',
        'visa_censeur',
        'date_visa',
        'vise_par_id',
        'observations_censeur',
    ];

    /**
     * Les attributs typés nativement.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_seance' => 'date',
        'date_visa' => 'datetime',
        'heures_prevues' => 'decimal:2',
        'heures_effectuees' => 'decimal:2',
        'visa_censeur' => 'boolean',
        'semaine_numero' => 'integer',
    ];

    /**
     * Relation avec l'année scolaire de rattachement.
     *
     * @return BelongsTo
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    /**
     * Relation avec l'attribution pédagogique (Enseignant - Classe - Matière).
     *
     * @return BelongsTo
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(Attribution::class, 'attribution_id');
    }

    /**
     * Relation avec la période d'évaluation (Trimestre / Semestre).
     *
     * @return BelongsTo
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    /**
     * Relation avec l'utilisateur ayant apposé son visa hiérarchique.
     *
     * @return BelongsTo
     */
    public function visePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vise_par_id');
    }

    /**
     * Scope pour filtrer par attribution spécifique.
     *
     * @param Builder $query
     * @param int $attributionId
     * @return Builder
     */
    public function scopePourAttribution(Builder $query, int $attributionId): Builder
    {
        return $query->where('attribution_id', $attributionId);
    }

    /**
     * Scope pour filtrer les séances visées par l'administration.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeVisees(Builder $query): Builder
    {
        return $query->where('visa_censeur', true);
    }

    /**
     * Scope pour filtrer les séances en attente de visa.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeEnAttenteVisa(Builder $query): Builder
    {
        return $query->where('visa_censeur', false);
    }
}

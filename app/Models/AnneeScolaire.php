<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Année Scolaire (cadre temporel global).
 *
 * Cadre temporel global dans lequel s'inscrivent toutes les opérations
 * d'inscription, de scolarité, d'évaluation et de bilan (Module 3.1 Gesco).
 *
 * @property int $id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property \Illuminate\Support\Carbon $date_debut
 * @property \Illuminate\Support\Carbon $date_fin
 * @property string $statut
 * @property string $mode_calcul_moyenne
 * @property bool $is_current
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Periode> $periodes
 */
class AnneeScolaire extends Model
{
    use HasFactory;

    /**
     * Nom explicite de la table.
     *
     * @var string
     */
    protected $table = 'annees_scolaires';

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'nom',
        'nom_en',
        'date_debut',
        'date_fin',
        'statut',
        'mode_calcul_moyenne',
        'is_current',
    ];

    /**
     * Typages des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * Relation un-à-plusieurs vers les périodes (Trimestres / Semestres).
     *
     * @return HasMany
     */
    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class, 'annee_scolaire_id')->orderBy('ordre');
    }

    /**
     * Relation un-à-plusieurs vers les inscriptions de cette année scolaire.
     *
     * @return HasMany
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'annee_scolaire_id');
    }

    /**
     * Récupère l'année scolaire active courante du système.
     *
     * @return AnneeScolaire|null
     */
    public static function current(): ?self
    {
        return self::where('is_current', true)->first() ?? self::latest('id')->first();
    }

    /**
     * Relation un-à-plusieurs vers les attributions pédagogiques de l'année.
     *
     * @return HasMany
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(Attribution::class, 'annee_scolaire_id');
    }
}

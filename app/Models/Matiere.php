<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Matière / Discipline dans le Catalogue Général.
 *
 * Référentiel des disciplines pouvant être enseignées dans l'ensemble des établissements
 * du groupe scolaire ou propres à un établissement spécifique (Module 4.2 Gesco).
 *
 * @property int $id
 * @property int|null $etablissement_id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property string|null $libelle_bulletin
 * @property string|null $libelle_bulletin_en
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement|null $etablissement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatiereNiveau> $matiereNiveaux
 */
class Matiere extends Model
{
    use HasFactory;

    /**
     * Nom explicite de la table.
     *
     * @var string
     */
    protected $table = 'matieres';

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'code',
        'nom',
        'nom_en',
        'libelle_bulletin',
        'libelle_bulletin_en',
        'is_active',
    ];

    /**
     * Typages des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'etablissement_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relation plusieurs-à-un vers l'Établissement scolaire propriétaire (null si tronc commun).
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    /**
     * Relation un-à-plusieurs vers les coefficients par niveau.
     *
     * @return HasMany
     */
    public function matiereNiveaux(): HasMany
    {
        return $this->hasMany(MatiereNiveau::class, 'matiere_id');
    }

    /**
     * Filtre les matières disponibles pour un établissement scolaire (tronc commun + spécifiques).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Requête Eloquent.
     * @param int|null $etablissementId Identifiant de l'établissement.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAvailableForEtablissement($query, ?int $etablissementId)
    {
        if (! $etablissementId) {
            return $query;
        }

        return $query->where(function ($q) use ($etablissementId) {
            $q->where('etablissement_id', $etablissementId)
                ->orWhereNull('etablissement_id');
        });
    }

    /**
     * Libellé effectif d'affichage sur les bulletins.
     * Si le libellé d'impression n'est pas renseigné, retourne le nom complet (Règle 4.2 Gesco).
     *
     * @param string $locale Code de langue ('fr' ou 'en').
     * @return string
     */
    public function getDisplayName(string $locale = 'fr'): string
    {
        if ($locale === 'en' && ! empty($this->libelle_bulletin_en)) {
            return $this->libelle_bulletin_en;
        }

        if ($locale === 'en' && ! empty($this->nom_en)) {
            return $this->nom_en;
        }

        return ! empty($this->libelle_bulletin) ? $this->libelle_bulletin : $this->nom;
    }

    /**
     * Attributions de cours impliquant cette matière.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(Attribution::class, 'matiere_id');
    }
}

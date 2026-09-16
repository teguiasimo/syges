<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Classe réelle d'élèves.
 *
 * Cellule terminale de la structure pédagogique dans laquelle les élèves sont inscrits.
 * Exemples : 6ème M1, Terminale C, Form 3 Science.
 *
 * @property int $id
 * @property int $niveau_id
 * @property string $code
 * @property string $nom
 * @property string|null $serie
 * @property int $capacite_max
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Niveau $niveau
 */
class Classe extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'niveau_id',
        'code',
        'nom',
        'serie',
        'capacite_max',
        'professeur_principal_id',
        'is_active',
    ];

    /**
     * Les attributs à convertir en types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capacite_max' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relation plusieurs-à-un vers le Niveau parent.
     *
     * @return BelongsTo
     */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /**
     * Relation vers toutes les inscriptions associées à cette classe.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function inscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inscription::class, 'classe_id');
    }

    /**
     * Relation vers tous les élèves inscrits dans cette classe.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function eleves(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'inscriptions', 'classe_id', 'eleve_id')
            ->withPivot(['annee_scolaire_id', 'statut', 'type_inscription', 'is_redoublant', 'date_inscription'])
            ->withTimestamps();
    }

    /**
     * Enseignant désigné Professeur Principal de la classe (Gesco Section 2.7.5).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function professeurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'professeur_principal_id');
    }

    /**
     * Attributions pédagogiques des cours rattachés à cette classe.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attribution::class, 'classe_id');
    }
}

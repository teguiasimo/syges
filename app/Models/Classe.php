<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Initialisation du modèle avec garde-fou strict d'intégrité pédagogique
     * et auto-assignation automatique de l'etablissement_id depuis le niveau parent.
     */
    protected static function booted(): void
    {
        static::saving(function (Classe $classe) {
            if (empty($classe->etablissement_id) && $classe->niveau_id) {
                $niveau = Niveau::find($classe->niveau_id);
                $classe->etablissement_id = $niveau?->etablissement_id ?? $niveau?->cycle?->section?->etablissement_id;
            }
        });

        static::deleting(function (Classe $classe) {
            if (! $classe->canBeDeleted()) {
                throw new \DomainException("La classe « {$classe->nom} » ne peut pas être supprimée car elle contient des données pédagogiques actives.");
            }
        });
    }

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
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
        'etablissement_id' => 'integer',
        'capacite_max' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relation vers l'Établissement scolaire de rattachement.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    /**
     * Scope local pour filtrer les classes appartenant à un établissement précis.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $etablissementId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEtablissement($query, ?int $etablissementId)
    {
        if (! $etablissementId) {
            return $query;
        }

        return $query->where('etablissement_id', $etablissementId);
    }

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
     * @return HasMany
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'classe_id');
    }

    /**
     * Relation vers tous les élèves inscrits dans cette classe.
     *
     * @return BelongsToMany
     */
    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'inscriptions', 'classe_id', 'eleve_id')
            ->withPivot(['annee_scolaire_id', 'statut', 'type_inscription', 'is_redoublant', 'date_inscription'])
            ->withTimestamps();
    }

    /**
     * Enseignant désigné Professeur Principal de la classe (Gesco Section 2.7.5).
     *
     * @return BelongsTo
     */
    public function professeurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'professeur_principal_id');
    }

    /**
     * Attributions pédagogiques des cours rattachés à cette classe (Matière + Enseignant).
     *
     * @return HasMany
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(Attribution::class, 'classe_id');
    }

    /**
     * Séances d'emploi du temps associées à cette classe.
     *
     * @return HasMany
     */
    public function seancesEmploiTemps(): HasMany
    {
        return $this->hasMany(SeanceEmploiTemps::class, 'classe_id');
    }

    /**
     * Délibérations de conseil de classe associées.
     *
     * @return HasMany
     */
    public function deliberations(): HasMany
    {
        return $this->hasMany(DeliberationConseil::class, 'classe_id');
    }

    /**
     * Récupère l'établissement scolaire auquel appartient cette classe.
     *
     * @return Etablissement|null
     */
    public function getEtablissementAttribute(): ?Etablissement
    {
        return $this->niveau?->cycle?->section?->etablissement;
    }

    /**
     * Détermine si la classe peut être supprimée sans compromettre l'intégrité pédagogique.
     * Une classe ne peut être supprimée QUE si elle ne contient aucune attribution de matière/professeur,
     * aucun élève inscrit, aucun cours à l'emploi du temps ni aucune délibération.
     *
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        return empty($this->getDeletionBlockers());
    }

    /**
     * Retourne la liste des éléments bloquant la suppression avec leurs décomptes respectifs.
     *
     * @return array<string, int>
     */
    public function getDeletionBlockers(): array
    {
        $blockers = [];

        $attributionsCount = $this->attributions()->count();
        if ($attributionsCount > 0) {
            $blockers['attributions'] = $attributionsCount;
        }

        $inscriptionsCount = $this->inscriptions()->count();
        if ($inscriptionsCount > 0) {
            $blockers['inscriptions'] = $inscriptionsCount;
        }

        $seancesCount = $this->seancesEmploiTemps()->count();
        if ($seancesCount > 0) {
            $blockers['seances'] = $seancesCount;
        }

        $deliberationsCount = $this->deliberations()->count();
        if ($deliberationsCount > 0) {
            $blockers['deliberations'] = $deliberationsCount;
        }

        return $blockers;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant l'attribution d'un cours (Enseignant <-> Classe <-> Matière).
 * Conforme au Cahier des Charges Gesco V1.1 (Sections 9.1 et 9.2).
 * 
 * Clé de voûte de la répartition pédagogique assurée par le Censeur :
 * - Associe un enseignant à une classe et une discipline pour une année scolaire.
 * - Définit le volume horaire effectif hebdomadaire.
 * - Déclenche le provisioning automatique des droits de saisie des notes (Module 10).
 */
class Attribution extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée au modèle.
     *
     * @var string
     */
    protected $table = 'attributions';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'annee_scolaire_id',
        'personnel_id',
        'classe_id',
        'matiere_id',
        'volume_horaire_reel',
        'is_titulaire',
        'is_active',
    ];

    /**
     * Les attributs typés nativement.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'volume_horaire_reel' => 'decimal:2',
        'is_titulaire' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Année scolaire dans laquelle s'inscrit cette attribution pédagogique.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    /**
     * Enseignant affecté à la charge de ce cours.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    /**
     * Classe bénéficiaire de l'enseignement.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    /**
     * Discipline / Matière enseignée.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class, 'matiere_id');
    }

    /**
     * Scope pour filtrer les attributions d'une année scolaire spécifique.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $anneeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAnnee($query, int $anneeId)
    {
        return $query->where('annee_scolaire_id', $anneeId);
    }

    /**
     * Scope pour filtrer uniquement les attributions actives.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

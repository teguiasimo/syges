<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une séance d'Heure de Consigne / Retenue du Samedi (Phase 5).
 *
 * @property int $id
 * @property int $etablissement_id
 * @property int $annee_scolaire_id
 * @property int $inscription_id
 * @property int $eleve_id
 * @property \Illuminate\Support\Carbon $date_consigne
 * @property string|null $heure_debut
 * @property string|null $heure_fin
 * @property int $nombre_heures
 * @property string $motif
 * @property string|null $travail_assigne
 * @property string|null $surveillant_responsable
 * @property string $statut
 * @property string|null $observations
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement $etablissement
 * @property-read \App\Models\AnneeScolaire $anneeScolaire
 * @property-read \App\Models\Inscription $inscription
 * @property-read \App\Models\Eleve $eleve
 */
class HeureConsigne extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'heures_consigne';

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
        'date_consigne',
        'heure_debut',
        'heure_fin',
        'nombre_heures',
        'motif',
        'travail_assigne',
        'surveillant_responsable',
        'statut',
        'observations',
        'created_by',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_consigne' => 'date',
        'nombre_heures' => 'integer',
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
     * Relation vers l'inscription.
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

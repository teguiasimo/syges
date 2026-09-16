<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle représentant une Séance d'Emploi du Temps Hebdomadaire (Gesco V1.1 - Module 13.2).
 *
 * Articule le triplet pédagogique Classe / Matière / Enseignant (Attribution - Module 9)
 * avec une Salle physique (Module 13.1) sur un créneau récurrent du Lundi au Samedi.
 * Prend en charge le statut actif, l'annulation et le remplacement par un collègue (13.4).
 *
 * @property int $id
 * @property int $annee_scolaire_id Année scolaire active
 * @property int $classe_id Classe bénéficiaire du cours
 * @property int $attribution_id Attribution du cours (enseignant + matière)
 * @property int|null $salle_id Salle physique où se déroule le cours
 * @property string $jour_semaine 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'
 * @property string $heure_debut Heure de début (H:i)
 * @property string $heure_fin Heure de fin (H:i)
 * @property string $statut 'actif', 'annule', 'remplace'
 * @property int|null $remplace_par_personnel_id Enseignant remplaçant affecté
 * @property string|null $motif_annulation Motif en cas d'annulation ou indisponibilité
 *
 * @property-read AnneeScolaire $anneeScolaire
 * @property-read Classe $classe
 * @property-read Attribution $attribution
 * @property-read Salle|null $salle
 * @property-read Personnel|null $remplacant
 */
class SeanceEmploiTemps extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Table associée au modèle.
     *
     * @var string
     */
    protected $table = 'seances_emploi_temps';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'annee_scolaire_id',
        'classe_id',
        'attribution_id',
        'salle_id',
        'jour_semaine',
        'heure_debut',
        'heure_fin',
        'statut',
        'remplace_par_personnel_id',
        'motif_annulation',
    ];

    /**
     * Relation avec l'année scolaire de la séance.
     *
     * @return BelongsTo
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /**
     * Relation avec la classe concernée.
     *
     * @return BelongsTo
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Relation avec l'attribution pédagogique (Matière + Enseignant titulaire).
     *
     * @return BelongsTo
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(Attribution::class);
    }

    /**
     * Relation avec la salle physique occupée.
     *
     * @return BelongsTo
     */
    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    /**
     * Relation avec le personnel enseignant ayant effectué un remplacement.
     *
     * @return BelongsTo
     */
    public function remplacant(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'remplace_par_personnel_id');
    }

    /**
     * Calcule la durée de la séance en minutes.
     *
     * @return int
     */
    public function getDureeMinutesAttribute(): int
    {
        $debut = Carbon::parse($this->heure_debut);
        $fin = Carbon::parse($this->heure_fin);

        return (int) $debut->diffInMinutes($fin);
    }

    /**
     * Calcule la durée de la séance en heures décimales (ex: 1.5 pour 1h30).
     *
     * @return float
     */
    public function getDureeHeuresAttribute(): float
    {
        return round($this->duree_minutes / 60, 2);
    }
}

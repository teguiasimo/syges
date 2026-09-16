<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle représentant une Salle Physique ou Local Pédagogique (Gesco V1.1 - Module 13.1).
 *
 * Enregistre les locaux d'enseignement (salles de cours standard, laboratoires scientifiques,
 * salles multimédia/informatique, plateaux sportifs, etc.) avec leur capacité d'accueil
 * pour le contrôle anti-conflits et la vérification des effectifs.
 *
 * @property int $id Identifiant unique de la salle
 * @property int $etablissement_id Établissement de rattachement
 * @property string $nom Nom usuel de la salle (ex: "Salle 101", "Labo SVT")
 * @property string $code Code unique dans l'établissement (ex: "S101", "LAB-SVT")
 * @property string $type_salle Type de local ('standard', 'laboratoire', 'informatique', 'sport', 'polyvalente')
 * @property int $capacite_places Nombre maximal de places assises / postes de travail
 * @property string $statut Disponibilité opérationnelle ('disponible', 'indisponible')
 * @property string|null $description Spécificités ou équipements installés
 *
 * @property-read Etablissement $etablissement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SeanceEmploiTemps> $seancesEmploiTemps
 */
class Salle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Table associée au modèle.
     *
     * @var string
     */
    protected $table = 'salles';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'nom',
        'code',
        'type_salle',
        'capacite_places',
        'statut',
        'description',
    ];

    /**
     * Attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capacite_places' => 'integer',
    ];

    /**
     * Relation avec l'établissement propriétaire de la salle.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation avec les séances d'emploi du temps programmées dans cette salle.
     *
     * @return HasMany
     */
    public function seancesEmploiTemps(): HasMany
    {
        return $this->hasMany(SeanceEmploiTemps::class);
    }
}

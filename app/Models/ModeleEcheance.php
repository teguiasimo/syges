<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant un Modèle d'Échéancier Calendaire de Paiement (Aurore §3.24).
 *
 * Définit le calendrier des dates limites de règlement des tranches de scolarité
 * pour une année scolaire donnée.
 *
 * @property int $id Identifiant unique
 * @property int $etablissement_id Établissement
 * @property int $annee_scolaire_id Année scolaire
 * @property string $nom Libellé de l'échéancier (ex: 'Échéancier Général 2026-2027')
 * @property string|null $description
 * @property bool $is_default Indique si ce calendrier est appliqué par défaut
 */
class ModeleEcheance extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table.
     *
     * @var string
     */
    protected $table = 'modeles_echeances';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'annee_scolaire_id',
        'nom',
        'description',
        'is_default',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];


    /**
     * Année scolaire.
     *
     * @return BelongsTo<AnneeScolaire, ModeleEcheance>
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /**
     * Tranches et dates d'échéances associées.
     *
     * @return HasMany<EcheanceTranche>
     */
    public function tranches(): HasMany
    {
        return $this->hasMany(EcheanceTranche::class, 'modele_echeance_id')->orderBy('tranche_numero');
    }
}

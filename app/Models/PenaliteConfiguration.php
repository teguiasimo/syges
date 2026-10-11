<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une règle de calcul de Pénalités de retard financier (Aurore §3.17).
 *
 * Définit les majorations appliquées en cas de retard de paiement après date d'échéance :
 * - Montant fixe (ex: 2 000 FCFA de frais de dossier/relance)
 * - Pourcentage (ex: 5% du montant restant dû)
 * - Période de grâce (ex: 5 jours de tolérance après la date limite)
 *
 * @property int $id Identifiant unique
 * @property int $etablissement_id Établissement
 * @property string $nom Libellé de la règle (ex: 'Pénalité standard retard tranche')
 * @property string $mode_calcul ('montant_fixe', 'pourcentage')
 * @property float $valeur Montant forfaitaire ou taux en %
 * @property int $jours_grace Nombre de jours de tolérance avant déclenchement
 * @property bool $is_active État d'activation
 */
class PenaliteConfiguration extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table.
     *
     * @var string
     */
    protected $table = 'penalites_configurations';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'nom',
        'mode_calcul',
        'valeur',
        'jours_grace',
        'is_active',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valeur' => 'float',
        'jours_grace' => 'integer',
        'is_active' => 'boolean',
    ];


    /**
     * Pénalités effectives appliquées aux élèves avec cette règle.
     *
     * @return HasMany<PenaliteEleve>
     */
    public function penalitesAppliquees(): HasMany
    {
        return $this->hasMany(PenaliteEleve::class, 'penalite_configuration_id');
    }
}

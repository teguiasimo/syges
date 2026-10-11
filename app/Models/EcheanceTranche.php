<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une tranche calendaire d'un échéancier de paiement (Aurore §3.24).
 *
 * @property int $id Identifiant unique
 * @property int $modele_echeance_id Modèle d'échéance parent
 * @property int $tranche_numero Numéro de tranche (1, 2, 3...)
 * @property string $libelle Libellé de la tranche (ex: 'Tranche 1 - Rentrée')
 * @property \Carbon\Carbon $date_limite Date limite d'exigibilité
 * @property float $pourcentage_exigible Pourcentage cumulé exigible à cette date (ex: 50.00%)
 */
class EcheanceTranche extends Model
{
    use HasFactory;

    /**
     * Nom de la table.
     *
     * @var string
     */
    protected $table = 'echeances_tranches';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'modele_echeance_id',
        'tranche_numero',
        'libelle',
        'date_limite',
        'pourcentage_exigible',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tranche_numero' => 'integer',
        'date_limite' => 'date',
        'pourcentage_exigible' => 'float',
    ];

    /**
     * Modèle d'échéance parent.
     *
     * @return BelongsTo<ModeleEcheance, EcheanceTranche>
     */
    public function modeleEcheance(): BelongsTo
    {
        return $this->belongsTo(ModeleEcheance::class, 'modele_echeance_id');
    }
}

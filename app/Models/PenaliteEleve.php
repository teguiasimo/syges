<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Pénalité de retard appliquée au compte financier d'un élève (Aurore §3.17).
 *
 * S'ajoute au débit exigible de l'élève en cas de non-respect des échéances de paiement.
 *
 * @property int $id Identifiant unique
 * @property int $compte_financier_id Compte financier élève
 * @property int|null $penalite_configuration_id Règle de pénalité appliquée
 * @property string $libelle Libellé (ex: 'Pénalité de retard - Tranche 1')
 * @property float $montant Montant de la pénalité
 * @property \Carbon\Carbon $date_application Date d'application
 * @property string $statut ('a_payer', 'paye', 'annule')
 * @property string|null $motif Justificatif
 * @property int|null $applique_par Utilisateur ayant appliqué la pénalité
 */
class PenaliteEleve extends Model
{
    use HasFactory;

    /**
     * Nom de la table.
     *
     * @var string
     */
    protected $table = 'penalites_eleves';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'compte_financier_id',
        'penalite_configuration_id',
        'libelle',
        'montant',
        'date_application',
        'statut',
        'motif',
        'applique_par',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant' => 'float',
        'date_application' => 'date',
    ];

    /**
     * Compte financier de l'élève.
     *
     * @return BelongsTo<CompteFinancierEleve, PenaliteEleve>
     */
    public function compteFinancier(): BelongsTo
    {
        return $this->belongsTo(CompteFinancierEleve::class, 'compte_financier_id');
    }

    /**
     * Règle de pénalité appliquée.
     *
     * @return BelongsTo<PenaliteConfiguration, PenaliteEleve>
     */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(PenaliteConfiguration::class, 'penalite_configuration_id');
    }

    /**
     * Utilisateur ayant validé la pénalité.
     *
     * @return BelongsTo<User, PenaliteEleve>
     */
    public function appliquePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applique_par');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant l'attribution d'une Bourse ou Remise au compte scolaire d'un élève (Aurore §4.1.6).
 *
 * Lie le compte financier de l'élève au référentiel de bourses/remises et enregistre le montant
 * déduit sur sa scolarité exigible.
 *
 * @property int $id Identifiant unique
 * @property int $compte_financier_id Compte financier élève
 * @property int $bourse_remise_id Modèle de bourse ou remise
 * @property float $montant_calcule Montant réel déduit en FCFA
 * @property \Carbon\Carbon $date_attribution Date d'octroi
 * @property string|null $motif Justificatif ou observation
 * @property int|null $attribue_par Utilisateur ayant accordé la bourse/remise
 */
class EleveBourseRemise extends Model
{
    use HasFactory;

    /**
     * Nom de la table.
     *
     * @var string
     */
    protected $table = 'eleve_bourses_remises';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'compte_financier_id',
        'bourse_remise_id',
        'montant_calcule',
        'date_attribution',
        'motif',
        'attribue_par',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant_calcule' => 'float',
        'date_attribution' => 'date',
    ];

    /**
     * Compte financier de l'élève.
     *
     * @return BelongsTo<CompteFinancierEleve, EleveBourseRemise>
     */
    public function compteFinancier(): BelongsTo
    {
        return $this->belongsTo(CompteFinancierEleve::class, 'compte_financier_id');
    }

    /**
     * Modèle de bourse ou remise appliqué.
     *
     * @return BelongsTo<BourseRemise, EleveBourseRemise>
     */
    public function bourseRemise(): BelongsTo
    {
        return $this->belongsTo(BourseRemise::class, 'bourse_remise_id');
    }

    /**
     * Utilisateur ayant validé l'octroi.
     *
     * @return BelongsTo<User, EleveBourseRemise>
     */
    public function attribuePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attribue_par');
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Dispense Financière accordée à un élève sur une tranche ou rubrique précise.
 *
 * Permet de traiter proprement et séparément des bourses commerciales :
 * - Un élève arrivant en cours d'année (ex: 2ème ou 3ème trimestre) dispensé totalement ou partiellement de la Tranche 1.
 * - Une exonération ciblée de cotisation (ex: APEE déjà réglée par le parent sur l'aîné de la fratrie).
 *
 * @property int $id Identifiant unique de la dispense
 * @property int $compte_financier_id Compte financier de l'élève concerné
 * @property int $frais_scolaire_id Ligne de tarif / tranche faisant l'objet de la dispense
 * @property string $type_dispense Nature de la dispense ('totale' ou 'partielle')
 * @property float $montant_dispense Montant exonéré en FCFA sur cette ligne
 * @property string $motif Justification obligatoire (ex: Arrivée au 2ème trimestre)
 * @property Carbon $date_dispense Date d'enregistrement de la dispense
 * @property int|null $accorde_par Utilisateur ayant accordé la dispense
 */
class DispenseFinanciere extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'dispenses_financieres';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'compte_financier_id',
        'frais_scolaire_id',
        'type_dispense',
        'montant_dispense',
        'motif',
        'date_dispense',
        'accorde_par',
    ];

    /**
     * Conversion native des types d'attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant_dispense' => 'float',
        'date_dispense' => 'date',
    ];

    /**
     * Relation vers le compte financier de l'élève bénéficiaire.
     *
     * @return BelongsTo<CompteFinancierEleve, DispenseFinanciere>
     */
    public function compteFinancier(): BelongsTo
    {
        return $this->belongsTo(CompteFinancierEleve::class, 'compte_financier_id');
    }

    /**
     * Relation vers la tranche ou rubrique de la grille tarifaire dispensée.
     *
     * @return BelongsTo<FraisScolaire, DispenseFinanciere>
     */
    public function fraisScolaire(): BelongsTo
    {
        return $this->belongsTo(FraisScolaire::class, 'frais_scolaire_id');
    }

    /**
     * Relation vers l'utilisateur ayant validé la dispense.
     *
     * @return BelongsTo<User, DispenseFinanciere>
     */
    public function accordePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accorde_par');
    }
}

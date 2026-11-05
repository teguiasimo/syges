<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant la souscription d'un élève à un frais scolaire optionnel.
 *
 * Permet de rattacher des services annexes (bus scolaire, cantine, internat, activités périscolaires)
 * au compte financier individuel de l'élève au sein du complexe scolaire.
 *
 * @property int $id Identifiant unique
 * @property int $compte_financier_id Compte financier parent de l'élève
 * @property int $frais_scolaire_id Frais scolaire optionnel rattaché
 * @property float $montant Montant facturé en FCFA
 * @property Carbon $date_souscription Date de souscription effective
 * @property string $statut Statut de la souscription ('actif' ou 'annule')
 * @property string|null $observations Observations éventuelles
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read CompteFinancierEleve $compteFinancier
 * @property-read FraisScolaire $fraisScolaire
 */
class SouscriptionFraisOptionnel extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'souscriptions_frais_optionnels';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'compte_financier_id',
        'frais_scolaire_id',
        'montant',
        'date_souscription',
        'statut',
        'observations',
    ];

    /**
     * Conversions de types pour les attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant' => 'float',
        'date_souscription' => 'date',
    ];

    /**
     * Relation vers le compte financier élève bénéficiaire.
     *
     * @return BelongsTo<CompteFinancierEleve, SouscriptionFraisOptionnel>
     */
    public function compteFinancier(): BelongsTo
    {
        return $this->belongsTo(CompteFinancierEleve::class, 'compte_financier_id');
    }

    /**
     * Relation vers le tarif optionnel paramétré dans la grille de l'établissement.
     *
     * @return BelongsTo<FraisScolaire, SouscriptionFraisOptionnel>
     */
    public function fraisScolaire(): BelongsTo
    {
        return $this->belongsTo(FraisScolaire::class, 'frais_scolaire_id');
    }
}


<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant un Règlement Divers / Encaissement libre hors scolarité (Aurore §4.2).
 *
 * Enregistre les recettes exceptionnelles ou annexes non rattachées au compte d'un élève :
 * - Vente d'uniformes, macarons, fournitures
 * - Recettes de cantine au ticket / visiteurs
 * - Locations de salles et infrastructures
 * - Dons et subventions
 *
 * @property int $id Identifiant unique
 * @property int $etablissement_id Établissement
 * @property int $caisse_id Caisse d'encaissement
 * @property string $reference_recu Numéro de reçu unique (ex: REC-DIV-2026-0001)
 * @property \Carbon\Carbon $date_reglement Date et heure de l'encaissement
 * @property string $payeur_nom Nom de la personne ou entité payeuse
 * @property string|null $payeur_telephone Téléphone du payeur
 * @property string $categorie Nature de la recette ('uniformes', 'cantine_libre', 'macaron', 'location', 'don', 'autre')
 * @property string $libelle Intitulé explicite de l'opération
 * @property float $montant Montant perçu
 * @property string $mode_paiement ('especes', 'cheque', 'virement', 'mobile_money_manuel')
 * @property string|null $reference_paiement
 * @property int|null $caissier_id Personnel ayant encaissé
 * @property int|null $user_id Utilisateur connecté
 * @property string $statut ('valide', 'annule')
 * @property string|null $motif_annulation
 * @property string|null $observations
 */
class ReglementDivers extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table.
     *
     * @var string
     */
    protected $table = 'reglements_divers';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'caisse_id',
        'reference_recu',
        'date_reglement',
        'payeur_nom',
        'payeur_telephone',
        'categorie',
        'libelle',
        'montant',
        'mode_paiement',
        'reference_paiement',
        'caissier_id',
        'user_id',
        'statut',
        'motif_annulation',
        'observations',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_reglement' => 'datetime',
        'montant' => 'float',
    ];


    /**
     * Caisse d'encaissement.
     *
     * @return BelongsTo<Caisse, ReglementDivers>
     */
    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    /**
     * Caissier ayant enregistré l'opération.
     *
     * @return BelongsTo<Personnel, ReglementDivers>
     */
    public function caissier(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'caissier_id');
    }

    /**
     * Utilisateur authentifié ayant validé l'écriture.
     *
     * @return BelongsTo<User, ReglementDivers>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

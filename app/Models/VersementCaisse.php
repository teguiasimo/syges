<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle représentant un Versement de Caisse physique (Gesco V1.1 - Module 11.2).
 *
 * Enregistre chaque paiement effectué au guichet par un parent/élève avec son mode de règlement
 * (Espèces, Chèque, Virement, Mobile Money manuel) et son numéro séquentiel unique de reçu.
 *
 * @property int $id Identifiant unique du versement
 * @property string $reference_recu Numéro unique de reçu officiel (REC-YYYY-XXXXXX)
 * @property int $compte_financier_id Compte financier élève crédité
 * @property int $inscription_id Inscription scolaire de l'élève
 * @property int $etablissement_id Établissement
 * @property int|null $personnel_id Caissier / Économe ayant encaissé
 * @property int|null $cloture_caisse_id Clôture journalière rattachée
 * @property string $date_versement Date et heure précises du versement
 * @property float $montant_total Montant total versé en FCFA
 * @property string $mode_paiement Mode ('especes', 'cheque', 'virement', 'mobile_money_manuel')
 * @property string|null $reference_paiement Numéro du chèque, bordereau ou identifiant MoMo
 * @property string|null $recu_par Nom de l'agent caissier
 * @property string|null $payeur_nom Nom de la personne effectuant le versement
 * @property string|null $payeur_telephone Contact téléphonique du payeur
 * @property string $statut Statut du versement ('valide', 'annule')
 * @property string|null $motif_annulation Motif en cas d'annulation administrative
 * @property int|null $annule_par Utilisateur ayant autorisé l'annulation
 * @property string|null $annule_le Horodatage de l'annulation
 * @property string|null $observations Remarques ou précisions
 */
class VersementCaisse extends Model
{
    use HasFactory, SoftDeletes, BelongsToEtablissement;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'versements_caisse';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_recu',
        'compte_financier_id',
        'inscription_id',
        'etablissement_id',
        'personnel_id',
        'cloture_caisse_id',
        'date_versement',
        'montant_total',
        'mode_paiement',
        'reference_paiement',
        'recu_par',
        'payeur_nom',
        'payeur_telephone',
        'statut',
        'motif_annulation',
        'annule_par',
        'annule_le',
        'observations',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_versement' => 'datetime',
        'montant_total' => 'float',
        'annule_le' => 'datetime',
    ];

    /**
     * Relation vers le compte financier de l'élève.
     *
     * @return BelongsTo<CompteFinancierEleve, VersementCaisse>
     */
    public function compteFinancier(): BelongsTo
    {
        return $this->belongsTo(CompteFinancierEleve::class, 'compte_financier_id');
    }

    /**
     * Relation vers l'inscription scolaire concernée.
     *
     * @return BelongsTo<Inscription, VersementCaisse>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }


    /**
     * Relation vers le caissier / personnel ayant encaissé.
     *
     * @return BelongsTo<Personnel, VersementCaisse>
     */
    public function caissier(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    /**
     * Relation vers la clôture de caisse si la journée a été clôturée.
     *
     * @return BelongsTo<ClotureCaisse, VersementCaisse>
     */
    public function clotureCaisse(): BelongsTo
    {
        return $this->belongsTo(ClotureCaisse::class, 'cloture_caisse_id');
    }

    /**
     * Relation vers l'utilisateur ayant annulé le versement.
     *
     * @return BelongsTo<User, VersementCaisse>
     */
    public function annulateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    /**
     * Relation vers les détails de ventilation du versement.
     *
     * @return HasMany<VentilationVersement>
     */
    public function ventilations(): HasMany
    {
        return $this->hasMany(VentilationVersement::class, 'versement_id');
    }
}

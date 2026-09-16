<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Clôture Journalière de Caisse (Gesco V1.1 - Module 11.4).
 *
 * Fige les écritures comptables de la journée pour un caissier donné et calcule les totaux par mode de règlement
 * (Espèces, Chèque, Virement, Mobile Money manuel).
 *
 * @property int $id Identifiant de la clôture
 * @property int $etablissement_id Établissement
 * @property int|null $personnel_id Personnel / Caissier concerné
 * @property string $date_cloture Date de la journée clôturée (Y-m-d)
 * @property float $total_especes Total encaissé en espèces
 * @property float $total_cheques Total encaissé en chèques
 * @property float $total_virements Total encaissé en virements
 * @property float $total_mobile_money Total encaissé en Mobile Money manuel
 * @property float $total_general Somme globale de la journée
 * @property int $nombre_transactions Nombre de reçus émis
 * @property string $statut Statut de la clôture ('ouverte', 'cloturee')
 * @property string|null $observations Observations ou écarts de caisse constatés
 * @property string|null $cloture_a Horodatage de clôture
 * @property int|null $verifie_par Administrateur ayant validé la clôture
 */
class ClotureCaisse extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'clotures_caisse';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'personnel_id',
        'date_cloture',
        'total_especes',
        'total_cheques',
        'total_virements',
        'total_mobile_money',
        'total_general',
        'nombre_transactions',
        'statut',
        'observations',
        'cloture_a',
        'verifie_par',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_cloture' => 'date',
        'total_especes' => 'float',
        'total_cheques' => 'float',
        'total_virements' => 'float',
        'total_mobile_money' => 'float',
        'total_general' => 'float',
        'nombre_transactions' => 'integer',
        'cloture_a' => 'datetime',
    ];

    /**
     * Relation vers l'établissement scolaire.
     *
     * @return BelongsTo<Etablissement, ClotureCaisse>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation vers le caissier / économe.
     *
     * @return BelongsTo<Personnel, ClotureCaisse>
     */
    public function caissier(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    /**
     * Relation vers l'administrateur ayant vérifié la clôture.
     *
     * @return BelongsTo<User, ClotureCaisse>
     */
    public function verificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifie_par');
    }

    /**
     * Relation vers les versements rattachés à cette clôture.
     *
     * @return HasMany<VersementCaisse>
     */
    public function versements(): HasMany
    {
        return $this->hasMany(VersementCaisse::class, 'cloture_caisse_id');
    }
}

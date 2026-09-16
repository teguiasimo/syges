<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Ligne de Ventilation d'un Versement (Gesco V1.1 - Module 11.2).
 *
 * Décompose le montant global encaissé par rubrique ou tranche :
 * - Scolarité Tranche 1
 * - Frais APEE
 * - Cantine Scolaire
 * - Transport, etc.
 *
 * @property int $id Identifiant unique de la ventilation
 * @property int $versement_id Versement de caisse parent
 * @property int|null $type_frais_id Type de frais associé
 * @property int|null $frais_scolaire_id Tarif d'origine
 * @property string $libelle Description de la rubrique ou tranche ventilée
 * @property float $montant Montant affecté à cette rubrique en FCFA
 */
class VentilationVersement extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'ventilation_versements';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'versement_id',
        'type_frais_id',
        'frais_scolaire_id',
        'libelle',
        'montant',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant' => 'float',
    ];

    /**
     * Relation vers le versement parent.
     *
     * @return BelongsTo<VersementCaisse, VentilationVersement>
     */
    public function versement(): BelongsTo
    {
        return $this->belongsTo(VersementCaisse::class, 'versement_id');
    }

    /**
     * Relation vers le type de frais associé.
     *
     * @return BelongsTo<TypeFrais, VentilationVersement>
     */
    public function typeFrais(): BelongsTo
    {
        return $this->belongsTo(TypeFrais::class, 'type_frais_id');
    }

    /**
     * Relation vers le frais scolaire d'origine.
     *
     * @return BelongsTo<FraisScolaire, VentilationVersement>
     */
    public function fraisScolaire(): BelongsTo
    {
        return $this->belongsTo(FraisScolaire::class, 'frais_scolaire_id');
    }
}

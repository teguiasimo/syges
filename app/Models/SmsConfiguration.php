<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant la Configuration de la Passerelle SMS (Gesco V1.1 - Module 14.1).
 *
 * Paramètre la connexion aux agrégateurs télécoms (Orange, MTN, Africa's Talking)
 * avec le Sender ID personnalisé (max 11 caractères) et le suivi du solde de crédits.
 *
 * @property int $id
 * @property int $etablissement_id Établissement rattaché
 * @property string $fournisseur 'orange', 'mtn', 'africas_talking', etc.
 * @property string|null $api_url URL de l'API passerelle
 * @property string|null $api_key Clé d'API / Token
 * @property string|null $api_secret Secret ou mot de passe d'API
 * @property string $sender_id Nom d'expéditeur GSM officiel (max 11 car.)
 * @property int $solde_credits Solde actuel de SMS disponibles
 * @property int $seuil_alerte_solde Seuil déclenchant un avertissement de solde bas
 * @property bool $is_active Passerelle opérationnelle
 *
 * @property-read Etablissement $etablissement
 */
class SmsConfiguration extends Model
{
    use HasFactory;

    /**
     * Table associée au modèle.
     *
     * @var string
     */
    protected $table = 'sms_configurations';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'fournisseur',
        'api_url',
        'api_key',
        'api_secret',
        'sender_id',
        'solde_credits',
        'seuil_alerte_solde',
        'is_active',
    ];

    /**
     * Attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'solde_credits' => 'integer',
        'seuil_alerte_solde' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relation avec l'établissement propriétaire de la passerelle.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }
}

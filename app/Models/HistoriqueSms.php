<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Entrée dans l'Historique des SMS (Gesco V1.1 - Module 14.3).
 *
 * Trace chaque message sortant (alerte absence, sanction, rappel paiement, reçu, campagne)
 * avec le numéro destinataire normalisé, le message nettoyé GSM 7-bit et le statut de livraison.
 *
 * @property int $id
 * @property int $etablissement_id Établissement
 * @property int|null $campagne_id Campagne parente (le cas échéant)
 * @property int|null $inscription_id Élève concerné (le cas échéant)
 * @property string $type_notification 'alerte_absence', 'alerte_sanction', 'rappel_paiement', 'confirmation_recu', 'campagne_masse', 'personnalise'
 * @property string|null $destinataire_nom Nom du parent ou contact
 * @property string $destinataire_telephone Numéro au format international (+237...)
 * @property string $message Contenu du SMS émis
 * @property string $statut_distribution 'en_attente', 'envoye', 'echec'
 * @property int $credits_consommes Nombre de segments / crédits SMS déduits
 * @property string|null $reponse_fournisseur Réponse brute ou identifiant de message de l'opérateur
 *
 * @property-read Etablissement $etablissement
 * @property-read SmsCampagne|null $campagne
 * @property-read Inscription|null $inscription
 */
class HistoriqueSms extends Model
{
    use HasFactory;

    /**
     * Table associée au modèle.
     *
     * @var string
     */
    protected $table = 'historique_sms';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'campagne_id',
        'inscription_id',
        'type_notification',
        'destinataire_nom',
        'destinataire_telephone',
        'message',
        'statut_distribution',
        'credits_consommes',
        'reponse_fournisseur',
    ];

    /**
     * Attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'credits_consommes' => 'integer',
    ];

    /**
     * Relation avec l'établissement émetteur.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation avec la campagne de masse éventuelle.
     *
     * @return BelongsTo
     */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(SmsCampagne::class, 'campagne_id');
    }

    /**
     * Relation avec l'inscription de l'élève concerné.
     *
     * @return BelongsTo
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }
}

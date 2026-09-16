<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Campagne de SMS Groupés (Gesco V1.1 - Module 14.2.B).
 *
 * Enregistre les envois en masse (avis aux parents, réunions, relances d'impayés)
 * avec le ciblage sélectionné, le message brut et la version sanitariée GSM 7-bit.
 *
 * @property int $id
 * @property int $etablissement_id Établissement
 * @property string $titre Objet de la campagne
 * @property string $message_brut Texte original saisi par l'utilisateur
 * @property string $message_sanitise Texte converti en GSM 7-bit (sans accents)
 * @property string $cible_type 'tous', 'section', 'niveau', 'classe', 'insolvables'
 * @property int|null $cible_id Identifiant de la cible (classe, niveau, section)
 * @property int $nombre_destinataires Nombre de parents destinataires
 * @property int $total_credits Nombre total de crédits consommés
 * @property string $statut 'en_attente', 'en_cours', 'termine', 'echec'
 * @property int|null $lance_par_user_id Utilisateur ayant déclenché la campagne
 *
 * @property-read Etablissement $etablissement
 * @property-read User|null $lanceur
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HistoriqueSms> $historiques
 */
class SmsCampagne extends Model
{
    use HasFactory;

    /**
     * Table associée au modèle.
     *
     * @var string
     */
    protected $table = 'sms_campagnes';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'titre',
        'message_brut',
        'message_sanitise',
        'cible_type',
        'cible_id',
        'nombre_destinataires',
        'total_credits',
        'statut',
        'lance_par_user_id',
    ];

    /**
     * Attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'nombre_destinataires' => 'integer',
        'total_credits' => 'integer',
    ];

    /**
     * Relation avec l'établissement organisateur de la campagne.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation avec l'utilisateur ayant lancé la campagne.
     *
     * @return BelongsTo
     */
    public function lanceur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lance_par_user_id');
    }

    /**
     * Relation avec les messages individuels générés par cette campagne.
     *
     * @return HasMany
     */
    public function historiques(): HasMany
    {
        return $this->hasMany(HistoriqueSms::class, 'campagne_id');
    }
}

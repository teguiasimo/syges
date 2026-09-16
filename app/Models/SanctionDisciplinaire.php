<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Sanction Disciplinaire prononcée contre un élève (Gesco V1.1 - Module 12.2).
 *
 * Enregistre officiellement les avertissements, blâmes, retenues et exclusions avec leur motif,
 * leur gravité, l'autorité décisionnaire et le suivi de notification aux parents.
 *
 * @property int $id Identifiant unique de la sanction
 * @property int $inscription_id Inscription scolaire de l'élève
 * @property int $periode_id Trimestre / Période concernée
 * @property string $date_decision Date de notification de la sanction (Y-m-d)
 * @property string $type_sanction 'avertissement', 'blame', 'retenue', 'exclusion_temporaire', 'exclusion_definitive'
 * @property string $motif Description des faits reprochés
 * @property int $nombre_jours_ou_heures Durée de la sanction (heures pour retenue, jours pour exclusion)
 * @property string $decide_par Autorité décisionnaire ('Censeur', 'Conseil de discipline', 'Chef d\'établissement')
 * @property string|null $document_notification_path Fichier scanné de la lettre d'exclusion ou notification
 * @property bool $is_notified_parent Indique si le parent a été notifié par SMS / courrier
 */
class SanctionDisciplinaire extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'sanctions_disciplinaires';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inscription_id',
        'periode_id',
        'date_decision',
        'type_sanction',
        'motif',
        'nombre_jours_ou_heures',
        'decide_par',
        'document_notification_path',
        'is_notified_parent',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_decision' => 'date',
        'nombre_jours_ou_heures' => 'integer',
        'is_notified_parent' => 'boolean',
    ];

    /**
     * Relation vers l'inscription de l'élève.
     *
     * @return BelongsTo<Inscription, SanctionDisciplinaire>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /**
     * Relation vers la période scolaire (trimestre).
     *
     * @return BelongsTo<Periode, SanctionDisciplinaire>
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /**
     * Renvoie le niveau de sévérité numérique pour déterminer la sanction principale du bulletin
     * (Gesco V1.1 - Module 12.3 : Exclusion définitive > Exclusion temporaire > Blâme > Avertissement > Retenue).
     *
     * @return int
     */
    public function getSeveriteAttribute(): int
    {
        return match ($this->type_sanction) {
            'exclusion_definitive' => 5,
            'exclusion_temporaire' => 4,
            'blame' => 3,
            'avertissement' => 2,
            'retenue' => 1,
            default => 0,
        };
    }
}

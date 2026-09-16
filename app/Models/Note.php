<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une Note attribuée à un élève pour une évaluation et une attribution données.
 *
 * Conforme au Cahier des Charges Gesco V1.1 (Section 10) et aux nouvelles normes MINESEC (APC) :
 * - Associe l'élève (via son inscription annuelle), la matière/classe/enseignant (via l'attribution) et la séquence (via l'évaluation).
 * - Enregistre la note chiffrée sur 20, le statut de présence, l'appréciation qualitative.
 * - Intègre la compétence évaluée (APC) et la cote d'acquisition (CTBA, CBA, CMA, CNA).
 * - Gère le verrouillage temporel et la traçabilité de saisie.
 *
 * @property int $id
 * @property int $inscription_id
 * @property int $attribution_id
 * @property int $evaluation_id
 * @property float|null $valeur_note
 * @property string $statut_presence
 * @property string|null $appreciation
 * @property string|null $competence_evaluee
 * @property string|null $cote
 * @property bool $is_verrouillee
 * @property int|null $saisie_par_user_id
 * @property int|null $modifie_par_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Note extends Model
{
    use HasFactory;

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inscription_id',
        'attribution_id',
        'evaluation_id',
        'valeur_note',
        'statut_presence',
        'appreciation',
        'competence_evaluee',
        'competence',
        'cote',
        'is_verrouillee',
        'saisie_par_user_id',
        'modifie_par_user_id',
    ];

    /**
     * Mutateur pour l'alias competence pointant sur competence_evaluee.
     *
     * @param  string|null  $value
     */
    public function setCompetenceAttribute(?string $value): void
    {
        $this->attributes['competence_evaluee'] = $value;
    }

    /**
     * Accesseur pour l'alias competence pointant sur competence_evaluee.
     */
    public function getCompetenceAttribute(): ?string
    {
        return $this->attributes['competence_evaluee'] ?? null;
    }

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valeur_note' => 'decimal:2',
        'is_verrouillee' => 'boolean',
    ];

    /**
     * Événements du modèle : calcul automatique de la cote APC si non fournie.
     */
    protected static function booted(): void
    {
        static::saving(function (Note $note) {
            if ($note->valeur_note !== null && empty($note->cote)) {
                $note->cote = self::determinerCoteAPC((float) $note->valeur_note);
            }
        });
    }

    /**
     * Détermine la cote d'acquisition APC selon les normes officielles camerounaises.
     *
     * - CTBA (Compétence Très Bien Acquise) : Note >= 16.00 / 20 (Expert)
     * - CBA  (Compétence Bien Acquise)      : Note de 14.00 à 15.99 / 20 (Acquis)
     * - CMA  (Compétence Moyennement Acquise): Note de 10.00 à 13.99 / 20 (En cours)
     * - CNA  (Compétence Non Acquise)       : Note < 10.00 / 20 (Non acquis)
     *
     * @param  float  $note  Valeur chiffrée sur 20
     * @return string Cote APC ('CTBA', 'CBA', 'CMA', 'CNA')
     */
    public static function determinerCoteAPC(float $note): string
    {
        if ($note >= 16.0) {
            return 'CTBA';
        } elseif ($note >= 14.0) {
            return 'CBA';
        } elseif ($note >= 10.0) {
            return 'CMA';
        } else {
            return 'CNA';
        }
    }

    /**
     * Relation vers l'inscription annuelle de l'élève.
     *
     * @return BelongsTo<Inscription, Note>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    /**
     * Relation vers l'attribution pédagogique (Matière + Classe + Enseignant).
     *
     * @return BelongsTo<Attribution, Note>
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(Attribution::class, 'attribution_id');
    }

    /**
     * Relation vers l'évaluation / séquence académique.
     *
     * @return BelongsTo<Evaluation, Note>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class, 'evaluation_id');
    }

    /**
     * Utilisateur ayant saisi la note.
     *
     * @return BelongsTo<User, Note>
     */
    public function saisiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par_user_id');
    }

    /**
     * Utilisateur ayant modifié la note en dernier.
     *
     * @return BelongsTo<User, Note>
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_user_id');
    }
}

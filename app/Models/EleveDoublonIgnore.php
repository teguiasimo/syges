<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Représente une paire d'élèves explicitement marquée comme « non doublons »
 * (ex. jumeaux, fratrie ou vrais homonymes) afin de ne plus remonter dans les alertes.
 *
 * Les identifiants sont toujours stockés de façon ordonnée (`eleve_min_id < eleve_max_id`)
 * pour garantir l'unicité symétrique de chaque paire.
 *
 * @property int $id
 * @property int $eleve_min_id
 * @property int $eleve_max_id
 * @property string|null $motif
 * @property int|null $ignored_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EleveDoublonIgnore extends Model
{
    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'eleves_doublons_ignores';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'eleve_min_id',
        'eleve_max_id',
        'motif',
        'ignored_by_user_id',
    ];

    /**
     * Premier élève de la paire ignorée (ID inférieur).
     *
     * @return BelongsTo<Eleve, EleveDoublonIgnore>
     */
    public function eleveMin(): BelongsTo
    {
        return $this->belongsTo(Eleve::class, 'eleve_min_id');
    }

    /**
     * Second élève de la paire ignorée (ID supérieur).
     *
     * @return BelongsTo<Eleve, EleveDoublonIgnore>
     */
    public function eleveMax(): BelongsTo
    {
        return $this->belongsTo(Eleve::class, 'eleve_max_id');
    }

    /**
     * Utilisateur ayant confirmé que ces deux élèves ne sont pas des doublons.
     *
     * @return BelongsTo<User, EleveDoublonIgnore>
     */
    public function ignoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignored_by_user_id');
    }
}

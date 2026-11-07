<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une catégorie personnalisable de Bourse ou de Remise scolaire.
 *
 * Permet de qualifier librement les réductions tarifaires accordées individuellement aux élèves
 * (ex: Paiement en avance avant la rentrée, Fratrie, Enfant du personnel, Excellence, Cas social...).
 *
 * @property int $id Identifiant unique de la catégorie
 * @property int|null $etablissement_id Identifiant de l'établissement propriétaire (null si global)
 * @property string $nom Libellé de la catégorie de bourse/remise
 * @property bool $is_active État d'activation
 */
class CategorieBourseRemise extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'categories_bourses_remises';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'nom',
        'is_active',
    ];

    /**
     * Conversion automatique des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relation vers l'établissement propriétaire de la catégorie (si spécifique).
     *
     * @return BelongsTo<Etablissement, CategorieBourseRemise>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }
}

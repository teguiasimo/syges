<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle représentant une catégorie personnalisable de Recette Diverse hors scolarité.
 *
 * Les catégories ayant `etablissement_id = null` sont des catégories systèmes partagées,
 * tandis que chaque établissement peut créer et gérer ses propres catégories dédiées.
 *
 * @property int $id Identifiant unique de la catégorie
 * @property int|null $etablissement_id Identifiant de l'établissement propriétaire (null si global)
 * @property string $nom Libellé de la catégorie de recette
 * @property bool $is_active État d'activation
 */
class CategorieRecetteDiverse extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'categories_recettes_diverses';

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
     * @return BelongsTo<Etablissement, CategorieRecetteDiverse>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }
}

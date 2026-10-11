<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Catégorie de Dépense / Imputation Budgétaire (Phase 1).
 *
 * Permet de classer les décaissements de l'établissement :
 * - Fournitures scolaires et consommables
 * - Salaires, primes et vacations du personnel
 * - Énergie (Eneo), Eau (Camwater), Carburant
 * - Maintenance et entretien des bâtiments
 * - Charges administratives et pédagogiques
 *
 * @property int $id
 * @property int|null $etablissement_id
 * @property string $code
 * @property string $nom
 * @property string|null $description
 * @property string $couleur
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Etablissement|null $etablissement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Depense> $depenses
 */
class CategorieDepense extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'categories_depenses';

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'etablissement_id',
        'code',
        'nom',
        'description',
        'couleur',
        'is_active',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relation vers l'établissement (null si standard partagé).
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation vers les dépenses enregistrées sous cette catégorie.
     *
     * @return HasMany
     */
    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class, 'categorie_depense_id');
    }
}

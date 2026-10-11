<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Bourse ou une Remise paramétrable (Aurore §3.19, §3.21, §3.25).
 *
 * Permet de définir :
 * - Bourses d'excellence, bourses sociales ou étatiques
 * - Modèles de remises par niveau ou par critères familiaux (fratrie, enfants de personnel)
 * - Mode de calcul : pourcentage (%) ou montant forfaitaire (FCFA)
 *
 * @property int $id Identifiant unique
 * @property int $etablissement_id Établissement
 * @property int|null $annee_scolaire_id Année scolaire
 * @property string $code Code abrégé unique
 * @property string $nom Libellé complet
 * @property string $type ('bourse', 'remise')
 * @property string $categorie ('excellence', 'sociale', 'fratrie', 'personnel', 'autre')
 * @property string $mode_calcul ('pourcentage', 'montant_fixe')
 * @property float $valeur Pourcentage ou montant fixe en FCFA
 * @property int|null $niveau_id Niveau cible (si spécifique)
 * @property int|null $type_frais_id Type de frais cible (si spécifique)
 * @property string|null $description
 * @property bool $is_active
 */
class BourseRemise extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table.
     *
     * @var string
     */
    protected $table = 'bourses_remises';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'annee_scolaire_id',
        'code',
        'nom',
        'type',
        'categorie',
        'mode_calcul',
        'valeur',
        'niveau_id',
        'type_frais_id',
        'description',
        'is_active',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valeur' => 'float',
        'is_active' => 'boolean',
    ];


    /**
     * Année scolaire.
     *
     * @return BelongsTo<AnneeScolaire, BourseRemise>
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /**
     * Niveau scolaire ciblé (nullable).
     *
     * @return BelongsTo<Niveau, BourseRemise>
     */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /**
     * Type de frais ciblé (nullable).
     *
     * @return BelongsTo<TypeFrais, BourseRemise>
     */
    public function typeFrais(): BelongsTo
    {
        return $this->belongsTo(TypeFrais::class);
    }

    /**
     * Attributions de cette bourse/remise aux élèves.
     *
     * @return HasMany<EleveBourseRemise>
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(EleveBourseRemise::class, 'bourse_remise_id');
    }
}

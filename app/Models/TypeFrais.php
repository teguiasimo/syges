<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Modèle représentant un Frais configuré dans le Catalogue des Frais (Gesco V1.1 - Module 5.2 / 11.1).
 *
 * Définit un frais complet réutilisable sur un ou plusieurs niveaux pédagogiques
 * (ex: « Inscription SIL - CM2 », « Tranche 1 SIL - CM2 », « CEPE », « Fête de fin d'année », « Bus »),
 * rattaché à une Catégorie de Frais (Scolarité, Frais d'Examen, Fête...) avec son montant,
 * sa priorité d'encaissement, sa date butoir et ses informations complémentaires.
 *
 * @property int $id Identifiant unique du frais au catalogue
 * @property int $etablissement_id Établissement de rattachement
 * @property int|null $categorie_frais_id Catégorie de regroupement (Scolarité, Examen, Fête...)
 * @property string $code Code technique unique généré ou saisi
 * @property string $libelle Libellé complet du frais (ex: Inscription SIL - CM2)
 * @property string|null $description Informations complémentaires éventuelles
 * @property bool $obligatoire Indique si le frais est obligatoire ou optionnel
 * @property string $periodicite Périodicité (annuel, trimestriel, mensuel, ponctuel)
 * @property int $ordre_priorite Ordre de priorité d'encaissement dans l'établissement (1 = prioritaire)
 * @property float $montant_defaut Montant configuré du frais en FCFA
 * @property Carbon|string|null $date_limite_defaut Date butoir d'exigibilité du frais
 * @property bool $is_active Statut d'activation
 */
class TypeFrais extends Model
{
    use BelongsToEtablissement, HasFactory, SoftDeletes;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'types_frais';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'categorie_frais_id',
        'code',
        'libelle',
        'description',
        'obligatoire',
        'periodicite',
        'ordre_priorite',
        'montant_defaut',
        'date_limite_defaut',
        'is_active',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'categorie_frais_id' => 'integer',
        'obligatoire' => 'boolean',
        'ordre_priorite' => 'integer',
        'montant_defaut' => 'float',
        'date_limite_defaut' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    /**
     * Relation vers la catégorie de regroupement du frais (ex: Scolarité, Frais d'Examen, Fête).
     *
     * @return BelongsTo<CategorieFrais, $this>
     */
    public function categorieFrais(): BelongsTo
    {
        return $this->belongsTo(CategorieFrais::class, 'categorie_frais_id');
    }

    /**
     * Relation vers les affectations par niveau définies dans la grille tarifaire.
     *
     * @return HasMany<FraisScolaire>
     */
    public function fraisScolaires(): HasMany
    {
        return $this->hasMany(FraisScolaire::class);
    }
}

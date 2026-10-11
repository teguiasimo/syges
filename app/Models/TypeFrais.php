<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle représentant un Type de Frais scolaire (Gesco V1.1 - Module 5.2 / 11.1).
 *
 * Définit la nomenclature des frais applicables au sein d'un établissement
 * (Droits d'inscription, Scolarité, APEE, Cantine, Transport, Uniforme, etc.).
 *
 * @property int $id Identifiant unique du type de frais
 * @property int $etablissement_id Établissement de rattachement
 * @property string $code Code technique unique (ex: INSCRIPTION, SCOLARITE, APEE)
 * @property string $libelle Libellé complet du frais
 * @property string|null $description Description ou précisions éventuelles
 * @property bool $obligatoire Indique si le frais est obligatoire ou optionnel
 * @property string $periodicite Périodicité (annuel, trimestriel, mensuel, ponctuel)
 * @property float $montant_defaut Montant indicatif par défaut
 * @property bool $is_active Statut d'activation
 */
class TypeFrais extends Model
{
    use HasFactory, SoftDeletes, BelongsToEtablissement;

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
        'code',
        'libelle',
        'description',
        'obligatoire',
        'periodicite',
        'montant_defaut',
        'is_active',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'obligatoire' => 'boolean',
        'montant_defaut' => 'float',
        'is_active' => 'boolean',
    ];


    /**
     * Relation vers les tarifs définis dans la grille tarifaire.
     *
     * @return HasMany<FraisScolaire>
     */
    public function fraisScolaires(): HasMany
    {
        return $this->hasMany(FraisScolaire::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant un Frais Scolaire paramétré dans la Matrice Tarifaire (Gesco V1.1 - Module 5.2 / 11.1).
 *
 * Associe un type de frais à un niveau, une série/filière (optionnelle),
 * avec un numéro de tranche et une date limite d'exigibilité.
 *
 * @property int $id Identifiant unique du tarif
 * @property int $annee_scolaire_id Année scolaire concernée
 * @property int $etablissement_id Établissement de rattachement
 * @property int $niveau_id Niveau académique (ex: 6ème, Terminale)
 * @property string|null $serie Série/Filière spécifique (null = Toutes les séries)
 * @property int $type_frais_id Type de frais associé
 * @property int $tranche_numero Numéro de tranche (1, 2, 3...)
 * @property string|null $libelle_tranche Libellé d'affichage de l'échéance
 * @property float $montant Montant exigible en FCFA
 * @property string|null $date_limite Date limite d'exigibilité
 */
class FraisScolaire extends Model
{
    use HasFactory;

    /**
     * Nom de la table associée dans la base de données.
     *
     * @var string
     */
    protected $table = 'frais_scolaires';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'annee_scolaire_id',
        'etablissement_id',
        'niveau_id',
        'serie',
        'type_frais_id',
        'tranche_numero',
        'libelle_tranche',
        'montant',
        'date_limite',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tranche_numero' => 'integer',
        'montant' => 'float',
        'date_limite' => 'date',
    ];

    /**
     * Relation vers l'année scolaire.
     *
     * @return BelongsTo<AnneeScolaire, FraisScolaire>
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /**
     * Relation vers l'établissement scolaire.
     *
     * @return BelongsTo<Etablissement, FraisScolaire>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Relation vers le niveau académique.
     *
     * @return BelongsTo<Niveau, FraisScolaire>
     */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /**
     * Relation vers la catégorie/type de frais.
     *
     * @return BelongsTo<TypeFrais, FraisScolaire>
     */
    public function typeFrais(): BelongsTo
    {
        return $this->belongsTo(TypeFrais::class);
    }

    /**
     * Relation vers les ventilations de versements ayant ciblé ce frais.
     *
     * @return HasMany<VentilationVersement>
     */
    public function ventilations(): HasMany
    {
        return $this->hasMany(VentilationVersement::class);
    }
}

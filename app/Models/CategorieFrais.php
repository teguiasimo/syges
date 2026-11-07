<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant une Catégorie de Frais scolaire (ex: Scolarité, Frais d'Examen, Fête, Transport).
 *
 * Sert à regrouper logiquement les frais configurés dans le Catalogue des Frais d'un établissement
 * (ex: Inscription SIL-CM2, Tranche 1 SIL-CM2, Tranche 2 SIL-CM2 sous « Scolarité », ou CEPE sous « Frais d'Examen »)
 * afin de calculer les montants globaux attendus, encaissés et restants par catégorie pour une classe ou un niveau.
 *
 * @property int $id Identifiant unique de la catégorie de frais
 * @property int $etablissement_id Établissement propriétaire
 * @property string $nom Libellé court de la catégorie (ex: Scolarité, Frais d'Examen, Fête)
 * @property string|null $description Description facultative du périmètre de la catégorie
 * @property bool $is_active Indique si la catégorie est active
 */
class CategorieFrais extends Model
{
    use BelongsToEtablissement, HasFactory;

    /**
     * Nom explicite de la table en base de données.
     *
     * @var string
     */
    protected $table = 'categories_frais';

    /**
     * Attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'nom',
        'description',
        'is_active',
    ];

    /**
     * Conversion automatique des attributs natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relation vers les frais du catalogue rattachés à cette catégorie.
     *
     * @return HasMany<TypeFrais, $this>
     */
    public function typesFrais(): HasMany
    {
        return $this->hasMany(TypeFrais::class, 'categorie_frais_id');
    }

    /**
     * Garantit qu'un établissement dispose au minimum des catégories de base prêtes à l'emploi,
     * puis retourne la liste ordonnée des catégories actives de l'établissement.
     *
     * @param  int  $etablissementId  Identifiant de l'établissement
     * @return Collection<int, CategorieFrais>
     */
    public static function obtenirOuInitialiserPourEtablissement(int $etablissementId): Collection
    {
        if ($etablissementId <= 0) {
            return new Collection;
        }

        $existantes = static::withoutGlobalScopes()
            ->where('etablissement_id', $etablissementId)
            ->orderBy('id')
            ->get();

        if ($existantes->isEmpty()) {
            $defauts = [
                [
                    'nom' => 'Scolarité',
                    'description' => "Droits d'inscription, pension et tranches de scolarité.",
                ],
                [
                    'nom' => "Frais d'Examen",
                    'description' => 'Examens officiels et blancs (CEP/CEPE, BEPC, Probatoire, Baccalauréat).',
                ],
                [
                    'nom' => 'Fête & Vie Scolaire',
                    'description' => "Cotisations APEE, fête de fin d'année, activités culturelles et sportives.",
                ],
                [
                    'nom' => 'Transport & Services Annexes',
                    'description' => 'Services optionnels : bus scolaire, cantine, études surveillées, tenues.',
                ],
            ];

            foreach ($defauts as $defaut) {
                static::withoutGlobalScopes()->firstOrCreate(
                    [
                        'etablissement_id' => $etablissementId,
                        'nom' => $defaut['nom'],
                    ],
                    [
                        'description' => $defaut['description'],
                        'is_active' => true,
                    ]
                );
            }
        }

        return static::withoutGlobalScopes()
            ->where('etablissement_id', $etablissementId)
            ->where('is_active', true)
            ->withCount('typesFrais')
            ->orderBy('id')
            ->get();
    }
}

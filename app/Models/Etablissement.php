<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle représentant un Établissement scolaire (campus physique).
 *
 * Représente une entité scolaire spécifique (ex: Collège, Lycée, École primaire)
 * rattachée à une institution mère, avec ses coordonnées, arrêté de création et sections.
 *
 * @property int $id
 * @property int $institution_id
 * @property string $code
 * @property string $nom
 * @property string|null $nom_en
 * @property string $statut_juridique
 * @property string $ordre_enseignement
 * @property string|null $decret_creation
 * @property string|null $nom_chef_etablissement
 * @property string|null $email
 * @property string|null $telephone_1
 * @property string|null $telephone_2
 * @property string|null $adresse
 * @property string|null $logo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Institution $institution
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Section> $sections
 */
class Etablissement extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'code',
        'nom',
        'nom_en',
        'statut_juridique',
        'ordre_enseignement',
        'decret_creation',
        'nom_chef_etablissement',
        'email',
        'telephone_1',
        'telephone_2',
        'adresse',
        'logo',
        'mode_bulletin_defaut',
        'type_calcul_trimestre',
        'seuil_absences_decheance_mentions',
        'verrou_solvabilite_actif',
        'bonus_paiement_anticipe_actif',
        'montant_bonus_anticipe',
        'date_limite_bonus',
    ];

    /**
     * Les attributs convertis dans des types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'seuil_absences_decheance_mentions' => 'integer',
        'verrou_solvabilite_actif' => 'boolean',
        'bonus_paiement_anticipe_actif' => 'boolean',
        'montant_bonus_anticipe' => 'float',
        'date_limite_bonus' => 'date',
    ];

    /**
     * Relation plusieurs-à-un vers l'Institution parente.
     *
     * @return BelongsTo
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Relation un-à-plusieurs vers les sections d'enseignement.
     *
     * @return HasMany
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Relation un-à-plusieurs vers les séries configurées pour cet établissement.
     *
     * @return HasMany
     */
    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    /**
     * Membres du personnel affectés à cet établissement.
     *
     * @return HasMany
     */
    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class, 'etablissement_id');
    }

    /**
     * Référentiel des types de frais de cet établissement.
     *
     * @return HasMany<TypeFrais>
     */
    public function typesFrais(): HasMany
    {
        return $this->hasMany(TypeFrais::class);
    }

    /**
     * Tarifs et échéanciers scolaires de l'établissement.
     *
     * @return HasMany<FraisScolaire>
     */
    public function fraisScolaires(): HasMany
    {
        return $this->hasMany(FraisScolaire::class);
    }

    /**
     * Comptes financiers des élèves de l'établissement.
     *
     * @return HasMany<CompteFinancierEleve>
     */
    public function comptesFinanciers(): HasMany
    {
        return $this->hasMany(CompteFinancierEleve::class);
    }

    /**
     * Clôtures journalières de caisse de l'établissement.
     *
     * @return HasMany<ClotureCaisse>
     */
    public function cloturesCaisse(): HasMany
    {
        return $this->hasMany(ClotureCaisse::class);
    }

    /**
     * Périodes et trimestres d'évaluation rattachés à cet établissement.
     *
     * @return HasMany<Periode>
     */
    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class, 'etablissement_id')->orderBy('ordre');
    }
}

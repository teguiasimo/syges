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
        'seuil_classement_actif',
        'mode_seuil_classement',
        'seuil_classement_valeur',
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
        'seuil_classement_actif' => 'boolean',
        'seuil_classement_valeur' => 'float',
    ];

    /**
     * Les accesseurs personnalisés automatiquement ajoutés aux tableaux et JSON.
     *
     * @var list<string>
     */
    protected $appends = [
        'effective_logo',
        'logo_url',
        'titre_chef',
        'titre_chef_en',
    ];

    /**
     * Détermine dynamiquement le titre officiel du chef d'établissement selon le type d'établissement (Lycée, Collège, École).
     *
     * Pour un Lycée : 'Le Proviseur'
     * Pour un Collège : 'Le Principal'
     * Pour une École primaire ou maternelle : 'Le Directeur'
     * Par défaut : 'Le Chef d’Établissement'
     *
     * @return string Titre officiel en français
     */
    public function getTitreChefAttribute(): string
    {
        $nomLower = mb_strtolower($this->nom ?? '', 'UTF-8');
        $ordre = $this->ordre_enseignement ?? '';

        if (str_contains($nomLower, 'lycée') || str_contains($nomLower, 'lycee')) {
            return 'Le Proviseur';
        }

        if (str_contains($nomLower, 'collège') || str_contains($nomLower, 'college')) {
            return 'Le Principal';
        }

        if (str_contains($nomLower, 'école') || str_contains($nomLower, 'ecole') || in_array($ordre, ['Maternelle', 'Primaire'])) {
            return 'Le Directeur';
        }

        return 'Le Chef d’Établissement';
    }

    /**
     * Détermine le titre officiel du chef d'établissement en anglais.
     *
     * @return string Titre officiel en anglais
     */
    public function getTitreChefEnAttribute(): string
    {
        $titreFr = $this->titre_chef;

        return match ($titreFr) {
            'Le Proviseur' => 'The Principal',
            'Le Principal' => 'The Principal',
            'Le Directeur' => 'The Headmaster',
            default => 'The Head of Institution',
        };
    }

    /**
     * Accesseur pour le logo effectif du campus avec mécanisme de fallback automatique.
     *
     * Chaque établissement est autonome et peut disposer de son propre logo.
     * Si l'établissement ne possède pas de logo spécifique, le logo officiel
     * de l'institution faîtière parente est automatiquement utilisé pour tous les états imprimables.
     *
     * @return string|null Chemin relatif ou URL du logo effectif.
     */
    public function getEffectiveLogoAttribute(): ?string
    {
        if (! empty($this->attributes['logo'] ?? null)) {
            return $this->attributes['logo'];
        }

        return $this->institution?->logo;
    }

    /**
     * Accesseur standardisé pour l'URL du logo (compatibilité avec les vues PDF et états officiels).
     *
     * @return string|null URL ou chemin du logo avec repli automatique sur l'institution.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->effective_logo;
    }

    /**
     * Calcule le seuil minimal de coefficients requis pour qu'un élève soit classé dans la classe.
     *
     * @param float $totalCoefficientsClasse Total théorique des coefficients de la classe
     * @return float Seuil minimal requis en coefficients
     */
    public function getSeuilCoefficientsMinimal(float $totalCoefficientsClasse): float
    {
        if (! ($this->seuil_classement_actif ?? true)) {
            return 0.0;
        }

        $mode = $this->mode_seuil_classement ?? 'pourcentage';
        $valeur = (float) ($this->seuil_classement_valeur ?? 60.0);

        if ($mode === 'valeur_fixe') {
            return min($valeur, $totalCoefficientsClasse);
        }

        // Par défaut : pourcentage du total de la classe (ex: 60%)
        return round(($totalCoefficientsClasse * $valeur) / 100.0, 2);
    }

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

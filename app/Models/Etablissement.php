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
        'titre_chef',
        'titre_chef_en',
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
        'delegation_regionale',
        'delegation_regionale_en',
        'delegation_departementale',
        'delegation_departementale_en',
        'arrondissement',
        'arrondissement_en',
        'devise',
        'boite_postale',
        'ministere_tutelle',
        'ministere_tutelle_en',
        'ville',
        'sigle',
    ];

    /**
     * Détermine dynamiquement le titre officiel du chef d'établissement selon la saisie ou le type d'établissement.
     *
     * Si une valeur personnalisée a été configurée dans les paramètres de l'établissement, elle est prioritaire.
     * Sinon, le titre est déduit intelligemment du nom ou de l'ordre d'enseignement :
     * - Pour un CES (Collège d'Enseignement Secondaire) ou CETIC : 'Le Directeur'
     * - Pour un Lycée : 'Le Proviseur'
     * - Pour un Collège classique/privé : 'Le Principal'
     * - Pour une École primaire ou maternelle : 'Le Directeur'
     * - Par défaut : 'Le Chef d’Établissement'
     *
     * @param string|null $value Valeur brute stockée en base de données.
     * @return string Titre officiel en français
     */
    public function getTitreChefAttribute(?string $value = null): string
    {
        if (!empty($value)) {
            return $value;
        }

        $nomLower = mb_strtolower($this->nom ?? '', 'UTF-8');
        $ordre = $this->ordre_enseignement ?? '';

        // Détection prioritaire CES (Collège d'Enseignement Secondaire) et CETIC (dirigés par un Directeur)
        if (
            str_contains($nomLower, 'ces ') ||
            str_starts_with($nomLower, 'ces ') ||
            str_contains($nomLower, 'ces-') ||
            str_contains($nomLower, 'c.e.s') ||
            str_contains($nomLower, 'cetic') ||
            str_contains($nomLower, 'collège d\'enseignement secondaire') ||
            str_contains($nomLower, 'college d\'enseignement secondaire') ||
            str_contains($nomLower, 'collège d’enseignement secondaire') ||
            str_contains($nomLower, 'collège d\'enseignement technique') ||
            str_contains($nomLower, 'college d\'enseignement technique') ||
            str_contains($nomLower, 'collège d’enseignement technique')
        ) {
            return 'Le Directeur';
        }

        // Lycées -> Le Proviseur
        if (str_contains($nomLower, 'lycée') || str_contains($nomLower, 'lycee')) {
            return 'Le Proviseur';
        }

        // Collèges classiques ou confessionnels -> Le Principal
        if (str_contains($nomLower, 'collège') || str_contains($nomLower, 'college')) {
            return 'Le Principal';
        }

        // Écoles maternelles ou primaires -> Le Directeur
        if (str_contains($nomLower, 'école') || str_contains($nomLower, 'ecole') || in_array($ordre, ['Maternelle', 'Primaire'])) {
            return 'Le Directeur';
        }

        return 'Le Chef d’Établissement';
    }

    /**
     * Détermine le titre officiel du chef d'établissement en anglais.
     *
     * @param string|null $value Valeur brute stockée en base de données.
     * @return string Titre officiel en anglais
     */
    public function getTitreChefEnAttribute(?string $value = null): string
    {
        if (!empty($value)) {
            return $value;
        }

        $titreFr = $this->titre_chef;

        return match ($titreFr) {
            'Le Proviseur' => 'The Principal',
            'Le Principal' => 'The Principal',
            'Le Directeur' => 'The Director',
            'La Directrice' => 'The Directress',
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
     * Accesseur pour la Délégation Régionale de tutelle (avec repli automatique sur l'institution parente).
     *
     * @return string|null
     */
    public function getDelegationRegionaleAttribute(): ?string
    {
        return $this->attributes['delegation_regionale'] ?? $this->institution?->delegation_regionale;
    }

    /**
     * Accesseur pour la Délégation Régionale de tutelle en anglais (avec repli sur l'institution parente).
     *
     * @return string|null
     */
    public function getDelegationRegionaleEnAttribute(): ?string
    {
        return $this->attributes['delegation_regionale_en'] ?? $this->institution?->delegation_regionale_en;
    }

    /**
     * Accesseur pour la Délégation Départementale de tutelle (avec repli sur l'institution parente).
     *
     * @return string|null
     */
    public function getDelegationDepartementaleAttribute(): ?string
    {
        return $this->attributes['delegation_departementale'] ?? $this->institution?->delegation_departementale;
    }

    /**
     * Accesseur pour la Délégation Départementale de tutelle en anglais (avec repli sur l'institution parente).
     *
     * @return string|null
     */
    public function getDelegationDepartementaleEnAttribute(): ?string
    {
        return $this->attributes['delegation_departementale_en'] ?? $this->institution?->delegation_departementale_en;
    }

    /**
     * Accesseur pour l'Arrondissement de rattachement (avec repli sur l'institution parente).
     *
     * @return string|null
     */
    public function getArrondissementAttribute(): ?string
    {
        return $this->attributes['arrondissement'] ?? $this->institution?->arrondissement;
    }

    /**
     * Accesseur pour l'Arrondissement de rattachement en anglais (avec repli sur l'institution parente).
     *
     * @return string|null
     */
    public function getArrondissementEnAttribute(): ?string
    {
        return $this->attributes['arrondissement_en'] ?? $this->institution?->arrondissement_en;
    }

    /**
     * Accesseur pour la devise de l'établissement (avec repli sur l'institution parente).
     *
     * @return string|null
     */
    public function getDeviseAttribute(): ?string
    {
        return $this->attributes['devise'] ?? $this->institution?->devise;
    }

    /**
     * Accesseur pour la boîte postale de l'établissement (avec repli sur l'institution parente).
     *
     * @return string|null
     */
    public function getBoitePostaleAttribute(): ?string
    {
        return $this->attributes['boite_postale'] ?? $this->institution?->boite_postale;
    }

    /**
     * Accesseur pour le Ministère de tutelle en français selon l'ordre d'enseignement.
     *
     * @return string
     */
    public function getMinistereTutelleAttribute(): string
    {
        $ordre = $this->ordre_enseignement ?? '';
        if (in_array($ordre, ['Maternelle', 'Primaire'])) {
            return "MINISTÈRE DE L'ÉDUCATION DE BASE";
        }
        return 'MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES';
    }

    /**
     * Accesseur pour le Ministère de tutelle en anglais selon l'ordre d'enseignement.
     *
     * @return string
     */
    public function getMinistereTutelleEnAttribute(): string
    {
        $ordre = $this->ordre_enseignement ?? '';
        if (in_array($ordre, ['Maternelle', 'Primaire'])) {
            return 'MINISTRY OF BASIC EDUCATION';
        }
        return 'MINISTRY OF SECONDARY EDUCATION';
    }

    /**
     * Accesseur pour la ville de signature officielle de l'établissement (avec repli automatique sur l'institution).
     *
     * @return string
     */
    public function getVilleAttribute(): string
    {
        return $this->attributes['ville']
            ?? $this->institution?->ville
            ?? $this->institution?->arrondissement
            ?? 'Fongo-Tongo';
    }

    /**
     * Accesseur pour le sigle usuel de l'établissement.
     * Renvoie en priorité le code court de l'établissement (ex: 'CES MEGUEU'),
     * ou une abréviation déduite de son nom si le code n'est pas spécifié.
     *
     * @return string
     */
    public function getSigleAttribute(): string
    {
        if (! empty($this->code)) {
            return $this->code;
        }

        // Si aucun code n'est configuré, déduit le sigle depuis le nom
        return $this->nom ? preg_replace('/\b(DE|DU|DES|D\'|ET|LA|LE|LES)\b/i', '', $this->nom) : 'CES MEGUEU';
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

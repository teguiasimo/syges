<?php

namespace App\Models;

use App\Scopes\EtablissementScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property string|null $titre_chef
 * @property string|null $titre_chef_en
 * @property string|null $titre_responsable_classe
 * @property string|null $titre_responsable_classe_en
 * @property string|null $email
 * @property string|null $telephone_1
 * @property string|null $telephone_2
 * @property string|null $adresse
 * @property string|null $logo
 * @property string|null $logo_filigrane
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Institution $institution
 * @property-read Collection<int, Section> $sections
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
        'titre_responsable_classe',
        'titre_responsable_classe_en',
        'email',
        'telephone_1',
        'telephone_2',
        'adresse',
        'logo',
        'logo_filigrane',
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
        'effective_filigrane',
        'filigrane_url',
        'titre_chef',
        'titre_chef_en',
        'titre_responsable_classe',
        'titre_responsable_classe_en',
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
     * @param  string|null  $value  Valeur brute stockée en base de données.
     * @return string Titre officiel en français
     */
    public function getTitreChefAttribute(?string $value = null): string
    {
        if (! empty($value)) {
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
     * @param  string|null  $value  Valeur brute stockée en base de données.
     * @return string Titre officiel en anglais
     */
    public function getTitreChefEnAttribute(?string $value = null): string
    {
        if (! empty($value)) {
            return $value;
        }

        $titreFr = $this->titre_chef;

        return match ($titreFr) {
            'Le Proviseur' => 'The Principal',
            'Le Principal' => 'The Principal',
            'Le Directeur' => 'The Director',
            'La Directrice' => 'The Headmistress',
            default => 'The Head of Institution',
        };
    }

    /**
     * Détermine dynamiquement le libellé du responsable pédagogique d'une classe en français.
     *
     * Permet à chaque établissement d'utiliser sa propre terminologie :
     * - Maternelle / Primaire (MINEDUB) : « Le Maître / La Maîtresse »
     * - Secondaire (MINESEC) : « Le Professeur Principal »
     *
     * @param  string|null  $value  Valeur personnalisée configurée en base de données.
     * @return string Libellé officiel du responsable de classe.
     */
    public function getTitreResponsableClasseAttribute(?string $value = null): string
    {
        if (! empty($value)) {
            return $value;
        }

        $ordre = $this->ordre_enseignement ?? '';
        if (in_array($ordre, ['Maternelle', 'Primaire'], true)) {
            return 'Le Maître / La Maîtresse';
        }

        return 'Le Professeur Principal';
    }

    /**
     * Détermine dynamiquement le libellé du responsable pédagogique d'une classe en anglais.
     *
     * @param  string|null  $value  Valeur personnalisée configurée en base de données.
     * @return string Libellé officiel en anglais.
     */
    public function getTitreResponsableClasseEnAttribute(?string $value = null): string
    {
        if (! empty($value)) {
            return $value;
        }

        $ordre = $this->ordre_enseignement ?? '';
        if (in_array($ordre, ['Maternelle', 'Primaire'], true)) {
            return 'Class Teacher';
        }

        return 'Class Master';
    }

    /**
     * Cache statique par requête pour l'institution parente (évite les requêtes N+1 lors de la sérialisation JSON).
     *
     * @var array<int, Institution|null>
     */
    protected static array $institutionFallbackCache = [];

    /**
     * Cache statique par requête pour le chemin du logo public par défaut (évite les appels disque file_exists répétés).
     */
    protected static ?string $defaultPublicLogoCache = null;

    /**
     * Indique si la vérification disque du logo public par défaut a déjà été effectuée durant la requête.
     */
    protected static bool $defaultPublicLogoResolved = false;

    /**
     * Réinitialise le cache statique de l'institution parente (utile lors des mises à jour ou tests).
     */
    public static function clearFallbackCache(): void
    {
        self::$institutionFallbackCache = [];
    }

    /**
     * Résout l'institution parente sans déclencher de requêtes SQL N+1 lorsque la relation n'est pas préchargée.
     */
    protected function resolveInstitutionFallback(): ?Institution
    {
        if ($this->relationLoaded('institution')) {
            return $this->getRelation('institution');
        }

        $instId = (int) ($this->attributes['institution_id'] ?? 0);
        if ($instId <= 0) {
            return null;
        }

        if (! array_key_exists($instId, self::$institutionFallbackCache)) {
            self::$institutionFallbackCache[$instId] = Institution::find($instId);
        }

        $institution = self::$institutionFallbackCache[$instId];
        $this->setRelation('institution', $institution);

        return $institution;
    }

    /**
     * Accesseur pour le logo filigrane effectif avec repli automatique sur l'institution parente.
     *
     * @return string|null Chemin relatif ou URL du filigrane effectif, ou null s'il n'y en a aucun.
     */
    public function getEffectiveFiligraneAttribute(): ?string
    {
        if (! empty($this->attributes['logo_filigrane'] ?? null)) {
            return $this->attributes['logo_filigrane'];
        }

        $institution = $this->resolveInstitutionFallback();
        if (! empty($institution?->logo_filigrane)) {
            return $institution->logo_filigrane;
        }

        return null;
    }

    /**
     * Accesseur standardisé pour l'URL du filigrane de l'établissement.
     *
     * @return string|null URL ou chemin du filigrane effectif.
     */
    public function getFiligraneUrlAttribute(): ?string
    {
        return $this->effective_filigrane;
    }

    /**
     * Accesseur pour le logo effectif du campus avec mécanisme de fallback automatique.
     *
     * Chaque établissement est autonome et peut disposer de son propre logo.
     * Si l'établissement ne possède pas de logo spécifique, le logo officiel
     * de l'institution faîtière parente ou le logo par défaut est automatiquement utilisé pour tous les états imprimables.
     *
     * @return string|null Chemin relatif ou URL du logo effectif.
     */
    public function getEffectiveLogoAttribute(): ?string
    {
        if (! empty($this->attributes['logo'] ?? null)) {
            return $this->attributes['logo'];
        }

        $institution = $this->resolveInstitutionFallback();
        if (! empty($institution?->logo)) {
            return $institution->logo;
        }

        // Repli par défaut sur le logo officiel configuré (mis en cache en mémoire pour éviter les accès disque répétés)
        if (! self::$defaultPublicLogoResolved) {
            self::$defaultPublicLogoResolved = true;
            if (file_exists(public_path('logo.png'))) {
                self::$defaultPublicLogoCache = '/logo.png';
            } elseif (file_exists(public_path('logo.jpg'))) {
                self::$defaultPublicLogoCache = '/logo.jpg';
            } else {
                self::$defaultPublicLogoCache = null;
            }
        }

        return self::$defaultPublicLogoCache;
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
     * Retourne le logo effectif encodé en base64 pour une intégration directe et infaillible dans DomPDF.
     * Élimine les dépendances réseau ou chemins relatifs lors de la génération des états imprimables.
     *
     * @return string|null Chaîne data URI (ex: data:image/png;base64,...) ou null.
     */
    public function getEffectiveLogoBase64(): ?string
    {
        $candidates = array_filter([
            $this->logo_url,
            $this->attributes['logo'] ?? null,
            $this->institution?->logo,
            '/logo.png',
            '/logo.jpg',
        ]);

        foreach ($candidates as $candidate) {
            $encoded = Institution::resolveImageBase64($candidate);
            if ($encoded !== null) {
                return $encoded;
            }
        }

        return null;
    }

    /**
     * Retourne le filigrane effectif de l'établissement encodé en Base64 pour DomPDF.
     *
     * Vérifie d'abord le filigrane propre à l'établissement, puis celui de l'institution faîtière.
     * Aucun repli invasif n'est appliqué s'il n'est pas configuré afin de préserver la sobriété des états.
     *
     * @return string|null Chaîne data URI Base64 ou null.
     */
    public function getEffectiveFiligraneBase64(): ?string
    {
        $candidates = array_filter([
            $this->attributes['logo_filigrane'] ?? null,
            $this->institution?->logo_filigrane,
        ]);

        foreach ($candidates as $candidate) {
            $encoded = Institution::resolveImageBase64($candidate);
            if ($encoded !== null) {
                return $encoded;
            }
        }

        return null;
    }

    /**
     * Calcule le seuil minimal de coefficients requis pour qu'un élève soit classé dans la classe.
     *
     * @param  float  $totalCoefficientsClasse  Total théorique des coefficients de la classe
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
     */
    public function getDelegationRegionaleAttribute(): ?string
    {
        return $this->attributes['delegation_regionale'] ?? $this->resolveInstitutionFallback()?->delegation_regionale;
    }

    /**
     * Accesseur pour la Délégation Régionale de tutelle en anglais (avec repli sur l'institution parente).
     */
    public function getDelegationRegionaleEnAttribute(): ?string
    {
        return $this->attributes['delegation_regionale_en'] ?? $this->resolveInstitutionFallback()?->delegation_regionale_en;
    }

    /**
     * Accesseur pour la Délégation Départementale de tutelle (avec repli sur l'institution parente).
     */
    public function getDelegationDepartementaleAttribute(): ?string
    {
        return $this->attributes['delegation_departementale'] ?? $this->resolveInstitutionFallback()?->delegation_departementale;
    }

    /**
     * Accesseur pour la Délégation Départementale de tutelle en anglais (avec repli sur l'institution parente).
     */
    public function getDelegationDepartementaleEnAttribute(): ?string
    {
        return $this->attributes['delegation_departementale_en'] ?? $this->resolveInstitutionFallback()?->delegation_departementale_en;
    }

    /**
     * Accesseur pour l'Arrondissement de rattachement (avec repli sur l'institution parente).
     */
    public function getArrondissementAttribute(): ?string
    {
        return $this->attributes['arrondissement'] ?? $this->resolveInstitutionFallback()?->arrondissement;
    }

    /**
     * Accesseur pour l'Arrondissement de rattachement en anglais (avec repli sur l'institution parente).
     */
    public function getArrondissementEnAttribute(): ?string
    {
        return $this->attributes['arrondissement_en'] ?? $this->resolveInstitutionFallback()?->arrondissement_en;
    }

    /**
     * Accesseur pour la devise de l'établissement (avec repli sur l'institution parente).
     */
    public function getDeviseAttribute(): ?string
    {
        return $this->attributes['devise'] ?? $this->resolveInstitutionFallback()?->devise;
    }

    /**
     * Accesseur pour la boîte postale de l'établissement (avec repli sur l'institution parente).
     */
    public function getBoitePostaleAttribute(): ?string
    {
        return $this->attributes['boite_postale'] ?? $this->resolveInstitutionFallback()?->boite_postale;
    }

    /**
     * Accesseur pour le Ministère de tutelle en français selon l'ordre d'enseignement.
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
     */
    public function getVilleAttribute(): string
    {
        $institution = $this->resolveInstitutionFallback();

        return $this->attributes['ville']
            ?? $institution?->ville
            ?? $institution?->arrondissement
            ?? '';
    }

    /**
     * Accesseur pour le sigle usuel de l'établissement.
     * Renvoie en priorité le code court de l'établissement (ex: 'GSB ESPOIR'),
     * ou une abréviation déduite de son nom si le code n'est pas spécifié.
     */
    public function getSigleAttribute(): string
    {
        if (! empty($this->code)) {
            return $this->code;
        }

        // Si aucun code n'est configuré, déduit le sigle depuis le nom
        return $this->nom ? preg_replace('/\b(DE|DU|DES|D\'|ET|LA|LE|LES)\b/i', '', $this->nom) : 'CAMPUS';
    }

    /**
     * Événements du cycle de vie du modèle.
     * Verrouille également toute suppression programmatique directe d'un établissement contenant des données actives.
     */
    protected static function booted(): void
    {
        static::deleting(function (Etablissement $etablissement): void {
            if (! $etablissement->canBeDeleted()) {
                throw new \DomainException("L'établissement « {$etablissement->nom} » ne peut pas être supprimé car il contient encore des données actives.");
            }
        });
    }

    /**
     * Relation plusieurs-à-un vers l'Institution parente.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Relation un-à-plusieurs vers les sections d'enseignement.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Relation un-à-plusieurs vers les cycles pédagogiques rattachés à cet établissement.
     *
     * @return HasMany<Cycle, $this>
     */
    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class, 'etablissement_id');
    }

    /**
     * Relation un-à-plusieurs vers les niveaux d'étude rattachés à cet établissement.
     *
     * @return HasMany<Niveau, $this>
     */
    public function niveaux(): HasMany
    {
        return $this->hasMany(Niveau::class, 'etablissement_id');
    }

    /**
     * Relation un-à-plusieurs vers les classes rattachées à cet établissement.
     *
     * @return HasMany<Classe, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class, 'etablissement_id');
    }

    /**
     * Relation un-à-plusieurs vers les inscriptions d'élèves rattachées à cet établissement.
     *
     * @return HasMany<Inscription, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'etablissement_id');
    }

    /**
     * Relation un-à-plusieurs vers les séries configurées pour cet établissement.
     */
    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    /**
     * Membres du personnel affectés à cet établissement.
     */
    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class, 'etablissement_id');
    }

    /**
     * Comptes utilisateurs directement rattachés à cet établissement.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'etablissement_id');
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
     * Dépenses et décaissements de caisse rattachés à cet établissement.
     *
     * @return HasMany<Depense, $this>
     */
    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class, 'etablissement_id');
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

    /**
     * Indique si l'établissement est totalement vierge et peut être supprimé sans perte de données.
     *
     * @return bool Vrai uniquement lorsqu'aucune dépendance pédagogique, RH ou financière n'existe.
     */
    public function canBeDeleted(): bool
    {
        return empty($this->getDeletionBlockers());
    }

    /**
     * Recense l'ensemble des dépendances actives bloquant la suppression de cet établissement
     * en contournant uniquement `EtablissementScope` (tout en conservant `SoftDeletingScope`
     * pour ne pas bloquer sur des enregistrements déjà supprimés logiquement).
     *
     * @return array<string, int> Tableau associatif `[type_dependance => nombre_elements]` non nuls.
     */
    public function getDeletionBlockers(): array
    {
        $counts = [
            'sections' => $this->sections()->count(),
            'cycles' => Cycle::where('etablissement_id', $this->id)->count(),
            'niveaux' => Niveau::where('etablissement_id', $this->id)->count(),
            'classes' => Classe::withoutGlobalScope(EtablissementScope::class)->where('etablissement_id', $this->id)->count(),
            'inscriptions' => Inscription::withoutGlobalScope(EtablissementScope::class)->where('etablissement_id', $this->id)->count(),
            'personnels' => Personnel::withoutGlobalScope(EtablissementScope::class)->where('etablissement_id', $this->id)->count(),
            'users' => User::where('etablissement_id', $this->id)->count(),
            'periodes' => Periode::withoutGlobalScope(EtablissementScope::class)->where('etablissement_id', $this->id)->count(),
            'frais_scolaires' => FraisScolaire::withoutGlobalScope(EtablissementScope::class)->where('etablissement_id', $this->id)->count(),
            'comptes_financiers' => CompteFinancierEleve::withoutGlobalScope(EtablissementScope::class)->where('etablissement_id', $this->id)->count(),
            'depenses' => Depense::withoutGlobalScope(EtablissementScope::class)->where('etablissement_id', $this->id)->count(),
        ];

        return array_filter($counts, static fn (int $count): bool => $count > 0);
    }
}

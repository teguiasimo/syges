<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Modèle représentant un Élève (Base Élève Globale et Pérenne).
 * Centralise l'identité civile, familiale et médicale de l'élève,
 * indépendamment des années scolaires et des établissements fréquentés.
 *
 * @property int $id
 * @property string $matricule Identifiant unique interne
 * @property string|null $matricule_national Identifiant national MINESEC/MINEDUB
 * @property string $nom Nom de famille
 * @property string|null $prenom Prénom(s)
 * @property string|null $sexe 'M', 'F' ou null (Non défini / À compléter)
 * @property Carbon|null $date_naissance
 * @property string|null $lieu_naissance
 * @property string $nationalite Nationalité (par défaut CAMEROUNAISE)
 * @property string|null $photo_url Chemin de la photo d'identité
 * @property string|null $nom_pere
 * @property string|null $nom_mere
 * @property string|null $nom_tuteur
 * @property string|null $telephone_parent Contact téléphonique principal du tuteur
 * @property string|null $email_parent
 * @property string|null $adresse Quartier ou adresse physique
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $nom_complet
 * @property-read Collection<int, Inscription> $inscriptions
 */
class Eleve extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse (conformes à la vue create.tsx).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'matricule',
        'matricule_national',
        'nom',
        'prenom',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'photo_url',
        'nom_pere',
        'nom_mere',
        'nom_tuteur',
        'telephone_parent',
        'email_parent',
        'adresse',
        'is_active',
    ];

    /**
     * Les attributs convertis en types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_naissance' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Attributs virtuels ajoutés lors de la sérialisation JSON.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'nom_complet',
    ];

    /**
     * Accesseur retournant le nom complet formaté de l'élève.
     */
    public function getNomCompletAttribute(): string
    {
        return trim($this->nom.' '.($this->prenom ?? ''));
    }

    /**
     * Relation vers toutes les inscriptions scolaires de l'élève au fil des années.
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'eleve_id')->orderByDesc('date_inscription');
    }

    /**
     * Inscription la plus récente de l'élève.
     */
    public function latestInscription(): HasOne
    {
        return $this->hasOne(Inscription::class, 'eleve_id')->latestOfMany();
    }

    /**
     * Classes fréquentées par l'élève à travers ses inscriptions.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'inscriptions', 'eleve_id', 'classe_id')
            ->withPivot(['annee_scolaire_id', 'statut', 'type_inscription', 'is_redoublant', 'date_inscription'])
            ->withTimestamps();
    }

    /**
     * Relation vers tous les comptes financiers scolaires rattachés à l'élève.
     *
     * @return HasMany<CompteFinancierEleve>
     */
    public function comptesFinanciers(): HasMany
    {
        return $this->hasMany(CompteFinancierEleve::class, 'eleve_id');
    }

    /**
     * Vérifie si l'élève possède des données liées actives (financières, pédagogiques ou disciplinaires)
     * qui interdisent sa suppression physique afin de préserver l'intégrité référentielle et comptable.
     *
     * Note métier : un versement préalablement annulé (`statut = 'annule'`) ou une option
     * résiliée (`statut = 'annule'`) ne bloquent pas la suppression d'un dossier saisi par erreur.
     *
     * @return array<int, string> Liste lisible des dépendances bloquantes détectées (vide si supprimable)
     */
    public function getDeletionBlockers(): array
    {
        $inscriptionIds = Inscription::withoutGlobalScopes()
            ->where('eleve_id', $this->id)
            ->pluck('id');

        $compteIds = CompteFinancierEleve::withoutGlobalScopes()
            ->where('eleve_id', $this->id)
            ->pluck('id');

        $blockers = [];

        // 1. Contrôle financier : seuls les versements valides, bourses, dispenses, options actives et pénalités actives bloquent
        $nbVersementsValides = VersementCaisse::withoutGlobalScopes()
            ->where('statut', 'valide')
            ->where(function (Builder $q) use ($inscriptionIds, $compteIds) {
                if ($inscriptionIds->isNotEmpty()) {
                    $q->whereIn('inscription_id', $inscriptionIds);
                }
                if ($compteIds->isNotEmpty()) {
                    $q->orWhereIn('compte_financier_id', $compteIds);
                }
            })
            ->when($inscriptionIds->isEmpty() && $compteIds->isEmpty(), fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->count();

        if ($nbVersementsValides > 0) {
            $blockers[] = "{$nbVersementsValides} versement(s) financier(s) valide(s) (annulez-le(s) d'abord dans Finances)";
        }

        if ($compteIds->isNotEmpty()) {
            $nbBourses = EleveBourseRemise::whereIn('compte_financier_id', $compteIds)->count();
            if ($nbBourses > 0) {
                $blockers[] = "{$nbBourses} bourse(s) ou remise(s) attribuée(s)";
            }

            $nbDispensesFin = DispenseFinanciere::withoutGlobalScopes()
                ->whereIn('compte_financier_id', $compteIds)
                ->count();
            if ($nbDispensesFin > 0) {
                $blockers[] = "{$nbDispensesFin} dispense(s) financière(s)";
            }

            $nbOptionsActives = SouscriptionFraisOptionnel::whereIn('compte_financier_id', $compteIds)
                ->where('statut', 'actif')
                ->count();
            if ($nbOptionsActives > 0) {
                $blockers[] = "{$nbOptionsActives} option(s) financière(s) active(s)";
            }

            $nbPenalitesActives = PenaliteEleve::whereIn('compte_financier_id', $compteIds)
                ->where('statut', '!=', 'annule')
                ->count();
            if ($nbPenalitesActives > 0) {
                $blockers[] = "{$nbPenalitesActives} pénalité(s) de retard active(s)";
            }
        }

        // 2. Contrôle pédagogique & disciplinaire sur les inscriptions
        if ($inscriptionIds->isNotEmpty()) {
            $nbNotes = Note::whereIn('inscription_id', $inscriptionIds)->count();
            if ($nbNotes > 0) {
                $blockers[] = "{$nbNotes} note(s) ou cote(s) d'évaluation";
            }

            $nbAssiduites = Assiduite::whereIn('inscription_id', $inscriptionIds)->count();
            if ($nbAssiduites > 0) {
                $blockers[] = "{$nbAssiduites} relevé(s) d'assiduité";
            }

            $nbSanctions = SanctionDisciplinaire::whereIn('inscription_id', $inscriptionIds)->count();
            if ($nbSanctions > 0) {
                $blockers[] = "{$nbSanctions} sanction(s) disciplinaire(s)";
            }

            $nbConsignes = HeureConsigne::withoutGlobalScopes()
                ->whereIn('inscription_id', $inscriptionIds)
                ->count();
            if ($nbConsignes > 0) {
                $blockers[] = "{$nbConsignes} heure(s) de consigne";
            }

            $nbDisciplinesBulletins = DisciplineBulletin::whereIn('inscription_id', $inscriptionIds)
                ->where(function (Builder $q) {
                    $q->where('absences_justifiees', '>', 0)
                        ->orWhere('absences_non_justifiees', '>', 0)
                        ->orWhere('retards', '>', 0)
                        ->orWhere('consignes', '>', 0)
                        ->orWhere('avertissements_conduite', '>', 0)
                        ->orWhere('blames_conduite', '>', 0)
                        ->orWhere('exclusions_jours', '>', 0)
                        ->orWhereNotNull('decision_conseil');
                })
                ->count();
            if ($nbDisciplinesBulletins > 0) {
                $blockers[] = "{$nbDisciplinesBulletins} synthèse(s) disciplinaire(s) de bulletin";
            }

            $nbDeliberations = DeliberationConseil::whereIn('inscription_id', $inscriptionIds)->count();
            if ($nbDeliberations > 0) {
                $blockers[] = "{$nbDeliberations} délibération(s) de conseil";
            }
        }

        $nbDispensesPedago = DispenseEleve::withoutGlobalScopes()
            ->where('eleve_id', $this->id)
            ->count();
        if ($nbDispensesPedago > 0) {
            $blockers[] = "{$nbDispensesPedago} dispense(s) pédagogique(s)";
        }

        $nbConseilsDiscipline = DossierConseilDiscipline::where('eleve_id', $this->id)->count();
        if ($nbConseilsDiscipline > 0) {
            $blockers[] = "{$nbConseilsDiscipline} dossier(s) de conseil de discipline";
        }

        return $blockers;
    }

    /**
     * Historique des fusions de doublons absorbés dans ce dossier Maître.
     *
     * @return HasMany<EleveFusionAudit>
     */
    public function fusionsAudit(): HasMany
    {
        return $this->hasMany(EleveFusionAudit::class, 'master_eleve_id');
    }

    /**
     * Scope pour rechercher des élèves par nom, prénom, matricule, ancien matricule fusionné ou téléphone.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('nom', 'like', $term)
                ->orWhere('prenom', 'like', $term)
                ->orWhere('matricule', 'like', $term)
                ->orWhere('matricule_national', 'like', $term)
                ->orWhere('telephone_parent', 'like', $term)
                ->orWhereHas('fusionsAudit', function (Builder $fq) use ($term) {
                    $fq->where('absorbed_matricule', 'like', $term)
                        ->orWhere('absorbed_nom_complet', 'like', $term);
                });
        });
    }

    /**
     * Scope pour filtrer les élèves actifs.
     */
    public function scopeActif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Génère un matricule unique pour un nouvel élève selon la nomenclature de l'établissement.
     *
     * Format officiel structuré : `{CODE_ETAB}-{YYYY}-{0001}`
     * (ex : `ESP-PRI-2026-0283` ou `ESP-MAT-2026-0222`).
     * Identifie instantanément le campus/ordre d'enseignement, l'année d'inscription (4 chiffres)
     * et le numéro séquentiel incrémental unique.
     *
     * @param  int|null  $anneeScolaireId  Identifiant de l'année scolaire pour extraire les 4 chiffres
     * @param  string|null  $prefix  Préfixe explicite optionnel (prioritaire sur le code campus)
     * @param  int|null  $etablissementId  Identifiant du campus d'inscription pour récupérer son code officiel
     * @return string Le matricule unique formaté
     */
    public static function generateNextMatricule(
        ?int $anneeScolaireId = null,
        ?string $prefix = null,
        ?int $etablissementId = null
    ): string {
        // 1. Détermine l'année d'inscription sur 4 chiffres depuis l'année scolaire ou l'année active
        $annee = $anneeScolaireId ? AnneeScolaire::find($anneeScolaireId) : AnneeScolaire::current();
        if (! $annee) {
            $annee = AnneeScolaire::current();
        }

        $yearCode = null;
        if ($annee?->date_debut) {
            $yearCode = $annee->date_debut->format('Y');
        } elseif ($annee?->code && preg_match('/(\d{4})/', (string) $annee->code, $yearMatches)) {
            $yearCode = $yearMatches[1];
        } else {
            $yearCode = Carbon::now()->format('Y');
        }

        // 2. Détermine le code d'établissement / préfixe (ex: "ESP-PRI", "ESP-MAT")
        $etabPrefix = null;
        if (! empty($prefix)) {
            $etabPrefix = strtoupper(trim($prefix));
        } elseif ($etablissementId) {
            $etablissement = Etablissement::find($etablissementId);
            if ($etablissement && ! empty($etablissement->code)) {
                $etabPrefix = strtoupper(trim((string) $etablissement->code));
            } elseif ($etablissement && ! empty($etablissement->sigle)) {
                $etabPrefix = strtoupper(trim((string) $etablissement->sigle));
            }
        }

        $base = ($etabPrefix ?: 'GS').'-'.$yearCode.'-';

        // 3. Recherche le numéro séquentiel numérique maximal existant pour cette nomenclature
        $pattern = '/^'.preg_quote($base, '/').'(\d+)$/';
        $maxNum = 0;

        static::where('matricule', 'like', $base.'%')
            ->pluck('matricule')
            ->each(function (string $matricule) use ($pattern, &$maxNum): void {
                if (preg_match($pattern, $matricule, $matches)) {
                    $num = (int) $matches[1];
                    if ($num > $maxNum) {
                        $maxNum = $num;
                    }
                }
            });

        $nextNum = $maxNum + 1;

        // 4. Boucle de garantie stricte d'unicité en base de données
        do {
            $candidate = sprintf('%s%04d', $base, $nextNum);
            $exists = static::where('matricule', $candidate)->exists();
            if ($exists) {
                $nextNum++;
            }
        } while ($exists);

        return $candidate;
    }
}

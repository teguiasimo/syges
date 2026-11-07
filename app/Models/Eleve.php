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
 * @property string $sexe 'M' ou 'F'
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
     * Vérifie si l'élève possède des données liées (financières, pédagogiques ou disciplinaires)
     * qui interdisent sa suppression physique afin de préserver l'intégrité référentielle et comptable.
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

        // 1. Contrôle financier : versements, bourses/remises, dispenses financières, souscriptions optionnelles
        $nbVersements = VersementCaisse::withoutGlobalScopes()
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

        if ($nbVersements > 0) {
            $blockers[] = "{$nbVersements} versement(s) financier(s)";
        }

        if ($compteIds->isNotEmpty()) {
            $nbBourses = EleveBourseRemise::whereIn('compte_financier_id', $compteIds)->count();
            if ($nbBourses > 0) {
                $blockers[] = "{$nbBourses} bourse(s) ou remise(s) accordée(s)";
            }

            $nbDispensesFin = DispenseFinanciere::withoutGlobalScopes()
                ->whereIn('compte_financier_id', $compteIds)
                ->count();
            if ($nbDispensesFin > 0) {
                $blockers[] = "{$nbDispensesFin} dispense(s) financière(s)";
            }

            $nbOptions = SouscriptionFraisOptionnel::whereIn('compte_financier_id', $compteIds)->count();
            if ($nbOptions > 0) {
                $blockers[] = "{$nbOptions} option(s) souscrite(s)";
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
     * Scope pour rechercher des élèves par nom, prénom, matricule ou téléphone.
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
                ->orWhere('telephone_parent', 'like', $term);
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
     * Génère un matricule unique pour un nouvel élève.
     * Format officiel court et parlant : [YY]-[0001], ex: 26-0001 (pour l'année 2026/2027).
     *
     * @param  int|null  $anneeScolaireId  Identifiant de l'année scolaire pour extraire les 2 chiffres
     * @param  string|null  $prefix  Préfixe optionnel si besoin
     * @return string Le matricule unique formaté
     */
    public static function generateNextMatricule(?int $anneeScolaireId = null, ?string $prefix = null): string
    {
        // 1. Détermine l'année sur 2 chiffres depuis l'année scolaire ou l'année active
        $yearCode = null;
        if ($anneeScolaireId) {
            $annee = AnneeScolaire::find($anneeScolaireId);
            $yearCode = $annee?->date_debut ? $annee->date_debut->format('y') : null;
        }

        if (! $yearCode) {
            $currentAnnee = AnneeScolaire::current();
            $yearCode = $currentAnnee?->date_debut ? $currentAnnee->date_debut->format('y') : Carbon::now()->format('y');
        }

        // 2. Base du matricule : ex: "26-" (ou avec préfixe optionnel si spécifié)
        $base = $prefix ? strtoupper(trim($prefix)).$yearCode.'-' : $yearCode.'-';

        // 3. Trouve le dernier numéro incrémental attribué pour cette base
        $lastRecord = static::where('matricule', 'like', $base.'%')
            ->orderByDesc('matricule')
            ->first();

        $nextNum = 1;
        if ($lastRecord && preg_match('/-(\d+)$/', $lastRecord->matricule, $matches)) {
            $nextNum = ((int) $matches[1]) + 1;
        }

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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $profession_parent
 * @property string|null $adresse Quartier ou adresse physique
 * @property string|null $ville
 * @property string|null $groupe_sanguin
 * @property string|null $contact_urgence_nom
 * @property string|null $contact_urgence_telephone
 * @property string|null $allergies_conditions
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read string $nom_complet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Inscription> $inscriptions
 */
class Eleve extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
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
        'profession_parent',
        'adresse',
        'ville',
        'groupe_sanguin',
        'contact_urgence_nom',
        'contact_urgence_telephone',
        'allergies_conditions',
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
     *
     * @return string
     */
    public function getNomCompletAttribute(): string
    {
        return trim($this->nom . ' ' . ($this->prenom ?? ''));
    }

    /**
     * Relation vers toutes les inscriptions scolaires de l'élève au fil des années.
     *
     * @return HasMany
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'eleve_id')->orderByDesc('date_inscription');
    }

    /**
     * Inscription la plus récente de l'élève.
     *
     * @return HasOne
     */
    public function latestInscription(): HasOne
    {
        return $this->hasOne(Inscription::class, 'eleve_id')->latestOfMany();
    }

    /**
     * Classes fréquentées par l'élève à travers ses inscriptions.
     *
     * @return BelongsToMany
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'inscriptions', 'eleve_id', 'classe_id')
            ->withPivot(['annee_scolaire_id', 'statut', 'type_inscription', 'is_redoublant', 'date_inscription'])
            ->withTimestamps();
    }

    /**
     * Scope pour rechercher des élèves par nom, prénom, matricule ou téléphone.
     *
     * @param Builder $query
     * @param string|null $search
     * @return Builder
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $term = '%' . trim($search) . '%';

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
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Génère un matricule unique pour un nouvel élève.
     * Format par défaut : [PREFIX][YY]-[00001], ex: GS26-0001.
     *
     * @param string $prefix
     * @return string
     */
    public static function generateNextMatricule(string $prefix = 'GS'): string
    {
        $year = Carbon::now()->format('y'); // Ex: 26 pour 2026
        $base = strtoupper($prefix) . $year . '-';

        // Trouve le dernier matricule commençant par cette base
        $lastRecord = static::where('matricule', 'like', $base . '%')
            ->orderByDesc('matricule')
            ->first();

        $nextNum = 1;
        if ($lastRecord && preg_match('/-(\d+)$/', $lastRecord->matricule, $matches)) {
            $nextNum = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%04d', $base, $nextNum);
    }
}

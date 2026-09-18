<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle représentant un Membre du Personnel / Fiche Administrative RH (Module 8 Gesco).
 * 
 * Centralise l'identité civile, contractuelle et pédagogique des employés de l'établissement
 * (corps enseignant, administratif, technique et d'appui).
 *
 * @property int $id
 * @property string $matricule
 * @property int|null $user_id
 * @property int $etablissement_id
 * @property int|null $departement_id
 * @property string $nom
 * @property string|null $prenom
 * @property string $sexe
 * @property \Illuminate\Support\Carbon|null $date_naissance
 * @property string|null $lieu_naissance
 * @property string $telephone
 * @property string|null $telephone_secondaire
 * @property string|null $email
 * @property string|null $adresse
 * @property string|null $photo_url
 * @property string $fonction_principale
 * @property string $type_contrat
 * @property \Illuminate\Support\Carbon $date_embauche
 * @property string $statut
 * @property string|null $numero_cnps
 * @property string|null $diplome_plus_eleve
 * @property string|null $observations
 * @property bool $is_enseignant
 * @property string|null $specialite
 * @property float $volume_horaire_hebdo
 * @property float|null $taux_horaire_vacation
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Personnel extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'matricule',
        'user_id',
        'etablissement_id',
        'departement_id',
        'nom',
        'prenom',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'telephone',
        'telephone_secondaire',
        'email',
        'adresse',
        'photo_url',
        'fonction_principale',
        'type_contrat',
        'date_embauche',
        'statut',
        'numero_cnps',
        'diplome_plus_eleve',
        'observations',
        'is_enseignant',
        'specialite',
        'volume_horaire_hebdo',
        'taux_horaire_vacation',
    ];

    /**
     * Les attributs typés nativement.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_naissance' => 'date',
        'date_embauche' => 'date',
        'is_enseignant' => 'boolean',
        'volume_horaire_hebdo' => 'decimal:2',
        'taux_horaire_vacation' => 'decimal:2',
    ];

    /**
     * Attributs virtuels ajoutés automatiquement aux sérialisations JSON / Inertia.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'nom_complet',
    ];

    /**
     * Accessor calculant le nom complet du membre du personnel.
     *
     * @return string
     */
    public function getNomCompletAttribute(): string
    {
        return trim($this->nom . ' ' . ($this->prenom ?? ''));
    }

    /**
     * Compte utilisateur lié dans la table `users` (authentification, permissions).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Établissement / campus principal auquel est rattaché l'employé.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    /**
     * Département pédagogique de rattachement (pour les enseignants).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    /**
     * Génère automatiquement un matricule séquentiel unique pour un nouveau personnel.
     * Format : PERS-AAAA-XXXX (ex: PERS-2026-0001).
     *
     * @param int|null $annee Année civile de référence (par défaut l'année en cours).
     * @return string Le matricule généré.
     */
    public static function generateMatricule(?int $annee = null): string
    {
        $year = $annee ?? (int) date('Y');
        $prefix = "PERS-{$year}-";

        // Récupère le dernier numéro séquentiel attribué pour cette année
        $last = static::where('matricule', 'LIKE', "{$prefix}%")
            ->orderBy('matricule', 'desc')
            ->value('matricule');

        $sequence = 1;
        if ($last) {
            $numPart = str_replace($prefix, '', $last);
            if (is_numeric($numPart)) {
                $sequence = (int) $numPart + 1;
            }
        }

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Attributions de cours dispensés par cet enseignant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attribution::class, 'personnel_id');
    }

    /**
     * Classes où cet enseignant intervient.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function classesEnseignees(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'attributions', 'personnel_id', 'classe_id')
            ->withPivot(['annee_scolaire_id', 'matiere_id', 'volume_horaire_reel', 'is_titulaire'])
            ->withTimestamps();
    }

    /**
     * Scope Eloquent pour filtrer les membres du personnel ayant un statut actif.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActif(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('statut', ['Actif', 'actif']);
    }
}


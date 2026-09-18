<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modèle représentant une Inscription Scolaire.
 * Lie un élève de la Base Élève Globale à une classe spécifique
 * pour une année scolaire donnée avec son statut (Gesco 5.3).
 *
 * @property int $id
 * @property int $eleve_id Clé étrangère vers eleves
 * @property int $classe_id Clé étrangère vers classes
 * @property int|null $etablissement_id Clé étrangère directe vers etablissements (cloisonnement multi-campus)
 * @property int $annee_scolaire_id Clé étrangère vers annees_scolaires
 * @property string $type_inscription 'nouveau' ou 'reinscription'
 * @property bool $is_redoublant Indique si l'élève redouble sa classe
 * @property string $statut 'en_attente', 'validee', 'suspendue', 'exclu', 'transfere'
 * @property Carbon $date_inscription Date de validation de l'inscription
 * @property string|null $motif_sortie Raison de départ ou transfert
 * @property string|null $etablissement_provenance Établissement précédent en cas d'admission extérieure
 * @property string|null $observations Remarques administratives
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Eleve $eleve
 * @property-read Classe $classe
 * @property-read Etablissement|null $etablissement
 * @property-read AnneeScolaire $anneeScolaire
 */
class Inscription extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'eleve_id',
        'classe_id',
        'etablissement_id',
        'annee_scolaire_id',
        'type_inscription',
        'is_redoublant',
        'statut',
        'date_inscription',
        'motif_sortie',
        'etablissement_provenance',
        'observations',
    ];

    /**
     * Les attributs convertis en types natifs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'etablissement_id' => 'integer',
        'is_redoublant' => 'boolean',
        'date_inscription' => 'date',
    ];

    /**
     * Initialisation automatique des hooks de cycle de vie du modèle Inscription.
     * Assure la résolution automatique et la cohérence de `etablissement_id`
     * à partir de la classe d'affectation lors de chaque création ou sauvegarde.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (Inscription $inscription): void {
            if (empty($inscription->etablissement_id) && !empty($inscription->classe_id)) {
                $classe = $inscription->classe ?? Classe::find($inscription->classe_id);
                if ($classe) {
                    $inscription->etablissement_id = $classe->etablissement_id;
                }
            }
        });
    }

    /**
     * Relation vers l'élève rattaché.
     *
     * @return BelongsTo
     */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class, 'eleve_id');
    }

    /**
     * Relation vers la classe d'affectation.
     *
     * @return BelongsTo
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    /**
     * Relation directe vers l'établissement scolaire d'inscription.
     *
     * @return BelongsTo
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class, 'etablissement_id');
    }

    /**
     * Relation vers l'année scolaire de rattachement.
     *
     * @return BelongsTo
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    /**
     * Scope pour filtrer les inscriptions validées (officielles).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeValidee(Builder $query): Builder
    {
        return $query->where('statut', 'validee');
    }

    /**
     * Scope pour filtrer les inscriptions en attente.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Scope pour filtrer par année scolaire.
     *
     * @param Builder $query
     * @param int $anneeId
     * @return Builder
     */
    public function scopeForAnnee(Builder $query, int $anneeId): Builder
    {
        return $query->where('annee_scolaire_id', $anneeId);
    }

    /**
     * Scope pour filtrer par classe.
     *
     * @param Builder $query
     * @param int $classeId
     * @return Builder
     */
    public function scopeForClasse(Builder $query, int $classeId): Builder
    {
        return $query->where('classe_id', $classeId);
    }

    /**
     * Scope pour filtrer les inscriptions appartenant à un établissement scolaire spécifique.
     * Si l'identifiant est nul, la requête n'est pas contrainte (ex: Super Admin en vue consolidée).
     *
     * @param Builder $query Requête Eloquent en cours de construction.
     * @param int|null $etablissementId Identifiant de l'établissement ciblé.
     * @return Builder Requête filtrée par établissement.
     */
    public function scopeForEtablissement(Builder $query, ?int $etablissementId): Builder
    {
        if ($etablissementId) {
            return $query->where('etablissement_id', $etablissementId);
        }

        return $query;
    }

    /**
     * Relation vers toutes les notes obtenues par l'élève dans cette inscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Note>
     */
    public function notes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Note::class, 'inscription_id');
    }

    /**
     * Relation vers les synthèses disciplinaires trimestrielles du bulletin.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<DisciplineBulletin>
     */
    public function disciplinesBulletins(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DisciplineBulletin::class, 'inscription_id');
    }

    /**
     * Relation vers le compte financier scolaire de l'inscription (Gesco V1.1 - Module 11).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<CompteFinancierEleve>
     */
    public function compteFinancier(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CompteFinancierEleve::class, 'inscription_id');
    }

    /**
     * Relation vers l'historique des versements de caisse effectués pour cette inscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<VersementCaisse>
     */
    public function versements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VersementCaisse::class, 'inscription_id');
    }

    /**
     * Relation vers les délibérations et décisions du conseil de classe pour cette inscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<DeliberationConseil>
     */
    public function deliberations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeliberationConseil::class, 'inscription_id');
    }
}

<?php

namespace App\Models;

use App\Concerns\BelongsToEtablissement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle ArchiveAdministrative - Centre d'Archivage Administratif (Aurore §3.27.8 & §3.28.3).
 * 
 * Assure la traçabilité des départs d'élèves et de personnels (motif officiel, destination,
 * date d'effet, pièces justificatives) avec conservation intégrale des dossiers historiques
 * et possibilité de réintégration / restauration en cas d'erreur ou retour.
 *
 * @property int $id
 * @property int $etablissement_id
 * @property int|null $annee_scolaire_id
 * @property string $type_entite (eleve, personnel)
 * @property int|null $eleve_id
 * @property int|null $personnel_id
 * @property string $matricule
 * @property string $nom_complet
 * @property string|null $derniere_classe_ou_fonction
 * @property string $motif
 * @property \Illuminate\Support\Carbon $date_archivage
 * @property string|null $destination
 * @property string|null $observations
 * @property string $statut (archive, restaure)
 * @property int|null $archive_par_id
 * @property \Illuminate\Support\Carbon|null $date_restauration
 * @property int|null $restaure_par_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ArchiveAdministrative extends Model
{
    use HasFactory, BelongsToEtablissement;

    /**
     * Nom de la table associée.
     *
     * @var string
     */
    protected $table = 'archives_administratives';

    /**
     * Les attributs assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'etablissement_id',
        'annee_scolaire_id',
        'type_entite',
        'eleve_id',
        'personnel_id',
        'matricule',
        'nom_complet',
        'derniere_classe_ou_fonction',
        'motif',
        'date_archivage',
        'destination',
        'observations',
        'statut',
        'archive_par_id',
        'date_restauration',
        'restaure_par_id',
    ];

    /**
     * Les attributs typés nativement.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_archivage' => 'date',
        'date_restauration' => 'datetime',
    ];

    /**
     * Relation avec l'élève concerné (si type_entite == eleve).
     *
     * @return BelongsTo
     */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class, 'eleve_id');
    }

    /**
     * Relation avec le personnel concerné (si type_entite == personnel).
     *
     * @return BelongsTo
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    /**
     * Relation avec l'année scolaire de l'archivage.
     *
     * @return BelongsTo
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    /**
     * Utilisateur ayant procédé à la mise en archive.
     *
     * @return BelongsTo
     */
    public function archivePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archive_par_id');
    }

    /**
     * Utilisateur ayant procédé à la restauration.
     *
     * @return BelongsTo
     */
    public function restaurePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restaure_par_id');
    }

    /**
     * Scope pour filtrer les archives actives (non restaurées).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActives(Builder $query): Builder
    {
        return $query->where('statut', 'archive');
    }

    /**
     * Scope pour filtrer les archives d'élèves.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeEleves(Builder $query): Builder
    {
        return $query->where('type_entite', 'eleve');
    }

    /**
     * Scope pour filtrer les archives de personnel.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePersonnels(Builder $query): Builder
    {
        return $query->where('type_entite', 'personnel');
    }

    /**
     * Libellé humain du motif d'archivage.
     *
     * @return string
     */
    public function getMotifLibelleAttribute(): string
    {
        return match ($this->motif) {
            'diplome' => 'Fin de cycle / Diplômé',
            'transfert_radiation' => 'Radiation / Transfert externe',
            'exclusion_definitive' => 'Exclusion définitive',
            'abandon' => 'Abandon de scolarité',
            'demission' => 'Démission volontaire',
            'retraite' => 'Départ à la retraite',
            'fin_contrat' => 'Fin de contrat / Vacation',
            'licenciement' => 'Licenciement',
            'mutation' => 'Mutation / Affectation État',
            'deces' => 'Décès',
            default => 'Autre motif',
        };
    }
}
